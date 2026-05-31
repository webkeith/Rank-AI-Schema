<?php
/**
 * Meta Box — Schema + SEO tabs.
 * Two-tab interface per post/page: Schema override | SEO fields.
 * @package RankAISchema
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RAS_Meta_Box {

    public static function init() {
        add_action( 'add_meta_boxes', [ __CLASS__, 'register' ] );
        add_action( 'save_post',      [ __CLASS__, 'save' ], 10, 2 );
    }

    public static function register() {
        $types = get_post_types( [ 'public' => true ], 'names' );
        foreach ( $types as $t ) {
            add_meta_box( 'ras-meta-box', '★ Rank AI Schema', [ __CLASS__, 'render' ],
                $t, 'normal', 'high' );
        }
    }

    /* ─── RENDER ───────────────────────────────────────────────────── */
    public static function render( $post ) {
        wp_nonce_field( 'ras_mb_save', 'ras_mb_nonce' );

        $mode    = get_post_meta( $post->ID, '_ras_schema_mode', true ) ?: 'global';
        $type    = get_post_meta( $post->ID, '_ras_schema_type', true ) ?: 'Article';

        /* SEO fields */
        $kw        = get_post_meta( $post->ID, '_ras_focus_kw',  true );
        $meta_desc = get_post_meta( $post->ID, '_ras_meta_desc', true );
        $og_title  = get_post_meta( $post->ID, '_ras_og_title',  true );
        $og_desc   = get_post_meta( $post->ID, '_ras_og_desc',   true );
        $og_img    = get_post_meta( $post->ID, '_ras_og_image',  true );
        $noindex   = get_post_meta( $post->ID, '_ras_noindex',   true );
        $seo_score = get_post_meta( $post->ID, '_ras_seo_score', true );
        $seo_ts    = get_post_meta( $post->ID, '_ras_seo_ts',    true );

        /* Schema-specific fields */
        $author_name  = get_post_meta( $post->ID, '_ras_author_name',  true );
        $author_url   = get_post_meta( $post->ID, '_ras_author_url',   true );
        $faq_items    = json_decode( stripslashes( (string) get_post_meta( $post->ID, '_ras_faq_items', true ) ), true ) ?: [ [ 'q' => '', 'a' => '' ] ];
        $steps        = json_decode( stripslashes( (string) get_post_meta( $post->ID, '_ras_steps', true ) ), true ) ?: [ [ 'name' => '', 'text' => '' ] ];
        $total_time   = get_post_meta( $post->ID, '_ras_total_time', true );
        $price        = get_post_meta( $post->ID, '_ras_price', true );
        $currency     = get_post_meta( $post->ID, '_ras_currency', true ) ?: 'USD';
        $avail        = get_post_meta( $post->ID, '_ras_availability', true ) ?: 'InStock';
        $sku          = get_post_meta( $post->ID, '_ras_sku', true );
        $rating       = get_post_meta( $post->ID, '_ras_rating', true );
        $rcount       = get_post_meta( $post->ID, '_ras_rating_count', true );
        $ev_start     = get_post_meta( $post->ID, '_ras_event_start', true );
        $ev_end       = get_post_meta( $post->ID, '_ras_event_end', true );
        $ev_status    = get_post_meta( $post->ID, '_ras_event_status', true ) ?: 'EventScheduled';
        $ev_attend    = get_post_meta( $post->ID, '_ras_event_attend', true ) ?: 'OfflineEventAttendanceMode';
        $venue        = get_post_meta( $post->ID, '_ras_venue', true );
        $venue_addr   = get_post_meta( $post->ID, '_ras_venue_address', true );
        $venue_city   = get_post_meta( $post->ID, '_ras_venue_city', true );
        $venue_ctry   = get_post_meta( $post->ID, '_ras_venue_country', true );
        $prep_time    = get_post_meta( $post->ID, '_ras_prep_time', true );
        $cook_time    = get_post_meta( $post->ID, '_ras_cook_time', true );
        $ingredients  = get_post_meta( $post->ID, '_ras_ingredients', true );
        $recipe_yield = get_post_meta( $post->ID, '_ras_recipe_yield', true );
        $calories     = get_post_meta( $post->ID, '_ras_calories', true );
        $custom_json  = get_post_meta( $post->ID, '_ras_custom_json', true );

        $schema_types = [
            'Article' => 'Article', 'BlogPosting' => 'Blog Post',
            'NewsArticle' => 'News Article', 'WebPage' => 'Web Page',
            'FAQPage' => 'FAQ Page', 'HowTo' => 'How-To',
            'Product' => 'Product', 'Event' => 'Event',
            'Recipe' => 'Recipe', 'LocalBusiness' => 'Local Business',
            'Custom' => 'Custom JSON-LD',
        ];

        $score_label = $seo_score !== '' && $seo_score !== false ? RAS_SEO_Analyzer::label( (int) $seo_score ) : 'none';
        ?>
        <div class="ras-mb">
            <!-- TABS -->
            <div class="ras-mb-tabs">
                <button type="button" class="ras-mb-tab ras-mb-tab-active" data-tab="schema">⬡ Schema</button>
                <button type="button" class="ras-mb-tab" data-tab="seo">
                    🔍 SEO Analysis
                    <?php if ( $seo_score !== '' && $seo_score !== false ) : ?>
                        <span class="ras-mb-badge ras-mb-badge-<?php echo esc_attr( $score_label ); ?>"><?php echo (int) $seo_score; ?></span>
                    <?php endif; ?>
                </button>
            </div>

            <!-- ════════ SCHEMA TAB ════════ -->
            <div class="ras-mb-panel ras-mb-panel-active" id="ras-tab-schema">
                <div class="ras-mb-row">
                    <label class="ras-mb-radio <?php echo $mode === 'global' ? 'checked' : ''; ?>">
                        <input type="radio" name="_ras_schema_mode" value="global" <?php checked( $mode, 'global' ); ?>>
                        <span>Use Global Schema</span>
                    </label>
                    <label class="ras-mb-radio <?php echo $mode === 'override' ? 'checked' : ''; ?>">
                        <input type="radio" name="_ras_schema_mode" value="override" <?php checked( $mode, 'override' ); ?>>
                        <span>Override for this page</span>
                    </label>
                    <label class="ras-mb-radio <?php echo $mode === 'disabled' ? 'checked' : ''; ?>">
                        <input type="radio" name="_ras_schema_mode" value="disabled" <?php checked( $mode, 'disabled' ); ?>>
                        <span>Disable Schema</span>
                    </label>
                    <a href="https://search.google.com/test/rich-results" target="_blank" class="ras-btn ras-btn-xs ras-btn-ghost" style="margin-left:auto;">Test in Google ↗</a>
                </div>

                <div id="ras-schema-override" style="<?php echo 'override' === $mode ? '' : 'display:none;'; ?>">
                    <!-- Type selector -->
                    <div class="ras-mb-field-row">
                        <div class="ras-mb-field">
                            <label>Schema Type</label>
                            <select name="_ras_schema_type" id="ras_schema_type" class="ras-mb-select">
                                <?php foreach ( $schema_types as $val => $lbl ) : ?>
                                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $type, $val ); ?>><?php echo esc_html( $lbl ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ras-mb-field">
                            <label>Author Name <span class="ras-mb-muted">(Article types)</span></label>
                            <input type="text" name="_ras_author_name" value="<?php echo esc_attr( $author_name ); ?>" placeholder="Post author name">
                        </div>
                        <div class="ras-mb-field">
                            <label>Author URL</label>
                            <input type="url" name="_ras_author_url" value="<?php echo esc_url( $author_url ); ?>" placeholder="https://…">
                        </div>
                    </div>

                    <!-- FAQ items -->
                    <div class="ras-schema-group" data-for="FAQPage">
                        <div class="ras-mb-section-label">FAQ Items <span class="ras-mb-muted">— must match text visible on the page</span></div>
                        <div id="ras-faq-list">
                            <?php foreach ( $faq_items as $i => $fi ) : ?>
                            <div class="ras-repeater-item">
                                <div class="ras-ri-num"><?php echo $i + 1; ?></div>
                                <div class="ras-ri-body">
                                    <input type="text" name="_ras_faq_q[]" value="<?php echo esc_attr( $fi['q'] ); ?>" placeholder="Question">
                                    <textarea name="_ras_faq_a[]" rows="2" placeholder="Answer"><?php echo esc_textarea( $fi['a'] ); ?></textarea>
                                </div>
                                <button type="button" class="ras-ri-del" data-list="ras-faq-list">✕</button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="ras-btn ras-btn-xs ras-btn-ghost ras-add-row" data-list="ras-faq-list" data-template="faq">+ Add Question</button>
                    </div>

                    <!-- Steps (HowTo / Recipe) -->
                    <div class="ras-schema-group" data-for="HowTo Recipe">
                        <div class="ras-mb-section-label">Steps</div>
                        <div id="ras-steps-list">
                            <?php foreach ( $steps as $i => $st ) : ?>
                            <div class="ras-repeater-item">
                                <div class="ras-ri-num"><?php echo $i + 1; ?></div>
                                <div class="ras-ri-body">
                                    <input type="text" name="_ras_step_name[]" value="<?php echo esc_attr( $st['name'] ); ?>" placeholder="Step title">
                                    <textarea name="_ras_step_text[]" rows="2" placeholder="Step description"><?php echo esc_textarea( $st['text'] ); ?></textarea>
                                </div>
                                <button type="button" class="ras-ri-del" data-list="ras-steps-list">✕</button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="ras-btn ras-btn-xs ras-btn-ghost ras-add-row" data-list="ras-steps-list" data-template="step">+ Add Step</button>
                    </div>

                    <!-- HowTo extras -->
                    <div class="ras-schema-group" data-for="HowTo">
                        <div class="ras-mb-field-row">
                            <div class="ras-mb-field"><label>Total Time (ISO 8601)</label>
                                <input type="text" name="_ras_total_time" value="<?php echo esc_attr( $total_time ); ?>" placeholder="PT30M"></div>
                        </div>
                    </div>

                    <!-- Product fields -->
                    <div class="ras-schema-group" data-for="Product">
                        <div class="ras-mb-section-label">Product Details</div>
                        <div class="ras-mb-field-row">
                            <div class="ras-mb-field"><label>SKU</label><input type="text" name="_ras_sku" value="<?php echo esc_attr( $sku ); ?>"></div>
                            <div class="ras-mb-field"><label>Price</label><input type="text" name="_ras_price" value="<?php echo esc_attr( $price ); ?>" placeholder="19.99"></div>
                            <div class="ras-mb-field"><label>Currency (ISO 4217)</label><input type="text" name="_ras_currency" value="<?php echo esc_attr( $currency ); ?>" placeholder="USD" maxlength="3"></div>
                        </div>
                        <div class="ras-mb-field-row">
                            <div class="ras-mb-field"><label>Availability</label>
                                <select name="_ras_availability" class="ras-mb-select">
                                    <?php foreach ( [ 'InStock', 'OutOfStock', 'PreOrder', 'BackOrder', 'Discontinued', 'SoldOut' ] as $av ) : ?>
                                        <option value="<?php echo esc_attr( $av ); ?>" <?php selected( $avail, $av ); ?>><?php echo esc_html( $av ); ?></option>
                                    <?php endforeach; ?>
                                </select></div>
                            <div class="ras-mb-field"><label>Rating (1–5)</label><input type="number" name="_ras_rating" min="1" max="5" step="0.1" value="<?php echo esc_attr( $rating ); ?>"></div>
                            <div class="ras-mb-field"><label>Rating Count</label><input type="number" name="_ras_rating_count" min="1" value="<?php echo esc_attr( $rcount ); ?>"></div>
                        </div>
                    </div>

                    <!-- Event fields -->
                    <div class="ras-schema-group" data-for="Event">
                        <div class="ras-mb-section-label">Event Details</div>
                        <div class="ras-mb-field-row">
                            <div class="ras-mb-field"><label>Start Date & Time <span class="ras-required">*</span></label>
                                <input type="datetime-local" name="_ras_event_start" value="<?php echo esc_attr( $ev_start ); ?>"></div>
                            <div class="ras-mb-field"><label>End Date & Time</label>
                                <input type="datetime-local" name="_ras_event_end" value="<?php echo esc_attr( $ev_end ); ?>"></div>
                        </div>
                        <div class="ras-mb-field-row">
                            <div class="ras-mb-field"><label>Event Status <span class="ras-required">*</span></label>
                                <select name="_ras_event_status" class="ras-mb-select">
                                    <?php foreach ( [ 'EventScheduled', 'EventCancelled', 'EventPostponed', 'EventRescheduled', 'EventMovedOnline' ] as $es ) :
                                        echo '<option value="' . esc_attr( $es ) . '" ' . selected( $ev_status, $es, false ) . '>' . esc_html( $es ) . '</option>';
                                    endforeach; ?>
                                </select></div>
                            <div class="ras-mb-field"><label>Attendance Mode <span class="ras-required">*</span></label>
                                <select name="_ras_event_attend" class="ras-mb-select">
                                    <?php foreach ( [ 'OfflineEventAttendanceMode', 'OnlineEventAttendanceMode', 'MixedEventAttendanceMode' ] as $ea ) :
                                        echo '<option value="' . esc_attr( $ea ) . '" ' . selected( $ev_attend, $ea, false ) . '>' . esc_html( $ea ) . '</option>';
                                    endforeach; ?>
                                </select></div>
                        </div>
                        <div class="ras-mb-field-row">
                            <div class="ras-mb-field"><label>Venue Name</label><input type="text" name="_ras_venue" value="<?php echo esc_attr( $venue ); ?>"></div>
                            <div class="ras-mb-field"><label>Street Address</label><input type="text" name="_ras_venue_address" value="<?php echo esc_attr( $venue_addr ); ?>"></div>
                            <div class="ras-mb-field"><label>City</label><input type="text" name="_ras_venue_city" value="<?php echo esc_attr( $venue_city ); ?>"></div>
                            <div class="ras-mb-field"><label>Country (ISO)</label><input type="text" name="_ras_venue_country" value="<?php echo esc_attr( $venue_ctry ); ?>" maxlength="2"></div>
                        </div>
                    </div>

                    <!-- Recipe fields -->
                    <div class="ras-schema-group" data-for="Recipe">
                        <div class="ras-mb-section-label">Recipe Details <span class="ras-mb-muted">— featured image is required by Google</span></div>
                        <div class="ras-mb-field-row">
                            <div class="ras-mb-field"><label>Prep Time (ISO)</label><input type="text" name="_ras_prep_time" value="<?php echo esc_attr( $prep_time ); ?>" placeholder="PT15M"></div>
                            <div class="ras-mb-field"><label>Cook Time (ISO)</label><input type="text" name="_ras_cook_time" value="<?php echo esc_attr( $cook_time ); ?>" placeholder="PT30M"></div>
                            <div class="ras-mb-field"><label>Total Time (ISO)</label><input type="text" name="_ras_total_time_r" value="<?php echo esc_attr( $total_time ); ?>" placeholder="PT45M"></div>
                            <div class="ras-mb-field"><label>Yield</label><input type="text" name="_ras_recipe_yield" value="<?php echo esc_attr( $recipe_yield ); ?>" placeholder="4 servings"></div>
                        </div>
                        <div class="ras-mb-field">
                            <label>Ingredients <span class="ras-mb-muted">(one per line)</span></label>
                            <textarea name="_ras_ingredients" rows="5" placeholder="2 cups flour&#10;1 tsp salt&#10;3 large eggs"><?php echo esc_textarea( $ingredients ); ?></textarea>
                        </div>
                        <div class="ras-mb-field-row">
                            <div class="ras-mb-field"><label>Calories</label><input type="text" name="_ras_calories" value="<?php echo esc_attr( $calories ); ?>" placeholder="250"></div>
                            <div class="ras-mb-field"><label>Rating (1–5)</label><input type="number" name="_ras_rating" min="1" max="5" step="0.1" value="<?php echo esc_attr( $rating ); ?>"></div>
                            <div class="ras-mb-field"><label>Rating Count</label><input type="number" name="_ras_rating_count" min="1" value="<?php echo esc_attr( $rcount ); ?>"></div>
                        </div>
                    </div>

                    <!-- Custom JSON-LD -->
                    <div class="ras-schema-group" data-for="Custom">
                        <div class="ras-mb-section-label">Custom JSON-LD</div>
                        <textarea name="_ras_custom_json" rows="10" class="ras-code-area" placeholder='{"@context":"https://schema.org","@type":"Thing","name":"…"}'><?php echo esc_textarea( $custom_json ); ?></textarea>
                        <div id="ras-json-status" class="ras-json-status"></div>
                    </div>
                </div><!-- /#ras-schema-override -->
            </div><!-- /#ras-tab-schema -->

            <!-- ════════ SEO TAB ════════ -->
            <div class="ras-mb-panel" id="ras-tab-seo">

                <!-- Score widget -->
                <div class="ras-seo-score-row">
                    <div class="ras-seo-circle ras-seo-circle-<?php echo esc_attr( $score_label ); ?>">
                        <span><?php echo ( $seo_score !== '' && $seo_score !== false ) ? (int) $seo_score : '–'; ?></span>
                    </div>
                    <div class="ras-seo-score-meta">
                        <strong>SEO Score</strong>
                        <p><?php
                            if ( $seo_ts ) {
                                printf( 'Analyzed %s ago', human_time_diff( (int) $seo_ts ) );
                            } else {
                                echo 'Not yet analyzed';
                            }
                        ?></p>
                    </div>
                    <button type="button" class="ras-btn ras-btn-primary ras-btn-xs ras-mb-analyze"
                        data-post="<?php echo (int) $post->ID; ?>">
                        <?php echo ( $seo_score !== '' ) ? '↺ Re-analyze' : '🔍 Analyze Now'; ?>
                    </button>
                </div>

                <!-- Mini check results -->
                <div id="ras-mb-checks" class="ras-mb-checks">
                    <?php
                    $raw = get_post_meta( $post->ID, '_ras_seo_results', true );
                    $res = $raw ? json_decode( $raw, true ) : [];
                    if ( $res ) {
                        foreach ( $res as $id => $r ) {
                            $icon = $r['status'] === 'pass' ? '✓' : ( $r['status'] === 'warn' ? '⚠' : '✗' );
                            printf(
                                '<div class="ras-mc ras-mc-%s"><span class="ras-mc-icon">%s</span><span class="ras-mc-label">%s</span><span class="ras-mc-msg">%s</span></div>',
                                esc_attr( $r['status'] ), esc_html( $icon ),
                                esc_html( $r['label'] ), esc_html( $r['message'] )
                            );
                        }
                    } else {
                        echo '<p class="ras-mb-muted-p">Run analysis to see detailed checks.</p>';
                    }
                    ?>
                </div>

                <div class="ras-mb-sep"></div>

                <!-- Focus keyword -->
                <div class="ras-mb-field">
                    <label>Focus Keyword</label>
                    <input type="text" name="_ras_focus_kw" value="<?php echo esc_attr( $kw ); ?>" placeholder="e.g. best coffee recipes">
                    <p class="ras-mb-hint">Primary keyword this page targets — used in all SEO checks.</p>
                </div>

                <!-- Meta description -->
                <div class="ras-mb-field">
                    <label>Meta Description <span id="ras-desc-count" class="ras-char-counter">0 / 160</span></label>
                    <textarea id="ras-meta-desc" name="_ras_meta_desc" rows="3"
                        placeholder="120–160 character description for search results…"><?php echo esc_textarea( $meta_desc ); ?></textarea>
                    <div class="ras-desc-bar"><div id="ras-desc-fill" class="ras-desc-fill"></div></div>
                </div>

                <!-- OG fields -->
                <div class="ras-mb-field-row">
                    <div class="ras-mb-field">
                        <label>OG Title</label>
                        <input type="text" name="_ras_og_title" value="<?php echo esc_attr( $og_title ); ?>" placeholder="Defaults to page title">
                    </div>
                    <div class="ras-mb-field">
                        <label>OG Description</label>
                        <input type="text" name="_ras_og_desc" value="<?php echo esc_attr( $og_desc ); ?>" placeholder="Defaults to meta description">
                    </div>
                </div>

                <div class="ras-mb-field">
                    <label>Social Image URL</label>
                    <input type="url" name="_ras_og_image" value="<?php echo esc_url( $og_img ); ?>" placeholder="Defaults to featured image — recommended 1200×630">
                </div>

                <!-- No-index -->
                <div class="ras-mb-toggle-row">
                    <div>
                        <strong>No-Index this page</strong>
                        <p>Tells search engines not to index this URL.</p>
                    </div>
                    <label class="ras-toggle">
                        <input type="checkbox" name="_ras_noindex" value="1" <?php checked( $noindex, '1' ); ?>>
                        <span></span>
                    </label>
                </div>

                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rank-ai-schema-seo' ) ); ?>" class="ras-btn ras-btn-ghost ras-btn-xs" style="margin-top:12px;">📊 Open Full SEO Dashboard ↗</a>
            </div><!-- /#ras-tab-seo -->
        </div><!-- /.ras-mb -->
        <?php
    }

    /* ─── SAVE ─────────────────────────────────────────────────────── */
    public static function save( $post_id, $post ) {
        if ( ! isset( $_POST['ras_mb_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ras_mb_nonce'] ) ), 'ras_mb_save' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
        if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

        /* Mode & type */
        $mode = sanitize_key( $_POST['_ras_schema_mode'] ?? 'global' );
        update_post_meta( $post_id, '_ras_schema_mode', $mode );
        update_post_meta( $post_id, '_ras_schema_type', sanitize_text_field( $_POST['_ras_schema_type'] ?? 'Article' ) );

        /* Text fields */
        $texts = [
            '_ras_author_name', '_ras_author_url', '_ras_sku', '_ras_price', '_ras_currency',
            '_ras_availability', '_ras_rating', '_ras_rating_count',
            '_ras_event_start', '_ras_event_end', '_ras_event_status', '_ras_event_attend',
            '_ras_venue', '_ras_venue_address', '_ras_venue_city', '_ras_venue_country',
            '_ras_prep_time', '_ras_cook_time', '_ras_total_time', '_ras_recipe_yield', '_ras_calories',
            '_ras_focus_kw', '_ras_og_title', '_ras_og_desc', '_ras_og_image',
        ];
        foreach ( $texts as $k ) {
            update_post_meta( $post_id, $k, sanitize_text_field( wp_unslash( $_POST[ $k ] ?? '' ) ) );
        }

        /* Textareas */
        update_post_meta( $post_id, '_ras_meta_desc',   sanitize_textarea_field( wp_unslash( $_POST['_ras_meta_desc'] ?? '' ) ) );
        update_post_meta( $post_id, '_ras_ingredients',  sanitize_textarea_field( wp_unslash( $_POST['_ras_ingredients'] ?? '' ) ) );

        /* Checkbox */
        update_post_meta( $post_id, '_ras_noindex', isset( $_POST['_ras_noindex'] ) ? '1' : '0' );

        /* FAQ items */
        $qs = array_map( 'sanitize_text_field', wp_unslash( $_POST['_ras_faq_q'] ?? [] ) );
        $as = array_map( 'sanitize_textarea_field', wp_unslash( $_POST['_ras_faq_a'] ?? [] ) );
        $faq = [];
        foreach ( $qs as $i => $q ) {
            if ( trim( $q ) ) { $faq[] = [ 'q' => $q, 'a' => $as[ $i ] ?? '' ]; }
        }
        update_post_meta( $post_id, '_ras_faq_items', wp_slash( wp_json_encode( $faq ) ) );

        /* Steps */
        $sn = array_map( 'sanitize_text_field', wp_unslash( $_POST['_ras_step_name'] ?? [] ) );
        $st = array_map( 'sanitize_textarea_field', wp_unslash( $_POST['_ras_step_text'] ?? [] ) );
        $steps = [];
        foreach ( $sn as $i => $n ) {
            if ( trim( $n ) ) { $steps[] = [ 'name' => $n, 'text' => $st[ $i ] ?? '' ]; }
        }
        update_post_meta( $post_id, '_ras_steps', wp_slash( wp_json_encode( $steps ) ) );

        /* Custom JSON */
        $cj = wp_unslash( $_POST['_ras_custom_json'] ?? '' );
        if ( $cj && null !== json_decode( $cj ) ) {
            update_post_meta( $post_id, '_ras_custom_json', wp_slash( trim( $cj ) ) );
        }
    }
}

RAS_Meta_Box::init();
