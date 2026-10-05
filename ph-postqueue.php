<?php
/**
 * Plugin Name:       Postqueue - DEV
 * Description:       Loads public/ph-postqueue.php when this repository is checked out into wp-content/plugins/. Not shipped - the released plugin is the content of public/.
 * Version:           X.X.X
 * Requires at least: 6.6
 * Tested up to:      7.1
 * Author:            Palasthotel <webmaster@palasthotel.de>
 * Author URI:        https://palasthotel.de
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       postqueue
 */

include dirname(__FILE__)."/public/ph-postqueue.php";

register_activation_hook(__FILE__, function($multisite){
	postqueue_plugin()->onActivation($multisite);
});

register_deactivation_hook(__FILE__, function($multisite){
	postqueue_plugin()->onDeactivation($multisite);
});
