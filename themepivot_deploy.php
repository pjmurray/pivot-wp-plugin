<?php
/**
 * Created by JetBrains PhpStorm.
 * User: drew
 * Date: 25/02/12
 * Time: 1:14 PM
 * To change this template use File | Settings | File Templates.
 */

require_once('themepivot_util.php');

class ThemepPivot_Deploy
{

  private $solution_id;

  private $proj_filename;

  // private constructor
  function __construct($solution_id) {

    $this->solution_id = $solution_id;

    $this->proj_filename = $this->solution_id . 'zip';
  }

  function get_files() {

  }

  function ftp_connect() {

  }

  public function download() {

    @set_time_limit( 0 );

    $svr = '107.21.227.54';
    $conn_id = ftp_connect($svr);

    $ftp_user = 'themepivot';
    $ftp_pass = 'pivoting';
    $login_res = ftp_login($conn_id, $ftp_user, $ftp_pass);
    //ftp_pasv($conn_id, true);

    if ((!$conn_id) || (!$login_res)) {
      throw new Exception( "FTP connect failed" );
    }

    $remote_file = '/completed_proj' . $this->proj_filename;
    $local_file = '';
    $upload = ftp_put($conn_id, $local_file, $remote_file, FTP_BINARY);

    if (!$upload) {
      throw new Exception("FTP upload failed");
    }

    ftp_close($conn_id);
  }

  private function cleanup() {

    // remove generated files
    /*
    @unlink($this->archive_file);
    @unlink($this->sql_dump_file);
    @unlink($this->manifest_file);
    */
  }

}