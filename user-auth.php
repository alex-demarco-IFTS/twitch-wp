<?php
namespace IFQ\Twitch;
/**
 * gestore connessione utente con Twitch
 */

/*
 * HTML pagina 'Collegamento ad un account Twitch' (ID = 528)
 */
// HTML pulsante 'Collega / Scollega account Twitch'
function connect_twitch_button_html() {
    $is_user_logged = ! empty( get_user_meta( get_current_user_id(), 'ifqtw_tw_user_id', true )[0] ); // flag utente collegato ad account Twitch
    $button_text = ( $is_user_logged ? 'Sc' : 'C' ) . 'ollega account Twitch'; // gestisce testo pulsante a seconda se l'account dell'utente è già collegato o meno
    $button_link = null;
    if ( ! $is_user_logged ) {
        $tw_user_login_uri = Twitch_Api_Interface::oauth_retrieve_tw_user_login_uri(); // inizio Authorization code grant flow
        $button_link = $tw_user_login_uri;
    } else {
        // scollegare account Twitch: revoca access tokens, alla disconnessione cancellare metadati
        $button_link = 'http://localhost/wordpress/twitch-oauth-callback?revoke=1';
    }
    ?>
    <a href="<?php echo esc_attr( $button_link ); ?>">
        <input type="button" class="ifqtw_connect_twitch_button" name="ifqtw_connect_twitch_button" value="<?php echo esc_attr( $button_text ); ?>">
    </a>
    <?php
    connect_twitch_button_style_css();
}
// CSS foglio di stile per il pulsante 'Collega / Scollega account Twitch'
function connect_twitch_button_style_css() {
    ?>
    <style>
        body {
            background: #f0f0f1;
        }
        .ifqtw_connect_twitch_button {
            margin: 0;
            position: absolute;
            top: 50%;
            left: 50%;
            -ms-transform: translate(-50%, -50%);
            transform: translate(-50%, -50%);
            color: #ffffff;
            background: #9146FF;
            border-color: #9146FF;
            border-radius: 3px;
            height: 30px;
            border-style: solid;
        }
        .ifqtw_connect_twitch_button:hover {
            background: #3f167e;
            border-color: #3f167e;
        }
    </style>
    <?php
}


/*
 * Authorization code grant flow, reindirizzamento, revoca
 */
// Authorization code grant flow - pagina di reindirizzamento dopo autorizzazione utente (ID = 532) e revoca
function oauth_user_authorization_redirect() {
    global $wp_query;
    if ( $wp_query->is_page && $wp_query->queried_object_id == 532 ) {
        if ( ! empty( $_GET['state'] ) ) { // Authorization code grant flow - reindirizzamento dopo autorizzazione - parametri nella url query string
            $user_auth_code = oauth_pick_user_authorization_code(); // step 1
            oauth_obtain_user_token( $user_auth_code ); // step 2
            oauth_completion(); // step 3
            die(); // previene reinvio richiesta POST
        } elseif ( ! empty( $_GET['revoke'] ) ) { // scollegamento account Twitch: revoca access tokens, alla disconnessione cancellare metadati
            $revoke_success = Twitch_Api_Interface::oauth_revoke_user_access();
            if ( $revoke_success ) {
                delete_tw_user_meta();
                wp_redirect( 'http://localhost/wordpress/scollegamento-account-twitch-successo/' );
            } else {
                wp_redirect( 'http://localhost/wordpress/collegamento-allaccount-twitch-errore/' );
            }
            die();
        }
    }
}
add_action( 'wp', 'IFQ\Twitch\oauth_user_authorization_redirect' );

// Authorization code grant flow - preleva codice di autorizzazione utente dalla query string
function oauth_pick_user_authorization_code() {
    // controllo state string
    if ( $_GET['state'] != get_post_meta( 532, 'ifqtw_user_auth_temp_state_string' )[0] || isset( $_GET['error'] ) ) { // se 'state' della risposta non corrisponde a quello della richiesta -> ignora
        // mostrare messaggio errore autorizzazione/login non effettuato
        throw new \Exception( "Richiesta autorizzazione non valida." );
    }
    return $_GET['code'];
}

// Authorization code grant flow - token <- richiesta, token -> salvataggio
function oauth_obtain_user_token( $user_auth_code ) {
    // richiesta e ottenimento token
    $token_response = Twitch_Api_Interface::oauth_user_token_request( $user_auth_code );
    // salvataggio token
    save_tw_user_tokens( $token_response );
    // flow autorizzazione completato
}

// salva i token di accesso utente negli user meta
function save_tw_user_tokens( $token_response ) {
    // prelievo token dalla risposta
    $access_token = $token_response['access_token'];
    $access_token_expiration_minutes = $token_response['expires_in'];
    $access_token_expiration_date = time() + ( $access_token_expiration_minutes * 60 ); // calcolo data scadenza token
    $refresh_token = $token_response['refresh_token'];
    // salvataggio token negli user meta
    update_user_meta( get_current_user_id(), 'ifqtw_user_access_token', $access_token );
    update_user_meta( get_current_user_id(), 'ifqtw_user_access_token_expiration', $access_token_expiration_date );
    update_user_meta( get_current_user_id(), 'ifqtw_user_refresh_token', $refresh_token );
}

