<?php

namespace AgoLab\Harden\Modules;

defined( 'ABSPATH' ) || exit;

class XmlRpc {

    public static function init(): void {
        // Skip if ago-cleanup already handles XML-RPC.
        if ( self::is_handled_by_cleanup() ) {
            return;
        }

        add_filter( 'xmlrpc_enabled', '__return_false' );
        add_filter( 'xmlrpc_methods', '__return_empty_array' );

        // Remove RSD link (XML-RPC discovery).
        remove_action( 'wp_head', 'rsd_link' );

        // Remove X-Pingback header.
        add_filter( 'wp_headers', [ __CLASS__, 'remove_pingback_header' ] );
    }

    public static function remove_pingback_header( array $headers ): array {
        unset( $headers['X-Pingback'] );
        return $headers;
    }

    /**
     * Check if ago-cleanup is active and has xmlrpc toggle enabled.
     */
    private static function is_handled_by_cleanup(): bool {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if ( ! is_plugin_active( 'ago-cleanup/ago-cleanup.php' ) ) {
            return false;
        }

        $cleanup_settings = get_option( 'ago_cleanup_settings', [] );
        return ! empty( $cleanup_settings['xmlrpc'] );
    }
}
