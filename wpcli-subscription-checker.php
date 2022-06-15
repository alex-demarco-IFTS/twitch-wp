<?php
namespace IFQ\Twitch;
/*
 * script per il controllo periodico degli abbonamenti di utenti WP scaduti da meno di 7 giorni
 */
if ( php_sapi_name() == 'cli' ) {
    class Subscription_Checker_WPCLI  {

        static protected $_instance = null;

        public static function instance() {
            if ( is_null( static::$_instance ) ) {
                static::$_instance = new static();
            }
            return static::$_instance;
        }

        public function __construct() {
            add_action( 'init', array( __CLASS__, 'cli_init' ) );
        }

        public static function cli_init() {
            WP_CLI::add_command( 'ifqtw check-active-subs', array( __CLASS__, 'IFQ\Twitch\check_wordpress_users_expired_subscriptions' ) );
        }

        // WHERE wp_user_id IS NOT NULL AND end < <today> AND 7 < <today> - end => controllo periodico check_tw_active_user_subscription() con script WPCLI
        public static function check_wordpress_users_expired_subscriptions() {
            $search_params = array(
                    'wp_user_id' => array(
                            'value'   => 'NULL',
                            'compare' => 'IS NOT' ),
                    'end' => array(                               // scaduto da meno di 7 giorni
                            'value'   => wp_date( 'Y-m-d H:i:s' ),
                            'compare' => '<' ),
                    '7' => array(                                 // scaduto da meno di 7 giorni
                            'value'   => wp_date( 'Y-m-d H:i:s' ) . ' - end',
                            'compare' => '<' )
            );
            $subscriptions_to_check = Database_Manager::search_subscriptions( $search_params );
            $tw_broadcaster_id = retrieve_tw_broadcaster_id();
            foreach ( $subscriptions_to_check as $subscription ) {
                $tw_user_id = $subscription['tw_user_id'];
                $subscribed = Twitch_Api_Interface::check_tw_active_user_subscription( $tw_user_id, $tw_broadcaster_id );
                if ( $subscribed ) {
                    $subscription['end'] = wp_date( 'Y-m-d H:i:s', time() + 31 * DAY_IN_SECONDS ); // aggiunge 1 mese
                    Database_Manager::save_subscription( $subscription, true );
                }
            }
            WP_CLI::log( "Done." );
        }
    }

    Subscription_Checker_WPCLI::instance();
}