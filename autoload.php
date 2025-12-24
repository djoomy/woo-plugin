<?php
/**
 * Autoloader PSR-4 pour WooCommerce Gateway Djomy
 * 
 * @package WooCommerce_Gateway_Djomy
 */

namespace Djomy;

defined('ABSPATH') || exit;

/**
 * Autoloader pour les classes du plugin
 */
class Autoloader {
    
    /**
     * Instance unique
     * @var Autoloader|null
     */
    private static $instance = null;
    
    /**
     * Namespace racine
     * @var string
     */
    private $namespace = 'Djomy\\';
    
    /**
     * Chemin racine
     * @var string
     */
    private $base_path;
    
    /**
     * Constructeur privé (singleton)
     */
    private function __construct() {
        $this->base_path = DJOMY_PLUGIN_PATH . 'includes/';
        
        spl_autoload_register([$this, 'autoload']);
    }
    
    /**
     * Obtenir l'instance unique
     * 
     * @return Autoloader
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Méthode d'autochargement
     * 
     * @param string $class_name Nom complet de la classe
     */
    public function autoload($class_name) {
        // Vérifier si la classe appartient à notre namespace
        if (strpos($class_name, $this->namespace) !== 0) {
            return;
        }
        
        // Supprimer le namespace de base
        $relative_class = substr($class_name, strlen($this->namespace));
        
        // Convertir le namespace en chemin de fichier
        $file = $this->base_path . str_replace('\\', '/', $relative_class) . '.php';
        
        // Inclure le fichier s'il existe
        if (file_exists($file)) {
            require_once $file;
        }
    }
    
    /**
     * Initialiser l'autoloader
     */
    public static function init() {
        self::get_instance();
    }
}