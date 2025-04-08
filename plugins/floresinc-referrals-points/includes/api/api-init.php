<?php
/**
 * Inicialización de API REST
 * 
 * Funciones para inicializar y configurar la API REST.
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registrar endpoints de API
 */
function floresinc_rp_register_api_endpoints() {
    add_action('rest_api_init', 'floresinc_rp_register_routes');
}

/**
 * Registrar rutas de API
 */
function floresinc_rp_register_routes() {
    // Namespace base para nuestros endpoints
    $namespace = 'floresinc/v1';
    
    // === ENDPOINTS DE PUNTOS ===
    
    // Ruta para obtener Flores Coins del usuario actual
    register_rest_route($namespace, '/points', [
        'methods' => 'GET',
        'callback' => 'floresinc_rp_get_current_user_points_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
    
    // Ruta para obtener transacciones de Flores Coins del usuario actual
    register_rest_route($namespace, '/points/transactions', [
        'methods' => 'GET',
        'callback' => 'floresinc_rp_get_user_transactions_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
    
    // Ruta para transferir Flores Coins a otro usuario usando código de referido
    register_rest_route($namespace, '/points/transfer', [
        'methods' => 'POST',
        'callback' => 'floresinc_rp_transfer_points_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
    
    // Ruta para obtener información de Flores Coins de un producto (pública)
    register_rest_route($namespace, '/product/(?P<id>\d+)/points', [
        'methods' => 'GET',
        'callback' => 'floresinc_rp_get_product_points_endpoint',
        'permission_callback' => '__return_true'
    ]);
    
    // === ENDPOINTS DE REFERIDOS ===
    
    // Ruta para obtener información de referidos del usuario actual
    register_rest_route($namespace, '/referrals', [
        'methods' => 'GET',
        'callback' => 'floresinc_rp_get_user_referrals_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
    
    // Ruta para obtener estadísticas de referidos del usuario actual
    register_rest_route($namespace, '/referrals/stats', [
        'methods' => 'GET',
        'callback' => 'floresinc_rp_get_user_referral_stats_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
    
    // Ruta para obtener el código y enlace de referido del usuario actual
    register_rest_route($namespace, '/referrals/code', [
        'methods' => 'GET',
        'callback' => 'floresinc_rp_get_user_referral_code_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
    
    // Ruta para obtener la configuración del programa de referidos
    register_rest_route($namespace, '/referrals/config', [
        'methods' => 'GET',
        'callback' => 'floresinc_rp_get_referral_config_endpoint',
        'permission_callback' => '__return_true'
    ]);
    
    // Ruta para validar un código de referido
    register_rest_route($namespace, '/referrals/validate-code', [
        'methods' => 'GET',
        'callback' => 'floresinc_rp_validate_referral_code_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
    
    // === ENDPOINTS DE ADMINISTRACIÓN ===
    
    // Ruta para obtener resumen de estadísticas (solo admin)
    register_rest_route($namespace, '/admin/stats', [
        'methods' => 'GET',
        'callback' => 'floresinc_rp_get_admin_stats_endpoint',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ]);
    
    // === ENDPOINTS DE REFERRAL-API.PHP ===
    
    // Registramos los endpoints definidos en referral-api.php
    floresinc_rp_register_referrer_endpoint();
}
