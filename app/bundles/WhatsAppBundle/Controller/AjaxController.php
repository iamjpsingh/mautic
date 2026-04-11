<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Controller;

use Mautic\CoreBundle\Controller\AjaxController as CommonAjaxController;
use Mautic\CoreBundle\Controller\AjaxLookupControllerTrait;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\WhatsAppBundle\Model\WhatsAppModel;
use Mautic\WhatsAppBundle\Service\WebhookStatusTracker;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class AjaxController extends CommonAjaxController
{
    use AjaxLookupControllerTrait;

    /**
     * Return template data for the preview panel (AJAX).
     */
    public function templatePreviewAction(Request $request): JsonResponse
    {
        $templateId = (int) $request->get('templateId');

        if (!$templateId) {
            return new JsonResponse(['error' => 'Missing templateId'], 400);
        }

        /** @var WhatsAppModel $model */
        $model    = $this->getModel('whatsapp');
        $template = $model->findTemplate($templateId);

        if (!$template) {
            return new JsonResponse(['error' => 'Template not found'], 404);
        }

        $components = $template->getComponents() ?? [];
        $body       = '';
        $header     = '';
        $footer     = '';
        $buttons    = [];

        foreach ($components as $component) {
            $type = strtoupper($component['type'] ?? '');

            switch ($type) {
                case 'BODY':
                    $body = $component['text'] ?? '';
                    break;
                case 'HEADER':
                    $header = $component['text'] ?? '';
                    break;
                case 'FOOTER':
                    $footer = $component['text'] ?? '';
                    break;
                case 'BUTTONS':
                    $buttons = $component['buttons'] ?? [];
                    break;
            }
        }

        return new JsonResponse([
            'name'       => $template->getName(),
            'category'   => $template->getCategory(),
            'language'   => $template->getLanguage(),
            'components' => $components,
            'body'       => $body,
            'header'     => $header,
            'footer'     => $footer,
            'buttons'    => $buttons,
        ]);
    }

    public function getWhatsAppCountStatsAction(Request $request): JsonResponse
    {
        /** @var WhatsAppModel $model */
        $model = $this->getModel('whatsapp');

        $id  = $request->get('id');
        $ids = $request->query->all()['ids'] ?? [];

        if (!$ids && $id) {
            $ids = [$id];
        }

        $data = [];
        foreach ($ids as $id) {
            if ($message = $model->getEntity($id)) {
                $data[] = [
                    'id'        => $id,
                    'sent'      => $message->getSentCount(),
                    'delivered' => $message->getDeliveredCount(),
                    'read'      => $message->getReadCount(),
                ];
            }
        }

        if ($request->get('id')) {
            $data = $data[0] ?? [];
        } else {
            $data = [
                'success' => 1,
                'stats'   => $data,
            ];
        }

        return new JsonResponse($data);
    }

    /**
     * Test the WhatsApp webhook configuration and report the current tracker state.
     *
     * NOTE: This does NOT make a self-request anymore. A self-request (PHP → HTTPS →
     * Cloudflare → tunnel → same PHP-FPM pool) causes a worker deadlock because the
     * round-trip is served by the same worker pool that's waiting for the response.
     *
     * Instead, we rely on the WebhookStatusTracker which records REAL events from Meta
     * (verification GETs and delivery POSTs). The tracker reflects ground truth.
     */
    public function testWebhookAction(
        Request $request,
        CoreParametersHelper $coreParametersHelper,
        WebhookStatusTracker $statusTracker,
        \Psr\Log\LoggerInterface $logger,
    ): JsonResponse {
        $logger->info('WhatsApp: testWebhookAction invoked');

        $verifyToken     = (string) $coreParametersHelper->get('whatsapp_webhook_verify_token');
        $siteUrl         = trim((string) $coreParametersHelper->get('site_url'), '/');
        $whatsappEnabled = (bool) $coreParametersHelper->get('whatsapp_enabled');
        $tokenSet        = '' !== $verifyToken;
        $state           = $statusTracker->getState($tokenSet);

        // Configuration validation
        $issues = [];
        if (!$whatsappEnabled) {
            $issues[] = 'WhatsApp is not enabled in system configuration';
        }
        if (!$tokenSet) {
            $issues[] = 'Webhook verify token is not set';
        }
        if ('' === $siteUrl) {
            $issues[] = 'Site URL is not set (required for webhook URL to be public)';
        }

        if (!empty($issues)) {
            return new JsonResponse([
                'status'  => 'broken',
                'reason'  => 'Configuration incomplete: '.implode('; ', $issues),
                'details' => [
                    'issues'      => $issues,
                    'webhook_url' => '' !== $siteUrl ? $siteUrl.'/whatsapp/meta_cloud/callback' : '(site_url not set)',
                ],
                'state' => $state,
            ]);
        }

        $webhookUrl = $siteUrl.'/whatsapp/meta_cloud/callback';

        // Report based on actual tracked state from Meta webhook events
        switch ($state['status']) {
            case 'active':
                return new JsonResponse([
                    'status'  => 'ok',
                    'reason'  => sprintf(
                        'Webhook is active and healthy. Last event received: %s. Total events received: %d.',
                        $state['last_received_at'] ?? 'never',
                        $state['receive_count']
                    ),
                    'details' => [
                        'webhook_url'      => $webhookUrl,
                        'last_verified_at' => $state['last_verified_at'],
                        'last_received_at' => $state['last_received_at'],
                        'events_received'  => $state['receive_count'],
                    ],
                    'state' => $state,
                ]);

            case 'idle':
                return new JsonResponse([
                    'status'  => 'ok',
                    'reason'  => sprintf(
                        'Webhook is verified by Meta. Last verified: %s. No events in last 24h — this is normal if no messages have been sent recently.',
                        $state['last_verified_at'] ?? 'unknown'
                    ),
                    'details' => [
                        'webhook_url'      => $webhookUrl,
                        'last_verified_at' => $state['last_verified_at'],
                        'last_received_at' => $state['last_received_at'],
                    ],
                    'state' => $state,
                ]);

            case 'pending':
                return new JsonResponse([
                    'status'  => 'broken',
                    'reason'  => 'Configuration is complete but Meta has not verified the webhook yet. In Meta Developer Dashboard → WhatsApp → Configuration → Webhook, paste the URL and verify token below, then click "Verify and save".',
                    'details' => [
                        'webhook_url'  => $webhookUrl,
                        'verify_token' => $verifyToken,
                        'next_step'    => 'Copy the URL and token to Meta Dashboard and click Verify and save',
                    ],
                    'state' => $state,
                ]);

            case 'error':
                return new JsonResponse([
                    'status'  => 'broken',
                    'reason'  => 'Last webhook attempt from Meta failed: '.($state['last_error'] ?? 'unknown error'),
                    'details' => [
                        'webhook_url'   => $webhookUrl,
                        'last_error'    => $state['last_error'],
                        'last_error_at' => $state['last_error_at'],
                    ],
                    'state' => $state,
                ]);

            default:
                return new JsonResponse([
                    'status'  => 'broken',
                    'reason'  => 'Webhook is not configured.',
                    'details' => ['webhook_url' => $webhookUrl],
                    'state'   => $state,
                ]);
        }
    }
}
