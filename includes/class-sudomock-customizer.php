<?php
/**
 * WordPress Customizer integration for SudoMock button styling.
 *
 * Equivalent to Shopify Theme Editor's block settings schema.
 * Merchants access via Appearance → Customize → SudoMock section.
 *
 * @package SudoMock_Product_Customizer
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SudoMock_Customizer {

    /** @var self|null */
    private static $instance = null;

    /** Option prefix */
    const PREFIX = 'sudomock_btn_';

    /** Defaults — mirrors Shopify's customizer-button.liquid schema */
    private static $defaults = array(
        'label'            => 'Customize This Product',
        'full_width'       => true,
        'show_icon'        => true,
        'icon_style'       => 'pencil',
        'icon_position'    => 'left',
        'font_weight'      => '600',
        'text_transform'   => 'none',
        'shadow'           => false,
        'bg_color'         => '#ffffff',
        'text_color'       => '#121212',
        'border_color'     => '#121212',
        'hover_bg_color'   => '#121212',
        'hover_text_color' => '#ffffff',
        'font_size'        => 15,
        'padding_y'        => 14,
        'padding_x'        => 24,
        'border_radius'    => 6,
        'border_width'     => 2,
        'heading'          => '',
        'heading_color'    => '#0f172a',
        'subtext'          => '',
        'subtext_color'    => '#6b7280',
        'bottom_text'      => '',
        'alignment'        => 'center',
        'divider_top'      => false,
        'divider_bottom'   => false,
        'divider_color'    => '#e5e7eb',
        'margin_top'       => 8,
        'margin_bottom'    => 8,
        'position'         => 'after_add_to_cart',
    );

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'customize_register', array( $this, 'register' ) );
        add_action( 'customize_preview_init', array( $this, 'preview_js' ) );
    }

    /**
     * Get a button option with default fallback.
     *
     * @param string $key Option key without prefix.
     * @return mixed
     */
    public static function get( $key ) {
        $default = isset( self::$defaults[ $key ] ) ? self::$defaults[ $key ] : '';
        return get_option( self::PREFIX . $key, $default );
    }

    /**
     * Get all button options as an array.
     *
     * @return array
     */
    public static function get_all() {
        $opts = array();
        foreach ( self::$defaults as $key => $default ) {
            $opts[ $key ] = get_option( self::PREFIX . $key, $default );
        }
        return $opts;
    }

    /**
     * Register Customizer panel, sections, settings, and controls.
     *
     * @param WP_Customize_Manager $wp_customize
     */
    public function register( $wp_customize ) {

        // ── Panel ────────────────────────────────────────
        $wp_customize->add_panel( 'sudomock_panel', array(
            'title'       => __( 'SudoMock Customizer', 'sudomock-product-customizer' ),
            'description' => __( 'Customize the product customization button appearance on your storefront.', 'sudomock-product-customizer' ),
            'priority'    => 160,
        ) );

        // ── Section: Button ──────────────────────────────
        $wp_customize->add_section( 'sudomock_button', array(
            'title' => __( 'Button', 'sudomock-product-customizer' ),
            'panel' => 'sudomock_panel',
            'priority' => 10,
        ) );

        $this->add_text( $wp_customize, 'label', __( 'Label', 'sudomock-product-customizer' ), 'sudomock_button', 10 );

        $this->add_checkbox( $wp_customize, 'full_width', __( 'Full width', 'sudomock-product-customizer' ), 'sudomock_button', 20 );

        $this->add_checkbox( $wp_customize, 'show_icon', __( 'Show icon', 'sudomock-product-customizer' ), 'sudomock_button', 30 );

        $wp_customize->add_setting( self::PREFIX . 'icon_style', array(
            'default'           => self::$defaults['icon_style'],
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( self::PREFIX . 'icon_style', array(
            'label'   => __( 'Icon', 'sudomock-product-customizer' ),
            'section' => 'sudomock_button',
            'type'    => 'select',
            'choices' => array(
                'pencil'  => __( 'Pencil', 'sudomock-product-customizer' ),
                'palette' => __( 'Palette', 'sudomock-product-customizer' ),
                'wand'    => __( 'Wand', 'sudomock-product-customizer' ),
                'brush'   => __( 'Brush', 'sudomock-product-customizer' ),
                'sparkle' => __( 'Sparkle', 'sudomock-product-customizer' ),
            ),
            'priority' => 35,
        ) );

        $wp_customize->add_setting( self::PREFIX . 'icon_position', array(
            'default'           => self::$defaults['icon_position'],
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( self::PREFIX . 'icon_position', array(
            'label'   => __( 'Icon position', 'sudomock-product-customizer' ),
            'section' => 'sudomock_button',
            'type'    => 'select',
            'choices' => array(
                'left'  => __( 'Left', 'sudomock-product-customizer' ),
                'right' => __( 'Right', 'sudomock-product-customizer' ),
            ),
            'priority' => 36,
        ) );

        $wp_customize->add_setting( self::PREFIX . 'font_weight', array(
            'default'           => self::$defaults['font_weight'],
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( self::PREFIX . 'font_weight', array(
            'label'   => __( 'Weight', 'sudomock-product-customizer' ),
            'section' => 'sudomock_button',
            'type'    => 'select',
            'choices' => array(
                '500' => __( 'Medium', 'sudomock-product-customizer' ),
                '600' => __( 'Semibold', 'sudomock-product-customizer' ),
                '700' => __( 'Bold', 'sudomock-product-customizer' ),
            ),
            'priority' => 40,
        ) );

        $wp_customize->add_setting( self::PREFIX . 'text_transform', array(
            'default'           => self::$defaults['text_transform'],
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( self::PREFIX . 'text_transform', array(
            'label'   => __( 'Case', 'sudomock-product-customizer' ),
            'section' => 'sudomock_button',
            'type'    => 'select',
            'choices' => array(
                'none'      => __( 'Normal', 'sudomock-product-customizer' ),
                'uppercase' => __( 'UPPERCASE', 'sudomock-product-customizer' ),
            ),
            'priority' => 45,
        ) );

        $this->add_checkbox( $wp_customize, 'shadow', __( 'Drop shadow', 'sudomock-product-customizer' ), 'sudomock_button', 50 );

        $wp_customize->add_setting( self::PREFIX . 'position', array(
            'default'           => self::$defaults['position'],
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        $wp_customize->add_control( self::PREFIX . 'position', array(
            'label'   => __( 'Button placement', 'sudomock-product-customizer' ),
            'section' => 'sudomock_button',
            'type'    => 'select',
            'choices' => array(
                'after_add_to_cart'  => __( 'After Add to Cart button', 'sudomock-product-customizer' ),
                'before_add_to_cart' => __( 'Before Add to Cart button', 'sudomock-product-customizer' ),
                'after_summary'      => __( 'After product summary', 'sudomock-product-customizer' ),
                'shortcode'          => __( 'Manual (shortcode only)', 'sudomock-product-customizer' ),
            ),
            'priority' => 55,
            'description' => __( 'Use [sudomock_button] shortcode for manual placement.', 'sudomock-product-customizer' ),
        ) );

        // ── Section: Colors ──────────────────────────────
        $wp_customize->add_section( 'sudomock_colors', array(
            'title' => __( 'Colors', 'sudomock-product-customizer' ),
            'panel' => 'sudomock_panel',
            'priority' => 20,
        ) );

        $this->add_color( $wp_customize, 'bg_color', __( 'Background', 'sudomock-product-customizer' ), 'sudomock_colors', 10 );
        $this->add_color( $wp_customize, 'text_color', __( 'Text', 'sudomock-product-customizer' ), 'sudomock_colors', 20 );
        $this->add_color( $wp_customize, 'border_color', __( 'Border', 'sudomock-product-customizer' ), 'sudomock_colors', 30 );
        $this->add_color( $wp_customize, 'hover_bg_color', __( 'Hover background', 'sudomock-product-customizer' ), 'sudomock_colors', 40 );
        $this->add_color( $wp_customize, 'hover_text_color', __( 'Hover text', 'sudomock-product-customizer' ), 'sudomock_colors', 50 );

        // ── Section: Sizing ──────────────────────────────
        $wp_customize->add_section( 'sudomock_sizing', array(
            'title' => __( 'Sizing', 'sudomock-product-customizer' ),
            'panel' => 'sudomock_panel',
            'priority' => 30,
        ) );

        $this->add_range( $wp_customize, 'font_size', __( 'Font size (px)', 'sudomock-product-customizer' ), 'sudomock_sizing', 10, 12, 22 );
        $this->add_range( $wp_customize, 'padding_y', __( 'Vertical padding (px)', 'sudomock-product-customizer' ), 'sudomock_sizing', 20, 6, 24 );
        $this->add_range( $wp_customize, 'padding_x', __( 'Horizontal padding (px)', 'sudomock-product-customizer' ), 'sudomock_sizing', 30, 12, 48 );
        $this->add_range( $wp_customize, 'border_radius', __( 'Corner radius (px)', 'sudomock-product-customizer' ), 'sudomock_sizing', 40, 0, 30 );
        $this->add_range( $wp_customize, 'border_width', __( 'Border width (px)', 'sudomock-product-customizer' ), 'sudomock_sizing', 50, 0, 4 );

        // ── Section: Extra Text ──────────────────────────
        $wp_customize->add_section( 'sudomock_text', array(
            'title' => __( 'Extra Text', 'sudomock-product-customizer' ),
            'panel' => 'sudomock_panel',
            'priority' => 40,
        ) );

        $this->add_text( $wp_customize, 'heading', __( 'Heading above', 'sudomock-product-customizer' ), 'sudomock_text', 10 );
        $this->add_color( $wp_customize, 'heading_color', __( 'Heading color', 'sudomock-product-customizer' ), 'sudomock_text', 15 );
        $this->add_text( $wp_customize, 'subtext', __( 'Description above', 'sudomock-product-customizer' ), 'sudomock_text', 20 );
        $this->add_color( $wp_customize, 'subtext_color', __( 'Description color', 'sudomock-product-customizer' ), 'sudomock_text', 25 );
        $this->add_text( $wp_customize, 'bottom_text', __( 'Text below', 'sudomock-product-customizer' ), 'sudomock_text', 30 );

        $wp_customize->add_setting( self::PREFIX . 'alignment', array(
            'default'           => self::$defaults['alignment'],
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( self::PREFIX . 'alignment', array(
            'label'   => __( 'Text alignment', 'sudomock-product-customizer' ),
            'section' => 'sudomock_text',
            'type'    => 'select',
            'choices' => array(
                'left'   => __( 'Left', 'sudomock-product-customizer' ),
                'center' => __( 'Center', 'sudomock-product-customizer' ),
                'right'  => __( 'Right', 'sudomock-product-customizer' ),
            ),
            'priority' => 35,
        ) );

        // ── Section: Layout ──────────────────────────────
        $wp_customize->add_section( 'sudomock_layout', array(
            'title' => __( 'Layout', 'sudomock-product-customizer' ),
            'panel' => 'sudomock_panel',
            'priority' => 50,
        ) );

        $this->add_checkbox( $wp_customize, 'divider_top', __( 'Divider above', 'sudomock-product-customizer' ), 'sudomock_layout', 10 );
        $this->add_checkbox( $wp_customize, 'divider_bottom', __( 'Divider below', 'sudomock-product-customizer' ), 'sudomock_layout', 20 );
        $this->add_color( $wp_customize, 'divider_color', __( 'Divider color', 'sudomock-product-customizer' ), 'sudomock_layout', 30 );
        $this->add_range( $wp_customize, 'margin_top', __( 'Top margin (px)', 'sudomock-product-customizer' ), 'sudomock_layout', 40, 0, 40 );
        $this->add_range( $wp_customize, 'margin_bottom', __( 'Bottom margin (px)', 'sudomock-product-customizer' ), 'sudomock_layout', 50, 0, 40 );
    }

    // ── Helpers for registering controls ──────────────────

    private function add_text( $wp_customize, $key, $label, $section, $priority ) {
        $wp_customize->add_setting( self::PREFIX . $key, array(
            'default'           => self::$defaults[ $key ],
            'sanitize_callback' => 'sanitize_text_field',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( self::PREFIX . $key, array(
            'label'    => $label,
            'section'  => $section,
            'type'     => 'text',
            'priority' => $priority,
        ) );
    }

    private function add_checkbox( $wp_customize, $key, $label, $section, $priority ) {
        $wp_customize->add_setting( self::PREFIX . $key, array(
            'default'           => self::$defaults[ $key ],
            'sanitize_callback' => array( __CLASS__, 'sanitize_bool' ),
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( self::PREFIX . $key, array(
            'label'    => $label,
            'section'  => $section,
            'type'     => 'checkbox',
            'priority' => $priority,
        ) );
    }

    private function add_color( $wp_customize, $key, $label, $section, $priority ) {
        $wp_customize->add_setting( self::PREFIX . $key, array(
            'default'           => self::$defaults[ $key ],
            'sanitize_callback' => 'sanitize_hex_color',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, self::PREFIX . $key, array(
            'label'    => $label,
            'section'  => $section,
            'priority' => $priority,
        ) ) );
    }

    private function add_range( $wp_customize, $key, $label, $section, $priority, $min, $max ) {
        $wp_customize->add_setting( self::PREFIX . $key, array(
            'default'           => self::$defaults[ $key ],
            'sanitize_callback' => 'absint',
            'transport'         => 'postMessage',
        ) );
        $wp_customize->add_control( self::PREFIX . $key, array(
            'label'    => $label,
            'section'  => $section,
            'type'     => 'range',
            'priority' => $priority,
            'input_attrs' => array(
                'min'  => $min,
                'max'  => $max,
                'step' => 1,
            ),
        ) );
    }

    /**
     * Enqueue live preview JS.
     */
    public function preview_js() {
        wp_enqueue_script(
            'sudomock-customizer-preview',
            SUDOMOCK_PLUGIN_URL . 'assets/js/customizer-preview.js',
            array( 'customize-preview', 'jquery' ),
            SUDOMOCK_VERSION,
            true
        );
    }

    /**
     * Sanitize boolean for checkbox.
     *
     * @param mixed $value
     * @return bool
     */
    public static function sanitize_bool( $value ) {
        return (bool) $value;
    }
}
