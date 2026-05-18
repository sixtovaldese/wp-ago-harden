<?php

namespace AgoLab\Harden\Modules;

defined( 'ABSPATH' ) || exit;

class DirectoryListing {

    public static function init(): void {
        // .htaccess is written on save/activation.
    }

    /**
     * Add Options -Indexes to root .htaccess.
     */
    public static function write_htaccess(): void {
        $htaccess = ABSPATH . '.htaccess';

        if ( ! file_exists( $htaccess ) ) {
            return; // No .htaccess to modify (nginx?).
        }

        $content = file_get_contents( $htaccess );

        if ( strpos( $content, '# BEGIN aGo Harden' ) !== false ) {
            return; // Already present.
        }

        $rules = self::get_rules();

        // Prepend before WordPress rules.
        if ( strpos( $content, '# BEGIN WordPress' ) !== false ) {
            $content = $rules . "\n\n" . $content;
        } else {
            $content .= "\n" . $rules;
        }

        file_put_contents( $htaccess, $content );
    }

    /**
     * Remove our rules from root .htaccess.
     */
    public static function remove_htaccess(): void {
        $htaccess = ABSPATH . '.htaccess';

        if ( ! file_exists( $htaccess ) ) {
            return;
        }

        $content = file_get_contents( $htaccess );
        $content = preg_replace(
            '/# BEGIN aGo Harden.*?# END aGo Harden\s*/s',
            '',
            $content
        );

        file_put_contents( $htaccess, $content );
    }

    private static function get_rules(): string {
        return <<<'HTACCESS'
# BEGIN aGo Harden
Options -Indexes
# END aGo Harden
HTACCESS;
    }
}
