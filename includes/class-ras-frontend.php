<?php
/**
 * Frontend — JSON-LD schema output & meta tags.
 * Fully Google Rich Results compliant.
 * @package RankAISchema
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class RAS_Frontend {

    public static function init() {
        add_action( 'wp_head', [ __CLASS__, 'output' ], 2 );
    }

    public static function output() {
        $g = RAS_Settings::get();
        $schemas = [];

        /* 1. Organization */
        if ( '1' === $g['organization'] ) {
            $schemas[] = self::organization( $g );
        }

        /* 2. WebSite + Sitelinks Searchbox (homepage only) */
        if ( is_front_page() && '1' === $g['sitelinks'] ) {
            $schemas[] = self::website( $g );
        }

        /* 3. BreadcrumbList */
        if ( ! is_front_page() && '1' === $g['breadcrumbs'] ) {
            $bc = self::breadcrumbs();
            if ( $bc ) { $schemas[] = $bc; }
        }

        /* 4. Per-page schema */
        if ( is_singular() ) {
            $ps = self::page_schema( get_the_ID() );
            if ( $ps ) { $schemas[] = $ps; }
        }

        /* 5. Meta / OG tags */
        self::meta_tags();

        foreach ( $schemas as $s ) {
            echo "\n<script type=\"application/ld+json\">\n";
            echo wp_json_encode( $s, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ); // phpcs:ignore
            echo "\n</script>\n";
        }
    }

    /* ── Meta / OG tags ───────────────────────────── */
    private static function meta_tags() {
        if ( ! is_singular() ) { return; }
        $id   = get_the_ID();
        $desc = get_post_meta( $id, '_ras_meta_desc', true );
        $og_t = get_post_meta( $id, '_ras_og_title', true );
        $og_d = get_post_meta( $id, '_ras_og_desc', true );
        $og_i = get_post_meta( $id, '_ras_og_image', true );
        $noix = get_post_meta( $id, '_ras_noindex', true );

        if ( $desc ) {
            printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) );
        }
        if ( $noix ) {
            echo '<meta name="robots" content="noindex,nofollow">' . "\n";
        }
        $og_title = $og_t ?: get_the_title( $id );
        $og_desc  = $og_d ?: $desc ?: wp_trim_words( get_the_excerpt( $id ), 30 );
        $og_img   = $og_i ?: ( has_post_thumbnail( $id ) ? wp_get_attachment_url( get_post_thumbnail_id( $id ) ) : '' );

        printf( '<meta property="og:type" content="article">' . "\n" );
        printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $og_title ) );
        printf( '<meta property="og:url" content="%s">' . "\n", esc_url( get_permalink( $id ) ) );
        if ( $og_desc ) {
            printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $og_desc ) );
        }
        if ( $og_img ) {
            printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $og_img ) );
        }
    }

    /* ── Organization ─────────────────────────────── */
    private static function organization( $g ) {
        $org_url = trailingslashit( $g['org_url'] );
        $s = [
            '@context' => 'https://schema.org', '@type' => 'Organization',
            '@id'  => $org_url . '#organization',
            'name' => $g['org_name'], 'url' => $g['org_url'],
        ];
        if ( $g['org_logo'] ) {
            $dim = self::image_dims( $g['org_logo'] );
            $logo = [ '@type' => 'ImageObject', 'url' => $g['org_logo'] ];
            if ( $dim ) { $logo['width'] = $dim[0]; $logo['height'] = $dim[1]; }
            $s['logo'] = $logo;
        }
        if ( $g['org_email'] ) {
            $s['email'] = $g['org_email'];
            $s['contactPoint'] = [ '@type' => 'ContactPoint', 'email' => $g['org_email'], 'contactType' => 'customer service' ];
        }
        $same = array_filter( [ $g['social_fb'], $g['social_tw'], $g['social_ig'], $g['social_li'], $g['social_yt'] ] );
        if ( $same ) { $s['sameAs'] = array_values( $same ); }
        return $s;
    }

    /* ── WebSite + Sitelinks Searchbox ───────────── */
    private static function website( $g ) {
        $url = trailingslashit( $g['org_url'] );
        return [
            '@context' => 'https://schema.org', '@type' => 'WebSite',
            '@id'  => $url . '#website',
            'name' => $g['org_name'], 'url' => $g['org_url'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [ '@type' => 'EntryPoint', 'urlTemplate' => $url . '?s={search_term_string}' ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /* ── BreadcrumbList ───────────────────────────── */
    private static function breadcrumbs() {
        $items = [ [ '@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => home_url('/') ] ];
        $pos   = 2;
        if ( is_singular() ) {
            $post = get_post();
            if ( 'post' === $post->post_type ) {
                $cats = get_the_category( $post->ID );
                if ( $cats ) {
                    $items[] = [ '@type' => 'ListItem', 'position' => $pos++,
                                 'name' => html_entity_decode( $cats[0]->name ),
                                 'item' => get_category_link( $cats[0]->term_id ) ];
                }
            }
            $items[] = [ '@type' => 'ListItem', 'position' => $pos,
                         'name' => html_entity_decode( get_the_title( $post->ID ) ),
                         'item' => get_permalink( $post->ID ) ];
        } elseif ( is_category() || is_tag() || is_tax() ) {
            $t = get_queried_object();
            $items[] = [ '@type' => 'ListItem', 'position' => $pos, 'name' => html_entity_decode( $t->name ), 'item' => get_term_link( $t ) ];
        }
        if ( count( $items ) < 2 ) { return null; }
        return [ '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items ];
    }

    /* ── Per-page schema dispatcher ──────────────── */
    public static function page_schema( $post_id ) {
        $mode = get_post_meta( $post_id, '_ras_schema_mode', true ) ?: 'global';
        if ( 'disabled' === $mode ) { return null; }
        $type = get_post_meta( $post_id, '_ras_schema_type', true ) ?: 'Article';
        $post = get_post( $post_id );
        $g    = RAS_Settings::get();

        switch ( $type ) {
            case 'Article': case 'BlogPosting': case 'NewsArticle':
                return self::article( $type, $post, $g );
            case 'FAQPage':
                return self::faq( $post );
            case 'HowTo':
                return self::howto( $post );
            case 'Product':
                return self::product( $post, $g );
            case 'Event':
                return self::event( $post, $g );
            case 'Recipe':
                return self::recipe( $post, $g );
            case 'LocalBusiness':
                return self::local_business( $post, $g );
            case 'Custom':
                $raw = get_post_meta( $post_id, '_ras_custom_json', true );
                $dec = json_decode( stripslashes( (string) $raw ), true );
                return is_array( $dec ) ? $dec : null;
            default:
                return self::webpage( $post, $g );
        }
    }

    /* ── Article ──────────────────────────────────── */
    private static function article( $type, $post, $g ) {
        $url   = get_permalink( $post->ID );
        $title = html_entity_decode( get_the_title( $post->ID ) );
        $g_url = trailingslashit( $g['org_url'] );

        $publisher = [ '@type' => 'Organization', '@id' => $g_url . '#organization', 'name' => $g['org_name'] ];
        if ( $g['org_logo'] ) {
            $dim = self::image_dims( $g['org_logo'] );
            $logo = [ '@type' => 'ImageObject', 'url' => $g['org_logo'] ];
            if ( $dim ) { $logo['width'] = $dim[0]; $logo['height'] = $dim[1]; }
            $publisher['logo'] = $logo;
        }

        $author_name = get_post_meta( $post->ID, '_ras_author_name', true ) ?: get_the_author_meta( 'display_name', $post->post_author );
        $author_url  = get_post_meta( $post->ID, '_ras_author_url', true ) ?: get_author_posts_url( $post->post_author );

        $s = [
            '@context' => 'https://schema.org', '@type' => $type,
            '@id'      => $url . '#article',
            'headline' => mb_substr( $title, 0, 110 ),
            'url'      => $url,
            'mainEntityOfPage' => [ '@type' => 'WebPage', '@id' => $url ],
            'datePublished'    => get_the_date( 'c', $post->ID ),
            'dateModified'     => get_the_modified_date( 'c', $post->ID ),
            'author'           => [ '@type' => 'Person', 'name' => $author_name, 'url' => $author_url ],
            'publisher'        => $publisher,
            'description'      => wp_strip_all_tags( get_the_excerpt( $post->ID ) ),
            'inLanguage'       => get_bloginfo( 'language' ),
        ];

        $imgs = self::post_images( $post->ID );
        if ( $imgs ) { $s['image'] = count( $imgs ) > 1 ? $imgs : $imgs[0]; }
        return $s;
    }

    /* ── FAQPage ──────────────────────────────────── */
    private static function faq( $post ) {
        $raw   = get_post_meta( $post->ID, '_ras_faq_items', true );
        $items = json_decode( stripslashes( (string) $raw ), true ) ?: [];
        $main  = [];
        foreach ( $items as $item ) {
            if ( empty( $item['q'] ) ) { continue; }
            $main[] = [ '@type' => 'Question', 'name' => sanitize_text_field( $item['q'] ),
                        'acceptedAnswer' => [ '@type' => 'Answer', 'text' => wp_kses_post( $item['a'] ) ] ];
        }
        if ( ! $main ) { return null; }
        return [ '@context' => 'https://schema.org', '@type' => 'FAQPage',
                 'name' => html_entity_decode( get_the_title( $post->ID ) ),
                 'url'  => get_permalink( $post->ID ), 'mainEntity' => $main ];
    }

    /* ── HowTo ───────────────────────────────────── */
    private static function howto( $post ) {
        $raw   = get_post_meta( $post->ID, '_ras_steps', true );
        $steps = json_decode( stripslashes( (string) $raw ), true ) ?: [];
        $built = [];
        foreach ( $steps as $i => $step ) {
            if ( empty( $step['name'] ) ) { continue; }
            $built[] = [ '@type' => 'HowToStep', 'position' => $i + 1,
                         'name' => sanitize_text_field( $step['name'] ),
                         'text' => sanitize_textarea_field( $step['text'] ),
                         'url'  => get_permalink( $post->ID ) . '#step-' . ( $i + 1 ) ];
        }
        if ( ! $built ) { return null; }
        $s = [ '@context' => 'https://schema.org', '@type' => 'HowTo',
               'name'  => html_entity_decode( get_the_title( $post->ID ) ),
               'url'   => get_permalink( $post->ID ),
               'step'  => $built ];
        $tt = get_post_meta( $post->ID, '_ras_total_time', true );
        if ( $tt ) { $s['totalTime'] = $tt; }
        $img = self::post_image( $post->ID );
        if ( $img ) { $s['image'] = $img; }
        return $s;
    }

    /* ── Product ──────────────────────────────────── */
    private static function product( $post, $g ) {
        $price = get_post_meta( $post->ID, '_ras_price', true );
        $s = [
            '@context'    => 'https://schema.org', '@type' => 'Product',
            'name'        => html_entity_decode( get_the_title( $post->ID ) ),
            'url'         => get_permalink( $post->ID ),
            'description' => wp_strip_all_tags( get_the_excerpt( $post->ID ) ),
            'brand'       => [ '@type' => 'Brand', 'name' => $g['org_name'] ],
        ];
        $sku = get_post_meta( $post->ID, '_ras_sku', true );
        if ( $sku ) { $s['sku'] = $sku; }
        if ( $price ) {
            $avail = get_post_meta( $post->ID, '_ras_availability', true ) ?: 'InStock';
            $valid  = get_post_meta( $post->ID, '_ras_price_until', true ) ?: gmdate( 'Y-m-d', strtotime( '+1 year' ) );
            $s['offers'] = [
                '@type' => 'Offer', 'price' => (string) $price,
                'priceCurrency' => get_post_meta( $post->ID, '_ras_currency', true ) ?: 'USD',
                'availability' => 'https://schema.org/' . $avail,
                'priceValidUntil' => $valid,
                'url' => get_permalink( $post->ID ),
                'seller' => [ '@type' => 'Organization', 'name' => $g['org_name'] ],
            ];
        }
        $rating = get_post_meta( $post->ID, '_ras_rating', true );
        $rcount = get_post_meta( $post->ID, '_ras_rating_count', true );
        if ( $rating && $rcount ) {
            $s['aggregateRating'] = [ '@type' => 'AggregateRating',
                'ratingValue' => number_format( (float) $rating, 1 ),
                'ratingCount' => (int) $rcount, 'bestRating' => '5', 'worstRating' => '1' ];
        }
        $imgs = self::post_images( $post->ID );
        if ( $imgs ) { $s['image'] = count( $imgs ) > 1 ? $imgs : $imgs[0]; }
        return $s;
    }

    /* ── Event ───────────────────────────────────── */
    private static function event( $post, $g ) {
        $start = get_post_meta( $post->ID, '_ras_event_start', true );
        if ( ! $start ) { return null; }
        $status_map = [
            'EventScheduled'   => 'https://schema.org/EventScheduled',
            'EventCancelled'   => 'https://schema.org/EventCancelled',
            'EventPostponed'   => 'https://schema.org/EventPostponed',
            'EventRescheduled' => 'https://schema.org/EventRescheduled',
            'EventMovedOnline' => 'https://schema.org/EventMovedOnline',
        ];
        $att_map = [
            'OfflineEventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'OnlineEventAttendanceMode'  => 'https://schema.org/OnlineEventAttendanceMode',
            'MixedEventAttendanceMode'   => 'https://schema.org/MixedEventAttendanceMode',
        ];
        $raw_status = get_post_meta( $post->ID, '_ras_event_status', true ) ?: 'EventScheduled';
        $raw_att    = get_post_meta( $post->ID, '_ras_event_attend', true ) ?: 'OfflineEventAttendanceMode';
        $tz         = (float) get_option( 'gmt_offset', 0 );
        $sign       = $tz >= 0 ? '+' : '-';
        $tz_str     = $sign . sprintf( '%02d:00', abs( $tz ) );
        $fmt_start  = date( 'Y-m-d\TH:i:s', strtotime( $start ) ) . $tz_str;

        $s = [
            '@context'            => 'https://schema.org', '@type' => 'Event',
            'name'                => html_entity_decode( get_the_title( $post->ID ) ),
            'startDate'           => $fmt_start,
            'eventStatus'         => $status_map[ $raw_status ] ?? 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => $att_map[ $raw_att ] ?? 'https://schema.org/OfflineEventAttendanceMode',
            'url'                 => get_permalink( $post->ID ),
            'description'         => wp_strip_all_tags( get_the_excerpt( $post->ID ) ),
            'organizer'           => [ '@type' => 'Organization', 'name' => $g['org_name'], 'url' => $g['org_url'] ],
        ];

        $end = get_post_meta( $post->ID, '_ras_event_end', true );
        if ( $end ) { $s['endDate'] = date( 'Y-m-d\TH:i:s', strtotime( $end ) ) . $tz_str; }

        $venue = get_post_meta( $post->ID, '_ras_venue', true );
        if ( 'OnlineEventAttendanceMode' === $raw_att ) {
            $s['location'] = [ '@type' => 'VirtualLocation', 'url' => get_permalink( $post->ID ) ];
        } elseif ( $venue ) {
            $s['location'] = [ '@type' => 'Place', 'name' => $venue,
                'address' => [ '@type' => 'PostalAddress',
                    'streetAddress'   => get_post_meta( $post->ID, '_ras_venue_address', true ),
                    'addressLocality' => get_post_meta( $post->ID, '_ras_venue_city', true ),
                    'addressCountry'  => get_post_meta( $post->ID, '_ras_venue_country', true ) ] ];
        }

        $img = self::post_image( $post->ID );
        if ( $img ) { $s['image'] = $img; }
        return $s;
    }

    /* ── Recipe ──────────────────────────────────── */
    private static function recipe( $post, $g ) {
        $imgs = self::post_images( $post->ID );
        if ( ! $imgs ) { return null; } // image required by Google

        $s = [
            '@context'      => 'https://schema.org', '@type' => 'Recipe',
            'name'          => html_entity_decode( get_the_title( $post->ID ) ),
            'url'           => get_permalink( $post->ID ),
            'description'   => wp_strip_all_tags( get_the_excerpt( $post->ID ) ),
            'image'         => count( $imgs ) > 1 ? $imgs : $imgs[0],
            'author'        => [ '@type' => 'Person', 'name' => get_the_author_meta( 'display_name', $post->post_author ) ],
            'datePublished' => get_the_date( 'c', $post->ID ),
        ];

        $times = [ 'prepTime' => '_ras_prep_time', 'cookTime' => '_ras_cook_time', 'totalTime' => '_ras_total_time' ];
        foreach ( $times as $prop => $key ) {
            $v = get_post_meta( $post->ID, $key, true );
            if ( $v ) { $s[ $prop ] = $v; }
        }
        $yield = get_post_meta( $post->ID, '_ras_recipe_yield', true );
        if ( $yield ) { $s['recipeYield'] = $yield; }

        $raw_ing = get_post_meta( $post->ID, '_ras_ingredients', true );
        if ( $raw_ing ) {
            $s['recipeIngredient'] = array_values( array_filter( array_map( 'trim', explode( "\n", $raw_ing ) ) ) );
        }

        $raw_steps = get_post_meta( $post->ID, '_ras_steps', true );
        $steps = json_decode( stripslashes( (string) $raw_steps ), true ) ?: [];
        if ( $steps ) {
            $inst = [];
            foreach ( $steps as $i => $step ) {
                if ( empty( $step['name'] ) ) { continue; }
                $inst[] = [ '@type' => 'HowToStep', 'name' => $step['name'],
                            'text' => $step['text'], 'url' => get_permalink( $post->ID ) . '#step-' . ( $i + 1 ) ];
            }
            if ( $inst ) { $s['recipeInstructions'] = $inst; }
        }

        $cal = get_post_meta( $post->ID, '_ras_calories', true );
        if ( $cal ) { $s['nutrition'] = [ '@type' => 'NutritionInformation', 'calories' => $cal . ' calories' ]; }

        $rating = get_post_meta( $post->ID, '_ras_rating', true );
        $rcount = get_post_meta( $post->ID, '_ras_rating_count', true );
        if ( $rating && $rcount ) {
            $s['aggregateRating'] = [ '@type' => 'AggregateRating',
                'ratingValue' => number_format( (float) $rating, 1 ),
                'ratingCount' => (int) $rcount, 'bestRating' => '5', 'worstRating' => '1' ];
        }
        return $s;
    }

    /* ── LocalBusiness ───────────────────────────── */
    private static function local_business( $post, $g ) {
        $url = trailingslashit( $g['org_url'] );
        $s = [
            '@context' => 'https://schema.org', '@type' => 'LocalBusiness',
            '@id'  => $url . '#localbusiness',
            'name' => $g['org_name'], 'url' => $g['org_url'],
        ];
        if ( $g['org_email'] ) { $s['email'] = $g['org_email']; }
        $img = self::post_image( $post->ID );
        if ( $img ) { $s['image'] = $img; }
        return $s;
    }

    /* ── WebPage fallback ────────────────────────── */
    private static function webpage( $post, $g ) {
        $url = trailingslashit( $g['org_url'] );
        return [
            '@context'      => 'https://schema.org', '@type' => 'WebPage',
            '@id'           => get_permalink( $post->ID ) . '#webpage',
            'name'          => html_entity_decode( get_the_title( $post->ID ) ),
            'url'           => get_permalink( $post->ID ),
            'datePublished' => get_the_date( 'c', $post->ID ),
            'dateModified'  => get_the_modified_date( 'c', $post->ID ),
            'isPartOf'      => [ '@type' => 'WebSite', '@id' => $url . '#website' ],
            'publisher'     => [ '@type' => 'Organization', '@id' => $url . '#organization' ],
        ];
    }

    /* ── Image helpers ───────────────────────────── */
    private static function post_image( $post_id ) {
        if ( ! has_post_thumbnail( $post_id ) ) { return null; }
        $tid = get_post_thumbnail_id( $post_id );
        $src = wp_get_attachment_image_src( $tid, 'large' );
        if ( ! $src ) { return null; }
        return [ '@type' => 'ImageObject', 'url' => $src[0], 'width' => $src[1], 'height' => $src[2] ];
    }

    private static function post_images( $post_id ) {
        if ( ! has_post_thumbnail( $post_id ) ) { return []; }
        $tid  = get_post_thumbnail_id( $post_id );
        $imgs = [];
        foreach ( [ 'full', 'medium_large', 'thumbnail' ] as $size ) {
            $src = wp_get_attachment_image_src( $tid, $size );
            if ( $src ) {
                $imgs[] = [ '@type' => 'ImageObject', 'url' => $src[0], 'width' => $src[1], 'height' => $src[2] ];
            }
        }
        return array_unique( $imgs, SORT_REGULAR );
    }

    private static function image_dims( $url ) {
        $id = attachment_url_to_postid( $url );
        if ( ! $id ) { return null; }
        $m = wp_get_attachment_metadata( $id );
        return ( $m && isset( $m['width'] ) ) ? [ $m['width'], $m['height'] ] : null;
    }
}

RAS_Frontend::init();
