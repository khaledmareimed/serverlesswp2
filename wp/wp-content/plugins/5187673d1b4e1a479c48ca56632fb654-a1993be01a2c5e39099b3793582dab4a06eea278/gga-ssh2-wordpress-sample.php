<?php
/*
Plugin Name: GGA SSH2 Sample
Description: Sample SSH2 upload
Author: Pete Nelson (@GunGeekATX)
Version: 1.0
*/

if (!defined( 'ABSPATH' )) exit('restricted access');

add_action( 'admin_init', 'gga_ssh2_sample_upload' );

function gga_ssh2_sample_upload() {

	// this is VERY basic demonstration code for SSHing a file to a server

	// files for the filesytem classes
	require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php' );
	require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-ssh2.php' );

	if ( class_exists( 'WP_Filesystem_SSH2' ) ) {

		// also accepts public_key and private_key filenames
		$options = array(
			'port' => 22,
			'hostname' => 'ftp.drivehq.com',
			'username' => 'khaled2005.marei',
			'password' => 'C6a@eyCiHaukBUV',
		);

		$ssh = new WP_Filesystem_SSH2( $options );

		// check for errors
		// if ssh2 is not an installed PHP extension:  sudo apt-get install libssh2-php
		if ( ! empty( $ssh->errors->errors ) ) {
			echo $ssh->errors->errors;
			return;
		}


		if ( ! $ssh->connect() ) {
			echo 'unable to connect';
		} else {
			// put the contents into a remote file
			if ( $ssh->put_contents( '/home/awp-sample/hello-world.txt', 'Hello world! ' . current_time( 'timestamp' ) , 0644 ) ) {
				echo 'hello-world.txt uploaded';
			} else {
				echo 'unable to upload file';
			}

		}

	}

}
