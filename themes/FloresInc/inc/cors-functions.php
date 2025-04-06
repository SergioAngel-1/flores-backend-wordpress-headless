<?php
/**
 * CORS Control Functions
 * 
 * Funciones para habilitar y controlar CORS (Cross-Origin Resource Sharing)
 * en la API REST de WordPress.
 */

/**
 * Añadir cabeceras CORS a las respuestas de la API REST
 */
function flores_add_cors_headers() {
    // Verificar si es una solicitud a la API REST
    if (strpos($_SERVER['REQUEST_URI'], '/wp-json/') !== false) {
        // Obtener el origen de la solicitud
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
        
        // Permitir solicitudes desde cualquier origen (o específicos si es necesario)
        header("Access-Control-Allow-Origin: $origin");
        
        // Permitir métodos específicos
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        
        // Permitir cabeceras específicas
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, X-WP-Nonce, Cache-Control, Pragma, Expires');
        
        // Permitir credenciales (cookies, etc.)
        header('Access-Control-Allow-Credentials: true');
        
        // Establecer tiempo de caché para la respuesta preflight
        header('Access-Control-Max-Age: 86400'); // 24 horas
        
        // Si es una solicitud OPTIONS (preflight), devolver 200 OK
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            status_header(200);
            exit();
        }
    }
}
// Usar prioridad alta (1) para asegurar que se ejecute temprano
add_action('init', 'flores_add_cors_headers', 1);

// También añadir a la acción 'rest_api_init' para asegurar que se aplique a todas las solicitudes de la API REST
add_action('rest_api_init', 'flores_add_cors_headers', 1);

/**
 * Permitir cabeceras personalizadas en la API REST
 */
function flores_allow_custom_headers($allow_headers) {
    return array(
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-WP-Nonce',
        'Cache-Control',
        'Pragma',
        'Expires'
    );
}
add_filter('rest_allowed_cors_headers', 'flores_allow_custom_headers');

/**
 * Asegurar que las cabeceras CORS se apliquen a endpoints personalizados
 */
function flores_custom_endpoints_cors($response, $handler, $request) {
    // Obtener el origen de la solicitud
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
    
    // Aplicar encabezados CORS a todos los endpoints personalizados
    if (strpos($request->get_route(), 'floresinc/v1') !== false) {
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, X-WP-Nonce, Cache-Control, Pragma, Expires');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
    }
    
    return $response;
}
add_filter('rest_pre_serve_request', 'flores_custom_endpoints_cors', 10, 3);

/**
 * Manejar específicamente las solicitudes preflight OPTIONS para endpoints personalizados
 */
function flores_handle_preflight_requests() {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS' && strpos($_SERVER['REQUEST_URI'], '/wp-json/floresinc/v1/') !== false) {
        // Obtener el origen de la solicitud
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
        
        // Configurar encabezados CORS
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, X-WP-Nonce, Cache-Control, Pragma, Expires');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
        
        // Enviar respuesta 200 OK
        status_header(200);
        exit();
    }
}
add_action('init', 'flores_handle_preflight_requests', 0);
