<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function jhh_pb_get_day_schedule_summary( $post_id ) {
    $labels = [
        'montag'     => __( 'Mo', 'jhh-posts-block' ),
        'dienstag'   => __( 'Di', 'jhh-posts-block' ),
        'mittwoch'   => __( 'Mi', 'jhh-posts-block' ),
        'donnerstag' => __( 'Do', 'jhh-posts-block' ),
        'freitag'    => __( 'Fr', 'jhh-posts-block' ),
        'samstag'    => __( 'Sa', 'jhh-posts-block' ),
        'sonntag'    => __( 'So', 'jhh-posts-block' ),
    ];
    $selected_days = get_post_meta( $post_id, 'jhh_days', true );
    $times         = get_post_meta( $post_id, 'jhh_day_times', true );

    if ( ! is_array( $selected_days ) || ! $selected_days ) {
        return '';
    }

    if ( ! is_array( $times ) ) {
        $times = [];
    }

    $items = [];
    foreach ( $selected_days as $day_key ) {
        if ( ! isset( $labels[ $day_key ] ) ) {
            continue;
        }
        $label = $labels[ $day_key ];
        $start = isset( $times[ $day_key ]['start'] ) ? trim( (string) $times[ $day_key ]['start'] ) : '';
        $end   = isset( $times[ $day_key ]['end'] ) ? trim( (string) $times[ $day_key ]['end'] ) : '';

        if ( $start && $end ) {
            $items[] = sprintf( '%1$s %2$s-%3$s', $label, $start, $end );
        } elseif ( $start ) {
            $items[] = sprintf( '%1$s %2$s', $label, $start );
        } else {
            $items[] = $label;
        }
    }

    return implode( ', ', $items );
}

add_filter( 'manage_angebot_posts_columns', function( $columns ) {
    $new_columns = [];
    foreach ( $columns as $key => $label ) {
        $new_columns[ $key ] = $label;
        if ( $key === 'title' ) {
            $new_columns['jhh_angebot_jugend'] = __( 'Jugendarbeit', 'jhh-posts-block' );
            $new_columns['jhh_angebot_paed']   = __( 'Pädagogik', 'jhh-posts-block' );
            $new_columns['jhh_angebot_days']   = __( 'Wochentage', 'jhh-posts-block' );
        }
    }
    return $new_columns;
} );

add_action( 'manage_angebot_posts_custom_column', function( $column, $post_id ) {
    if ( $column === 'jhh_angebot_jugend' ) {
        $terms = get_the_terms( $post_id, JHH_TAX_JUGEND );
        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            echo '<span aria-hidden="true">—</span>';
            return;
        }

        echo esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) );
        return;
    }

    if ( $column === 'jhh_angebot_paed' ) {
        $terms = get_the_terms( $post_id, JHH_TAX_PAED );
        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            echo '<span aria-hidden="true">—</span>';
            return;
        }

        $badges = [];
        foreach ( $terms as $term ) {
            $bg_color   = trim( (string) get_term_meta( $term->term_id, 'badge_bg_color', true ) );
            $text_color = trim( (string) get_term_meta( $term->term_id, 'badge_text_color', true ) );
            $style      = '';
            if ( $bg_color ) {
                $style .= 'background:' . esc_attr( $bg_color ) . ';';
            }
            if ( $text_color ) {
                $style .= 'color:' . esc_attr( $text_color ) . ';';
            }
            $badges[] = '<span style="display:inline-block;margin:0 6px 6px 0;padding:4px 8px;border-radius:999px;border:1px solid rgba(0,0,0,.08);font-size:11px;font-weight:600;' . $style . '">' . esc_html( $term->name ) . '</span>';
        }

        echo wp_kses_post( implode( '', $badges ) );
        return;
    }

    if ( $column === 'jhh_angebot_days' ) {
        $summary = jhh_pb_get_day_schedule_summary( $post_id );
        echo $summary ? esc_html( $summary ) : '<span aria-hidden="true">—</span>';
    }
}, 10, 2 );

add_filter( 'manage_angebotsevent_posts_columns', function( $columns ) {
    $new_columns = [];
    foreach ( $columns as $key => $label ) {
        $new_columns[ $key ] = $label;
        if ( $key === 'title' ) {
            $new_columns['jhh_event_date'] = __( 'Veranstaltungsdatum', 'jhh-posts-block' );
        }
    }
    if ( ! isset( $new_columns['jhh_event_date'] ) ) {
        $new_columns['jhh_event_date'] = __( 'Veranstaltungsdatum', 'jhh-posts-block' );
    }
    return $new_columns;
} );

