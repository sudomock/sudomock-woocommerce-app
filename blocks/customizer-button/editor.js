/**
 * SudoMock Customizer Button — Gutenberg Block (editor side).
 *
 * Shows a live preview of the button in the Site Editor / Block Editor.
 * Settings panel mirrors Shopify's theme extension schema.
 */
(function (wp) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var SelectControl = wp.components.SelectControl;
	var RangeControl = wp.components.RangeControl;
	var ColorPicker = wp.components.ColorPicker;
	var BaseControl = wp.components.BaseControl;

	var ICONS = {
		pencil: el('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round' },
			el('path', { d: 'M17 3a2.83 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z' })),
		palette: el('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round' },
			el('circle', { cx: 13.5, cy: 6.5, r: 0.5, fill: 'currentColor' }),
			el('circle', { cx: 17.5, cy: 10.5, r: 0.5, fill: 'currentColor' }),
			el('circle', { cx: 8.5, cy: 7.5, r: 0.5, fill: 'currentColor' }),
			el('circle', { cx: 6.5, cy: 12.5, r: 0.5, fill: 'currentColor' }),
			el('path', { d: 'M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.93 0 1.5-.67 1.5-1.5 0-.39-.14-.74-.39-1.04-.24-.3-.39-.65-.39-1.04 0-.83.67-1.5 1.5-1.5H16c3.31 0 6-2.69 6-6 0-5.52-4.48-9.96-10-9.96z' })),
		wand: el('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round' },
			el('path', { d: 'm15 4-1 1 4 4 1-1a2.83 2.83 0 1 0-4-4z' }),
			el('path', { d: 'm13 6-8.5 8.5a2.12 2.12 0 1 0 3 3L16 9' })),
		brush: el('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round' },
			el('path', { d: 'm9.06 11.9 8.07-8.06a2.85 2.85 0 1 1 4.03 4.03l-8.06 8.08' }),
			el('path', { d: 'M7.07 14.94c-1.66 0-3 1.35-3 3.02 0 1.33-2.5 1.52-2 2.02 1.08 1.1 2.49 2.02 4 2.02 2.2 0 4-1.8 4-4.04a3.01 3.01 0 0 0-3-3.02z' })),
		sparkle: el('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round' },
			el('path', { d: 'm12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z' })),
	};

	// SudoMock branded icon for block inserter
	var sudomockIcon = el('svg', { width: 16, height: 16, viewBox: '0 0 1080 1080', fill: '#0f172a' },
		el('path', { d: 'M888 379c-30-8-75-50-110-76L639 193c-34-27-75-54-89-79-24-40-2-99 43-111 56-13 77 23 134 63l192 151c29 23 62 47 62 87 3 49-48 88-91 76zm-487 698c-30-8-74-50-110-76L152 891c-34-27-74-54-89-79-24-40-2-99 43-111 56-13 78 23 134 63l193 151c28 23 61 47 61 87 3 49-48 88-91 76zm84-964c-14 25-53 51-87 79L246 312c-35 26-71 60-99 67-60 20-117-52-84-109 15-26 43-44 84-77L352 31c25-19 53-38 85-29 47 10 72 70 49 109z' }),
		el('path', { d: 'M401 620c-30-8-74-50-110-77L152 435c-34-27-74-54-89-79-24-40-2-99 43-111 56-13 78 22 134 63l193 151c28 23 61 47 61 87 3 49-48 88-91 76zm149 347c13-25 53-51 86-79l153-120c35-26 71-60 98-67 61-20 117 52 85 109-15 26-44 44-84 77l-205 162c-25 19-54 38-86 29-47-10-71-70-48-109z' }),
		el('path', { d: 'M633 460c30 8 75 50 110 76l140 110c34 27 74 54 88 79 25 40 2 99-43 111-56 13-77-22-133-63L602 622c-28-23-62-47-62-87-3-49 48-88 91-76z' })
	);

	registerBlockType({ name: 'sudomock/customizer-button' }, {
		icon: sudomockIcon,
		edit: function (props) {
			var a = props.attributes;
			var set = props.setAttributes;
			var blockProps = useBlockProps();

			var btnStyle = {
				display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: '8px',
				width: a.fullWidth ? '100%' : 'auto',
				minHeight: '48px', boxSizing: 'border-box',
				padding: a.paddingY + 'px ' + a.paddingX + 'px',
				fontSize: a.fontSize + 'px', fontWeight: a.fontWeight,
				fontFamily: 'inherit', lineHeight: '1.4',
				border: a.borderWidth + 'px solid ' + a.borderColor,
				borderRadius: a.borderRadius + 'px',
				background: a.bgColor, color: a.textColor,
				cursor: 'pointer',
				textTransform: a.textTransform,
				boxShadow: a.shadow ? '0 2px 8px rgba(0,0,0,0.12)' : 'none',
			};

			var icon = a.showIcon && ICONS[a.iconStyle] ? ICONS[a.iconStyle] : null;

			return el('div', blockProps,
				// Inspector (sidebar) controls
				el(InspectorControls, null,
					// Button section
					el(PanelBody, { title: __('Button', 'sudomock-product-customizer'), initialOpen: true },
						el(TextControl, { label: __('Label', 'sudomock-product-customizer'), value: a.label, onChange: function (v) { set({ label: v }); } }),
						el(ToggleControl, { label: __('Full width', 'sudomock-product-customizer'), checked: a.fullWidth, onChange: function (v) { set({ fullWidth: v }); } }),
						el(ToggleControl, { label: __('Show icon', 'sudomock-product-customizer'), checked: a.showIcon, onChange: function (v) { set({ showIcon: v }); } }),
						a.showIcon && el(SelectControl, { label: __('Icon', 'sudomock-product-customizer'), value: a.iconStyle, options: [
							{ value: 'pencil', label: 'Pencil' }, { value: 'palette', label: 'Palette' },
							{ value: 'wand', label: 'Wand' }, { value: 'brush', label: 'Brush' }, { value: 'sparkle', label: 'Sparkle' }
						], onChange: function (v) { set({ iconStyle: v }); } }),
						a.showIcon && el(SelectControl, { label: __('Icon position', 'sudomock-product-customizer'), value: a.iconPosition, options: [
							{ value: 'left', label: 'Left' }, { value: 'right', label: 'Right' }
						], onChange: function (v) { set({ iconPosition: v }); } }),
						el(SelectControl, { label: __('Weight', 'sudomock-product-customizer'), value: a.fontWeight, options: [
							{ value: '500', label: 'Medium' }, { value: '600', label: 'Semibold' }, { value: '700', label: 'Bold' }
						], onChange: function (v) { set({ fontWeight: v }); } }),
						el(SelectControl, { label: __('Case', 'sudomock-product-customizer'), value: a.textTransform, options: [
							{ value: 'none', label: 'Normal' }, { value: 'uppercase', label: 'UPPERCASE' }
						], onChange: function (v) { set({ textTransform: v }); } }),
						el(ToggleControl, { label: __('Drop shadow', 'sudomock-product-customizer'), checked: a.shadow, onChange: function (v) { set({ shadow: v }); } })
					),
					// Colors section
					el(PanelBody, { title: __('Colors', 'sudomock-product-customizer'), initialOpen: false },
						el(BaseControl, { label: __('Background', 'sudomock-product-customizer') },
							el(ColorPicker, { color: a.bgColor, onChangeComplete: function (c) { set({ bgColor: c.hex }); }, disableAlpha: true })
						),
						el(BaseControl, { label: __('Text', 'sudomock-product-customizer') },
							el(ColorPicker, { color: a.textColor, onChangeComplete: function (c) { set({ textColor: c.hex }); }, disableAlpha: true })
						),
						el(BaseControl, { label: __('Border', 'sudomock-product-customizer') },
							el(ColorPicker, { color: a.borderColor, onChangeComplete: function (c) { set({ borderColor: c.hex }); }, disableAlpha: true })
						)
					),
					// Sizing section
					el(PanelBody, { title: __('Sizing', 'sudomock-product-customizer'), initialOpen: false },
						el(RangeControl, { label: __('Font size', 'sudomock-product-customizer'), value: a.fontSize, min: 12, max: 22, onChange: function (v) { set({ fontSize: v }); } }),
						el(RangeControl, { label: __('Vertical padding', 'sudomock-product-customizer'), value: a.paddingY, min: 6, max: 24, onChange: function (v) { set({ paddingY: v }); } }),
						el(RangeControl, { label: __('Horizontal padding', 'sudomock-product-customizer'), value: a.paddingX, min: 12, max: 48, onChange: function (v) { set({ paddingX: v }); } }),
						el(RangeControl, { label: __('Corner radius', 'sudomock-product-customizer'), value: a.borderRadius, min: 0, max: 30, onChange: function (v) { set({ borderRadius: v }); } }),
						el(RangeControl, { label: __('Border width', 'sudomock-product-customizer'), value: a.borderWidth, min: 0, max: 4, onChange: function (v) { set({ borderWidth: v }); } })
					),
					// Extra text section
					el(PanelBody, { title: __('Extra Text', 'sudomock-product-customizer'), initialOpen: false },
						el(TextControl, { label: __('Heading above', 'sudomock-product-customizer'), value: a.heading, onChange: function (v) { set({ heading: v }); } }),
						el(TextControl, { label: __('Description above', 'sudomock-product-customizer'), value: a.subtext, onChange: function (v) { set({ subtext: v }); } }),
						el(TextControl, { label: __('Text below', 'sudomock-product-customizer'), value: a.bottomText, onChange: function (v) { set({ bottomText: v }); } })
					)
				),

				// Live preview
				a.heading && el('p', { style: { fontSize: '14px', fontWeight: 600, color: '#0f172a', margin: '0 0 8px', textAlign: 'center' } }, a.heading),
				a.subtext && el('p', { style: { fontSize: '13px', color: '#6b7280', margin: '0 0 10px', textAlign: 'center', lineHeight: 1.5 } }, a.subtext),
				el('button', { type: 'button', style: btnStyle, onClick: function (e) { e.preventDefault(); } },
					icon && a.iconPosition === 'left' ? icon : null,
					' ' + a.label + ' ',
					icon && a.iconPosition === 'right' ? icon : null
				),
				a.bottomText && el('p', { style: { fontSize: '11px', color: '#6b7280', margin: '6px 0 0', textAlign: 'center' } }, a.bottomText),

				// Editor-only hint
				el('div', { style: { marginTop: '10px', padding: '10px 14px', background: '#f0f4ff', border: '1px dashed #93a8e0', borderRadius: '8px', fontSize: '12px', color: '#3b5998', lineHeight: 1.5 } },
					el('strong', null, 'SudoMock'), ' — ',
					__('This button appears on products with a mapped PSD mockup. Hidden on other products.', 'sudomock-product-customizer')
				)
			);
		},
	});
})(window.wp);
