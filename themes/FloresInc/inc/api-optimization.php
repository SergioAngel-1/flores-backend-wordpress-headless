<?php
/**
 * API Optimization Functions
 * 
 * Funciones para optimizar el rendimiento de la API REST de WordPress
 * y mejorar la comunicación con el frontend React
 */

// Registrar la activación del archivo
add_action('after_setup_theme', function() {
    // Solo registrar mensajes de depuración si se ha habilitado explícitamente
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('API Optimization module loaded');
    }
});

/**
 * DESACTIVAR TEMPORALMENTE toda la optimización de API para depuración
 * Esta constante permite desactivar rápidamente todo el sistema de optimización
 */
if (!defined('DISABLE_API_OPTIMIZATION')) {
    define('DISABLE_API_OPTIMIZATION', true);
}

/**
 * Sistema de caché en memoria para respuestas de la API
 * Utiliza transients de WordPress para almacenar respuestas en caché
 */
class API_Cache_Manager {
    // Prefijo para las claves de transient
    private $prefix = 'flores_api_cache_';
    
    // Tiempos de expiración predeterminados (en segundos)
    private $ttl = [
        'product' => 300,        // 5 minutos para productos individuales
        'products' => 120,       // 2 minutos para listas de productos
        'category' => 1800,      // 30 minutos para categorías individuales
        'categories' => 1800,    // 30 minutos para listas de categorías
        'user' => 600,           // 10 minutos para datos de usuario
        'catalog' => 600,        // 10 minutos para catálogos
        'banner' => 1800,        // 30 minutos para banners
        'homeSection' => 300,    // 5 minutos para secciones de inicio
        'legal' => 3600          // 60 minutos para contenido legal
    ];
    
    // Lista de rutas y namespaces que nunca deben ser cacheados
    private $excluded_routes = [
        '/jwt-auth/',          // Autenticación JWT
        '/aam/',               // Advanced Access Manager
        '/wpml/',              // WPML
        '/contact-form-7/',    // Contact Form 7
        '/floresinc/v1/batch', // Nuestro propio endpoint de batch
        '/floresinc/v1/query', // Nuestro propio endpoint de query
        'token',               // Cualquier endpoint relacionado con tokens
        'login',               // Cualquier endpoint relacionado con login
        'user/me',             // Endpoint de usuario actual
        'logout'               // Cualquier endpoint relacionado con logout
    ];
    
    /**
     * Construye una clave única para la caché
     */
    public function build_cache_key($content_type, $id = null, $params = []) {
        // Ordenar parámetros para asegurar consistencia en la clave
        if (!empty($params)) {
            ksort($params);
        }
        
        $params_str = !empty($params) ? md5(json_encode($params)) : '';
        $key = $this->prefix . $content_type . '_' . ($id ?? 'list') . '_' . $params_str;
        
        // Asegurar que la clave no exceda el límite de longitud para transients
        if (strlen($key) > 172) { // 172 es seguro para la mayoría de bases de datos
            $key = $this->prefix . $content_type . '_' . ($id ?? 'list') . '_' . md5($params_str);
        }
        
        return $key;
    }
    
    /**
     * Obtiene un elemento de la caché
     */
    public function get($content_type, $id = null, $params = []) {
        if (defined('DISABLE_API_OPTIMIZATION') && DISABLE_API_OPTIMIZATION) {
            return null;
        }
        
        $key = $this->build_cache_key($content_type, $id, $params);
        $cached_data = get_transient($key);
        
        if ($cached_data !== false) {
            // Debug en desarrollo
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("API Cache Hit: {$key}");
            }
            return $cached_data;
        }
        
