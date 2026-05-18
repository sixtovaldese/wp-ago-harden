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
        add_action( 'init', [ $this, 'load_textdomain' ] );
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        register_activation_hook( AGO_HARDEN_FILE, [ $this, 'activate' ] );
        register_deactivation_hook( AGO_HARDEN_FILE, [ $this, 'deactivate' ] );

        // Init active modules.
        $this->init_modules();
    }

    /* ───── Textdomain ───── */

    public function load_textdomain(): void {
        load_plugin_textdomain( 'ago-harden', false, dirname( plugin_basename( AGO_HARDEN_FILE ) ) . '/languages' );
    }

    public function activate(): void {
        $settings = self::get_settings();

        // Write .htaccess rules if toggles are already active.
        if ( ! empty( $settings['block_php_uploads'] ) ) {
            Modules\PhpUploads::write_htaccess();
        }
        if ( ! empty( $settings['disable_directory_listing'] ) ) {
            Modules\DirectoryListing::write_htaccess();
        }
    }

    public function deactivate(): void {
        Modules\PhpUploads::remove_htaccess();
        Modules\DirectoryListing::remove_htaccess();
    }

    /* ───── Admin menu (smart pattern) ───── */

    public function register_admin_menu(): void {
        if ( empty( $GLOBALS['admin_page_hooks']['ago-tools'] ) ) {
            add_menu_page(
                __( 'aGo Tools', 'ago-harden' ),
                __( 'aGo Tools', 'ago-harden' ),
                'manage_options',
                'ago-tools',
                '__return_null',
                'dashicons-hammer',
                81
            );
        }

        add_submenu_page(
            'ago-tools',
            __( 'aGo Harden', 'ago-harden' ),
            __( 'Harden', 'ago-harden' ),
            'manage_options',
            'ago-harden',
            [ Admin\Page::class, 'render' ]
        );

        remove_submenu_page( 'ago-tools', 'ago-tools' );
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

        $old_settings = self::get_settings();
        update_option( 'ago_harden_settings', $settings );

        // Handle .htaccess writes.
        if ( ! empty( $settings['block_php_uploads'] ) ) {
            Modules\PhpUploads::write_htaccess();
        } elseif ( ! empty( $old_settings['block_php_uploads'] ) ) {
            Modules\PhpUploads::remove_htaccess();
        }

        if ( ! empty( $settings['disable_directory_listing'] ) ) {
            Modules\DirectoryListing::write_htaccess();
        } elseif ( ! empty( $old_settings['disable_directory_listing'] ) ) {
            Modules\DirectoryListing::remove_htaccess();
        }

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
            'ago-harden-admin',
            AGO_HARDEN_URL . 'assets/css/admin.css',
            [],
            AGO_HARDEN_VERSION
        );

        wp_enqueue_script(
            'ago-harden-admin',
            AGO_HARDEN_URL . 'assets/js/admin.js',
            [],
            AGO_HARDEN_VERSION,
            true
        );

        wp_localize_script( 'ago-harden-admin', 'agoHarden', [
            'restUrl'  => rest_url( 'ago-harden/v1' ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'settings' => array_merge( self::get_settings(), [
                'security_score' => Score::calculate( self::get_settings() ),
            ] ),
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
        $saved    = get_option( 'ago_harden_settings', [] );
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
