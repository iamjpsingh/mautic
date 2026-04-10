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
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

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
     * Test the WhatsApp webhook by issuing a self-request to the public verify URL.
     * This catches cases where Cloudflare / DNS / SSL prevents Meta from reaching us.
     */
    public function testWebhookAction(
        Request $request,
        CoreParametersHelper $coreParametersHelper,
        RouterInterface $router,
        HttpClientInterface $httpClient,
        WebhookStatusTracker $statusTracker,
        \Psr\Log\LoggerInterface $logger,
    ): JsonResponse {
        $logger->info('WhatsApp: testWebhookAction invoked');

        $verifyToken = (string) $coreParametersHelper->get('whatsapp_webhook_verify_token');

        if ('' === $verifyToken) {
            return new JsonResponse([
                'status'  => 'broken',
                'reason'  => 'No webhook verify token is configured. Enter one above and save.',
                'details' => null,
                'state'   => $statusTracker->getState(false),
            ]);
        }

        // Prefer site_url (canonical public URL) so we test what Meta actually reaches.
        $siteUrl = trim((string) $coreParametersHelper->get('site_url'), '/');

        if ('' !== $siteUrl) {
            $webhookUrl = $siteUrl.'/whatsapp/meta_cloud/callback';
        } else {
            $scheme     = $request->getScheme();
            $host       = $request->getHttpHost();
            $basePath   = $request->getBaseUrl();
            $path       = $router->generate('mautic_whatsapp_webhook_callback', ['transport' => 'meta_cloud']);
            $webhookUrl = $scheme.'://'.$host.$basePath.$path;
        }

        // Meta-style challenge
        $challenge = 'mautic_selftest_'.bin2hex(random_bytes(8));
        $testUrl   = $webhookUrl.'?hub_mode=subscribe&hub_verify_token='.urlencode($verifyToken).'&hub_challenge='.urlencode($challenge);

        $logger->info('WhatsApp: test webhook url built', ['url' => $webhookUrl]);

        try {
            $response = $httpClient->request('GET', $testUrl, [
                'timeout'           => 15,
                'max_redirects'     => 3,
                'verify_peer'       => false,
                'verify_host'       => false,
                'headers'           => [
                    'User-Agent' => 'Mautic-WhatsApp-SelfTest/1.0',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $body       = $response->getContent(false);

            $logger->info('WhatsApp: test webhook response', [
                'status' => $statusCode,
                'body'   => substr($body, 0, 500),
            ]);

            if (200 === $statusCode && trim($body) === $challenge) {
                return new JsonResponse([
                    'status'  => 'ok',
                    'reason'  => 'Webhook is reachable and returned the correct challenge.',
                    'details' => [
                        'url'         => $webhookUrl,
                        'status_code' => $statusCode,
                    ],
                    'state' => $statusTracker->getState(true),
                ]);
            }

            if (200 === $statusCode) {
                return new JsonResponse([
                    'status'  => 'broken',
                    'reason'  => 'Webhook returned 200 but the challenge response did not match. Check the response body below — another app may be intercepting, or Mautic is returning an HTML error page.',
                    'details' => [
                        'url'              => $webhookUrl,
                        'status_code'      => $statusCode,
                        'expected'         => $challenge,
                        'received_preview' => substr($body, 0, 500),
                    ],
                    'state' => $statusTracker->getState(true),
                ]);
            }

            return new JsonResponse([
                'status'  => 'broken',
                'reason'  => sprintf('Webhook returned HTTP %d. This usually means Cloudflare/WAF is blocking the request, or the URL is wrong.', $statusCode),
                'details' => [
                    'url'         => $webhookUrl,
                    'status_code' => $statusCode,
                    'body'        => substr($body, 0, 500),
                ],
                'state' => $statusTracker->getState(true),
            ]);
        } catch (\Throwable $e) {
            $logger->error('WhatsApp: test webhook threw exception', [
                'exception' => $e::class,
                'message'   => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            $message = $e->getMessage();
            if ('' === $message) {
                $message = $e::class.' (no message)';
            }

            return new JsonResponse([
                'status'  => 'broken',
                'reason'  => 'Could not reach the webhook URL: '.$message,
                'details' => [
                    'url'       => $webhookUrl,
                    'test_url'  => $testUrl,
                    'exception' => $e::class,
                    'message'   => $message,
                ],
                'state' => $statusTracker->getState(true),
            ]);
        }
    }
}
