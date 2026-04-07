<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Controller;

use Doctrine\Common\Collections\Collection;
use Mautic\CoreBundle\Controller\FormController;
use Mautic\CoreBundle\Factory\PageHelperFactoryInterface;
use Mautic\CoreBundle\Form\Type\DateRangeType;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\CoreBundle\Model\AuditLogModel;
use Mautic\LeadBundle\Controller\EntityContactsTrait;
use Mautic\WhatsAppBundle\Entity\WhatsAppMessage;
use Mautic\WhatsAppBundle\Entity\WhatsAppTemplateRepository;
use Mautic\WhatsAppBundle\Model\WhatsAppModel;
use Mautic\WhatsAppBundle\Service\TemplateSyncService;
use Mautic\WhatsAppBundle\WhatsApp\TransportChain;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WhatsAppController extends FormController
{
    use EntityContactsTrait;

    /**
     * @param int $page
     *
     * @return JsonResponse|Response
     */
    public function indexAction(Request $request, TransportChain $transportChain, $page = 1)
    {
        /** @var WhatsAppModel $model */
        $model = $this->getModel('whatsapp');

        // set some permissions
        $permissions = $this->security->isGranted(
            [
                'whatsapp:messages:viewown',
                'whatsapp:messages:viewother',
                'whatsapp:messages:create',
                'whatsapp:messages:editown',
                'whatsapp:messages:editother',
                'whatsapp:messages:deleteown',
                'whatsapp:messages:deleteother',
                'whatsapp:messages:publishown',
                'whatsapp:messages:publishother',
            ],
            'RETURN_ARRAY'
        );

        if (!$permissions['whatsapp:messages:viewown'] && !$permissions['whatsapp:messages:viewother']) {
            return $this->accessDenied();
        }

        $this->setListFilters();

        $session = $request->getSession();

        // set limits
        $limit = $session->get('mautic.whatsapp.limit', $this->coreParametersHelper->get('default_pagelimit'));
        $start = (1 === $page) ? 0 : (($page - 1) * $limit);
        if ($start < 0) {
            $start = 0;
        }

        $search = $request->get('search', $session->get('mautic.whatsapp.filter', ''));
        $session->set('mautic.whatsapp.filter', $search);

        $filter = ['string' => $search];

        if (!$permissions['whatsapp:messages:viewother']) {
            $filter['force'][] =
                [
                    'column' => 'e.createdBy',
                    'expr'   => 'eq',
                    'value'  => $this->user->getId(),
                ];
        }

        $orderBy    = $session->get('mautic.whatsapp.orderby', 'e.name');
        $orderByDir = $session->get('mautic.whatsapp.orderbydir', $this->getDefaultOrderDirection());

        $messages = $model->getEntities([
            'start'      => $start,
            'limit'      => $limit,
            'filter'     => $filter,
            'orderBy'    => $orderBy,
            'orderByDir' => $orderByDir,
        ]);

        $count = count($messages);
        if ($count && $count < ($start + 1)) {
            // the number of entities are now less than the current page so redirect to the last page
            if (1 === $count) {
                $lastPage = 1;
            } else {
                $lastPage = (floor($count / $limit)) ?: 1;
            }

            $session->set('mautic.whatsapp.page', $lastPage);
            $returnUrl = $this->generateUrl('mautic_whatsapp_index', ['page' => $lastPage]);

            return $this->postActionRedirect([
                'returnUrl'       => $returnUrl,
                'viewParameters'  => ['page' => $lastPage],
                'contentTemplate' => 'Mautic\WhatsAppBundle\Controller\WhatsAppController::indexAction',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_whatsapp_index',
                    'mauticContent' => 'whatsapp',
                ],
            ]);
        }
        $session->set('mautic.whatsapp.page', $page);

        return $this->delegateView([
            'viewParameters' => [
                'searchValue' => $search,
                'items'       => $messages,
                'totalItems'  => $count,
                'page'        => $page,
                'limit'       => $limit,
                'tmpl'        => $request->get('tmpl', 'index'),
                'permissions' => $permissions,
                'model'       => $model,
                'security'    => $this->security,
                'configured'  => count($transportChain->getEnabledTransports()) > 0,
            ],
            'contentTemplate' => '@MauticWhatsApp/WhatsApp/list.html.twig',
            'passthroughVars' => [
                'activeLink'    => '#mautic_whatsapp_index',
                'mauticContent' => 'whatsapp',
                'route'         => $this->generateUrl('mautic_whatsapp_index', ['page' => $page]),
            ],
        ]);
    }

    /**
     * Loads a specific form into the detailed panel.
     *
     * @return JsonResponse|Response
     */
    public function viewAction(Request $request, $objectId)
    {
        /** @var WhatsAppModel $model */
        $model    = $this->getModel('whatsapp');
        $security = $this->security;

        /** @var WhatsAppMessage $message */
        $message = $model->getEntity($objectId);
        // set the page we came from
        $page = $request->getSession()->get('mautic.whatsapp.page', 1);

        if (null === $message) {
            // set the return URL
            $returnUrl = $this->generateUrl('mautic_whatsapp_index', ['page' => $page]);

            return $this->postActionRedirect([
                'returnUrl'       => $returnUrl,
                'viewParameters'  => ['page' => $page],
                'contentTemplate' => 'Mautic\WhatsAppBundle\Controller\WhatsAppController::indexAction',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_whatsapp_index',
                    'mauticContent' => 'whatsapp',
                ],
                'flashes' => [
                    [
                        'type'    => 'error',
                        'msg'     => 'mautic.whatsapp.error.notfound',
                        'msgVars' => ['%id%' => $objectId],
                    ],
                ],
            ]);
        } elseif (!$this->security->hasEntityAccess(
            'whatsapp:messages:viewown',
            'whatsapp:messages:viewother',
            $message->getCreatedBy()
        )
        ) {
            return $this->accessDenied();
        }

        // Audit Log
        $auditLogModel = $this->getModel('core.auditlog');
        \assert($auditLogModel instanceof AuditLogModel);
        $logs = $auditLogModel->getLogForObject('whatsapp', $message->getId(), $message->getDateAdded());

        // Init the date range filter form
        $dateRangeValues = $request->query->all()['daterange'] ?? $request->request->all()['daterange'] ?? [];
        $action          = $this->generateUrl('mautic_whatsapp_action', ['objectAction' => 'view', 'objectId' => $objectId]);
        $dateRangeForm   = $this->formFactory->create(DateRangeType::class, $dateRangeValues, ['action' => $action]);
        $entityViews     = $model->getHitsLineChartData(
            null,
            new \DateTime($dateRangeForm->get('date_from')->getData()),
            new \DateTime($dateRangeForm->get('date_to')->getData()),
            null,
            ['whatsapp_message_id' => $message->getId()]
        );

        return $this->delegateView([
            'returnUrl'      => $this->generateUrl('mautic_whatsapp_action', ['objectAction' => 'view', 'objectId' => $message->getId()]),
            'viewParameters' => [
                'message'     => $message,
                'logs'        => $logs,
                'isEmbedded'  => $request->get('isEmbedded') ?: false,
                'permissions' => $security->isGranted([
                    'whatsapp:messages:viewown',
                    'whatsapp:messages:viewother',
                    'whatsapp:messages:create',
                    'whatsapp:messages:editown',
                    'whatsapp:messages:editother',
                    'whatsapp:messages:deleteown',
                    'whatsapp:messages:deleteother',
                    'whatsapp:messages:publishown',
                    'whatsapp:messages:publishother',
                ], 'RETURN_ARRAY'),
                'security'    => $security,
                'entityViews' => $entityViews,
                'contacts'    => $this->forward(
                    'Mautic\WhatsAppBundle\Controller\WhatsAppController::contactsAction',
                    [
                        'objectId'   => $message->getId(),
                        'page'       => $request->getSession()->get('mautic.whatsapp.contact.page', 1),
                        'ignoreAjax' => true,
                    ]
                )->getContent(),
                'dateRangeForm' => $dateRangeForm->createView(),
            ],
            'contentTemplate' => '@MauticWhatsApp/WhatsApp/details.html.twig',
            'passthroughVars' => [
                'activeLink'    => '#mautic_whatsapp_index',
                'mauticContent' => 'whatsapp',
            ],
        ]);
    }

    /**
     * Generates new form and processes post data.
     *
     * @param WhatsAppMessage $entity
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function newAction(Request $request, $entity = null)
    {
        /** @var WhatsAppModel $model */
        $model = $this->getModel('whatsapp');

        if (!$entity instanceof WhatsAppMessage) {
            /** @var WhatsAppMessage $entity */
            $entity = $model->getEntity();
        }

        $method  = $request->getMethod();
        $session = $request->getSession();

        if (!$this->security->isGranted('whatsapp:messages:create')) {
            return $this->accessDenied();
        }

        // set the page we came from
        $page         = $session->get('mautic.whatsapp.page', 1);
        $action       = $this->generateUrl('mautic_whatsapp_action', ['objectAction' => 'new']);
        $whatsapp     = $request->request->all()['whatsapp'] ?? [];
        $updateSelect = 'POST' === $method
            ? ($whatsapp['updateSelect'] ?? false)
            : $request->get('updateSelect', false);

        if ($updateSelect) {
            $entity->setMessageType('template');
        }

        // create the form
        $form = $model->createForm($entity, $this->formFactory, $action, ['update_select' => $updateSelect]);

        // Check for a submitted form and process it
        if ('POST' == $method) {
            $valid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {
                // Auto-fill templateName and templateLanguage from selected template
                $templateId = $form->has('templateId') ? $form->get('templateId')->getData() : null;
                if ($templateId && 'template' === $entity->getMessageType() && empty($entity->getTemplateName())) {
                    $whatsAppTemplate = $this->getDoctrine()->getManager()
                        ->getRepository(\Mautic\WhatsAppBundle\Entity\WhatsAppTemplate::class)
                        ->find($templateId);
                    if ($whatsAppTemplate) {
                        $entity->setTemplateName($whatsAppTemplate->getName());
                        $entity->setTemplateLanguage($whatsAppTemplate->getLanguage());
                        $entity->setTemplateComponents($whatsAppTemplate->getComponents() ?? []);
                    }
                }
                if ($valid = $this->isFormValid($form)) {
                    // form is valid so process the data
                    $model->saveEntity($entity);

                    $this->addFlashMessage(
                        'mautic.core.notice.created',
                        [
                            '%name%'      => $entity->getName(),
                            '%menu_link%' => 'mautic_whatsapp_index',
                            '%url%'       => $this->generateUrl(
                                'mautic_whatsapp_action',
                                [
                                    'objectAction' => 'edit',
                                    'objectId'     => $entity->getId(),
                                ]
                            ),
                        ]
                    );

                    if ($this->getFormButton($form, ['buttons', 'save'])->isClicked()) {
                        $viewParameters = [
                            'objectAction' => 'view',
                            'objectId'     => $entity->getId(),
                        ];
                        $returnUrl = $this->generateUrl('mautic_whatsapp_action', $viewParameters);
                        $template  = 'Mautic\WhatsAppBundle\Controller\WhatsAppController::viewAction';
                    } else {
                        // return edit view so that all the session stuff is loaded
                        return $this->editAction($request, $entity->getId(), true);
                    }
                }
            } else {
                $viewParameters = ['page' => $page];
                $returnUrl      = $this->generateUrl('mautic_whatsapp_index', $viewParameters);
                $template       = 'Mautic\WhatsAppBundle\Controller\WhatsAppController::indexAction';
                // clear any modified content
                $session->remove('mautic.whatsapp.'.$entity->getId().'.content');
            }

            $passthrough = [
                'activeLink'    => 'mautic_whatsapp_index',
                'mauticContent' => 'whatsapp',
            ];

            // Check to see if this is a popup
            if (isset($form['updateSelect'])) {
                $template    = false;
                $passthrough = array_merge(
                    $passthrough,
                    [
                        'updateSelect' => $form['updateSelect']->getData(),
                        'id'           => $entity->getId(),
                        'name'         => $entity->getName(),
                        'group'        => $entity->getLang(),
                    ]
                );
            }

            if ($cancelled || ($valid && $this->getFormButton($form, ['buttons', 'save'])->isClicked())) {
                return $this->postActionRedirect(
                    [
                        'returnUrl'       => $returnUrl,
                        'viewParameters'  => $viewParameters,
                        'contentTemplate' => $template,
                        'passthroughVars' => $passthrough,
                    ]
                );
            }
        }

        return $this->delegateView(
            [
                'viewParameters' => [
                    'form'    => $form->createView(),
                    'message' => $entity,
                ],
                'contentTemplate' => '@MauticWhatsApp/WhatsApp/form.html.twig',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_whatsapp_index',
                    'mauticContent' => 'whatsapp',
                    'updateSelect'  => InputHelper::clean($request->query->get('updateSelect')),
                    'route'         => $this->generateUrl(
                        'mautic_whatsapp_action',
                        [
                            'objectAction' => 'new',
                        ]
                    ),
                ],
            ]
        );
    }

    /**
     * @param bool $ignorePost
     * @param bool $forceTypeSelection
     *
     * @return array|JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function editAction(Request $request, $objectId, $ignorePost = false, $forceTypeSelection = false)
    {
        /** @var WhatsAppModel $model */
        $model   = $this->getModel('whatsapp');
        $method  = $request->getMethod();
        $entity  = $model->getEntity($objectId);
        $session = $request->getSession();
        $page    = $session->get('mautic.whatsapp.page', 1);

        // set the return URL
        $returnUrl = $this->generateUrl('mautic_whatsapp_index', ['page' => $page]);

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'Mautic\WhatsAppBundle\Controller\WhatsAppController::indexAction',
            'passthroughVars' => [
                'activeLink'    => 'mautic_whatsapp_index',
                'mauticContent' => 'whatsapp',
            ],
        ];

        // not found
        if (null === $entity) {
            return $this->postActionRedirect(
                array_merge(
                    $postActionVars,
                    [
                        'flashes' => [
                            [
                                'type'    => 'error',
                                'msg'     => 'mautic.whatsapp.error.notfound',
                                'msgVars' => ['%id%' => $objectId],
                            ],
                        ],
                    ]
                )
            );
        } elseif (!$this->security->hasEntityAccess(
            'whatsapp:messages:viewown',
            'whatsapp:messages:viewother',
            $entity->getCreatedBy()
        )
        ) {
            return $this->accessDenied();
        } elseif ($model->isLocked($entity)) {
            // deny access if the entity is locked
            return $this->isLocked($postActionVars, $entity, 'whatsapp.message');
        }

        // Create the form
        $action       = $this->generateUrl('mautic_whatsapp_action', ['objectAction' => 'edit', 'objectId' => $objectId]);
        $whatsapp     = $request->request->all()['whatsapp'] ?? [];
        $updateSelect = 'POST' === $method
            ? ($whatsapp['updateSelect'] ?? false)
            : $request->get('updateSelect', false);

        $form = $model->createForm($entity, $this->formFactory, $action, ['update_select' => $updateSelect]);

        // Check for a submitted form and process it
        if (!$ignorePost && 'POST' == $method) {
            $valid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {
                if ($valid = $this->isFormValid($form)) {
                    // form is valid so process the data
                    $model->saveEntity($entity, $this->getFormButton($form, ['buttons', 'save'])->isClicked());

                    $this->addFlashMessage(
                        'mautic.core.notice.updated',
                        [
                            '%name%'      => $entity->getName(),
                            '%menu_link%' => 'mautic_whatsapp_index',
                            '%url%'       => $this->generateUrl(
                                'mautic_whatsapp_action',
                                [
                                    'objectAction' => 'edit',
                                    'objectId'     => $entity->getId(),
                                ]
                            ),
                        ],
                        'warning'
                    );
                }
            } else {
                // clear any modified content
                $session->remove('mautic.whatsapp.'.$objectId.'.content');
                // unlock the entity
                $model->unlockEntity($entity);
            }

            $passthrough = [
                'activeLink'    => 'mautic_whatsapp_index',
                'mauticContent' => 'whatsapp',
            ];

            $template = 'Mautic\WhatsAppBundle\Controller\WhatsAppController::viewAction';

            // Check to see if this is a popup
            if (isset($form['updateSelect'])) {
                $template    = false;
                $passthrough = array_merge(
                    $passthrough,
                    [
                        'updateSelect' => $form['updateSelect']->getData(),
                        'id'           => $entity->getId(),
                        'name'         => $entity->getName(),
                        'group'        => $entity->getLang(),
                    ]
                );
            }

            if ($cancelled || ($valid && $this->getFormButton($form, ['buttons', 'save'])->isClicked())) {
                $viewParameters = [
                    'objectAction' => 'view',
                    'objectId'     => $entity->getId(),
                ];

                return $this->postActionRedirect(
                    array_merge(
                        $postActionVars,
                        [
                            'returnUrl'       => $this->generateUrl('mautic_whatsapp_action', $viewParameters),
                            'viewParameters'  => $viewParameters,
                            'contentTemplate' => $template,
                            'passthroughVars' => $passthrough,
                        ]
                    )
                );
            }
        } else {
            // lock the entity
            $model->lockEntity($entity);
        }

        return $this->delegateView(
            [
                'viewParameters' => [
                    'form'               => $form->createView(),
                    'message'            => $entity,
                    'forceTypeSelection' => $forceTypeSelection,
                ],
                'contentTemplate' => '@MauticWhatsApp/WhatsApp/form.html.twig',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_whatsapp_index',
                    'mauticContent' => 'whatsapp',
                    'updateSelect'  => InputHelper::clean($request->query->get('updateSelect')),
                    'route'         => $this->generateUrl(
                        'mautic_whatsapp_action',
                        [
                            'objectAction' => 'edit',
                            'objectId'     => $entity->getId(),
                        ]
                    ),
                ],
            ]
        );
    }

    /**
     * Clone an entity.
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function cloneAction(Request $request, $objectId)
    {
        $model  = $this->getModel('whatsapp');
        $entity = $model->getEntity($objectId);

        if (null != $entity) {
            if (!$this->security->isGranted('whatsapp:messages:create')
                || !$this->security->hasEntityAccess(
                    'whatsapp:messages:viewown',
                    'whatsapp:messages:viewother',
                    $entity->getCreatedBy()
                )
            ) {
                return $this->accessDenied();
            }

            $entity = clone $entity;
        }

        return $this->newAction($request, $entity);
    }

    /**
     * Deletes the entity.
     *
     * @return Response
     */
    public function deleteAction(Request $request, $objectId)
    {
        $page      = $request->getSession()->get('mautic.whatsapp.page', 1);
        $returnUrl = $this->generateUrl('mautic_whatsapp_index', ['page' => $page]);
        $flashes   = [];

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'Mautic\WhatsAppBundle\Controller\WhatsAppController::indexAction',
            'passthroughVars' => [
                'activeLink'    => 'mautic_whatsapp_index',
                'mauticContent' => 'whatsapp',
            ],
        ];

        if (Request::METHOD_POST === $request->getMethod()) {
            $model = $this->getModel('whatsapp');
            \assert($model instanceof WhatsAppModel);
            $entity = $model->getEntity($objectId);

            if (null === $entity) {
                $flashes[] = [
                    'type'    => 'error',
                    'msg'     => 'mautic.whatsapp.error.notfound',
                    'msgVars' => ['%id%' => $objectId],
                ];
            } elseif (!$this->security->hasEntityAccess(
                'whatsapp:messages:deleteown',
                'whatsapp:messages:deleteother',
                $entity->getCreatedBy()
            )
            ) {
                return $this->accessDenied();
            } elseif ($model->isLocked($entity)) {
                return $this->isLocked($postActionVars, $entity, 'whatsapp.message');
            }

            $model->deleteEntity($entity);

            $flashes[] = [
                'type'    => 'notice',
                'msg'     => 'mautic.core.notice.deleted',
                'msgVars' => [
                    '%name%' => $entity->getName(),
                    '%id%'   => $objectId,
                ],
            ];
        } // else don't do anything

        return $this->postActionRedirect(
            array_merge(
                $postActionVars,
                ['flashes' => $flashes]
            )
        );
    }

    /**
     * Deletes a group of entities.
     */
    public function batchDeleteAction(Request $request): Response
    {
        $page      = $request->getSession()->get('mautic.whatsapp.page', 1);
        $returnUrl = $this->generateUrl('mautic_whatsapp_index', ['page' => $page]);
        $flashes   = [];

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'Mautic\WhatsAppBundle\Controller\WhatsAppController::indexAction',
            'passthroughVars' => [
                'activeLink'    => '#mautic_whatsapp_index',
                'mauticContent' => 'whatsapp',
            ],
        ];

        if (Request::METHOD_POST == $request->getMethod()) {
            $model = $this->getModel('whatsapp');
            \assert($model instanceof WhatsAppModel);
            $ids = json_decode($request->query->get('ids', '{}'));

            $deleteIds = [];

            // Loop over the IDs to perform access checks pre-delete
            foreach ($ids as $objectId) {
                $entity = $model->getEntity($objectId);

                if (null === $entity) {
                    $flashes[] = [
                        'type'    => 'error',
                        'msg'     => 'mautic.whatsapp.error.notfound',
                        'msgVars' => ['%id%' => $objectId],
                    ];
                } elseif (!$this->security->hasEntityAccess(
                    'whatsapp:messages:viewown',
                    'whatsapp:messages:viewother',
                    $entity->getCreatedBy()
                )
                ) {
                    $flashes[] = $this->accessDenied(true);
                } elseif ($model->isLocked($entity)) {
                    $flashes[] = $this->isLocked($postActionVars, $entity, 'whatsapp.message', true);
                } else {
                    $deleteIds[] = $objectId;
                }
            }

            // Delete everything we are able to
            if (!empty($deleteIds)) {
                $entities = $model->deleteEntities($deleteIds);

                $flashes[] = [
                    'type'    => 'notice',
                    'msg'     => 'mautic.whatsapp.notice.batch_deleted',
                    'msgVars' => [
                        '%count%' => count($entities),
                    ],
                ];
            }
        } // else don't do anything

        return $this->postActionRedirect(
            array_merge(
                $postActionVars,
                ['flashes' => $flashes]
            )
        );
    }

    /**
     * @return JsonResponse|Response
     */
    public function previewAction($objectId)
    {
        /** @var WhatsAppModel $model */
        $model    = $this->getModel('whatsapp');
        $message  = $model->getEntity($objectId);
        $security = $this->security;

        if (null !== $message && $security->hasEntityAccess('whatsapp:messages:viewown', 'whatsapp:messages:viewother')) {
            return $this->delegateView([
                'viewParameters' => [
                    'message' => $message,
                ],
                'contentTemplate' => '@MauticWhatsApp/WhatsApp/preview.html.twig',
            ]);
        }

        return new Response('', Response::HTTP_NOT_FOUND);
    }

    /**
     * @param int $page
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function contactsAction(
        Request $request,
        PageHelperFactoryInterface $pageHelperFactory,
        $objectId,
        $page = 1,
    ) {
        return $this->generateContactsGrid(
            $request,
            $pageHelperFactory,
            $objectId,
            $page,
            'whatsapp:messages:view',
            'whatsapp',
            'whatsapp_message_stats',
            'whatsapp_message',
            'whatsapp_message_id'
        );
    }

    /**
     * Display the list of synced WhatsApp templates.
     *
     * @return JsonResponse|Response
     */
    public function templatesAction(WhatsAppTemplateRepository $templateRepository, int $page = 1): JsonResponse|Response
    {
        if (!$this->security->isGranted('whatsapp:messages:viewown') && !$this->security->isGranted('whatsapp:messages:viewother')) {
            return $this->accessDenied();
        }

        $templates = $templateRepository->findBy([], ['name' => 'ASC']);

        return $this->delegateView([
            'viewParameters' => [
                'templates' => $templates,
                'page'      => $page,
            ],
            'contentTemplate' => '@MauticWhatsApp/WhatsApp/templates.html.twig',
            'passthroughVars' => [
                'activeLink'    => '#mautic_whatsapp_templates',
                'mauticContent' => 'whatsapp_templates',
                'route'         => $this->generateUrl('mautic_whatsapp_templates', ['page' => $page]),
            ],
        ]);
    }

    /**
     * Sync WhatsApp templates from Meta and redirect back to templates page.
     */
    public function syncTemplatesAction(TemplateSyncService $syncService): Response
    {
        if (!$this->security->isGranted('whatsapp:messages:create')) {
            return $this->accessDenied();
        }

        try {
            $results = $syncService->syncTemplates();

            $this->addFlashMessage(
                'mautic.whatsapp.command.sync_templates.synced',
                ['%count%' => $results['synced']]
            );
        } catch (\RuntimeException $e) {
            $this->addFlashMessage(
                'mautic.whatsapp.command.sync_templates.error',
                ['%error%' => $e->getMessage()],
                'error'
            );
        }

        return $this->redirectToRoute('mautic_whatsapp_templates');
    }

    protected function getModelName(): string
    {
        return 'whatsapp';
    }

    protected function getDefaultOrderDirection(): string
    {
        return 'DESC';
    }
}
