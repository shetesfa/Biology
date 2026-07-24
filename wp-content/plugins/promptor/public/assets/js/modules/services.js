/**
 * Promptor Services Module
 *
 * Renders service/product/article/FAQ recommendation cards.
 *
 * @package Promptor
 * @since 1.3.0
 */
(function (global) {
  'use strict';

  var PromptorModules = global.PromptorModules || {};
  var H = PromptorModules.DomHelpers;

  /**
   * Create a card renderer.
   *
   * @param {Object} config Configuration.
   * @param {Object} config.i18n Internationalization strings.
   * @param {Function} config.__ Translation function.
   * @return {Object} Service rendering methods.
   */
  function createRenderer(config) {
    var i18n = config.i18n || {};
    var __ = config.__ || function (s) { return s; };
    var woo = config.woo || {};

    /**
     * Render recommendation cards for products, articles, FAQs, and services.
     *
     * @param {Array} services Service objects.
     * @param {Array} products Product objects.
     * @param {Array} articles Article objects.
     * @param {Array} faqs FAQ objects.
     * @return {string} HTML string.
     */
    /**
     * Render a product comparison table.
     *
     * @param {Object} comparison Comparison data with products and rows.
     * @return {string} HTML string for comparison table.
     */
    /**
     * Format a comparison cell value with appropriate styling.
     *
     * @param {string} val Cell value.
     * @param {string} key Row key (price, stock, rating, etc.).
     * @return {string} Styled HTML.
     */
    function formatComparisonCell(val, key) {
      if (!val || val === '—') return '<span style="color:#999">—</span>';

      if (key === 'price') {
        return '<span class="promptor-comparison-price">' + H.esc(val) + '</span>';
      }

      if (key === 'stock') {
        var lower = val.toLowerCase();
        if (lower.indexOf('out') !== -1) {
          return '<span class="promptor-comparison-stock-out">' + H.esc(val) + '</span>';
        }
        if (lower.indexOf('low') !== -1) {
          return '<span class="promptor-comparison-stock-low">' + H.esc(val) + '</span>';
        }
        return '<span class="promptor-comparison-stock-in">' + H.esc(val) + '</span>';
      }

      if (key === 'rating' && val !== '—') {
        var match = val.match(/([\d.]+)\/5/);
        if (match) {
          var rating = parseFloat(match[1]);
          var stars = '';
          for (var i = 1; i <= 5; i++) {
            stars += i <= Math.round(rating) ? '\u2605' : '\u2606';
          }
          return '<span class="promptor-comparison-rating"><span class="promptor-comparison-rating-stars">' + stars + '</span> ' + H.esc(val) + '</span>';
        }
      }

      return H.esc(val);
    }

    function renderComparisonTable(comparison) {
      if (!comparison || !comparison.products || comparison.products.length < 2) {
        return '';
      }

      var cols = comparison.products;
      var rows = comparison.rows || [];
      var html = '<div class="promptor-comparison-wrapper">';
      html += '<h4 class="promptor-section-title">' + H.esc(__('Product Comparison', 'promptor')) + '</h4>';
      html += '<div class="promptor-comparison-scroll"><table class="promptor-comparison-table"><thead><tr><th></th>';

      cols.forEach(function (col) {
        var img = col.image ? '<img src="' + H.safeUrl(col.image) + '" alt="' + H.esc(col.title) + '">' : '';
        var titleHtml = col.link
          ? '<a href="' + H.safeUrl(col.link) + '"' + H.EXT_LINK + '>' + H.esc(col.title) + '</a>'
          : H.esc(col.title);
        html += '<th class="promptor-comparison-col">' + img + '<span>' + titleHtml + '</span></th>';
      });
      html += '</tr></thead><tbody>';

      rows.forEach(function (row) {
        var rowKey = row.key || '';
        html += '<tr><td class="promptor-comparison-label">' + H.esc(row.label) + '</td>';
        row.values.forEach(function (val) {
          html += '<td>' + formatComparisonCell(val, rowKey) + '</td>';
        });
        html += '</tr>';
      });

      html += '</tbody></table></div>';

      // "Add all to cart" button.
      var ids = [];
      cols.forEach(function (col) { if (col.id) ids.push(col.id); });
      if (ids.length > 1) {
        html += '<div class="promptor-comparison-actions">';
        html += '<button type="button" class="promptor-product-action-btn promptor-add-all-to-cart-btn" data-product_ids="' + ids.join(',') + '">' +
          H.esc(__('Add All to Cart', 'promptor')) + '</button>';
        html += '</div>';
      }

      html += '</div>';
      return html;
    }

    function renderCards(services, products, articles, faqs, comparison) {
      var cardsHtml = '';

      // PRODUCTS
      if (Array.isArray(products) && products.length > 0) {
        var productsTitle = H.esc(__('Recommended Products', 'promptor'));
        cardsHtml += '<h4 class="promptor-section-title">' + productsTitle + '</h4><div class="promptor-product-cards">';
        products.forEach(function (p) {
          if (!p || typeof p !== 'object') return;

          var id = H.safeInt(p.id);
          var link = H.safeUrl(p.link || '#');
          var img = p.image ? H.safeUrl(p.image) : '';
          var title = H.esc(p.title || '');
          var alt = title;
          var desc = H.esc(p.description || '');
          var priceHtml = p.price || '';

          var discount = p.discount_percentage || p.sale_percentage || 0;
          var discountBadge = discount > 0
            ? '<div class="promptor-product-discount-badge">%' + H.safeInt(discount) + ' ' + H.esc(__('OFF', 'promptor')) + '</div>'
            : '';

          // Inventory-aware badges and button state.
          var inStock = p.in_stock !== false;
          var stockStatus = p.stock_status || 'instock';
          var stockQty = p.stock_quantity;
          var stockBadge = '';
          var btnDisabled = '';
          var btnLabel = H.esc(i18n.addToCart || __('Add to Cart', 'promptor'));
          var showStockBadge = woo.enable_stock_badge !== 0 && woo.enable_stock_badge !== '0';

          if (!inStock || stockStatus === 'outofstock') {
            if (showStockBadge) {
              stockBadge = '<div class="promptor-product-stock-badge promptor-stock-out">' + H.esc(__('Out of Stock', 'promptor')) + '</div>';
            }
            btnDisabled = ' disabled';
            btnLabel = H.esc(__('Out of Stock', 'promptor'));
          } else if (showStockBadge && typeof stockQty === 'number' && stockQty <= 5 && stockQty > 0) {
            stockBadge = '<div class="promptor-product-stock-badge promptor-stock-low">' + H.esc(__('Only', 'promptor')) + ' ' + H.safeInt(stockQty) + ' ' + H.esc(__('left', 'promptor')) + '</div>';
          }

          cardsHtml +=
            '<div class="promptor-product-card' + (!inStock ? ' promptor-product-out-of-stock' : '') + '"' + (id ? ' data-id="' + id + '"' : '') + '>' +
            (img && img !== '#' ? '<a href="' + link + '" class="promptor-product-image"' + H.EXT_LINK + '>' + discountBadge + stockBadge + '<img src="' + img + '" alt="' + alt + '"></a>' : '') +
            '<div class="promptor-product-details">' +
            '<h5 class="promptor-product-title"><a href="' + link + '"' + H.EXT_LINK + '>' + title + '</a></h5>' +
            (desc ? '<p class="promptor-product-description">' + desc + '</p>' : '') +
            '<div class="promptor-product-footer">' +
            (priceHtml ? '<div class="promptor-product-price" aria-label="' + H.esc(__('Price', 'promptor')) + '">' + priceHtml + '</div>' : '') +
            '<button type="button" class="promptor-product-action-btn promptor-add-to-cart-btn" data-product_id="' + id + '"' + btnDisabled + '>' +
            btnLabel +
            '</button></div></div></div>';
        });
        cardsHtml += '</div>';
      }

      // ARTICLES & DOCUMENTS
      if (Array.isArray(articles) && articles.length > 0) {
        var articlesTitle = H.esc(i18n.relatedArticles || __('Related Articles', 'promptor'));
        cardsHtml += '<h4 class="promptor-section-title">' + articlesTitle + '</h4><div class="promptor-article-cards">';
        articles.forEach(function (a) {
          if (!a || typeof a !== 'object') return;

          var link = H.safeUrl(a.link || '#');
          var img = a.image ? H.safeUrl(a.image) : '';
          var title = H.esc(a.title || '');
          var alt = title;
          var desc = H.esc(a.description || '');
          var fileSize = a.file_size || '';
          var isPDF = fileSize || a.type === 'pdf' || a.type === 'document';

          if (isPDF) {
            cardsHtml +=
              '<div class="promptor-document-card">' +
              '<div class="promptor-document-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor"><path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20M10,19L12,15H9V10H15V15L13,19H10Z"/></svg></div>' +
              '<div class="promptor-document-details"><h5 class="promptor-document-title">' + title + '</h5>' +
              (desc ? '<p class="promptor-document-description">' + desc + '</p>' : '') +
              (fileSize ? '<span class="promptor-document-size">' + H.esc(fileSize) + '</span>' : '') +
              '</div>' +
              '<a href="' + link + '" class="promptor-document-download" download' + H.EXT_LINK + '>' +
              '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg> ' +
              H.esc(__('Download', 'promptor')) + '</a></div>';
          } else {
            cardsHtml +=
              '<a href="' + link + '" class="promptor-article-card"' + H.EXT_LINK + '>' +
              (img && img !== '#' ? '<div class="promptor-article-image"><img src="' + img + '" alt="' + alt + '"></div>' : '') +
              '<div class="promptor-article-details"><h5 class="promptor-article-title">' + title + '</h5>' +
              (desc ? '<p class="promptor-article-description">' + desc + '</p>' : '') +
              '</div></a>';
          }
        });
        cardsHtml += '</div>';
      }

      // FAQS
      if (Array.isArray(faqs) && faqs.length > 0) {
        var faqsTitle = H.esc(__('Frequently Asked Questions', 'promptor'));
        var readMore = H.esc(__('Read more\u2026', 'promptor'));
        cardsHtml += '<h4 class="promptor-section-title">' + faqsTitle + '</h4><div class="promptor-faq-items">';
        faqs.forEach(function (f) {
          if (!f || typeof f !== 'object') return;

          var title = H.esc(f.title || '');
          var desc = H.esc(f.description || '');
          var link = H.safeUrl(f.link || '#');

          cardsHtml +=
            '<details class="promptor-faq-item">' +
            '<summary><span class="promptor-faq-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M11,18H13V16H11V18M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,20C7.59,20 4,16.41 4,12C4,7.59 7.59,4 12,4C16.41,4 20,7.59 20,12C20,16.41 16.41,20 12,20M12,6A4,4 0 0,0 8,10H10A2,2 0 0,1 12,8A2,2 0 0,1 14,10C14,12 11,11.75 11,15H13C13,12.75 16,12.5 16,10A4,4 0 0,0 12,6Z"/></svg></span>' +
            '<span class="promptor-faq-question">' + title + '</span>' +
            '<span class="promptor-faq-arrow"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg></span></summary>' +
            '<div class="promptor-faq-content">' + desc + (link !== '#' ? ' <a href="' + link + '"' + H.EXT_LINK + '>' + readMore + '</a>' : '') + '</div></details>';
        });
        cardsHtml += '</div>';
      }

      // SERVICES + FORM (old checkbox UI for backward compat)
      if (Array.isArray(services) && services.length > 0) {
        var servicesTitle = H.esc(__('Recommended Services', 'promptor'));
        var inquiryBtnText = H.esc(__('Make an Inquiry for Selected Services', 'promptor'));
        var nameLabel = H.esc(__('Your Name', 'promptor'));
        var emailLabel = H.esc(__('Your Email', 'promptor'));
        var phoneLabel = H.esc(__('Your Phone', 'promptor'));
        var notesLabel = H.esc(__('Anything else you would like to add?', 'promptor'));

        cardsHtml += '<h4 class="promptor-section-title">' + servicesTitle + '</h4>';
        cardsHtml += '<div class="promptor-contact-form-container"><form class="promptor-contact-form" data-type="service-inquiry" novalidate>';

        services.forEach(function (s, idx) {
          if (!s || typeof s !== 'object') return;
          var title = H.esc(s.title || '');
          var desc = H.esc(s.description || '');
          var cid = 'svc-' + idx + '-' + Date.now();

          cardsHtml +=
            '<div class="promptor-service-card">' +
            '<label class="promptor-service-label" for="' + cid + '">' +
            '<input id="' + cid + '" type="checkbox" name="selected_services[]" value="' + title + '">' +
            '<h4>' + title + '</h4></label>' +
            (desc ? '<p class="promptor-service-description">' + desc + '</p>' : '') +
            '</div>';
        });

        cardsHtml +=
          '<div class="promptor-form-fields-wrapper" style="display:none;margin-top:15px;">' +
          '<div class="promptor-form-field"><label>' + nameLabel + '*</label><input type="text" name="name" required autocomplete="name"></div>' +
          '<div class="promptor-form-field"><label>' + emailLabel + '*</label><input type="email" name="email" required autocomplete="email"></div>' +
          '<div class="promptor-form-field"><label>' + phoneLabel + '</label><input type="tel" name="phone" inputmode="tel" autocomplete="tel"></div>' +
          '<div class="promptor-form-field"><label>' + notesLabel + '</label><textarea name="notes" rows="3"></textarea></div>' +
          '</div>' +
          '<button type="submit" class="promptor-submit-button">' + inquiryBtnText + '</button>' +
          '</form></div>';
      }

      // COMPARISON TABLE (rendered inside cards wrapper, right after products)
      if (comparison && comparison.products && comparison.products.length >= 2) {
        cardsHtml += renderComparisonTable(comparison);
      }

      return cardsHtml ? '<div class="promptor-cards-wrapper">' + cardsHtml + '</div>' : '';
    }

    return {
      renderCards: renderCards,
      renderComparisonTable: renderComparisonTable
    };
  }

  PromptorModules.Services = { createRenderer: createRenderer };
  global.PromptorModules = PromptorModules;

})(window);
