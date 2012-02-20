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
- check permissions on everything and make sure that the end user is notified if there's incorrec perms anywhere.
- check user has permission to perform backup / upload
- check user has permission to write files to default archive path. if not try alternatives such as /tmp and c:\tmp
- check for ziparchive dependency because that is heaps quicker.
- note - after the file has been compressed, we want to remove the options.json. becuase that exposed is bad.
*/


class ThemePivot {
	
	/* #############################################
	 *
	 * ADMIN PAGE
	 *
	 * ############################################/

	/*
	 * Init Action
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', get_class() . '::plugin_admin_init' );
		add_action( 'admin_menu', get_class() . '::plugin_admin_menu' );
	}
	
	/*
	 * Adds a subpage under "Tools"
	 *
	 * @return void
	 */
	public static function plugin_admin_menu() {
		$page = add_submenu_page( 'tools.php', 
															__('ThemePivot'), 
															__('ThemePivot'), 
															'manage_options', 
															'themepivot', 
															get_class() . '::admin_ui' );

		// hook script load onto our page only
		add_action('admin_print_styles-' . $page, get_class() . '::plugin_styles');
		add_action('admin_print_scripts-' . $page, get_class(). '::plugin_scripts');
	}

	/*
	 * Registers stylesheets and scripts for later loading
	 *
	 * @ return void
	 */
	public static function plugin_admin_init() {
		// register assets
		wp_register_style('themepivot_css', plugins_url('/assets/styles/themepivot.css', __FILE__), null, '1.0', 'screen');
		wp_register_script('themepivot_js', plugins_url('/assets/scripts/themepivot.js', __FILE__));
	}

	/*
	 * Queues scripts for loading
	 *
	 * @return void
	 */
	public static function plugin_scripts() {
		wp_enqueue_script( 'themepivot_js' );
		// embed the javascript file that makes the AJAX request
		//wp_enqueue_script( 'my-ajax-request', plugin_dir_url( __FILE__ ) . 'js/ajax.js', array( 'jquery' ) );
		// declare the URL to the file that handles th AJAX request (wp-admin/admin-ajax.php)
		//wp_localize_script( 'my-ajax-request', 'MyAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
	}

	/*
	 * Queues stylesheets for loading
	 *
	 * @return void
	 */
	public static function plugin_styles() {
		wp_enqueue_style('themepivot_css');
		echo "<link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css'>";
	}

	/*
	 * UI for ThemePivot Page
	 *
	 * @return void
	 */
	public static function admin_ui() {
	?>
	<div class="wrap">
		<div id="headline">
		  <div class="tp_wrap">
			  <h1>Theme Pivot</h1>
			</div>
		</div>

		<div class="section">
	<?php

		if( isset($_POST['submit']) && isset($_POST['job_id'])) {
			$t = $_POST['job_id'];
			if ( !is_wp_error ( $res = self::run( $_POST['job_id'] ) ) ) {
				print "<div>";
				print "<p>Your website has been uploaded to ThemePivot and has been made available to our marketplace of developers to start making your changes</p>";
				print "<p><a href='http://pivot-market.herokuapp.com/projects/$t' target='_blank'>Click here</a>&nbsp;to view your project page</p>";
				print "</div>";
			} else {
				// errored
				$err_msg = $res->get_error_message();
				print "<div>";
				print "<p>Sorry, we've run into an error whilst taking a snapshot of your site</p>";
				print "<p>$err_msg</p>";
				print "</div>";
			}
		}
		else {
			?>
			<form accept-charset="UTF-8" action="" class="new_project" id="new_pivot" method="POST"><div style="margin:0;padding:0;display:inline"><input name="utf8" type="hidden" value="&#x2713;" /></div>
				<input class="text" id="project_website" name="job_id" placeholder="Enter Project Activation Key to upload site to marketplace" size="60" type="text" />
				<input class="submit" name="submit" type="submit" value="Submit Job" />
			</form>
		</div>
		<?php }
		?>
		</div>
		<?php
	}

	
	/**
	 * Runs the process
	 *
	 **/
	public static function run( $job_id ) {
	
		ThemePivot_Backup::instance()->set_job_id( $job_id );
		
		if ( is_wp_error( $res = ThemePivot_Backup::instance()->backup() ) ) {
			return $res;
		}

    /*
		if ( is_wp_error( ThemePivot_Backup::instance()->upload() ) ) {
			return false;
		}
		*/
		return true;
	}

	function verify_nonce() {

	}
}

// Fire!
ThemePivot::init();


class ThemePivot_Backup {

	// singleton
	private static $instance;

	// path to backup
	private $wp_root_path;

	// plugin path
	private $plugin_path;

	// archive path
	private $archive_path;

