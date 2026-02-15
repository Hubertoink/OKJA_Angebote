<?php
/**
 * Single template for Angebotsevent (JHH Posts Block plugin)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $post;
get_header();

while ( have_posts() ) : the_post();
    $post_id    = get_the_ID();
    $hero_url   = get_the_post_thumbnail_url( $post_id, 'full' );
    
    // Ensure plugin styles are available
    if ( function_exists( 'wp_enqueue_style' ) ) {
        wp_enqueue_style( 'jhh-posts-block-style' );
    }

    // Event meta
    $event_date   = get_post_meta( $post_id, 'jhh_event_date', true );
    $time_start   = get_post_meta( $post_id, 'jhh_event_time_start', true );
    $time_end     = get_post_meta( $post_id, 'jhh_event_time_end', true );
    $price        = get_post_meta( $post_id, 'jhh_event_price', true );
    $max_part     = (int) get_post_meta( $post_id, 'jhh_event_max_participants', true );
    $angebot_id   = (int) get_post_meta( $post_id, 'jhh_event_angebot_id', true );
    $sold_out     = (bool) get_post_meta( $post_id, 'jhh_event_sold_out', true );

    // Format date
    $date_display = '';
    $date_weekday = '';
    if ( $event_date ) {
        $ts = strtotime( $event_date );
        if ( $ts ) {
            $date_display = wp_date( 'j. F Y', $ts );
            $date_weekday = wp_date( 'l', $ts );
        }
    }

    // Format time
    $time_display = '';
    if ( $time_start && $time_end ) {
        $time_display = esc_html( $time_start ) . ' – ' . esc_html( $time_end ) . ' Uhr';
    } elseif ( $time_start ) {
        $time_display = esc_html( $time_start ) . ' Uhr';
    }

    // Back link
    $back_param = isset( $_GET['back'] ) ? esc_url_raw( wp_unslash( $_GET['back'] ) ) : '';
    $back_href  = $back_param ? $back_param : ( $angebot_id ? get_permalink( $angebot_id ) : get_post_type_archive_link( 'angebotsevent' ) );

    // Hero animation settings (reuse from angebot)
    $hero_animation = get_option( 'okja_hero_animation', 'none' );
    $hero_hover     = get_option( 'okja_hero_hover', 'none' );
    $combined_animations = [ 'cinematic', 'parallax-drift', 'explosive', 'vortex', 'aurora', 'spotlight', 'glitch-storm' ];
    $is_combined = in_array( $hero_animation, $combined_animations, true );
    
    $hero_classes = 'jhh-hero';
    if ( $is_combined && $hero_animation !== 'none' ) {
        $hero_classes .= ' anim-' . esc_attr( $hero_animation );
    }
    $title_classes = 'jhh-hero-title';
    if ( ! $is_combined && $hero_animation !== 'none' ) {
        $title_classes .= ' anim-' . esc_attr( $hero_animation );
    }
    if ( $hero_hover !== 'none' ) {
        $title_classes .= ' hover-' . esc_attr( $hero_hover );
    }
    $title_text = get_the_title();
?>

<main class="jhh-single-event" id="main">
    <?php if ( $hero_url ) : ?>
    <section class="<?php echo esc_attr( $hero_classes ); ?>" style="background-image:url('<?php echo esc_url( $hero_url ); ?>')">
        <div class="jhh-hero-overlay">
            <h1 class="<?php echo esc_attr( $title_classes ); ?>" data-text="<?php echo esc_attr( $title_text ); ?>"><?php echo esc_html( $title_text ); ?></h1>
        </div>
    </section>
    <?php else : ?>
    <section class="jhh-hero jhh-hero--no-image">
        <div class="jhh-hero-overlay">
            <h1 class="jhh-hero-title"><?php echo esc_html( $title_text ); ?></h1>
        </div>
    </section>
    <?php endif; ?>

    <div class="jhh-single-wrap">

        <!-- Event Details Card -->
        <div class="jhh-event-details-card">
            <div class="jhh-event-details-topline"></div>
            <div class="jhh-event-details-grid">
                <?php if ( $date_display ) : ?>
                <div class="jhh-event-detail">
                    <span class="jhh-event-detail-icon">📅</span>
                    <div class="jhh-event-detail-content">
                        <span class="jhh-event-detail-label"><?php esc_html_e( 'Datum', 'jhh-posts-block' ); ?></span>
                        <span class="jhh-event-detail-value"><?php echo esc_html( $date_weekday . ', ' . $date_display ); ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ( $time_display ) : ?>
                <div class="jhh-event-detail">
                    <span class="jhh-event-detail-icon">🕐</span>
                    <div class="jhh-event-detail-content">
                        <span class="jhh-event-detail-label"><?php esc_html_e( 'Uhrzeit', 'jhh-posts-block' ); ?></span>
                        <span class="jhh-event-detail-value"><?php echo $time_display; ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ( $price ) : ?>
                <div class="jhh-event-detail">
                    <span class="jhh-event-detail-icon">💰</span>
                    <div class="jhh-event-detail-content">
                        <span class="jhh-event-detail-label"><?php esc_html_e( 'Preis', 'jhh-posts-block' ); ?></span>
                        <span class="jhh-event-detail-value"><?php echo esc_html( $price ); ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ( $max_part > 0 ) : ?>
                <div class="jhh-event-detail">
                    <span class="jhh-event-detail-icon">👥</span>
                    <div class="jhh-event-detail-content">
                        <span class="jhh-event-detail-label"><?php esc_html_e( 'Teilnehmer', 'jhh-posts-block' ); ?></span>
                        <span class="jhh-event-detail-value"><?php printf( esc_html__( 'max. %d Plätze', 'jhh-posts-block' ), $max_part ); ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ( $sold_out ) : ?>
                <div class="jhh-event-detail jhh-event-detail--sold-out">
                    <span class="jhh-event-detail-icon">🚫</span>
                    <div class="jhh-event-detail-content">
                        <span class="jhh-event-detail-label"><?php esc_html_e( 'Status', 'jhh-posts-block' ); ?></span>
                        <span class="jhh-event-detail-value" style="color:#ff6b6b;"><?php esc_html_e( 'Ausgebucht', 'jhh-posts-block' ); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ( $angebot_id > 0 ) :
                $angebot_title = get_the_title( $angebot_id );
                $angebot_link  = get_permalink( $angebot_id );
                $angebot_bg    = get_the_post_thumbnail_url( $angebot_id, 'large' );
                if ( $angebot_title ) : ?>
                <div class="jhh-event-linked-angebot">
                    <span class="jhh-event-detail-label"><?php esc_html_e( 'Gehört zum Angebot', 'jhh-posts-block' ); ?></span>
                    <a class="jhh-event-angebot-card" href="<?php echo esc_url( $angebot_link ); ?>"<?php if ( $angebot_bg ) : ?> style="background:url('<?php echo esc_url( $angebot_bg ); ?>') center/cover no-repeat;"<?php endif; ?>>
                        <span class="jhh-event-angebot-name"><?php echo esc_html( $angebot_title ); ?></span>
                        <span class="jhh-event-angebot-arrow">→</span>
                    </a>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Content -->
        <article class="jhh-content">
            <?php the_content(); ?>
        </article>

        <?php
        // CTA / Anmeldung Button
        $cta_url   = get_post_meta( $post_id, 'jhh_event_cta_url', true );
        $cta_label = get_post_meta( $post_id, 'jhh_event_cta_label', true );
        if ( $cta_url ) :
            $cta_label = $cta_label ?: __( 'Jetzt anmelden', 'jhh-posts-block' );
            $is_sold_out = $sold_out;
        ?>
        <div class="jhh-event-cta-wrap">
            <a class="jhh-event-cta-btn<?php echo $is_sold_out ? ' jhh-event-cta-btn--disabled' : ''; ?>" href="<?php echo esc_url( $cta_url ); ?>"<?php echo $is_sold_out ? '' : ' target="_blank" rel="noopener noreferrer"'; ?>>
                <?php if ( $is_sold_out ) : ?>
                    <span class="jhh-event-cta-icon">🚫</span>
                    <span><?php esc_html_e( 'Ausgebucht', 'jhh-posts-block' ); ?></span>
                <?php else : ?>
                    <span class="jhh-event-cta-icon">✉️</span>
                    <span><?php echo esc_html( $cta_label ); ?></span>
                <?php endif; ?>
            </a>
        </div>
        <?php endif; ?>

        <?php
        // Weitere Events vom selben Angebot
        if ( $angebot_id > 0 ) {
            $related_events = new WP_Query( [
                'post_type'      => 'angebotsevent',
                'posts_per_page' => 4,
                'post__not_in'   => [ $post_id ],
                'post_status'    => 'publish',
                'meta_query'     => [
                    'relation' => 'AND',
                    [
                        'key'     => 'jhh_event_angebot_id',
                        'value'   => $angebot_id,
                        'compare' => '=',
                        'type'    => 'NUMERIC',
                    ],
                    [
                        'key'     => 'jhh_event_date',
                        'value'   => current_time( 'Y-m-d' ),
                        'compare' => '>=',
                        'type'    => 'DATE',
                    ],
                ],
                'meta_key'       => 'jhh_event_date',
                'orderby'        => 'meta_value',
                'order'          => 'ASC',
                'no_found_rows'  => true,
            ] );

            if ( $related_events->have_posts() ) :
            ?>
            <section class="jhh-related-events">
                <h3><?php esc_html_e( 'Weitere Events', 'jhh-posts-block' ); ?></h3>
                <div class="jhh-related-events-grid">
                    <?php while ( $related_events->have_posts() ) : $related_events->the_post();
                        $rel_id     = get_the_ID();
                        $rel_date   = get_post_meta( $rel_id, 'jhh_event_date', true );
                        $rel_time_s = get_post_meta( $rel_id, 'jhh_event_time_start', true );
                        $rel_price  = get_post_meta( $rel_id, 'jhh_event_price', true );
                        $rel_thumb  = get_the_post_thumbnail( $rel_id, 'medium', [ 'class' => 'jhh-related-thumb' ] );

                        $rel_date_display = '';
                        if ( $rel_date ) {
                            $rts = strtotime( $rel_date );
                            if ( $rts ) $rel_date_display = wp_date( 'j. M Y', $rts );
                        }
                    ?>
                    <a class="jhh-related-item" href="<?php echo esc_url( get_permalink( $rel_id ) ); ?>">
                        <?php echo $rel_thumb ?: ''; ?>
                        <div class="jhh-related-content">
                            <span class="jhh-related-title"><?php echo esc_html( get_the_title() ); ?></span>
                            <div class="jhh-related-hover" style="transform:translateY(0);opacity:1;position:relative;">
                                <?php if ( $rel_date_display ) : ?>
                                <div class="jhh-related-schedule"><span class="jhh-related-icon">📅</span><?php echo esc_html( $rel_date_display ); ?><?php if ( $rel_time_s ) echo ' ' . esc_html( $rel_time_s ) . ' Uhr'; ?></div>
                                <?php endif; ?>
                                <?php if ( $rel_price ) : ?>
                                <div class="jhh-related-schedule"><span class="jhh-related-icon">💰</span><?php echo esc_html( $rel_price ); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>
            </section>
            <?php
            endif;
        }
        ?>

    </div>
</main>

<?php endwhile; get_footer();
