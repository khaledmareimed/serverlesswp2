<?php
/*
Plugin Name: CDN Media Host
Description: Automatically uploads media to a custom CDN and displays them in the WordPress Media Library.
Version: 1.0
Author: Your Name
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class CDN_Media_Host {
    private $cdn_api_endpoint = 'https://cdn.example.com/api/upload'; // Replace with your CDN's actual upload endpoint

    public function __construct() {
        // Hooks to handle upload and URL replacement
        add_filter('wp_handle_upload', array($this, 'upload_to_cdn'), 10, 2);
        add_filter('wp_generate_attachment_metadata', array($this, 'replace_attachment_url'), 10, 2);
        add_filter('wp_get_attachment_url', array($this, 'get_attachment_url'), 10, 2);

        // Admin settings
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_menu', array($this, 'add_settings_page'));
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('cdn_media_host_settings_group', 'cdn_media_host_api_key');

        add_settings_section(
            'cdn_media_host_settings_section',
            'CDN Media Host Settings',
            null,
            'cdn-media-host-settings'
        );

        add_settings_field(
            'cdn_media_host_api_key',
            'API Key',
            array($this, 'api_key_callback'),
            'cdn-media-host-settings',
            'cdn_media_host_settings_section'
        );
    }

    /**
     * API Key Field Callback
     */
    public function api_key_callback() {
        $api_key = esc_attr(get_option('cdn_media_host_api_key'));
        echo '<input type="text" name="cdn_media_host_api_key" value="' . $api_key . '" size="50" />';
    }

    /**
     * Add settings page
     */
    public function add_settings_page() {
        add_options_page(
            'CDN Media Host Settings',
            'CDN Media Host',
            'manage_options',
            'cdn-media-host-settings',
            array($this, 'create_settings_page')
        );
    }

    /**
     * Create settings page content
     */
    public function create_settings_page() {
        ?>
        <div class="wrap">
            <h1>CDN Media Host Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('cdn_media_host_settings_group');
                do_settings_sections('cdn-media-host-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Upload image to CDN
     *
     * @param array $upload An array of upload data.
     * @param string $context The context of the upload.
     * @return array Modified upload data.
     */
    public function upload_to_cdn($upload, $context) {
        // Only proceed for image uploads
        if (strpos($upload['type'], 'image') === false) {
            return $upload;
        }

        $api_key = get_option('cdn_media_host_api_key');
        if (!$api_key) {
            // API key not set; skip uploading
            return $upload;
        }

        $file_path = $upload['file'];
        $file_name = basename($file_path);
        $file_type = wp_check_filetype($file_path)['ext'];

        // Read the file content
        $file_data = file_get_contents($file_path);
        if ($file_data === false) {
            // Failed to read file; skip uploading
            return $upload;
        }

        // Prepare the request
        $response = wp_remote_post($this->cdn_api_endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => json_encode(array(
                'file'     => base64_encode($file_data),
                'filename' => $file_name,
            )),
            'timeout' => 60,
        ));

        if (is_wp_error($response)) {
            // Handle error (you might want to log this)
            error_log('CDN Media Host Upload Error: ' . $response->get_error_message());
            return $upload;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['status']) && $data['status'] === 'success' && isset($data['url'])) {
            // Replace the file URL with the external URL from CDN
            $external_url = esc_url_raw($data['url']);
            $upload['url'] = $external_url;
            $upload['file'] = $external_url;

            // Optionally, delete the local file to save space
            // Uncomment the line below to enable deletion
            // unlink($file_path);
        } else {
            // Handle unsuccessful response
            error_log('CDN Media Host Upload Failed: ' . $body);
        }

        return $upload;
    }

    /**
     * Replace attachment metadata with external URL
     *
     * @param array $metadata Attachment metadata.
     * @param int $attachment_id Attachment ID.
     * @return array Modified metadata.
     */
    public function replace_attachment_url($metadata, $attachment_id) {
        $attachment = get_post($attachment_id);
        $api_key = get_option('cdn_media_host_api_key');

        if (!$api_key) {
            return $metadata;
        }

        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) {
            return $metadata;
        }

        $file_name = basename($file);
        $file_data = file_get_contents($file);
        if ($file_data === false) {
            return $metadata;
        }

        // Upload to CDN
        $response = wp_remote_post($this->cdn_api_endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => json_encode(array(
                'file'     => base64_encode($file_data),
                'filename' => $file_name,
            )),
            'timeout' => 60,
        ));

        if (is_wp_error($response)) {
            // Handle error
            error_log('CDN Media Host Replacement Upload Error: ' . $response->get_error_message());
            return $metadata;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['status']) && $data['status'] === 'success' && isset($data['url'])) {
            $external_url = esc_url_raw($data['url']);
            // Update the attachment metadata with the external URL
            update_post_meta($attachment_id, '_external_image_url', $external_url);

            // Optionally, delete the local file to save space
            // Uncomment the line below to enable deletion
            // unlink($file);
        } else {
            // Handle unsuccessful response
            error_log('CDN Media Host Replacement Upload Failed: ' . $body);
        }

        return $metadata;
    }

    /**
     * Filter the attachment URL to use external CDN URL if available
     *
     * @param string $url The URL to the attachment.
     * @param int $post_id The attachment ID.
     * @return string Modified URL.
     */
    public function get_attachment_url($url, $post_id) {
        $external_url = get_post_meta($post_id, '_external_image_url', true);
        if ($external_url) {
            return esc_url($external_url);
        }
        return $url;
    }
}

new CDN_Media_Host();
