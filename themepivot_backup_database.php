<?php

require_once('themepivot_config.php');

class TP_Backup_Database {

  private $db_dump_filename;
  private $db_dump_file;
  private $db_fp;
  private $db;

  public function __construct() {
    global $wpdb;
    $this->db_dump_filename = DB_DUMP_FILENAME;
    $this->db_dump_file = DB_DUMP_FILE;
    $this->db = $wpdb;
  }

  public function backup() {

    // reset script timer
    @set_time_limit( 0 );

    try {

      $this->db_fp = tp_open_file($this->db_dump_file);

      $tables = $this->db->get_results( "SHOW TABLES", ARRAY_N );

      if ( false === $tables ) {
        throw new Exception("Unable to read list of tables from database");
      }

      tp_write_file($this->db_fp, $this->sql_file_header());

      foreach ( $tables as $table ) {
        @set_time_limit( 0 );
        $this->backup_table( $table[0]);
      }

      return tp_close_file( $this->db_fp );
    }
    catch (Exception $e) {

      tp_close_file($this->db_fp);
      throw($e);
    }
  }

  private function sql_file_header() {

    // sql dump file header
    $sql  =	"# " . __( "ThemePivot WordPress Database Dump") . "\n" ;
    $sql .= "#\n";
    $sql .= "# " . sprintf( __( "Generated: %s" ), date( "l j. F Y H:i T" ) ) . "\n";
    $sql .= "#\n";

    return $sql;
  }

  /**
   * Taken partially from phpMyAdmin and partially from
   * Alain Wolf, Zurich - Switzerland
   * Website: http://restkultur.ch/personal/wolf/scripts/db_backup/
   */

  private function backup_table( $table) {

    $sql = $this->drop_table_sql( $table );
    $sql .= $this->create_table_sql( $table );

    tp_write_file( $this->db_fp, $sql );

    // do a row count
    $row_count = $this->db->get_var( "SELECT COUNT(*) FROM $table" );

    // guard against timeouts: if safe_mode is enabled skip tables that have 20K records
    // and are not one of the core wp tables.
    //if ( ( $row_count > 20000 ) && ( ini_get("safe_mode") ) ) {
    // TODO: until timeout issue is addressed in the archive / upload fn just skip
    // all large non core wp tables.
    if ( ( $row_count > 20000 ) ) {
      if ( ( strpos( $table, "comments" ) === false ) ||
        ( strpos( $table, "posts" ) === false ) ) {
        return;
      }
    }

    $sql = $this->table_data_sql( $table );
    $sql .= $this->footer_sql( $table );

    tp_write_file( $this->db_fp, $sql );
  }

  private function drop_table_sql( $table ) {

    // drop existing table sql
    $sql = "\n";
    $sql .= "\n";
    $sql .= "#\n";
    $sql .= "# " . sprintf( __("Delete any existing table %s","wp-db-backup" ), $this->backquote( $table ) ) . "\n";
    $sql .= "#\n";
    $sql .= "\n";
    $sql .= "DROP TABLE IF EXISTS " . $this->backquote( $table ) . ";\n";

    return $sql;
  }

  private function create_table_sql( $table ) {

    // table struct header
    $sql = "\n";
    $sql .= "\n";
    $sql .= "#\n";
    $sql .= "# " . sprintf( __ ( "Table structure of table %s" ), $this->backquote( $table ) ) . "\n";
    $sql .= "#\n";
    $sql .= "\n";

    $res = $this->db->get_results("SHOW CREATE TABLE $table", ARRAY_N);

    $sql .= $res[0][1] . " ;";

    return $sql;
  }

