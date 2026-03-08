<?php
/**
 * Plugin Name: OKJA_Angebote
 * Description: Flexibler Beitrags-/CPT-Block mit Live-Vorschau, Post-Typ-Auswahl, Taxonomie-Filtern/Badges, frei anordenbaren Elementen und Style-Optionen.
 * Author: Hubertoink
 * Version: 1.0.2
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'JHH_PB_URL', plugin_dir_url( __FILE__ ) );
define( 'JHH_PB_DIR', plugin_dir_path( __FILE__ ) );
define( 'JHH_PB_VERSION', '1.0.2' );

require_once JHH_PB_DIR . 'includes/admin-dashboard.php';
require_once JHH_PB_DIR . 'includes/admin-editor.php';
require_once JHH_PB_DIR . 'includes/admin-settings.php';

// Flush rewrite rules on activation so new CPT slugs work immediately
register_activation_hook( __FILE__, function() {
    // Force CPTs to be registered before flushing
    do_action( 'init' );
    flush_rewrite_rules();
    update_option( 'jhh_pb_version', JHH_PB_VERSION );
} );

// Flush rewrite rules once after plugin updates (e.g. new CPT slugs)
add_action( 'init', function() {
    $stored_version = (string) get_option( 'jhh_pb_version', '' );
    if ( $stored_version !== JHH_PB_VERSION ) {
        flush_rewrite_rules();
        update_option( 'jhh_pb_version', JHH_PB_VERSION );
    }
}, 99 );
// ------------------------------------------------------------
// 1. Globale Konstanten
// ------------------------------------------------------------
// Taxonomie-Slugs
define( 'JHH_TAX_JUGEND', 'jugendarbeit' );
define( 'JHH_TAX_PAED',   'paedagogik' );
define( 'JHH_TAX_TAGE',   'tage' );

// ------------------------------------------------------------
// 2. CPT & Taxonomien registrieren
// ----------------------------------------------------------

add_action( 'init', function() {

    // --- Custom Post Type "Angebot" ---
    register_post_type( 'angebot', [
        'labels' => [
            'name'          => __( 'Angebote', 'jhh-posts-block' ),
            'singular_name' => __( 'Angebot', 'jhh-posts-block' ),
            'add_new_item'  => __( 'Neues Angebot hinzufügen', 'jhh-posts-block' ),
            'edit_item'     => __( 'Angebot bearbeiten', 'jhh-posts-block' ),
        ],
        'public'       => true,
        'show_in_rest' => true,
        'has_archive'  => true,
        'menu_icon'    => 'dashicons-welcome-learn-more',
        'supports'     => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
    ] );

    // --- Taxonomie "Jugendarbeit" ---
    register_taxonomy( JHH_TAX_JUGEND, 'angebot', [
        'labels' => [
            'name'          => __( 'Jugendarbeit', 'jhh-posts-block' ),
            'singular_name' => __( 'Jugendarbeit', 'jhh-posts-block' ),
        ],
        'public'       => true,
        'show_in_rest' => true,
        'hierarchical' => true,
    ] );

    // --- Taxonomie "Pädagogik" ---
    register_taxonomy( JHH_TAX_PAED, 'angebot', [
        'labels' => [
            'name'          => __( 'Pädagogik', 'jhh-posts-block' ),
            'singular_name' => __( 'Pädagogik', 'jhh-posts-block' ),
        ],
        'public'       => true,
        'show_in_rest' => true,
        'hierarchical' => true,
    ] );

    // --- (Alt) Taxonomie "Tage" deaktiviert: wir verwenden feste Wochentage als Post-Meta
    // Aus Kompatibilitätsgründen registrieren wir sie "unsichtbar", damit Alt-Daten lesbar bleiben,
    // aber nicht mehr im Backend bearbeitet werden können.
    register_taxonomy( JHH_TAX_TAGE, 'angebot', [
        'labels' => [
            'name'          => __( 'Tage (veraltet)', 'jhh-posts-block' ),
            'singular_name' => __( 'Tag', 'jhh-posts-block' ),
        ],
        'public'       => false,
        'show_ui'      => false,
        'show_in_rest' => false,
        'hierarchical' => true,
    ] );

    // --- Custom Post Type "Angebotsevent" (A-Event) ---
    register_post_type( 'angebotsevent', [
        'labels' => [
            'name'               => __( 'A-Events', 'jhh-posts-block' ),
            'singular_name'      => __( 'A-Event', 'jhh-posts-block' ),
            'add_new'            => __( 'Neues A-Event', 'jhh-posts-block' ),
            'add_new_item'       => __( 'Neues A-Event hinzufügen', 'jhh-posts-block' ),
            'edit_item'          => __( 'A-Event bearbeiten', 'jhh-posts-block' ),
            'view_item'          => __( 'A-Event ansehen', 'jhh-posts-block' ),
            'all_items'          => __( 'A-Events', 'jhh-posts-block' ),
            'search_items'       => __( 'A-Events durchsuchen', 'jhh-posts-block' ),
            'not_found'          => __( 'Keine A-Events gefunden.', 'jhh-posts-block' ),
            'not_found_in_trash' => __( 'Keine A-Events im Papierkorb.', 'jhh-posts-block' ),
        ],
        'public'       => true,
        'show_in_rest' => true,
        'has_archive'  => true,
        'show_in_menu' => 'edit.php?post_type=angebot',
        'menu_icon'    => 'dashicons-calendar-alt',
        'supports'     => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
        'rewrite'      => [ 'slug' => 'angebotsevent', 'with_front' => false ],
    ] );

}, 0 );

// Admin list: show event date column for A-Events next to title
// ------------------------------------------------------------
// Plugin-Einstellungen: Globaler Staff Card Style + Farben
// ------------------------------------------------------------
function okja_get_settings_page_slug() {
    return 'okja-angebote-settings';
}

function okja_get_settings_parent_slug() {
    return 'edit.php?post_type=angebot';
}

/* ----------------------------------------------------------
 * Excerpt Jugendarbeiter: Term-Meta, Admin-UI, Save + Frontend-Renderer
 * ----------------------------------------------------------
 */
if ( ! defined( 'JHH_TAX_STAFF' ) ) {
    define( 'JHH_TAX_STAFF', defined( 'JHH_TAX_JUGEND' ) ? JHH_TAX_JUGEND : 'jugendarbeit' );
}

// Term-Meta registrieren
add_action( 'init', function () {
    if ( taxonomy_exists( JHH_TAX_STAFF ) ) {
        register_term_meta( JHH_TAX_STAFF, 'avatar_id', [
            'type' => 'integer',
            'single' => true,
            'sanitize_callback' => 'absint',
            'show_in_rest' => true,
            'auth_callback' => '__return_true',
        ] );
        register_term_meta( JHH_TAX_STAFF, 'funktion', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest' => true,
            'auth_callback' => '__return_true',
        ] );
        register_term_meta( JHH_TAX_STAFF, 'bio', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => 'wp_kses_post',
            'show_in_rest' => true,
            'auth_callback' => '__return_true',
        ] );
        register_term_meta( JHH_TAX_STAFF, 'contact', [
            'type' => 'string',
            'single' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest' => true,
            'auth_callback' => '__return_true',
        ] );
    }
} );

// Medien-Upload im Backend für die Staff-Taxonomie
add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( strpos( $hook, 'edit-tags.php' ) === false && strpos( $hook, 'term.php' ) === false ) return;
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( $screen && ! empty( $screen->taxonomy ) && $screen->taxonomy === JHH_TAX_STAFF ) {
        wp_enqueue_media();
        wp_enqueue_script(
            'jhh-term-media-uploader',
            JHH_PB_URL . 'assets/term-media-uploader.js',
            [ 'jquery' ],
            null,
            true
        );
    }
} );

// Backend: Felder beim Erstellen
add_action( JHH_TAX_STAFF . '_add_form_fields', function () {
    wp_nonce_field( 'jhh_staff_term_meta', 'jhh_staff_term_meta_nonce' ); ?>
    <div class="form-field">
        <label for="jhh_staff_funktion">Funktion/Rolle</label>
        <input type="text" name="jhh_staff_funktion" id="jhh_staff_funktion" placeholder="Welche Rolle hat die Person im Team?" />
    </div>
    <div class="form-field">
        <label for="jhh_staff_bio">Kurzvorstellung</label>
        <textarea name="jhh_staff_bio" id="jhh_staff_bio" rows="5" placeholder="Kurzvorstellung der Person"></textarea>
    </div>
    <div class="form-field">
        <label for="jhh_staff_contact">Kontakt</label>
        <input type="text" name="jhh_staff_contact" id="jhh_staff_contact" placeholder="E-Mail oder Telefon" />
    </div>
    <div class="form-field">
        <label for="jhh_staff_avatar_id">Bild ID</label>
        <input type="number" name="jhh_staff_avatar_id" id="jhh_staff_avatar_id" />
        <p class="description">Medien-ID des Profilbilds.</p>
    </div>
<?php } );

// Backend: Felder beim Bearbeiten
add_action( JHH_TAX_STAFF . '_edit_form_fields', function ( $term ) {
    $funktion  = get_term_meta( $term->term_id, 'funktion', true );
    $bio       = get_term_meta( $term->term_id, 'bio', true );
    $contact   = get_term_meta( $term->term_id, 'contact', true );
    $avatar_id = (int) get_term_meta( $term->term_id, 'avatar_id', true );
    wp_nonce_field( 'jhh_staff_term_meta', 'jhh_staff_term_meta_nonce' ); ?>
    <tr class="form-field">
        <th scope="row"><label for="jhh_staff_funktion">Funktion/Rolle</label></th>
        <td><input name="jhh_staff_funktion" id="jhh_staff_funktion" type="text" value="<?php echo esc_attr( $funktion ); ?>"></td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="jhh_staff_bio">Kurzvorstellung</label></th>
        <td><textarea name="jhh_staff_bio" id="jhh_staff_bio" rows="6"><?php echo esc_textarea( $bio ); ?></textarea></td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="jhh_staff_contact">Kontakt</label></th>
        <td><input name="jhh_staff_contact" id="jhh_staff_contact" type="text" value="<?php echo esc_attr( $contact ); ?>" placeholder="E-Mail oder Telefon"></td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="jhh_staff_avatar_id">Bild ID</label></th>
        <td>
            <input name="jhh_staff_avatar_id" id="jhh_staff_avatar_id" type="number" value="<?php echo esc_attr( $avatar_id ); ?>">
            <?php if ( $avatar_id ) echo wp_get_attachment_image( $avatar_id, 'thumbnail', false, array( 'style' => 'display:block;margin-top:8px;border-radius:6px;' ) ); ?>
        </td>
    </tr>
<?php } );

// Speichern der Zusatzfelder (Terms)
$__jhh_staff_save_cb = function ( $term_id ) {
    if ( ! isset( $_POST['jhh_staff_term_meta_nonce'] ) || ! wp_verify_nonce( $_POST['jhh_staff_term_meta_nonce'], 'jhh_staff_term_meta' ) ) {
        return;
    }
    if ( isset( $_POST['jhh_staff_funktion'] ) ) {
        update_term_meta( $term_id, 'funktion', sanitize_text_field( wp_unslash( $_POST['jhh_staff_funktion'] ) ) );
    }
    if ( isset( $_POST['jhh_staff_bio'] ) ) {
        update_term_meta( $term_id, 'bio', wp_kses_post( wp_unslash( $_POST['jhh_staff_bio'] ) ) );
    }
    if ( isset( $_POST['jhh_staff_contact'] ) ) {
        update_term_meta( $term_id, 'contact', sanitize_text_field( wp_unslash( $_POST['jhh_staff_contact'] ) ) );
    }
    if ( isset( $_POST['jhh_staff_avatar_id'] ) ) {
        update_term_meta( $term_id, 'avatar_id', absint( $_POST['jhh_staff_avatar_id'] ) );
    }
};
add_action( 'created_' . JHH_TAX_STAFF, $__jhh_staff_save_cb );
add_action( 'edited_' . JHH_TAX_STAFF,  $__jhh_staff_save_cb );



// ------------------------------------------------------------
// 3. Assets (CSS/JS) registrieren & Editor-Daten bereitstellen
// ------------------------------------------------------------




