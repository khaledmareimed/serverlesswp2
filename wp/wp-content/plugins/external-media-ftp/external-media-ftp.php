<?php
/*
Plugin Name: FTP Media Upload with Settings, CDN, and Notifications
Description: Uploads media to an external FTP server with configuration in WordPress admin, supports CDN, and shows notifications for success or errors.
Version: 1.4
Author: Your Name
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Hook into the upload process
add_filter('wp_handle_upload', 'upload_media_to_ftp', 10, 2);

// Add settings page for FTP configuration
add_action('admin_menu', 'ftp_media_upload_settings_menu');
add_action('admin_init', 'ftp_media_upload_settings_init');

// Hook for admin notices (for errors or success messages)
add_action('admin_notices', 'ftp_media_upload_admin_notice');

/**
 * Create settings menu in WordPress dashboard.
 */
function ftp_media_upload_settings_menu() {
    add_options_page(
        'FTP Media Upload Settings',
        'FTP Media Upload',
        'manage_options',
        'ftp-media-upload-settings',
        'ftp_media_upload_settings_page'
    );
}

/**
 * Register and initialize the FTP settings.
 */
function ftp_media_upload_settings_init() {
    register_setting('ftp_media_upload_settings', 'ftp_media_upload_options');

    add_settings_section(
        'ftp_media_upload_section',
        'FTP & CDN Settings',
        null,
        'ftp-media-upload-settings'
    );

    add_settings_field(
        'ftp_server',
        'FTP Server',
        'ftp_server_field_callback',
        'ftp-media-upload-settings',
        'ftp_media_upload_section'
    );
    
    add_settings_field(
        'ftp_user',
        'FTP Username',
        'ftp_user_field_callback',
        'ftp-media-upload-settings',
        'ftp_media_upload_section'
    );
    
    add_settings_field(
        'ftp_pass',
        'FTP Password',
        'ftp_pass_field_callback',
        'ftp-media-upload-settings',
        'ftp_media_upload_section'
    );

    add_settings_field(
        'ftp_path',
        'FTP Path',
        'ftp_path_field_callback',
        'ftp-media-upload-settings',
        'ftp_media_upload_section'
    );

    add_settings_field(
        'cdn_url',
        'CDN Base URL',
        'cdn_url_field_callback',
        'ftp-media-upload-settings',
        'ftp_media_upload_section'
    );
}

// Callbacks to display fields
function ftp_server_field_callback() {
    $options = get_option('ftp_media_upload_options');
    echo '<input type="text" name="ftp_media_upload_options[ftp_server]" value="' . esc_attr($options['ftp_server'] ?? '') . '" />';
}

function ftp_user_field_callback() {
    $options = get_option('ftp_media_upload_options');
    echo '<input type="text" name="ftp_media_upload_options[ftp_user]" value="' . esc_attr($options['ftp_user'] ?? '') . '" />';
}

function ftp_pass_field_callback() {
    $options = get_option('ftp_media_upload_options');
    echo '<input type="password" name="ftp_media_upload_options[ftp_pass]" value="' . esc_attr($options['ftp_pass'] ?? '') . '" />';
}

function ftp_path_field_callback() {
    $options = get_option('ftp_media_upload_options');
    echo '<input type="text" name="ftp_media_upload_options[ftp_path]" value="' . esc_attr($options['ftp_path'] ?? '') . '" />';
}

function cdn_url_field_callback() {
    $options = get_option('ftp_media_upload_options');
    echo '<input type="text" name="ftp_media_upload_options[cdn_url]" value="' . esc_attr($options['cdn_url'] ?? '') . '" />';
}

/**
 * Display the settings page in WordPress dashboard.
 */
function ftp_media_upload_settings_page() {
    ?>
    <div class="wrap">
        <h1>FTP Media Upload Settings</h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('ftp_media_upload_settings');
            do_settings_sections('ftp-media-upload-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

/**
 * Upload media file to an FTP server.
 *
 * @param array $upload Array of upload data.
 * @param string $context Upload context.
 * @return array Modified upload data.
 */
function upload_media_to_ftp($upload) {
    $options = get_option('ftp_media_upload_options');
    $ftp_server = $options['ftp_server'] ?? '';
    $ftp_user = $options['ftp_user'] ?? '';
    $ftp_pass = $options['ftp_pass'] ?? '';
    $ftp_path = $options['ftp_path'] ?? '/';
    $cdn_url = $options['cdn_url'] ?? ''; // Get CDN base URL

    // Check if FTP extension is available
    if (!function_exists('ftp_connect')) {
        set_transient('ftp_upload_status', 'error_ftp_extension', 10);
        return $upload;
    }

    if (!$ftp_server || !$ftp_user || !$ftp_pass) {
        set_transient('ftp_upload_status', 'error_invalid_settings', 10);
        return $upload;
    }

    // Check if the file was uploaded without errors
    if ($upload['type'] && !empty($upload['file'])) {
        // File path
        $file = $upload['file'];
        $filename = basename($file);

        // Establish FTP connection
        $ftp_conn = ftp_connect($ftp_server);
        if (!$ftp_conn) {
            set_transient('ftp_upload_status', 'error_connection', 10);
            return $upload;
        }

        // Login to the FTP server
        $login = ftp_login($ftp_conn, $ftp_user, $ftp_pass);
        if (!$login) {
            ftp_close($ftp_conn);
            set_transient('ftp_upload_status', 'error_login', 10);
            return $upload;
        }

        // Enable passive mode
        ftp_pasv($ftp_conn, true);

        // Upload the file
        $upload_result = ftp_put($ftp_conn, $ftp_path . $filename, $file, FTP_BINARY);

        if (!$upload_result) {
            set_transient('ftp_upload_status', 'error_upload_failed', 10);
        } else {
            set_transient('ftp_upload_status', 'success', 10);

            // Use CDN URL if provided, otherwise use FTP URL
            if ($cdn_url) {
                $upload['url'] = rtrim($cdn_url, '/') . '/' . $filename;
            } else {
                $upload['url'] = $ftp_server . '/' . ltrim($ftp_path, '/') . '/' . $filename;
            }
        }

        // Close the connection
        ftp_close($ftp_conn);
    }

    return $upload;
}

/**
 * Show admin notices for FTP upload status.
 */
function ftp_media_upload_admin_notice() {
    if ($status = get_transient('ftp_upload_status')) {
        switch ($status) {
            case 'success':
                $class = 'notice-success';
                $message = 'File uploaded successfully to FTP server.';
                break;
            case 'error_ftp_extension':
                $class = 'notice-error';
                $message = 'Error: FTP extension is not enabled in your PHP configuration.';
                break;
            case 'error_invalid_settings':
                $class = 'notice-error';
                $message = 'Error: FTP settings are incomplete or incorrect.';
                break;
            case 'error_connection':
                $class = 'notice-error';
                $message = 'Error: Failed to connect to the FTP server.';
                break;
            case 'error_login':
                $class = 'notice-error';
                $message = 'Error: FTP login failed.';
                break;
            case 'error_upload_failed':
                $class = 'notice-error';
                $message = 'Error: FTP upload failed.';
                break;
            default:
                $class = 'notice-error';
                $message = 'An unknown error occurred during FTP upload.';
        }

        echo "<div class='$class'><p>$message</p></div>";

        // Delete the transient so it doesn't show repeatedly
        delete_transient('ftp_upload_status');
    }
}
