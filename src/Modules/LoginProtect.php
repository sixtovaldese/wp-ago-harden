<?php

namespace AgoLab\Harden\Modules;

defined( 'ABSPATH' ) || exit;

class LoginProtect {

    private const MAX_ATTEMPTS  = 3;
    private const WINDOW_MIN    = 15;
    private const LOCKOUT_HOUR  = 1;

    public static function init(): void {
        add_filter( 'authenticate', [ __CLASS__, 'check_lockout' ], 30, 3 );
        add_action( 'wp_login_failed', [ __CLASS__, 'record_failure' ] );
    }

    /**
     * Check if IP is locked out before authenticating.
     */
    public static function check_lockout( $user, string $username, string $password ) {
        if ( empty( $username ) ) {
            return $user;
        }

        $ip_hash  = self::ip_hash();
        $blocked  = get_transient( 'agoharden_login_blocked_' . $ip_hash );

        if ( $blocked ) {
            return new \WP_Error(
                'agoharden_locked',
                sprintf(
                    /* translators: %d: lockout duration in hours */
                    __( 'Too many failed login attempts. Please try again in %d hour.', 'ago-harden' ),
                    self::LOCKOUT_HOUR
                )
            );
        }

        return $user;
    }

    /**
     * Record failed login attempt and lock out if threshold reached.
     */
    public static function record_failure( string $username ): void {
        $ip_hash     = self::ip_hash();
        $transient   = 'agoharden_login_attempts_' . $ip_hash;
        $attempts    = (int) get_transient( $transient );
        $attempts++;

        set_transient( $transient, $attempts, self::WINDOW_MIN * MINUTE_IN_SECONDS );

        if ( $attempts >= self::MAX_ATTEMPTS ) {
            set_transient(
                'agoharden_login_blocked_' . $ip_hash,
                time(),
                self::LOCKOUT_HOUR * HOUR_IN_SECONDS
            );
            delete_transient( $transient );
        }
    }

    private static function ip_hash(): string {
        $ip = isset( $_SERVER['REMOTE_ADDR'] )
            ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
            : '0.0.0.0';
        return md5( $ip . wp_salt( 'auth' ) );
    }
}
