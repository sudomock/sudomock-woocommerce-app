<?php
/**
 * Plugin Name: SudoMock Product Customizer
 * Plugin URI: https://sudomock.com/woocommerce
 * Description: Connect your WooCommerce store to SudoMock's PSD mockup rendering engine. Let customers customize products with professional mockup designs.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: SudoMock
 * Author URI: https://sudomock.com
 * Developer: SudoMock
 * Developer URI: https://sudomock.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sudomock-product-customizer
 * Domain Path: /languages
 * WC requires at least: 8.0
 * WC tested up to: 9.6
 * Requires Plugins: woocommerce
 *
 * @package SudoMock_Product_Customizer
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'SUDOMOCK_VERSION', '1.0.0' );
define( 'SUDOMOCK_PLUGIN_FILE', __FILE__ );
define( 'SUDOMOCK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SUDOMOCK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SUDOMOCK_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SUDOMOCK_API_BASE', 'https://api.sudomock.com' );
define( 'SUDOMOCK_STUDIO_BASE', 'https://studio.sudomock.com' );
define( 'SUDOMOCK_MIN_PHP', '7.4' );
define( 'SUDOMOCK_MIN_WC', '8.0' );

/**
 * Main plugin class — singleton pattern.
 *
 * @since 1.0.0
 */
final class SudoMock_Product_Customizer {

    /**
     * Plugin instance.
     *
     * @var self|null
     */
    private static $instance = null;

    /**
     * Get singleton instance.
     *
     * @return self
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Prevent cloning of the singleton.
     */
    private function __clone() {}

    /**
     * Prevent unserializing of the singleton.
     */
    public function __wakeup() {
        throw new \Exception( 'Cannot unserialize singleton' );
    }

    /**
     * Constructor — hook into WordPress.
     */
    private function __construct() {
        // Check environment before loading
        if ( ! $this->check_environment() ) {
            return;
        }

        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Verify PHP version and WooCommerce availability.
     *
     * @return bool
     */
    private function check_environment() {
        if ( version_compare( PHP_VERSION, SUDOMOCK_MIN_PHP, '<' ) ) {
            add_action( 'admin_notices', array( $this, 'php_version_notice' ) );
            return false;
        }
        return true;
    }

    /**
     * Load class dependencies.
     */
    private function load_dependencies() {
        require_once SUDOMOCK_PLUGIN_DIR . 'includes/class-sudomock-encryption.php';
        require_once SUDOMOCK_PLUGIN_DIR . 'includes/class-sudomock-api-client.php';
        require_once SUDOMOCK_PLUGIN_DIR . 'includes/class-sudomock-customizer.php';
        require_once SUDOMOCK_PLUGIN_DIR . 'includes/class-sudomock-admin.php';
        require_once SUDOMOCK_PLUGIN_DIR . 'includes/class-sudomock-product.php';
        require_once SUDOMOCK_PLUGIN_DIR . 'includes/class-sudomock-storefront.php';
        require_once SUDOMOCK_PLUGIN_DIR . 'includes/class-sudomock-cart.php';
        require_once SUDOMOCK_PLUGIN_DIR . 'includes/class-sudomock-order.php';
        require_once SUDOMOCK_PLUGIN_DIR . 'includes/class-sudomock-blocks.php';
        require_once SUDOMOCK_PLUGIN_DIR . 'includes/class-sudomock-privacy.php';
    }

    /**
     * Initialize all hooks.
     */
    private function init_hooks() {
        // Load translations
        add_action( 'init', array( $this, 'load_textdomain' ) );

        // Customizer always loads (Appearance → Customize)
        SudoMock_Customizer::get_instance();

        // Admin menu must register early — not inside woocommerce_loaded
        if ( is_admin() ) {
            SudoMock_Admin::get_instance();
            SudoMock_Product::get_instance();
        }

        // WooCommerce storefront features — handle both cases:
        // 1) woocommerce_loaded already fired (our plugin loaded after WC)
        // 2) woocommerce_loaded hasn't fired yet
        if ( did_action( 'woocommerce_loaded' ) ) {
            $this->on_woocommerce_loaded();
        } else {
            add_action( 'woocommerce_loaded', array( $this, 'on_woocommerce_loaded' ) );
        }

        // WooCommerce not active notice
        add_action( 'admin_notices', array( $this, 'wc_missing_notice' ) );

        // Declare HPOS compatibility
        add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );

        // Plugin action links
        add_filter( 'plugin_action_links_' . SUDOMOCK_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );

        // Plugin row meta
        add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
    }

