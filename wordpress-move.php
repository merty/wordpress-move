<?php
/*
Plugin Name: WordPress Move
Plugin URI: http://www.mertyazicioglu.com/wordpress-move/
Description: WordPress Move is a migration assistant for WordPress that can take care of changing your domain name or moving your database and files to another server. After activating the plugin, please navigate to WordPress Move page under the Settings menu to configure it. Then, you can start using the Migration Assistant under the Tools menu.
Version: 1.0
Author: Mert Yazicioglu
Author URI: http://www.mertyazicioglu.com
License: GPL2
*/

/*  Copyright 2011  Mert Yazicioglu  (email : mert@mertyazicioglu.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Define file path constants
define( 'WPMOVE_DIR', WP_PLUGIN_DIR . '/' . basename( dirname( __FILE__ ) ) );
define( 'WPMOVE_BACKUP_DIR', WPMOVE_DIR . '/backup' );
define( 'WPMOVE_OLD_BACKUP_DIR', WPMOVE_BACKUP_DIR . '/old' );
define( 'WPMOVE_URL', WP_PLUGIN_URL . '/' . basename( dirname( __FILE__ ) ) );

// Load functions needed for database and file operations
require_once( 'libs/functions-database-backup.php' );
require_once( 'libs/functions-file-backup.php' );

// Load PemFTP's classes if they're not loaded already
if ( ! class_exists( 'ftp_base' ) )
	require_once( ABSPATH . "wp-admin/includes/class-ftp.php" );

if ( ! class_exists( 'ftp' ) )
	require_once( ABSPATH . "wp-admin/includes/class-ftp-pure.php" );

// Create the class only if it doesn't exist
if ( ! class_exists( 'WPMove' ) ) {

	/**
	 *	WPMove Class
	 */
	class WPMove {

		// Name of the option set
		public $admin_options_name = 'wpmove_options';

		/**
		 * Autoloader
		 *
		 * @param void
		 * @return void
		 */
		function WPMove() {
			$this->load_language_file();
		}

		/**
		 * Loads the language file.
		 *
		 * @param void
		 * @return void
		 */
		function load_language_file() {
			load_plugin_textdomain( 'WPMove', FALSE, basename( dirname( __FILE__ ) ) . '/lang/' );
		}

		/**
		 * Adds the script to the head for Migration Assistant.
		 *
		 * @param void
		 * @return void
		 */
		function add_migration_assistant_js() {
			?>
			<script type="text/javascript"> 
				jQuery( document ).ready( function( $ ) {
					$( "#wpmove_change_domain_name" ).css( 'display', 'none' );
					$( "#wpmove_change_domain_name_br" ).css( 'display', 'none' );
					$( "#wpmove_toggle_change_domain_name" ).click( function () {
					 	if ( $( "#wpmove_change_domain_name" ).css( 'display' ) ==  "none" ) {
							$( "#wpmove_change_domain_name" ).css( 'display', 'block' );
							$( "#wpmove_change_domain_name_br" ).css( 'display', 'block' );
						} else {
							$( "#wpmove_change_domain_name" ).css( 'display', 'none' );
							$( "#wpmove_change_domain_name_br" ).css( 'display', 'none' );
						}
					} );
				 	$( "#wpmove_file_tree_loading" ).css( 'display', 'block' );
					$( "#wpmove_file_tree" ).bind( "loaded.jstree", function( event, data ) {
						$( "#wpmove_file_tree_loading" ).css( 'display', 'none' );
						$( "#wpmove_file_tree" ).css( 'display', 'block' );
						$( "#wpmove_file_tree_buttons" ).css( 'display', 'block' );
						$( "#wpmove_file_tree_check_all" ).click( function () {	$( "#wpmove_file_tree" ).jstree( "check_all" ); } );
						$( "#wpmove_file_tree_uncheck_all" ).click( function () { $( "#wpmove_file_tree" ).jstree( "uncheck_all" );	} );
					}).jstree( {
					 	"themes" : { "dots" : false	},
						"types" : {	"valid_children" : [ "file" ], "types" : { "file" : { "icon" : { "image" : "<?php echo WPMOVE_URL; ?>/libs/js/themes/default/file.png" } } } },
						"checkbox" : { "real_checkboxes" : true, "real_checkboxes_names" : function(n) { return [ "files[]", $( n[0] ).children( 'a' ).attr( 'title' ) ]; }	},
						"plugins" : [ "themes", "types", "checkbox", "html_data" ],
					} );
				} );
			</script>
			<?php
		}

		/**
		 * Loads the JS file for Advanced Migration.
		 *
		 * @param void
		 * @return void
		 */
		function load_advanced_migration_scripts() {
			wp_enqueue_script( 'file_tree', '/wp-content/plugins/wordpress-move/libs/js/jquery.jstree.js', array( 'jquery' ) );
		}

		/**
		 * Calls functions we need to call after the activation of the plugin.
		 *
		 * @param void
		 * @return void
		 */
		function init() {
			$this->get_admin_options();
		}

		/**
		 * Returns settings of the plugin.
		 *
		 * @param void
		 * @return void
		 */
		function get_admin_options() {

		 	// Define options and their default values
			$wpmove_admin_options = array( 'db_chunk_size'		=> 0,
										   'fs_chunk_size'		=> 10,
										   'ftp_hostname'		=> '',
										   'ftp_port'			=> 21,
										   'ftp_username'		=> '',
										   'ftp_password'		=> '',
										   'ftp_passive_mode'	=> 1,
										   'ftp_remote_path'	=> '',
										 );

			// Try retrieving options from the database
			$wpmove_options = get_option( $this->admin_options_name );

			// If the option set already exists in the database, reset their values
			if ( ! empty( $wpmove_options ) )
				foreach ( $wpmove_options as $key => $value )
					$wpmove_admin_options[$key] = $value;

			// Update the database
			update_option( $this->admin_options_name, $wpmove_admin_options );

			// Return options
			return $wpmove_admin_options;
		}

		/**
		 * Generates the settings page of the plugin.
		 *
		 * @param void
		 * @return void
		 */
		function print_settings_page() {

		 	// Get plugin's settings
			$wpmove_options = $this->get_admin_options();

			// If the form is submitted successfully...
			if ( $_POST && check_admin_referer( 'wpmove_update_settings' ) ) {

				// If the user was redirected from the migration assistant, redirect him/her back once all necessary fields are filled
				if ( isset( $_POST['wpmove_ref'] ) && $_POST['wpmove_ftp_hostname'] !== '' && $_POST['wpmove_ftp_username'] !== '' && $_POST['wpmove_ftp_port'] !== 0 )
					echo '<meta http-equiv="refresh" content="' . esc_attr( '0;url=tools.php?page=wpmove&do=migrate' ) . '" />';

			 	// Store the changes made...
				$wpmove_options['db_chunk_size'] 	 = intval( $_POST['wpmove_db_chunk_size'] );
				$wpmove_options['fs_chunk_size'] 	 = intval( $_POST['wpmove_fs_chunk_size'] );
				$wpmove_options['ftp_hostname']  	 = sanitize_text_field( $_POST['wpmove_ftp_hostname'] );
				$wpmove_options['ftp_port'] 	 	 = intval( $_POST['wpmove_ftp_port'] );
				$wpmove_options['ftp_username']  	 = sanitize_text_field( $_POST['wpmove_ftp_username'] );
				$wpmove_options['ftp_password']  	 = sanitize_text_field( $_POST['wpmove_ftp_password'] );
				$wpmove_options['ftp_passive_mode']  = intval( $_POST['wpmove_ftp_passive_mode'] );
				$wpmove_options['ftp_remote_path']	 = sanitize_text_field( $_POST['wpmove_ftp_remote_path'] );

				// Update plugin settings
				update_option( $this->admin_options_name, $wpmove_options );

				?>
				<div class="updated"><p><strong><?php _e( 'Settings saved.', 'WPMove' ); ?></strong></p></div>
				<?php
			}

			// Tell the user to fill in the FTP details if he/she was redirected from the migration assistant
			if ( isset( $_GET['ref'] ) || isset( $_POST['wpmove_ref'] ) )
				echo '<div class="updated"><p><strong>' . __( 'Please fill in FTP Connection Details in order to start the migration process.', 'WPMove' ) . '</strong></p></div>';

			?>
			<div class="wrap">
				<div id="icon-options-general" class="icon32">
					<br>
				</div>
				<h2><?php _e( 'WordPress Move Settings', 'WPMove' ); ?></h2>
				<p>
					<?php _e( 'Please configure the plugin using the settings below before starting to use the Migration Assistant under the Tools menu. If connecting to the remote server fails, please toggle the Passive Mode setting and try again.', 'WPMove' ); ?>
				</p>
				<form method="post" action="options-general.php?page=wpmove-settings">
					<?php wp_nonce_field( 'wpmove_update_settings' ); ?>
					<h3><?php _e( 'FTP Connection Details', 'WPMove' ); ?></h3>
					<?php _e( 'These are the FTP connection details of your new server.', 'WPMove' ); ?>
					<table class="form-table">
						<tbody>
							<tr valign="top">
								<th scope="row">
									<label for="wpmove_ftp_hostname"><?php _e( 'Hostname', 'WPMove' ); ?></label>
								</th>
								<td>
									<input class="regular-text code" id="wpmove_ftp_hostname" name="wpmove_ftp_hostname" type="text" value="<?php echo esc_attr( $wpmove_options['ftp_hostname'] ); ?>" /> <i><?php _e( 'The hostname you use to establish an FTP connection to the remote server. Might be an IP address or a domain name.', 'WPMove' ); ?></i>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">
									<label for="wpmove_ftp_port"><?php _e( 'Port', 'WPMove' ); ?></label>
								</th>
								<td>
									<input id="wpmove_ftp_port" name="wpmove_ftp_port" type="text" value="<?php echo esc_attr( $wpmove_options['ftp_port'] ); ?>" size="5" /> <i><?php _e( 'If you do not know what to write, it is most probably 21.', 'WPMove' ); ?></i>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">
									<label for="wpmove_ftp_username"><?php _e( 'Username', 'WPMove' ); ?></label>
								</th>
								<td>
									<input class="regular-text" id="wpmove_ftp_username" name="wpmove_ftp_username" type="text" value="<?php echo esc_attr( $wpmove_options['ftp_username'] ); ?>" /> <i><?php _e( 'The username you use to establish an FTP connection to the remote server.', 'WPMove' ); ?></i>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">
									<label for="wpmove_ftp_password"><?php _e( 'Password', 'WPMove' ); ?></label>
								</th>
								<td>
									<input class="regular-text" id="wpmove_ftp_password" name="wpmove_ftp_password" type="password" value="<?php echo esc_attr( $wpmove_options['ftp_password'] ); ?>" /> <i><?php _e( 'The password you use to establish an FTP connection to the remote server.', 'WPMove' ); ?></i>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">
									<label for="wpmove_ftp_remote_path"><?php _e( 'Remote Backup Path', 'WPMove' ); ?></label>
								</th>
								<td>
									<input class="regular-text code" id="wpmove_ftp_remote_path" name="wpmove_ftp_remote_path" type="text" value="<?php echo esc_attr( $wpmove_options['ftp_remote_path'] ); ?>" /> <i><?php _e( 'Path from root to the backup directory of the WordPress Move plugin on the remote server. For instance:', 'WPMove' ); ?> <code>/var/www/wp-content/plugins/wordpress-move/backup/</code></i>
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">
									<label for="wpmove_ftp_passive_mode"><?php _e( 'Use Passive Mode', 'WPMove' ); ?></label>
								</th>
								<td>
									<label title="enabled">
										<input type="radio" name="wpmove_ftp_passive_mode" value="1" <?php if ( $wpmove_options['ftp_passive_mode'] ) echo 'checked="checked"'; ?> />
										<span style="font-size:11px;"><?php _e( 'Yes', 'WPMove' ); ?></span>
									</label>
									<br>
									<label title="disabled">
										<input type="radio" name="wpmove_ftp_passive_mode" value="0" <?php if ( ! $wpmove_options['ftp_passive_mode'] ) echo 'checked="checked"'; ?> />
										<span style="font-size:11px;"><?php _e( 'No', 'WPMove' ); ?></span>
									</label>
								</td>
							</tr>
						</tbody>
					</table><br>
					<h3><?php _e( 'Database Backup Settings', 'WPMove' ); ?></h3>
					<?php _e( 'The size of each chunk of your database backup. Actual sizes of chunks may exceed this size limit. 0 means unlimited.', 'WPMove' ); ?>
					<table class="form-table">
						<tbody>
							<tr valign="top">
								<th scope="row">
									<label for="wpmove_db_chunk_size"><?php _e( 'Chunk Size', 'WPMove' ); ?></label>
								</th>
								<td>
									<input id="wpmove_db_chunk_size" name="wpmove_db_chunk_size" type="text" value="<?php echo esc_attr( $wpmove_options['db_chunk_size'] ); ?>" size="5" /> MB
								</td>
							</tr>
						</tbody>
					</table><br>
					<h3><?php _e( 'File Backup Settings', 'WPMove' ); ?></h3>
					<?php _e( 'The size of files to compress per filesystem backup chunk. Sizes of chunks will be less than or equal to this size limit, depending on the compression ratio. 0 means unlimited.', 'WPMove' ); ?>
					<table class="form-table">
						<tbody>
							<tr valign="top">
								<th scope="row">
									<label for="wpmove_fs_chunk_size"><?php _e( 'Chunk Size', 'WPMove' ); ?></label>
								</th>
								<td>
									<input id="wpmove_fs_chunk_size" name="wpmove_fs_chunk_size" type="text" value="<?php echo esc_attr( $wpmove_options['fs_chunk_size'] ); ?>" size="5" /> MB
								</td>
							</tr>
						</tbody>
					</table>
					<?php

					// Pass the refferer info with a hidden input field
					if ( isset( $_GET['ref'] ) || isset( $_POST['wpmove_ref'] ) )
						echo '<input id="wpmove_ref" name="wpmove_ref" type="hidden" />';

					// Display the submit button
					submit_button();

					?>
				</form>
			</div>
			<?php
		}

		/**
		 * Generates the migration assistant page of the plugin.
		 *
		 * @param void
		 * @return void
		 */
		function print_migration_assistant_page() {

			// If the form is submitted...
			if ( isset( $_GET['do'] ) ) {

				// Call the requested function			
				switch ( $_GET['do'] ) {
					case 'domain':		$this->print_change_domain_name_page();
										break;
					case 'migrate':		$this->print_start_migration_page();
										break;
					case 'complete':	$this->print_complete_migration_page();
										break;
				}
			} else {
			?>
			<div class="wrap">
				<div id="icon-tools" class="icon32">
					<br>
				</div>
				<h2><?php _e( 'Migration Assistant', 'WPMove' ); ?></h2>
				<p>
					<?php _e( 'Please make sure you have configured the plugin using the WordPress Move page under the Settings menu before selecting an action to proceed...', 'WPMove' ); ?>
				</p>
				<table class="widefat" cellspacing="0">
					<tbody>
						<tr class="alternate">
							<td class="row-title" style="width: 10%;">
								<a href="<?php echo esc_url( admin_url( 'tools.php?page=wpmove&do=domain' ) ); ?>"><?php _e( 'Change Domain Name', 'WPMove' ); ?></a>
							</td>
							<td class="desc">
								<?php _e( 'By selecting this option, you will be able to replace all instances of your current domain name in the database with the new one you want to use from now on. All you need to do is to type in your new domain name and configure your DNS servers.', 'WPMove' ); ?>
							</td>
						</tr>
						<tr>
							<td class="row-title">
								<a href="<?php echo esc_url( admin_url( 'tools.php?page=wpmove&do=migrate' ) ); ?>"><?php _e( 'Start Migration', 'WPMove' ); ?></a>
							</td>
							<td class="desc">
								<?php _e( 'By selecting this option, you will be able to migrate your current installation either as is or partially to another server. Before proceeding, please make sure you have installed WordPress and WordPress Move on the remote server as well.', 'WPMove' ); ?>
							</td>
						</tr>
						<tr class="alternate">
							<td class="row-title">
								<a href="<?php echo esc_url( admin_url( 'tools.php?page=wpmove&do=complete' ) ); ?>"><?php _e( 'Complete Migration', 'WPMove' ); ?></a>
							</td>
							<td class="desc">
								<?php _e( 'By selecting this option, you will be able to complete the migration process you have started from another server. Before proceeding, please make sure that the installation you want to migrate from has completed uploading backup files to this server successfully.', 'WPMove' ); ?>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
			<?php
			}
		}

		/**
		 * Handles the domain name changing process.
		 *
		 * @param void
		 * @return void
		 */
		function print_change_domain_name_page() {

			// If the form is filled in completely and submitted successfully...
			if ( $_POST && ! empty( $_POST['old_domain_name'] ) && ! empty( $_POST['new_domain_name'] ) && check_admin_referer( 'wpmove_change_domain_name_start' ) ) {

				// Load plugin settings
				$wpmove_options = $this->get_admin_options();

				// Apply filters to the given domain names
				$old_domain_name = esc_url_raw( $_POST['old_domain_name'] );
				$new_domain_name = esc_url_raw( $_POST['new_domain_name'] );

				// Create a backup of the database in case the operation fails
				$db_backups = wpmove_create_db_backup( $wpmove_options['db_chunk_size'] );

				// Create a backup of the database by changing instances of the old domain name with the newer one
				$new_db_backups = wpmove_create_db_backup( $wpmove_options['db_chunk_size'], count( $db_backups ) + 1, $old_domain_name, $new_domain_name );

				// Set error counter to zero
				$errors_occured = 0;

				// Import databsae backups we've just created
				foreach ( $new_db_backups as $backup )
					if ( TRUE !== wpmove_import_db_backup( $backup ) )
						$errors_occured++;

				// Delete backup files on success
				if ( ! $errors_occured ) {

					foreach ( $db_backups as $backup )
						wpmove_remove_db_backup( $backup );

					foreach ( $new_db_backups as $backup )
						wpmove_remove_db_backup( $backup );

					// Prepare the new homepage URL
					$new_home_url = str_replace( $old_domain_name, $new_domain_name, home_url( '/' ) );

					// Display a success message
				?>
					<div class="wrap">
						<div id="icon-tools" class="icon32">
							<br>
						</div>
						<h2><?php _e( 'Success!', 'WPMove' ); ?></h2>
						<p>
							<?php _e( 'Your domain name has been changed successfully.', 'WPMove' ); ?>
							<?php printf( __( '<a href="%s">Click here</a> to go to your site using your new domain.', 'WPMove' ), $new_home_url ); ?>
						</p>
					</div>
				<?php

				} else {

					// Display a failure message on failure
				?>
					<div class="wrap">
						<div id="icon-tools" class="icon32">
							<br>
						</div>
						<h2><?php _e( 'Failure!', 'WPMove' ); ?></h2>
						<p>

						<?php

						_e( 'An error occured while changing instances of your domain name.', 'WPMove' );

						// To seperate the next message from the previous one
						echo ' ';

						// Remove the database backup with replaced domain names
						foreach ( $new_db_backups as $backup )
							wpmove_remove_db_backup( $backup );

						// Set error counter to zero
						$errors_occured = 0;

						// Try to rollback to the previous state
						foreach ( $db_backups as $backup )
							if ( ! wpmove_import_db_backup( $backup ) )
								$errors_occured++;

						if ( ! $errors_occured ) {
							_e( 'Changes on your domain has been rolled back automatically.', 'WPMove' );
							foreach ( $db_backups as $backup )
								wpmove_remove_db_backup( $backup );
						} else {
							_e( 'Rolling back to the previous state also failed. Please try importing the database backup stored under the backup folder manually.', 'WPMove' );
						}

						?>

						</p>
					</div>
				<?php

				}

			} else {
			?>
			<div class="wrap">
				<div id="icon-tools" class="icon32">
					<br>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'tools.php?page=wpmove&do=domain' ) ); ?>">
					<?php wp_nonce_field( 'wpmove_change_domain_name_start' ); ?>
					<h2><?php _e( 'Changing Domain Name', 'WPMove' ); ?></h2>
					<p>
					<?php _e( 'Please enter exact paths to your WordPress installations on both domains without the trailing slash. After replacing instances of your old domain name in the database with the new one completes, please do not forget to update your nameservers.', 'WPMove' ); ?>
					</p>
					<table class="form-table">
						<tbody>
							<tr valign="top">
								<th scope="row">
									<label for="old_domain_name"><?php _e( 'Old Domain Name', 'WPMove' ); ?></label>
								</th>
								<td>
									<input class="regular-text code" id="old_domain_name" name="old_domain_name" type="text" value="<?php echo home_url(); ?>" />
								</td>
							</tr>
							<tr valign="top">
								<th scope="row">
									<label for="new_domain_name"><?php _e( 'New Domain Name', 'WPMove' ); ?></label>
								</th>
								<td>
									<input class="regular-text code" id="new_domain_name" name="new_domain_name" type="text" />
								</td>
							</tr>
						</tbody>
					</table>
					<p class="submit">
						<input class="button-primary" type="submit" name="wpmove_change_domain_name" value="<?php _e( 'Change', 'WPMove' ); ?>" />
					</p>
				</form>
			</div>
			<?php
			}
		}

		/**
		 * Handles the migration starting process.
		 *
		 * @param void
		 * @return void
		 */
		function print_start_migration_page() {

			// Load plugin settings
			$wpmove_options = $this->get_admin_options();

			// If the FTP details are not on file, redirect the user to the settings page
		 	if ( $wpmove_options['ftp_hostname'] == '' || $wpmove_options['ftp_username'] == '' || $wpmove_options['ftp_port'] == 0 ) {
				echo '<meta http-equiv="refresh" content="0;url=options-general.php?page=wpmove-settings&ref=ma" />';
			}

			// Call the requested function if there's any
			if ( isset( $_GET['type'] ) ) {

				if ( $_GET['type'] == 'simple' )
					$this->print_simple_migration_page();
				elseif ( $_GET['type'] == 'advanced' )
					$this->print_advanced_migration_page();

			} else {

				?>
				<div class="wrap">
					<div id="icon-tools" class="icon32">
						<br>
					</div>
					<h2><?php _e( 'Migration Assistant', 'WPMove' ); ?></h2>
					<p>
						<?php _e( 'Please select a migration type to proceed...', 'WPMove' ); ?>
					</p>
					<table class="widefat" cellspacing="0">
						<tbody>
							<tr class="alternate">
								<td class="row-title" style="width: 10%;">
									<a href="<?php echo esc_url( admin_url( 'tools.php?page=wpmove&do=migrate&type=simple' ) ); ?>"><?php _e( 'Simple Migration', 'WPMove' ); ?></a>
								</td>
								<td class="desc">
									<?php _e( 'Simple Migration creates a backup of your database and files excluding the plugin directory. Uploading backup files to the remote server starts once the backup files are created.', 'WPMove' ); ?>
								</th>
							</tr>
							<tr>
								<td class="row-title">
									<a href="<?php echo esc_url( admin_url( 'tools.php?page=wpmove&do=migrate&type=advanced' ) ); ?>"><?php _e( 'Advanced Migration', 'WPMove' ); ?></a>
								</td>
								<td class="desc">
									<?php _e( 'Advanced Migration creates a backup of the database but lets you select the files to backup. Uploading backup files to the remote server starts once the backup files are created.', 'WPMove' ); ?>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
				<?php
			}
		}

		/**
		 * Handles the simple migration process.
		 *
		 * @param void
		 * @return void
		 */
		function print_simple_migration_page() {

			if ( $_POST && check_admin_referer( 'wpmove_simple_migration_start' ) ) {

			?>

				<div class="wrap">
					<div id="icon-tools" class="icon32">
						<br>
					</div>
					<h2><?php _e( 'Simple Migration', 'WPMove' ); ?></h2>
					<p>
					<?php

					// Load plugin settings
					$wpmove_options = $this->get_admin_options();

					// An array to hold backup files that will be uploaded
					$backups = array();

					// If changing the current domain name is also requested...
					if ( ! empty( $_POST['old_domain_name'] ) && ! empty( $_POST['new_domain_name'] ) ) {

						// Apply filters to the given domain names
						$old_domain_name = esc_url_raw( $_POST['old_domain_name'] );
						$new_domain_name = esc_url_raw( $_POST['new_domain_name'] );

						// Create a backup of the database by changing instances of the old domain name with the newer one
						$db_backups = wpmove_create_db_backup( $wpmove_options['db_chunk_size'], 1, $old_domain_name, $new_domain_name );

					} else {

						// Create a backup of the database
						$db_backups = wpmove_create_db_backup( $wpmove_options['db_chunk_size'] );
					
					}

					// Add names of database backup files to the array of backup files
				 	$backups = array_merge( $backups, $db_backups );

					// List all of the files inside the main directory
					$abspath = substr( ABSPATH, 0, strlen( ABSPATH ) - 1 );
					$files = wpmove_list_all_files( $abspath, FALSE, array( WPMOVE_DIR, WPMOVE_BACKUP_DIR, WPMOVE_OLD_BACKUP_DIR ) );

				 	// Create chunks from the selected files
				 	$chunks = wpmove_divide_into_chunks( $files, $wpmove_options['fs_chunk_size'] );

				 	// To prevent overwriting archives created in the same second
				 	$chunk_id = 1;

				 	// Create an archive of the each chunk
				 	foreach ( $chunks as $chunk )
				 		array_push( $backups, wpmove_create_archive( $chunk, ABSPATH, $chunk_id++ ) );

					// Check whether creating backups files succeeded or not
				 	if ( ! file_exists( trailingslashit( WPMOVE_BACKUP_DIR ) . $backups['0'] ) ) {
				 		_e( 'Could not create backup files. Please make sure the backup directory is writable. For further info, please refer to the documentation.', 'WPMove' );
				 	} else {

						// Upload files to the new server and display a success message on success
						if ( $this->upload_files( $backups ) ) {

						?>
						<br>
						<?php _e( 'Creating and uploading backups have been completed. You can now go to your new installation and run the migration assistant in Complete Migration mode.', 'WPMove' ); ?>
					</p>
				</div>
						<?php

						} else {

						?>
						<br>
						<?php _e( 'Please check your FTP connection details on the settings page.', 'WPMove' ); ?>
					</p>
				</div>
							<?php

						}
					}
	
			} else {
			?>

				<div class="wrap">
					<div id="icon-tools" class="icon32">
						<br>
					</div>
					<h2><?php _e( 'Simple Migration', 'WPMove' ); ?></h2>
					<p>
						<?php _e( 'This will backup your database and files as is and upload them to the server you want to migrate to.', 'WPMove' ); ?><br>
					</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'tools.php?page=wpmove&do=migrate&type=simple' ) ); ?>">
						<div id="wpmove_change_domain_name">
							<p>
								<?php _e( 'Please enter exact paths to your WordPress installations on both domains without the trailing slash.', 'WPMove' ); ?><br>
							</p>
							<table class="form-table">
								<tbody>
									<tr valign="top">
										<th scope="row">
											<label for="old_domain_name"><?php _e( 'Old Domain Name', 'WPMove' ); ?></label>
										</th>
										<td>
											<input class="regular-text code" id="old_domain_name" name="old_domain_name" type="text" value="<?php echo home_url(); ?>" />
										</td>
									</tr>
									<tr valign="top">
										<th scope="row">
											<label for="new_domain_name"><?php _e( 'New Domain Name', 'WPMove' ); ?></label>
										</th>
										<td>
											<input class="regular-text code" id="new_domain_name" name="new_domain_name" type="text" />
										</td>
									</tr>
								</tbody>
							</table>
							<br>
						</div>
						<?php
							wp_nonce_field( 'wpmove_simple_migration_start' );
							submit_button( __( 'Start Migration', 'WPMove' ), 'primary', 'submit', FALSE );
						?>
							<input type="button" name="wpmove_toggle_change_domain_name" id="wpmove_toggle_change_domain_name" class="button-secondary" value="<?php _e( 'Change Domain Name', 'WPMove' ); ?>" />
					</form>
				</div>

			<?php
			}
		}

		/**
		 * Handles the advanced migration process.
		 *
		 * @param void
		 * @return void
		 */
		function print_advanced_migration_page() {

			if ( $_POST && check_admin_referer( 'wpmove_advanced_migration_start' ) ) {

				?>
				<div class="wrap">
					<div id="icon-tools" class="icon32">
						<br>
					</div>
					<h2><?php _e( 'Advanced Migration', 'WPMove' ); ?></h2>
					<p>
					<?php

					// Load plugin settings
					$wpmove_options = $this->get_admin_options();

					// Create an array to hold backup files that will be uploaded
					$backups = array();

					// If changing the current domain name is also requested...
					if ( ! empty( $_POST['old_domain_name'] ) && ! empty( $_POST['new_domain_name'] ) ) {

						// Apply filters to the given domain names
						$old_domain_name = esc_url_raw( $_POST['old_domain_name'] );
						$new_domain_name = esc_url_raw( $_POST['new_domain_name'] );

						// Create a backup of the database by changing instances of the old domain name with the newer one
						$db_backups = wpmove_create_db_backup( $wpmove_options['db_chunk_size'], 1, $old_domain_name, $new_domain_name );

					} else {

						// Create a backup of the database
						$db_backups = wpmove_create_db_backup( $wpmove_options['db_chunk_size'] );
					
					}

					// Add names of database backup files to the array of backup files
				 	$backups = array_merge( $backups, $db_backups );

					// Check whether an array is actually posted or not
					if ( isset( $_POST['files'] ) && is_array( $_POST['files'] ) ) {

				 	 	// Use the POST data directly, if the fallback method is being used
				 	 	$files = array_map( 'sanitize_text_field', $_POST['files'] );

				 	 	// Remove non-empty directories from the array
				 	 	$files = array_filter( $files );

					 	// Create chunks from the selected files
					 	$chunks = wpmove_divide_into_chunks( $files, $wpmove_options['fs_chunk_size'] );

					 	// To prevent overwriting archives created in the same second
					 	$chunk_id = 1;

					 	// Create an archive of the each chunk
					 	foreach ( $chunks as $chunk )
					 		array_push( $backups, wpmove_create_archive( $chunk, ABSPATH, $chunk_id++ ) );			 	
					}

					// Check whether creating backups files succeeded or not
				 	if ( ! file_exists( trailingslashit( WPMOVE_BACKUP_DIR ) . $backups['0'] ) ) {
				 		_e( 'Could not create backup files. Please make sure the backup directory is writable. For further info, please refer to the documentation.', 'WPMove' );
				 	} else {

						// Upload files and display a success message on success
						if ( $this->upload_files( $backups ) ) {

						?>
						<br>
						<?php _e( 'Creating and uploading backups have been completed. You can now go to your new installation and run the migration assistant in Complete Migration mode.', 'WPMove' ); ?>
					</p>
				</div>
						<?php

						} else {

						?>
						<br>
						<?php _e( 'Please check your FTP connection details on the settings page.', 'WPMove' ); ?>
					</p>
				</div>
						<?php

						}
					}

			} else {

				?>

				<div class="wrap">
					<div id="icon-tools" class="icon32">
						<br>
					</div>
					<h2><?php _e( 'Advanced Migration', 'WPMove' ); ?></h2>
					<p>
						<?php _e( 'Please select the files you want to include in the backup from the list below.', 'WPMove' ); ?>
					</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'tools.php?page=wpmove&do=migrate&type=advanced' ) ); ?>">
						<div id="wpmove_change_domain_name">
							<p>
								<?php _e( 'Please enter exact paths to your WordPress installations on both domains without the trailing slash.', 'WPMove' ); ?><br>
							</p>
							<table class="form-table">
								<tbody>
									<tr valign="top">
										<th scope="row">
											<label for="old_domain_name"><?php _e( 'Old Domain Name', 'WPMove' ); ?></label>
										</th>
										<td>
											<input class="regular-text code" id="old_domain_name" name="old_domain_name" type="text" value="<?php echo home_url(); ?>" />
										</td>
									</tr>
									<tr valign="top">
										<th scope="row">
											<label for="new_domain_name"><?php _e( 'New Domain Name', 'WPMove' ); ?></label>
										</th>
										<td>
											<input class="regular-text code" id="new_domain_name" name="new_domain_name" type="text" />
										</td>
									</tr>
								</tbody>
							</table>
							<br>
						</div>
						<?php wp_nonce_field( 'wpmove_advanced_migration_start' ); ?>
						<div id="wpmove_file_tree_buttons" style="display: none;">
							<input type="button" name="wpmove_file_tree_check_all" id="wpmove_file_tree_check_all" class="button-secondary" value="<?php _e( 'Select All', 'WPMove' ); ?>" />
							<input type="button" name="wpmove_file_tree_uncheck_all" id="wpmove_file_tree_uncheck_all" class="button-secondary" value="<?php _e( 'Unselect All', 'WPMove' ); ?>" />
							<input type="button" name="wpmove_toggle_change_domain_name" id="wpmove_toggle_change_domain_name" class="button-secondary" value="<?php _e( 'Change Domain Name', 'WPMove' ); ?>" />
						</div>
						<blockquote>
							<?php

								// To use as a file ID
								$i = 0;

								// List all of the files inside the main directory
								$abspath = substr( ABSPATH, 0, strlen( ABSPATH ) - 1 );
								$files = wpmove_generate_file_tree( $abspath, FALSE, array( WPMOVE_DIR, WPMOVE_BACKUP_DIR, WPMOVE_OLD_BACKUP_DIR ) );

							?>
							<div id="wpmove_file_tree" style="display:none;">
								<ul>
									<?php wpmove_display_file_tree( $files ); ?>
								</ul>
							</div>
							<div id="wpmove_file_tree_loading" style="display:none;">
								<?php echo '<img src="' . WPMOVE_URL . '/libs/js/themes/default/throbber.gif" alt="' . __( 'Loading...', 'WPMove' ) . '" style="vertical-align:middle;" /> <strong>' . __( 'Loading...', 'WPMove' ) . '</strong>'; ?>
							</div>
							<noscript>
								<?php

									// Prepare the file list
									$files = wpmove_list_all_files( $abspath, FALSE, array( WPMOVE_DIR, WPMOVE_BACKUP_DIR, WPMOVE_OLD_BACKUP_DIR ) );

									// Display each file with a checked checkbox
									foreach ( $files as $file ) {
									 	if ( is_file( $file ) ) {
										 	$short_path = str_replace( ABSPATH, '', $file );
											echo '<input id="file-' . $i . '" name="files[]" type="checkbox" value="' . $file . '" checked> <label for="file-' . $i++ . '"><span class="code">' . $short_path . '</span></label><br>';
										}
									}

								?>
							</noscript>
						</blockquote>
						<?php submit_button( __( 'Start Migration', 'WPMove' ), 'primary', 'submit', FALSE ); ?>
					</form>
					<br>
				</div>

			<?php
			}
		}

		/**
		 * Handles uploading processes of the migration
		 *
		 * @param array $files Files to upload
		 * @return bool TRUE on success, FALSE on failure
		 */
		function upload_files( $files ) {

				// Load plugin settings
				$wpmove_options = $this->get_admin_options();

				// Instantiate the FTP class
				$ftp = new ftp();

				// Enter Passive Mode if enabled
				if ( $wpmove_options['ftp_passive_mode'] )
					$ftp->Passive( TRUE );

				echo '<span class="code">';

				printf( __( 'Connecting to %s:%d...', 'WPMove' ), $wpmove_options['ftp_hostname'], $wpmove_options['ftp_port'] );

				// Set the hostname and the port
				$ftp->SetServer( $wpmove_options['ftp_hostname'], intval( $wpmove_options['ftp_port'] ) );

				// Try connecting to the server
				if ( $ftp->connect() ) {

					echo ' <strong>' . __( 'Success!', 'WPMove' ) . '</strong><br>';

					// Display a different message if no password is given
					if ( '' !== $wpmove_options['ftp_password'] )
						printf( __( 'Logging in as %s using password...', 'WPMove' ), $wpmove_options['ftp_username'] );
					else
						printf( __( 'Logging in as %s without a password...', 'WPMove' ), $wpmove_options['ftp_username'] );

					// Login to the server using the supplied credentials
					if ( $ftp->login( $wpmove_options['ftp_username'], $wpmove_options['ftp_password'] ) ) {

						echo ' <strong>' . __( 'Success!', 'WPMove' ) . '</strong><br>' . __( 'Starting uploading files...', 'WPMove' ) . '<br>';

						// Changes the present working directory to the backup directory on the remote server
						$ftp->chdir( $wpmove_options['ftp_remote_path'] );

						// Start counting errors during the file upload
						$error_count = 0;

						// Upload the given backup files under the backup folder to the server
						foreach ( $files as $file ) {
							printf( __( '%s is being uploaded...', 'WPMove' ), basename( $file ) );
							if ( FALSE !== ( $ftp->put( trailingslashit( WPMOVE_BACKUP_DIR ) . $file, basename( $file ) ) ) ) {
								echo '<strong>' . __( ' Success!', 'WPMove' ) . '</strong><br>';
							} else {
								echo '<strong>' . __( ' Failed!', 'WPMove' ) . '</strong><br>';
								$error_count++;
							}
						}

						// Notify the user about the errors occured
						if ( $error_count )
							printf( _n( 'Uploading files is completed with %d error...', 'Uploading files is completed with %d errors...', $error_count, 'WPMove' ), $error_count );
						else
							_e( 'Uploading files is completed without an error...', 'WPMove' );

						echo '<br>';
						_e( 'Closing the FTP connection...', 'WPMove' );
						echo '</span><br>';

						// Close the connection
						$ftp->quit();

						// Return TRUE on success
						return TRUE;
					}

					// Close the connection
					$ftp->quit();
				}

				echo ' <strong>' . __( ' Failed!', 'WPMove' ) . '</strong><br>' . __( 'Operation terminated...', 'WPMove' ) . '</span><br>';

				// If it reaches here, apparently it failed
				return FALSE;
		}

		/**
		 * Handles the migration completing process.
		 *
		 * @param void
		 * @return void
		 */
		function print_complete_migration_page() {

			// If the user clicks the proceed link...
			if ( $_POST && check_admin_referer( 'wpmove_complete_migration_start' ) ) {

			?>
				<div class="wrap">
					<div id="icon-tools" class="icon32">
						<br>
					</div>
					<h2><?php _e( 'Completing Migration', 'WPMove' ); ?></h2>
					<br>
					<?php

						// Create a list of all the files inside the backup directory
						$files = wpmove_list_all_files( WPMOVE_BACKUP_DIR, TRUE );

						// Categorize the files listed
						$backups = $this->categorize_files($files);

						// Set the error counter to zero
						$errors_occured = 0;

						// Import every single database backup one by one
						foreach ( $backups['db'] as $file ) {

							echo '<span class="code">';
							printf( __( '%s is being imported... ', 'WPMove' ), basename( $file ) );
							
							if ( wpmove_import_db_backup( basename( $file ) ) ) {
								echo '<b>' . __( 'Success!', 'WPMove' ) . '</b></span><br>';
							} else {
								$errors_occured++;
								echo '<b>' . __( 'Failed!', 'WPMove' ) . '</b></span><br>';
							}
						}

						// Extract every single file system backup one by one
						foreach ( $backups['fs'] as $file ) {

						 	echo '<span class="code">';
							printf( __( '%s is being extracted... ', 'WPMove' ), basename( $file ) );

						 	if ( wpmove_extract_archive( basename( $file ), ABSPATH ) ) {
								echo '<b>' . __( 'Success!', 'WPMove' ) . '</b></span><br>';
						 	} else {
						 	 	$errors_occured++;
								echo '<b>' . __( 'Failed!', 'WPMove' ) . '</b></span><br>';
						 	}
						}

						echo '<br>';

						// If there were errors, notify the user
						if ( $errors_occured > 0 )
							printf( _n( 'Migration has been completed but with %d error.', 'Migration has been completed but with %d errors.', $errors_occured, 'WPMove' ), $errors_occured );
						else
							_e( 'Migration has been completed successfully!', 'WPMove' );
					?>
				</div>
			<?php
			}
			else {
			?>
				<div class="wrap">
					<div id="icon-tools" class="icon32">
						<br>
					</div>
					<h2><?php _e( 'Completing Migration', 'WPMove' ) ?></h2>
					<br>
					<?php

						// Create a list of all the files inside the backup directory
						$files = wpmove_list_all_files( WPMOVE_BACKUP_DIR, TRUE );

						// Categorize the files listed
						$backups = $this->categorize_files( $files );
						$total_files = count( $backups, COUNT_RECURSIVE );

						if ( $total_files > 2 ) {

						?>
						<?php _e( 'Proceeding will use the following files and database backups. You can choose which files to use by going to the Backup Manager.', 'WPMove' ); ?>
						<br><br>
						<table class="wp-list-table widefat fixed" cellspacing="0">
							<thead>
								<tr>
									<th scope="col" id="cb" class="manage-column column-cb check-column" style>
										<input type="checkbox" checked disabled>
									</th>
									<th scope="col" id="name" class="manage-column column-name" style>
										<a href="#"><?php _e( 'Name', 'WPMove' ); ?></a>
									</th>
									<th scope="col" id="type" class="manage-column column-type" style>
										<a href="#"><?php _e( 'Type', 'WPMove' ); ?></a>
									</th>
									<th scope="col" id="size" class="manage-column column-size" style>
										<a href="#"><?php _e( 'Size', 'WPMove' ); ?></a>
									</th>
									<th scope="col" id="date" class="manage-column column-date" style>
										<a href="#"><?php _e( 'Date Created', 'WPMove' ); ?></a>
									</th>
								</tr>
							</thead>
							<tfoot>
								<tr>
									<th scope="col" id="cb" class="manage-column column-cb check-column" style>
										<input type="checkbox" checked disabled>
									</th>
									<th scope="col" id="name" class="manage-column column-name" style>
										<a href="#"><?php _e( 'Name', 'WPMove' ); ?></a>
									</th>
									<th scope="col" id="type" class="manage-column column-type" style>
										<a href="#"><?php _e( 'Type', 'WPMove' ); ?></a>
									</th>
									<th scope="col" id="size" class="manage-column column-size" style>
										<a href="#"><?php _e( 'Size', 'WPMove' ); ?></a>
									</th>
									<th scope="col" id="date" class="manage-column column-date" style>
										<a href="#"><?php _e( 'Date Created', 'WPMove' ); ?></a>
									</th>
								</tr>
							</tfoot>
							<tbody id="the-list">
								<?php

									// For zebra striping
									$i = 0;

									foreach ( $files as $file ) {

										// Get the file extension
									 	$ext = substr( $file, -3, 3 );

										// Decide the type of the backup
										if ( 'sql' == $ext ) {
											preg_match( '/DBBackup-([0-9]*).sql/', basename( $file ), $timestamp );
											$type = __( 'Database Backup', 'WPMove' );
										} else if ( 'zip' == $ext ) {
											preg_match( '/Backup-([0-9]*).zip/', basename( $file ), $timestamp );
											$type = __( 'Filesystem Backup', 'WPMove' );
										}

										// For zebra striping
									 	if ( $i % 2 !== 0 )
										 	$class = ' class="alternate"';
										else
											$class = '';

										// Display the row
										echo '	<tr id="file-' . $i . '" valign="middle"' . $class . '>
													<th scope="row" class="check-column">
														<input id="file-' . $i . '" name="files[]" type="checkbox" value="' . $file . '" checked disabled>
													</th>
													<td class="column-name">
														<strong>
															<a href="#">' . esc_html( basename( $file ) ) . '</a>
														</strong>
													</td>
													<td class="column-type">
														<a href="#">' . esc_html( $type ) . '</a>
													</td>
													<td class="column-size">
														' . esc_html( round( filesize( $file ) / 1024, 2 ) ) . ' KB
													</td>
													<td class="column-date">
														' . esc_html( date( 'd.m.Y H:i:s', substr( $timestamp['1'], 0, 10 )  ) ) . '
													</td>
												</tr>';

										// Increase the counter for zebra striping
										$i++;
									}
								?>
							</tbody>
						</table>
						<br>
						<form method="post" action="<?php echo esc_url( admin_url( 'tools.php?page=wpmove&do=complete' ) ); ?>">
							<?php wp_nonce_field( 'wpmove_complete_migration_start' ); ?>
							<input class="button-primary" type="submit" name="wpmove_complete_migration" value="<?php _e( 'Complete Migration', 'WPMove' ); ?>" />
						</form>
						<?php
						} else {

							_e( 'There are no backup files to use to complete the migration. Please start the migration using WordPress Move on the server you want to migrate from.', 'WPMove' );

						}
						?>
				</div>
			<?php
			}
		}

		/**
		 * Generates the backup manager page of the plugin.
		 *
		 * @param void
		 * @return void
		 */
		function print_backup_manager_page() {

			if ( $_POST && check_admin_referer( 'wpmove_backup_manager_submit' ) ) {

			 	// Set the appropriate target directory depending on the form submitted
				if ( isset( $_POST['wpmove_current_backups'] ) )
					$move_target = WPMOVE_OLD_BACKUP_DIR;
				else if ( isset( $_POST['wpmove_old_backups'] ) )
					$move_target = WPMOVE_BACKUP_DIR;

				// If there's an actual array sent
				if ( is_array( $_POST['files'] ) ) {

					// Sanitize the POST data
					$files = array_map( 'sanitize_text_field', $_POST['files'] );
					$action = sanitize_text_field( $_POST['action'] );

					// Do what's requested
					if ( 'delete' == $action )
						foreach ( $files as $file )
							unlink( $file );
					else if ( 'toggle' == $action )
						foreach ( $files as $file )
							rename( $file, trailingslashit( $move_target ) . basename($file) );
				}

			} else if ( isset( $_GET['do'] ) && 'create' == $_GET['do'] ) {
				
				// Load plugin settings
				$wpmove_options = $this->get_admin_options();

				// An array to hold backup files that will be uploaded
				$backups = array();

				// Create a backup of the database
				$db_backups = wpmove_create_db_backup( $wpmove_options['db_chunk_size'] );
			 	$backups = array_merge( $backups, $db_backups );

				// List all of the files inside the main directory
				$abspath = substr( ABSPATH, 0, strlen( ABSPATH ) - 1 );
				$files = wpmove_list_all_files( $abspath, FALSE, array( WPMOVE_DIR, WPMOVE_BACKUP_DIR, WPMOVE_OLD_BACKUP_DIR ) );

			 	// Create chunks from the selected files
			 	$chunks = wpmove_divide_into_chunks( $files, $wpmove_options['fs_chunk_size'] );

			 	// To prevent overwriting archives created in the same second
			 	$chunk_id = 1;

			 	// Create an archive of the each chunk
			 	foreach ( $chunks as $chunk )
			 		array_push( $backups, wpmove_create_archive( $chunk, ABSPATH, $chunk_id++ ) );

			}

			?>
			<div class="wrap">
				<div id="icon-tools" class="icon32">
					<br>
				</div>
				<h2><?php _e( 'Backup Manager', 'WPMove' ); ?> <a class="add-new-h2" href="<?php echo esc_url( admin_url( 'tools.php?page=wpmove-backup-manager&do=create' ) ); ?>" title="Create A Backup"><?php _e( 'Backup Now', 'WPMove' ); ?></a></h2>
				<h3><?php _e( 'Current Backups', 'WPMove' ); ?></h3>
				<p>
					<?php _e( 'Below are the files stored under your backup directory. These files will be used if you choose to complete the migration.', 'WPMove' ) ?>
				</p>
				<?php

					// List all current backup files and categorize them
					$files = wpmove_list_all_files( WPMOVE_BACKUP_DIR, TRUE );
					$current_backups = $this->categorize_files( $files );

					// List all old backup files and categorize them
					$old_files = wpmove_list_all_files( WPMOVE_OLD_BACKUP_DIR );
					$old_backups = $this->categorize_files( $old_files );

				?>
				<form method="post" action="<?php echo esc_url( admin_url( 'tools.php?page=wpmove-backup-manager' ) ); ?>">
					<?php wp_nonce_field( 'wpmove_backup_manager_submit' ); ?>
					<div class="tablenav top">
						<div class="alignleft actions">
							<select name="action" size="1" height="1">
								<option value="toggle"><?php _e( 'Archive', 'WPMove' ); ?></option>
								<option value="delete"><?php _e( 'Delete', 'WPMove' ); ?></option>
							</select>
							<?php submit_button( __( 'Apply', 'WPMove' ), 'secondary', 'wpmove_current_backups', FALSE ); ?>
						</div>
					</div>
					<table class="wp-list-table widefat fixed" cellspacing="0">
						<thead>
							<tr>
								<th scope="col" id="cb" class="manage-column column-cb check-column" style>
									<input type="checkbox">
								</th>
								<th scope="col" id="name" class="manage-column column-name" style>
									<a href="#"><?php _e( 'Name', 'WPMove' ); ?></a>
								</th>
								<th scope="col" id="type" class="manage-column column-type" style>
									<a href="#"><?php _e( 'Type', 'WPMove' ); ?></a>
								</th>
								<th scope="col" id="size" class="manage-column column-size" style>
									<a href="#"><?php _e( 'Size', 'WPMove' ); ?></a>
								</th>
								<th scope="col" id="date" class="manage-column column-date" style>
									<a href="#"><?php _e( 'Date Created', 'WPMove' ); ?></a>
								</th>
							</tr>
						</thead>
						<tfoot>
							<tr>
								<th scope="col" id="cb" class="manage-column column-cb check-column" style>
									<input type="checkbox">
								</th>
								<th scope="col" id="name" class="manage-column column-name" style>
									<a href="#"><?php _e( 'Name', 'WPMove' ); ?></a>
								</th>
								<th scope="col" id="type" class="manage-column column-type" style>
									<a href="#"><?php _e( 'Type', 'WPMove' ); ?></a>
								</th>
								<th scope="col" id="size" class="manage-column column-size" style>
									<a href="#"><?php _e( 'Size', 'WPMove' ); ?></a>
								</th>
								<th scope="col" id="date" class="manage-column column-date" style>
									<a href="#"><?php _e( 'Date Created', 'WPMove' ); ?></a>
								</th>
							</tr>
						</tfoot>
						<tbody id="the-list">
							<?php

								// Display a message if no backup files found
								if ( count( $current_backups, COUNT_RECURSIVE ) > 2 ) {

									// For zebra striping
									$i = 0;

									// Display all current backups starting with database backups
									foreach ( $current_backups as $backups ) {

										foreach ( $backups as $file ) {

											// Get the file extension
										 	$ext = substr( $file, -3, 3 );

											// Decide the type of the backup
											if ( 'sql' == $ext ) {
												preg_match( '/DBBackup-([0-9]*).sql/', basename( $file ), $timestamp );
												$type = __( 'Database Backup', 'WPMove' );
											} else if ( 'zip' == $ext ) {
												preg_match( '/Backup-([0-9]*).zip/', basename( $file ), $timestamp );
												$type = __( 'Filesystem Backup', 'WPMove' );
											}

											// For zebra striping
										 	if ( $i % 2 !== 0 )
											 	$class = ' class="alternate"';
											else
												$class = '';

											// Display the row
											echo '	<tr id="file-' . $i . '" valign="middle"' . $class . '>
														<th scope="row" class="check-column">
															<input id="file-' . $i . '" name="files[]" type="checkbox" value="' . $file . '">
														</th>
														<td class="column-name">
															<strong>
																<a href="#">' . esc_html( basename( $file ) ) . '</a>
															</strong>
														</td>
														<td class="column-type">
															<a href="#">' . esc_html( $type ) . '</a>
														</td>
														<td class="column-size">
															' . esc_html( round( filesize( $file ) / 1024, 2 ) ) . ' KB
														</td>
														<td class="column-date">
															' . esc_html( date( 'd.m.Y H:i:s', substr( $timestamp['1'], 0, 10 )  ) ) . '
														</td>
													</tr>';

											// Increase the counter for zebra striping
											$i++;
										}
									}

								} else {

									echo '<tr class="no-items">
										  	<td class="colspanchange" colspan="5">
										  		' . __( 'No backup files found.', 'WPMove' ) . '
										  	</td>
										  </tr>';
								}
							?>
						</tbody>
					</table>
				</form>
				<br>
				<h3><?php _e( 'Old Backups', 'WPMove' ); ?></h3>
				<p>
					<?php _e( 'Below are the files stored under your old backup directory. These files will not be used while completing the migration unless you unarchive them.', 'WPMove' ) ?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'tools.php?page=wpmove-backup-manager' ) ); ?>">
					<?php wp_nonce_field( 'wpmove_backup_manager_submit' ); ?>
					<div class="tablenav top">
						<div class="alignleft actions">
							<select name="action" size="1" height="1">
								<option value="toggle"><?php _e( 'Unarchive', 'WPMove' ); ?></option>
								<option value="delete"><?php _e( 'Delete', 'WPMove' ); ?></option>
							</select>
							<?php submit_button( __( 'Apply', 'WPMove' ), 'secondary', 'wpmove_old_backups', FALSE ); ?>
						</div>
					</div>
					<table class="wp-list-table widefat fixed" cellspacing="0">
						<thead>
							<tr>
								<th scope="col" id="cb" class="manage-column column-cb check-column" style>
									<input type="checkbox">
								</th>
								<th scope="col" id="name" class="manage-column column-name" style>
									<a href="#"><?php _e( 'Name', 'WPMove' ); ?></a>
								</th>
								<th scope="col" id="type" class="manage-column column-type" style>
									<a href="#"><?php _e( 'Type', 'WPMove' ); ?></a>
								</th>
								<th scope="col" id="size" class="manage-column column-size" style>
									<a href="#"><?php _e( 'Size', 'WPMove' ); ?></a>
								</th>
								<th scope="col" id="date" class="manage-column column-date" style>
									<a href="#"><?php _e( 'Date Created', 'WPMove' ); ?></a>
								</th>
							</tr>
						</thead>
						<tfoot>
							<tr>
								<th scope="col" id="cb" class="manage-column column-cb check-column" style>
									<input type="checkbox">
								</th>
								<th scope="col" id="name" class="manage-column column-name" style>
									<a href="#"><?php _e( 'Name', 'WPMove' ); ?></a>
								</th>
								<th scope="col" id="type" class="manage-column column-type" style>
									<a href="#"><?php _e( 'Type', 'WPMove' ); ?></a>
								</th>
								<th scope="col" id="size" class="manage-column column-size" style>
									<a href="#"><?php _e( 'Size', 'WPMove' ); ?></a>
								</th>
								<th scope="col" id="date" class="manage-column column-date" style>
									<a href="#"><?php _e( 'Date Created', 'WPMove' ); ?></a>
								</th>
							</tr>
						</tfoot>
						<tbody id="the-list">
							<?php

								// Display a message if no backup files found
								if ( count( $old_backups, COUNT_RECURSIVE ) > 2 ) {

									// For zebra striping
									$i = 0;

									// Display all old backups starting with database backups
									foreach ( $old_backups as $backups ) {

										foreach ( $backups as $file ) {

											// Get the file extension
										 	$ext = substr( $file, -3, 3 );

											// Decide the backup type
											if ( $ext == 'sql' ) {
												preg_match( '/DBBackup-([0-9]*).sql/', basename( $file ), $timestamp );
												$type = __( 'Database Backup', 'WPMove' );
											} else if ( $ext == 'zip' ) {
												preg_match( '/Backup-([0-9]*).zip/', basename( $file ), $timestamp );
												$type = __( 'Filesystem Backup', 'WPMove' );
											}

											// For zebra striping
										 	if ( $i % 2 !== 0 )
											 	$class = ' class="alternate"';
											else
												$class = '';

											// Display the row
											echo '	<tr id="file-' . $i . '" valign="middle"' . $class . '>
														<th scope="row" class="check-column">
															<input id="file-' . $i . '" name="files[]" type="checkbox" value="' . $file . '">
														</th>
														<td class="column-name">
															<strong>
																<a href="#">' . esc_html( basename( $file ) ) . '</a>
															</strong>
														</td>
														<td class="column-type">
															<a href="#">' . esc_html( $type ) . '</a>
														</td>
														<td class="column-size">
															' . esc_html( round( filesize( $file ) / 1024, 2 ) ) . ' KB
														</td>
														<td class="column-date">
															' . esc_html( date( 'd.m.Y H:i:s', substr( $timestamp['1'], 0, 10 )  ) ) . '
														</td>
													</tr>';
	
											// For zebra striping
											$i++;
										}
									}

								} else {

									echo '<tr class="no-items">
										  	<td class="colspanchange" colspan="5">
										  		' . __( 'No backup files found.', 'WPMove' ) . '
										  	</td>
										  </tr>';
								}
							?>
						</tbody>
					</table>
				</form>
			</div>
			<?php
		}

		/**
		 * Categorizes given files.
		 *
		 * @param array $files Array of files
		 * @return array $backups Array of categorized files
		 */
		function categorize_files( $files ) {

			// Initialize the array
			$backups = array( 'db'	=> array(),
							  'fs'	=> array() );

			// Check the extension of each file and categorize accordingly
			foreach ( $files as $file ) {
			 	$ext = substr( $file, -3, 3 );
				if ( 'sql' == $ext )
					array_push( $backups['db'], $file );
				else if ( 'zip' == $ext )
					array_push( $backups['fs'], $file );
			}

			return $backups;
		}

		/**
		 * Adds a menu to the ACP
		 *
		 * @param void
		 * @return void
		 */
		function wpmove_acp() {

			// Add Migration Assistant and Backup Manager to the Tools menu
			$ma = add_management_page( __( 'Migration Assistant', 'WPMove' ), __( 'Migration Assistant', 'WPMove' ), 'manage_options', 'wpmove', array( &$this, 'print_migration_assistant_page' ) );
			$bm = add_management_page( __( 'Backup Manager', 'WPMove' ), __( 'Backup Manager', 'WPMove' ), 'manage_options', 'wpmove-backup-manager', array( &$this, 'print_backup_manager_page' ) );

			// Add WordPress Move Settings to the Settings menu
		 	$s = add_options_page( __( 'Settings', 'WPMove' ), __( 'WordPress Move', 'WPMove' ), 'manage_options', 'wpmove-settings', array( &$this, 'print_settings_page' ) );

			// Add styles and scripts for Advanced Migration to the queue
			add_action( 'admin_print_scripts-' . $ma, array( $this, 'load_advanced_migration_scripts' ) );
			add_action( 'admin_head-' . $ma, array( $this, 'add_migration_assistant_js' ) );
		}
	}
}

// Instantiate the WPMove class if it doesn't exist.
if ( class_exists( 'WPMove' ) ) {
	$wpm = new WPMove();
}

// If there's an instance of the class available...
if ( isset( $wpm ) ) {

 	// Create a page in the ACP to control the plugin.
	add_action( 'admin_menu', array( &$wpm, 'wpmove_acp' ) );

	// Set which function we should call during the plugin activation process.
	add_action( 'activate_wordpress-move/wordpress-move.php', array( &$wpm, 'init' ) );

	// Hook language file loader to WP init
	add_action( 'init', array( &$wpm, 'load_language_file' ) );
}
?>