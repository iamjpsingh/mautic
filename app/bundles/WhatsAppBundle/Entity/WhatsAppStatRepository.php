<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;
use Mautic\CoreBundle\Helper\DateTimeHelper;
use Mautic\LeadBundle\Entity\TimelineTrait;

/**
 * @extends CommonRepository<WhatsAppStat>
 */
class WhatsAppStatRepository extends CommonRepository
{
    use TimelineTrait;

    /**
     * @return WhatsAppStat|null
     *
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getWhatsAppStatus(string $trackingHash): ?WhatsAppStat
    {
        $q = $this->createQueryBuilder('s');
        $q->select('s')
            ->leftJoin('s.lead', 'l')
            ->leftJoin('s.whatsappMessage', 'e')
            ->where(
                $q->expr()->eq('s.trackingHash', ':hash')
            )
            ->setParameter('hash', $trackingHash);

        $result = $q->getQuery()->getResult();

        return (!empty($result)) ? $result[0] : null;
    }

    /**
     * Find a stat by Meta's WhatsApp message ID (wamid).
     */
    public function findByWhatsAppMessageId(string $whatsappMessageId): ?WhatsAppStat
    {
        return $this->findOneBy(['whatsappMessageId' => $whatsappMessageId]);
    }

    /**
     * @return array<int, int>
     */
    public function getSentStats(int $messageId, ?int $listId = null): array
    {
        $q = $this->_em->getConnection()->createQueryBuilder();
        $q->select('s.lead_id')
            ->from(MAUTIC_TABLE_PREFIX.'whatsapp_message_stats', 's')
            ->where('s.whatsapp_message_id = :messageId')
            ->setParameter('messageId', $messageId);

        if ($listId) {
            $q->andWhere('s.list_id = :list')
                ->setParameter('list', $listId);
        }

        $result = $q->executeQuery()->fetchAllAssociative();

        $stats = [];
        foreach ($result as $r) {
            $stats[$r['lead_id']] = $r['lead_id'];
        }

        unset($result);

        return $stats;
    }

    /**
     * @param int|array<int>|null $messageIds
     */
    public function getSentCount(int|array|null $messageIds = null, ?int $listId = null): int
    {
        $q = $this->_em->getConnection()->createQueryBuilder();

        $q->select('count(s.id) as sent_count')
            ->from(MAUTIC_TABLE_PREFIX.'whatsapp_message_stats', 's');

        if ($messageIds) {
            if (!is_array($messageIds)) {
                $messageIds = [$messageIds];
            }
            $q->where(
                $q->expr()->in('s.whatsapp_message_id', $messageIds)
            );
        }

        if ($listId) {
            $q->andWhere('s.list_id = '.$listId);
        }

        $q->andWhere('s.is_failed = :false')
            ->setParameter('false', false, 'boolean');

        $results = $q->executeQuery()->fetchAllAssociative();

        return (isset($results[0])) ? (int) $results[0]['sent_count'] : 0;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getLeadStats(int $leadId, array $options = []): array
    {
        $query = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $query->from(MAUTIC_TABLE_PREFIX.'whatsapp_message_stats', 's')
            ->leftJoin('s', MAUTIC_TABLE_PREFIX.'whatsapp_messages', 'e', 's.whatsapp_message_id = e.id');

        if ($leadId) {
            $query->andWhere(
                $query->expr()->eq('s.lead_id', ':leadId')
            )->setParameter('leadId', $leadId);
        }

        if (!empty($options['basic_select'])) {
            $query->select(
                's.whatsapp_message_id, s.id, s.date_sent as dateSent, e.name, e.name as whatsapp_name, s.is_failed as isFailed, s.status'
            );
        } else {
            $query->select(
                's.whatsapp_message_id, s.id, s.date_sent as dateSent, s.date_delivered as dateDelivered, '
                .'s.date_read as dateRead, e.name, e.name as whatsapp_name, e.message, e.message_type as type, '
                .'s.is_failed as isFailed, s.status, s.list_id, l.name as list_name, s.tracking_hash as idHash, '
                .'s.lead_id, s.details, s.wa_message_id as whatsappMessageId'
            )
                ->leftJoin('s', MAUTIC_TABLE_PREFIX.'lead_lists', 'l', 's.list_id = l.id');
        }

        if (isset($options['state'])) {
            $state = $options['state'];
            if ('failed' === $state) {
                $query->andWhere(
                    $query->expr()->eq('s.is_failed', 1)
                );
            } elseif ('delivered' === $state) {
                $query->andWhere(
                    $query->expr()->eq('s.status', $query->expr()->literal(WhatsAppStat::STATUS_DELIVERED))
                );
            } elseif ('read' === $state) {
                $query->andWhere(
                    $query->expr()->eq('s.status', $query->expr()->literal(WhatsAppStat::STATUS_READ))
                );
            }
        }
        $state = 'sent';

        if (isset($options['search']) && $options['search']) {
            $query->andWhere(
                $query->expr()->or(
                    $query->expr()->like('e.name', $query->expr()->literal('%'.$options['search'].'%'))
                )
            );
        }

        if (isset($options['fromDate']) && $options['fromDate']) {
            $dt = new DateTimeHelper($options['fromDate']);
            $query->andWhere(
                $query->expr()->gte('s.date_sent', $query->expr()->literal($dt->toUtcString()))
            );
        }

        return $this->getTimelineResults(
            $query,
            $options,
            'e.name',
            's.date_'.$state
        );
    }

    /**
     * Updates lead ID (e.g. after a lead merge).
     */
    public function updateLead(int $fromLeadId, int $toLeadId): void
    {
        $q = $this->_em->getConnection()->createQueryBuilder();
        $q->update(MAUTIC_TABLE_PREFIX.'whatsapp_message_stats')
            ->set('lead_id', $toLeadId)
            ->where('lead_id = '.$fromLeadId)
            ->executeStatement();
    }

    public function deleteStat(int $id): void
    {
        $this->_em->getConnection()->delete(MAUTIC_TABLE_PREFIX.'whatsapp_message_stats', ['id' => $id]);
    }

    public function getTableAlias(): string
    {
        return 's';
    }
}
