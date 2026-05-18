<?php

namespace AgoLab\Harden\Modules;

defined( 'ABSPATH' ) || exit;

class LoginErrors {

    public static function init(): void {
        add_filter( 'login_errors', [ __CLASS__, 'generic_error' ] );
    }

    /**
     * Replace all login error messages with a generic one.
     */
    public static function generic_error( string $error ): string {
        return __( 'Invalid credentials. Please try again.', 'ago-harden' );
    }
}
