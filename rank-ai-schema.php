<?php
/**
 * Plugin Name:  Rank AI Schema
 * Plugin URI:   https://github.com/webkeith/Rank-AI-Schema
 * Description:  JSON-LD Schema markup + full SEO analysis engine with per-page scoring, site-wide reports, and rich results compliance.
 * Version:      2.0.0
 * Author:       Keith Quinones
 * Author URI:   https://github.com/webkeith/
 * License:      GPL v2 or later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  rank-ai-schema
 * Domain Path:  /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Update URI:   https://github.com/webkeith/Rank-AI-Schema
 *
 * @package RankAISchema
 */


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ── Plugin constants ─────────────────────────────────── */
define( 'RAS_VERSION',     '2.0.0' );
define( 'RAS_DIR',         plugin_dir_path( __FILE__ ) );
define( 'RAS_URL',         plugin_dir_url( __FILE__ ) );
define( 'RAS_BASE',        plugin_basename( __FILE__ ) );
define( 'RAS_FILE',        __FILE__ );

/* ── GitHub update settings — EDIT THESE ─────────────── */
define( 'RAS_GITHUB_REPO',  'https://github.com/YOUR_GITHUB_USERNAME/rank-ai-schema' );
define( 'RAS_GITHUB_TOKEN', '' ); // Leave empty for public repos.
                                  // For private repos: paste a Personal Access Token here,
                                  // or better — store it via wp-config.php:
                                  //   define('RAS_GITHUB_TOKEN', 'ghp_xxxxxxxxxxxxxxxxxxxx');

/* ── Bootstrap Plugin Update Checker ─────────────────── */
require_once RAS_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$ras_updater = PucFactory::buildUpdateChecker(
    RAS_GITHUB_REPO,   // Your GitHub repo URL
    RAS_FILE,          // Full path to the main plugin file
    'rank-ai-schema'   // Plugin slug (must match the folder name exactly)
);

// Tell PUC to use GitHub Releases as the update source.
// Create a GitHub Release tagged "v2.0.1" → WordPress will see version 2.0.1.
$ras_updater->setBranch( 'main' );             // or 'master' — branch to track
$ras_updater->getVcsApi()->enableReleaseAssets(); // use Release assets (the ZIP) if present

// Private repo: attach a Personal Access Token.
if ( defined( 'RAS_GITHUB_TOKEN' ) && RAS_GITHUB_TOKEN ) {
    $ras_updater->setAuthentication( RAS_GITHUB_TOKEN );
}

/* ── Main plugin class (singleton) ───────────────────── */
final class Rank_AI_Schema {

    private static $instance = null;

    public static function get() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_files();
        add_action( 'admin_menu',            [ $this, 'menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
        add_filter( 'plugin_action_links_' . RAS_BASE, [ $this, 'action_links' ] );
    }

    private function load_files() {
        require_once RAS_DIR . 'includes/class-ras-settings.php';
        require_once RAS_DIR . 'includes/class-ras-frontend.php';
        require_once RAS_DIR . 'includes/class-ras-meta-box.php';
        require_once RAS_DIR . 'includes/class-ras-dashboard.php';
        require_once RAS_DIR . 'includes/class-ras-seo-analyzer.php';
        require_once RAS_DIR . 'includes/class-ras-seo-page.php';
        require_once RAS_DIR . 'includes/class-ras-woo-bridge.php';
    }

    /* ── Admin menus ──────────────────────────────────── */
    public function menus() {
        $icon = 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>'
        );

        add_menu_page(
            'Rank AI Schema', 'Rank AI Schema', 'manage_options',
            'rank-ai-schema', [ 'RAS_Dashboard', 'render' ], $icon, 58
        );
        add_submenu_page( 'rank-ai-schema', 'Schema Dashboard', 'Schema Dashboard',
            'manage_options', 'rank-ai-schema', [ 'RAS_Dashboard', 'render' ] );
        add_submenu_page( 'rank-ai-schema', 'SEO Analysis', 'SEO Analysis',
            'manage_options', 'rank-ai-schema-seo', [ 'RAS_SEO_Page', 'render' ] );
        add_submenu_page( 'rank-ai-schema', 'Global Settings', 'Global Settings',
            'manage_options', 'rank-ai-schema-settings', [ 'RAS_Settings', 'render' ] );
    }

    /* ── Admin assets ─────────────────────────────────── */
    public function assets( $hook ) {
        $pages = [
            'toplevel_page_rank-ai-schema',
            'rank-ai-schema_page_rank-ai-schema-seo',
            'rank-ai-schema_page_rank-ai-schema-settings',
            'post.php', 'post-new.php',
        ];
        if ( ! in_array( $hook, $pages, true ) ) {
            return;
        }

        wp_enqueue_style(
            'ras-admin', RAS_URL . 'admin/css/admin.css', [], RAS_VERSION
        );
        wp_enqueue_script(
            'ras-chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            [], '4.4.0', true
        );
        wp_enqueue_script(
            'ras-admin', RAS_URL . 'admin/js/admin.js',
            [ 'jquery', 'ras-chartjs' ], RAS_VERSION, true
        );
        wp_localize_script( 'ras-admin', 'RAS', [
            'ajax'    => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'ras_nonce' ),
            'siteUrl' => get_bloginfo( 'url' ),
        ] );
    }

    /* ── Plugin action links ──────────────────────────── */
    public function action_links( $links ) {
        return array_merge( [
            '<a href="' . admin_url( 'admin.php?page=rank-ai-schema-seo' ) . '">SEO Analysis</a>',
            '<a href="' . admin_url( 'admin.php?page=rank-ai-schema-settings' ) . '">Settings</a>',
        ], $links );
    }
}

/* ── Activation / deactivation ─────────────────────── */
register_activation_hook( __FILE__, function () {
    if ( ! get_option( 'ras_global_settings' ) ) {
        update_option( 'ras_global_settings', [
            'org_name'     => get_bloginfo( 'name' ),
            'org_url'      => get_bloginfo( 'url' ),
            'org_logo'     => '',
            'org_email'    => get_option( 'admin_email' ),
            'social_fb'    => '', 'social_tw' => '',
            'social_ig'    => '', 'social_li' => '', 'social_yt' => '',
            'breadcrumbs'  => '1',
            'sitelinks'    => '1',
            'organization' => '1',
        ] );
    }
    update_option( 'ras_version', RAS_VERSION );
    flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
    flush_rewrite_rules();
} );

/* ── Boot ──────────────────────────────────────────── */
add_action( 'plugins_loaded', function () {
    Rank_AI_Schema::get();
} );
