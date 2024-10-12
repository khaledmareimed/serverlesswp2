<?php
/**
 * Plugin Name: Imgbb Image Upload
 * Description: Automatically upload images to Imgbb and store them in the WordPress media library.
 * Version: 1.1
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add settings link on plugin page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'imgbb_add_settings_link');

function imgbb_add_settings_link($links) {
    $settings_link = '<a href="options-general.php?page=imgbb-settings">Settings</a>';
    array_push($links, $settings_link);
    return $links;
}

// Create settings menu
add_action('admin_menu', 'imgbb_create_menu');

function imgbb_create_menu() {
    add_options_page(
        'Imgbb Image Upload Settings',
        'Imgbb Image Upload',
        'manage_options',
        'imgbb-settings',
        'imgbb_settings_page'
    );
}

// Register settings
add_action('admin_init', 'imgbb_register_settings');

function imgbb_register_settings() {
    register_setting('imgbb-settings-group', 'imgbb_api_key');
}

// Settings page HTML
function imgbb_settings_page() {
    ?>
    <div class="wrap">
        <h1>Imgbb Image Upload Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('imgbb-settings-group'); ?>
            <?php do_settings_sections('imgbb-settings-group'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Imgbb API Key</th>
                    <td><input type="text" name="imgbb_api_key" value="<?php echo esc_attr(get_option('imgbb_api_key')); ?>" size="50" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Hook into the media upload process
add_filter('wp_handle_upload', 'imgbb_upload_image_to_imgbb');

function imgbb_upload_image_to_imgbb($upload) {
    // Get the API key from settings
    $api_key = get_option('imgbb_api_key');
    if (empty($api_key)) {
        return $upload; // If API key is not set, do nothing
    }

    // Get the file path and read the image data
    $file = $upload['file'];
    $image_data = base64_encode(file_get_contents($file));

    // Make the API request to Imgbb
    $response = wp_remote_post('https://api.imgbb.com/1/upload', [
        'body' => [
            'key' => $api_key,
            'image' => $image_data,
        ],
    ]);

    // Check if the request was successful
    if (is_wp_error($response)) {
        return $upload; // If failed, return the original upload
    }

    $response_body = wp_remote_retrieve_body($response);
    $json_response = json_decode($response_body, true);

    // Check if the upload to Imgbb was successful
    if (isset($json_response['data']['url'])) {
        // Retrieve the Imgbb URL
        $imgbb_url = esc_url($json_response['data']['url']);

        // Replace the local file URL with the Imgbb URL
        $upload['url'] = $imgbb_url;

        // Update the file URL in the database
        imgbb_update_image_url_in_database($upload['file'], $imgbb_url);

        // Optional: Delete the local file to save space
        if (file_exists($file)) {
            unlink($file);
        }
    }

    return $upload;
}

/**
 * Update the URL of the uploaded image in the WordPress database
 *
 * @param string $local_file_path
 * @param string $imgbb_url
 */
function imgbb_update_image_url_in_database($local_file_path, $imgbb_url) {
    global $wpdb;

    // Get the attachment ID by file path
    $attachment_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT ID FROM $wpdb->posts WHERE guid LIKE %s AND post_type = 'attachment'",
            '%' . $wpdb->esc_like(basename($local_file_path)) . '%'
        )
    );

    if ($attachment_id) {
        // Update the post's guid (URL) in the wp_posts table
        $wpdb->update(
            $wpdb->posts,
            ['guid' => $imgbb_url],
            ['ID' => $attachment_id],
            ['%s'],
            ['%d']
        );

        // Optional: Update meta fields like '_wp_attached_file' if needed
        update_post_meta($attachment_id, '_wp_attached_file', $imgbb_url);
    }
}
