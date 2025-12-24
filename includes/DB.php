<?php
/**
 * Gestion de la base de données des transactions Djomy
 * 
 * @package WooCommerce_Gateway_Djomy
 */

namespace Djomy;

defined('ABSPATH') || exit;

class DB {
    
    /**
     * Nom de la table
     * @var string
     */
    private $table_name;
    
    /**
     * Constructeur
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'djomy_transactions';
    }
    
    /**
     * Créer la table des transactions
     */
    public function create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            transaction_id varchar(100) DEFAULT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(10) NOT NULL,
            status varchar(50) NOT NULL,
            payment_method varchar(50) DEFAULT NULL,
            fees decimal(10,2) DEFAULT 0,
            customer_name varchar(255) DEFAULT NULL,
            customer_email varchar(255) DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY transaction_id (transaction_id),
            KEY status (status)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    
    /**
     * Insérer une nouvelle transaction
     * 
     * @param array $data Données de la transaction
     * @return int|false
     */
    public function insert_transaction($data) {
        global $wpdb;

        $defaults = array(
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );

        $data = wp_parse_args($data, $defaults);

        return $wpdb->insert($this->table_name, $data);
    }
    
    /**
     * Mettre à jour une transaction
     * 
     * @param int   $order_id ID de la commande
     * @param array $data     Données à mettre à jour
     * @return int|false
     */
    public function update_transaction($order_id, $data) {
        global $wpdb;

        if (!isset($data['updated_at'])) {
            $data['updated_at'] = current_time('mysql');
        }

        return $wpdb->update(
            $this->table_name,
            $data,
            array('order_id' => $order_id)
        );
    }
    
    /**
     * Récupérer une transaction par order_id
     * 
     * @param int $order_id ID de la commande
     * @return object|null
     */
    public function get_transaction($order_id) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table_name} WHERE order_id = %d", $order_id)
        );
    }
    
    /**
     * Récupérer toutes les transactions
     * 
     * @param int $limit  Nombre d'éléments
     * @param int $offset Offset
     * @return array
     */
    public function get_transactions($limit = 20, $offset = 0) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
    }
    
    /**
     * Compter le nombre total de transactions
     * 
     * @return int
     */
    public function count_transactions() {
        global $wpdb;

        return $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
    }
    
    /**
     * Rechercher des transactions
     * 
     * @param string $search_term Terme de recherche
     * @return array
     */
    public function search_transactions($search_term) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} 
                 WHERE order_id LIKE %s 
                 OR transaction_id LIKE %s 
                 OR customer_name LIKE %s 
                 OR customer_email LIKE %s 
                 ORDER BY created_at DESC",
                '%' . $wpdb->esc_like($search_term) . '%',
                '%' . $wpdb->esc_like($search_term) . '%',
                '%' . $wpdb->esc_like($search_term) . '%',
                '%' . $wpdb->esc_like($search_term) . '%'
            )
        );
    }
    
    /**
     * Récupérer les transactions par statut
     * 
     * @param string $status Statut
     * @param int    $limit  Limite
     * @param int    $offset Offset
     * @return array
     */
    public function get_transactions_by_status($status, $limit = 20, $offset = 0) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} 
                 WHERE status = %s 
                 ORDER BY created_at DESC 
                 LIMIT %d OFFSET %d",
                $status,
                $limit,
                $offset
            )
        );
    }
    
    /**
     * Récupérer les transactions avec pagination et recherche
     * 
     * @param int    $per_page    Nombre par page
     * @param int    $page_number Numéro de page
     * @param string $search_term Terme de recherche
     * @return array
     */
    public function get_transactions_with_pagination($per_page = 20, $page_number = 1, $search_term = '') {
        $offset = ($page_number - 1) * $per_page;

        if (!empty($search_term)) {
            return $this->search_transactions($search_term);
        }
        
        return $this->get_transactions($per_page, $offset);
    }
    
    /**
     * Compter les transactions avec recherche
     * 
     * @param string $search_term Terme de recherche
     * @return int
     */
    public function count_transactions_with_search($search_term = '') {
        global $wpdb;

        if (!empty($search_term)) {
            return $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$this->table_name} 
                     WHERE order_id LIKE %s 
                     OR transaction_id LIKE %s 
                     OR customer_name LIKE %s 
                     OR customer_email LIKE %s",
                    '%' . $wpdb->esc_like($search_term) . '%',
                    '%' . $wpdb->esc_like($search_term) . '%',
                    '%' . $wpdb->esc_like($search_term) . '%',
                    '%' . $wpdb->esc_like($search_term) . '%'
                )
            );
        }
        
        return $this->count_transactions();
    }
}