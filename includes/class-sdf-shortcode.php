<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SDF_Shortcode {

    /**
     * Register shortcode and frontend hooks.
     */
    public static function init() {

        add_shortcode(
            'sdf_form',
            array( __CLASS__, 'render_form' )
        );

        add_action(
            'wp_enqueue_scripts',
            array( __CLASS__, 'enqueue_assets' )
        );
    }

    /**
     * Load frontend CSS and JavaScript when shortcode is present....
     */
    public static function enqueue_assets() {

        global $post;

        if (
            ! is_a( $post, 'WP_Post' ) ||
            ! has_shortcode( $post->post_content, 'sdf_form' )
        ) 
        {
            return;
        }


        // frontend CSS  form which the user are seeing 
        wp_enqueue_style(
            'sdf-form-css',
            SDF_PLUGIN_URL . 'assets/form.css',
            array(),
            SDF_VERSION
        );


        // Frontend JavaScript for the form like showing error messages, validation, etc. which the user are typing in the form and also for the success message after submission       
         wp_enqueue_script(
            'sdf-form-js',
            SDF_PLUGIN_URL . 'assets/form.js',
            array(),
            SDF_VERSION,
            true
        );
    }

    /**
     * Display the dynamic form.
     */
    public static function render_form() {

        $fields = SDF_Fields::get_fields();

        $settings = get_option(
            'sdf_settings',
            SDF_Fields::get_default_settings()
        );

        // Retrieve the temporary result token.
        $token = isset( $_GET['sdf_result'] )
            ? sanitize_key( wp_unslash( $_GET['sdf_result'] ) )
            : '';

        // Retrieve submission result.
        $result = $token
            ? get_transient( 'sdf_result_' . $token )
            : false;

        // Delete the transient after retrieving it.
        if ( $token && false !== $result ) {
            delete_transient( 'sdf_result_' . $token );
        }

        // Retrieve validation errors.
        $errors = (
            is_array( $result ) &&
            ! empty( $result['errors'] )
        )
            ? $result['errors']
            : array();

        // Retrieve previously submitted values.
        $old_values = (
            is_array( $result ) &&
            ! empty( $result['old_values'] )
        )
            ? $result['old_values']
            : array();

        // Check submission success.
        $success = (
            is_array( $result ) &&
            ! empty( $result['success'] )
        );

        ob_start();

        ?>

        <div class="sdf-form-container">

            <h2>Contact Form</h2>

            <?php if ( $success ) : ?>

                <div class="sdf-success-message" role="status">
                    Your form has been submitted successfully!
                </div>

            <?php endif; ?>

            <?php if ( ! empty( $errors['general'] ) ) : ?>

                <div class="sdf-general-error" role="alert">
                    <?php echo esc_html( $errors['general'] ); ?>
                </div>

            <?php endif; ?>

            <form
                class="sdf-form"
                method="post"
                action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                novalidate
            >

                <!-- Submission action -->
                <input
                    type="hidden"
                    name="action"
                    value="sdf_submit"
                >

                <!-- Security nonce -->
                <?php
                wp_nonce_field(
                    'sdf_submit',
                    'sdf_nonce'
                );
                ?>

                <?php foreach ( $fields as $key => $field ) : ?>

                    <?php

                    // Check whether the field should be displayed.
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

                    // Previously submitted value.
                    $value = isset( $old_values[ $key ] )
                        ? $old_values[ $key ]
                        : '';

                    // Field-specific validation error.
                    $field_error = isset( $errors[ $key ] )
                        ? $errors[ $key ]
                        : '';

                    ?>

                    <div class="sdf-form-group">

                        <label for="sdf_<?php echo esc_attr( $key ); ?>">

                            <?php echo esc_html( $field['label'] ); ?>

                            <?php if ( $required ) : ?>
                                <span class="sdf-required">*</span>
                            <?php endif; ?>

                        </label>

                        <?php if ( 'textarea' === $field['type'] ) : ?>

                            <textarea
                                id="sdf_<?php echo esc_attr( $key ); ?>"
                                name="sdf_submission[<?php echo esc_attr( $key ); ?>]"
                                placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"
                                <?php echo $required ? 'required' : ''; ?>
                                maxlength="<?php echo esc_attr( $field['max_length'] ?? 1000 ); ?>"
                                aria-invalid="<?php echo $field_error ? 'true' : 'false'; ?>"
                            ><?php echo esc_textarea( $value ); ?></textarea>

                        <?php else : ?>

                            <input
                                type="<?php echo esc_attr( $field['type'] ); ?>"
                                id="sdf_<?php echo esc_attr( $key ); ?>"
                                name="sdf_submission[<?php echo esc_attr( $key ); ?>]"
                                value="<?php echo esc_attr( $value ); ?>"
                                placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"
                                <?php echo $required ? 'required' : ''; ?>

                                <?php if ( isset( $field['max_length'] ) ) : ?>
                                    maxlength="<?php echo esc_attr( $field['max_length'] ); ?>"
                                <?php endif; ?>

                                <?php if ( isset( $field['min'] ) ) : ?>
                                    min="<?php echo esc_attr( $field['min'] ); ?>"
                                <?php endif; ?>

                                <?php if ( isset( $field['max'] ) ) : ?>
                                    max="<?php echo esc_attr( $field['max'] ); ?>"
                                <?php endif; ?>

                                <?php if ( isset( $field['step'] ) ) : ?>
                                    step="<?php echo esc_attr( $field['step'] ); ?>"
                                <?php endif; ?>

                                <?php if ( isset( $field['pattern'] ) ) : ?>
                                    pattern="<?php echo esc_attr( $field['pattern'] ); ?>"
                                <?php endif; ?>

                                aria-invalid="<?php echo $field_error ? 'true' : 'false'; ?>"
                            >

                        <?php endif; ?>

                        <?php if ( $field_error ) : ?>

                            <p class="sdf-error-message" role="alert">
                                <?php echo esc_html( $field_error ); ?>
                            </p>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

                <button
                    type="submit"
                    class="sdf-submit-button"
                >
                    Submit
                </button>

            </form>

        </div>

        <?php

        return ob_get_clean();
    }

}
