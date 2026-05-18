<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'ago_harden_settings' );

// Clean up .htaccess in uploads directory.
$uploads_htaccess = wp_upload_dir()['basedir'] . '/.htaccess';
if ( file_exists( $uploads_htaccess ) ) {
    $content = file_get_contents( $uploads_htaccess );
    $marker  = '# BEGIN aGo Harden';
    if ( strpos( $content, $marker ) !== false ) {
        $content = preg_replace(
            '/# BEGIN aGo Harden.*?# END aGo Harden\s*/s',
            '',
            $content
        );
        file_put_contents( $uploads_htaccess, $content );
    }
}

// Clean up Options -Indexes from root .htaccess.
$root_htaccess = ABSPATH . '.htaccess';
if ( file_exists( $root_htaccess ) ) {
    $content = file_get_contents( $root_htaccess );
    $marker  = '# BEGIN aGo Harden';
    if ( strpos( $content, $marker ) !== false ) {
        $content = preg_replace(
            '/# BEGIN aGo Harden.*?# END aGo Harden\s*/s',
            '',
            $content
        );
        file_put_contents( $root_htaccess, $content );
    }
}
