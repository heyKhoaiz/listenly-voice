<?php
/**
 * Plugin Name:       Listenly Voice
 * Description:       Let visitors listen to your posts with natural voices, an accessible audio player and read-along highlighting.
 * Version:           0.1.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Listenly
 * Author URI:        https://profiles.wordpress.org/shaikhkhoaiz/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       listenly-voice
 * Domain Path:       /languages
 *
 * @package ListenlyVoice
 */

defined( 'ABSPATH' ) || exit;

define( 'LISTENLY_VERSION', '0.1.0' );
define( 'LISTENLY_FILE', __FILE__ );
define( 'LISTENLY_PATH', plugin_dir_path( __FILE__ ) );
define( 'LISTENLY_URL', plugin_dir_url( __FILE__ ) );

if ( is_readable( LISTENLY_PATH . 'vendor/autoload.php' ) ) {
	require_once LISTENLY_PATH . 'vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( $class_name ) {
			$prefix = 'ListenlyVoice\\';
			if ( 0 !== strpos( $class_name, $prefix ) ) {
				return;
			}
			$file = LISTENLY_PATH . 'backend/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	);
}

register_activation_hook( __FILE__, array( \ListenlyVoice\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \ListenlyVoice\Deactivator::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( \ListenlyVoice\Plugin::class, 'boot' ), 10 );
