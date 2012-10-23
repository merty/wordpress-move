<?php
/*
Plugin Name: WordPress Move
Plugin URI: http://www.mertyazicioglu.com/wordpress-move/
Description: WordPress Move is a migration assistant for WordPress that can take care of changing your domain name and/or moving your database and files to another server. After activating the plugin, please navigate to WordPress Move page under the Settings menu to configure it. Then, you can start using the Migration Assistant under the Tools menu.
Version: 1.3.2
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
define( 'WPMOVE_CONVERTED_BACKUP_DIR', WPMOVE_BACKUP_DIR . '/converted' );
define( 'WPMOVE_OLD_BACKUP_DIR', WPMOVE_BACKUP_DIR . '/old' );
define( 'WPMOVE_URL', WP_PLUGIN_URL . '/' . basename( dirname( __FILE__ ) ) );
define( 'WPMOVE_BACKUP_URL', WPMOVE_URL . '/backup' );
define( 'WPMOVE_CONVERTED_BACKUP_URL', WPMOVE_BACKUP_URL . '/converted' );
define( 'WPMOVE_OLD_BACKUP_URL', WPMOVE_BACKUP_URL . '/old' );

// Load functions needed for database and file operations
require_once( 'libs/functions-database-backup.php' );
require_once( 'libs/functions-file-backup.php' );

// Some operations may exceed the limit set by max_execution_time
if( ! ini_get('safe_mode') )
	set_time_limit(0);

// Load PemFTP's classes if they're not loaded already
if ( ! class_exists( 'ftp_base' ) )
	require_once( ABSPATH . "wp-admin/includes/class-ftp.php" );

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
					$( "#wpmove-ma-domain-desc" ).css( 'min-height', $( "#wpmove-ma-migrate-desc" ).css( 'height' ) );
					$( "#wpmove-ma-restore-desc" ).css( 'min-height', $( "#wpmove-ma-migrate-desc" ).css( 'height' ) );
					if ( $( "#wpmove_file_tree" ).length ) {
					 	$( "#wpmove_file_tree_loading" ).css( 'display', 'block' );
						$( "#wpmove_file_tree" ).bind( "loaded.jstree", function( event, data ) {
							$( "#wpmove_file_tree_loading" ).css( 'display', 'none' );
							$( "#wpmove_file_tree" ).css( 'display', 'block' );
							$( "#wpmove_file_tree_buttons" ).css( 'display', 'block' );
							$( "#wpmove_file_tree_check_all" ).click( function () {	$( "#wpmove_file_tree" ).jstree( "check_all" ); } );
							$( "#wpmove_file_tree_uncheck_all" ).click( function () { $( "#wpmove_file_tree" ).jstree( "uncheck_all" );	} );
							$( "#wpmove_file_tree" ).jstree( "check_all" );
						}).jstree( {
						 	"themes" : { "dots" : false	},
							"types" : {	"valid_children" : [ "file" ], "types" : { "file" : { "icon" : { "image" : "<?php echo WPMOVE_URL; ?>/libs/js/themes/default/file.png" } } } },
							"checkbox" : { "real_checkboxes" : true, "real_checkboxes_names" : function(n) { return [ "files[]", $( n[0] ).children( 'a' ).attr( 'title' ) ]; }	},
							"plugins" : [ "themes", "types", "checkbox", "html_data" ],
						} );
					}
					$( '.if-js-closed' ).removeClass( 'if-js-closed' ).addClass( 'closed' );
					postboxes.add_postbox_toggles( 'wpmove-ma-domain' );
					postboxes.add_postbox_toggles( 'wpmove-ma-migrate' );
					postboxes.add_postbox_toggles( 'wpmove-ma-restore' );
				} );
			</script>
			<?php

		}

		/**
		 * Loads the JS file for Migration Assistant.
		 *
		 * @param void
		 * @return void
		 */
		function load_migration_assistant_scripts() {

			wp_enqueue_script( 'file_tree', '/wp-content/plugins/wordpress-move/libs/js/jquery.jstree.js', array( 'jquery' ) );

			// Load scripts needed for meta boxes
			wp_enqueue_script( 'common' );
			wp_enqueue_script( 'wp-lists' );
			wp_enqueue_script( 'postbox' );

			// Add meta boxes to queue
			add_meta_box( 'wpmove-ma-domain', __( 'Change Domain Name', 'WPMove' ), array( $this, 'metabox_ma_domain' ), 'wpmove-domain' );
			add_meta_box( 'wpmove-ma-migrate', __( 'Migrate', 'WPMove' ), array( $this, 'metabox_ma_migrate' ), 'wpmove-migrate' );
			add_meta_box( 'wpmove-ma-restore', __( 'Restore', 'WPMove' ), array( $this, 'metabox_ma_restore' ), 'wpmove-restore' );
			add_meta_box( 'wpmove-ma-migrate-ftp', __( 'FTP Settings', 'WPMove' ), array( $this, 'metabox_ma_migrate_ftp' ), 'wpmove-ma-migrate' );
			add_meta_box( 'wpmove-ma-migrate-domain', __( 'Change Domain Name (Optional)', 'WPMove' ), array( $this, 'metabox_ma_migrate_domain' ), 'wpmove-ma-migrate' );
			add_meta_box( 'wpmove-ma-migrate-filetree', __( 'Files to Transfer', 'WPMove' ), array( $this, 'metabox_ma_migrate_filetree' ), 'wpmove-ma-migrate' );
		}

		/**
		 * Adds the script to the head of the settings page.
		 *
		 * @param void
		 * @return void
		 */
		function add_settings_page_js() {
			?>
			<script type="text/javascript"> 
				jQuery( document ).ready( function( $ ) {
					$( '.if-js-closed' ).removeClass( 'if-js-closed' ).addClass( 'closed' );
					postboxes.add_postbox_toggles( 'wpmove-settings' );
				} );
			</script>
			<?php
		}

		/**
		 * Loads JS files for the settings page.
		 *
		 * @param void
		 * @return void
		 */
		function load_settings_page_scripts() {

			// Load scripts needed for meta boxes
			wp_enqueue_script( 'common' );
			wp_enqueue_script( 'wp-lists' );
			wp_enqueue_script( 'postbox' );

			// Add meta boxes to queue
			add_meta_box( 'wpmove-ftp-connection-details', __( 'FTP Connection Details', 'WPMove' ), array( $this, 'metabox_ftp_connection_details' ), 'wpmove-settings' );
			add_meta_box( 'wpmove-db-backup-settings', __( 'Database Backup Settings', 'WPMove' ), array( $this, 'metabox_db_backup_settings' ), 'wpmove-settings' );
			add_meta_box( 'wpmove-fs-backup-settings', __( 'File Backup Settings', 'WPMove' ), array( $this, 'metabox_fs_backup_settings' ), 'wpmove-settings' );

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
										   'ftp_passive_mode'	=> 1,
										   'ftp_remote_path'	=> '',
										 );

			// Try retrieving options from the database
			$wpmove_options = get_option( $this->admin_options_name );

			// Deletes the FTP Password stored in the database
			if ( is_array( $wpmove_options ) && array_key_exists( 'ftp_password', $wpmove_options ) )
				unset( $wpmove_options['ftp_password'] );

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
					<?php

						// To make sure the form is submitted via ACP.
						wp_nonce_field( 'wpmove_update_settings' );

						// Needed to be able to toggle meta boxes
						wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
						wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
					
					?>
					<div id="poststuff" class="metabox-holder">
						<?php do_meta_boxes( 'wpmove-settings', 'advanced', $wpmove_options ); ?>
					</div>
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
		 * Callback function for the FTP Connection Details meta box.
		 *
		 * @param $wpmove_options Plugin settings array
		 * @return void
		 */
		function metabox_ftp_connection_details( $wpmove_options ) {

			?>
			<p>
				<?php _e( 'These are the FTP connection details of your new server.', 'WPMove' ); ?>
			</p>
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
							<i><?php _e( 'You will be asked to enter your FTP Password you use to establish an FTP connection to the remote server, right before starting the migration process.', 'WPMove' ); ?></i>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="wpmove_ftp_remote_path"><?php _e( 'Remote Backup Path', 'WPMove' ); ?></label>
						</th>
						<td>
							<input class="regular-text code" id="wpmove_ftp_remote_path" name="wpmove_ftp_remote_path" type="text" value="<?php echo esc_attr( $wpmove_options['ftp_remote_path'] ); ?>" /> <i><?php _e( 'Path from the top directory that your FTP account has access to, to the backup directory of the WordPress Move plugin on the remote server. For instance:', 'WPMove' ); ?> <code>/var/www/wp-content/plugins/wordpress-move/backup/</code></i>
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
			</table>
			<?php

		}

		/**
		 * Callback function for the Database Backup Settings meta box.
		 *
		 * @param $wpmove_options Plugin settings array
		 * @return void
		 */
		function metabox_db_backup_settings( $wpmove_options ) {
			
			?>
			<p>
				<?php _e( 'The size of each chunk of your database backup. Actual sizes of chunks may exceed this size limit. 0 means unlimited.', 'WPMove' ); ?>
			</p>
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
			</table>
			<?php

		}

		/**
		 * Callback function for the File Backup Settings meta box.
		 *
		 * @param $wpmove_options Plugin settings array
		 * @return void
		 */
		function metabox_fs_backup_settings( $wpmove_options ) {

			?>
			<p>
				<?php _e( 'The size of files to compress per filesystem backup chunk. Sizes of chunks will be less than or equal to this size limit, depending on the compression ratio. 0 means unlimited.', 'WPMove' ); ?>
			</p>
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
					case 'migrate':		$this->print_migration_page();
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
					<?php _e( 'Please make sure you read the documentation carefully, before selecting an action to proceed...', 'WPMove' ); ?>
				</p>
				<?php

					// Needed to be able to toggle meta boxes
					wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
					wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );

				?>
				<div id="poststuff" class="metabox-holder">
					<div id="post-body" style="clear:left;display:block;float:left;position:relative;width:32%;">
						<div id="post-body-content">
							<?php do_meta_boxes( 'wpmove-domain', 'advanced', null ); ?>
						</div>
					</div>
					<div id="post-body" style="clear:right;display:block;float:right;position:relative;width:32%;">
						<div id="post-body-content">
							<?php do_meta_boxes( 'wpmove-restore', 'advanced', null ); ?>
						</div>
					</div>
					<div id="post-body" style="margin-left:34%;width:32%;">
						<div id="post-body-content">
							<?php do_meta_boxes( 'wpmove-migrate', 'advanced', null ); ?>
						</div>
					</div>
				</div>
			</div>
			<?php
			}
		}

		/**
		 * Callback function for the Change Domain Name meta box.
		 *
		 * @param void
		 * @return void
		 */
		function metabox_ma_domain() {
			
			?>
			<div id="domain">
				<div id="wpmove-ma-domain-desc">
					<p>
						<strong><?php _e( 'If you wish to do the following...', 'WPMove' ); ?></strong>
					</p>
					<p>
						&nbsp;&nbsp;&nbsp;<strong>&bull;</strong> <?php _e( 'Just change the domain name this installation uses.', 'WPMove' ); ?><br>
					</p>
					<p>
						<strong><?php _e( 'Do not forget that...', 'WPMove' ); ?></strong>
					</p>
					<p>
						&nbsp;&nbsp;&nbsp;<strong>&bull;</strong> <?php _e( 'Your files and database will not be transferred to another server.', 'WPMove' ); ?><br>
						&nbsp;&nbsp;&nbsp;<strong>&bull;</strong> <?php _e( 'Only instances of your old domain name in the database will be replaced.', 'WPMove' ); ?><br>
						&nbsp;&nbsp;&nbsp;<strong>&bull;</strong> <?php _e( 'You need to manually configure your server and new domain name to use it on this server.', 'WPMove' ); ?><br>
						&nbsp;&nbsp;&nbsp;<strong>&bull;</strong> <?php _e( 'A backup of your database will be made available under the backup directory.', 'WPMove' ); ?><br>
						&nbsp;&nbsp;&nbsp;<strong>&bull;</strong> <?php _e( 'Creating a manual backup of your database is still highly encouraged.', 'WPMove' ); ?><br>
					</p>
					<br>
				</div>
				<div id="wpmove-ma-domain-button" align="center">
					<a class="button-primary" href="<?php echo esc_url( admin_url( 'tools.php?page=wpmove&do=domain' ) ); ?>"><?php _e( 'Begin', 'WPMove' ); ?></a>
				</div>
			</div>
			<?php
		}

		/**
		 * Callback function for the Migrate meta box.
		 *
		 * @param void
		 * @return void
		 */
		function metabox_ma_migrate() {
			
			?>
			<div id="migrate">
				<div id="wpmove-ma-migrate-desc">
					<p>
						<strong><?php _e( 'If you wish to do one or more of the following...', 'WPMove' ); ?></strong>
					</p>
					<p>
						&nbsp;&nbsp;&nbsp;<strong>&bull;</strong> <?php _e( 'Transfer your database to another server.	', 'WPMove' ); ?><br>
						&nbsp;&nbsp;&nbsp;<strong>&bull;</strong> <?php _e( 'Transfer some/all of your files to another server.', 'WPMove' ); ?><br>
						&nbsp;&nbsp;&nbsp;<strong>&bull;</strong> <?php _e( 'Use a different domain name on the target server.', 'WPMove' ); ?><br>
					</p>
					<p>
						<strong><?php _e( 'Make sure that...', 'WPMove' ); ?></strong>
					</p>
					<p>
						&nbsp;&nbsp;&nbsp;<strong>&bull;</strong> <?php _e( 'WordPress and WordPress Move are installed on the target server.', 'WPMove' ); ?><br>
					</p>
					<p>
						<strong><?php _e( 'Do not forget that...', 'WPMove' ); ?></strong>
					</p>
					<p>
						&nbsp;&nbsp;&nbsp;<strong>&bull;</strong> <?php _e( 'This installation will stay as-is after the operation.', 'WPMove' ); ?><br>
						&nbsp;&nbsp;&nbsp;<strong>&bull;</strong> <?php _e( 'You need to configure the plugin using the WordPress Move page under the Settings menu.', 'WPMove' ); ?><br>
						&nbsp;&nbsp;&nbsp;<strong>&bull;</strong> <?php _e( 'You need to manually configure your existing domain to use it on the target server.', 'WPMove' ); ?><br>
					</p>
					<br>
				</div>
				<div id="wpmove-ma-migrate-button" align="center">
					<a class="button-primary" href="<?php echo esc_url( admin_url( 'tools.php?page=wpmove&do=migrate' ) ); ?>"><?php _e( 'Begin', 'WPMove' ); ?></a>
				</div>
			</div>
			<?php
		}

		/**
		 * Callback function for the Restore meta box.
		 *
		 * @param void
		 * @return void
		 */
		function metabox_ma_restore() {
			
			?>
			<div id="restore">
				<div id="wpmove-ma-restore-desc">
					<p>
						<strong><?php _e( 'If you wish to do one or more of the following...', 'WPMove' ); ?></strong>
					</p>
					<p>
						&nbsp;&nbsp;&nbsp;<strong>&bull;</strong> <?php _e( 'Complete migrating to this server.', 'WPMove' ); ?><br>
						&nbsp;&nbsp;&nbsp;<strong>&bull;</strong> <?php _e( 'Restore backup files listed under the Current Backups section of the Backup Manager.', 'WPMove' ); ?><br>
					</p>
					<p>
						<strong><?php _e( 'Make sure that...', 'WPMove' ); ?></strong>
					</p>
					<p>
						&nbsp;&nbsp;&nbsp;<strong>&bull;</strong> <?php _e( 'You have backup files to use for this process under the backup directory.', 'WPMove' ); ?><br>
					</p>
					<p>
						<strong><?php _e( 'Do not forget that...', 'WPMove' ); ?></strong>
					</p>
					<p>
						&nbsp;&nbsp;&nbsp;<strong>&bull;</strong> <?php _e( 'Backups will be processed starting from old to new.', 'WPMove' ); ?><br>
					</p>
					<br>
				</div>
				<div id="wpmove-ma-restore-button" align="center">
					<a class="button-primary" href="<?php echo esc_url( admin_url( 'tools.php?page=wpmove&do=complete' ) ); ?>"><?php _e( 'Begin', 'WPMove' ); ?></a>
				</div>
			</div>
			<?php
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

				// If everything went well...
				if ( ! $errors_occured ) {

					// Move the backup files we created "just in case" to the old backup directory
					foreach ( $db_backups as $backup )
						rename( trailingslashit( WPMOVE_BACKUP_DIR ) . $backup, trailingslashit( WPMOVE_OLD_BACKUP_DIR ) . $backup );

					// Remove the backup files we've just imported as we won't need them anymore
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

						// If rolling back succeeds...
						if ( ! $errors_occured ) {

							_e( 'Changes on your domain has been rolled back automatically.', 'WPMove' );

							// Move the backup files we created "just in case" to the old backup directory, again, just in case
							foreach ( $db_backups as $backup )
								rename( trailingslashit( WPMOVE_BACKUP_DIR ) . $backup, trailingslashit( WPMOVE_OLD_BACKUP_DIR ) . $backup );

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
		 * Handles the advanced migration process.
		 *
		 * @param void
		 * @return void
		 */
		function print_migration_page() {

			// Load plugin settings
			$wpmove_options = $this->get_admin_options();

			// If the FTP details are not on file, redirect the user to the settings page
		 	if ( $wpmove_options['ftp_hostname'] == '' || $wpmove_options['ftp_username'] == '' || $wpmove_options['ftp_port'] == 0 ) {
				echo '<meta http-equiv="refresh" content="0;url=options-general.php?page=wpmove-settings&ref=ma" />';
			}

			if ( $_POST && check_admin_referer( 'wpmove_advanced_migration_start' ) ) {

				?>
				<div class="wrap">
					<div id="icon-tools" class="icon32">
						<br>
					</div>
					<h2><?php _e( 'Migration Assistant', 'WPMove' ); ?></h2>
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
						if ( $this->upload_files( $backups, sanitize_text_field( $_POST['ftp_password'] ) ) ) {

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
					<h2><?php _e( 'Migration Assistant', 'WPMove' ); ?></h2>
					<p>
						<?php _e( 'Please select the files you want to include in the backup from the list below.', 'WPMove' ); ?>
					</p>
					<div id="poststuff" class="metabox-holder">
						<form method="post" action="<?php echo esc_url( admin_url( 'tools.php?page=wpmove&do=migrate&type=advanced' ) ); ?>">
							<?php
								wp_nonce_field( 'wpmove_advanced_migration_start' );
								wp_nonce_field( 'closedpostboxes', 'closedpostboxesnonce', false );
								wp_nonce_field( 'meta-box-order', 'meta-box-order-nonce', false );
								do_meta_boxes( 'wpmove-ma-migrate', 'advanced', null );
								submit_button( __( 'Start Migration', 'WPMove' ), 'primary', 'submit', FALSE );
							?>
						</form>
					</div>
					<br>
				</div>
			<?php
			}
		}

		/**
		 * Callback function for the Migration FTP Settings meta box.
		 *
		 * @param $wpmove_options Plugin settings array
		 * @return void
		 */
		function metabox_ma_migrate_ftp() {

			?>
			<p>
				<?php _e( 'If your FTP account uses a password, please enter it below.', 'WPMove' ); ?><br>
				<blockquote>
					<b><?php _e( 'FTP Password:', 'WPMove' ); ?></b> <input id="ftp_password" name="ftp_password" type="password" /><br>
				</blockquote>
			</p>
			<?php

		}

		/**
		 * Callback function for the Migration Change Domain Name meta box.
		 *
		 * @param $wpmove_options Plugin settings array
		 * @return void
		 */
		function metabox_ma_migrate_domain() {

			?>
			<p>
				<?php _e( 'Please enter the exact path to your WordPress installation on your new domain name without the trailing slash and then click Start Migration button to start the migration process.', 'WPMove' ); ?><br>
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
			<?php

		}

		/**
		 * Callback function for the Migration Files to Transfer meta box.
		 *
		 * @param $wpmove_options Plugin settings array
		 * @return void
		 */
		function metabox_ma_migrate_filetree() {

			?>
			<p id="wpmove_file_tree_buttons" style="display: none;">
				<input type="button" name="wpmove_file_tree_check_all" id="wpmove_file_tree_check_all" class="button-secondary" value="<?php _e( 'Select All', 'WPMove' ); ?>" />
				<input type="button" name="wpmove_file_tree_uncheck_all" id="wpmove_file_tree_uncheck_all" class="button-secondary" value="<?php _e( 'Unselect All', 'WPMove' ); ?>" />
				<input type="button" name="wpmove_toggle_change_domain_name" id="wpmove_toggle_change_domain_name" class="button-secondary" value="<?php _e( 'Change Domain Name', 'WPMove' ); ?>" style="display:none;" />
			</p>
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
			<?php

		}

		/**
		 * Handles uploading processes of the migration
		 *
		 * @param array $files Files to upload
		 * @param string $ftp_password FTP Password
		 * @return bool TRUE on success, FALSE on failure
		 */
		function upload_files( $files, $ftp_password ) {

				// Load plugin settings
				$wpmove_options = $this->get_admin_options();

				// Instantiate the FTP class
				$ftp = new ftp();

				// Enter Passive Mode if enabled
				if ( $wpmove_options['ftp_passive_mode'] )
					$ftp->Passive( TRUE );

				echo '<span class="code">';

				printf( __( 'Connecting to %s:%d...', 'WPMove' ), $wpmove_options['ftp_hostname'], $wpmove_options['ftp_port'] );
				$this->flush_output();

				// Set the hostname and the port
				$ftp->SetServer( $wpmove_options['ftp_hostname'], intval( $wpmove_options['ftp_port'] ) );

				// Try connecting to the server
				if ( $ftp->connect() ) {

					echo ' <strong>' . __( 'Success!', 'WPMove' ) . '</strong><br>';
					$this->flush_output();

					// Display a different message if no password is given
					if ( '' !== $ftp_password )
						printf( __( 'Logging in as %s using password...', 'WPMove' ), $wpmove_options['ftp_username'] );
					else
						printf( __( 'Logging in as %s without a password...', 'WPMove' ), $wpmove_options['ftp_username'] );

					$this->flush_output();

					// Login to the server using the supplied credentials
					if ( $ftp->login( $wpmove_options['ftp_username'], $ftp_password ) ) {

						echo ' <strong>' . __( 'Success!', 'WPMove' ) . '</strong><br>' . __( 'Starting uploading files...', 'WPMove' ) . '<br>';

						$this->flush_output();

						// Changes the present working directory to the backup directory on the remote server
						$ftp->chdir( $wpmove_options['ftp_remote_path'] );

						// Start counting errors during the file upload
						$error_count = 0;

						// Upload the given backup files under the backup folder to the server
						foreach ( $files as $file ) {
							printf( __( '%s is being uploaded...', 'WPMove' ), basename( $file ) );
							$this->flush_output();
							if ( FALSE !== ( $ftp->put( trailingslashit( WPMOVE_BACKUP_DIR ) . $file, basename( $file ) ) ) ) {
								echo '<strong>' . __( ' Success!', 'WPMove' ) . '</strong><br>';
							} else {
								echo '<strong>' . __( ' Failed!', 'WPMove' ) . '</strong><br>';
								$error_count++;
							}
							$this->flush_output();
						}

						// Notify the user about the errors occured
						if ( $error_count )
							printf( _n( 'Uploading files is completed with %d error...', 'Uploading files is completed with %d errors...', $error_count, 'WPMove' ), $error_count );
						else
							_e( 'Uploading files is completed without an error...', 'WPMove' );

						$this->flush_output();

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
						// If there's an actual array sent
						if ( isset( $_POST['files'] ) && is_array( $_POST['files'] ) ) {

							// Sanitize the POST data
							$files = array_map( 'sanitize_text_field', $_POST['files'] );

							// Set the error counter to zero
							$errors_occured = 0;

							// Check the extension of each file to import database backups and extract filesystem backups
							foreach ( $files as $file ) {

							 	$ext = substr( $file, -3, 3 );

								if ( 'sql' == $ext ) {

									echo '<span class="code">';
									printf( __( '%s is being imported...', 'WPMove' ), basename( $file ) );

									$this->flush_output();

									echo ' <b>';
									
									if ( wpmove_import_db_backup( basename( $file ) ) ) {
										_e( 'Success!', 'WPMove' );
									} else {
										$errors_occured++;
										_e( 'Failed!', 'WPMove' );
										if ( ! is_readable( $file ) )
											echo '&nbsp;' . __( 'Check file permissions...', 'WPMove' );
									}

									echo '</b></span><br>';

								} else if ( 'zip' == $ext ) {
									
									echo '<span class="code">';
									printf( __( '%s is being extracted...', 'WPMove' ), basename( $file ) );

									$this->flush_output();

									echo ' <b>';

								 	if ( wpmove_extract_archive( basename( $file ), ABSPATH ) ) {
										_e( 'Success!', 'WPMove' );
								 	} else {
								 	 	$errors_occured++;
										_e( 'Failed!', 'WPMove' );
										if ( ! is_readable( $file ) )
											echo '&nbsp;' . __( 'Check file permissions...', 'WPMove' );
								 	}

								 	echo '</b></span><br>';
								}
							}
						}

						// If there were errors, notify the user
						if ( ! isset( $errors_occured ) )
							_e( 'Please select files to migrate before proceeding!', 'WPMove' );
						else {
							echo '<br />';
							if ( $errors_occured > 0 )
								printf( _n( 'Migration has been completed but with %d error.', 'Migration has been completed but with %d errors.', $errors_occured, 'WPMove' ), $errors_occured );
							else
								_e( 'Migration has been completed successfully!', 'WPMove' );
						}

					?>
				</div>
			<?php
			} else {
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

						if ( count( $files ) > 1 ) {

					?>
					<?php _e( 'Below are the files stored under the main backup directory. Please select backup files below to proceed.', 'WPMove' ); ?>
					<br><br>
					<form method="post" action="<?php echo esc_url( admin_url( 'tools.php?page=wpmove&do=complete' ) ); ?>">
						<table class="wp-list-table widefat fixed" cellspacing="0">
							<thead>
								<tr>
									<th scope="col" id="cb" class="manage-column column-cb check-column" style>
										<input type="checkbox" checked>
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
										<input type="checkbox" checked>
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
										} else {
											continue;
										}

										// For zebra striping
									 	if ( $i % 2 !== 0 )
										 	$class = ' class="alternate"';
										else
											$class = '';

										// Display the row
										echo '	<tr id="file-' . $i . '" valign="middle"' . $class . '>
													<th scope="row" class="check-column">
														<input id="file-' . $i . '" name="files[]" type="checkbox" value="' . $file . '" checked>
													</th>
													<td class="column-name">
														<strong>
															<a href="' . esc_url( trailingslashit( WPMOVE_BACKUP_URL ) . basename( $file ) ) . '">' . esc_html( basename( $file ) ) . '</a>
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

			if ( $_POST && 'manage' == $_POST['act'] && check_admin_referer( 'wpmove_backup_manager_submit' ) ) {

			 	// Set the appropriate target directory depending on the form submitted
				if ( isset( $_POST['wpmove_current_backups'] ) )
					$move_target = WPMOVE_OLD_BACKUP_DIR;
				else if ( isset( $_POST['wpmove_old_backups'] ) )
					$move_target = WPMOVE_BACKUP_DIR;

				// If there's an actual array sent
				if ( isset( $_POST['files'] ) && is_array( $_POST['files'] ) ) {

					// Sanitize the POST data
					$files = array_map( 'sanitize_text_field', $_POST['files'] );
					$action = sanitize_text_field( $_POST['action'] );

					// Do what's requested
					if ( 'delete' == $action )
						foreach ( $files as $file )
							unlink( $file );
					else if ( 'toggle' == $action )
						foreach ( $files as $file )
							rename( $file, trailingslashit( $move_target ) . basename( $file ) );
					else if ( 'convert' == $action )
						foreach ( $files as $file )
							wpmove_convert_db_backup( $file );
				}

			} else if ( $_POST && 'create' == $_POST['act'] && check_admin_referer( 'wpmove_backup_manager_create_backup' ) ) {
				
				// Load plugin settings
				$wpmove_options = $this->get_admin_options();

				// An array to hold backup files that will be uploaded
				$backups = array();

				// Create a backup of the database
				wpmove_create_db_backup( $wpmove_options['db_chunk_size'] );

				if ( isset( $_POST['wpmove_create_full_backup'] ) ) {

					// List all of the files inside the main directory
					$abspath = substr( ABSPATH, 0, strlen( ABSPATH ) - 1 );
					$files = wpmove_list_all_files( $abspath, FALSE, array( WPMOVE_DIR, WPMOVE_BACKUP_DIR, WPMOVE_OLD_BACKUP_DIR ) );

				 	// Create chunks from the selected files
				 	$chunks = wpmove_divide_into_chunks( $files, $wpmove_options['fs_chunk_size'] );

				 	// To prevent overwriting archives created in the same second
				 	$chunk_id = 1;

				 	// Create an archive of the each chunk
				 	foreach ( $chunks as $chunk )
				 		wpmove_create_archive( $chunk, ABSPATH, $chunk_id++ );

				}

			}

			?>
			<div class="wrap">
				<div id="icon-tools" class="icon32">
					<br>
				</div>
				<h2><?php _e( 'Backup Manager', 'WPMove' ); ?> <a class="add-new-h2" href="<?php echo esc_url( admin_url( 'tools.php?page=wpmove-backup-manager&do=create' ) ); ?>" title="Create A Backup"><?php _e( 'Backup Now', 'WPMove' ); ?></a></h2>
				<h3><?php _e( 'Backup Now', 'WPMove' ); ?></h3>
				<p>
					<?php _e( 'You can always create backups of your WordPress installation to use as restoration points. Select one of the methods below to create a quick backup.', 'WPMove' ); ?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'tools.php?page=wpmove-backup-manager' ) ); ?>">
					<input name="act" type="hidden" value="create" />
					<?php
						wp_nonce_field( 'wpmove_backup_manager_create_backup' );
						submit_button( __( 'Create a Database Backup', 'WPMove' ), 'secondary', 'wpmove_create_database_backup', FALSE );
						echo '&nbsp;';
						submit_button( __( 'Create a Full Backup', 'WPMove' ), 'secondary', 'wpmove_create_full_backup', FALSE );
					?>
				</form>			
				<br>
				<h3><?php _e( 'Current Backups', 'WPMove' ); ?></h3>
				<p>
					<?php _e( 'Below are the files stored under your backup directory. These files will be used if you choose to complete the migration.', 'WPMove' ); ?>
				</p>
				<?php

					// List all current backup files
					$current_backups = wpmove_list_all_files( WPMOVE_BACKUP_DIR, TRUE );

					// List all old backup files
					$old_backups = wpmove_list_all_files( WPMOVE_OLD_BACKUP_DIR );

					// List all converted database backup files
					$converted_backups = wpmove_list_all_files( WPMOVE_CONVERTED_BACKUP_DIR );

				?>
				<form method="post" action="<?php echo esc_url( admin_url( 'tools.php?page=wpmove-backup-manager' ) ); ?>">
					<?php wp_nonce_field( 'wpmove_backup_manager_submit' ); ?>
					<input name="act" type="hidden" value="manage" />
					<div class="tablenav top">
						<div class="alignleft actions">
							<select name="action" size="1" height="1">
								<option value="toggle"><?php _e( 'Archive', 'WPMove' ); ?></option>
								<option value="convert"><?php _e( 'Convert', 'WPMove' ); ?></option>
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
							if ( count( $current_backups ) > 1 ) {

								// For zebra striping
								$i = 0;

								// Display all current backups starting with database backups
								foreach ( $current_backups as $file ) {

									// Get the file extension
								 	$ext = substr( $file, -3, 3 );

									// Decide the type of the backup
									if ( 'sql' == $ext ) {
										preg_match( '/DBBackup-([0-9]*).sql/', basename( $file ), $timestamp );
										$type = __( 'Database Backup', 'WPMove' );
									} else if ( 'zip' == $ext ) {
										preg_match( '/Backup-([0-9]*).zip/', basename( $file ), $timestamp );
										$type = __( 'Filesystem Backup', 'WPMove' );
									} else {
										continue;
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
														<a href="' . esc_url( trailingslashit( WPMOVE_BACKUP_URL ) . basename( $file ) ) . '">' . esc_html( basename( $file ) ) . '</a>
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
					<input name="act" type="hidden" value="manage" />
					<div class="tablenav top">
						<div class="alignleft actions">
							<select name="action" size="1" height="1">
								<option value="toggle"><?php _e( 'Unarchive', 'WPMove' ); ?></option>
								<option value="convert"><?php _e( 'Convert', 'WPMove' ); ?></option>
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
							if ( count( $old_backups ) > 1 ) {

								// For zebra striping
								$i = 0;

								// Display all old backups starting with database backups
								foreach ( $old_backups as $file ) {

									// Get the file extension
								 	$ext = substr( $file, -3, 3 );

									// Decide the backup type
									if ( $ext == 'sql' ) {
										preg_match( '/DBBackup-([0-9]*).sql/', basename( $file ), $timestamp );
										$type = __( 'Database Backup', 'WPMove' );
									} else if ( $ext == 'zip' ) {
										preg_match( '/Backup-([0-9]*).zip/', basename( $file ), $timestamp );
										$type = __( 'Filesystem Backup', 'WPMove' );
									} else {
										continue;
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
														<a href="' . esc_url( trailingslashit( WPMOVE_OLD_BACKUP_URL ) . basename( $file ) ) . '">' . esc_html( basename( $file ) ) . '</a>
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
				<h3><?php _e( 'Converted Database Backups', 'WPMove' ); ?></h3>
				<p>
					<?php _e( 'Below are the converted database backup files which, unlike the files listed above, can be used outside WordPress Move. You may need the converted versions of your databsae backups if the plugin fails to migrate your installation properly. These files will not be used by the plugin at any stage.', 'WPMove' ); ?>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'tools.php?page=wpmove-backup-manager' ) ); ?>">
					<?php wp_nonce_field( 'wpmove_backup_manager_submit' ); ?>
					<input name="act" type="hidden" value="manage" />
					<div class="tablenav top">
						<div class="alignleft actions">
							<select name="action" size="1" height="1">
								<option value="delete"><?php _e( 'Delete', 'WPMove' ); ?></option>
							</select>
							<?php submit_button( __( 'Apply', 'WPMove' ), 'secondary', 'wpmove_converted_backups', FALSE ); ?>
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
							if ( count( $converted_backups ) > 1 ) {

								// For zebra striping
								$i = 0;

								// Display all current backups starting with database backups
								foreach ( $converted_backups as $file ) {

									// Get the file extension
								 	$ext = substr( $file, -3, 3 );

									// Decide the type of the backup
									if ( $ext == 'sql' ) {
										preg_match( '/DBBackup-([0-9]*).sql/', basename( $file ), $timestamp );
										$type = __( 'Database Backup', 'WPMove' );
									} else {
										continue;
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
														<a href="' . esc_url( trailingslashit( WPMOVE_CONVERTED_BACKUP_URL ) . basename( $file ) ) . '">' . esc_html( basename( $file ) ) . '</a>
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
							} else {

								echo '<tr class="no-items">
									  	<td class="colspanchange" colspan="5">
									  		' . __( 'No converted database backup files found. You can convert a database backup file using the Convert option from the dropdown lists above.', 'WPMove' ) . '
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
		 * Flushes the output buffer.
		 *
		 * @param void
		 * @return void
		 */
		function flush_output() {
			wp_ob_end_flush_all();
			flush();
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
			add_action( 'admin_print_scripts-' . $ma, array( $this, 'load_migration_assistant_scripts' ) );
			add_action( 'admin_head-' . $ma, array( $this, 'add_migration_assistant_js' ) );
			add_action( 'admin_head-' . $s, array( $this, 'add_settings_page_js' ) );
			add_action( 'load-' . $s, array( $this, 'load_settings_page_scripts' ) );

		}
	}
}

// Instantiate the WPMove class if it doesn't exist.
if ( class_exists( 'WPMove' ) )
	$wpm = new WPMove();

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
