<?php
/**
 * Plugin Name: aGo Harden
 * Plugin URI:  https://ago.cl/herramientas/
 * Description: Security hardening dashboard with toggles. Custom login URL, brute-force protection, security headers, file editor lockdown and a real-time security score.
 * Version:     1.0.0
 * Requires PHP: 8.1
 * Author:      aGo Lab
 * Author URI:  https://ago.cl/
 * License:     GPL-2.0-or-later
 * Text Domain: ago-harden
 */

defined( 'ABSPATH' ) || exit;

define( 'AGO_HARDEN_VERSION', '1.0.0' );
define( 'AGO_HARDEN_FILE', __FILE__ );
define( 'AGO_HARDEN_PATH', plugin_dir_path( __FILE__ ) );
define( 'AGO_HARDEN_URL', plugin_dir_url( __FILE__ ) );

// PSR-4 Autoloader
spl_autoload_register( function ( string $class ): void {
    $prefix = 'AgoLab\\Harden\\';
    if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
        return;
    }
    $relative = substr( $class, strlen( $prefix ) );
    $file     = AGO_HARDEN_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

// Boot
add_action( 'plugins_loaded', [ AgoLab\Harden\Plugin::class, 'instance' ] );
