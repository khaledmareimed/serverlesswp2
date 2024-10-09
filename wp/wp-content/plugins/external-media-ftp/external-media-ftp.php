<?php
/*
Plugin Name: FTP Media Upload with Settings (htdocs)
Description: Uploads media to an external FTP server inside an 'htdocs' folder, with configuration in WordPress admin.
Version: 1.2
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
        'FTP Settings',
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
 * Upload media file to an FTP server inside the 'htdocs' folder.
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

    if (!$ftp_server || !$ftp_user || !$ftp_pass) {
        error_log('FTP settings are not properly configured.');
        return $upload;
    }

    // Ensure the file is uploaded to the 'htdocs' directory
    $ftp_path = 'htdocs/' . ltrim($ftp_path, '/');

    // Check if the file was uploaded without errors
    if ($upload['type'] && !empty($upload['file'])) {
        // File path
        $file = $upload['file'];
        $filename = basename($file);

        // Establish FTP connection
        $ftp_conn = ftp_connect($ftp_server);
        if (!$ftp_conn) {
            error_log('FTP connection failed.');
            return $upload;
        }

        // Login to the FTP server
        $login = ftp_login($ftp_conn, $ftp_user, $ftp_pass);
        if (!$login) {
            error_log('FTP login failed.');
            ftp_close($ftp_conn);
            return $upload;
        }

        // Enable passive mode
        ftp_pasv($ftp_conn, true);

        // Upload the file to 'htdocs' directory
        $upload_result = ftp_put($ftp_conn, $ftp_path . $filename, $file, FTP_BINARY);

        if (!$upload_result) {
            error_log('FTP upload failed.');
        } else {
            error_log('FTP upload successful.');
        }

        // Close the connection
        ftp_close($ftp_conn);
    }

    return $upload;
}
