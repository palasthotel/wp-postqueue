<?php

namespace Postqueue;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * The overview of postqueues, as the table WordPress uses everywhere else.
 *
 * WP_List_Table is what gives Posts, Pages and Users their search box, sortable
 * columns, row actions, bulk actions and pagination. Core marks the class private, but
 * reimplementing it means reimplementing all of that and still not matching it - so it
 * is used here the way the rest of the ecosystem uses it.
 */
class QueuesListTable extends \WP_List_Table {

	private Store $store;
	private string $pageSlug;

	public function __construct( Store $store, string $pageSlug ) {
		$this->store    = $store;
		$this->pageSlug = $pageSlug;

		parent::__construct( [
			'singular' => 'postqueue',
			'plural'   => 'postqueues',
			'ajax'     => false,
		] );
	}

	public function get_columns(): array {
		return [
			'cb'        => '<input type="checkbox" />',
			'name'      => _x( 'Name', 'list table', 'postqueue' ),
			'slug'      => _x( 'Slug', 'list table', 'postqueue' ),
			'items'     => _x( 'Posts', 'list table', 'postqueue' ),
			'shortcode' => _x( 'Shortcode', 'list table', 'postqueue' ),
		];
	}

	protected function get_sortable_columns(): array {
		return [
			'name'  => [ 'name', false ],
			'slug'  => [ 'slug', false ],
			'items' => [ 'items', false ],
		];
	}

	protected function get_bulk_actions(): array {
		return [
			'delete' => _x( 'Delete', 'list table', 'postqueue' ),
		];
	}

	protected function column_cb( $item ): string {
		return sprintf(
			'<input type="checkbox" name="queues[]" value="%d" />',
			(int) $item['id']
		);
	}

	protected function column_name( $item ): string {
		$editUrl = add_query_arg(
			[ 'page' => $this->pageSlug, 'queue' => (int) $item['id'] ],
			admin_url( 'tools.php' )
		);
		$deleteUrl = wp_nonce_url(
			add_query_arg(
				[ 'page' => $this->pageSlug, 'action' => 'delete', 'queue' => (int) $item['id'] ],
				admin_url( 'tools.php' )
			),
			Editor::NONCE_ACTION
		);

		$actions = [
			'edit'   => sprintf(
				'<a href="%s">%s</a>',
				esc_url( $editUrl ),
				esc_html_x( 'Edit', 'list table', 'postqueue' )
			),
			'delete' => sprintf(
				'<a href="%s" class="submitdelete" onclick="return confirm(%s)">%s</a>',
				esc_url( $deleteUrl ),
				esc_js( wp_json_encode( __( 'Delete this postqueue? Its order is lost, the posts are not touched.', 'postqueue' ) ) ),
				esc_html_x( 'Delete', 'list table', 'postqueue' )
			),
		];

		return sprintf(
			'<strong><a class="row-title" href="%s">%s</a></strong>%s',
			esc_url( $editUrl ),
			esc_html( $item['name'] ),
			$this->row_actions( $actions )
		);
	}

	protected function column_slug( $item ): string {
		return esc_html( $item['slug'] );
	}

	protected function column_items( $item ): string {
		$editUrl = add_query_arg(
			[ 'page' => $this->pageSlug, 'queue' => (int) $item['id'] ],
			admin_url( 'tools.php' )
		);

		return sprintf( '<a href="%s">%d</a>', esc_url( $editUrl ), (int) $item['items'] );
	}

	protected function column_shortcode( $item ): string {
		return sprintf(
			'<code>[postqueue slug="%s"]</code>',
			esc_html( $item['slug'] )
		);
	}

	/**
	 * Cells of columns this class does not know.
	 *
	 * A column added through "manage_{$screen->id}_columns" has no column_<name>() here,
	 * so WP_List_Table falls back to this method - which without the filter below would
	 * print an empty cell. Named after core's own hook on the terms table, and a filter
	 * rather than an action for the same reason: the caller wants the cell back, not
	 * whatever happened to be echoed.
	 *
	 * @param array  $item        the queue row, with id, name, slug and items
	 * @param string $column_name
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		return (string) apply_filters(
			"manage_{$this->screen->id}_custom_column",
			'',
			$column_name,
			$item
		);
	}

	public function no_items() {
		esc_html_e( 'No postqueues yet. Create one above.', 'postqueue' );
	}

	public function prepare_items() {
		// _column_headers is deliberately left alone. WP_List_Table's constructor hooks
		// this class's get_columns() into "manage_{$screen->id}_columns" at priority 0,
		// and get_column_info() only consults that filter while _column_headers is
		// unset - so assigning it here, as this did, switched off the one extension
		// point another plugin has for adding a column. Same for sortable columns and
		// for the hidden-columns box in Screen Options.
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$queues = '' === $search ? $this->store->get_queues() : $this->store->search( $search );
		$counts = $this->store->get_queue_item_counts();

		$rows = array_map( function ( $queue ) use ( $counts ) {
			return [
				'id'    => (int) $queue->id,
				'name'  => $queue->name,
				'slug'  => $queue->slug,
				'items' => $counts[ (int) $queue->id ] ?? 0,
			];
		}, $queues );

		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_key( $_REQUEST['orderby'] ) : 'name';
		$order   = ( isset( $_REQUEST['order'] ) && 'desc' === sanitize_key( wp_unslash( $_REQUEST['order'] ) ) ) ? 'desc' : 'asc';
		if ( in_array( $orderby, [ 'name', 'slug', 'items' ], true ) ) {
			usort( $rows, function ( $a, $b ) use ( $orderby ) {
				return 'items' === $orderby
					? $a['items'] <=> $b['items']
					: strnatcasecmp( $a[ $orderby ], $b[ $orderby ] );
			} );
			if ( 'desc' === $order ) {
				$rows = array_reverse( $rows );
			}
		}

		$perPage = 20;
		$total   = count( $rows );
		$page    = $this->get_pagenum();

		$this->items = array_slice( $rows, ( $page - 1 ) * $perPage, $perPage );
		$this->set_pagination_args( [
			'total_items' => $total,
			'per_page'    => $perPage,
			'total_pages' => (int) ceil( $total / $perPage ),
		] );
	}
}
