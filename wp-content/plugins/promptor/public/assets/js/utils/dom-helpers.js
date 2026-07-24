/**
 * Promptor DOM Helper Utilities
 *
 * Shared DOM manipulation helpers used across all modules.
 *
 * @package Promptor
 * @since 1.3.0
 */
(function (global) {
  'use strict';

  var PromptorModules = global.PromptorModules || {};

  /**
   * Escape text for safe insertion into HTML text nodes.
   *
   * @param {*} s Value to escape.
   * @return {string} Escaped string.
   */
  function esc(s) {
    if (s == null) return '';
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /**
   * Safe URL helper with strict validation.
   *
   * @param {string} u URL string.
   * @return {string} Validated URL or '#'.
   */
  function safeUrl(u) {
    if (!u) return '#';
    try {
      var url = new URL(String(u), window.location.origin);
      if (url.protocol !== 'http:' && url.protocol !== 'https:') {
        return '#';
      }
      return url.href;
    } catch (err) {
      return '#';
    }
  }

  /**
   * Scroll element to bottom.
   *
   * @param {jQuery} $el jQuery element.
   */
  function scrollToBottom($el) {
    var el = $el[0];
    if (el) el.scrollTop = el.scrollHeight;
  }

  /**
   * Safe integer parser with default value.
   *
   * @param {*} val Value to parse.
   * @param {number} defaultVal Default value.
   * @return {number} Parsed integer.
   */
  function safeInt(val, defaultVal) {
    if (typeof defaultVal === 'undefined') defaultVal = 0;
    var parsed = parseInt(val, 10);
    return isNaN(parsed) ? defaultVal : parsed;
  }

  /**
   * External link attributes string.
   *
   * @type {string}
   */
  var EXT_LINK = ' target="_blank" rel="noopener noreferrer nofollow" ';

  /**
   * Return a human-readable relative time string.
   *
   * @param {number} ts Unix timestamp in seconds.
   * @return {string} e.g. "just now", "2 min ago", "1 hr ago".
   */
  var _timeAgoLabels = {};

  function setTimeAgoLabels(labels) {
    _timeAgoLabels = labels || {};
  }

  function timeAgo(ts) {
    var now = Math.floor(Date.now() / 1000);
    var diff = now - ts;
    if (diff < 60) return _timeAgoLabels.justNow || 'just now';
    if (diff < 3600) return Math.floor(diff / 60) + (_timeAgoLabels.minAgo || ' min ago');
    if (diff < 86400) return Math.floor(diff / 3600) + (_timeAgoLabels.hrAgo || ' hr ago');
    return Math.floor(diff / 86400) + (_timeAgoLabels.dAgo || ' d ago');
  }

  /**
   * Debounce a function.
   *
   * @param {Function} fn Function to debounce.
   * @param {number} delay Delay in ms.
   * @return {Function} Debounced function.
   */
  function debounce(fn, delay) {
    var timer;
    return function () {
      var ctx = this;
      var args = arguments;
      clearTimeout(timer);
      timer = setTimeout(function () { fn.apply(ctx, args); }, delay);
    };
  }

  /**
   * Render a safe subset of markdown in pre-escaped text.
   *
   * Supports: **bold**, bullet lists (- / * ), numbered lists (1. ),
   * and paragraph breaks (double newline).
   *
   * IMPORTANT: Input MUST already be HTML-escaped via esc().
   *
   * @param {string} escaped Already-escaped HTML string.
   * @return {string} HTML with safe formatting applied.
   */
  function formatMessage(escaped) {
    if (!escaped) return '';

    var lines = escaped.split('\n');
    var blocks = [];
    var inUl = false;
    var inOl = false;
    var paraLines = [];

    function flushPara() {
      if (paraLines.length > 0) {
        blocks.push('<p>' + paraLines.join('<br>') + '</p>');
        paraLines = [];
      }
    }

    for (var i = 0; i < lines.length; i++) {
      var line = lines[i];

      // Numbered list: "1. text" or "2. text" etc.
      var olMatch = line.match(/^(\d+)\.\s+(.+)$/);
      if (olMatch) {
        flushPara();
        if (inUl) { blocks.push('</ul>'); inUl = false; }
        if (!inOl) { blocks.push('<ol>'); inOl = true; }
        blocks.push('<li>' + olMatch[2] + '</li>');
        continue;
      }

      // Bullet list: "- text" or "* text"
      var ulMatch = line.match(/^[-*]\s+(.+)$/);
      if (ulMatch) {
        flushPara();
        if (inOl) { blocks.push('</ol>'); inOl = false; }
        if (!inUl) { blocks.push('<ul>'); inUl = true; }
        blocks.push('<li>' + ulMatch[1] + '</li>');
        continue;
      }

      // Close open lists when a non-list line appears
      if (inUl) { blocks.push('</ul>'); inUl = false; }
      if (inOl) { blocks.push('</ol>'); inOl = false; }

      // Empty line = paragraph break
      if (line.trim() === '') {
        flushPara();
      } else {
        paraLines.push(line);
      }
    }

    // Flush remaining content
    if (inUl) { flushPara(); blocks.push('</ul>'); }
    else if (inOl) { flushPara(); blocks.push('</ol>'); }
    else { flushPara(); }

    var html = blocks.join('');

    // Bold: **text** (on already-escaped content, so safe)
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

    return html;
  }

  // Export
  PromptorModules.DomHelpers = {
    esc: esc,
    safeUrl: safeUrl,
    scrollToBottom: scrollToBottom,
    safeInt: safeInt,
    EXT_LINK: EXT_LINK,
    timeAgo: timeAgo,
    setTimeAgoLabels: setTimeAgoLabels,
    debounce: debounce,
    formatMessage: formatMessage
  };

  global.PromptorModules = PromptorModules;

})(window);
