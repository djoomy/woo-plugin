<?php
/**
 * Liste des transactions Djomy
 * 
 * @package WooCommerce_Gateway_Djomy
 */

namespace Djomy;

defined('ABSPATH') || exit;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class TransactionsList extends \WP_List_Table {
    
    /**
     * Instance de la base de données
     * @var DB
     */
    private $db;
    
    /**
     * Constructeur
     */
    public function __construct() {
        parent::__construct([
            'singular' => __('Transaction', 'gateway-djomy'),
            'plural'   => __('Transactions', 'gateway-djomy'),
            'ajax'     => false
        ]);

        $this->db = new DB();
    }
    
    /**
     * Préparer les éléments à afficher
     */
    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);

        $per_page = $this->get_items_per_page('transactions_per_page', 20);
        $current_page = $this->get_pagenum();

        // Gestion de la recherche
        $search_term = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';

        // Utiliser les nouvelles méthodes avec pagination et recherche
        $this->items = $this->db->get_transactions_with_pagination($per_page, $current_page, $search_term);
        $total_items = $this->db->count_transactions_with_search($search_term);

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }

    /**
     * Définir les colonnes
     */
    public function get_columns() {
        return [
            'cb'             => '<input type="checkbox" />',
            'order_id'       => __('Commande', 'gateway-djomy'),
            'transaction_id' => __('ID Transaction', 'gateway-djomy'),
            'amount'         => __('Montant', 'gateway-djomy'),
            'status'         => __('Statut', 'gateway-djomy'),
            'payment_method' => __('Méthode', 'gateway-djomy'),
            'customer_name'  => __('Client', 'gateway-djomy'),
            'created_at'     => __('Date', 'gateway-djomy'),
            'actions'        => __('Actions', 'gateway-djomy')
        ];
    }

    /**
     * Colonnes cachées
     */
    public function get_hidden_columns() {
        return [];
    }

    /**
     * Colonnes triables
     */
    public function get_sortable_columns() {
        return [
            'order_id'   => ['order_id', false],
            'created_at' => ['created_at', true],
            'amount'     => ['amount', false]
        ];
    }

    /**
     * Colonne par défaut
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'order_id':
                $order = wc_get_order($item->order_id);
                if ($order) {
                    return sprintf(
                        '<a href="%s">#%s</a>',
                        get_edit_post_link($item->order_id),
                        $item->order_id
                    );
                }
                return '#' . $item->order_id;

            case 'transaction_id':
                return $item->transaction_id ?: __('N/A', 'gateway-djomy');

            case 'amount':
                return wc_price($item->amount, ['currency' => $item->currency]);

            case 'status':
                return $this->get_status_badge($item->status);

            case 'payment_method':
                return $item->payment_method ?: 'Djomy';

            case 'customer_name':
                return $item->customer_name ?: __('N/A', 'gateway-djomy');

            case 'created_at':
                return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->created_at));

            case 'actions':
                return $this->get_action_buttons($item);

            default:
                return print_r($item, true);
        }
    }

    /**
     * Colonne checkbox
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="transaction[]" value="%s" />',
            $item->id
        );
    }

    /**
     * Badge de statut
     */
    private function get_status_badge($status) {
        $status_classes = [
            'pending'   => 'warning',
            'success'   => 'success',
            'completed' => 'success',
            'failed'    => 'error',
            'cancelled' => 'error'
        ];

        $class = isset($status_classes[$status]) ? $status_classes[$status] : 'secondary';
        $label = ucfirst($status);

        return sprintf(
            '<span class="woocommerce-order-status__indicator -%s">%s</span>',
            $class,
            $label
        );
    }

    /**
     * Boutons d'action
     */
    private function get_action_buttons($item) {
        $actions = [];

        // Voir la commande
        $order = wc_get_order($item->order_id);
        if ($order) {
            $actions['view'] = sprintf(
                '<a href="%s" title="%s">%s</a>',
                get_edit_post_link($item->order_id),
                __('Voir la commande', 'gateway-djomy'),
                __('Voir', 'gateway-djomy')
            );
        }

        // Vérifier à nouveau le statut
        $actions['refresh'] = sprintf(
            '<a href="%s" title="%s">%s</a>',
            wp_nonce_url(
                add_query_arg([
                    'action' => 'refresh_djomy_status',
                    'order_id' => $item->order_id, 
                    'transaction_id' => $item->transaction_id
                ], admin_url('admin.php')),
                'refresh_djomy_status'
            ),
            __('Vérifier le statut', 'gateway-djomy'),
            __('Actualiser', 'gateway-djomy')
        );

        return implode(' | ', $actions);
    }

    /**
     * Options bulk
     */
    public function get_bulk_actions() {
        return [
            'refresh_status' => __('Vérifier le statut', 'gateway-djomy'),
            'export_csv'     => __('Exporter en CSV', 'gateway-djomy')
        ];
    }

    /**
     * Traiter les actions bulk
     */
    public function process_bulk_action() {
        if ('refresh_status' === $this->current_action()) {
            // Traiter la vérification du statut
            if (isset($_GET['transaction'])) {
                $transaction_ids = array_map('absint', $_GET['transaction']);
                foreach ($transaction_ids as $transaction_id) {
                    // Implémenter la logique de rafraîchissement du statut
                }
            }
        } elseif ('export_csv' === $this->current_action()) {
            // Traiter l'export CSV
            if (isset($_GET['transaction'])) {
                $transaction_ids = array_map('absint', $_GET['transaction']);
                $this->export_transactions_csv($transaction_ids);
            }
        }
    }

    /**
     * Exporter les transactions en CSV
     */
    private function export_transactions_csv($transaction_ids) {
        // Implémenter l'export CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="djomy-transactions-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Order ID', 'Transaction ID', 'Amount', 'Currency', 'Status', 'Customer Name', 'Date']);
        
        foreach ($transaction_ids as $transaction_id) {
            $transaction = $this->db->get_transaction($transaction_id);
            if ($transaction) {
                fputcsv($output, [
                    $transaction->order_id,
                    $transaction->transaction_id,
                    $transaction->amount,
                    $transaction->currency,
                    $transaction->status,
                    $transaction->customer_name,
                    $transaction->created_at
                ]);
            }
        }
        
        fclose($output);
        exit;
    }

    /**
     * Afficher la recherche
     */
    public function search_box($text, $input_id) {
        ?>
        <p class="search-box">
            <label class="screen-reader-text" for="<?php echo esc_attr($input_id); ?>"><?php echo esc_html($text); ?>:</label>
            <input type="search" id="<?php echo esc_attr($input_id); ?>" name="s" value="<?php echo esc_attr(isset($_REQUEST['s']) ? $_REQUEST['s'] : ''); ?>" />
            <?php submit_button($text, 'button', '', false, array('id' => 'search-submit')); ?>
        </p>
        <?php
    }

    /**
     * Aucun élément
     */
    public function no_items() {
        esc_html_e('Aucune transaction Djomy trouvée.', 'gateway-djomy');
    }

    /**
     * Extra tablenav
     */
    public function extra_tablenav($which) {
        if ($which === 'top') {
            echo '<div class="alignleft actions">';
            $this->search_box(__('Rechercher', 'gateway-djomy'), 'search');
            echo '</div>';
        }
    }
}