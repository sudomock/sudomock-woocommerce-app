const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { resolve } = require('node:path');
const { test } = require('node:test');
const vm = require('node:vm');
const { webcrypto } = require('node:crypto');

const jsSource = readFileSync(resolve(__dirname, '../assets/js/storefront.js'), 'utf8');
const apiClientSource = readFileSync(resolve(__dirname, '../includes/class-sudomock-api-client.php'), 'utf8');
const storefrontPhpSource = readFileSync(resolve(__dirname, '../includes/class-sudomock-storefront.php'), 'utf8');
const productPhpSource = readFileSync(resolve(__dirname, '../includes/class-sudomock-product.php'), 'utf8');
const adminPhpSource = readFileSync(resolve(__dirname, '../includes/class-sudomock-admin.php'), 'utf8');
const uninstallSource = readFileSync(resolve(__dirname, '../uninstall.php'), 'utf8');

const mockupUuid = '44444444-4444-4444-8444-444444444444';
const renderUuid = '55555555-5555-4555-8555-555555555555';
const childNonce = 'AAAAAAAAAAAAAAAAAAAAAA';

function session(messageSessionId, secret) {
	return {
		session: 'sess_' + messageSessionId,
		message_session_id: messageSessionId,
		bootstrap_secret: secret,
		expires_in: 900,
	};
}

function sessionError(status, errorCode) {
	return { error: true, status, errorCode };
}

function envelope(type, requestId, messageSessionId, payload) {
	return {
		version: 1,
		source: 'sudomock-studio',
		type,
		request_id: requestId,
		message_session_id: messageSessionId,
		payload,
	};
}

function createHarness(sessionResponses, cartSuccess = true, deferCart = false) {
	const windowListeners = new Map();
	const documentListeners = new Map();
	const fetchCalls = [];
	const frames = [];
	let releaseCart = () => {};
	const cartGate = deferCart
		? new Promise((resolveCart) => { releaseCart = resolveCart; })
		: Promise.resolve();

	const button = {
		classList: { add() {}, remove() {} },
		disabled: false,
		style: {},
		getAttribute(name) {
			if (name === 'data-product-id') return '100';
			return null;
		},
	};
	const document = {
		readyState: 'complete',
		body: { style: {}, appendChild() {} },
		addEventListener(type, listener) {
			const listeners = documentListeners.get(type) || [];
			listeners.push(listener);
			documentListeners.set(type, listeners);
		},
		querySelector(selector) {
			if (selector.includes('input[name="quantity"]')) return { value: '2' };
			if (selector.includes('input[name="variation_id"]')) return { value: '201' };
			return null;
		},
		querySelectorAll(selector) {
			if (selector === '.sudomock-customize-btn, .sudomock-customizer-root') {
				return [button];
			}
			return [];
		},
		getElementById() {
			return null;
		},
		createElement(tagName) {
			const contentWindow = tagName === 'iframe'
				? {
					messages: [],
					postMessage(message, origin) {
						this.messages.push({ message, origin });
					},
				}
				: undefined;
			if (contentWindow) frames.push(contentWindow);
			return {
				id: '',
				className: '',
				style: {},
				parentNode: null,
				contentWindow,
				setAttribute() {},
				appendChild() {},
				addEventListener() {},
				focus() {},
			};
		},
	};
	const windowObject = {
		sudomockStorefront: {
			ajaxUrl: '/wp-admin/admin-ajax.php',
			nonce: 'nonce-old',
			studioBase: 'https://studio.sudomock.com',
			i18n: {},
		},
		location: { href: '' },
		addEventListener(type, listener) {
			const listeners = windowListeners.get(type) || [];
			listeners.push(listener);
			windowListeners.set(type, listeners);
		},
		removeEventListener(type, listener) {
			const listeners = windowListeners.get(type) || [];
			windowListeners.set(type, listeners.filter((candidate) => candidate !== listener));
		},
	};

	const context = {
		window: windowObject,
		document,
		crypto: webcrypto,
		btoa: (value) => Buffer.from(value, 'binary').toString('base64'),
		FormData,
		AbortController,
		Uint8Array,
		Set,
		Map,
		Promise,
		Number,
		Object,
		Array,
		String,
		Math,
		console,
		setTimeout: () => 1,
		clearTimeout: () => {},
		fetch: async (url, options) => {
			const action = options.body instanceof FormData ? options.body.get('action') : '';
			fetchCalls.push({ url, options, action });
			if (action === 'sudomock_refresh_nonce') {
				return { json: async () => ({ success: true, data: { nonce: 'nonce-fresh' } }) };
			}
			if (action === 'sudomock_create_session') {
				const next = sessionResponses.shift();
				if (!next) throw new Error('No session response');
				if (next.error) {
					return {
						json: async () => ({
							success: false,
							data: {
								status: next.status,
								error_code: next.errorCode,
								message: 'Session unavailable',
							},
						}),
					};
				}
				return { json: async () => ({ success: true, data: next }) };
			}
			if (action === 'sudomock_add_to_cart') {
				await cartGate;
				return {
					json: async () => cartSuccess
						? { success: true, data: { cart_url: '/cart' } }
						: { success: false, data: { message: 'Variant unavailable' } },
				};
			}
			throw new Error('Unexpected request');
		},
	};

	vm.runInNewContext(jsSource, context);
	return {
		button,
		fetchCalls,
		frames,
		releaseCart,
		click() {
			const event = {
				preventDefault() {},
				target: { closest: () => button },
			};
			(documentListeners.get('click') || []).forEach((listener) => listener(event));
		},
		message(event) {
			(windowListeners.get('message') || []).slice().forEach((listener) => listener(event));
		},
	};
}