add_action( 'init', function() {
    // Cache-busting: use file mtime so editor always gets the newest assets after updates.
    $legacy_style_css = JHH_PB_DIR . 'assets/style.css';
    $posts_css        = JHH_PB_DIR . 'assets/blocks.css';
    $single_css       = JHH_PB_DIR . 'assets/single.css';
    $team_css         = JHH_PB_DIR . 'assets/team.css';
    $events_css       = JHH_PB_DIR . 'assets/events.css';
    $editor_css       = JHH_PB_DIR . 'assets/editor.css';
    $editor_js        = JHH_PB_DIR . 'assets/editor.js';
    $carousel_js      = JHH_PB_DIR . 'assets/carousel.js';
    $tilt_js          = JHH_PB_DIR . 'assets/tilt-effect.js';
    $event_modal_css  = JHH_PB_DIR . 'assets/event-modal.css';
    $event_modal_js   = JHH_PB_DIR . 'assets/event-modal.js';

    $v_legacy  = file_exists( $legacy_style_css ) ? JHH_PB_VERSION . '.' . filemtime( $legacy_style_css ) : JHH_PB_VERSION;
    $v_posts   = file_exists( $posts_css )        ? JHH_PB_VERSION . '.' . filemtime( $posts_css )        : JHH_PB_VERSION;
    $v_single  = file_exists( $single_css )       ? JHH_PB_VERSION . '.' . filemtime( $single_css )       : JHH_PB_VERSION;
    $v_team    = file_exists( $team_css )         ? JHH_PB_VERSION . '.' . filemtime( $team_css )         : JHH_PB_VERSION;
    $v_events  = file_exists( $events_css )       ? JHH_PB_VERSION . '.' . filemtime( $events_css )       : JHH_PB_VERSION;
    $v_editorc = file_exists( $editor_css )       ? JHH_PB_VERSION . '.' . filemtime( $editor_css )       : JHH_PB_VERSION;
    $v_editorj = file_exists( $editor_js )        ? JHH_PB_VERSION . '.' . filemtime( $editor_js )        : JHH_PB_VERSION;
    $v_car     = file_exists( $carousel_js )      ? JHH_PB_VERSION . '.' . filemtime( $carousel_js )      : JHH_PB_VERSION;
    $v_tilt    = file_exists( $tilt_js )          ? JHH_PB_VERSION . '.' . filemtime( $tilt_js )          : JHH_PB_VERSION;
    $v_modal_c = file_exists( $event_modal_css )  ? JHH_PB_VERSION . '.' . filemtime( $event_modal_css )  : JHH_PB_VERSION;
    $v_modal_j = file_exists( $event_modal_js )   ? JHH_PB_VERSION . '.' . filemtime( $event_modal_js )   : JHH_PB_VERSION;

    wp_register_style( 'jhh-posts-block-style', JHH_PB_URL . 'assets/blocks.css', [], $v_posts );
    wp_register_style( 'jhh-posts-block-posts-style', JHH_PB_URL . 'assets/blocks.css', [], $v_posts );
    wp_register_style( 'jhh-posts-block-single-style', JHH_PB_URL . 'assets/single.css', [], $v_single );
    wp_register_style( 'jhh-posts-block-team-style', JHH_PB_URL . 'assets/team.css', [], $v_team );
    wp_register_style( 'jhh-posts-block-events-style', JHH_PB_URL . 'assets/events.css', [], $v_events );
    wp_register_style( 'jhh-posts-block-event-modal-style', JHH_PB_URL . 'assets/event-modal.css', [ 'jhh-posts-block-events-style' ], $v_modal_c );
    wp_register_style( 'jhh-posts-block-legacy-style', JHH_PB_URL . 'assets/style.css', [], $v_legacy );
    wp_register_style( 'jhh-posts-block-editor-style', JHH_PB_URL . 'assets/editor.css', [ 'wp-edit-blocks' ], $v_editorc );

    wp_register_script(
        'jhh-posts-block-editor',
        JHH_PB_URL . 'assets/editor.js',
        [ 'wp-blocks', 'wp-element', 'wp-components', 'wp-editor', 'wp-block-editor', 'wp-i18n', 'wp-data', 'wp-server-side-render' ],
        $v_editorj,
        true
    );

    // Frontend carousel script
    wp_register_script(
        'jhh-posts-carousel',
        JHH_PB_URL . 'assets/carousel.js',
        [],
        $v_car,
        true
    );

    // Frontend tilt effect script
    wp_register_script(
        'jhh-posts-tilt',
        JHH_PB_URL . 'assets/tilt-effect.js',
        [],
        $v_tilt,
        true
    );

    wp_register_script(
        'jhh-posts-event-modal',
        JHH_PB_URL . 'assets/event-modal.js',
        [],
        $v_modal_j,
        true
    );

add_action( 'enqueue_block_editor_assets', function() {

    // Editor-Script einreihen
    wp_enqueue_script( 'jhh-posts-block-editor' );
    wp_enqueue_style( 'jhh-posts-block-editor-style' );

    // Taxonomien holen
    $jugend_terms = taxonomy_exists( JHH_TAX_JUGEND ) ? get_terms( [ 'taxonomy' => JHH_TAX_JUGEND, 'hide_empty' => false ] ) : [];
    $paed_terms   = taxonomy_exists( JHH_TAX_PAED )   ? get_terms( [ 'taxonomy' => JHH_TAX_PAED,   'hide_empty' => false ] ) : [];
    $tage_terms   = taxonomy_exists( JHH_TAX_TAGE )   ? get_terms( [ 'taxonomy' => JHH_TAX_TAGE,   'hide_empty' => false ] ) : [];

    // Localize wie gehabt
    wp_localize_script( 'jhh-posts-block-editor', 'JHH_POSTS_BLOCK_DATA', [
        'taxonomies' => [
            'jugendarbeit' => array_map( fn( $t ) => [ 'id' => (int) $t->term_id, 'name' => $t->name ], $jugend_terms ),
            'paedagogik'   => array_map( fn( $t ) => [ 'id' => (int) $t->term_id, 'name' => $t->name ], $paed_terms ),
            'tage'         => array_map( fn( $t ) => [ 'id' => (int) $t->term_id, 'name' => $t->name ], $tage_terms ),
        ],
        'postTypes' => jhh_pb_get_post_types(),
        // Angebote list for Events block dropdown
        'angebote' => array_map( function( $p ) {
            return [ 'id' => (int) $p->ID, 'title' => $p->post_title ];
        }, get_posts( [ 'post_type' => 'angebot', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'post_status' => 'publish' ] ) ),
    ] );
});

if ( ! function_exists( 'jhh_pb_enqueue_frontend_styles' ) ) {
    function jhh_pb_enqueue_frontend_styles( $groups ) {
        $groups = array_values( array_unique( array_filter( (array) $groups ) ) );

        if ( empty( $groups ) ) {
            return;
        }

        $handle_map = [
            'posts'  => 'jhh-posts-block-posts-style',
            'single' => 'jhh-posts-block-single-style',
            'team'   => 'jhh-posts-block-team-style',
            'events' => 'jhh-posts-block-events-style',
        ];

        foreach ( $groups as $group ) {
            if ( isset( $handle_map[ $group ] ) ) {
                wp_enqueue_style( $handle_map[ $group ] );
            }
        }
    }
}

if ( ! function_exists( 'okja_get_event_link_mode' ) ) {
    function okja_get_event_link_mode() {
        $mode = get_option( 'okja_event_link_mode', 'single' );
        return in_array( $mode, [ 'single', 'modal' ], true ) ? $mode : 'single';
    }
}

if ( ! function_exists( 'jhh_pb_use_event_modal' ) ) {
    function jhh_pb_use_event_modal() {
        return okja_get_event_link_mode() === 'modal';
    }
}

if ( ! function_exists( 'jhh_pb_show_event_calendar_button' ) ) {
    function jhh_pb_show_event_calendar_button() {
        return get_option( 'okja_event_calendar_button', '1' ) === '1';
    }
}

if ( ! function_exists( 'jhh_pb_show_event_modal_permalink_button' ) ) {
    function jhh_pb_show_event_modal_permalink_button() {
        return get_option( 'okja_event_modal_permalink_button', '1' ) === '1';
    }
}

if ( ! function_exists( 'jhh_pb_enqueue_event_modal_assets' ) ) {
    function jhh_pb_enqueue_event_modal_assets() {
        static $localized = false;

        if ( ! jhh_pb_use_event_modal() ) {
            return;
        }

        wp_enqueue_style( 'jhh-posts-block-event-modal-style' );
        wp_enqueue_script( 'jhh-posts-event-modal' );

        if ( ! $localized ) {
            wp_localize_script( 'jhh-posts-event-modal', 'jhhEventModalData', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'jhh_pb_event_modal' ),
                'showPermalink' => jhh_pb_show_event_modal_permalink_button(),
                'labels'  => [
                    'close'   => __( 'Schließen', 'jhh-posts-block' ),
                    'loading' => __( 'Event wird geladen...', 'jhh-posts-block' ),
                    'error'   => __( 'Das Event konnte gerade nicht geladen werden.', 'jhh-posts-block' ),
                    'open'    => __( 'Event als Seite öffnen', 'jhh-posts-block' ),
                ],
            ] );
            $localized = true;
        }
    }
}

if ( ! function_exists( 'jhh_pb_get_event_link_attributes' ) ) {
    function jhh_pb_get_event_link_attributes( $event_id ) {
        $event_id = (int) $event_id;
        if ( $event_id <= 0 ) {
            return '';
        }

        $attributes = [
            'data-jhh-event-id' => (string) $event_id,
        ];

        if ( jhh_pb_use_event_modal() ) {
            $attributes['data-jhh-event-modal'] = '1';
        }

        $html = '';
        foreach ( $attributes as $name => $value ) {
            $html .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( $value ) );
        }

        return $html;
    }
}


// Hilfsfunktion: HEX abdunkeln (z.B. 0.25 = 25%)
if ( ! function_exists( 'jhh_hex_darken' ) ) {
    function jhh_hex_darken( $hex, $strength = 0.25 ) {
        $hex = trim( $hex );
        if ( $hex === '' ) return $hex;

        // #RGB -> #RRGGBB
        if ( preg_match( '/^#([0-9a-f]{3})$/i', $hex, $m ) ) {
            $r = str_repeat( $m[1][0], 2 );
            $g = str_repeat( $m[1][1], 2 );
            $b = str_repeat( $m[1][2], 2 );
            $hex = "#{$r}{$g}{$b}";
        }
        if ( ! preg_match( '/^#([0-9a-f]{6})$/i', $hex, $m ) ) {
            return $hex;
        }

        $r = hexdec( substr( $m[1], 0, 2 ) );
        $g = hexdec( substr( $m[1], 2, 2 ) );
        $b = hexdec( substr( $m[1], 4, 2 ) );

        $factor = max( 0, min( 1, $strength ) );
        $r = (int) max( 0, round( $r * ( 1 - $factor ) ) );
        $g = (int) max( 0, round( $g * ( 1 - $factor ) ) );
        $b = (int) max( 0, round( $b * ( 1 - $factor ) ) );

        return sprintf( '#%02x%02x%02x', $r, $g, $b );
    }
}

/**
 * Gibt dynamische CSS-Variablen für alle Pädagogik-Terms aus.
 * - Setzt für jeden Term: --jhh-term-<slug>-bg und --jhh-term-<slug>-color
 * - Optional: Dark-Mode-Override für Badges
 */
if ( ! function_exists( 'jhh_output_paed_dynamic_css' ) ) {
    function jhh_output_paed_dynamic_css() {

        // Taxonomie-Slug ermitteln (Konstante oder gängige Fallbacks)
        $tax = null;
        if ( defined( 'JHH_TAX_PAED' ) && taxonomy_exists( JHH_TAX_PAED ) ) {
            $tax = JHH_TAX_PAED;
        } elseif ( taxonomy_exists( 'paed' ) ) {
            $tax = 'paed';
        } elseif ( taxonomy_exists( 'paedagogik' ) ) {
            $tax = 'paedagogik';
        }
        if ( ! $tax ) return;

        $terms = get_terms( [
            'taxonomy'   => $tax,
            'hide_empty' => false,
        ] );
        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            return;
        }

        // Globale Fallbacks (greifen in deinem CSS als zweite Werte in var(..., Fallback))
        $bg_fallback_default  = '#E0E0E0';
        $txt_fallback_default = '#333333';

        $css = "<style id=\"jhh-paed-dynamic-css\">\n";

        // 1) Variablen zentral auf :root setzen, damit sie überall verfügbar sind
        $css .= ":root{\n";
        foreach ( $terms as $term ) {
            $slug = sanitize_title( $term->slug );
            $bg_color  = trim( (string) get_term_meta( $term->term_id, 'badge_bg_color', true ) );
            $txt_color = trim( (string) get_term_meta( $term->term_id, 'badge_text_color', true ) );

            if ( $bg_color !== '' ) {
                $css .= "--jhh-term-{$slug}-bg: {$bg_color};\n";
            }
            if ( $txt_color !== '' ) {
                $css .= "--jhh-term-{$slug}-color: {$txt_color};\n";
            }
        }
        $css .= "}\n";

        // 2) Optional: Direkter Style für Badges (falls irgendwo kein Child-CSS greift)
        $dark_sel = wp_strip_all_tags( okja_get_dark_selector() );

        foreach ( $terms as $term ) {
            $slug = sanitize_title( $term->slug );

            // Archiv-/Loop-Badges aus dem Plugin UND Single-Badges aus dem Child-Theme
            $selectors = [
                ".jhh-badge.term-{$slug}",
                ".jhh-post-taxonomies .badge.term-{$slug}",
            ];
            $selector_list = implode(',', $selectors);

            // Normalmodus mit Variablen + Fallbacks
            $css .= "{$selector_list}{";
            $css .= "background-color: var(--jhh-term-{$slug}-bg, {$bg_fallback_default});";
            $css .= "color: var(--jhh-term-{$slug}-color, {$txt_fallback_default});";
            $css .= "}\n";

            // Dark-Mode: Text weiß, BG etwas dunkler
            $dark_selectors = array_map(
                fn($sel) => "{$dark_sel} {$sel}",
                $selectors
            );
            $css .= implode(',', $dark_selectors) . '{';
            $css .= "color:#fff;";
            $css .= "background-color: color-mix(in srgb, var(--jhh-term-{$slug}-bg, {$bg_fallback_default}) 75%, black 25%);";
            $css .= "}\n";

            // Fallback für Browser ohne color-mix
            $base_for_fallback = get_term_meta( $term->term_id, 'badge_bg_color', true );
            $base_for_fallback = $base_for_fallback ? $base_for_fallback : $bg_fallback_default;
            $darkened = jhh_hex_darken( $base_for_fallback, 0.25 );

            $css .= "@supports not (background-color: color-mix(in srgb, white, black)) {";
            $css .= "{$dark_sel} {$selector_list}{background-color: {$darkened};}";
            $css .= "}\n";
        }

        $css .= "</style>\n";
        echo $css;
    }

    // Früh genug anhängen, damit Variablen vor Theme/Child-CSS vorhanden sind
    add_action( 'wp_head', 'jhh_output_paed_dynamic_css', 20 );
}

/**
 * Helper: Get the configured dark/light CSS selectors.
 */
if ( ! function_exists( 'okja_get_dark_selector' ) ) {
    function okja_get_dark_selector() {
        return get_option( 'okja_dark_selector', 'html[data-neve-theme="dark"]' );
    }
}
if ( ! function_exists( 'okja_get_light_selector' ) ) {
    function okja_get_light_selector() {
        return get_option( 'okja_light_selector', 'html[data-neve-theme="light"]' );
    }
}

/**
 * Output dynamic theme-aware CSS for event cards, inline event tiles,
 * back-link, staff cards, etc. using the configured dark/light selectors.
 * This replaces the need for hardcoded selectors in static CSS.
 */
