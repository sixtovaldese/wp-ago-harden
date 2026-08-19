<?php

namespace AgoLab\Harden\Modules;

defined( 'ABSPATH' ) || exit;

class AuthorEnum {

    public static function init(): void {
        add_action( 'template_redirect', [ __CLASS__, 'block_enumeration' ] );
        add_filter( 'rest_endpoints', [ __CLASS__, 'restrict_users_endpoint' ] );
    }

    /**
     * Block ?author=N enumeration for non-logged-in users.
     */
    public static function block_enumeration(): void {
        if ( is_user_logged_in() ) {
            return;
        }

        // Only the presence of the parameter is read, to send the visitor back
        // to the home page. Nothing is processed and no state changes, so there
        // is no form and no nonce to check.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['author'] ) || isset( $_GET['author_name'] ) ) {
            wp_safe_redirect( home_url(), 301 );
            exit;
        }
    }

    /**
     * Restrict /wp-json/wp/v2/users endpoint for non-authenticated requests.
     */
    public static function restrict_users_endpoint( array $endpoints ): array {
        if ( ! is_user_logged_in() ) {
            unset( $endpoints['/wp/v2/users'] );
            unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
        }
        return $endpoints;
    }
}