async function flush() {
	await new Promise((resolveFlush) => setImmediate(resolveFlush));
	await new Promise((resolveFlush) => setImmediate(resolveFlush));
}

for (const errorCode of ['SETUP_REQUIRED', 'MOCKUP_TERMINAL']) {
	test(`Woo hides a stale mapping for permanent 409 code ${errorCode}`, async () => {
		const harness = createHarness([sessionError(409, errorCode)]);

		harness.click();
		await flush();

		assert.equal(harness.button.style.display, 'none');
	});
}

test('Woo keeps a non-permanent 409 mapping retryable', async () => {
	const harness = createHarness([sessionError(409, 'SESSION_CONFLICT')]);

	harness.click();
	await flush();

	assert.notEqual(harness.button.style.display, 'none');
});

test('Woo bridge isolates two iframe secrets and rejects origin/source/session/replay', async () => {
	const idOne = '11111111-1111-4111-8111-111111111111';
	const idTwo = '22222222-2222-4222-8222-222222222222';
	const secretOne = 'A'.repeat(43);
	const secretTwo = 'B'.repeat(43);
	const harness = createHarness([session(idOne, secretOne), session(idTwo, secretTwo)]);

	harness.click();
	await flush();
	harness.click();
	await flush();
	assert.equal(harness.frames.length, 2);

	const readyOne = envelope(
		'studio.ready',
		'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
		idOne,
		{ child_nonce: childNonce, protocol_version: 1 }
	);
	harness.message({ origin: 'https://evil.example', source: harness.frames[0], data: readyOne });
	harness.message({ origin: 'https://studio.sudomock.com', source: harness.frames[1], data: readyOne });
	harness.message({
		origin: 'https://studio.sudomock.com',
		source: harness.frames[0],
		data: { ...readyOne, message_session_id: idTwo },
	});
	assert.equal(harness.frames[0].messages.length, 0);
	assert.equal(harness.frames[1].messages.length, 0);

	harness.message({ origin: 'https://studio.sudomock.com', source: harness.frames[0], data: readyOne });
	harness.message({ origin: 'https://studio.sudomock.com', source: harness.frames[0], data: readyOne });
	assert.equal(harness.frames[0].messages.length, 1);
	assert.deepEqual(
		JSON.parse(JSON.stringify(harness.frames[0].messages[0].message)),
		{
			version: 1,
			source: 'sudomock-parent',
			type: 'parent.bootstrap',
			request_id: readyOne.request_id,
			message_session_id: idOne,
			payload: {
				child_nonce: childNonce,
				parent_nonce: harness.frames[0].messages[0].message.payload.parent_nonce,
				bootstrap_secret: secretOne,
			},
		}
	);
	assert.match(harness.frames[0].messages[0].message.payload.parent_nonce, /^[A-Za-z0-9_-]{22}$/);
	assert.doesNotMatch(JSON.stringify(harness.frames[0].messages[0]), new RegExp(secretTwo));

	const readyTwo = envelope(
		'studio.ready',
		'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
		idTwo,
		{ child_nonce: childNonce, protocol_version: 1 }
	);
	harness.message({ origin: 'https://studio.sudomock.com', source: harness.frames[1], data: readyTwo });
	assert.equal(harness.frames[1].messages[0].message.payload.bootstrap_secret, secretTwo);
});