add_action( 'manage_angebotsevent_posts_custom_column', function( $column, $post_id ) {
    if ( $column !== 'jhh_event_date' ) {
        return;
    }

    $event_date = get_post_meta( $post_id, 'jhh_event_date', true );
    if ( ! $event_date ) {
        echo '—';
        return;
    }

    $ts = strtotime( $event_date );
    echo $ts ? esc_html( wp_date( 'd.m.Y', $ts ) ) : esc_html( $event_date );
}, 10, 2 );

add_filter( 'manage_edit-angebotsevent_sortable_columns', function( $columns ) {
    $columns['jhh_event_date'] = 'jhh_event_date';
    return $columns;
} );

add_action( 'pre_get_posts', function( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) {
        return;
    }
    if ( $query->get( 'post_type' ) !== 'angebotsevent' ) {
        return;
    }
    if ( $query->get( 'orderby' ) !== 'jhh_event_date' ) {
        return;
    }

    $query->set( 'meta_key', 'jhh_event_date' );
    $query->set( 'orderby', 'meta_value' );
} );

if ( ! function_exists( 'jhh_pb_sanitize_day_times' ) ) {
    function jhh_pb_sanitize_day_times( $value ) {
        $days = [ 'montag', 'dienstag', 'mittwoch', 'donnerstag', 'freitag', 'samstag', 'sonntag' ];
        $out  = [];
        if ( is_string( $value ) ) {
            $decoded = json_decode( $value, true );
            if ( is_array( $decoded ) ) {
                $value = $decoded;
            } else {
                $value = [];
            }
        }
        if ( is_object( $value ) ) {
            $value = (array) $value;
        }
        if ( ! is_array( $value ) ) {
            return $out;
        }

        $is_time = static function( $time ) {
            return is_string( $time ) && preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time );
        };

        foreach ( $days as $day ) {
            $row = [];
            if ( isset( $value[ $day ]['start'] ) && $is_time( $value[ $day ]['start'] ) ) {
                $row['start'] = $value[ $day ]['start'];
            }
            if ( isset( $value[ $day ]['end'] ) && $is_time( $value[ $day ]['end'] ) ) {
                $row['end'] = $value[ $day ]['end'];
            }
            if ( $row ) {
                $out[ $day ] = $row;
            }
        }
        return $out;
    }
}

if ( ! function_exists( 'jhh_pb_sanitize_days' ) ) {
    function jhh_pb_sanitize_days( $value ) {
        $allowed = [ 'montag', 'dienstag', 'mittwoch', 'donnerstag', 'freitag', 'samstag', 'sonntag' ];
        $out     = [];
        if ( is_string( $value ) ) {
            $decoded = json_decode( $value, true );
            if ( is_array( $decoded ) ) {
                $value = $decoded;
            } else {
                $value = [];
            }
        }
        if ( is_object( $value ) ) {
            $value = (array) $value;
        }
        if ( ! is_array( $value ) ) {
            return $out;
        }
        foreach ( $value as $entry ) {
            $entry = strtolower( sanitize_text_field( (string) $entry ) );
            if ( in_array( $entry, $allowed, true ) ) {
                $out[] = $entry;
            }
        }
        $order = array_flip( $allowed );
        usort( $out, function( $left, $right ) use ( $order ) {
            return ( $order[ $left ] ?? 99 ) <=> ( $order[ $right ] ?? 99 );
        } );
        return array_values( array_unique( $out ) );
    }
}

add_action( 'init', function() {
    register_post_meta( 'angebot', 'jhh_days', [
        'single'            => true,
        'type'              => 'array',
        'sanitize_callback' => 'jhh_pb_sanitize_days',
        'show_in_rest'      => false,
        'auth_callback'     => function() {
            return current_user_can( 'edit_posts' );
        },
    ] );

    register_post_meta( 'angebot', 'jhh_staff_card_style', [
        'single'            => true,
        'type'              => 'string',
        'sanitize_callback' => function( $value ) {
            $value = sanitize_key( (string) $value );
            return in_array( $value, [ 'global', 'simple', 'notebook', 'aurora', 'custom' ], true ) ? $value : 'global';
        },
        'show_in_rest'      => false,
        'auth_callback'     => function() {
            return current_user_can( 'edit_posts' );
        },
    ] );
}, 5 );

