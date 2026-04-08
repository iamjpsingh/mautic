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
};
