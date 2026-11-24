<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// The autoloader is not registered during uninstall, so the classes that know
// where the rules live are loaded by hand. The removal is the same code the
// switches use, not a second copy of it.
require_once __DIR__ . '/src/Htaccess.php';
require_once __DIR__ . '/src/Modules/PhpUploads.php';
require_once __DIR__ . '/src/Modules/DirectoryListing.php';

delete_option( 'agoharden_settings' );

AgoLab\Harden\Modules\PhpUploads::remove_htaccess();
AgoLab\Harden\Modules\DirectoryListing::remove_htaccess();
