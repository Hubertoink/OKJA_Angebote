<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function okja_get_settings_grainy_urls() {
    return [
        'grainy-1' => esc_url( JHH_PB_URL . 'assets/pexels-codioful-7130481.jpg' ),
        'grainy-2' => esc_url( JHH_PB_URL . 'assets/pexels-codioful-7130499.jpg' ),
        'grainy-3' => esc_url( JHH_PB_URL . 'assets/pexels-codioful-7130555.jpg' ),
    ];
}

function okja_register_settings_submenu() {
    global $okja_settings_page_hook;

    $okja_settings_page_hook = add_submenu_page(
        okja_get_settings_parent_slug(),
        __( 'OKJA Angebote Einstellungen', 'jhh-posts-block' ),
        __( 'OKJA Angebote Einstellungen', 'jhh-posts-block' ),
        'manage_options',
        okja_get_settings_page_slug(),
        'okja_render_settings_page'
    );
}

add_action( 'admin_menu', 'okja_register_settings_submenu', 99 );

add_action( 'admin_menu', function() {
    remove_submenu_page( 'options-general.php', okja_get_settings_page_slug() );
}, 100 );

add_action( 'admin_init', function() {
    global $pagenow;

    if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( $pagenow !== 'options-general.php' ) {
        return;
    }

    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    if ( $page !== okja_get_settings_page_slug() ) {
        return;
    }

    wp_safe_redirect( admin_url( okja_get_settings_parent_slug() . '&page=' . okja_get_settings_page_slug() ) );
    exit;
} );

add_action( 'admin_enqueue_scripts', function( $hook ) {
    global $okja_settings_page_hook;

    if ( ! $okja_settings_page_hook || $hook !== $okja_settings_page_hook ) {
        return;
    }

    $settings_css = JHH_PB_DIR . 'assets/admin-settings.css';
    $settings_js  = JHH_PB_DIR . 'assets/admin-settings.js';
    $version      = JHH_PB_VERSION;

    if ( file_exists( $settings_css ) ) {
        $version .= '.' . filemtime( $settings_css );
    }
    if ( file_exists( $settings_js ) ) {
        $version .= '.' . filemtime( $settings_js );
    }

    wp_enqueue_script( 'jquery' );
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_script( 'wp-color-picker' );
    wp_enqueue_style(
        'okja-admin-settings',
        JHH_PB_URL . 'assets/admin-settings.css',
        [ 'wp-color-picker' ],
        $version
    );
    wp_enqueue_script(
        'okja-admin-settings',
        JHH_PB_URL . 'assets/admin-settings.js',
        [ 'jquery', 'wp-color-picker' ],
        $version,
        true
    );
    wp_localize_script( 'okja-admin-settings', 'okjaAdminSettingsData', [
        'grainyMap' => okja_get_settings_grainy_urls(),
    ] );
} );

