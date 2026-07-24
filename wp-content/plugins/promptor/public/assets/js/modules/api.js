/**
 * Promptor API Module
 *
 * Handles all AJAX communication with the backend.
 *
 * @package Promptor
 * @since 1.3.0
 */
(function (global, $) {
  'use strict';

  var PromptorModules = global.PromptorModules || {};

  /**
   * Create an API instance bound to a specific endpoint and nonces.
   *
   * @param {Object} config Configuration object.
   * @param {string} config.ajaxEndpoint AJAX URL.
   * @param {string} config.aiQueryNonce Nonce for AI query.
   * @param {string} config.formNonce Nonce for form submission.
   * @param {string} config.addToCartNonce Nonce for add to cart.
   * @param {string} config.feedbackNonce Nonce for feedback.
   * @return {Object} API methods.
   */
  function createApi(config) {
    var ajaxEndpoint = config.ajaxEndpoint || '';

    /**
     * Generic AJAX POST request.
     *
     * @param {Object} data POST data.
     * @param {Object} opts Options (timeout).
     * @return {jQuery.Deferred}
     */
    function post(data, opts) {
      var timeout = (opts && opts.timeout) ? opts.timeout : 30000;
      return $.ajax({
        url: ajaxEndpoint,
        type: 'POST',
        dataType: 'json',
        data: data,
        timeout: timeout
      });
    }

    /**
     * Send an AI query.
     *
     * @param {string} query User's query.
     * @param {string} historyJson Conversation history as JSON.
     * @param {string} context Context key.
     * @return {jQuery.Deferred}
     */
    function sendAiQuery(query, historyJson, context) {
      return post({
        action: 'promptor_get_ai_suggestion',
        nonce: config.aiQueryNonce,
        query: query,
        history: historyJson,
        context: context
      });
    }

    /**
     * Submit feedback.
     *
     * @param {number} queryId Query ID.
     * @param {number} feedbackValue 1 or -1.
     * @return {jQuery.Deferred}
     */
    function sendFeedback(queryId, feedbackValue) {
      return post({
        action: 'promptor_save_feedback',
        nonce: config.feedbackNonce,
        query_id: queryId,
        feedback: feedbackValue
      });
    }

    /**
     * Submit contact/quote form.
     *
     * @param {Object} formData Form data.
     * @return {jQuery.Deferred}
     */
    function submitContactForm(formData) {
      formData.action = 'promptor_submit_contact_form';
      formData.nonce = config.formNonce;
      return post(formData);
    }

    /**
     * Add product to cart.
     *
     * @param {number} productId Product ID.
     * @param {number} queryId Query ID.
     * @return {jQuery.Deferred}
     */
    function addToCart(productId, queryId) {
      return post({
        action: 'promptor_add_to_cart',
        nonce: config.addToCartNonce,
        product_id: productId,
        query_id: queryId
      });
    }

    return {
      post: post,
      sendAiQuery: sendAiQuery,
      sendFeedback: sendFeedback,
      submitContactForm: submitContactForm,
      addToCart: addToCart
    };
  }

  PromptorModules.Api = { createApi: createApi };
  global.PromptorModules = PromptorModules;

})(window, jQuery);
