<?php
/**
 * Created by JetBrains PhpStorm.
 * User: drew
 * Date: 27/02/12
 * Time: 6:48 PM
 * To change this template use File | Settings | File Templates.
 */

DEFINE('WP_PATH', normalise_path(ABSPATH));

DEFINE('PLUGIN_PATH', normalise_path(dirname(__FILE__)));

DEFINE('ARCHIVE_PATH', normalise_path(PLUGIN_PATH . '/uploads'));

// Normalise a given path
function normalise_path( $dir, $recursive = false ) {

  // replace slashes
  $dir = str_replace( '//', '/', $dir );
  $dir = str_replace( '\\', '/', $dir );

  // remove trailing slash
  $dir = untrailingslashit( $dir );

  // recurse
  if ( !$recursive && normalise_path( $dir, true ) != $dir ) {
    return normalise_path( $dir );
  }

  return $dir;
}

?>