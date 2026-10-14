<?php

namespace Postqueue;

defined( 'ABSPATH' ) || exit;

use Postqueue\Component\Component;

/**
 * The Postqueues screen under Tools.
 *
 * Two views on one page, the way core splits a list screen from an edit screen:
 *
 * - without ?queue=  the overview, a WP_List_Table plus an "add new" form
 * - with ?queue=<id> the queue itself, where the order is edited
 *
 * Creating and deleting are ordinary form posts handled before anything is rendered, so
 * they can redirect afterwards - the pattern core uses to keep a reload from repeating
 * the action.
 */
class Editor extends Component {

	const PAGE_SLUG    = 'tools-postqueue';
	const NONCE_ACTION = 'postqueue_admin';

	private ?QueuesListTable $table = null;

	public function onCreate() {
		add_action( 'admin_menu', array( $this, 'tools_page' ) );
	}

	/**
	 * @return string
	 */
	public function getCapability() {
		return apply_filters( Plugin::FILTER_POSTQUEUE_EDIT_CAPABILITY, 'manage_options' );
	}

	public function tools_page() {
		$hook = add_submenu_page(
			'tools.php',
			_x( 'Postqueues', 'admin page', 'postqueue' ),
			_x( 'Postqueues', 'admin menu', 'postqueue' ),
			$this->getCapability(),
			self::PAGE_SLUG,
			array( $this, 'render' )
		);

		if ( $hook ) {
			// Runs before the page is rendered, which is what makes a redirect after a
			// create or delete possible, and it is where WP_List_Table wants building.
			add_action( "load-$hook", array( $this, 'load' ) );
		}
	}

	public function load() {
		if ( ! current_user_can( $this->getCapability() ) ) {
			return;
		}

		$this->handleActions();

		if ( ! $this->currentQueueId() ) {
			$this->table = new QueuesListTable( $this->plugin->store, self::PAGE_SLUG );
			$this->table->prepare_items();
		}
	}

	/**
	 * The queue being edited, or 0 for the overview.
	 */
	private function currentQueueId(): int {
		$id = isset( $_GET['queue'] ) ? (int) $_GET['queue'] : 0;

		return ( $id > 0 && $this->plugin->store->queueExists( $id ) ) ? $id : 0;
	}

	private function pageUrl( array $args = array() ): string {
		return add_query_arg(
			array_merge( array( 'page' => self::PAGE_SLUG ), $args ),
			admin_url( 'tools.php' )
		);
	}

	/**
	 * Create and delete, then redirect. Nothing is rendered from here.
	 */
	private function handleActions(): void {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : '';
		$bulk   = isset( $_REQUEST['action2'] ) && 'delete' === sanitize_key( $_REQUEST['action2'] );

		if ( 'create' === $action ) {
			check_admin_referer( self::NONCE_ACTION );

			$name   = isset( $_POST['postqueue_name'] ) ? sanitize_text_field( wp_unslash( $_POST['postqueue_name'] ) ) : '';
			$result = $this->plugin->store->create( $name );

			wp_safe_redirect( $this->pageUrl( array(
				'message' => ! empty( $result->success )
					? 'created'
					: ( ( isset( $result->reason ) && 'duplicate' === $result->reason ) ? 'duplicate' : 'invalid' ),
			) ) );
			exit;
		}

		if ( 'delete' === $action || $bulk ) {
			$ids = array();
			if ( isset( $_REQUEST['queues'] ) && is_array( $_REQUEST['queues'] ) ) {
				$ids = array_map( 'intval', $_REQUEST['queues'] );
			} elseif ( isset( $_REQUEST['queue'] ) ) {
				$ids = array( (int) $_REQUEST['queue'] );
			}

			if ( empty( $ids ) ) {
				return;
			}

			// A single delete is a link, so it carries _wpnonce; a bulk delete comes from
			// the list table's form, which carries its own nonce field.
			if ( isset( $_REQUEST['queues'] ) ) {
				check_admin_referer( 'bulk-postqueues' );
			} else {
				check_admin_referer( self::NONCE_ACTION );
			}

			foreach ( $ids as $id ) {
				if ( $id > 0 ) {
					do_action( 'ph_postqueue_deleting', $id );
					$this->plugin->store->delete_queue( $id );
				}
			}

			wp_safe_redirect( $this->pageUrl( array( 'message' => 'deleted', 'count' => count( $ids ) ) ) );
			exit;
		}
	}

