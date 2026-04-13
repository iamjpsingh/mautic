/**
 * WhatsApp bundle — form helpers, template preview, and placeholder mapping.
 *
 * @author iamjpsingh
 */

// ============================================================================
// Helpers
// ============================================================================

Mautic.whatsappEscapeHtml = function (str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
};

// ============================================================================
// Form section switching
// ============================================================================

Mautic.whatsappMessageTypeChanged = function (el) {
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

// ============================================================================
// Template preview (WhatsApp-style green bubble)
// ============================================================================

Mautic.whatsappLoadTemplatePreview = function (templateId) {
    var previewPanel = mQuery('#whatsapp-template-preview-panel');
    var mappingPanel = mQuery('#whatsapp-placeholder-mapping');

    if (!templateId) {
        previewPanel.addClass('hide');
        mappingPanel.addClass('hide');
        return;
    }

    mQuery.ajax({
        url: mauticAjaxUrl + '?action=whatsapp:templatePreview&templateId=' + templateId,
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            if (!response || response.error) {
                previewPanel.addClass('hide');
                mappingPanel.addClass('hide');
                return;
            }

            Mautic.whatsappRenderPreview(response);
            Mautic.whatsappBuildPlaceholderMapping(response);
        },
        error: function () {
            previewPanel.addClass('hide');
            mappingPanel.addClass('hide');
        }
    });
};

Mautic.whatsappRenderPreview = function (data) {
    var panel = mQuery('#whatsapp-template-preview-panel');

    // Category badge
    if (data.category) {
        mQuery('#preview-category-badge').text(data.category).show();
    } else {
        mQuery('#preview-category-badge').hide();
    }

    // Header
    if (data.header) {
        mQuery('#preview-header-text').text(data.header);
        mQuery('#preview-header').removeClass('hide');
    } else {
        mQuery('#preview-header').addClass('hide');
    }

    // Body
    mQuery('#preview-body-text').text(data.body || '');

    // Footer
    if (data.footer) {
        mQuery('#preview-footer-text').text(data.footer);
        mQuery('#preview-footer').removeClass('hide');
    } else {
        mQuery('#preview-footer').addClass('hide');
    }

    // Timestamp
    var now = new Date();
    var hours = now.getHours();
    var mins = now.getMinutes();
    mQuery('#preview-timestamp').text(
        (hours < 10 ? '0' : '') + hours + ':' + (mins < 10 ? '0' : '') + mins
    );

    // Buttons (WhatsApp-style)
    if (data.buttons && data.buttons.length > 0) {
        var buttonHtml = '';
        for (var i = 0; i < data.buttons.length; i++) {
            var btn = data.buttons[i];
            var icon = '<i class="ri-reply-line"></i> ';
            if (btn.type === 'URL') icon = '<i class="ri-external-link-line"></i> ';
            if (btn.type === 'PHONE_NUMBER') icon = '<i class="ri-phone-line"></i> ';
            buttonHtml += '<div style="background:#fff;border-radius:8px;padding:8px;text-align:center;margin-top:4px;box-shadow:0 1px 1px rgba(0,0,0,.13);">' +
                '<span style="color:#00a5f4;font-size:13px;font-weight:500;">' +
                icon + Mautic.whatsappEscapeHtml(btn.text || btn.type || 'Button') + '</span></div>';
        }
        mQuery('#preview-buttons-list').html(buttonHtml);
        mQuery('#preview-buttons').removeClass('hide');
    } else {
        mQuery('#preview-buttons').addClass('hide');
    }

    // Auto-fill hidden template name/language fields for form submission
    if (data.name) {
        mQuery('#whatsapp_templateName').val(data.name);
    }
    if (data.language) {
        mQuery('#whatsapp_templateLanguage').val(data.language);
    }

    panel.removeClass('hide');
};

// ============================================================================
// Placeholder mapping
// ============================================================================

Mautic.whatsappBuildPlaceholderMapping = function (data) {
    var mappingPanel = mQuery('#whatsapp-placeholder-mapping');
    var rowsContainer = mQuery('#placeholder-mapping-rows');
    rowsContainer.empty();

    // Collect all {{N}} from body and header
    var allText = (data.body || '') + ' ' + (data.header || '');
    var regex = /\{\{(\d+)\}\}/g;
    var match;
    var params = [];

    while ((match = regex.exec(allText)) !== null) {
        var paramNum = parseInt(match[1], 10);
        if (params.indexOf(paramNum) === -1) {
            params.push(paramNum);
        }
    }

    if (params.length === 0) {
        mappingPanel.addClass('hide');
        return;
    }

    params.sort(function (a, b) { return a - b; });

    // Load existing mappings if any
    var existingMappings = {};
    try {
        var stored = mQuery('#whatsapp_templateComponents').val();
        if (stored) {
            var parsed = JSON.parse(stored);
            if (Array.isArray(parsed) && parsed.length > 0 && parsed[0].param !== undefined) {
                for (var i = 0; i < parsed.length; i++) {
                    existingMappings[parsed[i].param] = parsed[i].token || '';
                }
            }
        }
    } catch (e) {
        // ignore
    }

    for (var i = 0; i < params.length; i++) {
        var paramNum = params[i];
        var existingToken = existingMappings[paramNum] || '';
        rowsContainer.append(Mautic.whatsappBuildMappingRow(paramNum, existingToken));
    }

    // Bind change events for source dropdowns
    rowsContainer.find('.param-source').on('change', function () {
        var paramIdx = mQuery(this).data('param');
        var customInput = rowsContainer.find('.param-custom-value[data-param="' + paramIdx + '"]');
        if (mQuery(this).val() === '__custom__') {
            customInput.removeClass('hide');
        } else {
            customInput.addClass('hide');
        }
    });

    mappingPanel.removeClass('hide');
};