if ( ! function_exists( 'okja_output_theme_dynamic_css' ) ) {
    function okja_output_theme_dynamic_css() {
        $mode  = get_option( 'okja_color_mode', 'auto' );
        $dark  = wp_strip_all_tags( okja_get_dark_selector() );
        $light = wp_strip_all_tags( okja_get_light_selector() );

        $css = "<style id=\"okja-theme-dynamic-css\">\n";

        // -------------------------------------------------------
        // DARK MODE styles
        // We wrap in the configured dark selector (or :root for forced dark)
        // -------------------------------------------------------
        if ( $mode === 'dark' ) {
            $d = ':root';
        } elseif ( $mode === 'auto' ) {
            $d = $dark;
        } else {
            $d = ''; // light mode forced = no dark rules
        }

        if ( $d ) {
            // Event block cards (Angebotsevent card tiles)
            $css .= "{$d} .jhh-event-card { background: #1e1b1b; color: #e0e0e0; }\n";
            $css .= "{$d} .jhh-event-title { color: #fff; }\n";
            $css .= "{$d} .jhh-event-badge { background: rgba(255,255,255,0.08); color: #d0d0d0; }\n";

            // Event inline cards (in Angebot single page)
            $css .= "{$d} .jhh-events-section h3 { color: #fff; }\n";
            $css .= "{$d} .jhh-event-inline-card { background: #1e1b1b; color: #e0e0e0; }\n";
            $css .= "{$d} .jhh-event-inline-title { color: #fff; }\n";
            $css .= "{$d} .jhh-event-inline-day { color: #fff; }\n";
            $css .= "{$d} .jhh-event-inline-month { color: #b9aaff; }\n";
            $css .= "{$d} .jhh-event-inline-meta { color: #a0a0a0; }\n";
            $css .= "{$d} .jhh-event-inline-date-block { background: rgba(185, 170, 255, 0.12); }\n";

            // Event details card (single event page)
            $css .= "{$d} .jhh-event-details-card { background-color: var(--jhh-event-card-bg, #1e1b1b); color: var(--jhh-event-card-text, #fff); }\n";
            $css .= "{$d} .jhh-event-detail-value { color: var(--jhh-event-card-text, #fff); }\n";
            $css .= "{$d} .jhh-event-detail-label { color: var(--jhh-event-card-label, #888); }\n";

            // Staff/Team simple cards (force dark variant in dark mode)
            $css .= "{$d} .jhh-staff-card.bg-simple, {$d} .jhh-team-card.bg-simple { background: #1f1b1b; color: #f5f6f7; }\n";
            $css .= "{$d} .jhh-staff-card.bg-simple .jhh-staff-contact, {$d} .jhh-team-card.bg-simple .jhh-team-contact { color: #93c5fd; }\n";

            // Related events
            $css .= "{$d} .jhh-related-events h3 { color: #fff; }\n";
            $css .= "{$d} .jhh-event-cal-trigger { background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.12); color: rgba(255,255,255,0.82); }\n";
            $css .= "{$d} .jhh-event-cal-trigger:hover { background: rgba(255,255,255,0.12); border-color: rgba(255,255,255,0.2); color: #fff; }\n";
            $css .= "{$d} .jhh-event-cal-menu { background: #1c1a1e; border-color: rgba(255,255,255,0.1); }\n";
            $css .= "{$d} .jhh-event-cal-item { color: rgba(255,255,255,0.85); }\n";
            $css .= "{$d} .jhh-event-cal-item:hover { background: rgba(255,255,255,0.08); color: #fff; }\n";

            // Back link
            $css .= "{$d} .jhh-back-link { background: #2b2727; color: #fff; }\n";

            // Existing staff/team card dark rules (replicate with dynamic selector)
            $css .= "{$d} .jhh-team-card.bg-dark, {$d} .jhh-team-card.bg-simple { background:#1f1b1b; color:#f5f6f7; }\n";
            $css .= "{$d} .jhh-team-card.bg-none { background:rgba(255,255,255,0.03); color:#f6f6f6; border-color:rgba(255,255,255,0.12); }\n";
            $css .= "{$d} .jhh-team-card, {$d} .jhh-team-card .jhh-team-meta, {$d} .jhh-team-card .jhh-team-name, {$d} .jhh-team-card .jhh-team-role, {$d} .jhh-team-card .jhh-team-bio, {$d} .jhh-team-card p, {$d} .jhh-team-card a { color:#f5f6f7 !important; }\n";
            $css .= "{$d} .jhh-team-card .jhh-team-role, {$d} .jhh-team-card .jhh-team-bio { opacity:1; }\n";
            $css .= "{$d} .jhh-team-card.bg-blue .jhh-team-contact, {$d} .jhh-team-card.bg-purple .jhh-team-contact, {$d} .jhh-team-card.bg-sunset .jhh-team-contact, {$d} .jhh-team-card.bg-rainbow .jhh-team-contact, {$d} .jhh-team-card.bg-none .jhh-team-contact { color:#93c5fd; }\n";
            $css .= "{$d} .jhh-team-card.bg-blue { background:linear-gradient(135deg,#3a3331,#2f2b28); color:#f5f6f7; }\n";
            $css .= "{$d} .jhh-team-card.bg-purple { background:linear-gradient(135deg,#3a2f2e,#2e292c); color:#f5f6f7; }\n";
            $css .= "{$d} .jhh-team-card.bg-sunset { background:linear-gradient(135deg,#3b322f,#2f2a27); color:#f5f6f7; }\n";
            $css .= "{$d} .jhh-team-card.bg-rainbow { background:linear-gradient(135deg,#38322e,#2c2725); color:#f5f6f7; }\n";
            $css .= "{$d} .jhh-team-card.bg-glass { background:rgba(255,255,255,0.05); border-color:rgba(255,255,255,0.1); }\n";
            $css .= "{$d} .jhh-team-card.bg-gradient-border { background:#111; }\n";
            $css .= "{$d} .jhh-team-card.bg-muted { background:linear-gradient(145deg,#1a1d1e,#12171a); }\n";
            $css .= "{$d} .jhh-team-card.bg-charcoal { background:linear-gradient(160deg,#18191a,#2d2e2f); }\n";
            $css .= "{$d} .jhh-team-card.bg-aurora { background:radial-gradient(circle,rgba(255,255,255,0.20),rgba(255,255,255,0.05)); }\n";
            $css .= "{$d} .jhh-team-card.bg-aurora::before { filter:blur(42px); opacity:.38; }\n";
            $css .= "{$d} .jhh-team-card.bg-aurora::after { filter:blur(36px); opacity:.28; }\n";

            // Staff card (single angebot) dark
            $css .= "{$d} .jhh-staff-card.bg-aurora { background:radial-gradient(circle,rgba(255,255,255,0.20),rgba(255,255,255,0.05)); }\n";
        }

        // -------------------------------------------------------
        // LIGHT MODE styles
        // -------------------------------------------------------
        if ( $mode === 'light' ) {
            $l = ':root';
        } elseif ( $mode === 'auto' ) {
            $l = $light;
        } else {
            $l = ''; // dark mode forced = no light rules
        }

        if ( $l ) {
            // Event block cards – light
            $css .= "{$l} .jhh-event-card { background: #f5f1eb; color: #333; }\n";
            $css .= "{$l} .jhh-event-card::before { background: linear-gradient(90deg, #ff6a00, #ee0979, #8a2be2, #4169e1, #00c6ff); }\n";
            $css .= "{$l} .jhh-event-title { color: #1a1a1a; }\n";
            $css .= "{$l} .jhh-event-badge { background: rgba(0,0,0,0.06); color: #555; }\n";
            $css .= "{$l} .jhh-event-body { color: #444; }\n";

            // Event inline cards – light
            $css .= "{$l} .jhh-events-section h3 { color: #1a1a1a; }\n";
            $css .= "{$l} .jhh-event-inline-card { background: #f5f1eb; color: #333; }\n";
            $css .= "{$l} .jhh-event-inline-card::before { background: linear-gradient(90deg, #ff6a00, #ee0979, #8a2be2, #4169e1, #00c6ff); }\n";
            $css .= "{$l} .jhh-event-inline-title { color: #1a1a1a; }\n";
            $css .= "{$l} .jhh-event-inline-day { color: #1a1a1a; }\n";
            $css .= "{$l} .jhh-event-inline-month { color: #7c3aed; }\n";
            $css .= "{$l} .jhh-event-inline-meta { color: #666; }\n";
            $css .= "{$l} .jhh-event-inline-date-block { background: rgba(124, 58, 237, 0.08); }\n";

            // Event details card – light
            $css .= "{$l} .jhh-event-details-card { background-color: #f5f1eb; color: #333; }\n";
            $css .= "{$l} .jhh-event-detail-value { color: #1a1a1a; }\n";
            $css .= "{$l} .jhh-event-detail-label { color: #888; }\n";
            $css .= "{$l} .jhh-event-linked-angebot { border-top-color: rgba(0,0,0,0.08); }\n";
            $css .= "{$l} .jhh-event-cal-trigger { background: rgba(0,0,0,0.05); border-color: rgba(0,0,0,0.08); color: #1f1a17; }\n";
            $css .= "{$l} .jhh-event-cal-trigger:hover { background: rgba(0,0,0,0.08); border-color: rgba(0,0,0,0.14); color: #111; }\n";
            $css .= "{$l} .jhh-event-cal-menu { background: #fff; border-color: rgba(0,0,0,0.1); box-shadow: 0 12px 48px rgba(0,0,0,0.15); }\n";
            $css .= "{$l} .jhh-event-cal-item { color: #333; }\n";
            $css .= "{$l} .jhh-event-cal-item:hover { background: rgba(0,0,0,0.05); color: #111; }\n";

            // Staff/Team simple cards – light
            $css .= "{$l} .jhh-staff-card.bg-simple, {$l} .jhh-team-card.bg-simple { background: #f5f1eb; color: #1a1a1a; }\n";
            $css .= "{$l} .jhh-staff-card.bg-simple .jhh-staff-contact, {$l} .jhh-team-card.bg-simple .jhh-team-contact { color: #7c3aed; }\n";
            $css .= "{$l} .jhh-staff-card.bg-simple .jhh-staff-name, {$l} .jhh-staff-card.bg-simple .jhh-staff-role, {$l} .jhh-staff-card.bg-simple .jhh-staff-bio, {$l} .jhh-staff-card.bg-simple a, {$l} .jhh-team-card.bg-simple .jhh-team-name, {$l} .jhh-team-card.bg-simple .jhh-team-role, {$l} .jhh-team-card.bg-simple .jhh-team-bio, {$l} .jhh-team-card.bg-simple a { color: #1a1a1a !important; }\n";

            // Related offers (single angebot) – light
            $css .= "{$l} .jhh-single-angebot .jhh-related-item { background: #f5f1eb; border: 1px solid rgba(0,0,0,0.08); }\n";
            $css .= "{$l} .jhh-single-angebot .jhh-related-title { color: #1a1a1a !important; }\n";
            $css .= "{$l} .jhh-single-angebot .jhh-related-hover { background: linear-gradient(180deg, rgba(245,241,235,0.96) 0%, rgba(236,229,220,0.98) 100%); }\n";
            $css .= "{$l} .jhh-single-angebot .jhh-related-staff, {$l} .jhh-single-angebot .jhh-related-schedule { color: #333; }\n";

            // Related events – light
            $css .= "{$l} .jhh-related-events h3 { color: #1a1a1a; }\n";

            // Back link – light
            $css .= "{$l} .jhh-back-link { background: #f0ebe4; color: #333; }\n";

            // Carousel dots – light (dark dots on light background)
            $css .= "{$l} .jhh-carousel-dots button { background: rgba(0,0,0,.25); }\n";
            $css .= "{$l} .jhh-carousel-dots button[aria-current=\"true\"] { background: #333; }\n";
        }

        $css .= "</style>\n";
        echo $css;
    }
    add_action( 'wp_head', 'okja_output_theme_dynamic_css', 25 );
}


	
// 🎨 WordPress Color Picker für Pädagogik-Taxonomie aktivieren (Erstellen + Bearbeiten)
add_action( 'admin_enqueue_scripts', function( $hook_suffix ) {
    // Greift auf "Neue Begriffe hinzufügen" (edit-tags.php) und "Begriff bearbeiten" (term.php)
    if (
        in_array( $hook_suffix, [ 'edit-tags.php', 'term.php' ], true )
        && isset( $_GET['taxonomy'] )
        && $_GET['taxonomy'] === JHH_TAX_PAED
    ) {
        // WP Color Picker CSS + JS laden
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );

        // Picker initialisieren
        wp_add_inline_script(
            'wp-color-picker',
            'jQuery(document).ready(function($){
                $(".color-picker").wpColorPicker();
            });'
        );
    }
});


	// 🟢 Farbfelder BEIM ERSTELLEN neuer Begriffe
add_action( 'paedagogik_add_form_fields', function() {
    ?>
    <div class="form-field">
        <label for="badge_bg_color">Badge Hintergrundfarbe</label>
        <input type="text" name="badge_bg_color" id="badge_bg_color" value="" class="color-picker" />
        <p class="description">Wähle die Hintergrundfarbe für diesen Pädagogik-Begriff.</p>
    </div>
    <div class="form-field">
        <label for="badge_text_color">Badge Textfarbe</label>
        <input type="text" name="badge_text_color" id="badge_text_color" value="" class="color-picker" />
        <p class="description">Wähle die Textfarbe für diesen Pädagogik-Begriff.</p>
    </div>
    <?php
}, 10, 2);

// 🟢 Farbfelder BEIM BEARBEITEN bestehender Begriffe
add_action( 'paedagogik_edit_form_fields', function( $term ) {
    $bg  = get_term_meta( $term->term_id, 'badge_bg_color', true );
    $txt = get_term_meta( $term->term_id, 'badge_text_color', true );
    ?>
    <tr class="form-field">
        <th scope="row"><label for="badge_bg_color">Badge Hintergrundfarbe</label></th>
        <td>
            <input type="text" name="badge_bg_color" id="badge_bg_color"
                   value="<?php echo esc_attr( $bg ); ?>" class="color-picker" />
            <p class="description">Wähle die Hintergrundfarbe für diesen Pädagogik-Begriff.</p>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="badge_text_color">Badge Textfarbe</label></th>
        <td>
            <input type="text" name="badge_text_color" id="badge_text_color"
                   value="<?php echo esc_attr( $txt ); ?>" class="color-picker" />
            <p class="description">Wähle die Textfarbe für diesen Pädagogik-Begriff.</p>
        </td>
    </tr>
    <?php
}, 10, 2);

// 🟢 Speichern BEIM ERSTELLEN
add_action( 'created_paedagogik', function( $term_id ) {
    if ( isset( $_POST['badge_bg_color'] ) ) {
        update_term_meta( $term_id, 'badge_bg_color', sanitize_hex_color( $_POST['badge_bg_color'] ) );
    }
    if ( isset( $_POST['badge_text_color'] ) ) {
        update_term_meta( $term_id, 'badge_text_color', sanitize_hex_color( $_POST['badge_text_color'] ) );
    }
});

