<?php
/*
Plugin Name: Custom CDN Media Host
Description: Uploads media to a custom CDN and displays them in the WordPress Media Library.
Version: 1.0
Author: Your Name
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class CustomCDNMediaHost {
    private $cdn_api_endpoint = 'https://cdn.example.com/api/upload'; // Replace with your CDN's upload endpoint

    public function __construct() {
        add_filter('wp_handle_upload', array($this, 'upload_to_cdn'), 10, 2);
        add_filter('wp_generate_attachment_metadata', array($this, 'replace_attachment_url'), 10, 2);
        add_filter('wp_get_attachment_url', array($this, 'get_attachment_url'), 10, 2);
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_settings_page'));
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('custom_cdn_settings_group', 'custom_cdn_api_key');
        register_setting('custom_cdn_settings_group', 'custom_cdn_base_url');

        add_settings_section(
            'custom_cdn_settings_section',
            'Custom CDN Settings',
            null,
            'custom-cdn-settings'
        );

        add_settings_field(
            'custom_cdn_api_key',
            'API Key',
            array($this, 'api_key_callback'),
            'custom-cdn-settings',
            'custom_cdn_settings_section'
        );

        add_settings_field(
            'custom_cdn_base_url',
            'CDN Base URL',
            array($this, 'base_url_callback'),
            'custom-cdn-settings',
            'custom_cdn_settings_section'
        );
    }

    /**
     * API Key Field Callback
     */
    public function api_key_callback() {
        $api_key = esc_attr(get_option('custom_cdn_api_key'));
        echo '<input type="text" name="custom_cdn_api_key" value="' . $api_key . '" size="50" />';
    }

    /**
     * CDN Base URL Field Callback
     */
    public function base_url_callback() {
        $base_url = esc_attr(get_option('custom_cdn_base_url'));
        echo '<input type="text" name="custom_cdn_base_url" value="' . $base_url . '" size="50" />';
    }

    /**
     * Add settings page
     */
    public function add_settings_page() {
        add_options_page(
            'Custom CDN Media Host Settings',
            'Custom CDN Media Host',
            'manage_options',
            'custom-cdn-media-host',
            array($this, 'create_settings_page')
        );
    }

    /**
     * Create settings page content
     */
    public function create_settings_page() {
        ?>
        <div class="wrap">
            <h1>Custom CDN Media Host Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('custom_cdn_settings_group');
                do_settings_sections('custom-cdn-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Upload image to CDN
     */
    public function upload_to_cdn($upload, $context) {
        // Only proceed for image uploads
        if (strpos($upload['type'], 'image') === false) {
            return $upload;
        }

        $api_key = get_option('custom_cdn_api_key');
        $base_url = rtrim(get_option('custom_cdn_base_url'), '/');

        if (!$api_key || !$base_url) {
            // API key or base URL not set; skip uploading
            return $upload;
        }

        $file_path = $upload['file'];
        $file_name = basename($file_path);
        $file_type = wp_check_filetype($file_path)['ext'];

        // Read the file content
        $file_data = file_get_contents($file_path);

        // Prepare the request
        $response = wp_remote_post($this->cdn_api_endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body' => array(
                'file' => base64_encode($file_data),
                'filename' => $file_name,
            ),
            'timeout' => 60,
        ));

        if (is_wp_error($response)) {
            // Handle error (you might want to log this)
            return $upload;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['status']) && $data['status'] == 'success') {
            // Replace the file URL with the external URL from CDN
            $external_url = $data['url']; // Adjust based on your CDN's response
            $upload['url'] = $external_url;
            $upload['file'] = $external_url;
            // Optionally, delete the local file to save space
            // unlink($file_path);
        }

        return $upload;
    }

    /**
     * Replace attachment metadata with external URL
     */
    public function replace_attachment_url($metadata, $attachment_id) {
        $attachment = get_post($attachment_id);
        $api_key = get_option('custom_cdn_api_key');
        $base_url = rtrim(get_option('custom_cdn_base_url'), '/');

        if (!$api_key || !$base_url) {
            return $metadata;
        }

        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) {
            return $metadata;
        }

        $file_name = basename($file);
        $file_data = file_get_contents($file);

        // Upload to CDN
        $response = wp_remote_post($this->cdn_api_endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body' => array(
                'file' => base64_encode($file_data),
                'filename' => $file_name,
            ),
            'timeout' => 60,
        ));

        if (is_wp_error($response)) {
            return $metadata;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['status']) && $data['status'] == 'success') {
            $external_url = $data['url']; // Adjust based on your CDN's response
            // Update the attachment metadata with the external URL
            update_post_meta($attachment_id, '_external_image_url', $external_url);
            // Optionally, delete the local file
            // unlink($file);
        }

        return $metadata;
    }

    /**
     * Filter the attachment URL to use external CDN URL if available
     */
    public function get_attachment_url($url, $post_id) {
        $external_url = get_post_meta($post_id, '_external_image_url', true);
        if ($external_url) {
            return $external_url;
        }
        return $url;
    }
}

new CustomCDNMediaHost();
