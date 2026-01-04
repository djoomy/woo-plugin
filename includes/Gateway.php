<?php
/**
 * Gateway de paiement Djomy
 * 
 * @package WooCommerce_Gateway_Djomy
 */

namespace Djomy;

use Exception;

defined('ABSPATH') || exit;

class Gateway extends \WC_Payment_Gateway {
    
    /**
     * Instance de la base de données
     * @var DB
     */
    private $db;
    
    /**
     * Clé API
     * @var string
     */
    private $api_key;
    
    /**
     * Secret API
     * @var string
     */
    private $api_secret;
    
    /**
     * URL de base
     * @var string
     */
    public $base_url;
    
    /**
     * Mode test
     * @var bool
     */
    private $testmode;
    
    /**
     * Mode debug
     * @var bool
     */
    public $debug;
    
    /**
     * Endpoint de paiement
     * @var string
     */
    public $endpoint_payment;
    
    /**
     * Endpoint de vérification
     * @var string
     */
    public $endpoint_check;
    
    /**
     * Code pays
     * @var string
     */
    private $shop_country_code;
    
    /**
     * Signature HMAC
     * @var string
     */
    private $signature_hmac;
    
    /**
     * Clé API formatée
     * @var string
     */
    public $x_api_key;
    
    /**
     * URL de l'icône
     * @var string
     */
    public $icon;

    /**
     * Domaine du partenaire
     * @var string
     */
    public $partner_domaine;
    
    /**
     * Constructeur
     */
    public function __construct() {
        $this->id                 = 'djomy';
        $this->has_fields         = false;
        $this->method_title       = __('Djomy', 'gateway-djomy');
        $this->method_description = __('Acceptez les paiements par Mobile Money via la plateforme Djomy.', 'gateway-djomy');
        $this->supports           = array('products');
        
        // Initialiser la base de données
        $this->db = new DB();
        
        // Initialiser les champs
        $this->init_form_fields();
        $this->init_settings();
        
        // Définir les propriétés
        $this->setup_properties();
        
        // Définir les hooks
        $this->setup_hooks();
    }
    
    /**
     * Configurer les propriétés
     */
    public function setup_properties() {
        $this->enabled          = $this->get_option('enabled');
        $this->title            = $this->get_option('title');
        $this->description      = $this->get_option('description');
        $this->api_key          = $this->get_option('api_key');
        $this->api_secret       = $this->get_option('api_secret');
        $this->base_url         = $this->get_option('base_url');
        $this->testmode         = 'yes' === $this->get_option('testmode');
        $this->debug            = 'yes' === $this->get_option('debug');
        $this->icon             = $this->get_option('icon_url');
        $this->partner_domaine  = $this->get_option('partner_domaine');
        
        // Configurer les endpoints
        $this->setup_endpoints();
        
        // Configurer la localisation
        $this->setup_location();
        
        // Générer la signature
        $this->generate_signature();
    }
    
    /**
     * Configurer les endpoints
     */
    public function setup_endpoints() {
        $endpoint_payment_raw = $this->get_option('endpoint_payment', '');
        $endpoint_check_raw   = $this->get_option('endpoint_check', '');
        
        if (!empty($this->base_url)) {
            $this->endpoint_payment = rtrim($this->base_url, '/') . '/' . ltrim($endpoint_payment_raw, '/');
            $this->endpoint_check   = rtrim($this->base_url, '/') . '/' . ltrim($endpoint_check_raw, '/');
        } else {
            $this->endpoint_payment = $endpoint_payment_raw;
            $this->endpoint_check   = $endpoint_check_raw;
        }
    }
    
    /**
     * Configurer la localisation
     */
    public function setup_location() {
        $shop_base_location = wc_get_base_location();
        $this->shop_country_code = $shop_base_location['country'];
    }

    public function generate_hmac($clientId, $clientSecret) {
        try {
            $hmacSignature = hash_hmac('sha256', $clientId, $clientSecret);
            return $hmacSignature;
        } catch (Exception $e) {
            throw new Exception(
                esc_html(
                    sprintf(
                        __('Erreur de chiffrement : %s', 'gateway-djomy'),
                        $e->getMessage()
                    )
                )
            );
        }
    }
    
    /**
     * Générer la signature
     */
    public function generate_signature() {
        $this->signature_hmac = $this->generate_hmac($this->api_key, $this->api_secret);
        $this->x_api_key = $this->api_key . ':' . $this->signature_hmac;
    }
    
