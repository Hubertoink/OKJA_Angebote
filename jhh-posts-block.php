<?php
/**
 * Plugin Name: OKJA_Angebote
 * Description: Flexibler Beitrags-/CPT-Block mit Live-Vorschau, Post-Typ-Auswahl, Taxonomie-Filtern/Badges, frei anordenbaren Elementen und Style-Optionen.
 * Author: Hubertoink
 * Version: 1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'JHH_PB_URL', plugin_dir_url( __FILE__ ) );
define( 'JHH_PB_DIR', plugin_dir_path( __FILE__ ) );
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

}, 0 );

// ------------------------------------------------------------
// Plugin-Einstellungen: Globaler Staff Card Style + Farben
// ------------------------------------------------------------
add_action( 'admin_menu', function() {
    add_options_page(
        __( 'OKJA Angebote Einstellungen', 'jhh-posts-block' ),
        __( 'OKJA Angebote', 'jhh-posts-block' ),
        'manage_options',
        'okja-angebote-settings',
        'okja_render_settings_page'
    );
} );

// Enqueue color picker on settings page
add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( $hook !== 'settings_page_okja-angebote-settings' ) return;
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_script( 'wp-color-picker' );
    wp_add_inline_script( 'wp-color-picker', "
        jQuery(document).ready(function($){
            $('.okja-color-picker').wpColorPicker({
                change: function(event, ui) {
                    okjaUpdatePreview();
                },
                clear: function() {
                    okjaUpdatePreview();
                }
            });
            
            // Live preview update
            function okjaUpdatePreview() {
                setTimeout(function() {
                    var bg = $('input[name=okja_staff_bg_color]').val() || '#2b2727';
                    var text = $('input[name=okja_staff_text_color]').val() || '#ffffff';
                    var accent = $('input[name=okja_staff_accent_color]').val() || '#b9aaff';
                    
                    $('#okja-staff-preview').css({
                        'background': bg,
                        'color': text
                    });
                    $('#okja-staff-preview .okja-preview-contact').css('color', accent);
                    $('#okja-staff-preview .okja-preview-topline').css('background', 'linear-gradient(90deg, ' + accent + ', #ee0979, #8a2be2, #4169e1, #00c6ff)');
                }, 50);
            }
            
            // Initial preview
            okjaUpdatePreview();
        });
    " );
} );

add_action( 'admin_init', function() {
    // Style setting
    register_setting( 'okja_settings_group', 'okja_default_staff_style', [
        'type' => 'string',
        'default' => 'simple',
        'sanitize_callback' => function( $val ) {
            return in_array( $val, [ 'simple', 'notebook', 'aurora', 'custom' ], true ) ? $val : 'simple';
        }
    ] );
    
    // Color settings
    register_setting( 'okja_settings_group', 'okja_staff_bg_color', [
        'type' => 'string',
        'default' => '#2b2727',
        'sanitize_callback' => 'sanitize_hex_color'
    ] );
    register_setting( 'okja_settings_group', 'okja_staff_text_color', [
        'type' => 'string',
        'default' => '#ffffff',
        'sanitize_callback' => 'sanitize_hex_color'
    ] );
    register_setting( 'okja_settings_group', 'okja_staff_accent_color', [
        'type' => 'string',
        'default' => '#b9aaff',
        'sanitize_callback' => 'sanitize_hex_color'
    ] );
    
    // Section: Style
    add_settings_section(
        'okja_staff_section',
        __( 'Mitarbeiter-Karten (Single-Ansicht)', 'jhh-posts-block' ),
        function() {
            echo '<p>' . esc_html__( 'Wähle den Standard-Style für alle Mitarbeiter-Karten in der Einzelansicht der Angebote.', 'jhh-posts-block' ) . '</p>';
        },
        'okja-angebote-settings'
    );
    
    add_settings_field(
        'okja_default_staff_style',
        __( 'Standard Staff-Card Style', 'jhh-posts-block' ),
        'okja_render_staff_style_field',
        'okja-angebote-settings',
        'okja_staff_section'
    );
    
    // Section: Colors
    add_settings_section(
        'okja_colors_section',
        __( 'Benutzerdefinierte Farben', 'jhh-posts-block' ),
        function() {
            echo '<p>' . esc_html__( 'Diese Farben werden beim Style "Benutzerdefiniert" verwendet. Bei anderen Styles dienen sie als Basis-Anpassung.', 'jhh-posts-block' ) . '</p>';
        },
        'okja-angebote-settings'
    );
    
    add_settings_field(
        'okja_staff_colors',
        __( 'Kartenfarben', 'jhh-posts-block' ),
        'okja_render_color_fields',
        'okja-angebote-settings',
        'okja_colors_section'
    );
    
    add_settings_field(
        'okja_staff_preview',
        __( 'Vorschau', 'jhh-posts-block' ),
        'okja_render_preview_field',
        'okja-angebote-settings',
        'okja_colors_section'
    );
} );

function okja_render_staff_style_field() {
    $style = get_option( 'okja_default_staff_style', 'simple' );
    ?>
    <fieldset>
        <label style="display:block;margin-bottom:8px;">
            <input type="radio" name="okja_default_staff_style" value="simple" <?php checked( $style, 'simple' ); ?>>
            <?php esc_html_e( 'Schlicht (dunkel, mit Farblinie)', 'jhh-posts-block' ); ?>
        </label>
        <label style="display:block;margin-bottom:8px;">
            <input type="radio" name="okja_default_staff_style" value="notebook" <?php checked( $style, 'notebook' ); ?>>
            <?php esc_html_e( 'Papier (Notizbuch) – mit aufgepinntem Foto', 'jhh-posts-block' ); ?>
        </label>
        <label style="display:block;margin-bottom:8px;">
            <input type="radio" name="okja_default_staff_style" value="aurora" <?php checked( $style, 'aurora' ); ?>>
            <?php esc_html_e( 'Pastell-Gradient – ohne Pin', 'jhh-posts-block' ); ?>
        </label>
        <label style="display:block;">
            <input type="radio" name="okja_default_staff_style" value="custom" <?php checked( $style, 'custom' ); ?>>
            <?php esc_html_e( 'Benutzerdefiniert (eigene Farben unten)', 'jhh-posts-block' ); ?>
        </label>
    </fieldset>
    <p class="description"><?php esc_html_e( 'Dieser Style wird für alle Angebote verwendet, außer wenn ein Angebot einen eigenen Style definiert hat.', 'jhh-posts-block' ); ?></p>
    <?php
}

function okja_render_color_fields() {
    $bg = get_option( 'okja_staff_bg_color', '#2b2727' );
    $text = get_option( 'okja_staff_text_color', '#ffffff' );
    $accent = get_option( 'okja_staff_accent_color', '#b9aaff' );
    ?>
    <table class="form-table" style="margin:0;">
        <tr>
            <th scope="row" style="padding:10px 10px 10px 0;width:150px;"><?php esc_html_e( 'Hintergrund', 'jhh-posts-block' ); ?></th>
            <td style="padding:10px 0;">
                <input type="text" name="okja_staff_bg_color" value="<?php echo esc_attr( $bg ); ?>" class="okja-color-picker" data-default-color="#2b2727">
            </td>
        </tr>
        <tr>
            <th scope="row" style="padding:10px 10px 10px 0;"><?php esc_html_e( 'Text', 'jhh-posts-block' ); ?></th>
            <td style="padding:10px 0;">
                <input type="text" name="okja_staff_text_color" value="<?php echo esc_attr( $text ); ?>" class="okja-color-picker" data-default-color="#ffffff">
            </td>
        </tr>
        <tr>
            <th scope="row" style="padding:10px 10px 10px 0;"><?php esc_html_e( 'Akzent (Links, Linie)', 'jhh-posts-block' ); ?></th>
            <td style="padding:10px 0;">
                <input type="text" name="okja_staff_accent_color" value="<?php echo esc_attr( $accent ); ?>" class="okja-color-picker" data-default-color="#b9aaff">
            </td>
        </tr>
    </table>
    <?php
}

function okja_render_preview_field() {
    $bg = get_option( 'okja_staff_bg_color', '#2b2727' );
    $text = get_option( 'okja_staff_text_color', '#ffffff' );
    $accent = get_option( 'okja_staff_accent_color', '#b9aaff' );
    ?>
    <div id="okja-staff-preview" style="
        position: relative;
        background: <?php echo esc_attr( $bg ); ?>;
        color: <?php echo esc_attr( $text ); ?>;
        border-radius: 16px;
        padding: 24px 18px 18px;
        max-width: 350px;
        overflow: hidden;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    ">
        <div class="okja-preview-topline" style="
            position: absolute;
            left: 0;
            right: 0;
            top: 0;
            height: 6px;
            background: linear-gradient(90deg, <?php echo esc_attr( $accent ); ?>, #ee0979, #8a2be2, #4169e1, #00c6ff);
        "></div>
        <div style="display: flex; flex-direction: column; align-items: center; text-align: center;">
            <div style="
                width: 80px;
                height: 80px;
                border-radius: 50%;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                margin-bottom: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 32px;
            ">👤</div>
            <h3 style="margin: 0 0 4px; font-size: 1.3rem;">Max Mustermann</h3>
            <div style="opacity: 0.9; font-weight: 600; margin-bottom: 4px;">Jugendarbeiter*in</div>
            <a class="okja-preview-contact" href="#" style="color: <?php echo esc_attr( $accent ); ?>; font-weight: 700; text-decoration: none;">max@example.de</a>
        </div>
        <div style="margin-top: 12px; opacity: 0.95; text-align: left; font-size: 0.9rem;">
            Dies ist eine Beispiel-Biografie. Hier steht eine kurze Beschreibung der Person.
        </div>
    </div>
    <p class="description" style="margin-top: 12px;"><?php esc_html_e( 'Live-Vorschau der Kartenfarben. Änderungen werden sofort angezeigt.', 'jhh-posts-block' ); ?></p>
    <?php
}

function okja_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'okja_settings_group' );
            do_settings_sections( 'okja-angebote-settings' );
            submit_button( __( 'Einstellungen speichern', 'jhh-posts-block' ) );
            ?>
        </form>
    </div>
    <?php
}

/*Custom-Fields-Jugendarbeit erstellen*/

