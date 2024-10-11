<?php
/**
 * Plugin Name: ImgBB Uploader
 * Description: Upload images to ImgBB and save them in the WordPress Media Library with the ImgBB URL.
 * Version: 1.0
 * Author: khaled
 */

// Enqueue admin styles and scripts
function imgbb_uploader_enqueue() {
    wp_enqueue_style( 'imgbb_uploader_css', plugins_url( '/css/style.css', __FILE__ ) );
}
add_action( 'admin_enqueue_scripts', 'imgbb_uploader_enqueue' );

// Create settings page for the API key
function imgbb_uploader_menu() {
    add_menu_page( 'ImgBB Uploader Settings', 'ImgBB Uploader', 'manage_options', 'imgbb-uploader', 'imgbb_uploader_settings_page', 'dashicons-upload', 110 );
}
add_action( 'admin_menu', 'imgbb_uploader_menu' );

// Settings page content
function imgbb_uploader_settings_page() {
    if ( isset( $_POST['imgbb_api_key'] ) ) {
        update_option( 'imgbb_api_key', sanitize_text_field( $_POST['imgbb_api_key'] ) );
        echo '<div class="notice notice-success is-dismissible"><p>API Key saved successfully.</p></div>';
    }

    $imgbb_api_key = get_option( 'imgbb_api_key', '' );
    ?>
    <div class="wrap">
        <h1>ImgBB Uploader Settings</h1>
        <form method="POST">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="imgbb_api_key">ImgBB API Key</label></th>
                    <td><input type="text" id="imgbb_api_key" name="imgbb_api_key" value="<?php echo esc_attr( $imgbb_api_key ); ?>" class="regular-text" /></td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary">Save API Key</button>
            </p>
        </form>
    </div>
    <?php
}

// Add a custom image uploader button to the Media Library
function imgbb_uploader_media_button() {
    echo '<button id="imgbb-upload-btn" class="button">Upload via ImgBB</button>';
}
add_action( 'media_buttons', 'imgbb_uploader_media_button', 20 );

// Handle ImgBB image upload via AJAX
function imgbb_uploader_ajax() {
    $imgbb_api_key = get_option( 'imgbb_api_key' );

    if ( ! $imgbb_api_key ) {
        wp_send_json_error( 'ImgBB API key not set.' );
        return;
    }

    if ( ! isset( $_FILES['image'] ) ) {
        wp_send_json_error( 'No image provided.' );
        return;
    }

    $image = $_FILES['image'];

    // Upload the image to ImgBB
    $response = wp_remote_post( 'https://api.imgbb.com/1/upload?key=' . $imgbb_api_key, [
        'body' => [
            'image' => base64_encode( file_get_contents( $image['tmp_name'] ) ),
        ],
    ] );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( 'Error uploading image to ImgBB.' );
        return;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( isset( $body['data']['url'] ) ) {
        // Add image to WordPress media library with the ImgBB URL
        $url = $body['data']['url'];
        $attachment = array(
            'guid'           => $url, 
            'post_mime_type' => $image['type'],
            'post_title'     => sanitize_file_name( $image['name'] ),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );

        $attach_id = wp_insert_attachment( $attachment, $url );
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        $attach_data = wp_generate_attachment_metadata( $attach_id, $url );
        wp_update_attachment_metadata( $attach_id, $attach_data );

        wp_send_json_success( [ 'url' => $url, 'attachment_id' => $attach_id ] );
    } else {
        wp_send_json_error( 'ImgBB upload failed.' );
    }
}
add_action( 'wp_ajax_imgbb_uploader', 'imgbb_uploader_ajax' );

// Admin page CSS styling
function imgbb_uploader_admin_styles() {
    ?>
    <style>
        .wrap h1 {
            font-size: 26px;
            margin-bottom: 20px;
        }
        .wrap form {
            background: #fff;
            padding: 20px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
        }
        .wrap table.form-table th {
            padding: 10px 20px;
            width: 250px;
        }
        .wrap table.form-table td {
            padding: 10px 20px;
        }
    </style>
    <?php
}
add_action( 'admin_head', 'imgbb_uploader_admin_styles' );
