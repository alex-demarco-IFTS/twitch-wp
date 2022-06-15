<?php
namespace IFQ\Twitch;
/**
 * EventSub event handler
 */

// pagina 'Listener Twitch EventSub' (ID=554, slug=twitch-eventsub, callback_uri=http://localhost/wordpress/twitch-eventsub)
function eventsub_channel_subscriptions_listener() {
    global $wp_query;
    if ( $wp_query->is_page && $wp_query->queried_object_id == 554 ) {
        // verifica firma HMAC
        if ( eventsub_event_message_verification() ) {
            // error_log( '$_SERVER: ' . print_r( $_SERVER, 1 ) );
            $eventJSON = file_get_contents( 'php://input' );
            $event = json_decode( $eventJSON, true );
            $header_message_type = $_SERVER['HTTP_TWITCH_EVENTSUB_MESSAGE_TYPE'];
            switch ( $header_message_type ) {
                case 'notification': // se è notifica di sub
                    // controllare eventuale duplicato tramite ID messaggi precedenti
                    // rispondere con codice 2XX
                    http_response_code( 203 );
                    $event_type = $event['subscription']['type']; // tipologie di evento
                    if ( 'channel.subscribe' === $event_type ||
                         'channel.subscription.message' === $event_type ||
                         'channel.subscription.end' === $event_type ) {
                        $sub_event = $event['event'];
                        store_user_subscription( $sub_event, $event_type );
                    } elseif ( 'stream.online' === $event_type ) {
                        update_option( 'ifqtw_livestream_ongoing', 1 );
                    } elseif ( 'stream.offline' === $event_type ) {
                        update_option( 'ifqtw_livestream_ongoing', 0 );
                    }
                    break;
                case 'webhook_callback_verification':
                    // (https://dev.twitch.tv/docs/eventsub/handling-webhook-events/#responding-to-a-challenge-request)
                    $challenge = $event['challenge'];
                    eventsub_challenge_response( $challenge );
                    break;
                case 'revocation':
                    // (https://dev.twitch.tv/docs/eventsub/handling-webhook-events/#revoking-your-subscription)
                    break;
                default:
                    break;
            }
            var_dump( $event['event'] );
        } else {
            error_log( '403: verifica del messaggio non superata.' );
            // rispondere con codice 403
            http_response_code( 403 );
        }
        die();
    }
}
add_action( 'wp', 'IFQ\Twitch\eventsub_channel_subscriptions_listener' );

// EventSub - verifica che il messaggio di evento provenga da Twitch
// https://dev.twitch.tv/docs/eventsub/handling-webhook-events/#verifying-the-event-message
function eventsub_event_message_verification() {
    $twitch_message_signature = $_SERVER['HTTP_TWITCH_EVENTSUB_MESSAGE_SIGNATURE'];
    $test_secret = '1234567890';// $secret = get_post_meta(554, 'ifqtw_eventsub_temp_secret_string'); ABILITARE IN VERSIONE DI PRODUZIONE
    $message = $_SERVER['HTTP_TWITCH_EVENTSUB_MESSAGE_ID'] . $_SERVER['HTTP_TWITCH_EVENTSUB_MESSAGE_TIMESTAMP'] . file_get_contents('php://input');
    $hmac = 'sha256=' . hash_hmac( 'sha256', $message, $test_secret );
    // error_log( 'my hmac: ' . print_r( $hmac, 1 ) );
    // error_log( 'tw hmac: ' . print_r( $twitch_message_signature, 1 ) );
    return $hmac === $twitch_message_signature;
}

// EventSub - aggiunge le iscrizioni agli eventi di abbonamento e livestream per il canale Twitch di IFQ - eseguire una tantum
function eventsub_event_subscription_create() {
    /*
    * iscrizione a:
    * channel.subscribe (inizio / rinnovo abbonamento)
    * channel.subscription.message (rinnovo abbonamento)
    * channel.subscription.end (fine abbonamento)
    * stream.online (live iniziata)
    * stream.offline (live terminata)
    */
    $challenge = Twitch_Api_Interface::eventsub_subscribe_to_channel_event( 'channel.subscribe', true );
    $challenge = Twitch_Api_Interface::eventsub_subscribe_to_channel_event( 'channel.subscription.message', true );
    $challenge = Twitch_Api_Interface::eventsub_subscribe_to_channel_event( 'channel.subscription.end', true );
    $challenge = Twitch_Api_Interface::eventsub_subscribe_to_channel_event( 'stream.online', true );
    $challenge = Twitch_Api_Interface::eventsub_subscribe_to_channel_event( 'stream.offline', true );
    die();
}

// EventSub - risposta alla challenge per l'iscrizone ad un evento
function eventsub_challenge_response( $challenge ) {
    header( 'Content-Type: text/plain' );
    echo $challenge;
    die();
}

// salva gli abbonamenti ricevuti
function store_user_subscription( $tw_subscription, $event_type ) {
    // controllare - prelevare abbonamento esistente dal database (in base all'id utente Twitch)
    $tw_user_id = $tw_subscription['user_id'];
    $search_param = array( 'tw_user_id' => $tw_user_id );
    $db_subscription = Database_Manager::search_subscriptions( $search_param, null, 1 );
    $existing_sub = ! empty( $db_subscription );
    $existing_sub_expired = $existing_sub && ! empty( $db_subscription['end'] ) && strtotime( $db_subscription['end'] ) < wp_date( 'U' );
    $existing_sub_expired_since_more_than_two_days = $existing_sub_expired && days_passed( $db_subscription['end'], wp_date('Y-m-d H:i:s') ) > 2;

    // settaggio campi
    // scadenza abbonamento (expiration)
    if ( $existing_sub && 'channel.subscription.end' === $event_type ) {
        Database_Manager::subscription_expiration( $tw_user_id );
        return; // fine
    }
    // id utente Twitch - impostato in ogni caso per consentire all'apposita funzione l'aggiornamento nel db
    $db_subscription['tw_user_id'] = $tw_user_id;
    // piano - impostato in ogni caso
    $db_subscription['plan'] = $tw_subscription['tier'];
    // data di inizio - se abbonamento nuovo (non messaggio di rinnovo) o esistente scaduto da più di 2 giorni imposta data corrente
    if ( ! $existing_sub || $existing_sub_expired_since_more_than_two_days ) { 
        $db_subscription['start'] = wp_date( 'Y-m-d H:i:s' );
    }
    // data fine, mesi cumulativi - rinnovo abbonamento (resubscribe)
    if ( 'channel.subscription.message' === $event_type ) {
        $db_subscription['cumulative_months'] = $tw_subscription['cumulative_months'];
        $db_subscription['streak_months'] = $tw_subscription['streak_months'];
        
    }
    if ( ! $existing_sub || $existing_sub_expired ) { // sia se nuovo o esistente (non messaggio di rinnovo) scaduto - scadenza dopo un mese
        $db_subscription['end'] = wp_date( 'Y-m-d H:i:s', time() + 31 * DAY_IN_SECONDS );
    }

    // salvataggio sul database
    Database_Manager::save_subscription( $db_subscription, $existing_sub );
}