// 🟢 Speichern BEIM BEARBEITEN
add_action( 'edited_paedagogik', function( $term_id ) {
    if ( isset( $_POST['badge_bg_color'] ) ) {
        update_term_meta( $term_id, 'badge_bg_color', sanitize_hex_color( $_POST['badge_bg_color'] ) );
    }
    if ( isset( $_POST['badge_text_color'] ) ) {
        update_term_meta( $term_id, 'badge_text_color', sanitize_hex_color( $_POST['badge_text_color'] ) );
    }
});
	
    register_block_type( 'jhh/posts', [
        'api_version'     => 2,
        'style'           => 'jhh-posts-block-posts-style',
        'editor_style'    => 'jhh-posts-block-editor-style',
        'editor_script'   => 'jhh-posts-block-editor',
        'render_callback' => 'jhh_pb_render',
        'attributes'      => [
            'postType'        => [ 'type' => 'string',  'default' => 'angebot' ],
            'postsToShow'     => [ 'type' => 'number',  'default' => 6 ],
            'order'           => [ 'type' => 'string',  'default' => 'DESC' ],
            'orderBy'         => [ 'type' => 'string',  'default' => 'date' ],
            'layout'          => [ 'type' => 'string',  'default' => 'grid' ],
            'columns'         => [ 'type' => 'number',  'default' => 3 ],
            'gap'             => [ 'type' => 'number',  'default' => 16 ],
            // Carousel options
            'carouselAutoplay'     => [ 'type' => 'boolean', 'default' => true ],
            'carouselInterval'     => [ 'type' => 'number',  'default' => 7 ],
            'carouselPauseOnHover' => [ 'type' => 'boolean', 'default' => true ],
            'carouselIndicators'   => [ 'type' => 'boolean', 'default' => true ],
            'carouselArrows'       => [ 'type' => 'boolean', 'default' => true ],
            'carouselBlur'         => [ 'type' => 'boolean', 'default' => true ],
            'showImage'       => [ 'type' => 'boolean', 'default' => true ],
            'imageSize'       => [ 'type' => 'string',  'default' => 'medium' ],
            'imageHoverEffect' => [ 'type' => 'string',  'default' => 'none' ],
            'showTitle'       => [ 'type' => 'boolean', 'default' => true ],
            'showDate'        => [ 'type' => 'boolean', 'default' => true ],
            'showAuthor'      => [ 'type' => 'boolean', 'default' => false ],
            'showExcerpt'     => [ 'type' => 'boolean', 'default' => true ],
            'excerptLength'   => [ 'type' => 'number',  'default' => 20 ],
            'showEventImageBadge' => [ 'type' => 'boolean', 'default' => false ],
            'showReadMore'    => [ 'type' => 'boolean', 'default' => true ],
            'showGradientLine' => [ 'type' => 'boolean', 'default' => false ],
            'gradientMarginTop' => [ 'type' => 'number', 'default' => 16 ],
            'gradientMarginBottom' => [ 'type' => 'number', 'default' => 16 ],

            // Taxonomie-Filter
            'termsJugend'     => [ 'type' => 'array',   'default' => [] ],
            'termsPaed'       => [ 'type' => 'array',   'default' => [] ],
            'termsTage'       => [ 'type' => 'array',   'default' => [] ],

            // Taxonomie-Anzeige (Badges)
            'showTaxonomies'  => [ 'type' => 'boolean', 'default' => false ],
            'showTaxJugend'   => [ 'type' => 'boolean', 'default' => true ],
            'showTaxPaed'     => [ 'type' => 'boolean', 'default' => true ],
            'showTaxTage'     => [ 'type' => 'boolean', 'default' => true ],

            // Reihenfolge der Elemente
            'elementsOrder'   => [
                'type'    => 'array',
                'default' => [ 'image', 'title', 'meta', 'taxonomies', 'excerpt', 'readmore', 'gradientline' ]
            ],

            // Back URL for single page (appended as ?back=... to post links)
            'backUrl'         => [ 'type' => 'string', 'default' => '' ],
            'singleShowEvents'=> [ 'type' => 'boolean', 'default' => true ],

            // Style-Attribute
            'colorTitle'         => [ 'type' => 'string', 'default' => '' ],
            'colorTitleHover'    => [ 'type' => 'string', 'default' => '' ],
            'colorText'          => [ 'type' => 'string', 'default' => '' ],
            'colorReadMore'      => [ 'type' => 'string', 'default' => '' ],
            'colorReadMoreHover' => [ 'type' => 'string', 'default' => '' ],
            'colorBadgeJugBg'    => [ 'type' => 'string', 'default' => '' ],
            'colorBadgeJugTxt'   => [ 'type' => 'string', 'default' => '' ],
            'colorBadgePaedBg'   => [ 'type' => 'string', 'default' => '' ],
            'colorBadgePaedTxt'  => [ 'type' => 'string', 'default' => '' ],
            'colorBadgeTageBg'   => [ 'type' => 'string', 'default' => '' ],
            'colorBadgeTageTxt'  => [ 'type' => 'string', 'default' => '' ],
            'colorBadgeHoverBg'  => [ 'type' => 'string', 'default' => '' ],
            'colorBadgeHoverTxt' => [ 'type' => 'string', 'default' => '' ],
        ],
        'supports'        => [
            'align'   => [ 'wide', 'full' ],
            'spacing' => [ 'margin', 'padding' ],
        ],
    ] );

    // New dynamic block: JHH Team
    register_block_type( 'jhh/team', [
        'api_version'     => 2,
        'style'           => 'jhh-posts-block-team-style',
        'editor_style'    => 'jhh-posts-block-editor-style',
        'editor_script'   => 'jhh-posts-block-editor',
        'render_callback' => 'jhh_team_render',
        'attributes'      => [
            'layout'        => [ 'type' => 'string', 'default' => 'grid' ], // grid | list
            'columns'       => [ 'type' => 'number', 'default' => 3 ],
            'gap'           => [ 'type' => 'number', 'default' => 16 ],
            'termIds'       => [ 'type' => 'array',  'default' => [] ], // select subset of staff terms
            'termOrder'     => [ 'type' => 'array',  'default' => [] ], // explicit order of selected terms
            'orderMode'     => [ 'type' => 'string', 'default' => 'custom' ], // custom | name_asc | name_desc
            'cardBgStyle'   => [ 'type' => 'string', 'default' => 'dark' ],
            'cardCustomColor1'   => [ 'type' => 'string', 'default' => '#333333' ],
            'cardCustomColor2'   => [ 'type' => 'string', 'default' => '#000000' ],
            'cardCustomDirection'=> [ 'type' => 'string', 'default' => '135deg' ],
            'showAvatar'    => [ 'type' => 'boolean', 'default' => true ],
            'showName'      => [ 'type' => 'boolean', 'default' => true ],
            'showEmail'     => [ 'type' => 'boolean', 'default' => true ],
            'showBio'       => [ 'type' => 'boolean', 'default' => true ],
            'showOffers'    => [ 'type' => 'boolean', 'default' => true ],
            'showOfferHover'=> [ 'type' => 'boolean', 'default' => true ],
            'maxOffers'     => [ 'type' => 'number',  'default' => 6 ],
            // Back URL for single page (appended as ?back=... to offer links)
            'backUrl'       => [ 'type' => 'string', 'default' => '' ],
        ],
        'supports'        => [
            'align'   => [ 'wide', 'full' ],
            'spacing' => [ 'margin', 'padding' ],
        ],
    ] );

    // New dynamic block: JHH Events (Angebotsevents)
    register_block_type( 'jhh/events', [
        'api_version'     => 2,
        'style'           => 'jhh-posts-block-events-style',
        'editor_style'    => 'jhh-posts-block-editor-style',
        'editor_script'   => 'jhh-posts-block-editor',
        'render_callback' => 'jhh_events_render',
        'attributes'      => [
            'postsToShow'      => [ 'type' => 'number',  'default' => 6 ],
            'columns'          => [ 'type' => 'number',  'default' => 3 ],
            'gap'              => [ 'type' => 'number',  'default' => 16 ],
            'filterByAngebot'  => [ 'type' => 'number',  'default' => 0 ],
            'onlyFuture'       => [ 'type' => 'boolean', 'default' => true ],
            'orderBy'          => [ 'type' => 'string',  'default' => 'event_date' ],
            'order'            => [ 'type' => 'string',  'default' => 'ASC' ],
            'showImage'        => [ 'type' => 'boolean', 'default' => true ],
            'showPrice'        => [ 'type' => 'boolean', 'default' => true ],
            'showDate'         => [ 'type' => 'boolean', 'default' => true ],
            'showTime'         => [ 'type' => 'boolean', 'default' => true ],
            'showParticipants' => [ 'type' => 'boolean', 'default' => true ],
            'showAngebot'      => [ 'type' => 'boolean', 'default' => true ],
            'showExcerpt'      => [ 'type' => 'boolean', 'default' => false ],
        ],
        'supports'        => [
            'align'   => [ 'wide', 'full' ],
            'spacing' => [ 'margin', 'padding' ],
        ],
    ] );
} );

// Use custom single template for Angebote CPT and Angebotsevent CPT
add_filter( 'single_template', function( $template ) {
    if ( is_singular( 'angebot' ) ) {
        $custom = JHH_PB_DIR . 'templates/single-angebot.php';
        if ( file_exists( $custom ) ) return $custom;
    }
    if ( is_singular( 'angebotsevent' ) ) {
        $custom = JHH_PB_DIR . 'templates/single-angebotsevent.php';
        if ( file_exists( $custom ) ) return $custom;
    }
    return $template;
} );

function jhh_pb_get_post_types() {
    $objs = get_post_types( [ 'public' => true, 'show_in_rest' => true ], 'objects' );
    $allowed = [];
    foreach ( $objs as $slug => $obj ) {
        if ( $slug === 'attachment' ) {
            continue;
        }
        $allowed[] = [
            'slug'  => $slug,
            'label' => $obj->labels->singular_name ? $obj->labels->singular_name : $obj->labels->name,
        ];
    }
    return $allowed;
}

/**
 * Sanitize color values: hex, rgb/rgba, or CSS var().
 */
function jhh_pb_sanitize_color( $color ) {
    $color = trim( (string) $color );
    if ( $color === '' ) return '';
    $hex = sanitize_hex_color( $color );
    if ( $hex ) return $hex;
    if ( preg_match( '/^var\(--[a-zA-Z0-9\-]+\)$/', $color ) ) return $color;
    if ( preg_match( '/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}(?:\s*,\s*(0|1|0?\.\d+))?\s*\)$/', $color ) ) return $color;
    return '';
}

/**
 * Checks whether an Angebot has at least one upcoming linked A-Event.
 */
function jhh_pb_has_upcoming_event( $angebot_id ) {
    static $cache = [];

    $angebot_id = (int) $angebot_id;
    if ( $angebot_id <= 0 ) {
        return false;
    }
    if ( array_key_exists( $angebot_id, $cache ) ) {
        return $cache[ $angebot_id ];
    }

    $q = new WP_Query( [
        'post_type'      => 'angebotsevent',
        'posts_per_page' => 1,
        'post_status'    => 'publish',
        'no_found_rows'  => true,
        'fields'         => 'ids',
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
    ] );

    $cache[ $angebot_id ] = $q->have_posts();
    return $cache[ $angebot_id ];
}

if ( ! function_exists( 'jhh_pb_angebot_has_visible_staff_cards' ) ) {
    function jhh_pb_angebot_has_visible_staff_cards( $angebot_id ) {
        static $cache = [];

        $angebot_id = (int) $angebot_id;
        if ( $angebot_id <= 0 ) {
            return false;
        }

        if ( array_key_exists( $angebot_id, $cache ) ) {
            return $cache[ $angebot_id ];
        }

        if ( ! defined( 'JHH_TAX_JUGEND' ) || ! taxonomy_exists( JHH_TAX_JUGEND ) ) {
            $cache[ $angebot_id ] = false;
            return false;
        }

        $staff_terms = get_the_terms( $angebot_id, JHH_TAX_JUGEND );
        if ( empty( $staff_terms ) || is_wp_error( $staff_terms ) ) {
            $cache[ $angebot_id ] = false;
            return false;
        }

        foreach ( $staff_terms as $term ) {
            $bio     = (string) get_term_meta( $term->term_id, 'bio', true );
            $contact = (string) get_term_meta( $term->term_id, 'contact', true );

            if ( trim( wp_strip_all_tags( $bio ) ) !== '' || trim( $contact ) !== '' ) {
                $cache[ $angebot_id ] = true;
                return true;
            }
        }

        $cache[ $angebot_id ] = false;
        return false;
    }
}

if ( ! function_exists( 'jhh_pb_angebot_has_visible_events' ) ) {
    function jhh_pb_angebot_has_visible_events( $angebot_id ) {
        static $cache = [];

        $angebot_id = (int) $angebot_id;
        if ( $angebot_id <= 0 ) {
            return false;
        }

        if ( array_key_exists( $angebot_id, $cache ) ) {
            return $cache[ $angebot_id ];
        }

        if ( get_option( 'okja_events_show_in_angebot', '1' ) !== '1' ) {
            $cache[ $angebot_id ] = false;
            return false;
        }

        $today_ts    = current_time( 'timestamp' );
        $today       = wp_date( 'Y-m-d', $today_ts );
        $future_days = (int) get_option( 'okja_events_future_days', 365 );
        $past_days   = (int) get_option( 'okja_events_past_days', 0 );
        $start_date  = $past_days > 0 ? wp_date( 'Y-m-d', strtotime( '-' . $past_days . ' days', $today_ts ) ) : $today;
        $end_date    = $future_days > 0 ? wp_date( 'Y-m-d', strtotime( '+' . $future_days . ' days', $today_ts ) ) : '';

        $meta_query = [
            [
                'key'     => 'jhh_event_angebot_id',
                'value'   => $angebot_id,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ],
            [
                'key'     => 'jhh_event_date',
                'value'   => $start_date,
                'compare' => '>=',
                'type'    => 'DATE',
            ],
        ];

        if ( $end_date ) {
            $meta_query[] = [
                'key'     => 'jhh_event_date',
                'value'   => $end_date,
                'compare' => '<=',
                'type'    => 'DATE',
            ];
        }

        $q = new WP_Query( [
            'post_type'           => 'angebotsevent',
            'post_status'         => 'publish',
            'posts_per_page'      => 1,
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
            'fields'              => 'ids',
            'meta_key'            => 'jhh_event_date',
            'orderby'             => 'meta_value',
            'order'               => 'ASC',
            'meta_query'          => $meta_query,
        ] );

        $cache[ $angebot_id ] = $q->have_posts();
        return $cache[ $angebot_id ];
    }
}

if ( ! function_exists( 'jhh_pb_get_single_angebot_style_groups' ) ) {
    function jhh_pb_get_single_angebot_style_groups( $angebot_id ) {
        $groups = [ 'single' ];

        if ( jhh_pb_angebot_has_visible_staff_cards( $angebot_id ) ) {
            $groups[] = 'team';
        }

        if ( jhh_pb_angebot_has_visible_events( $angebot_id ) ) {
            $groups[] = 'events';
        }

        return $groups;
    }
}

if ( ! function_exists( 'jhh_pb_render_single_hero_markup' ) ) {
    function jhh_pb_render_single_hero_markup( $post_id, $args = [] ) {
        $post_id = (int) $post_id;
        if ( $post_id <= 0 ) {
            return '';
        }

        $args = wp_parse_args( $args, [
            'section_class'      => 'jhh-hero',
            'title_class'        => 'jhh-hero-title',
            'title_text'         => get_the_title( $post_id ),
            'image_size'         => 'large',
            'sizes'              => '100vw',
            'eager'              => true,
            'include_data_text'  => false,
        ] );

        $thumb_id = get_post_thumbnail_id( $post_id );
        if ( ! $thumb_id ) {
            return '';
        }

        $image_html = wp_get_attachment_image( $thumb_id, $args['image_size'], false, [
            'class'         => 'jhh-hero-image',
            'loading'       => $args['eager'] ? 'eager' : 'lazy',
            'fetchpriority' => $args['eager'] ? 'high' : 'auto',
            'decoding'      => 'async',
            'sizes'         => (string) $args['sizes'],
        ] );

        if ( ! $image_html ) {
            return '';
        }

        $title_attr = '';
        if ( $args['include_data_text'] ) {
            $title_attr = ' data-text="' . esc_attr( $args['title_text'] ) . '"';
        }

        return sprintf(
            '<section class="%1$s"><div class="jhh-hero-media">%2$s</div><div class="jhh-hero-overlay"><h1 class="%3$s"%4$s>%5$s</h1></div></section>',
            esc_attr( $args['section_class'] ),
            $image_html,
            esc_attr( $args['title_class'] ),
            $title_attr,
            esc_html( $args['title_text'] )
        );
    }
}

if ( ! function_exists( 'jhh_pb_get_event_calendar_payload' ) ) {
    function jhh_pb_get_event_calendar_payload( $post_id ) {
        $post_id    = (int) $post_id;
        $event_date = (string) get_post_meta( $post_id, 'jhh_event_date', true );
        if ( ! $event_date ) {
            return [];
        }

        $time_start = (string) get_post_meta( $post_id, 'jhh_event_time_start', true );
        $time_end   = (string) get_post_meta( $post_id, 'jhh_event_time_end', true );
        $timezone   = wp_timezone();
        $all_day    = $time_start === '';

        try {
            if ( $all_day ) {
                $start = new DateTimeImmutable( $event_date . ' 00:00:00', $timezone );
                $end   = $start->modify( '+1 day' );
            } else {
                $start = new DateTimeImmutable( $event_date . ' ' . $time_start . ':00', $timezone );
                if ( $time_end ) {
                    $end = new DateTimeImmutable( $event_date . ' ' . $time_end . ':00', $timezone );
                    if ( $end <= $start ) {
                        $end = $end->modify( '+1 day' );
                    }
                } else {
                    $end = $start->modify( '+2 hours' );
                }
            }
        } catch ( Exception $exception ) {
            return [];
        }

        $angebot_id    = (int) get_post_meta( $post_id, 'jhh_event_angebot_id', true );
        $angebot_title = $angebot_id ? get_the_title( $angebot_id ) : '';
        $content_text  = trim( wp_strip_all_tags( get_post_field( 'post_excerpt', $post_id ) ?: get_post_field( 'post_content', $post_id ) ) );
        $description   = trim( $content_text . "\n\n" . get_permalink( $post_id ) );

        return [
            'title'         => get_the_title( $post_id ),
            'description'   => $description,
            'location'      => $angebot_title,
            'start_local'   => $start,
            'end_local'     => $end,
            'start_utc'     => $start->setTimezone( new DateTimeZone( 'UTC' ) ),
            'end_utc'       => $end->setTimezone( new DateTimeZone( 'UTC' ) ),
            'all_day'       => $all_day,
            'angebot_id'    => $angebot_id,
            'angebot_title' => $angebot_title,
        ];
    }
}

