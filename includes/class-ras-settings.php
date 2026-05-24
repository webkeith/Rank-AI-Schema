<?php
/**
 * Settings — Rank AI Schema
 * @package RankAISchema
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RAS_Settings {

    public static function get() {
        return wp_parse_args( (array) get_option( 'ras_global_settings', [] ), [
            'org_name' => get_bloginfo( 'name' ), 'org_url' => get_bloginfo( 'url' ),
            'org_logo' => '', 'org_email' => '', 'social_fb' => '', 'social_tw' => '',
            'social_ig' => '', 'social_li' => '', 'social_yt' => '',
            'breadcrumbs' => '1', 'sitelinks' => '1', 'organization' => '1',
        ] );
    }

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }

        if ( isset( $_POST['ras_settings_nonce'] ) &&
             wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ras_settings_nonce'] ) ), 'ras_save_settings' ) ) {
            $s = [];
            $text_keys = [ 'org_name','org_url','org_logo','org_email',
                           'social_fb','social_tw','social_ig','social_li','social_yt' ];
            foreach ( $text_keys as $k ) {
                $s[ $k ] = isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : '';
            }
            $s['breadcrumbs']  = isset( $_POST['breadcrumbs'] )  ? '1' : '0';
            $s['sitelinks']    = isset( $_POST['sitelinks'] )    ? '1' : '0';
            $s['organization'] = isset( $_POST['organization'] ) ? '1' : '0';
            update_option( 'ras_global_settings', $s );
            echo '<div class="notice notice-success"><p><strong>Settings saved!</strong></p></div>';
        }

        $s = self::get();
        ?>
        <div class="ras-wrap">
        <div class="ras-page-header">
            <div class="ras-page-title"><span class="ras-logo-star">★</span> Rank AI Schema <span class="ras-page-sub">/ Global Settings</span></div>
            <?php self::nav( 'settings' ); ?>
        </div>
        <div class="ras-page-body">
        <form method="post">
            <?php wp_nonce_field( 'ras_save_settings', 'ras_settings_nonce' ); ?>
            <div class="ras-settings-grid">

                <!-- Organization -->
                <div class="ras-card">
                    <div class="ras-card-head"><h3>🏢 Organization Schema</h3><p>Your brand identity in Google's Knowledge Graph.</p></div>
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

                <!-- Social -->
                <div class="ras-card">
                    <div class="ras-card-head"><h3>🔗 Social Profiles (sameAs)</h3><p>Helps Google link your entity across the web.</p></div>
                    <div class="ras-card-body">
                        <div class="ras-field-row">
                            <div class="ras-field"><label>Facebook</label><input type="url" name="social_fb" value="<?php echo esc_attr( $s['social_fb'] ); ?>"></div>
                            <div class="ras-field"><label>X / Twitter</label><input type="url" name="social_tw" value="<?php echo esc_attr( $s['social_tw'] ); ?>"></div>
                        </div>
                        <div class="ras-field-row">
                            <div class="ras-field"><label>Instagram</label><input type="url" name="social_ig" value="<?php echo esc_attr( $s['social_ig'] ); ?>"></div>
                            <div class="ras-field"><label>LinkedIn</label><input type="url" name="social_li" value="<?php echo esc_attr( $s['social_li'] ); ?>"></div>
                            <div class="ras-field"><label>YouTube</label><input type="url" name="social_yt" value="<?php echo esc_attr( $s['social_yt'] ); ?>"></div>
                        </div>
                    </div>
                </div>

                <!-- Features -->
                <div class="ras-card">
                    <div class="ras-card-head"><h3>⚙️ Schema Features</h3><p>Global schema blocks output on every page.</p></div>
                    <div class="ras-card-body">
                        <?php
                        $toggles = [
                            'organization' => [ 'Organization Schema',        'Outputs @type:Organization with @id, logo, and sameAs links.' ],
                            'sitelinks'    => [ 'Sitelinks Searchbox',        'WebSite + SearchAction on the homepage.' ],
                            'breadcrumbs'  => [ 'BreadcrumbList',             'Auto-built breadcrumb trail on all non-home pages.' ],
                        ];
                        foreach ( $toggles as $key => [ $label, $desc ] ) : ?>
                            <div class="ras-toggle-row">
                                <div><strong><?php echo esc_html( $label ); ?></strong><p><?php echo esc_html( $desc ); ?></p></div>
                                <label class="ras-toggle"><input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( $s[ $key ], '1' ); ?>><span></span></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
            <div class="ras-form-footer">
                <button type="submit" class="ras-btn ras-btn-primary">💾 Save Settings</button>
            </div>
        </form>
        </div></div>
        <?php
    }

    public static function nav( $active = 'dashboard' ) {
        $links = [
            'dashboard' => [ admin_url( 'admin.php?page=rank-ai-schema' ),          '📊 Schema Dashboard' ],
            'seo'       => [ admin_url( 'admin.php?page=rank-ai-schema-seo' ),      '🔍 SEO Analysis'    ],
            'settings'  => [ admin_url( 'admin.php?page=rank-ai-schema-settings' ), '⚙️ Settings'        ],
        ];
        echo '<div class="ras-nav">';
        foreach ( $links as $key => [ $url, $label ] ) {
            $cls = $key === $active ? 'ras-nav-link active' : 'ras-nav-link';
            printf( '<a href="%s" class="%s">%s</a>', esc_url( $url ), esc_attr( $cls ), esc_html( $label ) );
        }
        echo '</div>';
    }
}
