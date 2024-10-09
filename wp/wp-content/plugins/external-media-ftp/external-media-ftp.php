<?php
/*
Plugin Name: PostImages Integration
Plugin URI: https://yourwebsite.com/postimages-integration
Description: Integrates WordPress media uploads with postImages.org for external image hosting.
Version: 1.1
Author: Your Name
Author URI: https://yourwebsite.com
License: GPL2
Text Domain: postimages-integration
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class PostImages_Integration {

    private $option_name = 'pi_api_key';

    public function __construct() {
        // Initialize plugin
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Handle media uploads
        add_filter( 'wp_handle_upload_prefilter', array( $this, 'filter_upload_prefilter' ) );
        add_filter( 'wp_handle_upload', array( $this, 'handle_upload' ), 10, 2 );

        // Customize attachment URL
        add_filter( 'wp_get_attachment_url', array( $this, 'filter_attachment_url' ), 10, 2 );

        // Add custom field to media library
        add_filter( 'attachment_fields_to_save', array( $this, 'save_attachment_fields' ), 10, 2 );
    }

    /**
     * Add settings page under Settings menu
     */
    public function add_settings_page() {
        add_options_page(
            'PostImages Integration Settings',
            'PostImages Integration',
            'manage_options',
            'postimages-integration',
            array( $this, 'create_settings_page' )
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting( 'pi_settings_group', $this->option_name, array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );

        add_settings_section(
            'pi_settings_section',
            'API Settings',
            array( $this, 'settings_section_callback' ),
            'postimages-integration'
        );

        add_settings_field(
            'pi_api_key_field',
            'PostImages API Key',
            array( $this, 'api_key_field_callback' ),
            'postimages-integration',
            'pi_settings_section'
        );
    }

    /**
     * Settings section description
     */
    public function settings_section_callback() {
        echo '<p>Enter your PostImages.org API key below.</p>';
    }

    /**
     * API Key field HTML
     */
    public function api_key_field_callback() {
        $api_key = get_option( $this->option_name );
        echo '<input type="text" id="pi_api_key_field" name="' . esc_attr( $this->option_name ) . '" value="' . esc_attr( $api_key ) . '" size="50" />';
    }

    /**
     * Create the settings page HTML
     */
    public function create_settings_page() {
        ?>
        <div class="wrap">
            <h1>PostImages Integration Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'pi_settings_group' );
                do_settings_sections( 'postimages-integration' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Pre-upload filter to ensure API key is set
     */
    public function filter_upload_prefilter( $file ) {
        // Check if API key is set
        $api_key = get_option( $this->option_name );
        if ( empty( $api_key ) ) {
            $file['error'] = 'PostImages Integration: API key is not set. Please set it in the plugin settings.';
        }

        return $file;
    }

    /**
     * Handle the upload by sending it to PostImages.org and modifying the upload array
     */
    public function handle_upload( $upload, $context ) {
        // Only handle if no errors
        if ( isset( $upload['error'] ) && ! empty( $upload['error'] ) ) {
            return $upload;
        }

        // Check if it's an image
        if ( strpos( $upload['type'], 'image' ) === false ) {
            return $upload;
        }

        $api_key = get_option( $this->option_name );
        if ( empty( $api_key ) ) {
            // This should have been caught in prefilter, but just in case
            $upload['error'] = 'PostImages Integration: API key is not set.';
            return $upload;
        }

        // Prepare the file for upload
        $file_path = $upload['file'];
        $file_name = basename( $file_path );

        // Read the file
        $file_data = file_get_contents( $file_path );
        if ( false === $file_data ) {
            $upload['error'] = 'PostImages Integration: Failed to read the uploaded file.';
            error_log( 'PostImages Integration: Failed to read the uploaded file at ' . $file_path );
            return $upload;
        }

        // Prepare the API request
        $response = wp_remote_post( 'https://api.postimages.org/1/upload', array(
            'body' => array(
                'key'    => $api_key,
                'image'  => base64_encode( $file_data ),
                'format' => 'json',
            ),
            'timeout' => 60,
        ) );

        if ( is_wp_error( $response ) ) {
            // Log the error for debugging
            error_log( 'PostImages Integration: API request failed. ' . $response->get_error_message() );
            $upload['error'] = 'PostImages Integration: API request failed. ' . $response->get_error_message();
            return $upload;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $response_code ) {
            error_log( 'PostImages Integration: API returned unexpected response code: ' . $response_code );
            $upload['error'] = 'PostImages Integration: API returned an unexpected response code: ' . $response_code;
            return $upload;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            error_log( 'PostImages Integration: Failed to parse API response. JSON Error: ' . json_last_error_msg() );
            $upload['error'] = 'PostImages Integration: Failed to parse API response.';
            return $upload;
        }

        if ( isset( $data['status'] ) && $data['status'] !== 'success' ) {
            $message = isset( $data['error'] ) ? $data['error'] : 'Unknown error.';
            error_log( 'PostImages Integration: API error - ' . $message );
            $upload['error'] = 'PostImages Integration: API error - ' . $message;
            return $upload;
        }

        if ( ! isset( $data['image']['url'] ) ) {
            error_log( 'PostImages Integration: API response does not contain image URL.' );
            $upload['error'] = 'PostImages Integration: API response does not contain image URL.';
            return $upload;
        }

        // Get the hosted image URL
        $hosted_url = esc_url_raw( $data['image']['url'] );

        // Update the upload array to use the hosted URL
        $upload['url']  = $hosted_url;
        $upload['file'] = $hosted_url;
        $upload['type'] = $upload['type']; // Keep the MIME type

        // Optionally, customize the file name based on API response
        if ( isset( $data['image']['shorturl'] ) ) {
            $unique_id        = sanitize_title( $data['image']['shorturl'] );
            $upload['name']   = $unique_id . '.' . pathinfo( $file_name, PATHINFO_EXTENSION );
        }

        // Save the hosted URL as post meta for future reference
        add_post_meta( $upload['id'], '_pi_hosted_url', $hosted_url, true );

        return $upload;
    }

    /**
     * Filter the attachment URL to point to the hosted image
     */
    public function filter_attachment_url( $url, $post_id ) {
        $hosted_url = get_post_meta( $post_id, '_pi_hosted_url', true );
        if ( ! empty( $hosted_url ) ) {
            return esc_url( $hosted_url );
        }
        return $url;
    }

    /**
     * Save custom attachment fields
     */
    public function save_attachment_fields( $post, $attachment ) {
        if ( isset( $attachment['pi_hosted_url'] ) ) {
            update_post_meta( $post['ID'], '_pi_hosted_url', esc_url_raw( $attachment['pi_hosted_url'] ) );
        }
        return $post;
    }

}

new PostImages_Integration();
