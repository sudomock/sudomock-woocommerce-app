/**
 * SudoMock Admin JS — OAuth connect, mockup modal, mockups tab, disconnect.
 *
 * @package SudoMock_Product_Customizer
 * @since   1.0.0
 */
(function () {
	'use strict';

	var ajaxUrl    = (window.sudomockAdmin || {}).ajaxUrl || '/wp-admin/admin-ajax.php';
	var nonce      = (window.sudomockAdmin || {}).nonce || '';
	var connectUrl = (window.sudomockAdmin || {}).connectUrl || '';
	var i18n       = (window.sudomockAdmin || {}).i18n || {};

	/* ────────────────────────────────────────────
	 * 1) OAuth Connect (popup flow)
	 * ──────────────────────────────────────────── */
	function initConnect() {
		var btn = document.getElementById('sudomock-connect-btn');
		if (!btn) return;

		btn.addEventListener('click', function () {
			if (!connectUrl) { showFeedback('error', 'Configuration error.'); return; }
			var w = 500, h = 700;
			var popup = window.open(connectUrl, 'sudomock_connect',
				'width=' + w + ',height=' + h + ',left=' + (screen.width - w) / 2 + ',top=' + (screen.height - h) / 2 + ',toolbar=no,menubar=no');
			if (!popup) { showFeedback('error', 'Popup blocked. Please allow popups for this site.'); return; }
			btn.disabled = true;
			btn.textContent = i18n.connecting || 'Connecting...';
		});

		window.addEventListener('message', function (e) {
			if (e.origin !== 'https://sudomock.com') return;
			if (!e.data || e.data.type !== 'sudomock:connected' || !e.data.apiKey) return;
			var body = new FormData();
			body.append('action', 'sudomock_save_api_key');
			body.append('nonce', nonce);
			body.append('api_key', e.data.apiKey);
			showFeedback('info', i18n.saving || 'Saving...');
			fetch(ajaxUrl, { method: 'POST', body: body })
				.then(function (r) { return r.json(); })
				.then(function (json) {
					if (json.success) {
						showFeedback('success', json.data.message || 'Connected!');
						setTimeout(function () { window.location.reload(); }, 800);
					} else {
						showFeedback('error', (json.data && json.data.message) || 'Connection failed.');
						resetConnectBtn();
					}
				}).catch(function () { showFeedback('error', 'Network error.'); resetConnectBtn(); });
		});
	}

	function resetConnectBtn() {
		var btn = document.getElementById('sudomock-connect-btn');
		if (btn) { btn.disabled = false; btn.textContent = i18n.connect || 'Connect Account'; }
	}

	/* ────────────────────────────────────────────
	 * 2) Disconnect
	 * ──────────────────────────────────────────── */
	function initDisconnect() {
		var btn = document.getElementById('sudomock-disconnect-btn');
		if (!btn) return;
		btn.addEventListener('click', function () {
			var msg = (i18n.confirmDisconnect || 'Are you sure you want to disconnect?') +
				'\n\n' + (i18n.confirmDisconnectDetail || 'This will remove all mockup assignments from your products and notify the SudoMock server. Product customization will stop working immediately.');
			if (!confirm(msg)) return;
			var body = new FormData();
			body.append('action', 'sudomock_disconnect');
			body.append('nonce', nonce);
			btn.disabled = true;
			btn.textContent = i18n.disconnecting || 'Disconnecting...';
			fetch(ajaxUrl, { method: 'POST', body: body })
				.then(function () { window.location.reload(); })
				.catch(function () { window.location.reload(); });
		});
	}

	/* ────────────────────────────────────────────
	 * 3) Mockup Picker Modal (Products tab)
	 * ──────────────────────────────────────────── */
	var modal = null;
	var selectedMockup = null;
	var currentProductId = null;

	function initMockupModal() {
		modal = document.getElementById('sudomock-mockup-modal');
		if (!modal) return;

		// "Map Mockup" / "Change" buttons
		document.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-action="map"]');
			if (btn) {
				currentProductId = btn.getAttribute('data-product-id');
				var name = btn.getAttribute('data-product-name');
				var info = modal.querySelector('.sudomock-modal__product-info');
				if (info) info.textContent = 'Assigning mockup to: ' + name;
				selectedMockup = null;
				updateAssignBtn();
				openModal();
				loadModalMockups('');
			}

			var unmap = e.target.closest('[data-action="unmap"]');
			if (unmap) {
				var pid = unmap.getAttribute('data-product-id');
				if (!confirm('Remove mockup mapping from this product?')) return;
				unmapProduct(pid, unmap);
			}
		});

		// Close
		var closeBtn = modal.querySelector('.sudomock-modal__close');
		var overlay = modal.querySelector('.sudomock-modal__overlay');
		var cancelBtn = document.getElementById('sudomock-modal-cancel');
		if (closeBtn) closeBtn.addEventListener('click', closeModal);
		if (overlay) overlay.addEventListener('click', closeModal);
		if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

		// Search with debounce
		var searchInput = document.getElementById('sudomock-modal-search');
		var searchTimer;
		if (searchInput) {
			searchInput.addEventListener('input', function () {
				clearTimeout(searchTimer);
				searchTimer = setTimeout(function () { loadModalMockups(searchInput.value); }, 300);
			});
		}

		// Assign
		var assignBtn = document.getElementById('sudomock-modal-assign');
		if (assignBtn) {
			assignBtn.addEventListener('click', function () {
				if (!selectedMockup || !currentProductId) return;
				mapProduct(currentProductId, selectedMockup);
			});
		}
	}

	function openModal() {
		if (modal) modal.style.display = 'flex';
	}
	function closeModal() {
		if (modal) modal.style.display = 'none';
		selectedMockup = null;
		currentProductId = null;
	}

	function loadModalMockups(search) {
		var grid = document.getElementById('sudomock-modal-grid');
		if (!grid) return;
		grid.innerHTML = '<div style="text-align:center;padding:40px;color:#616161;grid-column:1/-1;">Loading mockups...</div>';

		var params = 'action=sudomock_list_mockups&nonce=' + encodeURIComponent(nonce);
		if (search) params += '&search=' + encodeURIComponent(search);
		params += '&limit=20';

		fetch(ajaxUrl + '?' + params)
			.then(function (r) { return r.json(); })
			.then(function (json) {
				if (!json.success) {
					grid.innerHTML = '<div style="text-align:center;padding:40px;color:#616161;grid-column:1/-1;">Failed to load mockups.</div>';
					return;
				}
				var mockups = json.data.mockups || [];
				if (mockups.length === 0) {
					grid.innerHTML = '<div style="text-align:center;padding:40px;color:#616161;grid-column:1/-1;">No mockups found.' +
						(search ? '' : ' <a href="https://sudomock.com/dashboard/playground" target="_blank" rel="noopener">Upload your first PSD</a>') + '</div>';
					return;
				}
				renderMockupGrid(grid, mockups);
			})
			.catch(function () {
				grid.innerHTML = '<div style="text-align:center;padding:40px;color:#c00;grid-column:1/-1;">Network error loading mockups.</div>';
			});
	}

	function renderMockupGrid(grid, mockups) {
		grid.innerHTML = '';
		mockups.forEach(function (m) {
			var card = document.createElement('div');
			card.className = 'sudomock-mockup-card';
			card.setAttribute('data-uuid', m.uuid);

			var thumbUrl = '';
			if (m.thumbnails && m.thumbnails.length) {
				var t480 = m.thumbnails.find(function (t) { return t.label === '480' || t.width === 480; });
				thumbUrl = t480 ? t480.url : m.thumbnails[0].url;
			} else if (m.thumbnail) {
				thumbUrl = m.thumbnail;
			}

			var soCount = m.smart_objects ? m.smart_objects.length : 0;
			var dims = (m.width && m.height) ? m.width + ' × ' + m.height + 'px' : '';

			card.innerHTML =
				'<div class="sudomock-mockup-card__thumb">' +
					(thumbUrl ? '<img src="' + escapeHtml(thumbUrl) + '" alt="' + escapeHtml(m.name) + '" />' : '<span style="color:#94a3b8;font-size:12px;">No preview</span>') +
				'</div>' +
				'<div class="sudomock-mockup-card__info">' +
					'<div class="sudomock-mockup-card__name" title="' + escapeHtml(m.name) + '">' + escapeHtml(m.name) + '</div>' +
					'<div style="display:flex;gap:4px;align-items:center;flex-wrap:wrap;">' +
						'<span class="sudomock-badge sudomock-badge--info" style="font-size:10px;padding:1px 6px;">' + soCount + ' smart object' + (soCount !== 1 ? 's' : '') + '</span>' +
						(dims ? '<span style="font-size:10px;color:#94a3b8;">' + dims + '</span>' : '') +
					'</div>' +
				'</div>';

			card.addEventListener('click', function () {
				// Deselect all
				grid.querySelectorAll('.sudomock-mockup-card--selected').forEach(function (c) {
					c.classList.remove('sudomock-mockup-card--selected');
					var chk = c.querySelector('.sudomock-mockup-card__check');
					if (chk) chk.remove();
				});
				// Select this
				card.classList.add('sudomock-mockup-card--selected');
				var check = document.createElement('div');
				check.className = 'sudomock-mockup-card__check';
				check.textContent = '✓';
				card.appendChild(check);
				selectedMockup = m.uuid;
				updateAssignBtn();
			});

			grid.appendChild(card);
		});
	}

	function updateAssignBtn() {
		var btn = document.getElementById('sudomock-modal-assign');
		if (btn) btn.disabled = !selectedMockup;
	}

	function mapProduct(productId, mockupUuid) {
		var btn = document.getElementById('sudomock-modal-assign');
		if (btn) { btn.disabled = true; btn.textContent = 'Assigning...'; }

		var body = new FormData();
		body.append('action', 'sudomock_map_product');
		body.append('nonce', nonce);
		body.append('product_id', productId);
		body.append('mockup_uuid', mockupUuid);

		fetch(ajaxUrl, { method: 'POST', body: body })
			.then(function (r) { return r.json(); })
			.then(function (json) {
				if (json.success) {
					closeModal();
					window.location.reload();
				} else {
					alert((json.data && json.data.message) || 'Failed to map mockup.');
					if (btn) { btn.disabled = false; btn.textContent = 'Assign Mockup'; }
				}
			})
			.catch(function () {
				alert('Network error.');
				if (btn) { btn.disabled = false; btn.textContent = 'Assign Mockup'; }
			});
	}

	function unmapProduct(productId, btn) {
		btn.disabled = true;
		var body = new FormData();
		body.append('action', 'sudomock_unmap_product');
		body.append('nonce', nonce);
		body.append('product_id', productId);

		fetch(ajaxUrl, { method: 'POST', body: body })
			.then(function (r) { return r.json(); })
			.then(function () { window.location.reload(); })
			.catch(function () { window.location.reload(); });
	}

	/* ────────────────────────────────────────────
	 * 4) Mockups Tab (browse PSD library)
	 * ──────────────────────────────────────────── */
	var mockupsPage = 1;
	var mockupsSearch = '';
	var mockupsPerPage = 12;

	function initMockupsTab() {
		var grid = document.getElementById('sudomock-mockups-grid');
		if (!grid) return;

		loadMockupsTab();

		var searchInput = document.getElementById('sudomock-mockups-search');
		var searchTimer;
		if (searchInput) {
			searchInput.addEventListener('input', function () {
				clearTimeout(searchTimer);
				searchTimer = setTimeout(function () {
					mockupsSearch = searchInput.value;
					mockupsPage = 1;
					loadMockupsTab();
				}, 300);
			});
		}
	}

	function loadMockupsTab() {
		var grid = document.getElementById('sudomock-mockups-grid');
		var pag = document.getElementById('sudomock-mockups-pagination');
		if (!grid) return;

		grid.innerHTML = '<div style="text-align:center;padding:40px;color:#616161;grid-column:1/-1;">Loading mockups...</div>';

		var offset = (mockupsPage - 1) * mockupsPerPage;
		var params = 'action=sudomock_list_mockups&nonce=' + encodeURIComponent(nonce);
		params += '&limit=' + mockupsPerPage + '&offset=' + offset;
		if (mockupsSearch) params += '&search=' + encodeURIComponent(mockupsSearch);

		fetch(ajaxUrl + '?' + params)
			.then(function (r) { return r.json(); })
			.then(function (json) {
				if (!json.success) {
					grid.innerHTML = '<div style="text-align:center;padding:40px;color:#c00;grid-column:1/-1;">' + ((json.data && json.data.message) || 'Failed to load.') + '</div>';
					return;
				}
				var mockups = json.data.mockups || [];
				var total = json.data.total || 0;

				if (mockups.length === 0) {
					grid.textContent = '';
					var emptyDiv = document.createElement('div');
					emptyDiv.className = 'sudomock-empty-state';
					emptyDiv.style.gridColumn = '1 / -1';
					if (mockupsSearch) {
						var svgNs = 'http://www.w3.org/2000/svg';
						var searchSvg = document.createElementNS(svgNs, 'svg');
						searchSvg.setAttribute('width', '40');
						searchSvg.setAttribute('height', '40');
						searchSvg.setAttribute('viewBox', '0 0 24 24');
						searchSvg.setAttribute('fill', 'none');
						searchSvg.setAttribute('stroke', '#94a3b8');
						searchSvg.setAttribute('stroke-width', '1.5');
						var c = document.createElementNS(svgNs, 'circle');
						c.setAttribute('cx', '11'); c.setAttribute('cy', '11'); c.setAttribute('r', '8');
						searchSvg.appendChild(c);
						var l = document.createElementNS(svgNs, 'line');
						l.setAttribute('x1', '21'); l.setAttribute('y1', '21');
						l.setAttribute('x2', '16.65'); l.setAttribute('y2', '16.65');
						searchSvg.appendChild(l);
						var iconWrap = document.createElement('div');
						iconWrap.className = 'sudomock-empty-state__icon';
						iconWrap.appendChild(searchSvg);
						emptyDiv.appendChild(iconWrap);
						var msgP = document.createElement('p');
						msgP.className = 'sudomock-empty-state__desc';
						msgP.textContent = 'No mockups match "' + mockupsSearch + '"';
						emptyDiv.appendChild(msgP);
					} else {
						var svgNs2 = 'http://www.w3.org/2000/svg';
						var imgSvg = document.createElementNS(svgNs2, 'svg');
						imgSvg.setAttribute('width', '48');
						imgSvg.setAttribute('height', '48');
						imgSvg.setAttribute('viewBox', '0 0 24 24');
						imgSvg.setAttribute('fill', 'none');
						imgSvg.setAttribute('stroke', '#94a3b8');
						imgSvg.setAttribute('stroke-width', '1.5');
						var r = document.createElementNS(svgNs2, 'rect');
						r.setAttribute('x', '3'); r.setAttribute('y', '3');
						r.setAttribute('width', '18'); r.setAttribute('height', '18');
						r.setAttribute('rx', '2'); r.setAttribute('ry', '2');
						imgSvg.appendChild(r);
						var ci = document.createElementNS(svgNs2, 'circle');
						ci.setAttribute('cx', '8.5'); ci.setAttribute('cy', '8.5'); ci.setAttribute('r', '1.5');
						imgSvg.appendChild(ci);
						var pl = document.createElementNS(svgNs2, 'polyline');
						pl.setAttribute('points', '21 15 16 10 5 21');
						imgSvg.appendChild(pl);
						var iconWrap2 = document.createElement('div');
						iconWrap2.className = 'sudomock-empty-state__icon';
						iconWrap2.appendChild(imgSvg);
						emptyDiv.appendChild(iconWrap2);
						var h3 = document.createElement('h3');
						h3.className = 'sudomock-empty-state__title';
						h3.textContent = 'No PSD mockups yet';
						emptyDiv.appendChild(h3);
						var desc = document.createElement('p');
						desc.className = 'sudomock-empty-state__desc';
						desc.textContent = 'Upload PSD mockup files in your SudoMock Dashboard. Mockups with smart objects will appear here automatically.';
						emptyDiv.appendChild(desc);
						var cta = document.createElement('a');
						cta.href = 'https://sudomock.com/dashboard/playground';
						cta.target = '_blank';
						cta.rel = 'noopener';
						cta.className = 'sudomock-btn sudomock-btn--primary';
						cta.textContent = 'Upload Your First PSD';
						emptyDiv.appendChild(cta);
					}
					grid.appendChild(emptyDiv);
					if (pag) pag.textContent = '';
					return;
				}

				// Render grid - all data is sanitized via escapeHtml() before insertion
				grid.textContent = '';
				mockups.forEach(function (m) {
					var thumbUrl = '';
					if (m.thumbnails && m.thumbnails.length) {
						var t480 = m.thumbnails.find(function (t) { return t.label === '480' || t.width === 480; });
						thumbUrl = t480 ? t480.url : m.thumbnails[0].url;
					} else if (m.thumbnail) {
						thumbUrl = m.thumbnail;
					}
					var soCount = m.smart_objects ? m.smart_objects.length : 0;
					var textCount = m.text_layers ? m.text_layers.length : 0;
					var dims = (m.width && m.height) ? m.width + ' × ' + m.height + 'px' : '';

					var card = document.createElement('div');
					card.className = 'sudomock-mockup-card sudomock-mockup-card--browse';

					// Thumb
					var thumbDiv = document.createElement('div');
					thumbDiv.className = 'sudomock-mockup-card__thumb';
					if (thumbUrl) {
						var img = document.createElement('img');
						img.src = thumbUrl;
						img.alt = m.name || '';
						thumbDiv.appendChild(img);
					} else {
						var placeholder = document.createElement('div');
						placeholder.style.cssText = 'display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;';
						var svgNs = 'http://www.w3.org/2000/svg';
						var svg = document.createElementNS(svgNs, 'svg');
						svg.setAttribute('width', '32');
						svg.setAttribute('height', '32');
						svg.setAttribute('viewBox', '0 0 24 24');
						svg.setAttribute('fill', 'none');
						svg.setAttribute('stroke', '#94a3b8');
						svg.setAttribute('stroke-width', '1.5');
						var rect = document.createElementNS(svgNs, 'rect');
						rect.setAttribute('x', '3'); rect.setAttribute('y', '3');
						rect.setAttribute('width', '18'); rect.setAttribute('height', '18');
						rect.setAttribute('rx', '2'); rect.setAttribute('ry', '2');
						svg.appendChild(rect);
						var circle = document.createElementNS(svgNs, 'circle');
						circle.setAttribute('cx', '8.5'); circle.setAttribute('cy', '8.5'); circle.setAttribute('r', '1.5');
						svg.appendChild(circle);
						var polyline = document.createElementNS(svgNs, 'polyline');
						polyline.setAttribute('points', '21 15 16 10 5 21');
						svg.appendChild(polyline);
						placeholder.appendChild(svg);
						var label = document.createElement('div');
						label.style.cssText = 'font-size:11px;color:#94a3b8;margin-top:6px;';
						label.textContent = 'No preview';
						placeholder.appendChild(label);
						thumbDiv.appendChild(placeholder);
					}
					card.appendChild(thumbDiv);

					// SO badge overlay
					var soBadge = document.createElement('div');
					soBadge.className = 'sudomock-mockup-card__so-badge';
					soBadge.textContent = soCount + ' SO' + (soCount !== 1 ? 's' : '');
					card.appendChild(soBadge);

					// Info
					var infoDiv = document.createElement('div');
					infoDiv.className = 'sudomock-mockup-card__info';
					var nameDiv = document.createElement('div');
					nameDiv.className = 'sudomock-mockup-card__name';
					nameDiv.title = m.name || '';
					nameDiv.textContent = m.name || '';
					infoDiv.appendChild(nameDiv);

					var badgesDiv = document.createElement('div');
					badgesDiv.style.cssText = 'display:flex;gap:4px;flex-wrap:wrap;margin-top:2px;';
					var soBadgeInfo = document.createElement('span');
					soBadgeInfo.className = 'sudomock-badge sudomock-badge--info';
					soBadgeInfo.style.cssText = 'font-size:10px;padding:1px 6px;';
					soBadgeInfo.textContent = soCount + ' smart object' + (soCount !== 1 ? 's' : '');
					badgesDiv.appendChild(soBadgeInfo);
					if (textCount > 0) {
						var textBadge = document.createElement('span');
						textBadge.className = 'sudomock-badge';
						textBadge.style.cssText = 'font-size:10px;padding:1px 6px;background:#f1f5f9;color:#64748b;';
						textBadge.textContent = textCount + ' text';
						badgesDiv.appendChild(textBadge);
					}
					infoDiv.appendChild(badgesDiv);

					if (dims) {
						var dimsDiv = document.createElement('div');
						dimsDiv.style.cssText = 'font-size:10px;color:#94a3b8;margin-top:2px;';
						dimsDiv.textContent = dims;
						infoDiv.appendChild(dimsDiv);
					}
					card.appendChild(infoDiv);

					grid.appendChild(card);
				});

				// Pagination
				if (pag) {
					var totalPages = Math.ceil(total / mockupsPerPage);
					if (totalPages <= 1) {
						pag.innerHTML = '<span class="sudomock-text--muted sudomock-text--sm">' + total + ' mockup' + (total !== 1 ? 's' : '') + '</span>';
					} else {
						pag.innerHTML = '';
						if (mockupsPage > 1) {
							var prev = document.createElement('button');
							prev.className = 'sudomock-btn sudomock-btn--sm';
							prev.textContent = '← Previous';
							prev.addEventListener('click', function () { mockupsPage--; loadMockupsTab(); });
							pag.appendChild(prev);
						}
						var info = document.createElement('span');
						info.className = 'sudomock-text--muted sudomock-text--sm';
						info.style.margin = '0 12px';
						info.textContent = 'Page ' + mockupsPage + ' of ' + totalPages + ' (' + total + ' mockups)';
						pag.appendChild(info);
						if (mockupsPage < totalPages) {
							var next = document.createElement('button');
							next.className = 'sudomock-btn sudomock-btn--sm';
							next.textContent = 'Next →';
							next.addEventListener('click', function () { mockupsPage++; loadMockupsTab(); });
							pag.appendChild(next);
						}
					}
				}
			})
			.catch(function () {
				grid.innerHTML = '<div style="text-align:center;padding:40px;color:#c00;grid-column:1/-1;">Network error loading mockups.</div>';
			});
	}

	/* ────────────────────────────────────────────
	 * 5) Settings save feedback
	 * ──────────────────────────────────────────── */
	function initSettingsFeedback() {
		// WordPress options.php redirect has &settings-updated=true
		if (window.location.search.indexOf('settings-updated=true') > -1) {
			var wrap = document.querySelector('.sudomock-wrap');
			if (wrap) {
				var banner = document.createElement('div');
				banner.className = 'sudomock-banner sudomock-banner--success';
				banner.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Settings saved successfully.';
				wrap.insertBefore(banner, wrap.querySelector('.sudomock-tabs') ? wrap.querySelector('.sudomock-tabs').nextSibling : wrap.firstChild);
				setTimeout(function () { banner.style.opacity = '0'; banner.style.transition = 'opacity 0.3s'; setTimeout(function () { banner.remove(); }, 400); }, 3000);
			}
		}
	}

	/* ────────────────────────────────────────────
	 * 6) Studio Config Editor (Settings tab)
	 * ──────────────────────────────────────────── */
	var DEFAULTS = {
		primaryColor: '#0f172a', accentColor: '#da7756', successColor: '#16a34a',
		backgroundColor: '#f1f5f9', panelBackground: '#ffffff', textColor: '#0f172a',
		borderColor: '#e2e8f0', borderRadius: 10, logoUrl: '', theme: 'light',
		buttonText: 'Add to Cart', addingText: 'Adding...', successText: 'Added!',
		renderButtonText: 'Render Preview', uploadText: 'Drop image or click to upload',
		headerText: 'Customize Your Design',
		showAdjustments: true, showColorOverlay: true, showFitMode: true,
		showPosition: true, showSize: true, showRotation: true,
		showExportOptions: true, showZoomControls: true, showUndoRedo: true,
		displayMode: 'iframe', layout: 'full', autoRender: true,
		autoRenderDelay: 800, maxFileSize: 15
	};

	var savedConfig = null;
	var currentConfig = null;
	var configDirty = false;

	function initStudioConfig() {
		var container = document.getElementById('sudomock-studio-config');
		if (!container) return;

		container.style.display = '';
		loadStudioConfig();

		// Save button
		var saveBtn = document.getElementById('sudomock-config-save');
		if (saveBtn) saveBtn.addEventListener('click', saveStudioConfig);

		// Discard button
		var discardBtn = document.getElementById('sudomock-config-discard');
		if (discardBtn) discardBtn.addEventListener('click', discardStudioConfig);

		// Reset defaults button
		var resetBtn = document.getElementById('sudomock-config-reset');
		if (resetBtn) resetBtn.addEventListener('click', function () {
			if (!confirm('Reset all studio settings to defaults?')) return;
			currentConfig = JSON.parse(JSON.stringify(DEFAULTS));
			populateForm(currentConfig);
			markDirty();
		});
	}

	function loadStudioConfig() {
		var params = 'action=sudomock_get_studio_config&nonce=' + encodeURIComponent(nonce);
		fetch(ajaxUrl + '?' + params)
			.then(function (r) { return r.json(); })
			.then(function (json) {
				document.getElementById('sudomock-config-loading').style.display = 'none';
				document.getElementById('sudomock-config-form').style.display = '';

				if (!json.success) {
					showConfigBanner('error', (json.data && json.data.message) || 'Failed to load studio config. Using defaults.');
					savedConfig = JSON.parse(JSON.stringify(DEFAULTS));
				} else {
					var merged = {};
					for (var k in DEFAULTS) { merged[k] = DEFAULTS[k]; }
					var cfg = json.data.config || json.data;
					for (var ck in cfg) { if (DEFAULTS.hasOwnProperty(ck)) merged[ck] = cfg[ck]; }
					savedConfig = merged;
				}
				currentConfig = JSON.parse(JSON.stringify(savedConfig));
				populateForm(currentConfig);
				bindConfigInputs();
			})
			.catch(function () {
				document.getElementById('sudomock-config-loading').style.display = 'none';
				document.getElementById('sudomock-config-form').style.display = '';
				showConfigBanner('error', 'Network error loading studio config.');
				savedConfig = JSON.parse(JSON.stringify(DEFAULTS));
				currentConfig = JSON.parse(JSON.stringify(savedConfig));
				populateForm(currentConfig);
				bindConfigInputs();
			});
	}

	function populateForm(cfg) {
		// Color fields
		document.querySelectorAll('.sudomock-color-field__picker').forEach(function (el) {
			var key = el.getAttribute('data-config-key');
			if (cfg[key]) el.value = cfg[key];
		});
		document.querySelectorAll('.sudomock-color-field__hex').forEach(function (el) {
			var key = el.getAttribute('data-config-key');
			if (cfg[key]) el.value = cfg[key];
		});

		// Text inputs
		document.querySelectorAll('#sudomock-config-form input[type="text"][data-config-key]').forEach(function (el) {
			var key = el.getAttribute('data-config-key');
			el.value = cfg[key] || '';
		});

		// Selects
		document.querySelectorAll('#sudomock-config-form select[data-config-key]').forEach(function (el) {
			var key = el.getAttribute('data-config-key');
			if (cfg[key]) el.value = cfg[key];
		});

		// Checkboxes
		document.querySelectorAll('#sudomock-config-form input[type="checkbox"][data-config-key]').forEach(function (el) {
			var key = el.getAttribute('data-config-key');
			el.checked = !!cfg[key];
		});

		// Range sliders
		document.querySelectorAll('#sudomock-config-form input[type="range"][data-config-key]').forEach(function (el) {
			var key = el.getAttribute('data-config-key');
			el.value = cfg[key];
			updateRangeDisplay(key, cfg[key]);
		});

		// Auto-render delay visibility
		var delayRow = document.getElementById('sudomock-autorender-delay-row');
		if (delayRow) delayRow.style.display = cfg.autoRender ? '' : 'none';

		// Toggle count
		updateToggleCount();
	}

	function bindConfigInputs() {
		// Color pickers
		document.querySelectorAll('.sudomock-color-field__picker').forEach(function (el) {
			el.addEventListener('input', function () {
				var key = el.getAttribute('data-config-key');
				currentConfig[key] = el.value;
				// Sync hex input
				var hex = document.querySelector('.sudomock-color-field__hex[data-config-key="' + key + '"]');
				if (hex) hex.value = el.value;
				markDirty();
			});
		});

		// Hex inputs
		document.querySelectorAll('.sudomock-color-field__hex').forEach(function (el) {
			el.addEventListener('input', function () {
				var key = el.getAttribute('data-config-key');
				var val = el.value;
				if (/^#[0-9a-fA-F]{6}$/.test(val)) {
					currentConfig[key] = val;
					var picker = document.querySelector('.sudomock-color-field__picker[data-config-key="' + key + '"]');
					if (picker) picker.value = val;
					markDirty();
				}
			});
			el.addEventListener('change', function () {
				var key = el.getAttribute('data-config-key');
				var val = el.value;
				if (/^#[0-9a-fA-F]{6}$/.test(val)) {
					currentConfig[key] = val;
					markDirty();
				} else {
					el.value = currentConfig[key] || '';
				}
			});
		});

		// Text inputs
		document.querySelectorAll('#sudomock-config-form input[type="text"][data-config-key]').forEach(function (el) {
			if (el.classList.contains('sudomock-color-field__hex')) return;
			el.addEventListener('input', function () {
				var key = el.getAttribute('data-config-key');
				currentConfig[key] = el.value || null;
				markDirty();
			});
		});

		// Selects
		document.querySelectorAll('#sudomock-config-form select[data-config-key]').forEach(function (el) {
			el.addEventListener('change', function () {
				var key = el.getAttribute('data-config-key');
				currentConfig[key] = el.value;
				markDirty();
			});
		});

		// Checkboxes
		document.querySelectorAll('#sudomock-config-form input[type="checkbox"][data-config-key]').forEach(function (el) {
			el.addEventListener('change', function () {
				var key = el.getAttribute('data-config-key');
				currentConfig[key] = el.checked;
				if (key === 'autoRender') {
					var delayRow = document.getElementById('sudomock-autorender-delay-row');
					if (delayRow) delayRow.style.display = el.checked ? '' : 'none';
				}
				updateToggleCount();
				markDirty();
			});
		});

		// Range sliders
		document.querySelectorAll('#sudomock-config-form input[type="range"][data-config-key]').forEach(function (el) {
			el.addEventListener('input', function () {
				var key = el.getAttribute('data-config-key');
				currentConfig[key] = parseInt(el.value, 10);
				updateRangeDisplay(key, el.value);
				markDirty();
			});
		});
	}

	function updateRangeDisplay(key, value) {
		var span = document.querySelector('.sudomock-range-value[data-config-key="' + key + '"]');
		if (!span) return;
		if (key === 'borderRadius') span.textContent = value + 'px';
		else if (key === 'autoRenderDelay') span.textContent = value + 'ms';
		else if (key === 'maxFileSize') span.textContent = value + ' MB';
		else span.textContent = value;
	}

	function updateToggleCount() {
		var toggleKeys = ['showAdjustments', 'showColorOverlay', 'showFitMode', 'showPosition',
			'showSize', 'showRotation', 'showExportOptions', 'showZoomControls', 'showUndoRedo'];
		var active = 0;
		toggleKeys.forEach(function (k) { if (currentConfig && currentConfig[k]) active++; });
		var el = document.getElementById('sudomock-toggle-count');
		if (el) el.textContent = active + ' / ' + toggleKeys.length + ' active';
	}

	function markDirty() {
		configDirty = true;
		var discardBtn = document.getElementById('sudomock-config-discard');
		if (discardBtn) discardBtn.style.display = '';
	}

	function saveStudioConfig() {
		var saveBtn = document.getElementById('sudomock-config-save');
		if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = i18n.saving || 'Saving...'; }

		var body = new FormData();
		body.append('action', 'sudomock_save_studio_config');
		body.append('nonce', nonce);
		body.append('config', JSON.stringify(currentConfig));

		fetch(ajaxUrl, { method: 'POST', body: body })
			.then(function (r) { return r.json(); })
			.then(function (json) {
				if (json.success) {
					savedConfig = JSON.parse(JSON.stringify(currentConfig));
					configDirty = false;
					var discardBtn = document.getElementById('sudomock-config-discard');
					if (discardBtn) discardBtn.style.display = 'none';
					showConfigBanner('success', json.data.message || 'Studio config saved.');
				} else {
					showConfigBanner('error', (json.data && json.data.message) || 'Failed to save.');
				}
				if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save Studio Config'; }
			})
			.catch(function () {
				showConfigBanner('error', 'Network error saving config.');
				if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save Studio Config'; }
			});
	}

	function discardStudioConfig() {
		if (!savedConfig) return;
		currentConfig = JSON.parse(JSON.stringify(savedConfig));
		populateForm(currentConfig);
		configDirty = false;
		var discardBtn = document.getElementById('sudomock-config-discard');
		if (discardBtn) discardBtn.style.display = 'none';
		showConfigBanner('info', 'Changes discarded.');
	}

	function showConfigBanner(type, msg) {
		var container = document.getElementById('sudomock-config-banner');
		if (!container) return;
		var cls = 'sudomock-banner';
		if (type === 'success') cls += ' sudomock-banner--success';
		else if (type === 'error') cls += ' sudomock-banner--error';
		else cls += ' sudomock-banner--info';
		container.innerHTML = '<div class="' + cls + '">' + escapeHtml(msg) + '</div>';
		setTimeout(function () { container.innerHTML = ''; }, 5000);
	}

	/* ────────────────────────────────────────────
	 * Helpers
	 * ──────────────────────────────────────────── */
	function showFeedback(type, msg) {
		var el = document.getElementById('sudomock-connect-feedback');
		if (!el) return;
		el.style.display = 'block';
		el.className = 'sudomock-feedback sudomock-feedback--' + type;
		el.textContent = msg;
	}

	function escapeHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str || ''));
		return div.innerHTML;
	}

	/* ────────────────────────────────────────────
	 * Boot
	 * ──────────────────────────────────────────── */
	function boot() {
		initConnect();
		initDisconnect();
		initMockupModal();
		initMockupsTab();
		initSettingsFeedback();
		initStudioConfig();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