/**
 * ==============================
 * Angebot: Zeiten pro Tag (Meta Box)
 * ==============================
 */

// Sanitize helper for HH:MM times per weekday
if ( ! function_exists( 'jhh_pb_sanitize_day_times' ) ) {
    function jhh_pb_sanitize_day_times( $value ) {
        $days = [ 'montag','dienstag','mittwoch','donnerstag','freitag','samstag','sonntag' ];
        $out  = [];
        if ( is_string( $value ) ) {
            $decoded = json_decode( $value, true );
            if ( is_array( $decoded ) ) $value = $decoded; else $value = [];
        }
        if ( is_object( $value ) ) $value = (array) $value;
        if ( ! is_array( $value ) ) return $out;

        $is_time = static function( $t ) {
            return is_string( $t ) && preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $t );
        };

        foreach ( $days as $d ) {
            $row = [];
            if ( isset( $value[$d]['start'] ) && $is_time( $value[$d]['start'] ) ) $row['start'] = $value[$d]['start'];
            if ( isset( $value[$d]['end'] )   && $is_time( $value[$d]['end'] ) )   $row['end']   = $value[$d]['end'];
            if ( $row ) $out[$d] = $row;
        }
        return $out;
    }
}

// Sanitize helper for selected weekdays list
if ( ! function_exists( 'jhh_pb_sanitize_days' ) ) {
    function jhh_pb_sanitize_days( $value ) {
        $allowed = [ 'montag','dienstag','mittwoch','donnerstag','freitag','samstag','sonntag' ];
        $out = [];
        if ( is_string( $value ) ) {
            $decoded = json_decode( $value, true );
            if ( is_array( $decoded ) ) $value = $decoded; else $value = [];
        }
        if ( is_object( $value ) ) $value = (array) $value;
        if ( ! is_array( $value ) ) return $out;
        foreach ( $value as $v ) {
            $v = strtolower( sanitize_text_field( (string) $v ) );
            if ( in_array( $v, $allowed, true ) ) $out[] = $v;
        }
        // keep unique in weekday order
        $order = array_flip( $allowed );
        usort( $out, function( $a, $b ) use ( $order ) { return ($order[$a] ?? 99) <=> ($order[$b] ?? 99); } );
        return array_values( array_unique( $out ) );
    }
}