Mautic.whatsappBuildMappingRow = function (paramNum, existingToken) {
    var isCustom = existingToken && existingToken.indexOf('{') !== 0;
    var selectedValue = existingToken || '';

    if (isCustom && existingToken) {
        selectedValue = '__custom__';
    }

    var selectOptions = '<option value="">-- Select source --</option>' +
        '<optgroup label="Contact Fields">' +
        '<option value="{contactfield=firstname}"' + (selectedValue === '{contactfield=firstname}' ? ' selected' : '') + '>First Name</option>' +
        '<option value="{contactfield=lastname}"' + (selectedValue === '{contactfield=lastname}' ? ' selected' : '') + '>Last Name</option>' +
        '<option value="{contactfield=email}"' + (selectedValue === '{contactfield=email}' ? ' selected' : '') + '>Email</option>' +
        '<option value="{contactfield=phone}"' + (selectedValue === '{contactfield=phone}' ? ' selected' : '') + '>Phone</option>' +
        '<option value="{contactfield=company}"' + (selectedValue === '{contactfield=company}' ? ' selected' : '') + '>Company</option>' +
        '<option value="{contactfield=city}"' + (selectedValue === '{contactfield=city}' ? ' selected' : '') + '>City</option>' +
        '<option value="{contactfield=country}"' + (selectedValue === '{contactfield=country}' ? ' selected' : '') + '>Country</option>' +
        '</optgroup>' +
        '<optgroup label="Other">' +
        '<option value="__custom__"' + (selectedValue === '__custom__' ? ' selected' : '') + '>Custom Value</option>' +
        '<option value="{datetime=now}"' + (selectedValue === '{datetime=now}' ? ' selected' : '') + '>Current Date/Time</option>' +
        '</optgroup>';

    var customHideClass = isCustom ? '' : ' hide';
    var customValue = isCustom ? existingToken : '';

    return '<div class="row parameter-mapping" data-param="' + paramNum + '" style="margin-bottom:8px">' +
        '<div class="col-md-3">' +
        '<label>Parameter {{' + paramNum + '}}</label>' +
        '</div>' +
        '<div class="col-md-4">' +
        '<select class="form-control param-source" data-param="' + paramNum + '">' + selectOptions + '</select>' +
        '</div>' +
        '<div class="col-md-5">' +
        '<input type="text" class="form-control param-custom-value' + customHideClass + '" placeholder="Enter custom value" data-param="' + paramNum + '" value="' + Mautic.whatsappEscapeHtml(customValue) + '">' +
        '</div>' +
        '</div>';
};

Mautic.whatsappCollectMappings = function () {
    var mappings = [];
    mQuery('.parameter-mapping').each(function () {
        var paramNum = parseInt(mQuery(this).data('param'), 10);
        var source = mQuery(this).find('.param-source').val();
        var token = '';

        if (source === '__custom__') {
            token = mQuery(this).find('.param-custom-value').val() || '';
        } else {
            token = source || '';
        }

        if (token) {
            mappings.push({param: paramNum, token: token});
        }
    });

    if (mappings.length > 0) {
        mQuery('#whatsapp_templateComponents').val(JSON.stringify(mappings));
    }
};

// ============================================================================
// Initializer (called by Mautic when mauticContent === 'whatsapp')
// ============================================================================

Mautic.whatsappOnLoad = function (container) {
    // Character counter for session message body
    var messageField = mQuery('#whatsapp_message');
    if (messageField.length) {
        var updateCounter = function () {
            var len = messageField.val().length;
            mQuery('#whatsapp_nb_char').text(len);
        };
        messageField.on('keyup change', updateCounter);
        updateCounter();
    }

    // Bind template selector (works in both main page and modal popup)
    var templateSelect = mQuery('#whatsapp_templateId');
    if (templateSelect.length) {
        templateSelect.off('change.whatsapp').on('change.whatsapp', function () {
            Mautic.whatsappLoadTemplatePreview(mQuery(this).val());
        });

        // Bind form submit to collect mappings
        mQuery('form[name="whatsapp"]').off('submit.whatsapp').on('submit.whatsapp', function () {
            Mautic.whatsappCollectMappings();
        });

        // If a template is already selected, load its preview
        var currentVal = templateSelect.val();
        if (currentVal) {
            Mautic.whatsappLoadTemplatePreview(currentVal);
        }
    }

    if (typeof container === 'string' && mQuery(container + ' #list-search').length) {
        Mautic.activateSearchAutocomplete('list-search', 'whatsapp');
    }
};

