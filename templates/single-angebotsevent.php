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
    if ( function_exists( 'jhh_pb_enqueue_frontend_styles' ) ) {
        jhh_pb_enqueue_frontend_styles( [ 'single', 'events' ] );
    }
    if ( function_exists( 'jhh_pb_enqueue_event_modal_assets' ) ) {
        jhh_pb_enqueue_event_modal_assets();
    }

    $max_part     = (int) get_post_meta( $post_id, 'jhh_event_max_participants', true );
    $angebot_id   = (int) get_post_meta( $post_id, 'jhh_event_angebot_id', true );

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

        <?php echo function_exists( 'jhh_pb_get_event_detail_markup' ) ? jhh_pb_get_event_detail_markup( $post_id ) : ''; ?>

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
                    <a class="jhh-related-item" href="<?php echo esc_url( get_permalink( $rel_id ) ); ?>"<?php echo function_exists( 'jhh_pb_get_event_link_attributes' ) ? jhh_pb_get_event_link_attributes( $rel_id ) : ''; ?>>
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
