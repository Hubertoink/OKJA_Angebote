<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function okja_get_dashboard_page_slug() {
    return 'okja-angebote-dashboard';
}

function okja_get_dashboard_page_url( $args = [] ) {
    $base_args = [
        'post_type' => 'angebot',
        'page'      => okja_get_dashboard_page_slug(),
    ];

    return add_query_arg( array_merge( $base_args, $args ), admin_url( 'edit.php' ) );
}

function okja_register_dashboard_submenu() {
    global $okja_dashboard_page_hook;

    $okja_dashboard_page_hook = add_submenu_page(
        okja_get_settings_parent_slug(),
        __( 'OKJA Dashboard', 'jhh-posts-block' ),
        __( 'Dashboard', 'jhh-posts-block' ),
        'edit_posts',
        okja_get_dashboard_page_slug(),
        'okja_render_dashboard_page'
    );
}

add_action( 'admin_menu', 'okja_register_dashboard_submenu', 5 );

function okja_promote_dashboard_submenu() {
    global $submenu;

    $parent_slug    = okja_get_settings_parent_slug();
    $dashboard_slug = okja_get_dashboard_page_slug();

    if ( empty( $submenu[ $parent_slug ] ) || ! is_array( $submenu[ $parent_slug ] ) ) {
        return;
    }

    $dashboard_item = null;
    $other_items    = [];

    foreach ( $submenu[ $parent_slug ] as $item ) {
        if ( isset( $item[2] ) && $item[2] === $dashboard_slug ) {
            $dashboard_item = $item;
            continue;
        }

        $other_items[] = $item;
    }

    if ( null === $dashboard_item ) {
        return;
    }

    array_unshift( $other_items, $dashboard_item );
    $submenu[ $parent_slug ] = $other_items;
}

add_action( 'admin_menu', 'okja_promote_dashboard_submenu', 200 );

add_action( 'admin_init', function() {
    global $pagenow;

    if ( ! is_admin() || ! current_user_can( 'edit_posts' ) ) {
        return;
    }

    if ( $pagenow !== 'edit.php' ) {
        return;
    }

    $post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
    $page      = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

    if ( $post_type !== 'angebot' || $page !== '' ) {
        return;
    }

    $query_keys = array_keys( $_GET );
    $extra_keys = array_diff( $query_keys, [ 'post_type' ] );

    if ( ! empty( $extra_keys ) ) {
        return;
    }

    wp_safe_redirect( okja_get_dashboard_page_url() );
    exit;
} );

add_action( 'admin_enqueue_scripts', function( $hook ) {
    global $okja_dashboard_page_hook;

    if ( ! $okja_dashboard_page_hook || $hook !== $okja_dashboard_page_hook ) {
        return;
    }

    $dashboard_css     = JHH_PB_DIR . 'assets/admin-dashboard.css';
    $dashboard_js      = JHH_PB_DIR . 'assets/admin-dashboard.js';
    $dashboard_version = JHH_PB_VERSION;

    if ( file_exists( $dashboard_css ) ) {
        $dashboard_version .= '.' . filemtime( $dashboard_css );
    }

    if ( file_exists( $dashboard_js ) ) {
        $dashboard_version .= '.' . filemtime( $dashboard_js );
    }

    wp_enqueue_media();
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_script( 'wp-color-picker' );

    wp_enqueue_style(
        'okja-admin-dashboard',
        JHH_PB_URL . 'assets/admin-dashboard.css',
        [ 'common', 'wp-color-picker' ],
        $dashboard_version
    );

    wp_enqueue_script(
        'okja-admin-dashboard',
        JHH_PB_URL . 'assets/admin-dashboard.js',
        [ 'jquery', 'wp-color-picker', 'media-editor' ],
        $dashboard_version,
        true
    );

    wp_localize_script( 'okja-admin-dashboard', 'okjaDashboardData', [
        'texts' => [
            'chooseImage' => __( 'Profilbild auswählen', 'jhh-posts-block' ),
            'useImage'    => __( 'Verwenden', 'jhh-posts-block' ),
        ],
    ] );
} );

function okja_get_dashboard_terms( $taxonomy ) {
    if ( ! taxonomy_exists( $taxonomy ) ) {
        return [];
    }

    $terms = get_terms( [
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ] );

    if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
        return [];
    }

    return $terms;
}

function okja_get_dashboard_notice() {
    $notice = isset( $_GET['okja_notice'] ) ? sanitize_key( wp_unslash( $_GET['okja_notice'] ) ) : '';
    $type   = isset( $_GET['okja_notice_type'] ) ? sanitize_key( wp_unslash( $_GET['okja_notice_type'] ) ) : 'success';

    $messages = [
        'staff-created' => __( 'Jugendarbeit wurde angelegt.', 'jhh-posts-block' ),
        'paed-created'  => __( 'Pädagogik-Begriff wurde angelegt.', 'jhh-posts-block' ),
        'term-error'    => __( 'Der Begriff konnte nicht angelegt werden. Bitte Eingaben prüfen.', 'jhh-posts-block' ),
    ];

    if ( ! isset( $messages[ $notice ] ) ) {
        return null;
    }

    return [
        'message' => $messages[ $notice ],
        'type'    => $type === 'error' ? 'error' : 'success',
    ];
}

function okja_redirect_dashboard_notice( $notice, $type = 'success' ) {
    wp_safe_redirect( okja_get_dashboard_page_url( [
        'okja_notice'      => $notice,
        'okja_notice_type' => $type,
    ] ) );
    exit;
}

function okja_get_dashboard_term_initial( $name ) {
    $name = trim( (string) $name );
    if ( $name === '' ) {
        return '?';
    }

    $char = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 1 ) : substr( $name, 0, 1 );

    return strtoupper( $char );
}