test('Woo bridge accepts exact design payload once and posts correlated result', async () => {
	const messageSessionId = '11111111-1111-4111-8111-111111111111';
	const harness = createHarness([session(messageSessionId, 'A'.repeat(43))]);
	harness.click();
	await flush();
	const frame = harness.frames[0];
	const requestId = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
	harness.message({
		origin: 'https://studio.sudomock.com',
		source: frame,
		data: envelope(
			'studio.design-submitted',
			'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
			messageSessionId,
			{
				mockup_uuid: mockupUuid,
				render_uuid: renderUuid,
				action_id: 'delete-product',
			}
		),
	});
	await flush();
	assert.equal(harness.fetchCalls.filter((call) => call.action === 'sudomock_add_to_cart').length, 0);

	const submitted = envelope('studio.design-submitted', requestId, messageSessionId, {
		mockup_uuid: mockupUuid,
		render_uuid: renderUuid,
		action_id: 'add-to-cart',
	});
	harness.message({ origin: 'https://studio.sudomock.com', source: frame, data: submitted });
	await flush();
	harness.message({ origin: 'https://studio.sudomock.com', source: frame, data: submitted });
	await flush();

	const cartCalls = harness.fetchCalls.filter((call) => call.action === 'sudomock_add_to_cart');
	assert.equal(cartCalls.length, 1);
	const sessionFields = Object.fromEntries(
		harness.fetchCalls.find((call) => call.action === 'sudomock_create_session').options.body.entries()
	);
	assert.deepEqual(sessionFields, {
		action: 'sudomock_create_session',
		nonce: 'nonce-fresh',
		product_id: '100',
		variation_id: '201',
	});
	const fields = Object.fromEntries(cartCalls[0].options.body.entries());
	assert.deepEqual(fields, {
		action: 'sudomock_add_to_cart',
		nonce: 'nonce-fresh',
		version: '1',
		request_id: requestId,
		message_session_id: messageSessionId,
		type: 'studio.design-submitted',
		mockup_uuid: mockupUuid,
		render_uuid: renderUuid,
		action_id: 'add-to-cart',
		quantity: '2',
	});
	assert.doesNotMatch(JSON.stringify(fields), /sess_|product_id|variant_id|mockup_type|preview_url|artwork_url|api_key|proof_key|bootstrap_secret/);
	const actionResults = frame.messages.filter((entry) => (
		entry.origin === 'https://studio.sudomock.com'
		&& entry.message.type === 'parent.action-result'
		&& entry.message.request_id === requestId
		&& entry.message.message_session_id === messageSessionId
		&& entry.message.payload.render_uuid === renderUuid
		&& entry.message.payload.success === true
	));
	assert.equal(actionResults.length, 2);
	assert.deepEqual(
		JSON.parse(JSON.stringify(actionResults[1])),
		JSON.parse(JSON.stringify(actionResults[0]))
	);
});

test('Woo bridge uses the render UUID as the opaque receipt handle', async () => {
	const messageSessionId = '11111111-1111-4111-8111-111111111111';
	const harness = createHarness([session(messageSessionId, 'A'.repeat(43))]);
	harness.click();
	await flush();
	const frame = harness.frames[0];
	const requestId = '34343434-3434-4434-8434-343434343434';
	harness.message({
		origin: 'https://studio.sudomock.com',
		source: frame,
		data: envelope('studio.design-submitted', requestId, messageSessionId, {
			mockup_uuid: mockupUuid,
			render_uuid: renderUuid,
			action_id: 'add-to-cart',
		}),
	});
	await flush();

	const call = harness.fetchCalls.find((entry) => entry.action === 'sudomock_add_to_cart');
	const fields = Object.fromEntries(call.options.body.entries());
	assert.deepEqual(fields, {
		action: 'sudomock_add_to_cart',
		nonce: 'nonce-fresh',
		version: '1',
		request_id: requestId,
		message_session_id: messageSessionId,
		type: 'studio.design-submitted',
		mockup_uuid: mockupUuid,
		render_uuid: renderUuid,
		action_id: 'add-to-cart',
		quantity: '2',
	});
});

