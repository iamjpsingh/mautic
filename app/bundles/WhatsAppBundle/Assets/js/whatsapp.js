/**
 * WhatsApp message form JS helpers.
 *
 * @author iamjpsingh
 */
Mautic.whatsappMessageTypeChanged = function(el) {
    var messageType = mQuery(el).val();

    // Toggle message-type sections
    mQuery('#whatsapp-template-section').addClass('hide');
    mQuery('#whatsapp-session-section').addClass('hide');
    mQuery('#whatsapp-media-section').addClass('hide');
    mQuery('#whatsapp-interactive-section').addClass('hide');

    if (messageType === 'template') {
        mQuery('#whatsapp-template-section').removeClass('hide');
        mQuery('#leadList').addClass('hide');
    } else if (messageType === 'session') {
        mQuery('#whatsapp-session-section').removeClass('hide');
        mQuery('#leadList').removeClass('hide');
    } else if (messageType === 'media') {
        mQuery('#whatsapp-media-section').removeClass('hide');
        mQuery('#leadList').removeClass('hide');
    } else if (messageType === 'interactive') {
        mQuery('#whatsapp-interactive-section').removeClass('hide');
        mQuery('#leadList').removeClass('hide');
    }
};

Mautic.whatsappOnLoad = function(container) {
    // Character counter for session message body
    var messageField = mQuery('#whatsapp_message');
    if (messageField.length) {
        var updateCounter = function() {
            var len = messageField.val().length;
            mQuery('#whatsapp_nb_char').text(len);
        };
        messageField.on('keyup change', updateCounter);
        updateCounter();
    }

    // Initialize template preview and placeholder mapping
    if (typeof Mautic.whatsappTemplateOnLoad === 'function') {
        Mautic.whatsappTemplateOnLoad(container);
    }

    if (mQuery(container + ' #list-search').length) {
        Mautic.activateSearchAutocomplete('list-search', 'whatsapp');
    }
};

/**
 * Replace the whatsappId placeholder in the edit URL with the currently selected WhatsApp message ID.
 */
Mautic.standardWhatsAppUrl = function(options) {
    if (!options) {
        return;
    }

    var url = options.windowUrl;
    if (url) {
        var editKey = '/whatsapp/edit/whatsappId';
        if (url.indexOf(editKey) > -1) {
            options.windowUrl = url.replace('whatsappId', mQuery('#campaignevent_properties_whatsapp').val());
        }
    }

    return options;
};

/**
 * Enable/disable the edit button when the WhatsApp message dropdown changes.
 */
Mautic.disabledWhatsAppAction = function(opener) {
    if (typeof opener == 'undefined') {
        opener = window;
    }

    var whatsapp = opener.mQuery('#campaignevent_properties_whatsapp').val();

    var disabled = whatsapp === '' || whatsapp === null;

    opener.mQuery('#campaignevent_properties_editWhatsAppButton').prop('disabled', disabled);
    opener.mQuery('#campaignevent_properties_editWhatsAppTemplateButton').prop('disabled', disabled);
};