    /**
     * Fires when WooCommerce is loaded — initialize all modules.
     */
    public function on_woocommerce_loaded() {
        SudoMock_Product::get_instance();
        SudoMock_Storefront::get_instance();
        SudoMock_Cart::get_instance();
        SudoMock_Order::get_instance();
        SudoMock_Blocks::get_instance();
        SudoMock_Privacy::get_instance();

        // Register Gutenberg block for Site Editor
        add_action( 'init', array( $this, 'register_blocks' ) );
    }

    /**
     * Register Gutenberg blocks.
     */
    public function register_blocks() {
        register_block_type( SUDOMOCK_PLUGIN_DIR . 'blocks/customizer-button' );
    }

    /**
     * Load plugin textdomain for translations.
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'sudomock-product-customizer',
            false,
            dirname( SUDOMOCK_PLUGIN_BASENAME ) . '/languages'
        );
    }

    /**
     * Declare WooCommerce HPOS (High-Performance Order Storage) compatibility.
     */
    public function declare_hpos_compatibility() {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                SUDOMOCK_PLUGIN_FILE,
                true
            );
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'cart_checkout_blocks',
                SUDOMOCK_PLUGIN_FILE,
                true
            );
        }
    }

    /**
     * Add Settings link to plugins page.
     *
     * @param array $links Existing links.
     * @return array
     */
    public function plugin_action_links( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'admin.php?page=sudomock-settings' ) ),
            esc_html__( 'Settings', 'sudomock-product-customizer' )
        );
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Add Documentation link to plugin row meta.
     *
     * @param array  $links Plugin row meta links.
     * @param string $file  Plugin file.
     * @return array
     */
    public function plugin_row_meta( $links, $file ) {
        if ( SUDOMOCK_PLUGIN_BASENAME === $file ) {
            $links[] = sprintf(
                '<a href="%s" target="_blank">%s</a>',
                esc_url( 'https://sudomock.com/docs/woocommerce' ),
                esc_html__( 'Documentation', 'sudomock-product-customizer' )
            );
        }
        return $links;
    }

    /**
     * Admin notice: WooCommerce not active.
     */
    public function wc_missing_notice() {
        if ( class_exists( 'WooCommerce' ) ) {
            return;
        }
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                printf(
                    /* translators: %s: WooCommerce plugin name */
                    esc_html__( '%1$s requires %2$s to be installed and active.', 'sudomock-product-customizer' ),
                    '<strong>SudoMock Product Customizer</strong>',
                    '<strong>WooCommerce</strong>'
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Admin notice: PHP version too low.
     */
    public function php_version_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <?php
                printf(
                    /* translators: %s: Required PHP version */
                    esc_html__( 'SudoMock Product Customizer requires PHP %s or higher.', 'sudomock-product-customizer' ),
                    SUDOMOCK_MIN_PHP
                );
                ?>
            </p>
        </div>
        <?php
    }
}

// Boot the plugin
add_action( 'plugins_loaded', array( 'SudoMock_Product_Customizer', 'get_instance' ) );

// Activation hook
register_activation_hook( __FILE__, 'sudomock_activate' );

/**
 * Plugin activation.
 */
function sudomock_activate() {
    if ( version_compare( PHP_VERSION, SUDOMOCK_MIN_PHP, '<' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die(
            sprintf(
                /* translators: %s: Required PHP version */
                esc_html__( 'SudoMock Product Customizer requires PHP %s or higher.', 'sudomock-product-customizer' ),
                SUDOMOCK_MIN_PHP
            )
        );
    }
    // Set default options
    add_option( 'sudomock_version', SUDOMOCK_VERSION );
    add_option( 'sudomock_button_label', __( 'Customize This Product', 'sudomock-product-customizer' ) );
    add_option( 'sudomock_display_mode', 'iframe' );

    // Flush rewrite rules
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook( __FILE__, 'sudomock_deactivate' );

/**
 * Plugin deactivation.
 */
function sudomock_deactivate() {
    flush_rewrite_rules();
}
