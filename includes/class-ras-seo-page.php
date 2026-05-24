<?php
/**
 * SEO Analysis Page — Rank AI Schema
 * Full dashboard: site score gauge, distribution chart, run-all, per-page dropdown, all-pages table.
 * @package RankAISchema
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RAS_SEO_Page {

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }

        $summary   = RAS_SEO_Analyzer::summary();
        $all_posts = RAS_SEO_Analyzer::all_posts();
        $total     = count( $all_posts );
        $analyzed  = count( array_filter( $all_posts, fn($p) => $p['score'] !== null ) );
        $last_run  = $summary['ts'] ? human_time_diff( $summary['ts'] ) . ' ago' : 'Never';
        $avg       = $summary['avg'];
        ?>
        <div class="ras-wrap">
        <div class="ras-page-header">
            <div class="ras-page-title"><span class="ras-logo-star">★</span> Rank AI Schema <span class="ras-page-sub">/ SEO Analysis</span></div>
            <?php RAS_Settings::nav( 'seo' ); ?>
        </div>
        <div class="ras-page-body">

            <!-- ══ ROW 1: Score + Distribution + Run ═══════════════════ -->
            <div class="ras-seo-top">

                <!-- Site Score Gauge -->
                <div class="ras-card ras-gauge-card">
                    <div class="ras-card-head">
                        <h3>📈 Site SEO Score</h3>
                        <p>Average across <?php echo $analyzed; ?> analyzed pages</p>
                    </div>
                    <div class="ras-card-body ras-gauge-body">
                        <div class="ras-gauge-wrap">
                            <canvas id="ras-gauge" width="200" height="200"></canvas>
                            <div class="ras-gauge-center">
                                <span class="ras-gauge-num"><?php echo $avg; ?></span>
                                <span class="ras-gauge-lbl"><?php echo self::label_text( RAS_SEO_Analyzer::label( $avg ) ); ?></span>
                            </div>
                        </div>
                        <div class="ras-gauge-legend">
                            <?php foreach ( [ 'excellent' => [ 'Excellent', '#10b981' ], 'good' => [ 'Good', '#3b82f6' ], 'needs_work' => [ 'Needs Work', '#f59e0b' ], 'poor' => [ 'Poor', '#ef4444' ] ] as $k => [ $lbl, $col ] ) : ?>
                                <div class="ras-legend-row">
                                    <span class="ras-legend-dot" style="background:<?php echo $col; ?>"></span>
                                    <span class="ras-legend-lbl"><?php echo $lbl; ?></span>
                                    <span class="ras-legend-val"><?php echo $summary[ $k ]; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Distribution Donut -->
                <div class="ras-card ras-dist-card">
                    <div class="ras-card-head"><h3>📊 Score Distribution</h3><p>Pages by SEO grade</p></div>
                    <div class="ras-card-body ras-dist-body">
                        <canvas id="ras-dist" width="240" height="240"></canvas>
                        <div class="ras-dist-legend">
                            <?php
                            $cats = [
                                'excellent'  => [ 'Excellent (80–100)',  '#10b981', $summary['excellent'] ],
                                'good'       => [ 'Good (60–79)',        '#3b82f6', $summary['good'] ],
                                'needs_work' => [ 'Needs Work (40–59)', '#f59e0b', $summary['needs_work'] ],
                                'poor'       => [ 'Poor (0–39)',         '#ef4444', $summary['poor'] ],
                            ];
                            foreach ( $cats as [ $lbl, $col, $cnt ] ) : ?>
                                <div class="ras-dl-row">
                                    <span class="ras-dl-dot" style="background:<?php echo $col; ?>"></span>
                                    <span><?php echo $lbl; ?></span>
                                    <strong><?php echo $cnt; ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Run Analysis -->
                <div class="ras-card ras-run-card">
                    <div class="ras-card-head"><h3>⚡ Run Analysis</h3><p>Scan all pages to refresh scores</p></div>
                    <div class="ras-card-body">
                        <div class="ras-run-stats">
                            <div class="ras-rs"><span class="ras-rs-num" id="ras-stat-total"><?php echo $total; ?></span><span>Total</span></div>
                            <div class="ras-rs"><span class="ras-rs-num ras-col-blue" id="ras-stat-analyzed"><?php echo $analyzed; ?></span><span>Analyzed</span></div>
                            <div class="ras-rs"><span class="ras-rs-num ras-col-yellow" id="ras-stat-pending"><?php echo $total - $analyzed; ?></span><span>Pending</span></div>
                        </div>
                        <div id="ras-prog-wrap" style="display:none; margin:12px 0;">
                            <div class="ras-prog-bar"><div class="ras-prog-fill" id="ras-prog-fill"></div></div>
                            <p class="ras-prog-txt" id="ras-prog-txt">Analyzing…</p>
                        </div>
                        <button id="ras-run-btn" type="button" class="ras-btn ras-btn-primary ras-btn-full ras-btn-lg">
                            ▶ Analyze Entire Site
                        </button>
                        <p class="ras-hint" style="text-align:center; margin-top:10px;">
                            Last run: <strong id="ras-last-run"><?php echo esc_html( $last_run ); ?></strong>
                        </p>
                    </div>
                </div>

            </div><!-- /.ras-seo-top -->

            <!-- ══ ROW 2: Per-Page Analyzer ═════════════════════════════ -->
            <div class="ras-card">
                <div class="ras-card-head ras-flex-head">
                    <div><h3>🔍 Page SEO Analyzer</h3><p>Pick any page to see its full SEO report with checks, scores, and fix suggestions.</p></div>
                </div>
                <div class="ras-card-body">
                    <!-- Selector row -->
                    <div class="ras-selector-row">
                        <select id="ras-page-select" class="ras-page-select">
                            <option value="">— Select a page —</option>
                            <?php foreach ( $all_posts as $p ) : ?>
                                <option value="<?php echo (int) $p['id']; ?>"
                                    data-edit="<?php echo esc_url( $p['edit'] ); ?>"
                                    data-view="<?php echo esc_url( $p['view'] ); ?>"
                                    data-score="<?php echo $p['score'] !== null ? (int) $p['score'] : ''; ?>"
                                    data-label="<?php echo esc_attr( $p['label'] ); ?>">
                                    <?php echo esc_html( $p['title'] ); ?>
                                    <?php echo $p['score'] !== null ? '(' . $p['score'] . '/100)' : '(not analyzed)'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button id="ras-analyze-btn" type="button" class="ras-btn ras-btn-primary">🔍 Analyze</button>
                        <a id="ras-edit-link" href="#" target="_blank" class="ras-btn ras-btn-secondary" style="display:none;">✏️ Edit</a>
                        <a id="ras-view-link" href="#" target="_blank" class="ras-btn ras-btn-ghost" style="display:none;">🔗 View</a>
                    </div>

                    <!-- Loading -->
                    <div id="ras-analyzing" style="display:none;" class="ras-loading-box">
                        <div class="ras-spinner"></div><p>Analyzing page…</p>
                    </div>

                    <!-- Results panel -->
                    <div id="ras-results" style="display:none; margin-top:20px;">

                        <!-- Score header -->
                        <div class="ras-result-header">
                            <h4 id="ras-result-title"></h4>
                            <div class="ras-result-meta">
                                <div class="ras-score-circle" id="ras-score-circle"><span id="ras-score-num">0</span></div>
                                <div class="ras-score-pswf">
                                    <div class="ras-pswf-row ras-pswf-pass"><span id="ras-cnt-pass">0</span> ✓ Passed</div>
                                    <div class="ras-pswf-row ras-pswf-warn"><span id="ras-cnt-warn">0</span> ⚠ Warnings</div>
                                    <div class="ras-pswf-row ras-pswf-fail"><span id="ras-cnt-fail">0</span> ✗ Failed</div>
                                </div>
                                <div class="ras-score-bar-wrap">
                                    <div class="ras-score-bar"><div class="ras-score-fill" id="ras-score-fill"></div></div>
                                    <span id="ras-score-badge" class="ras-score-badge"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Category check cards -->
                        <div id="ras-check-grid" class="ras-check-grid"></div>

                    </div><!-- /#ras-results -->

                    <div id="ras-empty-hint" class="ras-empty-hint">
                        <span>🔎</span><p>Select a page above and click Analyze to see its full SEO report.</p>
                    </div>
                </div>
            </div>

            <!-- ══ ROW 3: All Pages Table ════════════════════════════════ -->
            <div class="ras-card">
                <div class="ras-card-head ras-flex-head">
                    <div><h3>📋 All Pages — SEO Scores</h3><p>Click a row to load its analysis. Run site analysis to populate all scores.</p></div>
                    <div class="ras-table-filters" id="ras-filters">
                        <?php foreach ( [ 'all' => 'All', 'poor' => 'Poor', 'needs_work' => 'Needs Work', 'good' => 'Good', 'excellent' => 'Excellent', 'unanalyzed' => 'Not Analyzed' ] as $k => $lbl ) : ?>
                            <button class="ras-filter-btn <?php echo $k === 'all' ? 'active' : ''; ?>" data-filter="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $lbl ); ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="ras-card-body ras-no-pad">
                    <div class="ras-table-search-bar">
                        <input type="text" id="ras-table-search" placeholder="Search pages…">
                    </div>
                    <table class="ras-table" id="ras-pages-table">
                        <thead>
                            <tr>
                                <th>Page</th>
                                <th>Type</th>
                                <th>Score</th>
                                <th>Status</th>
                                <th>Last Analyzed</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $all_posts as $p ) : ?>
                            <tr class="ras-tr" data-label="<?php echo esc_attr( $p['label'] ); ?>" data-id="<?php echo (int) $p['id']; ?>">
                                <td>
                                    <a href="#" class="ras-tbl-title ras-open-row"
                                        data-id="<?php echo (int) $p['id']; ?>"
                                        data-edit="<?php echo esc_url( $p['edit'] ); ?>"
                                        data-view="<?php echo esc_url( $p['view'] ); ?>">
                                        <?php echo esc_html( $p['title'] ); ?>
                                    </a>
                                </td>
                                <td><span class="ras-type-badge"><?php echo esc_html( $p['type'] ); ?></span></td>
                                <td>
                                    <?php if ( $p['score'] !== null ) : ?>
                                        <div class="ras-mini-bar-wrap">
                                            <div class="ras-mini-bar">
                                                <div class="ras-mini-fill ras-fill-<?php echo esc_attr( $p['label'] ); ?>" style="width:<?php echo (int) $p['score']; ?>%"></div>
                                            </div>
                                            <strong><?php echo (int) $p['score']; ?></strong>
                                        </div>
                                    <?php else : ?>
                                        <span class="ras-muted-dash">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="ras-status-badge ras-sb-<?php echo esc_attr( $p['label'] ); ?>"><?php echo esc_html( self::badge_text( $p['label'] ) ); ?></span></td>
                                <td class="ras-ts-cell"><?php echo $p['analyzed'] ? esc_html( human_time_diff( $p['analyzed'] ) . ' ago' ) : '—'; ?></td>
                                <td class="ras-actions-cell">
                                    <button class="ras-action-icon ras-open-row" title="Analyze"
                                        data-id="<?php echo (int) $p['id']; ?>"
                                        data-edit="<?php echo esc_url( $p['edit'] ); ?>"
                                        data-view="<?php echo esc_url( $p['view'] ); ?>">🔍</button>
                                    <a href="<?php echo esc_url( $p['edit'] ); ?>" class="ras-action-icon" title="Edit">✏️</a>
                                    <a href="<?php echo esc_url( $p['view'] ); ?>" class="ras-action-icon" title="View" target="_blank">🔗</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /.ras-page-body -->
        </div><!-- /.ras-wrap -->

        <!-- JS data -->
        <script>
        window.RAS_SEO = {
            summary: <?php echo wp_json_encode( $summary ); ?>,
            posts: <?php echo wp_json_encode( array_map( fn($p) => [
                'id'    => $p['id'], 'title' => $p['title'],
                'score' => $p['score'], 'label' => $p['label'],
                'edit'  => $p['edit'], 'view' => $p['view'], 'analyzed' => $p['analyzed'],
            ], $all_posts ) ); ?>,
            checks: <?php echo wp_json_encode( RAS_SEO_Analyzer::checks() ); ?>,
            cats: {
                content: 'Content', meta: 'Title & Meta',
                schema: 'Schema Markup', social: 'Social / OG', keyword: 'Focus Keyword'
            },
            catIcons: { content:'📝', meta:'🏷️', schema:'⬡', social:'📣', keyword:'🎯' }
        };
        </script>
        <?php
    }

    private static function label_text( $l ) {
        return [ 'excellent' => 'Excellent', 'good' => 'Good', 'needs_work' => 'Needs Work', 'poor' => 'Poor' ][ $l ] ?? 'N/A';
    }

    private static function badge_text( $l ) {
        return [ 'excellent' => '★ Excellent', 'good' => '▲ Good', 'needs_work' => '⚠ Needs Work',
                 'poor' => '✗ Poor', 'unanalyzed' => '○ Not Analyzed' ][ $l ] ?? $l;
    }
}
