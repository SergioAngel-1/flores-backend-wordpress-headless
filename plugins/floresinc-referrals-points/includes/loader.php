<?php
/**
 * Cargador principal del plugin FloresInc Referrals & Points
 * 
 * Este archivo carga todos los componentes del plugin FloresInc Referrals & Points
 * organizados en carpetas según su funcionalidad.
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Cargar archivos del núcleo del sistema
require_once dirname(__FILE__) . '/core/database.php';
require_once dirname(__FILE__) . '/core/cache-management.php';

// Cargar archivos de integración
require_once dirname(__FILE__) . '/integrations/woocommerce-integration.php';
require_once dirname(__FILE__) . '/integrations/user-approval-integration.php';

// Cargar archivos de administración
require_once dirname(__FILE__) . '/admin/admin-functions.php';
require_once dirname(__FILE__) . '/admin/admin-panel.php';
require_once dirname(__FILE__) . '/admin/class-transactions-table.php';
require_once dirname(__FILE__) . '/admin/class-referrals-table.php';

// Cargar sistema de puntos
require_once dirname(__FILE__) . '/points/points-loader.php';

// Cargar sistema de referidos
require_once dirname(__FILE__) . '/referrals/referral-loader.php';

// Cargar API REST
require_once dirname(__FILE__) . '/api/api-loader.php';

// Inicializar componentes
floresinc_rp_init_admin();       // Inicializar funciones administrativas
floresinc_rp_init_admin_panel(); // Inicializar panel administrativo
