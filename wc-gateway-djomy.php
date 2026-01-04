<?php
/**
 * Plugin Name: Gateway Djomy
 * Plugin URI: https://djomy.africa
 * Description: Passerelle de paiement Djomy pour WooCommerce
 * Version: 1.1.0
 * Author: Ibrahima Sory Diallo
 * Author URI: https://gts224.com
 * Text Domain: gateway-djomy
 * Requires at least: 5.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 */


// Charger l'autoloader
require_once __DIR__ . '/vendor/autoload.php';

use Djomy\DB;
use Djomy\Gateway;

defined('ABSPATH') || exit;

// Définition des constantes
define('DJOMY_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('DJOMY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DJOMY_VERSION', '1.6');

// Déclaration de compatibilité HPOS
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Initialiser la gateway
add_action('plugins_loaded', 'djomy_init_gateway_class', 0);

/**
 * Initialiser la classe de gateway
 */
function djomy_init_gateway_class() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'djomy_woocommerce_missing_notice');
        return;
    }
}

add_action('admin_menu', 'djomy_register_admin_submenu', 50);
function djomy_register_admin_submenu() {
    if (!current_user_can('manage_woocommerce')) {
        return;
    }

    // Essayer de récupérer l'instance gérée par WooCommerce
    $gateway = djomy_get_gateway_instance();

    // Si l'instance existe, l'utiliser comme callback
    if ($gateway && is_callable([$gateway, 'display_transactions_page'])) {
        add_submenu_page(
            'woocommerce',
            esc_html__('Transactions Djomy', 'gateway-djomy'),
            esc_html__('Transactions Djomy', 'gateway-djomy'),
            'manage_woocommerce',
            'djomy-transactions',
            array($gateway, 'display_transactions_page')
        );
        return;
    }

    // Fallback : utiliser une callback wrapper qui instancie temporairement la Gateway
    add_submenu_page(
        'woocommerce',
        esc_html__('Transactions Djomy', 'gateway-djomy'),
        esc_html__('Transactions Djomy', 'gateway-djomy'),
        'manage_woocommerce',
        'djomy-transactions',
        function() {
            // Instancier temporairement si besoin (sans réenregistrer tous les hooks)
            $gw = null;
            if (class_exists('\\Djomy\\Gateway')) {
                $gw = new \Djomy\Gateway();
            }

            if ($gw && is_callable([$gw, 'display_transactions_page'])) {
                return $gw->display_transactions_page();
            }

            echo '<div class="notice notice-error"><p>' . esc_html__('Module Djomy non disponible.', 'gateway-djomy') . '</p></div>';
        }
    );
}

/**
 * Retourne l'instance de la gateway gérée par WooCommerce si elle existe,
 * sinon null.
 *
 * @return \Djomy\Gateway|null
 */
function djomy_get_gateway_instance() {
    if (!function_exists('WC')) {
        return null;
    }

    // Récupère toutes les gateways instanciées par WooCommerce
    $payment_gateways = WC()->payment_gateways();
    if (!is_object($payment_gateways)) {
        return null;
    }

    $all = $payment_gateways->payment_gateways();
    if (!is_array($all)) {
        return null;
    }

    // Cherche une instance de notre classe
    foreach ($all as $gateway) {
        if ($gateway instanceof \Djomy\Gateway) {
            return $gateway;
        }
    }

    return null;
}


/**
 * Ajouter la gateway à WooCommerce
 */
add_filter('woocommerce_payment_gateways', 'djomy_add_gateway_class');

/**
 * Ajouter la classe de gateway
 */
function djomy_add_gateway_class($gateways) {
    $gateways[] = 'Djomy\Gateway';
    return $gateways;
}

/**
 * Afficher un message si WooCommerce n'est pas actif
 */
