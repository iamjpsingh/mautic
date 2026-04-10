<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Service;

use Mautic\CoreBundle\Helper\CacheStorageHelper;

/**
 * Tracks WhatsApp webhook health — last verified, last received, errors.
 *
 * Uses Mautic's CacheStorageHelper so state persists across requests
 * without needing its own database table.
 *
 * @author iamjpsingh
 */
class WebhookStatusTracker
{
    private const KEY_LAST_VERIFIED  = 'whatsapp.webhook.last_verified_at';
    private const KEY_LAST_RECEIVED  = 'whatsapp.webhook.last_received_at';
    private const KEY_LAST_ERROR     = 'whatsapp.webhook.last_error';
    private const KEY_LAST_ERROR_AT  = 'whatsapp.webhook.last_error_at';
    private const KEY_VERIFY_COUNT   = 'whatsapp.webhook.verify_count';
    private const KEY_RECEIVE_COUNT  = 'whatsapp.webhook.receive_count';

    public function __construct(
        private CacheStorageHelper $cacheStorageHelper,
    ) {
    }

    public function recordVerified(): void
    {
        $this->cacheStorageHelper->set(self::KEY_LAST_VERIFIED, (new \DateTime())->format(\DateTime::ATOM));
        $this->incrementCounter(self::KEY_VERIFY_COUNT);
        $this->clearError();
    }

    public function recordReceived(): void
    {
        $this->cacheStorageHelper->set(self::KEY_LAST_RECEIVED, (new \DateTime())->format(\DateTime::ATOM));
        $this->incrementCounter(self::KEY_RECEIVE_COUNT);
    }

    public function recordError(string $message): void
    {
        $this->cacheStorageHelper->set(self::KEY_LAST_ERROR, $message);
        $this->cacheStorageHelper->set(self::KEY_LAST_ERROR_AT, (new \DateTime())->format(\DateTime::ATOM));
    }

    public function clearError(): void
    {
        $this->cacheStorageHelper->delete(self::KEY_LAST_ERROR);
        $this->cacheStorageHelper->delete(self::KEY_LAST_ERROR_AT);
    }

    public function getLastVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->readDate(self::KEY_LAST_VERIFIED);
    }

    public function getLastReceivedAt(): ?\DateTimeImmutable
    {
        return $this->readDate(self::KEY_LAST_RECEIVED);
    }

    public function getLastError(): ?string
    {
        $error = $this->cacheStorageHelper->get(self::KEY_LAST_ERROR);

        return is_string($error) && '' !== $error ? $error : null;
    }

    public function getLastErrorAt(): ?\DateTimeImmutable
    {
        return $this->readDate(self::KEY_LAST_ERROR_AT);
    }

    public function getVerifyCount(): int
    {
        $count = $this->cacheStorageHelper->get(self::KEY_VERIFY_COUNT);

        return is_int($count) ? $count : 0;
    }

    public function getReceiveCount(): int
    {
        $count = $this->cacheStorageHelper->get(self::KEY_RECEIVE_COUNT);

        return is_int($count) ? $count : 0;
    }

    /**
     * Compute the overall status: 'active' | 'idle' | 'pending' | 'error' | 'not_configured'.
     */
    public function computeStatus(bool $tokenSet): string
    {
        if (!$tokenSet) {
            return 'not_configured';
        }

        if (null !== $this->getLastError() && null === $this->getLastVerifiedAt()) {
            return 'error';
        }

        $lastReceived = $this->getLastReceivedAt();
        if (null !== $lastReceived) {
            $hoursSince = (time() - $lastReceived->getTimestamp()) / 3600;
            if ($hoursSince <= 24) {
                return 'active';
            }

            return 'idle';
        }

        if (null !== $this->getLastVerifiedAt()) {
            return 'idle';
        }

        return 'pending';
    }

    /**
     * @return array{
     *   status: string,
     *   last_verified_at: ?string,
     *   last_received_at: ?string,
     *   last_error: ?string,
     *   last_error_at: ?string,
     *   verify_count: int,
     *   receive_count: int,
     * }
     */
    public function getState(bool $tokenSet): array
    {
        return [
            'status'           => $this->computeStatus($tokenSet),
            'last_verified_at' => $this->getLastVerifiedAt()?->format(\DateTime::ATOM),
            'last_received_at' => $this->getLastReceivedAt()?->format(\DateTime::ATOM),
            'last_error'       => $this->getLastError(),
            'last_error_at'    => $this->getLastErrorAt()?->format(\DateTime::ATOM),
            'verify_count'     => $this->getVerifyCount(),
            'receive_count'    => $this->getReceiveCount(),
        ];
    }

    private function readDate(string $key): ?\DateTimeImmutable
    {
        $value = $this->cacheStorageHelper->get($key);

        if (!is_string($value) || '' === $value) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function incrementCounter(string $key): void
    {
        $current = $this->cacheStorageHelper->get($key);
        $value   = is_int($current) ? $current + 1 : 1;
        $this->cacheStorageHelper->set($key, $value);
    }
}