add_action( 'add_meta_boxes', function() {
    add_meta_box( 'jhh_day_times', __( 'Zeiten pro Tag', 'jhh-posts-block' ), 'jhh_render_day_times_metabox', 'angebot', 'side', 'default' );
    add_meta_box( 'jhh_staff_style', __( 'Mitarbeiter-Karte Stil', 'jhh-posts-block' ), 'jhh_render_staff_style_metabox', 'angebot', 'side', 'default' );
} );

function jhh_render_day_times_metabox( $post ) {
    wp_nonce_field( 'jhh_day_times_save', 'jhh_day_times_nonce' );
    $times = get_post_meta( $post->ID, 'jhh_day_times', true );
    if ( ! is_array( $times ) ) {
        $times = [];
    }
    $days = [
        'montag'     => __( 'Montag', 'jhh-posts-block' ),
        'dienstag'   => __( 'Dienstag', 'jhh-posts-block' ),
        'mittwoch'   => __( 'Mittwoch', 'jhh-posts-block' ),
        'donnerstag' => __( 'Donnerstag', 'jhh-posts-block' ),
        'freitag'    => __( 'Freitag', 'jhh-posts-block' ),
        'samstag'    => __( 'Samstag', 'jhh-posts-block' ),
        'sonntag'    => __( 'Sonntag', 'jhh-posts-block' ),
    ];
    $selected_days = get_post_meta( $post->ID, 'jhh_days', true );
    if ( ! is_array( $selected_days ) ) {
        $selected_days = [];
    }
    echo '<p>' . esc_html__( 'Wähle die Tage und optional Start-/Endzeiten. Zeiten werden nur in der Single-Ansicht angezeigt.', 'jhh-posts-block' ) . '</p>';
    echo '<style>.jhh-day-row{margin:6px 0}.jhh-day-row label{display:inline-block;font-weight:600;margin:0 8px 0 0}.jhh-day-row .jhh-time{width:110px}</style>';
    foreach ( $days as $key => $label ) {
        $start   = isset( $times[ $key ]['start'] ) ? esc_attr( $times[ $key ]['start'] ) : '';
        $end     = isset( $times[ $key ]['end'] ) ? esc_attr( $times[ $key ]['end'] ) : '';
        $checked = in_array( $key, $selected_days, true ) ? 'checked' : '';
        echo '<div class="jhh-day-row">';
        echo '<label><input type="checkbox" name="jhh_days[]" value="' . esc_attr( $key ) . '" ' . $checked . '> ' . esc_html( $label ) . '</label>';
        echo '<input class="jhh-time" type="time" name="jhh_day_times[' . esc_attr( $key ) . '][start]" value="' . $start . '" /> ';
        echo '&nbsp;–&nbsp;';
        echo '<input class="jhh-time" type="time" name="jhh_day_times[' . esc_attr( $key ) . '][end]" value="' . $end . '" />';
        echo '</div>';
    }
}

function jhh_render_staff_style_metabox( $post ) {
    wp_nonce_field( 'jhh_staff_style_save', 'jhh_staff_style_nonce' );
    $global_default = get_option( 'okja_default_staff_style', 'simple' );
    $post_style     = get_post_meta( $post->ID, 'jhh_staff_card_style', true );
    $use_global     = empty( $post_style ) || $post_style === 'global';

    echo '<p>' . esc_html__( 'Darstellung der Mitarbeiter-Karte in der Single-Ansicht wählen.', 'jhh-posts-block' ) . '</p>';
    echo '<label style="display:block;margin-bottom:6px;background:#f0f0f1;padding:8px;border-radius:4px;"><input type="radio" name="jhh_staff_card_style" value="global"' . checked( $use_global, true, false ) . '> ' . sprintf( esc_html__( 'Global (%s) – Einstellung unter Einstellungen > OKJA Angebote', 'jhh-posts-block' ), esc_html( $global_default ) ) . '</label>';
    echo '<label style="display:block;margin-bottom:6px;"><input type="radio" name="jhh_staff_card_style" value="simple"' . checked( $post_style, 'simple', false ) . '> ' . esc_html__( 'Schlicht (themeabhängig: dunkel/hell, mit Farblinie)', 'jhh-posts-block' ) . '</label>';
    echo '<label style="display:block;margin-bottom:6px;"><input type="radio" name="jhh_staff_card_style" value="notebook"' . checked( $post_style, 'notebook', false ) . '> ' . esc_html__( 'Papier (Notizbuch) – mit aufgepinntem Foto', 'jhh-posts-block' ) . '</label>';
    echo '<label style="display:block;margin-bottom:6px;"><input type="radio" name="jhh_staff_card_style" value="aurora"' . checked( $post_style, 'aurora', false ) . '> ' . esc_html__( 'Pastell‑Gradient – ohne Pin', 'jhh-posts-block' ) . '</label>';
    echo '<label style="display:block;"><input type="radio" name="jhh_staff_card_style" value="custom"' . checked( $post_style, 'custom', false ) . '> ' . esc_html__( 'Benutzerdefiniert (Farben aus Einstellungen)', 'jhh-posts-block' ) . '</label>';
}

