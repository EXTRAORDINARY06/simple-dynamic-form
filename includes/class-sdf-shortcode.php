<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SDF_Shortcode {

    /**
     * Initialize shortcode and frontend assets.
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
     * Load frontend CSS and JavaScript only
     * on pages containing our shortcode.
     */
    public static function enqueue_assets() {

        global $post;

        if (
            ! is_a( $post, 'WP_Post' ) ||
            ! has_shortcode( $post->post_content, 'sdf_form' )
        ) {
            return;
        }

        // Load frontend CSS.
        wp_enqueue_style(
            'sdf-form-css',
            SDF_PLUGIN_URL . 'assets/form.css',
            array(),
            SDF_VERSION
        );

        // Load frontend JavaScript with jQuery dependency.
        wp_enqueue_script(
            'sdf-form-js',
            SDF_PLUGIN_URL . 'assets/form.js',
            array( 'jquery' ),
            SDF_VERSION,
            true
        );

        // Pass AJAX URL and security nonce to JavaScript.
        wp_localize_script(
            'sdf-form-js',
            'sdf_ajax',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'sdf_submit' )
            )
        );
    }

    /**
     * Render frontend form.
     */
    public static function render_form() {

        $fields = SDF_Fields::get_fields();

        $settings = get_option(
            'sdf_settings',
            SDF_Fields::get_default_settings()
        );

        $result = null;

        // Support the existing normal POST redirect flow.
        if ( isset( $_GET['sdf_result'] ) ) {

            $token = sanitize_text_field(
                wp_unslash( $_GET['sdf_result'] )
            );

            $result = get_transient(
                'sdf_result_' . $token
            );

            delete_transient(
                'sdf_result_' . $token
            );
        }

        ob_start();
        ?>

        <div class="sdf-form-container">

            <?php if ( ! empty( $result['errors'] ) ) : ?>

                <div class="sdf-response-message sdf-error-message" role="alert">
                    <?php
                    foreach ( $result['errors'] as $error ) {
                        echo '<p>' . esc_html( $error ) . '</p>';
                    }
                    ?>
                </div>

            <?php elseif ( ! empty( $result['success'] ) ) : ?>

                <div class="sdf-response-message sdf-success-message" role="status">
                    <?php echo esc_html( 'Your form has been submitted successfully!' ); ?>
                </div>

            <?php endif; ?>

            <form
                class="sdf-form"
                method="post"
                action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                novalidate
            >

                <input
                    type="hidden"
                    name="action"
                    value="sdf_submit"
                >

                <?php wp_nonce_field( 'sdf_submit', 'sdf_nonce' ); ?>

                <?php foreach ( $fields as $key => $field ) : ?>

                    <?php
                    if ( empty( $settings[ $key ]['show'] ) ) {
                        continue;
                    }

                    $is_required = ! empty(
                        $settings[ $key ]['required']
                    );

                    $old_value = isset( $result['old_values'][ $key ] )
                        ? $result['old_values'][ $key ]
                        : '';
                    ?>

                    <div class="sdf-form-group">

                        <label for="sdf-<?php echo esc_attr( $key ); ?>">
                            <?php echo esc_html( $field['label'] ); ?>

                            <?php if ( $is_required ) : ?>
                                <span aria-hidden="true">*</span>
                            <?php endif; ?>
                        </label>

                        <?php if ( 'textarea' === $field['type'] ) : ?>

                            <textarea
                                id="sdf-<?php echo esc_attr( $key ); ?>"
                                name="sdf_submission[<?php echo esc_attr( $key ); ?>]"
                                placeholder="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>"
                                maxlength="<?php echo esc_attr( $field['max_length'] ?? '' ); ?>"
                                <?php echo $is_required ? 'required' : ''; ?>
                                aria-invalid="false"
                            ><?php echo esc_textarea( $old_value ); ?></textarea>

                        <?php else : ?>

                            <input
                                type="<?php echo esc_attr( $field['type'] ); ?>"
                                id="sdf-<?php echo esc_attr( $key ); ?>"
                                name="sdf_submission[<?php echo esc_attr( $key ); ?>]"
                                value="<?php echo esc_attr( $old_value ); ?>"
                                placeholder="<?php echo esc_attr( $field['placeholder'] ?? '' ); ?>"
                                <?php echo $is_required ? 'required' : ''; ?>

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

                                aria-invalid="false"
                            >

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

                <button type="submit" class="sdf-submit-button">
                    Submit
                </button>

            </form>

        </div>

        <?php

        return ob_get_clean();
    }
}
