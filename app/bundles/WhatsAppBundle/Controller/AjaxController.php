<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle\Controller;

use Mautic\CoreBundle\Controller\AjaxController as CommonAjaxController;
use Mautic\CoreBundle\Controller\AjaxLookupControllerTrait;
use Mautic\CoreBundle\Helper\CacheStorageHelper;
use Mautic\WhatsAppBundle\Broadcast\BroadcastQuery;
use Mautic\WhatsAppBundle\Model\WhatsAppModel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class AjaxController extends CommonAjaxController
{
    use AjaxLookupControllerTrait;

    public function getWhatsAppCountStatsAction(Request $request, BroadcastQuery $broadcastQuery, CacheStorageHelper $cacheStorageHelper): JsonResponse
    {
        /** @var WhatsAppModel $model */
        $model = $this->getModel('whatsapp');

        $id  = $request->get('id');
        $ids = $request->query->all()['ids'] ?? [];

        // Support for legacy calls
        if (!$ids && $id) {
            $ids = [$id];
        }

        $data = [];
        foreach ($ids as $id) {
            if ($message = $model->getEntity($id)) {
                if ('list' !== $message->getMessageType()) {
                    continue;
                }

                $pending = $broadcastQuery->getPendingCount($message);
                $cacheStorageHelper->set(sprintf('%s|%s|%s', 'whatsapp', $message->getId(), 'pending'), $pending);
                if (!$pending) {
                    continue;
                }
                $data[] = [
                    'id'          => $id,
                    'pending'     => $this->translator->trans(
                        'mautic.whatsapp.stat.leadcount',
                        ['%count%' => $pending]
                    ),
                ];
            }
        }

        // Support for legacy calls
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
}
