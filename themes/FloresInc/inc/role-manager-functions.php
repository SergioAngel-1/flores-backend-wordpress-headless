<?php
/**
 * Funciones para gestionar roles y capacidades personalizadas
 * 
 * Este archivo contiene las funciones para configurar el rol "Gestor de la tienda"
 * con permisos específicos, limitando su acceso a ciertas secciones del panel de WordPress.
 */

// Evitar acceso directo al archivo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Función para configurar el rol "Gestor de la tienda"
 * Esta función se ejecuta durante la activación del tema o cuando se llama explícitamente
 */
function floresinc_configure_shop_manager_role() {
    // Verificar si el rol ya existe
    $role = get_role('shop_manager');
    
    // Si no existe, crearlo
    if (!$role) {
        add_role(
            'shop_manager',
            'Gestor de la tienda',
            array(
                'read' => true,
                'edit_posts' => true,
                'delete_posts' => false,
                'publish_posts' => false,
                'upload_files' => true,
            )
        );
        $role = get_role('shop_manager');
    }
    
    // Capacidades para WooCommerce
    $woocommerce_caps = array(
        'manage_woocommerce' => true,
        'view_woocommerce_reports' => true,
        'edit_product' => true,
        'read_product' => true,
        'delete_product' => true,
        'edit_products' => true,
        'edit_others_products' => true,
        'publish_products' => true,
        'read_private_products' => true,
        'delete_products' => true,
        'delete_private_products' => true,
        'delete_published_products' => true,
        'delete_others_products' => true,
        'edit_private_products' => true,
        'edit_published_products' => true,
        'manage_product_terms' => true,
        'edit_product_terms' => true,
        'delete_product_terms' => true,
        'assign_product_terms' => true,
        'edit_shop_order' => true,
        'read_shop_order' => true,
        'delete_shop_order' => true,
        'edit_shop_orders' => true,
        'edit_others_shop_orders' => true,
        'publish_shop_orders' => true,
        'read_private_shop_orders' => true,
        'delete_shop_orders' => true,
        'delete_private_shop_orders' => true,
        'delete_published_shop_orders' => true,
        'delete_others_shop_orders' => true,
        'edit_private_shop_orders' => true,
        'edit_published_shop_orders' => true,
        'manage_shop_order_terms' => true,
        'edit_shop_order_terms' => true,
        'delete_shop_order_terms' => true,
        'assign_shop_order_terms' => true,
    );
    
    // Asignar capacidades de WooCommerce
    foreach ($woocommerce_caps as $cap => $grant) {
        $role->add_cap($cap, $grant);
    }
    
    // Capacidades para WordPress
    $wordpress_caps = array(
        'edit_pages' => true,
        'edit_others_pages' => true,
        'edit_published_pages' => true,
        'publish_pages' => true,
        'read_private_pages' => true,
        'edit_theme_options' => false, // No permitir editar temas
        'update_plugins' => false,     // No permitir actualizar plugins
        'install_plugins' => false,    // No permitir instalar plugins
        'activate_plugins' => false,   // No permitir activar plugins
        'update_core' => false,        // No permitir actualizaciones del core
        'list_users' => true,          // Permitir ver usuarios
        'promote_users' => false,      // No permitir promover usuarios
        'remove_users' => false,       // No permitir eliminar usuarios
        'add_users' => false,          // No permitir añadir usuarios
        'create_users' => false,       // No permitir crear usuarios
        'manage_categories' => true,   // Permitir gestionar categorías
    );
    
    // Asignar capacidades de WordPress
    foreach ($wordpress_caps as $cap => $grant) {
        $role->add_cap($cap, $grant);
    }
    
    // Registrar que se ha configurado el rol
    update_option('floresinc_shop_manager_configured', true);
}

/**
 * Función para ocultar elementos del menú de administración para el rol "Gestor de la tienda"
 */
function floresinc_remove_admin_menu_items() {
    // Verificar si el usuario actual tiene el rol "Gestor de la tienda"
    $user = wp_get_current_user();
    if (in_array('shop_manager', (array) $user->roles)) {
        // Elementos a ocultar
        remove_menu_page('plugins.php');                // Plugins
        remove_menu_page('tools.php');                  // Herramientas
        remove_menu_page('options-general.php');        // Ajustes
        remove_menu_page('edit-comments.php');          // Comentarios
        remove_menu_page('themes.php');                 // Apariencia
        remove_menu_page('users.php');                  // Usuarios
        
        // Submenús de WooCommerce a ocultar
        remove_submenu_page('woocommerce', 'wc-settings');  // Ajustes de WooCommerce
        remove_submenu_page('woocommerce', 'wc-status');    // Estado del sistema
        remove_submenu_page('woocommerce', 'wc-addons');    // Extensiones
        
        // Ocultar elementos del panel de WordPress
        remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
        remove_meta_box('dashboard_primary', 'dashboard', 'side');
        remove_meta_box('dashboard_site_health', 'dashboard', 'normal');
    }
}

