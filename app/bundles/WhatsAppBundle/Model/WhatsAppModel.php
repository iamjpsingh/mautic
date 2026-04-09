<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Model;

use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Mautic\ChannelBundle\Entity\MessageQueue;
use Mautic\ChannelBundle\Model\MessageQueueModel;
use Mautic\CoreBundle\Event\TokenReplacementEvent;
use Mautic\CoreBundle\Helper\CacheStorageHelper;
use Mautic\CoreBundle\Helper\Chart\ChartQuery;
use Mautic\CoreBundle\Helper\Chart\LineChart;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Model\AjaxLookupModelInterface;
use Mautic\CoreBundle\Model\FormModel;
use Mautic\WhatsAppBundle\Entity\WhatsAppTemplate;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Entity\DoNotContact;
use Mautic\LeadBundle\Entity\DoNotContactRepository;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\PageBundle\Model\TrackableModel;
use Mautic\WhatsAppBundle\Entity\WhatsAppMessage;
use Mautic\WhatsAppBundle\Entity\WhatsAppMessageRepository;
use Mautic\WhatsAppBundle\Entity\WhatsAppStat;
use Mautic\WhatsAppBundle\Entity\WhatsAppStatRepository;
use Mautic\WhatsAppBundle\Event\WhatsAppMessageEvent;
use Mautic\WhatsAppBundle\Event\WhatsAppSendEvent;
use Mautic\WhatsAppBundle\Form\Type\WhatsAppType;
use Mautic\WhatsAppBundle\WhatsApp\TransportChain;
use Mautic\WhatsAppBundle\WhatsAppEvents;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @extends FormModel<WhatsAppMessage>
 *
 * @implements AjaxLookupModelInterface<WhatsAppMessage>
 */
class WhatsAppModel extends FormModel implements AjaxLookupModelInterface
{
    public function __construct(
        protected TrackableModel $pageTrackableModel,
        protected LeadModel $leadModel,
        protected MessageQueueModel $messageQueueModel,
        protected TransportChain $transport,
        private CacheStorageHelper $cacheStorageHelper,
        EntityManagerInterface $em,
        CorePermissions $security,
        EventDispatcherInterface $dispatcher,
        UrlGeneratorInterface $router,
        Translator $translator,
        UserHelper $userHelper,
        LoggerInterface $mauticLogger,
        CoreParametersHelper $coreParametersHelper,
    ) {
        parent::__construct($em, $security, $dispatcher, $router, $translator, $userHelper, $mauticLogger, $coreParametersHelper);
    }

    public function getRepository(): WhatsAppMessageRepository
    {
        return $this->em->getRepository(WhatsAppMessage::class);
    }

    public function getStatRepository(): WhatsAppStatRepository
    {
        return $this->em->getRepository(WhatsAppStat::class);
    }

    public function findTemplate(int $templateId): ?WhatsAppTemplate
    {
        return $this->em->getRepository(WhatsAppTemplate::class)->find($templateId);
    }

    public function getConnection(): \Doctrine\DBAL\Connection
    {
        return $this->em->getConnection();
    }

    public function getPermissionBase(): string
    {
        return 'whatsapp:messages';
    }

    public function saveEntity($entity, $unlock = true): void
    {
        parent::saveEntity($entity, $unlock);
    }

    /**
     * @param iterable<WhatsAppMessage> $entities
     */
    public function saveEntities($entities, $unlock = true): void
    {
        $batchSize = 20;
        $i         = 0;

        foreach ($entities as $entity) {
            $isNew = ($entity->getId()) ? false : true;

            $this->setTimestamps($entity, $isNew, $unlock);

            if ($dispatchEvent = $entity instanceof WhatsAppMessage) {
                $event = $this->dispatchEvent('pre_save', $entity, $isNew);
            }

            $this->getRepository()->saveEntity($entity, false);

            if ($dispatchEvent) {
                $this->dispatchEvent('post_save', $entity, $isNew, $event);
            }

            if (0 === ++$i % $batchSize) {
                $this->em->flush();
            }
        }

        $this->em->flush();
    }

    /**
     * @param mixed[] $options
     *
     * @throws MethodNotAllowedHttpException
     */
    public function createForm($entity, FormFactoryInterface $formFactory, $action = null, $options = []): FormInterface
    {
        if (!$entity instanceof WhatsAppMessage) {
            throw new MethodNotAllowedHttpException(['WhatsAppMessage']);
        }

        if (!empty($action)) {
            $options['action'] = $action;
        }

        return $formFactory->create(WhatsAppType::class, $entity, $options);
    }

