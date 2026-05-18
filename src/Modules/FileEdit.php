<?php

namespace AgoLab\Harden\Modules;

defined( 'ABSPATH' ) || exit;

class FileEdit {

    public static function init(): void {
        // Define the constant if not already defined (may be too late in some cases).
        if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
            define( 'DISALLOW_FILE_EDIT', true );
        }

        // Belt-and-suspenders: also strip the capabilities.
        add_filter( 'map_meta_cap', [ __CLASS__, 'restrict_caps' ], 10, 4 );
    }

    /**
     * Map edit_themes, edit_plugins, edit_files to do_not_allow.
     */
    public static function restrict_caps( array $caps, string $cap, int $user_id, array $args ): array {
        $blocked = [ 'edit_themes', 'edit_plugins', 'edit_files' ];

        if ( in_array( $cap, $blocked, true ) ) {
            return [ 'do_not_allow' ];
        }

        return $caps;
    }
}