        return null;
    }
    
    /**
     * Guarda un elemento en la caché
     */
    public function set($content_type, $data, $id = null, $params = [], $custom_ttl = null) {
        if (defined('DISABLE_API_OPTIMIZATION') && DISABLE_API_OPTIMIZATION) {
            return false;
        }
        
        $key = $this->build_cache_key($content_type, $id, $params);
        $ttl = $custom_ttl ?? ($this->ttl[$content_type] ?? 60); // Default 60 seconds if not specified
        
        $set_result = set_transient($key, $data, $ttl);
        
        // Debug en desarrollo
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("API Cache Set: {$key}, TTL: {$ttl}s, Result: " . ($set_result ? 'Success' : 'Failed'));
        }
        
        return $set_result;
    }
    
    /**
     * Invalida un elemento específico de la caché
     */
    public function invalidate($content_type, $id = null, $params = []) {
        if (defined('DISABLE_API_OPTIMIZATION') && DISABLE_API_OPTIMIZATION) {
            return false;
        }
        
        $key = $this->build_cache_key($content_type, $id, $params);
        $result = delete_transient($key);
        
        // Debug en desarrollo
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("API Cache Invalidated: {$key}, Result: " . ($result ? 'Success' : 'Failed'));
        }
        
        return $result;
    }
    
    /**
     * Invalida todos los elementos de la caché por tipo de contenido
     */
    public function invalidate_by_type($content_type) {
        if (defined('DISABLE_API_OPTIMIZATION') && DISABLE_API_OPTIMIZATION) {
            return 0;
        }
        
        global $wpdb;
        
        $like = $wpdb->esc_like($this->prefix . $content_type) . '%';
        $sql = $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name LIKE %s",
            '_transient_' . $like,
            '%'
        );
        
        $count = $wpdb->query($sql);
        
        // También eliminar las entradas de timeout
        $sql = $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name LIKE %s",
            '_transient_timeout_' . $like,
            '%'
        );
        
        $wpdb->query($sql);
        
        // Debug en desarrollo
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("API Cache Type Invalidated: {$content_type}, Entries removed: {$count}");
        }
        
        return $count;
    }
    
    /**
     * Verifica si una ruta debe ser excluida de la caché
     */
    public function should_exclude_route($route) {
        foreach ($this->excluded_routes as $excluded_route) {
            if (strpos($route, $excluded_route) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Obtener la instancia singleton
     */
    public static function instance() {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance;
    }
}

// Inicializar instancia de caché
function flores_api_cache() {
    return API_Cache_Manager::instance();
}

/**
 * Hooks para invalidar caché automáticamente
 */

// Invalidar caché de productos cuando hay cambios
function invalidate_product_cache($post_id) {
    if (get_post_type($post_id) !== 'product') {
        return;
    }
    
    flores_api_cache()->invalidate_by_type('product');
    flores_api_cache()->invalidate_by_type('products');
    
    // También invalidar categorías relacionadas
    $terms = get_the_terms($post_id, 'product_cat');
    if ($terms && !is_wp_error($terms)) {
        foreach ($terms as $term) {
            flores_api_cache()->invalidate('category', $term->term_id);
        }
    }
}
add_action('save_post_product', 'invalidate_product_cache');
add_action('woocommerce_update_product', 'invalidate_product_cache');
add_action('woocommerce_delete_product', 'invalidate_product_cache');

// Invalidar caché de categorías cuando hay cambios
function invalidate_category_cache($term_id, $tt_id, $taxonomy) {
    if ($taxonomy !== 'product_cat') {
        return;
    }
    
    flores_api_cache()->invalidate_by_type('category');
    flores_api_cache()->invalidate_by_type('categories');
    flores_api_cache()->invalidate_by_type('products'); // Productos filtrados por categoría
}
add_action('create_term', 'invalidate_category_cache', 10, 3);
add_action('edit_term', 'invalidate_category_cache', 10, 3);
add_action('delete_term', 'invalidate_category_cache', 10, 3);

// Invalidar caché de usuarios cuando hay cambios
function invalidate_user_cache($user_id) {
    flores_api_cache()->invalidate('user', $user_id);
}
add_action('profile_update', 'invalidate_user_cache');
add_action('user_register', 'invalidate_user_cache');
add_action('deleted_user', 'invalidate_user_cache');

/**
 * Funcionalidad de procesamiento por lotes (Batch Processing)
 * 
 * Permite ejecutar múltiples solicitudes de API en una sola llamada
 */

// Registrar endpoint para procesamiento por lotes
function register_batch_endpoint() {
    register_rest_route('floresinc/v1', '/batch', array(
        'methods' => 'POST',
        'callback' => 'handle_batch_requests',
        'permission_callback' => '__return_true', // La autenticación se verifica por solicitud individual
    ));
}
add_action('rest_api_init', 'register_batch_endpoint');

/**
 * Procesa múltiples solicitudes de API en un solo request
 */
function handle_batch_requests($request) {
    $params = $request->get_params();
    
    if (empty($params['requests']) || !is_array($params['requests'])) {
        return new WP_Error('invalid_batch', 'No hay solicitudes válidas en el lote', array('status' => 400));
    }
    
    $responses = array();
    $requests = $params['requests'];
    
    // Procesar cada solicitud
    foreach ($requests as $req) {
        // Verificar campos obligatorios
        if (empty($req['method']) || empty($req['path'])) {
            $responses[] = array(
                'id' => $req['id'] ?? 'unknown',
                'status' => 400,
                'data' => array('error' => 'Solicitud inválida: falta método o ruta')
            );
            continue;
        }
        
        // Normalizar path
        $path = ltrim($req['path'], '/');
        
        // Crear una instancia de WP_REST_Request
        $inner_request = new WP_REST_Request($req['method'], '/' . $path);
        
        // Añadir parámetros si están presentes
        if (!empty($req['params']) && is_array($req['params'])) {
            foreach ($req['params'] as $param_name => $param_value) {
                $inner_request->set_param($param_name, $param_value);
            }
        }
        
        // Añadir cuerpo si está presente
        if (!empty($req['body']) && is_array($req['body'])) {
            $inner_request->set_body_params($req['body']);
        }
        
        // Despachar la solicitud
        $response = rest_do_request($inner_request);
        
        // Preparar respuesta para este ítem del lote
        $resp_item = array(
            'id' => $req['id'] ?? 'unknown',
            'status' => $response->get_status(),
        );
        
        // Manejar éxito o error
        if ($response->is_error()) {
            $resp_item['data'] = array(
                'error' => $response->get_error_message(),
                'code' => $response->get_error_code()
            );
        } else {
            $resp_item['data'] = $response->get_data();
        }
        
        $responses[] = $resp_item;
    }
    
    return rest_ensure_response(array(
        'responses' => $responses
    ));
}

/**
 * Implementar GraphQL para campos seleccionados
 * Permite solicitar exactamente los campos necesarios
 */

// Registrar endpoint para consultas basadas en campos seleccionados
function register_fields_selection_endpoint() {
    register_rest_route('floresinc/v1', '/query', array(
        'methods' => 'POST',
        'callback' => 'handle_fields_selection_query',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'register_fields_selection_endpoint');

/**
 * Procesa una consulta con selección de campos específicos
 */
function handle_fields_selection_query($request) {
    $params = $request->get_params();
    
    if (empty($params['resource']) || empty($params['fields'])) {
        return new WP_Error('invalid_query', 'Consulta inválida: falta recurso o campos', array('status' => 400));
    }
    
    $resource = $params['resource'];
    $fields = $params['fields'];
    $resource_id = $params['id'] ?? null;
    $query_params = $params['params'] ?? array();
    
    // Verificar la caché antes de procesar
    $cache_type = determine_cache_type($resource, $resource_id);
    $cached_result = flores_api_cache()->get($cache_type, $resource_id, array_merge($query_params, ['fields' => $fields]));
    
    if ($cached_result !== null) {
        return rest_ensure_response($cached_result);
    }
    
    // Procesar la consulta
    switch ($resource) {
        case 'products':
            $result = get_products_with_fields($fields, $query_params, $resource_id);
            break;
        case 'categories':
            $result = get_categories_with_fields($fields, $query_params, $resource_id);
            break;
        case 'users':
            $result = get_users_with_fields($fields, $query_params, $resource_id);
            break;
        default:
            return new WP_Error('invalid_resource', 'Recurso no soportado', array('status' => 400));
    }
    
    // Guardar en caché
    flores_api_cache()->set($cache_type, $result, $resource_id, array_merge($query_params, ['fields' => $fields]));
    
    return rest_ensure_response($result);
}

/**
 * Determina el tipo de caché basado en el recurso
 */
function determine_cache_type($resource, $resource_id) {
    switch ($resource) {
        case 'products':
            return $resource_id ? 'product' : 'products';
        case 'categories':
            return $resource_id ? 'category' : 'categories';
        case 'users':
            return 'user';
        default:
            return $resource;
    }
}

/**
 * Obtiene productos con campos específicos
 */
function get_products_with_fields($fields, $query_params, $product_id = null) {
    // Si se especifica un ID, obtener un solo producto
    if ($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return null;
        }
        return extract_product_fields($product, $fields);
    }
    
    // Parámetros para la consulta
    $args = array(
        'status' => 'publish',
        'limit' => $query_params['per_page'] ?? 10,
        'page' => $query_params['page'] ?? 1,
    );
    
    // Añadir filtros adicionales
    if (!empty($query_params['category'])) {
        $args['category'] = array($query_params['category']);
    }
    
    if (!empty($query_params['search'])) {
        $args['s'] = $query_params['search'];
    }
    
    // Ordenamiento
    if (!empty($query_params['orderby'])) {
        $args['orderby'] = $query_params['orderby'];
        $args['order'] = $query_params['order'] ?? 'DESC';
    }
    
    // Obtener productos
    $products = wc_get_products($args);
    
    // Extraer los campos solicitados de cada producto
    $result = array();
    foreach ($products as $product) {
        $result[] = extract_product_fields($product, $fields);
    }
    
    return $result;
}

/**
 * Extrae campos específicos de un producto
 */
function extract_product_fields($product, $fields) {
    $data = array();
    
    // Procesar cada campo solicitado
    foreach ($fields as $field) {
        switch ($field) {
            case 'id':
                $data['id'] = $product->get_id();
                break;
            case 'name':
                $data['name'] = $product->get_name();
                break;
            case 'price':
                $data['price'] = $product->get_price();
                break;
            case 'regular_price':
                $data['regular_price'] = $product->get_regular_price();
                break;
            case 'sale_price':
                $data['sale_price'] = $product->get_sale_price();
                break;
            case 'description':
                $data['description'] = $product->get_description();
                break;
            case 'short_description':
                $data['short_description'] = $product->get_short_description();
                break;
            case 'sku':
                $data['sku'] = $product->get_sku();
                break;
            case 'stock_status':
                $data['stock_status'] = $product->get_stock_status();
                break;
            case 'stock_quantity':
                $data['stock_quantity'] = $product->get_stock_quantity();
                break;
            case 'categories':
                $data['categories'] = array();
                $terms = get_the_terms($product->get_id(), 'product_cat');
                if ($terms && !is_wp_error($terms)) {
                    foreach ($terms as $term) {
                        $data['categories'][] = array(
                            'id' => $term->term_id,
                            'name' => $term->name,
                            'slug' => $term->slug
                        );
                    }
                }
                break;
            case 'images':
                $data['images'] = array();
                
                // Imagen principal
                if ($product->get_image_id()) {
                    $image = wp_get_attachment_image_src($product->get_image_id(), 'full');
                    if ($image) {
                        $data['images'][] = array(
                            'id' => $product->get_image_id(),
                            'src' => $image[0],
                            'alt' => get_post_meta($product->get_image_id(), '_wp_attachment_image_alt', true)
                        );
                    }
                }
                
                // Imágenes de galería
                $gallery_ids = $product->get_gallery_image_ids();
                foreach ($gallery_ids as $id) {
                    $image = wp_get_attachment_image_src($id, 'full');
                    if ($image) {
                        $data['images'][] = array(
                            'id' => $id,
                            'src' => $image[0],
                            'alt' => get_post_meta($id, '_wp_attachment_image_alt', true)
                        );
                    }
                }
                break;
            case 'attributes':
                $data['attributes'] = array();
                $attributes = $product->get_attributes();
                foreach ($attributes as $attribute) {
                    if ($attribute->is_taxonomy()) {
                        $terms = wp_get_post_terms($product->get_id(), $attribute->get_name());
                        $options = array();
                        foreach ($terms as $term) {
                            $options[] = $term->name;
                        }
                    } else {
                        $options = $attribute->get_options();
                    }
                    $data['attributes'][] = array(
                        'name' => wc_attribute_label($attribute->get_name()),
                        'options' => $options
                    );
                }
                break;
        }
    }
    
    return $data;
}

/**
 * Obtiene categorías con campos específicos
 */
function get_categories_with_fields($fields, $query_params, $category_id = null) {
    // Implementación similar a productos, para categorías
    // ...
    return array(); // Implementación simplificada
}

/**
 * Obtiene usuarios con campos específicos
 */
function get_users_with_fields($fields, $query_params, $user_id = null) {
    // Implementación similar para usuarios
    // ...
    return array(); // Implementación simplificada
}

/**
 * Optimización de endpoints existentes para incluir caché
 */
function add_cache_to_rest_response($response, $handler, $request) {
    // Si la optimización está desactivada, no intervenir
    if (defined('DISABLE_API_OPTIMIZATION') && DISABLE_API_OPTIMIZATION) {
        return $response;
    }
    
    // Solo procesar respuestas exitosas
    if (is_wp_error($response) || $response->get_status() !== 200) {
        return $response;
    }
    
    // Solo cachear GET requests
    if ($request->get_method() !== 'GET') {
        return $response;
    }
    
    // Obtener la ruta de la API
    $route = $request->get_route();
    
    // Verificar exclusiones - crítico para autenticación y otras operaciones seguras
    if (flores_api_cache()->should_exclude_route($route)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("API Cache: Ruta excluida - {$route}");
        }
        return $response;
    }
    
    // Analizar la ruta para determinar el tipo de contenido
    $content_type = identify_content_type_from_route($route);
    
    // Si no se puede identificar un tipo de contenido válido, no cachear
    if (!$content_type) {
        return $response;
    }
    
    // Determinar ID si existe
    $id = extract_id_from_route($route);
    
    // Cachear la respuesta
    flores_api_cache()->set(
        $content_type,
        $response->get_data(),
        $id,
        $request->get_params()
    );
    
    return $response;
}

// Usar una prioridad más alta (20) para asegurarnos de que se ejecute después de
// que todos los demás filtros hayan procesado la respuesta
add_filter('rest_request_after_callbacks', 'add_cache_to_rest_response', 20, 3);

/**
 * Verificar y usar caché antes de procesar solicitudes REST
 */
function check_cache_before_rest_request($response, $handler, $request) {
    // Si la optimización está desactivada, no intervenir
    if (defined('DISABLE_API_OPTIMIZATION') && DISABLE_API_OPTIMIZATION) {
        return $response;
    }
    
    // Ignorar si ya hay una respuesta (errores, etc.)
    if ($response !== null) {
        return $response;
    }
    
    // Solo verificar caché para GET requests
    if ($request->get_method() !== 'GET') {
        return null;
    }
    
    // Obtener la ruta de la API
    $route = $request->get_route();
    
    // Debug en modo desarrollo
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("API Request Route: {$route}, Method: " . $request->get_method());
    }
    
    // Verificar exclusiones - crítico para autenticación y otras operaciones seguras
    if (flores_api_cache()->should_exclude_route($route)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("API Cache Bypass: Ruta excluida - {$route}");
        }
        return null;
    }
    
    // Analizar la ruta para determinar el tipo de contenido
    $content_type = identify_content_type_from_route($route);
    
    // Si no se puede identificar un tipo válido de contenido, continuar normalmente
    if (!$content_type) {
        return null;
    }
    
    // Determinar ID si existe
    $id = extract_id_from_route($route);
    
    // Verificar si hay datos en caché
    $cached_data = flores_api_cache()->get($content_type, $id, $request->get_params());
    
    if ($cached_data !== null) {
        // Crear respuesta con datos en caché
        $response = new WP_REST_Response($cached_data, 200);
        $response->header('X-WP-From-Cache', 'true');
        return $response;
    }
    
    return null;
}

