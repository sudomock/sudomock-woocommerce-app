/**
 * SudoMock Customizer — live preview updates (postMessage transport).
 *
 * @package SudoMock_Product_Customizer
 * @since   1.0.0
 */
(function ($) {
	'use strict';

	var P = 'sudomock_btn_';

	function bind(key, cb) {
		wp.customize(P + key, function (value) { value.bind(cb); });
	}

	function getBtn() { return document.querySelector('.sudomock-customize-btn'); }
	function getRoot() { return document.querySelector('.sudomock-customizer-root'); }

	// ── Button label ──
	bind('label', function (val) {
		var btn = getBtn();
		if (!btn) return;
		// Preserve icon SVGs, just replace text node
		var nodes = btn.childNodes;
		for (var i = 0; i < nodes.length; i++) {
			if (nodes[i].nodeType === 3 && nodes[i].textContent.trim()) {
				nodes[i].textContent = ' ' + val + ' ';
				return;
			}
		}
		btn.textContent = val;
	});

	// ── Colors ──
	bind('bg_color', function (val) { var b = getBtn(); if (b) b.style.background = val; });
	bind('text_color', function (val) { var b = getBtn(); if (b) b.style.color = val; });
	bind('border_color', function (val) { var b = getBtn(); if (b) b.style.borderColor = val; });

	// ── Sizing ──
	bind('font_size', function (val) { var b = getBtn(); if (b) b.style.fontSize = val + 'px'; });
	bind('padding_y', function (val) { var b = getBtn(); if (b) { b.style.paddingTop = val + 'px'; b.style.paddingBottom = val + 'px'; } });
	bind('padding_x', function (val) { var b = getBtn(); if (b) { b.style.paddingLeft = val + 'px'; b.style.paddingRight = val + 'px'; } });
	bind('border_radius', function (val) { var b = getBtn(); if (b) b.style.borderRadius = val + 'px'; });
	bind('border_width', function (val) { var b = getBtn(); if (b) b.style.borderWidth = val + 'px'; });

	// ── Font ──
	bind('font_weight', function (val) { var b = getBtn(); if (b) b.style.fontWeight = val; });
	bind('text_transform', function (val) { var b = getBtn(); if (b) b.style.textTransform = val; });

	// ── Full width ──
	bind('full_width', function (val) { var b = getBtn(); if (b) b.style.width = val ? '100%' : 'auto'; });

	// ── Shadow ──
	bind('shadow', function (val) { var b = getBtn(); if (b) b.style.boxShadow = val ? '0 2px 8px rgba(0,0,0,0.12)' : 'none'; });

	// ── Margin ──
	bind('margin_top', function (val) { var r = getRoot(); if (r) r.style.marginTop = val + 'px'; });
	bind('margin_bottom', function (val) { var r = getRoot(); if (r) r.style.marginBottom = val + 'px'; });

	// ── Heading ──
	bind('heading', function (val) {
		var r = getRoot(); if (!r) return;
		var h = r.querySelector('.sudomock-heading');
		if (val) {
			if (!h) { h = document.createElement('p'); h.className = 'sudomock-heading'; r.insertBefore(h, r.firstChild); }
			h.textContent = val;
		} else if (h) { h.remove(); }
	});
	bind('heading_color', function (val) { var h = document.querySelector('.sudomock-heading'); if (h) h.style.color = val; });

	// ── Subtext ──
	bind('subtext', function (val) {
		var r = getRoot(); if (!r) return;
		var s = r.querySelector('.sudomock-subtext');
		if (val) {
			if (!s) { s = document.createElement('p'); s.className = 'sudomock-subtext'; var btn = getBtn(); if (btn) r.insertBefore(s, btn); }
			s.textContent = val;
		} else if (s) { s.remove(); }
	});
	bind('subtext_color', function (val) { var s = document.querySelector('.sudomock-subtext'); if (s) s.style.color = val; });

	// ── Bottom text ──
	bind('bottom_text', function (val) {
		var r = getRoot(); if (!r) return;
		var b = r.querySelector('.sudomock-bottom-text');
		if (val) {
			if (!b) { b = document.createElement('p'); b.className = 'sudomock-bottom-text'; r.appendChild(b); }
			b.textContent = val;
		} else if (b) { b.remove(); }
	});

	// ── Alignment ──
	bind('alignment', function (val) { var r = getRoot(); if (r) r.style.textAlign = val; });

	// ── Dividers ──
	bind('divider_top', function (val) {
		var r = getRoot(); if (!r) return;
		var d = r.querySelector('.sudomock-divider-top');
		if (val && !d) { d = document.createElement('hr'); d.className = 'sudomock-divider-top'; r.insertBefore(d, r.firstChild); }
		else if (!val && d) { d.remove(); }
	});
	bind('divider_bottom', function (val) {
		var r = getRoot(); if (!r) return;
		var d = r.querySelector('.sudomock-divider-bottom');
		if (val && !d) { d = document.createElement('hr'); d.className = 'sudomock-divider-bottom'; r.appendChild(d); }
		else if (!val && d) { d.remove(); }
	});
	bind('divider_color', function (val) {
		document.querySelectorAll('.sudomock-divider-top, .sudomock-divider-bottom').forEach(function (d) { d.style.borderTopColor = val; });
	});

})(jQuery);
