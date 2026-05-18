<?php

namespace AgoLab\Harden\Modules;

defined( 'ABSPATH' ) || exit;

class PhpUploads {

    public static function init(): void {
        // .htaccess is written on save/activation. Nothing to hook at runtime
        // beyond ensuring it stays in place.
    }

    /**
     * Write .htaccess to wp-content/uploads/ blocking PHP execution.
     */
    public static function write_htaccess(): void {
        $upload_dir = wp_upload_dir();
        $basedir    = $upload_dir['basedir'] ?? '';

        if ( empty( $basedir ) || ! is_dir( $basedir ) ) {
            return;
        }

        $htaccess = $basedir . '/.htaccess';
        $rules    = self::get_rules();

        if ( file_exists( $htaccess ) ) {
            $content = file_get_contents( $htaccess );
            if ( strpos( $content, '# BEGIN aGo Harden' ) !== false ) {
                return; // Already present.
            }
            // Prepend our rules.
            file_put_contents( $htaccess, $rules . "\n" . $content );
        } else {
            file_put_contents( $htaccess, $rules );
        }
    }

    /**
     * Remove our .htaccess rules from wp-content/uploads/.
     */
    public static function remove_htaccess(): void {
        $upload_dir = wp_upload_dir();
        $basedir    = $upload_dir['basedir'] ?? '';
        $htaccess   = $basedir . '/.htaccess';

        if ( ! file_exists( $htaccess ) ) {
            return;
        }

        $content = file_get_contents( $htaccess );
        $content = preg_replace(
            '/# BEGIN aGo Harden.*?# END aGo Harden\s*/s',
            '',
            $content
        );

        if ( trim( $content ) === '' ) {
            wp_delete_file( $htaccess );
        } else {
            file_put_contents( $htaccess, $content );
        }
    }

    private static function get_rules(): string {
        return <<<'HTACCESS'
# BEGIN aGo Harden
<Files *.php>
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order deny,allow
        Deny from all
    </IfModule>
</Files>
# END aGo Harden
HTACCESS;
    }
}
