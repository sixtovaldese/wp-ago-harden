<?php
/**
 * Smoke tests for aGo Harden.
 *
 * Covers what the WordPress.org review rules of 2026 ask for (no bundled
 * translations, prefixed identifiers, own parent menu, translatable texts, no
 * inline script or style, .htaccess written through the WordPress API and
 * fully removed again) and the safety of the settings handler: only a user
 * with the right capability and a valid nonce can change anything, and every
 * value is sanitized before it is stored. It also guards the version masking:
 * the ?ver= value hides the release but still changes when the file changes,
 * so caches keep refreshing after an update.
 *
 * Run with the plugin deployed:
 *   docker compose run --rm wpcli eval-file \
 *     //var/www/html/wp-content/plugins/ago-harden/tests/smoke.php
 */

use AgoLab\Harden\Admin\Page;
use AgoLab\Harden\Modules\HideVersion;
use AgoLab\Harden\Plugin;
use AgoLab\Harden\Score;

class AgoHardenSmoke {

    public static int $ok = 0;
    public static int $fail = 0;

    private const ROLE = 'agoharden_smoke_manager';

    /** @var array<string, int> */
    private static array $users = [];

    private static int $admin = 0;

    public static function check( string $name, bool $cond, string $detail = '' ): void {
        if ( $cond ) {
            self::$ok++;
            echo "  ok    {$name}\n";
        } else {
            self::$fail++;
            echo "  FALLA {$name}" . ( $detail ? " ({$detail})" : '' ) . "\n";
        }
    }

    public static function run(): void {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/user.php';

        $saved_option = get_option( 'agoharden_settings', null );
        $saved_apache = $GLOBALS['is_apache'] ?? false;

        try {
            self::users();
            self::packaging();
            self::identifiers();
            self::admin_menu();
            self::page();
            self::rest_access();
            self::sanitizing();
            self::version_masking();
            self::uploads_htaccess();
            self::root_htaccess();
            self::stale_block();
            self::inert_on_this_server();
            self::no_front_credit();
            self::messages();
            self::http_nonce();
            self::login_url_http();
        } finally {
            $GLOBALS['is_apache'] = $saved_apache;
            wp_set_current_user( 0 );
            foreach ( self::$users as $id ) {
                wp_delete_user( $id );
            }
            remove_role( self::ROLE );
            null === $saved_option ? delete_option( 'agoharden_settings' ) : update_option( 'agoharden_settings', $saved_option );
            Plugin::sync_htaccess();
        }

        echo "\n" . self::$ok . ' ok, ' . self::$fail . " fallas\n";
        if ( self::$fail ) {
            exit( 1 );
        }
    }

    private static function users(): void {
        add_role( self::ROLE, 'aGo Harden smoke', [ 'read' => true, 'manage_options' => true ] );

        $admin       = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
        self::$admin = (int) $admin[0];
        self::$users = [];
        foreach ( [ 'subscriber', 'editor', self::ROLE ] as $role ) {
            self::$users[ $role ] = (int) wp_insert_user( [
                'user_login' => 'agoharden_smoke_' . $role . '_' . wp_rand(),
                'user_pass'  => wp_generate_password(),
                'role'       => $role,
            ] );
        }
    }

    /** @param array<string, mixed> $body */
    private static function save( array $body, int $user ): WP_REST_Response {
        wp_set_current_user( $user );
        $request = new WP_REST_Request( 'POST', '/agoharden/v1/settings' );
        $request->set_header( 'Content-Type', 'application/json' );
        $request->set_body( wp_json_encode( $body ) );
        $response = rest_do_request( $request );
        wp_set_current_user( 0 );
        return $response;
    }

    /** @param array<string, mixed> $changes */
    private static function settings( array $changes ): array {
        return array_merge( Plugin::DEFAULTS, $changes );
    }

