<?php
/**
 * SEO Analyzer Engine — Rank AI Schema
 * 23 checks across 5 categories. Scores 0–100.
 * @package RankAISchema
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RAS_SEO_Analyzer {

    const OPTION_SUMMARY = 'ras_seo_summary';

    /* ─── Check definitions ─────────────────────────────────────────────
       Each: [ category, label, weight, description, suggestion ]        */
    public static function checks() {
        return [
            /* Content */
            'word_count'     => [ 'content', 'Word Count',         10, '300+ words is recommended for ranking.',         'Aim for at least 300 words of useful content.' ],
            'has_h2'         => [ 'content', 'H2 Subheadings',      5, '2+ H2 headings improve content structure.',     'Break content into sections using H2 headings.' ],
            'single_h1'      => [ 'content', 'Single H1',           4, 'Only one H1 (page title). No extras in body.',  'Remove extra H1 tags from your page content.' ],
            'images_alt'     => [ 'content', 'Image Alt Text',      7, 'All images need descriptive alt attributes.',    'Add alt text to every image on this page.' ],
            'internal_links' => [ 'content', 'Internal Links',      5, '2+ internal links aid crawling and PageRank.',   'Add links to other relevant pages on your site.' ],
            'external_links' => [ 'content', 'External Links',      3, 'Link out to authoritative sources.',             'Add at least one external link to a trusted source.' ],
            'featured_img'   => [ 'content', 'Featured Image',      6, 'Featured images improve CTR and social sharing.','Set a featured image in the post editor.' ],
            'readability'    => [ 'content', 'Readability',         4, 'Short sentences (≤20 words) aid comprehension.', 'Shorten sentences — aim for 20 words or fewer.' ],
            /* Title & Meta */
            'title_length'   => [ 'meta', 'Title Length',          10, 'Optimal title is 50–60 characters.',            'Adjust title length to 50–60 characters.' ],
            'meta_desc'      => [ 'meta', 'Meta Description',       9, 'A meta description improves search CTR.',        'Add a meta description in the SEO tab.' ],
            'meta_desc_len'  => [ 'meta', 'Meta Desc. Length',      5, '120–160 characters is the sweet spot.',         'Adjust meta description to 120–160 characters.' ],
            'url_length'     => [ 'meta', 'URL Length',             3, 'Short, descriptive URLs perform better.',        'Shorten your URL slug.' ],
            /* Schema */
            'has_schema'     => [ 'schema', 'Schema Markup',        9, 'Structured data enables Google rich results.',   'Enable schema in the Schema tab.' ],
            'schema_type'    => [ 'schema', 'Schema Type',          4, 'A specific type (Article, FAQ…) is best.',      'Set a specific schema type in the Schema tab.' ],
            /* Social */
            'og_title'       => [ 'social', 'OG Title',             3, 'Controls title in social share cards.',          'Set an OG title in the SEO tab.' ],
            'og_desc'        => [ 'social', 'OG Description',       3, 'Controls description in social share cards.',    'Set an OG description in the SEO tab.' ],
            'og_image'       => [ 'social', 'Social Image',         5, 'An image is shown when shared on social media.', 'Set an OG image or featured image.' ],
            /* Keyword (only when focus keyword is set) */
            'kw_in_title'    => [ 'keyword', 'Keyword in Title',    9, 'Focus keyword in the title is a strong signal.', 'Include your focus keyword in the page title.' ],
            'kw_in_meta'     => [ 'keyword', 'Keyword in Meta',     6, 'Keyword in meta description boosts relevance.',  'Include focus keyword in your meta description.' ],
            'kw_in_intro'    => [ 'keyword', 'Keyword in Intro',    5, 'Keyword in the first 100 words signals topic.',  'Use the keyword in your opening paragraph.' ],
            'kw_density'     => [ 'keyword', 'Keyword Density',     4, 'Optimal density: 0.5%–2.5%.',                   'Adjust keyword usage — aim for 0.5–2.5%.' ],
            'kw_in_url'      => [ 'keyword', 'Keyword in URL',      3, 'Keyword in the slug is a positive signal.',      'Include the keyword in your URL slug.' ],
        ];
    }

    /* ─── Analyze a single post ─────────────────────────────────────── */
    public static function analyze( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) { return []; }

        $title    = html_entity_decode( get_the_title( $post_id ) );
        $url      = get_permalink( $post_id );
        $slug     = trim( str_replace( get_bloginfo( 'url' ), '', $url ), '/' );
        $content  = $post->post_content;
        $txt      = wp_strip_all_tags( strip_shortcodes( $content ) );
        $words    = self::word_count( $txt );
        $kw       = strtolower( trim( (string) get_post_meta( $post_id, '_ras_focus_kw', true ) ) );
        $meta_d   = trim( (string) get_post_meta( $post_id, '_ras_meta_desc', true ) );
        $og_t     = get_post_meta( $post_id, '_ras_og_title', true );
        $og_d     = get_post_meta( $post_id, '_ras_og_desc',  true );
        $og_i     = get_post_meta( $post_id, '_ras_og_image', true );
        $s_mode   = get_post_meta( $post_id, '_ras_schema_mode', true );
        $s_type   = get_post_meta( $post_id, '_ras_schema_type', true );
        $site_url = get_bloginfo( 'url' );

        /* Image audit */
        preg_match_all( '/<img[^>]+>/i', $content, $img_m );
        $total_imgs   = count( $img_m[0] );
        $missing_alts = 0;
        foreach ( $img_m[0] as $img ) {
            if ( ! preg_match( '/alt=["\'][^"\']+["\']/i', $img ) ) { $missing_alts++; }
        }

        /* Link audit */
        preg_match_all( '/href=["\']([^"\']+)["\']/i', $content, $link_m );
        $internal = 0; $external = 0;
        foreach ( $link_m[1] as $href ) {
            if ( strpos( $href, $site_url ) === 0 || ( strpos( $href, '/' ) === 0 && strpos( $href, '//' ) !== 0 ) ) {
                $internal++;
            } elseif ( preg_match( '/^https?:\/\//i', $href ) ) {
                $external++;
            }
        }

        /* Intro text (first 100 words) */
        $intro = implode( ' ', array_slice( explode( ' ', $txt ), 0, 100 ) );
        $h2s   = substr_count( strtolower( $content ), '<h2' );
        $h1s   = substr_count( strtolower( $content ), '<h1' ); // extra h1s in body
        $avg_s = self::avg_sentence( $txt );
        $tl    = mb_strlen( $title );
        $ml    = mb_strlen( $meta_d );
        $ul    = strlen( $slug );
        $has_thumb = has_post_thumbnail( $post_id );
        $has_schema = 'disabled' !== $s_mode;
        $has_type   = ( 'override' === $s_mode && ! empty( $s_type ) );
        $og_img_final = $og_i ?: ( $has_thumb ? wp_get_attachment_url( get_post_thumbnail_id( $post_id ) ) : '' );

        /* Build results */
        $checks = self::checks();
        $results = [];
        $tw = 0; $ew = 0;

        /* ── Content ── */
        $results['word_count'] = self::r(
            $words >= 300 ? 'pass' : ( $words >= 150 ? 'warn' : 'fail' ),
            $checks['word_count'], "$words words",
            $words < 300 ? $checks['word_count'][4] : ''
        );
        $results['has_h2'] = self::r(
            $h2s >= 2 ? 'pass' : ( $h2s >= 1 ? 'warn' : 'fail' ),
            $checks['has_h2'], "$h2s H2 heading" . ( $h2s !== 1 ? 's' : '' ),
            $h2s < 2 ? $checks['has_h2'][4] : ''
        );
        $results['single_h1'] = self::r(
            $h1s === 0 ? 'pass' : ( $h1s === 1 ? 'warn' : 'fail' ),
            $checks['single_h1'], $h1s === 0 ? 'No extra H1 in body — correct' : "$h1s extra H1 tag(s) in body",
            $h1s > 0 ? $checks['single_h1'][4] : ''
        );
        $results['images_alt'] = self::r(
            $total_imgs === 0 ? 'warn' : ( $missing_alts === 0 ? 'pass' : ( $missing_alts <= 1 ? 'warn' : 'fail' ) ),
            $checks['images_alt'],
            $total_imgs === 0 ? 'No images found' : "$missing_alts of $total_imgs images missing alt",
            $missing_alts > 0 ? $checks['images_alt'][4] : ''
        );
        $results['internal_links'] = self::r(
            $internal >= 3 ? 'pass' : ( $internal >= 1 ? 'warn' : 'fail' ),
            $checks['internal_links'], "$internal internal link" . ( $internal !== 1 ? 's' : '' ),
            $internal < 2 ? $checks['internal_links'][4] : ''
        );
        $results['external_links'] = self::r(
            $external >= 1 ? 'pass' : 'warn',
            $checks['external_links'], "$external external link" . ( $external !== 1 ? 's' : '' ),
            $external === 0 ? $checks['external_links'][4] : ''
        );
        $results['featured_img'] = self::r(
            $has_thumb ? 'pass' : 'fail',
            $checks['featured_img'], $has_thumb ? 'Featured image set' : 'No featured image',
            ! $has_thumb ? $checks['featured_img'][4] : ''
        );
        $results['readability'] = self::r(
            $avg_s <= 20 ? 'pass' : ( $avg_s <= 30 ? 'warn' : 'fail' ),
            $checks['readability'], "Avg. sentence: $avg_s words",
            $avg_s > 20 ? $checks['readability'][4] : ''
        );

        /* ── Meta ── */
        $results['title_length'] = self::r(
            ( $tl >= 50 && $tl <= 60 ) ? 'pass' : ( ( $tl >= 35 && $tl <= 70 ) ? 'warn' : 'fail' ),
            $checks['title_length'], "$tl characters (50–60 optimal)",
            ( $tl < 50 || $tl > 60 ) ? $checks['title_length'][4] : ''
        );
        $results['meta_desc'] = self::r(
            $meta_d ? 'pass' : 'fail',
            $checks['meta_desc'], $meta_d ? 'Set' : 'Missing',
            ! $meta_d ? $checks['meta_desc'][4] : ''
        );
        $results['meta_desc_len'] = self::r(
            ( $ml >= 120 && $ml <= 160 ) ? 'pass' : ( $ml >= 80 ? 'warn' : 'fail' ),
            $checks['meta_desc_len'], $meta_d ? "$ml characters (120–160 optimal)" : 'Not set',
            ( $ml < 120 || $ml > 160 ) && $meta_d ? $checks['meta_desc_len'][4] : ''
        );
        $results['url_length'] = self::r(
            $ul <= 60 ? 'pass' : ( $ul <= 90 ? 'warn' : 'fail' ),
            $checks['url_length'], "$ul characters",
            $ul > 60 ? $checks['url_length'][4] : ''
        );

        /* ── Schema ── */
        $results['has_schema'] = self::r(
            $has_schema ? 'pass' : 'fail',
            $checks['has_schema'], $has_schema ? 'Enabled' : 'Disabled for this page',
            ! $has_schema ? $checks['has_schema'][4] : ''
        );
        $results['schema_type'] = self::r(
            $has_type ? 'pass' : ( $has_schema ? 'warn' : 'fail' ),
            $checks['schema_type'], $has_type ? $s_type : ( $has_schema ? 'Using global default' : 'Schema disabled' ),
            ! $has_type ? $checks['schema_type'][4] : ''
        );

        /* ── Social ── */
        $results['og_title'] = self::r( $og_t ? 'pass' : 'warn', $checks['og_title'], $og_t ? 'Set' : 'Using page title', ! $og_t ? $checks['og_title'][4] : '' );
        $results['og_desc']  = self::r( $og_d ? 'pass' : 'warn', $checks['og_desc'],  $og_d ? 'Set' : 'Using meta desc',  ! $og_d ? $checks['og_desc'][4] : '' );
        $results['og_image'] = self::r( $og_img_final ? 'pass' : 'fail', $checks['og_image'], $og_img_final ? 'Set' : 'No image found', ! $og_img_final ? $checks['og_image'][4] : '' );

        /* ── Keyword (only if set) ── */
        if ( $kw ) {
            $kw_count   = substr_count( strtolower( $txt ), $kw );
            $kw_density = $words > 0 ? round( $kw_count / $words * 100, 1 ) : 0;
            $kw_url     = mb_stripos( $slug, str_replace( ' ', '-', $kw ) ) !== false || mb_stripos( $slug, str_replace( ' ', '', $kw ) ) !== false;

            $results['kw_in_title'] = self::r(
                mb_stripos( $title, $kw ) !== false ? 'pass' : 'fail',
                $checks['kw_in_title'], mb_stripos( $title, $kw ) !== false ? 'Found in title' : 'Not in title',
                mb_stripos( $title, $kw ) === false ? $checks['kw_in_title'][4] : ''
            );
            $results['kw_in_meta'] = self::r(
                $meta_d && mb_stripos( $meta_d, $kw ) !== false ? 'pass' : ( $meta_d ? 'warn' : 'fail' ),
                $checks['kw_in_meta'], $meta_d && mb_stripos( $meta_d, $kw ) !== false ? 'Found in meta description' : 'Not found',
                $checks['kw_in_meta'][4]
            );
            $results['kw_in_intro'] = self::r(
                mb_stripos( $intro, $kw ) !== false ? 'pass' : 'warn',
                $checks['kw_in_intro'], mb_stripos( $intro, $kw ) !== false ? 'Found in first paragraph' : 'Not in intro',
                mb_stripos( $intro, $kw ) === false ? $checks['kw_in_intro'][4] : ''
            );
            $results['kw_density'] = self::r(
                ( $kw_density >= 0.5 && $kw_density <= 2.5 ) ? 'pass' : ( $kw_density > 0 ? 'warn' : 'fail' ),
                $checks['kw_density'], "{$kw_density}% ({$kw_count} times)",
                ( $kw_density < 0.5 || $kw_density > 2.5 ) ? $checks['kw_density'][4] : ''
            );
            $results['kw_in_url'] = self::r(
                $kw_url ? 'pass' : 'warn', $checks['kw_in_url'],
                $kw_url ? 'Found in URL' : 'Not in URL slug',
                ! $kw_url ? $checks['kw_in_url'][4] : ''
            );
        }

        /* ── Score ── */
        foreach ( $results as $id => $r ) {
            if ( ! isset( $checks[ $id ] ) ) { continue; }
            $w   = $checks[ $id ][2];
            $tw += $w;
            if ( $r['status'] === 'pass' )     { $ew += $w; }
            elseif ( $r['status'] === 'warn' ) { $ew += $w * 0.5; }
        }
        $score = $tw > 0 ? (int) round( $ew / $tw * 100 ) : 0;

        /* ── Persist ── */
        update_post_meta( $post_id, '_ras_seo_score',   $score );
        update_post_meta( $post_id, '_ras_seo_results', wp_json_encode( $results ) );
        update_post_meta( $post_id, '_ras_seo_ts',      time() );

        return [
            'score'   => $score,
            'label'   => self::label( $score ),
            'results' => $results,
            'pass'    => count( array_filter( $results, fn($r) => $r['status'] === 'pass' ) ),
            'warn'    => count( array_filter( $results, fn($r) => $r['status'] === 'warn' ) ),
            'fail'    => count( array_filter( $results, fn($r) => $r['status'] === 'fail' ) ),
        ];
    }

    /* ─── Analyze all published posts ─────────────────────────────── */
    public static function analyze_all() {
        $ids = get_posts( [
            'post_type'      => array_values( get_post_types( [ 'public' => true ], 'names' ) ),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );

        $summary = [ 'total' => 0, 'excellent' => 0, 'good' => 0, 'needs_work' => 0, 'poor' => 0, 'avg' => 0, 'ts' => time() ];
        $total_score = 0;

        foreach ( $ids as $id ) {
            $r = self::analyze( $id );
            if ( empty( $r ) ) { continue; }
            $summary['total']++;
            $total_score += $r['score'];
            $summary[ $r['label'] ]++;
        }

        $summary['avg'] = $summary['total'] > 0 ? (int) round( $total_score / $summary['total'] ) : 0;
        update_option( self::OPTION_SUMMARY, $summary );
        return $summary;
    }

    /* ─── Helpers ───────────────────────────────────────────────────── */
    public static function label( $score ) {
        if ( $score >= 80 ) { return 'excellent'; }
        if ( $score >= 60 ) { return 'good'; }
        if ( $score >= 40 ) { return 'needs_work'; }
        return 'poor';
    }

    public static function summary() {
        return wp_parse_args( (array) get_option( self::OPTION_SUMMARY, [] ), [
            'total' => 0, 'excellent' => 0, 'good' => 0, 'needs_work' => 0, 'poor' => 0, 'avg' => 0, 'ts' => 0,
        ] );
    }

    public static function all_posts() {
        $types = get_post_types( [ 'public' => true ], 'objects' );
        $rows  = [];
        foreach ( $types as $pt ) {
            foreach ( get_posts( [ 'post_type' => $pt->name, 'post_status' => 'publish', 'posts_per_page' => -1 ] ) as $p ) {
                $score = get_post_meta( $p->ID, '_ras_seo_score', true );
                $ts    = (int) get_post_meta( $p->ID, '_ras_seo_ts', true );
                $rows[] = [
                    'id'       => $p->ID,
                    'title'    => get_the_title( $p->ID ),
                    'view'     => get_permalink( $p->ID ),
                    'edit'     => get_edit_post_link( $p->ID, 'raw' ),
                    'type'     => $pt->label,
                    'score'    => $score !== '' ? (int) $score : null,
                    'label'    => $score !== '' ? self::label( (int) $score ) : 'unanalyzed',
                    'analyzed' => $ts,
                ];
            }
        }
        usort( $rows, fn( $a, $b ) => ( $a['score'] ?? 999 ) - ( $b['score'] ?? 999 ) );
        return $rows;
    }

    private static function r( $status, $def, $message, $fix = '' ) {
        return [ 'status' => $status, 'cat' => $def[0], 'label' => $def[1], 'message' => $message, 'fix' => $fix ];
    }
    private static function word_count( $t ) { $t = preg_replace('/\s+/', ' ', trim($t)); return $t ? str_word_count( $t ) : 0; }
    private static function avg_sentence( $t ) {
        $s = preg_split( '/[.!?]+/', $t, -1, PREG_SPLIT_NO_EMPTY );
        if ( ! $s ) { return 0; }
        return (int) round( array_sum( array_map( fn($x) => self::word_count($x), $s ) ) / count($s) );
    }

    /* ─── AJAX ──────────────────────────────────────────────────────── */
    public static function init() {
        add_action( 'wp_ajax_ras_analyze_all',  [ __CLASS__, 'ajax_all' ] );
        add_action( 'wp_ajax_ras_analyze_post', [ __CLASS__, 'ajax_post' ] );
    }

    public static function ajax_all() {
        check_ajax_referer( 'ras_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }
        $summary = self::analyze_all();
        wp_send_json_success( [ 'summary' => $summary, 'posts' => self::all_posts() ] );
    }

    public static function ajax_post() {
        check_ajax_referer( 'ras_nonce', 'nonce' );
        $id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $id || ! current_user_can( 'edit_post', $id ) ) { wp_send_json_error(); }
        wp_send_json_success( self::analyze( $id ) );
    }
}

RAS_SEO_Analyzer::init();
