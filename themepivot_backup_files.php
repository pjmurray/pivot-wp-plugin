<?php

require_once('themepivot_config.php');

class TP_Backup_Files {

  private $zip_file;
  private $zip_filename;
  private $root_backup_path;
  private $options;
  private $excludes;
  private $files;
  private $unreadable_files;

  public function __construct() {

  }

  public function backup($filename, $path, $options = null) {
    $this->zip_filename = $filename;
    $this->zip_file = ARCHIVE_PATH . '/' . $filename;
    $this->root_backup_path = $path;
    $this->options = $options;

    try {
      $this->zip_files();
    }
    catch (Exception $e) {
      throw ($e);
    }
  }

  /*
  private function get_files() {

    $this->files = array();

    if ( defined( 'RecursiveDirectoryIterator::FOLLOW_SYMLINKS' ) ) {

      $filesystem = new RecursiveIteratorIterator( new RecuriveDirectoryIterator( WP_PATH, RecursiveDirectoryIterator::FOLLOW_SYMLINKS ),
        RecursiveIterator::SELF_FIRST,
        RecursiveIterator::CATCH_GET_CHILD );

      $excludes = $this->exclude_string( 'regex' );

      foreach ( $filesystem as $file ) {

        if ( !$file->isReadable() ) {
          $this->unreadable_files[] = $file->getPathName();
          continue;
        }

        // remove wp_path from front of file path
        $pathname = str_ireplace( trailingslashit( WP_PATH ), '', tp_normalise_path( $file->getPathname() ) );

        // compare stripped pathname against excludes
        if ( $excludes && preg_match( '(' . $excludes . ')', $pathname ) )
          continue;

        $this->files[] = $pathname;
      }

    } else {
      $this->files = $this->files_fallback( $this->root_path );
    }

    // add db dump
    //$this->files[] = $this->sql_dump_file;

    //return $this->files;
  }
  */

  /*
  private function get_files_fallback( $path ) {

  }
  */

  /*
  private function generate_excludes() {


  }
  */

  /**
   * Generate the exclude param string for the zip backup
   *
   * Takes the exclude rules and formats them for use with either
   * the shell zip command or pclzip
   *
   * @access public
   * @param string $context. (default: 'zip')
   * @return string
   */
  /*
  private function exclude_string( $context = 'zip' ) {

    // Return a comma separated list by default
    $separator = ', ';
    $wildcard = '';

    // The zip command
    if ( $context == 'zip' ) {
      $wildcard = '*';
      $separator = ' -x ';

      // The PclZip fallback library
    } elseif ( $context == 'regex' ) {
      $wildcard = '([\s\S]*?)';
      $separator = '|';
    }

    // Sanitize the excludes
    $excludes = array_filter( array_unique( array_map( 'trim', (array) $this->excludes ) ) );

    // If path() is inside root(), exclude it
    if ( strpos( $this->root_backup_path, $this->root() ) !== false )
      $excludes[] = trailingslashit( $this->path() );

    foreach( $excludes as $key => &$rule ) {

      $file = $absolute = $fragment = false;

      // Files don't end with /
      if ( ! in_array( substr( $rule, -1 ), array( '\\', '/' ) ) )
        $file = true;

      // If rule starts with a / then treat as absolute path
      elseif ( in_array( substr( $rule, 0, 1 ), array( '\\', '/' ) ) )
        $absolute = true;

      // Otherwise treat as dir fragment
      else
        $fragment = true;

      // Strip $this->root and conform
      $rule = str_ireplace( $this->root(), '', untrailingslashit( tp_normalise_path( $rule ) ) );

      // Strip the preceeding slash
      if ( in_array( substr( $rule, 0, 1 ), array( '\\', '/' ) ) )
        $rule = substr( $rule, 1 );

      // Escape string for regex
      if ( $context == 'regex' )
        $rule = str_replace( '.', '\.', $rule );

      // Convert any existing wildcards
      if ( $wildcard != '*' && strpos( $rule, '*' ) !== false )
        $rule = str_replace( '*', $wildcard, $rule );

      // Wrap directory fragments and files in wildcards for zip
      if ( $context == 'zip' && ( $fragment || $file ) )
        $rule = $wildcard . $rule . $wildcard;

      // Add a wildcard to the end of absolute url for zips
      if ( $context == 'zip' && $absolute )
        $rule .= $wildcard;

      // Add and end carrot to files for pclzip but only if it doesn't end in a wildcard
      if ( $file && $context == 'regex' )
        $rule .= '$';

      // Add a start carrot to absolute urls for pclzip
      if ( $absolute && $context == 'regex' )
        $rule = '^' . $rule;

    }

    // Escape shell args for zip command
    if ( $context == 'zip' )
      $excludes = array_map( 'escapeshellarg', array_unique( $excludes ) );

    return implode( $separator, $excludes );
  }
  */


  private function zip_files() {

    //$this->generate_excludes();

    // TODO: add support for shell cmd zip and zipArchive, both better than pcl_zip
    $this->pcl_zip_files();
  }

  private function pcl_zip_files() {

    //global $tp_excludes_string;

    $this->load_pclzip();

    $archive = new PclZip( $this->zip_file );

    // zero the archive
    $archive->delete();

    //$tp_excludes_string = $this->exclude_string();

    // zip
    if ( ! $archive->add( $this->root_backup_path, PCLZIP_OPT_REMOVE_PATH, $this->root_backup_path, PCLZIP_CB_PRE_ADD, 'tp_pclzip_callback' ) )
      $this->warning( $this->archive_method, $archive->errorInfo( true ) );
  }

  private function load_pclzip() {

    if ( !defined( 'PCLZIP_TEMPORARY_DIR' ) )
      define( 'PCLZIP_TEMPORARY_DIR', trailingslashit ( ARCHIVE_PATH ) );

    require_once( ABSPATH . 'wp-admin/includes/class-pclzip.php');
  }

  public function get_zip_file() {
    return $this->zip_file;
  }

  public function cleanup() {
    // unlink files
    @unlink($this->zip_file);
  }
}

function tp_pclzip_callback( $event, &$file) {
  //global $tp_excludes_string;

  // don't add undreadable files
  if ( !is_readable( $file['filename'] ) || !file_exists( $file['filename'] ) )
    return 0;

  $abs_file = tp_normalise_path($file['filename']);

  // dont include zips
  if (strpos($abs_file, '.zip')) {
    //tp_log("skipping $abs_file");
    return 0;
  }

  // if in themepivot path only include database dump
  if (strpos($abs_file, 'themepivot/')) {
    if (strpos($abs_file, DB_DUMP_FILENAME)) {
      return 1;
    }
    else {
      //tp_log("skipping $abs_file");
      return 0;
    }
  }

  // if in wp-content path only include /plugins and /themes
  if (strpos($abs_file, 'wp-content/')) {
    if (strpos($abs_file, 'plugins') || strpos($abs_file, 'themes'))
      return 1;
    else {
      //tp_log("skipping $abs_file");
      return 0;
    }
  }

  // check excludes
  //elseif ( $tp_excludes_string && preg_match( '(' . $$tp_excludes_string . ')', $file['stored_filename'] ) )
  //  return false;

  // include everything else
  return 1;
}

?>