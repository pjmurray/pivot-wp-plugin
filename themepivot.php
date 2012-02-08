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
	
	private static $error = '';
	private static $fp = null;
	private static $dir = null;
	private static $db_dump_filename = 'sql-out.txt';


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
		wp_enqueue_script( 'my-ajax-request', plugin_dir_url( __FILE__ ) . 'js/ajax.js', array( 'jquery' ) );
		// declare the URL to the file that handles th AJAX request (wp-admin/admin-ajax.php)
		wp_localize_script( 'my-ajax-request', 'MyAjax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
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
			$res = self::run($_POST['job_id']);
			$t = $_POST['job_id'];
			if ($res) {
				print "<div>";
				print "<p>Your website has been uploaded to ThemePivot and has been made available to our marketplace of developers to start making your changes</p>";
				print "<p><a href='http://www.themepivot.com/projects/$t' target='_blank'>Click here</a>&nbsp;to view your project page</p>";
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
	 * @return void
	 **/
	public static function run( $job_id ) {
		self::$error = new WP_Error();
	
		ThemePivot_Backup::instance()->set_job_id( $job_id );
		ThemePivot_Backup::instance()->backup();

		self::upload();
		
		if( is_wp_error( self::$error )) 
			return self::$error;
		else
			return false;
	}

	function can_user_backup() {

	}

	function verify_nonce() {

	}

	/**
	 * undocumented function
	 *
	 * @return void
	 **/
	/*
	public static function json_posts()
	{
		// note - we might want to get specific post types if the theme is using them.
		$args = array(
			'posts_per_page' => 30,
			'post_type'	=> array('post', 'page')
		);
		$query = new WP_Query( $args );
		$json = json_encode( $query->posts );
		$file =  ABSPATH . '/wp-content/uploads/themepivot/content.json';
		$file = fopen( $file, 'w' ); // make self account for unopenable file
		fwrite( $file, $json );
		fclose( $file );	
	}
	*/
	
	/**
	 * Upload zip to landing area 
	 *
	 * @return void
	 **/
	public static function upload() {
		$svr = '107.21.227.54';
		$conn_id = ftp_connect($svr);

		$ftp_user = 'themepivot';
		$ftp_pass = 'pivoting';
		$login_res = ftp_login($conn_id, $ftp_user, $ftp_pass);
		//ftp_pasv($conn_id, true);

		if ((!$conn_id) || (!$login_res)) {
			Throw new exception ('FTP connect failed');
		}

		$dest_file = 'uploads/' . ThemePivot_Backup::instance()->get_archive_filename();
		$upload = ftp_put($conn_id, $dest_file, ThemePivot_Backup::instance()->get_archive_file(), FTP_BINARY);

		if (!$upload) {
			Throw new exception ('FTP upload failed');
		}

		ftp_close($conn_id);
	}
}

// Fire!
ThemePivot::init();


class ThemePivot_Backup {

	// singleton
	private static $instance;

	// path to store archive file
	private $archive_path;

	// zip command path
	private $zip_command;

	// path to backup
	private $root;

	// db conn
	private $db;

	// errors
	private $errors;

	// archive filename
	private $archive_file;

	// sql dump filename
	private $sql_dump_file;

	// sql dump file handle
	private $sql_dump_file_handle;

	// manifest file
	private $manifest_file;

	// private construct??
	private function __construct() {

		@ini_set( 'memory_limi', apply_filters( 'admin_memory_limit', WP_MAX_MEMORY_LIMIT ) );
		@set_time_limit( 0 );

		$this->root = $this->normalise_path( ABSPATH );
		// need to look at replacing this with get_plugins_url('plugin name',__FILE__);
		$this->archive_path = $this->normalise_path( WP_PLUGIN_DIR . '/themepivot/uploads' );
		
		$this->manifest_file = 'manifest.json';
		//$this->sql_dump_file = DB_NAME . '.sql';
		$this->sql_dump_file = 'wp_db_dump.sql';
		//print "<div>path: $this->path</div>";
	}

	public static function instance() {

		if ( !isset (self::$instance ) ) {
			$class_name = __CLASS__;
			self::$instance = new $class_name;
		}
		
		return self::$instance;
	}

	// set job related params
	public function set_job_id( $job_id ) {
		$this->archive_file = $job_id . '.zip';
	}

	public function get_archive_filename() {
		return $this->archive_file;
	}

	public function get_archive_file() {
		return $this->normalise_path( $this->archive_path ) . '/' . $this->archive_file;
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

	public function backup() {

		$this->init();
		$this->generate_manifest();
		$this->dump_database();
		$this->archive();
	}

	private function init() {

		$this->sql_dump_file_handle = $this->open_file( $this->archive_path . "/" . $this->sql_dump_file );
		if ( false == $this->sql_dump_file_handle) {
			// error
			return false;
		}

		return true;
	}

	private function generate_manifest() {

		// extract options inc site_url, themepivot acct identifier etc
	
		// put options and job id into manifest
	}

	// dump wp database
	private function dump_database() {

		global $wpdb;

		$tables = $wpdb->get_results( "SHOW TABLES", ARRAY_N );

		$sql  =	"# " . __( 'ThemePivot WordPress Database Dump') . "\n" ;
		$sql .= "#\n";
		$sql .= "# " . sprintf( __( 'Generated: %s' ), date( "l j. F Y H:i T" ) ) . "\n";
		$sql .= "#\n";

		foreach ( $tables as $table ) {

			// set script execution time to 15 minutes for each table
			@set_time_limit(15*60);

			$this->backup_table( $table[0], $sql );

		}
	}

	/**
	 * Taken partially from phpMyAdmin and partially from
	 * Alain Wolf, Zurich - Switzerland
	 * Website: http://restkultur.ch/personal/wolf/scripts/db_backup/
	
	 * Modified by Scott Merrill (http://www.skippy.net/) 
	 * to use the WordPress $wpdb object
	 * @param string $table
	 * @param string $segment
	 * @return void
	 */
	private function backup_table( $table, $sql) {
		
		global $wpdb;

		//print "<div>backing up table $table</div>";

		$table_structure = $wpdb->get_results( "DESCRIBE $table" );

		if ( !$table_structure ) {
			
			$this->errors->add( "error", __( "Error getting table details" ) . ": $table" );
			//print_r( $this->error->get_error_messages() );
			return false;
		}
	
		// Add SQL statement to drop existing table
		$sql .= "\n";
		$sql .= "\n";
		$sql .= "#\n";
		$sql .= "# " . sprintf( __ ("Delete any existing table %s","wp-db-backup" ), $this->backquote( $table ) ) . "\n";
		$sql .= "#\n";
		$sql .= "\n";
		$sql .= "DROP TABLE IF EXISTS " . $this->backquote( $table ) . ";\n";
		
		/* Table structure */
		
		// Comment in SQL-file
		$sql .= "\n";
		$sql .= "\n";
		$sql .= "#\n";
		$sql .= "# " . sprintf( __ ( "Table structure of table %s" ), $this->backquote( $table ) ) . "\n";
		$sql .= "#\n";
		$sql .= "\n";
		
		$res = $wpdb->get_results("SHOW CREATE TABLE $table", ARRAY_N);

		if ( $res ) {
			$sql .= $res[0][1] . " ;";
		}

		/* Table contents */

		// Comment in SQL-file
		$sql .= "\n";
		$sql .= "\n";
		$sql .= "#\n";
		$sql .= "# " . sprintf( __( "Data contents of table %s"), $this->backquote( $table ) ) . "\n";
		$sql .= "#\n";

		$res = $wpdb->get_results( "SELECT * FROM $table", ARRAY_A);

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
			if ( !ini_get('safe_mode')) @set_time_limit(15*60);
			$table_data = $wpdb->get_results("SELECT * FROM $table LIMIT {$current_row}, {$batch}", ARRAY_A);

			if($table_data) {
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

				// write out batch
				$this->write_file( $this->sql_dump_file_handle, $sql );
				$sql = '';
			}
		} while( ( count( $table_data ) > 0 ) );
		
		// Create footer/closing comment in SQL-file
		$sql .= "\n";
		$sql .= "#\n";
		$sql .= "# " . sprintf( __( "End of data contents of table %s"), $this->backquote( $table ) ) . "\n";
		$sql .= "# --------------------------------------------------------\n";
		$sql .= "\n";

		$this->write_file( $this->sql_dump_file_handle, $sql );
	}

	private function open_file( $filename = '', $mode = 'w' ) {
		if ( '' == $filename ) {
			return false;
		}

		$fp = @fopen( $filename, $mode );
		return $fp;
	}

	// param is a file handle
	private function close_file( $fp ) {
		if ( fclose( $fp ) )
			return true;

		return false;
	}
	
	// param is a file handle
	private function write_file( $fp, $data ) {
		if ( fwrite( $fp, $data ) )
			return true;
		
		return false;
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




	/* ########################################
	*/

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
		//$files[] = $this->normalise_path( $this->archive_path ) . '/' . $this->sql_dump_file;

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

		$archive = new PclZip( $this->normalise_path( $this->archive_path ) . '/' . $this->archive_file );
		//$t = $this->normalise_path( $this->archive_path ) . '/' . $this->archive_file;
		//print "<div>zip: $t</div>";

		// clear out the archive
		$archive->delete();

		$f = '';

		foreach($files as $t) {
			$f .= $t . ',';
		}

		$f = trim($f, ',');

		$res = $archive->add($f, PCLZIP_OPT_REMOVE_PATH, ABSPATH );

		$res = $archive->add($this->normalise_path( $this->archive_path ) . '/' . $this->sql_dump_file, PCLZIP_OPT_REMOVE_PATH, $this->archive_path);
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

			$filesystem = new RecursiveIteratorIterator( new RecuriveDirectoryIterator( $this->normalise_path( $this->root ), RecursiveDirectoryIterator::FOLLOW_SYMLINKS ),
				RecursiveIterator::SELF_FIRST, 
				RecursiveIterator::CATCH_GET_CHILD );

			$excludes = $this->exclude_string( 'regex' );

			foreach ( $filesystem as $file ) {
				
				if ( !$file->isReadable() ) {
					$this->unreadable_files[] = $file->getPathName();
					continue;
				}

				$pathname = str_ireplace( trailingslashit( $this->normalise_path( $this->root ) ), '', $this->normalise_path( $file->getPathname() ) );

				// excludes
				if ( $excludes && preg_match( '(' . $excludes . ')', $pathname ) )
					continue;

				// dont include db dump
				if ( basename( $pathname ) == $this->sql_dump_file )
					continue;

				$this->files[] = $pathname;
			}

		} else {
			$this->files = $this->files_fallback( $this->normalise_path( $this->root ) );
		}

		// add db dump
		$this->files[] = $this->normalise_path( $this->archive_path ) . '/' . $this->sql_dump_file;

		return $this->files;
	}

	private function files_fallback( $path ) {

	}

	private function root() {
		// return path
	}
} //end
