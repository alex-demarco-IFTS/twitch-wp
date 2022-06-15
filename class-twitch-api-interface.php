<?php
namespace IFQ\Twitch;
/**
 * Twitch API
 */

/*
 * funzioni di recupero credenziali
 */
// recupera il client ID dalle impostazioni
function retrieve_tw_client_id() {
    $tw_client_id = get_option( 'ifqtw_client_id' );
    if ( ! $tw_client_id || empty( $tw_client_id ) ) {
        throw new \Exception( "Client ID non definito nella configurazione del plugin." );
    }
    return $tw_client_id;
}
// recupera il client secret dalle impostazioni
function retrieve_tw_client_secret() {
    $tw_client_secret = get_option( 'ifqtw_client_secret' );
    if ( ! $tw_client_secret || empty( $tw_client_secret ) ) {
        throw new \Exception( "Client Secret non definito nella configurazione del plugin." );
    }
    return $tw_client_secret;
}
// recupera l'app access token dalle impostazioni
function retrieve_tw_app_access_token() {
    $tw_app_access_token = get_option( 'ifqtw_app_access_token' );
    if ( ! $tw_app_access_token || empty( $tw_app_access_token ) ) {
        throw new \Exception( "App Access Token non definito nella configurazione del plugin." );
    }
    return $tw_app_access_token;
}
// recupera l'user access token dagli user meta
function retrieve_tw_user_access_token() {
    $tw_user_access_token = get_user_meta( get_current_user_id(), 'ifqtw_user_access_token' )[0];
    if ( ! $tw_user_access_token || empty( $tw_user_access_token ) ) {
        throw new \Exception( "User Access Token non definito nella configurazione del plugin." );
    }
    return $tw_user_access_token;
}
// recupera l'id del canale IFQ dalle impostazioni
function retrieve_tw_broadcaster_id() {
    $tw_broadcaster_id = get_option( 'ifqtw_tw_broadcaster_id' );
    if ( ! $tw_broadcaster_id || empty( $tw_broadcaster_id ) ) {
        throw new \Exception( "ID canale IFQ non definito nella configurazione del plugin." );
    }
    return $tw_broadcaster_id;
}
// recupera le credenziali del client e restituisce un array pronto per il request header
function retrieve_client_credentials_request_header( $token_type = 'user' ) {
    $tw_client_id = retrieve_tw_client_id();
    if ( $token_type == 'user' ) {
        try {
            $tw_access_token = retrieve_tw_user_access_token();
        } catch ( \Exception $ex ) {
            $tw_access_token = retrieve_tw_app_access_token();
        }
    } elseif ( $token_type == 'app' ) {
        $tw_access_token = retrieve_tw_app_access_token();
    } else {
        throw new \Exception( "Token non definito." );
    }
    return array( 'Client-ID: '            . $tw_client_id,
                  'Authorization: Bearer ' . $tw_access_token );
}
// generic CURL request
function curl_request( $url, $method = 'GET', $header = array(), $body = array(), $return_code = false ) {
    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, $url );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    if ( $method == 'POST' ) {
        curl_setopt( $ch, CURLOPT_POST, 1 );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header ); 
    $response = curl_exec( $ch );
    if ( $return_code ) {
        $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    }
    curl_close( $ch );
    return $return_code ?
            array( 'response' => json_decode( $response, true ),
                   'code'     => $http_code) :
            json_decode( $response, true );
}

class Twitch_Api_Interface {
    /*
    * Authorization code grant flow
    */
    /* Authorization code grant flow - uri per autorizzare l'utente WP con TW
    * Random state string: necessary for the OAuth user-app authorization request.
    * Must be unique for each OAuth request.
    * If this string doesn’t match the state string that you passed, ignore the response.
    * The server returns this string to you in your redirect URI (see the state parameter in the fragment portion of the URI).
    */
    public static function oauth_retrieve_tw_user_login_uri() {
        $oauth_uri = 'https://id.twitch.tv/oauth2/authorize';
        $random_unique_temp_state_string = generate_random_string( 32 );

        $oauth_params = array(
                'response_type' => 'code',
                'client_id'     => retrieve_tw_client_id(),
                'redirect_uri'  => 'http://localhost/wordpress/twitch-oauth-callback',
                'scope'         => 'user:read:email ' .
                                   'user:read:subscriptions ' .
                                   'channel:read:subscriptions',
                'state'         => $random_unique_temp_state_string
        );
        $oauth_params_query = http_build_query( $oauth_params );
        update_post_meta( 532, 'ifqtw_user_auth_temp_state_string', $random_unique_temp_state_string ); // salva stringa di stato temporanea nei post meta
        return $oauth_uri . "?" . $oauth_params_query;
    }

