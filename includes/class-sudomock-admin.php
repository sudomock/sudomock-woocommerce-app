<?php
/**
 * Admin Settings Page — Shopify app level UI.
 *
 * @package SudoMock_Product_Customizer
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class SudoMock_Admin {

    /** @var self|null */
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ), 90 );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_sudomock_save_api_key', array( $this, 'ajax_save_api_key' ) );
        add_action( 'wp_ajax_sudomock_disconnect', array( $this, 'ajax_disconnect' ) );
        add_action( 'wp_ajax_sudomock_list_mockups', array( $this, 'ajax_list_mockups' ) );
        add_action( 'wp_ajax_sudomock_map_product', array( $this, 'ajax_map_product' ) );
        add_action( 'wp_ajax_sudomock_unmap_product', array( $this, 'ajax_unmap_product' ) );
        add_action( 'wp_ajax_sudomock_get_studio_config', array( $this, 'ajax_get_studio_config' ) );
        add_action( 'wp_ajax_sudomock_save_studio_config', array( $this, 'ajax_save_studio_config' ) );
        add_action( 'wp_ajax_sudomock_submit_support', array( $this, 'ajax_submit_support' ) );
        add_action( 'wp_ajax_sudomock_dismiss_onboarding', array( $this, 'ajax_dismiss_onboarding' ) );
        add_action( 'wp_ajax_sudomock_generate_gallery', array( $this, 'ajax_generate_gallery' ) );
    }

    public function register_settings() {
        register_setting( 'sudomock_settings', 'sudomock_button_label', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => __( 'Customize This Product', 'sudomock-product-customizer' ),
        ) );
        register_setting( 'sudomock_settings', 'sudomock_display_mode', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'iframe',
        ) );
    }

    public function add_menu() {
        add_menu_page(
            __( 'SudoMock', 'sudomock-product-customizer' ),
            __( 'SudoMock', 'sudomock-product-customizer' ),
            'manage_woocommerce',
            'sudomock-settings',
            array( $this, 'render_page' ),
            SUDOMOCK_PLUGIN_URL . 'assets/images/icon-20.svg',
            58
        );
    }

    public function enqueue_assets( $hook_suffix ) {
        if ( 'toplevel_page_sudomock-settings' !== $hook_suffix ) {
            return;
        }

        wp_enqueue_style(
            'sudomock-admin',
            SUDOMOCK_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            SUDOMOCK_VERSION
        );

        wp_enqueue_script(
            'sudomock-admin',
            SUDOMOCK_PLUGIN_URL . 'assets/js/admin.js',
            array(),
            SUDOMOCK_VERSION,
            array( 'in_footer' => true, 'strategy' => 'defer' )
        );

        wp_localize_script( 'sudomock-admin', 'sudomockAdmin', array(
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'sudomock_admin' ),
            'connectUrl' => 'https://sudomock.com/integrations/woocommerce/connect?site_url=' . rawurlencode( site_url() ) . '&return_url=' . rawurlencode( admin_url( 'admin.php?page=sudomock-settings' ) ),
            'i18n'       => array(
                'connect'                 => __( 'Connect with SudoMock', 'sudomock-product-customizer' ),
                'connecting'              => __( 'Connecting...', 'sudomock-product-customizer' ),
                'saving'                  => __( 'Saving connection...', 'sudomock-product-customizer' ),
                'connected'               => __( 'Connected', 'sudomock-product-customizer' ),
                'error'                   => __( 'Connection failed', 'sudomock-product-customizer' ),
                'confirmDisconnect'       => __( 'Are you sure you want to disconnect? Product customizations will stop working.', 'sudomock-product-customizer' ),
                'confirmDisconnectDetail' => __( 'This will remove all mockup assignments from your products and notify the SudoMock server. Product customization will stop working immediately.', 'sudomock-product-customizer' ),
                'disconnecting'           => __( 'Disconnecting...', 'sudomock-product-customizer' ),
                'assigning'               => __( 'Assigning...', 'sudomock-product-customizer' ),
                'assignMockup'            => __( 'Assign Mockup', 'sudomock-product-customizer' ),
                'noMockupsFound'          => __( 'No mockups found.', 'sudomock-product-customizer' ),
                'loadingMockups'          => __( 'Loading mockups...', 'sudomock-product-customizer' ),
                'failedToLoad'            => __( 'Failed to load mockups.', 'sudomock-product-customizer' ),
                'failedToMap'             => __( 'Failed to map mockup.', 'sudomock-product-customizer' ),
                'networkError'            => __( 'Network error. Please try again.', 'sudomock-product-customizer' ),
                'removeMockupConfirm'     => __( 'Remove mockup mapping from this product?', 'sudomock-product-customizer' ),
                'assigningTo'             => __( 'Assigning mockup to:', 'sudomock-product-customizer' ),
                'noPreview'               => __( 'No preview', 'sudomock-product-customizer' ),
                'smartObject'             => __( 'smart object', 'sudomock-product-customizer' ),
                'smartObjects'            => __( 'smart objects', 'sudomock-product-customizer' ),
                'uploadFirstPsd'          => __( 'Upload Your First PSD', 'sudomock-product-customizer' ),
                'noPsdMockups'            => __( 'No PSD mockups yet', 'sudomock-product-customizer' ),
                'uploadPsdDesc'           => __( 'Upload PSD mockup files in your SudoMock Dashboard. Mockups with smart objects will appear here automatically.', 'sudomock-product-customizer' ),
                'noMockupsMatch'          => __( 'No mockups match', 'sudomock-product-customizer' ),
                'page'                    => __( 'Page', 'sudomock-product-customizer' ),
                'of'                      => __( 'of', 'sudomock-product-customizer' ),
                'mockups'                 => __( 'mockups', 'sudomock-product-customizer' ),
                'mockup'                  => __( 'mockup', 'sudomock-product-customizer' ),
                'previous'                => __( 'Previous', 'sudomock-product-customizer' ),
                'next'                    => __( 'Next', 'sudomock-product-customizer' ),
                'popupBlocked'            => __( 'Popup blocked. Please allow popups for this site.', 'sudomock-product-customizer' ),
                'configError'             => __( 'Configuration error.', 'sudomock-product-customizer' ),
                'settingsSaved'           => __( 'Settings saved successfully.', 'sudomock-product-customizer' ),
                'resetConfirm'            => __( 'Reset all studio settings to defaults?', 'sudomock-product-customizer' ),
                'changesDiscarded'        => __( 'Changes discarded.', 'sudomock-product-customizer' ),
                'saveStudioConfig'        => __( 'Save Studio Config', 'sudomock-product-customizer' ),
                'networkErrorConfig'      => __( 'Network error saving config.', 'sudomock-product-customizer' ),
                'failedToSave'            => __( 'Failed to save.', 'sudomock-product-customizer' ),
                'text'                    => __( 'text', 'sudomock-product-customizer' ),
                // Support form
                'supportSubjectRequired'  => __( 'Please enter a subject.', 'sudomock-product-customizer' ),
                'supportMessageRequired'  => __( 'Please enter a message.', 'sudomock-product-customizer' ),
                'supportSending'          => __( 'Sending...', 'sudomock-product-customizer' ),
                'supportSent'             => __( 'Your message has been sent. We will get back to you soon.', 'sudomock-product-customizer' ),
                'supportFailed'           => __( 'Failed to send message. Please try again.', 'sudomock-product-customizer' ),
                'supportRateLimit'        => __( 'You have reached the limit. Please wait before sending another message.', 'sudomock-product-customizer' ),
                'submitTicket'            => __( 'Submit', 'sudomock-product-customizer' ),
                // Onboarding
                'onboardingDismissed'     => __( 'Onboarding dismissed.', 'sudomock-product-customizer' ),
            ),
        ) );
    }

    /* ------------------------------------------------------------------ */
    /* AJAX Handlers                                                       */
    /* ------------------------------------------------------------------ */

    public function ajax_save_api_key() {
        check_ajax_referer( 'sudomock_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'sudomock-product-customizer' ) ), 403 );
        }

        $api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
        if ( empty( $api_key ) || 0 !== strpos( $api_key, 'sm_' ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid API key format. Keys start with sm_', 'sudomock-product-customizer' ) ) );
        }

        $result = SudoMock_API_Client::validate_key( $api_key );
        if ( ! $result['ok'] ) {
            wp_send_json_error( array( 'message' => $result['error'] ) );
        }

        SudoMock_API_Client::save_api_key( $api_key );

        $account = $result['data'];
        update_option( 'sudomock_account_email', sanitize_email( $account['account']['email'] ) );
        update_option( 'sudomock_plan_name', sanitize_text_field( $account['subscription']['plan'] ) );
        update_option( 'sudomock_plan_tier', sanitize_text_field( $account['subscription']['tier'] ) );
        update_option( 'sudomock_credits_used', absint( $account['usage']['credits_used_this_month'] ) );
        update_option( 'sudomock_credits_limit', absint( $account['usage']['credits_limit'] ) );
        update_option( 'sudomock_credits_remaining', absint( $account['usage']['credits_remaining'] ) );
        update_option( 'sudomock_connected_at', current_time( 'mysql' ) );

        wp_send_json_success( array(
            'message' => __( 'Connected successfully!', 'sudomock-product-customizer' ),
        ) );
    }

    public function ajax_disconnect() {
        check_ajax_referer( 'sudomock_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'sudomock-product-customizer' ) ), 403 );
        }

        // 1. Remove all product mockup meta data
        global $wpdb;
        $wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_sudomock_mockup_uuid' ) );       // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        $wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_sudomock_customization_enabled' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        $wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_sudomock_mockup_name' ) );        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key

        // 2. Notify backend about disconnect (best-effort, ignore errors)
        SudoMock_API_Client::notify_disconnect();

        // 3. Clear cached account data
        delete_transient( 'sudomock_account_data' );

        // 4. Remove all plugin options
        delete_option( 'sudomock_api_key' );
        delete_option( 'sudomock_account_email' );
        delete_option( 'sudomock_plan_name' );
        delete_option( 'sudomock_plan_tier' );
        delete_option( 'sudomock_credits_used' );
        delete_option( 'sudomock_credits_limit' );
        delete_option( 'sudomock_credits_remaining' );
        delete_option( 'sudomock_connected_at' );
        delete_option( 'sudomock_onboarding_dismissed' );

        wp_send_json_success( array( 'message' => __( 'Disconnected.', 'sudomock-product-customizer' ) ) );
    }

    public function ajax_list_mockups() {
        check_ajax_referer( 'sudomock_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'sudomock-product-customizer' ) ), 403 );
        }

        $search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
        $limit  = isset( $_GET['limit'] ) ? min( absint( $_GET['limit'] ), 50 ) : 20;
        $offset = isset( $_GET['offset'] ) ? absint( $_GET['offset'] ) : 0;
        $page   = isset( $_GET['page_num'] ) ? absint( $_GET['page_num'] ) : 0;

        // page_num override (backwards compat)
        if ( $page > 0 ) {
            $offset = ( $page - 1 ) * $limit;
        }

        $result = SudoMock_API_Client::list_mockups( array(
            'name'   => $search,
            'limit'  => $limit,
            'offset' => $offset,
        ) );

        if ( ! $result['ok'] ) {
            wp_send_json_error( array( 'message' => $result['error'] ) );
        }

        wp_send_json_success( $result['data'] );
    }

    /* ------------------------------------------------------------------ */
    /* Render Page                                                         */
    /* ------------------------------------------------------------------ */

    public function render_page() {
        $is_connected = ! empty( SudoMock_API_Client::get_api_key() );

        // Refresh account data from API (cached 5 min to reduce API calls)
        if ( $is_connected ) {
            $account = get_transient( 'sudomock_account_data' );
            if ( false === $account ) {
                $result = SudoMock_API_Client::validate_key();
                if ( $result['ok'] && ! empty( $result['data'] ) ) {
                    $account = $result['data'];
                    set_transient( 'sudomock_account_data', $account, 5 * MINUTE_IN_SECONDS );
                }
            }
            if ( ! empty( $account ) ) {
                update_option( 'sudomock_account_email', sanitize_email( $account['account']['email'] ) );
                update_option( 'sudomock_plan_name', sanitize_text_field( $account['subscription']['plan'] ) );
                update_option( 'sudomock_plan_tier', sanitize_text_field( $account['subscription']['tier'] ) );
                update_option( 'sudomock_credits_used', absint( $account['usage']['credits_used_this_month'] ) );
                update_option( 'sudomock_credits_limit', absint( $account['usage']['credits_limit'] ) );
                update_option( 'sudomock_credits_remaining', absint( $account['usage']['credits_remaining'] ) );
            }
        }

        // Tab routing
        $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'dashboard';
        $base_url    = admin_url( 'admin.php?page=sudomock-settings' );

        $tabs = array(
            'dashboard'  => __( 'Dashboard', 'sudomock-product-customizer' ),
            'products'   => __( 'Products', 'sudomock-product-customizer' ),
            'mockups'    => __( 'Mockups', 'sudomock-product-customizer' ),
            'settings'   => __( 'Settings', 'sudomock-product-customizer' ),
        );

        if ( ! $is_connected ) {
            $this->render_setup();
            return;
        }

        // Common dashboard data
        $email           = get_option( 'sudomock_account_email', '' );
        $plan            = get_option( 'sudomock_plan_name', '' );
        $plan_tier       = get_option( 'sudomock_plan_tier', 'free' );
        $credits_used    = (int) get_option( 'sudomock_credits_used', 0 );
        $credits_limit   = (int) get_option( 'sudomock_credits_limit', 0 );
        $connected_at    = get_option( 'sudomock_connected_at', '' );
        $display_mode    = get_option( 'sudomock_display_mode', 'iframe' );
        $button_label    = get_option( 'sudomock_button_label', __( 'Customize This Product', 'sudomock-product-customizer' ) );
        $credits_percent = $credits_limit > 0 ? min( round( ( $credits_used / $credits_limit ) * 100 ), 100 ) : 0;

        // Product counts
        $total_count  = (int) wp_count_posts( 'product' )->publish;
        $mapped_count = 0;
        global $wpdb;
        $mapped_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value != %s",
                '_sudomock_mockup_uuid',
                ''
            )
        );

        $data = compact(
            'email', 'plan', 'plan_tier', 'credits_used', 'credits_limit', 'credits_percent',
            'connected_at', 'display_mode', 'button_label', 'mapped_count', 'total_count'
        );
        ?>
        <div class="wrap sudomock-wrap">
            <div class="sudomock-page-header">
                <img src="<?php echo esc_url( SUDOMOCK_PLUGIN_URL . 'assets/images/logo.svg' ); ?>" alt="SudoMock" style="height:32px;width:auto;" />
            </div>

            <!-- Tab Navigation -->
            <nav class="sudomock-tabs">
                <?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'tab', $tab_key, $base_url ) ); ?>"
                       class="sudomock-tab <?php echo $current_tab === $tab_key ? 'sudomock-tab--active' : ''; ?>">
                        <?php echo esc_html( $tab_label ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php
            switch ( $current_tab ) {
                case 'products':
                    $this->render_products_tab( $data );
                    break;
                case 'mockups':
                    $this->render_mockups_tab();
                    break;
                case 'settings':
                    $this->render_settings_tab( $data );
                    break;
                default:
                    $this->render_dashboard( $data );
                    break;
            }
            ?>
        </div>
        <?php
    }


    /* ------------------------------------------------------------------ */
    /* Setup View (not connected)                                          */
    /* ------------------------------------------------------------------ */

    private function render_setup() {
        ?>
        <div class="wrap sudomock-wrap">
            <div class="sudomock-setup">
                <div class="sudomock-setup__main">
                    <div class="sudomock-card">
                        <div class="sudomock-card__body">
                            <h1 class="sudomock-setup__title">
                                <?php esc_html_e( 'Product Customization, Powered by Your PSDs', 'sudomock-product-customizer' ); ?>
                            </h1>
                            <p class="sudomock-setup__desc">
                                <?php esc_html_e( 'Let shoppers personalize products with their own artwork, logos, and text — rendered onto your PSD mockups in real time.', 'sudomock-product-customizer' ); ?>
                            </p>
                            <hr class="sudomock-divider" />
                            <h3 class="sudomock-setup__subtitle">
                                <?php esc_html_e( 'Connect your SudoMock account to begin', 'sudomock-product-customizer' ); ?>
                            </h3>
                            <div class="sudomock-setup__actions">
                                <button type="button" class="sudomock-btn sudomock-btn--primary sudomock-btn--lg" id="sudomock-connect-btn">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                    <?php esc_html_e( 'Connect Account', 'sudomock-product-customizer' ); ?>
                                </button>
                                <span class="sudomock-setup__or">
                                    <?php
                                    printf(
                                        /* translators: %s: registration link */
                                        esc_html__( 'or %s', 'sudomock-product-customizer' ),
                                        '<a href="https://sudomock.com/register" target="_blank" rel="noopener">' . esc_html__( 'create a free account', 'sudomock-product-customizer' ) . '</a>'
                                    );
                                    ?>
                                </span>
                            </div>
                            <div id="sudomock-connect-feedback" class="sudomock-feedback" style="display:none;"></div>
                        </div>
                    </div>

                    <!-- How It Works -->
                    <div class="sudomock-card">
                        <div class="sudomock-card__body">
                            <h2 class="sudomock-card__title"><?php esc_html_e( 'How It Works', 'sudomock-product-customizer' ); ?></h2>
                            <div class="sudomock-steps">
                                <?php
                                $steps = array(
                                    array( '1', __( 'Upload PSDs', 'sudomock-product-customizer' ), __( 'Upload Photoshop mockups on sudomock.com.', 'sudomock-product-customizer' ) ),
                                    array( '2', __( 'Map to Products', 'sudomock-product-customizer' ), __( 'Assign a PSD mockup to each WooCommerce product.', 'sudomock-product-customizer' ) ),
                                    array( '3', __( 'Customers Customize', 'sudomock-product-customizer' ), __( 'Shoppers upload artwork and see a live preview.', 'sudomock-product-customizer' ) ),
                                    array( '4', __( 'Render & Cart', 'sudomock-product-customizer' ), __( 'Final mockup rendered and attached to cart.', 'sudomock-product-customizer' ) ),
                                );
                                foreach ( $steps as $step ) :
                                ?>
                                <div class="sudomock-step">
                                    <div class="sudomock-step__num"><?php echo esc_html( $step[0] ); ?></div>
                                    <div>
                                        <h4 class="sudomock-step__title"><?php echo esc_html( $step[1] ); ?></h4>
                                        <p class="sudomock-step__desc"><?php echo esc_html( $step[2] ); ?></p>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="sudomock-setup__side">
                    <div class="sudomock-card">
                        <div class="sudomock-card__body">
                            <h3 class="sudomock-card__title"><?php esc_html_e( 'Pricing', 'sudomock-product-customizer' ); ?></h3>
                            <p class="sudomock-text--muted">
                                <?php esc_html_e( 'Free to install. Pay per render from your credit balance — $0.002 per render.', 'sudomock-product-customizer' ); ?>
                            </p>
                            <hr class="sudomock-divider" />
                            <div class="sudomock-pricing__highlight">
                                <span class="sudomock-pricing__number">500</span>
                                <span class="sudomock-text--muted"><?php esc_html_e( 'free renders / month', 'sudomock-product-customizer' ); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /* ------------------------------------------------------------------ */
    /* Dashboard View (connected)                                          */
    /* ------------------------------------------------------------------ */

    private function render_dashboard( $d ) {
        $bar_tone = $d['credits_percent'] > 80 ? 'sudomock-bar--critical' : 'sudomock-bar--primary';
        ?>
            <!-- Account + Credits -->
            <div class="sudomock-card">
                <div class="sudomock-card__body">
                    <div class="sudomock-account-row">
                        <div class="sudomock-account-row__left">
                            <span class="sudomock-dot sudomock-dot--success"></span>
                            <span class="sudomock-account-row__email"><?php echo esc_html( $d['email'] ); ?></span>
                            <span class="sudomock-badge sudomock-badge--<?php echo esc_attr( $d['plan_tier'] === 'free' ? 'info' : 'success' ); ?>">
                                <?php echo esc_html( ucfirst( $d['plan'] ) ); ?>
                            </span>
                        </div>
                        <div class="sudomock-account-row__right">
                            <a href="https://sudomock.com/dashboard/billing" target="_blank" rel="noopener" class="sudomock-btn sudomock-btn--sm">
                                <?php echo esc_html( $d['plan_tier'] === 'free' ? __( 'Upgrade', 'sudomock-product-customizer' ) : __( 'Manage Plan', 'sudomock-product-customizer' ) ); ?>
                            </a>
                            <button type="button" class="sudomock-btn sudomock-btn--sm sudomock-btn--danger-text" id="sudomock-disconnect-btn">
                                <?php esc_html_e( 'Disconnect', 'sudomock-product-customizer' ); ?>
                            </button>
                        </div>
                    </div>
                    <div class="sudomock-credits-bar">
                        <div class="sudomock-bar <?php echo esc_attr( $bar_tone ); ?>">
                            <div class="sudomock-bar__fill" style="width:<?php echo esc_attr( $d['credits_percent'] ); ?>%"></div>
                        </div>
                        <span class="sudomock-text--muted sudomock-text--sm">
                            <?php
                            printf(
                                /* translators: %1$s: used credits, %2$s: total credits */
                                esc_html__( '%1$s / %2$s credits', 'sudomock-product-customizer' ),
                                number_format_i18n( $d['credits_used'] ),
                                number_format_i18n( $d['credits_limit'] )
                            );
                            ?>
                        </span>
                    </div>
                </div>
            </div>

            <?php
            // Onboarding: show only if connected + not dismissed
            $onboarding_dismissed = get_option( 'sudomock_onboarding_dismissed', false );
            if ( ! $onboarding_dismissed ) :
            ?>
            <div id="sudomock-onboarding" class="sudomock-card">
                <div class="sudomock-card__body">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0f172a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            <h2 class="sudomock-card__title" style="margin-bottom:0;"><?php esc_html_e( 'Get Started', 'sudomock-product-customizer' ); ?></h2>
                        </div>
                        <button type="button" id="sudomock-onboarding-dismiss" class="sudomock-btn sudomock-btn--sm">
                            <?php esc_html_e( 'Dismiss', 'sudomock-product-customizer' ); ?>
                        </button>
                    </div>
                    <p class="sudomock-text--muted" style="margin-bottom:20px;">
                        <?php esc_html_e( 'Complete these 3 steps to start offering product customization to your customers.', 'sudomock-product-customizer' ); ?>
                    </p>
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
                        <!-- Step 1 -->
                        <div style="background:#f8fafc;border:1px solid #e1e3e5;border-radius:10px;padding:20px;">
                            <div class="sudomock-step__num" style="margin-bottom:12px;">1</div>
                            <h4 style="font-size:14px;font-weight:600;margin:0 0 6px;color:#1a1a1a;">
                                <?php esc_html_e( 'Upload your PSD mockups', 'sudomock-product-customizer' ); ?>
                            </h4>
                            <p class="sudomock-text--muted" style="margin:0 0 12px;font-size:12px;line-height:1.5;">
                                <?php esc_html_e( 'Upload your Photoshop mockup files with smart object layers in the SudoMock Dashboard.', 'sudomock-product-customizer' ); ?>
                            </p>
                            <a href="https://sudomock.com/dashboard/playground" target="_blank" rel="noopener" class="sudomock-btn sudomock-btn--sm sudomock-btn--primary">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                <?php esc_html_e( 'Open Dashboard', 'sudomock-product-customizer' ); ?>
                            </a>
                        </div>
                        <!-- Step 2 -->
                        <div style="background:#f8fafc;border:1px solid #e1e3e5;border-radius:10px;padding:20px;">
                            <div class="sudomock-step__num" style="margin-bottom:12px;">2</div>
                            <h4 style="font-size:14px;font-weight:600;margin:0 0 6px;color:#1a1a1a;">
                                <?php esc_html_e( 'Map mockups to products', 'sudomock-product-customizer' ); ?>
                            </h4>
                            <p class="sudomock-text--muted" style="margin:0 0 12px;font-size:12px;line-height:1.5;">
                                <?php esc_html_e( 'Assign a PSD mockup to each WooCommerce product you want to offer customization for.', 'sudomock-product-customizer' ); ?>
                            </p>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=sudomock-settings&tab=products' ) ); ?>" class="sudomock-btn sudomock-btn--sm sudomock-btn--primary">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                                <?php esc_html_e( 'Products Tab', 'sudomock-product-customizer' ); ?>
                            </a>
                        </div>
                        <!-- Step 3 -->
                        <div style="background:#f8fafc;border:1px solid #e1e3e5;border-radius:10px;padding:20px;">
                            <div class="sudomock-step__num" style="margin-bottom:12px;">3</div>
                            <h4 style="font-size:14px;font-weight:600;margin:0 0 6px;color:#1a1a1a;">
                                <?php esc_html_e( 'Customize button appearance', 'sudomock-product-customizer' ); ?>
                            </h4>
                            <p class="sudomock-text--muted" style="margin:0 0 12px;font-size:12px;line-height:1.5;">
                                <?php esc_html_e( 'Style the "Customize" button on product pages to match your store theme.', 'sudomock-product-customizer' ); ?>
                            </p>
                            <a href="<?php echo esc_url( admin_url( 'customize.php?autofocus[section]=sudomock_button&url=' . rawurlencode( sudomock_get_customizer_preview_url() ) ) ); ?>" class="sudomock-btn sudomock-btn--sm sudomock-btn--primary">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9"/></svg>
                                <?php esc_html_e( 'Open Customizer', 'sudomock-product-customizer' ); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ( $d['credits_percent'] > 80 ) : ?>
            <div class="sudomock-banner sudomock-banner--warning">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <?php
                printf(
                    /* translators: %d: percentage of credits used */
                    esc_html__( 'You have used %d%% of your monthly credits.', 'sudomock-product-customizer' ),
                    $d['credits_percent']
                );
                ?>
                <a href="https://sudomock.com/dashboard/billing" target="_blank" rel="noopener" class="sudomock-btn sudomock-btn--sm"><?php esc_html_e( 'Upgrade', 'sudomock-product-customizer' ); ?></a>
            </div>
            <?php endif; ?>

            <div class="sudomock-dashboard-grid">
                <!-- Setup Progress -->
                <div class="sudomock-dashboard-grid__main">
                    <div class="sudomock-card">
                        <div class="sudomock-card__body">
                            <?php
                            $has_mapped = $d['mapped_count'] > 0;
                            $steps_done = 1 + ( $has_mapped ? 1 : 0 ); // Connected = always done
                            $steps_total = 2;
                            $setup_percent = round( ( $steps_done / $steps_total ) * 100 );
                            ?>
                            <div class="sudomock-setup-header">
                                <h2 class="sudomock-card__title"><?php esc_html_e( 'Setup Progress', 'sudomock-product-customizer' ); ?></h2>
                                <span class="sudomock-badge sudomock-badge--<?php echo $steps_done === $steps_total ? 'success' : 'attention'; ?>">
                                    <?php
                                    printf(
                                        /* translators: %1$d: done, %2$d: total */
                                        esc_html__( '%1$d of %2$d', 'sudomock-product-customizer' ),
                                        $steps_done,
                                        $steps_total
                                    );
                                    ?>
                                </span>
                            </div>
                            <div class="sudomock-bar sudomock-bar--<?php echo $steps_done === $steps_total ? 'success' : 'primary'; ?>" style="margin-bottom: 16px;">
                                <div class="sudomock-bar__fill" style="width:<?php echo esc_attr( $setup_percent ); ?>%"></div>
                            </div>
                            <div class="sudomock-checklist">
                                <div class="sudomock-checklist__item sudomock-checklist__item--done">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" fill="#10b981"/><path d="M8 12l3 3 5-5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    <span><?php esc_html_e( 'Account connected', 'sudomock-product-customizer' ); ?></span>
                                </div>
                                <div class="sudomock-checklist__item <?php echo $has_mapped ? 'sudomock-checklist__item--done' : ''; ?>">
                                    <?php if ( $has_mapped ) : ?>
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" fill="#10b981"/><path d="M8 12l3 3 5-5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    <?php else : ?>
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="#94a3b8" stroke-width="2"/><line x1="8" y1="12" x2="16" y2="12" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"/></svg>
                                    <?php endif; ?>
                                    <span><?php esc_html_e( 'Map PSD mockups to products', 'sudomock-product-customizer' ); ?></span>
                                    <?php if ( ! $has_mapped ) : ?>
                                        <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>" class="sudomock-btn sudomock-btn--sm"><?php esc_html_e( 'Map Products', 'sudomock-product-customizer' ); ?></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar: Resources + Quick Stats -->
                <div class="sudomock-dashboard-grid__side">
                    <div class="sudomock-card">
                        <div class="sudomock-card__body">
                            <h3 class="sudomock-card__title"><?php esc_html_e( 'Resources', 'sudomock-product-customizer' ); ?></h3>
                            <div class="sudomock-resource-links">
                                <a href="https://sudomock.com/dashboard/playground" target="_blank" rel="noopener" class="sudomock-resource-link">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                    <?php esc_html_e( 'Upload PSD Mockups', 'sudomock-product-customizer' ); ?>
                                </a>
                                <a href="https://sudomock.com/docs/psd-preparation" target="_blank" rel="noopener" class="sudomock-resource-link">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                    <?php esc_html_e( 'PSD Preparation Guide', 'sudomock-product-customizer' ); ?>
                                </a>
                                <a href="https://sudomock.com/docs/integrations/woocommerce" target="_blank" rel="noopener" class="sudomock-resource-link">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                    <?php esc_html_e( 'Integration Docs', 'sudomock-product-customizer' ); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Nav Cards (inside grid flow) -->
            <div class="sudomock-nav-cards" style="margin-top:0;">
                <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>" class="sudomock-nav-card">
                    <div class="sudomock-nav-card__icon sudomock-nav-card__icon--green">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                    </div>
                    <div class="sudomock-nav-card__text">
                        <h4><?php esc_html_e( 'Products', 'sudomock-product-customizer' ); ?></h4>
                        <p>
                            <?php
                            printf(
                                /* translators: %1$d: mapped, %2$d: total */
                                esc_html__( '%1$d of %2$d mapped', 'sudomock-product-customizer' ),
                                $d['mapped_count'],
                                $d['total_count']
                            );
                            ?>
                        </p>
                    </div>
                    <svg class="sudomock-nav-card__arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7"/><path d="M7 7h10v10"/></svg>
                </a>
                <a href="https://sudomock.com/dashboard/playground" target="_blank" rel="noopener" class="sudomock-nav-card">
                    <div class="sudomock-nav-card__icon sudomock-nav-card__icon--blue">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
                    </div>
                    <div class="sudomock-nav-card__text">
                        <h4><?php esc_html_e( 'Mockups', 'sudomock-product-customizer' ); ?></h4>
                        <p><?php esc_html_e( 'Browse PSD mockups', 'sudomock-product-customizer' ); ?></p>
                    </div>
                    <svg class="sudomock-nav-card__arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7"/><path d="M7 7h10v10"/></svg>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=sudomock-settings&tab=settings' ) ); ?>" class="sudomock-nav-card">
                    <div class="sudomock-nav-card__icon sudomock-nav-card__icon--orange">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    </div>
                    <div class="sudomock-nav-card__text">
                        <h4><?php esc_html_e( 'Settings & Billing', 'sudomock-product-customizer' ); ?></h4>
                        <p><?php esc_html_e( 'Studio config and display settings', 'sudomock-product-customizer' ); ?></p>
                    </div>
                    <svg class="sudomock-nav-card__arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7"/><path d="M7 7h10v10"/></svg>
                </a>
            </div>
        <?php
    }

    /* ------------------------------------------------------------------ */
    /* Products Tab                                                        */
    /* ------------------------------------------------------------------ */

    private function render_products_tab( $d ) {
        $search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
        $filter = isset( $_GET['filter'] ) ? sanitize_text_field( $_GET['filter'] ) : 'all';
        $paged  = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

        $args = array(
            'post_type'      => 'product',
            'posts_per_page' => 20,
            'paged'          => $paged,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        if ( ! empty( $search ) ) {
            $args['s'] = $search;
        }

        if ( 'mapped' === $filter ) {
            $args['meta_query'] = array( array(
                'key'     => '_sudomock_mockup_uuid',
                'value'   => '',
                'compare' => '!=',
            ) );
        } elseif ( 'unmapped' === $filter ) {
            $args['meta_query'] = array(
                'relation' => 'OR',
                array( 'key' => '_sudomock_mockup_uuid', 'compare' => 'NOT EXISTS' ),
                array( 'key' => '_sudomock_mockup_uuid', 'value' => '' ),
            );
        }

        $query    = new WP_Query( $args );
        $products = $query->posts;
        $total    = $query->found_posts;
        $base_url = admin_url( 'admin.php?page=sudomock-settings&tab=products' );
        ?>

        <!-- Search -->
        <div class="sudomock-card" style="margin-bottom: 0;">
            <div class="sudomock-card__body" style="padding: 16px 24px;">
                <form method="get" style="display: flex; gap: 12px; align-items: center;">
                    <input type="hidden" name="page" value="sudomock-settings" />
                    <input type="hidden" name="tab" value="products" />
                    <input type="text" name="s" value="<?php echo esc_attr( $search ); ?>" class="sudomock-input" style="max-width: 100%; flex: 1;" placeholder="<?php esc_attr_e( 'Search products...', 'sudomock-product-customizer' ); ?>" />
                    <button type="submit" class="sudomock-btn"><?php esc_html_e( 'Search', 'sudomock-product-customizer' ); ?></button>
                </form>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="sudomock-filter-tabs">
            <a href="<?php echo esc_url( add_query_arg( 'filter', 'all', $base_url ) ); ?>"
               class="sudomock-filter-tab <?php echo 'all' === $filter ? 'sudomock-filter-tab--active' : ''; ?>">
                <?php
                /* translators: %d: total product count */
                printf( esc_html__( 'All (%d)', 'sudomock-product-customizer' ), $d['total_count'] ); ?>
            </a>
            <a href="<?php echo esc_url( add_query_arg( 'filter', 'mapped', $base_url ) ); ?>"
               class="sudomock-filter-tab <?php echo 'mapped' === $filter ? 'sudomock-filter-tab--active' : ''; ?>">
                <?php
                /* translators: %d: mapped product count */
                printf( esc_html__( 'Mapped (%d)', 'sudomock-product-customizer' ), $d['mapped_count'] ); ?>
            </a>
            <a href="<?php echo esc_url( add_query_arg( 'filter', 'unmapped', $base_url ) ); ?>"
               class="sudomock-filter-tab <?php echo 'unmapped' === $filter ? 'sudomock-filter-tab--active' : ''; ?>">
                <?php
                /* translators: %d: unmapped product count */
                printf( esc_html__( 'Unmapped (%d)', 'sudomock-product-customizer' ), $d['total_count'] - $d['mapped_count'] ); ?>
            </a>
        </div>

        <!-- Product Table -->
        <div class="sudomock-card" style="border-top-left-radius: 0; border-top-right-radius: 0;">
            <?php if ( empty( $products ) && empty( $search ) && 'all' === $filter ) : ?>
                <!-- Empty State -->
                <div class="sudomock-empty-state">
                    <div class="sudomock-empty-state__icon">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                            <line x1="12" y1="22.08" x2="12" y2="12"/>
                        </svg>
                    </div>
                    <h3 class="sudomock-empty-state__title"><?php esc_html_e( 'No products yet', 'sudomock-product-customizer' ); ?></h3>
                    <p class="sudomock-empty-state__desc"><?php esc_html_e( 'Add products in WooCommerce, then come back to map mockups to them.', 'sudomock-product-customizer' ); ?></p>
                    <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>" class="sudomock-btn sudomock-btn--primary">
                        <?php esc_html_e( 'Add Product', 'sudomock-product-customizer' ); ?>
                    </a>
                </div>
            <?php else : ?>
                <table class="sudomock-table">
                    <thead>
                        <tr>
                            <th style="width:50px;"></th>
                            <th><?php esc_html_e( 'Product', 'sudomock-product-customizer' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'sudomock-product-customizer' ); ?></th>
                            <th><?php esc_html_e( 'Mockup', 'sudomock-product-customizer' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'sudomock-product-customizer' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $products ) ) : ?>
                            <tr><td colspan="5" style="text-align:center;padding:40px;color:#616161;">
                                <?php
                                if ( $search ) {
                                    /* translators: %s: search query */
                                    printf( esc_html__( 'No products found for "%s"', 'sudomock-product-customizer' ), esc_html( $search ) );
                                } else {
                                    esc_html_e( 'No products in this filter.', 'sudomock-product-customizer' );
                                }
                                ?>
                            </td></tr>
                        <?php else : ?>
                            <?php foreach ( $products as $p ) :
                                $product    = wc_get_product( $p->ID );
                                if ( ! $product ) continue;
                                $thumb      = $product->get_image( array( 40, 40 ) );
                                $mockup_uuid = get_post_meta( $p->ID, '_sudomock_mockup_uuid', true );
                                $mockup_name = get_post_meta( $p->ID, '_sudomock_mockup_name', true );
                                $has_mockup  = ! empty( $mockup_uuid );
                                $status      = $product->get_status();
                            ?>
                            <tr>
                                <td><?php echo wp_kses_post( $thumb ); ?></td>
                                <td>
                                    <strong><?php echo esc_html( $product->get_name() ); ?></strong>
                                </td>
                                <td>
                                    <span class="sudomock-badge sudomock-badge--<?php echo 'publish' === $status ? 'success' : 'info'; ?>">
                                        <?php echo esc_html( 'publish' === $status ? __( 'Active', 'sudomock-product-customizer' ) : ucfirst( $status ) ); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ( $has_mockup ) : ?>
                                        <span class="sudomock-badge sudomock-badge--success"><?php esc_html_e( 'Mapped', 'sudomock-product-customizer' ); ?></span>
                                        <span class="sudomock-text--muted sudomock-text--sm" style="margin-left:4px;"><?php echo esc_html( ! empty( $mockup_name ) ? $mockup_name : substr( $mockup_uuid, 0, 8 ) . '...' ); ?></span>
                                    <?php else : ?>
                                        <span class="sudomock-badge" style="background:#f1f5f9;color:#64748b;"><?php esc_html_e( 'Not mapped', 'sudomock-product-customizer' ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="sudomock-btn sudomock-btn--sm <?php echo ! $has_mockup ? 'sudomock-btn--primary' : ''; ?>"
                                        data-action="map" data-product-id="<?php echo esc_attr( $p->ID ); ?>" data-product-name="<?php echo esc_attr( $product->get_name() ); ?>">
                                        <?php echo $has_mockup ? esc_html__( 'Change', 'sudomock-product-customizer' ) : esc_html__( 'Map Mockup', 'sudomock-product-customizer' ); ?>
                                    </button>
                                    <?php if ( $has_mockup ) : ?>
                                        <button type="button" class="sudomock-btn sudomock-btn--sm sudomock-btn--danger-text"
                                            data-action="unmap" data-product-id="<?php echo esc_attr( $p->ID ); ?>">
                                            <?php esc_html_e( 'Remove', 'sudomock-product-customizer' ); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php
                // Pagination
                $total_pages = $query->max_num_pages;
                if ( $total_pages > 1 ) :
                    $pag_base = add_query_arg( array(
                        'tab'    => 'products',
                        'filter' => $filter,
                    ), $base_url );
                    if ( ! empty( $search ) ) {
                        $pag_base = add_query_arg( 's', $search, $pag_base );
                    }
                ?>
                <div class="sudomock-products-pagination">
                    <div class="sudomock-products-pagination__buttons">
                        <?php if ( $paged > 1 ) : ?>
                            <a href="<?php echo esc_url( add_query_arg( 'paged', $paged - 1, $pag_base ) ); ?>" class="sudomock-btn sudomock-btn--sm">
                                &larr; <?php esc_html_e( 'Previous', 'sudomock-product-customizer' ); ?>
                            </a>
                        <?php endif; ?>
                        <span class="sudomock-text--muted sudomock-text--sm">
                            <?php
                            /* translators: 1: current page, 2: total pages, 3: total products */
                            printf(
                                esc_html__( 'Page %1$d of %2$d (%3$d products)', 'sudomock-product-customizer' ),
                                $paged,
                                $total_pages,
                                $total
                            );
                            ?>
                        </span>
                        <?php if ( $paged < $total_pages ) : ?>
                            <a href="<?php echo esc_url( add_query_arg( 'paged', $paged + 1, $pag_base ) ); ?>" class="sudomock-btn sudomock-btn--sm">
                                <?php esc_html_e( 'Next', 'sudomock-product-customizer' ); ?> &rarr;
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Mockup Picker Modal -->
        <div id="sudomock-mockup-modal" class="sudomock-modal" style="display:none;">
            <div class="sudomock-modal__overlay"></div>
            <div class="sudomock-modal__content">
                <div class="sudomock-modal__header">
                    <h2><?php esc_html_e( 'Select PSD Mockup', 'sudomock-product-customizer' ); ?></h2>
                    <button type="button" class="sudomock-modal__close">&times;</button>
                </div>
                <div class="sudomock-modal__product-info"></div>
                <div class="sudomock-modal__search">
                    <input type="text" id="sudomock-modal-search" class="sudomock-input" style="max-width:100%;"
                        placeholder="<?php esc_attr_e( 'Search mockups by name...', 'sudomock-product-customizer' ); ?>" />
                </div>
                <div id="sudomock-modal-grid" class="sudomock-mockup-grid"></div>
                <div class="sudomock-modal__footer">
                    <button type="button" class="sudomock-btn" id="sudomock-modal-cancel"><?php esc_html_e( 'Cancel', 'sudomock-product-customizer' ); ?></button>
                    <button type="button" class="sudomock-btn sudomock-btn--primary" id="sudomock-modal-assign" disabled><?php esc_html_e( 'Assign Mockup', 'sudomock-product-customizer' ); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    /* ------------------------------------------------------------------ */
    /* Mockups Tab                                                         */
    /* ------------------------------------------------------------------ */

    private function render_mockups_tab() {
        ?>
        <div class="sudomock-dashboard-grid">
            <div class="sudomock-dashboard-grid__main">
                <div class="sudomock-card">
                    <div class="sudomock-card__body" style="display:flex;justify-content:space-between;align-items:center;">
                        <div>
                            <h2 class="sudomock-card__title" style="margin-bottom:4px;"><?php esc_html_e( 'PSD Mockups', 'sudomock-product-customizer' ); ?></h2>
                            <p class="sudomock-text--muted"><?php esc_html_e( 'Browse and manage your uploaded PSD mockup files.', 'sudomock-product-customizer' ); ?></p>
                        </div>
                        <a href="https://sudomock.com/dashboard/playground" target="_blank" rel="noopener" class="sudomock-btn sudomock-btn--primary">
                            <?php esc_html_e( 'Upload PSD', 'sudomock-product-customizer' ); ?>
                        </a>
                    </div>
                </div>

                <div class="sudomock-card">
                    <div class="sudomock-card__body">
                        <input type="text" id="sudomock-mockups-search" class="sudomock-input" style="max-width:100%;margin-bottom:16px;"
                            placeholder="<?php esc_attr_e( 'Search mockups by name...', 'sudomock-product-customizer' ); ?>" />
                        <div id="sudomock-mockups-grid" class="sudomock-mockup-grid">
                            <div style="text-align:center;padding:40px;color:#616161;">
                                <?php esc_html_e( 'Loading mockups...', 'sudomock-product-customizer' ); ?>
                            </div>
                        </div>
                        <div id="sudomock-mockups-pagination" class="sudomock-pagination"></div>
                    </div>
                </div>
            </div>
            <div class="sudomock-dashboard-grid__side">
                <div class="sudomock-card">
                    <div class="sudomock-card__body">
                        <h3 class="sudomock-card__title"><?php esc_html_e( 'About Mockups', 'sudomock-product-customizer' ); ?></h3>
                        <p class="sudomock-text--muted" style="margin-bottom:12px;">
                            <?php esc_html_e( 'PSD mockups are uploaded via your SudoMock Dashboard. Each mockup\'s smart object layers become customizable areas where customers upload their artwork.', 'sudomock-product-customizer' ); ?>
                        </p>
                        <a href="https://sudomock.com/dashboard/playground" target="_blank" rel="noopener" class="sudomock-btn" style="width:100%;text-align:center;">
                            <?php esc_html_e( 'Open SudoMock Dashboard', 'sudomock-product-customizer' ); ?>
                        </a>
                    </div>
                </div>
                <div class="sudomock-card">
                    <div class="sudomock-card__body">
                        <h3 class="sudomock-card__title"><?php esc_html_e( 'Tips', 'sudomock-product-customizer' ); ?></h3>
                        <p class="sudomock-text--muted"><?php esc_html_e( 'Name your smart objects clearly (e.g. "Front Logo", "Back Design")', 'sudomock-product-customizer' ); ?></p>
                        <p class="sudomock-text--muted"><?php esc_html_e( 'Use high-resolution PSDs (3000px+) for best print quality', 'sudomock-product-customizer' ); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /* ------------------------------------------------------------------ */
    /* Settings Tab                                                        */
    /* ------------------------------------------------------------------ */

    private function render_settings_tab( $d ) {
        ?>
        <!-- Button Appearance - managed via WP Customizer -->
        <div class="sudomock-card">
            <div class="sudomock-card__body" style="display:flex;align-items:center;justify-content:space-between;">
                <div>
                    <h2 class="sudomock-card__title" style="margin-bottom:4px;"><?php esc_html_e( 'Button Appearance', 'sudomock-product-customizer' ); ?></h2>
                    <p class="sudomock-text--muted"><?php esc_html_e( 'Customize the storefront button colors, text, sizing, and placement in the WordPress Customizer.', 'sudomock-product-customizer' ); ?></p>
                </div>
                <a href="<?php echo esc_url( admin_url( 'customize.php?autofocus[section]=sudomock_button&url=' . rawurlencode( sudomock_get_customizer_preview_url() ) ) ); ?>" class="sudomock-btn sudomock-btn--primary">
                    <?php esc_html_e( 'Open Customizer', 'sudomock-product-customizer' ); ?>
                </a>
            </div>
        </div>

        <!-- Studio Config Editor (AJAX-loaded) -->
        <div id="sudomock-studio-config" class="sudomock-studio-config" style="display:none;">
            <!-- Loading state -->
            <div id="sudomock-config-loading" class="sudomock-card">
                <div class="sudomock-card__body" style="text-align:center;padding:40px;">
                    <p class="sudomock-text--muted"><?php esc_html_e( 'Loading studio config...', 'sudomock-product-customizer' ); ?></p>
                </div>
            </div>

            <!-- Config form (hidden until loaded) -->
            <div id="sudomock-config-form" style="display:none;">
                <!-- Banner area for save/error feedback -->
                <div id="sudomock-config-banner"></div>

                <!-- Branding Section -->
                <div class="sudomock-card">
                    <div class="sudomock-card__body">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                            <h2 class="sudomock-card__title" style="margin-bottom:0;"><?php esc_html_e( 'Studio Branding', 'sudomock-product-customizer' ); ?></h2>
                            <button type="button" id="sudomock-config-reset" class="sudomock-btn sudomock-btn--sm">
                                <?php esc_html_e( 'Reset defaults', 'sudomock-product-customizer' ); ?>
                            </button>
                        </div>
                        <p class="sudomock-text--muted" style="margin-bottom:16px;"><?php esc_html_e( 'Colors and branding for the customer-facing customizer. No SudoMock branding shown to customers.', 'sudomock-product-customizer' ); ?></p>
                        <hr class="sudomock-divider" />

                        <div class="sudomock-color-grid">
                            <?php
                            $colors = array(
                                'primaryColor'    => array( __( 'Primary color', 'sudomock-product-customizer' ), __( 'Buttons, active states', 'sudomock-product-customizer' ) ),
                                'accentColor'     => array( __( 'Accent color', 'sudomock-product-customizer' ), __( 'CTA button (Add to Cart)', 'sudomock-product-customizer' ) ),
                                'successColor'    => array( __( 'Success color', 'sudomock-product-customizer' ), __( 'Added to cart confirmation', 'sudomock-product-customizer' ) ),
                                'backgroundColor' => array( __( 'Background', 'sudomock-product-customizer' ), __( 'Canvas area', 'sudomock-product-customizer' ) ),
                                'panelBackground' => array( __( 'Panel background', 'sudomock-product-customizer' ), __( 'Side panels', 'sudomock-product-customizer' ) ),
                                'textColor'       => array( __( 'Text color', 'sudomock-product-customizer' ), __( 'Primary text', 'sudomock-product-customizer' ) ),
                                'borderColor'     => array( __( 'Border color', 'sudomock-product-customizer' ), __( 'Dividers', 'sudomock-product-customizer' ) ),
                            );
                            foreach ( $colors as $key => $meta ) : ?>
                                <div class="sudomock-color-field">
                                    <label class="sudomock-color-field__label"><?php echo esc_html( $meta[0] ); ?></label>
                                    <div class="sudomock-color-field__row">
                                        <input type="color" class="sudomock-color-field__picker" data-config-key="<?php echo esc_attr( $key ); ?>" />
                                        <input type="text" class="sudomock-color-field__hex" data-config-key="<?php echo esc_attr( $key ); ?>" maxlength="7" />
                                    </div>
                                    <p class="sudomock-text--muted sudomock-text--sm" style="margin-top:2px;"><?php echo esc_html( $meta[1] ); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <hr class="sudomock-divider" />

                        <div class="sudomock-form-row">
                            <label class="sudomock-form-row__label" for="sudomock-cfg-borderRadius">
                                <?php esc_html_e( 'Corner radius', 'sudomock-product-customizer' ); ?>
                            </label>
                            <div class="sudomock-range-row">
                                <input type="range" id="sudomock-cfg-borderRadius" data-config-key="borderRadius" min="0" max="20" step="2" class="sudomock-range" />
                                <span class="sudomock-range-value" data-config-key="borderRadius">10px</span>
                            </div>
                        </div>

                        <div class="sudomock-form-row">
                            <label class="sudomock-form-row__label" for="sudomock-cfg-logoUrl">
                                <?php esc_html_e( 'Logo URL', 'sudomock-product-customizer' ); ?>
                            </label>
                            <input type="text" id="sudomock-cfg-logoUrl" data-config-key="logoUrl" class="sudomock-input" placeholder="https://yourstore.com/logo.png" />
                            <p class="sudomock-text--muted sudomock-text--sm" style="margin-top:4px;">
                                <?php esc_html_e( 'Displayed in customizer header. Leave empty for no logo.', 'sudomock-product-customizer' ); ?>
                            </p>
                        </div>

                        <div class="sudomock-form-row">
                            <label class="sudomock-form-row__label" for="sudomock-cfg-theme">
                                <?php esc_html_e( 'Theme', 'sudomock-product-customizer' ); ?>
                            </label>
                            <select id="sudomock-cfg-theme" data-config-key="theme" class="sudomock-select">
                                <option value="light"><?php esc_html_e( 'Light', 'sudomock-product-customizer' ); ?></option>
                                <option value="dark"><?php esc_html_e( 'Dark', 'sudomock-product-customizer' ); ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Labels Section -->
                <div class="sudomock-card">
                    <div class="sudomock-card__body">
                        <h2 class="sudomock-card__title"><?php esc_html_e( 'Labels', 'sudomock-product-customizer' ); ?></h2>
                        <p class="sudomock-text--muted" style="margin-bottom:16px;"><?php esc_html_e( 'Customize all text shown to customers. Great for localization or brand voice.', 'sudomock-product-customizer' ); ?></p>
                        <hr class="sudomock-divider" />
                        <div class="sudomock-labels-grid">
                            <?php
                            $labels = array(
                                'headerText'       => array( __( 'Header text', 'sudomock-product-customizer' ), 'Customize Your Design' ),
                                'buttonText'       => array( __( 'CTA button', 'sudomock-product-customizer' ), 'Add to Cart' ),
                                'addingText'       => array( __( 'Adding state text', 'sudomock-product-customizer' ), 'Adding...' ),
                                'successText'      => array( __( 'Success text', 'sudomock-product-customizer' ), 'Added!' ),
                                'renderButtonText' => array( __( 'Render button', 'sudomock-product-customizer' ), 'Render Preview' ),
                                'uploadText'       => array( __( 'Upload text', 'sudomock-product-customizer' ), 'Drop image or click to upload' ),
                            );
                            foreach ( $labels as $key => $meta ) : ?>
                                <div class="sudomock-form-row" style="margin-bottom:0;">
                                    <label class="sudomock-form-row__label" for="sudomock-cfg-<?php echo esc_attr( $key ); ?>">
                                        <?php echo esc_html( $meta[0] ); ?>
                                    </label>
                                    <input type="text" id="sudomock-cfg-<?php echo esc_attr( $key ); ?>"
                                        data-config-key="<?php echo esc_attr( $key ); ?>"
                                        class="sudomock-input" style="max-width:100%;"
                                        placeholder="<?php echo esc_attr( $meta[1] ); ?>" />
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Feature Toggles Section -->
                <div class="sudomock-card">
                    <div class="sudomock-card__body">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                            <h2 class="sudomock-card__title" style="margin-bottom:0;"><?php esc_html_e( 'Feature Controls', 'sudomock-product-customizer' ); ?></h2>
                            <span id="sudomock-toggle-count" class="sudomock-badge sudomock-badge--info">0 / 9 active</span>
                        </div>
                        <p class="sudomock-text--muted" style="margin-bottom:16px;"><?php esc_html_e( 'Hide features to simplify the experience for your customers.', 'sudomock-product-customizer' ); ?></p>
                        <hr class="sudomock-divider" />
                        <div class="sudomock-toggle-grid">
                            <?php
                            $toggles = array(
                                'showAdjustments'   => array( __( 'Adjustments Panel', 'sudomock-product-customizer' ), __( 'Brightness, contrast, saturation, vibrance, opacity, blur', 'sudomock-product-customizer' ) ),
                                'showColorOverlay'  => array( __( 'Color Overlay', 'sudomock-product-customizer' ), __( 'Fill color with blend mode selector per layer', 'sudomock-product-customizer' ) ),
                                'showFitMode'       => array( __( 'Fit Mode', 'sudomock-product-customizer' ), __( 'Fill / Fit / Cover selector for uploaded images', 'sudomock-product-customizer' ) ),
                                'showPosition'      => array( __( 'Position Controls', 'sudomock-product-customizer' ), __( 'X, Y position fields for precise placement', 'sudomock-product-customizer' ) ),
                                'showSize'          => array( __( 'Size Controls', 'sudomock-product-customizer' ), __( 'Width, height fields with aspect ratio lock', 'sudomock-product-customizer' ) ),
                                'showRotation'      => array( __( 'Rotation', 'sudomock-product-customizer' ), __( 'Rotation slider for uploaded images', 'sudomock-product-customizer' ) ),
                                'showExportOptions' => array( __( 'Export Options', 'sudomock-product-customizer' ), __( 'Output format, size, and quality controls', 'sudomock-product-customizer' ) ),
                                'showZoomControls'  => array( __( 'Zoom Controls', 'sudomock-product-customizer' ), __( 'Zoom in/out and percentage on canvas toolbar', 'sudomock-product-customizer' ) ),
                                'showUndoRedo'      => array( __( 'Undo / Redo', 'sudomock-product-customizer' ), __( 'Undo and redo buttons with keyboard shortcuts', 'sudomock-product-customizer' ) ),
                            );
                            foreach ( $toggles as $key => $meta ) : ?>
                                <label class="sudomock-toggle-item">
                                    <input type="checkbox" data-config-key="<?php echo esc_attr( $key ); ?>" class="sudomock-toggle-checkbox" />
                                    <div>
                                        <span class="sudomock-toggle-item__label"><?php echo esc_html( $meta[0] ); ?></span>
                                        <span class="sudomock-toggle-item__desc"><?php echo esc_html( $meta[1] ); ?></span>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Behavior Section -->
                <div class="sudomock-card">
                    <div class="sudomock-card__body">
                        <h2 class="sudomock-card__title"><?php esc_html_e( 'Behavior', 'sudomock-product-customizer' ); ?></h2>
                        <hr class="sudomock-divider" />

                        <div class="sudomock-form-row">
                            <label class="sudomock-form-row__label" for="sudomock-cfg-displayMode">
                                <?php esc_html_e( 'Display mode', 'sudomock-product-customizer' ); ?>
                            </label>
                            <select id="sudomock-cfg-displayMode" data-config-key="displayMode" class="sudomock-select">
                                <option value="iframe"><?php esc_html_e( 'Embedded (in-page modal)', 'sudomock-product-customizer' ); ?></option>
                                <option value="popup"><?php esc_html_e( 'New window', 'sudomock-product-customizer' ); ?></option>
                            </select>
                            <p class="sudomock-text--muted sudomock-text--sm" style="margin-top:4px;">
                                <?php esc_html_e( 'Embedded opens the customizer as an overlay on the product page. New window opens it in a separate browser window.', 'sudomock-product-customizer' ); ?>
                            </p>
                        </div>

                        <div class="sudomock-form-row">
                            <label class="sudomock-form-row__label" for="sudomock-cfg-layout">
                                <?php esc_html_e( 'Layout mode', 'sudomock-product-customizer' ); ?>
                            </label>
                            <select id="sudomock-cfg-layout" data-config-key="layout" class="sudomock-select">
                                <option value="full"><?php esc_html_e( 'Full (3-panel desktop)', 'sudomock-product-customizer' ); ?></option>
                                <option value="compact"><?php esc_html_e( 'Compact (mobile-first)', 'sudomock-product-customizer' ); ?></option>
                            </select>
                            <p class="sudomock-text--muted sudomock-text--sm" style="margin-top:4px;">
                                <?php esc_html_e( 'Full shows all panels side by side. Compact stacks for smaller screens.', 'sudomock-product-customizer' ); ?>
                            </p>
                        </div>

                        <div class="sudomock-form-row">
                            <label class="sudomock-toggle-item" style="margin-bottom:0;">
                                <input type="checkbox" id="sudomock-cfg-autoRender" data-config-key="autoRender" class="sudomock-toggle-checkbox" />
                                <div>
                                    <span class="sudomock-toggle-item__label"><?php esc_html_e( 'Auto-render on changes', 'sudomock-product-customizer' ); ?></span>
                                    <span class="sudomock-toggle-item__desc"><?php esc_html_e( 'Automatically render preview when customer makes changes. Costs 1 credit per render.', 'sudomock-product-customizer' ); ?></span>
                                </div>
                            </label>
                        </div>

                        <div class="sudomock-form-row" id="sudomock-autorender-delay-row" style="display:none;">
                            <label class="sudomock-form-row__label" for="sudomock-cfg-autoRenderDelay">
                                <?php esc_html_e( 'Auto-render delay', 'sudomock-product-customizer' ); ?>
                            </label>
                            <div class="sudomock-range-row">
                                <input type="range" id="sudomock-cfg-autoRenderDelay" data-config-key="autoRenderDelay" min="300" max="3000" step="100" class="sudomock-range" />
                                <span class="sudomock-range-value" data-config-key="autoRenderDelay">800ms</span>
                            </div>
                            <p class="sudomock-text--muted sudomock-text--sm" style="margin-top:4px;">
                                <?php esc_html_e( 'Wait time after last change before auto-rendering.', 'sudomock-product-customizer' ); ?>
                            </p>
                        </div>

                        <div class="sudomock-form-row">
                            <label class="sudomock-form-row__label" for="sudomock-cfg-maxFileSize">
                                <?php esc_html_e( 'Max upload size', 'sudomock-product-customizer' ); ?>
                            </label>
                            <div class="sudomock-range-row">
                                <input type="range" id="sudomock-cfg-maxFileSize" data-config-key="maxFileSize" min="1" max="50" step="1" class="sudomock-range" />
                                <span class="sudomock-range-value" data-config-key="maxFileSize">15 MB</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <div style="display:flex;align-items:center;gap:12px;margin-top:8px;">
                    <button type="button" id="sudomock-config-save" class="sudomock-btn sudomock-btn--primary sudomock-btn--lg">
                        <?php esc_html_e( 'Save Studio Config', 'sudomock-product-customizer' ); ?>
                    </button>
                    <button type="button" id="sudomock-config-discard" class="sudomock-btn sudomock-btn--lg" style="display:none;">
                        <?php esc_html_e( 'Discard Changes', 'sudomock-product-customizer' ); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Need Help? Support Form -->
        <div class="sudomock-card" style="margin-top:16px;">
            <div class="sudomock-card__body">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0f172a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <h2 class="sudomock-card__title" style="margin-bottom:0;"><?php esc_html_e( 'Need Help?', 'sudomock-product-customizer' ); ?></h2>
                </div>
                <p class="sudomock-text--muted" style="margin-bottom:16px;">
                    <?php esc_html_e( 'Send us a message and we will get back to you as soon as possible.', 'sudomock-product-customizer' ); ?>
                </p>
                <div id="sudomock-support-banner"></div>
                <div class="sudomock-form-row">
                    <label class="sudomock-form-row__label" for="sudomock-support-subject">
                        <?php esc_html_e( 'Subject', 'sudomock-product-customizer' ); ?>
                    </label>
                    <input type="text" id="sudomock-support-subject" class="sudomock-input" style="max-width:100%;"
                        placeholder="<?php esc_attr_e( 'e.g. Issue with mockup rendering', 'sudomock-product-customizer' ); ?>" />
                </div>
                <div class="sudomock-form-row">
                    <label class="sudomock-form-row__label" for="sudomock-support-message">
                        <?php esc_html_e( 'Message', 'sudomock-product-customizer' ); ?>
                    </label>
                    <textarea id="sudomock-support-message" class="sudomock-input" rows="5" style="max-width:100%;resize:vertical;"
                        placeholder="<?php esc_attr_e( 'Describe your issue or question in detail...', 'sudomock-product-customizer' ); ?>"></textarea>
                </div>
                <button type="button" id="sudomock-support-submit" class="sudomock-btn sudomock-btn--primary">
                    <?php esc_html_e( 'Submit', 'sudomock-product-customizer' ); ?>
                </button>
            </div>
        </div>
        <?php
    }


    /* ------------------------------------------------------------------ */
    /* AJAX: Map / Unmap product                                           */
    /* ------------------------------------------------------------------ */

    public function ajax_map_product() {
        check_ajax_referer( 'sudomock_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'sudomock-product-customizer' ) ), 403 );
        }

        $product_id  = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $mockup_uuid = isset( $_POST['mockup_uuid'] ) ? sanitize_text_field( wp_unslash( $_POST['mockup_uuid'] ) ) : '';
        $mockup_name = isset( $_POST['mockup_name'] ) ? sanitize_text_field( wp_unslash( $_POST['mockup_name'] ) ) : '';

        if ( ! $product_id || empty( $mockup_uuid ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing product ID or mockup UUID.', 'sudomock-product-customizer' ) ) );
        }

        update_post_meta( $product_id, '_sudomock_mockup_uuid', $mockup_uuid );
        update_post_meta( $product_id, '_sudomock_mockup_name', $mockup_name );
        update_post_meta( $product_id, '_sudomock_customization_enabled', 'yes' );

        wp_send_json_success( array( 'message' => __( 'Mockup mapped successfully.', 'sudomock-product-customizer' ) ) );
    }

    public function ajax_unmap_product() {
        check_ajax_referer( 'sudomock_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'sudomock-product-customizer' ) ), 403 );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        if ( ! $product_id ) {
            wp_send_json_error( array( 'message' => __( 'Missing product ID.', 'sudomock-product-customizer' ) ) );
        }

        delete_post_meta( $product_id, '_sudomock_mockup_uuid' );
        delete_post_meta( $product_id, '_sudomock_mockup_name' );
        delete_post_meta( $product_id, '_sudomock_customization_enabled' );

        wp_send_json_success( array( 'message' => __( 'Mockup removed.', 'sudomock-product-customizer' ) ) );
    }

    /* ------------------------------------------------------------------ */
    /* Studio Config AJAX                                                   */
    /* ------------------------------------------------------------------ */

    public function ajax_get_studio_config() {
        check_ajax_referer( 'sudomock_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'sudomock-product-customizer' ) ), 403 );
        }

        $result = SudoMock_API_Client::get_studio_config();
        if ( ! $result['ok'] ) {
            wp_send_json_error( array( 'message' => isset( $result['error'] ) ? $result['error'] : __( 'Failed to load config.', 'sudomock-product-customizer' ) ) );
        }

        wp_send_json_success( $result['data'] );
    }

    public function ajax_save_studio_config() {
        check_ajax_referer( 'sudomock_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'sudomock-product-customizer' ) ), 403 );
        }

        $raw = isset( $_POST['config'] ) ? wp_unslash( $_POST['config'] ) : '';
        $parsed = json_decode( $raw, true );
        if ( ! is_array( $parsed ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid config data.', 'sudomock-product-customizer' ) ) );
        }

        // Whitelist allowed keys
        $allowed = array(
            'primaryColor', 'accentColor', 'successColor', 'backgroundColor', 'panelBackground',
            'textColor', 'borderColor', 'borderRadius', 'logoUrl', 'fontFamily',
            'buttonText', 'renderButtonText', 'uploadText', 'headerText',
            'addingText', 'successText',
            'showAdjustments', 'showColorOverlay', 'showFitMode', 'showPosition',
            'showSize', 'showRotation', 'showExportOptions', 'showZoomControls', 'showUndoRedo',
            'theme', 'layout', 'displayMode', 'autoRender', 'autoRenderDelay', 'maxFileSize',
        );
        $config = array();
        foreach ( $allowed as $key ) {
            if ( array_key_exists( $key, $parsed ) ) {
                $config[ $key ] = $parsed[ $key ];
            }
        }

        // Sanitize string fields
        $string_keys = array(
            'primaryColor', 'accentColor', 'successColor', 'backgroundColor', 'panelBackground',
            'textColor', 'borderColor', 'logoUrl', 'fontFamily',
            'buttonText', 'renderButtonText', 'uploadText', 'headerText',
            'addingText', 'successText', 'theme', 'layout', 'displayMode',
        );
        foreach ( $string_keys as $key ) {
            if ( isset( $config[ $key ] ) ) {
                $config[ $key ] = sanitize_text_field( $config[ $key ] );
            }
        }

        // Sanitize numeric fields
        if ( isset( $config['borderRadius'] ) ) {
            $config['borderRadius'] = max( 0, min( 20, absint( $config['borderRadius'] ) ) );
        }
        if ( isset( $config['autoRenderDelay'] ) ) {
            $config['autoRenderDelay'] = max( 300, min( 3000, absint( $config['autoRenderDelay'] ) ) );
        }
        if ( isset( $config['maxFileSize'] ) ) {
            $config['maxFileSize'] = max( 1, min( 50, absint( $config['maxFileSize'] ) ) );
        }

        // Sanitize boolean fields
        $bool_keys = array(
            'showAdjustments', 'showColorOverlay', 'showFitMode', 'showPosition',
            'showSize', 'showRotation', 'showExportOptions', 'showZoomControls', 'showUndoRedo',
            'autoRender',
        );
        foreach ( $bool_keys as $key ) {
            if ( isset( $config[ $key ] ) ) {
                $config[ $key ] = (bool) $config[ $key ];
            }
        }

        // Validate logo URL scheme
        if ( ! empty( $config['logoUrl'] ) && 0 !== strpos( $config['logoUrl'], 'https://' ) ) {
            wp_send_json_error( array( 'message' => __( 'Logo URL must use HTTPS.', 'sudomock-product-customizer' ) ) );
        }

        $result = SudoMock_API_Client::update_studio_config( $config );
        if ( ! $result['ok'] ) {
            wp_send_json_error( array( 'message' => isset( $result['error'] ) ? $result['error'] : __( 'Failed to save config.', 'sudomock-product-customizer' ) ) );
        }

        wp_send_json_success( array(
            'message'        => __( 'Studio config saved.', 'sudomock-product-customizer' ),
            'config_version' => isset( $result['data']['config_version'] ) ? $result['data']['config_version'] : 0,
        ) );
    }

    /* ------------------------------------------------------------------ */
    /* AJAX: Submit Support Ticket                                         */
    /* ------------------------------------------------------------------ */

    public function ajax_submit_support() {
        check_ajax_referer( 'sudomock_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'sudomock-product-customizer' ) ), 403 );
        }

        // Rate limit: max 3 messages per hour via transient.
        $transient_key = 'sudomock_support_count_' . get_current_user_id();
        $count         = (int) get_transient( $transient_key );
        if ( $count >= 3 ) {
            wp_send_json_error( array( 'message' => __( 'You have reached the limit of 3 messages per hour. Please wait before sending another message.', 'sudomock-product-customizer' ) ) );
        }

        $subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
        $message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

        if ( empty( $subject ) ) {
            wp_send_json_error( array( 'message' => __( 'Please enter a subject.', 'sudomock-product-customizer' ) ) );
        }
        if ( empty( $message ) ) {
            wp_send_json_error( array( 'message' => __( 'Please enter a message.', 'sudomock-product-customizer' ) ) );
        }

        $sent = false;

        // Try API endpoint first.
        $api_key = SudoMock_API_Client::get_api_key();
        if ( $api_key ) {
            $url  = SUDOMOCK_API_BASE . '/api/v1/support/ticket';
            $args = array(
                'method'  => 'POST',
                'timeout' => 15,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'x-api-key'   => $api_key,
                    'User-Agent'  => 'SudoMock-WooCommerce/' . SUDOMOCK_VERSION,
                ),
                'body'    => wp_json_encode( array(
                    'subject' => $subject,
                    'message' => $message,
                    'site'    => site_url(),
                    'email'   => get_option( 'sudomock_account_email', '' ),
                ) ),
            );

            $response = wp_remote_request( $url, $args );
            if ( ! is_wp_error( $response ) ) {
                $code = wp_remote_retrieve_response_code( $response );
                if ( $code >= 200 && $code < 300 ) {
                    $sent = true;
                }
            }
        }

        // Fallback: wp_mail if API not available or failed.
        if ( ! $sent ) {
            $email   = get_option( 'sudomock_account_email', get_option( 'admin_email' ) );
            $headers = array( 'Reply-To: ' . $email );
            $body    = sprintf(
                "Site: %s\nEmail: %s\n\n%s",
                site_url(),
                $email,
                $message
            );
            $sent = wp_mail( 'support@sudomock.com', '[WooCommerce Support] ' . $subject, $body, $headers );
        }

        if ( ! $sent ) {
            wp_send_json_error( array( 'message' => __( 'Failed to send message. Please try again later.', 'sudomock-product-customizer' ) ) );
        }

        // Increment rate limit counter.
        set_transient( $transient_key, $count + 1, HOUR_IN_SECONDS );

        wp_send_json_success( array(
            'message' => __( 'Your message has been sent. We will get back to you soon.', 'sudomock-product-customizer' ),
        ) );
    }

    /* ------------------------------------------------------------------ */
    /* AJAX: Dismiss Onboarding                                            */
    /* ------------------------------------------------------------------ */

    public function ajax_dismiss_onboarding() {
        check_ajax_referer( 'sudomock_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'sudomock-product-customizer' ) ), 403 );
        }

        update_option( 'sudomock_onboarding_dismissed', true );

        wp_send_json_success( array(
            'message' => __( 'Onboarding dismissed.', 'sudomock-product-customizer' ),
        ) );
    }

    /* ------------------------------------------------------------------ */
    /* AJAX: Generate Product Gallery Image                                */
    /* ------------------------------------------------------------------ */

    public function ajax_generate_gallery() {
        check_ajax_referer( 'sudomock_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'sudomock-product-customizer' ) ), 403 );
        }

        $product_id  = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $mockup_uuid = isset( $_POST['mockup_uuid'] ) ? sanitize_text_field( wp_unslash( $_POST['mockup_uuid'] ) ) : '';

        if ( ! $product_id || empty( $mockup_uuid ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing product ID or mockup UUID.', 'sudomock-product-customizer' ) ) );
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            wp_send_json_error( array( 'message' => __( 'Product not found.', 'sudomock-product-customizer' ) ) );
        }

        $api_key = SudoMock_API_Client::get_api_key();
        if ( empty( $api_key ) ) {
            wp_send_json_error( array( 'message' => __( 'API key not configured.', 'sudomock-product-customizer' ) ) );
        }

        // Request render from the backend (empty smart objects = default appearance).
        $url  = SUDOMOCK_API_BASE . '/api/v1/renders';
        $args = array(
            'method'  => 'POST',
            'timeout' => 60, // Renders can take time.
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key'   => $api_key,
                'User-Agent'  => 'SudoMock-WooCommerce/' . SUDOMOCK_VERSION,
            ),
            'body'    => wp_json_encode( array(
                'mockup_uuid'   => $mockup_uuid,
                'smart_objects' => array(), // Empty = default/preview render.
                'format'        => 'png',
            ) ),
        );

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => $response->get_error_message() ) );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 400 ) {
            $error = isset( $body['detail'] ) ? $body['detail'] : sprintf(
                /* translators: %d: HTTP status code */
                __( 'Render API error (HTTP %d)', 'sudomock-product-customizer' ),
                $code
            );
            wp_send_json_error( array( 'message' => $error ) );
        }

        // Extract render URL from response.
        $render_url = '';
        if ( ! empty( $body['data']['render_url'] ) ) {
            $render_url = $body['data']['render_url'];
        } elseif ( ! empty( $body['render_url'] ) ) {
            $render_url = $body['render_url'];
        } elseif ( ! empty( $body['data']['url'] ) ) {
            $render_url = $body['data']['url'];
        } elseif ( ! empty( $body['url'] ) ) {
            $render_url = $body['url'];
        }

        if ( empty( $render_url ) ) {
            wp_send_json_error( array( 'message' => __( 'No render URL returned from the API.', 'sudomock-product-customizer' ) ) );
        }

        // Download image and attach to Media Library.
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url( $render_url, 60 );
        if ( is_wp_error( $tmp ) ) {
            wp_send_json_error( array( 'message' => $tmp->get_error_message() ) );
        }

        $product_name = sanitize_file_name( $product->get_name() );
        $file_array   = array(
            'name'     => $product_name . '-sudomock-render.png',
            'tmp_name' => $tmp,
        );

        $attachment_id = media_handle_sideload( $file_array, $product_id );

        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
        }

        // Set as product featured image.
        set_post_thumbnail( $product_id, $attachment_id );

        wp_send_json_success( array(
            'message'       => __( 'Product image generated and set as featured image.', 'sudomock-product-customizer' ),
            'attachment_id' => $attachment_id,
            'image_url'     => wp_get_attachment_url( $attachment_id ),
        ) );
    }
}
