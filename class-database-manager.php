<?php
namespace IFQ\Twitch;
/**
 * gestore database
 */
class Database_Manager {
    // versione database
    static $ifqtw_db_version = '0.2';

    // creazione / aggiornamento tabella abbonamenti sul db
    public static function db_install() {
        global $wpdb;
        $installed_ver = get_option( "ifqtw_db_version" );
        $charset_collate = $wpdb->get_charset_collate();
        if ( $installed_ver != static::$ifqtw_db_version ) {
            $table_name = $wpdb->prefix . "ifqtw_subscriptions";
            $subscriptions_table_create_sql = 
                    "CREATE TABLE {$table_name} (
                        tw_user_id        INTEGER NOT NULL,
                        wp_user_id        MEDIUMINT(9) NULL,
                        plan              VARCHAR(10) NOT NULL,
                        start             TIMESTAMP NULL,
                        end               TIMESTAMP NULL,
                        cumulative_months MEDIUMINT(9) NULL,
                        streak_months     MEDIUMINT(9) NULL,
                        PRIMARY KEY  (tw_user_id)
                    );";
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $subscriptions_table_create_sql );
            update_option( 'ifqtw_db_version', static::$ifqtw_db_version );
        }
    }

    // funzione di ricerca sul database
    public static function search_subscriptions( $search_params = array(), $cols = array(), $limit = null, $offset = null ) {
        global $wpdb;
        if ( is_array( $cols ) && ! empty( $cols ) ) {
            $cols_string = implode( ",", $cols );
        } elseif ( empty( $cols ) ) {
            $cols_string = "*";
        }
        $where = array();
        if ( is_array( $search_params ) && ! empty( $search_params ) ) {
            foreach ( $search_params as $k => $v ) {
                if ( is_array( $v ) ) {
                    $where[] = $k . " " . $v['compare'] . " " . ( is_int( $v['value'] ) ? $v['value'] : "'" . $v['value'] . "'" );
                } elseif ( is_int( $v ) ) {
                    $where[] = $k . " = " . $v;
                } else {
                    $where[] = $k . " = '" . $v . "'";
                }
            }
        }
        if ( ! empty( $where ) ) {
            $where = implode( " AND ", $where );
            $where = "WHERE " . $where;
        } else {
            $where = "";
        }
        $tablename = $wpdb->prefix . "ifqtw_subscriptions";
        $query = "SELECT {$cols_string} FROM {$tablename} {$where}";
        $query .= " ORDER BY tw_user_id desc";
        if ( ! empty( $limit ) && is_numeric( $limit ) ) {
            $query .= " LIMIT " . $limit;
        }
        if ( ! empty( $offset ) && is_numeric( $offset ) ) {
            $query .= " OFFSET " . $offset;
        }
        $results = $wpdb->get_results( $query, 'ARRAY_A' ); // possibili risultati: singola variabile (array con un array di dimensione 1), singola riga (array con un array di dimensione 9), righe multiple (array con più array di dimensione 8), colonne multiple (array con più array di dimensione 1)
        if ( empty( $results ) ) {
            return null;
        }
        if ( ! empty( $cols ) ) {
            return ( $limit == 1 ) ? array_shift( $results ) : $results;
        }
        if ( $limit == 1 ) {
            return $results[0]; // singola riga
        }
        return $results; // più righe
    }

    // funzione di salvataggio abbonamento sul database
    public static function save_subscription( $subscription = array(), $existing = false ) {
        global $wpdb;
        $tablename = $wpdb->prefix . "ifqtw_subscriptions";
        $format = array( '%d', '%d', '%s', '%s', '%s', '%d', '%d' );
        if ( $existing ) {
            // update db existing
            $wpdb->update(
                    $tablename,
                    $subscription,
                    array( 'tw_user_id' => $subscription['tw_user_id'] ),
                    $format,
                    array( '%d' )
            );
        } else {
            // insert db new
            $wpdb->insert(
                    $tablename, 
                    $subscription, 
                    $format
            );
        }
    }

    // funzione richiamata alla ricezione di un evento channel.subscription.end - imposta la data di fine con quella corrente
    public static function subscription_expiration( $tw_user_id ) {
        global $wpdb;
        $tablename = $wpdb->prefix . 'ifqtw_subscriptions';
        $wpdb->update(
                $tablename,
                array( 'end'        => wp_date( 'Y-m-d H:i:s' ) ),
                array( 'tw_user_id' => $tw_user_id ),
                array( '%s' ),
                array( '%d' )
        );
    }

    // funzione che ritorna il numero di abbonamenti presenti nella tabella del database
    public static function total_subscriptions_count() {
        global $wpdb;
        $tablename = $wpdb->prefix . 'ifqtw_subscriptions';
        return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tablename}" ) );
    }
}