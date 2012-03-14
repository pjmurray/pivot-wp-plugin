<?php

require_once('themepivot_config.php');

class TP_Capabilities {

  private $safe_mode;
  private $mem_limit;
  private $file_permissions;
  private $fatal_constraint;

  public function __construct() {

    $this->determine_capabilities();
  }

  public function determine_capabilities() {

    $this->is_safe_mode();
    $this->wp_permissions();
  }

  public function is_fatal() {
    return $this->fatal_constraint;
  }

  // outputs a description of settings that MAY cause the plugin to fail
  public function display_warnings() {

    $this->display_safe_mode();
    $this->display_wp_filepermissions();
  }

  private function is_safe_mode() {
    if ( $this->ini_get_bool( 'safe_mode' ) === true ) {
      $this->safe_mode = true;
    } else {
      $this->safe_mode = false;
    }
  }

  private function wp_permissions() {

    try {

      if ( ! is_dir(ARCHIVE_PATH)  && !is_writable(PLUGIN_PATH)) {
        $this->fatal_constraint = true;

        echo '<div class="warning"><h3>' . __( 'Theme Pivot is almost ready.') . '</h3><p>' . sprintf('However, the exports directory can\'t be created because your %s directory isn\'t writable..', PLUGIN_PATH) . '</p>';
        if (strncasecmp(PHP_OS,'win',3) != 0) {

          $php_user = exec('whoami');
          $php_group = reset( explode( ' ', exec( 'groups' ) ) );
          echo '<p>' . sprintf( 'From a server shell prompt, run %s', '<code>chown ' . $php_user . ':' . $php_group . ' ' . PLUGIN_PATH . '</code>') . '</p>';
          echo '<p>' . sprintf( 'Or %s', '<code>chmod -R 777 ' . PLUGIN_PATH . '</code>') . '</p>';
          echo '<p>Or create the folder yourself.</p></div>';
        }
        else {
          echo '<p>Please refer wordpress.org for details on how to enable write permissions for Windows Servers.</p></div>';
        }
      }

      if ( is_dir(ARCHIVE_PATH) && !is_writable(ARCHIVE_PATH)) {
        $this->fatal_constraint = true;

        echo '<div class="warning"><h3>' . __( 'Theme Pivot is almost ready.') . '</h3><p>However, the Theme Pivot exports directory isn\'t writable..</p>';
        if (strncasecmp(PHP_OS,'win',3) != 0) {

          $php_user = exec('whoami');
          $php_group = reset( explode( ' ', exec( 'groups' ) ) );
          echo '<p>' . sprintf( 'From a server shell prompt, run %s', '<code>chown -R ' . $php_user . ':' . $php_group . ' ' . ARCHIVE_PATH . '</code>') . '</p>';
          echo '<p>' . sprintf( 'Or %s', '<code>chmod -R 777 ' . ARCHIVE_PATH . '</code>') . '</p>';
          echo '<p>Or set the permissions to your own liking.</p></div>';
        }
        else {
          echo '<p>Please refer wordpress.org for details on how to enable write permissions for Windows Servers.</p></div>';
        }

        /*
        // owner of themepivot path
        $owner = posix_getpwuid(fileowner(PLUGIN_PATH));

        // file permissions
        $permissions = fileperms(PLUGIN_PATH);

        if ($permissions < 755)
          print("<div>Owner permissions are insufficient to enable this plugin</div>");

        if ( (strcmp($php_user, $owner['name']) != 0) && $permissions < 775)

          echo "big problem: owner != user and permission < 775";
        */
      }
    }
    catch (Exception $e) {

    }
  }

  private function display_safe_mode() {
    if ($this->safe_mode) {
      print "<div class='warning'>Safe mode is enabled on your host. This impacts Themepivot plugin functinoality</div>";
    }
  }

  private function display_wp_filepermissions() {
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