function djomy_woocommerce_missing_notice() {
    $message = sprintf(
        esc_html__('La passerelle de paiement Djomy nécessite WooCommerce. %s', 'gateway-djomy'),
        '<a href="https://woocommerce.com/" target="_blank">' . esc_html__('Installer WooCommerce', 'gateway-djomy') . '</a>'
    );
    echo '<div class="error"><p>' . wp_kses($message, array('a' => array('href' => array(), 'target' => array()))) . '</p></div>';
}

/**
 * Activer le plugin
 */
register_activation_hook(__FILE__, 'djomy_activate');

/**
 * Fonction d'activation
 */
function djomy_activate() {
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('Ce plugin nécessite WooCommerce.', 'gateway-djomy'),
            esc_html__('Dépendance manquante', 'gateway-djomy'),
            array('back_link' => true)
        );
    }

    // Créer la table des transactions
    $db = new DB();
    $db->create_table();
}

/**
 * Désactiver le plugin
 */
register_deactivation_hook(__FILE__, 'djomy_deactivate');

/**
 * Fonction de désactivation
 */
function djomy_deactivate() {
    // Nettoyage optionnel
}


// Hook pour gérer admin.php?action=refresh_djomy_status
add_action('admin_action_refresh_djomy_status', 'djomy_handle_refresh_status');

//hook pour les messages d'admin
add_action('admin_notices', 'djomy_display_admin_notices');

function djomy_handle_refresh_status() {
    // Vérifications de sécurité
    if (!djomy_verify_security()) {
        return;
    }

    // Récupérer l'ID de commande
    $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : 0;
    if (!$order_id) {
        djomy_redirect_back();
    }

    // Récupérer la commande
    $order = wc_get_order($order_id);
    if (!$order) {
        djomy_redirect_back();
    }

    // Obtenir l'ID de transaction
    $transaction_id = djomy_get_transaction_id($order, $order_id);

    // Vérifier le statut de la transaction
    $result = djomy_check_transaction_status($transaction_id);
    wc_get_logger()->info($result, array('source' => 'djomy'));

    // Traiter le résultat et mettre à jour la commande
    $status_info = djomy_process_api_result($result, $order, $transaction_id);

    // Stocker le message dans une variable de session transitoire
    set_transient('djomy_admin_notice_' . get_current_user_id(), [
        'message' => $status_info['message'],
        'type' => $status_info['success'] ? 'success' : 'error'
    ], 30);

    // Rediriger
    $redirect_to = wp_get_referer() ?: admin_url('admin.php?page=wc-djomy-transactions');
    wp_safe_redirect($redirect_to);
    exit;
}

// ========== FONCTIONS AUXILIAIRES ==========



function djomy_verify_security() {
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(wp_unslash($_GET['_wpnonce']), 'refresh_djomy_status')) {
        wp_die(esc_html__('Nonce invalide.', 'gateway-djomy'));
    }

    if (!current_user_can('manage_woocommerce')) {
        wp_die(esc_html__('Autorisation refusée.', 'gateway-djomy'));
    }

    return true;
}

function djomy_get_transaction_id($order, $order_id) {
    // Essayer d'abord le transaction_id standard
    $transaction_id = $order->get_transaction_id();
    
    // Essayer le meta personnalisé
    if (empty($transaction_id)) {
        $transaction_id = $order->get_meta('_djomy_transaction_id', true);
    }

    // Chercher dans la base de données custom
    if (empty($transaction_id) && class_exists('Djomy\\DB')) {
        try {
            $db = new DB();
            $tx = $db->get_transaction($order_id);
            $transaction_id = $tx['transaction_id'] ?? '';
        } catch (\Throwable $e) {
            // Silence
        }
    }

    return $transaction_id;
}

