<?php

// Hook into the media upload process
add_filter('wp_handle_upload', 'emf_handle_upload', 10, 2);

function emf_handle_upload($fileinfo, $context) {
    // Only handle uploads from the media library
    if (isset($_POST['action']) && $_POST['action'] === 'upload-attachment') {
        // Get FTP credentials from settings
        $ftp_host = get_option('emf_ftp_host');
        $ftp_username = get_option('emf_ftp_username');
        $ftp_password = get_option('emf_ftp_password');
        $ftp_port = get_option('emf_ftp_port') ?: 21;
        $base_url = rtrim(get_option('emf_ftp_base_url'), '/') . '/';
        $ftp_directory = rtrim(get_option('emf_ftp_directory'), '/') . '/'; // Get custom directory

        // Establish FTP connection
        $ftp_conn = ftp_connect($ftp_host, $ftp_port, 30);
        if (!$ftp_conn) {
            wp_die('External Media FTP: Could not connect to FTP server.');
            return $fileinfo;
        }

        // Login to FTP
        $login = ftp_login($ftp_conn, $ftp_username, $ftp_password);
        if (!$login) {
            ftp_close($ftp_conn);
            wp_die('External Media FTP: Could not login to FTP server.');
            return $fileinfo;
        }

        // Enable passive mode
        ftp_pasv($ftp_conn, true);

        // Check if the FTP connection is still alive before proceeding
        if (!emf_is_ftp_alive($ftp_conn)) {
            wp_die('External Media FTP: FTP connection lost after login.');
            ftp_close($ftp_conn);
            return $fileinfo;
        }

        // Define the remote path (use custom directory if set)
        $remote_path = $ftp_directory . 'wp-content/uploads/' . date('Y/m');

        // Create remote directories if they don't exist
        emf_ftp_mkdir_recursive($ftp_conn, $remote_path);

        // Check connection again before uploading
        if (!emf_is_ftp_alive($ftp_conn)) {
            wp_die('External Media FTP: FTP connection lost before file upload.');
            ftp_close($ftp_conn);
            return $fileinfo;
        }

        // Define remote file path
        $remote_file = $remote_path . '/' . basename($fileinfo['file']);

        // Upload the file
        $upload = ftp_put($ftp_conn, $remote_file, $fileinfo['file'], FTP_BINARY);
        if (!$upload) {
            wp_die('External Media FTP: Failed to upload file to FTP server.');
            ftp_close($ftp_conn);
            return $fileinfo;
        }

        // Close FTP connection
        ftp_close($ftp_conn);

        // Set the URL to the external server
        $fileinfo['url'] = $base_url . $remote_file;

        // Optionally, delete the local file to save space
        // unlink($fileinfo['file']);
    }

    return $fileinfo;
}

// Function to check if the FTP connection is still alive
function emf_is_ftp_alive($ftp_conn) {
    // Use an FTP command to check if the connection is still alive
    return @ftp_raw($ftp_conn, 'NOOP') !== false;
}

// Function to create directories recursively on FTP server
function emf_ftp_mkdir_recursive($ftp_conn, $remote_dir) {
    $dirs = explode('/', $remote_dir);
    $path = '';
    foreach ($dirs as $dir) {
        $path .= '/' . $dir;
        if (@ftp_chdir($ftp_conn, $path)) {
            ftp_chdir($ftp_conn, '/'); // Change back to root after checking
            continue;
        }
        if (!@ftp_mkdir($ftp_conn, $path)) {
            wp_die("External Media FTP: Failed to create directory {$path}");
            return false;
        }
    }
    return true;
}
