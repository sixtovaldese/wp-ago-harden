<?php

namespace AgoLab\Harden\Modules;

defined( 'ABSPATH' ) || exit;

class LoginUrl {

    private static string $slug = '';

    public static function init( string $slug = '' ): void {
        if ( empty( $slug ) ) {
            return;
        }

        self::$slug = $slug;

        add_filter( 'login_url', [ __CLASS__, 'filter_login_url' ], 10, 3 );
        add_action( 'init', [ __CLASS__, 'handle_custom_login' ], 1 );
        add_filter( 'site_url', [ __CLASS__, 'filter_site_url' ], 10, 4 );
        add_filter( 'wp_redirect', [ __CLASS__, 'filter_redirect' ], 10, 2 );
    }

    public static function filter_login_url( string $login_url, string $redirect, bool $force_reauth ): string {
        $login_url = site_url( self::$slug, 'login' );
        if ( ! empty( $redirect ) ) {
            $login_url = add_query_arg( 'redirect_to', urlencode( $redirect ), $login_url );
        }
        if ( $force_reauth ) {
            $login_url = add_query_arg( 'reauth', '1', $login_url );
        }
        return $login_url;
    }

    public static function filter_site_url( string $url, string $path, ?string $scheme, ?int $blog_id ): string {
        return self::replace_login_path( $url );
    }

    public static function filter_redirect( string $location, int $status ): string {
        return self::replace_login_path( $location );
    }

    private static function replace_login_path( string $url ): string {
        if ( strpos( $url, 'wp-login.php' ) !== false ) {
            $url = str_replace( 'wp-login.php', self::$slug, $url );
        }
        return $url;
    }

    public static function handle_custom_login(): void {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $parsed      = wp_parse_url( $request_uri );
        $path        = $parsed['path'] ?? '';
        $path        = rtrim( $path, '/' );

        // Serve login page on custom slug.
        if ( $path === '/' . self::$slug || $path === site_url( self::$slug, 'relative' ) ) {
            // Let WordPress handle login.
            require_once ABSPATH . 'wp-login.php';
            exit;
        }

        // Block direct wp-login.php access (except POST for login processing).
        if ( strpos( $path, 'wp-login.php' ) !== false ) {
            // Allow AJAX/POST requests during login process (they post to wp-login.php).
            if ( is_user_logged_in() ) {
                return;
            }
            // Check if the request has the custom slug as referrer.
            $referer = wp_get_referer();
            if ( $referer && strpos( $referer, self::$slug ) !== false ) {
                return;
            }
            // Block access.
            wp_safe_redirect( home_url( '/404' ), 302 );
            exit;
        }
    }
}
