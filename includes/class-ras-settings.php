<?php
/**
 * Settings — Rank AI Schema
 * Global org settings + per-CPT default schema type.
 * @package RankAISchema
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RAS_Settings {

    public static function get() {
        return wp_parse_args( (array) get_option( 'ras_global_settings', [] ), [
            'org_name'      => get_bloginfo( 'name' ),
            'org_url'       => get_bloginfo( 'url' ),
            'org_logo'      => '',
            'org_email'     => '',
            'social_fb'     => '', 'social_tw' => '',
            'social_ig'     => '', 'social_li' => '', 'social_yt' => '',
            'breadcrumbs'   => '1',
            'sitelinks'     => '1',
            'organization'  => '1',
            'woo_bridge'    => '1',   // Auto-pull WooCommerce product data
            'cpt_defaults'  => [],    // [ 'post_type_name' => 'SchemaType' ]
        ] );
    }

    /**
     * Get the default schema type for a given post type.
     * Checks CPT-specific override first, then built-in smart defaults.
     */
    public static function schema_type_for_post_type( $post_type ) {
        $s = self::get();

        // 1. User-configured CPT override.
        if ( ! empty( $s['cpt_defaults'][ $post_type ] ) ) {
            return $s['cpt_defaults'][ $post_type ];
        }

        // 2. Smart built-in defaults.
        $defaults = [
            'post'    => 'Article',
            'page'    => 'WebPage',
            'product' => 'Product',       // WooCommerce
        ];
        return $defaults[ $post_type ] ?? 'WebPage';
    }

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }

        if ( isset( $_POST['ras_settings_nonce'] ) &&
             wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ras_settings_nonce'] ) ), 'ras_save_settings' ) ) {
            $s = [];
            $text_keys = [ 'org_name','org_url','org_logo','org_email',
                           'social_fb','social_tw','social_ig','social_li','social_yt' ];
            foreach ( $text_keys as $k ) {
                $s[ $k ] = sanitize_text_field( wp_unslash( $_POST[ $k ] ?? '' ) );
            }
            $s['breadcrumbs']  = isset( $_POST['breadcrumbs'] )  ? '1' : '0';
            $s['sitelinks']    = isset( $_POST['sitelinks'] )    ? '1' : '0';
            $s['organization'] = isset( $_POST['organization'] ) ? '1' : '0';
            $s['woo_bridge']   = isset( $_POST['woo_bridge'] )   ? '1' : '0';

            // CPT defaults: array of post_type => SchemaType
            $cpt_defaults = [];
            if ( isset( $_POST['cpt_defaults'] ) && is_array( $_POST['cpt_defaults'] ) ) {
                foreach ( $_POST['cpt_defaults'] as $pt => $schema ) {
                    $pt_clean     = sanitize_key( $pt );
                    $schema_clean = sanitize_text_field( wp_unslash( $schema ) );
                    if ( $pt_clean && $schema_clean ) {
                        $cpt_defaults[ $pt_clean ] = $schema_clean;
                    }
                }
            }
            $s['cpt_defaults'] = $cpt_defaults;
            update_option( 'ras_global_settings', $s );
            echo '<div class="notice notice-success is-dismissible"><p><strong>✓ Settings saved.</strong></p></div>';
        }

        $s           = self::get();
        $all_types   = self::get_public_post_types();
        $schema_list = self::schema_type_list();
        ?>
        <div class="ras-wrap">
        <div class="ras-page-header">
            <div class="ras-page-title"><span class="ras-logo-star">★</span> Rank AI Schema <span class="ras-page-sub">/ Global Settings</span></div>
            <?php self::nav( 'settings' ); ?>
        </div>
        <div class="ras-page-body">
        <form method="post">
            <?php wp_nonce_field( 'ras_save_settings', 'ras_settings_nonce' ); ?>

            <!-- Organization -->
            <div class="ras-card">
                <div class="ras-card-head"><h3>🏢 Organization</h3><p>Your brand identity — used in Organization schema and publisher fields.</p></div>
                <div class="ras-card-body">
                    <div class="ras-field-row">
                        <div class="ras-field"><label>Organization Name</label><input type="text" name="org_name" value="<?php echo esc_attr( $s['org_name'] ); ?>"></div>
                        <div class="ras-field"><label>Website URL</label><input type="url" name="org_url" value="<?php echo esc_attr( $s['org_url'] ); ?>"></div>
                    </div>
                    <div class="ras-field-row">
                        <div class="ras-field"><label>Logo URL <span class="ras-hint-inline">(fits 60×600 px per Google)</span></label><input type="url" name="org_logo" value="<?php echo esc_attr( $s['org_logo'] ); ?>"></div>
                        <div class="ras-field"><label>Contact Email</label><input type="email" name="org_email" value="<?php echo esc_attr( $s['org_email'] ); ?>"></div>
                    </div>
                </div>
            </div>

            <!-- Social Profiles -->
            <div class="ras-card">
                <div class="ras-card-head"><h3>🔗 Social Profiles (sameAs)</h3><p>Links your entity across the web for Google's Knowledge Graph.</p></div>
                <div class="ras-card-body">
                    <div class="ras-field-row">
                        <div class="ras-field"><label>Facebook</label><input type="url" name="social_fb" value="<?php echo esc_attr( $s['social_fb'] ); ?>"></div>
                        <div class="ras-field"><label>X / Twitter</label><input type="url" name="social_tw" value="<?php echo esc_attr( $s['social_tw'] ); ?>"></div>
                        <div class="ras-field"><label>Instagram</label><input type="url" name="social_ig" value="<?php echo esc_attr( $s['social_ig'] ); ?>"></div>
                    </div>
                    <div class="ras-field-row">
                        <div class="ras-field"><label>LinkedIn</label><input type="url" name="social_li" value="<?php echo esc_attr( $s['social_li'] ); ?>"></div>
                        <div class="ras-field"><label>YouTube</label><input type="url" name="social_yt" value="<?php echo esc_attr( $s['social_yt'] ); ?>"></div>
                    </div>
                </div>
            </div>

            <!-- Per-CPT Schema Defaults -->
            <div class="ras-card">
                <div class="ras-card-head">
                    <h3>📦 Post Type → Schema Defaults</h3>
                    <p>Set the default schema type for each post type. Overridden per-page in the Schema tab of the editor. WooCommerce <code>product</code> defaults to <code>Product</code> automatically.</p>
                </div>
                <div class="ras-card-body">
                    <table class="ras-cpt-table">
                        <thead>
                            <tr><th>Post Type</th><th>Slug</th><th>Default Schema Type</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $all_types as $pt ) :
                            $current = $s['cpt_defaults'][ $pt->name ] ?? self::schema_type_for_post_type( $pt->name );
                            $is_woo  = $pt->name === 'product';
                            $is_smart= in_array( $pt->name, [ 'post', 'page', 'product' ], true );
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $pt->label ); ?></strong>
                                    <?php if ( $is_woo ) : ?><span class="ras-woo-badge">WooCommerce</span><?php endif; ?>
                                </td>
                                <td><code><?php echo esc_html( $pt->name ); ?></code></td>
                                <td>
                                    <select name="cpt_defaults[<?php echo esc_attr( $pt->name ); ?>]" class="ras-cpt-select">
                                        <?php foreach ( $schema_list as $val => $lbl ) : ?>
                                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current, $val ); ?>><?php echo esc_html( $lbl ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <?php if ( $is_smart ) : ?>
                                        <span class="ras-tag ras-tag-blue">Smart default</span>
                                    <?php else : ?>
                                        <span class="ras-tag ras-tag-grey">Custom CPT</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Feature Toggles -->
            <div class="ras-card">
                <div class="ras-card-head"><h3>⚙️ Global Schema Features</h3></div>
                <div class="ras-card-body">
                    <?php
                    $toggles = [
                        'organization' => [ '🏢 Organization Schema',    'Outputs @type:Organization with @id, logo, and sameAs on every page.' ],
                        'sitelinks'    => [ '🔍 Sitelinks Searchbox',     'WebSite + SearchAction markup on the homepage.' ],
                        'breadcrumbs'  => [ '🔗 BreadcrumbList',          'Auto-built breadcrumbs on all non-home pages.' ],
                        'woo_bridge'   => [ '🛒 WooCommerce Data Bridge',  'Automatically pull price, SKU, stock, and reviews from WooCommerce products. Requires WooCommerce.' ],
                    ];
                    foreach ( $toggles as $key => [ $label, $desc ] ) : ?>
                        <div class="ras-toggle-row">
                            <div>
                                <strong><?php echo $label; ?></strong>
                                <p><?php echo esc_html( $desc ); ?></p>
                            </div>
                            <label class="ras-toggle">
                                <input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( $s[ $key ], '1' ); ?>>
                                <span></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="ras-form-footer">
                <button type="submit" class="ras-btn ras-btn-primary">💾 Save Settings</button>
            </div>
        </form>
        </div></div>
        <?php
    }

    /* ── Helpers ──────────────────────────────────── */
    public static function get_public_post_types() {
        $exclude = [ 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation' ];
        $types = get_post_types( [ 'public' => true ], 'objects' );
        return array_filter( $types, fn( $pt ) => ! in_array( $pt->name, $exclude, true ) );
    }

    public static function schema_type_list() {
        return [
            'WebPage'       => 'Web Page (generic)',
            'Article'       => 'Article',
            'BlogPosting'   => 'Blog Post',
            'NewsArticle'   => 'News Article',
            'Product'       => 'Product',
            'Event'         => 'Event',
            'FAQPage'       => 'FAQ Page',
            'HowTo'         => 'How-To',
            'Recipe'        => 'Recipe',
            'LocalBusiness' => 'Local Business',
            'JobPosting'    => 'Job Posting',
            'Course'        => 'Course',
            'SoftwareApplication' => 'Software App',
            'VideoObject'   => 'Video',
            'Custom'        => 'Custom JSON-LD',
        ];
    }

    public static function nav( $active = 'dashboard' ) {
        $links = [
            'dashboard' => [ admin_url( 'admin.php?page=rank-ai-schema' ),          '📊 Dashboard' ],
            'seo'       => [ admin_url( 'admin.php?page=rank-ai-schema-seo' ),      '🔍 SEO Analysis' ],
            'settings'  => [ admin_url( 'admin.php?page=rank-ai-schema-settings' ), '⚙️ Settings' ],
        ];
        echo '<div class="ras-nav">';
        foreach ( $links as $key => [ $url, $label ] ) {
            $cls = $key === $active ? 'ras-nav-link active' : 'ras-nav-link';
            printf( '<a href="%s" class="%s">%s</a>', esc_url( $url ), esc_attr( $cls ), esc_html( $label ) );
        }
        echo '</div>';
    }
}
