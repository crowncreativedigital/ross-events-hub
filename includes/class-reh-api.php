<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class REH_API {
    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes() {
        register_rest_route(
            'ross/v1',
            '/events/latest',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'latest_events' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'site'  => array( 'sanitize_callback' => 'sanitize_title' ),
                    'limit' => array( 'sanitize_callback' => 'absint' ),
                ),
            )
        );

        register_rest_route(
            'ross/v1',
            '/events/archive',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'archive_events' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'site'     => array( 'sanitize_callback' => 'sanitize_title' ),
                    'page'     => array( 'sanitize_callback' => 'absint' ),
                    'per_page' => array( 'sanitize_callback' => 'absint' ),
                ),
            )
        );

        register_rest_route(
            'ross/v1',
            '/locations',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'locations' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    public static function latest_events( WP_REST_Request $request ) {
        $site  = $request->get_param( 'site' );
        $limit = $request->get_param( 'limit' ) ? absint( $request->get_param( 'limit' ) ) : 5;
        $limit = max( 1, min( $limit, 20 ) );

        $query = new WP_Query( self::build_query_args( $site, $limit, 1 ) );

        return rest_ensure_response(
            array(
                'items' => self::format_events( $query->posts ),
            )
        );
    }

    public static function archive_events( WP_REST_Request $request ) {
        $site     = $request->get_param( 'site' );
        $page     = $request->get_param( 'page' ) ? absint( $request->get_param( 'page' ) ) : 1;
        $per_page = $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : 12;
        $per_page = max( 1, min( $per_page, 50 ) );

        $query = new WP_Query( self::build_query_args( $site, $per_page, $page ) );

        return rest_ensure_response(
            array(
                'items'        => self::format_events( $query->posts ),
                'found_posts'   => (int) $query->found_posts,
                'max_num_pages' => (int) $query->max_num_pages,
                'current_page'  => (int) $page,
            )
        );
    }

    public static function locations() {
        $terms = get_terms(
            array(
                'taxonomy'   => REH_Post_Types::LOCATION_TAXONOMY,
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
            )
        );

        if ( is_wp_error( $terms ) ) {
            return rest_ensure_response( array() );
        }

        $items = array();
        foreach ( $terms as $term ) {
            $label_colour      = '';
            $label_text_colour = '';

            if ( function_exists( 'get_field' ) ) {
                $label_colour      = get_field( 'label_colour', REH_Post_Types::LOCATION_TAXONOMY . '_' . $term->term_id );
                $label_text_colour = get_field( 'label_text_colour', REH_Post_Types::LOCATION_TAXONOMY . '_' . $term->term_id );
            }

            $items[] = array(
                'id'                => (int) $term->term_id,
                'name'              => $term->name,
                'slug'              => $term->slug,
                'label_colour'      => $label_colour,
                'label_text_colour' => $label_text_colour,
            );
        }

        return rest_ensure_response( array( 'items' => $items ) );
    }

    protected static function build_query_args( $site, $posts_per_page, $paged ) {
        $meta_query = array(
            array(
                'key'     => self::event_date_key(),
                'value'   => current_time( 'Ymd' ),
                'compare' => '>=',
                'type'    => 'NUMERIC',
            ),
        );

        $args = array(
            'post_type'           => REH_Post_Types::POST_TYPE,
            'post_status'         => 'publish',
            'posts_per_page'      => $posts_per_page,
            'paged'               => $paged,
            'meta_key'            => self::event_date_key(),
            'orderby'             => 'meta_value_num',
            'order'               => 'ASC',
            'meta_query'          => $meta_query,
            'ignore_sticky_posts' => true,
        );

        if ( $site && 'all' !== $site && 'group' !== $site ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => REH_Post_Types::LOCATION_TAXONOMY,
                    'field'    => 'slug',
                    'terms'    => $site,
                ),
            );
        }

        return $args;
    }

    protected static function format_events( $posts ) {
        $items = array();

        foreach ( $posts as $post ) {
            $summary = self::get_field_value( $post->ID, 'summary', 'event_summary' );

            $items[] = array(
                'id'             => (int) $post->ID,
                'title'          => get_the_title( $post ),
                'permalink'      => get_permalink( $post ),
                'summary'        => $summary,
                'excerpt'        => $summary ?: ( has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 24 ) ),
                'image'          => get_the_post_thumbnail_url( $post, 'large' ),
                'event_date'     => self::get_field_value( $post->ID, 'event_date', self::event_date_key() ),
                'date_formatted' => self::format_date( self::get_field_value( $post->ID, 'event_date', self::event_date_key() ) ),
                'start_time'     => self::get_field_value( $post->ID, 'start_time', 'event_start_time' ),
                'button_url'     => self::normalise_link_value( self::get_field_value( $post->ID, 'button_url', 'event_button_url' ) ),
                'location'       => self::get_primary_location( $post->ID ),
            );
        }

        return $items;
    }

    protected static function get_primary_location( $post_id ) {
        $terms = get_the_terms( $post_id, REH_Post_Types::LOCATION_TAXONOMY );

        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            return null;
        }

        $term = array_shift( $terms );

        $label_colour      = '';
        $label_text_colour = '';

        if ( function_exists( 'get_field' ) ) {
            $label_colour      = get_field( 'label_colour', REH_Post_Types::LOCATION_TAXONOMY . '_' . $term->term_id );
            $label_text_colour = get_field( 'label_text_colour', REH_Post_Types::LOCATION_TAXONOMY . '_' . $term->term_id );
        }

        return array(
            'id'                => (int) $term->term_id,
            'name'              => $term->name,
            'slug'              => $term->slug,
            'label_colour'      => $label_colour,
            'label_text_colour' => $label_text_colour,
        );
    }

    protected static function get_field_value( $post_id, $logical_key, $default_meta_key ) {
        $mapped_key = apply_filters( 'reh/event_field_map', $default_meta_key, $logical_key, $post_id );

        if ( function_exists( 'get_field' ) ) {
            $value = get_field( $mapped_key, $post_id );
            if ( null !== $value && '' !== $value ) {
                return $value;
            }
        }

        return get_post_meta( $post_id, $mapped_key, true );
    }

    protected static function event_date_key() {
        return apply_filters( 'reh/event_date_meta_key', 'event_date' );
    }

    protected static function format_date( $date ) {
        if ( empty( $date ) ) {
            return '';
        }

        if ( preg_match( '/^\d{8}$/', (string) $date ) ) {
            $dt = DateTime::createFromFormat( 'Ymd', $date );

            if ( $dt instanceof DateTime ) {
                return $dt->format( 'l jS F' );
            }
        }

        return (string) $date;
    }

    protected static function normalise_link_value( $value ) {
        if ( is_array( $value ) && ! empty( $value['url'] ) ) {
            return $value['url'];
        }

        return $value;
    }
}
