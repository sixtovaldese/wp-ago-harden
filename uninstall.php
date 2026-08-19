<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'agoharden_settings' );

/**
 * Strip the plugin's block from an .htaccess file, and delete the file when
 * nothing else is left in it.
 *
 * The module classes are not available during uninstall, so the removal is
 * repeated here rather than reused.
 *
 * @param string $agoharden_path Absolute path to the .htaccess file.
 */
function agoharden_strip_rules( string $agoharden_path ): void {
    if ( ! file_exists( $agoharden_path ) ) {
        return;
    }

    $agoharden_content = file_get_contents( $agoharden_path );

    if ( strpos( $agoharden_content, '# BEGIN aGo Harden' ) === false ) {
        return;
    }

    $agoharden_content = preg_replace(
        '/# BEGIN aGo Harden.*?# END aGo Harden\s*/s',
        '',
        $agoharden_content
    );

    if ( trim( $agoharden_content ) === '' ) {
        wp_delete_file( $agoharden_path );
        return;
    }

    // These are the two .htaccess files the plugin wrote to, so the paths are
    // the point: there is no alternative location for either of them.
    // phpcs:ignore PluginCheck.CodeAnalysis.WriteFile.ABSPATHDetected
    file_put_contents( $agoharden_path, $agoharden_content );
}

agoharden_strip_rules( wp_upload_dir()['basedir'] . '/.htaccess' );
agoharden_strip_rules( ABSPATH . '.htaccess' );