  // archive filename
  private $archive_filename;

	// archive file
	private $archive_file;

	// sql dump filename
	private $sql_dump_file;

	// project manifest file
	private $manifest_file;

	// sql dump file handle
	private $sql_dump_fp;

	// zip command path
	private $zip_command;

	// private constructor
	private function __construct() {

		// up the memory limit
		//@ini_set( 'memory_limit', apply_filters( 'admin_memory_limit', WP_MAX_MEMORY_LIMIT ) );
    @ini_set("memory_limit", "512M");

		$this->wp_root_path = $this->normalise_path( ABSPATH );

		$this->plugin_path = $this->normalise_path( dirname( __FILE__ ) );

		$this->archive_path = $this->plugin_path  . '/uploads';
		
		$this->manifest_file = $this->archive_path . '/manifest.json';
		
		$this->sql_dump_file = $this->archive_path . '/wp_db_dump.sql';
		
		//print "<div>archive_path: $this->archive_path</div>";
	}

	public static function instance() {

		if ( !isset (self::$instance ) ) {
			$class_name = __CLASS__;
			self::$instance = new $class_name;
		}
		
		return self::$instance;
	}

	public function backup() {

		// reset script execution time
		@set_time_limit( 0 );

		// initialise files etc
		if ( is_wp_error( $res = $this->init() ) ) {
			return $res;
		}

		// generate the project manifest
		if ( is_wp_error( $res = $this->generate_manifest() ) ) {
			$this->cleanup();
			return $res;
		}

		// generate the wp database dump
		if ( is_wp_error( $res = $this->dump_database() ) ) {
			$this->cleanup();
			return $res;
		}

		// archive wp assets
		if ( is_wp_error( $res = $this->archive() ) ) {
			$this->cleanup();
			return $res;
		}

		// upload files

		if ( is_wp_error( $res = $this->upload() ) ) {
			$this->cleanup();
			return $res;
		}



		// cleanup
		//$this->cleanup();

    return true;
	}

	private function init() {

		$this->sql_dump_fp = $this->open_file( $this->sql_dump_file );
		
		return $this->sql_dump_fp;
	}

	private function generate_manifest() {

		// extract options inc site_url, themepivot acct identifier etc
	
		// put options and job id into manifest
		return true;
	}

	// dump wp database
	private function dump_database() {

		global $wpdb;

		$tables = $wpdb->get_results( "SHOW TABLES", ARRAY_N );

		// check for error
		if ( false === $tables ) {
			return new WP_Error("database_error", __( "Unable to read list of tables from database" ) );
		}

		// sql dump file header
		$sql  =	"# " . __( "ThemePivot WordPress Database Dump") . "\n" ;
		$sql .= "#\n";
		$sql .= "# " . sprintf( __( "Generated: %s" ), date( "l j. F Y H:i T" ) ) . "\n";
		$sql .= "#\n";

		foreach ( $tables as $table ) {

			// reset script execution time
			@set_time_limit( 0 );

			// check for errors, kill execution if error returned
			if ( is_wp_error( $res = $this->backup_table( $table[0], $sql ) ) ) {
        $this->close_file( $this->sql_dump_fp );
				return $res;
			}	
		}

		$this->close_file( $this->sql_dump_fp );

		return true;
	}

	/**
	 * Taken partially from phpMyAdmin and partially from
	 * Alain Wolf, Zurich - Switzerland
	 * Website: http://restkultur.ch/personal/wolf/scripts/db_backup/
	
	 * Modified by Scott Merrill (http://www.skippy.net/) 
	 * to use the WordPress $wpdb object
	 * @param string $table
	 * @param string $segment
	 * @return
	 */
	private function backup_table( $table, $sql) {
		
		global $wpdb;
    print "<div>getting table $table</div>";

		$sql .= $this->drop_table_sql( $table );

		$sql .= $this->create_table_sql( $table );

    $this->write_file( $this->sql_dump_fp, $sql );

    // do a row count
    $row_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table" );

    // guard against timeouts: if safe_mode is enabled skip tables that have 20K records
    // and are not one of the core wp tables.
    //if ( ( $row_count > 20000 ) && ( ini_get("safe_mode") ) ) {
    // TODO: until timeout issue is addressed in the archive / upload fn just skip
    // all large non core wp tables.
    if ( ( $row_count > 20000 ) ) {
      if ( ( strpos( $table, "comments" ) === false ) ||
        ( strpos( $table, "posts" ) === false ) ) {
        return null;
      }
    }

		$sql = $this->table_data_sql( $table );

		$sql .= $this->footer_sql( $table );
		
		$this->write_file( $this->sql_dump_fp, $sql );
    print "<div>finished table $table</div>";
	}

