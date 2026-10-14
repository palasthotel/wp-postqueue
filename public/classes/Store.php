<?php

namespace Postqueue;

defined( 'ABSPATH' ) || exit;

use Postqueue\Component\Database;

class Store extends Database {

	/**
	 * @var null|array
	 */
	private $queues;
    public string $tableQueues;
    public string $tableContents;

	/**
	 * Define the core functionality of the plugin.
	 */
	public function init() {
		$this->queues        = null;
		$this->tableQueues   = $this->wpdb->prefix . "ph_postqueues";
		$this->tableContents = $this->wpdb->prefix . "ph_postqueue_contents";
	}

	/**
	 * creates a new queue
	 *
	 * @param String $name
	 */
	public function create( $name ) {
		global $wpdb;
		$result          = (object) array();
		// Stored raw until now, and the meta box echoed it into the page - so a queue
		// name was a stored XSS vector. Escaping on output is the actual fix; this
		// keeps markup out of the column in the first place.
		$result->name    = sanitize_text_field( $name );
		$result->slug    = sanitize_title( $result->name );

		// A name made only of markup sanitises to nothing, and so does a blank one.
		// Without this the INSERT ran with an empty slug and failed against the unique
		// key, printing a database error and returning id 0 to the caller.
		if ( '' === $result->name || '' === $result->slug ) {
			$result->success = false;
			$result->id      = 0;
			$result->reason  = 'empty';

			return $result;
		}

		// Same reason, different cause: two queues whose names sanitise to the same slug
		// collide on the unique key. That is an everyday case now that queues can be
		// created by typing a name in the editor sidebar, so it has to be an answer
		// rather than a database error.
		if ( $this->slugExists( $result->slug ) ) {
			$result->success = false;
			$result->id      = 0;
			$result->reason  = 'duplicate';

			return $result;
		}

		$result->success = $wpdb->insert(
			$this->tableQueues,
			array(
				'name' => $result->name,
				'slug' => $result->slug,
			),
			array(
				'%s',
				'%s',
			)
		);
		$result->id      = $wpdb->insert_id;

		return $result;

	}

	/**
	 * returns all postqueues
	 *
	 * @return  array queues
	 */
	public function get_queues() {
		if ( $this->queues == null ) {
			$this->queues = $this->search();
		}

		return $this->queues;
	}

	/**
	 * returns queue by id
	 *
	 * @return array queue
	 */
	public function get_queue_by_id( $qid ) {
		return $this->get_queue( 'queue_id', $qid );
	}

	/**
	 * returns queue by slug
	 *
	 * @return array queue
	 */
	public function get_queue_by_slug( $slug ) {
		return $this->get_queue( 'slug', $slug );
	}

	/**
	 * helper function for returning a queue
	 *
	 * @return array queue
	 */
	private function get_queue( $key, $value ) {
		global $wpdb;

		// $key names a column, so it cannot be a placeholder - it is checked against a
		// list instead. $value comes from a request and is bound.
		if ( ! in_array( $key, array( 'queue_id', 'slug' ), true ) ) {
			return array();
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT name, slug, contents.id as cid, queue_id, post_id, position, title_overwrite as title"
				. " FROM %i as queue LEFT JOIN %i as contents ON (queue.id = contents.queue_id)"
				. " WHERE %i = %s ORDER BY position ASC",
				$this->tableQueues,
				$this->tableContents,
				$key,
				$value
			)
		);
		for ( $i = 0; $i < count( $results ); $i ++ ) {
			if ( false === get_post_status( $results[ $i ]->post_id ) || $results[ $i ]->post_id == null ) {
				unset( $results[ $i ] );
				continue;
			}
			$pid = $results[ $i ]->post_id;
			if ( $results[ $i ]->title != "" ) {
				$results[ $i ]->post_title = $results[ $i ]->title;
			} else {
				$results[ $i ]->post_title = get_the_title( $pid );
			}
		}