add_action( 'save_post_angebot', function( $post_id ) {
    if ( ! isset( $_POST['jhh_day_times_nonce'] ) || ! wp_verify_nonce( $_POST['jhh_day_times_nonce'], 'jhh_day_times_save' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    $days_raw = isset( $_POST['jhh_days'] ) ? (array) $_POST['jhh_days'] : [];
    $days     = jhh_pb_sanitize_days( $days_raw );
    update_post_meta( $post_id, 'jhh_days', $days );

    $raw_times = isset( $_POST['jhh_day_times'] ) ? (array) $_POST['jhh_day_times'] : [];
    $san_times = jhh_pb_sanitize_day_times( $raw_times );
    if ( $days ) {
        $san_times = array_intersect_key( $san_times, array_flip( $days ) );
    } else {
        $san_times = [];
    }
    update_post_meta( $post_id, 'jhh_day_times', $san_times );

    if ( isset( $_POST['jhh_staff_style_nonce'] ) && wp_verify_nonce( $_POST['jhh_staff_style_nonce'], 'jhh_staff_style_save' ) ) {
        $style = isset( $_POST['jhh_staff_card_style'] ) ? sanitize_key( (string) $_POST['jhh_staff_card_style'] ) : 'global';
        if ( ! in_array( $style, [ 'simple', 'notebook', 'aurora', 'custom', 'global' ], true ) ) {
            $style = 'global';
        }
        update_post_meta( $post_id, 'jhh_staff_card_style', $style );
    }
} );

add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
        return;
    }

    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || $screen->post_type !== 'angebot' ) {
        return;
    }

    if ( get_option( 'okja_enable_angebot_wizard', '1' ) !== '1' ) {
        return;
    }

    $wizard_css = JHH_PB_DIR . 'assets/angebot-wizard.css';
    $wizard_js  = JHH_PB_DIR . 'assets/angebot-wizard.js';
    $wizard_ver = JHH_PB_VERSION;

    if ( file_exists( $wizard_js ) ) {
        $wizard_ver .= '.' . filemtime( $wizard_js );
    }

    wp_enqueue_media();
    wp_enqueue_style(
        'okja-angebot-wizard',
        JHH_PB_URL . 'assets/angebot-wizard.css',
        [],
        file_exists( $wizard_css ) ? JHH_PB_VERSION . '.' . filemtime( $wizard_css ) : $wizard_ver
    );
    wp_enqueue_script(
        'okja-angebot-wizard',
        JHH_PB_URL . 'assets/angebot-wizard.js',
        [ 'jquery', 'media-editor', 'wp-data', 'wp-blocks', 'wp-block-editor' ],
        $wizard_ver,
        true
    );

    $jugend_terms = taxonomy_exists( JHH_TAX_JUGEND ) ? get_terms( [
        'taxonomy'   => JHH_TAX_JUGEND,
        'hide_empty' => false,
    ] ) : [];
    $paed_terms = taxonomy_exists( JHH_TAX_PAED ) ? get_terms( [
        'taxonomy'   => JHH_TAX_PAED,
        'hide_empty' => false,
    ] ) : [];

    wp_localize_script( 'okja-angebot-wizard', 'okjaAngebotWizardData', [
        'isNew'       => $hook === 'post-new.php' ? '1' : '0',
        'canPublish'  => current_user_can( 'publish_posts' ) ? '1' : '0',
        'taxJugend'   => JHH_TAX_JUGEND,
        'taxPaed'     => JHH_TAX_PAED,
        'jugend'      => array_values( array_filter( array_map( function( $term ) {
            if ( ! $term instanceof WP_Term ) {
                return null;
            }
            $avatar_id  = (int) get_term_meta( $term->term_id, 'avatar_id', true );
            $avatar_url = $avatar_id ? wp_get_attachment_image_url( $avatar_id, 'thumbnail' ) : '';
            return [
                'id'        => (int) $term->term_id,
                'name'      => $term->name,
                'role'      => (string) get_term_meta( $term->term_id, 'funktion', true ),
                'avatarUrl' => $avatar_url ? esc_url_raw( $avatar_url ) : '',
            ];
        }, is_array( $jugend_terms ) ? $jugend_terms : [] ) ) ),
        'paed'        => array_values( array_filter( array_map( function( $term ) {
            if ( ! $term instanceof WP_Term ) {
                return null;
            }
            return [
                'id'        => (int) $term->term_id,
                'name'      => $term->name,
                'bgColor'   => (string) get_term_meta( $term->term_id, 'badge_bg_color', true ),
                'textColor' => (string) get_term_meta( $term->term_id, 'badge_text_color', true ),
            ];
        }, is_array( $paed_terms ) ? $paed_terms : [] ) ) ),
        'dayLabels'   => [
            'montag'     => __( 'Montag', 'jhh-posts-block' ),
            'dienstag'   => __( 'Dienstag', 'jhh-posts-block' ),
            'mittwoch'   => __( 'Mittwoch', 'jhh-posts-block' ),
            'donnerstag' => __( 'Donnerstag', 'jhh-posts-block' ),
            'freitag'    => __( 'Freitag', 'jhh-posts-block' ),
            'samstag'    => __( 'Samstag', 'jhh-posts-block' ),
            'sonntag'    => __( 'Sonntag', 'jhh-posts-block' ),
        ],
        'staffStyles' => [
            [ 'value' => 'global', 'label' => __( 'Global (Einstellung aus den Plugin-Optionen)', 'jhh-posts-block' ) ],
            [ 'value' => 'simple', 'label' => __( 'Schlicht', 'jhh-posts-block' ) ],
            [ 'value' => 'notebook', 'label' => __( 'Papier / Notizbuch', 'jhh-posts-block' ) ],
            [ 'value' => 'aurora', 'label' => __( 'Pastell-Gradient', 'jhh-posts-block' ) ],
            [ 'value' => 'custom', 'label' => __( 'Benutzerdefiniert (Farben aus Einstellungen)', 'jhh-posts-block' ) ],
        ],
        'texts'       => [
            'openWizard'    => __( 'Angebots-Wizard öffnen', 'jhh-posts-block' ),
            'classicEditor' => __( 'Editor direkt nutzen', 'jhh-posts-block' ),
            'back'          => __( 'Zurück', 'jhh-posts-block' ),
            'next'          => __( 'Weiter', 'jhh-posts-block' ),
            'publish'       => current_user_can( 'publish_posts' ) ? __( 'Veröffentlichen', 'jhh-posts-block' ) : __( 'Zur Prüfung einreichen', 'jhh-posts-block' ),
            'saveDraft'     => __( 'Entwurf speichern', 'jhh-posts-block' ),
            'contentNotice' => __( 'Bestehende komplexe Block-Inhalte werden vom Wizard nicht überschrieben. Nutze dafür weiterhin den normalen Editor.', 'jhh-posts-block' ),
            'imageButton'   => __( 'Beitragsbild wählen', 'jhh-posts-block' ),
            'imageReplace'  => __( 'Beitragsbild ersetzen', 'jhh-posts-block' ),
            'removeImage'   => __( 'Bild entfernen', 'jhh-posts-block' ),
            'noSelection'   => __( 'Noch nichts ausgewählt', 'jhh-posts-block' ),
            'newOffer'      => __( 'Neues Angebot', 'jhh-posts-block' ),
        ],
    ] );
} );