	private function drop_table_sql( $table ) {

		// drop existing table sql
		$sql = "\n";
		$sql .= "\n";
		$sql .= "#\n";
		$sql .= "# " . sprintf( __ ("Delete any existing table %s","wp-db-backup" ), $this->backquote( $table ) ) . "\n";
		$sql .= "#\n";
		$sql .= "\n";
		$sql .= "DROP TABLE IF EXISTS " . $this->backquote( $table ) . ";\n";

		return $sql;
	}

	private function create_table_sql( $table ) {

		global $wpdb;

		// table struct header 
		$sql = "\n";
		$sql .= "\n";
		$sql .= "#\n";
		$sql .= "# " . sprintf( __ ( "Table structure of table %s" ), $this->backquote( $table ) ) . "\n";
		$sql .= "#\n";
		$sql .= "\n";
		
		$res = $wpdb->get_results("SHOW CREATE TABLE $table", ARRAY_N);

		// check for error
		/*
		if (false === $res) {
			return new WP_Error( "database_error", __( "Unable to show create table statement for $table" ) );
		} else {
			$sql .= $res[0][1] . " ;";
		}
		*/

		$sql .= $res[0][1] . " ;";
	
		return $sql;
	}

	private function table_data_sql( $table ) {

		global $wpdb;
	
		$table_structure = $wpdb->get_results( "DESCRIBE $table" );

		// check for error
		if ( false === $table_structure ) {
			return new WP_Error( 'database_error', __( "Unable to describe table structure for $table" ) );
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
			$table_data = $wpdb->get_results("SELECT * FROM $table LIMIT {$current_row}, {$batch}", ARRAY_A);

			// check for error
			if (false === $table_data) {
				return new WP_Error( "database_error", __( "Unable to retrieve rows for $table" ) );
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
				$this->write_file( $this->sql_dump_fp, $sql );
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

	private function open_file( $filename = '', $mode = 'w' ) {
		if ( '' == $filename ) {
			return new WP_Error( "file_error", __( "Filename cant be empty" ) );
		}

		if ( !$fp = @fopen( $filename, $mode ) ) {
			return new WP_Error( "file_error", __( "Failed to open file $filename" ) );
		}

		return $fp; 
	}

	// param is a file handle
	private function close_file( $fp ) {
		if ( !fclose( $fp ) )
			return new WP_Error("file_error", __( "Unable to close file $fp" ) );

		return $fp;
	}
	
	// param is a file handle
	private function write_file( $fp, $data ) {
		if ( !fwrite( $fp, $data ) )
			return new WP_Error("file_error", __( "Unable to write file $fp" ) );
		
		return true;
	}

	/**
	 * Better addslashes for SQL queries.
	 * Taken from phpMyAdmin.
	 *
	 * implicit static method
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
	 *
	 * implicit static method
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

	private function archive() {

		//$res = glob( ABSPATH . 'wp-content');

		$files = $this->get_files();
		
		$this->pcl_zip_archive($files);

	}

	private function get_files() {

		$files = array();

		$it = new RecursiveDirectoryIterator( ABSPATH );

		$current_theme = get_template();

		foreach ( new RecursiveIteratorIterator($it, RecursiveIteratorIterator::LEAVES_ONLY, RecursiveIteratorIterator::CATCH_GET_CHILD) as $p) {
			
			// check for themepivot dir
			if ( ( strpos( $p, 'themepivot' ) !== false ) || ( strpos( $p, '.zip' ) !== false ) ) {
				// skip
			} else if ( strpos( $p, 'wp-content/themes/' ) !== false ) {
				if ( ( strpos( $p, $current_theme ) !== false ) || ( strpos( $p, 'themes/index.php') ) )
					$files[] = $p;
			} else {
				$files[] = $p;
			}
		}

		// add db dump
		//$files[] = $this->normalise_path( $this->sql_dump_file;

		//$res = $this->get_dir_iterative( ABSPATH, true );

		/*
		foreach ($files as $f)
			print "<div>$f</div>";
		*/
		return $files;
	}

	private function get_files_fallback($directory, $recursive) {
		$array_items = array();
		if ($handle = opendir($directory)) {
			while (false !== ($file = readdir($handle))) {
				if ($file != "." && $file != "..") {
					if (is_dir($directory. "/" . $file)) {
						if($recursive) {
							$array_items = array_merge($array_items, $this->get_files_fallback($directory. "/" . $file, $recursive));
						}
						$file = $directory . "/" . $file;
						$array_items[] = preg_replace("/\/\//si", "/", $file);
					} else {
						$file = $directory . "/" . $file;
						$array_items[] = preg_replace("/\/\//si", "/", $file);
					}
				}
			}
			closedir($handle);
		}
		return $array_items;
	}

	private function zip_archive() {
	
	}

	private function pcl_zip_archive($files) {

		$this->load_pclzip();

		$archive = new PclZip( $this->archive_file );
		//$t = $this->normalise_path( $this->archive_path ) . '/' . $this->archive_file;
		//print "<div>zip: $t</div>";

		// clear out the archive
		$archive->delete();

		$f = '';

		foreach($files as $t) {
			$f .= $t . ',';
		}

		$f = trim($f, ',');

		$res = $archive->add( $f, PCLZIP_OPT_REMOVE_PATH, ABSPATH );

		$res = $archive->add( $this->sql_dump_file, PCLZIP_OPT_REMOVE_PATH, $this->archive_path );

		//print "<div>$t</div>";
		//if ( !$res )
			//print "<div>errored:  </div>";
	}

	private function load_pclzip() {

		if ( !defined( 'PCLZIP_TEMPORARY_DIR' ) )
			define( 'PCLZIP_TEMPORARY_DIR', trailingslashit ( $this->normalise_path( $this->archive_path ) ) );

		require_once( ABSPATH . 'wp-admin/includes/class-pclzip.php');
	}

	public static function callback_pclzip( $event, &$file) {
	
		// don't add undreadable files
		if ( !is_readable( $file['filename'] ) || !file_exists( $file['filename'] ) )
			return false;

		// check excludes
		
		return true;
	}

	private function files() {

		$this->files = array();

		if ( defined( 'RecursiveDirectoryIterator::FOLLOW_SYMLINKS' ) ) {

			$filesystem = new RecursiveIteratorIterator( new RecuriveDirectoryIterator( $this->normalise_path( $this->wp_root_path ), RecursiveDirectoryIterator::FOLLOW_SYMLINKS ),
				RecursiveIterator::SELF_FIRST, 
				RecursiveIterator::CATCH_GET_CHILD );

			$excludes = $this->exclude_string( 'regex' );

			foreach ( $filesystem as $file ) {
				
				if ( !$file->isReadable() ) {
					$this->unreadable_files[] = $file->getPathName();
					continue;
				}

				$pathname = str_ireplace( trailingslashit( $this->normalise_path( $this->wp_root_path ) ), '', $this->normalise_path( $file->getPathname() ) );

				// excludes
				if ( $excludes && preg_match( '(' . $excludes . ')', $pathname ) )
					continue;

				// dont include db dump
				if ( basename( $pathname ) == $this->sql_dump_file )
					continue;

				$this->files[] = $pathname;
			}

		} else {
			$this->files = $this->files_fallback( $this->root_root );
		}

		// add db dump
		$this->files[] = $this->sql_dump_file;

		return $this->files;
	}

	private function files_fallback( $path ) {

	}

	/* TRANSFER FUNCTIONS */

	/**
	 * Upload zip to landing area 
	 *
	 * @return void
	 **/
	public function upload() {

    @set_time_limit( 0 );

		$svr = '107.21.227.54';
		$conn_id = ftp_connect($svr);

		$ftp_user = 'themepivot';
		$ftp_pass = 'pivoting';
		$login_res = ftp_login($conn_id, $ftp_user, $ftp_pass);
		//ftp_pasv($conn_id, true);

		if ((!$conn_id) || (!$login_res)) {
			return new WP_Error( "ftp_error", __( "FTP connect failed" ) );
		}

		$dest_file = 'uploads/' . $this->get_archive_filename();
		$upload = ftp_put($conn_id, $dest_file, $this->get_archive_file(), FTP_BINARY);

		if (!$upload) {
			return new WP_Error( "ftp_error", __( "FTP upload failed" ) );
		}

		ftp_close($conn_id);
	}

	/* UTIL FUNCTIONS */

	// set job id related params
	public function set_job_id( $job_id ) {
		$this->archive_filename = $job_id . '.zip';
		$this->archive_file = $this->archive_path . '/' . $this->archive_filename;
	}

	// return the archive filename
	public function get_archive_filename() {
		return $this->archive_filename;
	}

	// return the absolute path to the archive file
	public function get_archive_file() {
		return $this->archive_file;
	}

	// normalise paths
	public function normalise_path( $dir, $recursive = false ) {
		
		// replace slashes
		$dir = str_replace( '//', '/', $dir );
		$dir = str_replace( '\\', '/', $dir );

		// remove trailing slash
		$dir = untrailingslashit( $dir );

		// recurse
		if ( !$recursive && $this->normalise_path( $dir, true ) != $dir ) {
			return $this->normalise_path( $dir );
		}

		return $dir;
	}

	private function cleanup() {

		// remove generated files
		@unlink($this->archive_file);
		@unlink($this->sql_dump_file);
		@unlink($this->manifest_file);
	}

} //end
