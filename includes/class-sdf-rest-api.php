<?php

/**
 * Handles REST API endpoints for Simple Dynamic Form.
 *
 * React will communicate with these endpoints to:
 * 1. Fetch existing form settings.
 * 2. Save updated form settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SDF_REST_API {

    /**
     * Initialize REST API hooks.
     */
    public static function init() {

        // Register our REST API routes when WordPress initializes its REST API.
        add_action(
            'rest_api_init',
            array( 'SDF_REST_API', 'register_routes' )
        );
    }


    /**
     * Register all REST API endpoints.
     */
    public static function register_routes() {

        /**
         * GET endpoint:
         * Fetch existing form fields and their saved settings.
         *
         * URL:
         * /wp-json/sdf/v1/settings
         */
        register_rest_route(
            'sdf/v1',
            '/settings',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( 'SDF_REST_API', 'get_settings' ),
                'permission_callback' => array( 'SDF_REST_API', 'check_permissions' ),
            )
        );


        /**
         * POST endpoint:
         * Save updated form settings.
         *
         * URL:
         * /wp-json/sdf/v1/settings
         */
        register_rest_route(
            'sdf/v1',
            '/settings',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( 'SDF_REST_API', 'save_settings' ),
                'permission_callback' => array( 'SDF_REST_API', 'check_permissions' ),
            )
        );
    }


    /**
     * Check whether the current user is allowed to access these endpoints.
     */
    public static function check_permissions() {

        // Only administrators with this capability can manage form settings.
        return current_user_can( 'manage_options' );
    }


    /**
     * GET: Return all available fields and their current settings.
     */
    public static function get_settings() {

        // Get all available fields from our existing SDF_Fields class.
        $fields = SDF_Fields::get_fields();

        // Get the default settings for all fields.
        $default_settings = SDF_Fields::get_default_settings();

        // Fetch settings saved in the WordPress options table.
        // If no settings exist yet, use the default settings.
        $saved_settings = get_option(
            'sdf_settings',
            $default_settings
        );

        // Make sure the saved settings have the expected array structure.
        if ( ! is_array( $saved_settings ) ) {
            $saved_settings = $default_settings;
        }

        // Build a clean settings array using only our registered fields.
        $settings = array();

        foreach ( $fields as $field_key => $field ) {

            // Use the saved value if it exists; otherwise use the default.
            $field_setting = isset( $saved_settings[ $field_key ] )
                && is_array( $saved_settings[ $field_key ] )
                ? $saved_settings[ $field_key ]
                : $default_settings[ $field_key ];

            // Convert saved values into consistent 1 or 0 values.
            $show = ! empty( $field_setting['show'] ) ? 1 : 0;

            $required = ! empty( $field_setting['required'] ) ? 1 : 0;

            // A hidden field cannot be required.
            if ( ! $show ) {
                $required = 0;
            }

            $settings[ $field_key ] = array(
                'show'     => $show,
                'required' => $required,
            );
        }

        // Send the field definitions and settings back to React.
        return rest_ensure_response(
            array(
                'fields'   => $fields,
                'settings' => $settings,
            )
        );
    }


    /**
     * POST: Save updated form settings.
     */
    public static function save_settings( WP_REST_Request $request ) {

        // Read the JSON body sent by React.
        $data = $request->get_json_params();

        // Make sure React sent the expected data structure.
        if (
            ! is_array( $data )
            || ! isset( $data['settings'] )
            || ! is_array( $data['settings'] )
        ) {

            return new WP_Error(
                'sdf_invalid_request',
                'Invalid settings data was provided.',
                array(
                    'status' => 400,
                )
            );
        }

        // Extract the settings sent by React.
        $input_settings = $data['settings'];

        // Get the valid field keys defined by our plugin.
        $fields = SDF_Fields::get_fields();

        // Prepare a clean settings array.
        $normalized_settings = array();

        // Track whether at least one field is visible.
        $has_visible_field = false;


        /**
         * Process each registered field.
         *
         * We do not blindly trust the data received from React.
         * Only fields defined in SDF_Fields are accepted.
         */
        foreach ( $fields as $field_key => $field ) {

            // Get the settings for this particular field.
            $field_input = isset( $input_settings[ $field_key ] )
                && is_array( $input_settings[ $field_key ] )
                ? $input_settings[ $field_key ]
                : array();

            // Read and normalize the Show checkbox value.
            $show = rest_sanitize_boolean(
                $field_input['show'] ?? false
            );

            // Read and normalize the Required checkbox value.
            $required = rest_sanitize_boolean(
                $field_input['required'] ?? false
            );

            // A hidden field cannot be required.
            if ( ! $show ) {
                $required = false;
            }

            // Store normalized values for this field.
            $normalized_settings[ $field_key ] = array(
                'show'     => $show,
                'required' => $required,
            );

            // Check whether at least one field is visible.
            if ( $show ) {
                $has_visible_field = true;
            }
        }


        /**
         * Validation:
         * Do not allow the administrator to hide every form field.
         */
        if ( ! $has_visible_field ) {

            return new WP_Error(
                'sdf_no_visible_fields',
                'At least one form field must be visible.',
                array(
                    'status' => 400,
                )
            );
        }


        /**
         * Run our existing settings sanitization function.
         *
         * This reuses the validation logic already present
         * in the SDF_Admin class.
         */
        $sanitized_settings = SDF_Admin::sanitize_settings(
            $normalized_settings
        );


        // Save the sanitized settings in the WordPress options table.
        update_option(
            'sdf_settings',
            $sanitized_settings
        );


        // Fetch the latest saved settings.
        $saved_settings = get_option(
            'sdf_settings',
            SDF_Fields::get_default_settings()
        );


        // Return a success response to React.
        return rest_ensure_response(
            array(
                'message'  => 'Form settings saved successfully.',
                'settings' => $saved_settings,
            )
        );
    }

}
