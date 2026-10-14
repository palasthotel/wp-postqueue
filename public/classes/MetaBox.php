<?php

namespace Postqueue;

defined( 'ABSPATH' ) || exit;

class MetaBox extends Component\Component {

	const NONCE_ACTION = "postqueue_metabox";


	public function onCreate() {
		add_action( "init", array( $this, "init" ) );
	}

	/**
	 *
	 */
	function init() {
		
		/**
		* registers add_meta_boxes action that adds metaboxes to post edit
		*/
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		
		/**
		* register ajax callbacks for this metabox
		*/
		add_action( 'wp_ajax_postqueue_add_post', array( $this, 'ajax_callback_add_post' ) );
		add_action( 'wp_ajax_postqueue_remove_post', array( $this, 'ajax_callback_remove_post' ) );
	}
	
	/**
	* Hooks into WordPress' add_meta_boxes function.
	* Goes through screens (post types) and adds the meta box.
	*/
	public function add_meta_boxes() {

		if(!current_user_can($this->plugin->editor->getCapability())) return;

		// The block editor gets the document sidebar panel instead. A meta box would
		// still render there, in the compatibility area at the bottom of the page,
		// which is exactly the placement this replaces.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
			return;
		}

		$screens = get_post_types( array('public' => true) );

		foreach ( $screens as $screen ) {
			add_meta_box(
				'postqueue',
				__( 'Postqueue', 'postqueue' ),
				array( $this, 'render' ),
				$screen,
				'advanced',
				'default'
			);
		}
	}
	
	/**
	* Generates the HTML for the meta box
	* 
	* @param object $post WordPress post object
	*/
	public function render( $post ) {
		/**
		* Add css and javascript
		*/
		wp_enqueue_style(
			'postqueue-metabox-css',
			$this->plugin->url . 'dist/meta-box.css',
			array( ),
			filemtime($this->plugin->path."/dist/meta-box.css"),
			'all'
		);
		// The dependencies come from dist/meta-box.asset.php; the script has not needed
		// jQuery since it was rewritten in plain DOM.
		$this->plugin->assets->registerScript( 'postqueue-metabox', 'dist/meta-box.js' );
		wp_enqueue_script( 'postqueue-metabox' );
		wp_localize_script( 'postqueue-metabox', 'PostqueueMetaBoxL10n', array(
			'nonce' => wp_create_nonce( self::NONCE_ACTION ),
			'postremoved' => esc_html__( 'Post successfully removed from postqueue.', 'postqueue' ),
			'postadded' => esc_html__( 'Post successfully added to postqueue.', 'postqueue' ),
			'pleasechoose' => esc_html__( 'Please choose a postqueue!', 'postqueue' ),
			'erroroccured' => esc_html__( 'An error occured while sending the request. Please try again later.', 'postqueue' ),
			'removepostfromthispostqueue' => esc_html__( 'Remove post from this postqueue.', 'postqueue' ),
			'notstoredyet' => esc_html__( 'This post is not saved in any postqueue yet. You can add it to one below.', 'postqueue' )
		));
		$store = $this->plugin->store;
		require $this->plugin->path .'partials/postqueue-metabox.tpl.php';
	}
	
	/**
	* Callback function for the add post action
	*/
	/**
	 * Both AJAX callbacks used to run for any logged-in user, with no capability check
	 * and no nonce: wp_ajax_ hooks only require *some* login, so a subscriber could add
	 * a post to a curated queue or empty one. Queues drive what a site puts on its front
	 * page, so this gate is the same one the editor screen uses.
	 */
	private function checkAjaxRequest(): void {
		if ( ! current_user_can( $this->plugin->editor->getCapability() ) ) {
			wp_send_json_error( null, 403 );
		}
		check_ajax_referer( self::NONCE_ACTION );
	}

	function ajax_callback_add_post() {
		$this->checkAjaxRequest();

		$post_id = isset( $_POST['postid'] ) ? intval( $_POST['postid'] ) : 0;
		$queue_id = isset( $_POST['queueid'] ) ? intval( $_POST['queueid'] ) : 0;
		if ( $post_id <= 0 || $queue_id <= 0 ) {
			wp_send_json_error( null, 400 );
		}
		
		$position = \apply_filters(Plugin::FILTER_ADD_POSITION, null);
		
		if(in_array($position, ['first', 'last'])){
			$this->plugin->store->queue_add( $queue_id, $post_id, $position );
		}else{
			$this->plugin->store->add_post_to_queue( $post_id, $queue_id );
		}
		
		echo "Postqueue ID: " . (int) $queue_id;
		wp_die(); // this is required to terminate immediately and return a proper response
	}
	
	/**
	* Callback function for the remove post action
	*/
	function ajax_callback_remove_post() {
		$this->checkAjaxRequest();

		$post_id = isset( $_POST['postid'] ) ? intval( $_POST['postid'] ) : 0;
		$queue_id = isset( $_POST['queueid'] ) ? intval( $_POST['queueid'] ) : 0;
		if ( $post_id <= 0 || $queue_id <= 0 ) {
			wp_send_json_error( null, 400 );
		}
		$this->plugin->store->remove_post_from_queue( $post_id, $queue_id );
		echo "Postqueue ID: " . (int) $queue_id;
		wp_die(); // this is required to terminate immediately and return a proper response
	}
}
