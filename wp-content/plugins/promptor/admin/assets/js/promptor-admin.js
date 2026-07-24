(function ($) {
  'use strict';

  $(function () {
    const { __, _n, sprintf } =
      (window.wp && window.wp.i18n) || {
        __: (s) => s,
        _n: (s, p, n) => (n === 1 ? s : p),
        sprintf: (s, ...a) => s.replace(/%s|%d/g, () => a.shift()),
      };

    const adminData = window.promptor_admin_ajax || {};
    const dashboardData = window.promptor_dashboard_ajax || {};
    const allUiSettings = window.promptor_all_ui_settings || {};
    const $log = $('#promptor-indexing-log');
    let currentStatusSelector = null;

    // ---------- Helpers ----------
    function escapeHTML(s) {
      return String(s).replace(/[&<>"']/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
    }

    // Update selected items count display
    function updateSelectedCount($targetTab) {
      // If specific tab provided, update only that tab
      if ($targetTab && $targetTab.length) {
        const selectedCount = $targetTab.find('tbody input[type="checkbox"]:checked').length;
        const counterElement = $targetTab.find('.promptor-selected-count');
        if (counterElement.length) {
          counterElement.text(sprintf(__('%d items selected', 'promptor'), selectedCount));
        }
        return;
      }

      // Otherwise update active tab
      const activeTab = $('.promptor-tab-content.active');
      if (activeTab.length) {
        const selectedCount = activeTab.find('tbody input[type="checkbox"]:checked').length;
        const counterElement = activeTab.find('.promptor-selected-count');
        if (counterElement.length) {
          counterElement.text(sprintf(__('%d items selected', 'promptor'), selectedCount));
        }
      }
    }

    function addAdminNotice($scope, type, msg) {
      const cls = type === 'success' ? 'notice-success'
        : type === 'warning' ? 'notice-warning'
        : 'notice-error';
      const safe = escapeHTML(msg);
      $scope.find('h1, h2').first().after(
        $('<div/>', { 'class': `notice ${cls} is-dismissible` }).append($('<p/>').text(safe))
      );
    }

    const asBool = (v) => (v === true || v === 1 || v === '1' || String(v).toLowerCase() === 'true');

    function debounce(fn, wait) { let t; return function () { clearTimeout(t); const a = arguments, ctx = this; t = setTimeout(() => fn.apply(ctx, a), wait); }; }

    const chartRefs = new WeakMap();
    function initChart(ctxEl, type, data, options) {
      if (!ctxEl || typeof Chart === 'undefined') return;
      const prev = chartRefs.get(ctxEl);
      if (prev) prev.destroy();
      const inst = new Chart(ctxEl, { type, data, options });
      chartRefs.set(ctxEl, inst);
    }

    function addToLog(message) {
      if ($log.length) {
        const timestamp = new Date().toLocaleTimeString();
        const $line = $('<div/>').text(`[${timestamp}] ${message}`);
        $log.prepend($line);
      }
    }

    function updateStats(context) {
      return $.post(adminData.ajax_url, {
        action: 'promptor_get_indexing_stats',
        context,
        nonce: adminData.nonce,
      }).done((res) => {
        if (res.success)
          $(`#stats-${context}`).text(
            `${res.data.documents} / ${res.data.chunks}`
          );
      });
    }

    function toggleActionControls(disabled, $spinner = null) {
      $('.promptor-sync-btn, .promptor-clear-btn, #promptor-start-crawl-btn, #add_new_context_btn, #promptor-save-content-selection').prop('disabled', disabled);
      if ($spinner && $spinner.length) {
        $spinner.css('visibility', disabled ? 'visible' : 'hidden').toggleClass('is-active', disabled);
      }
    }

    // ---------- UI Settings / Preview ----------
    const $uiSettingsForm = $('#promptor-ui-settings-form');
    if ($uiSettingsForm.length) {
      const defaults = {
        chat_position: 'inline',
        hide_header: false,
        popup_context_source: 'default',
        primary_color: '#15b6ff',
        widget_bg_color: '#ffffff',
        user_bubble_color: '#15b6ff',
        user_text_color: '#ffffff',
        ai_bubble_color: '#f1f5f9',
        ai_text_color: '#0f172a',
        input_focus_color: '#15b6ff',
        font_size: '16',
        border_radius: '12',
        default_avatar: adminData.promptor_icon_url || '',
        header_title: __('AI Assistant', 'promptor'),
        header_subtitle: __('Typically replies in minutes', 'promptor'),
        input_placeholder: __('Ask a question...', 'promptor'),
        animation: 'fade',
      };

      function updatePreview() {
        if (!$('#promptor-preview-widget').length) return;

        const settings = {};
        $uiSettingsForm
          .find('.promptor-preview-input, input[name="promptor_ui_settings[hide_header]"]')
          .each(function () {
            let id = $(this).attr('id')
              ? $(this).attr('id').replace('ui_', '')
              : $(this).attr('name').match(/\[(.*?)\]/)[1];
            settings[id] = $(this).is(':checkbox')
              ? $(this).is(':checked')
              : $(this).val();
          });

        const isGlobal = $('#promptor-context-selector').val() === 'global_popup';
        $('#promptor-preview-header').css(
          'display',
          isGlobal ? 'flex' : settings.hide_header ? 'none' : 'flex'
        );

        $('#promptor-preview-header, #promptor-preview-button').css('backgroundColor', settings.primary_color);
        $('.promptor-preview-message.user').css({
          backgroundColor: settings.user_bubble_color,
          color: settings.user_text_color,
        });
        $('#promptor-preview-widget').css('backgroundColor', settings.widget_bg_color);
        $('#promptor-preview-body .ai').css({
          backgroundColor: settings.ai_bubble_color,
          color: settings.ai_text_color,
        });

        // Numeric guards
        const parsedBR = parseInt(settings.border_radius, 10);
        const parsedFS = parseInt(settings.font_size, 10);
        const br = isNaN(parsedBR) ? parseInt(defaults.border_radius, 10) : parsedBR;
        const fs = isNaN(parsedFS) ? parseInt(defaults.font_size, 10) : parsedFS;

        $('#promptor-preview-widget').css({ fontSize: fs + 'px' });

        $('#promptor-preview-dynamic-style').remove();
        $('head').append(
          `<style id="promptor-preview-dynamic-style">#promptor-preview-footer input:focus { border-color: ${settings.input_focus_color}; box-shadow: 0 0 0 2px ${settings.input_focus_color}33; }</style>`
        );

        $('#promptor-preview-title').text(settings.header_title);
        $('#promptor-preview-subtitle').text(settings.header_subtitle);
        $('#promptor-preview-footer input').attr('placeholder', settings.input_placeholder);
        $('#promptor-preview-avatar').attr('src', settings.default_avatar || defaults.default_avatar);

        const borderRadius = br + 'px';
        $('#promptor-preview-widget, #promptor-preview-header, .promptor-preview-message, #promptor-preview-footer input, #promptor-preview-button').css('borderRadius', borderRadius);
        $('.promptor-preview-message.user').css('border-radius', `${borderRadius} ${borderRadius} 4px ${borderRadius}`);
        $('.promptor-preview-message.ai').css('border-radius', `${borderRadius} ${borderRadius} ${borderRadius} 4px`);
      }

      function populateForm(contextKey) {
        const settings = { ...defaults, ...(allUiSettings[contextKey] || {}) };

        for (const key in settings) {
          const $el = $(`#ui_${key}, input[name="promptor_ui_settings[${key}]"]`);
          if ($el.length) {
            if ($el.is(':checkbox')) {
              $el.prop('checked', asBool(settings[key]));
            } else {
              $el.val(settings[key]);
            }
          }
        }

        $('select.promptor-preview-input').trigger('change');

        // Fix: Correctly set color via Iris API and init once
        $('.promptor-color-picker').each(function () {
          const $inp = $(this);
          const key = $inp.attr('id').replace('ui_', '');
          const val = settings[key] || defaults[key];
          if (!$inp.data('wpWpColorPicker')) {
            $inp.wpColorPicker({ change: updatePreview, clear: updatePreview });
          }
          if (typeof $inp.iris === 'function') {
            $inp.iris('color', val);
          }
        });

        $('#promptor_edited_context').val(contextKey);
        updatePreview();
      }

      // Initial color pickers (handlers only; values populateForm ile set ediliyor)
      $('.promptor-color-picker').wpColorPicker({
        change: updatePreview,
        clear: updatePreview,
      });

      // Before form submit: sync color picker values to their hidden inputs
      $uiSettingsForm.on('submit', function () {
        $('.promptor-color-picker').each(function () {
          const $inp = $(this);
          if (typeof $inp.iris === 'function') {
            const color = $inp.iris('color');
            if (color) {
              $inp.val(color);
            }
          }
        });
      });

      $uiSettingsForm.on(
        'input change',
        '.promptor-preview-input, input[name="promptor_ui_settings[hide_header]"]',
        updatePreview
      );

      $('#promptor-context-selector')
        .on('change', function () {
          const selectedContext = $(this).val();
          populateForm(selectedContext);

          const isGlobal = selectedContext === 'global_popup';

          $('.promptor-inline-setting').toggle(!isGlobal);
          $('.promptor-global-setting').toggle(isGlobal);

          const $chatPos = $('select#ui_chat_position');
          if (isGlobal) {
            $chatPos.find('option[value="inline"]').hide();
            if ($chatPos.val() === 'inline' || !$chatPos.val()) {
              $chatPos.val('bottom_right').trigger('change');
            }
          } else {
            $chatPos.find('option[value="inline"]').show();
            $chatPos.val('inline').trigger('change');
          }
        })
        .trigger('change');

      // Media frame cached
      let promptorMediaFrame = null;
      let $currentUploadTarget = null;
      $('body').on('click', '.promptor-upload-button', function (e) {
        e.preventDefault();
        $currentUploadTarget = $($(this).data('target-input'));

        if (!promptorMediaFrame) {
          promptorMediaFrame = wp.media({
            title: __('Select Image', 'promptor'),
            button: { text: __('Use This Image', 'promptor') },
            multiple: false,
          });
          promptorMediaFrame.on('select', () => {
            const attachment = promptorMediaFrame.state().get('selection').first().toJSON();
            if ($currentUploadTarget) {
              $currentUploadTarget.val(attachment.url).trigger('change');
              // Update filename display
              const $display = $($currentUploadTarget.attr('id') ? '#' + $currentUploadTarget.attr('id') + '_display' : null);
              if ($display.length) {
                const filename = attachment.filename || attachment.url.split('/').pop();
                $display.html('<span class="dashicons dashicons-format-image" style="vertical-align:middle;color:var(--promptor-success);"></span> ' + $('<span/>').text(filename).html());
              }
            }
          });
        }
        promptorMediaFrame.open();
      });

      // Remove avatar
      $('body').on('click', '.promptor-remove-avatar-btn', function (e) {
        e.preventDefault();
        const $targetInput = $($(this).data('target-input'));
        $targetInput.val('').trigger('change');
        const $display = $($targetInput.attr('id') ? '#' + $targetInput.attr('id') + '_display' : null);
        if ($display.length) {
          $display.html('<em>' + __('No file selected', 'promptor') + '</em>');
        }
        $(this).hide();
      });
    }

    // ---------- Selection limit (Lite) - Header checkbox ----------
    $('#promptor-content-selection-form').on(
      'click',
      'thead input[type="checkbox"]',
      function (e) {
        const $form = $('#promptor-content-selection-form');
        const isLite = parseInt($form.data('is-lite'), 10) === 1;
        const limit = parseInt($form.data('limit'), 10);

        if (!isLite || limit === -1) return;

        const $headerCheckbox = $(this);
        const $table = $headerCheckbox.closest('table');
        const totalCheckboxes = $table.find('tbody input[type="checkbox"]').length;

        if ($headerCheckbox.prop('checked') && totalCheckboxes > limit) {
          e.preventDefault();
          $headerCheckbox.prop('checked', false);
          PromptorToast.show( sprintf(
              __(
                'You are using Promptor Lite. You can select a maximum of %d content items. Please upgrade to Pro for unlimited selections.',
                'promptor'
              ),
              limit
            ), 'warning' );
        }
      }
    );

    // ---------- Selection limit (Lite) - Individual checkboxes ----------
    $('#promptor-content-selection-form').on(
      'click',
      'tbody input[type="checkbox"]',
      function (e) {
        const $form = $('#promptor-content-selection-form');
        const isLite = parseInt($form.data('is-lite'), 10) === 1;
        const limit = parseInt($form.data('limit'), 10);

        if (!isLite || limit === -1) return;

        const selectedCount = $form.find(
          'input[name="selected_pages[]"]:checked, input[name="selected_files[]"]:checked'
        ).length;

        if (selectedCount > limit) {
          e.preventDefault();
          PromptorToast.show( sprintf(
              __(
                'You are using Promptor Lite. You can select a maximum of %d content items. Please upgrade to Pro for unlimited selections.',
                'promptor'
              ),
              limit
            ), 'warning' );
          $(this).prop('checked', false);
        }
      }
    );

    // ---------- License test button ----------
    $('form').on('click', 'button[name="test_license"]', function (e) {
      e.preventDefault();
      const $button = $(this);
      const $form = $button.closest('form');
      const $spinner = $button.siblings('.spinner');

      $button.prop('disabled', true);
      $spinner.css('visibility', 'visible').addClass('is-active');

      const licenseKey = $('#promptor-license-key').val();

      $.post(adminData.ajax_url, {
        action: 'promptor_ajax_test_license',
        nonce: adminData.test_license_nonce,
        license_key: licenseKey,
      })
        .done(function (response) {
          $('.wrap .notice').remove();
          const type = response.success ? 'success' : 'error';
          const msg = response?.data?.message || (response.success ? __('Success!', 'promptor') : __('An unknown error occurred.', 'promptor'));
          addAdminNotice($form.closest('.wrap'), type, msg);
        })
        .fail(function () {
          $('.wrap .notice').remove();
          addAdminNotice($form.closest('.wrap'), 'error', __('An AJAX error occurred. Please try again.', 'promptor'));
        })
        .always(function () {
          $button.prop('disabled', false);
          $spinner.css('visibility', 'hidden').removeClass('is-active');
        });
    });

    // ---------- Slider label ----------
    $('.promptor-slider-input').on('input', function () {
      $(this).siblings('.promptor-slider-value').text($(this).val());
    });

    // ---------- Verify API key ----------
    $('#promptor-verify-api-key-btn').on('click', function () {
      const $btn = $(this),
        $spinner = $btn.siblings('.spinner'),
        $status = $('#api-key-status'),
        apiKey = $('#api_key').val();
      $btn.prop('disabled', true);
      $spinner.css('visibility', 'visible').addClass('is-active');
      $status.text(__('Verifying…', 'promptor')).css('color', '');
      $.post(adminData.ajax_url, {
        action: 'promptor_verify_api_key',
        nonce: adminData.verify_api_key_nonce,
        api_key: apiKey,
      })
        .done((res) =>
          $status
            .text(res.data.message)
            .css('color', res.success ? 'green' : 'red')
        )
        .fail(() =>
          $status
            .text(__('An unknown server error occurred.', 'promptor'))
            .css('color', 'red')
        )
        .always(() => {
          $btn.prop('disabled', false);
          $spinner.css('visibility', 'hidden').removeClass('is-active');
        });
    });

    // ---------- Add new context ----------
    $('#add_new_context_btn').on('click', function (e) {
      e.preventDefault();
      const $button = $(this),
        $input = $('#new_context_name'),
        contextName = $input.val().trim(),
        $spinner = $button.siblings('.spinner');
      if (!contextName) {
        PromptorToast.show( __('Please enter a name for the knowledge base.', 'promptor'), 'warning' );
        $input.focus();
        return;
      }
      toggleActionControls(true, $spinner);
      $.post(adminData.ajax_url, {
        action: 'promptor_add_context',
        nonce: adminData.manage_context_nonce,
        context_name: contextName,
      })
        .done((res) => {
          if (res.success) {
            location.reload();
          } else {
            PromptorToast.show( __('Error: ', 'promptor') + res.data.message, 'error' );
            toggleActionControls(false, $spinner);
          }
        })
        .fail(() => {
          PromptorToast.show( __('An unknown server error occurred.', 'promptor'), 'error' );
          toggleActionControls(false, $spinner);
        });
    });

    // ---------- Delete context ----------
    $('#the-list').on('click', '.promptor-delete-context-btn', function (e) {
      e.preventDefault();
      if (!confirm(__('Are you sure you want to delete this knowledge base?', 'promptor'))) return;
      const context = $(this).data('context'),
        $row = $(this).closest('tr');
      $row.css('background-color', '#ffbaba');
      $.post(adminData.ajax_url, {
        action: 'promptor_delete_context',
        nonce: adminData.manage_context_nonce,
        context,
      })
        .done((res) => {
          if (res.success) {
            $row.fadeOut(300, function () {
              $(this).remove();
            });
          } else {
            PromptorToast.show( __('Error: ', 'promptor') + res.data.message, 'error' );
            $row.css('background-color', '');
          }
        })
        .fail(() => {
          PromptorToast.show( __('An unknown server error occurred.', 'promptor'), 'error' );
          $row.css('background-color', '');
        });
    });

    // ---------- Save content selection ----------
    $('#promptor-save-content-selection').on('click', function (e) {
      e.preventDefault();
      const $button = $(this);
      const $spinner = $button.siblings('.spinner');
      const $form = $('#promptor-content-selection-form');
      const contextKey = $form.data('context');

      // Validate selection count for Lite users
      const isLite = parseInt($form.data('is-lite'), 10) === 1;
      const limit = parseInt($form.data('limit'), 10);
      const selectedCount = $form.find('input[name="selected_pages[]"]:checked, input[name="selected_files[]"]:checked').length;

      if (isLite && limit !== -1 && selectedCount > limit) {
        PromptorToast.show( sprintf(
            __(
              'You are using Promptor Lite. You can select a maximum of %d content items. Please upgrade to Pro for unlimited selections.',
              'promptor'
            ),
            limit
          ), 'warning' );
        return;
      }

      const contentData = {};
      const visibleIds = []; // Track ALL visible items (checked or not)

      // Collect all visible item IDs
      $form
        .find('input[name="selected_pages[]"], input[name="selected_files[]"]')
        .each(function () {
          visibleIds.push($(this).val());
        });

      // Collect only checked items with their roles
      $form
        .find('input[name="selected_pages[]"]:checked, input[name="selected_files[]"]:checked')
        .each(function () {
          const postId = $(this).val();
          const role = $form.find(`select[name="content_roles[${postId}]"]`).val();
          contentData[postId] = role || 'blog';
        });

      const data = {
        action: 'promptor_save_content_selection',
        nonce: adminData.manage_context_nonce,
        context_key: contextKey,
        content_data: JSON.stringify(contentData),
        visible_ids: JSON.stringify(visibleIds), // Send visible IDs to preserve non-visible items
        example_questions: $form.find('textarea[name="example_questions"]').val(),
        auto_sync_enabled: $form.find('input[name="auto_sync_enabled"]').is(':checked') ? 1 : 0,
      };

      toggleActionControls(true, $spinner);
      $.post(adminData.ajax_url, data)
        .done((res) => {
          $('.wrap > .notice').remove();
          const type = res.success ? 'success' : 'error';
          const msg = res?.data?.message || (res.success ? __('Saved.', 'promptor') : __('Unknown error', 'promptor'));
          addAdminNotice($('.wrap'), type, msg);
        })
        .fail(() => {
          $('.wrap > .notice').remove();
          addAdminNotice($('.wrap'), 'error', __('An unknown server error occurred.', 'promptor'));
        })
        .always(() => {
          toggleActionControls(false, $spinner);
          $('html, body').animate({ scrollTop: 0 }, 'slow');
        });
    });

    // ---------- Sync / Crawl / Clear ----------
    $('.promptor-knowledge-base-manager, #crawler-settings-content').on(
      'click',
      '.promptor-sync-btn, #promptor-start-crawl-btn, .promptor-clear-btn',
      function () {
        const $btn = $(this);
        const isCrawl = $btn.is('#promptor-start-crawl-btn'),
          isSync = $btn.is('.promptor-sync-btn'),
          isClear = $btn.is('.promptor-clear-btn');
        const context = isCrawl
          ? $('input[name="crawl_context"]:checked').val()
          : $btn.data('context');
        const $spinner = $btn.siblings('.spinner');
        let action,
          data = {},
          confirmMsg;

        if (isClear) {
          action = 'promptor_clear_index';
          confirmMsg = sprintf(
            __('Are you sure you want to clear the index for "%s"?', 'promptor'),
            context
          );
        } else if (isCrawl) {
          action = 'promptor_start_crawl';
          data = { sitemap_url: $('#sitemap_url').val() };
          if (!context || !data.sitemap_url)
            return PromptorToast.show( __('Please select a knowledge base and provide a sitemap URL.', 'promptor'), 'warning' );
          confirmMsg = sprintf(
            __('Sitemap will be crawled for "%s". Continue?', 'promptor'),
            context
          );
        } else {
          action = 'promptor_start_sync';
          confirmMsg = sprintf(
            __('Are you sure you want to sync manual content for "%s"?', 'promptor'),
            context
          );
        }

        if (!confirm(confirmMsg)) return;

        toggleActionControls(true, $spinner);
        $log.html('');
        addToLog(sprintf(__('Starting process for "%s"…', 'promptor'), context));

        $.post(adminData.ajax_url, { action, context, nonce: adminData.nonce, ...data })
          .done((res) => {
            if (res.success) {
              if (isClear) {
                addToLog(res.data.message);
                updateStats(context);
                toggleActionControls(false, $spinner);
              } else if (res.data.items?.length > 0) {
                addToLog(
                  res.data.message ||
                  sprintf(
                    _n('%d item found.', '%d items found.', res.data.items.length, 'promptor'),
                    res.data.items.length
                  )
                );
                processItems(res.data.items, res.data.item_type, context, $spinner);
              } else {
                addToLog(
                  res.data.message ||
                  __('No new items to process. Knowledge base is up to date.', 'promptor')
                );
                updateStats(context);
                toggleActionControls(false, $spinner);
              }
            } else {
              addToLog(
                __('ERROR: ', 'promptor') +
                (res.data?.message || __('Operation failed.', 'promptor'))
              );
              toggleActionControls(false, $spinner);
            }
          })
          .fail(() => {
            addToLog(__('Server error.', 'promptor'));
            toggleActionControls(false, $spinner);
          });
      }
    );

    function processItems(items, itemType, context, $spinner, index = 0) {
      if (index >= items.length) {
        addToLog(__('All items processed!', 'promptor'));
        updateStats(context).always(() => toggleActionControls(false, $spinner));
        if (itemType === 'url')
          $(`tr[data-context-row="${context}"] .content-source-cell`).html(
            '<span class="dashicons dashicons-admin-site-alt3"></span> ' +
            __('Crawler', 'promptor')
          );
        return;
      }
      const itemId = items[index];
      const itemLabel =
        itemType === 'url' ? itemId : sprintf(__('ID %d', 'promptor'), itemId);
      addToLog(
        sprintf(
          __('Processing item %1$d/%2$d (%3$s): %4$s…', 'promptor'),
          index + 1,
          items.length,
          itemType,
          itemLabel
        )
      );
      $.post(adminData.ajax_url, {
        action: 'promptor_process_item',
        item_id: itemId,
        item_type: itemType,
        context,
        nonce: adminData.nonce,
      })
        .done((res) => {
          const msg = res?.data?.message ? String(res.data.message) : __('OK', 'promptor');
          addToLog(res.success ? ` -> ${msg}` : ` -> ${__('ERROR:', 'promptor')} ${msg}`);
        })
        .fail(() =>
          addToLog(
            sprintf(__(' -> SERVER ERROR: Could not process item %s.', 'promptor'), itemLabel)
          )
        )
        .always(() => processItems(items, itemType, context, $spinner, index + 1));
    }

    // ---------- Inline status change (submissions) ----------
    $('.wp-list-table').on('click', '.status-option', function (e) {
      e.preventDefault();
      e.stopPropagation();
      const $link = $(this),
        $selector = $link.closest('.promptor-status-selector'),
        submissionId = $selector.data('id'),
        newStatus = $link.data('status'),
        $spinner = $selector.find('.spinner');
      $spinner.css('display', 'inline-block').addClass('is-active');
      $selector.removeClass('open');
      $.post(adminData.ajax_url, {
        action: 'promptor_update_submission_status',
        submission_id: submissionId,
        new_status: newStatus,
        nonce: adminData.submissions_nonce,
      })
        .done((res) => {
          if (res.success) {
            $selector.find('.current-status').text(res.data.new_status_text);
            const newIconClass = $link.find('.dashicons').attr('class');
            $selector.find('.dashicons').first().attr('class', newIconClass);
            $selector
              .removeClass((i, c) => (c.match(/(^|\s)status-is-\S+/g) || []).join(' '))
              .addClass('status-is-' + newStatus);
          } else {
            PromptorToast.show( sprintf(
                __('Error: %s', 'promptor'),
                res.data ? res.data.message : __('Unknown error.', 'promptor')
              ), 'error' );
          }
        })
        .fail(() => PromptorToast.show( __('Server error.', 'promptor'), 'error' ))
        .always(() => $spinner.hide().removeClass('is-active'));
    });

    // ---------- Link order ----------
    $('.wp-list-table').on('click', '.promptor-link-order-btn', function () {
      const $button = $(this),
        $wrapper = $button.closest('.order-link-wrapper'),
        submissionId = $wrapper.data('submission-id'),
        orderId = $wrapper.find('input[type="number"]').val();
      if (!orderId) return PromptorToast.show( __('Please enter an Order ID.', 'promptor'), 'warning' );
      $wrapper.find('.spinner').css('visibility', 'visible').addClass('is-active');
      $button.prop('disabled', true);
      $.post(adminData.ajax_url, {
        action: 'promptor_link_order',
        nonce: adminData.link_order_nonce,
        submission_id: submissionId,
        order_id: orderId,
      })
        .done((res) => {
          if (res.success) {
            // Güvenlik: HTML server tarafında wp_kses_post ile filtrelenmeli.
            const $newContent = $('<div>').html(res.data.html);
            $wrapper.empty().append($newContent.contents()).append('<span class="spinner" style="visibility: hidden;"></span>');
          } else {
            PromptorToast.show( sprintf(__('Error: %s', 'promptor'), res.data.message), 'error' );
            $button.prop('disabled', false);
          }
        })
        .fail(() => {
          PromptorToast.show( __('Unknown server error.', 'promptor'), 'error' );
          $button.prop('disabled', false);
        })
        .always(() =>
          $wrapper.find('.spinner').css('visibility', 'hidden').removeClass('is-active')
        );
    });

    // ---------- Unlink order ----------
    $('.wp-list-table').on('click', '.promptor-unlink-order-btn', function () {
      const $button = $(this),
        $wrapper = $button.closest('.order-link-wrapper'),
        submissionId = $wrapper.data('submission-id');
      $wrapper.find('.spinner').css('visibility', 'visible').addClass('is-active');
      $button.prop('disabled', true);
      $.post(adminData.ajax_url, {
        action: 'promptor_unlink_order',
        nonce: adminData.link_order_nonce,
        submission_id: submissionId,
      })
        .done((res) => {
          if (res.success) {
            // Güvenlik: HTML server tarafında wp_kses_post ile filtrelenmeli.
            const $newContent = $('<div>').html(res.data.html);
            $wrapper.empty().append($newContent.contents()).append('<span class="spinner" style="visibility: hidden;"></span>');
          } else {
            PromptorToast.show( sprintf(__('Error: %s', 'promptor'), res.data.message), 'error' );
            $button.prop('disabled', false);
          }
        })
        .fail(() => {
          PromptorToast.show( __('Unknown server error.', 'promptor'), 'error' );
          $button.prop('disabled', false);
        })
        .always(() =>
          $wrapper.find('.spinner').css('visibility', 'hidden').removeClass('is-active')
        );
    });

    // ---------- Open/close status selector ----------
    $(document).on('click', '.promptor-status-selector', function (e) {
      e.stopPropagation();
      if (currentStatusSelector && !currentStatusSelector.is($(this))) {
        currentStatusSelector.removeClass('open');
      }
      $(this).toggleClass('open');
      currentStatusSelector = $(this);
    });
    $(document).on('click', () => {
      if (currentStatusSelector) currentStatusSelector.removeClass('open');
    });

    // ---------- Sub tabs ----------
    $('.promptor-sub-tabs a.nav-tab').on('click', function (e) {
      e.preventDefault();
      const $this = $(this);
      const href = $this.attr('href');
      const urlParams = new URLSearchParams(href.substring(href.indexOf('?')));
      const subTabKey = urlParams.get('sub_tab');
      if (!subTabKey) return;
      const $targetPane = $('.promptor-tab-pane[id*="' + subTabKey + '"]');
      if ($targetPane.length) {
        $this.addClass('nav-tab-active').siblings().removeClass('nav-tab-active');
        $('.promptor-tab-pane').hide();
        $targetPane.show();
        window.history.pushState({}, '', href);
        $('input[name="_wp_http_referer"]').val(href);
      }
    });
    const initialSubTab = new URLSearchParams(window.location.search).get('sub_tab');
    if (initialSubTab) {
      $(`.promptor-sub-tabs a.nav-tab[href*="sub_tab=${initialSubTab}"]`).trigger('click');
    } else if ($('.promptor-sub-tabs').length > 0) {
      $('.promptor-sub-tabs a.nav-tab:first').trigger('click');
    }

    // ---------- Content tabs ----------
    $('.promptor-content-tabs a.nav-tab').on('click', function (e) {
      e.preventDefault();
      const $this = $(this);
      const targetId = $this.attr('href');
      $this.addClass('nav-tab-active').siblings().removeClass('nav-tab-active');
      $('.promptor-tab-content').removeClass('active').hide();
      const $targetTab = $(targetId);
      $targetTab.addClass('active').show();
      // Update selection counter when switching tabs
      updateSelectedCount($targetTab);
    });

    // Hide empty tabs (tabs with no content)
    $('.promptor-tab-content').each(function() {
      const $tab = $(this);
      const hasTable = $tab.find('table tbody tr').length > 0;
      if (!hasTable) {
        // Hide the tab content
        $tab.hide();
        // Hide the corresponding navigation tab
        const tabId = $tab.attr('id');
        $('.promptor-content-tabs a.nav-tab[href="#' + tabId + '"]').hide();
      }
    });

    if ($('.promptor-content-tabs').length) {
      // Click first visible tab
      $('.promptor-content-tabs a.nav-tab:visible:first').trigger('click');
    }

    // ---------- Search filter (debounced) ----------
    $('.promptor-content-search')
      .on('keyup', debounce(function () {
        const term = $(this).val().toLowerCase();
        const target = '#' + $(this).data('target-table');
        $(`${target} tbody tr`).each((i, row) =>
          $(row).toggle($(row).text().toLowerCase().indexOf(term) > -1)
        );
      }, 120))
      .on('keydown', (e) => {
        if (e.keyCode === 13) return false;
      });

    // ---------- Charts (safe guards for datasets) ----------
    const act = dashboardData?.activity;
    if (act?.labels && (act?.queries?.length || act?.submissions?.length || act?.revenue?.length)) {
      initChart(
        document.getElementById('promptorActivityChart'),
        'line',
        {
          labels: act.labels,
          datasets: [
            {
              label: dashboardData.i18n?.queries || __('Queries', 'promptor'),
              data: act.queries,
              borderColor: 'rgb(54, 162, 235)',
              backgroundColor: 'rgba(54, 162, 235, 0.1)',
              yAxisID: 'y',
              tension: 0.3,
            },
            {
              label: dashboardData.i18n?.submissions || __('Submissions', 'promptor'),
              data: act.submissions,
              borderColor: 'rgb(75, 192, 192)',
              backgroundColor: 'rgba(75, 192, 192, 0.1)',
              yAxisID: 'y',
              tension: 0.3,
            },
            {
              label: dashboardData.i18n?.revenue || __('Revenue', 'promptor'),
              data: act.revenue,
              borderColor: 'rgb(255, 159, 64)',
              backgroundColor: 'rgba(255, 159, 64, 0.1)',
              yAxisID: 'y1',
              tension: 0.3,
            },
            {
              label: dashboardData.i18n?.satisfaction || __('Satisfaction (%)', 'promptor'),
              data: act.satisfaction,
              borderColor: 'rgb(153, 102, 255)',
              backgroundColor: 'rgba(153, 102, 255, 0.1)',
              yAxisID: 'y2',
              tension: 0.3,
              spanGaps: true,
            },
          ],
        },
        {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: { type: 'linear', display: true, position: 'left', beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Count' } },
            y1: { type: 'linear', display: true, position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, title: { display: true, text: 'Revenue' } },
            y2: { type: 'linear', display: true, position: 'right', min: 0, max: 100, grid: { drawOnChartArea: false }, title: { display: true, text: 'Satisfaction (%)' } },
          },
          plugins: {
            legend: {
              display: true,
              position: 'top',
            },
          },
        }
      );
    }

    const pipe = dashboardData?.pipeline;
    if (pipe?.labels && pipe?.values?.length) {
      initChart(
        document.getElementById('promptorLeadPipelineChart'),
        'doughnut',
        {
          labels: pipe.labels,
          datasets: [
            {
              label: __('Leads', 'promptor'),
              data: pipe.values,
              backgroundColor: ['#FFC107', '#03A9F4', '#4CAF50', '#F44336'],
              hoverOffset: 4,
            },
          ],
        },
        { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
      );
    }

    const cat = dashboardData?.category_revenue;
    if (cat?.labels && cat?.values?.length) {
      initChart(
        document.getElementById('promptorCategoryRevenueChart'),
        'bar',
        {
          labels: cat.labels,
          datasets: [
            {
              label: __('Revenue', 'promptor'),
              data: cat.values,
              backgroundColor: 'rgba(153, 102, 255, 0.6)',
              borderColor: 'rgba(153, 102, 255, 1)',
              borderWidth: 1,
            },
          ],
        },
        {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: { x: { beginAtZero: true } },
        }
      );
    }

    // ---------- Notification test (email/slack) ----------
    $('#promptor-send-test-email, #promptor-send-test-slack').on('click', function (e) {
      e.preventDefault();
      const $button = $(this);
      const isEmail = $button.is('#promptor-send-test-email');
      const action = isEmail ? 'promptor_send_test_email' : 'promptor_send_test_slack';
      const nonce = isEmail ? adminData.test_email_nonce : adminData.test_slack_nonce;
      const $spinner = $button.siblings('.spinner');
      const $status = $button.siblings('.promptor-test-status');
      const $form = $button.closest('form');

      $button.prop('disabled', true);
      $spinner.css('visibility', 'visible').addClass('is-active');
      $status.text(__('Saving settings…', 'promptor')).css('color', '');

      const formData = $form.serializeArray();
      formData.push({ name: 'action', value: 'promptor_save_notification_settings' });
      formData.push({ name: 'nonce', value: adminData.save_notifications_nonce });

      $.post(adminData.ajax_url, formData)
        .done(function (saveResponse) {
          if (!saveResponse.success) {
            $status
              .text(sprintf(__('Error saving settings: %s', 'promptor'), saveResponse.data.message))
              .css('color', 'red');
            $button.prop('disabled', false);
            $spinner.css('visibility', 'hidden').removeClass('is-active');
            return;
          }

          $status.text(__('Settings saved. Sending test…', 'promptor'));
          $.post(adminData.ajax_url, { action: action, nonce: nonce })
            .done((res) =>
              $status.text(res.data.message).css('color', res.success ? 'green' : 'red')
            )
            .fail(() =>
              $status.text(__('Server error during test.', 'promptor')).css('color', 'red')
            )
            .always(() => {
              $button.prop('disabled', false);
              $spinner.css('visibility', 'hidden').removeClass('is-active');
            });
        })
        .fail(function () {
          $status.text(__('Server error while saving settings.', 'promptor')).css('color', 'red');
          $button.prop('disabled', false);
          $spinner.css('visibility', 'hidden').removeClass('is-active');
        });
    });

    // ---------- How-to toggle ----------
    const $howToToggle = $('.how-to-toggle');
    if ($howToToggle.length) {
      $howToToggle.on('click', function (e) {
        e.preventDefault();
        $('#promptor-how-to').slideToggle('fast');
      });
    }

    // ---------- Email templates left list ----------
    $('.promptor-email-templates-list a').on('click', function (e) {
      e.preventDefault();
      const $this = $(this);
      const targetPaneId = $this.attr('href');

      $('.promptor-email-templates-list a').removeClass('active');
      $this.addClass('active');

      $('.template-editor-pane').hide();
      $(targetPaneId).show();
      $(window).trigger('resize');
    });
    if ($('.promptor-email-templates-list a.active').length) {
      $('.promptor-email-templates-list a.active').trigger('click');
    } else if ($('.promptor-email-templates-list a').length) {
      $('.promptor-email-templates-list a').first().trigger('click');
    }

    // ---------- Bulk role apply ----------
    $('.promptor-content-selection-wrapper').on(
      'click',
      '.promptor-bulk-apply-btn',
      function (e) {
        e.preventDefault();
        const $button = $(this);
        const $selector = $button.siblings('.promptor-bulk-action-selector');
        const selectedAction = $selector.val();

        if (selectedAction === '-1') {
          PromptorToast.show( __('Please choose a bulk action.', 'promptor'), 'warning' );
          return;
        }

        const $activeTabPane = $selector.closest('.promptor-tab-content.active');
        const $checkedItems = $activeTabPane.find(
          'tbody .check-column input[type="checkbox"]:checked'
        );

        if ($checkedItems.length === 0) {
          PromptorToast.show( __('Please select at least one item to proceed.', 'promptor'), 'warning' );
          return;
        }

        const postIds = $checkedItems.map(function () { return $(this).val(); }).get();
        const newRole = selectedAction.replace('change-role-', '');
        const contextKey = $('#promptor-content-selection-form').data('context');

        $button.prop('disabled', true);

        $.post(adminData.ajax_url, {
          action: 'promptor_bulk_update_roles',
          nonce: adminData.manage_context_nonce,
          context_key: contextKey,
          post_ids: postIds,
          new_role: newRole,
        })
          .done(function (response) {
            if (response.success) {
              $checkedItems.each(function () {
                const postId = $(this).val();
                $activeTabPane
                  .find(`.promptor-role-selector[name="content_roles[${postId}]"]`)
                  .val(newRole);
              });
              PromptorToast.show( response.data.message, 'info' );
            } else {
              PromptorToast.show( sprintf(__('Error: %s', 'promptor'), response.data.message), 'error' );
            }
          })
          .fail(function () {
            PromptorToast.show( __('A server error occurred. Please try again.', 'promptor'), 'error' );
          })
          .always(function () {
            $button.prop('disabled', false);
            $selector.val('-1');
          });
      }
    );

    // ---------- Single role change save ----------
    $('#promptor-content-selection-form').on(
      'change',
      '.promptor-role-selector',
      function () {
        const $selector = $(this);
        const postId = $selector.attr('name').match(/\[(\d+)\]/)[1];
        const newRole = $selector.val();
        const $form = $selector.closest('#promptor-content-selection-form');
        const contextKey = $form.data('context');
        const $row = $selector.closest('tr');

        $row.addClass('saving');

        $.post(adminData.ajax_url, {
          action: 'promptor_update_single_role',
          nonce: adminData.manage_context_nonce,
          context_key: contextKey,
          post_id: postId,
          new_role: newRole,
        })
          .done(function (response) {
            if (response.success) {
              $row.addClass('save-success');
              setTimeout(function () {
                $row.removeClass('save-success');
              }, 1000);
            } else {
              PromptorToast.show( sprintf(
                  __('Error: %s', 'promptor'),
                  response.data.message || __('Unknown error', 'promptor')
                ), 'error' );
            }
          })
          .fail(function () {
            PromptorToast.show( __('A server error occurred. Please try again.', 'promptor'), 'error' );
          })
          .always(function () {
            $row.removeClass('saving');
          });
      }
    );

    // ---------- Submission Details Modal ----------
    const modal = document.getElementById('promptor-details-modal');
    if (modal) {
      document.querySelectorAll('.promptor-view-details').forEach(function (btn) {
        btn.addEventListener('click', function () {
          const modalQuery = document.getElementById('modal-query');
          const modalResponse = document.getElementById('modal-full_ai_response');

          if (modalQuery) {
            modalQuery.textContent = btn.dataset.query || '';
          }

          if (modalResponse) {
            try {
              const jsonResponse = JSON.parse(btn.dataset.full_ai_response);
              modalResponse.textContent = JSON.stringify(jsonResponse, null, 2);
            } catch (e) {
              modalResponse.textContent = btn.dataset.full_ai_response || '';
              console.warn('JSON parse error:', e);
            }
          }

          modal.style.display = 'flex';
        });
      });

      const closeModal = () => modal.style.display = 'none';

      modal.addEventListener('click', function (e) {
        if (e.target === modal) {
          closeModal();
        }
      });

      // Add ESC key support
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
          closeModal();
        }
      });

      // Trap focus inside modal
      modal.addEventListener('keydown', function (e) {
        if (e.key === 'Tab') {
          const focusableElements = modal.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
          );
          const firstElement = focusableElements[0];
          const lastElement = focusableElements[focusableElements.length - 1];

          if (e.shiftKey && document.activeElement === firstElement) {
            if (lastElement) {
              lastElement.focus();
              e.preventDefault();
            }
          } else if (!e.shiftKey && document.activeElement === lastElement) {
            if (firstElement) {
              firstElement.focus();
              e.preventDefault();
            }
          }
        }
      });

      const closeButton = modal.querySelector('.modal-close');
      if (closeButton) {
        closeButton.addEventListener('click', closeModal);
      }
    }

    // ---------- AI-Powered Example Questions Generator ----------
    $('#promptor-generate-questions').on('click', function (e) {
      e.preventDefault();

      const $button = $(this);
      const $textarea = $('#example_questions');
      const $spinner = $button.siblings('.spinner');
      const $status = $('#promptor-generate-status');
      const contextKey = $button.data('context');
      const nonce = $button.data('nonce');

      // Backend will check if there's indexed content
      $button.prop('disabled', true);
      $spinner.addClass('is-active');
      $status.text('');

      $.post(adminData.ajax_url, {
        action: 'promptor_generate_example_questions',
        nonce: nonce,
        context_key: contextKey
      })
      .done(function (response) {
        if (response.success && response.data.questions) {
          const questions = response.data.questions.join('\n');
          $textarea.val(questions);
          $status.text(__('✓ Questions generated successfully!', 'promptor'));

          // Fade out success message after 3 seconds
          setTimeout(function () {
            $status.fadeOut(function () {
              $(this).text('').show();
            });
          }, 3000);
        } else {
          PromptorToast.show( sprintf(__('Error: %s', 'promptor'), response.data.message || __('Failed to generate questions', 'promptor')), 'error' );
        }
      })
      .fail(function (xhr) {
        let errorMsg = __('A server error occurred. Please try again.', 'promptor');
        if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
          errorMsg = xhr.responseJSON.data.message;
        }
        PromptorToast.show( errorMsg, 'error' );
      })
      .always(function () {
        $button.prop('disabled', false);
        $spinner.removeClass('is-active');
      });
    });

  // Initialize selected count on page load
  if ($('.promptor-tab-content').length) {
    updateSelectedCount();
  }

  // KB Content Selection - Update count when checkboxes change
  $(document).on('change', '.promptor-tab-content input[type="checkbox"]', function () {
    const $tab = $(this).closest('.promptor-tab-content');

    // Free version: Check 3 item limit
    if (!adminData.is_premium) {
      const selectedCount = $tab.find('tbody input[type="checkbox"]:checked').length;
      const maxItems = parseInt(adminData.max_free_items, 10) || 3;

      if (selectedCount > maxItems) {
        // Uncheck this checkbox
        $(this).prop('checked', false);
        PromptorToast.show( sprintf(
          __('You are using Promptor Lite. You can select a maximum of %d content items. Please upgrade to Pro for unlimited selections.', 'promptor'),
          maxItems
        ), 'warning' );
        return;
      }
    }

    updateSelectedCount($tab);
  });

  // KB Content Selection - Select/Deselect all on current page
  $(document).on('click', '.promptor-select-page-btn', function () {
    const $tab = $(this).closest('.promptor-tab-content');
    const $checkboxes = $tab.find('tbody input[type="checkbox"]');
    const allChecked = $checkboxes.filter(':checked').length === $checkboxes.length;

    // If selecting all (not deselecting)
    if (!allChecked) {
      // Free version: Check 3 item limit
      if (!adminData.is_premium) {
        const maxItems = parseInt(adminData.max_free_items, 10) || 3;
        const itemsToSelect = $checkboxes.length;

        if (itemsToSelect > maxItems) {
          PromptorToast.show( sprintf(
            __('You are using Promptor Lite. You can select a maximum of %d content items. Please upgrade to Pro for unlimited selections.', 'promptor'),
            maxItems
          ), 'warning' );
          // Select only the first maxItems items
          $checkboxes.slice(0, maxItems).prop('checked', true);
          updateSelectedCount($tab);
          return;
        }
      }
    }

    $checkboxes.prop('checked', !allChecked);
    updateSelectedCount($tab);
  });

  // KB Content Selection - Select all (with limit)
  $(document).on('click', '.promptor-select-all-btn', function () {
    const $button = $(this);
    const $tab = $button.closest('.promptor-tab-content');
    const totalItems = parseInt($button.data('total'), 10);
    const $checkboxes = $tab.find('tbody input[type="checkbox"]');

    // Free version: Check 3 item limit first
    if (!adminData.is_premium) {
      const maxItems = parseInt(adminData.max_free_items, 10) || 3;
      const currentlySelected = $checkboxes.filter(':checked').length;

      if (currentlySelected >= maxItems) {
        // Already at limit
        PromptorToast.show( sprintf(
          __('You have already selected the maximum of %d content items. Please upgrade to Pro for unlimited selections.', 'promptor'),
          maxItems
        ), 'warning' );
        return;
      }

      const remainingSlots = maxItems - currentlySelected;

      if (totalItems > maxItems) {
        PromptorToast.show( sprintf(
          __('You are using Promptor Lite. You can select a maximum of %d content items. Selecting %d more items to reach the limit.', 'promptor'),
          maxItems,
          remainingSlots
        ), 'warning' );
      }

      // Select only the remaining allowed items (unchecked ones only)
      $checkboxes.filter(':not(:checked)').slice(0, remainingSlots).prop('checked', true);
      updateSelectedCount($tab);
      return;
    }

    // Pro version: Safety limit for performance
    const maxLimit = 500;
    if (totalItems > maxLimit) {
      const confirmMsg = sprintf(
        __('You are about to select %1$d items. For performance reasons, we limit bulk selection to %2$d items. Do you want to select the first %2$d items?', 'promptor'),
        totalItems,
        maxLimit
      );
      if (!confirm(confirmMsg)) {
        return;
      }
    }

    // Check all currently loaded checkboxes
    $checkboxes.prop('checked', true);
    updateSelectedCount($tab);

    // If there are more items, show a notice
    const loadedCount = $checkboxes.length;
    if (totalItems > loadedCount) {
      PromptorToast.show( sprintf(
          __('%1$d items selected. Note: There are %2$d more items. Load them first to select all.', 'promptor'),
          loadedCount,
          totalItems - loadedCount
        ), 'warning' );
    }
  });

  // Load More Content Handler
  $(document).on('click', '.promptor-load-more-btn', function () {
    const $button = $(this);
    const $spinner = $button.siblings('.spinner');
    const postType = $button.data('post-type');
    const context = $button.data('context');
    const currentPage = parseInt($button.data('page'), 10);
    const maxPages = parseInt($button.data('max-pages'), 10);
    const nextPage = currentPage + 1;

    // Disable button and show spinner
    $button.prop('disabled', true);
    $spinner.show();

    $.ajax({
      url: ajaxurl,
      type: 'POST',
      data: {
        action: 'promptor_load_more_content',
        nonce: promptor_admin_ajax.kb_nonce,
        post_type: postType,
        context: context,
        paged: nextPage,
      },
    })
      .done(function (response) {
        if (response.success && response.data.html) {
          // Append new rows to the table
          const $table = $('#table-' + postType + ' tbody');
          $table.append(response.data.html);

          // Update selected count for the active tab
          const $tab = $button.closest('.promptor-tab-content');
          updateSelectedCount($tab);

          if (response.data.has_more) {
            // Update button for next page
            $button.data('page', nextPage);
            const loaded = response.data.current_page * 50;
            const remaining = Math.max(0, response.data.total_found - loaded);
            $button.html(
              sprintf(__('Load More (%d remaining)', 'promptor'), remaining)
            );
          } else {
            // No more items, hide button
            $button.closest('.promptor-load-more-wrapper').hide();
          }
        } else {
          PromptorToast.show( __('Failed to load more content.', 'promptor'), 'error' );
        }
      })
      .fail(function () {
        PromptorToast.show( __('A server error occurred. Please try again.', 'promptor'), 'error' );
      })
      .always(function () {
        $button.prop('disabled', false);
        $spinner.hide();
      });
  });

  // ---------- Lead Score Breakdown Toggle ----------
  $(document).on('click', '.promptor-score-toggle', function (e) {
    e.preventDefault();
    e.stopPropagation();

    const $button = $(this);
    const rowId = $button.data('row-id');
    const $breakdownRow = $('#' + rowId);
    const isExpanded = $button.attr('aria-expanded') === 'true';

    // Close all other breakdowns (only one open at a time)
    $('.promptor-score-toggle[aria-expanded="true"]').each(function () {
      if (!$(this).is($button)) {
        $(this).attr('aria-expanded', 'false');
        const otherRowId = $(this).data('row-id');
        $('#' + otherRowId).removeClass('visible').attr('aria-hidden', 'true');
      }
    });

    // Toggle current breakdown
    if (!isExpanded) {
      $button.attr('aria-expanded', 'true');
      $breakdownRow.addClass('visible').attr('aria-hidden', 'false');
    } else {
      $button.attr('aria-expanded', 'false');
      $breakdownRow.removeClass('visible').attr('aria-hidden', 'true');
    }
  });

  // Keyboard support for score breakdown toggle
  $(document).on('keydown', '.promptor-score-toggle', function (e) {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      $(this).click();
    }
  });

  // ========== AI Behavior System (v1.3.0) ==========

  // --- Tone Preview Map (loaded from localized data for i18n) ---
  function getTonePreview(tone) {
    var data = window.promptorBehaviorData;
    if (data && data.toneStyles && data.toneStyles[tone]) {
      return data.toneStyles[tone];
    }
    return [];
  }

  // --- Dynamic Behavior Preview ---
  function getEffectiveTone(tone) {
    var data = window.promptorBehaviorData;
    if (!data || !data.allowedTones) return tone;
    if (data.allowedTones.indexOf(tone) === -1) return 'professional';
    return tone;
  }

  function updateBehaviorPreview() {
    const data = window.promptorBehaviorData;
    if (!data) return;

    const $previewBox = $('#promptor-behavior-preview-box');
    const $previewMeta = $('#promptor-behavior-preview-meta');
    const $previewCard = $previewBox.closest('.promptor-preview-card');
    if (!$previewBox.length) return;

    const customPromptEnabled = $('#promptor_enable_custom_prompt').is(':checked');

    if (customPromptEnabled) {
      $previewCard.addClass('promptor-preview-card--manual');
      $previewMeta.html(
        '<span class="promptor-preview-badge promptor-preview-badge--manual">' +
        '<span class="dashicons dashicons-editor-code"></span>' +
        data.i18n.manualBadge +
        '</span>'
      );
      $previewBox.text(data.i18n.manualPromptActive);
      return;
    }

    $previewCard.removeClass('promptor-preview-card--manual');

    const persona = $('#promptor_persona').val();
    const rawTone = $('#promptor_tone').val();
    const tone = getEffectiveTone(rawTone);
    const personaData = data.personas[persona];
    const toneData = data.tones[tone];

    if (personaData && toneData) {
      $previewMeta.html(
        '<span class="promptor-preview-badge">' +
        '<span class="dashicons dashicons-businessperson"></span>' +
        data.i18n.personaMeta.replace('%s', personaData.label) +
        '</span>' +
        '<span class="promptor-preview-separator">&middot;</span>' +
        '<span class="promptor-preview-badge">' +
        '<span class="dashicons dashicons-format-status"></span>' +
        data.i18n.toneMeta.replace('%s', toneData.label) +
        '</span>'
      );

      // BEHAVIOR section
      var html = '<span class="promptor-preview-behavior-label">' +
        '<span class="dashicons dashicons-lightbulb"></span>' +
        (data.i18n.behaviorLabel || 'Behavior') +
        '</span>';
      if (personaData.behaviors && personaData.behaviors.length) {
        html += '<ul class="promptor-preview-behavior-list">';
        personaData.behaviors.forEach(function (b) {
          html += '<li>' + $('<span>').text(b).html() + '</li>';
        });
        html += '</ul>';
      }

      // STYLE section
      var toneItems = getTonePreview(tone);
      if (toneItems.length) {
        html += '<span class="promptor-preview-behavior-label promptor-preview-style-label">' +
          '<span class="dashicons dashicons-format-status"></span>' +
          (data.i18n.styleLabel || 'Style') +
          '</span>';
        html += '<ul class="promptor-preview-behavior-list">';
        toneItems.forEach(function (item) {
          html += '<li>' + $('<span>').text(item).html() + '</li>';
        });
        html += '</ul>';
      }

      $previewBox.html(html);
    }
  }

  // Bind persona/tone change events
  $('#promptor_persona, #promptor_tone').on('change', updateBehaviorPreview);

  // Render initial preview on page load (ensures STYLE bullets appear for default tone)
  updateBehaviorPreview();

  // --- Collapsible Sections ---
  $(document).on('click', '#promptor-kb-overrides-toggle, #promptor-advanced-prompt-toggle', function () {
    const $header = $(this);
    const $content = $header.next('.promptor-collapsible-content');
    const $icon = $header.find('.promptor-collapse-icon');

    $content.slideToggle(200);
    $icon.toggleClass('promptor-collapsed-open');
  });

  // --- KB Override Toggle ---
  $(document).on('change', '.promptor-kb-override-toggle', function () {
    const kb = $(this).data('kb');
    const $fields = $('.promptor-kb-override-fields[data-kb="' + kb + '"]');
    const $row = $(this).closest('.promptor-kb-override-row');
    const $status = $row.find('.promptor-kb-status');
    const data = window.promptorBehaviorData;

    if ($(this).is(':checked')) {
      $fields.slideDown(150);
      $row.addClass('promptor-kb-override-row--active');
      if (data && data.i18n.customBehavior) {
        $status.text(data.i18n.customBehavior);
      }
    } else {
      $fields.slideUp(150);
      $row.removeClass('promptor-kb-override-row--active');
      if (data && data.i18n.usesGlobalSettings) {
        $status.text(data.i18n.usesGlobalSettings);
      }
    }
  });

  // --- Advanced Manual Prompt Toggle ---
  $('#promptor_enable_custom_prompt').on('change', function () {
    const $wrapper = $('#promptor-custom-prompt-wrapper');
    if ($(this).is(':checked')) {
      $wrapper.slideDown(150);
    } else {
      $wrapper.slideUp(150);
    }
    updateBehaviorPreview();
  });

  });
})(jQuery);