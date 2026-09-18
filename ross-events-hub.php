<?php
/**
 * Plugin Name: Ross Events Hub
 * Description: Centralised event management and REST API for Ross Hospitality Group multi-site event distribution.
 * Version: 1.2.0
 * Author: OpenAI
 * Text Domain: ross-events-hub
 * Update URI: https://github.com/crowncreativedigital/ross-events-hub
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'REH_VERSION', '1.2.0' );
define( 'REH_PLUGIN_FILE', __FILE__ );
define( 'REH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'REH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( file_exists( REH_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once REH_PLUGIN_DIR . 'vendor/autoload.php';
}

require_once REH_PLUGIN_DIR . 'includes/class-reh-post-types.php';
require_once REH_PLUGIN_DIR . 'includes/class-reh-api.php';

if ( class_exists( '\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory' ) ) {
    \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/crowncreativedigital/ross-events-hub',
        __FILE__,
        'ross-events-hub'
    )->setBranch( 'main' );
}

function reh_boot_plugin() {
    REH_Post_Types::init();
    REH_API::init();
}
add_action( 'plugins_loaded', 'reh_boot_plugin' );

register_activation_hook(
    __FILE__,
    function() {
        REH_Post_Types::register();
        flush_rewrite_rules();
    }
);

register_deactivation_hook(
    __FILE__,
    function() {
        flush_rewrite_rules();
    }
);
