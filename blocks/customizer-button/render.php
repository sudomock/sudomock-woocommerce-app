<?php
/**
 * Server-side render for sudomock/customizer-button block.
 *
 * @package SudoMock_Product_Customizer
 * @since   1.0.0
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content (empty for dynamic blocks).
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Wrap all variables in a function to avoid global variable prefix issues.
$sudomock_render_block = static function ( $attributes, $block ) {
    // Get product from block context or global.
    $sudomock_product = null;
    if ( ! empty( $block->context['postId'] ) ) {
        $sudomock_product = wc_get_product( $block->context['postId'] );
    }
    if ( ! $sudomock_product ) {
        global $product;
        $sudomock_product = $product;
        if ( ! $sudomock_product ) {
            $sudomock_product = wc_get_product( get_the_ID() );
        }
    }

    // Only render on customizable products.
    if ( ! $sudomock_product || ! SudoMock_Product::is_customizable( $sudomock_product->get_id() ) ) {
        return '';
    }

    $sudomock_mockup_uuid = SudoMock_Product::get_mockup_uuid( $sudomock_product->get_id() );
    $sudomock_a = $attributes;

    // Allowed SVG tags and attributes for wp_kses.
    $sudomock_svg_allowed = array(
        'svg'      => array( 'aria-hidden' => true, 'width' => true, 'height' => true, 'viewBox' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true ),
        'path'     => array( 'd' => true, 'fill' => true, 'stroke' => true ),
        'circle'   => array( 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true ),
    );

    // Icons.
    $sudomock_icons = array(
        'pencil'  => '<svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.83 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>',
        'palette' => '<svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.93 0 1.5-.67 1.5-1.5 0-.39-.14-.74-.39-1.04-.24-.3-.39-.65-.39-1.04 0-.83.67-1.5 1.5-1.5H16c3.31 0 6-2.69 6-6 0-5.52-4.48-9.96-10-9.96z"/></svg>',
        'wand'    => '<svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 4-1 1 4 4 1-1a2.83 2.83 0 1 0-4-4z"/><path d="m13 6-8.5 8.5a2.12 2.12 0 1 0 3 3L16 9"/></svg>',
        'brush'   => '<svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9.06 11.9 8.07-8.06a2.85 2.85 0 1 1 4.03 4.03l-8.06 8.08"/><path d="M7.07 14.94c-1.66 0-3 1.35-3 3.02 0 1.33-2.5 1.52-2 2.02 1.08 1.1 2.49 2.02 4 2.02 2.2 0 4-1.8 4-4.04a3.01 3.01 0 0 0-3-3.02z"/></svg>',
        'sparkle' => '<svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/></svg>',
    );

    $sudomock_icon_html = '';
    if ( ! empty( $sudomock_a['showIcon'] ) && isset( $sudomock_icons[ $sudomock_a['iconStyle'] ] ) ) {
        $sudomock_icon_html = $sudomock_icons[ $sudomock_a['iconStyle'] ];
    }

    $sudomock_btn_style = sprintf(
        'display:inline-flex;align-items:center;justify-content:center;gap:8px;'
        . 'width:%s;min-height:48px;box-sizing:border-box;'
        . 'padding:%dpx %dpx;font-size:%dpx;font-weight:%s;'
        . 'font-family:inherit;line-height:1.4;'
        . 'border:%dpx solid %s;border-radius:%dpx;'
        . 'background:%s;color:%s;'
        . 'cursor:pointer;transition:all 0.15s ease;'
        . 'text-transform:%s;-webkit-font-smoothing:antialiased;'
        . '%s',
        ! empty( $sudomock_a['fullWidth'] ) ? '100%' : 'auto',
        $sudomock_a['paddingY'], $sudomock_a['paddingX'],
        $sudomock_a['fontSize'], $sudomock_a['fontWeight'],
        $sudomock_a['borderWidth'], esc_attr( $sudomock_a['borderColor'] ),
        $sudomock_a['borderRadius'],
        esc_attr( $sudomock_a['bgColor'] ), esc_attr( $sudomock_a['textColor'] ),
        $sudomock_a['textTransform'],
        ! empty( $sudomock_a['shadow'] ) ? 'box-shadow:0 2px 8px rgba(0,0,0,0.12);' : ''
    );

    $sudomock_wrapper_attributes = get_block_wrapper_attributes( array(
        'class' => 'sudomock-customizer-root',
        'style' => 'text-align:center;',
    ) );

    ob_start();

    ?>
    <div <?php
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output from get_block_wrapper_attributes()
    echo $sudomock_wrapper_attributes;
    ?>>
        <?php if ( ! empty( $sudomock_a['heading'] ) ) : ?>
            <p class="sudomock-heading" style="font-size:14px;font-weight:600;color:#0f172a;margin:0 0 8px;font-family:inherit;">
                <?php echo esc_html( $sudomock_a['heading'] ); ?>
            </p>
        <?php endif; ?>

        <?php if ( ! empty( $sudomock_a['subtext'] ) ) : ?>
            <p class="sudomock-subtext" style="font-size:13px;color:#6b7280;margin:0 0 10px;line-height:1.5;font-family:inherit;">
                <?php echo esc_html( $sudomock_a['subtext'] ); ?>
            </p>
        <?php endif; ?>

        <button type="button"
                class="sudomock-customize-btn button"
                data-product-id="<?php echo esc_attr( $sudomock_product->get_id() ); ?>"
                data-mockup-uuid="<?php echo esc_attr( $sudomock_mockup_uuid ); ?>"
                style="<?php echo esc_attr( $sudomock_btn_style ); ?>"
        >
            <?php if ( ! empty( $sudomock_a['showIcon'] ) && 'left' === $sudomock_a['iconPosition'] ) echo wp_kses( $sudomock_icon_html, $sudomock_svg_allowed ); ?>
            <?php echo esc_html( $sudomock_a['label'] ); ?>
            <?php if ( ! empty( $sudomock_a['showIcon'] ) && 'right' === $sudomock_a['iconPosition'] ) echo wp_kses( $sudomock_icon_html, $sudomock_svg_allowed ); ?>
        </button>

        <?php if ( ! empty( $sudomock_a['bottomText'] ) ) : ?>
            <p class="sudomock-bottom-text" style="font-size:11px;color:#6b7280;margin:6px 0 0;font-family:inherit;">
                <?php echo esc_html( $sudomock_a['bottomText'] ); ?>
            </p>
        <?php endif; ?>
    </div>
    <?php

    return ob_get_clean();
};

echo $sudomock_render_block( $attributes, $block ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All output escaped within the callback
