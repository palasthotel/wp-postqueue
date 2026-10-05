=== Postqueue ===
Contributors: palasthotel, edwardbock, janaeggebrecht
Donate link: https://palasthotel.de/
Tags: loop, order posts, queue, gutenberg, curated
Requires at least: 6.6
Tested up to: 7.1
Stable tag: 2.1.2
Requires PHP: 7.4
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Allows you to create you very own loop order of posts

== Description ==

Sometimes you cannot use any parameter to order your desired post order but have to do this order by hand. Postqueue allows you to do that.

This Plugin provides a new Box for [Grid](http://wordpress.org/plugins/grid/ "Grid Landingpage Editor").

== Installation ==

1. Install the plugin through **Plugins → Add New**, or upload it to `/wp-content/plugins/postqueue/`.
2. Activate it through the **Plugins** menu.
3. Create queues under **Tools → Postqueues**.

== Frequently Asked Questions ==

= Where do I add a post to a queue? =

In the block editor, in the document sidebar next to the other post settings - the same
place the permalink and the categories live. It behaves like the category panel: ticking
a queue marks the post as changed, and the change is written when you save or update the
post. Nothing is sent while you are still clicking.

You can create a queue right there too, the way you add a category: the **Add new
postqueue** link opens a name field. Creating happens immediately - only the assignment
waits for the post to be saved.

A search field appears once there are more than eight queues, filterable with
`postqueue_panel_search_threshold`.

The classic editor keeps the meta box it always had. Only one of the two appears, never
both.

= Where do I manage the queues themselves? =

Under **Tools → Postqueues**. The overview is the same kind of table WordPress uses for
posts and pages, with a search box, sortable columns and bulk deletion, and a form next
to it for adding one. Opening a queue shows its posts in order.

Inside a queue you can reorder by dragging, or with the **Move up** and **Move down**
buttons - those also work from the keyboard. The order is applied when you press
**Save**; leaving the page with unsaved changes asks first.

= How do I output a queue with blocks? =

Insert the **Postqueue** block. It is a variation of the core Query Loop, so it comes
with the same pagination, layout options and Post Template inner blocks as any other
loop - pick a queue in the block sidebar instead of a category. The posts appear in the
order the queue defines, and an empty queue outputs nothing rather than everything.

The `[postqueue]` shortcode and the theme templates still work as before:
`[postqueue slug="my-queue" viewmode="" offset="0" limit="-1"]`. Only `slug` is
required; `viewmode` is one of the view modes registered with `postqueue_viewmodes`,
`offset` skips that many posts, and `limit` caps how many are shown (`-1` for all).



== Screenshots ==


== Changelog ==

= 2.1.2 =
**Bug Fixes**
* escape the postqueue shortcode's output (1d8c9b1)
* link the empty meta box to the Postqueues screen (558b7cc)

= 2.1.1 =
**Bug Fixes**
* hide the Query Loop's post type, order and sticky controls (0d94c3c)

= 2.1.0 =
**Features**
* let plugins add columns to the Postqueues table (e78cd47)

**Bug Fixes**
* render the blockX block on the front end (cf62c79)

= 2.0.0 =
**⚠ BREAKING CHANGES**
* the editor bundle no longer exports the queue overview, and the page template partials/ph-postqueue-editor.tpl.php is removed. A theme or plugin that included that template, or that styled the .queues-list markup the old React list produced, has to be adjusted.
* the ph_postqueue_* admin-ajax actions are gone. Nothing in this plugin used them, but code outside it that called them will stop working and has to move to the REST routes under postqueue/v1.

**Features**
* create postqueues from the sidebar panel, and match the category layout (638093f)
* filter the core Query Loop by postqueue (1bb79ed)
* move the queue assignment into the block editor sidebar (7ca3cc3)
* rebuild the Postqueues overview as a WordPress list table (37102c3)
* remove the deprecated admin-ajax endpoints (af63bc7)
* reorder queue items with the keyboard, and drop react-dnd (a528940)

**Bug Fixes**
* answer a duplicate queue name instead of a database error (cb86ff2)
* bind the values in every database query (2055a8f)
* complete the German translations (bc561e3)
* escape the queue name instead of running it as script (2533838)
* escape the queue name instead of running it as script (bc4ae10)
* name the postqueue in the screen heading (41bb4f1)
* render the queue editor with createRoot (5579734)
* repair the queue items table (515ddab)
* show the drag handle icon (2a8b509)
* stop any logged-in user from editing the queues (fa4239a)
* stop enqueueing the stylesheet against a script handle (7a644b7)

= 1.5.1 =
* Update: Update dependencies

= 1.5.0 =
* Feature: Added blockx block
* Feature: Added headless preparation for blockx block

= 1.4.2 =
 * Bugfix: Query only for public post types in builder search

= 1.4.1 =
 * Bugfix: meta box did not appear on custom post types sometimes

= 1.4.0 =
 * Visual update: Date and status information in post queue editor

= 1.3.1 =
 * Bugfix: PHP7.3 compatibility issue fixed

= 1.3.0 =
 * Post queue editor UI with React

= 1.2.4 =
 * Feature - Limit postqueue length with limiter. Default is -1 no limitation.

= 1.2.3 =
 * Optimization: JS and CSS versions via file time.

= 1.2.2 =
 * Optimization: Grid box with link to postqueue tools
 * Bugfix: Changed ajax methods from GET to POST to avoid request limits

= 1.2.1 =
 * Feature: new filter to change the postqueue search order
 * added some security conditions to ajax functions
 * new filter for default position if adding post to postqueue via post meta box

= 1.2.0 =
 * Optimization: New filter for default add to postqueue behavior of post meta box.
 * Security fix: Check capabilities within ajax calls.

= 1.2.0 =
 * New metabox that displays postqueues related to and lets you add post to postqueues

= 1.1.6 =
 * Modify postqueue rights filter
 * PHP namespacing changes
 * Postqueue class deprecated and is not \Postqueue\Plugin
 * Use public function postqueue_get() instead of global $postqueue

= 1.1.5 =
 * Empty queue fix
 * Deleted queue ends in infinity loop fix

= 1.1.4 =
 * Theme template moved to theme/plugin-parts/*

= 1.1.3 =
 * Extend grid postqueue box action

= 1.1.2 =
 * Tinymce preview optimized
 * template file

= 1.1.1 =
 * Tested up to 4.6.1

= 1.1.0 =
 * UI optimization
 * Shortcode support

= 1.0.7 =
 * Fixed empty queues bug

= 1.0.6 =
 * Post id zero in postqueue fix

= 1.0.5 =
 * Future posts can be added to queues

= 1.0.4 =
 * Title overwrite option

= 1.0.3 =
 * Grid box

= 1.0.2 =
 * Copy past bugfix

= 1.0.1 =
 * Table prefix bugfix

= 1.0 =
 * First release

== Upgrade Notice ==

Move theme template from theme/postqueue.php to theme/plugin-parts/postqueue.tpl.php

== Arbitrary section ==