	private function renderNotice(): void {
		$message = isset( $_GET['message'] ) ? sanitize_key( $_GET['message'] ) : '';
		if ( '' === $message ) {
			return;
		}

		$count   = isset( $_GET['count'] ) ? (int) $_GET['count'] : 1;
		$notices = array(
			'created'   => array( 'success', __( 'Postqueue created.', 'postqueue' ) ),
			'duplicate' => array( 'error', __( 'A postqueue with that name already exists.', 'postqueue' ) ),
			'invalid'   => array( 'error', __( 'Please enter a name for the postqueue.', 'postqueue' ) ),
			'deleted'   => array(
				'success',
				sprintf(
					/* translators: %s: number of deleted postqueues */
					_n( '%s postqueue deleted.', '%s postqueues deleted.', $count, 'postqueue' ),
					number_format_i18n( $count )
				),
			),
		);

		if ( ! isset( $notices[ $message ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $notices[ $message ][0] ),
			esc_html( $notices[ $message ][1] )
		);
	}

	public function render() {
		$queueId = $this->currentQueueId();

		echo '<div class="wrap">';
		$queueId ? $this->renderQueue( $queueId ) : $this->renderOverview();
		echo '</div>';
	}

	private function renderOverview(): void {
		?>
		<h1 class="wp-heading-inline"><?php echo esc_html_x( 'Postqueues', 'admin page', 'postqueue' ); ?></h1>
		<hr class="wp-header-end" />
		<?php $this->renderNotice(); ?>

		<div id="col-container" class="wp-clearfix">
			<div id="col-left">
				<div class="col-wrap">
					<div class="form-wrap">
						<h2><?php esc_html_e( 'Add new postqueue', 'postqueue' ); ?></h2>
						<form method="post" action="<?php echo esc_url( $this->pageUrl() ); ?>">
							<?php wp_nonce_field( self::NONCE_ACTION ); ?>
							<input type="hidden" name="action" value="create" />
							<div class="form-field form-required">
								<label for="postqueue_name"><?php esc_html_e( 'Name', 'postqueue' ); ?></label>
								<input name="postqueue_name" id="postqueue_name" type="text" value="" size="40" required />
								<p><?php esc_html_e( 'Shown wherever a queue is picked. The slug is derived from it.', 'postqueue' ); ?></p>
							</div>
							<?php submit_button( __( 'Add new postqueue', 'postqueue' ) ); ?>
						</form>
					</div>
				</div>
			</div>
			<div id="col-right">
				<div class="col-wrap">
					<form method="get" action="<?php echo esc_url( admin_url( 'tools.php' ) ); ?>">
						<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
						<?php $this->table->search_box( __( 'Search postqueues', 'postqueue' ), 'postqueue' ); ?>
					</form>
					<form method="post" action="<?php echo esc_url( $this->pageUrl() ); ?>">
						<?php
						wp_nonce_field( 'bulk-postqueues' );
						$this->table->display();
						?>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	private function renderQueue( int $queueId ): void {
		wp_enqueue_style( Plugin::HANDLE_EDITOR_CSS );
		wp_enqueue_script( Plugin::HANDLE_EDITOR_JS );
		// Only the queue id. The old template also handed over a listUrl the screen never
		// read, and a feedUrl from the site option "ph-postqueue-feeds-url" - an option
		// that only ph-postqueue-feeds ever wrote, the 2015 attempt at a feeds plugin
		// that was never published and is now archived. Postqueue Feeds, the one on
		// wordpress.org, does not know that option at all.
		wp_add_inline_script(
			Plugin::HANDLE_EDITOR_JS,
			'window.PostQueueScreen = ' . wp_json_encode( array(
				'queueId' => $queueId,
			) ) . ';',
			'before'
		);
		?>
		<h1 class="wp-heading-inline">
			<?php
			printf(
				/* translators: %s: name of the postqueue being edited */
				esc_html__( 'Edit Postqueue: %s', 'postqueue' ),
				esc_html( $this->plugin->store->get_queue_name( $queueId ) )
			);
			?>
		</h1>
		<a href="<?php echo esc_url( $this->pageUrl() ); ?>" class="page-title-action">
			<?php esc_html_e( 'Back to postqueues', 'postqueue' ); ?>
		</a>
		<hr class="wp-header-end" />
		<div id="post-queue-editor" class="ph-postqueue"></div>
		<?php
	}
}
