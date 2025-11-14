<?php
/**
 * Plugin Name: NBT Kalendarz Świąt
 * Description: Prosty miesięczny kalendarz świąt z pełnymi nazwami (shortcode [nbt_kalendarz]).
 * Version: 1.0.0
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
        $value = get_post_meta( $post->ID, '_nbt_swieto_data', true );
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
            '1.0.0'
        );

        wp_register_script(
            'nbt-calendar-js',
            $base . 'nbt-calendar.js',
            [],
            '1.0.0',
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

                $events[] = [
                    'id'    => $id,
                    'title' => get_the_title(),
                    'date'  => $date, // format RRRR-MM-DD
                    'link'  => get_permalink( $id ),
                ];
            }
            wp_reset_postdata();
        }

        wp_enqueue_style( 'nbt-calendar-css' );
        wp_enqueue_script( 'nbt-calendar-js' );
        wp_localize_script( 'nbt-calendar-js', 'nbtCalendarEvents', $events );

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
}

new NBT_Kalendarz_Swiat();