add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
        return;
    }

    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || $screen->post_type !== 'angebotsevent' ) {
        return;
    }

    if ( get_option( 'okja_enable_angebotsevent_wizard', '1' ) !== '1' ) {
        return;
    }

    $wizard_css = JHH_PB_DIR . 'assets/angebot-wizard.css';
    $wizard_js  = JHH_PB_DIR . 'assets/angebotsevent-wizard.js';
    $wizard_ver = JHH_PB_VERSION;

    if ( file_exists( $wizard_js ) ) {
        $wizard_ver .= '.' . filemtime( $wizard_js );
    }

    wp_enqueue_media();
    wp_enqueue_style(
        'okja-angebotsevent-wizard',
        JHH_PB_URL . 'assets/angebot-wizard.css',
        [],
        file_exists( $wizard_css ) ? JHH_PB_VERSION . '.' . filemtime( $wizard_css ) : $wizard_ver
    );
    wp_enqueue_script(
        'okja-angebotsevent-wizard',
        JHH_PB_URL . 'assets/angebotsevent-wizard.js',
        [ 'jquery', 'media-editor', 'wp-data', 'wp-blocks', 'wp-block-editor' ],
        $wizard_ver,
        true
    );

    $angebote = get_posts( [
        'post_type'      => 'angebot',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'post_status'    => 'publish',
    ] );

    wp_localize_script( 'okja-angebotsevent-wizard', 'okjaAngebotsEventWizardData', [
        'isNew'      => $hook === 'post-new.php' ? '1' : '0',
        'canPublish' => current_user_can( 'publish_posts' ) ? '1' : '0',
        'angebote'   => array_map( function( $post ) {
            return [
                'id'    => (int) $post->ID,
                'title' => $post->post_title,
            ];
        }, $angebote ),
        'texts'      => [
            'openWizard'    => __( 'A-Event-Wizard öffnen', 'jhh-posts-block' ),
            'classicEditor' => __( 'Editor direkt nutzen', 'jhh-posts-block' ),
            'back'          => __( 'Zurück', 'jhh-posts-block' ),
            'next'          => __( 'Weiter', 'jhh-posts-block' ),
            'publish'       => current_user_can( 'publish_posts' ) ? __( 'Veröffentlichen', 'jhh-posts-block' ) : __( 'Zur Prüfung einreichen', 'jhh-posts-block' ),
            'saveDraft'     => __( 'Entwurf speichern', 'jhh-posts-block' ),
            'contentNotice' => __( 'Bestehende komplexe Block-Inhalte werden vom Wizard nicht überschrieben. Nutze dafür weiterhin den normalen Editor.', 'jhh-posts-block' ),
            'imageButton'   => __( 'Beitragsbild wählen', 'jhh-posts-block' ),
            'imageReplace'  => __( 'Beitragsbild ersetzen', 'jhh-posts-block' ),
            'removeImage'   => __( 'Bild entfernen', 'jhh-posts-block' ),
            'noSelection'   => __( 'Noch nichts ausgewählt', 'jhh-posts-block' ),
            'newEvent'      => __( 'Neues A-Event', 'jhh-posts-block' ),
            'unlimited'     => __( 'Unbegrenzt', 'jhh-posts-block' ),
            'soldOut'       => __( 'Ausgebucht', 'jhh-posts-block' ),
        ],
    ] );
} );