test('Woo bridge reports a failed platform action with correlated IDs', async () => {
	const messageSessionId = '11111111-1111-4111-8111-111111111111';
	const harness = createHarness([session(messageSessionId, 'A'.repeat(43))], false);
	harness.click();
	await flush();
	const frame = harness.frames[0];
	const requestId = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee';
	harness.message({
		origin: 'https://studio.sudomock.com',
		source: frame,
		data: envelope('studio.design-submitted', requestId, messageSessionId, {
			mockup_uuid: mockupUuid,
			render_uuid: renderUuid,
			action_id: 'add-to-cart',
		}),
	});
	await flush();
	harness.message({
		origin: 'https://studio.sudomock.com',
		source: frame,
		data: envelope('studio.design-submitted', requestId, messageSessionId, {
			mockup_uuid: mockupUuid,
			render_uuid: renderUuid,
			action_id: 'add-to-cart',
		}),
	});
	await flush();
	const failedResults = frame.messages.filter((entry) => (
		entry.message.type === 'parent.action-result'
		&& entry.message.request_id === requestId
		&& entry.message.message_session_id === messageSessionId
		&& entry.message.payload.render_uuid === renderUuid
		&& entry.message.payload.success === false
	));
	assert.equal(failedResults.length, 2);
	assert.equal(
		harness.fetchCalls.filter((call) => call.action === 'sudomock_add_to_cart').length,
		1
	);
});

test('Woo bridge coalesces a pending retry and replays its terminal result', async () => {
	const messageSessionId = '11111111-1111-4111-8111-111111111111';
	const harness = createHarness([session(messageSessionId, 'A'.repeat(43))], true, true);
	harness.click();
	await flush();
	const frame = harness.frames[0];
	const requestId = 'abababab-abab-4bab-8bab-abababababab';
	const submitted = envelope('studio.design-submitted', requestId, messageSessionId, {
		mockup_uuid: mockupUuid,
		render_uuid: renderUuid,
		action_id: 'add-to-cart',
	});

	harness.message({ origin: 'https://studio.sudomock.com', source: frame, data: submitted });
	harness.message({ origin: 'https://studio.sudomock.com', source: frame, data: submitted });
	assert.equal(
		harness.fetchCalls.filter((call) => call.action === 'sudomock_add_to_cart').length,
		1
	);
	assert.equal(frame.messages.length, 0);

	harness.releaseCart();
	await flush();
	harness.message({ origin: 'https://studio.sudomock.com', source: frame, data: submitted });
	await flush();

	const results = frame.messages.filter((entry) => entry.message.type === 'parent.action-result');
	assert.equal(results.length, 2);
	assert.deepEqual(
		JSON.parse(JSON.stringify(results[1])),
		JSON.parse(JSON.stringify(results[0]))
	);
	assert.equal(
		harness.fetchCalls.filter((call) => call.action === 'sudomock_add_to_cart').length,
		1
	);
});

