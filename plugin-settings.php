<?php
namespace IFQ\Twitch;
/**
 * impostazioni del plugin
 */
// inizializzazione impostazioni
function settings_init() {
    register_setting( 'ifqtw_plugin_config', 'ifqtw_client_id' , 'sanitize_text_field' );         // id del client
    register_setting( 'ifqtw_plugin_config', 'ifqtw_client_secret' , 'sanitize_text_field' );     // client secret
    register_setting( 'ifqtw_plugin_config', 'ifqtw_app_access_token' , 'sanitize_text_field' );  // token di accesso app
    register_setting( 'ifqtw_plugin_config', 'ifqtw_tw_broadcaster_id' , 'sanitize_text_field' ); // id del canale IFQ
    add_settings_section( 'plugin_config_section', 'Impostazioni plugin',
                        'IFQ\Twitch\plugin_config_section_callback', 'ifqtw_plugin_config' );
    add_settings_field( 'client_id_field', 'ID Client',
                        'IFQ\Twitch\client_id_field_callback', 'ifqtw_plugin_config', 'plugin_config_section' );
    add_settings_field( 'client_secret_field', 'Segreto Client',
                        'IFQ\Twitch\client_secret_field_callback', 'ifqtw_plugin_config', 'plugin_config_section' );
    add_settings_field( 'app_access_token_field', 'Token di accesso app',
                        'IFQ\Twitch\app_access_token_field_callback', 'ifqtw_plugin_config', 'plugin_config_section' );
    add_settings_field( 'tw_broadcaster_id_field', 'ID canale IFQ',
                        'IFQ\Twitch\tw_broadcaster_id_field_callback', 'ifqtw_plugin_config', 'plugin_config_section' );
    add_settings_field( 'tw_user_name_field', 'Nome account Twitch',
                        'IFQ\Twitch\tw_user_name_field_callback', 'ifqtw_plugin_config', 'plugin_config_section' );
    add_settings_field( 'user_access_token_field', 'ID utente Twitch',
                        'IFQ\Twitch\tw_user_id_field_callback', 'ifqtw_plugin_config', 'plugin_config_section' );
    add_settings_field( 'tw_user_id_field', 'Token di accesso utente',
                        'IFQ\Twitch\user_access_token_field_callback', 'ifqtw_plugin_config', 'plugin_config_section' );
    }
add_action( 'admin_init', 'IFQ\Twitch\settings_init' );

// HTML descrizione
function plugin_config_section_callback() {
    ?><p>Credenziali API Twitch</p><?php
}

// HTML input 'Client ID'
function client_id_field_callback() {
    $client_id_setting = get_option( 'ifqtw_client_id' );
    ?><input type="password" size="22" name="ifqtw_client_id" value="<?php echo isset( $client_id_setting ) ? esc_attr( $client_id_setting ) : ''; ?>"><?php
}

// HTML input 'Client Secret'
function client_secret_field_callback() {
    $client_secret_setting = get_option( 'ifqtw_client_secret' );
    ?><input type="password" size="22" name="ifqtw_client_secret" value="<?php echo isset( $client_secret_setting ) ? esc_attr( $client_secret_setting ) : ''; ?>"><?php
}

// HTML input 'Token di accesso app'
function app_access_token_field_callback() {
    $app_access_token_setting = get_option( 'ifqtw_app_access_token' );
    ?><input type="password" size="22" name="ifqtw_app_access_token" value="<?php echo isset( $app_access_token_setting ) ? esc_attr( $app_access_token_setting ) : ''; ?>"><?php
}

// HTML input 'ID canale IFQ'
function tw_broadcaster_id_field_callback() {
    $tw_broadcaster_id_setting = get_option( 'ifqtw_tw_broadcaster_id' );
    ?><input type="text" size="8" name="ifqtw_tw_broadcaster_id" value="<?php echo isset( $tw_broadcaster_id_setting ) ? esc_attr( $tw_broadcaster_id_setting ) : ''; ?>"><?php
}

// HTML input 'Nome utente Twitch' (non modificabile)
function tw_user_name_field_callback() {
    $tw_user_name_setting = get_user_meta( get_current_user_id(), 'ifqtw_tw_user_name' )[0];
    if ( empty($tw_user_name_setting ) ) {
        $tw_user_name_setting = '';
    }
    ?><input type="text" size="15" name="ifqtw_tw_user_name" value="<?php echo isset( $tw_user_name_setting ) ? esc_attr( $tw_user_name_setting ) : ''; ?>" readonly><?php
}

