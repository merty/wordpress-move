<?php
/**
 * WP Move File Backup Functions
 *
 * @author Mert Yazicioglu
 * @date 2012-02-29 17:48:00 +02:00
 */

require_once( ABSPATH . 'wp-admin/includes/class-pclzip.php' );

/**
 * Creates an archive of the given files.
 *
 * @param	array	$files		Array of files to archive
 * @param	string	$ommit_path	Path to ommit
 * @param	integer	$id			To prevent overwriting archives created in the same second
 * @return	string  $filename	Filename of the archive created
 */
function wpmove_create_archive( $files, $ommit_path = '', $id = 0 ) {
	$filename = trailingslashit( WPMOVE_BACKUP_DIR ) . 'Backup-' . time() . $id . '.zip';
	$archive = new PclZip( $filename );
	$archive->add( $files, PCLZIP_OPT_REMOVE_PATH, $ommit_path );
	return basename( $filename );
}

/**
 * Extracts the given archive to the given destination.
 *
 * @param	string	$filename		Archive to extract
 * @param	string	$destination	Directory to extract to
 * @return	array
 */
function wpmove_extract_archive( $filename, $destination ) {
	$archive = new PclZip( trailingslashit( WPMOVE_BACKUP_DIR ) . $filename );
	return $archive->extract( PCLZIP_OPT_PATH, $destination );
}

/**
 * Calculates the total disk space the given directory uses.
 *
 * @param	string	$directory	Directory to calculate the disk usage of
 * @return	integer	$totalUsage	The disk space given directory uses
 */
function wpmove_calculate_total_usage( $directory ) {

	if ( is_file( $directory) )
		return filesize( $directory );
	
	$totalUsage = 0;

	if ( is_array( $fileList = glob( $directory . "/*" ) ) )
		foreach ( $fileList as $file )
			$totalUsage += calculateTotalUsage( $file );
	
	return $totalUsage;
}

/**
 * Creates a list of all the files under the given directory.
 *
 * @param	string	$directory			Directory to list the files of
 * @param	bool	$ignore_directories	Ignores sub directories if set to TRUE
 * @param	array	$ommit				Array of directories to ommit
 * @return	array	$files				File list
 */
function wpmove_list_all_files( $directory, $ignore_directories = FALSE, $ommit = array() ) {

	$files = array();

	if ( is_array( $fileList = glob( $directory . "/*" ) ) )
		foreach ( $fileList as $file ) {
			if ( is_file( $file ) ) {
				array_push( $files, $file );
			} elseif ( ! $ignore_directories ) {
				$skip = FALSE;
			 	foreach ( $ommit as $dir )
			 		if ( $file == $dir )
			 			$skip = TRUE;
			 	if ( ! $skip )
			 		if ( count( @scandir( $file ) ) > 2 )
						$files = array_merge( $files, wpmove_list_all_files( $file, FALSE, $ommit ) );
					else
						array_push( $files, $file );
			}
		}

	return $files;
}

/**
 * Generates a classic file tree.
 *
 * @param	string	$directory			Directory to list the files of
 * @param	bool	$ignore_directories	Ignores sub directories if set to TRUE
 * @return	array	$tree				Generated tree
 */
function wpmove_generate_file_tree( $directory, $ignore_directories = FALSE, $ommit = array() ) {

	$files = array();
	$directories = array();

	if ( is_array( $fileList = glob( $directory . "/*" ) ) )
		foreach ( $fileList as $file ) {
			if ( is_file( $file ) ) {
				array_push( $files, $file );
			} elseif ( ! $ignore_directories ) {
				$skip = FALSE;
			 	foreach ( $ommit as $dir )
			 		if ( $file == $dir )
			 			$skip = TRUE;
			 	if ( ! $skip ) {
			 	 	array_push( $directories, $file );
					array_push( $directories, wpmove_generate_file_tree( $file, FALSE, $ommit ) );
				}
			}
		}

	$tree = array_merge( $directories, $files );

	return $tree;
}

/**
 * Creates lists of files by dividing files inside a directory into chunks.
 *
 * @param 	array	$files		Files to divide into chunks
 * @param	integer	$chunk_size	Size of chunks in bytes
 * @return	array	$chunk		File lists
 */
function wpmove_divide_into_chunks( $files, $chunk_size ) {

	$chunk[0] = array();
	$currentChunk = 0;
	$currentSize = 0;
	$chunk_size = $chunk_size * 1024 * 1024;

	foreach ( $files as $file ) {
		if ( ( $currentSize + filesize( $file ) <= $chunk_size ) ) {
			array_push( $chunk[$currentChunk], $file );
			$currentSize += filesize( $file );
		} else {
			$currentChunk++;
			$currentSize = 0;
			$chunk[$currentChunk] = array();
			array_push( $chunk[$currentChunk], $file );
		}
	}

	return $chunk;
}

/**
 * Displays the file tree using the given array of files
 *
 * @param 	array	$files	Array of files in a tree form
 * @return	integer $i		Updates counter to make sure every item has a unique ID
 */
function wpmove_display_file_tree( $files, $i=0 ) {

	foreach ( $files as $file ) {
	 	if ( ! is_array( $file ) )
	 	 	if ( is_file( $file ) )
			 	echo '<li id="file-' . $i++ . '" rel="file"><a href="#" title="' . $file . '">' . basename( $file ) . '</a></li>';
			elseif ( count( @scandir( $file ) ) > 2 )
				echo '<li id="dir-' . $i++ . '" rel="directory"><a href="#" title="">' . basename( $file ) . '</a><ul>';
			else
				echo '<li id="dir-' . $i++ . '" rel="directory"><a href="#" title="' . $file . '">' . basename( $file ) . '</a><ul>';
		else
			$i = wpmove_display_file_tree( $file, $i );
	}

	echo '</ul></li>';
	
	return $i;
}
?>