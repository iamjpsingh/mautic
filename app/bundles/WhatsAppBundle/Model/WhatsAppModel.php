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
use Mautic\WhatsAppBundle\Service\SessionWindowTracker;
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
        private SessionWindowTracker $sessionWindowTracker,
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

    /**
     * Find a WhatsApp template by name and optionally by language.
     *
     * @author iamjpsingh
     */
    public function findTemplateByName(string $name, ?string $language = null): ?WhatsAppTemplate
    {
        $criteria = ['name' => $name];
        if ($language) {
            $criteria['language'] = $language;
        }

        return $this->em->getRepository(WhatsAppTemplate::class)->findOneBy($criteria);
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

            $isSessionMessage = WhatsAppMessage::MESSAGE_TYPE_SESSION === $message->getMessageType();

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
                        $stat->setIsFailed(true);
                        $stat->setStatus(WhatsAppStat::STATUS_FAILED);
                        $stat->addDetail('failed', 'Contact has no phone number');
                        $stats[] = $stat;
                        ++$failedCount;

                        continue;
                    }

                    // E.164 format check: digits after optional '+', 7-15 digits total.
                    // Meta rejects malformed numbers — fail fast with a clear reason
                    // instead of burning an API call.
                    if (!self::isE164($leadPhoneNumber)) {
                        $results[$leadId] = [
                            'sent'   => false,
                            'status' => 'mautic.whatsapp.campaign.failed.invalid_number',
                        ];
                        $stat->setIsFailed(true);
                        $stat->setStatus(WhatsAppStat::STATUS_FAILED);
                        $stat->addDetail('failed', sprintf('Invalid phone format (expected E.164): %s', $leadPhoneNumber));
                        $stats[] = $stat;
                        ++$failedCount;

                        continue;
                    }

                    // 24-hour customer service window enforcement for session (free-form) messages.
                    // Meta only permits session messages within 24 hours of the contact's last
                    // inbound message. If the window is closed (or the contact has never
                    // messaged), skip the send and record a clear failure reason.
                    if ($isSessionMessage && !$this->sessionWindowTracker->isWindowOpen($leadId)) {
                        $results[$leadId] = [
                            'sent'   => false,
                            'status' => 'mautic.whatsapp.campaign.failed.outside_session_window',
                        ];
                        $stat->setIsFailed(true);
                        $stat->setStatus(WhatsAppStat::STATUS_FAILED);
                        $stat->addDetail('failed', 'Outside 24h session window — contact must reply first');
                        $stats[] = $stat;
                        ++$failedCount;

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

                    // Transport returns: true (success), wamid string (success with ID), or error string (failure)
                    $isSent = true === $metadata || (is_string($metadata) && str_starts_with($metadata, 'wamid.'));

                    if (!$isSent) {
                        $sendResult['status'] = is_string($metadata) ? $metadata : 'Unknown send error';
                        $stat->setIsFailed(true);
                        $stat->setStatus(WhatsAppStat::STATUS_FAILED);
                        if (is_string($metadata)) {
                            $stat->addDetail('failed', $metadata);
                        }
                        ++$failedCount;
                    } else {
                        $sendResult['sent'] = true;
                        $stat->setStatus(WhatsAppStat::STATUS_SENT);

                        // Store the Meta message ID (wamid) for delivery tracking
                        if (is_string($metadata) && '' !== $metadata) {
                            $stat->setWhatsappMessageId($metadata);
                        }

                        // Bookkeeping for diagnostics — does not open a session window
                        // (only inbound messages from the contact can do that).
                        $this->sessionWindowTracker->recordOutbound($leadId);

                        ++$sentCount;
                    }

                    $stats[]            = $stat;
                    $results[$leadId]   = $sendResult;

                    unset($sendEvent, $tokenEvent, $sendResult, $metadata, $stat);
                }
            }
        }

        if ($sentCount || $failedCount) {
            // Persist stats BEFORE incrementing the counter so they stay in sync
            try {
                $this->getStatRepository()->saveEntities($stats);
            } catch (\Throwable $e) {
                $this->logger->error('WhatsApp: failed to save stats: '.$e->getMessage(), ['exception' => $e]);

                // Flip every result to failed so the UI reflects reality
                foreach ($stats as $stat) {
                    $leadId = $stat->getLead()?->getId();
                    if ($leadId && isset($results[$leadId])) {
                        $results[$leadId]['sent']   = false;
                        $results[$leadId]['status'] = 'Database error: '.$e->getMessage();
                    }
                }

                return $results;
            }

            $this->getRepository()->upCount($message->getId(), 'sent', $sentCount);

            foreach ($stats as $stat) {
                if (!$stat->isFailed()) {
                    $leadId = $stat->getLead()?->getId();
                    if ($leadId) {
                        $results[$leadId]['statId'] = $stat->getId();
                    }
                }

                $this->em->detach($stat);
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
                $this->buildTemplateSendComponents($message, $lead),
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
     * Build the components array for a template send request.
     *
     * Checks for parameter mapping format first (from the placeholder mapping UI),
     * then falls back to auto-detection from raw template components.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildTemplateSendComponents(WhatsAppMessage $message, Lead $lead): array
    {
        $mapping = $message->getTemplateComponents();

        if (empty($mapping)) {
            return [];
        }

        // Check if mapping is parameter mapping format [{param:1, token:"..."}, ...]
        if (isset($mapping[0]['param'])) {
            return $this->buildFromMapping($mapping, $lead);
        }

        // Fallback: old format (raw Meta component data)
        return $this->buildAutoComponents($mapping, $lead);
    }

    /**
     * Build components from explicit parameter mapping (from the UI).
     *
     * @param array<int, array<string, mixed>> $mapping
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildFromMapping(array $mapping, Lead $lead): array
    {
        $parameters = [];

        // Sort by param number
        usort($mapping, fn ($a, $b) => ($a['param'] ?? 0) <=> ($b['param'] ?? 0));

        foreach ($mapping as $entry) {
            $token = $entry['token'] ?? '';
            $value = $this->resolveToken($token, $lead);

            // Meta API rejects empty parameter values — substitute a single hyphen
            if ('' === $value) {
                $value = '-';
            }

            $parameters[] = ['type' => 'text', 'text' => $value];
        }

        if (empty($parameters)) {
            return [];
        }

        return [['type' => 'body', 'parameters' => $parameters]];
    }

    /**
     * Resolve a token string to an actual value using lead data.
     *
     * Supported tokens:
     *   {contactfield=firstname}          — contact field, empty string if missing
     *   {contactfield=firstname|Fallback} — contact field with explicit fallback
     *   {datetime=now}                    — current datetime
     *   anything else                     — returned as-is (plain text / custom value)
     */
    private function resolveToken(string $token, Lead $lead): string
    {
        // Contact field token: {contactfield=firstname} or {contactfield=firstname|Default}
        if (preg_match('/\{contactfield=(\w+)(?:\|(.+?))?\}/', $token, $matches)) {
            $field    = $matches[1];
            $fallback = $matches[2] ?? '';
            $value    = $lead->getFieldValue($field);

            if (null !== $value && '' !== (string) $value) {
                return (string) $value;
            }

            return $fallback;
        }

        // DateTime token: {datetime=now}
        if (preg_match('/\{datetime=(.+?)\}/', $token)) {
            return (new \DateTime())->format('Y-m-d H:i');
        }

        // Plain text / custom value — return as-is
        return $token;
    }

    /**
     * Fallback: auto-detect variables from raw Meta template components.
     *
     * @param array<int, array<string, mixed>> $storedComponents
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildAutoComponents(array $storedComponents, Lead $lead): array
    {
        $sendComponents = [];

        foreach ($storedComponents as $component) {
            $type = $component['type'] ?? null;

            if (null === $type) {
                continue;
            }

            // Check BODY component text for variable placeholders like {{1}}, {{2}}
            if ('BODY' === $type) {
                $text = $component['text'] ?? '';
                if (preg_match_all('/\{\{(\d+)\}\}/', $text, $matches)) {
                    $parameters = [];
                    foreach ($matches[1] as $index) {
                        $value = match ((int) $index) {
                            1       => $lead->getFirstname() ?: 'Valued Customer',
                            default => 'N/A',
                        };
                        $parameters[] = ['type' => 'text', 'text' => $value];
                    }

                    $sendComponents[] = [
                        'type'       => 'body',
                        'parameters' => $parameters,
                    ];
                }
            }

            // Check HEADER component for variable placeholders
            if ('HEADER' === $type) {
                $headerText = $component['text'] ?? '';
                if (preg_match_all('/\{\{(\d+)\}\}/', $headerText, $matches)) {
                    $parameters = [];
                    foreach ($matches[1] as $index) {
                        $value = match ((int) $index) {
                            1       => $lead->getFirstname() ?: 'Valued Customer',
                            default => 'N/A',
                        };
                        $parameters[] = ['type' => 'text', 'text' => $value];
                    }

                    $sendComponents[] = [
                        'type'       => 'header',
                        'parameters' => $parameters,
                    ];
                }
            }
        }

        return $sendComponents;
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
    /**
     * Validate E.164 phone format (optional leading '+', 7-15 digits).
     *
     * Meta's Cloud API rejects non-E.164 numbers. We pre-check locally so a
     * malformed number fails fast with a meaningful reason rather than
     * burning an API call and receiving an opaque Meta error.
     */
    public static function isE164(string $number): bool
    {
        return 1 === preg_match('/^\+?[1-9]\d{6,14}$/', $number);
    }

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

                // Return a flat list — no grouping by language (which looked ugly in dropdowns)
                foreach ($entities as $entity) {
                    $results[$entity['id']] = $entity['name'];
                }

                break;
        }

        return $results;
    }
}