test('PHP keeps credentials server-side and binds cart action to the server session', () => {
	const createSessionSource = apiClientSource.slice(
		apiClientSource.indexOf('public static function create_session'),
		apiClientSource.indexOf('public static function consume_studio_action')
	);
	assert.match(createSessionSource, /'mockup_type'\s*=>\s*\$mockup_type/);
	assert.doesNotMatch(createSessionSource, /'mockup_type'\s*=>\s*'2d'/);
	assert.match(createSessionSource, /in_array\(\s*\$mockup_type,\s*array\(\s*'psd',\s*'2d'/);
	assert.match(createSessionSource, /'session_kind'\s*=>\s*'customize'/);
	assert.match(createSessionSource, /'shop'\s*=>\s*strtolower\(\s*\$shop\s*\)/);
	assert.match(createSessionSource, /'variant_id'\s*=>\s*\(string\)\s*\$variation_id/);
	assert.match(createSessionSource, /'allowed_origin'\s*=>\s*\$allowed_origin/);
	assert.match(createSessionSource, /'action_id'\s*=>\s*'add-to-cart'/);
	assert.match(createSessionSource, /\$mockup_type\s*===\s*\$response\['mockup_type'\]/);
	assert.doesNotMatch(createSessionSource, /displayMode/);
	assert.match(createSessionSource, /'message_session_id'\s*=>\s*\$response\['message_session_id'\]/);
	assert.match(createSessionSource, /'bootstrap_secret'\s*=>\s*\$response\['bootstrap_secret'\]/);
	assert.doesNotMatch(createSessionSource, /\$response->get_error_message\(\)/);
	assert.match(createSessionSource, /array\(\s*'SETUP_REQUIRED',\s*'MOCKUP_TERMINAL'\s*\)/);
	assert.doesNotMatch(apiClientSource, /error_log|trigger_error/);

	assert.match(storefrontPhpSource, /SudoMock_Product::get_mockup_type\(\s*\$product_id\s*\)/);
	assert.match(storefrontPhpSource, /create_session\(\s*\$mockup_uuid,\s*\$mockup_type,\s*\$product_id/);
	assert.match(storefrontPhpSource, /\$_SERVER\['HTTP_ORIGIN'\]/);
	assert.match(storefrontPhpSource, /\$_SERVER\['HTTP_REFERER'\]/);
	assert.equal(
		(storefrontPhpSource.match(/if\s*\(\s*!\s*self::request_has_storefront_origin\(\s*\)\s*\)/g) || []).length,
		2
	);
	assert.match(storefrontPhpSource, /set_transient\(/);
	assert.match(storefrontPhpSource, /get_transient\(/);
	assert.doesNotMatch(storefrontPhpSource, /session_hash|verify_session\(/);
	assert.match(storefrontPhpSource, /SudoMock_API_Client::consume_studio_action\(\s*\$consume_request\s*\)/);
	assert.match(storefrontPhpSource, /SudoMock_Product::get_mockup_uuid\(\s*\$product_id\s*\)\s*!==\s*\$bound_mockup_uuid/);
	assert.match(storefrontPhpSource, /SudoMock_Product::get_mockup_type\(\s*\$product_id\s*\)\s*!==\s*\$mockup_type/);
	assert.match(storefrontPhpSource, /hash_equals\(\s*\$binding\['shop'\],\s*\$shop\s*\)/);
	assert.match(storefrontPhpSource, /\$variation->get_parent_id\(\)\s*!==\s*\$product_id/);
	assert.match(storefrontPhpSource, /\$receipt_context\['variant_id'\]/);
	assert.match(storefrontPhpSource, /'message_session_id'\s*=>\s*\$result\['message_session_id'\]/);
	assert.match(storefrontPhpSource, /'bootstrap_secret'\s*=>\s*\$result\['bootstrap_secret'\]/);
	assert.match(storefrontPhpSource, /'render_uuid'\s*=>\s*\$render_uuid/);
	assert.doesNotMatch(storefrontPhpSource, /'message'\s*=>\s*\$result\['error'\]/);
	assert.match(storefrontPhpSource, /array\(\s*'SETUP_REQUIRED',\s*'MOCKUP_TERMINAL'\s*\)/);
	assert.doesNotMatch(jsSource, /body\.append\(\s*'session'/);
	assert.doesNotMatch(storefrontPhpSource, /data-mockup-uuid/);
	assert.doesNotMatch(jsSource, /expectedMockupUuid|data-mockup-uuid/);
	assert.doesNotMatch(jsSource, /sudomock:add-to-cart|studio\.add-to-cart/);
	assert.doesNotMatch(jsSource, /window\.open|openStudioPopup|displayMode ===/);
	assert.doesNotMatch(adminPhpSource, /data-config-key="displayMode"|sudomock_display_mode/);
});

test('Woo mapping persists type, defaults legacy mappings to PSD, and cleans it everywhere', () => {
	assert.match(productPhpSource, /const META_MOCKUP_TYPE\s*=\s*'_sudomock_mockup_type'/);
	assert.match(productPhpSource, /if\s*\(\s*''\s*===\s*\$type\s*\)\s*\{\s*return 'psd'/);
	assert.match(productPhpSource, /update_post_meta\(\s*\$product_id,\s*self::META_MOCKUP_TYPE,\s*\$type\s*\)/);
	assert.match(adminPhpSource, /list_picker_mockups\(/);
	assert.match(adminPhpSource, /update_post_meta\(\s*\$product_id,\s*'_sudomock_mockup_type'/);
	assert.match(adminPhpSource, /delete_post_meta\(\s*\$product_id,\s*'_sudomock_mockup_type'/);
	assert.match(adminPhpSource, /meta_key'\s*=>\s*'_sudomock_mockup_type'/);
	assert.match(uninstallSource, /delete_post_meta_by_key\(\s*'_sudomock_mockup_type'\s*\)/);
	assert.match(apiClientSource, /\/api\/v1\/mockups/);
	assert.match(apiClientSource, /\/api\/v1\/sudoai\/2d-mockups/);
});