add_action( 'admin_init', function() {
    register_setting( 'okja_settings_group', 'okja_enable_angebot_wizard', [
        'type' => 'string',
        'default' => '1',
        'sanitize_callback' => function( $val ) {
            return in_array( $val, [ '0', '1' ], true ) ? $val : '1';
        }
    ] );
    register_setting( 'okja_settings_group', 'okja_enable_angebotsevent_wizard', [
        'type' => 'string',
        'default' => '1',
        'sanitize_callback' => function( $val ) {
            return in_array( $val, [ '0', '1' ], true ) ? $val : '1';
        }
    ] );

    register_setting( 'okja_settings_group', 'okja_default_staff_style', [
        'type' => 'string',
        'default' => 'simple',
        'sanitize_callback' => function( $val ) {
            return in_array( $val, [ 'simple', 'notebook', 'aurora', 'grainy-1', 'grainy-2', 'grainy-3', 'custom' ], true ) ? $val : 'simple';
        }
    ] );

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

    register_setting( 'okja_settings_group', 'okja_events_show_in_angebot', [
        'type' => 'string',
        'default' => '1',
        'sanitize_callback' => function( $val ) {
            return in_array( $val, [ '0', '1' ], true ) ? $val : '1';
        }
    ] );
    register_setting( 'okja_settings_group', 'okja_event_link_mode', [
        'type' => 'string',
        'default' => 'single',
        'sanitize_callback' => function( $val ) {
            return in_array( $val, [ 'single', 'modal' ], true ) ? $val : 'single';
        }
    ] );
    register_setting( 'okja_settings_group', 'okja_events_future_days', [
        'type' => 'integer',
        'default' => 365,
        'sanitize_callback' => 'absint'
    ] );
    register_setting( 'okja_settings_group', 'okja_events_past_days', [
        'type' => 'integer',
        'default' => 0,
        'sanitize_callback' => 'absint'
    ] );

    register_setting( 'okja_settings_group', 'okja_event_card_style', [
        'type' => 'string',
        'default' => 'simple',
        'sanitize_callback' => function( $val ) {
            return in_array( $val, [ 'simple', 'notebook', 'aurora', 'grainy-1', 'grainy-2', 'grainy-3', 'custom' ], true ) ? $val : 'simple';
        }
    ] );
    register_setting( 'okja_settings_group', 'okja_event_card_bg', [
        'type' => 'string',
        'default' => '#1e1b1b',
        'sanitize_callback' => 'sanitize_hex_color'
    ] );
    register_setting( 'okja_settings_group', 'okja_event_card_text', [
        'type' => 'string',
        'default' => '#ffffff',
        'sanitize_callback' => 'sanitize_hex_color'
    ] );
    register_setting( 'okja_settings_group', 'okja_event_card_accent', [
        'type' => 'string',
        'default' => '#b9aaff',
        'sanitize_callback' => 'sanitize_hex_color'
    ] );
    register_setting( 'okja_settings_group', 'okja_event_card_topline', [
        'type' => 'string',
        'default' => '1',
        'sanitize_callback' => function( $val ) {
            return in_array( $val, [ '0', '1' ], true ) ? $val : '1';
        }
    ] );

    register_setting( 'okja_settings_group', 'okja_hero_animation', [
        'type' => 'string',
        'default' => 'none',
        'sanitize_callback' => function( $val ) {
            $allowed = [ 'none', 'fade-in-up', 'slide-in-left', 'scale-pop', 'typewriter', 'glitch', 'wave', 'glow-pulse', 'cinematic', 'parallax-drift', 'explosive', 'vortex', 'aurora', 'spotlight', 'glitch-storm' ];
            return in_array( $val, $allowed, true ) ? $val : 'none';
        }
    ] );
    register_setting( 'okja_settings_group', 'okja_hero_hover', [
        'type' => 'string',
        'default' => 'none',
        'sanitize_callback' => function( $val ) {
            $allowed = [ 'none', 'glow', 'scale', 'color-shift', 'underline', 'shake' ];
            return in_array( $val, $allowed, true ) ? $val : 'none';
        }
    ] );
    register_setting( 'okja_settings_group', 'okja_color_mode', [
        'type' => 'string',
        'default' => 'auto',
        'sanitize_callback' => function( $val ) {
            return in_array( $val, [ 'auto', 'dark', 'light' ], true ) ? $val : 'auto';
        }
    ] );
    register_setting( 'okja_settings_group', 'okja_dark_selector', [
        'type' => 'string',
        'default' => 'html[data-neve-theme="dark"]',
        'sanitize_callback' => 'sanitize_text_field'
    ] );
    register_setting( 'okja_settings_group', 'okja_light_selector', [
        'type' => 'string',
        'default' => 'html[data-neve-theme="light"]',
        'sanitize_callback' => 'sanitize_text_field'
    ] );

    add_settings_section(
        'okja_editor_section',
        __( 'Redaktion', 'jhh-posts-block' ),
        function() {
            echo '<p>' . esc_html__( 'Hilfen für Redakteurinnen und Redakteure beim Anlegen neuer Angebote.', 'jhh-posts-block' ) . '</p>';
        },
        'okja-angebote-settings'
    );
    add_settings_field( 'okja_enable_angebot_wizard', __( 'Angebots-Wizard', 'jhh-posts-block' ), 'okja_render_angebot_wizard_field', 'okja-angebote-settings', 'okja_editor_section' );
    add_settings_field( 'okja_enable_angebotsevent_wizard', __( 'A-Event-Wizard', 'jhh-posts-block' ), 'okja_render_angebotsevent_wizard_field', 'okja-angebote-settings', 'okja_editor_section' );

    add_settings_section(
        'okja_staff_section',
        __( 'Mitarbeiter-Karten (Single-Ansicht)', 'jhh-posts-block' ),
        function() {
            echo '<p>' . esc_html__( 'Wähle den Standard-Style für alle Mitarbeiter-Karten in der Einzelansicht der Angebote.', 'jhh-posts-block' ) . '</p>';
        },
        'okja-angebote-settings'
    );
    add_settings_field( 'okja_default_staff_style', __( 'Standard Staff-Card Style', 'jhh-posts-block' ), 'okja_render_staff_style_field', 'okja-angebote-settings', 'okja_staff_section' );

    add_settings_section(
        'okja_colors_section',
        __( 'Benutzerdefinierte Farben', 'jhh-posts-block' ),
        function() {
            echo '<p>' . esc_html__( 'Diese Farben werden beim Style "Benutzerdefiniert" verwendet. Bei anderen Styles dienen sie als Basis-Anpassung.', 'jhh-posts-block' ) . '</p>';
        },
        'okja-angebote-settings'
    );
    add_settings_field( 'okja_staff_colors', __( 'Kartenfarben', 'jhh-posts-block' ), 'okja_render_color_fields', 'okja-angebote-settings', 'okja_colors_section' );
    add_settings_field( 'okja_staff_preview', __( 'Vorschau', 'jhh-posts-block' ), 'okja_render_preview_field', 'okja-angebote-settings', 'okja_colors_section' );

    add_settings_section(
        'okja_events_section',
        __( 'Events in Angeboten', 'jhh-posts-block' ),
        function() {
            echo '<p>' . esc_html__( 'Steuere, ob und welche Events auf der Einzelseite eines Angebots angezeigt werden.', 'jhh-posts-block' ) . '</p>';
        },
        'okja-angebote-settings'
    );
    add_settings_field( 'okja_events_show_in_angebot', __( 'Events anzeigen', 'jhh-posts-block' ), 'okja_render_events_show_field', 'okja-angebote-settings', 'okja_events_section' );
    add_settings_field( 'okja_event_link_mode', __( 'Event öffnen als', 'jhh-posts-block' ), 'okja_render_event_link_mode_field', 'okja-angebote-settings', 'okja_events_section' );
    add_settings_field( 'okja_events_range', __( 'Zeitraum', 'jhh-posts-block' ), 'okja_render_events_range_field', 'okja-angebote-settings', 'okja_events_section' );

    add_settings_section(
        'okja_event_card_section',
        __( 'Event-Kachel (Single-Ansicht)', 'jhh-posts-block' ),
        function() {
            echo '<p>' . esc_html__( 'Passe das Aussehen der Event-Details-Kachel auf der Einzelseite an.', 'jhh-posts-block' ) . '</p>';
        },
        'okja-angebote-settings'
    );
    add_settings_field( 'okja_event_card_style', __( 'Kachel-Style', 'jhh-posts-block' ), 'okja_render_event_card_style_field', 'okja-angebote-settings', 'okja_event_card_section' );
    add_settings_field( 'okja_event_card_topline_field', __( 'Gradient-Linie', 'jhh-posts-block' ), 'okja_render_event_card_topline_field', 'okja-angebote-settings', 'okja_event_card_section' );
    add_settings_field( 'okja_event_card_colors', __( 'Kachel-Farben', 'jhh-posts-block' ), 'okja_render_event_card_color_fields', 'okja-angebote-settings', 'okja_event_card_section' );
    add_settings_field( 'okja_event_card_preview', __( 'Vorschau', 'jhh-posts-block' ), 'okja_render_event_card_preview_field', 'okja-angebote-settings', 'okja_event_card_section' );

    add_settings_section(
        'okja_hero_section',
        __( 'Hero-Titel Animationen', 'jhh-posts-block' ),
        function() {
            echo '<p>' . esc_html__( 'Wähle Animationen für den Titel im Hero-Bereich der Einzelansicht.', 'jhh-posts-block' ) . '</p>';
        },
        'okja-angebote-settings'
    );
    add_settings_field( 'okja_hero_animation', __( 'Eingangs-Animation', 'jhh-posts-block' ), 'okja_render_hero_animation_field', 'okja-angebote-settings', 'okja_hero_section' );
    add_settings_field( 'okja_hero_hover', __( 'Hover-Effekt', 'jhh-posts-block' ), 'okja_render_hero_hover_field', 'okja-angebote-settings', 'okja_hero_section' );

    add_settings_section(
        'okja_theme_compat_section',
        __( 'Theme-Kompatibilität', 'jhh-posts-block' ),
        function() {
            echo '<p>' . esc_html__( 'Konfiguriere wie das Plugin mit dem Dark/Light Mode deines Themes zusammenarbeitet.', 'jhh-posts-block' ) . '</p>';
        },
        'okja-angebote-settings'
    );
    add_settings_field( 'okja_color_mode', __( 'Farbmodus', 'jhh-posts-block' ), 'okja_render_color_mode_field', 'okja-angebote-settings', 'okja_theme_compat_section' );
    add_settings_field( 'okja_dark_selector', __( 'Dark Mode Selektor', 'jhh-posts-block' ), 'okja_render_dark_selector_field', 'okja-angebote-settings', 'okja_theme_compat_section' );
    add_settings_field( 'okja_light_selector', __( 'Light Mode Selektor', 'jhh-posts-block' ), 'okja_render_light_selector_field', 'okja-angebote-settings', 'okja_theme_compat_section' );
} );

