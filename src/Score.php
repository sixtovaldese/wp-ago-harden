<?php

namespace AgoLab\Harden;

defined( 'ABSPATH' ) || exit;

class Score {

    /** Points per toggle. */
    private const WEIGHTS = [
        'custom_login_url'          => 15,
        'limit_login_attempts'      => 15,
        'disable_file_edit'         => 10,
        'hide_wp_version'           => 5,
        'block_author_enum'         => 5,
        'security_headers'          => 15,
        'disable_xmlrpc'            => 10,
        'block_php_uploads'         => 10,
        'disable_directory_listing' => 5,
        'force_logout_hours'        => 5,
        'hide_login_errors'         => 5,
    ];

    /**
     * Calculate security score (0–100).
     *
     * @param array<string, mixed> $settings
     */
    public static function calculate( array $settings ): int {
        $score = 0;

        foreach ( self::WEIGHTS as $key => $points ) {
            if ( $key === 'custom_login_url' ) {
                if ( ! empty( $settings[ $key ] ) && is_string( $settings[ $key ] ) ) {
                    $score += $points;
                }
            } elseif ( $key === 'force_logout_hours' ) {
                if ( ! empty( $settings[ $key ] ) && (int) $settings[ $key ] > 0 ) {
                    $score += $points;
                }
            } else {
                if ( ! empty( $settings[ $key ] ) ) {
                    $score += $points;
                }
            }
        }

        return min( 100, $score );
    }

    /**
     * Get the label for a score range.
     */
    public static function label( int $score ): string {
        if ( $score >= 90 ) {
            return __( 'Excellent', 'ago-harden' );
        }
        if ( $score >= 70 ) {
            return __( 'Good', 'ago-harden' );
        }
        if ( $score >= 40 ) {
            return __( 'Fair', 'ago-harden' );
        }
        return __( 'Weak', 'ago-harden' );
    }

    /**
     * Get CSS color for a score range.
     */
    public static function color( int $score ): string {
        if ( $score >= 90 ) {
            return '#00a32a';
        }
        if ( $score >= 70 ) {
            return '#72aee6';
        }
        if ( $score >= 40 ) {
            return '#dba617';
        }
        return '#d63638';
    }
}
