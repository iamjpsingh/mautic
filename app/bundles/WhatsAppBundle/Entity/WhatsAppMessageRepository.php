<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Entity;

use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Mautic\CoreBundle\Entity\CommonRepository;

/**
 * @extends CommonRepository<WhatsAppMessage>
 */
class WhatsAppMessageRepository extends CommonRepository
{
    /**
     * @return Paginator<WhatsAppMessage>
     */
    public function getEntities(array $args = []): Paginator
    {
        $q = $this->_em
            ->createQueryBuilder()
            ->select($this->getTableAlias())
            ->from(WhatsAppMessage::class, $this->getTableAlias(), $this->getTableAlias().'.id');

        if (empty($args['iterable_mode'])) {
            $q->leftJoin($this->getTableAlias().'.category', 'c');
        }

        $args['qb'] = $q;

        return parent::getEntities($args);
    }

    /**
     * @return iterable<WhatsAppMessage>
     */
    public function getPublishedBroadcastsIterable(?int $id = null): iterable
    {
        return $this->getPublishedBroadcastsQuery($id)->toIterable();
    }

    private function getPublishedBroadcastsQuery(?int $id = null): Query
    {
        $qb   = $this->createQueryBuilder($this->getTableAlias());
        $expr = $this->getPublishedByDateExpression($qb, null, true, true, false);

        if (null !== $id && 0 !== $id) {
            $expr->add(
                $qb->expr()->eq($this->getTableAlias().'.id', (int) $id)
            );
        }

        $qb->where($expr);

        return $qb->getQuery();
    }

    /**
     * @return \Doctrine\DBAL\Query\QueryBuilder
     */
    public function getSegmentsContactsQuery(int $messageId): \Doctrine\DBAL\Query\QueryBuilder
    {
        $q = $this->getEntityManager()->getConnection()->createQueryBuilder();
        $q->from(MAUTIC_TABLE_PREFIX.'whatsapp_message_list_xref', 'wml')
            ->join('wml', MAUTIC_TABLE_PREFIX.'lead_lists', 'll', 'll.id = wml.leadlist_id and ll.is_published = 1')
            ->join('ll', MAUTIC_TABLE_PREFIX.'lead_lists_leads', 'lll', 'lll.leadlist_id = wml.leadlist_id and lll.manually_removed = 0')
            ->join('lll', MAUTIC_TABLE_PREFIX.'leads', 'l', 'lll.lead_id = l.id')
            ->where(
                $q->expr()->and(
                    $q->expr()->eq('wml.whatsapp_message_id', ':messageId')
                )
            )
            ->setParameter('messageId', $messageId)
            ->orderBy('lll.lead_id');

        return $q;
    }

    /**
     * @return array<string, int>
     */
    public function getSentCount(): array
    {
        $q = $this->_em->createQueryBuilder();
        $q->select('SUM(e.sentCount) as sent_count, SUM(e.deliveredCount) as delivered_count, SUM(e.readCount) as read_count')
            ->from(WhatsAppMessage::class, 'e');
        $results = $q->getQuery()->getSingleResult(Query::HYDRATE_ARRAY);

        if (!isset($results['sent_count'])) {
            $results['sent_count'] = 0;
        }
        if (!isset($results['delivered_count'])) {
            $results['delivered_count'] = 0;
        }
        if (!isset($results['read_count'])) {
            $results['read_count'] = 0;
        }

        return $results;
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder|\Doctrine\DBAL\Query\QueryBuilder $q
     */
    protected function addSearchCommandWhereClause($q, $filter): array
    {
        [$expr, $parameters] = $this->addStandardSearchCommandWhereClause($q, $filter);
        if ($expr) {
            return [$expr, $parameters];
        }

        $command         = $filter->command;
        $unique          = $this->generateRandomParameterName();
        $returnParameter = false;

        switch ($command) {
            case $this->translator->trans('mautic.core.searchcommand.lang'):
                $langUnique      = $this->generateRandomParameterName();
                $langValue       = $filter->string.'_%';
                $forceParameters = [
                    $langUnique => $langValue,
                    $unique     => $filter->string,
                ];
                $expr = $q->expr()->or(
                    $q->expr()->eq('e.language', ":$unique"),
                    $q->expr()->like('e.language', ":$langUnique")
                );
                $returnParameter = true;
                break;
        }

        if ($expr && $filter->not) {
            $expr = $q->expr()->not($expr);
        }

        if (!empty($forceParameters)) {
            $parameters = $forceParameters;
        } elseif ($returnParameter) {
            $string     = ($filter->strict) ? $filter->string : "%{$filter->string}%";
            $parameters = ["$unique" => $string];
        }

        return [$expr, $parameters];
    }

    /**
     * @return string[]
     */
    public function getSearchCommands(): array
    {
        $commands = [
            'mautic.core.searchcommand.ispublished',
            'mautic.core.searchcommand.isunpublished',
            'mautic.core.searchcommand.isuncategorized',
            'mautic.core.searchcommand.ismine',
            'mautic.core.searchcommand.category',
            'mautic.core.searchcommand.lang',
        ];

        return array_merge($commands, parent::getSearchCommands());
    }

    /**
     * @return array<array<string>>
     */
    protected function getDefaultOrder(): array
    {
        return [
            ['e.name', 'ASC'],
        ];
    }

    public function getTableAlias(): string
    {
        return 'e';
    }

    /**
     * Increase a counter field (sent, delivered, read).
     */
    public function upCount(int $id, string $type = 'sent', int $increaseBy = 1): void
    {
        try {
            $q = $this->_em->getConnection()->createQueryBuilder();

            $q->update(MAUTIC_TABLE_PREFIX.'whatsapp_messages')
                ->set($type.'_count', $type.'_count + '.$increaseBy)
                ->where('id = '.$id);

            $q->executeStatement();
        } catch (\Exception) {
            // not important
        }
    }

    /**
     * @param array<int> $ignoreIds
     *
     * @return array<mixed>
     */
    public function getWhatsAppMessageList(
        mixed $search = '',
        int $limit = 10,
        int $start = 0,
        bool $viewOther = false,
        ?string $messageType = null,
        array $ignoreIds = [],
    ): array {
        $q = $this->createQueryBuilder('e');
        $q->select('partial e.{id, name, templateLanguage}');

        // Only show published messages in dropdowns
        $q->andWhere($q->expr()->eq('e.isPublished', ':published'))
            ->setParameter('published', true);

        if (!empty($search)) {
            if (is_array($search)) {
                $search = array_map('intval', $search);
                $q->andWhere($q->expr()->in('e.id', ':search'))
                    ->setParameter('search', $search);
            } else {
                $q->andWhere($q->expr()->like('e.name', ':search'))
                    ->setParameter('search', "%{$search}%");
            }
        }

        if (!$viewOther) {
            $q->andWhere($q->expr()->eq('e.createdBy', ':id'))
                ->setParameter('id', $this->currentUser->getId());
        }

        if (!empty($messageType)) {
            $q->andWhere(
                $q->expr()->eq('e.messageType', $q->expr()->literal($messageType))
            );
        }

        if (!empty($ignoreIds)) {
            $q->andWhere($q->expr()->notIn('e.id', ':ignoreIds'))
                ->setParameter('ignoreIds', $ignoreIds);
        }

        $q->orderBy('e.name');

        if (!empty($limit)) {
            $q->setFirstResult($start)
                ->setMaxResults($limit);
        }

        return $q->getQuery()->getArrayResult();
    }
}
