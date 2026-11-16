<?php
/**
 * Unit test bootstrap: Brain Monkey stubs WordPress functions.
 *
 * @package ListenlyVoice
 */

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require_once __DIR__ . '/TestCase.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'LISTENLY_VERSION' ) ) {
	define( 'LISTENLY_VERSION', '0.0.0-test' );
	define( 'LISTENLY_URL', 'https://example.test/wp-content/plugins/listenly-voice/' );
	define( 'LISTENLY_PATH', dirname( __DIR__, 2 ) . '/' );
}

// After ABSPATH: plugin files exit without it.
require_once __DIR__ . '/FakeProvider.php';

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Just enough of WP_Error for unit tests.
	 */
	class WP_Error { // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
		/** @var string */
		private $code;
		/** @var string */
		private $message;
		/** @var array<mixed> */
		public $data = array();

		public function __construct( $code = '', $message = '', $data = array() ) {
			$this->code    = (string) $code;
			$this->message = (string) $message;
			$this->data    = (array) $data;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}

		public function add_data( $data ) {
			$this->data = array_merge( $this->data, (array) $data );
		}
	}
}
