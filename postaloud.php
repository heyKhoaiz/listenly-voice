<?php
/**
 * Plugin Name:       PostAloud
 * Plugin URI:        https://github.com/heyKhoaiz/postaloud
 * Description:       Let visitors listen to your posts with natural voices, an accessible audio player and read-along highlighting.
 * Version:           0.1.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            PostAloud
 * Author URI:        https://profiles.wordpress.org/shaikhkhoaiz/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       postaloud
 * Domain Path:       /languages
 *
 * @package PostAloud
 */

defined( 'ABSPATH' ) || exit;

define( 'POSTALOUD_VERSION', '0.1.0' );
define( 'POSTALOUD_FILE', __FILE__ );
define( 'POSTALOUD_PATH', plugin_dir_path( __FILE__ ) );
define( 'POSTALOUD_URL', plugin_dir_url( __FILE__ ) );

if ( is_readable( POSTALOUD_PATH . 'vendor/autoload.php' ) ) {
	require_once POSTALOUD_PATH . 'vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( $class_name ) {
			$prefix = 'PostAloud\\';
			if ( 0 !== strpos( $class_name, $prefix ) ) {
				return;
			}
			$file = POSTALOUD_PATH . 'backend/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	);
}

register_activation_hook( __FILE__, array( \PostAloud\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \PostAloud\Deactivator::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( \PostAloud\Plugin::class, 'boot' ), 10 );
