<?php
/**
 * Plugin Name: ImgBB Uploader
 * Plugin URI: https://example.com/imgbb-uploader
 * Description: Upload images to ImgBB and integrate them into the WordPress media library.
 * Version: 1.0
 * Author: Your Name
 * Author URI: https://example.com
 */

// Add settings page
function imgbb_uploader_menu() {
    add_options_page('ImgBB Uploader Settings', 'ImgBB Uploader', 'manage_options', 'imgbb-uploader', 'imgbb_uploader_settings_page');
}
add_action('admin_menu', 'imgbb_uploader_menu');

// Settings page content
function imgbb_uploader_settings_page() {
    ?>
    <div class="wrap imgbb-uploader-wrap">
        <h1>ImgBB Uploader Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('imgbb_uploader_settings');
            do_settings_sections('imgbb_uploader_settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings
function imgbb_uploader_settings() {
    register_setting('imgbb_uploader_settings', 'imgbb_api_key');
    add_settings_section('imgbb_uploader_main', 'Main Settings', null, 'imgbb_uploader_settings');
    add_settings_field('imgbb_api_key', 'ImgBB API Key', 'imgbb_api_key_callback', 'imgbb_uploader_settings', 'imgbb_uploader_main');
}
add_action('admin_init', 'imgbb_uploader_settings');

// API Key field callback
function imgbb_api_key_callback() {
    $api_key = get_option('imgbb_api_key');
    echo "<input type='text' name='imgbb_api_key' value='$api_key' class='regular-text'>";
}

// Enqueue admin styles
function imgbb_uploader_admin_styles() {
    wp_add_inline_style('admin-menu', imgbb_uploader_get_admin_styles());
}
add_action('admin_enqueue_scripts', 'imgbb_uploader_admin_styles');

// Admin styles
function imgbb_uploader_get_admin_styles() {
    return "
    .imgbb-uploader-wrap {
        max-width: 800px;
        margin: 20px auto;
        background: #fff;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }
    .imgbb-uploader-wrap h1 {
        color: #23282d;
        font-size: 24px;
        margin-bottom: 20px;
    }
    .imgbb-uploader-wrap form {
        margin-top: 20px;
    }
    .imgbb-uploader-wrap .form-table th {
        font-weight: 600;
        padding: 20px 10px 20px 0;
    }
    .imgbb-uploader-wrap .form-table td {
        padding: 15px 10px;
    }
    .imgbb-uploader-wrap input[type='text'] {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    .imgbb-uploader-wrap .submit {
        margin-top: 20px;
    }
    .imgbb-uploader-wrap .button-primary {
        background: #0073aa;
        border-color: #0073aa;
        color: #fff;
        text-decoration: none;
        text-shadow: none;
        padding: 8px 15px;
        border-radius: 4px;
        font-size: 14px;
        cursor: pointer;
        transition: background 0.3s ease;
    }
    .imgbb-uploader-wrap .button-primary:hover {
        background: #005177;
    }
    ";
}

// Hook into WordPress upload process
function imgbb_upload_handler($file) {
    $api_key = get_option('imgbb_api_key');
    if (empty($api_key)) {
        return $file;
    }

    $imgbb_url = 'https://api.imgbb.com/1/upload';
    $image_data = file_get_contents($file['tmp_name']);
    
    $payload = array(
        'key' => $api_key,
        'image' => base64_encode($image_data)
    );

    $response = wp_remote_post($imgbb_url, array(
        'body' => $payload,
    ));

    if (is_wp_error($response)) {
        return $file;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($body['data']['url'])) {
        $file['url'] = $body['data']['url'];
        $file['type'] = $body['data']['mime'];
    }

    return $file;
}
add_filter('wp_handle_upload', 'imgbb_upload_handler');

// Modify attachment metadata to use ImgBB URL
function imgbb_update_attachment_metadata($metadata, $attachment_id) {
    $file = get_attached_file($attachment_id);
    $imgbb_url = get_post_meta($attachment_id, '_wp_attached_file', true);

    if (strpos($imgbb_url, 'https://i.ibb.co/') === 0) {
        update_post_meta($attachment_id, '_wp_attached_file', $imgbb_url);
        $metadata['file'] = $imgbb_url;
        $metadata['sizes'] = array(
            'full' => array(
                'file' => basename($imgbb_url),
                'width' => $metadata['width'],
                'height' => $metadata['height'],
                'mime-type' => $metadata['mime_type']
            )
        );
    }

    return $metadata;
}
add_filter('wp_generate_attachment_metadata', 'imgbb_update_attachment_metadata', 10, 2);
