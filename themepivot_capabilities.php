<?php

require_once('themepivot_config.php');

class TP_Capabilities {


  private $safe_mode;
  private $archive_path_writable;
  private $wp_content_path_writable;

  public function __construct($options) {
    $this->options = $options;
    $this->determine_capabilities();
  }

  public function determine_capabilities() {

    // flush fatal constraint
    $this->options->update_option('fatal_constraint', false);

    $this->safe_mode();
    $this->multisite();
    $this->wp_permissions();
  }

  public function is_fatal() {

    return $this->options->get_option('fatal_constraint');
  }

  // outputs a description of settings that MAY cause the plugin to fail
  public function ui_warnings() {

    $this->ui_safe_mode();
    $this->ui_multisite();
    $this->ui_wp_permissions();
  }

  private function safe_mode() {
    $this->safe_mode = $this->ini_get_bool('safe_mode');
  }

  private function multisite() {
    if ( is_multisite() )
      $this->options->update_option('fatal_constraint', true);
  }

  private function wp_permissions() {

    try {
      if ($this->options->get_option('active_project')) {
        // need wp-content writable
        $this->wp_content_path_writable = is_dir(WP_PATH . '/wp-content') && is_writable(WP_PATH . '/wp-content') ? true : false;

        if (!$this->wp_content_path_writable)
          $this->options->update_optoin('fatal_constraint', true);
      }
      else {
        // need themepivot writable
        $this->archive_path_writable = (ARCHIVE_PATH) && is_writable(ARCHIVE_PATH) ? true : false;

        /*
        $owner = posix_getpwuid(fileowner(PLUGIN_PATH));
        $permissions = fileperms(PLUGIN_PATH);

        if ($permissions < 755)
          // set flag options

        if ( (strcmp($php_user, $owner['name']) != 0) && $permissions < 775)
          // set flag in options

        */

        if (!$this->archive_path_writable)
          $this->options->update_option('fatal_constraint', true);
      }
    }
    catch (Exception $e) {
      $this->options->update_option('fatal_constraint', true);
    }
  }

  private function ui_safe_mode() {
    if ($this->safe_mode) {
      print "<div class='warning'><p>Safe mode is enabled on your host. This impacts Themepivot plugin functinoality</p></div>";
    }
  }

  private function ui_multisite() {
    if ( is_multisite() ) {
      ?>
      <div class="err">
        <h3>Sorry, ThemePivot doesn't yet support WordPress Multisite installs.</h3>
        <p>We're working on it, <a href="mailto:support@themepivot.com">Contact us</a> to register your interest in Multisite support!</p>
      </div>
      <?php
    }
  }

  private function ui_wp_permissions() {

    if (!$this->archive_path_writable && !$this->options->get_option('active_project')) {
      $php_user = exec('whoami');
      $php_group = reset( explode( ' ', exec( 'groups' ) ) );
      ?>
      <div class="warning">
        <h3>Theme Pivot is almost ready.</h3>
        <p>However, the Theme Pivot plugin directory <?php echo PLUGIN_PATH ?> isn't writable preventing your website from being uploaded to our servers.</p>
        <?php if (strncasecmp(PHP_OS,'win',3) != 0) { ?>
          <p>From a server shell prompt, run <code>chown <?php echo $php_user ?>:<?php echo $php_group . ' ' . PLUGIN_PATH ?></code></p>
          <p>Or <code>chmod -R 777 <?php echo PLUGIN_PATH ?></code></p>
          <p>Or create the folder yourself.</p>
          <?php } else ?>
          <p>Please refer wordpress.org for details on how to enable write permissions for Windows Servers.</p>
          <p style="font-weight: bold">Having problems?</p>
          <p><a href="mailto:support@themepivot.com">Contact us</a> and our experts will help you with initial setup.</p>
      </div>
      <?php
    }

    if (!$this->wp_content_path_writable && $this->options->get_option('active_project')) {

    }
  }

  private function ui_wp_extended_permissions() {
    /*
    $this->file_permissions = array();

    $paths = array(WP_PATH . '/',
              WP_PATH . '/wp-content/',
              WP_PATH . '/wp-content/plugins',
              WP_PATH . '/wp-content/themes/',
              WP_PATH . '/wp-content/exports/');

    foreach ($paths as $p) {
      $t = array(
        'path'			=>		$p,
        //'recommended_permissions'	=> $this->convert_filepermissions(0755),
        'recommended_permissions' => $this->convert_filepermissions(16877),
        //'current_permissions' => substr( sprintf( '%o', fileperms($p) ), -4 )
        'current_permissions' => $this->convert_filepermissions(fileperms($p))
      );
      array_push( $this->file_permissions, $t );
    }

    foreach ( $this->file_permissions as $f) {
      echo '<div>' . $f['path'] . '&nbsp;' . $f['recommended_permissions'] . '&nbsp;' . $f['current_permissions'] .'</div>';
    }
    */
  }

  private function convert_filepermissions($perms) {
    //$perms = fileperms($path);

    if (($perms & 0xC000) == 0xC000) {
      // Socket
      $info = 's';
    } elseif (($perms & 0xA000) == 0xA000) {
      // Symbolic Link
      $info = 'l';
    } elseif (($perms & 0x8000) == 0x8000) {
      // Regular
      $info = '-';
    } elseif (($perms & 0x6000) == 0x6000) {
      // Block special
      $info = 'b';
    } elseif (($perms & 0x4000) == 0x4000) {
      // Directory
      $info = 'd';
    } elseif (($perms & 0x2000) == 0x2000) {
      // Character special
      $info = 'c';
    } elseif (($perms & 0x1000) == 0x1000) {
      // FIFO pipe
      $info = 'p';
    } else {
      // Unknown
      $info = 'u';
    }

    // Owner
    $info .= (($perms & 0x0100) ? 'r' : '-');
    $info .= (($perms & 0x0080) ? 'w' : '-');
    $info .= (($perms & 0x0040) ?
      (($perms & 0x0800) ? 's' : 'x' ) :
      (($perms & 0x0800) ? 'S' : '-'));

    // Group
    $info .= (($perms & 0x0020) ? 'r' : '-');
    $info .= (($perms & 0x0010) ? 'w' : '-');
    $info .= (($perms & 0x0008) ?
      (($perms & 0x0400) ? 's' : 'x' ) :
      (($perms & 0x0400) ? 'S' : '-'));

    // World
    $info .= (($perms & 0x0004) ? 'r' : '-');
    $info .= (($perms & 0x0002) ? 'w' : '-');
    $info .= (($perms & 0x0001) ?
      (($perms & 0x0200) ? 't' : 'x' ) :
      (($perms & 0x0200) ? 'T' : '-'));

    return $info;
  }

  // from nicolas dot grekas+php at gmail dot com
  private function ini_get_bool($a)
  {
    $b = ini_get($a);

    switch (strtolower($b))
    {
      case 'on':
      case 'yes':
      case 'true':
        return 'assert.active' !== $a;

      case 'stdout':
      case 'stderr':
        return 'display_errors' === $a;

      default:
        return (bool) (int) $b;
    }
  }
}

?>