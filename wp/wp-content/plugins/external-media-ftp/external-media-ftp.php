<?php
/*
Plugin Name: Postimages Media Host
Description: Uploads media to Postimages.org and displays them in the WordPress Media Library.
Version: 1.0
Author: Your Name
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class PostimagesMediaHost {
    private $api_endpoint = 'https://api.postimages.org/1/upload';

    public function __construct() {
        add_filter('wp_handle_upload', array($this, 'upload_to_postimages'), 10, 2);
        add_filter('wp_generate_attachment_metadata', array($this, 'replace_attachment_url'), 10, 2);
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_settings_page'));
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('postimages_settings_group', 'postimages_api_key');

        add_settings_section(
            'postimages_settings_section',
            'Postimages API Settings',
            null,
            'postimages-settings'
        );

        add_settings_field(
            'postimages_api_key',
            'API Key',
            array($this, 'api_key_callback'),
            'postimages-settings',
            'postimages_settings_section'
        );
    }

    /**
     * API Key Field Callback
     */
    public function api_key_callback() {
        $api_key = esc_attr(get_option('postimages_api_key'));
        echo '<input type="text" name="postimages_api_key" value="' . $api_key . '" size="50" />';
    }

    /**
     * Add settings page
     */
    public function add_settings_page() {
        add_options_page(
            'Postimages Media Host Settings',
            'Postimages Media Host',
            'manage_options',
            'postimages-media-host',
            array($this, 'create_settings_page')
        );
    }

    /**
     * Create settings page content
     */
    public function create_settings_page() {
        ?>
        <div class="wrap">
            <h1>Postimages Media Host Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('postimages_settings_group');
                do_settings_sections('postimages-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Upload image to Postimages.org
     */
    public function upload_to_postimages($upload) {
        // Only proceed for image uploads
        if (strpos($upload['type'], 'image') === false) {
            return $upload;
        }

        $api_key = get_option('postimages_api_key');
        if (!$api_key) {
            // API key not set; skip uploading
            return $upload;
        }

        $file_path = $upload['file'];
        $file_name = basename($file_path);
        $file_type = wp_check_filetype($file_path)['ext'];

        $file_data = file_get_contents($file_path);

        $response = wp_remote_post($this->api_endpoint, array(
            'body' => array(
                'key' => $api_key,
                'image' => base64_encode($file_data),
                'format' => 'json',
            ),
        ));

        if (is_wp_error($response)) {
            // Handle error (you might want to log this)
            return $upload;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['status']) && $data['status'] == 'success') {
            // Replace the file URL with the external URL
            $upload['url'] = $data['image']['url'];
            $upload['file'] = $data['image']['url'];
            // Optionally, delete the local file
            // unlink($file_path);
        }

        return $upload;
    }

    /**
     * Replace attachment metadata with external URL
     */
    public function replace_attachment_url($metadata, $attachment_id) {
        $attachment = get_post($attachment_id);
        $api_key = get_option('postimages_api_key');

        if (!$api_key) {
            return $metadata;
        }

        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) {
            return $metadata;
        }

        // Upload to Postimages.org
        $file_data = file_get_contents($file);
        $response = wp_remote_post($this->api_endpoint, array(
            'body' => array(
                'key' => $api_key,
                'image' => base64_encode($file_data),
                'format' => 'json',
            ),
        ));

        if (is_wp_error($response)) {
            return $metadata;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['status']) && $data['status'] == 'success') {
            $external_url = $data['image']['url'];
            // Update the attachment metadata with the external URL
            update_post_meta($attachment_id, '_external_image_url', $external_url);
            // Optionally, delete the local file
            // unlink($file);
        }

        return $metadata;
    }
}

new PostimagesMediaHost();
