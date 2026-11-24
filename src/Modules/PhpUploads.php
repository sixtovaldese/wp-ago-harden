<?php

namespace AgoLab\Harden\Modules;

use AgoLab\Harden\Htaccess;

defined( 'ABSPATH' ) || exit;

class PhpUploads {

    public static function init(): void {
        // The rules are written on save, on activation and when the settings
        // page opens. Nothing to hook at runtime.
    }

    /** The .htaccess file of the uploads directory, or an empty string. */
    public static function file(): string {
        $basedir = wp_get_upload_dir()['basedir'] ?? '';
        return ( '' !== $basedir && is_dir( $basedir ) ) ? $basedir . '/.htaccess' : '';
    }

    /** The rules that block PHP execution. */
    private const LINES = [
        '<Files *.php>',
        '    <IfModule mod_authz_core.c>',
        '        Require all denied',
        '    </IfModule>',
        '    <IfModule !mod_authz_core.c>',
        '        Order deny,allow',
        '        Deny from all',
        '    </IfModule>',
        '</Files>',
    ];

    /**
     * Block PHP execution inside the uploads directory.
     *
     * @return bool Whether the rules are in place afterwards.
     */
    public static function write_htaccess(): bool {
        return Htaccess::add( self::file(), self::LINES );
    }

    public static function in_place(): bool {
        return Htaccess::in_place( self::file(), self::LINES );
    }

    /**
     * Take the rules out again, leaving the file as it was before.
     */
    public static function remove_htaccess(): void {
        Htaccess::remove( self::file() );
    }
}