  private function table_data_sql( $table ) {

    $table_structure = $this->db->get_results( "DESCRIBE $table" );

    // check for error
    if ( false === $table_structure ) {
      throw new Exception("Unable to describe table structure for $table");
    }

    // data content header
    $sql = "\n";
    $sql .= "\n";
    $sql .= "#\n";
    $sql .= "# " . sprintf( __( "Data contents of table %s"), $this->backquote( $table ) ) . "\n";
    $sql .= "#\n";

    $defs = array();
    $ints = array();

    foreach ( $table_structure as $struct ) {
      if ( ( 0 === strpos( $struct->Type, 'tinyint' ) ) ||
        ( 0 === strpos( strtolower( $struct->Type ), 'smallint' ) ) ||
        ( 0 === strpos( strtolower( $struct->Type ), 'mediumint' ) ) ||
        ( 0 === strpos( strtolower( $struct->Type ), 'int' ) ) ||
        ( 0 === strpos( strtolower( $struct->Type ), 'bigint' ) ) ) {
        $defs[strtolower( $struct->Field )] = ( null === $struct->Default ) ? 'NULL' : $struct->Default;
        $ints[strtolower( $struct->Field )] = true;
      } else {
        $ints[strtolower( $struct->Field )] = false;
      }
    }

    $entries = 'INSERT INTO ' . $this->backquote( $table ) . ' VALUES (';
    $search = array( '\x00', '\x0a', '\x0d', '\x1a' );
    $replace = array( '\0', '\n', '\r', '\Z' );
    $current_row = 0;
    $batch = 100;

    do {
      $table_data = $this->db->get_results("SELECT * FROM $table LIMIT {$current_row}, {$batch}", ARRAY_A);

      // check for error
      if (false === $table_data) {
        throw new Exception("Unable to retrieve rows for $table");
      } else {
        foreach ($table_data as $row) {
          $values = array();
          foreach ($row as $key => $value) {
            if ($ints[strtolower($key)]) {
              // make sure there are no blank spots in the insert syntax,
              // yet try to avoid quotation marks around integers
              $value = ( null === $value || '' === $value) ? $defs[strtolower($key)] : $value;
              $values[] = ( '' === $value ) ? "''" : $value;
            } else {
              $values[] = "'" . str_replace($search, $replace, $this->sql_addslashes($value)) . "'";
            }
          }
          $sql .= " \n" . $entries . implode(', ', $values) . ");";
        }

        $current_row += $batch;

        // reset script execution to prevent timeout
        if ( ( $current_row % 10000 ) && !ini_get( "safe_mode" ) )
          @set_time_limit( 0 );

        // write out batch
        tp_write_file( $this->db_fp, $sql );
        $sql = '';
      }
    } while( ( count( $table_data ) > 0 ) );

    return $sql;
  }

  private function footer_sql( $table ) {

    // footer
    $sql = "\n";
    $sql .= "#\n";
    $sql .= "# " . sprintf( __( "End of data contents of table %s"), $this->backquote( $table ) ) . "\n";
    $sql .= "# --------------------------------------------------------\n";
    $sql .= "\n";

    return $sql;
  }

  /**
   * Better addslashes for SQL queries.
   * Taken from phpMyAdmin.
   */
  private function sql_addslashes( $a_string = '', $is_like = false ) {
    if ( $is_like )
      $a_string = str_replace( '\\', '\\\\\\\\', $a_string );
    else
      $a_string = str_replace( '\\', '\\\\', $a_string );

    return str_replace( '\'', '\\\'', $a_string );
  }

  /**
   * Add backquotes to tables and db-names in
   * SQL queries. Taken from phpMyAdmin.
   */
  private function backquote( $a_name ) {

    if ( !empty( $a_name ) && $a_name != '*' ) {
      if ( is_array( $a_name ) ) {
        $result = array();
        reset( $a_name );
        while( list( $key, $val ) = each( $a_name ) )
          $result[$key] = '`' . $val . '`';
        return $result;
      } else {
        return '`' . $a_name . '`';
      }
    } else {
      return $a_name;
    }
  }

  public function cleanup() {
    @unlink($this->db_dump_file);
  }
}