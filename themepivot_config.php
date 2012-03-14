<?php

define('WP_PATH', tp_normalise_path(ABSPATH));

define('PLUGIN_PATH', tp_normalise_path(dirname(__FILE__)));

define('ARCHIVE_PATH', tp_normalise_path(PLUGIN_PATH . '/uploads'));

define('REMOTE_UPLOAD_PATH', '/uploads');

define('REMOTE_COMPLETED_PROJ_PATH', '/completed_proj');

define('DB_DUMP_FILENAME', 'wp_db_dump.sql');

define('DB_DUMP_FILE', PLUGIN_PATH . '/' . DB_DUMP_FILENAME);

// Normalise a given path
function tp_normalise_path( $dir, $recursive = false ) {

  // replace slashes
  $dir = str_replace( '//', '/', $dir );
  $dir = str_replace( '\\', '/', $dir );

  // remove trailing slash
  $dir = untrailingslashit( $dir );

  if ( !$recursive && tp_normalise_path( $dir, true ) != $dir ) {
    return tp_normalise_path( $dir );
  }

  return $dir;
}

function tp_connect_ftp() {

  // prod
  //$svr = '107.21.227.54';
  // pre-prod
  $svr = '23.21.239.84';
  $conn_id = ftp_connect($svr);

  $ftp_user = 'themepivot';
  $ftp_pass = 'pivoting';
  $login_res = ftp_login($conn_id, $ftp_user, $ftp_pass);
  //ftp_pasv($conn_id, true);

  if ((!$conn_id) || (!$login_res)) {
    throw new Exception( "FTP connect failed" );
  }

  return $conn_id;
}

function tp_open_file( $filename = '', $mode = 'w' ) {
  if ( '' == $filename ) {
    throw new Exception("Filename cant be empty");
  }

  // test path exists
  if (false === is_dir(dirname($filename))) {
    throw new Exception("Unable to open file $filename: path doesnt exist and cannot be created");
  }

  // try and touch it first
  touch($filename);

  if ( !$fp = fopen( $filename, $mode ) ) {
    throw new Exception("Unable to open file $filename");
  }

  return $fp;
}

function tp_close_file( $fp ) {
  if ( !fclose( $fp ) )
    throw new Exception("Unable to close file $fp");

  return true;
}

function tp_write_file( $fp, $data ) {

  if ( false === fwrite( $fp, $data ) )
    throw new Exception("Unable to write file $fp");

  return true;
}

function tp_log($log_data) {
  print "<div>$log_data</div>";
}

// async POST / GET requests. use for loggly
function tp_http_request_async($url, $params, $type='POST') {

  foreach ($params as $key => &$val) {
    if (is_array($val)) $val = implode(',', $val);
    $post_params[] = $key.'='.urlencode($val);
  }
  $post_string = implode('&', $post_params);

  $parts=parse_url($url);

  $fp = fsockopen($parts['host'],
    isset($parts['port'])?$parts['port']:80,
    $errno, $errstr, 30);

  // Data goes in the path for a GET request
  if('GET' == $type) $parts['path'] .= '?'.$post_string;

  $out = "$type ".$parts['path']." HTTP/1.1\r\n";
  $out.= "Host: ".$parts['host']."\r\n";
  $out.= "Content-Type: application/x-www-form-urlencoded\r\n";
  $out.= "Content-Length: ".strlen($post_string)."\r\n";
  $out.= "Connection: Close\r\n\r\n";

  // Data goes in the request body for a POST request
  if ('POST' == $type && isset($post_string)) $out.= $post_string;

  fwrite($fp, $out);
  fclose($fp);
}

?>