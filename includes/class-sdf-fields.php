<?php

// Defines available form fields and defaults

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SDF_Fields {

    /**
     * Return all available form fields.
     */
    public static function get_fields() {

        return array(

            'full_name' => array(
                'label'       => 'Full Name',
                'type'        => 'text',
                'required'    => true,
                'placeholder' => 'Enter your full name',
                'max_length'  => 100,
                'pattern'     => '[A-Za-z ]+',
            ),

            'email' => array(
                'label'       => 'Email',
                'type'        => 'email',
                'required'    => true,
                'placeholder' => 'Enter your email',
            ),

            'phone' => array(
                'label'       => 'Phone',
                'type'        => 'tel',
                'required'    => true,
                'placeholder' => 'Enter 10-digit phone number',
                'pattern'     => '[0-9]{10}',
                'max_length'  => 10,
            ),

            'age' => array(
                'label'       => 'Age',
                'type'        => 'number',
                'required'    => false,
                'placeholder' => 'Enter your age',
                'min'         => 1,
                'max'         => 120,
                'step'        => 1,
            ),

            'website' => array(
                'label'       => 'Website',
                'type'        => 'url',
                'required'    => false,
                'placeholder' => 'https://example.com',
            ),

            'city' => array(
                'label'       => 'City',
                'type'        => 'text',
                'required'    => false,
                'placeholder' => 'Enter your city',
                'max_length'  => 100,
            ),

            'message' => array(
                'label'       => 'Message',
                'type'        => 'textarea',
                'required'    => false,
                'placeholder' => 'Enter your message',
                'max_length'  => 1000,
            ),

        );
    }

    /**
     * Return default settings for all form fields.
     */
    public static function get_default_settings() {

        $fields = self::get_fields();

        $default_settings = array();

        foreach ( $fields as $field_key => $field ) {

            $default_settings[ $field_key ] = array(
                'show'     => 1,
                'required' => ! empty( $field['required'] ) ? 1 : 0,
            );
        }

        return $default_settings;
    }

}
