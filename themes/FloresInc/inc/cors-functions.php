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
        // Permitir solicitudes desde cualquier origen (puedes restringirlo a dominios específicos)
        header('Access-Control-Allow-Origin: *');
        
        // Permitir métodos específicos
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        
        // Permitir cabeceras específicas
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
        
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
add_action('init', 'flores_add_cors_headers');

/**
 * Permitir cabeceras personalizadas en la API REST
 */
function flores_allow_custom_headers($allow_headers) {
    return array(
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-WP-Nonce'
    );
}
add_filter('rest_allowed_cors_headers', 'flores_allow_custom_headers');

/**
 * Asegurar que las cabeceras CORS se apliquen a endpoints personalizados
 */
function flores_custom_endpoints_cors($response, $handler, $request) {
    if (strpos($request->get_route(), 'floresinc/v1') !== false) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
    }
    return $response;
}
add_filter('rest_post_dispatch', 'flores_custom_endpoints_cors', 10, 3);
