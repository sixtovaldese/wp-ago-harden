<?php

namespace AgoLab\Harden\Modules;

defined( 'ABSPATH' ) || exit;

class Headers {

    public static function init(): void {
        add_action( 'send_headers', [ __CLASS__, 'send_security_headers' ] );
    }

    public static function send_security_headers(): void {
        if ( headers_sent() ) {
            return;
        }

        header( 'X-Frame-Options: SAMEORIGIN' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Referrer-Policy: strict-origin-when-cross-origin' );
        header( 'X-XSS-Protection: 1; mode=block' );
        header( 'Permissions-Policy: geolocation=(), microphone=(), camera=()' );
    }
}
