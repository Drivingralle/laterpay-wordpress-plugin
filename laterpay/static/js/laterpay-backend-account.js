jQuery.noConflict();
(function($) { $(function() {

    var autofocusEmptyInput = function() {
            var $inputs = $('.api-key-input, .merchant-id-input');
            for (var i = 0, l = $inputs.length; i < l; i++) {
                if ($inputs.eq(i).val() === '') {
                    $inputs.eq(i).focus();
                    return;
                }
            }
        },
        togglePluginModeText = function() {
            if ($('#plugin-mode-toggle').prop('checked')) {
                $('#plugin_mode_live_hint').fadeIn(400);
                $('#plugin_mode_test_hint, #plugin-mode-indicator').fadeOut(400);
                $('#plugin_mode_test_text').hide();
                $('#plugin_mode_live_text').show();
            } else {
                $('#plugin_mode_test_hint, #plugin-mode-indicator').fadeIn(400);
                $('#plugin_mode_live_hint').fadeOut(400);
                $('#plugin_mode_live_text').hide();
                $('#plugin_mode_test_text').show();
            }
        },
        togglePluginMode = function() {
            var $toggle = $('#plugin-mode-toggle');

            if ($toggle.prop('checked')) {
                if ($('#laterpay_live_api_key').val().length !== 32 || $('#laterpay_live_merchant_id').val().length !== 22) {
                    setMessage({
                        message: lpVars.i18nLiveApiDataRequired,
                        success: false
                    });
                    $('#plugin_mode_hidden_input').val(0);
                    $('#plugin-mode-toggle').prop('checked', false);
                    togglePluginModeText();
                    makeAjaxRequest('plugin_mode', true);
                    return false;
                } else {
                    $('#plugin_mode_hidden_input').val(1);
                    togglePluginModeText();
                    makeAjaxRequest('plugin_mode', true);
                }
            } else {
                $('#plugin_mode_hidden_input').val(0);
                togglePluginModeText();
                makeAjaxRequest('plugin_mode', true);
            }
        },
        makeAjaxRequest = function(form_id) {
            $.post(
                ajaxurl,
                $('#' + form_id).serializeArray(),
                function(data) {
                    setMessage({
                        message: data.message,
                        success: data.success
                    });
                },
                'json'
            );
        },
        validateAPIKey = function(api_key_input) {
            var $input          = $(api_key_input),
                $form           = $input.parents('form'),
                value           = $input.val().trim(),
                apiKeyLength    = 32;

            // trim spaces from input
            if (value.length !== $input.val().length) {
                $input.val(value);
            }

            if (value.length === 0 || value.length === apiKeyLength) {
                makeAjaxRequest($form.attr('id'));
                togglePluginMode();
            } else {
                setMessage({
                    message: lpVars.i18nApiKeyInvalid,
                    success: false
                });
            }
        },
        validateMerchantId = function(merchant_id_input) {
            var $input              = $(merchant_id_input),
                $form               = $input.parents('form'),
                value               = $input.val().trim(),
                merchantIdLength    = 22;

            // trim spaces from input
            if (value.length !== $input.val().length) {
                $input.val(value);
            }

            if (value.length === 0 || value.length === merchantIdLength) {
                makeAjaxRequest($form.attr('id'));
                togglePluginMode();
            } else {
                setMessage({
                    message: lpVars.i18nMerchantIdInvalid,
                    success: false
                });
            }
        },
        hasNoValidCredentials = function() {
            if (
                (
                    // plugin is in test mode, but there are no valid Sandbox API credentials
                    !$('#plugin-mode-toggle').prop('checked') &&
                    (
                        $('#laterpay_sandbox_api_key').val().length     !== 32 ||
                        $('#laterpay_sandbox_merchant_id').val().length !== 22
                    )
                ) || (
                    // plugin is in live mode, but there are no valid Live API credentials
                    $('#plugin-mode-toggle').prop('checked') &&
                    (
                        $('#laterpay_live_api_key').val().length        !== 32 ||
                        $('#laterpay_live_merchant_id').val().length    !== 22
                    )
                )
            ) {
                return true;
            } else {
                return false;
            }
        };

    // API key Ajax forms
    $('.api-key-input').bind('input', function() {
        var api_key_input = this;
        setTimeout(function() {
            validateAPIKey(api_key_input);
        }, 50);
    });

    // Merchant ID Ajax forms
    $('.merchant-id-input').bind('input', function() {
        var merchant_id_input = this;
        setTimeout(function() {
            validateMerchantId(merchant_id_input);
        }, 50);
    });

    // plugin mode Ajax form
    $('#plugin-mode-toggle').click(function() {
        return togglePluginMode();
    });


    // show merchant contracts
    $('#request-live-credentials a')
    .mousedown(function() {
        var $button                 = $(this),
            src                     = 'https://laterpay.net/terms/index.html?group=merchant-contract',
            viewportHeight          = parseInt($(window).height(), 10),
            topMargin               = parseInt($('#wpadminbar').height(), 10) + 26,
            iframeHeight            = viewportHeight - topMargin,
            $iframeWrapperObject    = $('<div id="legal-docs-frame" style="height:' + iframeHeight + 'px;"></div>'),
            $iframeWrapper          = $('#legal-docs-frame'),
            iframeOffset,
            scrollPosition;

        $button.fadeOut(400);

        // remove possibly existing iframe and insert a wrapper to display the iframe in
        if ($('iframe', $iframeWrapper).length !== 0) {
            $('iframe', $iframeWrapper).remove();
        }
        if ($iframeWrapper.length === 0) {
            $('.credentials-hint').after($iframeWrapperObject.slideDown(400, function() {
                // scroll document so that iframe fills viewport
                iframeOffset = $('#legal-docs-frame').offset();
                scrollPosition = iframeOffset.top - topMargin;
                $('BODY, HTML').animate({
                    scrollTop: scrollPosition
                }, 400);
            }));
        }

        // cache object again after replacing it
        $iframeWrapper = $('#legal-docs-frame');

        // inject a new iframe with the requested src parameter into the wrapper
        $iframeWrapper
        .html(
            '<a href="#" class="close-iframe">x</a>' +
            '<iframe ' +
                'src="' + src + '" ' +
                'frameborder="0" ' +
                'height="' + iframeHeight + '" ' +
                'width="100%">' +
            '</iframe>'
        );
        $('.close-iframe', $iframeWrapper).bind('click', function(e) {
            $(this).fadeOut(400)
                .parent('#legal-docs-frame').slideUp(400, function() {
                    $(this).remove();
                });
            $button.fadeIn(400);
            e.preventDefault();
        });
    })
    .click(function(e) {e.preventDefault();});

    // initialize page
    autofocusEmptyInput();

    // prevent leaving the account page without any valid credentials
    window.onbeforeunload = function(e) {
        if (hasNoValidCredentials()) {
            return lpVars.i18nPreventUnload;
        }
    };

});})(jQuery);
