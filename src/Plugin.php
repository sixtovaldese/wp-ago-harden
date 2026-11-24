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
     * rule that was removed or emptied by hand, or lost in a migration, comes
     * back instead of leaving a toggle showing a protection that is not in
     * place. insert_with_markers() leaves the file untouched when the block is
     * already right, so repeated calls cost a read, not a write.
     *
     * @return string[] Toggles that are on but whose rule could not be written.
     */
    public static function sync_htaccess(): array {
        $settings = self::get_settings();
        $failed   = [];

        if ( ! empty( $settings['block_php_uploads'] ) ) {
            if ( ! Modules\PhpUploads::write_htaccess() ) {
                $failed[] = 'block_php_uploads';
            }
        } else {
            Modules\PhpUploads::remove_htaccess();
        }

        if ( ! empty( $settings['disable_directory_listing'] ) ) {
            // Without a root .htaccess there is nothing to write to; the page
            // already says so, and that is not a failed write.
            if ( file_exists( Modules\DirectoryListing::file() ) && ! Modules\DirectoryListing::write_htaccess() ) {
                $failed[] = 'disable_directory_listing';
            }
        } else {
            Modules\DirectoryListing::remove_htaccess();
        }

        return $failed;
    }

    /* ───── Admin menu (smart pattern) ───── */

    private const MENU_SLUG = 'agoharden-tools';

    public const PAGE_SLUG = 'agoharden';

    /**
     * Capability that guards the page and its route.
     *
     * The settings write the .htaccess at the root of the install and change
     * how every login works. On a network that is beyond what the administrator
     * of one site may decide, so it takes the network capability there.
     */
    public static function capability(): string {
        return is_multisite() ? 'manage_network_options' : 'manage_options';
    }

    public function register_admin_menu(): void {
        /*
         * Other plugins from the same author may already have added a shared
         * parent menu. When one is there the page goes under it, so the
         * dashboard does not grow a second top level entry for the same set of
         * tools. When there is none, this plugin creates its own parent under
         * its own prefix and never defines the shared slug.
         */
        $parent = 'agolab-tools';

        if ( empty( $GLOBALS['admin_page_hooks'][ $parent ] ) ) {
            $parent = self::MENU_SLUG;

            add_menu_page(
                __( 'aGo Tools', 'ago-harden' ),
                __( 'aGo Tools', 'ago-harden' ),
                self::capability(),
                $parent,
                '__return_null',
                'dashicons-hammer',
                81
            );
        }

        add_submenu_page(
            $parent,
            __( 'aGo Harden', 'ago-harden' ),
            __( 'Harden', 'ago-harden' ),
            self::capability(),
            self::PAGE_SLUG,
            [ Admin\Page::class, 'render' ]
        );

        remove_submenu_page( $parent, $parent );
    }

    /* ───── REST routes ───── */

    public function register_rest_routes(): void {
        register_rest_route( 'agoharden/v1', '/settings', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_save_settings' ],
            'permission_callback' => static function (): bool {
                return current_user_can( self::capability() );
            },
        ] );
    }

    /** Slugs WordPress already answers on, which a login URL must not take over. */
    private const RESERVED_SLUGS = [ 'wp-admin', 'wp-login', 'wp-login-php', 'admin', 'login', 'dashboard', 'wp-json', 'feed' ];

    public function handle_save_settings( \WP_REST_Request $request ): \WP_REST_Response {
        $input    = (array) $request->get_json_params();
        $settings = [];

        foreach ( self::DEFAULTS as $key => $default ) {
            if ( 'custom_login_url' === $key ) {
                $slug             = sanitize_title( (string) ( $input[ $key ] ?? '' ) );
                $settings[ $key ] = in_array( $slug, self::RESERVED_SLUGS, true ) ? '' : $slug;
            } elseif ( 'force_logout_hours' === $key ) {
                $settings[ $key ] = max( 0, min( 720, (int) ( $input[ $key ] ?? 0 ) ) );
            } else {
                $settings[ $key ] = ! empty( $input[ $key ] );
            }
        }

        update_option( 'agoharden_settings', $settings );
        $failed = self::sync_htaccess();

        $settings['security_score'] = Score::calculate( $settings, self::inert_toggles() );

        return new \WP_REST_Response( [ 'saved' => true, 'failed' => $failed, 'settings' => $settings ] );
    }

    /* ───── What this server can enforce ───── */

    /**
     * Switches that cannot take effect on this server.
     *
     * Two protections are .htaccess rules, which only Apache and LiteSpeed
     * read. On any other server, for the directory listing on a site without
     * a root .htaccess, and on Apache when the rule is not in the file (it
     * could not be written), the switch can be on while nothing protects the
     * site. The page says so next to the switch and the score leaves them
     * out, so neither reports a protection that is not in place.
     *
     * @return string[]
     */
    public static function inert_toggles(): array {
        if ( empty( $GLOBALS['is_apache'] ) ) {
            return [ 'block_php_uploads', 'disable_directory_listing' ];
        }

        $settings = self::get_settings();
        $inert    = [];

        if ( ! empty( $settings['block_php_uploads'] ) && ! Modules\PhpUploads::in_place() ) {
            $inert[] = 'block_php_uploads';
        }
        if ( ! file_exists( Modules\DirectoryListing::file() )
            || ( ! empty( $settings['disable_directory_listing'] ) && ! Modules\DirectoryListing::in_place() ) ) {
            $inert[] = 'disable_directory_listing';
        }

        return $inert;
    }

    /* ───── Assets ───── */

    public function enqueue_assets( string $hook ): void {
        if ( ! str_ends_with( $hook, '_page_' . self::PAGE_SLUG ) ) {
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
            'restUrl'  => rest_url( 'agoharden/v1' ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'inert'    => self::inert_toggles(),
            'i18n'     => [
                'excellent' => __( 'Excellent', 'ago-harden' ),
                'good'      => __( 'Good', 'ago-harden' ),
                'fair'      => __( 'Fair', 'ago-harden' ),
                'weak'      => __( 'Weak', 'ago-harden' ),
                'saving'    => __( 'Saving...', 'ago-harden' ),
                'saved'     => __( 'Saved!', 'ago-harden' ),
                'error'     => __( 'Error saving.', 'ago-harden' ),
                'save'      => __( 'Save Settings', 'ago-harden' ),
                'failed'    => __( 'Saved, but a rule could not be written to .htaccess. Check the file permissions; the switch adds nothing to the score until the rule is in place.', 'ago-harden' ),
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
