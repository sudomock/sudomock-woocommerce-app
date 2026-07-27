/**
 * SudoMock Product — thumbnail grid mockup picker for product edit page.
 *
 * @package SudoMock_Product_Customizer
 * @since   1.0.0
 */
(function () {
	'use strict';

	if (typeof sudomockProduct === 'undefined') return;

	var ajaxUrl = sudomockProduct.ajaxUrl;
	var nonce   = sudomockProduct.nonce;
	var i18n    = sudomockProduct.i18n || {};
	var selectedUuid = null;

	function init() {
		var grid = document.getElementById('sudomock-product-grid');
		if (!grid) return;

		// Populate current mockup thumbnail and name from server-side data
		if (sudomockProduct.currentMockup) {
			var cm = sudomockProduct.currentMockup;
			var selThumb = document.getElementById('sudomock-selected-thumb');
			var selName  = document.getElementById('sudomock-selected-name');
			var selUuid  = document.getElementById('sudomock-selected-uuid');
			var selType  = document.getElementById('sudomock-selected-type');
			var nameInput = document.getElementById('sudomock_mockup_name');

			if (selThumb && cm.thumbnail) {
				var img = document.createElement('img');
				img.src = cm.thumbnail;
				img.style.cssText = 'width:100%;height:100%;object-fit:contain;';
				selThumb.textContent = '';
				selThumb.appendChild(img);
			}
			if (selName && cm.name) {
				selName.textContent = cm.name;
			}
			if (selUuid && cm.uuid) {
				selUuid.textContent = cm.uuid.substring(0, 8) + '...';
			}
			if (selType && cm.type) {
				selType.textContent = cm.type.toUpperCase();
			}
			if (nameInput && cm.name) {
				nameInput.value = cm.name;
			}
		}

		// Load initial mockups
		loadMockups('');

		// Search with debounce
		var searchInput = document.getElementById('sudomock-product-search');
		var timer;
		if (searchInput) {
			searchInput.addEventListener('input', function () {
				clearTimeout(timer);
				timer = setTimeout(function () { loadMockups(searchInput.value); }, 300);
			});
		}

		// Change button
		var changeBtn = document.getElementById('sudomock-change-mockup');
		if (changeBtn) {
			changeBtn.addEventListener('click', function () {
				document.getElementById('sudomock-selected-mockup').style.display = 'none';
				document.getElementById('sudomock-mockup-picker').style.display = 'block';
				loadMockups('');
			});
		}

		// Remove button
		var removeBtn = document.getElementById('sudomock-remove-mockup');
		if (removeBtn) {
			removeBtn.addEventListener('click', function () {
				document.getElementById('sudomock_mockup_uuid').value = '';
				document.getElementById('sudomock_mockup_type').value = '';
				document.getElementById('sudomock_mockup_name').value = '';
				document.getElementById('sudomock-selected-mockup').style.display = 'none';
				document.getElementById('sudomock-mockup-picker').style.display = 'block';
				loadMockups('');
			});
		}
	}

	function loadMockups(search) {
		var grid = document.getElementById('sudomock-product-grid');
		if (!grid) return;

		grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:30px;color:#9ca3af;font-size:13px;">' + esc(i18n.loadingMockups || 'Loading mockups...') + '</div>';

		var params = 'action=sudomock_list_mockups&nonce=' + encodeURIComponent(nonce) + '&limit=30';
		if (search) params += '&search=' + encodeURIComponent(search);

		fetch(ajaxUrl + '?' + params)
			.then(function (r) { return r.json(); })
			.then(function (json) {
				if (!json.success) {
					grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:30px;color:#dc2626;font-size:13px;">' + esc(i18n.failedToLoad || 'Failed to load mockups.') + '</div>';
					return;
				}

				var mockups = json.data.mockups || [];
				if (mockups.length === 0) {
					grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:30px;color:#9ca3af;font-size:13px;">' +
						(search ? esc(i18n.noMockupsMatch || 'No mockups match') + ' "' + esc(search) + '"' : esc(i18n.noMockupsYet || 'No mockups yet.') + ' <a href="https://sudomock.com/dashboard/playground" target="_blank">' + esc(i18n.uploadFirstPsd || 'Upload your first PSD') + '</a>') + '</div>';
					return;
				}

				renderGrid(grid, mockups);
			})
			.catch(function () {
				grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:30px;color:#dc2626;font-size:13px;">' + esc(i18n.networkError || 'Network error.') + '</div>';
			});
	}

	function renderGrid(grid, mockups) {
		grid.innerHTML = '';

		mockups.forEach(function (m) {
			var thumbUrl = '';
			if (m.thumbnails && m.thumbnails.length) {
				var t = m.thumbnails.find(function (t) { return t.label === '480' || t.width === 480; });
				thumbUrl = t ? t.url : m.thumbnails[0].url;
			} else if (m.thumbnail) {
				thumbUrl = m.thumbnail;
			}

			var soCount = m.smart_objects ? m.smart_objects.length : 0;
			var mockupType = m.mockup_type === '2d' ? '2d' : 'psd';
			var layerCount = typeof m.layer_count === 'number' ? m.layer_count : soCount;
			var layerLabel = mockupType === '2d' ? 'print area' : (i18n.smartObject || 'smart object');

			var card = document.createElement('div');
			card.style.cssText = 'cursor:pointer;border:2px solid transparent;border-radius:8px;overflow:hidden;background:#f9fafb;transition:all 0.15s;';
			card.setAttribute('data-uuid', m.uuid);
			card.setAttribute('data-name', m.name || m.uuid);
			card.setAttribute('data-type', mockupType);

			card.innerHTML =
				'<div style="width:100%;aspect-ratio:4/3;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#e5e7eb;">' +
					(thumbUrl ? '<img src="' + esc(thumbUrl) + '" style="max-width:100%;max-height:100%;object-fit:contain;" />' : '<span style="color:#9ca3af;font-size:11px;">' + esc(i18n.noPreview || 'No preview') + '</span>') +
				'</div>' +
				'<div style="padding:6px 8px;">' +
					'<div style="font-size:11px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="' + esc(m.name) + '">' + esc(m.name) + '</div>' +
					'<div style="display:flex;gap:4px;align-items:center;font-size:10px;color:#9ca3af;"><span style="padding:1px 5px;border-radius:4px;background:#e0e7ff;color:#3730a3;text-transform:uppercase;">' + mockupType + '</span><span>' + layerCount + ' ' + layerLabel + (layerCount !== 1 ? 's' : '') + '</span></div>' +
				'</div>';

			card.addEventListener('mouseenter', function () {
				if (card.getAttribute('data-uuid') !== selectedUuid) {
					card.style.borderColor = '#d1d5db';
				}
			});
			card.addEventListener('mouseleave', function () {
				if (card.getAttribute('data-uuid') !== selectedUuid) {
					card.style.borderColor = 'transparent';
				}
			});

			card.addEventListener('click', function () {
				var uuid = card.getAttribute('data-uuid');
				var name = card.getAttribute('data-name');
				var type = card.getAttribute('data-type');

				// Set hidden inputs
				document.getElementById('sudomock_mockup_uuid').value = uuid;
				document.getElementById('sudomock_mockup_type').value = type;
				document.getElementById('sudomock_mockup_name').value = name;

				// Update selected display
				var selName = document.getElementById('sudomock-selected-name');
				var selUuid = document.getElementById('sudomock-selected-uuid');
				var selThumb = document.getElementById('sudomock-selected-thumb');
				var selType = document.getElementById('sudomock-selected-type');
				if (selName) selName.textContent = name;
				if (selUuid) selUuid.textContent = uuid.substring(0, 8) + '...';
				if (selType) selType.textContent = type.toUpperCase();

				// Update thumbnail in selected display
				var thumbSrc = card.querySelector('img');
				if (selThumb && thumbSrc) {
					selThumb.textContent = '';
					var thumbImg = document.createElement('img');
					thumbImg.src = thumbSrc.src;
					thumbImg.style.cssText = 'width:100%;height:100%;object-fit:contain;';
					selThumb.appendChild(thumbImg);
				}

				// Show selected, hide picker
				document.getElementById('sudomock-selected-mockup').style.display = 'block';
				document.getElementById('sudomock-mockup-picker').style.display = 'none';

				selectedUuid = uuid;
			});

			grid.appendChild(card);
		});
	}

	// Escapes for BOTH text and attribute context (quotes included), so a
	// mockup name/URL cannot break out of an attribute and execute (stored XSS).
	function esc(str) {
		return String( str == null ? '' : str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' );
	}

	// Boot
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