// Register meta for selected weekdays
add_action( 'init', function(){
    register_post_meta( 'angebot', 'jhh_days', [
        'single'            => true,
        'type'              => 'array',
        'sanitize_callback' => 'jhh_pb_sanitize_days',
        'show_in_rest'      => false,
        'auth_callback'     => function(){ return current_user_can( 'edit_posts' ); }
    ] );
    // Staff card style on single page: global | simple | notebook | aurora | custom
    register_post_meta( 'angebot', 'jhh_staff_card_style', [
        'single'            => true,
        'type'              => 'string',
        'sanitize_callback' => function( $v ){
            $v = sanitize_key( (string) $v );
            return in_array( $v, [ 'global', 'simple', 'notebook', 'aurora', 'custom' ], true ) ? $v : 'global';
        },
        'show_in_rest'      => false,
        'auth_callback'     => function(){ return current_user_can( 'edit_posts' ); }
    ] );
}, 5 );

// Meta box UI (appears in editor for CPT Angebot)
add_action( 'add_meta_boxes', function(){
    add_meta_box( 'jhh_day_times', __( 'Zeiten pro Tag', 'jhh-posts-block' ), 'jhh_render_day_times_metabox', 'angebot', 'side', 'default' );
    add_meta_box( 'jhh_staff_style', __( 'Mitarbeiter-Karte Stil', 'jhh-posts-block' ), 'jhh_render_staff_style_metabox', 'angebot', 'side', 'default' );
} );

