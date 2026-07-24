/**
 * Promptor UI Module
 *
 * Handles DOM rendering: messages, typing indicators, cards, forms.
 *
 * @package Promptor
 * @since 1.3.0
 */
(function (global, $) {
  'use strict';

  var PromptorModules = global.PromptorModules || {};
  var H = PromptorModules.DomHelpers;

  /**
   * Create a UI renderer bound to a specific chat log and config.
   *
   * @param {Object} config UI configuration.
   * @param {jQuery} config.$chatLog Chat log jQuery element.
   * @param {string} config.botAvatarUrl Bot avatar URL.
   * @param {Object} config.i18n Internationalization strings.
   * @param {string} config.context Context key.
   * @param {boolean} config.enableMemory Whether conversation memory is enabled.
   * @param {Object} config.conversationModule Reference to Conversation module.
   * @return {Object} UI methods.
   */
  function createUi(config) {
    var $chatLog = config.$chatLog;
    var botAvatarUrl = config.botAvatarUrl || '';
    var i18n = config.i18n || {};
    var __ = config.__ || function (s) { return s; };

    function getBotAvatarHtml() {
      var avatarUrl = H.safeUrl(botAvatarUrl);
      return avatarUrl !== '#'
        ? '<div class="promptor-avatar ai-avatar"><img src="' + avatarUrl + '" alt="AI"></div>'
        : '<div class="promptor-avatar ai-avatar"></div>';
    }

    /**
     * Add a message to the chat log.
     */
    function addToLog(text, sender, queryId, saveToMemory) {
      if (typeof saveToMemory === 'undefined') saveToMemory = true;

      var wrapperClass = sender === 'user' ? 'user-message' : 'ai-message';
      var escaped = H.esc(String(text));
      var safeContent = sender === 'ai' && H.formatMessage
        ? H.formatMessage(escaped)
        : escaped.replace(/\n/g, '<br>');
      var nowTs = Math.floor(Date.now() / 1000);

      var copyBtn = sender === 'ai'
        ? '<button class="promptor-copy-btn" aria-label="' + H.esc(__('Copy message', 'promptor')) + '" title="' + H.esc(__('Copy', 'promptor')) + '">' +
          '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>' +
          '</button>'
        : '';

      var messageHtml =
        '<div class="promptor-message-wrapper ' + wrapperClass + '" data-ts="' + nowTs + '">' +
        (sender === 'ai' ? getBotAvatarHtml() : '') +
        '<div class="promptor-message-bubble">' + safeContent + '</div>' +
        '<div class="promptor-message-meta">' +
        '<span class="promptor-message-time" data-ts="' + nowTs + '">' + H.timeAgo(nowTs) + '</span>' +
        copyBtn +
        '</div>' +
        '</div>';

      $chatLog.append(messageHtml);

      var qid = H.safeInt(queryId);
      if (sender === 'ai' && qid > 0) {
        var fbText = H.esc(i18n.isHelpful || __('Is this conversation helpful so far?', 'promptor'));
        var good = H.esc(i18n.goodResponse || __('Good response', 'promptor'));
        var bad = H.esc(i18n.badResponse || __('Bad response', 'promptor'));
        var thanks = H.esc(i18n.thankYou || __('Thank you!', 'promptor'));

        var feedbackBarHtml =
          '<div class="promptor-session-feedback-bar" data-query-id="' + qid + '">' +
          '<span class="feedback-text">' + fbText + '</span>' +
          '<div class="feedback-actions">' +
          '<button class="feedback-btn" data-feedback="1" title="' + good + '" aria-label="' + good + '">&#x1F44D;</button>' +
          '<button class="feedback-btn" data-feedback="-1" title="' + bad + '" aria-label="' + bad + '">&#x1F44E;</button>' +
          '</div>' +
          '<span class="feedback-thanks" style="display:none;">' + thanks + '</span>' +
          '</div>';
        $chatLog.append(feedbackBarHtml);
      }

      if (saveToMemory && config.conversationModule) {
        var role = sender === 'user' ? 'user' : 'assistant';
        config.conversationModule.addMessage(config.context, role, text);
      }

      H.scrollToBottom($chatLog);
    }

    /**
     * Show typing indicator with modern pulse animation.
     */
    function showTypingIndicator() {
      var thinkingText = H.esc(i18n.thinkingIndicator || __('Promptor is thinking', 'promptor'));
      $chatLog.append(
        '<div class="promptor-message-wrapper ai-message typing-indicator-wrapper" role="status" aria-label="' + thinkingText + '">' +
        getBotAvatarHtml() +
        '<div class="promptor-message-bubble promptor-thinking-bubble">' +
        '<div class="promptor-thinking-indicator">' +
        '<div class="promptor-thinking-dots"><span></span><span></span><span></span></div>' +
        '<span class="promptor-thinking-text">' + thinkingText + '</span>' +
        '</div></div></div>'
      );
      H.scrollToBottom($chatLog);
    }

    /**
     * Remove typing indicator.
     */
    function removeTypingIndicator() {
      $chatLog.find('.typing-indicator-wrapper').remove();
    }

    /**
     * Show example questions.
     */
    function showExampleQuestions(exampleQuestions, conversationHistoryLength) {
      if (Array.isArray(exampleQuestions) && exampleQuestions.length > 0 && conversationHistoryLength === 0) {
        var buttonsHtml = exampleQuestions
          .filter(function (q) { return q && typeof q === 'string'; })
          .map(function (q) { return '<button type="button" class="promptor-example-question-btn">' + H.esc(q) + '</button>'; })
          .join('');

        if (buttonsHtml) {
          var title = H.esc(i18n.exampleQuestionsHeader || __('Here are some ideas to get you started:', 'promptor'));
          $chatLog.append(
            '<div class="promptor-example-questions"><p class="promptor-example-questions-title">' + title + '</p><div class="button-grid">' + buttonsHtml + '</div></div>'
          );
        }
      }
    }

    /**
     * Add conversation summary with service chips.
     */
    function addConversationSummary(services, selectedServiceIds) {
      if (!selectedServiceIds) selectedServiceIds = [];
      if (!services || services.length === 0) return;

      $chatLog.find('.promptor-conversation-summary').remove();

      var introText = services.length === 1
        ? H.esc(i18n.conversationRecommendedService || __('Based on our conversation, here is the recommended service:', 'promptor'))
        : H.esc(i18n.conversationRecommendedServices || __('Based on our conversation so far, you need the following services:', 'promptor'));

      var closingText = services.length > 1
        ? H.esc(i18n.servicesTypicallyTogether || __('These services are typically handled together.', 'promptor'))
        : '';

      var chipsHtml = services.map(function (s) {
        var title = H.esc(s.title || '');
        var id = H.esc(s.id || '');
        var isSelected = selectedServiceIds.indexOf(id) >= 0;
        var selectedClass = isSelected ? ' chip-selected' : '';
        return '<span class="promptor-inline-chip' + selectedClass + '" data-service-id="' + id + '" data-service-title="' + title + '" data-service-description="' + H.esc(s.description || '') + '">' + title + '</span>';
      }).join(' ');

      var selectedCount = selectedServiceIds.length;
      var buttonText;
      if (selectedCount === 0) {
        buttonText = H.esc(i18n.selectServiceQuote || __('Select a service to request a quote', 'promptor'));
      } else if (selectedCount === 1) {
        buttonText = H.esc(i18n.requestQuote || __('Request Quote', 'promptor'));
      } else {
        buttonText = H.esc(i18n.requestQuoteForAll || __('Request Quote for All', 'promptor'));
      }

      var summaryHtml =
        '<div class="promptor-message-wrapper ai-message promptor-conversation-summary">' +
        '<div class="promptor-message-bubble">' +
        '<p><strong>' + introText + '</strong></p>' +
        '<p class="promptor-summary-instruction">' + H.esc(i18n.clickServiceDetails || __('Click on a service to see details and select:', 'promptor')) + '</p>' +
        '<p class="promptor-chips-container">' + chipsHtml + '</p>' +
        '<div class="promptor-service-description-container" style="display:none;"></div>' +
        (closingText ? '<p class="promptor-summary-note">' + closingText + '</p>' : '') +
        '<button class="promptor-cta-request-quote" ' + (selectedCount === 0 ? 'disabled' : '') + '>' + buttonText + '</button>' +
        '</div></div>';

      $chatLog.append(summaryHtml);
      H.scrollToBottom($chatLog);
    }

    /**
     * Open quote form for selected services.
     */
    function openQuoteForm(services) {
      $chatLog.find('.promptor-quote-form-fullwidth').remove();

      var nameLabel = H.esc(i18n.yourName || __('Your Name', 'promptor'));
      var emailLabel = H.esc(i18n.yourEmail || __('Your Email', 'promptor'));
      var phoneLabel = H.esc(i18n.yourPhone || __('Your Phone', 'promptor'));
      var notesLabel = H.esc(i18n.anythingElse || __('Anything else you would like to add?', 'promptor'));
      var submitLabel = H.esc(i18n.submitInquiry || __('Submit Inquiry', 'promptor'));

      var servicesList = services.map(function (s) { return '<li>' + H.esc(s.title) + '</li>'; }).join('');

      var formHtml =
        '<div class="promptor-quote-form-fullwidth">' +
        '<div class="promptor-quote-form-container">' +
        '<h4>' + H.esc(i18n.requestAQuote || __('Request a Quote', 'promptor')) + '</h4>' +
        '<p>' + H.esc(i18n.servicesInterestedIn || __('Services you are interested in:', 'promptor')) + '</p>' +
        '<ul class="promptor-services-list">' + servicesList + '</ul>' +
        '<form class="promptor-contact-form promptor-drawer-form" data-type="drawer-inquiry" novalidate>' +
        '<div class="promptor-form-field"><label>' + nameLabel + '*</label><input type="text" name="name" required autocomplete="name"></div>' +
        '<div class="promptor-form-field"><label>' + emailLabel + '*</label><input type="email" name="email" required autocomplete="email"></div>' +
        '<div class="promptor-form-field"><label>' + phoneLabel + '</label><input type="tel" name="phone" inputmode="tel" autocomplete="tel"></div>' +
        '<div class="promptor-form-field"><label>' + notesLabel + '</label><textarea name="notes" rows="3"></textarea></div>' +
        '<button type="submit" class="promptor-submit-button">' + submitLabel + '</button>' +
        '</form></div></div>';

      $chatLog.append(formHtml);
      H.scrollToBottom($chatLog);

      $chatLog.find('.promptor-drawer-form input[name="name"]').trigger('focus');
    }

    return {
      addToLog: addToLog,
      showTypingIndicator: showTypingIndicator,
      removeTypingIndicator: removeTypingIndicator,
      showExampleQuestions: showExampleQuestions,
      addConversationSummary: addConversationSummary,
      openQuoteForm: openQuoteForm,
      getBotAvatarHtml: getBotAvatarHtml
    };
  }

  PromptorModules.Ui = { createUi: createUi };
  global.PromptorModules = PromptorModules;

})(window, jQuery);
