<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Controller\Api;

use Doctrine\Persistence\ManagerRegistry;
use Mautic\ApiBundle\Controller\CommonApiController;
use Mautic\ApiBundle\Helper\EntityResultHelper;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\CoreBundle\Helper\AppVersion;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Controller\LeadAccessTrait;
use Mautic\WhatsAppBundle\Entity\WhatsAppMessage;
use Mautic\WhatsAppBundle\Model\WhatsAppModel;
use Mautic\WhatsAppBundle\WhatsApp\TransportChain;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 * @extends CommonApiController<WhatsAppMessage>
 */
class WhatsAppApiController extends CommonApiController
{
    use LeadAccessTrait;

    /**
     * @var WhatsAppModel|null
     */
    protected $model;

    public function __construct(CorePermissions $security, Translator $translator, EntityResultHelper $entityResultHelper, RouterInterface $router, FormFactoryInterface $formFactory, AppVersion $appVersion, RequestStack $requestStack, ManagerRegistry $doctrine, ModelFactory $modelFactory, EventDispatcherInterface $dispatcher, CoreParametersHelper $coreParametersHelper)
    {
        $whatsappModel = $modelFactory->getModel('whatsapp');
        \assert($whatsappModel instanceof WhatsAppModel);

        $this->model           = $whatsappModel;
        $this->entityClass     = WhatsAppMessage::class;
        $this->entityNameOne   = 'whatsapp';
        $this->entityNameMulti = 'whatsapps';

        $this->serializerGroups = [
            'whatsappDetails',
            'categoryList',
            'publishDetails',
            'leadListList',
        ];

        parent::__construct($security, $translator, $entityResultHelper, $router, $formFactory, $appVersion, $requestStack, $doctrine, $modelFactory, $dispatcher, $coreParametersHelper);
    }

    /**
     * Send a WhatsApp message to a specific contact.
     *
     * @return JsonResponse|Response
     */
    public function sendAction(TransportChain $transportChain, LoggerInterface $mauticLogger, $id, $contactId)
    {
        if (!$transportChain->getEnabledTransports()) {
            return new JsonResponse(json_encode(['error' => ['message' => 'WhatsApp transport is disabled.', 'code' => Response::HTTP_EXPECTATION_FAILED]]));
        }

        $message = $this->model->getEntity((int) $id);

        if (is_null($message)) {
            return $this->notFound();
        }

        $contact = $this->checkLeadAccess($contactId, 'edit');

        if ($contact instanceof Response) {
            return $this->accessDenied();
        }

        $mauticLogger->debug("Sending WhatsApp message #{$id} to contact #{$contactId}", ['originator' => 'api']);

        try {
            $response = $this->model->sendWhatsApp($message, $contact, ['channel' => 'api'])[$contact->getId()];
        } catch (\Exception $e) {
            $mauticLogger->error($e->getMessage(), ['error' => (array) $e]);

            return new Response('Internal server error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $success = !empty($response['sent']);

        if (!$success) {
            $mauticLogger->error('Failed to send WhatsApp message.', ['error' => $response['status']]);
        }

        $view = $this->view(
            [
                'success' => $success,
                'status'  => $this->translator->trans($response['status']),
                'result'  => $response,
                'errors'  => $success ? [] : [['message' => $response['status']]],
            ],
            Response::HTTP_OK
        );

        return $this->handleView($view);
    }

    /**
     * Send a WhatsApp template message to a specific contact.
     *
     * @return JsonResponse|Response
     */
    public function sendTemplateAction(Request $request, TransportChain $transportChain, LoggerInterface $mauticLogger, $id, $contactId)
    {
        if (!$transportChain->getEnabledTransports()) {
            return new JsonResponse(json_encode(['error' => ['message' => 'WhatsApp transport is disabled.', 'code' => Response::HTTP_EXPECTATION_FAILED]]));
        }

        $message = $this->model->getEntity((int) $id);

        if (is_null($message)) {
            return $this->notFound();
        }

        if (WhatsAppMessage::MESSAGE_TYPE_TEMPLATE !== $message->getMessageType()) {
            return new JsonResponse(
                ['error' => ['message' => 'This message is not a template type.', 'code' => Response::HTTP_BAD_REQUEST]],
                Response::HTTP_BAD_REQUEST
            );
        }

        $contact = $this->checkLeadAccess($contactId, 'edit');

        if ($contact instanceof Response) {
            return $this->accessDenied();
        }

        $mauticLogger->debug("Sending WhatsApp template #{$id} to contact #{$contactId}", ['originator' => 'api']);

        try {
            $options  = array_merge(['channel' => 'api'], $request->request->all());
            $response = $this->model->sendWhatsApp($message, $contact, $options)[$contact->getId()];
        } catch (\Exception $e) {
            $mauticLogger->error($e->getMessage(), ['error' => (array) $e]);

            return new Response('Internal server error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $success = !empty($response['sent']);

        if (!$success) {
            $mauticLogger->error('Failed to send WhatsApp template message.', ['error' => $response['status']]);
        }

        $view = $this->view(
            [
                'success' => $success,
                'status'  => $this->translator->trans($response['status']),
                'result'  => $response,
                'errors'  => $success ? [] : [['message' => $response['status']]],
            ],
            Response::HTTP_OK
        );

        return $this->handleView($view);
    }
}
