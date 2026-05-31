<?php
/**
 * Uninstall — Rank AI Schema
 *
 * Called by WordPress when the plugin is deleted (not just deactivated).
 * Removes all plugin data: options, post meta, scheduled events.
 *
 * @package RankAISchema
 */

// Abort if not called from WP uninstall context.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Load just enough to call the upgrader's clean uninstall.
if ( ! defined( 'RAS_DIR' ) ) {
    define( 'RAS_DIR', plugin_dir_path( __FILE__ ) );
}

// Inline clean-up (avoids loading the full plugin stack).
global $wpdb;

// 1. Plugin options.
$options = [
    'ras_global_settings',
    'ras_version',
    'ras_db_version',
    'ras_upgrade_history',
    'ras_seo_summary',
];
foreach ( $options as $opt ) {
    delete_option( $opt );
}

// 2. All _ras_ post meta (SEO scores, schema settings, SEO fields).
$wpdb->query(
    "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_ras_%'"
);

// 3. Clear any scheduled cron jobs (none currently, but future-proofed).
wp_clear_scheduled_hook( 'ras_scheduled_analysis' );
