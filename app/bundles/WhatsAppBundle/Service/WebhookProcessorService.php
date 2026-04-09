<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\WhatsAppBundle\Entity\WhatsAppMessage;
use Mautic\WhatsAppBundle\Entity\WhatsAppStat;
use Psr\Log\LoggerInterface;

class WebhookProcessorService
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function processDeliveryStatus(string $waMessageId, string $status, string $timestamp): void
    {
        $stat = $this->em->getRepository(WhatsAppStat::class)
            ->findOneBy(['whatsappMessageId' => $waMessageId]);

        if (null === $stat) {
            $this->logger->debug('WhatsApp webhook: no stat found for message ID: '.$waMessageId);

            return;
        }

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
}
