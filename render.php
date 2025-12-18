<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function jhh_pb_render( $attrs, $content ) {
    $a = wp_parse_args( $attrs, [
        'postType' => 'angebot',
        'postsToShow' => 6,
        'order' => 'DESC',
        'orderBy' => 'date',
        'layout' => 'grid',
        'columns' => 3,
        'gap' => 16,
        'showImage' => true,
        'imageSize' => 'medium',
        'imageHoverEffect' => 'none',
        'showTitle' => true,
        'showDate' => true,
        'showAuthor' => false,
        'showExcerpt' => true,
        'excerptLength' => 20,
        'showReadMore' => true,
        'showGradientLine' => false,
        'gradientMarginTop' => 16,
        'gradientMarginBottom' => 16,
        'termsJugend' => [],
        'termsPaed' => [],
        'termsTage' => [],
        'showTaxonomies' => false,
        'showTaxJugend' => true,
        'showTaxPaed' => true,
        'showTaxTage' => true,
        'elementsOrder' => [ 'image', 'title', 'meta', 'taxonomies', 'excerpt', 'readmore', 'gradientline' ],

        // Style defaults
        'colorTitle' => '',
        'colorTitleHover' => '',
        'colorText' => '',
        'colorReadMore' => '',
        'colorReadMoreHover' => '',
        'colorBadgeJugBg' => '',
        'colorBadgeJugTxt' => '',
        'colorBadgePaedBg' => '',
        'colorBadgePaedTxt' => '',
        'colorBadgeTageBg' => '',
        'colorBadgeTageTxt' => '',
    ] );

    // Tax Query
    $tax_query = [];
    if ( ! empty( $a['termsJugend'] ) && taxonomy_exists( JHH_TAX_JUGEND ) ) {
        $tax_query[] = [
            'taxonomy' => JHH_TAX_JUGEND,
            'field'    => 'term_id',
            'terms'    => array_map( 'intval', $a['termsJugend'] ),
        ];
    }
    if ( ! empty( $a['termsPaed'] ) && taxonomy_exists( JHH_TAX_PAED ) ) {
        $tax_query[] = [
            'taxonomy' => JHH_TAX_PAED,
            'field'    => 'term_id',
            'terms'    => array_map( 'intval', $a['termsPaed'] ),
        ];
    }
    if ( ! empty( $a['termsTage'] ) && taxonomy_exists( JHH_TAX_TAGE ) ) {
        $tax_query[] = [
            'taxonomy' => JHH_TAX_TAGE,
            'field'    => 'term_id',
            'terms'    => array_map( 'intval', $a['termsTage'] ),
        ];
    }
    if ( count( $tax_query ) > 1 ) {
        $tax_query['relation'] = 'AND';
    }

    // Query
    $q_args = [
        'post_type'           => sanitize_key( $a['postType'] ),
        'posts_per_page'      => (int) $a['postsToShow'],
        'orderby'             => sanitize_key( $a['orderBy'] ),
        'order'               => $a['order'] === 'ASC' ? 'ASC' : 'DESC',
        'ignore_sticky_posts' => true,
    ];
    if ( ! empty( $tax_query ) ) {
        $q_args['tax_query'] = $tax_query;
    }

    $q = new WP_Query( $q_args );
    if ( ! $q->have_posts() ) {
        return '<div class="jhh-posts-block jhh-empty">Keine Beiträge gefunden.</div>';
    }

    // CSS-Variablen aus Block-Attributen (optional für globale Farben)
    $styleVars = '';
    foreach ([
        'colorTitle'         => '--jhh-title-color',
        'colorTitleHover'    => '--jhh-title-hover',
        'colorText'          => '--jhh-text-color',
        'colorReadMore'      => '--jhh-readmore-color',
        'colorReadMoreHover' => '--jhh-readmore-hover',
        'colorBadgeJugBg'    => '--jhh-badge-jug-bg',
        'colorBadgeJugTxt'   => '--jhh-badge-jug-txt',
        'colorBadgePaedBg'   => '--jhh-badge-paed-bg',
        'colorBadgePaedTxt'  => '--jhh-badge-paed-txt',
        'colorBadgeTageBg'   => '--jhh-badge-tage-bg',
        'colorBadgeTageTxt'  => '--jhh-badge-tage-txt',
    ] as $attr => $var) {
        if ( ! empty( $a[$attr] ) ) {
            $styleVars .= $var . ':' . esc_attr( $a[$attr] ) . ';';
        }
    }

    ob_start(); ?>
    <div class="jhh-posts-block jhh-layout-<?php echo esc_attr( $a['layout'] ); ?>"
         style="--jhh-cols:<?php echo (int) $a['columns']; ?>; --jhh-gap:<?php echo (int) $a['gap']; ?>px;<?php echo $styleVars; ?>">
        <?php while ( $q->have_posts() ) : $q->the_post(); ?>
            <article class="jhh-card">
                <?php
                foreach ( (array) $a['elementsOrder'] as $el ) {
                    switch ( $el ) {
                        case 'image':
                            if ( $a['showImage'] && has_post_thumbnail() ) {
                                $hover_class = '';
                                if ( ! empty( $a['imageHoverEffect'] ) && $a['imageHoverEffect'] !== 'none' ) {
                                    $hover_class = ' jhh-hover-' . esc_attr( $a['imageHoverEffect'] );
                                }
                                echo '<a class="jhh-thumb' . $hover_class . '" href="' . esc_url( get_permalink() ) . '">';
                                the_post_thumbnail( esc_attr( $a['imageSize'] ) );
                                echo '</a>';
                            }
                            break;

                        case 'title':
                            if ( $a['showTitle'] ) {
                                echo '<h3 class="jhh-title"><a href="' . esc_url( get_permalink() ) . '">'
                                    . esc_html( get_the_title() ) . '</a></h3>';
                            }
                            break;

                        case 'meta':
                            if ( $a['showDate'] || $a['showAuthor'] ) {
                                echo '<div class="jhh-meta">';
                                if ( $a['showDate'] ) {
                                    echo '<span class="jhh-date">' . esc_html( get_the_date() ) . '</span>';
                                }
                                if ( $a['showAuthor'] ) {
                                    echo '<span class="jhh-author">' . esc_html( get_the_author() ) . '</span>';
                                }
                                echo '</div>';
                            }
                            break;

                        case 'taxonomies':
                            if ( $a['showTaxonomies'] ) {
                                $out = '';
                                foreach ([
                                    [ 'show' => $a['showTaxJugend'], 'tax' => JHH_TAX_JUGEND, 'class' => 'jhh-badge-jug' ],
                                    [ 'show' => $a['showTaxPaed'],   'tax' => JHH_TAX_PAED,   'class' => 'jhh-badge-pa' ],
                                    [ 'show' => $a['showTaxTage'],   'tax' => JHH_TAX_TAGE,   'class' => 'jhh-badge-tag' ],
                                ] as $group) {
                                    if ( $group['show'] && taxonomy_exists( $group['tax'] ) ) {
                                        $terms = get_the_terms( get_the_ID(), $group['tax'] );
                                        if ( $terms && ! is_wp_error( $terms ) ) {

                                            // Immer alphabetisch sortieren
                                            usort( $terms, function( $a, $b ) {
                                                return strcmp( $a->name, $b->name );
                                            });

                                            foreach ( $terms as $t ) {
                                                // Standard: kein Inline-Style
                                                $style_attr = '';

                                                // Für Pädagogik-Taxonomie: gespeicherte Picker-Farben als CSS-Variablen ausgeben
                                                
if ( $group['tax'] === JHH_TAX_PAED ) {
    $bg_color  = get_term_meta( $t->term_id, 'badge_bg_color', true );
    $txt_color = get_term_meta( $t->term_id, 'badge_text_color', true );

    $style_attr = '';
    if ( $bg_color || $txt_color ) {
        $styles = [];
        // Slug aufräumen, damit er exakt wie in der CSS-Datei geschrieben wird
        $slug = sanitize_title( $t->slug );

        if ( $bg_color ) {
            // setzt --jhh-term-{slug}-bg
            $styles[] = '--jhh-term-' . $slug . '-bg:' . esc_attr( $bg_color );
        }
        if ( $txt_color ) {
            // setzt --jhh-term-{slug}-color
            $styles[] = '--jhh-term-' . $slug . '-color:' . esc_attr( $txt_color );
        }
        $style_attr = ' style="' . implode( ';', $styles ) . '"';
    }
}

                                                $out .= sprintf(
                                                    '<span class="jhh-badge %s"%s>%s</span>',
                                                    esc_attr( $group['class'] ),
                                                    $style_attr,
                                                    esc_html( $t->name )
                                                );
                                            }
                                        }
                                    }
                                }
                                if ( $out ) {
                                    echo '<div class="jhh-tax-terms">' . $out . '</div>';
                                }
                            }
                            break;

case 'excerpt':
    if ( $a['showExcerpt'] ) {
        echo '<div class="jhh-excerpt">' . esc_html( wp_trim_words( get_the_excerpt(), (int) $a['excerptLength'] ) ) . '</div>';
    }
    break;

case 'readmore':
    if ( $a['showReadMore'] ) {
        echo '<button class="jhh-readmore" onclick="window.location.href=\'' . esc_url( get_permalink() ) . '\'">WEITERLESEN</button>';
    }
    break;

case 'gradientline':
    if ( $a['showGradientLine'] ) {
        $margin_top = isset( $a['gradientMarginTop'] ) ? (int) $a['gradientMarginTop'] : 16;
        $margin_bottom = isset( $a['gradientMarginBottom'] ) ? (int) $a['gradientMarginBottom'] : 16;
        echo '<div class="jhh-gradient-line" style="margin-top: ' . $margin_top . 'px; margin-bottom: ' . $margin_bottom . 'px;"></div>';
    }
    break;

                    }
                }
                ?>
            </article>
        <?php endwhile; wp_reset_postdata(); ?>
    </div>
    <?php
    return ob_get_clean();
}
