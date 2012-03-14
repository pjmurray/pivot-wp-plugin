<?php

require_once('themepivot_config.php');
require_once('themepivot_backup_files.php');

class TP_Deploy {

  private $capabilities;
  private $solution_id;
  private $proj_filename;
  private $proj_file;

  public function __construct($solution_id, $capabilities = null) {
    $this->solution_id = $solution_id;
    $this->capabilities = $capabilities;
  }

  public function deploy() {

    $this->proj_filename = $this->solution_id . '.zip';
    $this->proj_file = ARCHIVE_PATH . '/' . $this->proj_filename;

    @set_time_limit( 0 );

    try {

      $this->assess_install();
      $this->backup_current();
      $this->get_assets();
      //$this->unpack_assets();
      $this->deploy_assets();
      //$this->cleanup();
      return true;
    }
    catch (Exception $e) {
      $this->cleanup();
      return new WP_Error('Deploy error', $e->getMessage());
    }
  }

  // test for permissions, safe mode etc
  private function assess_install() {

  }

  // backup wp-content path
  private function backup_current() {
    $files_backup = new TP_Backup_Files();
    $files_backup->backup('current_backup.zip', WP_PATH . '/wp-content/');
  }

  // download solution assets
  private function get_assets() {

    @set_time_limit( 0 );

    $remote_file = REMOTE_COMPLETED_PROJ_PATH . '/' . $this->proj_filename;
    $ftp_conn = null;

    try {
      $ftp_conn = tp_connect_ftp();
      if ( !ftp_get($ftp_conn, $this->proj_file, $remote_file, FTP_BINARY) ) {
        throw new Exception( "FTP download failed");
      }

      ftp_close($ftp_conn);
      return true;
    }
    catch (Exception $e) {
      ftp_close($ftp_conn);
      throw ($e);
    }
  }

  // unpack and validate contents
  private function unpack_assets() {

    // ideally need to explode the zip, check the contents,
    // check the permissions in destination folder and try
    // various methods to move the files across
    try {
      tp_log("Extracting assets from $this->proj_file");
      $zip = new PCLZIP($this->proj_file);

      if ($zip->extract(PCL_OPT_PATH, tp_normalise_path(WP_PATH . '/wp-content')) == 0)
        throw new Exception("Archive extract failed");

      tp_log("Assets deployed");
    }
    catch (Exception $e) {
      throw ($e);
    }

    /*
    // extract into archive path
    if ($zip->extract(PCLZIP_OPT_PATH, ARCHIVE_PATH) == 0)
      throw new Exception("Archive extract failed");
    */
  }

  // push assets into wp-content path
  private function deploy_assets() {

  }

  private function rollback_deploy() {

  }

  public function display_work() {

  }

  private function cleanup() {
    @unlink($this->proj_file);
  }
}