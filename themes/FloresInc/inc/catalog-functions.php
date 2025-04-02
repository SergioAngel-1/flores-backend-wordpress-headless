<?php
/**
 * Funciones para el sistema de catálogos
 * 
 * Este archivo proporciona endpoints de API REST para gestionar catálogos y 
 * sus productos asociados, utilizado por el frontend React/TypeScript.
 */

// Evitar acceso directo al archivo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Inicializar los endpoints para la gestión de catálogos
 */
function floresinc_init_catalog_endpoints() {
    add_action('rest_api_init', 'floresinc_register_catalog_endpoints');
}
add_action('init', 'floresinc_init_catalog_endpoints');

/**
 * Registrar los endpoints de API para catálogos
 */
function floresinc_register_catalog_endpoints() {
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
    
    // Endpoint para obtener los productos de un catálogo
    register_rest_route($namespace, '/catalogs/(?P<id>\d+)/products', [
        'methods' => 'GET',
        'callback' => 'floresinc_get_catalog_products_endpoint',
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
    
    // Endpoint para generar un PDF del catálogo
    register_rest_route($namespace, '/catalogs/(?P<id>\d+)/pdf', [
        'methods' => 'GET',
        'callback' => 'floresinc_generate_catalog_pdf_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
    
    // Endpoint para crear productos personalizados
    register_rest_route($namespace, '/catalogs/custom-products', [
        'methods' => 'POST',
        'callback' => 'floresinc_create_custom_product_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
    
    // Endpoint para actualizar productos personalizados
    register_rest_route($namespace, '/catalogs/custom-products/(?P<id>\d+)', [
        'methods' => 'PUT',
        'callback' => 'floresinc_update_custom_product_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
    
    // Endpoint para actualizar manualmente la estructura de la tabla
    register_rest_route($namespace, '/catalogs/update-tables', [
        'methods' => 'POST',
        'callback' => 'floresinc_update_catalog_tables_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
}

/**
 * Crear las tablas para los catálogos en la base de datos
 */
function floresinc_create_catalog_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Tabla para catálogos
    $catalogs_table = $wpdb->prefix . 'floresinc_catalogs';
    $catalog_products_table = $wpdb->prefix . 'floresinc_catalog_products';
    
    $catalogs_sql = "CREATE TABLE $catalogs_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        user_id bigint(20) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    
    // Tabla para relación entre catálogos y productos
    $catalog_products_sql = "CREATE TABLE $catalog_products_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        catalog_id bigint(20) NOT NULL,
        product_id bigint(20) NOT NULL,
        catalog_price decimal(10,6) NULL,
        catalog_name varchar(255) NULL,
        catalog_description text NULL,
        catalog_short_description text NULL,
        catalog_sku varchar(100) NULL,
        catalog_image varchar(255) NULL,
        catalog_images text NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY catalog_id (catalog_id),
        KEY product_id (product_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($catalogs_sql);
    dbDelta($catalog_products_sql);
    
    // Verificar si la columna catalog_price existe, y si no, añadirla
    floresinc_check_catalog_table_columns();
}
// Registrar la función para la activación del tema
add_action('after_switch_theme', 'floresinc_create_catalog_tables');

/**
 * Verificar y añadir las columnas necesarias si no existen
 */
function floresinc_check_catalog_table_columns() {
    global $wpdb;
    
    $catalog_products_table = $wpdb->prefix . 'floresinc_catalog_products';
    
    // Verificar si la tabla existe
    if ($wpdb->get_var("SHOW TABLES LIKE '$catalog_products_table'") !== $catalog_products_table) {
        return;
    }
    
    // Lista de columnas a verificar y añadir si no existen
    $columns = [
        'catalog_price' => "ALTER TABLE `$catalog_products_table` ADD COLUMN `catalog_price` decimal(10,6) NULL AFTER `product_id`",
        'catalog_name' => "ALTER TABLE `$catalog_products_table` ADD COLUMN `catalog_name` varchar(255) NULL AFTER `catalog_price`",
        'catalog_description' => "ALTER TABLE `$catalog_products_table` ADD COLUMN `catalog_description` text NULL AFTER `catalog_name`",
        'catalog_short_description' => "ALTER TABLE `$catalog_products_table` ADD COLUMN `catalog_short_description` text NULL AFTER `catalog_description`",
        'catalog_sku' => "ALTER TABLE `$catalog_products_table` ADD COLUMN `catalog_sku` varchar(100) NULL AFTER `catalog_short_description`",
        'catalog_image' => "ALTER TABLE `$catalog_products_table` ADD COLUMN `catalog_image` varchar(255) NULL AFTER `catalog_sku`",
        'catalog_images' => "ALTER TABLE `$catalog_products_table` ADD COLUMN `catalog_images` text NULL AFTER `catalog_image`",
        'updated_at' => "ALTER TABLE `$catalog_products_table` ADD COLUMN `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`"
    ];
    
    foreach ($columns as $column_name => $sql) {
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `$catalog_products_table` LIKE '$column_name'");
        
        if (empty($column_exists)) {
            $wpdb->query($sql);
            error_log("Columna $column_name añadida a la tabla $catalog_products_table");
        }
    }
}

/**
 * Endpoint: Obtener todos los catálogos
 */
function floresinc_get_catalogs_endpoint(WP_REST_Request $request) {
    global $wpdb;
    $user_id = get_current_user_id();
    
    // Obtener parámetros de consulta
    $per_page = isset($request['per_page']) ? intval($request['per_page']) : 10;
    $page = isset($request['page']) ? intval($request['page']) : 1;
    $search = isset($request['search']) ? sanitize_text_field($request['search']) : '';
    
    $catalogs_table = $wpdb->prefix . 'floresinc_catalogs';
    $catalog_products_table = $wpdb->prefix . 'floresinc_catalog_products';
    
    $where = "WHERE user_id = %d";
    $where_args = [$user_id];
    
    // Añadir condición de búsqueda si se proporcionó
    if (!empty($search)) {
        $where .= " AND name LIKE %s";
        $where_args[] = '%' . $wpdb->esc_like($search) . '%';
    }
    
    // Calcular offset para paginación
    $offset = ($page - 1) * $per_page;
    
    // Obtener catálogos
    $catalogs = $wpdb->get_results($wpdb->prepare("
        SELECT c.*, (
            SELECT COUNT(*) 
            FROM $catalog_products_table cp 
            WHERE cp.catalog_id = c.id
        ) as product_count
        FROM $catalogs_table c
        $where
        ORDER BY updated_at DESC
        LIMIT %d OFFSET %d
    ", array_merge($where_args, [$per_page, $offset])), ARRAY_A);
    
    // Si no hay catálogos, devolver array vacío
    if (!$catalogs) {
        return new WP_REST_Response([], 200);
    }
    
    return new WP_REST_Response($catalogs, 200);
}

/**
 * Endpoint: Obtener un catálogo específico
 */
function floresinc_get_catalog_endpoint(WP_REST_Request $request) {
    global $wpdb;
    $user_id = get_current_user_id();
    $catalog_id = $request['id'];
    
    $catalogs_table = $wpdb->prefix . 'floresinc_catalogs';
    $catalog_products_table = $wpdb->prefix . 'floresinc_catalog_products';
    
    // Obtener el catálogo
    $catalog = $wpdb->get_row($wpdb->prepare("
        SELECT c.*, (
            SELECT COUNT(*) 
            FROM $catalog_products_table cp 
            WHERE cp.catalog_id = c.id
        ) as product_count
        FROM $catalogs_table c
        WHERE c.id = %d AND c.user_id = %d
    ", $catalog_id, $user_id), ARRAY_A);
    
    if (!$catalog) {
        return new WP_Error('catalog_not_found', 'Catálogo no encontrado', ['status' => 404]);
    }
    
    return new WP_REST_Response($catalog, 200);
}

/**
 * Endpoint: Obtener productos de un catálogo
 */
function floresinc_get_catalog_products_endpoint(WP_REST_Request $request) {
    global $wpdb;
    $catalog_id = $request->get_param('id');
    
    if (!$catalog_id) {
        return new WP_Error('missing_id', 'El ID del catálogo es obligatorio', ['status' => 400]);
    }
    
    $catalog_products_table = $wpdb->prefix . 'floresinc_catalog_products';
    
    // Obtener los IDs de productos y precios de catálogo
    $product_data = $wpdb->get_results($wpdb->prepare("
        SELECT product_id, catalog_price, catalog_name, catalog_description, catalog_short_description, catalog_sku, catalog_image, catalog_images
        FROM $catalog_products_table
        WHERE catalog_id = %d
    ", $catalog_id), ARRAY_A);
    
    if (!$product_data) {
        return new WP_REST_Response([], 200);
    }
    
    $products = [];
    
    foreach ($product_data as $item) {
        $product_id = $item['product_id'];
        $catalog_price = $item['catalog_price'];
        $catalog_name = $item['catalog_name'];
        $catalog_description = $item['catalog_description'];
        $catalog_short_description = $item['catalog_short_description'];
        $catalog_sku = $item['catalog_sku'];
        $catalog_image = $item['catalog_image'];
        $catalog_images = $item['catalog_images'];
        
        // Si es un producto personalizado (product_id = 0), crear un producto personalizado
        if ($product_id == 0) {
            $product_data = [
                'id' => $item['id'], // ID en la tabla de productos del catálogo
                'name' => $catalog_name ?: 'Producto personalizado',
                'price' => $catalog_price ?: 0,
                'catalog_price' => $catalog_price,
                'regular_price' => $catalog_price,
                'sale_price' => null,
                'sku' => $catalog_sku ?: '',
                'stock_status' => 'instock',
                'stock_quantity' => null,
                'permalink' => '',
                'image' => $catalog_image ?: '',
                'images' => $catalog_images ? json_decode($catalog_images, true) : [],
                'description' => $catalog_description ?: '',
                'short_description' => $catalog_short_description ?: '',
                'is_custom' => true
            ];
            
            $products[] = $product_data;
            continue;
        }
        
        $product = wc_get_product($product_id);
        
        if ($product) {
            $product_data = [
                'id' => $product->get_id(),
                'name' => $catalog_name ?: $product->get_name(),
                'price' => $product->get_price(),
                'catalog_price' => $catalog_price,
                'regular_price' => $product->get_regular_price(),
                'sale_price' => $product->get_sale_price(),
                'sku' => $catalog_sku ?: $product->get_sku(),
                'stock_status' => $product->get_stock_status(),
                'stock_quantity' => $product->get_stock_quantity(),
                'permalink' => get_permalink($product->get_id()),
                'image' => $catalog_image ?: '', // Agregar propiedad 'image'
                'images' => $catalog_images ? json_decode($catalog_images, true) : [],
                'description' => $catalog_description ?: $product->get_description(),
                'short_description' => $catalog_short_description ?: $product->get_short_description()
            ];
            
            // Obtener imágenes
            $attachment_ids = $product->get_gallery_image_ids();
            $main_image_id = $product->get_image_id();
            
            if ($main_image_id) {
                $main_image = wp_get_attachment_image_src($main_image_id, 'full');
                if ($main_image) {
                    $product_data['image'] = $main_image[0]; // Establecer la imagen principal como propiedad 'image'
                    $product_data['images'][] = [
                        'id' => $main_image_id,
                        'src' => $main_image[0],
                        'alt' => get_post_meta($main_image_id, '_wp_attachment_image_alt', true)
                    ];
                }
            } else {
                // Si no hay imagen principal, usar un placeholder
                $product_data['image'] = '/wp-content/themes/FloresInc/assets/img/no-image.svg';
            }
            
            foreach ($attachment_ids as $attachment_id) {
                $image = wp_get_attachment_image_src($attachment_id, 'full');
                if ($image) {
                    $product_data['images'][] = [
                        'id' => $attachment_id,
                        'src' => $image[0],
                        'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true)
                    ];
                }
            }
            
            $products[] = $product_data;
        }
    }
    
    return new WP_REST_Response($products, 200);
}

/**
 * Endpoint: Actualizar un producto específico del catálogo
 */
function floresinc_update_catalog_product_endpoint(WP_REST_Request $request) {
    global $wpdb;
    $catalog_id = $request['catalog_id'];
    $product_id = $request['product_id'];
    
    // Obtener y validar los datos
    $params = $request->get_json_params();
    
    if (!isset($params['catalog_price']) && !isset($params['catalog_name']) && !isset($params['catalog_description']) && !isset($params['catalog_short_description']) && !isset($params['catalog_sku']) && !isset($params['catalog_image'])) {
        return new WP_Error('missing_data', 'Debes proporcionar al menos un campo para actualizar', ['status' => 400]);
    }
    
    $catalog_price = isset($params['catalog_price']) ? floatval($params['catalog_price']) : null;
    $catalog_name = isset($params['catalog_name']) ? sanitize_text_field($params['catalog_name']) : null;
    $catalog_description = isset($params['catalog_description']) ? sanitize_text_field($params['catalog_description']) : null;
    $catalog_short_description = isset($params['catalog_short_description']) ? sanitize_text_field($params['catalog_short_description']) : null;
    $catalog_sku = isset($params['catalog_sku']) ? sanitize_text_field($params['catalog_sku']) : null;
    $catalog_image = isset($params['catalog_image']) ? sanitize_text_field($params['catalog_image']) : null;
    
    $catalog_products_table = $wpdb->prefix . 'floresinc_catalog_products';
    
    // Verificar que el producto existe en el catálogo
    $product_exists = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM $catalog_products_table 
        WHERE catalog_id = %d AND product_id = %d
    ", $catalog_id, $product_id));
    
    if (!$product_exists) {
        return new WP_Error('product_not_found', 'El producto no existe en el catálogo', ['status' => 404]);
    }
    
    // Actualizar el producto
    $update_data = [];
    $update_format = [];
    
    if ($catalog_price !== null) {
        $update_data['catalog_price'] = $catalog_price;
        $update_format[] = '%f';
    }
    
    if ($catalog_name !== null) {
        $update_data['catalog_name'] = $catalog_name;
        $update_format[] = '%s';
    }
    
    if ($catalog_description !== null) {
        $update_data['catalog_description'] = $catalog_description;
        $update_format[] = '%s';
    }
    
    if ($catalog_short_description !== null) {
        $update_data['catalog_short_description'] = $catalog_short_description;
        $update_format[] = '%s';
    }
    
    if ($catalog_sku !== null) {
        $update_data['catalog_sku'] = $catalog_sku;
        $update_format[] = '%s';
    }
    
    if ($catalog_image !== null) {
        $update_data['catalog_image'] = $catalog_image;
        $update_format[] = '%s';
    }
    
    $wpdb->update(
        $catalog_products_table,
        $update_data,
        ['catalog_id' => $catalog_id, 'product_id' => $product_id],
        $update_format,
        ['%d', '%d']
    );
    
    // Obtener el producto actualizado
    $updated_product = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM $catalog_products_table 
        WHERE catalog_id = %d AND product_id = %d
    ", $catalog_id, $product_id), ARRAY_A);
    
    return new WP_REST_Response($updated_product, 200);
}

/**
 * Endpoint: Crear un nuevo catálogo
 */
function floresinc_create_catalog_endpoint(WP_REST_Request $request) {
    global $wpdb;
    $user_id = get_current_user_id();
    
    // Habilitar el registro de errores de la base de datos para depuración
    $wpdb->show_errors();
    
    // Iniciar transacción para garantizar la integridad de los datos
    $wpdb->query('START TRANSACTION');
    
    try {
        // Obtener y validar los datos
        $params = $request->get_json_params();
        
        // Registrar los parámetros recibidos para depuración
        error_log('Parámetros recibidos para crear catálogo: ' . json_encode($params));
        
        if (!isset($params['name']) || empty($params['name'])) {
            throw new Exception('El nombre del catálogo es obligatorio');
        }
        
        $name = sanitize_text_field($params['name']);
        $products = isset($params['products']) && is_array($params['products']) ? $params['products'] : [];
        
        $catalogs_table = $wpdb->prefix . 'floresinc_catalogs';
        $catalog_products_table = $wpdb->prefix . 'floresinc_catalog_products';
        $associations_table = $wpdb->prefix . 'floresinc_catalog_product_associations';
        
        // Verificar si las tablas existen
        $catalogs_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$catalogs_table'") === $catalogs_table;
        $catalog_products_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$catalog_products_table'") === $catalog_products_table;
        
        if (!$catalogs_table_exists || !$catalog_products_table_exists) {
            // Crear las tablas si no existen
            floresinc_create_catalog_tables();
            
            // Verificar nuevamente
            $catalogs_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$catalogs_table'") === $catalogs_table;
            $catalog_products_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$catalog_products_table'") === $catalog_products_table;
            
            if (!$catalogs_table_exists || !$catalog_products_table_exists) {
                throw new Exception('Las tablas de catálogos no existen y no se pudieron crear');
            }
        }
        
        // Verificar y añadir las columnas necesarias
        floresinc_check_catalog_table_columns();
        
        // Verificar si la tabla de asociaciones existe
        $associations_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$associations_table'") === $associations_table;
        
        if (!$associations_table_exists) {
            // Crear la tabla si no existe
            $sql = "CREATE TABLE $associations_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                catalog_id bigint(20) NOT NULL,
                catalog_product_id bigint(20) NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY catalog_id (catalog_id),
                KEY catalog_product_id (catalog_product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            
            // Verificar nuevamente
            $associations_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$associations_table'") === $associations_table;
            
            if (!$associations_table_exists) {
                error_log('No se pudo crear la tabla de asociaciones');
            } else {
                error_log('Tabla de asociaciones creada correctamente');
            }
        }
        
        // Insertar el catálogo
        $result = $wpdb->insert(
            $catalogs_table,
            [
                'name' => $name,
                'user_id' => $user_id
            ],
            ['%s', '%d']
        );
        
        if ($result === false) {
            throw new Exception('Error al crear el catálogo: ' . $wpdb->last_error);
        }
        
        $catalog_id = $wpdb->insert_id;
        
        if (!$catalog_id) {
            throw new Exception('Error al crear el catálogo: No se pudo obtener el ID');
        }
        
        // Separar productos estándar y productos personalizados no guardados
        $standard_products = [];
        $custom_products = [];
        
        foreach ($products as $product_data) {
            if (is_array($product_data) && isset($product_data['is_custom']) && $product_data['is_custom'] && isset($product_data['unsaved_data'])) {
                $custom_products[] = $product_data;
            } else {
                $standard_products[] = $product_data;
            }
        }
        
        // Insertar los productos estándar
        $products_added = 0;
        foreach ($standard_products as $product_data) {
            // Si es un objeto con id y catalog_price
            if (is_array($product_data) && isset($product_data['id'])) {
                $product_id = intval($product_data['id']);
                $catalog_price = isset($product_data['catalog_price']) ? floatval($product_data['catalog_price']) : null;
                $catalog_name = isset($product_data['catalog_name']) ? sanitize_text_field($product_data['catalog_name']) : null;
                $catalog_description = isset($product_data['catalog_description']) ? sanitize_text_field($product_data['catalog_description']) : null;
                $catalog_short_description = isset($product_data['catalog_short_description']) ? sanitize_text_field($product_data['catalog_short_description']) : null;
                $catalog_sku = isset($product_data['catalog_sku']) ? sanitize_text_field($product_data['catalog_sku']) : null;
                $catalog_image = isset($product_data['catalog_image']) ? sanitize_text_field($product_data['catalog_image']) : null;
            } else {
                // Si es solo el ID del producto
                $product_id = intval($product_data);
                $catalog_price = null;
                $catalog_name = null;
                $catalog_description = null;
                $catalog_short_description = null;
                $catalog_sku = null;
                $catalog_image = null;
            }
            
            // Verificar que el producto existe
            $product = wc_get_product($product_id);
            
            if ($product) {
                $insert_data = [
                    'catalog_id' => $catalog_id,
                    'product_id' => $product_id,
                    'catalog_name' => $catalog_name,
                    'catalog_description' => $catalog_description,
                    'catalog_short_description' => $catalog_short_description,
                    'catalog_sku' => $catalog_sku,
                    'catalog_image' => $catalog_image
                ];
                
                $insert_format = ['%d', '%d', '%s', '%s', '%s', '%s', '%s'];
                
                // Añadir precio de catálogo si está definido
                if ($catalog_price !== null) {
                    $insert_data['catalog_price'] = $catalog_price;
                    $insert_format[] = '%f';
                }
                
                $wpdb->insert(
                    $catalog_products_table,
                    $insert_data,
                    $insert_format
                );
                
                if ($wpdb->insert_id) {
                    $products_added++;
                    
                    // Verificar si es necesario actualizar las asociaciones en la tabla floresinc_catalog_product_associations
                    if ($associations_table_exists) {
                        // Comprobar si ya existe la asociación
                        $existing = $wpdb->get_row($wpdb->prepare(
                            "SELECT * FROM $associations_table WHERE catalog_id = %d AND catalog_product_id = %d",
                            $catalog_id,
                            $wpdb->insert_id
                        ));
                        
                        if (!$existing) {
                            // Crear la asociación
                            $wpdb->insert(
                                $associations_table,
                                [
                                    'catalog_id' => $catalog_id,
                                    'catalog_product_id' => $wpdb->insert_id
                                ],
                                ['%d', '%d']
                            );
                            
                            if ($wpdb->last_error) {
                                error_log('Error al insertar asociación de producto personalizado: ' . $wpdb->last_error);
                            } else {
                                error_log('Asociación creada correctamente para el producto ID: ' . $wpdb->insert_id . ' con el catálogo ID: ' . $catalog_id);
                            }
                        }
                    } else {
                        error_log('No se pudo crear la asociación porque la tabla no existe');
                    }
                } else {
                    error_log('Error al insertar producto ' . $product_id . ' en catálogo ' . $catalog_id . ': ' . $wpdb->last_error);
                }
            } else {
                error_log('Producto no encontrado: ' . $product_id);
            }
        }
        
        // Insertar productos personalizados
        foreach ($custom_products as $custom_product) {
            if (isset($custom_product['unsaved_data'])) {
                $custom_data = $custom_product['unsaved_data'];
                
                // Datos mínimos requeridos
                if (empty($custom_data['name'])) {
                    continue; // Saltar este producto si falta el nombre
                }
                
                $name = sanitize_text_field($custom_data['name']);
                $description = isset($custom_data['description']) ? sanitize_text_field($custom_data['description']) : '';
                $short_description = isset($custom_data['short_description']) ? sanitize_text_field($custom_data['short_description']) : '';
                $price = isset($custom_data['price']) ? floatval($custom_data['price']) : 0;
                $sku = isset($custom_data['sku']) ? sanitize_text_field($custom_data['sku']) : '';
                $image = isset($custom_data['image']) ? sanitize_text_field($custom_data['image']) : '';
                
                $insert_data = [
                    'catalog_id' => $catalog_id,
                    'product_id' => 0, // ID ficticio para productos personalizados
                    'catalog_name' => $name,
                    'catalog_description' => $description,
                    'catalog_short_description' => $short_description,
                    'catalog_price' => $price,
                    'catalog_sku' => $sku,
                    'catalog_image' => $image
                ];
                
                $insert_format = ['%d', '%d', '%s', '%s', '%s', '%f', '%s', '%s'];
                
                $wpdb->insert(
                    $catalog_products_table,
                    $insert_data,
                    $insert_format
                );
                
                if ($wpdb->insert_id) {
                    $products_added++;
                    
                    // Verificar si es necesario actualizar las asociaciones en la tabla floresinc_catalog_product_associations
                    if ($associations_table_exists) {
                        // Comprobar si ya existe la asociación
                        $existing = $wpdb->get_row($wpdb->prepare(
                            "SELECT * FROM $associations_table WHERE catalog_id = %d AND catalog_product_id = %d",
                            $catalog_id,
                            $wpdb->insert_id
                        ));
                        
                        if (!$existing) {
                            // Crear la asociación
                            $wpdb->insert(
                                $associations_table,
                                [
                                    'catalog_id' => $catalog_id,
                                    'catalog_product_id' => $wpdb->insert_id
                                ],
                                ['%d', '%d']
                            );
                            
                            if ($wpdb->last_error) {
                                error_log('Error al insertar asociación de producto personalizado: ' . $wpdb->last_error);
                            } else {
                                error_log('Asociación creada correctamente para el producto ID: ' . $wpdb->insert_id . ' con el catálogo ID: ' . $catalog_id);
                            }
                        }
                    } else {
                        error_log('No se pudo crear la asociación porque la tabla no existe');
                    }
                } else {
                    error_log('Error al insertar producto personalizado en catálogo ' . $catalog_id . ': ' . $wpdb->last_error);
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
            FROM $catalogs_table c
            WHERE c.id = %d
        ", $catalog_id), ARRAY_A);
        
        if (!$catalog) {
            error_log('No se pudo recuperar el catálogo creado con ID ' . $catalog_id);
            
            // Intentar recuperar información básica
            $basic_catalog = [
                'id' => $catalog_id,
                'name' => $name,
                'user_id' => $user_id,
                'product_count' => $products_added
            ];
            
            $wpdb->query('COMMIT');
            return new WP_REST_Response($basic_catalog, 201);
        }
        
        $wpdb->query('COMMIT');
        return new WP_REST_Response($catalog, 201);
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        error_log('Excepción al crear catálogo: ' . $e->getMessage());
        return new WP_Error('catalog_creation_exception', 'Error al crear el catálogo: ' . $e->getMessage(), ['status' => 500]);
    }
}

/**
 * Endpoint: Actualizar un catálogo existente
 */
function floresinc_update_catalog_endpoint(WP_REST_Request $request) {
    global $wpdb;
    $user_id = get_current_user_id();
    $catalog_id = $request['id'];
    
    // Obtener y validar los datos
    $params = $request->get_json_params();
    
    if (!isset($params['name']) || empty($params['name'])) {
        return new WP_Error('missing_name', 'El nombre del catálogo es obligatorio', ['status' => 400]);
    }
    
    $name = sanitize_text_field($params['name']);
    $products = isset($params['products']) && is_array($params['products']) ? $params['products'] : [];
    
    $catalogs_table = $wpdb->prefix . 'floresinc_catalogs';
    $catalog_products_table = $wpdb->prefix . 'floresinc_catalog_products';
    $associations_table = $wpdb->prefix . 'floresinc_catalog_product_associations';
    
    // Verificar que el catálogo existe y pertenece al usuario
    $catalog = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM $catalogs_table 
        WHERE id = %d AND user_id = %d
    ", $catalog_id, $user_id));
    
    if (!$catalog) {
        return new WP_Error('catalog_not_found', 'Catálogo no encontrado', ['status' => 404]);
    }
    
    // Actualizar el nombre del catálogo
    $wpdb->update(
        $catalogs_table,
        ['name' => $name],
        ['id' => $catalog_id],
        ['%s'],
        ['%d']
    );
    
    // Eliminar todos los productos actuales del catálogo
    $wpdb->delete(
        $catalog_products_table,
        ['catalog_id' => $catalog_id],
        ['%d']
    );
    
    // Eliminar todas las asociaciones actuales del catálogo
    if ($wpdb->get_var("SHOW TABLES LIKE '$associations_table'") === $associations_table) {
        $wpdb->delete(
            $associations_table,
            ['catalog_id' => $catalog_id],
            ['%d']
        );
    }
    
    // Insertar los nuevos productos
    foreach ($products as $product_data) {
        // Si es un objeto con id y catalog_price
        if (is_array($product_data) && isset($product_data['id'])) {
            $product_id = intval($product_data['id']);
            $catalog_price = isset($product_data['catalog_price']) ? floatval($product_data['catalog_price']) : null;
            $catalog_name = isset($product_data['catalog_name']) ? sanitize_text_field($product_data['catalog_name']) : null;
            $catalog_description = isset($product_data['catalog_description']) ? sanitize_text_field($product_data['catalog_description']) : null;
            $catalog_short_description = isset($product_data['catalog_short_description']) ? sanitize_text_field($product_data['catalog_short_description']) : null;
            $catalog_sku = isset($product_data['catalog_sku']) ? sanitize_text_field($product_data['catalog_sku']) : null;
            $catalog_image = isset($product_data['catalog_image']) ? sanitize_text_field($product_data['catalog_image']) : null;
        } else {
            // Si es solo el ID del producto
            $product_id = intval($product_data);
            $catalog_price = null;
            $catalog_name = null;
            $catalog_description = null;
            $catalog_short_description = null;
            $catalog_sku = null;
            $catalog_image = null;
        }
        
        // Verificar que el producto existe
        $product = wc_get_product($product_id);
        if ($product) {
            $insert_data = [
                'catalog_id' => $catalog_id,
                'product_id' => $product_id,
                'catalog_name' => $catalog_name,
                'catalog_description' => $catalog_description,
                'catalog_short_description' => $catalog_short_description,
                'catalog_sku' => $catalog_sku,
                'catalog_image' => $catalog_image
            ];
            
            $insert_format = ['%d', '%d', '%s', '%s', '%s', '%s', '%s'];
            
            // Añadir precio de catálogo si está definido
            if ($catalog_price !== null) {
                $insert_data['catalog_price'] = $catalog_price;
                $insert_format[] = '%f';
            }
            
            $wpdb->insert(
                $catalog_products_table,
                $insert_data,
                $insert_format
            );
            
            if ($wpdb->insert_id) {
                // Verificar si es necesario actualizar las asociaciones en la tabla floresinc_catalog_product_associations
                if ($wpdb->get_var("SHOW TABLES LIKE '$associations_table'") === $associations_table) {
                    // Comprobar si ya existe la asociación
                    $existing = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM $associations_table WHERE catalog_id = %d AND catalog_product_id = %d",
                        $catalog_id,
                        $wpdb->insert_id
                    ));
                    
                    if (!$existing) {
                        // Crear la asociación
                        $wpdb->insert(
                            $associations_table,
                            [
                                'catalog_id' => $catalog_id,
                                'catalog_product_id' => $wpdb->insert_id
                            ],
                            ['%d', '%d']
                        );
                        
                        if ($wpdb->last_error) {
                            error_log('Error al insertar asociación de producto personalizado: ' . $wpdb->last_error);
                        } else {
                            error_log('Asociación creada correctamente para el producto ID: ' . $wpdb->insert_id . ' con el catálogo ID: ' . $catalog_id);
                        }
                    }
                } else {
                    error_log('No se pudo crear la asociación porque la tabla no existe');
                }
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
        FROM $catalogs_table c
        WHERE c.id = %d
    ", $catalog_id), ARRAY_A);
    
    return new WP_REST_Response($updated_catalog, 200);
}

/**
 * Endpoint: Eliminar un catálogo
 */
function floresinc_delete_catalog_endpoint(WP_REST_Request $request) {
    global $wpdb;
    $user_id = get_current_user_id();
    $catalog_id = $request['id'];
    
    $catalogs_table = $wpdb->prefix . 'floresinc_catalogs';
    $catalog_products_table = $wpdb->prefix . 'floresinc_catalog_products';
    $associations_table = $wpdb->prefix . 'floresinc_catalog_product_associations';
    
    // Verificar que el catálogo existe y pertenece al usuario
    $catalog = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM $catalogs_table 
        WHERE id = %d AND user_id = %d
    ", $catalog_id, $user_id));
    
    if (!$catalog) {
        return new WP_Error('catalog_not_found', 'Catálogo no encontrado', ['status' => 404]);
    }
    
    // Eliminar todos los productos del catálogo
    $wpdb->delete(
        $catalog_products_table,
        ['catalog_id' => $catalog_id],
        ['%d']
    );
    
    // Eliminar todas las asociaciones del catálogo
    if ($wpdb->get_var("SHOW TABLES LIKE '$associations_table'") === $associations_table) {
        $wpdb->delete(
            $associations_table,
            ['catalog_id' => $catalog_id],
            ['%d']
        );
    }
    
    // Eliminar el catálogo
    $wpdb->delete(
        $catalogs_table,
        ['id' => $catalog_id],
        ['%d']
    );
    
    return new WP_REST_Response(['message' => 'Catálogo eliminado correctamente'], 200);
}

/**
 * Endpoint: Generar un PDF del catálogo
 * 
 * Utiliza la librería TCPDF para generar un PDF con los productos del catálogo
 */
function floresinc_generate_catalog_pdf_endpoint(WP_REST_Request $request) {
    global $wpdb;
    $user_id = get_current_user_id();
    $catalog_id = $request['id'];
    
    $catalogs_table = $wpdb->prefix . 'floresinc_catalogs';
    $catalog_products_table = $wpdb->prefix . 'floresinc_catalog_products';
    
    // Verificar que el catálogo existe y pertenece al usuario
    $catalog = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM $catalogs_table 
        WHERE id = %d AND user_id = %d
    ", $catalog_id, $user_id));
    
    if (!$catalog) {
        return new WP_Error('catalog_not_found', 'Catálogo no encontrado', ['status' => 404]);
    }
    
    // Obtener IDs de productos
    $product_ids = $wpdb->get_col($wpdb->prepare("
        SELECT product_id FROM $catalog_products_table 
        WHERE catalog_id = %d
    ", $catalog_id));
    
    if (empty($product_ids)) {
        return new WP_Error('empty_catalog', 'El catálogo no tiene productos', ['status' => 400]);
    }
    
    // Verificar si está instalada la librería TCPDF
    if (!class_exists('TCPDF')) {
        // Si no está instalada, usar alternativa
        return floresinc_generate_simple_pdf($catalog, $product_ids);
    }
    
    // Generar PDF con TCPDF
    try {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        
        // Configuración del documento
        $pdf->SetCreator('FloresInc');
        $pdf->SetAuthor('FloresInc');
        $pdf->SetTitle($catalog->name);
        $pdf->SetSubject('Catálogo de productos');
        
        // Eliminar encabezado y pie de página
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Añadir página
        $pdf->AddPage();
        
        // Título del catálogo
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->Cell(0, 15, $catalog->name, 0, 1, 'C');
        
        // Fecha de generación
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->Cell(0, 8, 'Generado el: ' . date('d/m/Y'), 0, 1, 'R');
        
        $pdf->Ln(10);
        
        // Encabezado de la tabla
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(20, 10, 'Imagen', 1, 0, 'C');
        $pdf->Cell(100, 10, 'Producto', 1, 0, 'C');
        $pdf->Cell(25, 10, 'SKU', 1, 0, 'C');
        $pdf->Cell(25, 10, 'Precio', 1, 0, 'C');
        $pdf->Ln();
        
        // Contenido de la tabla
        $pdf->SetFont('helvetica', '', 10);
        
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $image_path = wp_get_original_image_path($product->get_image_id());
                $price = wc_price($product->get_price());
                $sku = $product->get_sku() ? $product->get_sku() : 'N/A';
                
                // Altura de la fila
                $row_height = 20;
                
                // Si hay imagen, agregarla
                if ($image_path && file_exists($image_path)) {
                    $y_before = $pdf->GetY();
                    $pdf->Cell(20, $row_height, '', 1, 0, 'C');
                    $pdf->Image($image_path, $pdf->GetX() - 18, $y_before + 1, 16, 0, '', '', 'T', true);
                } else {
                    $pdf->Cell(20, $row_height, 'Sin imagen', 1, 0, 'C');
                }
                
                // Nombre del producto con posible multitexto
                $pdf->MultiCell(100, $row_height, $product->get_name(), 1, 'L', 0, 0);
                
                // SKU y precio
                $pdf->Cell(25, $row_height, $sku, 1, 0, 'C');
                $pdf->Cell(25, $row_height, strip_tags($price), 1, 0, 'R');
                $pdf->Ln();
                
                // Verificar espacio disponible en la página
                if ($pdf->GetY() > 250) {
                    $pdf->AddPage();
                }
            }
        }
        
        // Generar el archivo PDF
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/catalogs';
        
        // Crear directorio si no existe
        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
        }
        
        // Nombre de archivo basado en slug del catálogo
        $filename = sanitize_title($catalog->name) . '-' . $catalog_id . '.pdf';
        $pdf_path = $pdf_dir . '/' . $filename;
        $pdf_url = $upload_dir['baseurl'] . '/catalogs/' . $filename;
        
        // Guardar PDF
        $pdf->Output($pdf_path, 'F');
        
        // Retornar URL del PDF
        $response = new WP_REST_Response([
            'pdf_url' => $pdf_url
        ], 200);
        
        return $response;
    } catch (Exception $e) {
        // Si falla, usar alternativa
        return floresinc_generate_simple_pdf($catalog, $product_ids);
    }
}

/**
 * Método de respaldo para generar un PDF simple sin TCPDF
 */
function floresinc_generate_simple_pdf($catalog, $product_ids) {
    // Crear un PDF simple usando HTML y CSS
    ob_start();
    
    // Encabezado HTML
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . esc_html($catalog->name) . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
            h1 { text-align: center; color: #333; }
            .date { text-align: right; font-style: italic; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; }
            th { background-color: #f2f2f2; padding: 10px; text-align: left; border: 1px solid #ddd; }
            td { padding: 8px; border: 1px solid #ddd; }
            .product-image { width: 60px; height: 60px; object-fit: cover; }
        </style>
    </head>
    <body>
        <h1>' . esc_html($catalog->name) . '</h1>
        <div class="date">Generado el: ' . date('d/m/Y') . '</div>
        <table>
            <thead>
                <tr>
                    <th>Imagen</th>
                    <th>Producto</th>
                    <th>SKU</th>
                    <th>Precio</th>
                </tr>
            </thead>
            <tbody>';
    
    // Contenido de la tabla
    foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);
        if ($product) {
            $image_url = wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src('thumbnail');
            echo '<tr>
                <td><img class="product-image" src="' . esc_url($image_url) . '" alt=""></td>
                <td>' . esc_html($product->get_name()) . '</td>
                <td>' . esc_html($product->get_sku() ?: 'N/A') . '</td>
                <td>' . wp_kses_post(wc_price($product->get_price())) . '</td>
            </tr>';
        }
    }
    
    // Pie de HTML
    echo '</tbody>
        </table>
    </body>
    </html>';
    
    $html = ob_get_clean();
    
    // Guardar el HTML como PDF usando wkhtmltopdf si está disponible
    $upload_dir = wp_upload_dir();
    $pdf_dir = $upload_dir['basedir'] . '/catalogs';
    
    // Crear directorio si no existe
    if (!file_exists($pdf_dir)) {
        wp_mkdir_p($pdf_dir);
    }
    
    // Nombre de archivo basado en slug del catálogo
    $filename_html = sanitize_title($catalog->name) . '-' . $catalog->id . '.html';
    $filename_pdf = sanitize_title($catalog->name) . '-' . $catalog->id . '.pdf';
    $html_path = $pdf_dir . '/' . $filename_html;
    $pdf_path = $pdf_dir . '/' . $filename_pdf;
    $pdf_url = $upload_dir['baseurl'] . '/catalogs/' . $filename_pdf;
    
    // Guardar el HTML
    file_put_contents($html_path, $html);
    
    // Intentar convertir a PDF con wkhtmltopdf si está disponible
    $wkhtmltopdf_path = '/usr/bin/wkhtmltopdf'; // Ajustar según la instalación
    
    if (file_exists($wkhtmltopdf_path)) {
        $command = "$wkhtmltopdf_path $html_path $pdf_path";
        exec($command);
        
        // Verificar si se generó el PDF
        if (file_exists($pdf_path)) {
            unlink($html_path); // Eliminar el HTML temporal
            
            // Retornar URL del PDF
            return new WP_REST_Response([
                'pdf_url' => $pdf_url
            ], 200);
        }
    }
    
    // Si no se pudo generar el PDF, devolver la URL del HTML
    $html_url = $upload_dir['baseurl'] . '/catalogs/' . $filename_html;
    
    return new WP_REST_Response([
        'pdf_url' => $html_url,
        'format' => 'html' // Indicar que es HTML en lugar de PDF
    ], 200);
}

/**
 * Endpoint: Actualizar manualmente la estructura de la tabla
 */
function floresinc_update_catalog_tables_endpoint(WP_REST_Request $request) {
    try {
        // Ejecutar la función para verificar y actualizar las tablas
        floresinc_create_catalog_tables();
        
        // Verificar y añadir las columnas necesarias
        floresinc_check_catalog_table_columns();
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Estructura de tablas de catálogos actualizada correctamente'
        ], 200);
    } catch (Exception $e) {
        return new WP_Error('update_tables_failed', 'Error al actualizar las tablas: ' . $e->getMessage(), ['status' => 500]);
    }
}

/**
 * Endpoint: Crear productos personalizados
 */
function floresinc_create_custom_product_endpoint(WP_REST_Request $request) {
    global $wpdb;
    $user_id = get_current_user_id();
    
    // Obtener y validar los datos
    $params = $request->get_json_params();
    
    // Log para depuración
    error_log('Parámetros recibidos para crear producto personalizado: ' . json_encode($params));
    
    if (!isset($params['name']) || empty($params['name'])) {
        return new WP_Error('missing_name', 'El nombre del producto es obligatorio', ['status' => 400]);
    }
    
    // Validar catalog_id
    if (!isset($params['catalog_id'])) {
        return new WP_Error('missing_catalog_id', 'El ID del catálogo es obligatorio', ['status' => 400]);
    }
    
    // Convertir a entero para asegurar un valor numérico
    $catalog_id = intval($params['catalog_id']);
    
    // Log para depuración del catalog_id
    error_log('ID del catálogo recibido: ' . $catalog_id);
    
    // Verificar que el catálogo existe y que el ID es mayor que cero
    if ($catalog_id <= 0) {
        error_log('Error: ID de catálogo inválido (cero o negativo): ' . $catalog_id);
        return new WP_Error('invalid_catalog_id', 'El ID del catálogo debe ser un número mayor que cero', ['status' => 400]);
    }
    
    // Verificar que el catálogo existe
    $catalog_table = $wpdb->prefix . 'floresinc_catalogs';
    $catalog = $wpdb->get_row($wpdb->prepare("SELECT * FROM $catalog_table WHERE id = %d", $catalog_id));
    
    if (!$catalog) {
        error_log('Catálogo no encontrado con ID: ' . $catalog_id);
        return new WP_Error('catalog_not_found', 'El catálogo especificado no existe', ['status' => 404]);
    } else {
        error_log('Catálogo encontrado correctamente con ID: ' . $catalog_id);
    }
    
    $name = sanitize_text_field($params['name']);
    $description = isset($params['description']) ? sanitize_text_field($params['description']) : '';
    $short_description = isset($params['short_description']) ? sanitize_text_field($params['short_description']) : '';
    $price = isset($params['price']) ? floatval($params['price']) : 0;
    $sku = isset($params['sku']) ? sanitize_text_field($params['sku']) : '';
    $image = isset($params['image']) ? sanitize_text_field($params['image']) : '';
    $images = isset($params['images']) && is_array($params['images']) ? $params['images'] : [];
    $images_json = !empty($images) ? json_encode($images) : null;
    
    $catalog_products_table = $wpdb->prefix . 'floresinc_catalog_products';
    $associations_table = $wpdb->prefix . 'floresinc_catalog_product_associations';
    
    // Verificar si la tabla de asociaciones existe
    $associations_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$associations_table'") === $associations_table;
    
    if (!$associations_table_exists) {
        // Crear la tabla si no existe
        $sql = "CREATE TABLE $associations_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            catalog_id bigint(20) NOT NULL,
            catalog_product_id bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY catalog_id (catalog_id),
            KEY catalog_product_id (catalog_product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Verificar nuevamente
        $associations_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$associations_table'") === $associations_table;
        
        if (!$associations_table_exists) {
            error_log('No se pudo crear la tabla de asociaciones');
        } else {
            error_log('Tabla de asociaciones creada correctamente');
        }
    }
    
    // Preparar datos para inserción
    $insert_data = [
        'catalog_id' => $catalog_id,
        'product_id' => 0, // ID de producto ficticio para productos personalizados
        'catalog_name' => $name,
        'catalog_description' => $description,
        'catalog_short_description' => $short_description,
        'catalog_price' => $price,
        'catalog_sku' => $sku,
        'catalog_image' => $image,
        'catalog_images' => $images_json
    ];
    
    // Log adicional para verificar los datos justo antes de la inserción
    error_log('Datos finales a insertar en la tabla ' . $catalog_products_table . ': ' . json_encode($insert_data));
    
    $insert_format = ['%d', '%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s'];
    
    // Usar transacción para garantizar integridad
    $wpdb->query('START TRANSACTION');
    
    try {
        // Verificar si el catálogo existe antes de insertar (doble verificación)
        $catalog_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}floresinc_catalogs WHERE id = %d",
            $catalog_id
        ));
        
        if (!$catalog_exists) {
            error_log("Error crítico: El catálogo con ID $catalog_id no existe en la base de datos.");
            throw new Exception("El catálogo con ID $catalog_id no existe");
        } else {
            error_log("Verificación adicional: El catálogo con ID $catalog_id existe correctamente.");
        }
        
        // Verificar la estructura de la tabla antes de insertar
        $table_columns = $wpdb->get_results("DESCRIBE $catalog_products_table");
        $column_names = array_map(function($col) { return $col->Field; }, $table_columns);
        error_log("Columnas en la tabla $catalog_products_table: " . implode(', ', $column_names));
        
        $result = $wpdb->insert(
            $catalog_products_table,
            $insert_data,
            $insert_format
        );
        
        if ($result === false) {
            throw new Exception($wpdb->last_error);
        }
        
        $product_id = $wpdb->insert_id;
        
        if (!$product_id) {
            throw new Exception('No se pudo obtener el ID del producto insertado');
        }
        
        // Verificar que el producto se haya insertado correctamente con el catalog_id correcto
        $inserted_product = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $catalog_products_table WHERE id = %d",
            $product_id
        ));
        
        if (!$inserted_product) {
            error_log("Error: No se pudo recuperar el producto recién insertado con ID $product_id");
            throw new Exception("No se pudo verificar la inserción del producto");
        }
        
        error_log("Producto insertado correctamente: " . json_encode($inserted_product));
        
        // Verificar que el catalog_id se haya guardado correctamente
        if (intval($inserted_product->catalog_id) !== $catalog_id) {
            error_log("Error crítico: El catalog_id guardado ($inserted_product->catalog_id) no coincide con el esperado ($catalog_id)");
        }
        
        // Actualizar el conteo de productos del catálogo (si pertenece a un catálogo)
        if ($catalog_id > 0) {
            // Actualizar la fecha de modificación del catálogo
            $wpdb->update(
                $wpdb->prefix . 'floresinc_catalogs', 
                ['updated_at' => current_time('mysql')], 
                ['id' => $catalog_id],
                ['%s'],
                ['%d']
            );
            
            // Verificar si es necesario actualizar las asociaciones en la tabla floresinc_catalog_product_associations
            if ($associations_table_exists) {
                // Comprobar si ya existe la asociación
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $associations_table WHERE catalog_id = %d AND catalog_product_id = %d",
                    $catalog_id,
                    $product_id
                ));
                
                if (!$existing) {
                    // Crear la asociación
                    $wpdb->insert(
                        $associations_table,
                        [
                            'catalog_id' => $catalog_id,
                            'catalog_product_id' => $product_id
                        ],
                        ['%d', '%d']
                    );
                    
                    if ($wpdb->last_error) {
                        error_log('Error al insertar asociación de producto personalizado: ' . $wpdb->last_error);
                    } else {
                        error_log('Asociación creada correctamente para el producto ID: ' . $product_id . ' con el catálogo ID: ' . $catalog_id);
                    }
                }
            } else {
                error_log('No se pudo crear la asociación porque la tabla no existe');
            }
        }
        
        // Preparar la respuesta
        $response = [
            'id' => $product_id,
            'catalog_id' => $catalog_id,
            'product_id' => 0,
            'name' => $name,
            'catalog_name' => $name,
            'description' => $description,
            'catalog_description' => $description,
            'short_description' => $short_description,
            'catalog_short_description' => $short_description,
            'price' => $price,
            'catalog_price' => $price,
            'sku' => $sku,
            'catalog_sku' => $sku,
            'image' => $image,
            'catalog_image' => $image,
            'images' => $images,
            'catalog_images' => $images,
            'is_custom' => true,
            'created_at' => current_time('mysql')
        ];
        
        // Log de la respuesta
        error_log('Respuesta del endpoint de creación de producto personalizado: ' . json_encode($response));
        
        // Confirmar la transacción
        $wpdb->query('COMMIT');
        
        return new WP_REST_Response($response, 201);
    } 
    catch (Exception $e) {
        // Revertir en caso de error
        $wpdb->query('ROLLBACK');
        error_log('Error al insertar producto personalizado: ' . $e->getMessage());
        return new WP_Error('product_creation_failed', 'Error al crear el producto: ' . $e->getMessage(), ['status' => 500]);
    }
}

/**
 * Endpoint: Actualizar productos personalizados
 */
function floresinc_update_custom_product_endpoint(WP_REST_Request $request) {
    global $wpdb;
    $product_id = $request['id'];
    
    // Obtener y validar los datos
    $params = $request->get_json_params();
    
    if (!isset($params['name']) || empty($params['name'])) {
        return new WP_Error('missing_name', 'El nombre del producto es obligatorio', ['status' => 400]);
    }
    
    $name = sanitize_text_field($params['name']);
    $description = isset($params['description']) ? sanitize_text_field($params['description']) : '';
    $short_description = isset($params['short_description']) ? sanitize_text_field($params['short_description']) : '';
    $price = isset($params['price']) ? floatval($params['price']) : 0;
    $sku = isset($params['sku']) ? sanitize_text_field($params['sku']) : '';
    $image = isset($params['image']) ? sanitize_text_field($params['image']) : '';
    
    $catalog_products_table = $wpdb->prefix . 'floresinc_catalog_products';
    
    // Verificar que el producto existe
    $product_exists = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM $catalog_products_table 
        WHERE id = %d
    ", $product_id));
    
    if (!$product_exists) {
        return new WP_Error('product_not_found', 'Producto no encontrado', ['status' => 404]);
    }
    
    // Actualizar el producto
    $update_data = [
        'catalog_name' => $name,
        'catalog_description' => $description,
        'catalog_short_description' => $short_description,
        'catalog_price' => $price,
        'catalog_sku' => $sku,
        'catalog_image' => $image
    ];
    
    $update_format = ['%s', '%s', '%s', '%f', '%s', '%s'];
    
    $wpdb->update(
        $catalog_products_table,
        $update_data,
        ['id' => $product_id],
        $update_format,
        ['%d']
    );
    
    // Obtener el producto actualizado
    $updated_product = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM $catalog_products_table 
        WHERE id = %d
    ", $product_id), ARRAY_A);
    
    return new WP_REST_Response($updated_product, 200);
}

// Incluir este archivo en functions.php
// Agregar el siguiente código en functions.php:
// require_once __DIR__ . '/inc/catalog-functions.php';