function jhh_render_day_times_metabox( $post ) {
    wp_nonce_field( 'jhh_day_times_save', 'jhh_day_times_nonce' );
    $times = get_post_meta( $post->ID, 'jhh_day_times', true );
    if ( ! is_array( $times ) ) $times = [];
    $days = [
        'montag' => __( 'Montag', 'jhh-posts-block' ),
        'dienstag' => __( 'Dienstag', 'jhh-posts-block' ),
        'mittwoch' => __( 'Mittwoch', 'jhh-posts-block' ),
        'donnerstag' => __( 'Donnerstag', 'jhh-posts-block' ),
        'freitag' => __( 'Freitag', 'jhh-posts-block' ),
        'samstag' => __( 'Samstag', 'jhh-posts-block' ),
        'sonntag' => __( 'Sonntag', 'jhh-posts-block' ),
    ];
    $selected_days = get_post_meta( $post->ID, 'jhh_days', true );
    if ( ! is_array( $selected_days ) ) $selected_days = [];
    echo '<p>' . esc_html__( 'Wähle die Tage und optional Start-/Endzeiten. Zeiten werden nur in der Single-Ansicht angezeigt.', 'jhh-posts-block' ) . '</p>';
    echo '<style>.jhh-day-row{margin:6px 0}.jhh-day-row label{display:inline-block;font-weight:600;margin:0 8px 0 0}.jhh-day-row .jhh-time{width:110px}</style>';
    foreach ( $days as $key => $label ) {
        $start = isset( $times[$key]['start'] ) ? esc_attr( $times[$key]['start'] ) : '';
        $end   = isset( $times[$key]['end'] )   ? esc_attr( $times[$key]['end'] )   : '';
        $checked = in_array( $key, $selected_days, true ) ? 'checked' : '';
        echo '<div class="jhh-day-row">';
        echo '<label><input type="checkbox" name="jhh_days[]" value="' . esc_attr( $key ) . '" ' . $checked . '> ' . esc_html( $label ) . '</label>';
        echo '<input class="jhh-time" type="time" name="jhh_day_times[' . esc_attr( $key ) . '][start]" value="' . $start . '" /> ';
        echo '&nbsp;–&nbsp;';
        echo '<input class="jhh-time" type="time" name="jhh_day_times[' . esc_attr( $key ) . '][end]" value="' . $end . '" />';
        echo '</div>';
    }
}

