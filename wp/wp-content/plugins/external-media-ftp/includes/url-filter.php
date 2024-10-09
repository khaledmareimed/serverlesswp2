<?php

// Filter the attachment URL to point to the external server
add_filter('wp_get_attachment_url', 'emf_filter_attachment_url', 10, 2);
add_filter('wp_get_attachment_image_src', 'emf_filter_attachment_image_src', 10, 4);

function emf_filter_attachment_url($url, $post_id) {
    // Get the attachment's upload directory
    $upload_dir = wp_get_upload_dir();

    // Get base URL of external server and custom directory
    $base_url = rtrim(get_option('emf_ftp_base_url'), '/');
    $ftp_directory = rtrim(get_option('emf_ftp_directory'), '/') . '/';

    // Replace the upload base URL with the external server base URL
    if (strpos($url, $upload_dir['baseurl']) !== false) {
        $url = str_replace($upload_dir['baseurl'], $base_url . $ftp_directory, $url);
    }

    return $url;
}

function emf_filter_attachment_image_src($image, $attachment_id, $size, $icon) {
    if (is_array($image)) {
        $image[0] = emf_filter_attachment_url($image[0], $attachment_id);
    }
    return $image;
}
