<?php
/**
 * API Endpoints para la gestión de productos en catálogos
 */

// Evitar acceso directo al archivo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Inicializar los endpoints para la gestión de productos de catálogos
 */
function floresinc_init_catalog_products_api() {
    add_action('rest_api_init', 'floresinc_register_catalog_products_endpoints');
}

/**
 * Registrar los endpoints de API para productos de catálogos
 */
function floresinc_register_catalog_products_endpoints() {
    // Namespace base para nuestros endpoints
    $namespace = 'floresinc/v1';
    
    // Endpoint para obtener los productos de un catálogo
    register_rest_route($namespace, '/catalogs/(?P<id>\d+)/products', [
        'methods' => 'GET',
        'callback' => 'floresinc_get_catalog_products_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
    
    // Endpoint para obtener los productos completos de un catálogo
    register_rest_route($namespace, '/catalogs/(?P<id>\d+)/complete-products', [
        'methods' => 'GET',
        'callback' => 'floresinc_get_catalog_complete_products_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
    
    // Endpoint para actualizar un producto específico del catálogo
    register_rest_route($namespace, '/catalogs/(?P<catalog_id>\d+)/products/(?P<product_id>\d+)', [
        'methods' => 'PUT',
        'callback' => 'floresinc_update_catalog_product_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
}

/**
 * Endpoint: Obtener productos de un catálogo
 */
function floresinc_get_catalog_products_endpoint(WP_REST_Request $request) {
    global $wpdb;
    
    $catalog_id = $request->get_param('id');
    $user_id = get_current_user_id();
    
    $catalog_table = $wpdb->prefix . 'floresinc_catalogs';
    $catalog_products_table = $wpdb->prefix . 'floresinc_catalog_products';
    
    // Verificar que el catálogo exista y pertenezca al usuario
    $catalog = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM $catalog_table WHERE id = %d AND user_id = %d
    ", $catalog_id, $user_id));
    
    if (!$catalog) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Catálogo no encontrado o no tienes permiso para acceder'
        ], 404);
    }
    
    // Obtener los productos del catálogo
    $products = $wpdb->get_results($wpdb->prepare("
        SELECT cp.* 
        FROM $catalog_products_table cp
        WHERE cp.catalog_id = %d
        ORDER BY cp.id ASC
    ", $catalog_id), ARRAY_A);
    
    // Formatear imágenes secundarias si existen
    if ($products) {
        foreach ($products as &$product) {
            if (!empty($product['catalog_images'])) {
                $product['catalog_images'] = json_decode($product['catalog_images'], true);
            } else {
                $product['catalog_images'] = [];
            }
        }
    }
    
    return new WP_REST_Response([
        'status' => 'success',
        'data' => $products
    ], 200);
}

/**
 * Endpoint: Obtener productos completos de un catálogo
 */
function floresinc_get_catalog_complete_products_endpoint(WP_REST_Request $request) {
    global $wpdb;
    
    $catalog_id = $request->get_param('id');
    $user_id = get_current_user_id();
    
    $catalog_table = $wpdb->prefix . 'floresinc_catalogs';
    $catalog_products_table = $wpdb->prefix . 'floresinc_catalog_products';
    
    // Verificar que el catálogo exista y pertenezca al usuario
    $catalog = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM $catalog_table WHERE id = %d AND user_id = %d
    ", $catalog_id, $user_id));
    
    if (!$catalog) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Catálogo no encontrado o no tienes permiso para acceder'
        ], 404);
    }
    
    // Obtener los productos del catálogo
    $catalog_products = $wpdb->get_results($wpdb->prepare("
        SELECT cp.* 
        FROM $catalog_products_table cp
        WHERE cp.catalog_id = %d
        ORDER BY cp.id ASC
    ", $catalog_id), ARRAY_A);
    
    if (empty($catalog_products)) {
        return new WP_REST_Response([
            'status' => 'success',
            'data' => []
        ], 200);
    }
    
    // Preparar la respuesta
    $complete_products = [];
    
    foreach ($catalog_products as $catalog_product) {
        // Formatear imágenes secundarias si existen
        if (!empty($catalog_product['catalog_images'])) {
            $catalog_product['catalog_images'] = json_decode($catalog_product['catalog_images'], true);
        } else {
            $catalog_product['catalog_images'] = [];
        }
        
        // Si es un producto personalizado, no necesitamos datos de WooCommerce
        if ($catalog_product['is_custom']) {
            $complete_products[] = [
                'id' => intval($catalog_product['id']),
                'product_id' => intval($catalog_product['product_id']),
                'name' => $catalog_product['catalog_name'],
                'price' => $catalog_product['catalog_price'],
                'description' => $catalog_product['catalog_description'],
                'short_description' => $catalog_product['catalog_short_description'],
                'sku' => $catalog_product['catalog_sku'],
                'catalog_price' => $catalog_product['catalog_price'],
                'product_price' => $catalog_product['product_price'],
                'catalog_image' => $catalog_product['catalog_image'],
                'catalog_images' => $catalog_product['catalog_images'],
                'catalog_name' => $catalog_product['catalog_name'],
                'catalog_description' => $catalog_product['catalog_description'],
                'catalog_short_description' => $catalog_product['catalog_short_description'],
                'catalog_sku' => $catalog_product['catalog_sku'],
                'is_custom' => true,
                'images' => [
                    [
                        'src' => $catalog_product['catalog_image']
                    ]
                ]
            ];
            continue;
        }
        
        // Para productos normales, obtener datos de WooCommerce
        try {
            $product_id = intval($catalog_product['product_id']);
            
            if ($product_id <= 0) {
                continue;
            }
            
            $woo_product = wc_get_product($product_id);
            
            if (!$woo_product) {
                // Producto no disponible, usar datos del catálogo
                $complete_products[] = [
                    'id' => intval($catalog_product['id']),
                    'product_id' => $product_id,
                    'name' => $catalog_product['catalog_name'] ?: 'Producto no disponible',
                    'price' => $catalog_product['catalog_price'],
                    'description' => $catalog_product['catalog_description'],
                    'short_description' => $catalog_product['catalog_short_description'],
                    'sku' => $catalog_product['catalog_sku'],
                    'catalog_price' => $catalog_product['catalog_price'],
                    'product_price' => $catalog_product['product_price'],
                    'catalog_image' => $catalog_product['catalog_image'],
                    'catalog_images' => $catalog_product['catalog_images'],
                    'catalog_name' => $catalog_product['catalog_name'],
                    'catalog_description' => $catalog_product['catalog_description'],
                    'catalog_short_description' => $catalog_product['catalog_short_description'],
                    'catalog_sku' => $catalog_product['catalog_sku'],
                    'is_unavailable' => true,
                    'images' => []
                ];
                continue;
            }
            
            // Obtener información del producto de WooCommerce
            $product_data = [
                'id' => intval($catalog_product['id']),
                'product_id' => $product_id,
                'woo_id' => $product_id,
                'name' => $woo_product->get_name(),
                'price' => $woo_product->get_price(),
                'regular_price' => $woo_product->get_regular_price(),
                'description' => $woo_product->get_description(),
                'short_description' => $woo_product->get_short_description(),
                'sku' => $woo_product->get_sku(),
                'stock_quantity' => $woo_product->get_stock_quantity(),
                'catalog_price' => $catalog_product['catalog_price'],
                'product_price' => $catalog_product['product_price'],
                'catalog_image' => $catalog_product['catalog_image'],
                'catalog_images' => $catalog_product['catalog_images'],
                'catalog_name' => $catalog_product['catalog_name'],
                'catalog_description' => $catalog_product['catalog_description'],
                'catalog_short_description' => $catalog_product['catalog_short_description'],
                'catalog_sku' => $catalog_product['catalog_sku']
            ];
            
            // Obtener imágenes del producto
            $product_data['images'] = [];
            $attachment_ids = $woo_product->get_gallery_image_ids();
            
            // Agregar imagen principal
            $image_id = $woo_product->get_image_id();
            if ($image_id) {
                $image_url = wp_get_attachment_url($image_id);
                $product_data['images'][] = [
                    'id' => $image_id,
                    'src' => $image_url
                ];
            }
            
            // Agregar imágenes adicionales
            foreach ($attachment_ids as $attachment_id) {
                $image_url = wp_get_attachment_url($attachment_id);
                $product_data['images'][] = [
                    'id' => $attachment_id,
                    'src' => $image_url
                ];
            }
            
            $complete_products[] = $product_data;
            
        } catch (Exception $e) {
            error_log('Error al obtener producto completo: ' . $e->getMessage());
            
            // En caso de error, usar los datos del catálogo
            $complete_products[] = [
                'id' => intval($catalog_product['id']),
                'product_id' => intval($catalog_product['product_id']),
                'name' => $catalog_product['catalog_name'] ?: 'Producto no disponible',
                'price' => $catalog_product['catalog_price'],
                'description' => $catalog_product['catalog_description'],
                'short_description' => $catalog_product['catalog_short_description'],
                'sku' => $catalog_product['catalog_sku'],
                'catalog_price' => $catalog_product['catalog_price'],
                'product_price' => $catalog_product['product_price'],
                'catalog_image' => $catalog_product['catalog_image'],
                'catalog_images' => $catalog_product['catalog_images'],
                'catalog_name' => $catalog_product['catalog_name'],
                'catalog_description' => $catalog_product['catalog_description'],
                'catalog_short_description' => $catalog_product['catalog_short_description'],
                'catalog_sku' => $catalog_product['catalog_sku'],
                'error' => true,
                'images' => []
            ];
        }
    }
    
    return new WP_REST_Response([
        'status' => 'success',
        'data' => $complete_products
    ], 200);
}

/**
 * Endpoint: Actualizar un producto específico del catálogo
 */
function floresinc_update_catalog_product_endpoint(WP_REST_Request $request) {
    global $wpdb;
    
    $catalog_id = $request->get_param('catalog_id');
    $catalog_product_id = $request->get_param('product_id');
    $params = $request->get_json_params();
    $user_id = get_current_user_id();
    
    $catalog_table = $wpdb->prefix . 'floresinc_catalogs';
    $catalog_products_table = $wpdb->prefix . 'floresinc_catalog_products';
    
    // Verificar que el catálogo exista y pertenezca al usuario
    $catalog = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM $catalog_table WHERE id = %d AND user_id = %d
    ", $catalog_id, $user_id));
    
    if (!$catalog) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Catálogo no encontrado o no tienes permiso para acceder'
        ], 404);
    }
    
    // Verificar que el producto exista en el catálogo
    $catalog_product = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM $catalog_products_table WHERE id = %d AND catalog_id = %d
    ", $catalog_product_id, $catalog_id));
    
    if (!$catalog_product) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Producto no encontrado en el catálogo'
        ], 404);
    }
    
    // Datos a actualizar
    $update_data = [];
    $update_format = [];
    
    // Campos que se pueden actualizar
    $allowed_fields = [
        'product_price' => '%f',
        'catalog_price' => '%f',
        'catalog_name' => '%s',
        'catalog_description' => '%s',
        'catalog_short_description' => '%s',
        'catalog_sku' => '%s',
        'catalog_image' => '%s'
    ];
    
    // Campos especiales
    if (isset($params['catalog_images']) && is_array($params['catalog_images'])) {
        $update_data['catalog_images'] = json_encode($params['catalog_images']);
        $update_format[] = '%s';
    }
    
    // Procesar los campos normales
    foreach ($allowed_fields as $field => $format) {
        if (isset($params[$field])) {
            $update_data[$field] = $params[$field];
            $update_format[] = $format;
        }
    }
    
    // Si no hay datos para actualizar, retornar error
    if (empty($update_data)) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'No se proporcionaron datos para actualizar'
        ], 400);
    }
    
    // Actualizar el producto
    $result = $wpdb->update(
        $catalog_products_table,
        $update_data,
        ['id' => $catalog_product_id],
        $update_format,
        ['%d']
    );
    
    if ($result === false) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Error al actualizar el producto: ' . $wpdb->last_error
        ], 500);
    }
    
    // Obtener el producto actualizado
    $updated_product = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM $catalog_products_table WHERE id = %d
    ", $catalog_product_id), ARRAY_A);
    
    // Formatear imágenes secundarias si existen
    if (!empty($updated_product['catalog_images'])) {
        $updated_product['catalog_images'] = json_decode($updated_product['catalog_images'], true);
    } else {
        $updated_product['catalog_images'] = [];
    }
    
    return new WP_REST_Response([
        'status' => 'success',
        'message' => 'Producto actualizado con éxito',
        'data' => $updated_product
    ], 200);
}
