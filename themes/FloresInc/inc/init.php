<?php
/**
 * Archivo de inicialización para cargar todas las funcionalidades del tema
 * Este archivo se incluye desde functions.php
 */

// Definir la ruta base al directorio inc
if (!defined('FLORES_INC_DIR')) {
    define('FLORES_INC_DIR', dirname(__FILE__) . '/');
}

// Asegurarnos de que los hooks de rest_api_init se ejecuten correctamente
add_action('rest_api_init', function() {
    // Registrar rutas de API manualmente para asegurar que funcionen
    if (function_exists('register_featured_categories_rest_route')) {
        register_featured_categories_rest_route();
    }
    
    if (function_exists('register_banner_rest_route')) {
        register_banner_rest_route();
    }
    
    if (function_exists('register_hiperofertas_rest_route')) {
        register_hiperofertas_rest_route();
    }
    
    if (function_exists('register_user_addresses_endpoint')) {
        register_user_addresses_endpoint();
    }
    
    if (function_exists('register_save_user_address_endpoint')) {
        register_save_user_address_endpoint();
    }
    
    if (function_exists('register_delete_user_address_endpoint')) {
        register_delete_user_address_endpoint();
    }
    
    if (function_exists('register_set_default_address_endpoint')) {
        register_set_default_address_endpoint();
    }
    
    if (function_exists('register_profile_endpoints')) {
        register_profile_endpoints();
    }
    
    if (function_exists('register_user_profile_endpoints')) {
        register_user_profile_endpoints();
    }
    
    if (function_exists('register_legal_documents_rest_route')) {
        register_legal_documents_rest_route();
    }
    
    if (function_exists('floresinc_register_promotional_grid_rest_route')) {
        floresinc_register_promotional_grid_rest_route();
    }
    
    if (function_exists('floresinc_register_menu_rest_route')) {
        floresinc_register_menu_rest_route();
    }
}, 20);

// Cargar funciones en un orden específico para evitar dependencias rotas
$required_files = array(
    'cors-functions.php',
    'woocommerce-functions.php',
    'woocommerce-orders-customization.php', // Nueva implementación para la tabla de pedidos
    'woocommerce-order-details.php', // Personalizaciones de la página de detalles del pedido
    'featured-categories-functions.php',
    'banner-functions.php',
    'hiperofertas-functions.php',
    'user-addresses-functions.php',
    'profile-functions.php',
    'user-profile-functions.php',
    'legal-functions.php',
    'promotional-grid-functions.php',
    'menu-functions.php'
);

// Verificar y cargar cada archivo solo si existe
foreach ($required_files as $file) {
    $filepath = FLORES_INC_DIR . $file;
    if (file_exists($filepath)) {
        require_once $filepath;
    } else {
        // Registrar un aviso si el archivo no existe, pero no interrumpir la ejecución
        error_log("Aviso: No se pudo cargar el archivo {$file} en el tema FloresInc. El archivo no existe, pero la ejecución continúa.");
    }
}

/**
 * Función para verificar que todas las funcionalidades estén cargadas correctamente
 */
function flores_check_functionality() {
    // Lista de funciones críticas que deben estar definidas
    $critical_functions = array(
        'flores_add_cors_headers',
        'register_profile_endpoints',
        'register_user_addresses_endpoint',
        'floresinc_register_banner_post_type',
        'register_featured_categories_page',
        'register_hiperofertas_post_type',
        'register_legal_post_type',
        'floresinc_register_promotional_grid_rest_route'
    );
    
    $missing_functions = array();
    
    // Verificar cada función
    foreach ($critical_functions as $function) {
        if (!function_exists($function)) {
            $missing_functions[] = $function;
        }
    }
    
    // Si hay funciones faltantes, registrar un error
    if (!empty($missing_functions)) {
        error_log('Error: Las siguientes funciones críticas no están definidas: ' . implode(', ', $missing_functions));
        
        // Solo mostrar en el admin para no afectar a los usuarios
        if (is_admin()) {
            add_action('admin_notices', function() use ($missing_functions) {
                echo '<div class="error"><p>';
                echo '<strong>Error en el tema FloresInc:</strong> Las siguientes funciones críticas no están definidas:<br>';
                echo implode('<br>', $missing_functions);
                echo '</p></div>';
            });
        }
    }
}
add_action('init', 'flores_check_functionality', 999); // Prioridad alta para ejecutar después de que todo esté cargado