add_action( 'init', function() {
    $event_meta = [
        'jhh_event_angebot_id' => [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
        ],
        'jhh_event_price' => [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ],
        'jhh_event_max_participants' => [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
        ],
        'jhh_event_sold_out' => [
            'type'              => 'boolean',
            'sanitize_callback' => function( $value ) {
                return (bool) $value;
            },
        ],
        'jhh_event_date' => [
            'type'              => 'string',
            'sanitize_callback' => function( $value ) {
                $value = sanitize_text_field( (string) $value );
                return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
            },
        ],
        'jhh_event_time_start' => [
            'type'              => 'string',
            'sanitize_callback' => function( $value ) {
                $value = sanitize_text_field( (string) $value );
                return preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value ) ? $value : '';
            },
        ],
        'jhh_event_time_end' => [
            'type'              => 'string',
            'sanitize_callback' => function( $value ) {
                $value = sanitize_text_field( (string) $value );
                return preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value ) ? $value : '';
            },
        ],
        'jhh_event_cta_url' => [
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
        ],
        'jhh_event_cta_label' => [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ],
    ];

    foreach ( $event_meta as $key => $args ) {
        register_post_meta( 'angebotsevent', $key, array_merge( $args, [
            'single'        => true,
            'show_in_rest'  => true,
            'auth_callback' => function() {
                return current_user_can( 'edit_posts' );
            },
        ] ) );
    }
}, 5 );

add_action( 'add_meta_boxes', function() {
    add_meta_box(
        'jhh_event_details',
        __( 'Event-Details', 'jhh-posts-block' ),
        'jhh_render_event_details_metabox',
        'angebotsevent',
        'normal',
        'high'
    );
} );

