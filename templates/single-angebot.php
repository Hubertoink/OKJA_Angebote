<?php
/**
 * Single template for Angebot (JHH Posts Block plugin)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $post;
get_header();

while ( have_posts() ) : the_post();
    $post_id = get_the_ID();
    // ensure plugin styles are available on single view
    if ( function_exists('wp_enqueue_style') ) {
        wp_enqueue_style( 'jhh-posts-block-style' );
    }
    $hero_url = get_the_post_thumbnail_url( $post_id, 'full' );
    $back_param = isset( $_GET['back'] ) ? esc_url_raw( wp_unslash( $_GET['back'] ) ) : '';
    $back_href = $back_param ? $back_param : get_post_type_archive_link( 'angebot' );
?>

<main class="jhh-single-angebot" id="main">
    <?php if ( $hero_url ) : ?>
    <section class="jhh-hero" style="background-image:url('<?php echo esc_url( $hero_url ); ?>')">
        <div class="jhh-hero-overlay">
            <h1 class="jhh-hero-title"><?php the_title(); ?></h1>
        </div>
    </section>
    <?php endif; ?>

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
                    $staff_style = get_post_meta( $post_id, 'jhh_staff_card_style', true );
                    // Allow: simple | notebook | aurora (fallback: notebook)
                    $staff_style = in_array( $staff_style, [ 'simple', 'notebook', 'aurora' ], true ) ? $staff_style : 'notebook';
                    $style_map   = [
                        'simple'   => 'bg-simple',
                        'notebook' => 'bg-notebook',
                        'aurora'   => 'bg-aurora',
                    ];
                    $style_class = $style_map[ $staff_style ] ?? 'bg-notebook';
                ?>
                <div class="jhh-staff-card <?php echo esc_attr( $style_class ); ?>">
                    <span class="jhh-staff-topline" aria-hidden="true"></span>
                    <div class="jhh-staff-inner">
                        <?php if ( $img ) echo $img; ?>
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
                    </div>
                    <?php if ( $has_bio ) : ?><div class="jhh-staff-bio"><?php echo wp_kses_post( wpautop( $bio ) ); ?></div><?php endif; ?>
                </div>
                <?php
                $cards_html .= ob_get_clean();
            endforeach;
            if ( $cards_html ) : ?>
                <section class="jhh-staff">
                    <?php echo $cards_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </section>
            <?php endif; ?>
        <?php endif; ?>

        <?php
        // Similar offers – share at least one Pädagogik term
        $paed_terms = taxonomy_exists( JHH_TAX_PAED ) ? wp_list_pluck( (array) get_the_terms( $post_id, JHH_TAX_PAED ), 'term_id' ) : [];
        if ( $paed_terms ) {
            $rel = new WP_Query([
                'post_type' => 'angebot',
                'posts_per_page' => 6,
                'post__not_in' => [ $post_id ],
                'tax_query' => [[
                    'taxonomy' => JHH_TAX_PAED,
                    'field' => 'term_id',
                    'terms' => array_map('intval', $paed_terms),
                    'operator' => 'IN'
                ]],
                'no_found_rows' => true
            ]);
            if ( $rel->have_posts() ) {
                echo '<section class="jhh-related"><h3>Ähnliche Angebote</h3><div class="jhh-related-grid">';
                while ( $rel->have_posts() ) { $rel->the_post();
                    $img = get_the_post_thumbnail( get_the_ID(), 'medium', [ 'class' => 'jhh-related-thumb' ] );
                    printf('<a class="jhh-related-item" href="%s">%s<span class="jhh-related-title">%s</span></a>', esc_url( get_permalink() ), $img ?: '', esc_html( get_the_title() ));
                }
                echo '</div></section>';
                wp_reset_postdata();
            }
        }
        ?>
        
        <p><a class="jhh-back-link" href="<?php echo esc_url( $back_href ); ?>">← Zurück zu allen Angeboten</a></p>
    </div>
</main>

<?php endwhile; get_footer();
