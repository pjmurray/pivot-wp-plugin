<?php
/*
Plugin Name: ThemePivot
Version: 1.0
Plugin URI: http://themepivot.com/
Description: 
Author: ThemePivot
Author URI: http://themepivot.com/

Copyright 2012 

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with self program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/* 
@todo 
- check permissions on everything and make sure that the end user is notified if there's incorrec perms anywhere.
- check user has permission to perform backup / upload
- check user has permission to write files to default archive path
- create archive path if doesn't exist
- check for ziparchive dependency because that is heaps quicker.
- randomise the backup directory name for security
*/

// dont call the file directly
if (!defined(ABSPATH))
  return;

require_once('themepivot_config.php');
require_once('themepivot_capabilities.php');
require_once('themepivot_backup.php');
require_once('themepivot_deploy.php');

class ThemePivot {

	/*
	 * Init Action
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', get_class() . '::plugin_admin_init' );
		add_action( 'admin_menu', get_class() . '::plugin_admin_menu' );
	}
	
	/*
	 * Adds a subpage under "Tools"
	 *
	 * @return void
	 */
	public static function plugin_admin_menu() {
		$page = add_submenu_page( 'tools.php', 
															__('ThemePivot'), 
															__('ThemePivot'), 
															'manage_options', 
															'themepivot', 
															get_class() . '::admin_ui' );

		// hook script load onto our page only
		add_action('admin_print_styles-' . $page, get_class() . '::plugin_styles');
		add_action('admin_print_scripts-' . $page, get_class(). '::plugin_scripts');
	}

	/*
	 * Registers stylesheets and scripts for later loading
	 *
	 * @ return void
	 */
	public static function plugin_admin_init() {
		// register assets
		wp_register_style('themepivot_css', plugins_url('/assets/styles/themepivot.css', __FILE__), null, '1.0', 'screen');
		wp_register_script('themepivot_js', plugins_url('/assets/scripts/themepivot.js', __FILE__));
	}

	/*
	 * Queues scripts for loading
	 *
	 * @return void
	 */
	public static function plugin_scripts() {
		wp_enqueue_script( 'themepivot_js' );

		// embed the javascript file that makes the AJAX request
		//wp_enqueue_script( 'my-ajax-request', plugin_dir_url( __FILE__ ) . 'js/ajax.js', array( 'jquery' ) );
		// declare the URL to the file that handles th AJAX request (wp-admin/admin-ajax.php)
		//wp_localize_script( 'my-ajax-request', 'MyAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
	}

	/*
	 * Queues stylesheets for loading
	 *
	 * @return void
	 */
	public static function plugin_styles() {
		wp_enqueue_style('themepivot_css');
		//echo "<link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css'>";
	}

	/*
	 * UI for ThemePivot Page
	 *
	 * @return void
	 */
	public static function admin_ui() {
	?>
	<div class="wrap">
		<div id="headline">
		  <div class="tp_wrap">
			  <h1>Theme Pivot</h1>
			</div>
		</div>

    <?php

    $capabilities = new TP_Capabilities();
    $capabilities->display_warnings();

    if (!$capabilities->is_fatal()) {

      if( isset($_POST['submit']) && isset($_POST['job_id'])) {
        self::new_job($_POST['job_id']);
      }
      elseif( isset($_POST['makechanges']) && isset($_POST['soln_id'])) {
        self::deploy_job($_POST['soln_id']);
      }
      else { ?>
      <div class='section'>
      <h5>Manage Projects</h5>

      <form accept-charset="UTF-8" action="" class="new_project" id="new_pivot" method="POST"><div style="margin:0;padding:0;display:inline"><input name="utf8" type="hidden" value="&#x2713;" /></div>
        <input class="text" id="activation_key" name="job_id" placeholder="Enter Project Activation Key to upload site to marketplace" size="60" type="text" />
        <input class="submit" name="submit" type="submit" value="Submit Job" />
      </form>

      <br />

        <h5>Deploy Solution</h5>
        <form accept-charset="UTF-8" action="" class="new_project" id="completed_pivot" method="POST"><div style="margin:0;padding:0;display:inline"><input name="utf8" type="hidden" value="&#x2713;" /></div>
          <input class="text" id="completed_key" name="soln_id" placeholder="Enter Successful Project Key to make changes to your site" size="60" type="text" />
          <input class="submit" name="makechanges" type="submit" value="Change My Site!" />
        </form>
      </div>
      <?php
      }
      ?>
  </div>
  <?php
    }
  }

	/**
	 * Runs the process
	 *
	 **/
	public static function new_job( $job_id ) {
	
		$job = new TP_Backup($job_id);
		$result = $job->backup();

    print "<div class='section'>";

    if ( !is_wp_error ( $result ) ) {
      print "<div>";
      print "<p>Your website has been uploaded to ThemePivot and has been made available to our marketplace of developers to start making your changes</p>";
      print "<p><a href='http://pivot-market.herokuapp.com/projects/$job_id' target='_blank'>Click here</a>&nbsp;to view your project page</p>";
      print "</div>";
    } else {
      // errored
      $err_msg = $result->get_error_message();
      print "<div>";
      print "<p>Sorry, we've run into an error whilst taking a snapshot of your site</p>";
      print "<p>$err_msg</p>";
      print "</div>";
    }
    print "</div>";
	}

  public static function deploy_job( $soln_id ) {

    $solution = new TP_Deploy($soln_id);
    $result =  $solution->deploy();

    print "<div class='section'>";
    if ( !is_wp_error ( $result)) {
      print "<div>";
      print "<p>Your website has been updated with the solution you chose.</p>";
      print "</div>";
    } else {
      // errored
      $err_msg = $result->get_error_message();
      print "<div>";
      print "<p>Sorry, we've run into an error whilst making changes to your site</p>";
      print "<p>All changes have been reverted</p>";
      print "<p>$err_msg</p>";
      print "</div>";
    }

    print "</div>";
  }

	function verify_nonce() {

	}
}

ThemePivot::init();

?>