if ( ! function_exists( 'jhh_pb_get_event_calendar_links' ) ) {
    function jhh_pb_get_event_calendar_links( $post_id ) {
        $payload = jhh_pb_get_event_calendar_payload( $post_id );
        if ( empty( $payload ) ) {
            return [];
        }

        if ( $payload['all_day'] ) {
            $google_dates = $payload['start_local']->format( 'Ymd' ) . '/' . $payload['end_local']->format( 'Ymd' );
            $yahoo_start  = $payload['start_local']->format( 'Ymd' );
            $yahoo_end    = $payload['end_local']->format( 'Ymd' );
        } else {
            $google_dates = $payload['start_utc']->format( 'Ymd\THis\Z' ) . '/' . $payload['end_utc']->format( 'Ymd\THis\Z' );
            $yahoo_start  = $payload['start_utc']->format( 'Ymd\THis\Z' );
            $yahoo_end    = $payload['end_utc']->format( 'Ymd\THis\Z' );
        }

        return [
            'google' => add_query_arg( [
                'action'   => 'TEMPLATE',
                'text'     => $payload['title'],
                'dates'    => $google_dates,
                'details'  => $payload['description'],
                'location' => $payload['location'],
            ], 'https://calendar.google.com/calendar/render' ),
            'outlook' => add_query_arg( [
                'path'     => '/calendar/action/compose',
                'rru'      => 'addevent',
                'subject'  => $payload['title'],
                'startdt'  => $payload['start_local']->format( DATE_ATOM ),
                'enddt'    => $payload['end_local']->format( DATE_ATOM ),
                'body'     => $payload['description'],
                'location' => $payload['location'],
            ], 'https://outlook.office.com/calendar/0/deeplink/compose' ),
            'yahoo' => add_query_arg( [
                'v'      => '60',
                'view'   => 'd',
                'type'   => '20',
                'title'  => $payload['title'],
                'st'     => $yahoo_start,
                'et'     => $yahoo_end,
                'desc'   => $payload['description'],
                'in_loc' => $payload['location'],
            ], 'https://calendar.yahoo.com/' ),
            'ics' => add_query_arg( [
                'action'   => 'jhh_pb_download_event_ics',
                'event_id' => (int) $post_id,
            ], admin_url( 'admin-ajax.php' ) ),
        ];
    }
}

