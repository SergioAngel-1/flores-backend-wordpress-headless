<?php
/**
 * API Endpoints para la gestión de catálogos
 */

// Evitar acceso directo al archivo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Inicializar los endpoints para la gestión de catálogos
 */
function floresinc_init_catalogs_api() {
    add_action('rest_api_init', 'floresinc_register_catalogs_endpoints');
}

/**
 * Registrar los endpoints de API para catálogos
 */
function floresinc_register_catalogs_endpoints() {
    // Namespace base para nuestros endpoints
    $namespace = 'floresinc/v1';
    
    // Endpoint para obtener todos los catálogos
    register_rest_route($namespace, '/catalogs', [
        'methods' => 'GET',
        'callback' => 'floresinc_get_catalogs_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
    
    // Endpoint para obtener un catálogo específico
    register_rest_route($namespace, '/catalogs/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'floresinc_get_catalog_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
    
    // Endpoint para crear un nuevo catálogo
    register_rest_route($namespace, '/catalogs', [
        'methods' => 'POST',
        'callback' => 'floresinc_create_catalog_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
    
    // Endpoint para actualizar un catálogo existente
    register_rest_route($namespace, '/catalogs/(?P<id>\d+)', [
        'methods' => 'PUT',
        'callback' => 'floresinc_update_catalog_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
    
    // Endpoint para eliminar un catálogo
    register_rest_route($namespace, '/catalogs/(?P<id>\d+)', [
        'methods' => 'DELETE',
        'callback' => 'floresinc_delete_catalog_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
    
    // Endpoint para actualizar manualmente la estructura de la tabla
    register_rest_route($namespace, '/catalogs/update-tables', [
        'methods' => 'POST',
        'callback' => 'floresinc_update_catalog_tables_endpoint',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ]);
}

/**
 * Endpoint: Obtener todos los catálogos
 */
function floresinc_get_catalogs_endpoint(WP_REST_Request $request) {
    global $wpdb;
    
    $user_id = get_current_user_id();
    $catalog_table = $wpdb->prefix . 'floresinc_catalogs';
    $catalog_products_table = $wpdb->prefix . 'floresinc_catalog_products';
    
    // Consultar solo catálogos del usuario actual
    $catalogs = $wpdb->get_results($wpdb->prepare("
        SELECT c.*, (
            SELECT COUNT(*) 
            FROM $catalog_products_table cp 
            WHERE cp.catalog_id = c.id
        ) as product_count
        FROM $catalog_table c
        WHERE c.user_id = %d
        ORDER BY c.created_at DESC
    ", $user_id), ARRAY_A);
    
    if (!$catalogs) {
        return new WP_REST_Response([
            'status' => 'success',
            'data' => []
        ], 200);
    }
    
    return new WP_REST_Response([
        'status' => 'success',
        'data' => $catalogs
    ], 200);
}

/**
 * Endpoint: Obtener un catálogo específico
 */
function floresinc_get_catalog_endpoint(WP_REST_Request $request) {
    global $wpdb;
    
    $catalog_id = $request->get_param('id');
    $user_id = get_current_user_id();
    
    $catalog_table = $wpdb->prefix . 'floresinc_catalogs';
    $catalog_products_table = $wpdb->prefix . 'floresinc_catalog_products';
    
    // Consultar el catálogo del usuario actual
    $catalog = $wpdb->get_row($wpdb->prepare("
        SELECT c.*, (
            SELECT COUNT(*) 
            FROM $catalog_products_table cp 
            WHERE cp.catalog_id = c.id
        ) as product_count
        FROM $catalog_table c
        WHERE c.id = %d AND c.user_id = %d
    ", $catalog_id, $user_id), ARRAY_A);
    
    if (!$catalog) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Catálogo no encontrado'
        ], 404);
    }
    
    return new WP_REST_Response([
        'status' => 'success',
        'data' => $catalog
    ], 200);
}

/**
 * Endpoint: Crear un nuevo catálogo
 */
function floresinc_create_catalog_endpoint(WP_REST_Request $request) {
    global $wpdb;
    
    $params = $request->get_json_params();
    
    // Validar los parámetros
    if (empty($params['name'])) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Nombre de catálogo requerido'
        ], 400);
    }
    
    $name = sanitize_text_field($params['name']);
    $products = isset($params['products']) ? $params['products'] : [];
    $logo_url = isset($params['logo_url']) ? sanitize_text_field($params['logo_url']) : null;
    $user_id = get_current_user_id();
    $catalog_table = $wpdb->prefix . 'floresinc_catalogs';
    $catalog_products_table = $wpdb->prefix . 'floresinc_catalog_products';
    
    // Iniciar transacción
    $wpdb->query('START TRANSACTION');
    
    try {
        // Insertar el catálogo
        $wpdb->insert(
            $catalog_table,
            [
                'name' => $name,
                'logo_url' => $logo_url,
                'user_id' => $user_id
            ],
            ['%s', '%s', '%d']
        );
        
        $catalog_id = $wpdb->insert_id;
        
        if (!$catalog_id) {
            throw new Exception('Error al crear el catálogo: ' . $wpdb->last_error);
        }
        
        // Procesar los productos
        $products_added = 0;
        
        if (!empty($products)) {
            foreach ($products as $product) {
                // Validar datos del producto
                if (empty($product['product_id']) && !isset($product['is_custom'])) {
                    continue;
                }
                
                // Sanear los datos del producto
                $product_id = isset($product['product_id']) ? intval($product['product_id']) : 0;
                
                // Preparar los datos para la inserción
                $insert_data = [
                    'catalog_id' => $catalog_id,
                    'product_id' => $product_id
                ];
                
                $insert_format = ['%d', '%d'];
                
                // Agregar campos opcionales si existen
                $optional_fields = [
                    'product_price', 'catalog_price', 'catalog_name', 
                    'catalog_description', 'catalog_short_description', 
                    'catalog_sku', 'catalog_image', 'catalog_images', 'is_custom'
                ];
                
                foreach ($optional_fields as $field) {
                    if (isset($product[$field])) {
                        if ($field === 'catalog_images' && is_array($product[$field])) {
                            $insert_data[$field] = json_encode($product[$field]);
                            $insert_format[] = '%s';
                        } else if ($field === 'is_custom') {
                            $insert_data[$field] = (bool)$product[$field] ? 1 : 0;
                            $insert_format[] = '%d';
                        } else {
                            $insert_data[$field] = $product[$field];
                            $insert_format[] = is_numeric($product[$field]) ? '%f' : '%s';
                        }
                    }
                }
                
                // Insertar el producto en el catálogo
                $wpdb->insert(
                    $catalog_products_table,
                    $insert_data,
                    $insert_format
                );
                
                if ($wpdb->insert_id) {
                    $products_added++;
                } else {
                    error_log('Error al insertar producto en catálogo ' . $catalog_id . ': ' . $wpdb->last_error);
                }
            }
        }
        
        // Obtener el catálogo creado con el conteo de productos
        $catalog = $wpdb->get_row($wpdb->prepare("
            SELECT c.*, (
                SELECT COUNT(*) 
                FROM $catalog_products_table cp 
                WHERE cp.catalog_id = c.id
            ) as product_count
            FROM $catalog_table c
            WHERE c.id = %d
        ", $catalog_id), ARRAY_A);
        
        // Confirmar la transacción
        $wpdb->query('COMMIT');
        
        return new WP_REST_Response([
            'status' => 'success',
            'message' => 'Catálogo creado con éxito',
            'data' => $catalog,
            'products_added' => $products_added
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
 * Endpoint: Actualizar un catálogo existente
 */
function floresinc_update_catalog_endpoint(WP_REST_Request $request) {
    global $wpdb;
    
    $catalog_id = $request->get_param('id');
    $params = $request->get_json_params();
    $user_id = get_current_user_id();
    
    // Validar los parámetros
    if (empty($params['name'])) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Nombre de catálogo requerido'
        ], 400);
    }
    
    $name = sanitize_text_field($params['name']);
    $products = isset($params['products']) ? $params['products'] : [];
    $logo_url = isset($params['logo_url']) ? sanitize_text_field($params['logo_url']) : null;
    
    $catalog_table = $wpdb->prefix . 'floresinc_catalogs';
    $catalog_products_table = $wpdb->prefix . 'floresinc_catalog_products';
    
    // Verificar que el catálogo exista y pertenezca al usuario
    $catalog = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM $catalog_table WHERE id = %d AND user_id = %d
    ", $catalog_id, $user_id));
    
    if (!$catalog) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Catálogo no encontrado o no tienes permiso para editarlo'
        ], 404);
    }
    
    // Iniciar transacción
    $wpdb->query('START TRANSACTION');
    
    try {
        // Actualizar el catálogo
        $wpdb->update(
            $catalog_table,
            [
                'name' => $name,
                'logo_url' => $logo_url
            ],
            ['id' => $catalog_id],
            ['%s', '%s'],
            ['%d']
        );
        
        // Eliminar los productos actuales del catálogo
        $wpdb->delete(
            $catalog_products_table,
            ['catalog_id' => $catalog_id],
            ['%d']
        );
        
        // Procesar los productos
        $products_added = 0;
        
        if (!empty($products)) {
            foreach ($products as $product) {
                // Validar datos del producto
                if (empty($product['product_id']) && !isset($product['is_custom'])) {
                    continue;
                }
                
                // Sanear los datos del producto
                $product_id = isset($product['product_id']) ? intval($product['product_id']) : 0;
                
                // Preparar los datos para la inserción
                $insert_data = [
                    'catalog_id' => $catalog_id,
                    'product_id' => $product_id
                ];
                
                $insert_format = ['%d', '%d'];
                
                // Agregar campos opcionales si existen
                $optional_fields = [
                    'product_price', 'catalog_price', 'catalog_name', 
                    'catalog_description', 'catalog_short_description', 
                    'catalog_sku', 'catalog_image', 'catalog_images', 'is_custom'
                ];
                
                foreach ($optional_fields as $field) {
                    if (isset($product[$field])) {
                        if ($field === 'catalog_images' && is_array($product[$field])) {
                            $insert_data[$field] = json_encode($product[$field]);
                            $insert_format[] = '%s';
                        } else if ($field === 'is_custom') {
                            $insert_data[$field] = (bool)$product[$field] ? 1 : 0;
                            $insert_format[] = '%d';
                        } else {
                            $insert_data[$field] = $product[$field];
                            $insert_format[] = is_numeric($product[$field]) ? '%f' : '%s';
                        }
                    }
                }
                
                // Insertar el producto en el catálogo
                $wpdb->insert(
                    $catalog_products_table,
                    $insert_data,
                    $insert_format
                );
                
                if ($wpdb->insert_id) {
                    $products_added++;
                } else {
                    error_log('Error al insertar producto en catálogo ' . $catalog_id . ': ' . $wpdb->last_error);
                }
            }
        }
        
        // Obtener el catálogo actualizado con el conteo de productos
        $updated_catalog = $wpdb->get_row($wpdb->prepare("
            SELECT c.*, (
                SELECT COUNT(*) 
                FROM $catalog_products_table cp 
                WHERE cp.catalog_id = c.id
            ) as product_count
            FROM $catalog_table c
            WHERE c.id = %d
        ", $catalog_id), ARRAY_A);
        
        // Confirmar la transacción
        $wpdb->query('COMMIT');
        
        return new WP_REST_Response([
            'status' => 'success',
            'message' => 'Catálogo actualizado con éxito',
            'data' => $updated_catalog,
            'products_added' => $products_added
        ], 200);
        
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
 * Endpoint: Eliminar un catálogo
 */
function floresinc_delete_catalog_endpoint(WP_REST_Request $request) {
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
            'message' => 'Catálogo no encontrado o no tienes permiso para eliminarlo'
        ], 404);
    }
    
    // Iniciar transacción
    $wpdb->query('START TRANSACTION');
    
    try {
        // Eliminar los productos del catálogo
        $wpdb->delete(
            $catalog_products_table,
            ['catalog_id' => $catalog_id],
            ['%d']
        );
        
        // Eliminar el catálogo
        $wpdb->delete(
            $catalog_table,
            ['id' => $catalog_id],
            ['%d']
        );
        
        // Confirmar la transacción
        $wpdb->query('COMMIT');
        
        return new WP_REST_Response([
            'status' => 'success',
            'message' => 'Catálogo eliminado con éxito'
        ], 200);
        
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
 * Endpoint: Actualizar manualmente la estructura de la tabla
 */
function floresinc_update_catalog_tables_endpoint(WP_REST_Request $request) {
    // Solo administradores pueden actualizar las tablas
    if (!current_user_can('manage_options')) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'No tienes permiso para realizar esta acción'
        ], 403);
    }
    
    floresinc_check_and_update_tables();
    
    return new WP_REST_Response([
        'status' => 'success',
        'message' => 'Estructura de tablas actualizada',
        'structure' => floresinc_get_table_structure()
    ], 200);
}