function okja_render_staff_style_field() {
    $style = get_option( 'okja_default_staff_style', 'simple' );
    ?>
    <fieldset class="okja-settings-radio-list">
        <label><input type="radio" name="okja_default_staff_style" value="simple" <?php checked( $style, 'simple' ); ?>> <?php esc_html_e( 'Schlicht (themeabhängig: dunkel/hell, mit Farblinie)', 'jhh-posts-block' ); ?></label>
        <label><input type="radio" name="okja_default_staff_style" value="notebook" <?php checked( $style, 'notebook' ); ?>> <?php esc_html_e( 'Papier (Notizbuch) – mit aufgepinntem Foto', 'jhh-posts-block' ); ?></label>
        <label><input type="radio" name="okja_default_staff_style" value="aurora" <?php checked( $style, 'aurora' ); ?>> <?php esc_html_e( 'Pastell-Gradient – ohne Pin', 'jhh-posts-block' ); ?></label>
        <label><input type="radio" name="okja_default_staff_style" value="grainy-1" <?php checked( $style, 'grainy-1' ); ?>> <?php esc_html_e( 'Grainy Gradient (Bild 1)', 'jhh-posts-block' ); ?></label>
        <label><input type="radio" name="okja_default_staff_style" value="grainy-2" <?php checked( $style, 'grainy-2' ); ?>> <?php esc_html_e( 'Grainy Gradient (Bild 2)', 'jhh-posts-block' ); ?></label>
        <label><input type="radio" name="okja_default_staff_style" value="grainy-3" <?php checked( $style, 'grainy-3' ); ?>> <?php esc_html_e( 'Grainy Gradient (Bild 3)', 'jhh-posts-block' ); ?></label>
        <label><input type="radio" name="okja_default_staff_style" value="custom" <?php checked( $style, 'custom' ); ?>> <?php esc_html_e( 'Benutzerdefiniert (eigene Farben unten)', 'jhh-posts-block' ); ?></label>
    </fieldset>
    <p class="description"><?php esc_html_e( 'Dieser Style wird für alle Angebote verwendet, außer wenn ein Angebot einen eigenen Style definiert hat. Bei „Schlicht“ wechseln Farben automatisch je nach Theme-Modus (Dark/Light).', 'jhh-posts-block' ); ?></p>
    <?php
}