// completamento flow di autorizzazione: controllo ottenimento token e eventuale salvataggio dati utente
function oauth_completion() {
    // controllo ottenimento token
    try {
        retrieve_tw_user_access_token();
    } catch ( \Exception $ex ) {
        wp_redirect( 'http://localhost/wordpress/collegamento-allaccount-twitch-errore/' );
        // autorizzazione non completata - user token non memorizzato
        // mostrare messaggio di errore
        return;
    }
    // ottenimento e salvataggio dettagli utente loggato
    retrieve_and_save_logged_tw_user_data();
    // controllo e eventuale aggiornamento abbonamento sul database
    verify_and_update_logged_user_subscription();
    wp_redirect( 'http://localhost/wordpress/collegamento-allaccount-twitch-successo/' );
    // autorizzazione completata - user token salvato negli user meta
    // mostrare messaggio autorizzazione/login effettuato
}

// ottiene e salva dettagli utente loggato (username e id)
function retrieve_and_save_logged_tw_user_data() {
    $logged_tw_user = Twitch_Api_Interface::get_tw_user_info();
    // salvataggio dettagli utente loggato
    update_user_meta( get_current_user_id(), 'ifqtw_tw_user_name', $logged_tw_user['display_name'] );
    update_user_meta( get_current_user_id(), 'ifqtw_tw_user_id', $logged_tw_user['id'] );
}

// controlla ed eventualmente aggiorna abbonamento utente loggato sul database
function verify_and_update_logged_user_subscription( $wp_user_id = null ) {
    if ( empty( $wp_user_id ) ) {
        $wp_user_id = get_current_user_id();
    }
    $logged_tw_user_id = get_user_meta( $wp_user_id, 'ifqtw_tw_user_id' )[0];
    $tw_broadcaster_id = retrieve_tw_broadcaster_id();
    $tw_subscription = Twitch_Api_Interface::check_tw_active_user_subscription( $logged_tw_user_id, $tw_broadcaster_id );
    if ( $tw_subscription ) { // se utente appena collegato abbonato al canale di IFQ
        store_logged_user_subscription( $tw_subscription );
    }
}

// salva l'abbonamento dell'utente collegato
function store_logged_user_subscription( $tw_subscription ) {
    $search_param = array( 'tw_user_id' => $tw_subscription['user_id'] );
    $db_subscription = Database_Manager::search_subscriptions( $search_param, null, 1 );
    $existing_sub = ! empty( $db_subscription ); // se abbonamento già presente sul database
    $db_subscription['tw_user_id'] = $tw_subscription['user_id'];
    $db_subscription['wp_user_id'] = get_current_user_id(); // imposta id utente Wordpress
    $db_subscription['plan'] = $tw_subscription['tier'];
    if ( ! $existing_sub ) {
        $db_subscription['start'] = wp_date( 'Y-m-d H:i:s' );
        $db_subscription['end'] = wp_date( 'Y-m-d H:i:s', time() + 31 * DAY_IN_SECONDS );
    }
    Database_Manager::save_subscription( $db_subscription, $existing_sub );
}

// revoca del token di accesso - pulisce i metadati dell'utente (ID, nome e token utente Twitch)
// chiavi: ifqtw_tw_user_name, ifqtw_tw_user_id, ifqtw_user_access_token, ifqtw_user_access_token_expiration, ifqtw_user_refresh_token
function delete_tw_user_meta( $wp_user_id = null ) {
    if ( empty( $wp_user_id ) ) {
        $wp_user_id = get_current_user_id();
    }
    delete_user_meta( $wp_user_id, 'ifqtw_tw_user_name' );
    delete_user_meta( $wp_user_id, 'ifqtw_tw_user_id' );
    delete_user_meta( $wp_user_id, 'ifqtw_user_access_token' );
    delete_user_meta( $wp_user_id, 'ifqtw_user_access_token_expiration' );
    delete_user_meta( $wp_user_id, 'ifqtw_user_refresh_token' );
}


/*
 * HTML pagina 'Account Twitch collegato con successo' (ID = 538)
 */
function oauth_success_html() {
    $tw_user_name = get_user_meta( get_current_user_id(), 'ifqtw_tw_user_name' )[0];
    ?>
    <h2>Account Twitch collegato con successo!</h2>
    <p>Hai effettuato l'accesso come <span><strong><?php echo esc_html( $tw_user_name ); ?></strong></span></p>
    <br><a href="<?php echo esc_attr( esc_url( admin_url('about.php') ) ); ?>">
        <input type="button" class="ifqtw_home_button" name="ifqtw_home_button" value="Ritorna alla home">
    </a>
    <?php
}
function oauth_success_style_css() {
    ?>
    <style>
        body {
            background: #f0f0f1;
        }
        #content {
            margin: 0;
            position: absolute;
            top: 50%;
            left: 50%;
            -ms-transform: translate(-50%, -50%);
            transform: translate(-50%, -50%);
            font-family: Arial, Helvetica, sans-serif;
        }
    </style>
    <?php
}