<?php

declare(strict_types=1);

return [
    'services' => [
        'helpers' => [
            'mautic.helper.whatsapp' => [
                'class'     => Mautic\WhatsAppBundle\Helper\WhatsAppHelper::class,
                'arguments' => [
                    'doctrine.orm.entity_manager',
                    'mautic.lead.model.lead',
                    'mautic.helper.phone_number',
                    'mautic.whatsapp.model.whatsapp',
                    'mautic.helper.integration',
                    'mautic.lead.model.dnc',
                    'mautic.helper.core_parameters',
                ],
                'alias' => 'whatsapp_helper',
            ],
        ],
        'other' => [
            'mautic.whatsapp.transport_chain' => [
                'class'     => Mautic\WhatsAppBundle\WhatsApp\TransportChain::class,
                'arguments' => [
                    '%mautic.whatsapp_transport%',
                    'mautic.helper.integration',
                ],
            ],
            'mautic.whatsapp.callback_handler_container' => [
                'class' => Mautic\WhatsAppBundle\Callback\HandlerContainer::class,
            ],
            'mautic.whatsapp.helper.contact' => [
                'class'     => Mautic\WhatsAppBundle\Helper\ContactHelper::class,
                'arguments' => [
                    'mautic.lead.repository.lead',
                    'doctrine.dbal.default_connection',
                    'mautic.helper.phone_number',
                ],
            ],
            'mautic.whatsapp.helper.reply' => [
                'class'     => Mautic\WhatsAppBundle\Helper\ReplyHelper::class,
            ],
            'mautic.whatsapp.broadcast.executioner' => [
                'class'     => Mautic\WhatsAppBundle\Broadcast\BroadcastExecutioner::class,
                'arguments' => [
                    'mautic.whatsapp.model.whatsapp',
                    'mautic.whatsapp.broadcast.query',
                    'translator',
                    'mautic.lead.repository.lead',
                ],
            ],
            'mautic.whatsapp.broadcast.query' => [
                'class'     => Mautic\WhatsAppBundle\Broadcast\BroadcastQuery::class,
                'arguments' => [
                    'doctrine.orm.entity_manager',
                    'mautic.whatsapp.model.whatsapp',
                ],
            ],
        ],
        'integrations' => [
            'mautic.integration.whatsapp_cloud' => [
                'class'     => Mautic\WhatsAppBundle\Integration\WhatsAppCloudIntegration::class,
                'arguments' => [
                    'event_dispatcher',
                    'mautic.helper.cache_storage',
                    'doctrine.orm.entity_manager',
                    'request_stack',
                    'router',
                    'translator',
                    'monolog.logger.mautic',
                    'mautic.helper.encryption',
                    'mautic.lead.model.lead',
                    'mautic.lead.model.company',
                    'mautic.helper.paths',
                    'mautic.core.model.notification',
                    'mautic.lead.model.field',
                    'mautic.plugin.model.integration_entity',
                    'mautic.lead.model.dnc',
                    'mautic.lead.field.fields_with_unique_identifier',
                ],
            ],
        ],
    ],
    'routes' => [
        'main' => [
            'mautic_whatsapp_index' => [
                'path'       => '/whatsapp/{page}',
                'controller' => 'Mautic\WhatsAppBundle\Controller\WhatsAppController::indexAction',
            ],
            'mautic_whatsapp_action' => [
                'path'       => '/whatsapp/{objectAction}/{objectId}',
                'controller' => 'Mautic\WhatsAppBundle\Controller\WhatsAppController::executeAction',
            ],
            'mautic_whatsapp_contacts' => [
                'path'       => '/whatsapp/view/{objectId}/contact/{page}',
                'controller' => 'Mautic\WhatsAppBundle\Controller\WhatsAppController::contactsAction',
            ],
        ],
        'public' => [
            'mautic_whatsapp_webhook_callback' => [
                'path'       => '/whatsapp/{transport}/callback',
                'controller' => 'Mautic\WhatsAppBundle\Controller\WebhookController::callbackAction',
                'methods'    => ['GET', 'POST'],
            ],
            'mautic_whatsapp_webhook_verify' => [
                'path'       => '/whatsapp/webhook/verify',
                'controller' => 'Mautic\WhatsAppBundle\Controller\WebhookController::verifyAction',
                'methods'    => ['GET'],
            ],
        ],
        'api' => [
            'mautic_api_whatsappstandard' => [
                'standard_entity' => true,
                'name'            => 'whatsapp',
                'path'            => '/whatsapp',
                'controller'      => Mautic\WhatsAppBundle\Controller\Api\WhatsAppApiController::class,
            ],
            'mautic_api_whatsapp_send' => [
                'path'       => '/whatsapp/{id}/contact/{contactId}/send',
                'controller' => 'Mautic\WhatsAppBundle\Controller\Api\WhatsAppApiController::sendAction',
                'methods'    => ['POST'],
            ],
            'mautic_api_whatsapp_send_template' => [
                'path'       => '/whatsapp/{id}/contact/{contactId}/send-template',
                'controller' => 'Mautic\WhatsAppBundle\Controller\Api\WhatsAppApiController::sendTemplateAction',
                'methods'    => ['POST'],
            ],
        ],
    ],
    'menu' => [
        'main' => [
            'items' => [
                'mautic.whatsapp.messages' => [
                    'route'  => 'mautic_whatsapp_index',
                    'access' => ['whatsapp:messages:viewown', 'whatsapp:messages:viewother'],
                    'parent' => 'mautic.core.channels',
                    'checks' => [
                        'integration' => [
                            'WhatsAppCloud' => [
                                'enabled' => true,
                            ],
                        ],
                    ],
                    'priority' => 65,
                ],
            ],
        ],
    ],
    'categories' => [
        'whatsapp' => null,
    ],
    'parameters' => [
        'whatsapp_enabled'              => false,
        'whatsapp_phone_number_id'      => null,
        'whatsapp_business_account_id'  => null,
        'whatsapp_access_token'         => null,
        'whatsapp_webhook_verify_token' => null,
        'whatsapp_frequency_number'     => 0,
        'whatsapp_frequency_time'       => 'DAY',
        'whatsapp_transport'            => 'mautic.whatsapp.cloud.transport',
    ],
];
