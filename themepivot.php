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
- check wp isn't multisite, die if it is
- check user has permission to perform backup / upload
- check for ziparchive dependency because that is heaps quicker.
*/

// dont call the file directly
if (!defined('ABSPATH'))
  return;

require_once('themepivot_config.php');
require_once('themepivot_capabilities.php');
require_once('themepivot_backup.php');
require_once('themepivot_deploy.php');

class ThemePivot {


  private $options;
  private $capabilities;

  function __construct() {

    $this->options = TP_Options::init();
    //$this->options->delete_option('active_project');

    if (is_admin())
      $this->add_admin_actions();
  }

  public static function &init() {
    static $instance = false;

    if ( !$instance ) {
      $instance = new ThemePivot();
    }

    return $instance;
  }

  function add_admin_actions() {
    add_action( 'admin_init', array($this, 'admin_init' ));
    add_action( 'admin_menu', array($this, 'admin_menu'));
  }

  // adds subpage under tools
  function admin_menu() {
    $page = add_submenu_page( 'tools.php', __('ThemePivot'), __('ThemePivot'), 'manage_options', 'themepivot', array($this,'ui'));

    // hook script load onto our page only
    add_action('admin_print_styles-' . $page, array($this, 'plugin_styles'));
    add_action('admin_print_scripts-' . $page, array($this, 'plugin_scripts'));
  }

  function admin_init() {
    // register assets
    wp_register_style('themepivot_css', plugins_url('/assets/styles/themepivot.css', __FILE__), null, '1.0', 'screen');
    wp_register_script('themepivot_js', plugins_url('/assets/scripts/themepivot.js', __FILE__));
    // buffer page load
    ob_start();
  }


  // queue scripts
  function plugin_scripts() {
    wp_enqueue_script( 'themepivot_js' );

    // embed the javascript file that makes the AJAX request
    //wp_enqueue_script( 'my-ajax-request', plugin_dir_url( __FILE__ ) . 'js/ajax.js', array( 'jquery' ) );
    // declare the URL to the file that handles th AJAX request (wp-admin/admin-ajax.php)
    //wp_localize_script( 'my-ajax-request', 'MyAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
  }

  // queue stylesheets
  function plugin_styles() {
    wp_enqueue_style('themepivot_css');
    //echo "<link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css'>";
  }

  // ui
  function ui() {

    if (!current_user_can( 'manage_options'))
      return;

    $this->ui_header();

    $this->capabilities = new TP_Capabilities($this->options);
    $this->capabilities->determine_capabilities();
    if ($this->capabilities->is_fatal()) {
      $this->capabilities->ui_warnings();
      return;
    }

    // todo: make capabilities set a message and redirect with ?error
    /*
    if (!empty($_GET['error'])) {
      $this->error_notice();
    }
    */

    $this->handle_requests();

    $this->ui_admin();
  }


  function error_notice() {
    $this->ui_message('');
  }

  function handle_requests() {
    if( isset($_POST['submit']) && isset($_POST['job_id'])) {
      check_admin_referer('new_project_nonce');
      $this->new_job();
    }
    elseif( isset($_POST['makechanges']) && isset($_POST['soln_id'])) {
      check_admin_referer('completed_project_nonce');
      $this->deploy_job();
    }
  }

  function new_job() {
    $job_id = $_POST['job_id'];
    if (isset($_POST['option_wp_content']) && $_POST['option_wp_content'] == 'true')
      $this->options->update_option('exclude_wp_content', true);

    $job = new TP_Backup($job_id);
    $result = $job->backup();

    if (is_wp_error($result))
      $this->options->persist_option('flash_message', array('content' => "Sorry, we've run into an error whilst taking a snapshot of your site.", 'type' => 'error'));
    else {
      $this->options->persist_option('flash_message', array('content' => "Thanks for your patience! Your wordpress install has now been uploaded to ThemePivot and has been made available to our marketplace of developers to start working on your changes.", 'type' => 'info'));
      $this->options->persist_option('active_project', $job_id);
    }

    wp_redirect( admin_url( 'admin.php?page=themepivot' ) );
    exit();
  }

  function deploy_job() {

    $soln_id = $_POST['soln_id'];

    $solution = new TP_Deploy($soln_id);
    $result =  $solution->deploy();

    if (is_wp_error($result))
      $this->options->persist_option('flash_message', array('content' => "Sorry, we've run into an error whilst making changes to your site.", 'type' => 'error'));
    else
      $this->options->persist_option('flash_message', array('content' => "Your website has been updated with the solution you chose.", 'type' => 'info'));

    wp_redirect( admin_url( 'admin.php?page=themepivot' ) );
    exit();
  }