// Usar una prioridad más baja (20) para asegurarnos de que otros filtros se ejecuten primero
// Esto ayuda a evitar interferencias con plugins de autenticación
add_filter('rest_pre_dispatch', 'check_cache_before_rest_request', 20, 3);

/**
 * Identifica el tipo de contenido basado en la ruta de la API
 */
function identify_content_type_from_route($route) {
    if (preg_match('/\/wc\/v3\/products\/(\d+)/', $route)) {
        return 'product';
    } elseif (preg_match('/\/wc\/v3\/products/', $route)) {
        return 'products';
    } elseif (preg_match('/\/wc\/v3\/products\/categories\/(\d+)/', $route)) {
        return 'category';
    } elseif (preg_match('/\/wc\/v3\/products\/categories/', $route)) {
        return 'categories';
    } elseif (preg_match('/\/wp\/v2\/users\/(\d+)/', $route)) {
        return 'user';
    }
    
    return null;
}

/**
 * Extrae el ID de la ruta si existe
 */
function extract_id_from_route($route) {
    if (preg_match('/\/(\d+)(?:\/|$)/', $route, $matches)) {
        return $matches[1];
    }
    return null;
}

/**
 * Optimizar endpoint de productos para admitir paginación eficiente y lazy loading
 */
function optimize_products_endpoint($response, $handler, $request) {
    // Verificar si es el endpoint de productos
    if ($request->get_route() !== '/wc/v3/products') {
        return $response;
    }
    
    // Asegurarse de que sea una respuesta exitosa
    if (is_wp_error($response) || $response->get_status() !== 200) {
        return $response;
    }
    
    // Verificar si se solicitan campos específicos para optimizar respuesta
    $fields = $request->get_param('_fields');
    if (!$fields) {
        return $response;
    }
    
    // Convertir string de campos a array
    $fields_array = explode(',', $fields);
    
    // Filtrar sólo los campos solicitados
    $data = $response->get_data();
    $filtered_data = array();
    
    foreach ($data as $item) {
        $filtered_item = array();
        foreach ($fields_array as $field) {
            $field = trim($field);
            if (isset($item[$field])) {
                $filtered_item[$field] = $item[$field];
            }
        }
        $filtered_data[] = $filtered_item;
    }
    
    // Actualizar la respuesta con los datos filtrados
    $response->set_data($filtered_data);
    
    return $response;
}
add_filter('rest_request_after_callbacks', 'optimize_products_endpoint', 10, 3);

