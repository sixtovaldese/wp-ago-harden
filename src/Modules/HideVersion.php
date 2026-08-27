<?php

namespace AgoLab\Harden\Modules;

defined( 'ABSPATH' ) || exit;

class HideVersion {

    public static function init(): void {
        // Remove generator meta tag.
        remove_action( 'wp_head', 'wp_generator' );

        // Remove version from RSS feeds.
        add_filter( 'the_generator', '__return_empty_string' );

        // Replace the version of scripts and styles with a value that says nothing.
        add_filter( 'style_loader_src', [ __CLASS__, 'mask_version' ], 9999 );
        add_filter( 'script_loader_src', [ __CLASS__, 'mask_version' ], 9999 );
    }

    /**
     * Hide what version an asset belongs to without breaking its cache.
     *
     * Deleting the parameter looks tempting and costs the site dearly: with the
     * URL unchanged, browsers and any CDN in front keep serving the old file
     * after an update, which is how a site ends up running yesterday's CSS
     * against today's markup. So the parameter stays and its value is replaced
     * by a short hash of the file's own modification time: it changes when the
     * file changes, which is the whole point of the parameter, and it tells a
     * visitor nothing about which WordPress or which plugin release is
     * installed.
     */
    public static function mask_version( string $src ): string {
        if ( false === strpos( $src, 'ver=' ) ) {
            return $src;
        }

        $path = self::local_path( $src );

        if ( '' === $path ) {
            /*
             * An asset served from somewhere else cannot be stamped from disk.
             * Its version goes away rather than being left on display.
             */
            return remove_query_arg( 'ver', $src );
        }

        return add_query_arg( 'ver', substr( md5( (string) filemtime( $path ) ), 0, 8 ), $src );
    }

    /** Absolute path of an asset served by this install, or an empty string. */
    private static function local_path( string $src ): string {
        $root = untrailingslashit( wp_normalize_path( ABSPATH ) );
        $home = set_url_scheme( home_url( '/' ) );
        $src  = set_url_scheme( $src );

        if ( 0 !== strpos( $src, $home ) ) {
            return '';
        }

        $relative = strtok( substr( $src, strlen( $home ) ), '?' );
        $path     = wp_normalize_path( $root . '/' . ltrim( (string) $relative, '/' ) );

        return is_file( $path ) ? $path : '';
    }
}