  function ui_admin() {

    if (($msg = $this->options->get_option('flash_message'))) {
      $this->ui_message($msg);
      $this->options->delete_option('flash_message');
    }

    $this->ui_active_project();
    $this->ui_new_project();
    $this->ui_server_config();
    $this->ui_footer();
  }

  function ui_message($message, $type = 'info') {

    if (is_array($message)) {
      $type = $message['type'];
      $message = $message['content'];
    }
    ?>
  <div class="<?php echo $type; ?>">
    <p><?php echo $message; ?></p>
  </div>
  <?php
  }

  function ui_header() {
    ?>
    <div class="wrap">
      <div id="headline">
        <div class="tp_wrap">
          <h1>Theme Pivot</h1>
        </div>
      </div>
    <?php
  }

  function ui_active_project() {

    $active_project = $this->options->get_option('active_project');

    echo "<h2>Manage Project</h2>";

    if ($active_project) {
      ?>
      <p>Your active project: <?php echo $this->options->get_option('active_project') ?></p>
      <p>Please visit <a href='http://pivot-market.herokuapp.com'>ThemePivot</a> to view your project.</p>
      <p style="font-weight: bold">Completed and paid for your project?</p>
      <p><a href="mailto:support@themepivot.com">Contact us</a> and our experts will help you with deploying your changes.</p>
  

      <!--p>If you have approved a work package and are ready to deploy</p>
      <form accept-charset="UTF-8" action="" class="new_project" id="completed_pivot" method="POST"><div style="margin:0;padding:0;display:inline"><input name="utf8" type="hidden" value="&#x2713;" /></div>
        <input class="submit" name="makechanges" type="submit" value="Change My Site!" /-->
        <?php //wp_nonce_field( 'completed_project_nonce' ); ?>
      <!--/form-->
      <?php
    }
  }

  function ui_new_project() {
    $active_project = $this->options->get_option('active_project');

    if (!$active_project) {
      ?>
      <p>You have no active projectes. Have you launched a project on <a href='http://pivot-market.herokuapp.com'>ThemePivot</a>? Enter your project activation key below to get your project started.</p>

      <form accept-charset="UTF-8" action="" class="new_project" id="new_pivot" method="POST"><div style="margin:0;padding:0;display:inline"><input name="utf8" type="hidden" value="&#x2713;" /></div>
        <input class="text" id="activation_key" name="job_id" placeholder="Enter Project Activation Key to upload site to Theme Pivot" size="60" type="text" />
        <input class="submit" name="submit" type="submit" value="Submit Project" />
        <?php wp_nonce_field( 'new_project_nonce' ); ?>
        <div class="options">
          <h5>You should only select options below if you are having problems</h5>
          <input class="checkbox" type="checkbox" id="wp_content" name="option_wp_content" value="true"/><label for="wp_content">Only backup themes and plugins directories</label>
        </div>
        <!--input class="submit" name="submit" type="submit" value="Submit Project" /-->
      </form>

      <br />
      <div class="warning">
        <h3>Important - After you click 'Submit Project' do not close the browser or leave this page!</h3>
        <p></p>
        <p>Submitting your project requires ThemePivot to take a snapshot of your wordpress install - this may take up to a minute or more.</p>
        <p>A notification message will be displayed when the project submission is complete.</p>
      </div>
      <p style="font-weight: bold">What does the plugin upload?</p>
      <p>Well, ThemePivot backs up most of your wordpress install including the core files and your database. Media files such as music tracks and large images are excluded.</p>
      <p style="font-weight: bold">What about my passwords?</p>
      <p>ThemePivot removes all passwords from your configuration files and your database before it is made available our developers. Passwords and secure tokens stored in your database by plugins such as Twitter or eCommerce are also removed.</p>
      <p style="font-weight: bold">Having problems?</p>
      <p><a href="mailto:support@themepivot.com">Contact us</a> and our experts will help you with initial setup, restoring your files, and any needs in between.</p>
      <?php
    }
  }

  function ui_server_config() {

  }

  function ui_footer() {
    ?>
    </div>
    <?php
  }
}

$themepivot = ThemePivot::init();

?>