// HTML input 'ID utente Twitch' (non modificabile)
function tw_user_id_field_callback() {
    $tw_user_id_setting = get_user_meta( get_current_user_id(), 'ifqtw_tw_user_id' )[0];
    if ( empty( $tw_user_id_setting ) ) {
        $tw_user_id_setting = '';
    }
    ?><input type="text" size="8" name="ifqtw_tw_user_id" value="<?php echo isset( $tw_user_id_setting ) ? esc_attr( $tw_user_id_setting ) : ''; ?>" readonly><?php
}

// HTML input 'Token di accesso utente' (non modificabile)
function user_access_token_field_callback() {
    $user_access_token_setting = get_user_meta( get_current_user_id(), 'ifqtw_user_access_token' )[0];
    if ( empty( $user_access_token_setting ) ) {
        $user_access_token_setting = '';
    }
    $user_access_token_expiration_date = wp_date( 'd/m/Y', get_user_meta( get_current_user_id(), 'ifqtw_user_access_token_expiration' )[0] );
    $user_access_token_expired = strtotime( $user_access_token_expiration_date ) < strtotime( wp_date( 'd/m/Y' ) );
    $user_access_token_expiration_label_text = $user_access_token_expired ? 'Scaduto' : 'Scade il ' . $user_access_token_expiration_date;
    $user_access_token_expiration_label_style = 'style="margin-left: 5px;' . ( $user_access_token_expired ? ' color: #FF0000;"' : '"' );
    ?>
    <input type="password" size="22" name="ifqtw_user_access_token" value="<?php echo isset( $user_access_token_setting ) ? esc_attr( $user_access_token_setting ) : ''; ?>" readonly>
    <label <?php echo esc_attr( $user_access_token_expiration_label_style ) ?>><?php echo esc_html( $user_access_token_expiration_label_text ) ?></label>
    <?php
}

// aggiunge pagina opzioni
function options_page() {
    add_options_page(
        'IFQ Twitch Connect - Configurazione Plugin',
        'IFQ Twitch Connect Plugin',
        'manage_options',
        'ifqtw_configuration',
        'IFQ\Twitch\options_page_html'
    );
}
add_action( 'admin_menu', 'IFQ\Twitch\options_page' );

// HTML pagina opzioni
function options_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    if ( isset( $_GET['settings-updated'] ) ) {
        add_settings_error( 'ifqtw_messages', 'ifqtw_message', __( 'Impostazioni salvate', 'ifqtw' ), 'updated' );
    }
    settings_errors( 'ifqtw_messages' );
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'ifqtw_plugin_config' );
            do_settings_sections( 'ifqtw_plugin_config' );
            submit_button( 'Salva' );
            ?>
        </form>
    </div>
    <?php
}

/*
 * Submenu tabella abbonamenti (paginata)
 */
// aggiunge pagina submenu tabella abbonamenti
function subscriptions_table_page() {
    add_submenu_page(
        'ifqtw_menu',
        'Lista abbonamenti Twitch',
        'Lista abbonamenti Twitch',
        'manage_options',
        'ifqtw_all_subscriptions',
        'IFQ\Twitch\subscriptions_table_page_html'
    );
    remove_submenu_page( 'ifqtw_menu', 'ifqtw_menu' );
}
add_action( 'admin_menu', 'IFQ\Twitch\subscriptions_table_page', 11 );

