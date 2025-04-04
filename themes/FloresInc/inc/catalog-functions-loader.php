<?php
/**
 * Cargador del sistema modular de catálogos
 * 
 * Este archivo sirve como puente entre el anterior sistema monolítico
 * y la nueva estructura modular de archivos para la API de catálogos.
 */

// Evitar acceso directo al archivo
if (!defined('ABSPATH')) {
    exit;
}

// Cargar el sistema modular de catálogos
require_once __DIR__ . '/catalogs/index.php';

// Este archivo debe ser incluido en functions.php:
// require_once __DIR__ . '/inc/catalog-functions-loader.php';

// Opcional: Puedes eliminar el archivo catalog-functions.php después de verificar 
// que todo funciona correctamente con la nueva estructura modular.
