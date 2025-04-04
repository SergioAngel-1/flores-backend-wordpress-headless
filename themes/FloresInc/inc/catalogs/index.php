<?php
/**
 * Archivo principal para el sistema de catálogos
 * 
 * Este archivo coordina la carga de todos los módulos relacionados con catálogos
 * y proporciona una estructura modular para facilitar el mantenimiento.
 */

// Evitar acceso directo al archivo
if (!defined('ABSPATH')) {
    exit;
}

// Cargar el módulo de base de datos
require_once __DIR__ . '/database.php';

// Cargar los endpoints de la API
require_once __DIR__ . '/api/catalogs.php';
require_once __DIR__ . '/api/products.php';
require_once __DIR__ . '/api/custom-products.php';
require_once __DIR__ . '/api/pdf.php';

/**
 * Inicializar todos los módulos de catálogos
 */
function floresinc_init_catalog_system() {
    // Inicializar la base de datos
    floresinc_init_catalog_database();
    
    // Inicializar los endpoints
    floresinc_init_catalogs_api();
    floresinc_init_catalog_products_api();
    floresinc_init_custom_products_api();
    floresinc_init_catalog_pdf_api();
}

// Inicializar el sistema de catálogos
add_action('init', 'floresinc_init_catalog_system');
