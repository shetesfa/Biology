/**
 * Promptor Conversation Memory Module
 *
 * Manages session-based conversation history and service accumulation.
 *
 * @package Promptor
 * @since 1.3.0 (extracted from promptor-public.js v1.1.0)
 */
(function (global) {
  'use strict';

  var PromptorModules = global.PromptorModules || {};

  var STORAGE_KEY = 'promptor_chat_v1';
  var VERSION = '1.1.0';
  var MAX_MESSAGES = 15;

  /**
   * Load conversation state from sessionStorage.
   *
   * @param {string} contextKey Context identifier.
   * @return {Object|null} Conversation state or null.
   */
  function load(contextKey) {
    try {
      var key = STORAGE_KEY + '_' + contextKey;
      var raw = sessionStorage.getItem(key);
      if (!raw) return null;

      var data = JSON.parse(raw);

      if (data.version !== VERSION) {
        clear(contextKey);
        return null;
      }

      if (!data.messages || !Array.isArray(data.messages)) {
        return null;
      }

      return data;
    } catch (err) {
      return null;
    }
  }

  /**
   * Save full state to sessionStorage.
   *
   * @param {string} contextKey Context identifier.
   * @param {Array} messages Message array.
   * @param {Array} services Services array.
   * @return {boolean} True on success.
   */
  function save(contextKey, messages, services) {
    try {
      var key = STORAGE_KEY + '_' + contextKey;
      var trimmedMessages = messages.slice(-MAX_MESSAGES);

      var data = {
        version: VERSION,
        updated_at: Math.floor(Date.now() / 1000),
        messages: trimmedMessages,
        services: services || []
      };

      sessionStorage.setItem(key, JSON.stringify(data));
      return true;
    } catch (err) {
      return false;
    }
  }

  /**
   * Add a message to conversation history.
   *
   * @param {string} contextKey Context identifier.
   * @param {string} role 'user' or 'assistant'.
   * @param {string} content Message content.
   */
  function addMessage(contextKey, role, content) {
    var state = load(contextKey) || { messages: [], services: [] };

    state.messages.push({
      role: role,
      content: content,
      ts: Math.floor(Date.now() / 1000)
    });

    save(contextKey, state.messages, state.services);
  }

  /**
   * Add a service with duplicate prevention.
   *
   * @param {string} contextKey Context identifier.
   * @param {Object} service Service object with title.
   * @return {boolean} True if added, false if duplicate.
   */
  function addService(contextKey, service) {
    if (!service || !service.title) return false;

    var state = load(contextKey) || { messages: [], services: [] };
    var serviceId = service.id || service.title.toLowerCase().replace(/[^a-z0-9]+/g, '-');

    var exists = state.services.some(function (s) {
      var existingId = s.id || s.title.toLowerCase().replace(/[^a-z0-9]+/g, '-');
      return existingId === serviceId;
    });

    if (!exists) {
      state.services.push({
        id: serviceId,
        title: service.title,
        description: service.description || ''
      });
      save(contextKey, state.messages, state.services);
      return true;
    }

    return false;
  }

  /**
   * Get accumulated services.
   *
   * @param {string} contextKey Context identifier.
   * @return {Array} Services array.
   */
  function getServices(contextKey) {
    var state = load(contextKey);
    return state && state.services ? state.services : [];
  }

  /**
   * Clear conversation history and services.
   *
   * @param {string} contextKey Context identifier.
   * @return {boolean} True on success.
   */
  function clear(contextKey) {
    try {
      var key = STORAGE_KEY + '_' + contextKey;
      sessionStorage.removeItem(key);
      return true;
    } catch (err) {
      return false;
    }
  }

  /**
   * Get messages for display.
   *
   * @param {string} contextKey Context identifier.
   * @return {Array} Messages array.
   */
  function getMessages(contextKey) {
    var state = load(contextKey);
    return state ? state.messages : [];
  }

  PromptorModules.Conversation = {
    load: load,
    save: save,
    addMessage: addMessage,
    addService: addService,
    getServices: getServices,
    clear: clear,
    getMessages: getMessages
  };

  global.PromptorModules = PromptorModules;

})(window);
