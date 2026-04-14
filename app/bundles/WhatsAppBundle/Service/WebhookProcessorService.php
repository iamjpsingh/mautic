<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Tracker\ContactTracker;
use Mautic\WhatsAppBundle\Entity\WhatsAppMessage;
use Mautic\WhatsAppBundle\Entity\WhatsAppStat;
use Mautic\WhatsAppBundle\Event\WhatsAppReplyEvent;
use Mautic\WhatsAppBundle\Exception\NumberNotFoundException;
use Mautic\WhatsAppBundle\Helper\ContactHelper;
use Mautic\WhatsAppBundle\WhatsAppEvents;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class WebhookProcessorService
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private ContactHelper $contactHelper,
        private SessionWindowTracker $sessionTracker,
        private EventDispatcherInterface $dispatcher,
        private ContactTracker $contactTracker,
    ) {
    }

    public function processDeliveryStatus(string $waMessageId, string $status, string $timestamp): void
    {
        $this->logger->info('WhatsApp webhook: looking up stat by wamid', [
            'wamid'  => $waMessageId,
            'status' => $status,
        ]);

        $stat = $this->em->getRepository(WhatsAppStat::class)
            ->findOneBy(['whatsappMessageId' => $waMessageId]);

        if (null === $stat) {
            $this->logger->warning('WhatsApp webhook: no stat found for wamid', ['wamid' => $waMessageId]);

            return;
        }

        $this->logger->info('WhatsApp webhook: found stat, updating status', [
            'stat_id' => $stat->getId(),
            'status'  => $status,
        ]);

        $dateTime = !empty($timestamp)
            ? (new \DateTime())->setTimestamp((int) $timestamp)
            : new \DateTime();

        switch ($status) {
            case 'delivered':
                if (null === $stat->getDateDelivered()) {
                    $stat->setDateDelivered($dateTime);
                    $stat->setStatus(WhatsAppStat::STATUS_DELIVERED);

                    $message = $stat->getWhatsappMessage();
                    if ($message) {
                        $this->em->getRepository(WhatsAppMessage::class)
                            ->createQueryBuilder('m')
                            ->update()
                            ->set('m.deliveredCount', 'm.deliveredCount + 1')
                            ->where('m.id = :id')
                            ->setParameter('id', $message->getId())
                            ->getQuery()
                            ->execute();
                    }
                }
                break;

            case 'read':
                if (null === $stat->getDateRead()) {
                    $stat->setDateRead($dateTime);
                    $stat->setStatus(WhatsAppStat::STATUS_READ);

                    $message = $stat->getWhatsappMessage();
                    if ($message) {
                        $this->em->getRepository(WhatsAppMessage::class)
                            ->createQueryBuilder('m')
                            ->update()
                            ->set('m.readCount', 'm.readCount + 1')
                            ->where('m.id = :id')
                            ->setParameter('id', $message->getId())
                            ->getQuery()
                            ->execute();
                    }
                }
                break;

            case 'failed':
                $stat->setIsFailed(true);
                $stat->setStatus(WhatsAppStat::STATUS_FAILED);
                break;

            case 'sent':
                $stat->setStatus(WhatsAppStat::STATUS_SENT);
                break;
        }

        $this->em->persist($stat);
        $this->em->flush();

        $this->logger->info(sprintf(
            'WhatsApp delivery status updated: %s -> %s (stat ID: %d)',
            $waMessageId,
            $status,
            $stat->getId()
        ));
    }

    /**
     * Process an inbound message from a contact.
     *
     * Finds the matching Mautic contact(s) by phone number, opens/refreshes
     * the 24-hour session window, routes opt-out keywords to DoNotContact,
     * and dispatches WHATSAPP_ON_REPLY so the timeline + campaign reply
     * decisions can react.
     *
     * Returns the number of contacts that received this reply (0 when no
     * matching contact is found).
     */
    public function processInboundMessage(string $senderNumber, string $body, ?\DateTimeInterface $at = null): int
    {
        if ('' === trim($body)) {
            return 0;
        }

        try {
            $contacts = $this->contactHelper->findContactsByNumber($senderNumber);
        } catch (NumberNotFoundException) {
            $this->logger->info('WhatsApp inbound: no Mautic contact matched phone number', [
                'from' => $senderNumber,
            ]);

            return 0;
        }

        $at ??= new \DateTime();
        $processed = 0;

        /** @var Lead $contact */
        foreach ($contacts as $contact) {
            $this->contactTracker->setSystemContact($contact);

            $wasOptOut = $this->sessionTracker->recordInbound($contact, $body, $at);

            // Dispatch reply event for timeline + campaign decisions even on opt-out
            // (it's useful context that the contact responded, just with STOP).
            $replyEvent = new WhatsAppReplyEvent($contact, trim($body));
            $this->dispatcher->dispatch($replyEvent, WhatsAppEvents::WHATSAPP_ON_REPLY);

            $this->logger->info('WhatsApp inbound processed', [
                'lead_id'     => $contact->getId(),
                'is_opt_out'  => $wasOptOut,
                'body_length' => strlen($body),
            ]);

            ++$processed;
        }

        return $processed;
    }
}