add_action( 'admin_post_okja_create_staff_term', function() {
    if ( ! current_user_can( 'manage_categories' ) ) {
        wp_die( esc_html__( 'Keine Berechtigung.', 'jhh-posts-block' ) );
    }

    check_admin_referer( 'okja_create_staff_term', 'okja_create_staff_term_nonce' );

    $name = isset( $_POST['tag-name'] ) ? sanitize_text_field( wp_unslash( $_POST['tag-name'] ) ) : '';
    if ( $name === '' ) {
        okja_redirect_dashboard_notice( 'term-error', 'error' );
    }

    $result = wp_insert_term( $name, JHH_TAX_STAFF, [
        'slug'        => isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '',
        'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
    ] );

    if ( is_wp_error( $result ) || empty( $result['term_id'] ) ) {
        okja_redirect_dashboard_notice( 'term-error', 'error' );
    }

    $term_id = (int) $result['term_id'];

    update_term_meta( $term_id, 'funktion', isset( $_POST['jhh_staff_funktion'] ) ? sanitize_text_field( wp_unslash( $_POST['jhh_staff_funktion'] ) ) : '' );
    update_term_meta( $term_id, 'bio', isset( $_POST['jhh_staff_bio'] ) ? wp_kses_post( wp_unslash( $_POST['jhh_staff_bio'] ) ) : '' );
    update_term_meta( $term_id, 'contact', isset( $_POST['jhh_staff_contact'] ) ? sanitize_text_field( wp_unslash( $_POST['jhh_staff_contact'] ) ) : '' );
    update_term_meta( $term_id, 'avatar_id', isset( $_POST['jhh_staff_avatar_id'] ) ? absint( wp_unslash( $_POST['jhh_staff_avatar_id'] ) ) : 0 );

    okja_redirect_dashboard_notice( 'staff-created' );
} );