function jhh_render_event_details_metabox( $post ) {
    wp_nonce_field( 'jhh_event_details_save', 'jhh_event_details_nonce' );

    $angebot_id       = (int) get_post_meta( $post->ID, 'jhh_event_angebot_id', true );
    $price            = get_post_meta( $post->ID, 'jhh_event_price', true );
    $max_participants = (int) get_post_meta( $post->ID, 'jhh_event_max_participants', true );
    $event_date       = get_post_meta( $post->ID, 'jhh_event_date', true );
    $time_start       = get_post_meta( $post->ID, 'jhh_event_time_start', true );
    $time_end         = get_post_meta( $post->ID, 'jhh_event_time_end', true );
    $sold_out         = (bool) get_post_meta( $post->ID, 'jhh_event_sold_out', true );

    $angebote = get_posts( [
        'post_type'      => 'angebot',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'post_status'    => 'publish',
    ] );

    echo '<style>.jhh-event-field{margin:12px 0}.jhh-event-field label{display:block;font-weight:600;margin-bottom:4px}.jhh-event-field input,.jhh-event-field select{width:100%;max-width:400px}.jhh-event-field input[type="time"]{width:160px}.jhh-event-field input[type="checkbox"]{width:auto;max-width:none}.jhh-event-row{display:flex;gap:16px;flex-wrap:wrap}.jhh-event-row .jhh-event-field{flex:1;min-width:160px}.jhh-event-soldout-field{margin-top:16px;padding:10px 12px;border:1px solid #d0d0d0;border-radius:6px;background:#f8f8f8}</style>';

    echo '<div class="jhh-event-field">';
    echo '<label for="jhh_event_angebot_id">' . esc_html__( 'Zugeordnetes Angebot', 'jhh-posts-block' ) . '</label>';
    echo '<select name="jhh_event_angebot_id" id="jhh_event_angebot_id">';
    echo '<option value="0">' . esc_html__( '— Kein Angebot —', 'jhh-posts-block' ) . '</option>';
    foreach ( $angebote as $angebot ) {
        printf( '<option value="%d" %s>%s</option>', $angebot->ID, selected( $angebot_id, $angebot->ID, false ), esc_html( $angebot->post_title ) );
    }
    echo '</select>';
    echo '<p class="description">' . esc_html__( 'Ordne dieses Event einem bestehenden Angebot zu.', 'jhh-posts-block' ) . '</p>';
    echo '</div>';

    echo '<div class="jhh-event-field">';
    echo '<label for="jhh_event_date">' . esc_html__( 'Datum', 'jhh-posts-block' ) . '</label>';
    echo '<input type="date" name="jhh_event_date" id="jhh_event_date" value="' . esc_attr( $event_date ) . '" />';
    echo '</div>';

    echo '<div class="jhh-event-row">';
    echo '<div class="jhh-event-field">';
    echo '<label for="jhh_event_time_start">' . esc_html__( 'Startzeit', 'jhh-posts-block' ) . '</label>';
    echo '<input type="time" name="jhh_event_time_start" id="jhh_event_time_start" value="' . esc_attr( $time_start ) . '" />';
    echo '</div>';
    echo '<div class="jhh-event-field">';
    echo '<label for="jhh_event_time_end">' . esc_html__( 'Endzeit', 'jhh-posts-block' ) . '</label>';
    echo '<input type="time" name="jhh_event_time_end" id="jhh_event_time_end" value="' . esc_attr( $time_end ) . '" />';
    echo '</div>';
    echo '</div>';

    echo '<div class="jhh-event-row">';
    echo '<div class="jhh-event-field">';
    echo '<label for="jhh_event_price">' . esc_html__( 'Preis', 'jhh-posts-block' ) . '</label>';
    echo '<input type="text" name="jhh_event_price" id="jhh_event_price" value="' . esc_attr( $price ) . '" placeholder="z.B. 5,00 € oder kostenlos" />';
    echo '</div>';
    echo '<div class="jhh-event-field">';
    echo '<label for="jhh_event_max_participants">' . esc_html__( 'Max. Teilnehmer', 'jhh-posts-block' ) . '</label>';
    echo '<input type="number" name="jhh_event_max_participants" id="jhh_event_max_participants" value="' . esc_attr( $max_participants ?: '' ) . '" min="0" placeholder="0 = unbegrenzt" />';
    echo '</div>';
    echo '</div>';

    echo '<input type="hidden" name="jhh_event_sold_out" value="0">';
    echo '<div class="jhh-event-field jhh-event-soldout-field">';
    echo '<label for="jhh_event_sold_out" style="display:flex;align-items:center;gap:8px;cursor:pointer;margin:0;font-weight:600;">';
    echo '<input type="checkbox" id="jhh_event_sold_out" name="jhh_event_sold_out" value="1" ' . checked( $sold_out, true, false ) . ' style="width:auto;max-width:none;margin:0;">';
    echo '<span>' . esc_html__( 'Ausgebucht', 'jhh-posts-block' ) . '</span>';
    echo '</label>';
    echo '<p class="description" style="margin:6px 0 0;">' . esc_html__( 'Markiert dieses Event als ausgebucht. Ein Banner wird über der Karte angezeigt.', 'jhh-posts-block' ) . '</p>';
    echo '</div>';

    $cta_url   = get_post_meta( $post->ID, 'jhh_event_cta_url', true );
    $cta_label = get_post_meta( $post->ID, 'jhh_event_cta_label', true );

    echo '<hr style="margin:20px 0 16px;border:0;border-top:1px solid #ddd;">';
    echo '<h4 style="margin:0 0 8px;">' . esc_html__( 'Call to Action / Anmeldung', 'jhh-posts-block' ) . '</h4>';
    echo '<div class="jhh-event-field">';
    echo '<label for="jhh_event_cta_url">' . esc_html__( 'Link / E-Mail / Telefon', 'jhh-posts-block' ) . '</label>';
    echo '<input type="text" name="jhh_event_cta_url" id="jhh_event_cta_url" value="' . esc_attr( $cta_url ) . '" placeholder="https://… oder mailto:… oder tel:…" />';
    echo '<p class="description">' . esc_html__( 'URL (https://…), E-Mail (mailto:…) oder Telefon (tel:…). Wird als Anmelde-Button angezeigt.', 'jhh-posts-block' ) . '</p>';
    echo '</div>';
    echo '<div class="jhh-event-field">';
    echo '<label for="jhh_event_cta_label">' . esc_html__( 'Button-Text', 'jhh-posts-block' ) . '</label>';
    echo '<input type="text" name="jhh_event_cta_label" id="jhh_event_cta_label" value="' . esc_attr( $cta_label ) . '" placeholder="Jetzt anmelden" />';
    echo '</div>';
}