function okja_render_color_fields() {
    $bg     = get_option( 'okja_staff_bg_color', '#2b2727' );
    $text   = get_option( 'okja_staff_text_color', '#ffffff' );
    $accent = get_option( 'okja_staff_accent_color', '#b9aaff' );
    ?>
    <table class="form-table okja-settings-compact-table">
        <tr>
            <th scope="row"><?php esc_html_e( 'Hintergrund', 'jhh-posts-block' ); ?></th>
            <td><input type="text" name="okja_staff_bg_color" value="<?php echo esc_attr( $bg ); ?>" class="okja-color-picker" data-default-color="#2b2727"></td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e( 'Text', 'jhh-posts-block' ); ?></th>
            <td><input type="text" name="okja_staff_text_color" value="<?php echo esc_attr( $text ); ?>" class="okja-color-picker" data-default-color="#ffffff"></td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e( 'Akzent (Links, Linie)', 'jhh-posts-block' ); ?></th>
            <td><input type="text" name="okja_staff_accent_color" value="<?php echo esc_attr( $accent ); ?>" class="okja-color-picker" data-default-color="#b9aaff"></td>
        </tr>
    </table>
    <?php
}

function okja_render_preview_field() {
    $bg          = get_option( 'okja_staff_bg_color', '#2b2727' );
    $text        = get_option( 'okja_staff_text_color', '#ffffff' );
    $accent      = get_option( 'okja_staff_accent_color', '#b9aaff' );
    $style       = get_option( 'okja_default_staff_style', 'simple' );
    $grainy_urls = okja_get_settings_grainy_urls();
    $image_var   = isset( $grainy_urls[ $style ] ) ? 'url(' . $grainy_urls[ $style ] . ')' : 'none';
    $classes     = 'okja-settings-staff-preview';
    if ( isset( $grainy_urls[ $style ] ) ) {
        $classes .= ' is-grainy';
    }
    $style_attr = '--okja-staff-bg:' . $bg . ';--okja-staff-text:' . $text . ';--okja-staff-accent:' . $accent . ';--okja-staff-bg-image:' . $image_var . ';';
    ?>
    <div id="okja-staff-preview" class="<?php echo esc_attr( $classes ); ?>" data-style="<?php echo esc_attr( $style ); ?>" style="<?php echo esc_attr( $style_attr ); ?>">
        <div class="okja-preview-topline"></div>
        <div class="okja-settings-preview-header">
            <div class="okja-settings-avatar">&#128100;</div>
            <h3>Max Mustermann</h3>
            <div class="okja-settings-preview-role">Jugendarbeiter*in</div>
            <a class="okja-preview-contact" href="#">max@example.de</a>
        </div>
        <div class="okja-settings-preview-copy"><?php esc_html_e( 'Dies ist eine Beispiel-Biografie. Hier steht eine kurze Beschreibung der Person.', 'jhh-posts-block' ); ?></div>
    </div>
    <p class="description okja-settings-preview-note"><?php esc_html_e( 'Live-Vorschau der Kartenfarben. Änderungen werden sofort angezeigt.', 'jhh-posts-block' ); ?></p>
    <?php
}

