/**
 * Jalma Quick Stock for WooCommerce — admin table
 *
 * Provides inline edit + auto-save for WC stock quantities and low-stock
 * thresholds. Fetches paginated products from the plugin's REST API,
 * renders them into a table, and saves changes via debounced POST /update.
 *
 * Globals: jqswData (injected via wp_localize_script), jQuery.
 */
(function ($) {
	'use strict';

	var data = window.jqswData || {};
	var S = data.strings || {};

	var state = {
		page: 1,
		perPage: 50,
		search: '',
		category: 0,
		stockStatus: '',
		totalPages: 1,
		totalProducts: 0,
		expandedVariableIds: {},
	};

	// ────────────────────────────────────────────────────────────────────
	// API helpers
	// ────────────────────────────────────────────────────────────────────

	function apiGet(path, params) {
		var url = data.restUrl + path;
		if (params) {
			var qs = Object.keys(params)
				.filter(function (k) { return params[k] !== '' && params[k] !== 0 && params[k] != null; })
				.map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]); })
				.join('&');
			if (qs) url += (url.indexOf('?') === -1 ? '?' : '&') + qs;
		}
		return fetch(url, {
			headers: { 'X-WP-Nonce': data.nonce },
			credentials: 'same-origin',
		}).then(function (r) {
			if (!r.ok) throw new Error('HTTP ' + r.status);
			return r.json();
		});
	}

	function apiPost(path, body) {
		return fetch(data.restUrl + path, {
			method: 'POST',
			headers: {
				'X-WP-Nonce': data.nonce,
				'Content-Type': 'application/json',
			},
			credentials: 'same-origin',
			body: JSON.stringify(body),
		}).then(function (r) {
			if (!r.ok) return r.json().then(function (err) { throw new Error(err.message || 'HTTP ' + r.status); });
			return r.json();
		});
	}

	// ────────────────────────────────────────────────────────────────────
	// Rendering
	// ────────────────────────────────────────────────────────────────────

	function escapeHtml(str) {
		if (str == null) return '';
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	function formatGlobalHint(amount) {
		return S.globalHint ? S.globalHint.replace('%d', amount) : amount + ' (global)';
	}

	function renderCategoryFilter() {
		var $sel = $('.jqsw-filter-category').empty();
		$sel.append('<option value="0">' + escapeHtml(S.allCategories) + '</option>');
		(data.categories || []).forEach(function (cat) {
			var prefix = '';
			for (var i = 0; i < cat.depth; i++) prefix += '— ';
			$sel.append('<option value="' + cat.id + '">' + escapeHtml(prefix + cat.name) + '</option>');
		});
		if ($.fn.select2) {
			$sel.select2({ width: '220px', placeholder: S.allCategories, allowClear: true });
		}
	}

	function renderStockStatusFilter() {
		var $sel = $('.jqsw-filter-stock-status').empty();
		$sel.append('<option value="">' + escapeHtml(S.anyStatus) + '</option>');
		$sel.append('<option value="instock">' + escapeHtml(S.inStock) + '</option>');
		$sel.append('<option value="outofstock">' + escapeHtml(S.outOfStock) + '</option>');
		$sel.append('<option value="onbackorder">' + escapeHtml(S.onBackorder) + '</option>');
		$sel.append('<option value="notmanaged">' + escapeHtml(S.notManaged) + '</option>');
	}

	function renderProductRow(p) {
		var thumb = p.thumbnail
			? '<img src="' + escapeHtml(p.thumbnail) + '" alt="" class="jqsw-thumb">'
			: '<span class="jqsw-thumb jqsw-thumb-placeholder"></span>';

		var sku = p.sku ? escapeHtml(p.sku) : '<em class="jqsw-no-sku">' + escapeHtml(S.noSku) + '</em>';
		var titleLink = p.edit_url
			? '<a href="' + escapeHtml(p.edit_url) + '" target="_blank" rel="noopener">' + escapeHtml(p.title) + '</a>'
			: escapeHtml(p.title);

		var productCell =
			'<td class="jqsw-col-product">' +
				'<div class="jqsw-product-inner">' +
					thumb +
					'<div class="jqsw-product-meta">' +
						'<div class="jqsw-product-title">' + titleLink + '</div>' +
						'<div class="jqsw-product-sku">' + escapeHtml(S.sku) + ': ' + sku + '</div>' +
					'</div>' +
				'</div>';

		// If variable, add the "manage per variation" checkbox
		if (p.type === 'variable') {
			productCell +=
				'<label class="jqsw-variation-toggle">' +
					'<input type="checkbox" class="jqsw-toggle-variation-stock" data-product-id="' + p.id + '"' +
					(p.manage_per_variation ? ' checked' : '') + '> ' +
					escapeHtml(S.managePerVariation) +
				'</label>';
		}

		productCell += '</td>';

		var stockCell, thresholdCell;

		if (p.type === 'variable' && p.manage_per_variation) {
			// Parent row in per-variation mode: no direct edit, show muted label
			stockCell = '<td class="jqsw-col-stock"><span class="jqsw-muted">' + escapeHtml(S.perVariation) + '</span></td>';
			thresholdCell = '<td class="jqsw-col-threshold"><span class="jqsw-muted">' + escapeHtml(S.perVariation) + '</span></td>';
		} else if (!p.manage_stock) {
			// Not tracked: show enable button
			stockCell =
				'<td class="jqsw-col-stock">' +
					'<span class="jqsw-muted">' + escapeHtml(S.notTracked) + '</span> ' +
					'<button type="button" class="button button-small jqsw-enable-management" data-product-id="' + p.id + '">' + escapeHtml(S.enableManagement) + '</button>' +
				'</td>';
			thresholdCell = '<td class="jqsw-col-threshold"></td>';
		} else {
			var stockVal = p.stock == null ? '' : p.stock;
			stockCell =
				'<td class="jqsw-col-stock">' +
					'<input type="number" step="1" class="jqsw-stock-input" data-product-id="' + p.id + '" value="' + escapeHtml(stockVal) + '">' +
				'</td>';

			var thresholdVal = p.low_stock_amount == null ? '' : p.low_stock_amount;
			var placeholder  = formatGlobalHint(data.globalLowStockAmount || 0);
			thresholdCell =
				'<td class="jqsw-col-threshold">' +
					'<input type="number" step="1" class="jqsw-threshold-input" data-product-id="' + p.id + '" value="' + escapeHtml(thresholdVal) + '" placeholder="' + escapeHtml(placeholder) + '">' +
				'</td>';
		}

		var statusCell = '<td class="jqsw-col-status"><span class="jqsw-status" data-product-id="' + p.id + '"></span></td>';

		return '<tr class="jqsw-row jqsw-row-' + p.type + '" data-product-id="' + p.id + '">' + productCell + stockCell + thresholdCell + statusCell + '</tr>';
	}

	function renderVariationRow(v) {
		var thumb = v.thumbnail
			? '<img src="' + escapeHtml(v.thumbnail) + '" alt="" class="jqsw-thumb">'
			: '<span class="jqsw-thumb jqsw-thumb-placeholder"></span>';

		var sku = v.sku ? escapeHtml(v.sku) : '<em class="jqsw-no-sku">' + escapeHtml(S.noSku) + '</em>';

		var productCell =
			'<td class="jqsw-col-product jqsw-variation-cell">' +
				'<div class="jqsw-product-inner jqsw-variation-inner">' +
					'<span class="jqsw-variation-arrow">└</span>' +
					thumb +
					'<div class="jqsw-product-meta">' +
						'<div class="jqsw-product-title">' + escapeHtml(v.title) + '</div>' +
						'<div class="jqsw-product-sku">' + escapeHtml(S.sku) + ': ' + sku + '</div>' +
					'</div>' +
				'</div>' +
			'</td>';

		var stockVal     = v.stock == null ? '' : v.stock;
		var thresholdVal = v.low_stock_amount == null ? '' : v.low_stock_amount;
		var placeholder  = formatGlobalHint(data.globalLowStockAmount || 0);

		var stockCell =
			'<td class="jqsw-col-stock">' +
				'<input type="number" step="1" class="jqsw-stock-input" data-product-id="' + v.id + '" value="' + escapeHtml(stockVal) + '">' +
			'</td>';

		var thresholdCell =
			'<td class="jqsw-col-threshold">' +
				'<input type="number" step="1" class="jqsw-threshold-input" data-product-id="' + v.id + '" value="' + escapeHtml(thresholdVal) + '" placeholder="' + escapeHtml(placeholder) + '">' +
			'</td>';

		var statusCell = '<td class="jqsw-col-status"><span class="jqsw-status" data-product-id="' + v.id + '"></span></td>';

		return '<tr class="jqsw-row jqsw-row-variation" data-product-id="' + v.id + '" data-parent-id="' + v.parent_id + '">' + productCell + stockCell + thresholdCell + statusCell + '</tr>';
	}

	function renderTable(products) {
		var $tbody = $('#jqsw-tbody').empty();
		if (!products || products.length === 0) {
			$tbody.append('<tr><td colspan="4" class="jqsw-empty">' + escapeHtml(S.noResults) + '</td></tr>');
			return;
		}
		products.forEach(function (p) {
			$tbody.append(renderProductRow(p));
			// If variable + per-variation mode, fetch and append variations
			if (p.type === 'variable' && p.manage_per_variation) {
				loadVariations(p.id);
			}
		});
	}

	function renderPagination() {
		var info = (S.pageOf || 'Page %1$d of %2$d (%3$d products)')
			.replace('%1$d', state.page)
			.replace('%2$d', state.totalPages)
			.replace('%3$d', state.totalProducts);
		$('.jqsw-page-info').text(info);
		$('.jqsw-prev').prop('disabled', state.page <= 1);
		$('.jqsw-next').prop('disabled', state.page >= state.totalPages);
	}

	// ────────────────────────────────────────────────────────────────────
	// Loading
	// ────────────────────────────────────────────────────────────────────

	function load() {
		$('#jqsw-tbody').html('<tr class="jqsw-loading-row"><td colspan="4">' + escapeHtml(S.loading) + '</td></tr>');

		apiGet('products', {
			page: state.page,
			per_page: state.perPage,
			search: state.search,
			category: state.category,
			stock_status: state.stockStatus,
		}).then(function (res) {
			state.totalPages = res.pages || 1;
			state.totalProducts = res.total || 0;
			renderTable(res.products);
			renderPagination();
		}).catch(function (err) {
			$('#jqsw-tbody').html('<tr><td colspan="4" class="jqsw-error">' + escapeHtml(err.message) + '</td></tr>');
		});
	}

	function loadVariations(parentId) {
		apiGet('variations/' + parentId).then(function (res) {
			var $parent = $('tr[data-product-id="' + parentId + '"]');
			// Remove any existing variation rows for this parent
			$('tr[data-parent-id="' + parentId + '"]').remove();
			var $anchor = $parent;
			res.variations.forEach(function (v) {
				var $row = $(renderVariationRow(v));
				$anchor.after($row);
				$anchor = $row;
			});
		});
	}

	// ────────────────────────────────────────────────────────────────────
	// Save (debounced per-input)
	// ────────────────────────────────────────────────────────────────────

	var saveTimers = {};

	function scheduleSave($input, field) {
		var productId = $input.data('product-id');
		var key       = productId + ':' + field;
		clearTimeout(saveTimers[key]);
		saveTimers[key] = setTimeout(function () {
			commitSave($input, field);
		}, 500);
	}

	function commitSave($input, field) {
		var productId = $input.data('product-id');
		var val       = $input.val();
		var payload   = { product_id: productId };

		if (field === 'stock') {
			payload.stock = val === '' ? null : parseInt(val, 10);
		} else {
			// threshold: empty string = clear override (null)
			payload.low_stock_amount = val === '' ? null : parseInt(val, 10);
		}

		var $status = $('tr[data-product-id="' + productId + '"] .jqsw-status');
		$status.removeClass('jqsw-status-saved jqsw-status-error').addClass('jqsw-status-saving').text(S.saving);

		apiPost('update', payload).then(function () {
			$status.removeClass('jqsw-status-saving').addClass('jqsw-status-saved').text(S.saved);
			setTimeout(function () { $status.text(''); $status.removeClass('jqsw-status-saved'); }, 2000);
		}).catch(function (err) {
			$status.removeClass('jqsw-status-saving').addClass('jqsw-status-error').attr('title', err.message).text(S.saveError);
		});
	}

	// ────────────────────────────────────────────────────────────────────
	// Event bindings
	// ────────────────────────────────────────────────────────────────────

	function bindEvents() {
		// Filter changes
		$(document).on('change', '.jqsw-filter-category', function () {
			state.category = parseInt($(this).val() || 0, 10);
			state.page = 1;
			load();
		});
		$(document).on('change', '.jqsw-filter-stock-status', function () {
			state.stockStatus = $(this).val() || '';
			state.page = 1;
			load();
		});

		// Search (debounced)
		var searchTimer;
		$(document).on('input', '.jqsw-filter-search', function () {
			var val = $(this).val();
			clearTimeout(searchTimer);
			searchTimer = setTimeout(function () {
				state.search = val;
				state.page = 1;
				load();
			}, 400);
		});

		// Pagination
		$(document).on('click', '.jqsw-prev', function () {
			if (state.page > 1) { state.page--; load(); }
		});
		$(document).on('click', '.jqsw-next', function () {
			if (state.page < state.totalPages) { state.page++; load(); }
		});

		// Inline edit — stock
		$(document).on('input', '.jqsw-stock-input', function () {
			scheduleSave($(this), 'stock');
		});
		$(document).on('blur', '.jqsw-stock-input', function () {
			var productId = $(this).data('product-id');
			var key = productId + ':stock';
			if (saveTimers[key]) { clearTimeout(saveTimers[key]); commitSave($(this), 'stock'); }
		});

		// Inline edit — threshold
		$(document).on('input', '.jqsw-threshold-input', function () {
			scheduleSave($(this), 'low_stock_amount');
		});
		$(document).on('blur', '.jqsw-threshold-input', function () {
			var productId = $(this).data('product-id');
			var key = productId + ':low_stock_amount';
			if (saveTimers[key]) { clearTimeout(saveTimers[key]); commitSave($(this), 'low_stock_amount'); }
		});

		// Keyboard: Enter → save and jump to next row's stock input
		$(document).on('keydown', '.jqsw-stock-input, .jqsw-threshold-input', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				var $all = $('.jqsw-stock-input, .jqsw-threshold-input');
				var idx = $all.index(this);
				if (idx >= 0 && idx < $all.length - 1) {
					$all.eq(idx + 1).focus().select();
				} else {
					$(this).blur();
				}
			}
		});

		// Variable product: toggle per-variation stock management.
		// Replace the parent row in place, and remove/add variation rows
		// as needed — no full reload.
		$(document).on('change', '.jqsw-toggle-variation-stock', function () {
			var productId = $(this).data('product-id');
			var checked   = this.checked;
			var $this     = $(this);
			$this.prop('disabled', true);
			apiPost('toggle-variation-stock', { product_id: productId, manage_per_variation: checked }).then(function (updated) {
				// Remove any existing variation rows for this parent
				$('tr[data-parent-id="' + productId + '"]').remove();
				// Replace the parent row with the updated product state
				var $parent = $('tr[data-product-id="' + productId + '"]').first();
				$parent.replaceWith(renderProductRow(updated));
				// If we're now in per-variation mode, fetch and append the variations
				if (updated.type === 'variable' && updated.manage_per_variation) {
					loadVariations(productId);
				}
				// Brief status feedback on the new parent row
				var $status = $('tr[data-product-id="' + productId + '"] .jqsw-status').first();
				$status.removeClass('jqsw-status-saving jqsw-status-error').addClass('jqsw-status-saved').text(S.saved);
				setTimeout(function () { $status.text('').removeClass('jqsw-status-saved'); }, 2000);
			}).catch(function (err) {
				// Roll back the checkbox state on failure
				$this.prop('checked', !checked);
				$this.prop('disabled', false);
				var $status = $('tr[data-product-id="' + productId + '"] .jqsw-status').first();
				$status.removeClass('jqsw-status-saving jqsw-status-saved').addClass('jqsw-status-error').attr('title', err.message).text(S.saveError);
			});
		});

		// Enable stock management on an untracked product — replace just the row.
		$(document).on('click', '.jqsw-enable-management', function () {
			var productId = $(this).data('product-id');
			var $btn      = $(this);
			$btn.prop('disabled', true);
			apiPost('enable-stock-management', { product_id: productId }).then(function (updated) {
				var $row = $('tr[data-product-id="' + productId + '"]').first();
				$row.replaceWith(renderProductRow(updated));
				// Focus the new stock input so user can start typing immediately
				var $newInput = $('tr[data-product-id="' + productId + '"] .jqsw-stock-input').first();
				if ($newInput.length) { $newInput.focus().select(); }
				// Brief status feedback
				var $status = $('tr[data-product-id="' + productId + '"] .jqsw-status').first();
				$status.removeClass('jqsw-status-saving jqsw-status-error').addClass('jqsw-status-saved').text(S.saved);
				setTimeout(function () { $status.text('').removeClass('jqsw-status-saved'); }, 2000);
			}).catch(function (err) {
				$btn.prop('disabled', false);
				alert(err.message);
			});
		});
	}

	// ────────────────────────────────────────────────────────────────────
	// Init
	// ────────────────────────────────────────────────────────────────────

	$(function () {
		renderCategoryFilter();
		renderStockStatusFilter();
		bindEvents();
		load();
	});
})(jQuery);