function djomy_check_transaction_status($transaction_id) {
    if (!class_exists('\\Djomy\\Gateway')) {
        return [
            'error' => true,
            'message' => __('Passerelle non disponible.', 'gateway-djomy')
        ];
    }

    // 1) Essayer de récupérer l'instance fournie par WooCommerce
    $gateway = djomy_get_gateway_instance();

    // 2) Si absente, instancier en dernier recours (try/catch)
    if (!$gateway) {
        try {
            $gateway = new \Djomy\Gateway();
        } catch (\Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                wc_get_logger()->error('djomy: impossible d\'instancier Gateway: ' . $e->getMessage(), ['source' => 'djomy']);
            }

            return [
                'error' => true,
                'message' => __('Impossible d\'initialiser la passerelle.', 'gateway-djomy')
            ];
        }
    }

    // Fallback: appel API direct
    return djomy_call_api($gateway, $transaction_id);
}

function djomy_call_api($gateway, $transaction_id) {
    if (empty($gateway->endpoint_check)) {
        return [
            'error' => true,
            'message' => __('Endpoint API non configuré.', 'gateway-djomy')
        ];
    }

    $gateway->generate_signature();
    $access_token = $gateway->generer_token_acces($gateway->base_url  .'/v1/auth');

    $endpoint = rtrim($gateway->endpoint_check, '/') . '/' . rawurlencode($transaction_id). '/' . 'status';
    $args = [
        'headers' => [
            'Content-Type'      => 'application/json',
            'X-API-KEY'         => $gateway->x_api_key ?? '',
            'Authorization'     => 'Bearer ' . ($access_token ?? ''),
            'X-PARTNER-DOMAINE' => $gateway->partner_domaine
        ],
        'timeout' => 20,
    ];

    $response = wp_remote_get($endpoint, $args);

    

    if (is_wp_error($response)) {
        
        if ($gateway->debug) {
            wc_get_logger()->error('Erreur connexion API Djomy: ' . $response->get_error_message(), array('source' => 'djomy'));
        }

        return [
            'error' => true,
            'message' => $response->get_error_message()
        ];
    }

    $send_response = [
        'http_code' => wp_remote_retrieve_response_code($response),
        'body' => json_decode(wp_remote_retrieve_body($response), true),
        'debug' => $gateway->debug
    ];

    

    // Traitement de la réponse
    $code_statut = $send_response['http_code'];
    $corps_reponse = $send_response['body'];
    $donnees_reponse = $corps_reponse;

    // Logging pour le débogage
    if ($gateway->debug) {
        $message_log = 'Réponse API Djomy: Code ' . $code_statut . ' - ' . print_r($donnees_reponse, true);
        wc_get_logger()->info($message_log, array('source' => 'djomy'));
    }


    return $send_response;
}

function djomy_process_api_result($result, $order, $transaction_id) {
    $message = '';
    $success = false;

    if (isset($result['error']) && $result['error']) {
        $message = $result['message'] ?? __('Erreur lors de la vérification', 'gateway-djomy');
        return compact('message', 'success');
    }

    // Extraire le statut de la réponse
    $status = djomy_extract_status($result);

    if (!$status) {
        $message = __('Impossible d\'extraire le statut de la réponse API.', 'gateway-djomy');
        return compact('message', 'success');
    }

    // Mettre à jour la commande selon le statut
    $update_result = djomy_update_order_status($order, $status, $transaction_id);
    
    // Mettre à jour la base de données custom
    djomy_update_custom_db($order->get_id(), $transaction_id, $update_result['new_status']);

    return [
        'message' => $update_result['message'],
        'success' => $update_result['success']
    ];
}

function djomy_extract_status($result) {
    if (isset($result['status'])) {
        return strtolower($result['status']);
    }

    if (!isset($result['body']) || !isset($result['http_code'])) {
        return null;
    }

    $body = $result['body'];
    
    if (isset($body['data']['status'])) {
        return strtolower($body['data']['status']);
    }
    
    if (isset($body['data']['paymentStatus'])) {
        return strtolower($body['data']['paymentStatus']);
    }

    return null;
}