function okja_render_events_show_field() {
    $show = get_option( 'okja_events_show_in_angebot', '1' );
    ?>
    <label>
        <input type="hidden" name="okja_events_show_in_angebot" value="0">
        <input type="checkbox" name="okja_events_show_in_angebot" value="1" <?php checked( $show, '1' ); ?>>
        <?php esc_html_e( 'Events auf der Einzelseite des Angebots anzeigen', 'jhh-posts-block' ); ?>
    </label>
    <p class="description"><?php esc_html_e( 'Wenn aktiviert, werden zugeordnete Events automatisch unter dem Team-Bereich angezeigt.', 'jhh-posts-block' ); ?></p>
    <?php
}

function okja_render_event_link_mode_field() {
    $mode = get_option( 'okja_event_link_mode', 'single' );
    ?>
    <fieldset class="okja-settings-radio-list">
        <label><input type="radio" name="okja_event_link_mode" value="single" <?php checked( $mode, 'single' ); ?>> <?php esc_html_e( 'Eigene Single-Seite', 'jhh-posts-block' ); ?></label>
        <label><input type="radio" name="okja_event_link_mode" value="modal" <?php checked( $mode, 'modal' ); ?>> <?php esc_html_e( 'Popup-Modal auf derselben Seite', 'jhh-posts-block' ); ?></label>
    </fieldset>
    <p class="description"><?php esc_html_e( 'Gilt für die vom Plugin gerenderten Event-Kacheln und Event-Links. Im Modal bleibt die normale Single-Seite weiterhin als Fallback erreichbar.', 'jhh-posts-block' ); ?></p>
    <?php
}

function okja_render_events_range_field() {
    $future_days = (int) get_option( 'okja_events_future_days', 365 );
    $past_days   = (int) get_option( 'okja_events_past_days', 0 );
    ?>
    <table class="form-table okja-settings-compact-table okja-settings-range-table">
        <tr>
            <th scope="row"><?php esc_html_e( 'Zukünftige Events anzeigen (Tage)', 'jhh-posts-block' ); ?></th>
            <td>
                <input type="number" name="okja_events_future_days" value="<?php echo esc_attr( $future_days ); ?>" min="0" max="3650" class="small-text">
                <p class="description"><?php esc_html_e( 'Wie viele Tage in die Zukunft sollen Events angezeigt werden? 0 = unbegrenzt.', 'jhh-posts-block' ); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e( 'Vergangene Events anzeigen (Tage)', 'jhh-posts-block' ); ?></th>
            <td>
                <input type="number" name="okja_events_past_days" value="<?php echo esc_attr( $past_days ); ?>" min="0" max="3650" class="small-text">
                <p class="description"><?php esc_html_e( 'Wie viele Tage nach Ablauf sollen vergangene Events noch angezeigt werden? 0 = keine.', 'jhh-posts-block' ); ?></p>
            </td>
        </tr>
    </table>
    <?php
}

