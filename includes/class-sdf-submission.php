<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


class SDF_Submission {

    /**
     * Register form submission handlers.
     */
    public static function init() {

        add_action(
            'admin_post_sdf_submit',  // Handle submissions from logged-in users.
            array( __CLASS__, 'handle_submission' )
        );

        add_action(
            'admin_post_nopriv_sdf_submit',   // Handle submissions from non-logged-in users.
            array( __CLASS__, 'handle_submission' )
        );
    }

    /**
     * Handle submitted form data.
     */
    public static function handle_submission() {

        // Verify nonce.
        if (
            ! isset( $_POST['sdf_nonce'] ) ||
            ! wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['sdf_nonce'] ) ),
                'sdf_submit'
            )
        ) {
            wp_die(
                'Security verification failed. Please go back and try again.'
            );
        }

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

        // Stop submission if validation fails.
        if ( ! empty( $errors ) ) {

            self::redirect_with_result(
                array(
                    'errors'     => $errors,
                    'old_values' => $clean_data,
                )
            );
        }

        global $wpdb;

        $table_name = $wpdb->prefix . 'sdf_submissions';

        // Prepare data for database insertion.
        $database_data = array(
            'full_name' => $clean_data['full_name'] ?? '',
            'email'     => $clean_data['email'] ?? '',
            'phone'     => $clean_data['phone'] ?? '',
            'age'       => isset( $clean_data['age'] ) && '' !== $clean_data['age']
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

            self::redirect_with_result(
                array(
                    'errors' => array(
                        'general' => 'Something went wrong while saving your submission. Please try again.',
                    ),
                    'old_values' => $clean_data,
                )
            );
        }

        // Redirect after successful submission.
        self::redirect_with_result(
            array(
                'success' => true,
            )
        );
    }

    /**
     * Redirect back with a temporary result.
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