    /** @return string[] */
    private static function php_files(): array {
        $files = [ AGOHARDEN_FILE, AGOHARDEN_PATH . 'uninstall.php' ];
        $it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( AGOHARDEN_PATH . 'src' ) );
        foreach ( $it as $f ) {
            if ( $f->isFile() && 'php' === $f->getExtension() ) {
                $files[] = $f->getPathname();
            }
        }
        return $files;
    }

    private static function source(): string {
        $source = '';
        foreach ( self::php_files() as $file ) {
            $source .= file_get_contents( $file );
        }
        return $source;
    }

    private static function packaging(): void {
        $data = get_plugin_data( AGOHARDEN_FILE, false, false );
        self::check( 'el paquete no lleva carpeta languages', ! is_dir( AGOHARDEN_PATH . 'languages' ) );
        self::check( 'el encabezado no declara Domain Path', '' === $data['DomainPath'], $data['DomainPath'] );
        self::check( 'el text domain es el slug', 'ago-harden' === $data['TextDomain'] );
        self::check( 'el nombre da el slug ago-harden en WordPress.org', 'ago-harden' === sanitize_title( $data['Name'] ), $data['Name'] );

        $source = self::source();
        self::check( 'nadie llama load_plugin_textdomain', ! str_contains( $source, 'load_plugin_textdomain' ) );
        self::check( 'ningun archivo escribe con file_put_contents ni fopen', ! preg_match( '/\b(file_put_contents|fopen|fwrite)\s*\(/', $source ) );
        self::check( 'el .htaccess se escribe con insert_with_markers', str_contains( $source, 'insert_with_markers(' ) );

        $readme = (string) file_get_contents( AGOHARDEN_PATH . 'readme.txt' );
        self::check( 'el readme declara Tested up to 7.1', (bool) preg_match( '/^Tested up to:\s*7\.1\s*$/m', $readme ) );
        self::check( 'el Stable tag es numerico', (bool) preg_match( '/^Stable tag:\s*\d+\.\d+\.\d+\s*$/m', $readme ) );
        self::check( 'el readme trae External services y Privacy', str_contains( $readme, '== External services ==' ) && str_contains( $readme, '== Privacy ==' ) );
        self::check( 'el readme ya no promete quitar ?ver=', ! preg_match( '/strip `?\?ver=/i', $readme ) );
    }

    private static function identifiers(): void {
        $routes = array_keys( rest_get_server()->get_routes() );
        $own    = array_values( array_filter( $routes, static fn ( $r ) => str_starts_with( $r, '/agoharden/' ) ) );
        $old    = array_filter( $routes, static fn ( $r ) => str_starts_with( $r, '/ago-harden/' ) );
        sort( $own );
        self::check( 'las rutas REST usan el namespace agoharden/v1', [ '/agoharden/v1', '/agoharden/v1/settings' ] === $own, implode( ',', $own ) );
        self::check( 'no queda el namespace ago-harden/v1', [] === $old );

        wp_set_current_user( self::$admin );
        set_current_screen( 'agoharden-tools_page_agoharden' );
        Plugin::instance()->enqueue_assets( 'agoharden-tools_page_agoharden' );
        $data = (string) wp_scripts()->get_data( 'agoharden-admin', 'data' );
        $json = json_decode( (string) preg_replace( '/^var agohardenData = (.*);$/s', '$1', $data ), true );
        self::check( 'el script y el estilo se encolan con el handle agoharden-admin', wp_script_is( 'agoharden-admin', 'enqueued' ) && wp_style_is( 'agoharden-admin', 'enqueued' ) );
        self::check( 'el objeto JS apunta a agoharden/v1', str_ends_with( (string) ( $json['restUrl'] ?? '' ), '/agoharden/v1' ), $data );
        self::check( 'el nonce entregado al JS es de wp_rest', false !== wp_verify_nonce( (string) ( $json['nonce'] ?? '' ), 'wp_rest' ) );
        self::check( 'los textos del JS se entregan traducibles', isset( $json['i18n']['saving'], $json['i18n']['error'] ) );
        wp_set_current_user( 0 );
    }

    private static function reset_menu(): void {
        $GLOBALS['admin_page_hooks']  = [];
        $GLOBALS['menu']              = [];
        $GLOBALS['submenu']           = [];
        $GLOBALS['_registered_pages'] = [];
        $GLOBALS['_parent_pages']     = [];
    }

    /** @return string[] */
    private static function submenu_slugs( string $parent ): array {
        return array_values( array_map( static fn ( $item ) => $item[2], $GLOBALS['submenu'][ $parent ] ?? [] ) );
    }

    private static function admin_menu(): void {
        wp_set_current_user( self::$admin );

        self::reset_menu();
        Plugin::instance()->register_admin_menu();
        self::check( 'sin el menu compartido crea su propio padre agoharden-tools', isset( $GLOBALS['admin_page_hooks']['agoharden-tools'] ) );
        self::check( 'nunca define el slug compartido agolab-tools', ! isset( $GLOBALS['admin_page_hooks']['agolab-tools'] ) );
        self::check( 'la pagina agoharden cuelga de agoharden-tools', [ 'agoharden' ] === self::submenu_slugs( 'agoharden-tools' ), implode( ',', self::submenu_slugs( 'agoharden-tools' ) ) );

        self::reset_menu();
        add_menu_page( 'aGo Tools', 'aGo Tools', 'manage_options', 'agolab-tools', '__return_null' );
        Plugin::instance()->register_admin_menu();
        self::check( 'con el menu compartido se engancha a el', in_array( 'agoharden', self::submenu_slugs( 'agolab-tools' ), true ) );
        self::check( 'con el menu compartido no crea un segundo padre', ! isset( $GLOBALS['admin_page_hooks']['agoharden-tools'] ) );

        self::reset_menu();
        wp_set_current_user( 0 );

        self::check( 'en un sitio simple la capacidad es manage_options', 'manage_options' === Plugin::capability() );
    }

    private static function render_page(): string {
        wp_set_current_user( self::$admin );
        ob_start();
        Page::render();
        $html = (string) ob_get_clean();
        wp_set_current_user( 0 );
        return $html;
    }

    private static function page(): void {
        $tag = static fn ( $translation, $text, $domain ) => 'ago-harden' === $domain ? '[t]' . $translation : $translation;
        $tagn = static fn ( $translation, $single, $plural, $number, $domain ) => 'ago-harden' === $domain ? '[t]' . $translation : $translation;
        add_filter( 'gettext', $tag, 10, 3 );
        add_filter( 'ngettext', $tagn, 10, 5 );
        $html = self::render_page();
        remove_filter( 'gettext', $tag, 10 );
        remove_filter( 'ngettext', $tagn, 10 );

        self::check( 'la pagina no imprime script en linea', ! str_contains( $html, '<script' ) );
        self::check( 'la pagina no imprime estilos en linea ni link directo', ! str_contains( $html, '<style' ) && ! str_contains( $html, '<link' ) );
        self::check( 'ningun interruptor se muestra deshabilitado', ! preg_match( '/\sdisabled[\s>=]/', $html ) );
        self::check( 'los puntos de cada interruptor pasan por traduccion', ! preg_match( '/>\s*\d+ pts\s*</', $html ) && str_contains( $html, '[t]15 pts' ) );
        self::check( 'la descripcion de ocultar version ya no dice que quita ?ver=', ! str_contains( $html, 'strip ?ver=' ) );
    }

    private static function rest_access(): void {
        update_option( 'agoharden_settings', self::settings( [] ) );
        $body = self::settings( [ 'disable_file_edit' => true ] );

        $r = self::save( $body, 0 );
        self::check( 'sin sesion guardar responde 401', 401 === $r->get_status(), (string) $r->get_status() );
        $r = self::save( $body, self::$users['subscriber'] );
        self::check( 'un suscriptor recibe 403', 403 === $r->get_status(), (string) $r->get_status() );
        $r = self::save( $body, self::$users['editor'] );
        self::check( 'un editor recibe 403', 403 === $r->get_status(), (string) $r->get_status() );
        self::check( 'ninguno de los tres cambio un ajuste', empty( Plugin::get_settings()['disable_file_edit'] ) );

        $r = self::save( $body, self::$admin );
        self::check( 'el administrador guarda', 200 === $r->get_status() && ! empty( Plugin::get_settings()['disable_file_edit'] ), (string) $r->get_status() );

        wp_set_current_user( self::$admin );
        $r = rest_do_request( new WP_REST_Request( 'GET', '/agoharden/v1/score' ) );
        wp_set_current_user( 0 );
        self::check( 'la ruta /score que nadie usaba ya no existe', 404 === $r->get_status(), (string) $r->get_status() );

        update_option( 'agoharden_settings', self::settings( [] ) );
    }

    private static function sanitizing(): void {
        $r = self::save( [
            'custom_login_url'   => '<script>alert(1)</script>Mi Acceso/../',
            'force_logout_hours' => 99999,
            'hide_login_errors'  => 'yes',
            'evil_key'           => 'x',
        ], self::$admin );
        $saved = (array) get_option( 'agoharden_settings' );
        self::check( 'el slug de acceso se guarda saneado', 200 === $r->get_status() && (bool) preg_match( '/^[a-z0-9-]+$/', (string) $saved['custom_login_url'] ), (string) ( $saved['custom_login_url'] ?? '' ) );
        self::check( 'las horas de sesion se limitan a 720', 720 === ( $saved['force_logout_hours'] ?? null ), (string) wp_json_encode( $saved['force_logout_hours'] ?? null ) );
        self::check( 'un interruptor se guarda como booleano', true === ( $saved['hide_login_errors'] ?? null ) );
        self::check( 'una clave desconocida no se guarda', ! array_key_exists( 'evil_key', $saved ) );

        self::save( [ 'force_logout_hours' => -5 ], self::$admin );
        $saved = (array) get_option( 'agoharden_settings' );
        self::check( 'unas horas negativas quedan en 0', 0 === ( $saved['force_logout_hours'] ?? null ), (string) wp_json_encode( $saved['force_logout_hours'] ?? null ) );

        foreach ( [ 'wp-admin', 'wp-login', 'admin', 'login', 'dashboard' ] as $reserved ) {
            self::save( [ 'custom_login_url' => $reserved ], self::$admin );
            $saved = (array) get_option( 'agoharden_settings' );
            self::check( "el slug reservado {$reserved} no se acepta", '' === ( $saved['custom_login_url'] ?? null ), (string) ( $saved['custom_login_url'] ?? '' ) );
        }

        update_option( 'agoharden_settings', self::settings( [] ) );
    }

    private static function version_masking(): void {
        $uploads = wp_get_upload_dir();
        $file    = $uploads['basedir'] . '/agoharden-smoke.js';
        $url     = $uploads['baseurl'] . '/agoharden-smoke.js';
        // phpcs:ignore WordPress.WP.AlternativeFunctions
        file_put_contents( $file, '/* smoke */' );

        try {
            touch( $file, time() - 3600 );
            clearstatcache();
            $first = HideVersion::mask_version( $url . '?ver=6.9.1' );
            parse_str( (string) wp_parse_url( $first, PHP_URL_QUERY ), $q1 );
            self::check( 'un asset local conserva el parametro ver', isset( $q1['ver'] ), $first );
            self::check( 'el valor de ver ya no es la version', '6.9.1' !== ( $q1['ver'] ?? '' ) && ! str_contains( $first, '6.9.1' ), $first );
            self::check( 'el valor es el hash corto de la fecha del archivo', substr( md5( (string) filemtime( $file ) ), 0, 8 ) === ( $q1['ver'] ?? '' ) );

            touch( $file, time() );
            clearstatcache();
            $second = HideVersion::mask_version( $url . '?ver=6.9.1' );
            self::check( 'cuando el archivo cambia, cambia la URL (la cache se renueva)', $first !== $second, $second );

            self::check( 'un asset de otro dominio pierde su version', 'https://cdn.example.com/lib.js' === HideVersion::mask_version( 'https://cdn.example.com/lib.js?ver=6.9.1' ) );
            self::check( 'una URL sin ver queda intacta', $url === HideVersion::mask_version( $url ) );

            $core = includes_url( 'js/jquery/jquery.min.js' ) . '?ver=' . get_bloginfo( 'version' );
            self::check( 'jQuery del core deja de mostrar la version de WordPress', ! str_contains( HideVersion::mask_version( $core ), 'ver=' . get_bloginfo( 'version' ) ) );
        } finally {
            wp_delete_file( $file );
        }
    }

    private static function uploads_file(): string {
        return wp_get_upload_dir()['basedir'] . '/.htaccess';
    }

    private static function read( string $file ): string {
        clearstatcache();
        return file_exists( $file ) ? (string) file_get_contents( $file ) : '';
    }

    private static function uploads_htaccess(): void {
        $file   = self::uploads_file();
        $backup = file_exists( $file ) ? self::read( $file ) : null;
        $other  = "# Rules from another plugin\nOptions -ExecCGI\n";

        try {
            wp_delete_file( $file );
            self::save( self::settings( [ 'block_php_uploads' => true ] ), self::$admin );
            self::check( 'al activar se escribe el bloque en uploads/.htaccess', str_contains( self::read( $file ), '# BEGIN aGo Harden' ) && str_contains( self::read( $file ), 'Require all denied' ) );

            self::save( self::settings( [] ), self::$admin );
            self::check( 'al apagar, el archivo que creo el plugin desaparece', ! file_exists( $file ) );

            // phpcs:ignore WordPress.WP.AlternativeFunctions
            file_put_contents( $file, $other );
            self::save( self::settings( [ 'block_php_uploads' => true ] ), self::$admin );
            self::check( 'con reglas ajenas el bloque se agrega sin tocarlas', str_contains( self::read( $file ), 'Options -ExecCGI' ) && str_contains( self::read( $file ), '# BEGIN aGo Harden' ) );

            Plugin::deactivate();
            self::check( 'al desactivar el plugin el archivo vuelve a su contenido original', trim( $other ) === trim( self::read( $file ) ), self::read( $file ) );

            Plugin::activate();
            self::check( 'al reactivar la regla vuelve, porque el interruptor sigue encendido', str_contains( self::read( $file ), '# BEGIN aGo Harden' ) );

            // phpcs:ignore WordPress.WP.AlternativeFunctions
            file_put_contents( $file, $other );
            wp_set_current_user( self::$admin );
            ob_start();
            Page::render();
            ob_end_clean();
            wp_set_current_user( 0 );
            self::check( 'abrir la pagina repone una regla borrada a mano', str_contains( self::read( $file ), '# BEGIN aGo Harden' ) );

            self::save( self::settings( [] ), self::$admin );
            self::check( 'al apagar no quedan marcadores vacios', ! str_contains( self::read( $file ), 'aGo Harden' ) && trim( $other ) === trim( self::read( $file ) ), self::read( $file ) );
        } finally {
            wp_delete_file( $file );
            if ( null !== $backup ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions
                file_put_contents( $file, $backup );
            }
        }
    }

    private static function root_htaccess(): void {
        $file   = ABSPATH . '.htaccess';
        $backup = file_exists( $file ) ? self::read( $file ) : null;
        $wp     = "# BEGIN WordPress\nRewriteEngine On\n# END WordPress\n";

        try {
            // phpcs:ignore WordPress.WP.AlternativeFunctions
            file_put_contents( $file, $wp );
            self::save( self::settings( [ 'disable_directory_listing' => true ] ), self::$admin );
            self::check( 'el listado de directorios se bloquea en el .htaccess raiz', str_contains( self::read( $file ), 'Options -Indexes' ) );
            self::check( 'las reglas de WordPress siguen ahi', str_contains( self::read( $file ), "# BEGIN WordPress\nRewriteEngine On\n# END WordPress" ) );

            self::save( self::settings( [] ), self::$admin );
            self::check( 'al apagar el .htaccess raiz queda como estaba', trim( $wp ) === trim( self::read( $file ) ), self::read( $file ) );

            wp_delete_file( $file );
            self::save( self::settings( [ 'disable_directory_listing' => true ] ), self::$admin );
            self::check( 'sin .htaccess raiz no se crea uno', ! file_exists( $file ) );
        } finally {
            wp_delete_file( $file );
            if ( null !== $backup ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions
                file_put_contents( $file, $backup );
            }
            update_option( 'agoharden_settings', self::settings( [] ) );
        }
    }

    /**
     * A block whose markers are there but whose rules are gone (emptied by
     * hand, or left empty when the disk could not be written) must be filled
     * again, not taken for a rule in place. While it cannot be filled, the
     * switch counts as having no effect.
     */
    private static function stale_block(): void {
        $file   = self::uploads_file();
        $backup = file_exists( $file ) ? self::read( $file ) : null;
        $empty  = "# Rules from another plugin\nOptions -ExecCGI\n\n# BEGIN aGo Harden\n# END aGo Harden\n";
        $stale  = "# BEGIN aGo Harden\nOptions +Indexes\n# END aGo Harden\n";

        try {
            update_option( 'agoharden_settings', self::settings( [ 'block_php_uploads' => true ] ) );

            // phpcs:ignore WordPress.WP.AlternativeFunctions
            file_put_contents( $file, $empty );
            Plugin::sync_htaccess();
            self::check( 'un bloque vacio se vuelve a llenar', str_contains( self::read( $file ), 'Require all denied' ) && str_contains( self::read( $file ), 'Options -ExecCGI' ), self::read( $file ) );

            // phpcs:ignore WordPress.WP.AlternativeFunctions
            file_put_contents( $file, $stale );
            Plugin::sync_htaccess();
            self::check( 'un bloque con otras reglas se reescribe', str_contains( self::read( $file ), 'Require all denied' ) && ! str_contains( self::read( $file ), 'Options +Indexes' ), self::read( $file ) );

            $GLOBALS['is_apache'] = true;
            // phpcs:ignore WordPress.WP.AlternativeFunctions
            file_put_contents( $file, $stale );
            chmod( $file, 0444 );
            $failed = Plugin::sync_htaccess();
            $locked = ! is_writable( $file );
            chmod( $file, 0644 );
            if ( $locked ) {
                self::check( 'si no se puede escribir, el guardado lo informa', in_array( 'block_php_uploads', $failed, true ), wp_json_encode( $failed ) );
                // phpcs:ignore WordPress.WP.AlternativeFunctions
                file_put_contents( $file, $stale );
                self::check( 'y el interruptor cuenta como sin efecto', in_array( 'block_php_uploads', Plugin::inert_toggles(), true ) );
            } else {
                self::check( 'un bloque con reglas ajenas cuenta como sin efecto en Apache', in_array( 'block_php_uploads', ( static function () use ( $file, $stale ) {
                    // phpcs:ignore WordPress.WP.AlternativeFunctions
                    file_put_contents( $file, $stale );
                    return Plugin::inert_toggles();
                } )(), true ) );
            }
        } finally {
            $GLOBALS['is_apache'] = false;
            if ( file_exists( $file ) ) {
                chmod( $file, 0644 );
            }
            wp_delete_file( $file );
            if ( null !== $backup ) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions
                file_put_contents( $file, $backup );
            }
            update_option( 'agoharden_settings', self::settings( [] ) );
        }
    }

    /**
     * The score and the page only count what is in place. A server that does
     * not read .htaccess (Nginx) gets the .htaccess toggles marked as having no
     * effect, and they add nothing to the score.
     */
    private static function inert_on_this_server(): void {
        $on = self::settings( [ 'block_php_uploads' => true, 'disable_directory_listing' => true ] );

        $GLOBALS['is_apache'] = false;
        self::check( 'en un servidor sin .htaccess ambos interruptores quedan sin efecto', [ 'block_php_uploads', 'disable_directory_listing' ] === Plugin::inert_toggles() );
        self::check( 'y no suman puntos', 0 === Score::calculate( $on, Plugin::inert_toggles() ) );
        update_option( 'agoharden_settings', $on );
        $html = self::render_page();
        self::check( 'la pagina lo avisa junto al interruptor', 2 === substr_count( $html, 'ago-toggle-inert' ) );

        $GLOBALS['is_apache'] = true;
        $root = ABSPATH . '.htaccess';
        if ( ! file_exists( $root ) ) {
            self::check( 'en Apache sin .htaccess raiz solo el listado queda sin efecto', [ 'disable_directory_listing' ] === Plugin::inert_toggles() );
        }
        self::check( 'en Apache bloquear PHP en uploads suma sus 10 puntos', 10 <= Score::calculate( $on, Plugin::inert_toggles() ) );

        $GLOBALS['is_apache'] = false;
        update_option( 'agoharden_settings', self::settings( [] ) );
        Plugin::sync_htaccess();
    }

    private static function no_front_credit(): void {
        ob_start();
        do_action( 'wp_head' );
        do_action( 'wp_footer' );
        $front = (string) ob_get_clean();
        self::check( 'el front no lleva credito ni enlace de aGo Lab', ! str_contains( $front, 'ago.cl' ) && ! str_contains( $front, 'aGo Lab' ) );
    }

    private static function messages(): void {
        $bare = [];
        foreach ( self::php_files() as $file ) {
            foreach ( file( $file ) as $n => $line ) {
                $trim = ltrim( $line );
                if ( str_starts_with( $trim, '//' ) || str_starts_with( $trim, '*' ) || str_starts_with( $trim, '/*' ) ) {
                    continue;
                }
                if ( ! preg_match_all( "/'([A-Z][a-z]+[ :][^']*)'/", $line, $m, PREG_OFFSET_CAPTURE ) ) {
                    continue;
                }
                foreach ( $m[0] as $hit ) {
                    $before = substr( $line, 0, $hit[1] );
                    if ( ! preg_match( '/(__|_e|_x|_n|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*$/', $before ) ) {
                        $bare[] = basename( $file ) . ':' . ( $n + 1 ) . ' ' . $hit[0];
                    }
                }
            }
        }
        self::check( 'ningun texto en ingles queda fuera de una funcion de traduccion', [] === $bare, implode( ' | ', array_slice( $bare, 0, 6 ) ) );

        $js = (string) file_get_contents( AGOHARDEN_PATH . 'assets/js/admin.js' );
        self::check( 'el JS no trae textos fijos en ingles', ! preg_match( "/'[A-Z][a-z]+[ :!.\x{2026}]/u", $js ) && ! preg_match( "/: '[A-Z][a-z]+'/", $js ) );
    }

    private static function login_url_http(): void {
        AgoHardenLoginSmoke::run();
    }

    /**
     * The real request from the admin screen: a session cookie alone does not
     * authenticate a REST call, the wp_rest nonce is required too.
     */
    private static function http_nonce(): void {
        $admin   = self::$admin;
        $expire  = time() + HOUR_IN_SECONDS;
        $token   = WP_Session_Tokens::get_instance( $admin )->create( $expire );
        $cookies = [
            AUTH_COOKIE        => wp_generate_auth_cookie( $admin, $expire, 'auth', $token ),
            SECURE_AUTH_COOKIE => wp_generate_auth_cookie( $admin, $expire, 'secure_auth', $token ),
            LOGGED_IN_COOKIE   => wp_generate_auth_cookie( $admin, $expire, 'logged_in', $token ),
        ];
        $_COOKIE[ LOGGED_IN_COOKIE ] = $cookies[ LOGGED_IN_COOKIE ];
        wp_set_current_user( $admin );
        $nonce = wp_create_nonce( 'wp_rest' );
        wp_set_current_user( 0 );
        unset( $_COOKIE[ LOGGED_IN_COOKIE ] );

        update_option( 'agoharden_settings', self::settings( [] ) );

        $host = wp_parse_url( home_url(), PHP_URL_HOST );
        $post = static function ( array $headers ) use ( $host, $cookies ) {
            return wp_remote_post( 'http://nginx/?rest_route=/agoharden/v1/settings', [
                'headers'     => array_merge( [ 'Host' => $host, 'X-Forwarded-Proto' => 'https', 'Content-Type' => 'application/json' ], $headers ),
                'cookies'     => $cookies,
                'body'        => wp_json_encode( [ 'disable_file_edit' => true ] ),
                'redirection' => 0,
                'timeout'     => 15,
            ] );
        };

        $r = $post( [] );
        if ( is_wp_error( $r ) ) {
            self::check( 'el sitio responde por HTTP', false, $r->get_error_message() );
        } else {
            self::check( 'con cookie y sin nonce responde 401', 401 === wp_remote_retrieve_response_code( $r ), (string) wp_remote_retrieve_response_code( $r ) );
            wp_cache_delete( 'alloptions', 'options' );
            wp_cache_delete( 'agoharden_settings', 'options' );
            self::check( 'y no cambia el ajuste', empty( Plugin::get_settings()['disable_file_edit'] ) );

            $r = $post( [ 'X-WP-Nonce' => $nonce ] );
            self::check( 'con cookie y nonce se guarda', 200 === wp_remote_retrieve_response_code( $r ), (string) wp_remote_retrieve_response_code( $r ) . ' ' . substr( wp_remote_retrieve_body( $r ), 0, 120 ) );
        }

        update_option( 'agoharden_settings', self::settings( [] ) );
        WP_Session_Tokens::get_instance( $admin )->destroy( $token );
    }
}

