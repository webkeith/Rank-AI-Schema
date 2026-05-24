<?php
/**
 * Schema Dashboard — Rank AI Schema
 * @package RankAISchema
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RAS_Dashboard {

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $types = get_post_types( [ 'public' => true ], 'objects' );
        $total = 0; $with_schema = 0;
        foreach ( $types as $pt ) {
            $posts = get_posts( [ 'post_type' => $pt->name, 'post_status' => 'publish', 'posts_per_page' => -1 ] );
            foreach ( $posts as $p ) {
                $total++;
                $mode = get_post_meta( $p->ID, '_ras_schema_mode', true );
                if ( 'disabled' !== $mode ) { $with_schema++; }
            }
        }
        $pct = $total > 0 ? round( $with_schema / $total * 100 ) : 0;
        ?>
        <div class="ras-wrap">
        <div class="ras-page-header">
            <div class="ras-page-title"><span class="ras-logo-star">★</span> Rank AI Schema <span class="ras-page-sub">/ Schema Dashboard</span></div>
            <?php RAS_Settings::nav( 'dashboard' ); ?>
        </div>
        <div class="ras-page-body">
            <div class="ras-kpi-grid">
                <div class="ras-kpi ras-kpi-blue"><div class="ras-kpi-icon">📄</div><div><div class="ras-kpi-val"><?php echo $total; ?></div><div class="ras-kpi-lbl">Total Pages</div></div></div>
                <div class="ras-kpi ras-kpi-green"><div class="ras-kpi-icon">✅</div><div><div class="ras-kpi-val"><?php echo $with_schema; ?></div><div class="ras-kpi-lbl">With Schema</div></div></div>
                <div class="ras-kpi ras-kpi-yellow"><div class="ras-kpi-icon">⚠️</div><div><div class="ras-kpi-val"><?php echo $total - $with_schema; ?></div><div class="ras-kpi-lbl">Without Schema</div></div></div>
                <div class="ras-kpi ras-kpi-purple"><div class="ras-kpi-icon">📊</div><div><div class="ras-kpi-val"><?php echo $pct; ?>%</div><div class="ras-kpi-lbl">Schema Coverage</div></div></div>
            </div>
            <div class="ras-card">
                <div class="ras-card-head"><h3>🚀 Quick Actions</h3></div>
                <div class="ras-card-body ras-quick-links">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rank-ai-schema-seo' ) ); ?>" class="ras-btn ras-btn-primary">🔍 SEO Analysis Dashboard</a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rank-ai-schema-settings' ) ); ?>" class="ras-btn ras-btn-secondary">⚙️ Global Settings</a>
                    <a href="https://search.google.com/test/rich-results" target="_blank" class="ras-btn ras-btn-ghost">🔗 Rich Results Tester ↗</a>
                    <a href="https://schema.org/" target="_blank" class="ras-btn ras-btn-ghost">📖 Schema.org Docs ↗</a>
                </div>
            </div>
        </div></div>
        <?php
    }
}