if ( ! function_exists( 'jhh_pb_render_event_calendar_actions' ) ) {
    function jhh_pb_render_event_calendar_actions( $post_id ) {
        if ( ! jhh_pb_show_event_calendar_button() ) {
            return '';
        }

        $links = jhh_pb_get_event_calendar_links( $post_id );
        if ( empty( $links ) ) {
            return '';
        }

        $icon_base = JHH_PB_URL . 'assets/';
        $items = [
            'google'  => [ 'label' => __( 'Google Kalender', 'jhh-posts-block' ), 'icon' => '<img src="' . esc_url( $icon_base . 'icon-google-calendar.svg' ) . '" alt="Google" width="22" height="22" loading="lazy">' ],
            'outlook' => [ 'label' => __( 'Outlook', 'jhh-posts-block' ), 'icon' => '<img src="' . esc_url( $icon_base . 'icon-microsoft-outlook.svg' ) . '" alt="Outlook" width="22" height="22" loading="lazy">' ],
            'ics'     => [ 'label' => __( 'Apple / ICS', 'jhh-posts-block' ), 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>' ],
        ];

        ob_start();
        ?>
        <div class="jhh-event-cal-drop" data-jhh-cal-drop>
            <button type="button" class="jhh-event-cal-trigger" aria-expanded="false" aria-haspopup="true">
                <svg class="jhh-event-cal-trigger-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span><?php esc_html_e( 'Zum Kalender', 'jhh-posts-block' ); ?></span>
                <svg class="jhh-event-cal-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div class="jhh-event-cal-menu" role="menu" hidden>
                <?php foreach ( $items as $provider => $item ) : ?>
                    <?php if ( empty( $links[ $provider ] ) ) continue; ?>
                    <a class="jhh-event-cal-item is-<?php echo esc_attr( $provider ); ?>" href="<?php echo esc_url( $links[ $provider ] ); ?>" target="_blank" rel="noopener noreferrer" role="menuitem">
                        <span class="jhh-event-cal-item-icon"><?php echo $item['icon']; ?></span>
                        <span><?php echo esc_html( $item['label'] ); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        /* Inline dropdown JS – only needed when event-modal.js is NOT loaded (single pages without modal mode). */
        static $cal_js_printed = false;
        if ( ! $cal_js_printed ) {
            $cal_js_printed = true;
            add_action( 'wp_footer', function () {
                if ( wp_script_is( 'jhh-posts-event-modal', 'done' ) || wp_script_is( 'jhh-posts-event-modal', 'enqueued' ) ) {
                    return; // event-modal.js already handles it
                }
                ?>
                <script>
                (function(){
                    if(window.__jhhCalDrop)return;window.__jhhCalDrop=true;
                    function closeAll(){var m=document.querySelectorAll('.jhh-event-cal-menu');for(var i=0;i<m.length;i++){m[i].hidden=true;var b=m[i].previousElementSibling;if(b)b.setAttribute('aria-expanded','false');}}
                    document.addEventListener('click',function(e){var t=e.target.closest('.jhh-event-cal-trigger');if(t){e.preventDefault();var menu=t.nextElementSibling;if(!menu)return;var o=!menu.hidden;closeAll();if(!o){menu.hidden=false;t.setAttribute('aria-expanded','true');}return;}if(!e.target.closest('.jhh-event-cal-menu'))closeAll();});
                })();
                </script>
                <?php
            }, 99 );
        }

        return ob_get_clean();
    }
}

if ( ! function_exists( 'jhh_pb_get_event_detail_markup' ) ) {
    function jhh_pb_get_event_detail_markup( $post_id, $args = [] ) {
        $post_id = (int) $post_id;
        $post    = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'angebotsevent' ) {
            return '';
        }

        $args = wp_parse_args( $args, [
            'for_modal' => false,
        ] );

        $for_modal    = ! empty( $args['for_modal'] );
        $hero_url     = get_the_post_thumbnail_url( $post_id, 'full' );
        $event_date   = get_post_meta( $post_id, 'jhh_event_date', true );
        $time_start   = get_post_meta( $post_id, 'jhh_event_time_start', true );
        $time_end     = get_post_meta( $post_id, 'jhh_event_time_end', true );
        $price        = get_post_meta( $post_id, 'jhh_event_price', true );
        $max_part     = (int) get_post_meta( $post_id, 'jhh_event_max_participants', true );
        $angebot_id   = (int) get_post_meta( $post_id, 'jhh_event_angebot_id', true );
        $sold_out     = (bool) get_post_meta( $post_id, 'jhh_event_sold_out', true );
        $cta_url      = get_post_meta( $post_id, 'jhh_event_cta_url', true );
        $cta_label    = get_post_meta( $post_id, 'jhh_event_cta_label', true );
        $show_modal_permalink = jhh_pb_show_event_modal_permalink_button();
        $date_display = '';
        $date_weekday = '';

        if ( $event_date ) {
            $timestamp = strtotime( $event_date );
            if ( $timestamp ) {
                $date_display = wp_date( 'j. F Y', $timestamp );
                $date_weekday = wp_date( 'l', $timestamp );
            }
        }

        $time_display = '';
        if ( $time_start && $time_end ) {
            $time_display = esc_html( $time_start ) . ' – ' . esc_html( $time_end ) . ' Uhr';
        } elseif ( $time_start ) {
            $time_display = esc_html( $time_start ) . ' Uhr';
        }

        $ec_style      = get_option( 'okja_event_card_style', 'simple' );
        $ec_bg         = get_option( 'okja_event_card_bg', '#1e1b1b' );
        $ec_text       = get_option( 'okja_event_card_text', '#ffffff' );
        $ec_accent     = get_option( 'okja_event_card_accent', '#b9aaff' );
        $ec_topline    = get_option( 'okja_event_card_topline', '1' );
        $ec_color_mode = get_option( 'okja_color_mode', 'auto' );
        $ec_grainy_map = [
            'grainy-1' => JHH_PB_URL . 'assets/pexels-codioful-7130481.jpg',
            'grainy-2' => JHH_PB_URL . 'assets/pexels-codioful-7130499.jpg',
            'grainy-3' => JHH_PB_URL . 'assets/pexels-codioful-7130555.jpg',
        ];
        $ec_inline_css = '';

        if ( $ec_style === 'notebook' ) {
            $ec_inline_css = '--jhh-event-card-bg:#f5f1eb;--jhh-event-card-text:#333;--jhh-event-card-label:#888;background-color:#f5f1eb;color:#333;';
        } elseif ( $ec_style === 'aurora' ) {
            $ec_inline_css = '--jhh-event-card-text:#fff;--jhh-event-card-label:rgba(255,255,255,0.6);background:linear-gradient(135deg,#667eea 0%,#764ba2 50%,#f093fb 100%);color:#fff;';
        } elseif ( isset( $ec_grainy_map[ $ec_style ] ) ) {
            $ec_inline_css = '--jhh-event-card-text:#fff;--jhh-event-card-label:rgba(255,255,255,0.6);background-color:#141414;background-image:url(' . esc_url( $ec_grainy_map[ $ec_style ] ) . ');color:#fff;';
        } elseif ( $ec_style === 'custom' ) {
            $ec_inline_css = '--jhh-event-card-bg:' . esc_attr( $ec_bg ) . ';--jhh-event-card-text:' . esc_attr( $ec_text ) . ';--jhh-event-card-label:' . esc_attr( $ec_accent ) . ';background-color:' . esc_attr( $ec_bg ) . ';color:' . esc_attr( $ec_text ) . ';';
        } elseif ( $ec_color_mode === 'dark' ) {
            $ec_inline_css = '--jhh-event-card-bg:#1e1b1b;--jhh-event-card-text:#fff;--jhh-event-card-label:#888;';
        }

        $ec_topline_css    = '--jhh-event-card-topline:linear-gradient(90deg,' . esc_attr( $ec_accent ) . ',#ee0979,#8a2be2,#4169e1,#00c6ff);';
        $ec_topline_hidden = ( $ec_topline !== '1' );

        setup_postdata( $post );
        $content_html = apply_filters( 'the_content', $post->post_content );
        wp_reset_postdata();

        ob_start();
        ?>
        <div class="jhh-event-detail-shell<?php echo $for_modal ? ' is-modal' : ''; ?>">
            <?php if ( $for_modal ) : ?>
                <div class="jhh-event-modal-cover<?php echo $hero_url ? '' : ' is-no-image'; ?>"<?php echo $hero_url ? ' style="background-image:url(\'' . esc_url( $hero_url ) . '\')"' : ''; ?>>
                    <div class="jhh-event-modal-cover-overlay">
                        <p class="jhh-event-modal-kicker"><?php esc_html_e( 'Angebotsevent', 'jhh-posts-block' ); ?></p>
                        <h2 class="jhh-event-modal-title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h2>
                        <?php if ( $show_modal_permalink ) : ?>
                            <a class="jhh-event-modal-permalink" href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php esc_html_e( 'Event als Seite öffnen', 'jhh-posts-block' ); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="jhh-event-details-card<?php echo $for_modal ? ' jhh-event-details-card--modal' : ''; ?>" style="<?php echo esc_attr( $ec_inline_css . $ec_topline_css ); ?>">
                <div class="jhh-event-details-topline<?php echo $ec_topline_hidden ? ' jhh-hidden' : ''; ?>"></div>
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

                <?php if ( $angebot_id > 0 ) : ?>
                    <?php $angebot_title = get_the_title( $angebot_id ); ?>
                    <?php if ( $angebot_title ) : ?>
                        <?php $angebot_bg = get_the_post_thumbnail_url( $angebot_id, 'large' ); ?>
                        <div class="jhh-event-linked-angebot">
                            <span class="jhh-event-detail-label"><?php esc_html_e( 'Gehört zum Angebot', 'jhh-posts-block' ); ?></span>
                            <a class="jhh-event-angebot-card" href="<?php echo esc_url( get_permalink( $angebot_id ) ); ?>"<?php if ( $angebot_bg ) : ?> style="background:url('<?php echo esc_url( $angebot_bg ); ?>') center/cover no-repeat;"<?php endif; ?>>
                                <span class="jhh-event-angebot-name"><?php echo esc_html( $angebot_title ); ?></span>
                                <span class="jhh-event-angebot-arrow">→</span>
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <article class="jhh-content<?php echo $for_modal ? ' jhh-content--event-modal' : ''; ?>">
                <?php echo $content_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </article>

            <?php if ( $cta_url ) : ?>
                <div class="jhh-event-cta-wrap<?php echo $for_modal ? ' is-modal' : ''; ?>">
                    <a class="jhh-event-cta-btn<?php echo $sold_out ? ' jhh-event-cta-btn--disabled' : ''; ?>" href="<?php echo esc_url( $cta_url ); ?>"<?php echo $sold_out ? '' : ' target="_blank" rel="noopener noreferrer"'; ?>>
                        <?php if ( $sold_out ) : ?>
                            <span class="jhh-event-cta-icon">🚫</span>
                            <span><?php esc_html_e( 'Ausgebucht', 'jhh-posts-block' ); ?></span>
                        <?php else : ?>
                            <span class="jhh-event-cta-icon">✉️</span>
                            <span><?php echo esc_html( $cta_label ?: __( 'Jetzt anmelden', 'jhh-posts-block' ) ); ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            <?php endif; ?>

            <?php echo jhh_pb_render_event_calendar_actions( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

add_action( 'wp_ajax_jhh_pb_get_event_modal', 'jhh_pb_ajax_get_event_modal' );
add_action( 'wp_ajax_nopriv_jhh_pb_get_event_modal', 'jhh_pb_ajax_get_event_modal' );

function jhh_pb_ajax_get_event_modal() {
    if ( ! check_ajax_referer( 'jhh_pb_event_modal', 'nonce', false ) ) {
        wp_send_json_error( [ 'message' => __( 'Ungültige Anfrage.', 'jhh-posts-block' ) ], 403 );
    }

    $event_id = isset( $_POST['event_id'] ) ? absint( wp_unslash( $_POST['event_id'] ) ) : 0;
    if ( $event_id <= 0 || get_post_type( $event_id ) !== 'angebotsevent' || get_post_status( $event_id ) !== 'publish' ) {
        wp_send_json_error( [ 'message' => __( 'Event nicht gefunden.', 'jhh-posts-block' ) ], 404 );
    }

    wp_send_json_success( [
        'html'  => jhh_pb_get_event_detail_markup( $event_id, [ 'for_modal' => true ] ),
        'title' => get_the_title( $event_id ),
        'link'  => get_permalink( $event_id ),
    ] );
}

add_action( 'wp_ajax_jhh_pb_download_event_ics', 'jhh_pb_download_event_ics' );
add_action( 'wp_ajax_nopriv_jhh_pb_download_event_ics', 'jhh_pb_download_event_ics' );

function jhh_pb_download_event_ics() {
    $event_id = isset( $_GET['event_id'] ) ? absint( wp_unslash( $_GET['event_id'] ) ) : 0;
    if ( $event_id <= 0 || get_post_type( $event_id ) !== 'angebotsevent' || get_post_status( $event_id ) !== 'publish' ) {
        status_header( 404 );
        exit;
    }

    $payload = jhh_pb_get_event_calendar_payload( $event_id );
    if ( empty( $payload ) ) {
        status_header( 404 );
        exit;
    }

    $title       = preg_replace( "/[\r\n]+/", ' ', $payload['title'] );
    $description = preg_replace( "/[\r\n]+/", '\\n', $payload['description'] );
    $location    = preg_replace( "/[\r\n]+/", ' ', $payload['location'] );
    $uid         = 'jhh-event-' . $event_id . '@' . wp_parse_url( home_url(), PHP_URL_HOST );
    $filename    = sanitize_title( get_the_title( $event_id ) ) ?: 'event';
    $lines       = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//OKJA Angebote//A-Event//DE',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        'UID:' . $uid,
        'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
    ];

    if ( $payload['all_day'] ) {
        $lines[] = 'DTSTART;VALUE=DATE:' . $payload['start_local']->format( 'Ymd' );
        $lines[] = 'DTEND;VALUE=DATE:' . $payload['end_local']->format( 'Ymd' );
    } else {
        $lines[] = 'DTSTART:' . $payload['start_utc']->format( 'Ymd\THis\Z' );
        $lines[] = 'DTEND:' . $payload['end_utc']->format( 'Ymd\THis\Z' );
    }

    $lines[] = 'SUMMARY:' . $title;
    if ( $description ) {
        $lines[] = 'DESCRIPTION:' . $description;
    }
    if ( $location ) {
        $lines[] = 'LOCATION:' . $location;
    }
    $lines[] = 'URL:' . get_permalink( $event_id );
    $lines[] = 'END:VEVENT';
    $lines[] = 'END:VCALENDAR';

    nocache_headers();
    header( 'Content-Type: text/calendar; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=' . $filename . '.ics' );
    echo implode( "\r\n", $lines );
    exit;
}



/**
 * Render callback
 */
function jhh_pb_render( $attributes, $content = '', $block = null ) {
    jhh_pb_enqueue_frontend_styles( [ 'posts' ] );
    
    $post_type     = isset( $attributes['postType'] ) ? sanitize_key( $attributes['postType'] ) : 'post';
    $posts_to_show = isset( $attributes['postsToShow'] ) ? max( 1, (int) $attributes['postsToShow'] ) : 6;
    $order         = ( isset( $attributes['order'] ) && strtoupper( $attributes['order'] ) === 'ASC' ) ? 'ASC' : 'DESC';
    $orderby       = isset( $attributes['orderBy'] ) ? sanitize_key( $attributes['orderBy'] ) : 'date';
    // grid | list | carousel
    $layout = 'grid';
    if ( isset( $attributes['layout'] ) && in_array( $attributes['layout'], [ 'grid', 'list', 'carousel' ], true ) ) {
        $layout = $attributes['layout'];
    }
    $columns       = isset( $attributes['columns'] ) ? max( 1, (int) $attributes['columns'] ) : 3;
    $gap           = isset( $attributes['gap'] ) ? max( 0, (int) $attributes['gap'] ) : 16;

    $show_image    = ! empty( $attributes['showImage'] );
    $image_size    = isset( $attributes['imageSize'] ) ? sanitize_key( $attributes['imageSize'] ) : 'medium';
    $image_hover   = isset( $attributes['imageHoverEffect'] ) ? sanitize_key( $attributes['imageHoverEffect'] ) : 'none';
    
    // Enqueue tilt script if tilt effect is used
    if ( in_array( $image_hover, [ 'tilt', 'tilt-zoom' ], true ) ) {
        wp_enqueue_script( 'jhh-posts-tilt' );
    }
    
    $show_title    = ! empty( $attributes['showTitle'] );
    $show_date     = ! empty( $attributes['showDate'] );
    $show_author   = ! empty( $attributes['showAuthor'] );
    $show_excerpt  = ! empty( $attributes['showExcerpt'] );
    $excerpt_len   = isset( $attributes['excerptLength'] ) ? max( 0, (int) $attributes['excerptLength'] ) : 20;
    $show_event_image_badge = ! empty( $attributes['showEventImageBadge'] );
    $show_readmore = ! empty( $attributes['showReadMore'] );
    $show_gradient_line = ! empty( $attributes['showGradientLine'] );
    $gradient_margin_top = isset( $attributes['gradientMarginTop'] ) ? max( 0, (int) $attributes['gradientMarginTop'] ) : 16;
    $gradient_margin_bottom = isset( $attributes['gradientMarginBottom'] ) ? max( 0, (int) $attributes['gradientMarginBottom'] ) : 16;

    $show_taxonomies = ! empty( $attributes['showTaxonomies'] );
    $show_tax_jugend = ! empty( $attributes['showTaxJugend'] );
    $show_tax_paed   = ! empty( $attributes['showTaxPaed'] );
    $show_tax_tage   = ! empty( $attributes['showTaxTage'] );

    $elements_order = isset( $attributes['elementsOrder'] ) && is_array( $attributes['elementsOrder'] )
        ? array_values( array_intersect( $attributes['elementsOrder'], [ 'image', 'title', 'meta', 'taxonomies', 'excerpt', 'readmore', 'gradientline' ] ) )
        : [ 'image', 'title', 'meta', 'taxonomies', 'excerpt', 'readmore', 'gradientline' ];
    // Migration: Falls ältere Blöcke keine "gradientline" im Array haben, aber aktiviert ist,
    // wird das Element automatisch hinten angehängt.
    if ( $show_gradient_line && ! in_array( 'gradientline', $elements_order, true ) ) {
        $elements_order[] = 'gradientline';
    }

    // Image size fallback to a valid size
    $valid_sizes = function_exists( 'get_intermediate_image_sizes' ) ? get_intermediate_image_sizes() : [];
    $valid_sizes = array_merge( [ 'thumbnail', 'medium', 'large', 'full' ], $valid_sizes );
    if ( ! in_array( $image_size, $valid_sizes, true ) ) {
        $image_size = 'medium';
    }

    // Query args
    $args = [
        'post_type'           => $post_type,
        'posts_per_page'      => $posts_to_show,
        'order'               => $order,
        'orderby'             => $orderby,
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
    ];

    // Tax filters
    $tax_query = [ 'relation' => 'AND' ];
    if ( ! empty( $attributes['termsJugend'] ) && taxonomy_exists( JHH_TAX_JUGEND ) ) {
        $tax_query[] = [
            'taxonomy' => JHH_TAX_JUGEND,
            'field'    => 'term_id',
            'terms'    => array_map( 'intval', (array) $attributes['termsJugend'] ),
            'operator' => 'IN',
        ];
    }
    if ( ! empty( $attributes['termsPaed'] ) && taxonomy_exists( JHH_TAX_PAED ) ) {
        $tax_query[] = [
            'taxonomy' => JHH_TAX_PAED,
            'field'    => 'term_id',
            'terms'    => array_map( 'intval', (array) $attributes['termsPaed'] ),
            'operator' => 'IN',
        ];
    }
    if ( ! empty( $attributes['termsTage'] ) && taxonomy_exists( JHH_TAX_TAGE ) ) {
        $tax_query[] = [
            'taxonomy' => JHH_TAX_TAGE,
            'field'    => 'term_id',
            'terms'    => array_map( 'intval', (array) $attributes['termsTage'] ),
            'operator' => 'IN',
        ];
    }
    if ( count( $tax_query ) > 1 ) {
        $args['tax_query'] = $tax_query;
    }

    $q = new WP_Query( $args );

    // Back URL for single view (appended to post links)
    $back_url = '';
    if ( ! empty( $attributes['backUrl'] ) ) {
        $back_url = esc_url_raw( $attributes['backUrl'] );
    }
    $single_show_events = isset( $attributes['singleShowEvents'] ) ? (bool) $attributes['singleShowEvents'] : true;

    $unique = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'jhh-pb-' ) : ( 'jhh-pb-' . uniqid() );

    // Inline styles for color controls
    $css_rules = [];

    $color_title       = jhh_pb_sanitize_color( $attributes['colorTitle']         ?? '' );
    $color_title_hover = jhh_pb_sanitize_color( $attributes['colorTitleHover']    ?? '' );
    $color_text        = jhh_pb_sanitize_color( $attributes['colorText']          ?? '' );
    $color_rm          = jhh_pb_sanitize_color( $attributes['colorReadMore']      ?? '' );
    $color_rm_hover    = jhh_pb_sanitize_color( $attributes['colorReadMoreHover'] ?? '' );

    $color_jug_bg      = jhh_pb_sanitize_color( $attributes['colorBadgeJugBg']    ?? '' );
    $color_jug_txt     = jhh_pb_sanitize_color( $attributes['colorBadgeJugTxt']   ?? '' );
    $color_pa_bg       = jhh_pb_sanitize_color( $attributes['colorBadgePaedBg']   ?? '' );
    $color_pa_txt      = jhh_pb_sanitize_color( $attributes['colorBadgePaedTxt']  ?? '' );
    $color_ta_bg       = jhh_pb_sanitize_color( $attributes['colorBadgeTageBg']   ?? '' );
    $color_ta_txt      = jhh_pb_sanitize_color( $attributes['colorBadgeTageTxt']  ?? '' );
    $color_badge_h_bg  = jhh_pb_sanitize_color( $attributes['colorBadgeHoverBg']  ?? '' );
    $color_badge_h_txt = jhh_pb_sanitize_color( $attributes['colorBadgeHoverTxt'] ?? '' );

    if ( $color_text ) {
        $css_rules[] = ".$unique { color: $color_text; }";
        $css_rules[] = ".$unique .jhh-posts .jhh-post { color: $color_text; }";
    }
    if ( $color_title ) {
        $css_rules[] = ".$unique .jhh-post-title a { color: $color_title; }";
    }
    if ( $color_title_hover ) {
        $css_rules[] = ".$unique .jhh-post-title a:hover, .$unique .jhh-post-title a:focus { color: $color_title_hover; }";
    }
    if ( $color_rm ) {
        $css_rules[] = ".$unique .jhh-post-readmore { color: $color_rm; }";
    }
    if ( $color_rm_hover ) {
        $css_rules[] = ".$unique .jhh-post-readmore:hover, .$unique .jhh-post-readmore:focus { color: $color_rm_hover; }";
    }
    if ( $color_jug_bg ) {
        $css_rules[] = ".$unique .jhh-badge.jhh-badge-jug { background:$color_jug_bg; }";
    }
    if ( $color_jug_txt ) {
        $css_rules[] = ".$unique .jhh-badge.jhh-badge-jug { color:$color_jug_txt; }";
    }
    if ( $color_pa_bg ) {
        $css_rules[] = ".$unique .jhh-badge.jhh-badge-pa { background:$color_pa_bg; }";
    }
    if ( $color_pa_txt ) {
        $css_rules[] = ".$unique .jhh-badge.jhh-badge-pa { color:$color_pa_txt; }";
    }
    if ( $color_ta_bg ) {
        $css_rules[] = ".$unique .jhh-badge.jhh-badge-ta { background:$color_ta_bg; }";
    }
    if ( $color_ta_txt ) {
        $css_rules[] = ".$unique .jhh-badge.jhh-badge-ta { color:$color_ta_txt; }";
    }
    if ( $color_badge_h_bg ) {
        $css_rules[] = ".$unique .jhh-badge:hover { background:$color_badge_h_bg; }";
    }
    if ( $color_badge_h_txt ) {
        $css_rules[] = ".$unique .jhh-badge:hover { color:$color_badge_h_txt; }";
    }

    ob_start();

    $container_classes = [
        'jhh-posts-block',
        $unique,
        "layout-$layout",
        "columns-$columns",
    ];
    $list_style = $layout === 'grid'
        ? "display:grid;gap:{$gap}px;grid-template-columns:repeat({$columns},minmax(0,1fr));"
        : "display:flex;flex-direction:column;gap:{$gap}px;";

    echo '<div class="' . esc_attr( implode( ' ', $container_classes ) ) . '">';

    if ( ! empty( $css_rules ) ) {
        echo '<style>' . implode( '', $css_rules ) . '</style>';
    }

    // Special rendering for Carousel
    if ( $layout === 'carousel' ) {
        // enqueue carousel runtime
        wp_enqueue_script( 'jhh-posts-carousel' );

        $data_attrs = sprintf(
            ' data-autoplay="%d" data-interval="%d" data-pause-hover="%d" data-arrows="%d" data-indicators="%d"',
            ! empty( $attributes['carouselAutoplay'] ) ? 1 : 0,
            max( 3, (int) ( $attributes['carouselInterval'] ?? 7 ) ),
            ! empty( $attributes['carouselPauseOnHover'] ) ? 1 : 0,
            ! empty( $attributes['carouselArrows'] ) ? 1 : 0,
            ! empty( $attributes['carouselIndicators'] ) ? 1 : 0
        );

        $carousel_classes = 'jhh-carousel';
        if ( isset( $attributes['carouselBlur'] ) && ! $attributes['carouselBlur'] ) {
            $carousel_classes .= ' jhh-carousel--no-blur';
        }
        echo '<div class="' . esc_attr( $carousel_classes ) . '" role="region" aria-label="Angebote Carousel"' . $data_attrs . '>';
        echo '<div class="jhh-carousel-track" role="listbox">';

        if ( $q->have_posts() ) :
            $slide_index = 0;
            while ( $q->have_posts() ) :
                $q->the_post();
                $post_id    = get_the_ID();
                $permalink  = get_permalink( $post_id );
                $permalink_with_back = $permalink;
                if ( $back_url ) {
                    $permalink_with_back = add_query_arg( 'back', rawurlencode( $back_url ), $permalink );
                }
                if ( $post_type === 'angebot' ) {
                    $permalink_with_back = add_query_arg( 'jhh_show_events', $single_show_events ? '1' : '0', $permalink_with_back );
                }
                $title      = get_the_title();
                $bg_url     = get_the_post_thumbnail_url( $post_id, 'large' );
                if ( ! $bg_url ) $bg_url = get_the_post_thumbnail_url( $post_id, 'medium' );

                // Reuse earlier built tax_html/excerpt/readmore (build minimal here)
                $badges = [];
                if ( $show_taxonomies ) {
                    if ( $show_tax_jugend && taxonomy_exists( JHH_TAX_JUGEND ) ) {
                        $terms = get_the_terms( $post_id, JHH_TAX_JUGEND );
                        if ( $terms && ! is_wp_error( $terms ) ) {
                            foreach ( $terms as $t ) {
                                $badges[] = sprintf('<span class="jhh-badge jhh-badge-jug">%s</span>', esc_html( $t->name ) );
                            }
                        }
                    }
                    if ( $show_tax_paed && taxonomy_exists( JHH_TAX_PAED ) ) {
                        $terms = get_the_terms( $post_id, JHH_TAX_PAED );
                        if ( $terms && ! is_wp_error( $terms ) ) {
                            foreach ( $terms as $t ) {
                                $slug = sanitize_title( $t->slug );
                                $bg   = get_term_meta( $t->term_id, 'badge_bg_color', true );
                                $txt  = get_term_meta( $t->term_id, 'badge_text_color', true );
                                $style_attr = '';
                                $styles = [];
                                if ( $bg )  $styles[] = '--jhh-term-' . $slug . '-bg:' . esc_attr( $bg );
                                if ( $txt ) $styles[] = '--jhh-term-' . $slug . '-color:' . esc_attr( $txt );
                                if ( $styles ) $style_attr = ' style="' . implode( ';', $styles ) . '"';
                                $badges[] = sprintf('<span class="jhh-badge jhh-badge-pa term-%s"%s>%s</span>', esc_attr( $t->slug ), $style_attr, esc_html( $t->name ) );
                            }
                        }
                    }
                    if ( $show_tax_tage ) {
                        $days = get_post_meta( $post_id, 'jhh_days', true );
                        if ( is_array( $days ) && ! empty( $days ) ) {
                            $labels = [ 'montag' => 'Montag', 'dienstag' => 'Dienstag', 'mittwoch' => 'Mittwoch', 'donnerstag' => 'Donnerstag', 'freitag' => 'Freitag', 'samstag' => 'Samstag', 'sonntag' => 'Sonntag' ];
                            $weekday_order = [ 'montag'=>1,'dienstag'=>2,'mittwoch'=>3,'donnerstag'=>4,'freitag'=>5,'samstag'=>6,'sonntag'=>7 ];
                            usort( $days, function($a,$b) use($weekday_order){ return ($weekday_order[$a] ?? 99) <=> ($weekday_order[$b] ?? 99); });
                            foreach ( $days as $d ) {
                                $label = $labels[$d] ?? ucfirst( $d );
                                $badges[] = '<span class="jhh-badge jhh-badge-ta">' . esc_html( $label ) . '</span>';
                            }
                        }
                    }
                }
                $tax_html_car = ! empty( $badges ) ? '<div class="jhh-post-taxonomies">' . implode('', $badges) . '</div>' : '';

                $excerpt_text = '';
                if ( $show_excerpt ) {
                    if ( has_excerpt( $post_id ) ) {
                        $excerpt_text = get_the_excerpt( $post_id );
                    } else {
                        $excerpt_text = wp_strip_all_tags( get_the_content( null, false, $post_id ) );
                    }
                    $excerpt_text = $excerpt_len > 0 ? wp_trim_words( $excerpt_text, $excerpt_len, '…' ) : $excerpt_text;
                }
                $readmore_button = $show_readmore ? sprintf('<a class="jhh-slide-readmore" href="%s">%s</a>', esc_url( $permalink_with_back ), esc_html__( 'Weiterlesen', 'default' ) ) : '';
                $image_badge_html = '';
                if ( $show_event_image_badge && $post_type === 'angebot' && jhh_pb_has_upcoming_event( $post_id ) ) {
                    $image_badge_html = '<span class="jhh-post-event-badge">Event verfügbar</span>';
                }

                printf(
                    '<div class="jhh-slide" role="option" aria-selected="%s">'
                  . '  <div class="jhh-slide-bg" style="background-image:url(%s);"></div>'
                  . '  <div class="jhh-slide-overlay">'
                  . '    %s'
                  . '    <h3 class="jhh-slide-title">%s</h3>'
                  . '    %s'
                  . '    %s'
                  . '  </div>'
                  . '</div>',
                    $slide_index === 0 ? 'true' : 'false',
                    esc_url( $bg_url ?: '' ),
                    $image_badge_html,
                    esc_html( $title ),
                    $tax_html_car,
                    $excerpt_text ? '<p class="jhh-slide-excerpt">' . esc_html( $excerpt_text ) . '</p>' . $readmore_button : $readmore_button
                );

                $slide_index++;
            endwhile;
            wp_reset_postdata();
        endif;

        echo '</div>'; // track
        // Arrows
        echo '<button class="jhh-carousel-prev" aria-label="Vorheriger Slide" tabindex="0">❮</button>';
        echo '<button class="jhh-carousel-next" aria-label="Nächster Slide" tabindex="0">❯</button>';
        echo '</div>'; // carousel
        echo '<div class="jhh-carousel-dots" aria-hidden="false"></div>';

    } else {
        // default grid/list rendering
        echo '<div class="jhh-posts" style="' . esc_attr( $list_style ) . '">';

        if ( $q->have_posts() ) :
        $__idx = 0;
        while ( $q->have_posts() ) :
            $q->the_post();
            $post_id    = get_the_ID();
            $permalink  = get_permalink( $post_id );
            $permalink_with_back = $permalink;
            if ( $back_url ) {
                $permalink_with_back = add_query_arg( 'back', rawurlencode( $back_url ), $permalink );
            }
            if ( $post_type === 'angebot' ) {
                $permalink_with_back = add_query_arg( 'jhh_show_events', $single_show_events ? '1' : '0', $permalink_with_back );
            }
            $title_html = $show_title ? sprintf(
                '<h3 class="jhh-post-title"><a href="%s">%s</a></h3>',
                esc_url( $permalink_with_back ),
                esc_html( get_the_title() )
            ) : '';

            $image_html = '';
            if ( $show_image && has_post_thumbnail( $post_id ) ) {
                $thumb = get_the_post_thumbnail( $post_id, $image_size, [ 'class' => 'jhh-post-thumb', 'loading' => 'lazy' ] );
                if ( $thumb ) {
                    $hover_class = ( $image_hover && $image_hover !== 'none' ) ? ' jhh-hover-' . esc_attr( $image_hover ) : '';
                    $image_badge_html = '';
                    if ( $show_event_image_badge && $post_type === 'angebot' && jhh_pb_has_upcoming_event( $post_id ) ) {
                        $image_badge_html = '<span class="jhh-post-event-badge">Event verfügbar</span>';
                    }
                    $image_html = sprintf(
                        '<a class="jhh-post-image%s" href="%s" aria-label="%s">%s%s</a>',
                        $hover_class,
                        esc_url( $permalink_with_back ),
                        esc_attr( get_the_title() ),
                        $thumb,
                        $image_badge_html
                    );
                }
            }
            // Background image URL (for list layout on mobile overlay)
            $bg_url = get_the_post_thumbnail_url( $post_id, 'large' );
            if ( ! $bg_url ) $bg_url = get_the_post_thumbnail_url( $post_id, 'medium' );

            $meta_bits = [];
            if ( $show_date ) {
                $meta_bits[] = '<span class="jhh-post-date">' . esc_html( get_the_date() ) . '</span>';
            }
            if ( $show_author ) {
                $meta_bits[] = '<span class="jhh-post-author">' . esc_html( get_the_author() ) . '</span>';
            }
            $meta_html = ! empty( $meta_bits ) ? '<div class="jhh-post-meta">' . implode( ' • ', $meta_bits ) . '</div>' : '';

            // Taxonomy badges (Jugend, Pädagogik, Tage)
            $tax_html = '';
            if ( $show_taxonomies ) {
                $badges = [];

                if ( $show_tax_jugend && taxonomy_exists( JHH_TAX_JUGEND ) ) {
                    $terms = get_the_terms( $post_id, JHH_TAX_JUGEND );
                    if ( $terms && ! is_wp_error( $terms ) ) {
                        foreach ( $terms as $t ) {
                            $badges[] = sprintf('<span class="jhh-badge jhh-badge-jug">%s</span>', esc_html( $t->name ));
                        }
                    }
                }
                if ( $show_tax_paed && taxonomy_exists( JHH_TAX_PAED ) ) {
                    $terms = get_the_terms( $post_id, JHH_TAX_PAED );
                    if ( $terms && ! is_wp_error( $terms ) ) {
                        foreach ( $terms as $t ) {
                            $style_attr = '';
                            $bg_color  = get_term_meta( $t->term_id, 'badge_bg_color', true );
                            $txt_color = get_term_meta( $t->term_id, 'badge_text_color', true );
                            if ( $bg_color || $txt_color ) {
                                $styles = [];
                                $slug = sanitize_title( $t->slug );
                                if ( $bg_color ) $styles[] = '--jhh-term-' . $slug . '-bg:' . esc_attr( $bg_color );
                                if ( $txt_color ) $styles[] = '--jhh-term-' . $slug . '-color:' . esc_attr( $txt_color );
                                $style_attr = ' style="' . implode( ';', $styles ) . '"';
                            }
                            $badges[] = sprintf('<span class="jhh-badge jhh-badge-pa term-%s"%s>%s</span>', esc_attr( $t->slug ), $style_attr, esc_html( $t->name ) );
                        }
                    }
                }
                if ( $show_tax_tage ) {
                    $days = get_post_meta( $post_id, 'jhh_days', true );
                    if ( is_array( $days ) && ! empty( $days ) ) {
                        $labels = [ 'montag' => 'Montag', 'dienstag' => 'Dienstag', 'mittwoch' => 'Mittwoch', 'donnerstag' => 'Donnerstag', 'freitag' => 'Freitag', 'samstag' => 'Samstag', 'sonntag' => 'Sonntag' ];
                        $weekday_order = [ 'montag'=>1,'dienstag'=>2,'mittwoch'=>3,'donnerstag'=>4,'freitag'=>5,'samstag'=>6,'sonntag'=>7 ];
                        usort( $days, function($a,$b) use($weekday_order){ return ($weekday_order[$a] ?? 99) <=> ($weekday_order[$b] ?? 99); });
                        foreach ( $days as $d ) {
                            $label = $labels[$d] ?? ucfirst( $d );
                            $badges[] = '<span class="jhh-badge jhh-badge-ta">' . esc_html( $label ) . '</span>';
                        }
                    }
                }

                if ( ! empty( $badges ) ) {
                    $tax_html = '<div class="jhh-post-taxonomies">' . implode( '', $badges ) . '</div>';
                }
            }

            // --- Ende Taxonomies ---

            $excerpt_html = '';
            if ( $show_excerpt ) {
                if ( has_excerpt( $post_id ) ) {
                    $text = get_the_excerpt( $post_id );
                } else {
                    $text = wp_strip_all_tags( get_the_content( null, false, $post_id ) );
                }
                $text = $excerpt_len > 0 ? wp_trim_words( $text, $excerpt_len, '…' ) : $text;
                $excerpt_html = '<div class="jhh-post-excerpt">' . esc_html( $text ) . '</div>';
            }

$readmore_html = '';
if ( $show_readmore ) {
    $readmore_html = sprintf(
        '<button class="jhh-post-readmore" onclick="window.location.href=\'%s\'">%s</button>',
        esc_url( $permalink_with_back ),
        esc_html__( 'Weiterlesen', 'default' )
    );
}


            if ( $layout === 'list' ) {
                $side_class = ($__idx % 2 === 0) ? 'image-left' : 'image-right';
                $has_bg_class = $bg_url ? ' has-bg' : '';
                echo '<article class="jhh-post jhh-list-item ' . esc_attr( $side_class . $has_bg_class ) . '">';
                if ( $bg_url ) {
                    echo '<div class="jhh-list-bg" style="background-image:url(' . esc_url( $bg_url ) . ');"></div>';
                }
                echo '<div class="jhh-list-row">';
                echo '<div class="jhh-list-media">' . $image_html . '</div>';
                echo '<div class="jhh-list-content">' . $title_html . $tax_html . $excerpt_html . $readmore_html;
                if ( $show_gradient_line ) {
                    echo '<div class="jhh-gradient-line" style="margin-top:' . $gradient_margin_top . 'px;margin-bottom:' . $gradient_margin_bottom . 'px"></div>';
                }
                echo '</div>'; // content
                echo '</div>'; // row
                echo '</article>';
                $__idx++;
            } else {
                echo '<article class="jhh-post">';
                foreach ( $elements_order as $el ) {
                    switch ( $el ) {
                        case 'image':
                            echo $image_html;
                            break;
                        case 'title':
                            echo $title_html;
                            break;
                        case 'meta':
                            echo $meta_html;
                            break;
                        case 'taxonomies':
                            echo $tax_html;
                            break;
                        case 'excerpt':
                            echo $excerpt_html;
                            break;
                        case 'readmore':
                            echo $readmore_html;
                            break;
                        case 'gradientline':
                            if ( $show_gradient_line ) {
                                echo '<div class="jhh-gradient-line" style="margin-top: ' . $gradient_margin_top . 'px; margin-bottom: ' . $gradient_margin_bottom . 'px;"></div>';
                            }
                            break;
                    }
                }
                echo '</article>';
            }

        endwhile;
        wp_reset_postdata();
    else:
        // Optional: leere Ausgabe für Konsistenz
        echo '<div class="jhh-posts-empty"></div>';
    endif;
        echo '</div>'; // .jhh-posts
    }
    echo '</div>'; // .jhh-posts-block

    return ob_get_clean();
}


/**
 * Render callback for the JHH Team block.
 * Displays Jugendarbeit terms as team member cards with optional avatar, email, bio, and related Angebote badges.
 */
function jhh_team_render( $attributes, $content = '', $block = null ) {
    jhh_pb_enqueue_frontend_styles( [ 'team' ] );

    // Resolve taxonomy slug
    $tax = defined('JHH_TAX_JUGEND') ? JHH_TAX_JUGEND : 'jugendarbeit';
    if ( ! taxonomy_exists( $tax ) ) return '';

    $layout   = in_array( $attributes['layout'] ?? 'grid', [ 'grid', 'list' ], true ) ? $attributes['layout'] : 'grid';
    $columns  = max( 1, (int) ( $attributes['columns'] ?? 3 ) );
    $gap      = max( 0, (int) ( $attributes['gap'] ?? 16 ) );
    $term_ids = array_map( 'intval', (array) ( $attributes['termIds'] ?? [] ) );
    $term_order = array_map( 'intval', (array) ( $attributes['termOrder'] ?? [] ) );
    $order_mode = sanitize_key( $attributes['orderMode'] ?? 'custom' );
    $bg_style   = sanitize_key( $attributes['cardBgStyle'] ?? 'dark' );
    $allowed_bg = [
        'none', 'dark', 'blue', 'purple', 'sunset', 'rainbow', 'notebook', 'simple',
        'grainy-1', 'grainy-2', 'grainy-3',
        'custom', 'muted', 'charcoal'
    ];
    if ( ! in_array( $bg_style, $allowed_bg, true ) ) { $bg_style = 'dark'; }
    
    // Custom background colors
    $custom_color1 = sanitize_hex_color( $attributes['cardCustomColor1'] ?? '#333333' ) ?: '#333333';
    $custom_color2 = sanitize_hex_color( $attributes['cardCustomColor2'] ?? '#000000' ) ?: '#000000';
    $custom_direction = preg_replace( '/[^a-z0-9deg\s,]/i', '', $attributes['cardCustomDirection'] ?? '135deg' ) ?: '135deg';
    
    $show_avatar = ! empty( $attributes['showAvatar'] );
    $show_name   = isset($attributes['showName']) ? (bool)$attributes['showName'] : true;
    $show_email  = ! empty( $attributes['showEmail'] );
    $show_bio    = ! empty( $attributes['showBio'] );
    $show_offers = ! empty( $attributes['showOffers'] );
    $show_offer_hover = isset( $attributes['showOfferHover'] ) ? (bool) $attributes['showOfferHover'] : true;
    $max_offers  = max( 0, (int) ( $attributes['maxOffers'] ?? 6 ) );

    // Back URL for single view (appended to offer links)
    $back_url = '';
    if ( ! empty( $attributes['backUrl'] ) ) {
        $back_url = esc_url_raw( $attributes['backUrl'] );
    }

    // Collect terms
    $term_args = [ 'taxonomy' => $tax, 'hide_empty' => false ];
    if ( $order_mode === 'custom' ) {
        if ( $term_order ) {
            $term_args['include'] = $term_order;
            $term_args['orderby'] = 'include';
        } elseif ( $term_ids ) {
            $term_args['include'] = $term_ids;
            $term_args['orderby'] = 'include';
        }
    } elseif ( in_array( $order_mode, [ 'name_asc', 'name_desc' ], true ) ) {
        if ( $term_ids ) {
            $term_args['include'] = $term_ids;
        }
        $term_args['orderby'] = 'name';
        $term_args['order']   = $order_mode === 'name_desc' ? 'DESC' : 'ASC';
    }
    $terms = get_terms( $term_args );
    if ( empty( $terms ) || is_wp_error( $terms ) ) return '';

    $unique = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'jhh-team-' ) : ( 'jhh-team-' . uniqid() );
    $container_classes = [ 'jhh-team-block', $unique, 'layout-' . $layout, 'columns-' . $columns ];
    $list_style = $layout === 'grid'
        ? "display:grid;gap:{$gap}px;grid-template-columns:repeat({$columns},minmax(0,1fr));"
        : "display:flex;flex-direction:column;gap:{$gap}px;";

    ob_start();
    echo '<div class="' . esc_attr( implode( ' ', $container_classes ) ) . '">';
    echo '<div class="jhh-team" style="' . esc_attr( $list_style ) . '">';

    foreach ( $terms as $t ) {
        $avatar_id = (int) get_term_meta( $t->term_id, 'avatar_id', true );
        $funktion  = get_term_meta( $t->term_id, 'funktion', true );
        $bio       = get_term_meta( $t->term_id, 'bio', true );
        $contact   = get_term_meta( $t->term_id, 'contact', true );

        $img = '';
        if ( $show_avatar && $avatar_id ) {
            $img = wp_get_attachment_image( $avatar_id, 'medium', false, [ 'class' => 'jhh-team-avatar', 'loading' => 'lazy' ] );
        }

        // Prepare email display and href similar to single template
        $email_html = '';
        if ( $show_email && $contact ) {
            $c_raw   = trim( (string) $contact );
            $display = preg_replace( '/\s*(\(at\)|\[at\]|\sat\s)\s*/i', '@', $c_raw );
            $candidate = preg_replace( '/\s*(\(at\)|\[at\]|\sat\s)\s*/i', '@', $c_raw );
            $candidate = preg_replace( '/\s+/', '', $candidate );
            $candidate = preg_replace( '/^mailto:/i', '', $candidate );
            $email     = sanitize_email( $candidate );
            if ( $email && strpos( $email, '@' ) !== false ) {
                $email_html = '<a class="jhh-team-contact" href="mailto:' . esc_attr( $email ) . '">' . esc_html( $display ) . '</a>'; 
            } else {
                $email_html = '<div class="jhh-team-contact">' . esc_html( $display ) . '</div>';
            }
        }

        // Related offers badges (Angebote)
        $offers_html = '';
        if ( $show_offers ) {
            $q = new WP_Query([
                'post_type' => 'angebot',
                'posts_per_page' => ($max_offers === 0 ? -1 : $max_offers),
                'tax_query' => [[
                    'taxonomy' => $tax,
                    'field'    => 'term_id',
                    'terms'    => [ (int) $t->term_id ],
                    'operator' => 'IN',
                ]],
                'no_found_rows' => true,
            ]);
            if ( $q->have_posts() ) {
                $badges = [];
                while ( $q->have_posts() ) { $q->the_post();
                    $pid    = get_the_ID();
                    $perma  = get_permalink( $pid );
                    $href   = $perma;
                    if ( $back_url ) {
                        $href = add_query_arg( 'back', rawurlencode( $back_url ), $perma );
                    }
                    
                    // Get weekdays for tooltip (only if hover tooltips enabled)
                    $days = get_post_meta( $pid, 'jhh_days', true );
                    $tooltip_html = '';
                    if ( $show_offer_hover && is_array( $days ) && ! empty( $days ) ) {
                        $day_abbrev = [
                            'montag' => 'Mo', 'dienstag' => 'Di', 'mittwoch' => 'Mi',
                            'donnerstag' => 'Do', 'freitag' => 'Fr', 'samstag' => 'Sa', 'sonntag' => 'So'
                        ];
                        $weekday_order = [ 'montag'=>1,'dienstag'=>2,'mittwoch'=>3,'donnerstag'=>4,'freitag'=>5,'samstag'=>6,'sonntag'=>7 ];
                        usort( $days, function($a,$b) use($weekday_order){ return ($weekday_order[$a] ?? 99) <=> ($weekday_order[$b] ?? 99); });
                        $abbrev_list = array_map( function($d) use($day_abbrev){ return $day_abbrev[$d] ?? ucfirst(substr($d,0,2)); }, $days );
                        $tooltip_html = '<span class="jhh-offer-tooltip">' . esc_html( implode(', ', $abbrev_list) ) . '</span>';
                    }
                    
                    $thumb = get_the_post_thumbnail_url( $pid, 'medium' );
                    if ( $thumb ) {
                        $badges[] = '<span class="jhh-offer-tooltip-container"><a class="jhh-offer-badge has-bg" href="' . esc_url( $href ) . '">'
                          . '<span class="jhh-offer-bg" style="background-image:url(' . esc_url( $thumb ) . ');"></span>'
                          . '<span class="jhh-offer-label">' . esc_html( get_the_title() ) . '</span>'
                          . '</a>' . $tooltip_html . '</span>';
                    } else {
                        $badges[] = '<span class="jhh-offer-tooltip-container"><a class="jhh-offer-badge" href="' . esc_url( $href ) . '">' . esc_html( get_the_title() ) . '</a>' . $tooltip_html . '</span>';
                    }
                }
                wp_reset_postdata();
                $offers_html = '<div class="jhh-team-offers">' . implode('', $badges) . '</div>';
            }
        }

    // Build card style attribute for custom backgrounds
    $card_inline_style = '';
    if ( $bg_style === 'custom' ) {
        $card_inline_style = sprintf(
            'background: linear-gradient(%s, %s, %s); color: #fff;',
            esc_attr( $custom_direction ),
            esc_attr( $custom_color1 ),
            esc_attr( $custom_color2 )
        );
    }
    
    echo '<article class="jhh-team-card bg-' . esc_attr( $bg_style ) . '"' . ( $card_inline_style ? ' style="' . esc_attr( $card_inline_style ) . '"' : '' ) . '>';
        echo '<div class="jhh-team-inner">';
        if ( $img ) echo $img;
        echo '<div class="jhh-team-meta">';
        if ( $show_name ) echo '<h3 class="jhh-team-name">' . esc_html( $t->name ) . '</h3>';
        if ( $funktion ) echo '<div class="jhh-team-role">' . esc_html( $funktion ) . '</div>';
        if ( $email_html ) echo $email_html;
        echo '</div>'; // meta
        echo '</div>'; // inner
        if ( $show_bio && $bio ) echo '<div class="jhh-team-bio">' . wp_kses_post( wpautop( $bio ) ) . '</div>';
        echo $offers_html;
        echo '</article>';
    }

    echo '</div>';
    echo '</div>';

    return ob_get_clean();
}


