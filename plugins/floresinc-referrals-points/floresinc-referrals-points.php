<?php
/**
 * Plugin Name: FloresInc Referrals & Points
 * Description: Sistema integral de referidos y puntos para WooCommerce
 * Version: 1.0
 * Author: FloresInc
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Asegurarse de que WP_REST_Response esté disponible
if (!class_exists('WP_REST_Response')) {
    require_once ABSPATH . 'wp-includes/rest-api/class-wp-rest-response.php';
}

// Definir constantes
define('FLORESINC_RP_DIR', plugin_dir_path(__FILE__));
define('FLORESINC_RP_URL', plugin_dir_url(__FILE__));
define('FLORESINC_RP_VERSION', '1.0.0');

// Incluir archivos de funciones
require_once FLORESINC_RP_DIR . 'includes/database.php';
require_once FLORESINC_RP_DIR . 'includes/admin-functions.php';
require_once FLORESINC_RP_DIR . 'includes/points-functions.php';
require_once FLORESINC_RP_DIR . 'includes/referral-functions.php';
require_once FLORESINC_RP_DIR . 'includes/api-endpoints.php';
require_once FLORESINC_RP_DIR . 'includes/woocommerce-integration.php';
require_once FLORESINC_RP_DIR . 'includes/class-transactions-table.php';
require_once FLORESINC_RP_DIR . 'includes/class-referrals-table.php';

// Registrar scripts y estilos
add_action('admin_enqueue_scripts', 'floresinc_rp_admin_scripts');
add_action('wp_enqueue_scripts', 'floresinc_rp_frontend_scripts');

/**
 * Cargar scripts y estilos para el admin
 */
function floresinc_rp_admin_scripts() {
    $screen = get_current_screen();
    
    // Cargar solo en páginas del plugin
    if (strpos($screen->id, 'floresinc-rp') !== false) {
        wp_enqueue_style('floresinc-rp-admin', FLORESINC_RP_URL . 'assets/css/admin.css', [], FLORESINC_RP_VERSION);
        wp_enqueue_script('floresinc-rp-admin', FLORESINC_RP_URL . 'assets/js/admin.js', ['jquery'], FLORESINC_RP_VERSION, true);
    }
}

/**
 * Cargar scripts y estilos para el frontend
 */
function floresinc_rp_frontend_scripts() {
    // Solo cargar si estamos en un entorno de React
    if (defined('FLORESINC_HEADLESS_MODE') && FLORESINC_HEADLESS_MODE) {
        return;
    }
    
    wp_enqueue_style('floresinc-rp-frontend', FLORESINC_RP_URL . 'assets/css/frontend.css', [], FLORESINC_RP_VERSION);
    wp_enqueue_script('floresinc-rp-frontend', FLORESINC_RP_URL . 'assets/js/frontend.js', ['jquery'], FLORESINC_RP_VERSION, true);
}

/**
 * Añadir encabezados CORS para permitir solicitudes desde el frontend
 */
function floresinc_rp_add_cors_headers() {
    // Obtener el origen de la solicitud
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    
    // Lista de orígenes permitidos
    $allowed_origins = [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:63347',
        'http://127.0.0.1:63347'
    ];
    
    // Verificar si el origen está permitido
    if (in_array($origin, $allowed_origins)) {
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With");
    }
    
    // Si es una solicitud OPTIONS (preflight), devolver 200 OK
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        status_header(200);
        exit();
    }
}

// Añadir la función para las solicitudes regulares y para la REST API
add_action('init', 'floresinc_rp_add_cors_headers');
add_action('rest_api_init', 'floresinc_rp_add_cors_headers', 15);

// Función para asegurar que los encabezados CORS se apliquen a la respuesta de la REST API
function floresinc_rp_rest_after_callback($response, $handler, $request) {
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    
    $allowed_origins = [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:63347',
        'http://127.0.0.1:63347'
    ];
    
    if (in_array($origin, $allowed_origins)) {
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With");
    }
    
    return $response;
}
add_filter('rest_post_dispatch', 'floresinc_rp_rest_after_callback', 10, 3);

// Clase principal del plugin
class FloresInc_Referrals_Points {
    
    // Singleton instance
    private static $instance = null;
    
    // Opciones del plugin
    private $options;
    
    /**
     * Constructor
     */
    private function __construct() {
        // Cargar opciones
        $this->options = get_option('floresinc_referrals_points_options', [
            'points_conversion_rate' => 100, // 100 puntos = 1 unidad de moneda
            'referral_commission_level1' => 10, // 10% en puntos para referidos directos
            'referral_commission_level2' => 5,  // 5% en puntos para referidos indirectos
            'points_expiration_days' => 365,    // Caducidad en días (0 = sin caducidad)
        ]);
        
        // Hooks de activación/desactivación
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Inicializar componentes
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    /**
     * Obtener instancia (Singleton)
     */
    public static function get_instance() {
        if (self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Activar plugin
     */
    public function activate() {
        // Crear tablas en la base de datos
        floresinc_rp_create_tables();
        
        // Opciones por defecto
        if (!get_option('floresinc_referrals_points_options')) {
            update_option('floresinc_referrals_points_options', $this->options);
        }
        
        // Marcar que es necesario regenerar códigos de referido para usuarios existentes
        update_option('floresinc_rp_generate_codes', 'yes');
        
        // Log de activación
        error_log('Plugin FloresInc Referrals & Points activado');
    }
    
    /**
     * Desactivar plugin
     */
    public function deactivate() {
        // Limpiar cron jobs
        wp_clear_scheduled_hook('floresinc_rp_daily_maintenance');
        
        // Log de desactivación
        error_log('Plugin FloresInc Referrals & Points desactivado');
    }
    
    /**
     * Inicializar plugin
     */
    public function init() {
        // Verificar dependencias
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Inicializar componentes
        floresinc_rp_init_admin();
        floresinc_rp_init_points();
        floresinc_rp_init_referrals();
        floresinc_rp_register_api_endpoints();
        floresinc_rp_init_woocommerce_integration();
        
        // Programar tarea diaria
        if (!wp_next_scheduled('floresinc_rp_daily_maintenance')) {
            wp_schedule_event(time(), 'daily', 'floresinc_rp_daily_maintenance');
        }
        
        // Generar códigos de referido para usuarios existentes si es necesario
        if (get_option('floresinc_rp_generate_codes') === 'yes') {
            $this->generate_referral_codes_for_existing_users();
            update_option('floresinc_rp_generate_codes', 'no');
        }
        
        // Log de inicialización
        error_log('Plugin FloresInc Referrals & Points inicializado');
    }
    
    /**
     * Generar códigos de referido para usuarios existentes
     */
    private function generate_referral_codes_for_existing_users() {
        $users = get_users(['fields' => 'ID']);
        foreach ($users as $user_id) {
            floresinc_rp_generate_referral_code($user_id);
        }
    }
    
    /**
     * Aviso de WooCommerce faltante
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php _e('FloresInc Referrals & Points requiere que WooCommerce esté instalado y activado.', 'floresinc-rp'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Obtener opciones
     */
    public function get_options() {
        return $this->options;
    }
    
    /**
     * Actualizar opciones
     */
    public function update_options($new_options) {
        $this->options = array_merge($this->options, $new_options);
        update_option('floresinc_referrals_points_options', $this->options);
    }
}

// Inicializar el plugin
function FloresInc_RP() {
    return FloresInc_Referrals_Points::get_instance();
}

// Comenzar
FloresInc_RP();
