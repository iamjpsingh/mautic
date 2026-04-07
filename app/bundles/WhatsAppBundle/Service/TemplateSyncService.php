<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\WhatsAppBundle\Entity\WhatsAppTemplate;
use Mautic\WhatsAppBundle\Entity\WhatsAppTemplateRepository;
use Mautic\WhatsAppBundle\Integration\MetaCloud\Configuration;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author iamjpsingh
 */
class TemplateSyncService
{
    private const API_VERSION = 'v22.0';

    private const BASE_URL = 'https://graph.facebook.com';

    public function __construct(
        private Configuration $configuration,
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Sync templates from Meta Graph API.
     *
     * @return array{synced: int, approved: int, pending: int, rejected: int}
     */
    public function syncTemplates(): array
    {
        $businessAccountId = $this->configuration->getBusinessAccountId();
        $accessToken       = $this->configuration->getAccessToken();

        $url = sprintf(
            '%s/%s/%s/message_templates',
            self::BASE_URL,
            self::API_VERSION,
            $businessAccountId
        );

        $results = [
            'synced'   => 0,
            'approved' => 0,
            'pending'  => 0,
            'rejected' => 0,
        ];

        /** @var WhatsAppTemplateRepository $repository */
        $repository = $this->entityManager->getRepository(WhatsAppTemplate::class);
        $now        = new \DateTime();

        do {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                ],
                'query' => [
                    'limit'        => 100,
                    'access_token' => $accessToken,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $errorBody = $response->getContent(false);
                $this->logger->error('Meta API error: '.$errorBody);
                throw new \RuntimeException('Meta API error ('.$statusCode.'): '.$errorBody);
            }

            $data = $response->toArray(false);

            foreach ($data['data'] ?? [] as $templateData) {
                $metaTemplateId = (string) $templateData['id'];
                $template       = $repository->findOneByMetaTemplateId($metaTemplateId);

                if (null === $template) {
                    $template = new WhatsAppTemplate();
                    $template->setMetaTemplateId($metaTemplateId);
                }

                $status = strtoupper($templateData['status'] ?? 'PENDING');

                $template->setName($templateData['name'] ?? '')
                    ->setStatus($status)
                    ->setLanguage($templateData['language'] ?? 'en')
                    ->setCategory($templateData['category'] ?? null)
                    ->setComponents($templateData['components'] ?? null)
                    ->setLastSyncedAt($now);

                $this->entityManager->persist($template);

                ++$results['synced'];

                match ($status) {
                    'APPROVED' => ++$results['approved'],
                    'REJECTED' => ++$results['rejected'],
                    default    => ++$results['pending'],
                };
            }

            $this->entityManager->flush();

            // Handle pagination
            $url = $data['paging']['next'] ?? null;
        } while (null !== $url);

        return $results;
    }

    /**
     * @return WhatsAppTemplate[]
     */
    public function getApprovedTemplates(): array
    {
        /** @var WhatsAppTemplateRepository $repository */
        $repository = $this->entityManager->getRepository(WhatsAppTemplate::class);

        return $repository->findApproved();
    }

    /**
     * Submit a new template to Meta Graph API.
     *
     * @param array<int, array<string, mixed>> $components
     *
     * @return array<string, mixed>
     */
    public function submitTemplate(
        string $name,
        string $category,
        string $language,
        array $components,
    ): array {
        $businessAccountId = $this->configuration->getBusinessAccountId();
        $accessToken       = $this->configuration->getAccessToken();

        $url = sprintf(
            '%s/%s/%s/message_templates',
            self::BASE_URL,
            self::API_VERSION,
            $businessAccountId
        );

        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Authorization' => 'Bearer '.$accessToken,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'name'       => $name,
                'category'   => $category,
                'language'   => $language,
                'components' => $components,
            ],
        ]);

        $statusCode = $response->getStatusCode();
        $body       = $response->toArray(false);

        if ($statusCode >= 200 && $statusCode < 300) {
            // Store the newly submitted template locally
            $template = new WhatsAppTemplate();
            $template->setMetaTemplateId((string) ($body['id'] ?? ''))
                ->setName($name)
                ->setStatus($body['status'] ?? 'PENDING')
                ->setLanguage($language)
                ->setCategory($category)
                ->setComponents($components)
                ->setLastSyncedAt(new \DateTime());

            $this->entityManager->persist($template);
            $this->entityManager->flush();

            $this->logger->info('WhatsApp template submitted successfully', ['name' => $name]);

            return $body;
        }

        $errorMessage = $body['error']['message'] ?? 'Unknown API error (HTTP '.$statusCode.')';
        $this->logger->warning('WhatsApp template submission failed: '.$errorMessage, ['response' => $body]);

        throw new \RuntimeException('Failed to submit WhatsApp template: '.$errorMessage);
    }
}