/**
 * Función para añadir elementos personalizados al menú para el rol "Gestor de la tienda"
 */
function floresinc_add_custom_menu_items() {
    // Verificar si el usuario actual tiene el rol "Gestor de la tienda"
    $user = wp_get_current_user();
    if (in_array('shop_manager', (array) $user->roles)) {
        // Añadir menú para funciones personalizadas
        add_menu_page(
            'Funciones Personalizadas',
            'Funciones Personalizadas',
            'manage_woocommerce',
            'floresinc-custom-functions',
            'floresinc_custom_functions_page',
            'dashicons-admin-generic',
            25
        );
        
        // Submenús para funciones personalizadas
        add_submenu_page(
            'floresinc-custom-functions',
            'Banners',
            'Banners',
            'manage_woocommerce',
            'floresinc-banners',
            'floresinc_banners_page'
        );
        
        add_submenu_page(
            'floresinc-custom-functions',
            'Categorías Destacadas',
            'Categorías Destacadas',
            'manage_woocommerce',
            'floresinc-featured-categories',
            'floresinc_featured_categories_page'
        );
        
        add_submenu_page(
            'floresinc-custom-functions',
            'Hiperofertas',
            'Hiperofertas',
            'manage_woocommerce',
            'floresinc-hiperofertas',
            'floresinc_hiperofertas_page'
        );
        
        add_submenu_page(
            'floresinc-custom-functions',
            'Grilla Promocional',
            'Grilla Promocional',
            'manage_woocommerce',
            'floresinc-promotional-grid',
            'floresinc_promotional_grid_page'
        );
    }
}

/**
 * Función para crear la página principal de funciones personalizadas
 */
function floresinc_custom_functions_page() {
    ?>
    <div class="wrap">
        <h1>Funciones Personalizadas</h1>
        <p>Bienvenido al panel de funciones personalizadas de Flores INC. Desde aquí puede gestionar diferentes aspectos de la tienda.</p>
        
        <div class="card" style="max-width: 800px; margin-top: 20px; padding: 20px; background-color: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h2>Funciones Disponibles</h2>
            <ul style="list-style-type: disc; margin-left: 20px;">
                <li><a href="<?php echo admin_url('admin.php?page=floresinc-banners'); ?>">Banners</a> - Gestionar banners y sliders de la tienda</li>
                <li><a href="<?php echo admin_url('admin.php?page=floresinc-featured-categories'); ?>">Categorías Destacadas</a> - Configurar categorías destacadas</li>
                <li><a href="<?php echo admin_url('admin.php?page=floresinc-hiperofertas'); ?>">Hiperofertas</a> - Gestionar ofertas especiales</li>
                <li><a href="<?php echo admin_url('admin.php?page=floresinc-promotional-grid'); ?>">Grilla Promocional</a> - Configurar la grilla promocional</li>
            </ul>
        </div>
    </div>
    <?php
}

/**
 * Páginas de funciones específicas (implementar según sea necesario)
 */
function floresinc_banners_page() {
    // Implementar interfaz para gestionar banners
    echo '<div class="wrap"><h1>Gestión de Banners</h1><p>Aquí puede gestionar los banners de la tienda.</p></div>';
}

function floresinc_featured_categories_page() {
    // Implementar interfaz para gestionar categorías destacadas
    echo '<div class="wrap"><h1>Categorías Destacadas</h1><p>Aquí puede gestionar las categorías destacadas.</p></div>';
}

function floresinc_hiperofertas_page() {
    // Implementar interfaz para gestionar hiperofertas
    echo '<div class="wrap"><h1>Hiperofertas</h1><p>Aquí puede gestionar las ofertas especiales.</p></div>';
}

function floresinc_promotional_grid_page() {
    // Implementar interfaz para gestionar la grilla promocional
    echo '<div class="wrap"><h1>Grilla Promocional</h1><p>Aquí puede gestionar la grilla promocional.</p></div>';
}

/**
 * Función para ocultar notificaciones de actualización para usuarios no administradores
 */
function floresinc_hide_update_notices() {
    if (!current_user_can('update_core')) {
        remove_action('admin_notices', 'update_nag', 3);
        remove_action('admin_notices', 'maintenance_nag', 10);
    }
}

// Hooks para ejecutar las funciones
add_action('init', 'floresinc_configure_shop_manager_role');
add_action('admin_menu', 'floresinc_remove_admin_menu_items', 999);
add_action('admin_menu', 'floresinc_add_custom_menu_items', 10);
add_action('admin_head', 'floresinc_hide_update_notices', 1);

/**
 * Función para ejecutar durante la activación del tema
 */
function floresinc_theme_activation() {
    floresinc_configure_shop_manager_role();
}
register_activation_hook(get_stylesheet_directory() . '/functions.php', 'floresinc_theme_activation');