add_action( 'admin_post_okja_create_paed_term', function() {
    if ( ! current_user_can( 'manage_categories' ) ) {
        wp_die( esc_html__( 'Keine Berechtigung.', 'jhh-posts-block' ) );
    }

    check_admin_referer( 'okja_create_paed_term', 'okja_create_paed_term_nonce' );

    $name = isset( $_POST['tag-name'] ) ? sanitize_text_field( wp_unslash( $_POST['tag-name'] ) ) : '';
    if ( $name === '' ) {
        okja_redirect_dashboard_notice( 'term-error', 'error' );
    }

    $result = wp_insert_term( $name, JHH_TAX_PAED, [
        'slug'        => isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '',
        'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
    ] );

    if ( is_wp_error( $result ) || empty( $result['term_id'] ) ) {
        okja_redirect_dashboard_notice( 'term-error', 'error' );
    }

    $term_id = (int) $result['term_id'];
    $bg      = isset( $_POST['badge_bg_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['badge_bg_color'] ) ) : '';
    $text    = isset( $_POST['badge_text_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['badge_text_color'] ) ) : '';

    update_term_meta( $term_id, 'badge_bg_color', $bg ?: '' );
    update_term_meta( $term_id, 'badge_text_color', $text ?: '' );

    okja_redirect_dashboard_notice( 'paed-created' );
} );

function okja_get_dashboard_counts() {
    $angebot_counts     = wp_count_posts( 'angebot' );
    $event_counts       = wp_count_posts( 'angebotsevent' );
    $today              = current_time( 'Y-m-d' );
    $countable_statuses = [ 'publish', 'future', 'draft', 'pending', 'private' ];

    $upcoming_events = new WP_Query( [
        'post_type'      => 'angebotsevent',
        'post_status'    => [ 'publish', 'future' ],
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_key'       => 'jhh_event_date',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query'     => [
            [
                'key'     => 'jhh_event_date',
                'value'   => $today,
                'compare' => '>=',
                'type'    => 'DATE',
            ],
        ],
    ] );

    $angebot_with_image = new WP_Query( [
        'post_type'      => 'angebot',
        'post_status'    => $countable_statuses,
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'     => '_thumbnail_id',
                'compare' => 'EXISTS',
            ],
        ],
    ] );

    return [
        'angebote_total'      => (int) array_sum( array_map( function( $status ) use ( $angebot_counts ) {
            return (int) ( $angebot_counts->{$status} ?? 0 );
        }, $countable_statuses ) ),
        'angebote_published'  => (int) ( $angebot_counts->publish ?? 0 ),
        'angebote_drafts'     => (int) ( $angebot_counts->draft ?? 0 ),
        'events_total'        => (int) array_sum( array_map( function( $status ) use ( $event_counts ) {
            return (int) ( $event_counts->{$status} ?? 0 );
        }, $countable_statuses ) ),
        'events_published'    => (int) ( $event_counts->publish ?? 0 ),
        'events_drafts'       => (int) ( $event_counts->draft ?? 0 ),
        'events_upcoming'     => (int) $upcoming_events->found_posts,
        'angebote_with_image' => (int) $angebot_with_image->found_posts,
    ];
}

function okja_get_upcoming_events_for_dashboard( $limit = 6 ) {
    return get_posts( [
        'post_type'      => 'angebotsevent',
        'post_status'    => [ 'publish', 'future', 'draft', 'pending', 'private' ],
        'posts_per_page' => $limit,
        'meta_key'       => 'jhh_event_date',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query'     => [
            'relation' => 'OR',
            [
                'key'     => 'jhh_event_date',
                'value'   => current_time( 'Y-m-d' ),
                'compare' => '>=',
                'type'    => 'DATE',
            ],
            [
                'key'     => 'jhh_event_date',
                'compare' => 'NOT EXISTS',
            ],
        ],
    ] );
}

function okja_get_recent_angebote_for_dashboard( $limit = 6 ) {
    return get_posts( [
        'post_type'      => 'angebot',
        'post_status'    => [ 'publish', 'draft', 'pending', 'future', 'private' ],
        'posts_per_page' => $limit,
        'orderby'        => 'modified',
        'order'          => 'DESC',
    ] );
}

function okja_format_dashboard_event_date( $post_id ) {
    $event_date = get_post_meta( $post_id, 'jhh_event_date', true );
    $time_start = get_post_meta( $post_id, 'jhh_event_time_start', true );
    $time_end   = get_post_meta( $post_id, 'jhh_event_time_end', true );

    $parts = [];
    if ( $event_date ) {
        $timestamp = strtotime( $event_date );
        $parts[]   = $timestamp ? wp_date( 'd.m.Y', $timestamp ) : $event_date;
    }
    if ( $time_start && $time_end ) {
        $parts[] = $time_start . ' - ' . $time_end;
    } elseif ( $time_start ) {
        $parts[] = $time_start;
    }

    return $parts ? implode( ' | ', $parts ) : __( 'Noch kein Datum gesetzt', 'jhh-posts-block' );
}

function okja_get_dashboard_status_markup( $enabled ) {
    $is_on = $enabled === '1' || $enabled === 1 || $enabled === true;
    $class = $is_on ? 'okja-dashboard-status is-on' : 'okja-dashboard-status is-off';
    $text  = $is_on ? __( 'Aktiv', 'jhh-posts-block' ) : __( 'Aus', 'jhh-posts-block' );

    return '<span class="' . esc_attr( $class ) . '">' . esc_html( $text ) . '</span>';
}

function okja_render_dashboard_page() {
    if ( ! current_user_can( 'edit_posts' ) ) {
        return;
    }

    $counts               = okja_get_dashboard_counts();
    $upcoming_events      = okja_get_upcoming_events_for_dashboard();
    $recent_angebote      = okja_get_recent_angebote_for_dashboard();
    $angebot_wizard       = get_option( 'okja_enable_angebot_wizard', '1' );
    $angebotsevent_wizard = get_option( 'okja_enable_angebotsevent_wizard', '1' );
    $events_in_angebot    = get_option( 'okja_events_show_in_angebot', '1' );
    $event_link_mode      = get_option( 'okja_event_link_mode', 'single' );
    $future_days          = (int) get_option( 'okja_events_future_days', 365 );
    $past_days            = (int) get_option( 'okja_events_past_days', 0 );
    $staff_style          = get_option( 'okja_default_staff_style', 'simple' );
    $event_card_style     = get_option( 'okja_event_card_style', 'simple' );
    $staff_terms          = okja_get_dashboard_terms( JHH_TAX_STAFF );
    $paed_terms           = okja_get_dashboard_terms( JHH_TAX_PAED );
    $dashboard_notice     = okja_get_dashboard_notice();
    $can_manage_terms     = current_user_can( 'manage_categories' );
    ?>
    <div class="wrap" id="okja-dashboard">
        <?php if ( $dashboard_notice ) : ?>
            <div class="notice notice-<?php echo esc_attr( $dashboard_notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $dashboard_notice['message'] ); ?></p></div>
        <?php endif; ?>

        <div class="okja-dashboard-hero">
            <section class="okja-dashboard-card okja-dashboard-hero-main">
                <p class="okja-dashboard-kicker"><?php esc_html_e( 'OKJA Angebote', 'jhh-posts-block' ); ?></p>
                <h1><?php esc_html_e( 'Redaktions-Dashboard', 'jhh-posts-block' ); ?></h1>
                <p class="okja-dashboard-lead"><?php esc_html_e( 'Hier liegen die wichtigsten Einstiege für Angebote, A-Events, Jugendarbeit und Pädagogik zusammen: neue Inhalte anlegen, offene Entwürfe prüfen und zentrale Anzeigeoptionen im Blick behalten.', 'jhh-posts-block' ); ?></p>
                <div class="okja-dashboard-pill-row">
                    <span class="okja-dashboard-pill"><?php echo wp_kses_post( okja_get_dashboard_status_markup( $angebot_wizard ) ); ?> <?php esc_html_e( 'Angebots-Wizard', 'jhh-posts-block' ); ?></span>
                    <span class="okja-dashboard-pill"><?php echo wp_kses_post( okja_get_dashboard_status_markup( $angebotsevent_wizard ) ); ?> <?php esc_html_e( 'A-Event-Wizard', 'jhh-posts-block' ); ?></span>
                    <span class="okja-dashboard-pill"><?php echo wp_kses_post( okja_get_dashboard_status_markup( $events_in_angebot ) ); ?> <?php esc_html_e( 'Events in Angeboten', 'jhh-posts-block' ); ?></span>
                </div>
                <div class="okja-dashboard-actions">
                    <a class="okja-dashboard-button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=angebot' ) ); ?>"><?php esc_html_e( 'Neues Angebot', 'jhh-posts-block' ); ?></a>
                    <a class="okja-dashboard-button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=angebotsevent' ) ); ?>"><?php esc_html_e( 'Neues A-Event', 'jhh-posts-block' ); ?></a>
                    <?php if ( $can_manage_terms ) : ?>
                        <button type="button" class="okja-dashboard-button is-secondary" data-okja-modal-open="staff"><?php esc_html_e( 'Jugendarbeit anlegen', 'jhh-posts-block' ); ?></button>
                        <button type="button" class="okja-dashboard-button is-secondary" data-okja-modal-open="paed"><?php esc_html_e( 'Pädagogik anlegen', 'jhh-posts-block' ); ?></button>
                    <?php endif; ?>
                    <?php if ( current_user_can( 'manage_options' ) ) : ?>
                        <a class="okja-dashboard-button is-secondary" href="<?php echo esc_url( admin_url( okja_get_settings_parent_slug() . '&page=' . okja_get_settings_page_slug() ) ); ?>"><?php esc_html_e( 'Alle Einstellungen', 'jhh-posts-block' ); ?></a>
                    <?php endif; ?>
                </div>
            </section>

            <aside class="okja-dashboard-card okja-dashboard-hero-side">
                <div>
                    <h2 class="okja-dashboard-side-title"><?php esc_html_e( 'Aktive Darstellung', 'jhh-posts-block' ); ?></h2>
                    <p class="okja-dashboard-side-copy"><?php printf( esc_html__( 'Mitarbeiterkarten: %1$s. Event-Karten: %2$s.', 'jhh-posts-block' ), esc_html( $staff_style ), esc_html( $event_card_style ) ); ?></p>
                </div>
                <div>
                    <h2 class="okja-dashboard-side-title"><?php esc_html_e( 'Event-Zeitraum', 'jhh-posts-block' ); ?></h2>
                    <p class="okja-dashboard-side-copy"><?php printf( esc_html__( 'Auf Angebotsseiten werden Events standardmäßig %1$d Tage in die Zukunft und %2$d Tage rückwirkend berücksichtigt.', 'jhh-posts-block' ), $future_days, $past_days ); ?></p>
                </div>
                <div>
                    <h2 class="okja-dashboard-side-title"><?php esc_html_e( 'Taxonomie-Stand', 'jhh-posts-block' ); ?></h2>
                    <p class="okja-dashboard-side-copy"><?php printf( esc_html__( '%1$d Jugendarbeit-Profile und %2$d Pädagogik-Begriffe stehen aktuell bereit.', 'jhh-posts-block' ), count( $staff_terms ), count( $paed_terms ) ); ?></p>
                </div>
            </aside>
        </div>

        <div class="okja-dashboard-stat-grid is-six">
            <section class="okja-dashboard-card okja-dashboard-stat">
                <p class="okja-dashboard-stat-label"><?php esc_html_e( 'Angebote gesamt', 'jhh-posts-block' ); ?></p>
                <p class="okja-dashboard-stat-value"><?php echo esc_html( number_format_i18n( $counts['angebote_total'] ) ); ?></p>
                <p class="okja-dashboard-stat-copy"><?php printf( esc_html__( '%1$d veröffentlicht, %2$d Entwürfe.', 'jhh-posts-block' ), $counts['angebote_published'], $counts['angebote_drafts'] ); ?></p>
            </section>
            <section class="okja-dashboard-card okja-dashboard-stat">
                <p class="okja-dashboard-stat-label"><?php esc_html_e( 'A-Events gesamt', 'jhh-posts-block' ); ?></p>
                <p class="okja-dashboard-stat-value"><?php echo esc_html( number_format_i18n( $counts['events_total'] ) ); ?></p>
                <p class="okja-dashboard-stat-copy"><?php printf( esc_html__( '%1$d veröffentlicht, %2$d Entwürfe.', 'jhh-posts-block' ), $counts['events_published'], $counts['events_drafts'] ); ?></p>
            </section>
            <section class="okja-dashboard-card okja-dashboard-stat">
                <p class="okja-dashboard-stat-label"><?php esc_html_e( 'Kommende A-Events', 'jhh-posts-block' ); ?></p>
                <p class="okja-dashboard-stat-value"><?php echo esc_html( number_format_i18n( $counts['events_upcoming'] ) ); ?></p>
                <p class="okja-dashboard-stat-copy"><?php esc_html_e( 'Auf Basis des Event-Datums ab heute.', 'jhh-posts-block' ); ?></p>
            </section>
            <section class="okja-dashboard-card okja-dashboard-stat">
                <p class="okja-dashboard-stat-label"><?php esc_html_e( 'Angebote mit Bild', 'jhh-posts-block' ); ?></p>
                <p class="okja-dashboard-stat-value"><?php echo esc_html( number_format_i18n( $counts['angebote_with_image'] ) ); ?></p>
                <p class="okja-dashboard-stat-copy"><?php esc_html_e( 'Hilft, Übersichten im Frontend vollständig zu halten.', 'jhh-posts-block' ); ?></p>
            </section>
            <section class="okja-dashboard-card okja-dashboard-stat">
                <p class="okja-dashboard-stat-label"><?php esc_html_e( 'Jugendarbeit', 'jhh-posts-block' ); ?></p>
                <p class="okja-dashboard-stat-value"><?php echo esc_html( number_format_i18n( count( $staff_terms ) ) ); ?></p>
                <p class="okja-dashboard-stat-copy"><?php esc_html_e( 'Team-Profile und Rollen im Überblick.', 'jhh-posts-block' ); ?></p>
            </section>
            <section class="okja-dashboard-card okja-dashboard-stat">
                <p class="okja-dashboard-stat-label"><?php esc_html_e( 'Pädagogik', 'jhh-posts-block' ); ?></p>
                <p class="okja-dashboard-stat-value"><?php echo esc_html( number_format_i18n( count( $paed_terms ) ) ); ?></p>
                <p class="okja-dashboard-stat-copy"><?php esc_html_e( 'Farbige Kategorien für Karten und Übersichten.', 'jhh-posts-block' ); ?></p>
            </section>
        </div>

        <div class="okja-dashboard-main-grid">
            <div>
                <section class="okja-dashboard-card okja-dashboard-section">
                    <div class="okja-dashboard-section-head">
                        <div>
                            <h2><?php esc_html_e( 'Jugendarbeit', 'jhh-posts-block' ); ?></h2>
                            <p><?php esc_html_e( 'Team-Mitglieder mit Rolle, Kontakt und Bild direkt im Dashboard überblicken und ergänzen.', 'jhh-posts-block' ); ?></p>
                        </div>
                        <div class="okja-dashboard-section-actions">
                            <?php if ( $can_manage_terms ) : ?>
                                <button type="button" class="okja-dashboard-button" data-okja-modal-open="staff"><?php esc_html_e( 'Neu anlegen', 'jhh-posts-block' ); ?></button>
                            <?php endif; ?>
                            <a class="okja-dashboard-button is-secondary" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . JHH_TAX_STAFF . '&post_type=angebot' ) ); ?>"><?php esc_html_e( 'Verwalten', 'jhh-posts-block' ); ?></a>
                        </div>
                    </div>
                    <div class="okja-dashboard-taxonomy-grid">
                        <?php if ( $staff_terms ) : ?>
                            <?php foreach ( $staff_terms as $term ) : ?>
                                <?php
                                $avatar_id = (int) get_term_meta( $term->term_id, 'avatar_id', true );
                                $role      = trim( (string) get_term_meta( $term->term_id, 'funktion', true ) );
                                $contact   = trim( (string) get_term_meta( $term->term_id, 'contact', true ) );
                                ?>
                                <article class="okja-dashboard-taxonomy-card okja-dashboard-taxonomy-card-staff">
                                    <div class="okja-dashboard-taxonomy-media">
                                        <?php if ( $avatar_id ) : ?>
                                            <?php echo wp_get_attachment_image( $avatar_id, 'thumbnail', false, [ 'class' => 'okja-dashboard-taxonomy-avatar' ] ); ?>
                                        <?php else : ?>
                                            <span class="okja-dashboard-taxonomy-avatar is-fallback"><?php echo esc_html( okja_get_dashboard_term_initial( $term->name ) ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="okja-dashboard-taxonomy-copy">
                                        <h3><a href="<?php echo esc_url( get_edit_term_link( $term, JHH_TAX_STAFF ) ); ?>"><?php echo esc_html( $term->name ); ?></a></h3>
                                        <p><?php echo esc_html( $role ?: __( 'Noch keine Rolle gepflegt', 'jhh-posts-block' ) ); ?></p>
                                        <?php if ( $contact ) : ?>
                                            <p><?php echo esc_html( $contact ); ?></p>
                                        <?php endif; ?>
                                        <span class="okja-dashboard-badge"><?php printf( esc_html__( '%d verknüpfte Angebote', 'jhh-posts-block' ), (int) $term->count ); ?></span>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <article class="okja-dashboard-list-item">
                                <div>
                                    <h3><?php esc_html_e( 'Noch keine Jugendarbeit angelegt', 'jhh-posts-block' ); ?></h3>
                                    <p><?php esc_html_e( 'Lege das erste Team-Mitglied direkt über das Dashboard-Modal an.', 'jhh-posts-block' ); ?></p>
                                </div>
                            </article>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="okja-dashboard-card okja-dashboard-section" style="margin-top:20px;">
                    <div class="okja-dashboard-section-head">
                        <div>
                            <h2><?php esc_html_e( 'Pädagogik', 'jhh-posts-block' ); ?></h2>
                            <p><?php esc_html_e( 'Farbcodierte Begriffe für Angebotskategorien inklusive Direktzugriff auf neue Einträge.', 'jhh-posts-block' ); ?></p>
                        </div>
                        <div class="okja-dashboard-section-actions">
                            <?php if ( $can_manage_terms ) : ?>
                                <button type="button" class="okja-dashboard-button" data-okja-modal-open="paed"><?php esc_html_e( 'Neu anlegen', 'jhh-posts-block' ); ?></button>
                            <?php endif; ?>
                            <a class="okja-dashboard-button is-secondary" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=' . JHH_TAX_PAED . '&post_type=angebot' ) ); ?>"><?php esc_html_e( 'Verwalten', 'jhh-posts-block' ); ?></a>
                        </div>
                    </div>
                    <div class="okja-dashboard-taxonomy-grid is-paedagogik">
                        <?php if ( $paed_terms ) : ?>
                            <?php foreach ( $paed_terms as $term ) : ?>
                                <?php
                                $bg_color   = trim( (string) get_term_meta( $term->term_id, 'badge_bg_color', true ) );
                                $text_color = trim( (string) get_term_meta( $term->term_id, 'badge_text_color', true ) );
                                $swatch     = '';

                                if ( $bg_color ) {
                                    $swatch .= 'background:' . $bg_color . ';';
                                }
                                if ( $text_color ) {
                                    $swatch .= 'color:' . $text_color . ';';
                                }
                                ?>
                                <article class="okja-dashboard-taxonomy-card okja-dashboard-taxonomy-card-paed">
                                    <span class="okja-dashboard-taxonomy-swatch" style="<?php echo esc_attr( $swatch ); ?>"><?php echo esc_html( okja_get_dashboard_term_initial( $term->name ) ); ?></span>
                                    <div class="okja-dashboard-taxonomy-copy">
                                        <h3><a href="<?php echo esc_url( get_edit_term_link( $term, JHH_TAX_PAED ) ); ?>"><?php echo esc_html( $term->name ); ?></a></h3>
                                        <p><?php echo esc_html( $bg_color ?: __( 'Noch keine Badge-Farbe gesetzt', 'jhh-posts-block' ) ); ?></p>
                                        <span class="okja-dashboard-badge"><?php printf( esc_html__( '%d verknüpfte Angebote', 'jhh-posts-block' ), (int) $term->count ); ?></span>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <article class="okja-dashboard-list-item">
                                <div>
                                    <h3><?php esc_html_e( 'Noch keine Pädagogik-Begriffe', 'jhh-posts-block' ); ?></h3>
                                    <p><?php esc_html_e( 'Lege die erste Kategorie direkt über das Dashboard-Modal an.', 'jhh-posts-block' ); ?></p>
                                </div>
                            </article>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="okja-dashboard-card okja-dashboard-section" style="margin-top:20px;">
                    <h2><?php esc_html_e( 'Kommende A-Events', 'jhh-posts-block' ); ?></h2>
                    <p><?php esc_html_e( 'Die nächsten Termine mit Schnellzugriff auf Bearbeitung und zugeordnetes Angebot.', 'jhh-posts-block' ); ?></p>
                    <div class="okja-dashboard-list">
                        <?php if ( $upcoming_events ) : ?>
                            <?php foreach ( $upcoming_events as $event_post ) : ?>
                                <?php
                                $angebot_id    = (int) get_post_meta( $event_post->ID, 'jhh_event_angebot_id', true );
                                $angebot_title = $angebot_id ? get_the_title( $angebot_id ) : '';
                                $status_object = get_post_status_object( get_post_status( $event_post ) );
                                ?>
                                <article class="okja-dashboard-list-item">
                                    <div>
                                        <h3><a href="<?php echo esc_url( get_edit_post_link( $event_post->ID ) ); ?>"><?php echo esc_html( get_the_title( $event_post ) ?: __( '(Ohne Titel)', 'jhh-posts-block' ) ); ?></a></h3>
                                        <p><?php echo esc_html( okja_format_dashboard_event_date( $event_post->ID ) ); ?></p>
                                        <p><?php echo $angebot_title ? esc_html( sprintf( __( 'Angebot: %s', 'jhh-posts-block' ), $angebot_title ) ) : esc_html__( 'Noch keinem Angebot zugeordnet', 'jhh-posts-block' ); ?></p>
                                    </div>
                                    <span class="okja-dashboard-badge"><?php echo esc_html( $status_object->label ?? get_post_status( $event_post ) ); ?></span>
                                </article>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <article class="okja-dashboard-list-item">
                                <div>
                                    <h3><?php esc_html_e( 'Keine kommenden A-Events', 'jhh-posts-block' ); ?></h3>
                                    <p><?php esc_html_e( 'Sobald Termine gepflegt sind, erscheinen sie hier automatisch.', 'jhh-posts-block' ); ?></p>
                                </div>
                            </article>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="okja-dashboard-card okja-dashboard-section" style="margin-top:20px;">
                    <h2><?php esc_html_e( 'Zuletzt bearbeitete Angebote', 'jhh-posts-block' ); ?></h2>
                    <p><?php esc_html_e( 'Praktisch für redaktionelle Nachpflege, Bilder und letzte Textanpassungen.', 'jhh-posts-block' ); ?></p>
                    <div class="okja-dashboard-list">
                        <?php if ( $recent_angebote ) : ?>
                            <?php foreach ( $recent_angebote as $angebot_post ) : ?>
                                <?php $status_object = get_post_status_object( get_post_status( $angebot_post ) ); ?>
                                <article class="okja-dashboard-list-item">
                                    <div>
                                        <h3><a href="<?php echo esc_url( get_edit_post_link( $angebot_post->ID ) ); ?>"><?php echo esc_html( get_the_title( $angebot_post ) ?: __( '(Ohne Titel)', 'jhh-posts-block' ) ); ?></a></h3>
                                        <p><?php printf( esc_html__( 'Zuletzt geändert am %s', 'jhh-posts-block' ), esc_html( wp_date( 'd.m.Y H:i', strtotime( $angebot_post->post_modified ) ) ) ); ?></p>
                                    </div>
                                    <span class="okja-dashboard-badge"><?php echo esc_html( $status_object->label ?? get_post_status( $angebot_post ) ); ?></span>
                                </article>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <article class="okja-dashboard-list-item">
                                <div>
                                    <h3><?php esc_html_e( 'Noch keine Angebote vorhanden', 'jhh-posts-block' ); ?></h3>
                                    <p><?php esc_html_e( 'Neue Angebote tauchen hier nach der ersten Bearbeitung auf.', 'jhh-posts-block' ); ?></p>
                                </div>
                            </article>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <div>
                <section class="okja-dashboard-card okja-dashboard-section">
                    <h2><?php esc_html_e( 'Wichtigste Optionen', 'jhh-posts-block' ); ?></h2>
                    <p><?php esc_html_e( 'Schneller Statusüberblick für die zentralen Schalter des Plugins.', 'jhh-posts-block' ); ?></p>
                    <div class="okja-dashboard-option-grid">
                        <div class="okja-dashboard-option">
                            <div>
                                <strong><?php esc_html_e( 'Angebots-Wizard', 'jhh-posts-block' ); ?></strong>
                                <span><?php esc_html_e( 'Geführte Eingabe für Angebote im Editor.', 'jhh-posts-block' ); ?></span>
                            </div>
                            <?php echo wp_kses_post( okja_get_dashboard_status_markup( $angebot_wizard ) ); ?>
                        </div>
                        <div class="okja-dashboard-option">
                            <div>
                                <strong><?php esc_html_e( 'A-Event-Wizard', 'jhh-posts-block' ); ?></strong>
                                <span><?php esc_html_e( 'Geführte Eingabe für Termin, CTA und Bild.', 'jhh-posts-block' ); ?></span>
                            </div>
                            <?php echo wp_kses_post( okja_get_dashboard_status_markup( $angebotsevent_wizard ) ); ?>
                        </div>
                        <div class="okja-dashboard-option">
                            <div>
                                <strong><?php esc_html_e( 'Events in Angeboten anzeigen', 'jhh-posts-block' ); ?></strong>
                                <span><?php printf( esc_html__( 'Zeitraum aktuell: %1$d Tage vorwärts, %2$d Tage rückwärts.', 'jhh-posts-block' ), $future_days, $past_days ); ?></span>
                            </div>
                            <?php echo wp_kses_post( okja_get_dashboard_status_markup( $events_in_angebot ) ); ?>
                        </div>
                        <div class="okja-dashboard-option">
                            <div>
                                <strong><?php esc_html_e( 'Event-Linkverhalten', 'jhh-posts-block' ); ?></strong>
                                <span><?php echo esc_html( $event_link_mode === 'modal' ? __( 'Öffnet als Modal-Popup', 'jhh-posts-block' ) : __( 'Öffnet auf eigener Single-Seite', 'jhh-posts-block' ) ); ?></span>
                            </div>
                            <span class="okja-dashboard-badge"><?php echo esc_html( $event_link_mode === 'modal' ? __( 'Modal', 'jhh-posts-block' ) : __( 'Single', 'jhh-posts-block' ) ); ?></span>
                        </div>
                        <div class="okja-dashboard-option">
                            <div>
                                <strong><?php esc_html_e( 'Aktive Kartenstile', 'jhh-posts-block' ); ?></strong>
                                <span><?php printf( esc_html__( 'Mitarbeiter: %1$s. Event-Karten: %2$s.', 'jhh-posts-block' ), esc_html( $staff_style ), esc_html( $event_card_style ) ); ?></span>
                            </div>
                            <span class="okja-dashboard-badge"><?php esc_html_e( 'Design', 'jhh-posts-block' ); ?></span>
                        </div>
                    </div>
                </section>

                <?php if ( current_user_can( 'manage_options' ) ) : ?>
                    <section class="okja-dashboard-card okja-dashboard-section" style="margin-top:20px;">
                        <h2><?php esc_html_e( 'Schnell-Einstellungen', 'jhh-posts-block' ); ?></h2>
                        <p><?php esc_html_e( 'Kompakte Bearbeitung der wichtigsten Schalter direkt im Dashboard. Für alle weiteren Optionen bleibt die vollständige Einstellungsseite erhalten.', 'jhh-posts-block' ); ?></p>
                        <form action="options.php" method="post">
                            <?php settings_fields( 'okja_settings_group' ); ?>
                            <div class="okja-dashboard-form-grid">
                                <label class="okja-dashboard-check">
                                    <input type="hidden" name="okja_enable_angebot_wizard" value="0">
                                    <input type="checkbox" name="okja_enable_angebot_wizard" value="1" <?php checked( $angebot_wizard, '1' ); ?>>
                                    <span><strong><?php esc_html_e( 'Angebots-Wizard aktiv', 'jhh-posts-block' ); ?></strong><?php esc_html_e( 'Beim Erstellen und Bearbeiten von Angeboten einblenden.', 'jhh-posts-block' ); ?></span>
                                </label>
                                <label class="okja-dashboard-check">
                                    <input type="hidden" name="okja_enable_angebotsevent_wizard" value="0">
                                    <input type="checkbox" name="okja_enable_angebotsevent_wizard" value="1" <?php checked( $angebotsevent_wizard, '1' ); ?>>
                                    <span><strong><?php esc_html_e( 'A-Event-Wizard aktiv', 'jhh-posts-block' ); ?></strong><?php esc_html_e( 'Beim Erstellen und Bearbeiten von A-Events einblenden.', 'jhh-posts-block' ); ?></span>
                                </label>
                                <label class="okja-dashboard-check is-span-2">
                                    <input type="hidden" name="okja_events_show_in_angebot" value="0">
                                    <input type="checkbox" name="okja_events_show_in_angebot" value="1" <?php checked( $events_in_angebot, '1' ); ?>>
                                    <span><strong><?php esc_html_e( 'A-Events in Angebots-Einzelansicht anzeigen', 'jhh-posts-block' ); ?></strong><?php esc_html_e( 'Verknüpfte Termine werden automatisch unterhalb des Angebots eingeblendet.', 'jhh-posts-block' ); ?></span>
                                </label>
                                <div class="okja-dashboard-field">
                                    <label for="okja_dashboard_staff_style"><?php esc_html_e( 'Standardstil Mitarbeiter-Karte', 'jhh-posts-block' ); ?></label>
                                    <select id="okja_dashboard_staff_style" name="okja_default_staff_style">
                                        <option value="simple" <?php selected( $staff_style, 'simple' ); ?>><?php esc_html_e( 'Schlicht', 'jhh-posts-block' ); ?></option>
                                        <option value="notebook" <?php selected( $staff_style, 'notebook' ); ?>><?php esc_html_e( 'Papier / Notizbuch', 'jhh-posts-block' ); ?></option>
                                        <option value="aurora" <?php selected( $staff_style, 'aurora' ); ?>><?php esc_html_e( 'Pastell-Gradient', 'jhh-posts-block' ); ?></option>
                                        <option value="grainy-1" <?php selected( $staff_style, 'grainy-1' ); ?>><?php esc_html_e( 'Grainy 1', 'jhh-posts-block' ); ?></option>
                                        <option value="grainy-2" <?php selected( $staff_style, 'grainy-2' ); ?>><?php esc_html_e( 'Grainy 2', 'jhh-posts-block' ); ?></option>
                                        <option value="grainy-3" <?php selected( $staff_style, 'grainy-3' ); ?>><?php esc_html_e( 'Grainy 3', 'jhh-posts-block' ); ?></option>
                                        <option value="custom" <?php selected( $staff_style, 'custom' ); ?>><?php esc_html_e( 'Benutzerdefiniert', 'jhh-posts-block' ); ?></option>
                                    </select>
                                </div>
                                <div class="okja-dashboard-field">
                                    <label for="okja_dashboard_event_card_style"><?php esc_html_e( 'Standardstil Event-Karte', 'jhh-posts-block' ); ?></label>
                                    <select id="okja_dashboard_event_card_style" name="okja_event_card_style">
                                        <option value="simple" <?php selected( $event_card_style, 'simple' ); ?>><?php esc_html_e( 'Schlicht', 'jhh-posts-block' ); ?></option>
                                        <option value="notebook" <?php selected( $event_card_style, 'notebook' ); ?>><?php esc_html_e( 'Papier / Notizbuch', 'jhh-posts-block' ); ?></option>
                                        <option value="aurora" <?php selected( $event_card_style, 'aurora' ); ?>><?php esc_html_e( 'Pastell-Gradient', 'jhh-posts-block' ); ?></option>
                                        <option value="grainy-1" <?php selected( $event_card_style, 'grainy-1' ); ?>><?php esc_html_e( 'Grainy 1', 'jhh-posts-block' ); ?></option>
                                        <option value="grainy-2" <?php selected( $event_card_style, 'grainy-2' ); ?>><?php esc_html_e( 'Grainy 2', 'jhh-posts-block' ); ?></option>
                                        <option value="grainy-3" <?php selected( $event_card_style, 'grainy-3' ); ?>><?php esc_html_e( 'Grainy 3', 'jhh-posts-block' ); ?></option>
                                        <option value="custom" <?php selected( $event_card_style, 'custom' ); ?>><?php esc_html_e( 'Benutzerdefiniert', 'jhh-posts-block' ); ?></option>
                                    </select>
                                </div>
                                <div class="okja-dashboard-field">
                                    <label for="okja_dashboard_future_days"><?php esc_html_e( 'Event-Zeitraum in Zukunft', 'jhh-posts-block' ); ?></label>
                                    <input id="okja_dashboard_future_days" type="number" name="okja_events_future_days" value="<?php echo esc_attr( $future_days ); ?>" min="0" max="3650">
                                </div>
                                <div class="okja-dashboard-field">
                                    <label for="okja_dashboard_event_link_mode"><?php esc_html_e( 'Event öffnen als', 'jhh-posts-block' ); ?></label>
                                    <select id="okja_dashboard_event_link_mode" name="okja_event_link_mode">
                                        <option value="single" <?php selected( $event_link_mode, 'single' ); ?>><?php esc_html_e( 'Single-Seite', 'jhh-posts-block' ); ?></option>
                                        <option value="modal" <?php selected( $event_link_mode, 'modal' ); ?>><?php esc_html_e( 'Modal-Popup', 'jhh-posts-block' ); ?></option>
                                    </select>
                                </div>
                                <div class="okja-dashboard-field">
                                    <label for="okja_dashboard_past_days"><?php esc_html_e( 'Event-Zeitraum rückwirkend', 'jhh-posts-block' ); ?></label>
                                    <input id="okja_dashboard_past_days" type="number" name="okja_events_past_days" value="<?php echo esc_attr( $past_days ); ?>" min="0" max="3650">
                                </div>
                            </div>
                            <div class="okja-dashboard-submit">
                                <?php submit_button( __( 'Schnell-Einstellungen speichern', 'jhh-posts-block' ), 'primary', 'submit', false ); ?>
                                <a class="okja-dashboard-button is-secondary" href="<?php echo esc_url( admin_url( okja_get_settings_parent_slug() . '&page=' . okja_get_settings_page_slug() ) ); ?>"><?php esc_html_e( 'Zur vollständigen Einstellungsseite', 'jhh-posts-block' ); ?></a>
                            </div>
                            <p class="okja-dashboard-note"><?php esc_html_e( 'Weitere Farb- und Designoptionen bleiben auf der vollständigen Einstellungsseite verfügbar.', 'jhh-posts-block' ); ?></p>
                        </form>
                    </section>
                <?php endif; ?>
            </div>
        </div>

        <?php if ( $can_manage_terms ) : ?>
            <div class="okja-dashboard-modal-root" data-okja-modal="staff" hidden>
                <div class="okja-dashboard-modal-backdrop" data-okja-modal-close></div>
                <div class="okja-dashboard-modal" role="dialog" aria-modal="true" aria-labelledby="okja-dashboard-staff-modal-title">
                    <button type="button" class="okja-dashboard-modal-close" data-okja-modal-close aria-label="<?php esc_attr_e( 'Schließen', 'jhh-posts-block' ); ?>">&times;</button>
                    <div class="okja-dashboard-modal-head">
                        <p class="okja-dashboard-kicker"><?php esc_html_e( 'Dashboard Wizard', 'jhh-posts-block' ); ?></p>
                        <h2 id="okja-dashboard-staff-modal-title"><?php esc_html_e( 'Jugendarbeit anlegen', 'jhh-posts-block' ); ?></h2>
                        <p><?php esc_html_e( 'Neues Team-Mitglied mit Rolle, Kontakt und Profilbild direkt im Dashboard erstellen.', 'jhh-posts-block' ); ?></p>
                    </div>
                    <form class="okja-dashboard-modal-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
                        <input type="hidden" name="action" value="okja_create_staff_term">
                        <?php wp_nonce_field( 'okja_create_staff_term', 'okja_create_staff_term_nonce' ); ?>
                        <div class="okja-dashboard-form-grid">
                            <div class="okja-dashboard-field">
                                <label for="okja_staff_term_name"><?php esc_html_e( 'Name', 'jhh-posts-block' ); ?></label>
                                <input id="okja_staff_term_name" type="text" name="tag-name" required>
                            </div>
                            <div class="okja-dashboard-field">
                                <label for="okja_staff_term_slug"><?php esc_html_e( 'Slug', 'jhh-posts-block' ); ?></label>
                                <input id="okja_staff_term_slug" type="text" name="slug">
                                <p class="okja-dashboard-field-help"><?php esc_html_e( 'Optional. Wird für die interne URL-/Kennung genutzt, z. B. max-mustermann. Leer lassen, dann wird sie automatisch erzeugt.', 'jhh-posts-block' ); ?></p>
                            </div>
                            <div class="okja-dashboard-field">
                                <label for="okja_staff_term_role"><?php esc_html_e( 'Funktion / Rolle', 'jhh-posts-block' ); ?></label>
                                <input id="okja_staff_term_role" type="text" name="jhh_staff_funktion">
                            </div>
                            <div class="okja-dashboard-field">
                                <label for="okja_staff_term_contact"><?php esc_html_e( 'Kontakt', 'jhh-posts-block' ); ?></label>
                                <input id="okja_staff_term_contact" type="text" name="jhh_staff_contact">
                            </div>
                            <div class="okja-dashboard-field is-span-2">
                                <label for="okja_staff_term_bio"><?php esc_html_e( 'Kurzvorstellung', 'jhh-posts-block' ); ?></label>
                                <textarea id="okja_staff_term_bio" name="jhh_staff_bio" rows="4"></textarea>
                            </div>
                            <div class="okja-dashboard-field is-span-2">
                                <label><?php esc_html_e( 'Profilbild', 'jhh-posts-block' ); ?></label>
                                <div class="okja-dashboard-media-picker">
                                    <input type="hidden" name="jhh_staff_avatar_id" data-okja-media-input>
                                    <div class="okja-dashboard-media-preview" data-okja-media-preview><?php esc_html_e( 'Noch kein Bild gewählt', 'jhh-posts-block' ); ?></div>
                                    <div class="okja-dashboard-media-actions">
                                        <button type="button" class="okja-dashboard-button is-secondary" data-okja-media-button><?php esc_html_e( 'Bild auswählen', 'jhh-posts-block' ); ?></button>
                                        <button type="button" class="okja-dashboard-button is-secondary" data-okja-media-reset><?php esc_html_e( 'Bild entfernen', 'jhh-posts-block' ); ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="okja-dashboard-submit">
                            <button type="submit" class="okja-dashboard-button"><?php esc_html_e( 'Jugendarbeit speichern', 'jhh-posts-block' ); ?></button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="okja-dashboard-modal-root" data-okja-modal="paed" hidden>
                <div class="okja-dashboard-modal-backdrop" data-okja-modal-close></div>
                <div class="okja-dashboard-modal" role="dialog" aria-modal="true" aria-labelledby="okja-dashboard-paed-modal-title">
                    <button type="button" class="okja-dashboard-modal-close" data-okja-modal-close aria-label="<?php esc_attr_e( 'Schließen', 'jhh-posts-block' ); ?>">&times;</button>
                    <div class="okja-dashboard-modal-head">
                        <p class="okja-dashboard-kicker"><?php esc_html_e( 'Dashboard Wizard', 'jhh-posts-block' ); ?></p>
                        <h2 id="okja-dashboard-paed-modal-title"><?php esc_html_e( 'Pädagogik anlegen', 'jhh-posts-block' ); ?></h2>
                        <p><?php esc_html_e( 'Neuen Pädagogik-Begriff mit Badge-Farben direkt aus dem Dashboard heraus erstellen.', 'jhh-posts-block' ); ?></p>
                    </div>
                    <form class="okja-dashboard-modal-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
                        <input type="hidden" name="action" value="okja_create_paed_term">
                        <?php wp_nonce_field( 'okja_create_paed_term', 'okja_create_paed_term_nonce' ); ?>
                        <div class="okja-dashboard-form-grid">
                            <div class="okja-dashboard-field">
                                <label for="okja_paed_term_name"><?php esc_html_e( 'Name', 'jhh-posts-block' ); ?></label>
                                <input id="okja_paed_term_name" type="text" name="tag-name" required>
                            </div>
                            <div class="okja-dashboard-field">
                                <label for="okja_paed_term_slug"><?php esc_html_e( 'Slug', 'jhh-posts-block' ); ?></label>
                                <input id="okja_paed_term_slug" type="text" name="slug">
                                <p class="okja-dashboard-field-help"><?php esc_html_e( 'Optional. Wird für die interne URL-/Kennung genutzt, z. B. offene-jugendarbeit. Leer lassen, dann wird sie automatisch erzeugt.', 'jhh-posts-block' ); ?></p>
                            </div>
                            <div class="okja-dashboard-field">
                                <label for="okja_paed_term_bg"><?php esc_html_e( 'Badge Hintergrundfarbe', 'jhh-posts-block' ); ?></label>
                                <input id="okja_paed_term_bg" class="okja-dashboard-color-picker" type="text" name="badge_bg_color" value="#ef8c27">
                            </div>
                            <div class="okja-dashboard-field">
                                <label for="okja_paed_term_text"><?php esc_html_e( 'Badge Textfarbe', 'jhh-posts-block' ); ?></label>
                                <input id="okja_paed_term_text" class="okja-dashboard-color-picker" type="text" name="badge_text_color" value="#ffffff">
                            </div>
                        </div>
                        <div class="okja-dashboard-submit">
                            <button type="submit" class="okja-dashboard-button"><?php esc_html_e( 'Pädagogik speichern', 'jhh-posts-block' ); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
