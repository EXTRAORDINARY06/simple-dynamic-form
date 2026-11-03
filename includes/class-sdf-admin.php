<?php
// Handles the WordPress admin settings and submissions pages.

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SDF_Admin {

    /**
     * Initialize admin hooks.
     */
    public static function init() {

        add_action( 'admin_menu', array( 'SDF_Admin', 'add_admin_menu' ) );

        add_action( 'admin_init', array( 'SDF_Admin', 'register_settings' ));

        add_action(
            'admin_enqueue_scripts',
            array( 'SDF_Admin', 'enqueue_admin_scripts' )
        );
    }

    /**
     * Register admin menu pages.
     */
    public static function add_admin_menu() {

        add_menu_page(
            'Simple Dynamic Form',
            'Simple Dynamic Form',
            'manage_options',
            'sdf-settings',
            array( 'SDF_Admin', 'render_settings_page' ),
            'dashicons-feedback',
            25
        );

        add_submenu_page(
            'sdf-settings',
            'Form Submissions',
            'Submissions',
            'manage_options',
            'sdf-submissions',
            array( 'SDF_Admin', 'render_submissions_page' )
        );
    }

    /**
     * Register plugin settings.
     */
    public static function register_settings() {

        register_setting(
            'sdf_settings_group',
            'sdf_settings',
            array(
                'sanitize_callback' => array( 'SDF_Admin', 'sanitize_settings' )
            )
        );
    }

    /**
     * Sanitize settings before saving.
     */
    public static function sanitize_settings( $input ) {

        $fields = SDF_Fields::get_fields();

        $sanitized_settings = array();

        foreach ( $fields as $field_key => $field ) {

            $show = isset( $input[ $field_key ]['show'] )
                ? 1
                : 0;

            $required = isset( $input[ $field_key ]['required'] )
                ? 1
                : 0;

            // A hidden field cannot be required.
            if ( ! $show ) {
                $required = 0;
            }

            $sanitized_settings[ $field_key ] = array(
                'show'     => $show,
                'required' => $required
            );
        }

        // Ensure at least one field remains visible.
        $has_visible_field = false;

        foreach ( $sanitized_settings as $field_setting ) {

            if ( $field_setting['show'] ) {
                $has_visible_field = true;
                break;
            }
        }

        if ( ! $has_visible_field ) {

            add_settings_error(
                'sdf_settings',
                'sdf_no_visible_fields',
                'At least one form field must be visible.',
                'error'
            );

            return get_option(
                'sdf_settings',
                SDF_Fields::get_default_settings()
            );
        }

        return $sanitized_settings;
    }

    /**
     * Load admin JavaScript.
     */

    // loading javascript file in setttings page to handle the required checkbox based on the show checkbox
    public static function enqueue_admin_scripts( $hook ) {

        // Load JavaScript only on the plugin settings page.
        if ( 'toplevel_page_sdf-settings' !== $hook ) {
            return;
        }

        $script_path = plugin_dir_path( dirname( __FILE__ ) )
            . 'assets/admin.js';

        $script_url = plugin_dir_url( dirname( __FILE__ ) )
            . 'assets/admin.js';

        wp_enqueue_script(
            'sdf-admin-js',
            $script_url,
            array(),
            file_exists( $script_path ) ? filemtime( $script_path ) : '1.0.0',
            true
        );
    }

    /**
     * Render settings page.
     */

    // when u click on the simple dynamic form in the admin menu, it will show the settings page where you can select which fields to show and which fields are required. It also shows the shortcode to use the form on any page.
    public static function render_settings_page() {

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $fields = SDF_Fields::get_fields();

        $settings = get_option(
            'sdf_settings',
            SDF_Fields::get_default_settings()
        );
        ?>

        <div class="wrap">

            <h1>Simple Dynamic Form Settings</h1>

            <p>
                Select which fields should appear on your form
                and which fields should be mandatory.
            </p>

            <form method="post" action="options.php">

                <?php settings_fields( 'sdf_settings_group' ); ?>

                <table class="widefat striped">

                    <thead>
                        <tr>
                            <th>Field Name</th>
                            <th>Show Field</th>
                            <th>Required Field</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ( $fields as $field_key => $field ) :

                            $field_settings = $settings[ $field_key ] ?? array();

                            $is_shown = ! empty( $field_settings['show'] );

                            $is_required = ! empty( $field_settings['required'] );

                            // A hidden field cannot be required.
                            $is_required = $is_shown && $is_required;

                        ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?php echo esc_html( $field['label'] ); ?>
                                    </strong>
                                </td>

                                <td>

                                    <label>
                                        <input
                                            type="checkbox"
                                            name="sdf_settings[<?php echo esc_attr( $field_key ); ?>][show]"
                                            value="1"
                                            class="sdf-show-field"
                                            data-field="<?php echo esc_attr( $field_key ); ?>"
                                            <?php checked( $is_shown ); ?>
                                        />

                                        Show
                                    </label>

                                </td>

                                <td>

                                    <label>
                                        <input
                                            type="checkbox"
                                            name="sdf_settings[<?php echo esc_attr( $field_key ); ?>][required]"
                                            value="1"
                                            class="sdf-required-field"
                                            data-field="<?php echo esc_attr( $field_key ); ?>"
                                            <?php checked( $is_required ); ?>
                                            <?php disabled( ! $is_shown ); ?>
                                        />

                                        Required
                                    </label>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

                <?php submit_button( 'Save Settings' ); ?>

            </form>

            <hr>

            <h3>Form Shortcode</h3>

            <p>
                Use the following shortcode on any WordPress page
                to display your dynamic form:
            </p>

            <code>[sdf_form]</code>

        </div>

        <?php
    }

    /**
     * Render submissions page.
     */

    // This is the page where you can see all the submissions of the form. It shows the data submitted by the users in a table format.
    //  It also has pagination to navigate through the submissions.
    public static function render_submissions_page() {

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $wpdb;

        $table_name = $wpdb->prefix . 'sdf_submissions';

        $per_page = 10;

        $current_page = isset( $_GET['paged'] )
            ? max( 1, absint( $_GET['paged'] ) )
            : 1;

        $offset = ( $current_page - 1 ) * $per_page;

        $total_items = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name}"
        );

        $total_pages = (int) ceil( $total_items / $per_page );

        $submissions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );
        ?>

        <div class="wrap">

            <h1>Form Submissions</h1>

            <table class="widefat striped">

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Age</th>
                        <th>Website</th>
                        <th>City</th>
                        <th>Message</th>
                        <th>Submitted At</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if ( ! empty( $submissions ) ) : ?>

                        <?php foreach ( $submissions as $submission ) : ?>

                            <tr>

                                <td><?php echo esc_html( $submission->id ); ?></td>

                                <td><?php echo esc_html( $submission->full_name ); ?></td>

                                <td><?php echo esc_html( $submission->email ); ?></td>

                                <td><?php echo esc_html( $submission->phone ); ?></td>

                                <td><?php echo esc_html( $submission->age ); ?></td>

                                <td>
                                    <?php if ( ! empty( $submission->website ) ) : ?>
                                        <a
                                            href="<?php echo esc_url( $submission->website ); ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <?php echo esc_html( $submission->website ); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>

                                <td><?php echo esc_html( $submission->city ); ?></td>

                                <td><?php echo esc_html( $submission->message ); ?></td>

                                <td><?php echo esc_html( $submission->created_at ); ?></td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else : ?>

                        <tr>
                            <td colspan="9">
                                No submissions found.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

            <?php if ( $total_pages > 1 ) : ?>

                <div class="tablenav">
                    <div class="tablenav-pages">

                        <?php

                        echo wp_kses_post(
                            paginate_links(
                                array(
                                    'base' => add_query_arg(
                                        'paged',
                                        '%#%',
                                        menu_page_url( 'sdf-submissions', false )
                                    ),
                                    'format'    => '',
                                    'current'   => $current_page,
                                    'total'     => $total_pages,
                                    'prev_text' => '&laquo;',
                                    'next_text' => '&raquo;'
                                )
                            )
                        );

                        ?>

                    </div>
                </div>

            <?php endif; ?>

        </div>

        <?php
    }
}
