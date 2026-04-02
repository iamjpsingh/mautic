<?php

declare(strict_types=1);

namespace Mautic\WhatsAppBundle;

/**
 * Events available for WhatsAppBundle.
 */
final class WhatsAppEvents
{
    /**
     * The mautic.whatsapp_token_replacement event is thrown right before the content is returned.
     *
     * The event listener receives a
     * Mautic\CoreBundle\Event\TokenReplacementEvent instance.
     */
    public const TOKEN_REPLACEMENT = 'mautic.whatsapp_token_replacement';

    /**
     * The mautic.whatsapp_on_send event is thrown when a WhatsApp message is sent.
     *
     * The event listener receives a
     * Mautic\WhatsAppBundle\Event\WhatsAppSendEvent instance.
     */
    public const WHATSAPP_ON_SEND = 'mautic.whatsapp_on_send';

    /**
     * The mautic.whatsapp_pre_save event is thrown right before a WhatsApp message entity is persisted.
     *
     * The event listener receives a
     * Mautic\WhatsAppBundle\Event\WhatsAppEvent instance.
     */
    public const WHATSAPP_PRE_SAVE = 'mautic.whatsapp_pre_save';

    /**
     * The mautic.whatsapp_post_save event is thrown right after a WhatsApp message entity is persisted.
     *
     * The event listener receives a
     * Mautic\WhatsAppBundle\Event\WhatsAppEvent instance.
     */
    public const WHATSAPP_POST_SAVE = 'mautic.whatsapp_post_save';

    /**
     * The mautic.whatsapp_pre_delete event is thrown prior to when a WhatsApp message entity is deleted.
     *
     * The event listener receives a
     * Mautic\WhatsAppBundle\Event\WhatsAppEvent instance.
     */
    public const WHATSAPP_PRE_DELETE = 'mautic.whatsapp_pre_delete';

    /**
     * The mautic.whatsapp_post_delete event is thrown after a WhatsApp message entity is deleted.
     *
     * The event listener receives a
     * Mautic\WhatsAppBundle\Event\WhatsAppEvent instance.
     */
    public const WHATSAPP_POST_DELETE = 'mautic.whatsapp_post_delete';

    /**
     * The mautic.whatsapp.on_campaign_trigger_action event is fired when a campaign action triggers
     * sending a WhatsApp template or session message.
     *
     * The event listener receives a
     * Mautic\CampaignBundle\Event\CampaignExecutionEvent instance.
     */
    public const ON_CAMPAIGN_TRIGGER_ACTION = 'mautic.whatsapp.on_campaign_trigger_action';

    /**
     * The mautic.whatsapp.on_campaign_trigger_action_template event is fired when a campaign action
     * triggers sending a WhatsApp template message specifically.
     *
     * The event listener receives a
     * Mautic\CampaignBundle\Event\CampaignExecutionEvent instance.
     */
    public const ON_CAMPAIGN_TRIGGER_ACTION_TEMPLATE = 'mautic.whatsapp.on_campaign_trigger_action_template';

    /**
     * The mautic.whatsapp.on_campaign_trigger_decision event is fired when a campaign decision
     * evaluates a WhatsApp reply.
     *
     * The event listener receives a
     * Mautic\CampaignBundle\Event\DecisionEvent instance.
     */
    public const ON_CAMPAIGN_TRIGGER_DECISION = 'mautic.whatsapp.on_campaign_trigger_decision';

    /**
     * The mautic.whatsapp.on_reply event is dispatched when a WhatsApp reply is received via webhook.
     *
     * The event listener receives a
     * Mautic\WhatsAppBundle\Event\WhatsAppReplyEvent instance.
     */
    public const WHATSAPP_ON_REPLY = 'mautic.whatsapp.on_reply';

    /**
     * The mautic.whatsapp.on_campaign_reply event is dispatched when a WhatsApp reply
     * campaign decision is processed.
     *
     * The event listener receives a
     * Mautic\WhatsAppBundle\Event\WhatsAppReplyEvent instance.
     */
    public const ON_CAMPAIGN_REPLY = 'mautic.whatsapp.on_campaign_reply';

    /**
     * The mautic.whatsapp.on_delivery event is dispatched when a delivery status webhook is received.
     *
     * The event listener receives a
     * Mautic\WhatsAppBundle\Event\WhatsAppDeliveryEvent instance.
     */
    public const WHATSAPP_ON_DELIVERY = 'mautic.whatsapp.on_delivery';

    /**
     * The mautic.whatsapp.on_tokens_build event is dispatched when contact token generation is built.
     *
     * The event listener receives a
     * Mautic\WhatsAppBundle\Event\TokensBuildEvent instance.
     */
    public const ON_WHATSAPP_TOKENS_BUILD = 'mautic.whatsapp.on_tokens_build';
}
