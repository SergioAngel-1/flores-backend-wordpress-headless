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
    
    // Verificar que el catálogo exista (sin restricción de usuario)
    $catalog = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM $catalog_table WHERE id = %d
    ", $catalog_id));
    
    if (!$catalog) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Catálogo no encontrado'
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
    
    // Establecer encabezados para evitar problemas de caché
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("Solicitando productos completos del catálogo ID: {$catalog_id}");
    }
    
    $catalog_table = $wpdb->prefix . 'floresinc_catalogs';
    $catalog_products_table = $wpdb->prefix . 'floresinc_catalog_products';
    
    // Verificar si el catálogo existe (sin restricción de usuario)
    $catalog = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM $catalog_table WHERE id = %d
    ", $catalog_id));
    
    // Si no existe el catálogo, devolver error
    if (!$catalog) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Error: Catálogo ID {$catalog_id} no encontrado");
        }
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Catálogo no encontrado'
        ], 404);
    }
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("Catálogo ID {$catalog_id} encontrado: " . $catalog->name);
    }
    
    // Obtener los productos del catálogo
    $catalog_products = $wpdb->get_results($wpdb->prepare("
        SELECT cp.* 
        FROM $catalog_products_table cp
        WHERE cp.catalog_id = %d
        ORDER BY cp.id ASC
    ", $catalog_id), ARRAY_A);
    
    if (empty($catalog_products)) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Catálogo ID {$catalog_id} no tiene productos asociados");
        }
        return new WP_REST_Response([
            'status' => 'success',
            'data' => []
        ], 200);
    }
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("Encontrados " . count($catalog_products) . " productos para el catálogo ID {$catalog_id}");
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
                    'is_custom' => false,
                    'images' => [
                        [
                            'src' => $catalog_product['catalog_image']
                        ]
                    ]
                ];
                continue;
            }
            
            // Obtener datos del producto WooCommerce
            $woo_name = $woo_product->get_name();
            $woo_description = $woo_product->get_description();
            $woo_short_description = $woo_product->get_short_description();
            $woo_sku = $woo_product->get_sku();
            $woo_price = $woo_product->get_price();
            
            // Dar prioridad a los datos del catálogo si están presentes
            $name = !empty($catalog_product['catalog_name']) ? $catalog_product['catalog_name'] : $woo_name;
            $description = !empty($catalog_product['catalog_description']) ? $catalog_product['catalog_description'] : $woo_description;
            $short_description = !empty($catalog_product['catalog_short_description']) ? $catalog_product['catalog_short_description'] : $woo_short_description;
            $sku = !empty($catalog_product['catalog_sku']) ? $catalog_product['catalog_sku'] : $woo_sku;
            $price = !empty($catalog_product['catalog_price']) ? $catalog_product['catalog_price'] : $woo_price;
            
            // Obtener imágenes del producto WooCommerce
            $woo_image = wp_get_attachment_image_url($woo_product->get_image_id(), 'full');
            $woo_gallery_image_ids = $woo_product->get_gallery_image_ids();
            $woo_gallery_images = [];
            
            foreach ($woo_gallery_image_ids as $gallery_image_id) {
                $image_url = wp_get_attachment_image_url($gallery_image_id, 'full');
                if ($image_url) {
                    $woo_gallery_images[] = $image_url;
                }
            }
            
            // Dar prioridad a las imágenes del catálogo
            $image = !empty($catalog_product['catalog_image']) ? $catalog_product['catalog_image'] : $woo_image;
            $gallery_images = !empty($catalog_product['catalog_images']) ? $catalog_product['catalog_images'] : $woo_gallery_images;
            
            // Preparar imágenes para la respuesta en el formato esperado
            $formatted_images = [];
            if ($image) {
                $formatted_images[] = ['src' => $image];
            }
            
            if (is_array($gallery_images)) {
                foreach ($gallery_images as $gallery_image) {
                    if (is_array($gallery_image) && !empty($gallery_image['src'])) {
                        $formatted_images[] = $gallery_image;
                    } elseif (is_string($gallery_image)) {
                        $formatted_images[] = ['src' => $gallery_image];
                    }
                }
            }
            
            // Añadir el producto completo a la respuesta
            $complete_products[] = [
                'id' => intval($catalog_product['id']),
                'product_id' => $product_id,
                'name' => $name,
                'price' => $price,
                'description' => $description,
                'short_description' => $short_description,
                'sku' => $sku,
                'catalog_price' => $catalog_product['catalog_price'],
                'product_price' => $catalog_product['product_price'],
                'catalog_image' => $catalog_product['catalog_image'],
                'catalog_images' => $catalog_product['catalog_images'],
                'catalog_name' => $catalog_product['catalog_name'],
                'catalog_description' => $catalog_product['catalog_description'],
                'catalog_short_description' => $catalog_product['catalog_short_description'],
                'catalog_sku' => $catalog_product['catalog_sku'],
                'is_custom' => false,
                'images' => $formatted_images
            ];
        } catch (Exception $e) {
            // Error al obtener el producto, usar datos del catálogo
            $complete_products[] = [
                'id' => intval($catalog_product['id']),
                'product_id' => intval($catalog_product['product_id']),
                'name' => $catalog_product['catalog_name'] ?: 'Error al cargar producto',
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
                'is_custom' => false,
                'images' => [
                    [
                        'src' => $catalog_product['catalog_image']
                    ]
                ],
                'error' => $e->getMessage()
            ];
        }
    }
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("Retornando " . count($complete_products) . " productos procesados para el catálogo ID {$catalog_id}");
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
    $product_id = $request->get_param('product_id');
    $user_id = get_current_user_id();
    
    // Validar que se recibieron los datos necesarios
    $params = $request->get_params();
    if (empty($params)) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'No se recibieron datos para actualizar'
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
    
    // Verificar que el producto exista en el catálogo
    $product = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM $catalog_products_table WHERE catalog_id = %d AND id = %d
    ", $catalog_id, $product_id));
    
    if (!$product) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Producto no encontrado en el catálogo'
        ], 404);
    }
    
    // Datos que se pueden actualizar
    $update_data = [];
    
    // Actualizar precio personalizado si se proporciona
    if (isset($params['catalog_price'])) {
        $update_data['catalog_price'] = floatval($params['catalog_price']);
    }
    
    // Actualizar nombre personalizado si se proporciona
    if (isset($params['catalog_name'])) {
        $update_data['catalog_name'] = sanitize_text_field($params['catalog_name']);
    }
    
    // Actualizar descripción personalizada si se proporciona
    if (isset($params['catalog_description'])) {
        $update_data['catalog_description'] = wp_kses_post($params['catalog_description']);
    }
    
    // Actualizar descripción corta personalizada si se proporciona
    if (isset($params['catalog_short_description'])) {
        $update_data['catalog_short_description'] = wp_kses_post($params['catalog_short_description']);
    }
    
    // Actualizar SKU personalizado si se proporciona
    if (isset($params['catalog_sku'])) {
        $update_data['catalog_sku'] = sanitize_text_field($params['catalog_sku']);
    }
    
    // Actualizar imagen personalizada si se proporciona
    if (isset($params['catalog_image'])) {
        $update_data['catalog_image'] = esc_url_raw($params['catalog_image']);
    }
    
    // Actualizar imágenes secundarias personalizadas si se proporcionan
    if (isset($params['catalog_images']) && is_array($params['catalog_images'])) {
        $update_data['catalog_images'] = json_encode(array_map('esc_url_raw', $params['catalog_images']));
    }
    
    // Si no hay datos para actualizar, devolver error
    if (empty($update_data)) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'No se proporcionaron datos válidos para actualizar'
        ], 400);
    }
    
    // Actualizar el producto en la base de datos
    $result = $wpdb->update(
        $catalog_products_table,
        $update_data,
        [
            'catalog_id' => $catalog_id,
            'id' => $product_id
        ]
    );
    
    if ($result === false) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Error al actualizar el producto en el catálogo',
            'error' => $wpdb->last_error
        ], 500);
    }
    
    // Obtener el producto actualizado
    $updated_product = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM $catalog_products_table WHERE catalog_id = %d AND id = %d
    ", $catalog_id, $product_id), ARRAY_A);
    
    // Formatear imágenes secundarias si existen
    if (!empty($updated_product['catalog_images'])) {
        $updated_product['catalog_images'] = json_decode($updated_product['catalog_images'], true);
    } else {
        $updated_product['catalog_images'] = [];
    }
    
    return new WP_REST_Response([
        'status' => 'success',
        'message' => 'Producto actualizado correctamente',
        'data' => $updated_product
    ], 200);
}
