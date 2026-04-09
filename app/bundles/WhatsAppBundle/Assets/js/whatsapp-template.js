/**
 * WhatsApp template preview and placeholder mapping.
 *
 * @author iamjpsingh
 */

Mautic.whatsappTemplateOnLoad = function (container) {
    var templateSelect = mQuery('#whatsapp_templateId');
    if (!templateSelect.length) {
        return;
    }

    // Bind template change
    templateSelect.on('change', function () {
        Mautic.whatsappLoadTemplatePreview(mQuery(this).val());
    });

    // Bind form submit to collect mappings
    mQuery('form[name="whatsapp"]').on('submit', function () {
        Mautic.whatsappCollectMappings();
    });

    // If a template is already selected, load its preview
    var currentVal = templateSelect.val();
    if (currentVal) {
        Mautic.whatsappLoadTemplatePreview(currentVal);
    }
};

/**
 * Fetch template data via AJAX and render preview + placeholder mapping.
 */
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

/**
 * Render the template preview panel (WhatsApp-style green bubble).
 */
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
            buttonHtml += '<div style="text-align:center;padding:6px 0;border-top:1px solid #dadde1;background:#fff;border-radius:' +
                (i === data.buttons.length - 1 ? '0 0 8px 8px' : '0') + ';">' +
                '<span style="color:#00a5f4;font-size:13px;">' +
                Mautic.whatsappEscapeHtml(btn.text || btn.type || 'Button') + '</span></div>';
        }
        mQuery('#preview-buttons-list').html(buttonHtml);
        mQuery('#preview-buttons').removeClass('hide');
    } else {
        mQuery('#preview-buttons').addClass('hide');
    }

    // Also auto-fill hidden template name/language fields for form submission
    if (data.name) {
        mQuery('#whatsapp_templateName').val(data.name);
    }
    if (data.language) {
        mQuery('#whatsapp_templateLanguage').val(data.language);
    }

    panel.removeClass('hide');
};

/**
 * Detect placeholders in body/header and build mapping rows.
 */
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

    // Try to load existing mappings
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

/**
 * Build a single mapping row.
 */
Mautic.whatsappBuildMappingRow = function (paramNum, existingToken) {
    var isCustom = existingToken && existingToken.indexOf('{') !== 0;
    var selectedValue = existingToken || '';

    // If it's a plain text value (not a token), mark as custom
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

/**
 * Collect all mappings and store as JSON in the hidden templateComponents field.
 */
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

/**
 * Escape HTML for safe insertion.
 */
Mautic.whatsappEscapeHtml = function (str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
};