function okja_render_event_card_style_field() {
    $style = get_option( 'okja_event_card_style', 'simple' );
    ?>
    <fieldset class="okja-settings-radio-list">
        <label><input type="radio" name="okja_event_card_style" value="simple" <?php checked( $style, 'simple' ); ?>> <?php esc_html_e( 'Schlicht (themeabhängig: dunkel/hell, mit Farblinie)', 'jhh-posts-block' ); ?></label>
        <label><input type="radio" name="okja_event_card_style" value="notebook" <?php checked( $style, 'notebook' ); ?>> <?php esc_html_e( 'Papier (Notizbuch)', 'jhh-posts-block' ); ?></label>
        <label><input type="radio" name="okja_event_card_style" value="aurora" <?php checked( $style, 'aurora' ); ?>> <?php esc_html_e( 'Pastell-Gradient', 'jhh-posts-block' ); ?></label>
        <label><input type="radio" name="okja_event_card_style" value="grainy-1" <?php checked( $style, 'grainy-1' ); ?>> <?php esc_html_e( 'Grainy Gradient (Bild 1)', 'jhh-posts-block' ); ?></label>
        <label><input type="radio" name="okja_event_card_style" value="grainy-2" <?php checked( $style, 'grainy-2' ); ?>> <?php esc_html_e( 'Grainy Gradient (Bild 2)', 'jhh-posts-block' ); ?></label>
        <label><input type="radio" name="okja_event_card_style" value="grainy-3" <?php checked( $style, 'grainy-3' ); ?>> <?php esc_html_e( 'Grainy Gradient (Bild 3)', 'jhh-posts-block' ); ?></label>
        <label><input type="radio" name="okja_event_card_style" value="custom" <?php checked( $style, 'custom' ); ?>> <?php esc_html_e( 'Benutzerdefiniert (eigene Farben unten)', 'jhh-posts-block' ); ?></label>
    </fieldset>
    <p class="description"><?php esc_html_e( 'Bei „Schlicht“ wechseln Farben automatisch je nach Theme-Modus (Dark/Light).', 'jhh-posts-block' ); ?></p>
    <?php
}

function okja_render_event_card_topline_field() {
    $show = get_option( 'okja_event_card_topline', '1' );
    ?>
    <label>
        <input type="hidden" name="okja_event_card_topline" value="0">
        <input type="checkbox" name="okja_event_card_topline" value="1" <?php checked( $show, '1' ); ?>>
        <?php esc_html_e( 'Gradient-Linie oben auf der Kachel anzeigen', 'jhh-posts-block' ); ?>
    </label>
    <p class="description"><?php esc_html_e( 'Zeigt eine farbige Gradient-Linie am oberen Rand der Event-Kachel an.', 'jhh-posts-block' ); ?></p>
    <?php
}

function okja_render_event_card_color_fields() {
    $bg     = get_option( 'okja_event_card_bg', '#1e1b1b' );
    $text   = get_option( 'okja_event_card_text', '#ffffff' );
    $accent = get_option( 'okja_event_card_accent', '#b9aaff' );
    ?>
    <table class="form-table okja-settings-compact-table">
        <tr>
            <th scope="row"><?php esc_html_e( 'Hintergrund', 'jhh-posts-block' ); ?></th>
            <td><input type="text" name="okja_event_card_bg" value="<?php echo esc_attr( $bg ); ?>" class="okja-color-picker" data-default-color="#1e1b1b"></td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e( 'Text', 'jhh-posts-block' ); ?></th>
            <td><input type="text" name="okja_event_card_text" value="<?php echo esc_attr( $text ); ?>" class="okja-color-picker" data-default-color="#ffffff"></td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e( 'Akzent (Labels, Linie)', 'jhh-posts-block' ); ?></th>
            <td><input type="text" name="okja_event_card_accent" value="<?php echo esc_attr( $accent ); ?>" class="okja-color-picker" data-default-color="#b9aaff"></td>
        </tr>
    </table>
    <?php
}

function okja_render_event_card_preview_field() {
    $bg          = get_option( 'okja_event_card_bg', '#1e1b1b' );
    $text        = get_option( 'okja_event_card_text', '#ffffff' );
    $accent      = get_option( 'okja_event_card_accent', '#b9aaff' );
    $style       = get_option( 'okja_event_card_style', 'simple' );
    $topline     = get_option( 'okja_event_card_topline', '1' );
    $grainy_urls = okja_get_settings_grainy_urls();
    $image_var   = isset( $grainy_urls[ $style ] ) ? 'url(' . $grainy_urls[ $style ] . ')' : 'none';
    $label_color = ( $style === 'notebook' ) ? '#888888' : 'rgba(255,255,255,0.55)';
    if ( $style === 'custom' ) {
        $label_color = $accent;
    }
    $style_attr = '--okja-event-bg:' . $bg . ';--okja-event-text:' . $text . ';--okja-event-accent:' . $accent . ';--okja-event-bg-image:' . $image_var . ';--okja-event-label:' . $label_color . ';';
    ?>
    <div id="okja-event-card-preview" class="okja-settings-event-preview" data-style="<?php echo esc_attr( $style ); ?>" data-topline="<?php echo esc_attr( $topline ); ?>" style="<?php echo esc_attr( $style_attr ); ?>">
        <div class="okja-event-preview-topline"></div>
        <div class="okja-settings-event-grid">
            <div class="okja-settings-event-item"><span class="okja-settings-event-icon">&#128197;</span><div><div class="okja-event-preview-label">Datum</div><div class="okja-settings-event-value">So, 22. Feb 2026</div></div></div>
            <div class="okja-settings-event-item"><span class="okja-settings-event-icon">&#128337;</span><div><div class="okja-event-preview-label">Uhrzeit</div><div class="okja-settings-event-value">14:00 - 18:00 Uhr</div></div></div>
            <div class="okja-settings-event-item"><span class="okja-settings-event-icon">&#128176;</span><div><div class="okja-event-preview-label">Preis</div><div class="okja-settings-event-value">3 EUR</div></div></div>
            <div class="okja-settings-event-item"><span class="okja-settings-event-icon">&#128101;</span><div><div class="okja-event-preview-label">Teilnehmer</div><div class="okja-settings-event-value">max. 10 Plaetze</div></div></div>
        </div>
    </div>
    <p class="description okja-settings-preview-note"><?php esc_html_e( 'Live-Vorschau der Event-Kachel. Änderungen werden sofort angezeigt.', 'jhh-posts-block' ); ?></p>
    <?php
}

