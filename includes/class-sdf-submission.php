<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SDF_Submission {

    /**
     * Register form submission handlers.
     */
    public static function init() {

        // Normal form submission: logged-in users.
        add_action(
            'admin_post_sdf_submit',
            array( __CLASS__, 'handle_submission' )
        );

        // Normal form submission: visitors.
        add_action(
            'admin_post_nopriv_sdf_submit',
            array( __CLASS__, 'handle_submission' )
        );

        // AJAX submission: logged-in users.
        add_action(
            'wp_ajax_sdf_submit',
            array( __CLASS__, 'handle_ajax_submission' )
        );

        // AJAX submission: visitors.
        add_action(
            'wp_ajax_nopriv_sdf_submit',
            array( __CLASS__, 'handle_ajax_submission' )
        );
    }


    /**
     * Handle normal form submission.
     */
    public static function handle_submission() {

        // Verify the normal form nonce.
        if (
            ! isset( $_POST['sdf_nonce'] ) ||
            ! wp_verify_nonce(
                sanitize_text_field(
                    wp_unslash( $_POST['sdf_nonce'] )
                ),
                'sdf_submit'
            )
        ) {

            wp_die(
                'Security verification failed. Please go back and try again.'
            );
        }

        // Process the submitted data.
        $result = self::process_submission();

        // Redirect back with the result.
        self::redirect_with_result( $result );
    }


    /**
     * Handle AJAX form submission.
     */
    public static function handle_ajax_submission() {

        // Verify the AJAX nonce.
        if (
            ! check_ajax_referer(
                'sdf_submit',
                'nonce',
                false
            )
        ) {

            wp_send_json_error(
                array(
                    'message' => 'Security verification failed. Please refresh the page and try again.',
                )
            );
        }

        // Process the submitted data.
        $result = self::process_submission();

        // Return the result as JSON.
        if ( ! empty( $result['success'] ) ) {

            wp_send_json_success(
                array(
                    'message' => 'Your form has been submitted successfully!',
                )
            );
        }

        wp_send_json_error(
            array(
                'message' => $result['errors']['general']
                    ?? reset( $result['errors'] ),
            )
        );
    }


    /**
     * Validate submitted data and save it in the database.
     *
     * Shared by normal and AJAX submissions.
     */
    private static function process_submission() {

        $fields = SDF_Fields::get_fields();

        $settings = get_option(
            'sdf_settings',
            SDF_Fields::get_default_settings()
        );

        // Get submitted form data.
        $submitted_data = isset( $_POST['sdf_submission'] ) &&
            is_array( $_POST['sdf_submission'] )
                ? wp_unslash( $_POST['sdf_submission'] )
                : array();

        $clean_data = array();
        $errors = array();

        // Sanitize and validate each configured field.
        foreach ( $fields as $key => $field ) {

            // Check whether the field is visible.
            $show = isset( $settings[ $key ]['show'] )
                ? (int) $settings[ $key ]['show']
                : 1;

            if ( 1 !== $show ) {
                continue;
            }

            // Check whether the field is required.
            $required = isset( $settings[ $key ]['required'] )
                ? (int) $settings[ $key ]['required']
                : ( ! empty( $field['required'] ) ? 1 : 0 );

            // Retrieve submitted value.
            $value = isset( $submitted_data[ $key ] ) &&
                is_scalar( $submitted_data[ $key ] )
                    ? trim( (string) $submitted_data[ $key ] )
                    : '';

            // Sanitize according to field type.
            switch ( $field['type'] ) {

                case 'email':
                    $value = sanitize_email( $value );
                    break;

                case 'url':
                    $value = esc_url_raw( $value );
                    break;

                case 'textarea':
                    $value = sanitize_textarea_field( $value );
                    break;

                default:
                    $value = sanitize_text_field( $value );
                    break;
            }

            $clean_data[ $key ] = $value;

            // Required field validation.
            if ( $required && '' === $value ) {

                $errors[ $key ] = $field['label'] . ' is required.';

                continue;
            }

            // Optional empty fields need no format validation.
            if ( '' === $value ) {
                continue;
            }

            // Field-specific validation.
            switch ( $key ) {

                case 'full_name':

                    if (
                        ! preg_match( '/^[\p{L}\s]+$/u', $value ) ||
                        mb_strlen( $value ) > 100
                    ) {
                        $errors[ $key ] = 'Enter a valid name using letters and spaces only (maximum 100 characters).';
                    }

                    break;

                case 'email':

                    if ( ! is_email( $value ) ) {
                        $errors[ $key ] = 'Enter a valid email address.';
                    }

                    break;

                case 'phone':

                    if ( ! preg_match( '/^[0-9]{10}$/', $value ) ) {
                        $errors[ $key ] = 'Phone number must contain exactly 10 digits.';
                    }

                    break;

                case 'age':

                    if (
                        ! ctype_digit( $value ) ||
                        (int) $value < 1 ||
                        (int) $value > 120
                    ) {
                        $errors[ $key ] = 'Age must be an integer between 1 and 120.';
                    }

                    break;

                case 'website':

                    $scheme = wp_parse_url(
                        $value,
                        PHP_URL_SCHEME
                    );

                    if (
                        ! filter_var( $value, FILTER_VALIDATE_URL ) ||
                        ! in_array(
                            strtolower( (string) $scheme ),
                            array( 'http', 'https' ),
                            true
                        )
                    ) {
                        $errors[ $key ] = 'Enter a valid website URL starting with http:// or https://.';
                    }

                    break;

                case 'city':

                    if ( mb_strlen( $value ) > 100 ) {
                        $errors[ $key ] = 'City must not exceed 100 characters.';
                    }

                    break;

                case 'message':

                    if ( mb_strlen( $value ) > 1000 ) {
                        $errors[ $key ] = 'Message must not exceed 1000 characters.';
                    }

                    break;
            }
        }

        // Stop if validation fails.
        if ( ! empty( $errors ) ) {

            return array(
                'success'    => false,
                'errors'     => $errors,
                'old_values' => $clean_data,
            );
        }

        global $wpdb;

        $table_name = $wpdb->prefix . 'sdf_submissions';

        // Prepare data for database insertion.
        $database_data = array(
            'full_name' => $clean_data['full_name'] ?? '',
            'email'     => $clean_data['email'] ?? '',
            'phone'     => $clean_data['phone'] ?? '',

            'age'       => isset( $clean_data['age'] ) &&
                '' !== $clean_data['age']
                    ? (int) $clean_data['age']
                    : null,

            'website'   => $clean_data['website'] ?? '',
            'city'      => $clean_data['city'] ?? '',
            'message'   => $clean_data['message'] ?? '',
        );

        // Insert the submission into the database.
        $inserted = $wpdb->insert(
            $table_name,
            $database_data,
            array(
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
            )
        );

        // Handle database insertion failure.
        if ( false === $inserted ) {

            return array(
                'success' => false,
                'errors'  => array(
                    'general' => 'Something went wrong while saving your submission. Please try again.',
                ),
                'old_values' => $clean_data,
            );
        }

        // Return successful result.
        return array(
            'success' => true,
        );
    }


    /**
     * Redirect back with a temporary result.
     *
     * Used only by normal form submission.
     */
    private static function redirect_with_result( $result ) {

        // Generate a unique token.
        $token = wp_generate_password(
            20,
            false,
            false
        );

        // Store submission result temporarily.
        set_transient(
            'sdf_result_' . $token,
            $result,
            5 * MINUTE_IN_SECONDS
        );

        // Get the previous page URL.
        $redirect_url = wp_get_referer();

        if ( ! $redirect_url ) {
            $redirect_url = home_url( '/' );
        }

        // Remove any previous result parameter.
        $redirect_url = remove_query_arg(
            'sdf_result',
            $redirect_url
        );

        // Add the new result token.
        $redirect_url = add_query_arg(
            'sdf_result',
            $token,
            $redirect_url
        );

        wp_safe_redirect( $redirect_url );

        exit;
    }

}
