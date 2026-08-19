<?php

namespace AgoLab\Harden;

defined( 'ABSPATH' ) || exit;

class Plugin {

    private static ?self $instance = null;

    /** Toggle keys mapped to module classes. */
    private const MODULES = [
        'custom_login_url'        => Modules\LoginUrl::class,
        'limit_login_attempts'    => Modules\LoginProtect::class,
        'disable_file_edit'       => Modules\FileEdit::class,
        'hide_wp_version'         => Modules\HideVersion::class,
        'block_author_enum'       => Modules\AuthorEnum::class,
        'security_headers'        => Modules\Headers::class,
        'disable_xmlrpc'          => Modules\XmlRpc::class,
        'block_php_uploads'       => Modules\PhpUploads::class,
        'disable_directory_listing' => Modules\DirectoryListing::class,
        'force_logout_hours'      => Modules\ForceLogout::class,
        'hide_login_errors'       => Modules\LoginErrors::class,
    ];

    /** Default settings. */
    public const DEFAULTS = [
        'custom_login_url'          => '',
        'limit_login_attempts'      => false,
        'disable_file_edit'         => false,
        'hide_wp_version'           => false,
        'block_author_enum'         => false,
        'security_headers'          => false,
        'disable_xmlrpc'            => false,
        'block_php_uploads'         => false,
        'disable_directory_listing' => false,
        'force_logout_hours'        => 0,
        'hide_login_errors'         => false,
    ];

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // Init active modules.
        $this->init_modules();
    }

    public static function activate(): void {
        self::sync_htaccess();
    }

    public static function deactivate(): void {
        Modules\PhpUploads::remove_htaccess();
        Modules\DirectoryListing::remove_htaccess();
    }

    /**
     * Bring the .htaccess rules in line with the saved toggles.
     *
     * The rules are derived state: the toggles are the source of truth. This
     * runs on activation, on save and when the settings page is opened, so a
     * rule that was removed by hand or lost in a migration comes back instead
     * of leaving a toggle showing a protection that is not in place. Both
     * writers check for their own marker first, so repeated calls do no I/O.
     */
    public static function sync_htaccess(): void {
        $settings = self::get_settings();

        if ( ! empty( $settings['block_php_uploads'] ) ) {
            Modules\PhpUploads::write_htaccess();
        } else {
            Modules\PhpUploads::remove_htaccess();
        }

        if ( ! empty( $settings['disable_directory_listing'] ) ) {
            Modules\DirectoryListing::write_htaccess();
        } else {
            Modules\DirectoryListing::remove_htaccess();
        }
    }

    /* ───── Admin menu (smart pattern) ───── */

    public function register_admin_menu(): void {
        if ( empty( $GLOBALS['admin_page_hooks']['agolab-tools'] ) ) {
            add_menu_page(
                __( 'aGo Tools', 'ago-harden' ),
                __( 'aGo Tools', 'ago-harden' ),
                'manage_options',
                'agolab-tools',
                '__return_null',
                'dashicons-hammer',
                81
            );
        }

        add_submenu_page(
            'agolab-tools',
            __( 'aGo Harden', 'ago-harden' ),
            __( 'Harden', 'ago-harden' ),
            'manage_options',
            'ago-harden',
            [ Admin\Page::class, 'render' ]
        );

        remove_submenu_page( 'agolab-tools', 'agolab-tools' );
    }

    /* ───── REST routes ───── */

    public function register_rest_routes(): void {
        register_rest_route( 'ago-harden/v1', '/settings', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_get_settings' ],
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_save_settings' ],
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            ],
        ] );

        register_rest_route( 'ago-harden/v1', '/score', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_get_score' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ] );
    }

    public function handle_get_settings(): \WP_REST_Response {
        $settings = self::get_settings();
        $settings['security_score'] = Score::calculate( $settings );
        return new \WP_REST_Response( $settings );
    }

    public function handle_save_settings( \WP_REST_Request $request ): \WP_REST_Response {
        $input    = $request->get_json_params();
        $settings = [];

        foreach ( self::DEFAULTS as $key => $default ) {
            if ( $key === 'custom_login_url' ) {
                $settings[ $key ] = sanitize_title( $input[ $key ] ?? '' );
            } elseif ( $key === 'force_logout_hours' ) {
                $settings[ $key ] = absint( $input[ $key ] ?? 0 );
            } else {
                $settings[ $key ] = ! empty( $input[ $key ] );
            }
        }

        update_option( 'agoharden_settings', $settings );
        self::sync_htaccess();

        $settings['security_score'] = Score::calculate( $settings );

        return new \WP_REST_Response( [ 'saved' => true, 'settings' => $settings ] );
    }

    public function handle_get_score(): \WP_REST_Response {
        return new \WP_REST_Response( [
            'score' => Score::calculate( self::get_settings() ),
        ] );
    }

    /* ───── Assets ───── */

    public function enqueue_assets( string $hook ): void {
        if ( ! str_ends_with( $hook, '_page_ago-harden' ) ) {
            return;
        }

        wp_enqueue_style(
            'agoharden-admin',
            AGOHARDEN_URL . 'assets/css/admin.css',
            [],
            AGOHARDEN_VERSION
        );

        wp_enqueue_script(
            'agoharden-admin',
            AGOHARDEN_URL . 'assets/js/admin.js',
            [],
            AGOHARDEN_VERSION,
            true
        );

        wp_localize_script( 'agoharden-admin', 'agohardenData', [
            'restUrl'  => rest_url( 'ago-harden/v1' ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'settings' => array_merge( self::get_settings(), [
                'security_score' => Score::calculate( self::get_settings() ),
            ] ),
            'i18n'     => [
                'excellent' => __( 'Excellent', 'ago-harden' ),
                'good'      => __( 'Good', 'ago-harden' ),
                'fair'      => __( 'Fair', 'ago-harden' ),
                'weak'      => __( 'Weak', 'ago-harden' ),
                'saving'    => __( 'Saving...', 'ago-harden' ),
                'saved'     => __( 'Saved!', 'ago-harden' ),
                'error'     => __( 'Error saving.', 'ago-harden' ),
                'save'      => __( 'Save Settings', 'ago-harden' ),
            ],
        ] );
    }

    /* ───── Modules ───── */

    private function init_modules(): void {
        $settings = self::get_settings();

        foreach ( self::MODULES as $key => $class ) {
            if ( $key === 'custom_login_url' ) {
                if ( ! empty( $settings[ $key ] ) ) {
                    $class::init( $settings[ $key ] );
                }
            } elseif ( $key === 'force_logout_hours' ) {
                if ( ! empty( $settings[ $key ] ) ) {
                    $class::init( (int) $settings[ $key ] );
                }
            } else {
                if ( ! empty( $settings[ $key ] ) ) {
                    $class::init();
                }
            }
        }
    }

    /** @return array<string, mixed> */
    public static function get_settings(): array {
        $saved    = get_option( 'agoharden_settings', [] );
        $settings = [];

        foreach ( self::DEFAULTS as $key => $default ) {
            if ( isset( $saved[ $key ] ) ) {
                $settings[ $key ] = $saved[ $key ];
            } else {
                $settings[ $key ] = $default;
            }
        }

        return $settings;
    }
}
