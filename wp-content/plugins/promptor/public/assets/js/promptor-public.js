/**
 * Promptor Public - Entry Point
 *
 * Coordinates all modules to provide the AI chat experience.
 * Modules are loaded as separate files via wp_enqueue_script dependencies:
 *   - utils/dom-helpers.js      (PromptorModules.DomHelpers)
 *   - utils/error-handler.js    (PromptorModules.ErrorHandler)
 *   - modules/conversation.js   (PromptorModules.Conversation)
 *   - modules/api.js            (PromptorModules.Api)
 *   - modules/ui.js             (PromptorModules.Ui)
 *   - modules/services.js       (PromptorModules.Services)
 *
 * @package Promptor
 * @since 1.3.0 (modularized from monolithic 1,290-line file)
 */
/* global jQuery, wp */
(function ($) {
  'use strict';

  // ---- i18n fallback -------------------------------------------------------
  var __ = window.wp && window.wp.i18n ? window.wp.i18n.__ : function (s) { return s; };

  // ---- Module references ----------------------------------------------------
  var Modules = window.PromptorModules || {};
  var H = Modules.DomHelpers;
  var ConversationMemory = Modules.Conversation;
  var ApiFactory = Modules.Api;
  var UiFactory = Modules.Ui;
  var ServicesFactory = Modules.Services;

  // ---- Backward compatibility: window.Promptor global -----------------------
  window.Promptor = window.Promptor || {
    version: '1.3.5',
    modules: Modules
  };

  // --------------------------------------------------------------------------
  // App initialization per instance
  // --------------------------------------------------------------------------
  function initializePromptorApp($app) {
    var contextKey = $app.data('context-key');
    var localizationObjectName = 'promptor_data_' + String(contextKey || '').replace(/-/g, '_');
    var promptorData = window[localizationObjectName];

    if (typeof promptorData === 'undefined') {
      $app.html(
        '<p style="padding:20px;text-align:center;">' +
          H.esc(__('Chat could not be loaded. (Context Error)', 'promptor')) +
          '</p>'
      );
      return;
    }

    // Destructure server-localized data
    var ajax_url = promptorData.ajax_url || '';
    var ai_query_nonce = promptorData.ai_query_nonce || '';
    var form_nonce = promptorData.form_nonce || '';
    var add_to_cart_nonce = promptorData.add_to_cart_nonce || '';
    var feedback_nonce = promptorData.feedback_nonce || '';
    var example_questions = promptorData.example_questions || [];
    var ui = promptorData.ui || {};
    var context = promptorData.context || '';
    var i18n = promptorData.i18n || {};
    var enable_conversation_memory = typeof promptorData.enable_conversation_memory !== 'undefined' ? promptorData.enable_conversation_memory : 1;

    // Wire up i18n labels for timeAgo helper
    H.setTimeAgoLabels({
      justNow: i18n.justNow,
      minAgo: i18n.minAgo,
      hrAgo: i18n.hrAgo,
      dAgo: i18n.dAgo
    });

    // Validate nonces
    if (!ai_query_nonce || !form_nonce || !add_to_cart_nonce || !feedback_nonce) {
      $app.html(
        '<p style="padding:20px;text-align:center;">' +
          H.esc(__('Security validation failed. Please refresh the page.', 'promptor')) +
          '</p>'
      );
      return;
    }

    var ajaxEndpoint =
      ajax_url ||
      (window.promptor_public && window.promptor_public.ajax_url) ||
      (typeof window.ajaxurl !== 'undefined' ? window.ajaxurl : '');

    if (!ajaxEndpoint) {
      $app.html(
        '<p style="padding:20px;text-align:center;">' +
          H.esc(__('Configuration error. Please contact administrator.', 'promptor')) +
          '</p>'
      );
      return;
    }

    var botAvatarUrl = ui.bot_avatar_url || '';

    // DOM elements
    var $chatLog = $app.find('.promptor-chat-log');
    var $form = $app.find('.promptor-search-form');
    var $input = $app.find('.promptor-query-input');
    var $askButton = $app.find('.promptor-ask-btn');

    // a11y — ARIA labels for key elements
    if ($chatLog.length) {
      $chatLog.attr({ role: 'log', 'aria-live': 'polite', 'aria-relevant': 'additions' });
    }
    $input.attr({ 'aria-label': __('Type your message', 'promptor'), autocomplete: 'off' });
    $askButton.attr('aria-label', __('Send message', 'promptor'));
    $form.attr('role', 'search');

    // Keyboard navigation — Tab through example questions, Enter to select
    $chatLog.on('keydown', '.promptor-example-question-btn', function (ev) {
      var $btns = $chatLog.find('.promptor-example-question-btn');
      var idx = $btns.index(this);
      if (ev.key === 'ArrowDown' || ev.key === 'ArrowRight') {
        ev.preventDefault();
        $btns.eq((idx + 1) % $btns.length).trigger('focus');
      } else if (ev.key === 'ArrowUp' || ev.key === 'ArrowLeft') {
        ev.preventDefault();
        $btns.eq((idx - 1 + $btns.length) % $btns.length).trigger('focus');
      }
    });

    // Input auto-resize — textarea grows with content
    $input.on('input.promptor-autoresize', function () {
      this.style.height = 'auto';
      var max = 120; // px
      this.style.height = Math.min(this.scrollHeight, max) + 'px';
    });

    // Debounced re-render prevention on fast typing
    var _inputDebounceTimer;
    $input.on('input.promptor-debounce', function () {
      var el = this;
      clearTimeout(_inputDebounceTimer);
      _inputDebounceTimer = setTimeout(function () {
        // Trigger any dependent UI that reacts to input value
        $(el).triggerHandler('promptor:input-settled');
      }, 300);
    });

    // Mobile keyboard handling
    if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
      var initialViewportHeight = window.innerHeight;

      $input.on('focus', function () {
        setTimeout(function () {
          if (window.innerHeight < initialViewportHeight) {
            $input[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            H.scrollToBottom($chatLog);
          }
        }, 300);
      });

      $input.on('blur', function () {
        initialViewportHeight = window.innerHeight;
      });

      $(window).on('resize.promptor-keyboard', function () {
        if (document.activeElement === $input[0] && window.innerHeight < initialViewportHeight) {
          setTimeout(function () { H.scrollToBottom($chatLog); }, 100);
        }
      });
    }

    // Initialize modules
    var api = ApiFactory.createApi({
      ajaxEndpoint: ajaxEndpoint,
      aiQueryNonce: ai_query_nonce,
      formNonce: form_nonce,
      addToCartNonce: add_to_cart_nonce,
      feedbackNonce: feedback_nonce
    });

    var uiRenderer = UiFactory.createUi({
      $chatLog: $chatLog,
      botAvatarUrl: botAvatarUrl,
      i18n: i18n,
      __: __,
      context: context,
      enableMemory: enable_conversation_memory,
      conversationModule: ConversationMemory
    });

    var serviceRenderer = ServicesFactory.createRenderer({
      i18n: i18n,
      __: __,
      woo: promptorData.woo || {}
    });

    // Inject Service Drawer
    var drawerHtml =
      '<div id="promptor-service-drawer" class="promptor-service-drawer" style="display:none;">' +
      '<div class="promptor-drawer-header"><h4>' +
      '&#x1F4CC; <span>' + H.esc(i18n.selectedServices || __('Selected Services', 'promptor')) + '</span> (<span class="drawer-count">0</span>)' +
      '</h4><button class="promptor-drawer-collapse-btn" aria-label="' + H.esc(i18n.toggleDrawer || __('Toggle drawer', 'promptor')) + '">&minus;</button></div>' +
      '<div class="promptor-drawer-content"><div class="promptor-service-chips"></div>' +
      '<button class="promptor-request-quote-btn">' + H.esc(i18n.requestQuote || __('Request Quote', 'promptor')) + '</button>' +
      '</div></div>';

    $chatLog.after(drawerHtml);
    var $drawer = $app.find('#promptor-service-drawer');

    // State
    var conversationHistory = [];
    var current_query_id = 0;
    var MAX_HISTORY_LENGTH = 50;

    function populateUI() {
      var avatarUrl = H.safeUrl(botAvatarUrl);
      if (avatarUrl !== '#') {
        $app.find('.promptor-header-avatar').attr('src', avatarUrl);
      }
      $app.find('.promptor-header-title').text(ui.header_title || '');
      $app.find('.promptor-header-subtitle').text(ui.header_subtitle || '');
      $input.attr('placeholder', String(ui.input_placeholder || ''));
    }

    function updateServiceDrawer() {
      var services = ConversationMemory.getServices(context);
      var count = services.length;

      $drawer.find('.drawer-count').text(count);

      if (count === 0) {
        $drawer.hide();
        return;
      }

      var chipsHtml = services.map(function (s) {
        return '<div class="promptor-service-chip" data-service-id="' + H.esc(s.id || '') + '">' + H.esc(s.title || '') + '</div>';
      }).join('');

      $drawer.find('.promptor-service-chips').html(chipsHtml);
    }

    function restoreConversation() {
      var messages = ConversationMemory.getMessages(context);

      if (messages && messages.length > 0) {
        messages.forEach(function (msg) {
          if (msg.role === 'user') {
            uiRenderer.addToLog(msg.content, 'user', 0, false);
            if (enable_conversation_memory) {
              conversationHistory.push({ role: 'user', content: msg.content });
            }
          } else if (msg.role === 'assistant') {
            uiRenderer.addToLog(msg.content, 'ai', 0, false);
            if (enable_conversation_memory) {
              conversationHistory.push({ role: 'assistant', content: msg.content });
            }
          }
        });
      }

      var allServices = ConversationMemory.getServices(context);
      if (allServices.length > 0) {
        uiRenderer.addConversationSummary(allServices);
      }

      updateServiceDrawer();
    }

    function updateServiceButtonText() {
      var $summary = $chatLog.find('.promptor-conversation-summary').last();
      var $button = $summary.find('.promptor-cta-request-quote');
      var $selectedChips = $summary.find('.promptor-inline-chip.chip-selected');
      var selectedCount = $selectedChips.length;

      var buttonText;
      if (selectedCount === 0) {
        buttonText = H.esc(i18n.selectServiceQuote || __('Select a service to request a quote', 'promptor'));
        $button.prop('disabled', true);
      } else if (selectedCount === 1) {
        buttonText = H.esc(i18n.requestQuote || __('Request Quote', 'promptor'));
        $button.prop('disabled', false);
      } else {
        buttonText = H.esc(i18n.requestQuoteForAll || __('Request Quote for All', 'promptor'));
        $button.prop('disabled', false);
      }

      $button.text(buttonText);
    }

    // ---- Event Handlers ----

    // Main ask handler
    $form.on('submit', function (ev) {
      ev.preventDefault();

      var query = String($input.val() || '').trim();
      if (!query) return;

      if (query.length > 5000) {
        window.alert(__('Your message is too long. Please shorten it and try again.', 'promptor'));
        return;
      }

      uiRenderer.addToLog(query, 'user');
      conversationHistory.push({ role: 'user', content: query });

      if (conversationHistory.length > MAX_HISTORY_LENGTH) {
        conversationHistory = conversationHistory.slice(-MAX_HISTORY_LENGTH);
      }

      $input.val('');
      $askButton.prop('disabled', true);
      $chatLog.find('.promptor-example-questions').remove();
      uiRenderer.showTypingIndicator();

      var historyJson = '[]';
      if (enable_conversation_memory && conversationHistory.length > 0) {
        try {
          historyJson = JSON.stringify(conversationHistory);
        } catch (e) {
          historyJson = JSON.stringify(conversationHistory.slice(-1));
        }
      }

      api.sendAiQuery(query, historyJson, context)
        .done(function (response) {
          uiRenderer.removeTypingIndicator();
          if (response && response.success) {
            var responseData = response.data || {};
            var ai_data = responseData.ai_data;
            current_query_id = H.safeInt(responseData.query_id);

            if (ai_data && ai_data.ai_explanation) {
              uiRenderer.addToLog(ai_data.ai_explanation, 'ai', current_query_id);
            }

            // Accumulate services
            if (ai_data && ai_data.services && Array.isArray(ai_data.services)) {
              ai_data.services.forEach(function (service) {
                ConversationMemory.addService(context, service);
              });
            }

            var allAccumulatedServices = ConversationMemory.getServices(context);

            // Render cards (products, articles, FAQs, and comparison table via services module)
            var cardsHtml = serviceRenderer.renderCards(
              [],
              (ai_data && ai_data.products) || [],
              (ai_data && ai_data.articles) || [],
              (ai_data && ai_data.faqs) || [],
              (ai_data && ai_data.comparison) || null
            );
            if (cardsHtml) {
              $chatLog.append(cardsHtml);
              H.scrollToBottom($chatLog);
            }

            // Abandoned cart reminder nudge.
            var cartReminder = response.data && response.data.cart_reminder;
            if (cartReminder && cartReminder.item_count > 0) {
              var itemLabel = cartReminder.item_count === 1 ? __('item', 'promptor') : __('items', 'promptor');
              var nudgeHtml = '<div class="promptor-cart-reminder">' +
                '<div class="promptor-cart-reminder-icon">&#128722;</div>' +
                '<div class="promptor-cart-reminder-body">' +
                '<strong>' + H.esc(__('You have items in your cart', 'promptor')) + '</strong>' +
                '<span>' + H.safeInt(cartReminder.item_count) + ' ' + H.esc(itemLabel) + ' &middot; <span class="promptor-cart-reminder-total">' + H.esc(cartReminder.total) + '</span></span>' +
                '</div>' +
                '<a href="' + H.safeUrl(cartReminder.cart_url) + '" class="promptor-cart-reminder-btn">' +
                H.esc(__('Complete Purchase', 'promptor')) + '</a></div>';
              $chatLog.append(nudgeHtml);
              H.scrollToBottom($chatLog);
            }

            // Show conversation summary
            if (allAccumulatedServices.length > 0) {
              uiRenderer.addConversationSummary(allAccumulatedServices);
            }

            try {
              conversationHistory.push({ role: 'assistant', content: JSON.stringify(ai_data || {}) });
            } catch (e) {
              conversationHistory.push({ role: 'assistant', content: '' });
            }
          } else {
            var err = (response && response.data && response.data.message) || __('An unknown error occurred.', 'promptor');
            uiRenderer.addToLog(String(err), 'ai');
          }
        })
        .fail(function (jqXHR) {
          uiRenderer.removeTypingIndicator();
          var errorMsg = '';
          if (jqXHR.status === 0) {
            errorMsg = __('Network error. Please check your connection.', 'promptor');
          } else {
            // Try to extract server message from JSON error response
            try {
              var resp = jqXHR.responseJSON || JSON.parse(jqXHR.responseText || '{}');
              errorMsg = (resp.data && resp.data.message) ? resp.data.message : '';
            } catch (e) { /* ignore */ }
            if (!errorMsg) {
              errorMsg = __('Sorry, an error occurred. Please try again.', 'promptor');
            }
          }
          uiRenderer.addToLog(errorMsg, 'ai');
        })
        .always(function () {
          $askButton.prop('disabled', false);
          H.scrollToBottom($chatLog);
          $input.trigger('focus');
        });
    });

    // Inline chip click
    $chatLog.on('click', '.promptor-inline-chip', function () {
      var $chip = $(this);
      var $summary = $chip.closest('.promptor-conversation-summary');
      var $descContainer = $summary.find('.promptor-service-description-container');
      var chipId = $chip.data('service-id');
      var chipTitle = $chip.data('service-title');
      var chipDescription = $chip.data('service-description');

      $chip.toggleClass('chip-selected');

      if (!$chip.hasClass('chip-selected')) {
        var $thisDesc = $descContainer.find('[data-desc-id="' + chipId + '"]');
        $thisDesc.slideUp(200, function () {
          $(this).remove();
          if ($descContainer.children().length === 0) {
            $descContainer.hide();
          }
        });
      }

      if ($chip.hasClass('chip-selected') && chipDescription) {
        var descHtml =
          '<div class="promptor-service-description-content" data-desc-id="' + H.esc(chipId) + '" style="display:none;">' +
          '<h4>' + H.esc(chipTitle) + '</h4><p>' + H.esc(chipDescription) + '</p></div>';

        $descContainer.append(descHtml);
        if (!$descContainer.is(':visible')) {
          $descContainer.show();
        }
        $descContainer.find('[data-desc-id="' + chipId + '"]').slideDown(200);
      }

      updateServiceButtonText();
    });

    // Close description
    $chatLog.on('click', '.promptor-close-description-btn', function () {
      var $content = $(this).closest('.promptor-service-description-content');
      var descId = $content.data('desc-id');
      var $summary = $content.closest('.promptor-conversation-summary');
      var $container = $content.parent();

      $summary.find('.promptor-inline-chip[data-service-id="' + descId + '"]').removeClass('chip-selected');

      $content.slideUp(200, function () {
        $(this).remove();
        if ($container.children().length === 0) {
          $container.hide();
        }
      });

      updateServiceButtonText();
    });

    // CTA Request Quote
    $chatLog.on('click', '.promptor-cta-request-quote', function () {
      var $summary = $(this).closest('.promptor-conversation-summary');
      var $selectedChips = $summary.find('.promptor-inline-chip.chip-selected');

      if ($selectedChips.length === 0) {
        window.alert(__('Please select at least one service.', 'promptor'));
        return;
      }

      var allServices = ConversationMemory.getServices(context);
      var selectedServiceIds = $selectedChips.map(function () {
        return $(this).data('service-id');
      }).get();

      var selectedServices = allServices.filter(function (s) {
        return selectedServiceIds.indexOf(s.id) >= 0;
      });

      uiRenderer.openQuoteForm(selectedServices);
    });

    // Drawer collapse/expand
    $app.on('click', '.promptor-drawer-collapse-btn', function () {
      var $content = $drawer.find('.promptor-drawer-content');
      var $btn = $(this);

      if ($content.is(':visible')) {
        $content.slideUp(300, 'swing');
        $btn.text('+').attr('aria-label', __('Expand drawer', 'promptor'));
      } else {
        $content.slideDown(300, 'swing', function () {
          if (window.innerWidth <= 768) {
            $content[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
          }
        });
        $btn.html('&minus;').attr('aria-label', __('Collapse drawer', 'promptor'));
      }
    });

    // Drawer Request Quote
    $app.on('click', '.promptor-request-quote-btn', function () {
      var services = ConversationMemory.getServices(context);
      if (services.length === 0) {
        window.alert(__('No services selected.', 'promptor'));
        return;
      }
      uiRenderer.openQuoteForm(services);
    });

    // New Conversation
    $app.on('click', '.promptor-new-conversation-btn', function () {
      var confirmMsg = i18n.confirmNewConversation || __('Are you sure you want to start a new conversation? This will clear your chat history and selected services.', 'promptor');

      if (confirm(confirmMsg)) {
        ConversationMemory.clear(context);
        $chatLog.empty();
        conversationHistory = [];
        $drawer.hide();
        $drawer.find('.promptor-service-chips').empty();
        $drawer.find('.drawer-count').text('0');
        uiRenderer.showExampleQuestions(example_questions, 0);
        var clearedMsg = __('Chat history cleared. You can start a fresh conversation!', 'promptor');
        uiRenderer.addToLog(clearedMsg, 'ai', 0, false);
      }
    });

    // Example question click
    $chatLog.on('click', '.promptor-example-question-btn', function () {
      $input.val($(this).text()).trigger('focus');
      $form.trigger('submit');
    });

    // Feedback
    $chatLog.on('click', '.promptor-session-feedback-bar .feedback-btn', function () {
      var $button = $(this);
      var $feedbackBar = $button.closest('.promptor-session-feedback-bar');
      var queryId = H.safeInt($feedbackBar.data('query-id'));
      var feedbackValue = H.safeInt($button.data('feedback'));

      if (!queryId) return;

      $feedbackBar.find('.feedback-btn').prop('disabled', true);
      $button.css('transform', 'scale(1.2)');

      api.sendFeedback(queryId, feedbackValue)
        .done(function (response) {
          if (response && response.success) {
            $feedbackBar.find('.feedback-text, .feedback-actions').fadeOut(200, function () {
              $feedbackBar.find('.feedback-thanks').fadeIn(200);
            });
          } else {
            $feedbackBar.find('.feedback-btn').prop('disabled', false);
            window.alert(
              String((response && response.data && response.data.message) || __('Could not save feedback.', 'promptor'))
            );
          }
        })
        .fail(function () {
          $feedbackBar.find('.feedback-btn').prop('disabled', false);
          window.alert(__('An error occurred while saving feedback.', 'promptor'));
        });
    });

    // Add to cart
    $chatLog.on('click', '.promptor-add-to-cart-btn', function (ev) {
      ev.preventDefault();

      var $button = $(this);
      if ($button.hasClass('loading') || $button.hasClass('added')) return;

      var pid = H.safeInt($button.data('product_id'));
      if (!pid) {
        window.alert(__('Invalid product.', 'promptor'));
        return;
      }

      $button.addClass('loading').prop('disabled', true);

      api.addToCart(pid, current_query_id)
        .done(function (response) {
          if (response && response.success) {
            $button.addClass('added').text(i18n.addedToCart || __('Added', 'promptor'));
            if (typeof document.body !== 'undefined') {
              $(document.body).trigger('wc_fragment_refresh');
            }
          } else {
            window.alert(String((response && response.data && response.data.message) || __('An error occurred.', 'promptor')));
            $button.prop('disabled', false);
          }
        })
        .fail(function () {
          window.alert(__('A network error occurred.', 'promptor'));
          $button.removeClass('added').prop('disabled', false);
        })
        .always(function () {
          $button.removeClass('loading');
        });
    });

    // Add all to cart (comparison table)
    $chatLog.on('click', '.promptor-add-all-to-cart-btn', function (ev) {
      ev.preventDefault();
      var $button = $(this);
      if ($button.hasClass('loading') || $button.hasClass('added')) return;

      var idsStr = $button.data('product_ids');
      if (!idsStr) return;

      var ids = String(idsStr).split(',').map(function (id) { return H.safeInt(id); }).filter(Boolean);
      if (!ids.length) return;

      $button.addClass('loading').prop('disabled', true);

      var done = 0;
      var failed = 0;

      ids.forEach(function (pid) {
        api.addToCart(pid, current_query_id)
          .done(function (response) {
            if (!response || !response.success) failed++;
          })
          .fail(function () { failed++; })
          .always(function () {
            done++;
            if (done === ids.length) {
              $button.removeClass('loading');
              if (failed === 0) {
                $button.addClass('added').text(__('All Added', 'promptor'));
                $(document.body).trigger('wc_fragment_refresh');
              } else {
                $button.prop('disabled', false);
              }
            }
          });
      });
    });

    // Service checkbox toggle
    $chatLog.on('change', 'input[name="selected_services[]"]', function () {
      var $formEl = $(this).closest('form');
      var hasChecked = $formEl.find('input[name="selected_services[]"]:checked').length > 0;
      if (hasChecked) {
        $formEl.find('.promptor-form-fields-wrapper').slideDown();
      } else {
        $formEl.find('.promptor-form-fields-wrapper').slideUp();
      }
    });

    // Service inquiry form submit
    $chatLog.on('submit', 'form[data-type="service-inquiry"]', function (ev) {
      ev.preventDefault();

      var $contactForm = $(this);
      var $submitButton = $contactForm.find('.promptor-submit-button');
      var originalButtonText = $submitButton.text();

      var selectedServices = $contactForm.find('input[name="selected_services[]"]:checked');
      if (selectedServices.length === 0) {
        window.alert(__('Please select at least one service.', 'promptor'));
        return;
      }

      var name = String($contactForm.find('[name="name"]').val() || '').trim();
      var email = String($contactForm.find('[name="email"]').val() || '').trim();

      if (!name || !email) {
        window.alert(__('Please fill in your name and email.', 'promptor'));
        return;
      }

      var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email)) {
        window.alert(__('Please enter a valid email address.', 'promptor'));
        return;
      }

      $submitButton.text(__('Sending...', 'promptor')).prop('disabled', true);

      api.submitContactForm({
        name: name,
        email: email,
        phone: String($contactForm.find('[name="phone"]').val() || '').trim(),
        notes: String($contactForm.find('[name="notes"]').val() || '').trim(),
        services: selectedServices.map(function (_, el) { return String($(el).val()); }).get(),
        query_id: current_query_id
      })
        .done(function (response) {
          if (response && response.success) {
            var msg = (response.data && response.data.message) || __('Thank you! We received your inquiry.', 'promptor');
            var $success = $('<div class="promptor-success-screen"><h3></h3></div>');
            $success.find('h3').text(String(msg));
            $contactForm.closest('.promptor-contact-form-container').empty().append($success);
          } else {
            window.alert(String((response && response.data && response.data.message) || __('An error occurred. Please try again.', 'promptor')));
            $submitButton.text(originalButtonText).prop('disabled', false);
          }
        })
        .fail(function () {
          window.alert(__('A network error occurred. Please try again.', 'promptor'));
          $submitButton.text(originalButtonText).prop('disabled', false);
        });
    });

    // Drawer inquiry form submit
    $chatLog.on('submit', 'form[data-type="drawer-inquiry"]', function (ev) {
      ev.preventDefault();

      var $contactForm = $(this);
      var $submitButton = $contactForm.find('.promptor-submit-button');
      var originalButtonText = $submitButton.text();

      var allServices = ConversationMemory.getServices(context);
      if (allServices.length === 0) {
        window.alert(__('No services selected.', 'promptor'));
        return;
      }

      var name = String($contactForm.find('[name="name"]').val() || '').trim();
      var email = String($contactForm.find('[name="email"]').val() || '').trim();

      if (!name || !email) {
        window.alert(__('Please fill in your name and email.', 'promptor'));
        return;
      }

      var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email)) {
        window.alert(__('Please enter a valid email address.', 'promptor'));
        return;
      }

      $submitButton.text(__('Sending...', 'promptor')).prop('disabled', true);

      api.submitContactForm({
        name: name,
        email: email,
        phone: String($contactForm.find('[name="phone"]').val() || '').trim(),
        notes: String($contactForm.find('[name="notes"]').val() || '').trim(),
        services: allServices.map(function (s) { return s.title; }),
        query_id: current_query_id
      })
        .done(function (response) {
          if (response && response.success) {
            var msg = (response.data && response.data.message) || __('Thank you! We received your inquiry.', 'promptor');
            var $success = $('<div class="promptor-success-screen"><h3></h3></div>');
            $success.find('h3').text(String(msg));
            $contactForm.closest('.promptor-quote-form-container').empty().append($success);
          } else {
            window.alert(String((response && response.data && response.data.message) || __('An error occurred. Please try again.', 'promptor')));
            $submitButton.text(originalButtonText).prop('disabled', false);
          }
        })
        .fail(function () {
          window.alert(__('A network error occurred. Please try again.', 'promptor'));
          $submitButton.text(originalButtonText).prop('disabled', false);
        });
    });

    // Copy message button
    $chatLog.on('click', '.promptor-copy-btn', function () {
      var $btn = $(this);
      var $bubble = $btn.closest('.promptor-message-wrapper').find('.promptor-message-bubble');
      var text = $bubble.text();
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () {
          $btn.addClass('copied');
          setTimeout(function () { $btn.removeClass('copied'); }, 1500);
        });
      } else {
        // Fallback for older browsers
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        $btn.addClass('copied');
        setTimeout(function () { $btn.removeClass('copied'); }, 1500);
      }
    });

    // Update timestamps every 60s
    setInterval(function () {
      $chatLog.find('.promptor-message-time[data-ts]').each(function () {
        var ts = H.safeInt($(this).data('ts'));
        if (ts) $(this).text(H.timeAgo(ts));
      });
    }, 60000);

    // Reset textarea height after submit
    $form.on('submit', function () {
      setTimeout(function () { $input.css('height', 'auto'); }, 0);
    });

    // Init
    populateUI();
    restoreConversation();
    uiRenderer.showExampleQuestions(example_questions, conversationHistory.length);
  }

  // --------------------------------------------------------------------------
  // DOM ready
  // --------------------------------------------------------------------------
  $(function () {
    // Initialize all instances
    $('.promptor-app').each(function () {
      try {
        initializePromptorApp($(this));
      } catch (err) {
        if (typeof console !== 'undefined') {
          console.error('Promptor initialization error:', err);
        }
        $(this).html(
          '<p style="padding:20px;text-align:center;">' +
            (H ? H.esc(__('Failed to initialize chat. Please refresh the page.', 'promptor')) : 'Failed to initialize chat.') +
            '</p>'
        );
      }
    });

    // Popup toggle
    var $popupContainer = $('#promptor-popup-container');
    if ($popupContainer.length > 0) {
      var $toggleBtn = $('#promptor-popup-toggle');
      var $chatWindow = $popupContainer.find('.promptor-chat-window');
      var $popupInput = $chatWindow.find('.promptor-query-input');
      var $popupChatLog = $chatWindow.find('.promptor-chat-log');

      // Focus trap helper for popup mode
      function trapFocus(ev) {
        if (ev.key !== 'Tab') return;
        var focusable = $chatWindow.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])').filter(':visible');
        if (!focusable.length) return;
        var first = focusable.first()[0];
        var last = focusable.last()[0];
        if (ev.shiftKey && document.activeElement === first) {
          ev.preventDefault();
          last.focus();
        } else if (!ev.shiftKey && document.activeElement === last) {
          ev.preventDefault();
          first.focus();
        }
      }

      $toggleBtn.on('click', function () {
        var isActive = $chatWindow.hasClass('active');
        var isMobile = window.innerWidth <= 768;

        if (!isActive) {
          $chatWindow.addClass('active').css('display', 'flex');
          $(this).find('.promptor-icon-chat').fadeOut(200);
          $(this).find('.promptor-icon-close').fadeIn(200);

          // Enable focus trap
          $chatWindow.on('keydown.promptor-focus-trap', trapFocus);

          // Close on Escape
          $chatWindow.on('keydown.promptor-escape', function (ev) {
            if (ev.key === 'Escape') $toggleBtn.trigger('click');
          });

          setTimeout(function () {
            if (!isMobile) {
              $popupInput.trigger('focus');
            }
            H.scrollToBottom($popupChatLog);
          }, isMobile ? 400 : 100);
        } else {
          $chatWindow.removeClass('active');
          $(this).find('.promptor-icon-chat').fadeIn(200);
          $(this).find('.promptor-icon-close').fadeOut(200);

          $popupInput.trigger('blur');
          $chatWindow.off('keydown.promptor-focus-trap keydown.promptor-escape');

          setTimeout(function () {
            if (!$chatWindow.hasClass('active')) {
              $chatWindow.css('display', 'none');
            }
          }, 300);
        }
      });

    }
  });
})(jQuery);
