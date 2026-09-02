/**
 * SudoMock Storefront — vanilla JS (no jQuery).
 *
 * Responsibilities:
	 * 1. "Customize" button click → AJAX create-session → open the Studio iframe modal.
 * 2. Complete the Studio v1 parent bootstrap handshake.
 * 3. Handle the bound design-submitted action via WooCommerce AJAX.
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
	var nonceReady  = null; // Promise: resolves once a fresh nonce is fetched.
	var UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;
	var NONCE_RE = /^[A-Za-z0-9_-]{22}$/;
	var SECRET_RE = /^[A-Za-z0-9_-]{43}$/;
	var MAX_SEEN_MESSAGES = 128;

	/**
	 * Ensure `nonce` is fresh before a protected action. On full-page-cached
	 * pages the baked nonce can be stale; fetch a new one once per page load.
	 * Non-fatal on failure — the (possibly stale) baked nonce is still tried.
	 */
	function ensureFreshNonce() {
		if (nonceReady) { return nonceReady; }
		var body = new FormData();
		body.append('action', 'sudomock_refresh_nonce');
		nonceReady = fetch(ajaxUrl, { method: 'POST', body: body })
			.then(function (r) { return r.json(); })
			.then(function (json) {
				if (json && json.success && json.data && json.data.nonce) {
					nonce = json.data.nonce;
				}
			})
			.catch(function () { /* keep the baked nonce */ });
		return nonceReady;
	}

	/**
	 * Create a session via WP AJAX (server-to-server, API key never in browser).
	 *
	 * @param {string} productId   WooCommerce product ID.
	 * @param {string} variationId Bound variation ID, or 0 for a simple product.
	 * @returns {Promise<{session: string, message_session_id: string, bootstrap_secret: string, expires_in: number}>}
	 */
	function createSession(productId, variationId) {
		var body = new FormData();
		body.append('action', 'sudomock_create_session');
		body.append('nonce', nonce);
		body.append('product_id', productId);
		body.append('variation_id', variationId);

		return fetch(ajaxUrl, { method: 'POST', body: body })
			.then(function (r) { return r.json(); })
			.then(function (json) {
				if (!json.success) {
					// Carry the typed backend failure so permanent mapping states
					// stay distinct from retryable conflicts.
					var err = new Error(json.data && json.data.message ? json.data.message : 'Session creation failed');
					err.status = (json.data && json.data.status) ? json.data.status : 0;
					err.code = (json.data && typeof json.data.error_code === 'string')
						? json.data.error_code
						: '';
					throw err;
				}
				var data = json.data;
				if (
					!data
					|| typeof data.session !== 'string'
					|| !data.session
					|| typeof data.message_session_id !== 'string'
					|| !UUID_RE.test(data.message_session_id)
					|| typeof data.bootstrap_secret !== 'string'
					|| !SECRET_RE.test(data.bootstrap_secret)
						|| typeof data.expires_in !== 'number'
						|| data.expires_in <= 0
						|| Object.prototype.hasOwnProperty.call(data, 'api_key')
					|| Object.prototype.hasOwnProperty.call(data, 'proof_key')
				) {
					throw new Error('Invalid session response');
				}
				return data;
			});
	}

	function object(value) {
		return value !== null && typeof value === 'object' && !Array.isArray(value) ? value : null;
	}

	function exact(value, keys) {
		var actual = Object.keys(value).sort();
		var expected = keys.slice().sort();
		return actual.length === expected.length && actual.every(function (key, index) {
			return key === expected[index];
		});
	}

	function validEnvelope(value, type, messageSessionId) {
		return exact(value, ['version', 'source', 'type', 'request_id', 'message_session_id', 'payload'])
			&& value.version === 1
			&& value.source === 'sudomock-studio'
			&& value.type === type
			&& typeof value.request_id === 'string'
			&& UUID_RE.test(value.request_id)
			&& value.message_session_id === messageSessionId;
	}

	function randomNonce() {
		var bytes = new Uint8Array(16);
		crypto.getRandomValues(bytes);
		var binary = '';
		Array.prototype.forEach.call(bytes, function (byte) {
			binary += String.fromCharCode(byte);
		});
		return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
	}

	function remember(seen, requestId) {
		seen.add(requestId);
		if (seen.size > MAX_SEEN_MESSAGES) {
			seen.delete(seen.values().next().value);
		}
	}

	/**
	 * Bind one Studio window to one server-minted message session.
	 */
	function createStudioBridge(studioWindow, sessionData, onClose, onActionSuccess) {
		var messageSessionId = sessionData.message_session_id;
		var bootstrapSecret = sessionData.bootstrap_secret;
		sessionData.bootstrap_secret = '';
		var seen = new Set();
		var actionRequests = new Map();
		var disposed = false;

		function post(type, requestId, payload) {
			if (disposed || !studioWindow || typeof studioWindow.postMessage !== 'function') return;
			studioWindow.postMessage({
				version: 1,
				source: 'sudomock-parent',
				type: type,
				request_id: requestId,
				message_session_id: messageSessionId,
				payload: payload,
			}, STUDIO_BASE);
		}

		function handleReady(envelope) {
			var payload = object(envelope.payload);
			if (
				!bootstrapSecret
				|| !payload
				|| !exact(payload, ['child_nonce', 'protocol_version'])
				|| typeof payload.child_nonce !== 'string'
				|| !NONCE_RE.test(payload.child_nonce)
				|| payload.protocol_version !== 1
			) return false;

			post('parent.bootstrap', envelope.request_id, {
				child_nonce: payload.child_nonce,
				parent_nonce: randomNonce(),
				bootstrap_secret: bootstrapSecret,
			});
			bootstrapSecret = '';
			return true;
		}

		// Validates the fields it acts on and ignores the rest. It used to demand an
		// exact key set, which silently refused every real submitted design from the
		// day the editor began sending its render parameters alongside them: the
		// message was dropped, no cart line was created, and nothing said why. A
		// receiver that enumerates the sender's whole message breaks on the next
		// field the sender adds, so this one names only what it needs.
		function handleDesignSubmitted(envelope) {
			var payload = object(envelope.payload);
			if (
				!payload
				|| typeof payload.mockup_uuid !== 'string'
				|| !UUID_RE.test(payload.mockup_uuid)
				|| typeof payload.render_uuid !== 'string'
				|| !UUID_RE.test(payload.render_uuid)
				|| payload.action_id !== 'add-to-cart'
			) return false;

			// The three fields this bridge acts on, not the whole message. A retry is
			// the same action when it names the same design, and keying on the whole
			// message would also serialise values this code has not inspected.
			var payloadKey = JSON.stringify([
				payload.mockup_uuid,
				payload.render_uuid,
				payload.action_id,
			]);
			var existing = actionRequests.get(envelope.request_id);
			if (existing) {
				if (existing.payloadKey !== payloadKey) return false;
				if (existing.result) {
					post('parent.action-result', envelope.request_id, existing.result);
				}
				return true;
			}
			if (actionRequests.size >= MAX_SEEN_MESSAGES) return false;

			var action = { payloadKey: payloadKey, result: null };
			actionRequests.set(envelope.request_id, action);
			addToCart(envelope)
				.catch(function () { return { success: false, cartUrl: '' }; })
				.then(function (result) {
				action.result = {
					render_uuid: payload.render_uuid,
					success: result.success === true,
				};
				post('parent.action-result', envelope.request_id, action.result);
				if (result.success && onActionSuccess) onActionSuccess(result.cartUrl);
			});
			return true;
		}

		function handleClose(envelope) {
			var payload = object(envelope.payload);
			if (
				!payload
				|| Object.keys(payload).some(function (key) { return key !== 'reason'; })
				|| (payload.reason !== undefined && typeof payload.reason !== 'string')
			) return false;
			if (onClose) onClose();
			return true;
		}

		function messageHandler(event) {
			if (disposed || event.origin !== STUDIO_BASE || event.source !== studioWindow) return;
			var envelope = object(event.data);
			if (!envelope || typeof envelope.type !== 'string' || seen.has(envelope.request_id)) return;
			if (actionRequests.has(envelope.request_id) && envelope.type !== 'studio.design-submitted') return;

			var accepted = false;
			if (validEnvelope(envelope, 'studio.ready', messageSessionId)) {
				accepted = handleReady(envelope);
			} else if (validEnvelope(envelope, 'studio.design-submitted', messageSessionId)) {
				accepted = handleDesignSubmitted(envelope);
			} else if (validEnvelope(envelope, 'studio.close', messageSessionId)) {
				accepted = handleClose(envelope);
			}
			if (accepted && envelope.type !== 'studio.design-submitted') {
				remember(seen, envelope.request_id);
			}
		}

		window.addEventListener('message', messageHandler);
		return function dispose() {
			if (disposed) return;
			disposed = true;
			bootstrapSecret = '';
			seen.clear();
			actionRequests.clear();
			window.removeEventListener('message', messageHandler);
		};
	}

	function openStudioIframe(sessionData) {
		var overlay = document.createElement('div');
		overlay.id = 'sudomock-overlay';
		overlay.setAttribute('role', 'dialog');
		overlay.setAttribute('aria-modal', 'true');
		overlay.setAttribute('aria-label', 'Product Customizer');

		var closeBtn = document.createElement('button');
		closeBtn.className = 'sudomock-close';
		closeBtn.setAttribute('aria-label', 'Close customizer');
		closeBtn.textContent = '\u00D7';

		var iframe = document.createElement('iframe');
		iframe.src = STUDIO_BASE + '/editor?session=' + encodeURIComponent(sessionData.session);
		iframe.className = 'sudomock-iframe';
		iframe.title = 'Product customizer';
		iframe.setAttribute('allow', 'clipboard-write');
		iframe.referrerPolicy = 'origin';

		overlay.appendChild(closeBtn);
		overlay.appendChild(iframe);
		document.body.appendChild(overlay);

		var disposeBridge = function () {};
		function close() {
			disposeBridge();
			if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
			document.body.style.overflow = '';
		}
		disposeBridge = createStudioBridge(
			iframe.contentWindow,
			sessionData,
			close,
			function (cartUrl) {
				setTimeout(function () {
					close();
					if (cartUrl) window.location.href = cartUrl;
				}, 800);
			}
		);

		closeBtn.addEventListener('click', close);
		overlay.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') close();
		});
		overlay.addEventListener('mousedown', function (e) {
			if (e.target === overlay) close();
		});
		closeBtn.focus();
		document.body.style.overflow = 'hidden';
	}

	var cartRequestInFlight = false;

	function addToCart(envelope) {
		if (cartRequestInFlight) {
			return Promise.resolve({ success: false, cartUrl: '' });
		}

		var body = new FormData();
		body.append('action', 'sudomock_add_to_cart');
		body.append('nonce', nonce);
		body.append('version', String(envelope.version));
		body.append('request_id', envelope.request_id);
		body.append('message_session_id', envelope.message_session_id);
		body.append('type', envelope.type);
		body.append('mockup_uuid', envelope.payload.mockup_uuid);
		body.append('render_uuid', envelope.payload.render_uuid);
		body.append('action_id', envelope.payload.action_id);
		body.append('quantity', getSelectedQuantity());

		cartRequestInFlight = true;
		var cartCtrl = new AbortController();
		var cartTimer = setTimeout(function () { cartCtrl.abort(); }, 20000);
		return fetch(ajaxUrl, { method: 'POST', body: body, signal: cartCtrl.signal })
			.then(function (r) { return r.json(); })
			.then(function (json) {
				clearTimeout(cartTimer);
				cartRequestInFlight = false;
				if (json.success) {
					return {
						success: true,
						cartUrl: (json.data && json.data.cart_url) || '',
					};
				}
				showNotice((json.data && json.data.message) || (i18n.cartError || 'Could not add to cart. Please try again.'));
				return { success: false, cartUrl: '' };
			})
			.catch(function () {
				clearTimeout(cartTimer);
				cartRequestInFlight = false;
				showNotice(i18n.networkCartError || 'Network error adding to cart. Please try again.');
				return { success: false, cartUrl: '' };
			});
	}

	/**
	 * Selected variation ID from the product form (variable products only).
	 * WooCommerce's variation script keeps input[name="variation_id"] in sync.
	 * Scoped to the given product's form when WooCommerce tagged one
	 * (data-product_id), so on multi-product-form pages the cart POST reads the
	 * SAME form the pre-open guard validated — never another product's value.
	 * Falls back to the page-global lookup for single-form/legacy themes.
	 *
	 * @param {string} productId Product the shopper is customizing.
	 * @returns {string} Variation ID, or '' when none/simple product.
	 */
	function getSelectedVariationId(productId) {
		// When THIS product's tagged form exists, trust only it — falling back to
		// the page-global lookup from here would reintroduce reading another
		// product's variation. The global chain remains for single-form/legacy
		// themes where no tagged form is found.
		var scopedForm = findVariationForm(productId);
		var input = scopedForm
			? scopedForm.querySelector('input[name="variation_id"]')
			: (document.querySelector('form.variations_form input[name="variation_id"]')
				|| document.querySelector('form.cart input[name="variation_id"]')
				|| document.querySelector('input[name="variation_id"]'));
		var val = input ? String(input.value || '').trim() : '';
		return val && val !== '0' ? val : '';
	}

	/**
	 * The variation form belonging to a specific product. On pages that render
	 * several product forms (page builders, grouped/bundle layouts) the global
	 * first-form lookup is wrong; WooCommerce tags each form with data-product_id.
	 *
	 * @param {string} productId Clicked button's product id.
	 * @returns {HTMLElement|null} That product's variations_form, or the single
	 *   page form as a fallback, or null when there is no variable product.
	 */
	function findVariationForm(productId) {
		// Only trust the form WooCommerce tagged for THIS product. A simple
		// product has no variations_form, so this returns null and the guard is
		// skipped. We must NEVER fall back to another product's form: on a page
		// with several product forms (bundles, grouped, page-builder loops) that
		// would false-block a simple product's customizer with a bogus 'choose
		// options'. The cart step re-validates the variation regardless. (Numeric
		// guard: productId is interpolated into the selector; a malformed value
		// would throw.)
		if (productId && /^\d+$/.test(productId)) {
			return document.querySelector('form.variations_form[data-product_id="' + productId + '"]');
		}
		return null;
	}

	/**
	 * Whether every attribute of a variation form has a chosen value. Reads the
	 * attribute selects directly rather than input[name="variation_id"], which
	 * WooCommerce's variation.js only fills a tick after DOM-ready (so an early
	 * click on a default-variation product would otherwise false-block).
	 *
	 * @param {HTMLElement} form variations_form element.
	 * @returns {boolean} true when chosen (or when there are no attribute selects
	 *   to judge, e.g. swatch plugins — the cart step then validates).
	 */
	function variationChosen(form) {
		var selects = form.querySelectorAll('.variations select');
		if (!selects.length) { return true; }
		for (var i = 0; i < selects.length; i++) {
			if (!selects[i].value) { return false; }
		}
		return true;
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
	 * Bind click handlers to all customize buttons.
	 */
	function init() {
		document.addEventListener('click', function (e) {
			var btn = e.target.closest('.sudomock-customize-btn');
			if (!btn) return;

			e.preventDefault();
			var productId = btn.getAttribute('data-product-id');

			if (!productId) {
				console.error('[SudoMock] Missing product-id on button.');
				return;
			}

			// Variable products: require a variation choice BEFORE opening the
			// customizer. Otherwise the shopper designs, hits add-to-cart, gets a
			// 'choose options' error, but the full-screen overlay hides the
			// variation selector — a dead end. Scoped to THIS product's form so a
			// second product's unset form can't block an unrelated button.
			var variationForm = findVariationForm(productId);
			if (variationForm && !variationChosen(variationForm)) {
				showNotice(i18n.chooseOptions || 'Please choose the product options first.');
				return;
			}

			btn.classList.add('sudomock-loading');
			btn.disabled = true;
			var variationId = getSelectedVariationId(productId) || '0';

			ensureFreshNonce()
				.then(function () { return createSession(productId, variationId); })
				.then(openStudioIframe)
				.catch(function (err) {
					var status = err && err.status;
					var code = err && err.code;
					// Hide definitive account/mapping failures and the two typed
					// terminal 409 states. Other conflicts stay retryable.
					if (
						status === 401
						|| status === 403
						|| status === 404
						|| (
							status === 409
							&& (code === 'SETUP_REQUIRED' || code === 'MOCKUP_TERMINAL')
						)
					) {
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