    /**
     * Configurer les hooks
     */
    public function setup_hooks() {
        // add_action('admin_menu', array($this, 'add_admin_menu'), 20);
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_djomy-callback', array($this, 'handle_callback'));
        add_action('woocommerce_api_djomy-webhook', array($this, 'handle_webhook'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_filter('woocommerce_settings_api_form_fields_' . $this->id, array($this, 'add_webhook_info'));
    }

    /**
     * Ajouter le menu d'administration
     */
    public function add_admin_menu()
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        
        add_submenu_page(
            'woocommerce',
            esc_html__('Transactions Djomy', 'gateway-djomy'),  
            esc_html__('Transactions Djomy', 'gateway-djomy'), 
            'manage_woocommerce',
            'djomy-transactions',
            array($this, 'display_transactions_page')
        );
    }

    /**
     * Afficher la page des transactions
     */
    public function display_transactions_page()
    {
        // Inclure la classe de liste des transactions
        if (!class_exists('WP_List_Table')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        }

        $transactions_list = new TransactionsList();
        $transactions_list->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Transactions Djomy', 'gateway-djomy'); ?></h1>
            <hr class="wp-header-end">

            <form method="get">
                <input type="hidden" name="page" value="djomy-transactions" />
                <?php
                $transactions_list->search_box(__('Rechercher', 'gateway-djomy'), 'search');
                $transactions_list->display();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Ajouter l'information sur l'URL de webhook
     */
    public function add_webhook_info($form_fields)
    {
        $webhook_url = WC()->api_request_url('djomy-webhook');

        $form_fields['webhook_info'] = array(
            'title'       => __('URL de Webhook', 'gateway-djomy'),
            'type'        => 'title',
            'description' => sprintf(
                esc_html__('Configurez cette URL dans votre tableau de bord Djomy pour recevoir les notifications de changement de statut: %s', 'gateway-djomy'),
                '<code>' . esc_html($webhook_url) . '</code>'
            )
        );

        return $form_fields;
    }
    
    /**
     * Initialiser les champs du formulaire
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Activer/Désactiver', 'gateway-djomy'),
                'type'    => 'checkbox',
                'label'   => __('Activer le paiement Djomy', 'gateway-djomy'),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => __('Titre', 'gateway-djomy'),
                'type'        => 'text',
                'description' => __('Texte affiché au checkout', 'gateway-djomy'),
                'default'     => __('Paiement Mobile Djomy', 'gateway-djomy'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'gateway-djomy'),
                'type'        => 'textarea',
                'description' => __('Description visible par le client', 'gateway-djomy'),
                'default'     => __('Payez via Djomy Mobile Money', 'gateway-djomy'),
                'desc_tip'    => true,
            ),
            'partner_domaine' => array(
                'title'       => __('Nom de domaine', 'gateway-djomy'),
                'type'        => 'text',
                'description' => __('Entrez votre nom de domaine', 'gateway-djomy'),
                'default'     => parse_url(home_url(), PHP_URL_HOST),
                'desc_tip'    => true,
            ),
            'api_credentials' => array(
                'title'       => __('Identifiants API', 'gateway-djomy'),
                'type'        => 'title',
            ),
            'api_key' => array(
                'title'       => __('API Key', 'gateway-djomy'),
                'type'        => 'text',
                'description' => __('Clé API fournie par Djomy', 'gateway-djomy'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'api_secret' => array(
                'title'       => __('API Secret', 'gateway-djomy'),
                'type'        => 'password',
                'description' => __('Secret fourni par Djomy', 'gateway-djomy'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'base_url' => array(
                'title'       => __('URL De base API', 'gateway-djomy'),
                'type'        => 'text',
                'description' => __('Configurez l\'url de base de l\'API Djomy.', 'gateway-djomy'),
                'default'     => 'https://api.djomy.africa'
            ),
            'endpoints' => array(
                'title'       => __('Endpoints API', 'gateway-djomy'),
                'type'        => 'title',
                'description' => __('Configurez les endpoints de l\'API Djomy.', 'gateway-djomy'),
            ),
            'endpoint_payment' => array(
                'title'       => __('Endpoint de création de paiement', 'gateway-djomy'),
                'type'        => 'text',
                'description' => __('Exemple :/v1/payments', 'gateway-djomy'),
                'default'     => '/v1/payments/gateway',
                'desc_tip'    => true,
            ),
            'endpoint_check' => array(
                'title'       => __('Endpoint de vérification', 'gateway-djomy'),
                'type'        => 'text',
                'description' => __('Exemple : /v1/payments/{reference}', 'gateway-djomy'),
                'default'     => '/v1/payments/',
                'desc_tip'    => true,
            ),
            'icon_url' => array(
                'title'       => __('Icône', 'gateway-djomy'),
                'type'        => 'text',
                'description' => __('Sélectionnez une image depuis la médiathèque ou entrez une URL.', 'gateway-djomy'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'testmode' => array(
                'title'       => __('Mode test', 'gateway-djomy'),
                'type'        => 'checkbox',
                'label'       => __('Activer le mode test', 'gateway-djomy'),
                'default'     => 'no',
                'description' => __('Utilisez le mode test pour effectuer des paiements simulés.', 'gateway-djomy'),
            ),
            'debug' => array(
                'title'       => __('Journalisation de débogage', 'gateway-djomy'),
                'type'        => 'checkbox',
                'label'       => __('Activer la journalisation', 'gateway-djomy'),
                'default'     => 'no',
                'description' => sprintf(
                    __('Enregistre les événements de l\'API Djomy dans %s', 'gateway-djomy'),
                    '<code>' . \WC_Log_Handler_File::get_log_file_path('djomy') . '</code>'
                ),
            ),
        );
    }
    /**
     * Page de réception (redirection vers Djomy)
     */
    public function receipt_page($order_id)
    {
        echo '<p>' . esc_html__('Vous serez redirigé vers Djomy pour effectuer le paiement.', 'gateway-djomy') . '</p>';
        echo wp_kses_post($this->generate_djomy_form($order_id));
    }

    /**
     * Générer un signature HMAC
     */
    public function generateHmac($clientId, $clientSecret) {
        try {
            $hmacSignature = hash_hmac('sha256', $clientId, $clientSecret);
            return $hmacSignature;
        } catch (Exception $e) {
            throw new Exception(
                esc_html(
                    sprintf(
                        __('Erreur de chiffrement : %s', 'gateway-djomy'),
                        $e->getMessage()
                    )
                )
            );
        }
    }

    /**
     * Générer un access token
     */
    public function generer_token_acces($endpoint) {
        // Vérification des prérequis
        if (empty($this->api_key) || empty($this->api_secret)) {
            $message_erreur = __('Les identifiants API ne sont pas configurés correctement.', 'gateway-djomy');
            wc_add_notice($message_erreur, 'error');
            wc_get_logger()->error('Erreur configuration API: Clés manquantes', array('source' => 'djomy'));
            return false;
        }

        // Préparation des données de la requête
        $donnees_requete = array(
            'clientId' => $this->api_key,
        );

        $parametres_requete = array(
            'body'        => json_encode($donnees_requete),
            'headers'     => array(
                'Content-Type'  => 'application/json',
                'X-API-KEY'     => $this->x_api_key,
                'Accept'        => 'application/json',
                'X-PARTNER-DOMAINE' => $this->partner_domaine
            ),
            'timeout'     => 30,
            'user-agent'  => 'WooCommerce/' . WC()->version . '; ' . get_bloginfo('url')
        );

        // Exécution de la requête API
        $reponse = wp_remote_post($endpoint, $parametres_requete);

        // Gestion des erreurs de connexion
        if (is_wp_error($reponse)) {
            $message_erreur = __('Erreur de connexion à l\'API Djomy: ', 'gateway-djomy') . $reponse->get_error_message();
            wc_add_notice($message_erreur, 'error');
            
            if ($this->debug) {
                wc_get_logger()->error('Erreur connexion API Djomy: ' . $reponse->get_error_message(), array('source' => 'djomy'));
            }
            
            return false;
        }

        // Traitement de la réponse
        $code_statut = wp_remote_retrieve_response_code($reponse);
        $corps_reponse = wp_remote_retrieve_body($reponse);
        $donnees_reponse = json_decode($corps_reponse, true);

        // Logging pour le débogage
        if ($this->debug) {
            $message_log = 'Réponse API Djomy: Code ' . $code_statut . ' - ' . print_r($donnees_reponse, true);
            wc_get_logger()->info($message_log, array('source' => 'djomy'));
        }

        // Vérification du code statut HTTP
        if ($code_statut !== 200 && $code_statut !== 201) {
            $message_erreur = sprintf(
                __('L\'API Djomy a retourné un code d\'erreur: %d', 'gateway-djomy'),
                $code_statut
            );
            wc_add_notice($message_erreur, 'error');
            
            if ($this->debug) {
                wc_get_logger()->error('Code statut API invalide: ' . $code_statut, array('source' => 'djomy'));
            }
            
            return false;
        }

        // Validation de la structure de la réponse
        if (!isset($donnees_reponse['data']['accessToken']) || empty($donnees_reponse['data']['accessToken'])) {
            $message_erreur = __('Le token d\'accès est absent de la réponse API.', 'gateway-djomy');
            wc_add_notice($message_erreur, 'error');
            
            if ($this->debug) {
                wc_get_logger()->error('Structure réponse API invalide: ' . print_r($donnees_reponse, true), array('source' => 'djomy'));
            }
            
            return false;
        }

        // Retour du token d'accès
        return sanitize_text_field($donnees_reponse['data']['accessToken']);
    }

    /**
     * Générer le formulaire de redirection vers Djomy
     */
    public function generate_djomy_form($order_id)
    {
        $order = wc_get_order($order_id);

        // URLs de retour et d'annulation
        $return_url = $this->get_return_url($order);
        $cancel_url = $order->get_cancel_order_url(); // URL d'annulation WooCommerce
        
        $access_token = $this->generer_token_acces($this->base_url .'/v1/auth');
    
        // Préparer les données pour Djomy
        $body = array(
            'amount'                    => $order->get_total(),
            'currency'                  => get_woocommerce_currency(),
            "countryCode"               => $this->shop_country_code,
            "payerNumber"               => $order->get_billing_phone(),
            'reference'                 => $order->get_id(),
            "merchantPaymentReference"  => $order->get_id(),
            'customerName'              => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'customerEmail'             => $order->get_billing_email(),
            'callback_url'              => WC()->api_request_url('djomy-callback'),
            'webhook_url'               => WC()->api_request_url('djomy-webhook'),
            'returnUrl'                 => $return_url,
            'cancelUrl'                 => $cancel_url,
        );
    
        $args = array(
            'body'        => json_encode($body),
            'headers'     => array(
                'Content-Type'      => 'application/json',
                'X-API-KEY'         => $this->x_api_key,  // Votre clé API
                'Authorization'     => 'Bearer ' . $access_token,  // Le token JWT fourni par Djomy
                'X-PARTNER-DOMAINE' => $this->partner_domaine
            ),
            'timeout'     => 30,
            'user-agent'  => 'WooCommerce/' . WC()->version . '; ' . get_bloginfo('url')
        );
    
        if ($this->testmode) {
            $args['headers']['X-Test-Mode'] = 'true';
        }
    
        // Journalisation des requêtes si le debug est activé
        if ($this->debug) {
            wc_get_logger()->info('Requête API Djomy: ' . print_r($args, true), array('source' => 'djomy'));
        }
    
        // Appel API Djomy
        $response = wp_remote_post($this->endpoint_payment, $args);
        
        if (is_wp_error($response)) {
            $error_message = __('Erreur de connexion à l\'API Djomy: ', 'gateway-djomy') . $response->get_error_message();
            wc_add_notice($error_message, 'error');
            
            if ($this->debug) {
                wc_get_logger()->error('Erreur API Djomy: ' . $response->get_error_message(), array('source' => 'djomy'));
            }
            
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($this->debug) {
            wc_get_logger()->info('Réponse API Djomy: Code ' . $response_code . ' - ' . print_r($response_body, true), array('source' => 'djomy'));
        }
        

        if ($response_code === 200 || $response_code === 201) {
            if (isset($response_body['data']['redirectUrl'])) {
                // Mettre à jour le statut de la commande
                $order->update_status('pending', __('En attente de paiement Djomy', 'gateway-djomy'));
                
                // Stocker l'ID de transaction
                $transaction_id = isset($response_body['data']['transactionId']) ? $response_body['data']['transactionId'] : '';
                if ($transaction_id) {
                    $order->set_transaction_id($transaction_id);
                    $order->save();
                }
                
                // Enregistrer la transaction dans la base de données
                $transaction_data = array(
                    'order_id' => $order_id,
                    'transaction_id' => $transaction_id,
                    'amount' => $order->get_total(),
                    'currency' => get_woocommerce_currency(),
                    'status' => 'pending',
                    'payment_method' => 'djomy',
                    'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'customer_email' => $order->get_billing_email(),
                    'created_at' => current_time('mysql')
                );
                
                $this->db->insert_transaction($transaction_data);
                

                // Stocker les URLs dans les meta de la commande
                $order->update_meta_data('_djomy_return_url', $return_url);
                $order->update_meta_data('_djomy_cancel_url', $cancel_url);
                $order->save();
                
                // Redirection automatique vers Djomy
                wp_redirect($response_body['data']['redirectUrl']);
                exit;
            } else {
                $error_message = __('L\'API Djomy n\'a pas retourné d\'URL de paiement.', 'gateway-djomy');
                wc_add_notice($error_message, 'error');
                
                if ($this->debug) {
                    wc_get_logger()->error('Réponse API incomplète: ' . print_r($response_body, true), array('source' => 'djomy'));
                }
                
                return false;
            }
        } else {
            $error_message = __('Erreur API Djomy: ', 'gateway-djomy');
            
            if (isset($response_body['message'])) {
                $error_message .= $response_body['message'];
            } else {
                $error_message .= sprintf(__('Code d\'erreur %s', 'gateway-djomy'), $response_code);
            }
            
            wc_add_notice($error_message, 'error');
            
            if ($this->debug) {
                wc_get_logger()->error('Erreur API: Code ' . $response_code . ' - ' . print_r($response_body, true), array('source' => 'djomy'));
            }
            
            return false;
        }
    }

    /**
     * Traitement du paiement
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        return array(
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );
    }

    /**
     * Gérer le callback de Djomy
     */
    public function handle_callback()
    {
        $raw_input = file_get_contents('php://input');
        $data = json_decode($raw_input, true);

        if ($this->debug) {
            wc_get_logger()->info('Callback reçu: ' . $raw_input, array('source' => 'djomy'));
        }

        // Vérifier la signature si fournie
        if (isset($_SERVER['HTTP_X_SIGNATURE'])) {
            $signature = $_SERVER['HTTP_X_SIGNATURE'];
            $expected_signature = hash_hmac('sha256', $raw_input, $this->api_secret);

            if (!hash_equals($expected_signature, $signature)) {
                if ($this->debug) {
                    wc_get_logger()->error('Signature invalide: ' . $signature . ' vs ' . $expected_signature, array('source' => 'djomy'));
                }
                status_header(403);
                exit;
            }
        }

        if (isset($data['reference']) && isset($data['status'])) {
            $this->process_payment_status($data['reference'], $data);

            status_header(200);
            echo json_encode(array('status' => 'success'));
            exit;
        } else {
            if ($this->debug) {
                wc_get_logger()->error('Données de callback incomplètes: ' . print_r($data, true), array('source' => 'djomy'));
            }
            status_header(400);
            echo json_encode(array('status' => 'error', 'message' => 'Données manquantes'));
            exit;
        }
    }

    /**
     * Gérer les webhooks Djomy
     */
    public function handle_webhook()
    {
        $raw_input = file_get_contents('php://input');
        $data = json_decode($raw_input, true);
    
        if ($this->debug) {
            wc_get_logger()->info('Webhook reçu: ' . $raw_input, array('source' => 'djomy-webhook'));
        }
    
        // Traiter l'événement webhook directement
        if (isset($data['eventType']) && isset($data['data']['merchantPaymentReference'])) {
            $order_id = $data['data']['merchantPaymentReference'];
            $event_type = $data['eventType'];
    
            if ($this->debug) {
                wc_get_logger()->info('Traitement webhook: ' . $event_type . ' pour commande ' . $order_id, array('source' => 'djomy-webhook'));
            }
    
            // Mettre à jour la transaction dans la base de données
            $this->update_transaction_from_webhook($order_id, $data);
    
            switch ($event_type) {
                case 'payment.created':
                    $this->handle_payment_created($order_id, $data);
                    break;
    
                case 'payment.pending':
                    $this->handle_payment_pending($order_id, $data);
                    break;
    
                case 'payment.success':
                    $this->handle_payment_success($order_id, $data);
                    break;
    
                case 'payment.failed':
                    $this->handle_payment_failed($order_id, $data);
                    break;
    
                default:
                    if ($this->debug) {
                        wc_get_logger()->warning('Type d\'événement webhook non géré: ' . $event_type, array('source' => 'djomy-webhook'));
                    }
                    break;
            }
    
            status_header(200);
            echo json_encode(array('status' => 'success', 'message' => 'Webhook traité'));
            exit;
        } else {
            if ($this->debug) {
                wc_get_logger()->error('Données webhook incomplètes: ' . print_r($data, true), array('source' => 'djomy-webhook'));
            }
            status_header(400);
            echo json_encode(array('status' => 'error', 'message' => 'Données webhook manquantes'));
            exit;
        }
    }

    /**
     * Mettre à jour la transaction depuis le webhook
     */
    public function update_transaction_from_webhook($order_id, $data)
    {
        $update_data = array(
            'status' => strtolower(str_replace('payment.', '', $data['eventType'])),
            'updated_at' => current_time('mysql')
        );

        // Ajouter les données spécifiques si disponibles
        if (isset($data['data']['transactionId'])) {
            $update_data['transaction_id'] = $data['data']['transactionId'];
        }

        if (isset($data['data']['paidAmount'])) {
            $update_data['amount'] = $data['data']['paidAmount']; // Conversion depuis les centimes
        }

        if (isset($data['data']['currency'])) {
            $update_data['currency'] = $data['data']['currency'];
        }

        if (isset($data['data']['paymentMethod'])) {
            $update_data['payment_method'] = $data['data']['paymentMethod'];
        }

        if (isset($data['data']['fees'])) {
            $update_data['fees'] = $data['data']['fees'];
        }

        // Mettre à jour la transaction
        $this->db->update_transaction($order_id, $update_data);
    }

    /**
     * Traiter le statut de paiement
     */
    public function process_payment_status($order_id, $data)
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            if ($this->debug) {
                wc_get_logger()->error('Commande non trouvée: ' . $order_id, array('source' => 'djomy'));
            }
            return false;
        }

        // Vérifier que le paiement n'a pas déjà été traité
        if ($order->is_paid()) {
            if ($this->debug) {
                wc_get_logger()->info('Paiement déjà traité pour la commande: ' . $order_id, array('source' => 'djomy'));
            }
            return true;
        }

        // Mettre à jour la transaction dans la base de données
        $update_data = array(
            'status' => strtolower($data['status']),
            'updated_at' => current_time('mysql')
        );

        if (isset($data['transaction_id'])) {
            $update_data['transaction_id'] = $data['transaction_id'];
        }

        $this->db->update_transaction($order_id, $update_data);

        // Traiter le statut du paiement
        switch (strtoupper($data['status'])) {
            case 'SUCCESS':
            case 'COMPLETED':
                $order->payment_complete();
                $order->add_order_note(__('Paiement Djomy confirmé.', 'gateway-djomy'));

                if (isset($data['transaction_id'])) {
                    $order->set_transaction_id($data['transaction_id']);
                }

                $order->save();
                break;

            case 'PENDING':
                $order->update_status('on-hold', __('Paiement Djomy en attente de confirmation.', 'gateway-djomy'));
                break;

            case 'FAILED':
            case 'CANCELLED':
                $order->update_status('failed', __('Paiement Djomy échoué ou annulé.', 'gateway-djomy'));
                break;

            default:
                if ($this->debug) {
                    wc_get_logger()->warning('Statut inconnu reçu: ' . $data['status'], array('source' => 'djomy'));
                }
                break;
        }

        return true;
    }

    /**
     * Gérer l'événement payment.created
     */
    public function handle_payment_created($order_id, $data)
    {
        $order = wc_get_order($order_id);

        if ($order) {
            $order->add_order_note(__('Paiement Djomy créé avec succès.', 'gateway-djomy'));
            if ($this->debug) {
                wc_get_logger()->info('Payment created: ' . $order_id, array('source' => 'djomy-webhook'));
            }
        }
    }

    /**
     * Gérer l'événement payment.pending
     */
    public function handle_payment_pending($order_id, $data)
    {
        $order = wc_get_order($order_id);

        if ($order) {
            $order->update_status('on-hold', __('Paiement Djomy en attente de traitement.', 'gateway-djomy'));
            $order->add_order_note(
                sprintf(
                    __('Paiement en attente - Montant payé: %s %s', 'gateway-djomy'),
                    $data['data']['paidAmount'] / 100,
                    $data['data']['currency']
                )
            );

            if ($this->debug) {
                wc_get_logger()->info('Payment pending: ' . $order_id, array('source' => 'djomy-webhook'));
            }
        }
    }

    /**
     * Gérer l'événement payment.success
     */
    public function handle_payment_success($order_id, $data)
    {
        $order = wc_get_order($order_id);

        if ($order) {
            $order->payment_complete($data['data']['transactionId']);
            $order->add_order_note(
                sprintf(
                    __('Paiement réussi - ID Transaction: %s - Montant: %s %s - Frais: %s %s', 'gateway-djomy'),
                    $data['data']['transactionId'],
                    $data['data']['paidAmount'] / 100,
                    $data['data']['currency'],
                    $data['data']['fees'] / 100,
                    $data['data']['currency']
                )
            );

            if ($this->debug) {
                wc_get_logger()->info('Payment success: ' . $order_id, array('source' => 'djomy-webhook'));
            }
        }
    }

    /**
     * Gérer l'événement payment.failed
     */
    public function handle_payment_failed($order_id, $data)
    {
        $order = wc_get_order($order_id);

        if ($order) {
            $order->update_status('failed', __('Paiement Djomy échoué.', 'gateway-djomy'));
            $order->add_order_note(
                sprintf(
                    __('Paiement échoué - Raison: %s', 'gateway-djomy'),
                    isset($data['message']) ? $data['message'] : __('Raison non spécifiée', 'gateway-djomy')
                )
            );

            if ($this->debug) {
                wc_get_logger()->info('Payment failed: ' . $order_id, array('source' => 'djomy-webhook'));
            }
        }
    }

    /**
     * Vérification des paramètres de configuration
     */
    public function admin_options()
    {
        if (empty($this->api_key) || empty($this->api_secret)) {
            echo '<div class="notice notice-warning"><p>' .
            esc_html__('Veuillez configurer votre clé API et secret Djomy pour activer le paiement.', 'gateway-djomy') .
            '</p></div>';
        }

        if (empty($this->endpoint_payment) || empty($this->endpoint_check)) {
            echo '<div class="notice notice-warning"><p>' .
            esc_html__('Les endpoints de l\'API Djomy doivent être configurés.', 'gateway-djomy') .
            '</p></div>';
        }

        parent::admin_options();
    }

    /**
     * Ajout script js pour changer dynimiquement la base_url
     */
    public function enqueue_scripts() {
        // Charger le script uniquement dans l'admin
        wp_enqueue_media();
        if (is_admin()) {
            wp_enqueue_script(
                'djomy-js',
                plugin_dir_url(dirname(__FILE__)) . 'assets/js/djomy.js',
                ['jquery'],
                '1.0',
                true
            );

            // Passer les URLs au JS
            wp_localize_script('djomy-js', 'djomy_settings', [
                'sandbox_url'    => 'https://sandbox-api.djomy.africa',
                'production_url' => 'https://api.djomy.africa',
            ]);
        }
    }

}

// Ajouter la gateway à WooCommerce
add_filter('woocommerce_payment_gateways', 'djomy_add_gateway_class');
function djomy_add_gateway_class($gateways)
{
    $gateways[] = 'WC_Gateway_Djomy';
    return $gateways;
}

// Afficher un message si WooCommerce n'est pas actif
function djomy_woocommerce_missing_notice()
{
    $message = sprintf(
        esc_html__('La passerelle de paiement Djomy nécessite WooCommerce. Veuillez installer et activer %s.', 'gateway-djomy'),
        '<a href="https://woocommerce.com/" target="_blank">' . esc_html__('WooCommerce', 'gateway-djomy') . '</a>'
    );

    echo '<div class="error"><p>' . wp_kses($message, array(
        'a' => array(
            'href' => array(),
            'target' => array()
        )
    )) . '</p></div>';
}

// Hook d'activation du plugin
register_activation_hook(__FILE__, 'djomy_activate');
function djomy_activate()
{
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('Ce plugin nécessite WooCommerce. Veuillez d\'abord installer et activer WooCommerce.', 'gateway-djomy'),
            esc_html__('Dépendance manquante', 'gateway-djomy'),
            array('back_link' => true)
        );
    }

    // Créer la table des transactions lors de l'activation
    require_once DJOMY_PLUGIN_PATH . 'includes/class-djomy-db.php';
    $db = new DB();
    $db->create_table();
}

// Hook de désactivation du plugin
register_deactivation_hook(__FILE__, 'djomy_deactivate');
function djomy_deactivate()
{
    // Nettoyage optionnel lors de la désactivation
}