function djomy_update_order_status($order, $status, $transaction_id) {
    $status_map = [
        'success' => ['action' => 'complete', 'wc_status' => 'success', 'message' => 'Paiement confirmé via Djomy (vérification manuelle).'],
        'completed' => ['action' => 'complete', 'wc_status' => 'completed', 'message' => 'Paiement confirmé via Djomy (vérification manuelle).'],
        'paid' => ['action' => 'complete', 'wc_status' => 'success', 'message' => 'Paiement confirmé via Djomy (vérification manuelle).'],
        'pending' => ['action' => 'hold', 'wc_status' => 'on-hold', 'message' => 'Paiement en attente (Djomy).'],
        'created' => ['action' => 'hold', 'wc_status' => 'on-hold', 'message' => 'Paiement en attente (Djomy).'],
        'processing' => ['action' => 'hold', 'wc_status' => 'on-hold', 'message' => 'Paiement en attente (Djomy).'],
        'failed' => ['action' => 'fail', 'wc_status' => 'failed', 'message' => 'Paiement échoué ou annulé (Djomy).'],
        'cancelled' => ['action' => 'fail', 'wc_status' => 'failed', 'message' => 'Paiement échoué ou annulé (Djomy).'],
        'refused' => ['action' => 'fail', 'wc_status' => 'failed', 'message' => 'Paiement échoué ou annulé (Djomy).'],
    ];

    if (!isset($status_map[$status])) {
        return [
            'success' => false,
            'new_status' => null,
            'message' => __('Statut inconnu retourné par l\'API.', 'gateway-djomy')
        ];
    }

    $config = $status_map[$status];
    $success = false;

    switch ($config['action']) {
        case 'complete':
            $order->payment_complete($transaction_id ?: '');
            $order->add_order_note(
                sprintf(
                    /* translators: %s = message from Djomy API */
                    esc_html__( 'Message Djomy : %s', 'gateway-djomy' ),
                    sanitize_text_field( $config['message'] )
                )
            );
            $success = true;
            $user_message = __('Statut mis à jour : Paiement confirmé.', 'gateway-djomy');
            break;

        case 'hold':
            $order->update_status(
                'on-hold',
                sanitize_text_field( $config['message'] )  // Message direct, pas de traduction
            );
            $user_message = __('Statut : en attente.', 'gateway-djomy');
            break;

        case 'fail':
            $order->update_status(
            'failed',
            sanitize_text_field( $config['message'] )  // Message direct, pas de traduction
        );
            $user_message = __('Statut : Échoué/Annulé.', 'gateway-djomy');
            break;
    }

    return [
        'success' => $success,
        'new_status' => $config['wc_status'],
        'message' => $user_message
    ];
}

function djomy_update_custom_db($order_id, $transaction_id, $new_status) {
    if (!class_exists('Djomy\\DB') || !$new_status) {
        return;
    }

    try {
        $db = new DB();
        $update = [
            'updated_at' => current_time('mysql')
        ];

        if ($transaction_id) {
            $update['transaction_id'] = $transaction_id;
        }

        $update['status'] = $new_status;
        $db->update_transaction($order_id, $update);
    } catch (\Throwable $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('djomy refresh db update error: ' . $e->getMessage());
        }
    }
}

function djomy_redirect_with_message($message, $success) {
    $redirect_to = wp_get_referer() ?: admin_url('admin.php?page=djomy-transactions');
    $redirect_to = add_query_arg([
        'djomy_msg' => rawurlencode($message),
        'djomy_success' => $success ? '1' : '0'
    ], $redirect_to);

    wp_safe_redirect($redirect_to);
    exit;
}

function djomy_redirect_back() {
    wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php'));
    exit;
}

function djomy_display_admin_notices() {
    // Récupération du message stocké
    $notice = get_transient('djomy_admin_notice_' . get_current_user_id());
    
    if ($notice) {
        // Supprission du message après l'avoir affiché
        delete_transient('djomy_admin_notice_' . get_current_user_id());
        
        // Affichage du message
        $class = $notice['type'] === 'success' ? 'notice-success' : 'notice-error';
        ?>
        <div class="notice <?php echo esc_attr($class); ?> is-dismissible">
            <p><?php echo esc_html($notice['message']); ?></p>
        </div>
        <?php
    }
}