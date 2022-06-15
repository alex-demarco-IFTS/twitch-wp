<?php
namespace IFQ\Twitch;
/**
 * @package IFQ Twitch Connect
 * @version 0.1
 */
/*
  Plugin Name: IFQ Twitch Connect
  Plugin URI: http://www.ilfattoquotidiano.it
  Description: Plugin per la piattaforma de Il Fatto Quotidiano. Consente l'integrazione con Twitch. Destinato ad uso interno all'azienda.
  Version: 0.1
  Author: Alessandro De Marco
  Author URI: https://www.seif-spa.it/
*/
// DEBUG
error_reporting(E_ALL); // to set the level of errors to log, E_ALL sets all warning, info , error
ini_set("log_errors", true);
ini_set("error_log", "C:\xampp\htdocs\php-errors.log"); // send error log to log file specified here.
//

require_once 'index.php';
require_once 'class-database-manager.php';
require_once 'user-auth.php';
require_once 'plugin-settings.php';
require_once 'class-twitch-api-interface.php';
require_once 'eventsub-listener.php';
require_once 'class-livestream-player-widget.php';
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once dirname( __FILE__ ) . '/wpcli-subscription-checker.php';
}

// utilità - restituisce il numero di giorni trascorsi fra due date
function days_passed( $date_string_1 = '', $date_string_2 = '' ) {
    $seconds_passed = strtotime( $date_string_2 ) - strtotime( $date_string_1 );
    return $seconds_passed / 86400;
}

// utilità - genera una stringa di caratteri casuali della lunghezza desiderata (OAuth state string e EventSub secret)
function generate_random_string( $length = 1 ) {
    $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $pieces = [];
    $max = mb_strlen( $keyspace, '8bit' ) - 1;
    for ( $i = 0; $i < $length; ++$i ) {
        $pieces[] = $keyspace[ random_int( 0, $max ) ];
    }
    return implode( '', $pieces );
}

// aggiunge top-level menu funzionalità plugin
function top_menu_page() {
    add_menu_page(
        'IFQ Twitch Connect',
        'IFQ Twitch Connect',
        'manage_options',
        'ifqtw_menu'
    );
}
add_action( 'admin_menu', 'IFQ\Twitch\top_menu_page' );

// plugin basic hooks
function activate() {
    Database_Manager::db_install();
}
register_activation_hook( __FILE__, 'IFQ\Twitch\activate' );

function deactivate() {
}
register_deactivation_hook( __FILE__, 'IFQ\Twitch\deactivate' );