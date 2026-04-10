<?php

declare(strict_types=1);

return [
    'services' => [
        'other' => [
            'mautic.whatsapp.transport_chain' => [
                'class'     => Mautic\WhatsAppBundle\WhatsApp\TransportChain::class,
                'arguments' => [
                    '%mautic.whatsapp_transport%',
                    'mautic.helper.core_parameters',
                ],
            ],
            'mautic.whatsapp.cloud.transport' => [
                'class'     => Mautic\WhatsAppBundle\Integration\MetaCloud\MetaCloudTransport::class,
                'arguments' => [
                    'mautic.whatsapp.cloud.configuration',
                    'http_client',
                    'monolog.logger.mautic',
                ],
                'tag'          => 'mautic.whatsapp_transport',
                'tagArguments' => [
                    'integrationAlias' => 'MetaWhatsApp',
                ],
            ],
            'mautic.whatsapp.cloud.configuration' => [
                'class'     => Mautic\WhatsAppBundle\Integration\MetaCloud\Configuration::class,
                'arguments' => [
                    'mautic.helper.core_parameters',
                ],
            ],
            'mautic.whatsapp.cloud.callback' => [
                'class'     => Mautic\WhatsAppBundle\Integration\MetaCloud\MetaCloudCallback::class,
                'arguments' => [
                    'mautic.whatsapp.helper.contact',
                    'mautic.whatsapp.cloud.configuration',
                    'monolog.logger.mautic',
                ],
                'tag' => 'mautic.whatsapp_callback_handler',
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
        ],
    ],
    'routes' => [
        'main' => [
            'mautic_whatsapp_index' => [
                'path'       => '/whatsapp/{page}',
                'controller' => 'Mautic\WhatsAppBundle\Controller\WhatsAppController::indexAction',
            ],
            'mautic_whatsapp_templates' => [
                'path'       => '/whatsapp/templates/{page}',
                'controller' => 'Mautic\WhatsAppBundle\Controller\WhatsAppController::templatesAction',
            ],
            'mautic_whatsapp_send_to_contact_select' => [
                'path'       => '/whatsapp/send/contact/{contactId}',
                'controller' => 'Mautic\WhatsAppBundle\Controller\WhatsAppController::sendToContactSelectAction',
            ],
            'mautic_whatsapp_send_to_contact' => [
                'path'       => '/whatsapp/{objectId}/send/{contactId}',
                'controller' => 'Mautic\WhatsAppBundle\Controller\WhatsAppController::sendToContactAction',
            ],
            'mautic_whatsapp_create_template' => [
                'path'       => '/whatsapp/templates/create',
                'controller' => 'Mautic\WhatsAppBundle\Controller\WhatsAppController::createTemplateAction',
            ],
            'mautic_whatsapp_view_template' => [
                'path'       => '/whatsapp/templates/view/{id}',
                'controller' => 'Mautic\WhatsAppBundle\Controller\WhatsAppController::viewTemplateAction',
                'requirements' => [
                    'id' => '\d+',
                ],
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
                    'priority' => 65,
                ],
                'mautic.whatsapp.templates.menu' => [
                    'route'  => 'mautic_whatsapp_templates',
                    'access' => ['whatsapp:messages:viewown', 'whatsapp:messages:viewother'],
                    'parent' => 'mautic.core.channels',
                    'priority' => 64,
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
        'whatsapp_app_secret'           => null,
        'whatsapp_frequency_number'     => 0,
        'whatsapp_frequency_time'       => 'DAY',
        'whatsapp_transport'            => 'mautic.whatsapp.cloud.transport',
    ],
];
