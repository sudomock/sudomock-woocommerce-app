/**
 * SudoMock Storefront — vanilla JS (no jQuery).
 *
 * Responsibilities:
 * 1. "Customize" button click → AJAX create-session → open Studio iframe / popup.
 * 2. Listen for postMessage from Studio (render complete, close).
 * 3. Inject render preview + add to cart via AJAX.
 *
 * @package SudoMock_Product_Customizer
 * @since   1.0.0
 */
(function () {
	'use strict';

	var STUDIO_BASE = (window.sudomockStorefront && window.sudomockStorefront.studioBase) || 'https://studio.sudomock.com';
	var ajaxUrl     = (window.sudomockStorefront && window.sudomockStorefront.ajaxUrl)    || '/wp-admin/admin-ajax.php';
	var nonce       = (window.sudomockStorefront && window.sudomockStorefront.nonce)      || '';
	var i18n        = (window.sudomockStorefront && window.sudomockStorefront.i18n)       || {};

	/**
	 * Create a session via WP AJAX (server-to-server, API key never in browser).
	 *
	 * @param {string} productId  WooCommerce product ID.
	 * @param {string} mockupUuid SudoMock mockup UUID.
	 * @returns {Promise<{token: string, expires_in: number, displayMode: string}>}
	 */
	function createSession(productId, mockupUuid) {
		var body = new FormData();
		body.append('action', 'sudomock_create_session');
		body.append('nonce', nonce);
		body.append('product_id', productId);
		body.append('mockup_uuid', mockupUuid);

		return fetch(ajaxUrl, { method: 'POST', body: body })
			.then(function (r) { return r.json(); })
			.then(function (json) {
				if (!json.success) {
					// Carry the backend status so the caller can tell a permanent
					// mapping problem (401/403/404) from a transient one.
					var err = new Error(json.data && json.data.message ? json.data.message : 'Session creation failed');
					err.status = (json.data && json.data.status) ? json.data.status : 0;
					throw err;
				}
				return json.data;
			});
	}

	/**
	 * Open Studio in an iframe overlay.
	 *
	 * @param {string} token  Opaque session token (sess_xxx).
	 */
	function openStudioIframe(token) {
		// Overlay
		var overlay = document.createElement('div');
		overlay.id = 'sudomock-overlay';
		overlay.setAttribute('role', 'dialog');
		overlay.setAttribute('aria-label', 'Product Customizer');

		// Close button
		var closeBtn = document.createElement('button');
		closeBtn.className = 'sudomock-close';
		closeBtn.setAttribute('aria-label', 'Close customizer');
		closeBtn.textContent = '\u00D7';
		closeBtn.addEventListener('click', function () { closeStudio(overlay); });

		// Iframe
		var iframe = document.createElement('iframe');
		iframe.src = STUDIO_BASE + '/editor?session=' + encodeURIComponent(token);
		iframe.className = 'sudomock-iframe';
		iframe.setAttribute('allow', 'clipboard-write');

		overlay.appendChild(closeBtn);
		overlay.appendChild(iframe);
		document.body.appendChild(overlay);

		// Prevent background scroll
		document.body.style.overflow = 'hidden';
	}

	/**
	 * Open Studio in a popup window.
	 *
	 * @param {string} token  Opaque session token (sess_xxx).
	 */
	function openStudioPopup(token) {
		var url = STUDIO_BASE + '/editor?session=' + encodeURIComponent(token);
		var w = Math.min(1200, screen.width - 100);
		var h = Math.min(800, screen.height - 100);
		var left = (screen.width - w) / 2;
		var top = (screen.height - h) / 2;
		var popup = window.open(url, 'sudomock-studio',
			'width=' + w + ',height=' + h + ',left=' + left + ',top=' + top +
			',menubar=no,toolbar=no,location=no,status=no,resizable=yes,scrollbars=no'
		);

		// Poll for popup close
		var pollTimer = setInterval(function () {
			if (popup && popup.closed) {
				clearInterval(pollTimer);
				window.removeEventListener('message', handleStudioMessage);
				var activeBtn = document.querySelector('.sudomock-customize-btn');
				if (activeBtn) {
					activeBtn.classList.remove('sudomock-loading');
					activeBtn.disabled = false;
				}
			}
		}, 500);

		window.addEventListener('message', handleStudioMessage);
	}

	/**
	 * Close Studio overlay.
	 *
	 * @param {HTMLElement} overlay The overlay element.
	 */
	function closeStudio(overlay) {
		if (overlay && overlay.parentNode) {
			overlay.parentNode.removeChild(overlay);
		}
		document.body.style.overflow = '';
	}

	/**
	 * Handle messages from Studio iframe.
	 *
	 * @param {MessageEvent} event
	 */
	function handleStudioMessage(event) {
		if (event.origin !== STUDIO_BASE) {
			return; // Origin validation (B10 security fix)
		}

		var data = event.data;
		if (!data || !data.type) {
			return;
		}

		switch (data.type) {
			case 'sudomock:render-complete':
				onRenderComplete(data);
				break;
			case 'sudomock:add-to-cart':
				// Pass the Studio window (iframe contentWindow or popup) so we can
				// report success/error back to it in both display modes.
				onAddToCart(data, event.source);
				break;
			case 'sudomock:close':
				var overlay = document.getElementById('sudomock-overlay');
				closeStudio(overlay);
				break;
		}
	}

	/**
	 * Called when Studio reports a completed render.
	 *
	 * @param {Object} data  { type, renderUrl, mockupUuid, token }
	 */
	function onRenderComplete(data) {
		// Studio sends: preview_url (not renderUrl), mockup_uuid (not mockupUuid)
		var previewUrl = data.preview_url || data.renderUrl || '';
		if (!previewUrl) {
			return;
		}

		var overlay = document.getElementById('sudomock-overlay');
		closeStudio(overlay);

		showPreview(previewUrl);

		var form = document.querySelector('form.cart, form.variations_form');
		if (form) {
			setHidden(form, 'sudomock_render_url', previewUrl);
			setHidden(form, 'sudomock_mockup_uuid', data.mockup_uuid || data.mockupUuid || '');
			setHidden(form, 'sudomock_session_token', data.session || data.token || '');
		}
	}

	/**
	 * Called when Studio sends an add-to-cart message.
	 * Posts to WP AJAX, closes studio, and notifies Studio iframe of result.
	 *
	 * @param {Object} data  { type, renderUrl, mockupUuid, session, productId }
	 */
	function onAddToCart(data, studioWindow) {
		// Studio sends: preview_url (not renderUrl), mockup_uuid (not mockupUuid), product_id (not productId)
		var previewUrl = data.preview_url || data.renderUrl || '';
		if (!previewUrl) {
			notifyStudio(studioWindow, 'sudomock:cart-error', i18n.cartError || 'Could not add to cart. Please try again.');
			return;
		}

		var btn = document.querySelector('.sudomock-customize-btn');
		var productId = data.product_id || data.productId || (btn && btn.getAttribute('data-product-id')) || '';

		if (!productId) {
			console.error('[SudoMock] Cannot add to cart: no product ID.');
			notifyStudio(studioWindow, 'sudomock:cart-error', i18n.cartError || 'Could not add to cart. Please try again.');
			return;
		}

		var body = new FormData();
		body.append('action', 'sudomock_add_to_cart');
		body.append('nonce', nonce);
		body.append('product_id', productId);
		body.append('mockup_uuid', data.mockup_uuid || data.mockupUuid || '');
		body.append('preview_url', previewUrl);

		// The shopper's live variant + quantity selections from the product form.
		// Variable products carry variation_id (kept in sync by WooCommerce's own
		// variation script); without it the cart falls back to the parent product.
		var variationId = getSelectedVariationId();
		if (variationId) {
			body.append('variation_id', variationId);
		}
		body.append('quantity', getSelectedQuantity());

		// Render id for merchant cross-reference (support, re-render, audit).
		if (typeof data.render_uuid === 'string' && data.render_uuid) {
			body.append('render_uuid', data.render_uuid);
		}

		// Original customer-uploaded artwork URLs (when Studio supplies them).
		// `artwork_urls` (array, request order) wins; single-field fallback keeps
		// older Studio versions working. data: URIs and over-long values are
		// dropped — the order only ever stores short, durable URLs.
		function isShortUrl(u) {
			return typeof u === 'string' && u.length > 0 && u.length < 2000 && u.indexOf('data:') !== 0;
		}
		var artworkUrls = Array.isArray(data.artwork_urls) ? data.artwork_urls.filter(isShortUrl) : [];
		if (artworkUrls.length === 0) {
			var single = data.artwork_url || data.design_url || data.source_url || '';
			if (isShortUrl(single)) {
				artworkUrls = [single];
			}
		}
		artworkUrls.slice(0, 10).forEach(function (u) {
			body.append('artwork_urls[]', u);
		});

		fetch(ajaxUrl, { method: 'POST', body: body })
			.then(function (r) { return r.json(); })
			.then(function (json) {
				if (json.success) {
					// Success: notify Studio, close overlay, then redirect. The
					// overlay stays up until here so nothing is lost mid-request.
					notifyStudio(studioWindow, 'sudomock:cart-success', (json.data && json.data.cart_url) || '');
					closeStudio(document.getElementById('sudomock-overlay'));
					showPreview(previewUrl);
					if (json.data && json.data.cart_url) {
						window.location.href = json.data.cart_url;
					}
				} else {
					// Failure: keep the customizer OPEN so the shopper's artwork and
					// edits are preserved and they can retry. Report the error to
					// Studio instead of a blocking alert or destroying the session.
					notifyStudio(studioWindow, 'sudomock:cart-error', (json.data && json.data.message) || (i18n.cartError || 'Could not add to cart. Please try again.'));
				}
			})
			.catch(function () {
				notifyStudio(studioWindow, 'sudomock:cart-error', i18n.networkCartError || 'Network error adding to cart. Please try again.');
			});
	}

	/**
	 * Post a result message back to the Studio window (iframe or popup),
	 * origin-scoped to STUDIO_BASE. No-op if the window is gone.
	 *
	 * @param {Window} studioWindow Source window from the postMessage event.
	 * @param {string} type         'sudomock:cart-success' | 'sudomock:cart-error'.
	 * @param {string} payload      cartUrl on success, message on error.
	 */
	function notifyStudio(studioWindow, type, payload) {
		if (!studioWindow) {
			return;
		}
		var msg = { type: type };
		if (type === 'sudomock:cart-success') {
			msg.cartUrl = payload;
		} else {
			msg.message = payload;
		}
		try {
			studioWindow.postMessage(msg, STUDIO_BASE);
		} catch (e) {
			/* window closed or cross-origin — nothing to do */
		}
	}

	/**
	 * Selected variation ID from the product form (variable products only).
	 * WooCommerce's variation script keeps input[name="variation_id"] in sync.
	 *
	 * @returns {string} Variation ID, or '' when none/simple product.
	 */
	function getSelectedVariationId() {
		var input = document.querySelector('form.variations_form input[name="variation_id"]')
			|| document.querySelector('form.cart input[name="variation_id"]')
			|| document.querySelector('input[name="variation_id"]');
		var val = input ? String(input.value || '').trim() : '';
		return val && val !== '0' ? val : '';
	}

	/**
	 * Selected quantity from the product form; defaults to 1 when absent
	 * or unparseable/invalid.
	 *
	 * @returns {number} Quantity (>= 1).
	 */
	function getSelectedQuantity() {
		var input = document.querySelector('form.cart input[name="quantity"]')
			|| document.querySelector('input[name="quantity"]');
		var qty = input ? parseInt(input.value, 10) : 1;
		return qty && qty > 0 ? qty : 1;
	}

	/**
	 * Hide every customize button/root. Used when the product's mapping is
	 * invalid for the connected account, so shoppers see no dead action.
	 */
	function hideCustomizeButtons() {
		var els = document.querySelectorAll('.sudomock-customize-btn, .sudomock-customizer-root');
		Array.prototype.forEach.call(els, function (el) { el.style.display = 'none'; });
	}

	/**
	 * Show a brief, non-blocking notice (replaces blocking alert()).
	 *
	 * @param {string} message Text to show.
	 */
	function showNotice(message) {
		var existing = document.getElementById('sudomock-notice');
		if (existing) { existing.remove(); }
		var el = document.createElement('div');
		el.id = 'sudomock-notice';
		el.setAttribute('role', 'status');
		el.textContent = message;
		el.style.cssText =
			'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);' +
			'background:#dc2626;color:#fff;padding:12px 20px;border-radius:10px;' +
			'font-size:14px;font-weight:600;z-index:2147483647;max-width:90vw;' +
			'box-shadow:0 8px 30px rgba(0,0,0,0.2);';
		document.body.appendChild(el);
		setTimeout(function () { if (el.parentNode) { el.parentNode.removeChild(el); } }, 4000);
	}

	/**
	 * Insert or update a hidden input inside a form.
	 */
	function setHidden(form, name, value) {
		var input = form.querySelector('input[name="' + name + '"]');
		if (!input) {
			input = document.createElement('input');
			input.type = 'hidden';
			input.name = name;
			form.appendChild(input);
		}
		input.value = value;
	}

	/**
	 * Display the rendered preview image on the product page.
	 *
	 * @param {string} url Render image URL.
	 */
	function showPreview(url) {
		var container = document.getElementById('sudomock-preview');
		if (!container) {
			container = document.createElement('div');
			container.id = 'sudomock-preview';
			var btn = document.querySelector('.sudomock-customize-btn');
			if (btn && btn.parentNode) {
				btn.parentNode.insertBefore(container, btn.nextSibling);
			}
		}
		container.textContent = '';
		var img = document.createElement('img');
		img.src = url;
		img.alt = 'Your design';
		img.className = 'sudomock-preview-img';
		container.appendChild(img);
	}

	/**
	 * Bind click handlers to all customize buttons.
	 */
	function init() {
		window.addEventListener('message', handleStudioMessage);

		document.addEventListener('click', function (e) {
			var btn = e.target.closest('.sudomock-customize-btn');
			if (!btn) return;

			e.preventDefault();
			var productId = btn.getAttribute('data-product-id');
			var mockupUuid = btn.getAttribute('data-mockup-uuid');

			if (!productId || !mockupUuid) {
				console.error('[SudoMock] Missing product-id or mockup-uuid on button.');
				return;
			}

			btn.classList.add('sudomock-loading');
			btn.disabled = true;

			createSession(productId, mockupUuid)
				.then(function (session) {
					var mode = session.displayMode || 'iframe';
					if (mode === 'popup') {
						openStudioPopup(session.session);
					} else {
						openStudioIframe(session.session);
					}
				})
				.catch(function (err) {
					console.error('[SudoMock] Session error:', err);
					var status = err && err.status;
					// 401/403/404: this product cannot be customized for the
					// connected account (mockup deleted, or the store was
					// reconnected to a different account). Hide the button so
					// shoppers are not offered a dead action; the merchant fixes
					// the mapping in the plugin. Other errors are transient.
					if (status === 401 || status === 403 || status === 404) {
						hideCustomizeButtons();
					} else {
						showNotice(i18n.sessionError || 'Customizer is temporarily unavailable. Please try again in a moment.');
					}
				})
				.finally(function () {
					btn.classList.remove('sudomock-loading');
					btn.disabled = false;
				});
		});
	}

	// Boot
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
