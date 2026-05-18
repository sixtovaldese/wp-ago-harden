<?php

namespace AgoLab\Harden\Modules;

defined( 'ABSPATH' ) || exit;

class HideVersion {

    public static function init(): void {
        // Remove generator meta tag.
        remove_action( 'wp_head', 'wp_generator' );

        // Remove version from RSS feeds.
        add_filter( 'the_generator', '__return_empty_string' );

        // Strip ?ver= from scripts and styles.
        add_filter( 'style_loader_src', [ __CLASS__, 'strip_version' ], 9999 );
        add_filter( 'script_loader_src', [ __CLASS__, 'strip_version' ], 9999 );
    }

    public static function strip_version( string $src ): string {
        if ( strpos( $src, 'ver=' ) !== false ) {
            $src = remove_query_arg( 'ver', $src );
        }
        return $src;
    }
}
