<?php

// Prevent direct access.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Custom submissions table.
$table_name = $wpdb->prefix . 'sdf_submissions';

// Delete the custom database table.
$wpdb->query(
    $wpdb->prepare(
        'DROP TABLE IF EXISTS %1s',
        $table_name
    )
);

// Delete plugin settings.
delete_option( 'sdf_settings' );
