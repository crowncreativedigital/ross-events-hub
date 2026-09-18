<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class REH_Post_Types {
    const POST_TYPE         = 'rhg_event';
    const LOCATION_TAXONOMY = 'rhg_event_location';

    public static function init() {
        add_action( 'init', array( __CLASS__, 'register' ) );
        add_action( 'add_meta_boxes', array( __CLASS__, 'add_location_metabox' ) );
        add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_location_metabox' ) );
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'add_admin_columns' ) );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_admin_columns' ), 10, 2 );
    }

    public static function register() {
        register_post_type(
            self::POST_TYPE,
            array(
                'labels' => array(
                    'name'               => __( 'Events', 'ross-events-hub' ),
                    'singular_name'      => __( 'Event', 'ross-events-hub' ),
                    'add_new_item'       => __( 'Add New Event', 'ross-events-hub' ),
                    'edit_item'          => __( 'Edit Event', 'ross-events-hub' ),
                    'new_item'           => __( 'New Event', 'ross-events-hub' ),
                    'view_item'          => __( 'View Event', 'ross-events-hub' ),
                    'search_items'       => __( 'Search Events', 'ross-events-hub' ),
                    'not_found'          => __( 'No events found', 'ross-events-hub' ),
                    'not_found_in_trash' => __( 'No events found in Trash', 'ross-events-hub' ),
                    'menu_name'          => __( 'Events', 'ross-events-hub' ),
                ),
                'public'             => true,
                'show_ui'            => true,
                'show_in_rest'       => true,
                'rest_base'          => 'events',
                'menu_icon'          => 'dashicons-calendar-alt',
                'has_archive'        => false,
                'rewrite'            => array( 'slug' => 'event' ),
                'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
                'publicly_queryable' => true,
                'menu_position'      => 21,
            )
        );

        register_taxonomy(
            self::LOCATION_TAXONOMY,
            array( self::POST_TYPE ),
            array(
                'labels' => array(
                    'name'          => __( 'Event Locations', 'ross-events-hub' ),
                    'singular_name' => __( 'Event Location', 'ross-events-hub' ),
                    'menu_name'     => __( 'Event Locations', 'ross-events-hub' ),
                ),
                'public'            => true,
                'show_ui'           => true,
                'show_admin_column' => false,
                'show_in_rest'      => true,
                'hierarchical'      => true,
                'meta_box_cb'       => false,
                'rewrite'           => array( 'slug' => 'event-location' ),
            )
        );
    }

    public static function add_location_metabox() {
        add_meta_box(
            'reh_event_location',
            __( 'Event Location', 'ross-events-hub' ),
            array( __CLASS__, 'render_location_metabox' ),
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    public static function render_location_metabox( $post ) {
        wp_nonce_field( 'reh_save_event_location', 'reh_event_location_nonce' );

        $terms    = wp_get_object_terms( $post->ID, self::LOCATION_TAXONOMY, array( 'fields' => 'ids' ) );
        $selected = ! empty( $terms ) ? (int) $terms[0] : 0;
        $locations = get_terms(
            array(
                'taxonomy'   => self::LOCATION_TAXONOMY,
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
            )
        );

        echo '<p><label for="reh_event_location_field">' . esc_html__( 'Select a location', 'ross-events-hub' ) . '</label></p>';
        echo '<select name="reh_event_location_field" id="reh_event_location_field" style="width:100%;">';
        echo '<option value="">' . esc_html__( '— Select location —', 'ross-events-hub' ) . '</option>';

        if ( ! is_wp_error( $locations ) ) {
            foreach ( $locations as $location ) {
                printf(
                    '<option value="%1$d" %2$s>%3$s</option>',
                    (int) $location->term_id,
                    selected( $selected, (int) $location->term_id, false ),
                    esc_html( $location->name )
                );
            }
        }

        echo '</select>';
        echo '<p style="margin-top:8px;color:#646970;">' . esc_html__( 'Manage available locations under Events → Event Locations.', 'ross-events-hub' ) . '</p>';
    }

    public static function save_location_metabox( $post_id ) {
        if ( ! isset( $_POST['reh_event_location_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['reh_event_location_nonce'] ) ), 'reh_save_event_location' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $location_id = isset( $_POST['reh_event_location_field'] ) ? (int) $_POST['reh_event_location_field'] : 0;

        if ( $location_id > 0 ) {
            wp_set_object_terms( $post_id, array( $location_id ), self::LOCATION_TAXONOMY, false );
        } else {
            wp_set_object_terms( $post_id, array(), self::LOCATION_TAXONOMY, false );
        }
    }

    public static function add_admin_columns( $columns ) {
        $new = array();

        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;

            if ( 'title' === $key ) {
                $new['reh_event_location'] = __( 'Location', 'ross-events-hub' );
            }
        }

        return $new;
    }

    public static function render_admin_columns( $column, $post_id ) {
        if ( 'reh_event_location' !== $column ) {
            return;
        }

        $terms = get_the_terms( $post_id, self::LOCATION_TAXONOMY );

        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            echo '—';
            return;
        }

        echo esc_html( $terms[0]->name );
    }
}
