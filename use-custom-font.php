<?php
/**
 * Plugin Name: Use Custom Font
 * Plugin URI:  https://example.com/use-custom-font
 * Description: Upload .woff2 font files and apply them to your site's frontend elements.
 * Version:     1.0.0
 * Author:      Developer
 * License:     GPL-2.0+
 * Text Domain: use-custom-font
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require 'includes/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/agskanchana-sam/custom-font',
	__FILE__,
	'use-custom-font'
);

define( 'UCF_VERSION', '1.0.0' );
define( 'UCF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'UCF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/* ------------------------------------------------------------------ */
/*  Activation – create DB table                                      */
/* ------------------------------------------------------------------ */
function ucf_activate() {
    global $wpdb;
    $table   = $wpdb->prefix . 'ucf_fonts';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        font_name   VARCHAR(255)  NOT NULL,
        font_slug   VARCHAR(255)  NOT NULL,
        font_weight VARCHAR(20)   NOT NULL DEFAULT '400',
        font_style  VARCHAR(20)   NOT NULL DEFAULT 'normal',
        file_url    TEXT          NOT NULL,
        file_path   TEXT          NOT NULL,
        created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    // Default option: element → font assignment map.
    if ( false === get_option( 'ucf_font_assignments' ) ) {
        add_option( 'ucf_font_assignments', array() );
    }
}
register_activation_hook( __FILE__, 'ucf_activate' );

/* ------------------------------------------------------------------ */
/*  Allow .woff2 uploads                                              */
/* ------------------------------------------------------------------ */
function ucf_allow_woff2_upload( $mimes ) {
    $mimes['woff2'] = 'font/woff2';
    return $mimes;
}
add_filter( 'upload_mimes', 'ucf_allow_woff2_upload' );

/**
 * WordPress sometimes fails the "real MIME" check for font files.
 * This filter fixes that so .woff2 uploads are not blocked.
 */
function ucf_fix_mime_type_detection( $data, $file, $filename, $mimes ) {
    $ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
    if ( 'woff2' === $ext ) {
        $data['ext']  = 'woff2';
        $data['type'] = 'font/woff2';
    }
    return $data;
}
add_filter( 'wp_check_filetype_and_ext', 'ucf_fix_mime_type_detection', 10, 4 );

/* ------------------------------------------------------------------ */
/*  Include sub-files                                                 */
/* ------------------------------------------------------------------ */
require_once UCF_PLUGIN_DIR . 'includes/class-ucf-admin.php';
require_once UCF_PLUGIN_DIR . 'includes/class-ucf-frontend.php';