// ============================================================================
// Campaign builder helpers
// ============================================================================

Mautic.standardWhatsAppUrl = function (options) {
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

Mautic.disabledWhatsAppAction = function (opener) {
    if (typeof opener == 'undefined') {
        opener = window;
    }

    var whatsapp = opener.mQuery('#campaignevent_properties_whatsapp').val();
    var disabled = whatsapp === '' || whatsapp === null;

    opener.mQuery('#campaignevent_properties_editWhatsAppButton').prop('disabled', disabled);
    opener.mQuery('#campaignevent_properties_editWhatsAppTemplateButton').prop('disabled', disabled);
};

// ============================================================================
// Webhook self-test (config page)
// ============================================================================

Mautic.whatsappTestWebhook = function () {
    var btn = mQuery('#whatsapp-test-webhook-btn');
    var result = mQuery('#whatsapp-test-webhook-result');

    btn.prop('disabled', true);
    btn.html('<i class="ri-loader-line ri-spin"></i> Testing...');
    result.html('');

    console.log('[WhatsApp] Test Connection: firing AJAX request');

    mQuery.ajax({
        url: mauticAjaxUrl + '?action=whatsapp:testWebhook',
        type: 'POST',
        dataType: 'json',
        success: function (response, textStatus, xhr) {
            btn.prop('disabled', false);
            btn.html('<i class="ri-plug-line"></i> Test Connection');

            console.log('[WhatsApp] Test Connection SUCCESS callback:', {
                status: xhr.status,
                response: response,
                textStatus: textStatus
            });

            var html = '';
            if (response && response.status === 'ok') {
                html = '<div class="alert alert-success">'
                    + '<i class="ri-checkbox-circle-line"></i> <strong>Success!</strong> '
                    + Mautic.whatsappEscapeHtml(response.reason || '')
                    + '</div>';
            } else {
                html = '<div class="alert alert-danger">'
                    + '<i class="ri-close-circle-line"></i> <strong>Broken:</strong> '
                    + Mautic.whatsappEscapeHtml((response && response.reason) || 'Unknown error');

                html += '<pre style="margin-top:10px;max-height:300px;overflow:auto;font-size:11px;background:#fff;padding:8px;">';
                html += Mautic.whatsappEscapeHtml('Raw response: ' + JSON.stringify(response, null, 2));
                html += '</pre>';
                html += '</div>';
            }

            result.html(html);

            if (response && response.state) {
                Mautic.whatsappUpdateWebhookStatusPanel(response.state);
            }
        },
        error: function (xhr, textStatus, errorThrown) {
            btn.prop('disabled', false);
            btn.html('<i class="ri-plug-line"></i> Test Connection');

            console.error('[WhatsApp] Test Connection ERROR callback:', {
                status: xhr.status,
                statusText: xhr.statusText,
                textStatus: textStatus,
                errorThrown: errorThrown,
                responseText: xhr.responseText
            });

            var html = '<div class="alert alert-danger">'
                + '<i class="ri-close-circle-line"></i> <strong>Request failed:</strong> '
                + Mautic.whatsappEscapeHtml('HTTP ' + xhr.status + ' ' + (xhr.statusText || errorThrown || 'unknown'));

            if (xhr.responseText) {
                html += '<pre style="margin-top:10px;max-height:300px;overflow:auto;font-size:11px;background:#fff;padding:8px;">';
                html += Mautic.whatsappEscapeHtml(xhr.responseText.substring(0, 2000));
                html += '</pre>';
            }
            html += '</div>';

            result.html(html);
        }
    });
};

Mautic.whatsappUpdateWebhookStatusPanel = function (state) {
    var panel = mQuery('#whatsapp-webhook-status-panel');
    if (!panel.length || !state) return;

    var alertClass = 'alert-danger';
    var icon = 'ri-close-circle-line';
    var label = 'Broken';
    var desc = '';

    if (state.status === 'active') {
        alertClass = 'alert-success';
        icon = 'ri-checkbox-circle-line';
        label = 'Active';
        desc = 'Receiving webhooks normally';
    } else if (state.status === 'idle') {
        alertClass = 'alert-warning';
        icon = 'ri-time-line';
        label = 'Verified — Idle';
        desc = 'Verified but no recent activity';
    } else if (state.status === 'pending') {
        alertClass = 'alert-warning';
        icon = 'ri-error-warning-line';
        label = 'Pending Verification';
        desc = 'Token set but Meta has not verified yet';
    } else if (state.status === 'error') {
        alertClass = 'alert-danger';
        icon = 'ri-close-circle-line';
        label = 'Broken';
        desc = state.last_error || 'Webhook is not responding correctly';
    } else {
        alertClass = 'alert-danger';
        icon = 'ri-close-circle-line';
        label = 'Not Configured';
        desc = 'Set a verify token and save';
    }

    panel.html(
        '<div class="alert ' + alertClass + '">'
        + '<i class="' + icon + '"></i> <strong>' + label + '</strong> — ' + Mautic.whatsappEscapeHtml(desc)
        + '</div>'
    );
};
