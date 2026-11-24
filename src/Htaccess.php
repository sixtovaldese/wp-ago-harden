<?php

namespace AgoLab\Harden;

defined( 'ABSPATH' ) || exit;

/**
 * The block of rules this plugin keeps in an .htaccess file.
 *
 * Writing goes through insert_with_markers(), the function WordPress itself
 * uses for its rewrite rules: it locks the file, keeps every line outside the
 * markers and does nothing when the block is already up to date, so it is
 * called every time instead of trusting that the markers are there. Removing
 * goes further than emptying the block, because a switch that is turned off
 * has to leave the file exactly as it was before: the markers go too, and a
 * file that only held this block is deleted.
 */
class Htaccess {

    public const MARKER = 'aGo Harden';

    private static function load_misc(): void {
        if ( ! function_exists( 'insert_with_markers' ) ) {
            require_once ABSPATH . 'wp-admin/includes/misc.php';
        }
    }

    /**
     * Write the block and confirm the file now holds exactly those rules.
     *
     * @param string[] $lines
     */
    public static function add( string $file, array $lines ): bool {
        if ( '' === $file ) {
            return false;
        }
        self::load_misc();
        return insert_with_markers( $file, self::MARKER, $lines ) && self::in_place( $file, $lines );
    }

    /**
     * Whether the file holds the block with exactly these rules.
     *
     * The markers alone prove nothing: a block emptied by hand, or left empty
     * when the disk could not be written, still has them.
     *
     * @param string[] $lines
     */
    public static function in_place( string $file, array $lines ): bool {
        if ( '' === $file || ! is_readable( $file ) ) {
            return false;
        }
        self::load_misc();
        $rules = array_values( array_filter(
            extract_from_markers( $file, self::MARKER ),
            static fn ( string $line ): bool => '' !== trim( $line ) && ! str_starts_with( ltrim( $line ), '#' )
        ) );
        return $rules === $lines;
    }

    public static function has( string $file ): bool {
        return '' !== $file
            && is_readable( $file )
            && str_contains( (string) file_get_contents( $file ), '# BEGIN ' . self::MARKER );
    }

    public static function remove( string $file ): void {
        if ( ! self::has( $file ) ) {
            return;
        }

        $rest = (string) preg_replace(
            '/^# BEGIN ' . preg_quote( self::MARKER, '/' ) . '$.*?^# END ' . preg_quote( self::MARKER, '/' ) . '$\R?/ms',
            '',
            (string) file_get_contents( $file )
        );

        if ( '' === trim( $rest ) ) {
            wp_delete_file( $file );
            return;
        }

        global $wp_filesystem;
        require_once ABSPATH . 'wp-admin/includes/file.php';

        if ( WP_Filesystem() && $wp_filesystem ) {
            $wp_filesystem->put_contents( $file, rtrim( $rest ) . "\n", FS_CHMOD_FILE );
            return;
        }

        // Without direct disk access the rules still go away; only the two
        // empty marker lines stay behind.
        self::load_misc();
        insert_with_markers( $file, self::MARKER, [] );
    }
}
