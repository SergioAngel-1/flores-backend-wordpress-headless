<?php
/**
 * Funciones para la gestión de la base de datos de catálogos
 */

// Evitar acceso directo al archivo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Inicializar la base de datos del sistema de catálogos
 */
function floresinc_init_catalog_database() {
    // Registrar la función para la activación del tema
    add_action('after_switch_theme', 'floresinc_create_catalog_tables');
    
    // Verificar y actualizar las tablas cuando sea necesario
    floresinc_check_and_update_tables();
}

/**
 * Crear las tablas para los catálogos en la base de datos
 */
function floresinc_create_catalog_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Tabla para catálogos
    $catalog_table = $wpdb->prefix . 'floresinc_catalogs';
    $catalog_products_table = $wpdb->prefix . 'floresinc_catalog_products';
    
    $catalogs_sql = "CREATE TABLE $catalog_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        logo_url varchar(255) NULL,
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
        product_price decimal(10,6) NULL,
        catalog_price decimal(10,6) NULL,
        catalog_name varchar(255) NULL,
        catalog_description text NULL,
        catalog_short_description text NULL,
        catalog_sku varchar(100) NULL,
        catalog_image varchar(255) NULL,
        catalog_images text NULL,
        is_custom tinyint(1) NOT NULL DEFAULT 0,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY catalog_id (catalog_id),
        KEY product_id (product_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($catalogs_sql);
    dbDelta($catalog_products_sql);
    
    // Verificar que todas las columnas estén actualizadas
    floresinc_check_and_update_tables();
}

/**
 * Verificar y actualizar la estructura de las tablas
 */
function floresinc_check_and_update_tables() {
    global $wpdb;
    
    // Tabla de catálogos
    $catalog_table = $wpdb->prefix . 'floresinc_catalogs';
    $catalog_products_table = $wpdb->prefix . 'floresinc_catalog_products';
    
    // Verificar si existen las tablas
    $catalog_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$catalog_table'") == $catalog_table;
    $products_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$catalog_products_table'") == $catalog_products_table;
    
    if (!$catalog_table_exists || !$products_table_exists) {
        // Si no existen, volver a crearlas
        floresinc_create_catalog_tables();
        return;
    }
    
    // Verificar y agregar columnas que puedan faltar en la tabla de productos
    $columns_to_check = [
        'catalog_price' => "ALTER TABLE $catalog_products_table ADD COLUMN catalog_price decimal(10,6) NULL AFTER product_price;",
        'catalog_name' => "ALTER TABLE $catalog_products_table ADD COLUMN catalog_name varchar(255) NULL AFTER catalog_price;",
        'catalog_description' => "ALTER TABLE $catalog_products_table ADD COLUMN catalog_description text NULL AFTER catalog_name;",
        'catalog_short_description' => "ALTER TABLE $catalog_products_table ADD COLUMN catalog_short_description text NULL AFTER catalog_description;",
        'catalog_sku' => "ALTER TABLE $catalog_products_table ADD COLUMN catalog_sku varchar(100) NULL AFTER catalog_short_description;",
        'catalog_image' => "ALTER TABLE $catalog_products_table ADD COLUMN catalog_image varchar(255) NULL AFTER catalog_sku;",
        'catalog_images' => "ALTER TABLE $catalog_products_table ADD COLUMN catalog_images text NULL AFTER catalog_image;",
        'is_custom' => "ALTER TABLE $catalog_products_table ADD COLUMN is_custom tinyint(1) NOT NULL DEFAULT 0 AFTER catalog_images;"
    ];
    
    // Obtener las columnas actuales
    $existing_columns = $wpdb->get_results("DESCRIBE $catalog_products_table", ARRAY_A);
    $existing_column_names = array_column($existing_columns, 'Field');
    
    // Verificar cada columna y agregarla si no existe
    foreach ($columns_to_check as $column => $query) {
        if (!in_array($column, $existing_column_names)) {
            $wpdb->query($query);
        }
    }
}

/**
 * Obtener estructura de tablas para diagnóstico
 */
function floresinc_get_table_structure() {
    global $wpdb;
    
    $tables = [
        $wpdb->prefix . 'floresinc_catalogs',
        $wpdb->prefix . 'floresinc_catalog_products'
    ];
    
    $structure = [];
    
    foreach ($tables as $table) {
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
        
        if ($exists) {
            $columns = $wpdb->get_results("DESCRIBE $table", ARRAY_A);
            $structure[$table] = $columns;
        } else {
            $structure[$table] = ['exists' => false];
        }
    }
    
    return $structure;
}
