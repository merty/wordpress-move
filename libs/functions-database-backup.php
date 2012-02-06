<?php
/**
 * WP Move Database Backup Functions
 *
 * @author Mert Yazicioglu
 * @date 2011-11-04 02:42:00 +02:00
 */

/**
 * Converts the database backup to an SQL file.
 *
 * @param 	string	$filename	Filename of the backup file
 * @param 	string	$directory	Directory the file is inside of (optional)
 * @return	void
 */
function wpmove_convert_db_backup( $filename ) {

	if ( file_exists( $filename ) && preg_match( '/DBBackup-([0-9]*).sql/', basename( $filename ) ) ) {

		// Read the whole database backup file into a variable
		if ( $f = fopen( $filename, 'r' ) ) {
			$serialized = fread( $f, filesize( $filename ) );
			fclose( $f );
		} else {
			return false;
		}

		// Unserialize the queries
		$queries = unserialize( $serialized );

		// Create an output file
		$output = touch( trailingslashit( WPMOVE_CONVERTED_BACKUP_DIR ) . 'Converted-' . basename( $filename ) );

		// Display an error message if creating an output file fails
		if ( ! $output )
			return false;

		// Open the output file and write each query one by one
		if ( $f = fopen( trailingslashit( WPMOVE_CONVERTED_BACKUP_DIR ) . 'Converted-' . basename( $filename ), 'w' ) ) {
			foreach ( $queries as $q )
				fwrite( $f, $q, strlen( $q ) );
			fclose( $f );
		} else {
			return false;
		}

		return true;
	}

	return false;
}

/**
 * Creates the backup of the database.
 *
 * @param	integer $chunk_size Size of the each chunk
 * @param	integer	$chunk_id	ID number for the first chunk
 * @param 	string	$old_url	URL to replace
 * @param 	string	$new_url	URL to replace with
 * @return	array	$filenames	Array of database backup files' names
 */
function wpmove_create_db_backup( $chunk_size = 0, $chunk_id = 1, $old_url = NULL, $new_url = NULL ) {

	global $wpdb;

	$queries = array();
	$filenames = array();
	$replacement_mode = FALSE;

	$filename = 'DBBackup-' . time() . $chunk_id++ . '.sql';
	$output = fopen( trailingslashit( WPMOVE_BACKUP_DIR ) . $filename, 'w+' );

	array_push( $filenames, $filename );

	if ( ! is_null( $old_url ) && ! is_null( $new_url ) )
		$replacement_mode = TRUE;

	$cnt = $wpdb->query( 'SHOW TABLES' );
	$row = $wpdb->get_results( 'SHOW TABLES', ARRAY_N );

	for ( $i = 0; $i < $cnt; $i++ )
		$tables[$i] = $row[$i][0];

	foreach ( $tables as $table ) {

		$cnt_fields = $wpdb->query( 'SELECT * FROM ' . $table );

		array_push( $queries, "DROP TABLE IF EXISTS " . $table . ";" );

		$res = $wpdb->get_results( 'SHOW CREATE TABLE ' . $table, ARRAY_N );
		array_push( $queries, $res[0][1] . ";" );

		$row = $wpdb->get_results( 'SELECT * FROM ' . $table, ARRAY_N );

		for ( $i = 0; $i < $cnt_fields; $i++ ) {

			if ( $chunk_size > 0 && strlen( serialize( $queries ) ) > ( $chunk_size * 1024 * 1024 ) ) {
				fwrite( $output, serialize( $queries ) );
				fclose( $output );
				$queries = array();
				$filename = 'DBBackup-' . time() . $chunk_id++ . '.sql';
				$output = fopen( trailingslashit( WPMOVE_BACKUP_DIR ) . $filename, 'w+' );
				array_push( $filenames, $filename );
			}

			$query = "INSERT INTO " . $table . " VALUES( ";

			$j = 0;

			$values = array();

			while ( isset( $row[$i][$j] ) ) {

				if ( strstr( $row[$i][$j], '_site_transient_' ) || strstr( $row[$i][$j], '_transient_' ) )
					continue 2;

			 	if ( is_int( $row[$i][$j] ) )
				 	$query .= "%d, ";
				else
					$query .= "%s, ";   

				if ( $replacement_mode )
					array_push( $values, wpmove_replace_url( $old_url, $new_url, $row[$i][$j] ) );
				else
					array_push( $values, $row[$i][$j] );

				$j++;
			}

			$query = substr( $query, 0, strlen( $query ) - 2 );

			$query .= " ); ";

			array_push( $queries, $wpdb->prepare( $query, $values ) );
		}
	}

	fwrite( $output, serialize( $queries ) );
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

	$filename = trailingslashit( WPMOVE_BACKUP_DIR ) . $filename;

	$sql = file_get_contents( $filename );

	$queries = unserialize( $sql );

	if ( is_array( $queries ) )
		foreach ( $queries as $query )
			$wpdb->query( $query );
	else
		return FALSE;

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
		$filename = trailingslashit( WPMOVE_BACKUP_DIR ) . $filename;

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
		elseif ( is_string( $option ) )
			$option = str_replace( $find, $replace, $option );
	}

	return $option;
}
?>