add_action( 'save_post_angebotsevent', function( $post_id ) {
    if ( ! isset( $_POST['jhh_event_details_nonce'] ) || ! wp_verify_nonce( $_POST['jhh_event_details_nonce'], 'jhh_event_details_save' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $fields = [
        'jhh_event_angebot_id'       => 'absint',
        'jhh_event_price'            => 'sanitize_text_field',
        'jhh_event_max_participants' => 'absint',
        'jhh_event_date'             => 'sanitize_text_field',
        'jhh_event_time_start'       => 'sanitize_text_field',
        'jhh_event_time_end'         => 'sanitize_text_field',
    ];
    foreach ( $fields as $key => $sanitize ) {
        if ( isset( $_POST[ $key ] ) ) {
            $value = call_user_func( $sanitize, wp_unslash( $_POST[ $key ] ) );
            update_post_meta( $post_id, $key, $value );
        }
    }

    $sold_out = isset( $_POST['jhh_event_sold_out'] ) ? (bool) absint( $_POST['jhh_event_sold_out'] ) : false;
    update_post_meta( $post_id, 'jhh_event_sold_out', $sold_out );

    if ( isset( $_POST['jhh_event_cta_url'] ) ) {
        $raw_cta = sanitize_text_field( wp_unslash( $_POST['jhh_event_cta_url'] ) );
        if ( preg_match( '/^(mailto:|tel:)/i', $raw_cta ) ) {
            update_post_meta( $post_id, 'jhh_event_cta_url', $raw_cta );
        } else {
            update_post_meta( $post_id, 'jhh_event_cta_url', esc_url_raw( $raw_cta ) );
        }
    }
    if ( isset( $_POST['jhh_event_cta_label'] ) ) {
        update_post_meta( $post_id, 'jhh_event_cta_label', sanitize_text_field( wp_unslash( $_POST['jhh_event_cta_label'] ) ) );
    }
} );