    public function getEntity($id = null): ?WhatsAppMessage
    {
        if (null === $id) {
            return new WhatsAppMessage();
        }

        return parent::getEntity($id);
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return \Doctrine\ORM\Tools\Pagination\Paginator<WhatsAppMessage>|array<WhatsAppMessage>
     */
    public function getEntities(array $args = [])
    {
        $entities = parent::getEntities($args);

        foreach ($entities as $entity) {
            $pending = $this->cacheStorageHelper->get(sprintf('%s|%s|%s', 'whatsapp', $entity->getId(), 'pending'));

            if (false !== $pending) {
                $entity->setPendingCount($pending);
            }
        }

        return $entities;
    }

    /**
     * Send a WhatsApp message to one or more contacts.
     *
     * Handles all message types: template, session, media, interactive.
     * Checks DNC, frequency rules, resolves contacts, and dispatches events.
     *
     * @param Lead|array<Lead|int> $sendTo
     * @param array<string, mixed> $options
     * @param array<int, Lead>     $leads
     *
     * @return array<int, array<string, mixed>>
     */
    public function sendWhatsApp(WhatsAppMessage $message, Lead|array $sendTo, array $options = [], array &$leads = []): array
    {
        $channel = $options['channel'] ?? null;
        $listId  = $options['listId'] ?? null;

        if ($sendTo instanceof Lead) {
            $sendTo = [$sendTo];
        } elseif (!is_array($sendTo)) {
            $sendTo = [$sendTo];
        }

        $sentCount       = 0;
        $failedCount     = 0;
        $results         = [];
        $contacts        = [];
        $fetchContacts   = [];

        foreach ($sendTo as $lead) {
            if (!$lead instanceof Lead) {
                $fetchContacts[] = $lead;
            } else {
                $contacts[$lead->getId()] = $lead;
                $leads[$lead->getId()]    = $lead;
            }
        }

        if ($fetchContacts) {
            /** @var Lead[] $foundContacts */
            $foundContacts = $this->leadModel->getEntities(
                [
                    'ids' => $fetchContacts,
                ]
            );

            foreach ($foundContacts as $contact) {
                $contacts[$contact->getId()] = $contact;
                $leads[$contact->getId()]    = $contact;
            }
        }

        if (!$message->isPublished()) {
            foreach ($contacts as $leadId => $lead) {
                $results[$leadId] = [
                    'sent'   => false,
                    'status' => 'mautic.whatsapp.campaign.failed.unpublished',
                ];
            }

            return $results;
        }

        $contactIds = array_keys($contacts);

        // Check Do Not Contact for the whatsapp channel
        /** @var DoNotContactRepository $dncRepo */
        $dncRepo = $this->em->getRepository(DoNotContact::class);
        $dnc     = $dncRepo->getChannelList('whatsapp', $contactIds);

        foreach ($dnc as $removeMeId => $removeMeReason) {
            $results[$removeMeId] = [
                'sent'   => false,
                'status' => 'mautic.whatsapp.campaign.failed.not_contactable',
            ];

            unset($contacts[$removeMeId], $contactIds[$removeMeId]);
        }

        if (!empty($contacts)) {
            $messageQueue    = $options['resend_message_queue'] ?? null;
            $campaignEventId = (is_array($channel) && 'campaign.event' === $channel[0] && !empty($channel[1])) ? $channel[1] : null;

            // Process frequency rules
            $queued = $this->messageQueueModel->processFrequencyRules(
                $contacts,
                'whatsapp',
                $message->getId(),
                $campaignEventId,
                3,
                MessageQueue::PRIORITY_NORMAL,
                $messageQueue,
                'whatsapp_message_stats'
            );

            foreach ($queued as $queue) {
                $results[$queue] = [
                    'sent'   => false,
                    'status' => 'mautic.whatsapp.timeline.status.scheduled',
                ];

                unset($contacts[$queue]);
            }

            $stats = [];

            if (count($contacts)) {
                /** @var Lead $lead */
                foreach ($contacts as $lead) {
                    $leadId = $lead->getId();
                    $stat   = $this->createStatEntry($message, $lead, $channel, false, $listId);

                    $leadPhoneNumber = $lead->getLeadPhoneNumber();

                    if (empty($leadPhoneNumber)) {
                        $results[$leadId] = [
                            'sent'   => false,
                            'status' => 'mautic.whatsapp.campaign.failed.missing_number',
                        ];

                        continue;
                    }

                    // Dispatch pre-send event
                    $sendEvent = new WhatsAppSendEvent(
                        $message->getId(),
                        $lead,
                        $message->getMessage() ?? '',
                        $message->getTemplateName(),
                    );
                    $this->dispatcher->dispatch($sendEvent, WhatsAppEvents::WHATSAPP_ON_SEND);

                    // Token replacement for session/text messages
                    $tokenEvent = $this->dispatcher->dispatch(
                        new TokenReplacementEvent(
                            $sendEvent->getContent(),
                            $lead,
                            [
                                'channel' => [
                                    'whatsapp' => $message->getId(),
                                ],
                                'stat' => $stat->getTrackingHash(),
                            ]
                        ),
                        WhatsAppEvents::TOKEN_REPLACEMENT
                    );

                    $sendResult = [
                        'sent'    => false,
                        'type'    => 'mautic.whatsapp.message',
                        'status'  => 'mautic.whatsapp.timeline.status.sent',
                        'id'      => $message->getId(),
                        'name'    => $message->getName(),
                        'content' => $tokenEvent->getContent(),
                    ];

                    // Route to the correct transport method based on message type
                    $metadata = $this->dispatchToTransport($message, $lead, $tokenEvent->getContent());

                    if (true !== $metadata) {
                        $sendResult['status'] = $metadata;
                        $stat->setIsFailed(true);
                        $stat->setStatus(WhatsAppStat::STATUS_FAILED);
                        if (is_string($metadata)) {
                            $stat->addDetail('failed', $metadata);
                        }
                        ++$failedCount;
                    } else {
                        $sendResult['sent'] = true;
                        $stat->setStatus(WhatsAppStat::STATUS_SENT);
                        ++$sentCount;
                    }

                    $stats[]            = $stat;
                    $results[$leadId]   = $sendResult;

                    unset($sendEvent, $tokenEvent, $sendResult, $metadata, $stat);
                }
            }
        }

        if ($sentCount || $failedCount) {
            $this->getRepository()->upCount($message->getId(), 'sent', $sentCount);
            $this->getStatRepository()->saveEntities($stats);

            foreach ($stats as $stat) {
                if (!$stat->isFailed()) {
                    $results[$stat->getLead()->getId()]['statId'] = $stat->getId();
                }

                $this->getRepository()->detachEntity($stat);
            }
        }

        return $results;
    }

    /**
     * Dispatch the message to the transport based on its type.
     */
    private function dispatchToTransport(WhatsAppMessage $message, Lead $lead, string $processedContent): bool|string
    {
        return match ($message->getMessageType()) {
            WhatsAppMessage::MESSAGE_TYPE_TEMPLATE => $this->transport->sendTemplate(
                $lead,
                $message->getTemplateName() ?? '',
                $message->getTemplateLanguage() ?? 'en_US',
                [], // Don't send synced template components — they're definitions, not send parameters
            ),
            WhatsAppMessage::MESSAGE_TYPE_MEDIA => $this->transport->sendMedia(
                $lead,
                $message->getMediaType() ?? 'image',
                $message->getMediaUrl() ?? '',
                ['caption' => $processedContent],
            ),
            WhatsAppMessage::MESSAGE_TYPE_INTERACTIVE => $this->transport->sendInteractive(
                $lead,
                $message->getInteractiveData(),
            ),
            default => $this->transport->sendText($lead, $processedContent), // session / text
        };
    }

    /**
     * Create a stat entry for a sent message.
     *
     * @param array<mixed>|string|null $source
     *
     * @throws \Exception
     */
    public function createStatEntry(
        WhatsAppMessage $message,
        Lead $lead,
        array|string|null $source = null,
        bool $persist = true,
        ?int $listId = null,
    ): WhatsAppStat {
        $stat = new WhatsAppStat();
        $stat->setDateSent(new \DateTime());
        $stat->setLead($lead);
        $stat->setWhatsappMessage($message);
        $stat->setStatus(WhatsAppStat::STATUS_SENT);

        if (null !== $listId) {
            $stat->setList($this->leadModel->getLeadListRepository()->getEntity($listId));
        }

        if (is_array($source)) {
            $stat->setSourceId($source[1]);
            $source = $source[0];
        }

        $stat->setSource($source);
        $stat->setTrackingHash(str_replace('.', '', uniqid('', true)));

        if ($persist) {
            $this->getStatRepository()->saveEntity($stat);
        }

        return $stat;
    }

    /**
     * @throws MethodNotAllowedHttpException
     */
    protected function dispatchEvent($action, &$entity, $isNew = false, ?Event $event = null): ?Event
    {
        if (!$entity instanceof WhatsAppMessage) {
            throw new MethodNotAllowedHttpException(['WhatsAppMessage']);
        }

        $name = match ($action) {
            'pre_save'    => WhatsAppEvents::WHATSAPP_PRE_SAVE,
            'post_save'   => WhatsAppEvents::WHATSAPP_POST_SAVE,
            'pre_delete'  => WhatsAppEvents::WHATSAPP_PRE_DELETE,
            'post_delete' => WhatsAppEvents::WHATSAPP_POST_DELETE,
            default       => null,
        };

        if (null === $name) {
            return null;
        }

        if ($this->dispatcher->hasListeners($name)) {
            if (empty($event)) {
                $event = new WhatsAppMessageEvent($entity, $isNew);
                $event->setEntityManager($this->em);
            }

            $this->dispatcher->dispatch($event, $name);

            return $event;
        }

        return null;
    }

    /**
     * Joins the message table and limits created_by to the currently logged-in user.
     */
    public function limitQueryToCreator(QueryBuilder &$q): void
    {
        $q->join('t', MAUTIC_TABLE_PREFIX.'whatsapp_messages', 'wa', 'wa.id = t.whatsapp_message_id')
            ->andWhere('wa.created_by = :userId')
            ->setParameter('userId', $this->userHelper->getUser()->getId());
    }

    /**
     * Get line chart data of message stats.
     *
     * @param array<string, mixed> $filter
     *
     * @return array<string, mixed>
     */
    public function getHitsLineChartData(
        ?string $unit,
        \DateTime $dateFrom,
        \DateTime $dateTo,
        ?string $dateFormat = null,
        array $filter = [],
        bool $canViewOthers = true,
    ): array {
        $flag = null;

        if (isset($filter['flag'])) {
            $flag = $filter['flag'];
            unset($filter['flag']);
        }

        $chart = new LineChart($unit, $dateFrom, $dateTo, $dateFormat);
        $query = new ChartQuery($this->em->getConnection(), $dateFrom, $dateTo);

        if (!$flag || 'total_and_unique' === $flag) {
            $filter['is_failed'] = 0;
            $q                   = $query->prepareTimeDataQuery('whatsapp_message_stats', 'date_sent', $filter);

            if (!$canViewOthers) {
                $this->limitQueryToCreator($q);
            }

            $data = $query->loadAndBuildTimeData($q);
            $chart->setDataset($this->translator->trans('mautic.whatsapp.show.total.sent'), $data);
        }

        if (!$flag || 'delivered' === $flag) {
            unset($filter['is_failed']);
            $filter['status'] = WhatsAppStat::STATUS_DELIVERED;
            $q                = $query->prepareTimeDataQuery('whatsapp_message_stats', 'date_delivered', $filter);

            if (!$canViewOthers) {
                $this->limitQueryToCreator($q);
            }

            $data = $query->loadAndBuildTimeData($q);
            $chart->setDataset($this->translator->trans('mautic.whatsapp.show.delivered'), $data);
            unset($filter['status']);
        }

        if (!$flag || 'read' === $flag) {
            unset($filter['is_failed']);
            $filter['status'] = WhatsAppStat::STATUS_READ;
            $q                = $query->prepareTimeDataQuery('whatsapp_message_stats', 'date_read', $filter);

            if (!$canViewOthers) {
                $this->limitQueryToCreator($q);
            }

            $data = $query->loadAndBuildTimeData($q);
            $chart->setDataset($this->translator->trans('mautic.whatsapp.show.read'), $data);
            unset($filter['status']);
        }

        if (!$flag || 'failed' === $flag) {
            $filter['is_failed'] = 1;
            $q                   = $query->prepareTimeDataQuery('whatsapp_message_stats', 'date_sent', $filter);

            if (!$canViewOthers) {
                $this->limitQueryToCreator($q);
            }

            $data = $query->loadAndBuildTimeData($q);
            $chart->setDataset($this->translator->trans('mautic.whatsapp.show.failed'), $data);
        }

        return $chart->render();
    }

    public function getWhatsAppStatus(string $idHash): ?WhatsAppStat
    {
        return $this->getStatRepository()->getWhatsAppStatus($idHash);
    }

    /**
     * @return array<WhatsAppStat>
     */
    public function getWhatsAppStatByLeadId(int $messageId, int $leadId): array
    {
        return $this->getStatRepository()->findBy(
            [
                'whatsappMessage' => $messageId,
                'lead'            => $leadId,
            ],
            ['dateSent' => 'DESC']
        );
    }

    /**
     * @return array<mixed>
     */
    public function getWhatsAppClickStats(int $messageId): array
    {
        return $this->pageTrackableModel->getTrackableList('whatsapp', $messageId);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<mixed>
     */
    public function getLookupResults($type, $filter = '', $limit = 10, $start = 0, $options = []): array
    {
        $results = [];

        switch ($type) {
            case 'whatsapp':
            case WhatsAppType::class:
                $entities = $this->getRepository()->getWhatsAppMessageList(
                    $filter,
                    $limit,
                    $start,
                    $this->security->isGranted($this->getPermissionBase().':viewother'),
                    $options['message_type'] ?? null,
                    $options['ignore_ids'] ?? [],
                );

                foreach ($entities as $entity) {
                    $results[$entity['language']][$entity['id']] = $entity['name'];
                }

                ksort($results);

                break;
        }

        return $results;
    }
}