// Staff card style meta box
function jhh_render_staff_style_metabox( $post ){
    wp_nonce_field( 'jhh_staff_style_save', 'jhh_staff_style_nonce' );
    $global_default = get_option( 'okja_default_staff_style', 'simple' );
    $post_style = get_post_meta( $post->ID, 'jhh_staff_card_style', true );
    $use_global = empty( $post_style ) || $post_style === 'global';
    $style = $use_global ? $global_default : $post_style;
    
    echo '<p>' . esc_html__( 'Darstellung der Mitarbeiter-Karte in der Single-Ansicht wählen.', 'jhh-posts-block' ) . '</p>';
    echo '<label style="display:block;margin-bottom:6px;background:#f0f0f1;padding:8px;border-radius:4px;"><input type="radio" name="jhh_staff_card_style" value="global"' . checked( $use_global, true, false ) . '> ' . sprintf( esc_html__( 'Global (%s) – Einstellung unter Einstellungen > OKJA Angebote', 'jhh-posts-block' ), esc_html( $global_default ) ) . '</label>';
    echo '<label style="display:block;margin-bottom:6px;"><input type="radio" name="jhh_staff_card_style" value="simple"' . checked( $post_style, 'simple', false ) . '> ' . esc_html__( 'Schlicht (dunkel, mit Farblinie)', 'jhh-posts-block' ) . '</label>';
    echo '<label style="display:block;margin-bottom:6px;"><input type="radio" name="jhh_staff_card_style" value="notebook"' . checked( $post_style, 'notebook', false ) . '> ' . esc_html__( 'Papier (Notizbuch) – mit aufgepinntem Foto', 'jhh-posts-block' ) . '</label>';
    echo '<label style="display:block;margin-bottom:6px;"><input type="radio" name="jhh_staff_card_style" value="aurora"' . checked( $post_style, 'aurora', false ) . '> ' . esc_html__( 'Pastell‑Gradient – ohne Pin', 'jhh-posts-block' ) . '</label>';
    echo '<label style="display:block;"><input type="radio" name="jhh_staff_card_style" value="custom"' . checked( $post_style, 'custom', false ) . '> ' . esc_html__( 'Benutzerdefiniert (Farben aus Einstellungen)', 'jhh-posts-block' ) . '</label>';
}

// Save handler
add_action( 'save_post_angebot', function( $post_id ) {
    if ( ! isset( $_POST['jhh_day_times_nonce'] ) || ! wp_verify_nonce( $_POST['jhh_day_times_nonce'], 'jhh_day_times_save' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    $days_raw = isset( $_POST['jhh_days'] ) ? (array) $_POST['jhh_days'] : [];
    $days     = jhh_pb_sanitize_days( $days_raw );
    update_post_meta( $post_id, 'jhh_days', $days );

    $raw_times = isset( $_POST['jhh_day_times'] ) ? (array) $_POST['jhh_day_times'] : [];
    $san_times = jhh_pb_sanitize_day_times( $raw_times );
    // keep only times for selected days
    if ( $days ) {
        $san_times = array_intersect_key( $san_times, array_flip( $days ) );
    } else {
        $san_times = [];
    }
    update_post_meta( $post_id, 'jhh_day_times', $san_times );
    // Save staff card style (separate nonce, tolerate if meta box hidden)
    if ( isset( $_POST['jhh_staff_style_nonce'] ) && wp_verify_nonce( $_POST['jhh_staff_style_nonce'], 'jhh_staff_style_save' ) ) {
        $style = isset( $_POST['jhh_staff_card_style'] ) ? sanitize_key( (string) $_POST['jhh_staff_card_style'] ) : 'global';
        if ( ! in_array( $style, [ 'simple', 'notebook', 'aurora', 'global' ], true ) ) $style = 'global';
        update_post_meta( $post_id, 'jhh_staff_card_style', $style );
    }
} );

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
    // Bump versions to ensure latest assets are loaded in editor and frontend
    wp_register_style( 'jhh-posts-block-style', JHH_PB_URL . 'assets/style.css', [], '1.3.32' );
    wp_register_style( 'jhh-posts-block-editor-style', JHH_PB_URL . 'assets/editor.css', [ 'wp-edit-blocks' ], '1.3.5' );

    wp_register_script(
        'jhh-posts-block-editor',
        JHH_PB_URL . 'assets/editor.js',
        [ 'wp-blocks', 'wp-element', 'wp-components', 'wp-editor', 'wp-block-editor', 'wp-i18n', 'wp-data', 'wp-server-side-render' ],
        '1.3.11',
        true
    );

    // Frontend carousel script
    wp_register_script(
        'jhh-posts-carousel',
        JHH_PB_URL . 'assets/carousel.js',
        [],
        '1.3.3',
        true
    );

    // Frontend tilt effect script
    wp_register_script(
        'jhh-posts-tilt',
        JHH_PB_URL . 'assets/tilt-effect.js',
        [],
        '1.0.0',
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
        'postTypes' => jhh_pb_get_post_types()
    ] );
});


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
    fn($sel) => ":root[data-neve-theme=\"dark\"] {$sel}",
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
            $css .= ":root[data-neve-theme=\"dark\"] {$selector_list}{background-color: {$darkened};}";
            $css .= "}\n";
        }

        $css .= "</style>\n";
        echo $css;
    }

    // Früh genug anhängen, damit Variablen vor Theme/Child-CSS vorhanden sind
    add_action( 'wp_head', 'jhh_output_paed_dynamic_css', 20 );
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
        'style'           => 'jhh-posts-block-style',
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
            'showImage'       => [ 'type' => 'boolean', 'default' => true ],
            'imageSize'       => [ 'type' => 'string',  'default' => 'medium' ],
            'imageHoverEffect' => [ 'type' => 'string',  'default' => 'none' ],
            'showTitle'       => [ 'type' => 'boolean', 'default' => true ],
            'showDate'        => [ 'type' => 'boolean', 'default' => true ],
            'showAuthor'      => [ 'type' => 'boolean', 'default' => false ],
            'showExcerpt'     => [ 'type' => 'boolean', 'default' => true ],
            'excerptLength'   => [ 'type' => 'number',  'default' => 20 ],
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
        'style'           => 'jhh-posts-block-style',
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
            'showAvatar'    => [ 'type' => 'boolean', 'default' => true ],
            'showName'      => [ 'type' => 'boolean', 'default' => true ],
            'showEmail'     => [ 'type' => 'boolean', 'default' => true ],
            'showBio'       => [ 'type' => 'boolean', 'default' => true ],
            'showOffers'    => [ 'type' => 'boolean', 'default' => true ],
            'maxOffers'     => [ 'type' => 'number',  'default' => 6 ],
            // Back URL for single page (appended as ?back=... to offer links)
            'backUrl'       => [ 'type' => 'string', 'default' => '' ],
        ],
        'supports'        => [
            'align'   => [ 'wide', 'full' ],
            'spacing' => [ 'margin', 'padding' ],
        ],
    ] );
} );

