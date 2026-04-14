<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Service;

use Doctrine\DBAL\Connection;
use Mautic\LeadBundle\Entity\DoNotContact;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\DoNotContact as DoNotContactModel;
use Psr\Log\LoggerInterface;

/**
 * Tracks the 24-hour WhatsApp customer service window per contact.
 *
 * Meta only allows free-form (session) messages to be delivered within
 * 24 hours of the contact's most recent inbound message. This service is
 * the single source of truth for:
 *   - recording inbound timestamps from webhook replies
 *   - answering "is the session open for this contact right now?"
 *   - recognising opt-out keywords (STOP, UNSUBSCRIBE, etc.) and routing
 *     them to the DoNotContact model
 *
 * Persists state in the `whatsapp_contact_sessions` table via DBAL for
 * hot-path efficiency. No Doctrine entity is needed.
 *
 * @author iamjpsingh
 */
class SessionWindowTracker
{
    public const TABLE = 'whatsapp_contact_sessions';

    /**
     * Window duration in seconds. Meta's policy is 24 hours — we use a
     * hard 24-hour window with no grace period.
     */
    public const WINDOW_SECONDS = 86400;

    /**
     * Keywords that trigger an automatic opt-out (case-insensitive, exact
     * match on the trimmed inbound body). Keep this list conservative —
     * matching a substring would cause false positives on real replies.
     *
     * @var list<string>
     */
    public const OPT_OUT_KEYWORDS = [
        'STOP',
        'STOPALL',
        'UNSUBSCRIBE',
        'CANCEL',
        'QUIT',
        'END',
    ];

    public function __construct(
        private Connection $connection,
        private DoNotContactModel $doNotContact,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Record an inbound message from a contact. Opens (or refreshes) the
     * 24-hour session window and returns whether the body was an
     * opt-out keyword.
     */
    public function recordInbound(Lead $contact, string $messageBody, ?\DateTimeInterface $at = null): bool
    {
        $at     ??= new \DateTime();
        $leadId   = $contact->getId();

        if (null === $leadId) {
            return false;
        }

        $this->upsert($leadId, ['last_inbound_at' => $at->format('Y-m-d H:i:s')]);

        if (!$this->isOptOutKeyword($messageBody)) {
            return false;
        }

        $this->doNotContact->addDncForContact(
            $leadId,
            'whatsapp',
            DoNotContact::UNSUBSCRIBED,
            'WhatsApp opt-out keyword received: '.trim($messageBody)
        );

        $this->upsert($leadId, [
            'is_opted_out' => 1,
            'opted_out_at' => $at->format('Y-m-d H:i:s'),
        ]);

        $this->logger->info('WhatsApp: contact opted out via keyword', [
            'lead_id' => $leadId,
            'body'    => $messageBody,
        ]);

        return true;
    }

    /**
     * Record an outbound send. Used for diagnostics only — does NOT open
     * or extend the session window (only inbound messages can do that).
     */
    public function recordOutbound(int $leadId, ?\DateTimeInterface $at = null): void
    {
        $at ??= new \DateTime();

        $this->upsert($leadId, ['last_outbound_at' => $at->format('Y-m-d H:i:s')]);
    }

    /**
     * Is the 24-hour session window currently open for this contact?
     * False if the contact has never messaged in, or if the last inbound
     * was more than 24 hours ago.
     */
    public function isWindowOpen(int $leadId, ?\DateTimeInterface $now = null): bool
    {
        $lastInbound = $this->getLastInboundAt($leadId);
        if (null === $lastInbound) {
            return false;
        }

        $now ??= new \DateTime();

        return ($now->getTimestamp() - $lastInbound->getTimestamp()) < self::WINDOW_SECONDS;
    }

    public function getLastInboundAt(int $leadId): ?\DateTimeInterface
    {
        $row = $this->connection->createQueryBuilder()
            ->select('last_inbound_at')
            ->from(MAUTIC_TABLE_PREFIX.self::TABLE)
            ->where('lead_id = :leadId')
            ->setParameter('leadId', $leadId)
            ->executeQuery()
            ->fetchAssociative();

        if (!$row || empty($row['last_inbound_at'])) {
            return null;
        }

        return new \DateTime((string) $row['last_inbound_at']);
    }

    public function isOptOutKeyword(string $body): bool
    {
        $normalized = strtoupper(trim($body));

        return in_array($normalized, self::OPT_OUT_KEYWORDS, true);
    }

    /**
     * @param array<string, scalar> $columns
     */
    private function upsert(int $leadId, array $columns): void
    {
        $columns['lead_id'] = $leadId;

        $quoted     = array_map(fn (string $c) => "`{$c}`", array_keys($columns));
        $placeholder = array_map(fn (string $c) => ":{$c}", array_keys($columns));
        $updateExpr = array_map(
            fn (string $c) => "`{$c}` = VALUES(`{$c}`)",
            array_filter(array_keys($columns), fn (string $c) => 'lead_id' !== $c)
        );

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
            MAUTIC_TABLE_PREFIX.self::TABLE,
            implode(', ', $quoted),
            implode(', ', $placeholder),
            implode(', ', $updateExpr)
        );

        $this->connection->executeStatement($sql, $columns);
    }
}