// HTML pagina submenu tabella abbonamenti
function subscriptions_table_page_html() {
    ?>
    <div class="wrap">
        <h2>Lista abbonamenti Twitch</h2>
        <br>
        <?php results_per_page_select_html(); ?>
        <?php $total_subscriptions = total_subscriptions_counter_html(); ?>
        <?php subscriptions_table_html(); ?>
        <br>
        <?php prevoius_next_page_buttons_html( $total_subscriptions ); ?>
    </div>
    <?php
    subscriptions_table_stylesheet_html();
}
//  HTML contatore abbonamenti totali
function total_subscriptions_counter_html() {
    $total_subscriptions = Database_Manager::total_subscriptions_count();
    ?><p>Abbonamenti totali: <?php echo esc_html( $total_subscriptions ); ?></p><?php
    return $total_subscriptions;
}
// HTML selettore numero di risultati per pagina
function results_per_page_select_html() {
    $url = admin_url( 'admin.php' );
    $url = add_query_arg( array( 'page_number' => 1 ) );
    $results_per_page = isset( $_GET['results_per_page'] ) ? absint( $_GET['results_per_page'] ) : 25;
    ?>
    <form method="get">
        <label>Risultati per pagina:</label>
        <select name="results_per_page" id="results_per_page" onchange="set_results_per_page();">
            <option value="25" <?php if ( $results_per_page == 25 ) { echo 'selected="selected"'; } ?>>25</option>
            <option value="50" <?php if ( $results_per_page == 50 ) { echo 'selected="selected"'; } ?>>50</option>
            <option value="100" <?php if ( $results_per_page == 100 ) { echo 'selected="selected"'; } ?>>100</option>
        </select>
    </form>
    <script type="text/javascript">
        function set_results_per_page() {
            results_per_page = document.getElementById("results_per_page").value;
            window.location.replace("<?php echo $url; ?>&results_per_page=" + results_per_page);
        }
    </script>
    <?php
}
// HTML output tabella abbonamenti
function subscriptions_table_html() {
    $results_per_page = isset( $_GET['results_per_page'] ) ? absint( $_GET['results_per_page'] ) : 25;
    $page_number = isset( $_GET['page_number'] ) && $_GET['page_number'] > 1 ? absint( $_GET['page_number'] ) : 1;
    $offset = $results_per_page * ( $page_number - 1 );

    $paged_all_subscriptions = Database_Manager::search_subscriptions( null, null, $results_per_page, $offset );
    ?>
    <table>
        <tr>
            <th>ID utente Twitch</th>
            <th>ID utente Wordpress</th>
            <th>Piano</th>
            <th style="width: 18%;">Inizio</th>
            <th style="width: 18%;">Scadenza</th>
            <th>Mesi cumulativi</th>
            <th>Mesi consecutivi</th>
        </tr>
        <tr>
            <th>'tw_user_id'</th>
            <th>'wp_user_id'</th>
            <th>'plan'</th>
            <th>'start'</th>
            <th>'end'</th>
            <th>'cumulative_months'</th>
            <th>'streak_months'</th>
        </tr>
        <?php if ( ! empty( $paged_all_subscriptions ) ) : ?>
            <?php foreach ( $paged_all_subscriptions as $subscription ) : ?>
                <tr>
                    <td><?php echo esc_html( $subscription['tw_user_id'] ); ?></td>
                    <td><?php echo empty( $subscription['wp_user_id'] ) ? "-" : esc_html( $subscription['wp_user_id'] ); ?></td>
                    <td><?php echo esc_html( $subscription['plan'] ); ?></td>
                    <td><?php echo empty( $subscription['start'] ) ? "-" : esc_html( $subscription['start'] ); ?></td>
                    <td><?php echo empty( $subscription['end'] ) ? "-" : esc_html( $subscription['end'] ); ?></td>
                    <td><?php echo empty( $subscription['cumulative_months'] ) ? "-" : esc_html( $subscription['cumulative_months'] ); ?></td>
                    <td><?php echo empty( $subscription['streak_months'] ) ? "-" : esc_html( $subscription['streak_months'] ); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table>
    <?php
}
// HTML pulsanti avanti/indietro pagina tabella
function prevoius_next_page_buttons_html( $total_subscriptions ) {
    $results_per_page = isset( $_GET['results_per_page'] ) ? absint( $_GET['results_per_page'] ) : 25;
    $page_number = isset( $_GET['page_number'] ) && $_GET['page_number'] > 1 ? absint( $_GET['page_number'] ) : 1;

    $enabled_back_button = $page_number > 1;
    $enabled_next_button = $total_subscriptions > ( $results_per_page * $page_number );

    if ( $enabled_back_button ) {
        $previous_page_url = admin_url( 'admin.php' );
        $previous_page_url = add_query_arg( array(
            'page_number'      => $page_number - 1,
            'results_per_page' => $results_per_page
        ) );
    }
    if ( $enabled_next_button ) {
        $next_page_url = admin_url( 'admin.php' );
        $next_page_url = add_query_arg( array(
            'page_number'      => $page_number + 1,
            'results_per_page' => $results_per_page
        ) );
    }

    ?>
    <a href="<?php echo $enabled_back_button ? esc_url( $previous_page_url ) : ''; ?>">
        <input type="button" name="previous_page" value="<" <?php echo $enabled_back_button ? '' : 'disabled'; ?>>
    </a>
    <a href="<?php echo $enabled_next_button ? esc_url( $next_page_url ) : ''; ?>">
        <input type="button" name="next_page" value=">" <?php echo $enabled_next_button ? '' : 'disabled'; ?>>
    </a>
    <?php
}
// CSS foglio di stile tabella abbonamenti
function subscriptions_table_stylesheet_html() {
    ?>
    <style>
        h2 {
            font-weight: 600;
        }
        table {
            width: 55%;
            border: 1px solid #999;
            background: #fff;
            border-radius: 5px;
        }
        table th, table td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        table tr:last-child > td {
            border-bottom: none;
        }
    </style>
    <?php
}