/**
 * The custom login URL, over real HTTP: the new address serves the full login
 * form, wp-login.php stops answering, and the page renders without PHP notices
 * (wp-login.php is included from inside a function, so its globals must be
 * declared first or WordPress loses track of them).
 */
class AgoHardenLoginSmoke {

    public static function run(): void {
        update_option( 'agoharden_settings', array_merge( Plugin::DEFAULTS, [ 'custom_login_url' => 'agoharden-smoke-entry' ] ) );
        $host = wp_parse_url( home_url(), PHP_URL_HOST );
        $get  = static function ( string $path ) use ( $host ) {
            return wp_remote_get( 'http://nginx' . $path, [
                'headers'     => [ 'Host' => $host, 'X-Forwarded-Proto' => 'https' ],
                'redirection' => 0,
                'timeout'     => 15,
            ] );
        };

        try {
            $r = $get( '/agoharden-smoke-entry' );
            if ( is_wp_error( $r ) ) {
                AgoHardenSmoke::check( 'el sitio responde por HTTP en la URL de acceso', false, $r->get_error_message() );
                return;
            }
            $body = wp_remote_retrieve_body( $r );
            AgoHardenSmoke::check( 'la URL de acceso propia muestra el formulario', 200 === wp_remote_retrieve_response_code( $r ) && str_contains( $body, 'id="loginform"' ), (string) wp_remote_retrieve_response_code( $r ) );
            AgoHardenSmoke::check( 'el formulario envia a la URL propia y no a wp-login.php', str_contains( $body, 'agoharden-smoke-entry' ) && ! str_contains( $body, 'wp-login.php' ) );
            AgoHardenSmoke::check( 'la pagina de acceso sale sin avisos de PHP', ! preg_match( '/(Notice|Warning|Deprecated)<\/b>:|Undefined variable/', $body ) );

            $r = $get( '/wp-login.php' );
            AgoHardenSmoke::check( 'wp-login.php deja de mostrar el formulario', ! is_wp_error( $r ) && 200 !== wp_remote_retrieve_response_code( $r ), is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_response_code( $r ) );
        } finally {
            update_option( 'agoharden_settings', Plugin::DEFAULTS );
        }
    }
}

AgoHardenSmoke::run();
