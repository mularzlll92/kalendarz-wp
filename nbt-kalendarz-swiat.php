<?php
/**
 * Plugin Name: NBT Kalendarz Świąt
 * Description: Prosty miesięczny kalendarz świąt z pełnymi nazwami (shortcode [nbt_kalendarz]).
 * Version: 1.2.0
 * Author: Narodowa Baza Talentów
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NBT_Kalendarz_Swiat {

    public function __construct() {
        add_action( 'init', [ $this, 'register_cpt' ] );
        add_action( 'add_meta_boxes', [ $this, 'register_metabox' ] );
        add_action( 'save_post', [ $this, 'save_metabox' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
        add_shortcode( 'nbt_kalendarz', [ $this, 'shortcode_calendar' ] );
        add_action( 'wp_ajax_nbt_calendar_preview', [ $this, 'ajax_preview' ] );
        add_action( 'wp_ajax_nopriv_nbt_calendar_preview', [ $this, 'ajax_preview' ] );
    }

    /**
     * Rejestracja CPT na święta
     */
    public function register_cpt() {

        $labels = [
            'name'               => 'Święta w kalendarzu',
            'singular_name'      => 'Święto',
            'menu_name'          => 'Kalendarz świąt',
            'add_new'            => 'Dodaj święto',
            'add_new_item'       => 'Dodaj nowe święto',
            'edit_item'          => 'Edytuj święto',
            'new_item'           => 'Nowe święto',
            'view_item'          => 'Zobacz święto',
            'search_items'       => 'Szukaj świąt',
            'not_found'          => 'Brak świąt',
            'not_found_in_trash' => 'Brak świąt w koszu',
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'menu_icon'          => 'dashicons-calendar',
            'supports'           => [ 'title' ],
            'has_archive'        => false,
            'rewrite'            => false,
        ];

        register_post_type( 'nbt_swieto', $args );
    }

    /**
     * Metabox z datą święta
     */
    public function register_metabox() {
        add_meta_box(
            'nbt_swieto_data',
            'Data święta',
            [ $this, 'metabox_html' ],
            'nbt_swieto',
            'side',
            'high'
        );
    }

    public function metabox_html( $post ) {
        wp_nonce_field( 'nbt_swieto_data_save', 'nbt_swieto_data_nonce' );
        $value      = get_post_meta( $post->ID, '_nbt_swieto_data', true );
        $link_value = get_post_meta( $post->ID, '_nbt_swieto_link', true );
        ?>
        <p>
            <label for="nbt_swieto_data">Data:</label><br>
            <input
                type="date"
                id="nbt_swieto_data"
                name="nbt_swieto_data"
                value="<?php echo esc_attr( $value ); ?>"
                style="width:100%;"
            >
        </p>
        <p style="font-size:12px;color:#666;">
            Ustaw konkretną datę w formacie RRRR-MM-DD. Nazwa święta pochodzi z tytułu wpisu.
        </p>
        <p>
            <label for="nbt_swieto_link">Adres przekierowania:</label><br>
            <input
                type="url"
                id="nbt_swieto_link"
                name="nbt_swieto_link"
                value="<?php echo esc_attr( $link_value ); ?>"
                placeholder="https://przyklad.pl"
                style="width:100%;"
            >
        </p>
        <p style="font-size:12px;color:#666;">
            Podaj pełny adres URL, pod który ma prowadzić święto w kalendarzu. Pozostaw puste, aby użyć domyślnego linku do wpisu.
        </p>
        <?php
    }

    public function save_metabox( $post_id ) {
        if ( ! isset( $_POST['nbt_swieto_data_nonce'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_POST['nbt_swieto_data_nonce'], 'nbt_swieto_data_save' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( isset( $_POST['post_type'] ) && 'nbt_swieto' === $_POST['post_type'] ) {
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return;
            }
        }

        if ( isset( $_POST['nbt_swieto_data'] ) && ! empty( $_POST['nbt_swieto_data'] ) ) {
            $date = sanitize_text_field( $_POST['nbt_swieto_data'] );
            // prosta walidacja formatu RRRR-MM-DD
            if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
                update_post_meta( $post_id, '_nbt_swieto_data', $date );
            }
        } else {
            delete_post_meta( $post_id, '_nbt_swieto_data' );
        }

        if ( isset( $_POST['nbt_swieto_link'] ) ) {
            $link = esc_url_raw( trim( wp_unslash( $_POST['nbt_swieto_link'] ) ) );
            if ( ! empty( $link ) ) {
                update_post_meta( $post_id, '_nbt_swieto_link', $link );
            } else {
                delete_post_meta( $post_id, '_nbt_swieto_link' );
            }
        }
    }

    /**
     * Rejestracja CSS/JS
     */
    public function register_assets() {
        $base = plugin_dir_url( __FILE__ ) . 'assets/';

        wp_register_style(
            'nbt-calendar-css',
            $base . 'nbt-calendar.css',
            [],
            '1.2.0'
        );

        wp_register_script(
            'nbt-calendar-js',
            $base . 'nbt-calendar.js',
            [],
            '1.2.0',
            true
        );
    }

    /**
     * Shortcode [nbt_kalendarz]
     */
    public function shortcode_calendar() {

        // pobierz wszystkie święta z datą
        $query = new WP_Query([
            'post_type'      => 'nbt_swieto',
            'posts_per_page' => -1,
            'meta_key'       => '_nbt_swieto_data',
            'orderby'        => 'meta_value',
            'order'          => 'ASC',
        ]);

        $events = [];

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $id   = get_the_ID();
                $date = get_post_meta( $id, '_nbt_swieto_data', true );
                if ( ! $date ) {
                    continue;
                }

                $custom_link = get_post_meta( $id, '_nbt_swieto_link', true );
                $final_link  = $custom_link ? $custom_link : get_permalink( $id );

                $events[] = [
                    'id'    => $id,
                    'title' => get_the_title(),
                    'date'  => $date, // format RRRR-MM-DD
                    'link'  => esc_url( $final_link ),
                ];
            }
            wp_reset_postdata();
        }

        wp_enqueue_style( 'nbt-calendar-css' );
        wp_enqueue_script( 'nbt-calendar-js' );
        wp_localize_script( 'nbt-calendar-js', 'nbtCalendarEvents', $events );
        wp_localize_script(
            'nbt-calendar-js',
            'nbtCalendarConfig',
            [
                'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
                'previewNonce' => wp_create_nonce( 'nbt_calendar_preview' ),
            ]
        );

        ob_start();
        ?>
        <div class="nbt-cal-wrapper">
            <div id="nbt-calendar">
                <!-- nagłówek i siatka zbuduje JS -->
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    /**
     * AJAX: podgląd linku święta
     */
    public function ajax_preview() {
        if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'nbt_calendar_preview' ) ) {
            wp_send_json_error( [ 'message' => 'Błąd weryfikacji.' ], 403 );
        }

        $url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

        if ( empty( $url ) ) {
            wp_send_json_error( [ 'message' => 'Brak adresu URL.' ], 400 );
        }

        $response = wp_safe_remote_get( $url, [ 'timeout' => 8 ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => $response->get_error_message() ], 500 );
        }

        $body = wp_remote_retrieve_body( $response );

        if ( empty( $body ) ) {
            wp_send_json_error( [ 'message' => 'Brak treści odpowiedzi.' ], 500 );
        }

        $meta = [
            'title'       => '',
            'description' => '',
            'image'       => '',
        ];

        if ( preg_match( '/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $body, $matches ) ) {
            $meta['title'] = sanitize_text_field( $matches[1] );
        }

        if ( preg_match( '/<meta[^>]+property=["\']og:description["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $body, $matches ) ) {
            $meta['description'] = sanitize_text_field( $matches[1] );
        }

        if ( preg_match( '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $body, $matches ) ) {
            $meta['image'] = esc_url_raw( $matches[1] );
        }

        if ( empty( $meta['title'] ) && preg_match( '/<title>([^<]+)<\/title>/i', $body, $matches ) ) {
            $meta['title'] = sanitize_text_field( $matches[1] );
        }

        wp_send_json_success( $meta );
    }
}

new NBT_Kalendarz_Swiat();