/**
 * Función para determinar si es una solicitud que debe evitar nuestros filtros de optimización
 * Esto incluye verificaciones para solicitudes de autenticación y administrativas
 */
function flores_should_bypass_api_optimization() {
    // Si es una solicitud AJAX, evitar caché
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return true;
    }
    
    // Verificar si es una solicitud administrativa
    if (is_admin()) {
        return true;
    }
    
    // Verificar si el usuario está actualizando algo
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] !== 'GET') {
        return true;
    }
    
    // Si no hay WP_REST_Request, no es una solicitud REST
    if (!function_exists('rest_get_url_prefix') || empty($_SERVER['REQUEST_URI'])) {
        return false;
    }
    
    // Verificar si la solicitud contiene el prefijo REST
    $rest_prefix = rest_get_url_prefix();
    if (strpos($_SERVER['REQUEST_URI'], "/{$rest_prefix}/") === false) {
        return false;
    }
    
    // Revisar si la ruta contiene patrones sensibles
    $sensitive_patterns = [
        'jwt-auth',
        'token',
        'login',
        'logout',
        'auth',
        'wc/v3/orders',
        'user/me',
        'users/me',
        'wp-admin'
    ];
    
    foreach ($sensitive_patterns as $pattern) {
        if (strpos($_SERVER['REQUEST_URI'], $pattern) !== false) {
            return true;
        }
    }
    
    return false;
}

// Modificamos los hooks para asegurar que se registren solo cuando sea apropiado
function flores_register_api_optimization_hooks() {
    // Si es una solicitud que debería evitar la optimización, no registrar hooks
    if (flores_should_bypass_api_optimization()) {
        return;
    }
    
    // Registrar los hooks solo si no estamos en una operación sensible
    add_filter('rest_pre_dispatch', 'check_cache_before_rest_request', 20, 3);
    add_filter('rest_request_after_callbacks', 'add_cache_to_rest_response', 20, 3);
    add_filter('rest_request_after_callbacks', 'optimize_products_endpoint', 20, 3);
}

// Registramos los hooks en la fase de init para tener acceso a más información
add_action('init', 'flores_register_api_optimization_hooks');

// Incluir el archivo en functions.php
// require_once get_template_directory() . '/inc/api-optimization.php';
