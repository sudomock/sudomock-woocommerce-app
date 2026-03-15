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
					throw new Error(json.data && json.data.message ? json.data.message : 'Session creation failed');
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
		closeBtn.innerHTML = '&times;';
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
				onAddToCart(data);
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
	function onAddToCart(data) {
		// Studio sends: preview_url (not renderUrl), mockup_uuid (not mockupUuid), product_id (not productId)
		var previewUrl = data.preview_url || data.renderUrl || '';
		if (!previewUrl) {
			return;
		}

		var btn = document.querySelector('.sudomock-customize-btn');
		var productId = data.product_id || data.productId || (btn && btn.getAttribute('data-product-id')) || '';

		if (!productId) {
			console.error('[SudoMock] Cannot add to cart: no product ID.');
			return;
		}

		var body = new FormData();
		body.append('action', 'sudomock_add_to_cart');
		body.append('nonce', nonce);
		body.append('product_id', productId);
		body.append('mockup_uuid', data.mockup_uuid || data.mockupUuid || '');
		body.append('preview_url', previewUrl);

		fetch(ajaxUrl, { method: 'POST', body: body })
			.then(function (r) { return r.json(); })
			.then(function (json) {
				// Close studio
				var overlay = document.getElementById('sudomock-overlay');
				closeStudio(overlay);

				if (json.success) {
					showPreview(previewUrl);

					// Notify Studio iframe of success (if still open)
					var iframe = overlay && overlay.querySelector('.sudomock-iframe');
					if (iframe && iframe.contentWindow) {
						iframe.contentWindow.postMessage({ type: 'sudomock:cart-success', cartUrl: json.data.cart_url }, STUDIO_BASE);
					}

					// Redirect to cart or show success
					if (json.data && json.data.cart_url) {
						window.location.href = json.data.cart_url;
					}
				} else {
					alert((json.data && json.data.message) || (i18n.cartError || 'Failed to add to cart. Please try again.'));
				}
			})
			.catch(function () {
				var overlay = document.getElementById('sudomock-overlay');
				closeStudio(overlay);
				alert(i18n.networkCartError || 'Network error adding to cart.');
			});
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
		container.innerHTML = '<img src="' + url + '" alt="Your design" class="sudomock-preview-img" />';
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
					alert(err.message || (i18n.sessionError || 'Could not open customizer. Please try again.'));
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
