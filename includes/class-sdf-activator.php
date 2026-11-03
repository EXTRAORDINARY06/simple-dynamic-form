<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SDF_Activator {

    /**
     * Create the custom database table during plugin activation.
     */
    public static function activate() {

        global $wpdb;

        // Custom submissions table name.
        $table_name = $wpdb->prefix . 'sdf_submissions';

        // Get the database character set and collation.
        $charset_collate = $wpdb->get_charset_collate();

        // SQL query to create the submissions table.
        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(10) NOT NULL,
            age INT(3) DEFAULT NULL,
            website VARCHAR(255) DEFAULT NULL,
            city VARCHAR(100) DEFAULT NULL,
            message TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Load WordPress database upgrade functionality.
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Create or update the database table.
        dbDelta( $sql );

        // Get default settings from SDF_Fields.
        // developed by developer itelf 
        $default_settings = SDF_Fields::get_default_settings();
        

        // Add default settings only if they do not already exist.
        // only 1st time activation, if settings already exist, do not overwrite them
        add_option( 'sdf_settings', $default_settings );
    }

}
