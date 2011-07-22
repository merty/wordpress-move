<?php
/**
 * WP Move Database Backup Functions
 *
 * @author Mert Yazicioglu
 * @date 2011-07-07 00:41:00 +03:00
 */

/**
 * Creates the backup of the database.
 *
 * @param	integer $chunk_size Size of the each chunk
 * @param 	string	$old_url	URL to replace
 * @param 	string	$new_url	URL to replace with
 * @return	array	$filenames	Array of database backup files' names
 */
function wpmove_create_db_backup( $chunk_size = 0, $old_url = NULL, $new_url = NULL ) {

	global $wpdb;
	$queries = "";
	$filenames = array();
	$chunk_id = 1;
	
	$filename = 'DBBackup-' . time() . $chunk_id++ . '.sql';
	$output = fopen( WP_PLUGIN_DIR . '/wordpress-move/backup/' . $filename, 'w+' );
	
	array_push( $filenames, $filename );
	
	if ( ! is_null( $old_url ) && ! is_null( $new_url ) )
		define( "REPLACEMENT_MODE", TRUE );

	$cnt = $wpdb->query( 'SHOW TABLES' );
	$row = $wpdb->get_results( 'SHOW TABLES', ARRAY_N );

	for ( $i = 0; $i < $cnt; $i++ )
		$tables[$i] = $row[$i][0];

	foreach ( $tables as $table ) {

		$cnt_fields = $wpdb->query( 'SELECT * FROM ' . $table );

		$queries .= "DROP TABLE IF EXISTS " . $table . ";";
		$res = $wpdb->get_results( 'SHOW CREATE TABLE ' . $table, ARRAY_N );
		$queries .= "\n\n" . $res[0][1] . ";\n\n";

		$row = $wpdb->get_results( 'SELECT * FROM ' . $table, ARRAY_N );

		for ( $i = 0; $i < $cnt_fields; $i++ ){

			if ( $chunk_size > 0 && strlen( $queries ) > ( $chunk_size * 1024 * 1024 ) ) {
				fwrite( $output, $queries );
				fclose( $output );
				$queries = "";
				$filename = 'DBBackup-' . time() . $chunk_id++ . '.sql';
				$output = fopen( WP_PLUGIN_DIR . '/wordpress-move/backup/' . $filename, 'w+' );
				array_push( $filenames, $filename );
			}

			$queries .= "INSERT INTO " . $table . " VALUES(";

			$j = 0;

			while ( isset( $row[$i][$j] ) ) {
				$row[$i][$j] = addslashes( $row[$i][$j] );
				$row[$i][$j] = ereg_replace( "\n", "\\n", $row[$i][$j] );

				if ( isset( $row[$i][$j] ) ) {
					if ( REPLACEMENT_MODE )
						$queries .= '"' . addslashes( wpmove_replace_url( $old_url, $new_url, stripslashes( $row[$i][$j] ) ) ) . '",';
					else
						$queries .= '"' . $row[$i][$j] . '",';
				}
				else
					$queries .= '"",';

				$j++;
			}

			$queries = substr( $queries, 0, strlen( $queries ) - 1 );
			$queries .= ");\n";
		}
		
		$queries .= "\n\n\n";
	}
	
	fwrite( $output, $queries );
	fclose( $output );
	
	return $filenames;
}

/**
 * Imports the database backup.
 *
 * @param	string	$filename	Name of the backup file
 * @return	void
 */
function wpmove_import_db_backup( $filename ) {

	global $wpdb;
	
	$filename = WP_PLUGIN_DIR . '/wordpress-move/backup/' . $filename;

	$sql = file_get_contents( $filename );

	preg_match_all( "/(CREATE|DROP|INSERT)(.*)[;](\s|[A-Z])/Us", $sql, $queries );

	foreach ( $queries['0'] as $query )
		$wpdb->query( $query );
	
	return TRUE;
}

/**
 * Downloads the database backup.
 *
 * @param 	string	$filename	Filename of the backup file
 * @return	int	 0	If file does not exist
 * @return	int	 1	On success
 */
function wpmove_download_db_backup( $filename ) {
	
	if ( file_exists( $filename ) ) {
		header( 'Content-type: text/plain' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		readfile( $filename );
		return 1;
	}
	
	return 0;
}

/**
 * Lists database backups.
 *
 * @param 	void
 * @return	void
 */
function wpmove_list_db_backups() {
	
	$files = scandir( '.' );
	
	for ( $i = 0; $i < count( $files ); $i++ )
		if ( preg_match( "^DBBackup-([0-9]*).sql^", $files[$i] ) )
			echo $files[$i] . "\t" . filesize( $files[$i] ) . "<br />";
}

/**
 * Removes the database backup.
 *
 * @param 	string	$filename	Filename of the backup file
 * @param 	string	$directory	Directory the file is inside of (optional)
 * @return	void
 */
function wpmove_remove_db_backup( $filename, $directory = NULL ) {
		
	if ( $directory )
		$filename = $directory . $filename;
	else
		$filename = WP_PLUGIN_DIR . '/wordpress-move/backup/' . $filename;

	if ( file_exists( $filename ) )
		unlink( $filename );
}

/**
 * Replaces instances of first argument with
 * 	the second one inside the serialized string.
 *
 * @param 	string	$find		URL to replace
 * @param 	string	$replace	URL to replace with
 * @param 	string	$option		String to search within
 * @return	string	Replaced string
 */
function wpmove_replace_url( $find, $replace, $option ){
	
	if ( is_serialized( $option ) ) {
	
		$option = unserialize( $option );
		
		if ( is_array( $option ) )
			foreach ( $option as $key => $val )
				$option[$key] = wpmove_replace_url( $find, $replace, $val );
		
		$option = serialize( $option );
	} else {
		if ( is_array( $option ) )
			foreach ( $option as $key => $val )
				$option[$key] = wpmove_replace_url( $find, $replace, $val );
		else
			$option = str_replace( $find, $replace, $option );
	}

	return $option;
}
?>