/**
 * Render callback for the JHH Events block.
 * Displays Angebotsevents as attractive event cards.
 */
function jhh_events_render( $attributes, $content = '', $block = null ) {
    jhh_pb_enqueue_frontend_styles( [ 'events' ] );
    jhh_pb_enqueue_event_modal_assets();

    $posts_to_show    = max( 1, (int) ( $attributes['postsToShow'] ?? 6 ) );
    $columns          = max( 1, (int) ( $attributes['columns'] ?? 3 ) );
    $gap              = max( 0, (int) ( $attributes['gap'] ?? 16 ) );
    $filter_angebot   = (int) ( $attributes['filterByAngebot'] ?? 0 );
    $only_future      = ! empty( $attributes['onlyFuture'] );
    $order_by         = sanitize_key( $attributes['orderBy'] ?? 'event_date' );
    $order            = ( isset( $attributes['order'] ) && strtoupper( $attributes['order'] ) === 'DESC' ) ? 'DESC' : 'ASC';
    $show_image       = isset( $attributes['showImage'] ) ? (bool) $attributes['showImage'] : true;
    $show_price       = isset( $attributes['showPrice'] ) ? (bool) $attributes['showPrice'] : true;
    $show_date        = isset( $attributes['showDate'] ) ? (bool) $attributes['showDate'] : true;
    $show_time        = isset( $attributes['showTime'] ) ? (bool) $attributes['showTime'] : true;
    $show_participants= isset( $attributes['showParticipants'] ) ? (bool) $attributes['showParticipants'] : true;
    $show_angebot     = isset( $attributes['showAngebot'] ) ? (bool) $attributes['showAngebot'] : true;
    $show_excerpt     = ! empty( $attributes['showExcerpt'] );

    // Build query
    $args = [
        'post_type'           => 'angebotsevent',
        'posts_per_page'      => $posts_to_show,
        'post_status'         => 'publish',
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
    ];

    // Meta query for filtering
    $meta_query = [];
    if ( $filter_angebot > 0 ) {
        $meta_query[] = [
            'key'     => 'jhh_event_angebot_id',
            'value'   => $filter_angebot,
            'compare' => '=',
            'type'    => 'NUMERIC',
        ];
    }
    if ( $only_future ) {
        $meta_query[] = [
            'key'     => 'jhh_event_date',
            'value'   => current_time( 'Y-m-d' ),
            'compare' => '>=',
            'type'    => 'DATE',
        ];
    }

    // Ordering
    if ( $order_by === 'event_date' ) {
        $meta_query['event_date_clause'] = [
            'key'     => 'jhh_event_date',
            'compare' => 'EXISTS',
            'type'    => 'DATE',
        ];
        $args['orderby'] = [ 'event_date_clause' => $order ];
    } else {
        $args['orderby'] = $order_by;
        $args['order']   = $order;
    }

    if ( ! empty( $meta_query ) ) {
        if ( count( $meta_query ) > 1 ) {
            $meta_query['relation'] = 'AND';
        }
        $args['meta_query'] = $meta_query;
    }

    $q = new WP_Query( $args );
    if ( ! $q->have_posts() ) {
        return '<div class="jhh-events-block jhh-empty">Keine Events gefunden.</div>';
    }

    $unique = function_exists( 'wp_unique_id' ) ? wp_unique_id( 'jhh-ev-' ) : ( 'jhh-ev-' . uniqid() );

    ob_start();
    echo '<div class="jhh-events-block ' . esc_attr( $unique ) . '">';
    echo '<div class="jhh-events-grid" style="display:grid;gap:' . (int) $gap . 'px;grid-template-columns:repeat(' . (int) $columns . ',minmax(0,1fr));">';

    while ( $q->have_posts() ) : $q->the_post();
        $pid          = get_the_ID();
        $permalink    = get_permalink( $pid );
        $event_date   = get_post_meta( $pid, 'jhh_event_date', true );
        $time_start   = get_post_meta( $pid, 'jhh_event_time_start', true );
        $time_end     = get_post_meta( $pid, 'jhh_event_time_end', true );
        $price        = get_post_meta( $pid, 'jhh_event_price', true );
        $max_part     = (int) get_post_meta( $pid, 'jhh_event_max_participants', true );
        $angebot_id   = (int) get_post_meta( $pid, 'jhh_event_angebot_id', true );
        $sold_out     = (bool) get_post_meta( $pid, 'jhh_event_sold_out', true );

        // Format date
        $date_display = '';
        if ( $event_date ) {
            $ts = strtotime( $event_date );
            if ( $ts ) {
                $date_display = wp_date( 'j. F Y', $ts );
            }
        }

        // Format time
        $time_display = '';
        if ( $time_start && $time_end ) {
            $time_display = esc_html( $time_start ) . ' – ' . esc_html( $time_end ) . ' Uhr';
        } elseif ( $time_start ) {
            $time_display = esc_html( $time_start ) . ' Uhr';
        }

        $card_class = 'jhh-event-card';
        if ( $sold_out ) $card_class .= ' jhh-event--sold-out';
        echo '<a class="' . esc_attr( $card_class ) . '" href="' . esc_url( $permalink ) . '"' . jhh_pb_get_event_link_attributes( $pid ) . '>';

        // Sold out banner
        if ( $sold_out ) {
            echo '<span class="jhh-event-sold-out-banner">' . esc_html__( 'Ausgebucht', 'jhh-posts-block' ) . '</span>';
        }

        // Image
        if ( $show_image && has_post_thumbnail( $pid ) ) {
            echo '<div class="jhh-event-image">';
            the_post_thumbnail( 'medium', [ 'class' => 'jhh-event-thumb', 'loading' => 'lazy' ] );
            echo '</div>';
        }

        echo '<div class="jhh-event-body">';

        // Title
        echo '<h3 class="jhh-event-title">' . esc_html( get_the_title() ) . '</h3>';

        // Meta badges
        echo '<div class="jhh-event-meta">';
        if ( $show_date && $date_display ) {
            echo '<span class="jhh-event-badge jhh-event-date-badge">📅 ' . esc_html( $date_display ) . '</span>';
        }
        if ( $show_time && $time_display ) {
            echo '<span class="jhh-event-badge jhh-event-time-badge">🕐 ' . $time_display . '</span>';
        }
        if ( $show_price && $price ) {
            echo '<span class="jhh-event-badge jhh-event-price-badge">💰 ' . esc_html( $price ) . '</span>';
        }
        if ( $show_participants && $max_part > 0 ) {
            echo '<span class="jhh-event-badge jhh-event-part-badge">👥 max. ' . (int) $max_part . ' Teilnehmer</span>';
        }
        echo '</div>';

        // Excerpt
        if ( $show_excerpt ) {
            $text = has_excerpt( $pid ) ? get_the_excerpt( $pid ) : wp_strip_all_tags( get_the_content( null, false, $pid ) );
            $text = wp_trim_words( $text, 15, '…' );
            if ( $text ) {
                echo '<p class="jhh-event-excerpt">' . esc_html( $text ) . '</p>';
            }
        }

        // Linked Angebot
        if ( $show_angebot && $angebot_id > 0 ) {
            $angebot_title = get_the_title( $angebot_id );
            if ( $angebot_title ) {
                $angebot_bg = get_the_post_thumbnail_url( $angebot_id, 'medium_large' );
                $badge_style = $angebot_bg ? ' style="background-image:url(' . esc_url( $angebot_bg ) . ');"' : '';
                echo '<span class="jhh-event-angebot-badge"' . $badge_style . '>';
                echo '<span class="jhh-event-angebot-badge-label">' . esc_html__( 'Angebot', 'jhh-posts-block' ) . '</span>';
                echo '<span class="jhh-event-angebot-badge-name">' . esc_html( $angebot_title ) . '</span>';
                echo '</span>';
            }
        }

        echo '</div>'; // body
        echo '</a>'; // card
    endwhile;
    wp_reset_postdata();

    echo '</div>'; // grid
    echo '</div>'; // block

    return ob_get_clean();
}