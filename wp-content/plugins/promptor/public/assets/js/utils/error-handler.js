/**
 * Promptor Error Handler Utility
 *
 * Standardized error creation and handling for the frontend.
 *
 * @package Promptor
 * @since 1.3.0
 */
(function (global) {
  'use strict';

  var PromptorModules = global.PromptorModules || {};

  /**
   * Known error types and their default user-facing messages.
   */
  var ERROR_MESSAGES = {
    nonce_failed:           'Security verification failed. Please refresh the page.',
    unauthorized:           'You do not have permission to perform this action.',
    rate_limited:           'Too many requests. Please wait and try again.',
    validation_error:       'Invalid input provided.',
    invalid_input:          'Invalid input provided.',
    empty_query:            'Please type your question and press Send.',
    not_found:              'The requested resource was not found.',
    api_error:              'AI service error. Please try again.',
    api_key_missing:        'API configuration needed. Please contact the site administrator.',
    api_request_failed:     'Connection issue. Please check your internet connection.',
    embedding_request_failed: 'Unable to process your query. Please try again.',
    quota_exceeded:         'Usage limit reached. Please try again later.',
    daily_limit_exceeded:   'Daily usage limit reached. Service will resume tomorrow.',
    monthly_limit_exceeded: 'Monthly usage limit reached. Please contact the administrator.',
    limit_reached:          'Query limit reached. Upgrade for unlimited access.',
    server_error:           'An internal error occurred. Please try again.',
    woocommerce_inactive:   'Shopping features are not available.',
    product_unavailable:    'This product cannot be purchased.',
    save_failed:            'Could not save data. Please try again.',
    configuration_error:    'Configuration error. Please contact administrator.',
    network_error:          'Network error. Please check your connection.',
    timeout:                'Request timed out. Please try again.',
    unknown:                'An unknown error occurred. Please try again.'
  };

  /**
   * Create a standardized error object.
   *
   * @param {string} code    Error code.
   * @param {string} message Optional custom message (uses default if empty).
   * @param {Object} data    Optional additional data.
   * @return {Object} Standardized error object.
   */
  function createError(code, message, data) {
    return {
      code: code || 'unknown',
      message: message || ERROR_MESSAGES[code] || ERROR_MESSAGES.unknown,
      data: data || {},
      timestamp: Date.now()
    };
  }

  /**
   * Create an error from a jQuery AJAX failure (jqXHR object).
   *
   * @param {Object} jqXHR The jQuery XHR object.
   * @return {Object} Standardized error object.
   */
  function fromAjaxError(jqXHR) {
    if (!jqXHR) {
      return createError('unknown');
    }

    // Network/timeout errors
    if (jqXHR.status === 0) {
      return createError('network_error');
    }

    if (jqXHR.statusText === 'timeout') {
      return createError('timeout');
    }

    // Try to extract structured error from response
    var response = null;
    try {
      response = typeof jqXHR.responseJSON !== 'undefined'
        ? jqXHR.responseJSON
        : JSON.parse(jqXHR.responseText || '{}');
    } catch (e) {
      response = null;
    }

    if (response && response.data) {
      var errorType = response.data.error_type || 'server_error';
      var errorMsg = response.data.message || '';
      return createError(errorType, errorMsg, response.data);
    }

    // Fallback based on HTTP status
    if (jqXHR.status === 403) {
      return createError('unauthorized');
    }
    if (jqXHR.status === 429) {
      return createError('rate_limited');
    }
    if (jqXHR.status >= 500) {
      return createError('server_error');
    }

    return createError('unknown');
  }

  /**
   * Create an error from a JSON response (wp_send_json_error format).
   *
   * @param {Object} response The AJAX response object.
   * @return {Object} Standardized error object.
   */
  function fromJsonResponse(response) {
    if (!response || response.success) {
      return null;
    }

    var data = response.data || {};
    var code = data.error_type || 'unknown';
    var message = data.message || '';

    return createError(code, message, data);
  }

  /**
   * Get a user-friendly message for a given error code.
   *
   * @param {string} code Error code.
   * @return {string} User-friendly message.
   */
  function getUserMessage(code) {
    return ERROR_MESSAGES[code] || ERROR_MESSAGES.unknown;
  }

  /**
   * Check if an error code is a known error type.
   *
   * @param {string} code Error code.
   * @return {boolean}
   */
  function isKnownCode(code) {
    return ERROR_MESSAGES.hasOwnProperty(code);
  }

  // Export as PromptorModules.ErrorHandler
  PromptorModules.ErrorHandler = {
    createError: createError,
    fromAjaxError: fromAjaxError,
    fromJsonResponse: fromJsonResponse,
    getUserMessage: getUserMessage,
    isKnownCode: isKnownCode
  };

  global.PromptorModules = PromptorModules;

})(window);