function okja_render_hero_animation_field() {
    $animation = get_option( 'okja_hero_animation', 'none' );
    $options = [
        'none'          => __( 'Keine Animation', 'jhh-posts-block' ),
        'fade-in-up'    => __( '⬆️ Fade In Up – Sanftes Einblenden von unten', 'jhh-posts-block' ),
        'slide-in-left' => __( '⬅️ Slide In Left – Hereinrutschen von links', 'jhh-posts-block' ),
        'scale-pop'     => __( '💥 Scale Pop – Aufploppen mit Bounce', 'jhh-posts-block' ),
        'typewriter'    => __( '⌨️ Typewriter – Schreibmaschinen-Effekt', 'jhh-posts-block' ),
        'glitch'        => __( '⚡ Glitch – Digitaler Störungs-Effekt', 'jhh-posts-block' ),
        'wave'          => __( '🌊 Wave – Sanftes Wippen (dauerhaft)', 'jhh-posts-block' ),
        'glow-pulse'    => __( '✨ Glow Pulse – Pulsierendes Leuchten', 'jhh-posts-block' ),
    ];
    $combined_options = [
        'cinematic'      => __( '🎬 Cinematic – Ken Burns Zoom + Epic Text Reveal', 'jhh-posts-block' ),
        'parallax-drift' => __( '🌙 Parallax Drift – Schwebendes Bild + Traumhafte Schrift', 'jhh-posts-block' ),
        'explosive'      => __( '💣 Explosive – Burst Zoom + Impact Text', 'jhh-posts-block' ),
        'vortex'         => __( '🌀 Vortex – Spiral Zoom + Drehende Schrift', 'jhh-posts-block' ),
        'aurora'         => __( '🌈 Aurora Borealis – Farbwechsel + Ätherische Schrift', 'jhh-posts-block' ),
        'spotlight'      => __( '🔦 Spotlight – Dramatischer Lichtstrahl + Bold Reveal', 'jhh-posts-block' ),
        'glitch-storm'   => __( '⚡ Glitch Storm – Digitales Chaos + Verzerrte Schrift', 'jhh-posts-block' ),
    ];
    ?>
    <fieldset>
        <legend class="okja-settings-legend"><?php esc_html_e( 'Einfache Text-Animationen:', 'jhh-posts-block' ); ?></legend>
        <select name="okja_hero_animation" id="okja_hero_animation" class="okja-settings-wide-select">
            <?php foreach ( $options as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $animation, $value ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
            <optgroup label="<?php esc_attr_e( '🎭 Opulente Kombinationen (Bild + Text)', 'jhh-posts-block' ); ?>">
                <?php foreach ( $combined_options as $value => $label ) : ?>
                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $animation, $value ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </optgroup>
        </select>
    </fieldset>
    <p class="description"><?php esc_html_e( 'Einfache Animationen: Nur der Titel wird animiert.', 'jhh-posts-block' ); ?><br><?php esc_html_e( 'Opulente Kombinationen: Hintergrundbild UND Titel werden gemeinsam animiert.', 'jhh-posts-block' ); ?></p>
    <?php
}

function okja_render_hero_hover_field() {
    $hover = get_option( 'okja_hero_hover', 'none' );
    $options = [
        'none'        => __( 'Kein Hover-Effekt', 'jhh-posts-block' ),
        'glow'        => __( '✨ Glow – Leuchtender Schein', 'jhh-posts-block' ),
        'scale'       => __( '🔍 Scale – Leicht vergrößern', 'jhh-posts-block' ),
        'color-shift' => __( '🌈 Color Shift – Regenbogen-Farbverlauf', 'jhh-posts-block' ),
        'underline'   => __( '➖ Underline – Animierte Unterstreichung', 'jhh-posts-block' ),
        'shake'       => __( '📳 Shake – Wackeln', 'jhh-posts-block' ),
    ];
    ?>
    <select name="okja_hero_hover">
        <?php foreach ( $options as $value => $label ) : ?>
            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $hover, $value ); ?>><?php echo esc_html( $label ); ?></option>
        <?php endforeach; ?>
    </select>
    <p class="description"><?php esc_html_e( 'Wird beim Hover über den Titel aktiviert.', 'jhh-posts-block' ); ?></p>
    <?php
}

