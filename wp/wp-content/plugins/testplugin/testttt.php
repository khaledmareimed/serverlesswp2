<?php
/*
Plugin Name: ImgBB Uploader
Description: Upload images to ImgBB and display them in the WordPress media library.
Version: 1.1
Author: Your Name
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Create a settings menu
add_action('admin_menu', 'imgbb_uploader_menu');

function imgbb_uploader_menu() {
    add_options_page('ImgBB Uploader Settings', 'ImgBB Uploader', 'manage_options', 'imgbb-uploader', 'imgbb_uploader_settings_page');
}

// Settings page
function imgbb_uploader_settings_page() {
    ?>
    <div class="wrap">
        <h1>ImgBB Uploader Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('imgbb-uploader-settings-group');
            do_settings_sections('imgbb-uploader-settings-group');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">ImgBB API Key</th>
                    <td><input type="text" name="imgbb_api_key" value="<?php echo esc_attr(get_option('imgbb_api_key')); ?>" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Register settings
add_action('admin_init', 'imgbb_uploader_settings');

function imgbb_uploader_settings() {
    register_setting('imgbb-uploader-settings-group', 'imgbb_api_key');
}

// Enqueue JavaScript for handling file uploads
add_action('admin_enqueue_scripts', 'imgbb_uploader_enqueue_scripts');

function imgbb_uploader_enqueue_scripts($hook) {
    wp_enqueue_media();
    wp_enqueue_script('imgbb-uploader-script', plugin_dir_url(__FILE__) . 'imgbb-uploader.js', array('jquery'), null, true);
}

// Add the upload button
add_action('media_buttons', 'imgbb_uploader_button');

function imgbb_uploader_button() {
    echo '<button id="imgbb-uploader-button" class="button">Upload to ImgBB</button>';
}

// Add inline JavaScript for image upload
add_action('admin_footer', 'imgbb_uploader_inline_script');

function imgbb_uploader_inline_script() {
    ?>
    <script>
    jQuery(document).ready(function ($) {
        $('#imgbb-uploader-button').on('click', function (e) {
            e.preventDefault();
            
            var file_frame;

            // Create the media frame
            file_frame = wp.media({
                title: 'Select Image',
                button: {
                    text: 'Use this image'
                },
                multiple: false
            });

            file_frame.on('select', function () {
                var attachment = file_frame.state().get('selection').first().toJSON();
                uploadImage(attachment.url);
            });

            file_frame.open();
        });

        function uploadImage(imageUrl) {
            var apiKey = '<?php echo esc_js(get_option("imgbb_api_key")); ?>';

            if (!apiKey) {
                alert('Please set your ImgBB API key in the settings.');
                return;
            }

            $.ajax({
                url: 'https://api.imgbb.com/1/upload',
                method: 'POST',
                data: {
                    key: apiKey,
                    image: imageUrl
                },
                success: function (response) {
                    if (response.success) {
                        addImageToMediaLibrary(response.data.url);
                    } else {
                        alert('Upload failed: ' + response.error.message);
                    }
                },
                error: function () {
                    alert('An error occurred during the upload process.');
                }
            });
        }

        function addImageToMediaLibrary(imgUrl) {
            var data = {
                action: 'imgbb_add_image',
                img_url: imgUrl,
                _ajax_nonce: '<?php echo wp_create_nonce("imgbb_nonce"); ?>'
            };

            $.post(ajaxurl, data, function (response) {
                if (response.success) {
                    alert('Image added to Media Library successfully!');
                } else {
                    alert('Error adding image to Media Library: ' + response.data);
                }
            });
        }
    });
    </script>
    <?php
}

// AJAX handler to add the image to the media library
add_action('wp_ajax_imgbb_add_image', 'imgbb_add_image_to_media_library');

function imgbb_add_image_to_media_library() {
    check_ajax_referer('imgbb_nonce', '_ajax_nonce');

    if (!current_user_can('upload_files')) {
        wp_send_json_error('Permission denied.');
        return;
    }

    $img_url = esc_url_raw($_POST['img_url']);

    // Prepare the file for upload
    $response = wp_remote_get($img_url);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        wp_send_json_error('Failed to fetch image from ImgBB.');
        return;
    }

    // Upload the image to WordPress
    $image_data = $response['body'];
    $filename = basename($img_url);

    // Set upload directory
    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['path'] . '/' . $filename;

    // Check if file already exists
    if (file_exists($file_path)) {
        wp_send_json_error('File already exists.');
        return;
    }

    // Create the file on the server
    file_put_contents($file_path, $image_data);

    // Prepare attachment
    $attachment = array(
        'guid' => $upload_dir['url'] . '/' . basename($file_path),
        'post_mime_type' => 'image/jpeg',
        'post_title' => sanitize_file_name($filename),
        'post_content' => '',
        'post_status' => 'inherit'
    );

    // Insert attachment to the database
    $attach_id = wp_insert_attachment($attachment, $file_path);

    // Include image.php for further processing
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    // Generate metadata and update the database
    $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
    wp_update_attachment_metadata($attach_id, $attach_data);

    wp_send_json_success('Image added to Media Library.');
}
