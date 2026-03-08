<?php
/**
 * Single template for Angebot (JHH Posts Block plugin)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $post;
get_header();

while ( have_posts() ) : the_post();
    $post_id = get_the_ID();
    $single_style_groups = function_exists( 'jhh_pb_get_single_angebot_style_groups' )
        ? jhh_pb_get_single_angebot_style_groups( $post_id )
        : [ 'single', 'team', 'events' ];

    // ensure plugin styles are available on single view
    if ( function_exists( 'jhh_pb_enqueue_frontend_styles' ) ) {
        jhh_pb_enqueue_frontend_styles( $single_style_groups );
    }
    if ( in_array( 'events', $single_style_groups, true ) && function_exists( 'jhh_pb_enqueue_event_modal_assets' ) ) {
        jhh_pb_enqueue_event_modal_assets();
    }
    $back_param = isset( $_GET['back'] ) ? esc_url_raw( wp_unslash( $_GET['back'] ) ) : '';
    $back_href = $back_param ? $back_param : get_post_type_archive_link( 'angebot' );
    
    // Get hero animation settings
    $hero_animation = get_option( 'okja_hero_animation', 'none' );
    $hero_hover = get_option( 'okja_hero_hover', 'none' );
    
    // Opulente kombinierte Animationen (werden auf den Hero-Container angewendet)
    $combined_animations = [ 'cinematic', 'parallax-drift', 'explosive', 'vortex', 'aurora', 'spotlight', 'glitch-storm' ];
    $is_combined = in_array( $hero_animation, $combined_animations, true );
    
    // Hero container classes
    $hero_classes = 'jhh-hero';
    if ( $is_combined && $hero_animation !== 'none' ) {
        $hero_classes .= ' anim-' . esc_attr( $hero_animation );
    }
    
    // Title classes (nur bei einfachen Animationen)
    $title_classes = 'jhh-hero-title';
    if ( ! $is_combined && $hero_animation !== 'none' ) {
        $title_classes .= ' anim-' . esc_attr( $hero_animation );
    }
    if ( $hero_hover !== 'none' ) {
        $title_classes .= ' hover-' . esc_attr( $hero_hover );
    }
    $title_text = get_the_title();
?>

<main class="jhh-single-angebot" id="main">
    <?php
    if ( function_exists( 'jhh_pb_render_single_hero_markup' ) ) {
        echo jhh_pb_render_single_hero_markup( $post_id, [
            'section_class'     => $hero_classes,
            'title_class'       => $title_classes,
            'title_text'        => $title_text,
            'image_size'        => 'large',
            'sizes'             => '100vw',
            'eager'             => true,
            'include_data_text' => true,
        ] );
    }
    ?>

    <div class="jhh-single-wrap">
        <?php
        // Badges
        $badges = [];
        // Jugendarbeit & Pädagogik wie gehabt über Taxonomie
        foreach ( [ JHH_TAX_JUGEND => 'jhh-badge-jug', JHH_TAX_PAED => 'jhh-badge-pa' ] as $tax => $cls ) {
            if ( taxonomy_exists( $tax ) ) {
                $terms = get_the_terms( $post_id, $tax );
                if ( $terms && ! is_wp_error( $terms ) ) {
                    foreach ( $terms as $t ) {
                        $style_attr = '';
                        if ( $tax === JHH_TAX_PAED ) {
                            $slug = sanitize_title( $t->slug );
                            $bg   = get_term_meta( $t->term_id, 'badge_bg_color', true );
                            $txt  = get_term_meta( $t->term_id, 'badge_text_color', true );
                            $styles = [];
                            if ( $bg )  $styles[] = '--jhh-term-' . $slug . '-bg:' . esc_attr( $bg );
                            if ( $txt ) $styles[] = '--jhh-term-' . $slug . '-color:' . esc_attr( $txt );
                            if ( $bg )  $styles[] = '--jhh-badge-pa-bg:' . esc_attr( $bg );
                            if ( $txt ) $styles[] = '--jhh-badge-pa-color:' . esc_attr( $txt );
                            if ( $styles ) $style_attr = ' style="' . implode( ';', $styles ) . '"';
                        }
                        $badges[] = sprintf('<span class="jhh-badge %s term-%s"%s>%s</span>', esc_attr( $cls ), esc_attr( $t->slug ), $style_attr, esc_html( $t->name ) );
                    }
                }
            }
        }

        // Tage jetzt aus Post-Meta (fest implementierte Wochentage)
        $days = get_post_meta( $post_id, 'jhh_days', true );
        $times = get_post_meta( $post_id, 'jhh_day_times', true );
        $days = is_array( $days ) ? $days : [];
        $times = is_array( $times ) ? $times : [];
        if ( $days ) {
            $labels = [ 'montag' => 'Montag', 'dienstag' => 'Dienstag', 'mittwoch' => 'Mittwoch', 'donnerstag' => 'Donnerstag', 'freitag' => 'Freitag', 'samstag' => 'Samstag', 'sonntag' => 'Sonntag' ];
            $weekday_order = [ 'montag'=>1,'dienstag'=>2,'mittwoch'=>3,'donnerstag'=>4,'freitag'=>5,'samstag'=>6,'sonntag'=>7 ];
            usort( $days, function($a,$b) use($weekday_order){ return ($weekday_order[$a] ?? 99) <=> ($weekday_order[$b] ?? 99); });
            foreach ( $days as $key ) {
                $label = $labels[$key] ?? ucfirst( $key );
                $start = isset( $times[$key]['start'] ) ? trim( (string) $times[$key]['start'] ) : '';
                $end   = isset( $times[$key]['end'] )   ? trim( (string) $times[$key]['end'] )   : '';
                $text  = $label;
                if ( $start && $end ) {
                    $text = sprintf( '%s %s – %s', $label, esc_html( $start ), esc_html( $end ) );
                }
                $badges[] = '<span class="jhh-badge jhh-badge-ta">' . esc_html( $text ) . '</span>';
            }
        }
        if ( $badges ) echo '<div class="jhh-post-taxonomies">' . implode('', $badges) . '</div>';
        ?>

        <article class="jhh-content">
            <?php the_content(); ?>
        </article>

        <?php
        // Staff profiles (Jugendarbeit terms) – nur anzeigen, wenn Bio oder Kontakt vorhanden
        $staff_terms = taxonomy_exists( JHH_TAX_JUGEND ) ? get_the_terms( $post_id, JHH_TAX_JUGEND ) : [];
        if ( $staff_terms && ! is_wp_error( $staff_terms ) ) :
            $cards_html = '';
            foreach ( $staff_terms as $t ) :
                $avatar_id = (int) get_term_meta( $t->term_id, 'avatar_id', true );
                $funktion  = get_term_meta( $t->term_id, 'funktion', true );
                $bio       = get_term_meta( $t->term_id, 'bio', true );
                $contact   = get_term_meta( $t->term_id, 'contact', true );
                $has_bio   = is_string( $bio ) ? trim( wp_strip_all_tags( $bio ) ) !== '' : ! empty( $bio );
                $has_contact = is_string( $contact ) ? trim( $contact ) !== '' : ! empty( $contact );

                // Nur Karte rendern, wenn Bio ODER Kontakt vorhanden ist
                if ( ! $has_bio && ! $has_contact ) {
                    continue;
                }

                $img = $avatar_id ? wp_get_attachment_image( $avatar_id, 'medium', false, [ 'class' => 'jhh-staff-avatar' ] ) : '';

                ob_start();
                ?>
                <?php
                    // Get staff style: use post-specific if set, otherwise global default
                    $post_style = get_post_meta( $post_id, 'jhh_staff_card_style', true );
                    $global_default = get_option( 'okja_default_staff_style', 'simple' );
                    
                    // If post style is empty or 'global', use the global default
                    if ( empty( $post_style ) || $post_style === 'global' ) {
                        $staff_style = $global_default;
                    } else {
                        $staff_style = $post_style;
                    }
                    
                    // Allow: simple | notebook | aurora | grainy-1..3 | custom (fallback: simple)
                    $staff_style = in_array( $staff_style, [ 'simple', 'notebook', 'aurora', 'grainy-1', 'grainy-2', 'grainy-3', 'custom' ], true ) ? $staff_style : 'simple';
                    $style_map   = [
                        'simple'   => 'bg-simple',
                        'notebook' => 'bg-notebook',
                        'aurora'   => 'bg-aurora',
                        'grainy-1'  => 'bg-grainy-1',
                        'grainy-2'  => 'bg-grainy-2',
                        'grainy-3'  => 'bg-grainy-3',
                        'custom'   => 'bg-custom',
                    ];
                    $style_class = $style_map[ $staff_style ] ?? 'bg-simple';
                    
                    // Custom inline styles for custom mode
                    $custom_inline = '';
                    if ( $staff_style === 'custom' ) {
                        $bg_color = get_option( 'okja_staff_bg_color', '#2b2727' );
                        $text_color = get_option( 'okja_staff_text_color', '#ffffff' );
                        $accent_color = get_option( 'okja_staff_accent_color', '#b9aaff' );
                        $custom_inline = sprintf(
                            '--okja-bg:%s;--okja-text:%s;--okja-accent:%s;',
                            esc_attr( $bg_color ),
                            esc_attr( $text_color ),
                            esc_attr( $accent_color )
                        );
                    }
                ?>
                <div class="jhh-staff-card <?php echo esc_attr( $style_class ); ?>"<?php echo $custom_inline ? ' style="' . $custom_inline . '"' : ''; ?>>
                    <div class="jhh-staff-inner">
                        <?php if ( $img ) echo $img; ?>
                    </div>
                    <div class="jhh-staff-content">
                        <div class="jhh-staff-meta">
                            <h2 class="jhh-staff-name"><?php echo esc_html( $t->name ); ?></h2>
                            <?php if ( $funktion ) : ?><div class="jhh-staff-role"><?php echo esc_html( $funktion ); ?></div><?php endif; ?>
                            <?php if ( $has_contact ) : ?>
                                <?php
                                // Kontakt als E-Mail-Link ausgeben, wenn er wie eine E-Mail aussieht
                                $c_raw   = trim( (string) $contact );
                                // Anzeige-Text: (at), [at], " at " durch @ ersetzen
                                $display = preg_replace( '/\s*(\(at\)|\[at\]|\sat\s)\s*/i', '@', $c_raw );
                                // Kandidat für mailto: – Sonderformen in echtes @ wandeln und Whitespace entfernen
                                $candidate = preg_replace( '/\s*(\(at\)|\[at\]|\sat\s)\s*/i', '@', $c_raw );
                                $candidate = preg_replace( '/\s+/', '', $candidate );
                                // Falls ein führendes mailto: enthalten ist, entfernen
                                $candidate = preg_replace( '/^mailto:/i', '', $candidate );
                                $email     = sanitize_email( $candidate );
                                if ( $email && strpos( $email, '@' ) !== false ) : ?>
                                    <a class="jhh-staff-contact" href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $display ); ?></a>
                                <?php else : ?>
                                    <div class="jhh-staff-contact"><?php echo esc_html( $display ); ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php if ( $has_bio ) : ?><div class="jhh-staff-bio"><?php echo wp_kses_post( wpautop( $bio ) ); ?></div><?php endif; ?>
                    </div>
                </div>
                <?php
                $cards_html .= ob_get_clean();
            endforeach;
            if ( $cards_html ) : ?>
                <?php
                $staff_count = is_array( $staff_terms ) ? count( $staff_terms ) : 0;
                $staff_layout_class = '';
                if ( $staff_count === 1 ) {
                    $staff_layout_class = 'jhh-staff--1';
                } elseif ( $staff_count === 2 ) {
                    $staff_layout_class = 'jhh-staff--2';
                }
                ?>
                <section class="jhh-staff <?php echo esc_attr( $staff_layout_class ); ?>">
                    <?php echo $cards_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </section>
            <?php endif; ?>
        <?php endif; ?>

        <?php
        $show_events_in_angebot = get_option( 'okja_events_show_in_angebot', '1' );
        if ( $show_events_in_angebot === '1' ) :
            $today_ts    = current_time( 'timestamp' );
            $today       = wp_date( 'Y-m-d', $today_ts );
            $future_days = (int) get_option( 'okja_events_future_days', 365 );
            $past_days   = (int) get_option( 'okja_events_past_days', 0 );
            $start_date  = $past_days > 0 ? wp_date( 'Y-m-d', strtotime( '-' . $past_days . ' days', $today_ts ) ) : $today;
            $end_date    = $future_days > 0 ? wp_date( 'Y-m-d', strtotime( '+' . $future_days . ' days', $today_ts ) ) : '';

            $event_meta_query = [
                [
                    'key'     => 'jhh_event_angebot_id',
                    'value'   => $post_id,
                    'compare' => '=',
                ],
                [
                    'key'     => 'jhh_event_date',
                    'value'   => $start_date,
                    'compare' => '>=',
                    'type'    => 'DATE',
                ],
            ];

            if ( $end_date ) {
                $event_meta_query[] = [
                    'key'     => 'jhh_event_date',
                    'value'   => $end_date,
                    'compare' => '<=',
                    'type'    => 'DATE',
                ];
            }

            $angebot_events = new WP_Query( [
                'post_type'           => 'angebotsevent',
                'post_status'         => 'publish',
                'posts_per_page'      => -1,
                'ignore_sticky_posts' => true,
                'no_found_rows'       => true,
                'meta_key'            => 'jhh_event_date',
                'orderby'             => 'meta_value',
                'order'               => 'ASC',
                'meta_query'          => $event_meta_query,
            ] );

            if ( $angebot_events->have_posts() ) :
        ?>
            <section class="jhh-events-section">
                <h3><?php esc_html_e( 'Passende A-Events', 'jhh-posts-block' ); ?></h3>
                <div class="jhh-events-inline-grid">
                    <?php while ( $angebot_events->have_posts() ) : $angebot_events->the_post(); ?>
                        <?php
                        $event_id          = get_the_ID();
                        $event_date        = get_post_meta( $event_id, 'jhh_event_date', true );
                        $event_time_start  = get_post_meta( $event_id, 'jhh_event_time_start', true );
                        $event_time_end    = get_post_meta( $event_id, 'jhh_event_time_end', true );
                        $event_price       = get_post_meta( $event_id, 'jhh_event_price', true );
                        $event_max         = (int) get_post_meta( $event_id, 'jhh_event_max_participants', true );
                        $event_sold_out    = (bool) get_post_meta( $event_id, 'jhh_event_sold_out', true );
                        $event_excerpt     = has_excerpt( $event_id ) ? get_the_excerpt() : wp_trim_words( wp_strip_all_tags( get_the_content( null, false, $event_id ) ), 24 );
                        $event_thumb       = get_the_post_thumbnail( $event_id, 'medium_large', [ 'class' => 'jhh-event-inline-thumb' ] );
                        $event_timestamp   = $event_date ? strtotime( $event_date ) : false;
                        $event_day         = $event_timestamp ? wp_date( 'd', $event_timestamp ) : '–';
                        $event_month       = $event_timestamp ? wp_date( 'M', $event_timestamp ) : __( 'Offen', 'jhh-posts-block' );
                        $event_card_class  = 'jhh-event-inline-card';
                        $event_meta_items  = [];

                        if ( $event_timestamp && $event_timestamp < strtotime( $today ) ) {
                            $event_card_class .= ' jhh-event--past';
                        }

                        if ( $event_sold_out ) {
                            $event_card_class .= ' jhh-event--sold-out';
                        }

                        if ( $event_date ) {
                            $date_label = $event_timestamp ? wp_date( 'D, j. M Y', $event_timestamp ) : $event_date;
                            $event_meta_items[] = '<span>📅 ' . esc_html( $date_label ) . '</span>';
                        }

                        if ( $event_time_start && $event_time_end ) {
                            $event_meta_items[] = '<span>🕐 ' . esc_html( $event_time_start . ' - ' . $event_time_end . ' Uhr' ) . '</span>';
                        } elseif ( $event_time_start ) {
                            $event_meta_items[] = '<span>🕐 ' . esc_html( $event_time_start . ' Uhr' ) . '</span>';
                        }

                        if ( $event_price ) {
                            $event_meta_items[] = '<span>💰 ' . esc_html( $event_price ) . '</span>';
                        }

                        if ( $event_sold_out ) {
                            $event_meta_items[] = '<span>' . esc_html__( 'Ausgebucht', 'jhh-posts-block' ) . '</span>';
                        } elseif ( $event_max > 0 ) {
                            $event_meta_items[] = '<span>👥 ' . esc_html( sprintf( __( 'max. %d Plätze', 'jhh-posts-block' ), $event_max ) ) . '</span>';
                        }
                        ?>
                        <a class="<?php echo esc_attr( $event_card_class ); ?>" href="<?php the_permalink(); ?>"<?php echo function_exists( 'jhh_pb_get_event_link_attributes' ) ? jhh_pb_get_event_link_attributes( $event_id ) : ''; ?>>
                            <?php if ( $event_sold_out ) : ?>
                                <span class="jhh-event-sold-out-banner"><?php esc_html_e( 'Ausgebucht', 'jhh-posts-block' ); ?></span>
                            <?php endif; ?>
                            <?php if ( $event_thumb ) : ?>
                                <div class="jhh-event-inline-image">
                                    <?php echo $event_thumb; ?>
                                    <?php if ( $event_excerpt ) : ?>
                                        <div class="jhh-event-hover-desc"><p><?php echo esc_html( $event_excerpt ); ?></p></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="jhh-event-inline-body">
                                <div class="jhh-event-inline-date-block">
                                    <span class="jhh-event-inline-day"><?php echo esc_html( $event_day ); ?></span>
                                    <span class="jhh-event-inline-month"><?php echo esc_html( $event_month ); ?></span>
                                </div>
                                <div class="jhh-event-inline-info">
                                    <div class="jhh-event-inline-title"><?php the_title(); ?></div>
                                    <?php if ( $event_meta_items ) : ?>
                                        <div class="jhh-event-inline-meta"><?php echo wp_kses_post( implode( '', $event_meta_items ) ); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endwhile; ?>
                </div>
            </section>
        <?php
            endif;
            wp_reset_postdata();
        endif;
        ?>

        <?php
        // Weitere Angebote – zeige alle anderen Angebote (max 5), zufällig sortiert
        $rel_args = [
            'post_type' => 'angebot',
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'post__not_in' => [ $post_id ],
            'ignore_sticky_posts' => true,
            'no_found_rows' => true,
            'orderby' => 'rand'
        ];
        
        $rel = new WP_Query( $rel_args );
        
        if ( $rel->have_posts() ) {
            echo '<section class="jhh-related"><h3>Weitere Angebote</h3><div class="jhh-related-grid">';
            while ( $rel->have_posts() ) { $rel->the_post();
                $rel_post_id = get_the_ID();
                $img = get_the_post_thumbnail( $rel_post_id, 'medium', [ 'class' => 'jhh-related-thumb' ] );
                
                // Pass the back parameter to related offer links
                $related_link = get_permalink();
                if ( $back_param ) {
                    $related_link = add_query_arg( 'back', urlencode( $back_param ), $related_link );
                }
                
                // Hole Mitarbeiter (Jugendarbeit Terms) mit Avataren
                $rel_staff = [];
                if ( taxonomy_exists( JHH_TAX_JUGEND ) ) {
                    $rel_terms = get_the_terms( $rel_post_id, JHH_TAX_JUGEND );
                    if ( $rel_terms && ! is_wp_error( $rel_terms ) ) {
                        foreach ( $rel_terms as $rt ) {
                            $avatar_id = (int) get_term_meta( $rt->term_id, 'avatar_id', true );
                            $avatar_html = '';
                            if ( $avatar_id ) {
                                $avatar_url = wp_get_attachment_image_url( $avatar_id, 'thumbnail' );
                                if ( $avatar_url ) {
                                    $avatar_html = '<img class="jhh-related-avatar" src="' . esc_url( $avatar_url ) . '" alt="' . esc_attr( $rt->name ) . '">';
                                }
                            }
                            $rel_staff[] = [
                                'name' => esc_html( $rt->name ),
                                'avatar' => $avatar_html
                            ];
                        }
                    }
                }
                
                // Hole Uhrzeiten
                $rel_days = get_post_meta( $rel_post_id, 'jhh_days', true );
                $rel_times = get_post_meta( $rel_post_id, 'jhh_day_times', true );
                $rel_days = is_array( $rel_days ) ? $rel_days : [];
                $rel_times = is_array( $rel_times ) ? $rel_times : [];
                $rel_schedule = [];
                if ( $rel_days ) {
                    $day_labels = [ 'montag' => 'Mo', 'dienstag' => 'Di', 'mittwoch' => 'Mi', 'donnerstag' => 'Do', 'freitag' => 'Fr', 'samstag' => 'Sa', 'sonntag' => 'So' ];
                    $weekday_order = [ 'montag'=>1,'dienstag'=>2,'mittwoch'=>3,'donnerstag'=>4,'freitag'=>5,'samstag'=>6,'sonntag'=>7 ];
                    usort( $rel_days, function($a,$b) use($weekday_order){ return ($weekday_order[$a] ?? 99) <=> ($weekday_order[$b] ?? 99); });
                    foreach ( $rel_days as $day_key ) {
                        $day_label = $day_labels[$day_key] ?? ucfirst( substr( $day_key, 0, 2 ) );
                        $start = isset( $rel_times[$day_key]['start'] ) ? trim( (string) $rel_times[$day_key]['start'] ) : '';
                        $end   = isset( $rel_times[$day_key]['end'] )   ? trim( (string) $rel_times[$day_key]['end'] )   : '';
                        if ( $start && $end ) {
                            $rel_schedule[] = sprintf( '%s %s–%s', $day_label, $start, $end );
                        } else {
                            $rel_schedule[] = $day_label;
                        }
                    }
                }
                
                // Baue Hover-Overlay nur wenn Daten vorhanden
                $hover_html = '';
                if ( $rel_staff || $rel_schedule ) {
                    $hover_html = '<div class="jhh-related-hover">';
                    if ( $rel_staff ) {
                        $hover_html .= '<div class="jhh-related-staff">';
                        $hover_html .= '<div class="jhh-related-avatars">';
                        foreach ( $rel_staff as $staff_item ) {
                            if ( $staff_item['avatar'] ) {
                                $hover_html .= $staff_item['avatar'];
                            }
                        }
                        $hover_html .= '</div>';
                        $staff_names = array_map( function($s) { return $s['name']; }, $rel_staff );
                        $hover_html .= '<span class="jhh-related-names">' . implode( ', ', $staff_names ) . '</span>';
                        $hover_html .= '</div>';
                    }
                    if ( $rel_schedule ) {
                        $hover_html .= '<div class="jhh-related-schedule"><span class="jhh-related-icon">🕐</span>' . implode( ' | ', $rel_schedule ) . '</div>';
                    }
                    $hover_html .= '</div>';
                }
                
                printf(
                    '<a class="jhh-related-item%s" href="%s">%s<div class="jhh-related-content"><span class="jhh-related-title">%s</span>%s</div></a>',
                    $hover_html ? ' has-hover' : '',
                    esc_url( $related_link ),
                    $img ?: '',
                    esc_html( get_the_title() ),
                    $hover_html
                );
            }
            echo '</div></section>';
            wp_reset_postdata();
        }
        ?>
        
        <p><a class="jhh-back-link" href="<?php echo esc_url( $back_href ); ?>">← Zurück zu allen Angeboten</a></p>
    </div>
</main>

<?php endwhile; get_footer();