function okja_render_color_mode_field() {
    $mode = get_option( 'okja_color_mode', 'auto' );
    ?>
    <select name="okja_color_mode">
        <option value="auto" <?php selected( $mode, 'auto' ); ?>><?php esc_html_e( 'Automatisch (Theme-Selektor)', 'jhh-posts-block' ); ?></option>
        <option value="dark" <?php selected( $mode, 'dark' ); ?>><?php esc_html_e( 'Immer Dark', 'jhh-posts-block' ); ?></option>
        <option value="light" <?php selected( $mode, 'light' ); ?>><?php esc_html_e( 'Immer Light', 'jhh-posts-block' ); ?></option>
    </select>
    <p class="description"><?php esc_html_e( 'Bei "Automatisch" wird der Dark/Light Mode anhand der Theme-Selektoren erkannt.', 'jhh-posts-block' ); ?></p>
    <?php
}

function okja_render_dark_selector_field() {
    $sel = get_option( 'okja_dark_selector', 'html[data-neve-theme="dark"]' );
    ?>
    <input type="text" name="okja_dark_selector" value="<?php echo esc_attr( $sel ); ?>" class="regular-text okja-settings-textwide">
    <p class="description"><?php esc_html_e( 'CSS-Selektor für Dark Mode. Beispiele:', 'jhh-posts-block' ); ?><br><code>html[data-neve-theme="dark"]</code> (Neve Theme)<br><code>body.dark-mode</code> (Andere Themes)<br><code>html.dark</code> (Tailwind-basierte Themes)</p>
    <?php
}

function okja_render_light_selector_field() {
    $sel = get_option( 'okja_light_selector', 'html[data-neve-theme="light"]' );
    ?>
    <input type="text" name="okja_light_selector" value="<?php echo esc_attr( $sel ); ?>" class="regular-text okja-settings-textwide">
    <p class="description"><?php esc_html_e( 'CSS-Selektor für Light Mode (wird genutzt um Dark bei auto auszuschließen). Beispiele:', 'jhh-posts-block' ); ?><br><code>html[data-neve-theme="light"]</code> (Neve Theme)<br><code>body.light-mode</code> (Andere Themes)</p>
    <?php
}

function okja_render_angebot_wizard_field() {
    $enabled = get_option( 'okja_enable_angebot_wizard', '1' );
    ?>
    <label>
        <input type="hidden" name="okja_enable_angebot_wizard" value="0">
        <input type="checkbox" name="okja_enable_angebot_wizard" value="1" <?php checked( $enabled, '1' ); ?>>
        <?php esc_html_e( 'Geführten Wizard beim Erstellen und Bearbeiten von Angeboten anzeigen', 'jhh-posts-block' ); ?>
    </label>
    <p class="description"><?php esc_html_e( 'Öffnet beim Anlegen neuer Angebote eine schrittweise Eingabemaske für Titel, Beschreibung, Kategorien, Zeiten und Bild. Bestehende Angebote können über einen Button im Editor erneut im Wizard bearbeitet werden.', 'jhh-posts-block' ); ?></p>
    <?php
}

function okja_render_angebotsevent_wizard_field() {
    $enabled = get_option( 'okja_enable_angebotsevent_wizard', '1' );
    ?>
    <label>
        <input type="hidden" name="okja_enable_angebotsevent_wizard" value="0">
        <input type="checkbox" name="okja_enable_angebotsevent_wizard" value="1" <?php checked( $enabled, '1' ); ?>>
        <?php esc_html_e( 'Geführten Wizard beim Erstellen und Bearbeiten von A-Events anzeigen', 'jhh-posts-block' ); ?>
    </label>
    <p class="description"><?php esc_html_e( 'Öffnet beim Anlegen neuer A-Events eine schrittweise Eingabemaske für Angebot, Datum, Uhrzeit, Preis, Teilnehmerzahl, CTA und Bild.', 'jhh-posts-block' ); ?></p>
    <?php
}

function okja_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap" id="okja-angebote-settings">
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