    // Authorization code grant flow - invio auth code -> richiesta token
    public static function oauth_user_token_request( $user_auth_code ) {
        $oauth_uri = 'https://id.twitch.tv/oauth2/token';

        $oauth_request_body = array(
                'client_id'     => retrieve_tw_client_id(),
                'client_secret' => retrieve_tw_client_secret(),
                'code'          => $user_auth_code,
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => 'http://localhost/wordpress/twitch-oauth-callback'
        );
        $response = curl_request(
                $oauth_uri,
                'POST',
                array( 'Content-Type: application/x-www-form-urlencoded' ),
                http_build_query( $oauth_request_body )
        );
        return $response;
    }

    // revoca access tokens utente
    public static function oauth_revoke_user_access() {
        $oauth_uri = 'https://id.twitch.tv/oauth2/revoke';

        $oauth_request_body = array(
                'client_id' => retrieve_tw_client_id(),
                'token'     => retrieve_tw_user_access_token()
        );
        $response = curl_request(
                $oauth_uri,
                'POST',
                array( 'Content-Type: application/x-www-form-urlencoded' ),
                http_build_query( $oauth_request_body ),
                true
        );
        return $response['code'] == 200;
    }


    // recupera le informazioni di un account utente Twitch
    public static function get_tw_user_info( $tw_user = '', $mock = false ) {
        $tw_api_url = $mock ? 'http://localhost:8080/mock' : 'https://api.twitch.tv/helix';
        $tw_api_endpoint = '/users';
        $query = ( ! empty( $tw_user ) ) ? '?login=' . $tw_user : '';

        $response = curl_request(
                $tw_api_url . $tw_api_endpoint . $query,
                'GET',
                retrieve_client_credentials_request_header()
        );
        return $response['data'][0];
    }

    // controlla se un utente Twitch è abbonato ad un canale
    public static function check_tw_active_user_subscription( $tw_user_id, $tw_broadcaster_id, $mock = false ) {
        $tw_api_url = $mock ? 'http://localhost:8080/mock' : 'https://api.twitch.tv/helix';
        $tw_api_endpoint = '/subscriptions/user';
        $query = '?broadcaster_id=' . $tw_broadcaster_id . '&user_id=' . $tw_user_id;

        $response = curl_request(
                $tw_api_url . $tw_api_endpoint . $query,
                'GET',
                retrieve_client_credentials_request_header()
        );
        return empty( $response['data'] ) ? false : $response['data'][0];
    }

    // EventSub - iscrizione all'evento 'channel.subscribe' per il canale IFQ per abilitare la ricezione delle notifiche di abbonamento utenti
    public static function eventsub_subscribe_to_channel_event( $event_type, $mock = false ) {
        $tw_api_url = $mock ? 'http://localhost:8080/mock' : 'https://api.twitch.tv/helix';
        $tw_api_endpoint = '/eventsub/subscriptions';

        $request_header = retrieve_client_credentials_request_header( 'app' );
        $request_header[2] = 'Content-Type: application/json';
        $secret = generate_random_string( 64 );
        update_post_meta( 554, 'ifqtw_eventsub_temp_secret_string', $secret ); // salva stringa segreto temporanea nei post meta
        
        $request_body = array(  
                'type'      => $event_type,
                'version'   => '1',
                'condition' => array(
                                'broadcaster_user_id' => retrieve_tw_broadcaster_id() ),
                'transport' => array(
                                'method'   => 'webhook',
                                'callback' => 'http://localhost/wordpress/twitch-eventsub',
                                'secret'   => $secret )
        );
        $response = curl_request(
                $tw_api_url . $tw_api_endpoint,
                'POST',
                $request_header,
                json_encode( $request_body )
        );
        return $response;
    }
}