<?php
/**
 * Plugin Name: HostImages Integration
 * Plugin URI: https://yourwebsite.com/hostimages-integration
 * Description: Integrates WordPress with HostImages.org for external image hosting.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL2
 * Text Domain: hostimages-integration
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HostImages_Integration {

    private $options;

    public function __construct() {
        // Initialize plugin
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'settings_init' ) );
        add_filter( 'wp_handle_upload', array( $this, 'handle_upload' ), 10, 2 );
        add_filter( 'wp_insert_attachment_data', array( $this, 'update_attachment_data' ), 10, 2 );
        add_filter( 'sanitize_file_name', array( $this, 'customize_file_name' ), 10, 2 );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
    }

    /**
     * Add settings page under Settings menu
     */
    public function add_admin_menu() {
        add_options_page(
            'HostImages Integration',
            'HostImages',
            'manage_options',
            'hostimages_integration',
            array( $this, 'options_page' )
        );
    }

    /**
     * Register settings, sections, and fields
     */
    public function settings_init() {
        register_setting( 'hostimages_settings_group', 'hostimages_settings', array( $this, 'sanitize_settings' ) );

        add_settings_section(
            'hostimages_settings_section',
            __( 'API Configuration', 'hostimages-integration' ),
            array( $this, 'settings_section_callback' ),
            'hostimages_settings_group'
        );

        add_settings_field(
            'hostimages_api_key',
            __( 'HostImages API Key', 'hostimages-integration' ),
            array( $this, 'api_key_render' ),
            'hostimages_settings_group',
            'hostimages_settings_section'
        );
    }

    /**
     * Sanitize settings input
     */
    public function sanitize_settings( $input ) {
        $sanitized = array();
        if ( isset( $input['hostimages_api_key'] ) ) {
            $sanitized['hostimages_api_key'] = sanitize_text_field( $input['hostimages_api_key'] );
        }
        return $sanitized;
    }

    /**
     * Render API Key field
     */
    public function api_key_render() {
        $options = get_option( 'hostimages_settings' );
        ?>
        <input type="text" name="hostimages_settings[hostimages_api_key]" value="<?php echo isset( $options['hostimages_api_key'] ) ? esc_attr( $options['hostimages_api_key'] ) : ''; ?>" size="50" />
        <?php
    }

    /**
     * Settings section callback
     */
    public function settings_section_callback() {
        echo __( 'Enter your HostImages.org API key to enable image hosting integration.', 'hostimages-integration' );
    }

    /**
     * Render options page
     */
    public function options_page() {
        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Check if settings have been updated
        if ( isset( $_GET['settings-updated'] ) ) {
            add_settings_error( 'hostimages_messages', 'hostimages_message', __( 'Settings Saved', 'hostimages-integration' ), 'updated' );
        }

        // Show error/update messages
        settings_errors( 'hostimages_messages' );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'hostimages_settings_group' );
                do_settings_sections( 'hostimages_settings_group' );
                submit_button( 'Save Settings' );
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Handle image uploads by sending them to HostImages.org
     */
    public function handle_upload( $upload, $context ) {
        // Only proceed for media uploads
        if ( 'upload' !== $context['action'] ) {
            return $upload;
        }

        // Get file information
        if ( isset( $upload['file'] ) ) {
            $file_path = $upload['file'];
            $file_type = wp_check_filetype( $file_path );
            $allowed_types = array( 'jpg', 'jpeg', 'png', 'gif', 'bmp' );

            if ( in_array( strtolower( $file_type['ext'] ), $allowed_types ) ) {
                // Get API key
                $options = get_option( 'hostimages_settings' );
                $api_key = isset( $options['hostimages_api_key'] ) ? $options['hostimages_api_key'] : '';

                if ( empty( $api_key ) ) {
                    // API key not set; skip processing
                    return $upload;
                }

                // Read the file contents
                $image_data = file_get_contents( $file_path );

                // Prepare the API request
                $response = wp_remote_post( 'https://hostimages.org/api/upload', array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type'  => 'application/octet-stream',
                    ),
                    'body'    => $image_data,
                    'timeout' => 60,
                ) );

                // Check for errors
                if ( is_wp_error( $response ) ) {
                    // Log the error and return original upload
                    error_log( 'HostImages Upload Error: ' . $response->get_error_message() );
                    return $upload;
                }

                // Parse the response
                $body = wp_remote_retrieve_body( $response );
                $result = json_decode( $body, true );

                if ( isset( $result['url'] ) ) {
                    // Replace the local URL with the hosted URL
                    $upload['url'] = esc_url_raw( $result['url'] );

                    // Optionally, delete the local file to save space
                    if ( file_exists( $file_path ) ) {
                        unlink( $file_path );
                    }

                    // Modify the file path to indicate it's hosted
                    $upload['file'] = 'hosted/' . basename( $file_path );

                    // Store the hosted URL in the upload array for later use
                    $upload['hostimages_url'] = $result['url'];
                } else {
                    // Handle unexpected response
                    error_log( 'HostImages Upload Error: Invalid response.' );
                }
            }
        }

        return $upload;
    }

    /**
     * Update attachment data with hosted URL
     */
    public function update_attachment_data( $data, $postarr ) {
        if ( isset( $postarr['hostimages_url'] ) ) {
            $data['guid'] = esc_url_raw( $postarr['hostimages_url'] );
            $data['post_mime_type'] = wp_check_filetype( $postarr['hostimages_url'] )['type'];
            $data['post_title'] = sanitize_file_name( basename( $postarr['hostimages_url'] ) );
            $data['post_content'] = '';
            $data['post_excerpt'] = '';
        }

        return $data;
    }

    /**
     * Customize file name based on HostImages.org response
     */
    public function customize_file_name( $filename, $raw_filename ) {
        // Get attachment ID from the current post
        $attachment_id = isset( $_REQUEST['post_id'] ) ? intval( $_REQUEST['post_id'] ) : 0;

        if ( $attachment_id ) {
            $hosted_url = get_post_meta( $attachment_id, '_hostimages_url', true );

            if ( $hosted_url ) {
                $parsed_url = parse_url( $hosted_url );
                $hosted_filename = basename( $parsed_url['path'] );

                return sanitize_file_name( $hosted_filename );
            }
        }

        // Default behavior if no hosted URL is found
        return $filename;
    }

    /**
     * Display admin notices for errors or updates
     */
    public function admin_notices() {
        // Example: Display a notice if the API key is missing
        $screen = get_current_screen();
        if ( 'settings_page_hostimages_integration' !== $screen->id ) {
            return;
        }

        $options = get_option( 'hostimages_settings' );
        if ( empty( $options['hostimages_api_key'] ) ) {
            echo '<div class="notice notice-warning is-dismissible">
                <p>' . __( 'HostImages Integration: Please enter your API key to enable image hosting.', 'hostimages-integration' ) . '</p>
            </div>';
        }
    }

}

// Initialize the plugin
new HostImages_Integration();

/**
 * Register activation and deactivation hooks if needed
 */
// register_activation_hook( __FILE__, 'hostimages_activate' );
// register_deactivation_hook( __FILE__, 'hostimages_deactivate' );

/**
 * Optional: Define activation and deactivation functions
 */
/*
function hostimages_activate() {
    // Actions to perform on activation
}

function hostimages_deactivate() {
    // Actions to perform on deactivation
}
*/
