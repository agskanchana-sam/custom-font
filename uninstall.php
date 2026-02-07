<?php
/**
 * Uninstall handler – runs when the plugin is deleted via the admin UI.
 * Removes the custom DB table, uploaded font files, and options.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// 1. Delete uploaded font files.
$table = $wpdb->prefix . 'ucf_fonts';
$fonts = $wpdb->get_results( "SELECT file_path FROM {$table}" );
if ( $fonts ) {
    foreach ( $fonts as $font ) {
        if ( file_exists( $font->file_path ) ) {
            wp_delete_file( $font->file_path );
        }
    }
}

// Remove the upload directory if empty.
$upload_dir = wp_upload_dir();
$font_dir   = $upload_dir['basedir'] . '/ucf-fonts';
if ( is_dir( $font_dir ) ) {
    @rmdir( $font_dir );
}

// 2. Drop the custom table.
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

// 3. Delete options.
delete_option( 'ucf_font_assignments' );
