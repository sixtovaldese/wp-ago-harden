<?php

namespace AgoLab\Harden\Modules;

defined( 'ABSPATH' ) || exit;

class ForceLogout {

    private static int $hours = 24;

    public static function init( int $hours = 24 ): void {
        self::$hours = max( 1, $hours );
        add_filter( 'auth_cookie_expiration', [ __CLASS__, 'set_expiration' ], 10, 3 );
    }

    /**
     * Override auth cookie expiration.
     */
    public static function set_expiration( int $length, int $user_id, bool $remember ): int {
        return self::$hours * HOUR_IN_SECONDS;
    }
}
