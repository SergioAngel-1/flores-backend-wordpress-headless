<?php
/**
 * API Endpoints para la gestión de productos personalizados
 */

// Evitar acceso directo al archivo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Inicializar los endpoints para la gestión de productos personalizados
 */
function floresinc_init_custom_products_api() {
    add_action('rest_api_init', 'floresinc_register_custom_products_endpoints');
}

/**
 * Registrar los endpoints de API para productos personalizados
 */
function floresinc_register_custom_products_endpoints() {
    // Namespace base para nuestros endpoints
    $namespace = 'floresinc/v1';
    
    // Endpoint para crear un producto personalizado
    register_rest_route($namespace, '/catalogs/(?P<catalog_id>\d+)/custom-products', [
        'methods' => 'POST',
        'callback' => 'floresinc_create_custom_product_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
    
    // Endpoint para actualizar un producto personalizado
    register_rest_route($namespace, '/catalogs/custom-products/(?P<id>\d+)', [
        'methods' => 'PUT',
        'callback' => 'floresinc_update_custom_product_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
    
    // Endpoint para obtener un producto personalizado
    register_rest_route($namespace, '/catalogs/custom-products/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'floresinc_get_custom_product_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
}

/**
 * Endpoint: Crear un producto personalizado para un catálogo
 */
function floresinc_create_custom_product_endpoint(WP_REST_Request $request) {
    global $wpdb;
    
    $catalog_id = $request->get_param('catalog_id');
    $params = $request->get_json_params();
    $user_id = get_current_user_id();
    
    // Validar los parámetros requeridos
    if (empty($params['name'])) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Nombre del producto requerido'
        ], 400);
    }
    
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
    
    // Crear un ID negativo para el producto personalizado, para identificarlo en el front
    $negative_id = -1 * abs(time());
    
    // Preparar datos del producto personalizado
    $name = sanitize_text_field($params['name']);
    $price = isset($params['price']) ? floatval($params['price']) : 0;
    $description = isset($params['description']) ? wp_kses_post($params['description']) : '';
    $short_description = isset($params['short_description']) ? wp_kses_post($params['short_description']) : '';
    $sku = isset($params['sku']) ? sanitize_text_field($params['sku']) : '';
    $image = isset($params['image']) ? esc_url_raw($params['image']) : '';
    
    // Procesar imágenes secundarias
    $images = [];
    if (isset($params['images']) && is_array($params['images'])) {
        foreach ($params['images'] as $img) {
            if (is_string($img) && !empty($img)) {
                $images[] = esc_url_raw($img);
            }
        }
    }
    
    // Insertar en la tabla de productos del catálogo
    $insert_data = [
        'catalog_id' => $catalog_id,
        'product_id' => $negative_id, // ID negativo para productos personalizados
        'product_price' => $price,
        'catalog_price' => $price,
        'catalog_name' => $name,
        'catalog_description' => $description,
        'catalog_short_description' => $short_description,
        'catalog_sku' => $sku,
        'catalog_image' => $image,
        'catalog_images' => !empty($images) ? json_encode($images) : null,
        'is_custom' => 1
    ];
    
    $insert_format = [
        '%d', // catalog_id
        '%d', // product_id
        '%f', // product_price
        '%f', // catalog_price
        '%s', // catalog_name
        '%s', // catalog_description
        '%s', // catalog_short_description
        '%s', // catalog_sku
        '%s', // catalog_image
        '%s', // catalog_images
        '%d'  // is_custom
    ];
    
    // Iniciar transacción
    $wpdb->query('START TRANSACTION');
    
    try {
        // Insertar el producto personalizado
        $wpdb->insert(
            $catalog_products_table,
            $insert_data,
            $insert_format
        );
        
        $custom_product_id = $wpdb->insert_id;
        
        if (!$custom_product_id) {
            throw new Exception('Error al crear producto personalizado: ' . $wpdb->last_error);
        }
        
        // Obtener el producto creado
        $custom_product = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $catalog_products_table WHERE id = %d
        ", $custom_product_id), ARRAY_A);
        
        // Formatear imágenes secundarias
        if (!empty($custom_product['catalog_images'])) {
            $custom_product['catalog_images'] = json_decode($custom_product['catalog_images'], true);
        } else {
            $custom_product['catalog_images'] = [];
        }
        
        // Confirmar la transacción
        $wpdb->query('COMMIT');
        
        // Formatear la respuesta para que sea compatible con la estructura esperada
        $response_data = [
            'id' => intval($custom_product['id']),
            'product_id' => intval($custom_product['product_id']),
            'name' => $custom_product['catalog_name'],
            'price' => $custom_product['catalog_price'],
            'description' => $custom_product['catalog_description'],
            'short_description' => $custom_product['catalog_short_description'],
            'sku' => $custom_product['catalog_sku'],
            'catalog_price' => $custom_product['catalog_price'],
            'product_price' => $custom_product['product_price'],
            'catalog_image' => $custom_product['catalog_image'],
            'catalog_images' => $custom_product['catalog_images'],
            'catalog_name' => $custom_product['catalog_name'],
            'catalog_description' => $custom_product['catalog_description'],
            'catalog_short_description' => $custom_product['catalog_short_description'],
            'catalog_sku' => $custom_product['catalog_sku'],
            'is_custom' => true,
            'images' => [
                [
                    'src' => $custom_product['catalog_image']
                ]
            ]
        ];
        
        return new WP_REST_Response([
            'status' => 'success',
            'message' => 'Producto personalizado creado con éxito',
            'data' => $response_data
        ], 201);
        
    } catch (Exception $e) {
        // Revertir en caso de error
        $wpdb->query('ROLLBACK');
        
        return new WP_REST_Response([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
}

/**
 * Endpoint: Actualizar un producto personalizado
 */
function floresinc_update_custom_product_endpoint(WP_REST_Request $request) {
    global $wpdb;
    
    $custom_product_id = $request->get_param('id');
    $params = $request->get_json_params();
    $user_id = get_current_user_id();
    
    // Validar los parámetros requeridos
    if (empty($params['name'])) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Nombre del producto requerido'
        ], 400);
    }
    
    $catalog_table = $wpdb->prefix . 'floresinc_catalogs';
    $catalog_products_table = $wpdb->prefix . 'floresinc_catalog_products';
    
    // Verificar que el producto personalizado exista
    $custom_product = $wpdb->get_row($wpdb->prepare("
        SELECT cp.*, c.user_id
        FROM $catalog_products_table cp
        JOIN $catalog_table c ON cp.catalog_id = c.id
        WHERE cp.id = %d AND cp.is_custom = 1
    ", $custom_product_id));
    
    if (!$custom_product) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Producto personalizado no encontrado'
        ], 404);
    }
    
    // Verificar que el usuario tenga permiso para editar
    if ($custom_product->user_id != $user_id) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'No tienes permiso para editar este producto'
        ], 403);
    }
    
    // Preparar datos del producto personalizado
    $name = sanitize_text_field($params['name']);
    $price = isset($params['price']) ? floatval($params['price']) : 0;
    $description = isset($params['description']) ? wp_kses_post($params['description']) : '';
    $short_description = isset($params['short_description']) ? wp_kses_post($params['short_description']) : '';
    $sku = isset($params['sku']) ? sanitize_text_field($params['sku']) : '';
    $image = isset($params['image']) ? esc_url_raw($params['image']) : '';
    
    // Procesar imágenes secundarias
    $images = [];
    if (isset($params['images']) && is_array($params['images'])) {
        foreach ($params['images'] as $img) {
            if (is_string($img) && !empty($img)) {
                $images[] = esc_url_raw($img);
            }
        }
    }
    
    // Datos a actualizar
    $update_data = [
        'product_price' => $price,
        'catalog_price' => $price,
        'catalog_name' => $name,
        'catalog_description' => $description,
        'catalog_short_description' => $short_description,
        'catalog_sku' => $sku,
        'catalog_image' => $image,
        'catalog_images' => !empty($images) ? json_encode($images) : null
    ];
    
    $update_format = [
        '%f', // product_price
        '%f', // catalog_price
        '%s', // catalog_name
        '%s', // catalog_description
        '%s', // catalog_short_description
        '%s', // catalog_sku
        '%s', // catalog_image
        '%s'  // catalog_images
    ];
    
    // Actualizar el producto personalizado
    $result = $wpdb->update(
        $catalog_products_table,
        $update_data,
        ['id' => $custom_product_id],
        $update_format,
        ['%d']
    );
    
    if ($result === false) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Error al actualizar el producto personalizado: ' . $wpdb->last_error
        ], 500);
    }
    
    // Obtener el producto actualizado
    $updated_product = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM $catalog_products_table WHERE id = %d
    ", $custom_product_id), ARRAY_A);
    
    // Formatear imágenes secundarias
    if (!empty($updated_product['catalog_images'])) {
        $updated_product['catalog_images'] = json_decode($updated_product['catalog_images'], true);
    } else {
        $updated_product['catalog_images'] = [];
    }
    
    // Formatear la respuesta para que sea compatible con la estructura esperada
    $response_data = [
        'id' => intval($updated_product['id']),
        'product_id' => intval($updated_product['product_id']),
        'name' => $updated_product['catalog_name'],
        'price' => $updated_product['catalog_price'],
        'description' => $updated_product['catalog_description'],
        'short_description' => $updated_product['catalog_short_description'],
        'sku' => $updated_product['catalog_sku'],
        'catalog_price' => $updated_product['catalog_price'],
        'product_price' => $updated_product['product_price'],
        'catalog_image' => $updated_product['catalog_image'],
        'catalog_images' => $updated_product['catalog_images'],
        'catalog_name' => $updated_product['catalog_name'],
        'catalog_description' => $updated_product['catalog_description'],
        'catalog_short_description' => $updated_product['catalog_short_description'],
        'catalog_sku' => $updated_product['catalog_sku'],
        'is_custom' => true,
        'images' => [
            [
                'src' => $updated_product['catalog_image']
            ]
        ]
    ];
    
    return new WP_REST_Response([
        'status' => 'success',
        'message' => 'Producto personalizado actualizado con éxito',
        'data' => $response_data
    ], 200);
}