		return $results;
	}

	/**
	 * clears all contents of a queue
	 *
	 * @param int $queue_id
	 *
	 * @return void
	 */
	public function queue_clear( $queue_id ) {
		global $wpdb;
		$wpdb->delete(
			$this->tableContents,
			array( "queue_id" => $queue_id ),
			array( "%d" )
		);
	}

	public function queue_add_all( $qid, $post_ids ) {
		foreach ( $post_ids as $position => $post_id ) {
			$this->queue_add( $qid, $post_id, $position );
		}
	}

	public function queue_add_all_with_title( $qid, $post_ids, $titles ) {
		for ( $i = 0; $i < count( $post_ids ); $i ++ ) {
			$this->queue_add( $qid, $post_ids[ $i ], $i, $titles[ $i ] );
		}
	}

	public function queue_add( $queue_id, $post_id, $position = 'last', $title = "" ) {

		// add posts to queue without knowing the exact position
		global $wpdb;

		if ( 'last' === $position ) {
			$position = $this->get_last_position_of_queue( $queue_id );
			$position ++;
		} elseif ( 'first' === $position ) {
			$position = 0;

			// increase the position of every other item in the queue by 1
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE %i SET position=position+1 WHERE queue_id=%d ORDER BY position desc",
					$this->tableContents,
					$queue_id
				)
			);
		}

		$wpdb->insert(
			$this->tableContents,
			array(
				'queue_id'        => $queue_id,
				'post_id'         => $post_id,
				'position'        => $position,
				'title_overwrite' => $title,
			),
			array(
				"%d",
				"%d",
				"%d",
				'%s',
			)
		);

		// limit the length of the queue
		$global_limit_for_postqueue = apply_filters( Plugin::FILTER_POSTQUEUE_LIMITER, - 1 );

		if ( $global_limit_for_postqueue > 0 ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM %i WHERE position>%d AND queue_id=%d",
					$this->tableContents,
					$global_limit_for_postqueue - 1,
					$queue_id
				)
			);
		}
	}

	/**
	 * deletes all contents of a queue and the queue itself
	 *
	 * @param int $queue_id
	 *
	 * @return void
	 */
	public function delete_queue( $queue_id ) {
		global $wpdb;
		$wpdb->delete(
			$this->tableContents,
			array(
				"queue_id" => $queue_id,
			),
			array(
				"%d",
			)
		);
		$wpdb->delete(
			$this->tableQueues,
			array(
				"id" => $queue_id,
			),
			array(
				"%d",
			)
		);
	}

	/**
	 * removes all contents of given post_id in queue
	 *
	 * @param int $queue_id
	 * @param int $post_id
	 *
	 * @return void
	 */
	public function delete_queue_post( $queue_id, $post_id ) {
		global $wpdb;
		$wpdb->delete(
			$this->tableContents,
			array(
				"post_id"  => $post_id,
				"queue_id" => $queue_id,
			),
			array(
				"%d",
				"%d",
			)
		);
	}

	/**
	 * deletes all queue contents of the deleted post id
	 */
	public function clear_for_post_id( $post_id ) {
		global $wpdb;
		$wpdb->delete(
			$this->tableContents,
			array(
				"post_id" => $post_id,
			),
			array(
				"%d",
			)
		);
	}

	/**
	 * search queue
	 *
	 * @param string $name
	 *
	 * @return array
	 */
	public function search( $name = "" ) {
		global $wpdb;
		// The order comes from a filter, so it is code, not a request - but ORDER BY
		// cannot be a placeholder, and sanitize_sql_orderby() keeps it to column names
		// and directions.
		$order = sanitize_sql_orderby( (string) apply_filters( Plugin::FILTER_POSTQUEUE_SEARCH_ORDER, 'id ASC' ) );
		if ( ! $order ) {
			$order = 'id ASC';
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $order passed sanitize_sql_orderby() above; ORDER BY cannot take a placeholder
				"SELECT * FROM %i WHERE name LIKE %s ORDER BY {$order}",
				$this->tableQueues,
				'%' . $wpdb->esc_like( $name ) . '%'
			)
		);
	}

	/**
	 * adds a post to a postqueue
	 *
	 * @return true|false
	 */
	public function add_post_to_queue( $post_id, $queue_id ) {
		$this->queue_add( $queue_id, $post_id );
	}

	/**
	 * removes a post to a postqueue
	 *
	 * @return true|false
	 */
	public function remove_post_from_queue( $post_id, $queue_id ) {
		// @todo not sure if this is enough, because what about position?
		$this->delete_queue_post( $queue_id, $post_id );
	}

	/**
	 * returns list of postqueues a given post is in
	 *
	 * @return array
	 */
	public function get_queues_for_post( $post_id ) {
		global $wpdb;
		$result     = $wpdb->get_results(
			$wpdb->prepare( "SELECT queue_id FROM %i WHERE post_id = %d", $this->tableContents, $post_id )
		);
		$postqueues = array();
		foreach ( $result as $row ) {
			$postqueues[] = $this->get_queue_by_id( $row->queue_id );
		}

		return $postqueues;
	}

	/**
	 * checks if post is in a given postqueue
	 */
	public function is_post_in_queue( $post_id, $queue_id ) {
		global $wpdb;
		$result = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM %i WHERE post_id = %d AND queue_id = %d", $this->tableContents, $post_id, $queue_id )
		);
		if ( count( $result ) > 0 ) {
			return true;
		}

		return false;
	}

	/**
	 * return the last free position in a queue
	 *
	 * @param int $queue_id
	 *
	 * @return int $position
	 */
	public function get_last_position_of_queue( $queue_id ) {
		global $wpdb;
		return $wpdb->get_var(
			$wpdb->prepare( "SELECT MAX(position) FROM %i WHERE queue_id = %d", $this->tableContents, $queue_id )
		);
	}

	/**
	 * The ids of the queues a post belongs to.
	 *
	 * get_queues_for_post() returns a nested array of queue rows, which is more than a
	 * caller that just wants the ids needs.
	 *
	 * @return int[]
	 */
	public function get_queue_ids_for_post( int $post_id ): array {
		global $wpdb;

		return array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT queue_id FROM %i WHERE post_id = %d ORDER BY queue_id ASC",
					$this->tableContents,
					$post_id
				)
			)
		);
	}

	/**
	 * Number of items per queue, keyed by queue id.
	 *
	 * One grouped query instead of one per row, because the overview shows a count for
	 * every queue.
	 *
	 * @return array<int,int>
	 */
	public function get_queue_item_counts(): array {
		global $wpdb;

		$counts = array();
		$rows   = $wpdb->get_results(
			$wpdb->prepare( "SELECT queue_id, COUNT(*) as total FROM %i GROUP BY queue_id", $this->tableContents )
		);
		foreach ( $rows as $row ) {
			$counts[ (int) $row->queue_id ] = (int) $row->total;
		}

		return $counts;
	}

	/**
	 * The name of a queue, or an empty string.
	 */
	public function get_queue_name( int $queue_id ): string {
		global $wpdb;

		return (string) $wpdb->get_var(
			$wpdb->prepare( "SELECT name FROM %i WHERE id = %d", $this->tableQueues, $queue_id )
		);
	}

	/**
	 * Whether a queue with this slug exists.
	 */
	public function slugExists( string $slug ): bool {
		global $wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM %i WHERE slug = %s", $this->tableQueues, $slug )
		);
	}

	/**
	 * Whether a queue row exists.
	 *
	 * get_queue_by_id() joins the contents table, so an existing but empty queue comes
	 * back as an empty array - indistinguishable from "no such queue".
	 */
	public function queueExists( int $queue_id ): bool {
		global $wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM %i WHERE id = %d", $this->tableQueues, $queue_id )
		);
	}

	public function createTables() {
		parent::createTables();

		dbDelta( "CREATE TABLE IF NOT EXISTS $this->tableQueues (
				  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
				  `name` varchar(30) NOT NULL DEFAULT '',
				  `slug` varchar(30) NOT NULL DEFAULT '',
				  PRIMARY KEY (`id`),
				  UNIQUE KEY `slug` (`slug`),
				  KEY `name` (`name`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;" );

		dbDelta( "CREATE TABLE IF NOT EXISTS $this->tableContents  (
				  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
				  `queue_id` int(11) unsigned NOT NULL,
				  `post_id` int(11) unsigned NOT NULL,
				  `position` int(11) unsigned NOT NULL DEFAULT 0,
				  `title_overwrite` varchar(255) NOT NULL DEFAULT '',
				  PRIMARY KEY (`id`),
				  UNIQUE KEY `post_in_queue` (`post_id`, `queue_id`),
				  UNIQUE KEY `position_in_queue` (`position`, `queue_id`),
				  KEY `queue_id` (`queue_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;" );

	}
}