// Use custom single template for Angebote CPT
add_filter( 'single_template', function( $template ) {
    if ( is_singular( 'angebot' ) ) {
        $custom = JHH_PB_DIR . 'templates/single-angebot.php';
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
 * Render callback
 */
function jhh_pb_render( $attributes, $content = '', $block = null ) {
    // Enqueue frontend styles
    wp_enqueue_style( 'jhh-posts-block-style' );
    
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

        echo '<div class="jhh-carousel" role="region" aria-label="Angebote Carousel"' . $data_attrs . '>';
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

                printf(
                    '<div class="jhh-slide" role="option" aria-selected="%s">'
                  . '  <div class="jhh-slide-bg" style="background-image:url(%s);"></div>'
                  . '  <div class="jhh-slide-overlay">'
                  . '    <h3 class="jhh-slide-title">%s</h3>'
                  . '    %s'
                  . '    %s'
                  . '  </div>'
                  . '</div>',
                    $slide_index === 0 ? 'true' : 'false',
                    esc_url( $bg_url ?: '' ),
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
        echo '<div class="jhh-carousel-dots" aria-hidden="false"></div>';
        echo '</div>'; // carousel

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
                    $image_html = sprintf(
                        '<a class="jhh-post-image%s" href="%s" aria-label="%s">%s</a>',
                        $hover_class,
                        esc_url( $permalink_with_back ),
                        esc_attr( get_the_title() ),
                        $thumb
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
    // Ensure CSS is available
    wp_enqueue_style( 'jhh-posts-block-style' );

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
    $allowed_bg = [ 'none', 'dark', 'blue', 'purple', 'sunset', 'rainbow', 'notebook', 'simple' ];
    if ( ! in_array( $bg_style, $allowed_bg, true ) ) { $bg_style = 'dark'; }
    $show_avatar = ! empty( $attributes['showAvatar'] );
    $show_name   = isset($attributes['showName']) ? (bool)$attributes['showName'] : true;
    $show_email  = ! empty( $attributes['showEmail'] );
    $show_bio    = ! empty( $attributes['showBio'] );
    $show_offers = ! empty( $attributes['showOffers'] );
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
                    
                    // Get weekdays for tooltip
                    $days = get_post_meta( $pid, 'jhh_days', true );
                    $tooltip_html = '';
                    if ( is_array( $days ) && ! empty( $days ) ) {
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

    echo '<article class="jhh-team-card bg-' . esc_attr( $bg_style ) . '">';
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