/**
 * Endpoint: Obtener un producto personalizado
 */
function floresinc_get_custom_product_endpoint(WP_REST_Request $request) {
    global $wpdb;
    
    $custom_product_id = $request->get_param('id');
    $user_id = get_current_user_id();
    
    $catalog_table = $wpdb->prefix . 'floresinc_catalogs';
    $catalog_products_table = $wpdb->prefix . 'floresinc_catalog_products';
    
    // Verificar que el producto personalizado exista y pertenezca al usuario
    $custom_product = $wpdb->get_row($wpdb->prepare("
        SELECT cp.*
        FROM $catalog_products_table cp
        JOIN $catalog_table c ON cp.catalog_id = c.id
        WHERE cp.id = %d AND cp.is_custom = 1 AND c.user_id = %d
    ", $custom_product_id, $user_id), ARRAY_A);
    
    if (!$custom_product) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Producto personalizado no encontrado o no tienes permiso para acceder'
        ], 404);
    }
    
    // Formatear imágenes secundarias
    if (!empty($custom_product['catalog_images'])) {
        $custom_product['catalog_images'] = json_decode($custom_product['catalog_images'], true);
    } else {
        $custom_product['catalog_images'] = [];
    }
    
    // Formatear la respuesta para que sea compatible con la estructura esperada
    $response_data = [
        'id' => intval($custom_product['id']),
        'product_id' => intval($custom_product['product_id']),
        'name' => $custom_product['catalog_name'],
        'price' => $custom_product['catalog_price'],
        'description' => $custom_product['catalog_description'],
        'short_description' => $custom_product['catalog_short_description'],
        'sku' => $custom_product['catalog_sku'],
        'catalog_price' => $custom_product['catalog_price'],
        'product_price' => $custom_product['product_price'],
        'catalog_image' => $custom_product['catalog_image'],
        'catalog_images' => $custom_product['catalog_images'],
        'catalog_name' => $custom_product['catalog_name'],
        'catalog_description' => $custom_product['catalog_description'],
        'catalog_short_description' => $custom_product['catalog_short_description'],
        'catalog_sku' => $custom_product['catalog_sku'],
        'is_custom' => true,
        'images' => [
            [
                'src' => $custom_product['catalog_image']
            ]
        ]
    ];
    
    return new WP_REST_Response([
        'status' => 'success',
        'data' => $response_data
    ], 200);
}
