<?php

namespace AgoLab\Harden\Modules;

use AgoLab\Harden\Htaccess;

defined( 'ABSPATH' ) || exit;

class DirectoryListing {

    /** The Apache directive, written as it goes in the file. */
    private const LINES = [
        <<<'HTACCESS'
        Options -Indexes
        HTACCESS,
    ];

    public static function init(): void {
        // The rule is written on save, on activation and when the settings
        // page opens. Nothing to hook at runtime.
    }

    /** The .htaccess file at the root of the site, where WordPress keeps its own. */
    public static function file(): string {
        if ( ! function_exists( 'get_home_path' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        return get_home_path() . '.htaccess';
    }

    /**
     * Add Options -Indexes to the root .htaccess.
     *
     * The file is only edited when it exists: a site without one is served by
     * a server that does not read it (Nginx) or has no rewrite rules yet, and
     * creating it here would add a file nobody asked for.
     *
     * @return bool Whether the rule is in place afterwards.
     */
    public static function write_htaccess(): bool {
        $file = self::file();
        return file_exists( $file ) && Htaccess::add( $file, self::LINES );
    }

    public static function in_place(): bool {
        return Htaccess::in_place( self::file(), self::LINES );
    }

    /**
     * Take the rule out again, leaving the file as it was before.
     */
    public static function remove_htaccess(): void {
        Htaccess::remove( self::file() );
    }
}
