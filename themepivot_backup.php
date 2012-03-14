<?php

require_once('themepivot_config.php');
require_once('themepivot_backup_database.php');
require_once('themepivot_backup_files.php');

class TP_Backup {

  private $job_id;
  private $zip_filename;
  private $zip_file;
  private $capabilities;

  public function __construct($job_id, $capabilities = null) {
    $this->job_id = $job_id;
    $this->capabilities = $capabilities;
  }

  public function backup() {

    $this->zip_filename = $this->job_id . '.zip';

    @ini_set( 'memory_limit', apply_filters( 'admin_memory_limit', WP_MAX_MEMORY_LIMIT ) );
    //tp_log("mem limit: " . @ini_get('memory_limit'));

    @set_time_limit( 0 );

    try {

      $this->init_fs();

      $db_backup = new TP_Backup_Database();
      $db_backup->backup();

      //$files_backup = new TP_Backup_Files();
      //$files_backup->backup($this->zip_filename, WP_PATH);
      //$this->zip_file = $files_backup->get_zip_file();

      //$this->upload();

      //$db_backup->cleanup();
      //$files_backup->cleanup();

      return true;
    }
    catch (Exception $e) {
      return new WP_Error('Upload error', $e->getMessage());
    }
  }

  private function init_fs() {

    // check archive path exists
    if (!is_dir(ARCHIVE_PATH)) {
      if (!mkdir(ARCHIVE_PATH, 0775)) {
        throw new Exception("Unable to create Archive path");
      }
    }

    // lock archive path down
    if (!file_exists(ARCHIVE_PATH . '/.htaccess')) {
      if ($file = tp_open_file(ARCHIVE_PATH . '/.htaccess', 'w')) {
        tp_write_file($file, "Order allow,deny\ndeny from all");
        tp_close_file($file);
      }
    }

    if (!file_exists(ARCHIVE_PATH . '/index.html')){
      if ($file = tp_open_file(ARCHIVE_PATH . '/index.html')) {
        tp_write_file($file, "");
        tp_close_file($file);
      }
    }

  }

  // generate a list of files to exclude from the archive
  /*
  private function exclude_files() {
    $files = array();
    $files[] = 'themepivot';
    $files[] = '.zip';
    // add the current theme path
    $files[] = get_template();
  }
  */

  public function upload() {

    @set_time_limit( 0 );

    $remote_file = REMOTE_UPLOAD_PATH . '/' . $this->zip_filename;
    $ftp_conn = null;

    try {
      $ftp_conn = tp_connect_ftp();

      if ( !$upload = ftp_put($ftp_conn, $remote_file, $this->zip_file, FTP_BINARY) ) {
       throw new Exception('FTP upload failed');
      }

      ftp_close($ftp_conn);
      return true;
    }
    catch (Exception $e) {
      ftp_close($ftp_conn);
      throw ($e);
    }
  }

  public function display_work() {

  }
}
?>