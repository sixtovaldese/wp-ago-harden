<?php
/**
 * Plugin Name: aGo Harden
 * Plugin URI:  https://ago.cl/herramientas/
 * Description: Security hardening dashboard with toggles. Custom login URL, brute-force protection, security headers, file editor lockdown and a real-time security score.
 * Version:     1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author:      aGo Lab
 * Author URI:  https://ago.cl/
 * License:     GPL-2.0-or-later
 * Text Domain: ago-harden
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'AGOHARDEN_VERSION', '1.0.0' );
define( 'AGOHARDEN_FILE', __FILE__ );
define( 'AGOHARDEN_PATH', plugin_dir_path( __FILE__ ) );
define( 'AGOHARDEN_URL', plugin_dir_url( __FILE__ ) );

// PSR-4 Autoloader
spl_autoload_register( function ( string $class ): void {
    $prefix = 'AgoLab\\Harden\\';
    if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
        return;
    }
    $relative = substr( $class, strlen( $prefix ) );
    $file     = AGOHARDEN_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

// Boot
add_action( 'plugins_loaded', [ AgoLab\Harden\Plugin::class, 'instance' ] );

// Lifecycle hooks go at file scope: on activation WordPress loads this file
// after plugins_loaded has already fired, so a registration made from inside
// the Plugin constructor would arrive too late and never run.
register_activation_hook( __FILE__, [ AgoLab\Harden\Plugin::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ AgoLab\Harden\Plugin::class, 'deactivate' ] );
