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

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Just enough of WP_Post for unit tests.
	 */
	class WP_Post { // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
		/** @var int */
		public $ID = 0;
		/** @var string */
		public $post_status = 'publish';
		/** @var string */
		public $post_type = 'post';
		/** @var string */
		public $post_content = '';
		/** @var string */
		public $post_title = '';
		/** @var string */
		public $post_excerpt = '';
	}
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

		public function get_error_data() {
			return $this->data ? $this->data : null;
		}

		// Like WordPress: replaces the data rather than merging it.
		public function add_data( $data ) {
			$this->data = (array) $data;
		}
	}
}
