<?php
/**
 * Funciones relacionadas con WooCommerce
 * 
 * Este archivo contiene funciones personalizadas para extender WooCommerce
 */

// Verificar si WooCommerce está activo
if (!function_exists('is_woocommerce_activated')) {
    function is_woocommerce_activated() {
        return class_exists('WooCommerce');
    }
}

// Añadir soporte para WooCommerce en el tema
function floresinc_woocommerce_support() {
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
}
add_action('after_setup_theme', 'floresinc_woocommerce_support');

// Personalizar los endpoints de la API de WooCommerce
function floresinc_customize_woocommerce_rest_api() {
    // Permitir acceso público a los endpoints de productos y categorías
    add_filter('woocommerce_rest_check_permissions', 'floresinc_allow_public_access_to_products', 10, 4);
}
add_action('init', 'floresinc_customize_woocommerce_rest_api');

// Permitir acceso público a los endpoints de productos y categorías
function floresinc_allow_public_access_to_products($permission, $context, $object_id, $post_type) {
    // Permitir acceso a productos y categorías de productos
    if ($post_type === 'product' || $post_type === 'product_cat') {
        return true;
    }
    
    return $permission;
}

// Personalizar la respuesta de la API de WooCommerce para productos
function floresinc_customize_product_response($response, $post, $request) {
    if ($post->post_type !== 'product') {
        return $response;
    }
    
    // Personalizar la respuesta aquí si es necesario
    
    return $response;
}
add_filter('woocommerce_rest_prepare_product', 'floresinc_customize_product_response', 10, 3);

/**
 * Asegurarse de que las categorías de productos de WooCommerce estén disponibles en los menús
 */
function floresinc_ensure_product_categories_in_menus() {
    // Verificar si WooCommerce está activo
    if (!class_exists('WooCommerce')) {
        return;
    }
    
    // Registrar la taxonomía product_cat para que aparezca en los menús
    register_taxonomy_for_object_type('product_cat', 'product');
    
    // Asegurarse de que la taxonomía product_cat sea visible en los menús
    add_filter('register_taxonomy_args', function($args, $taxonomy) {
        if ($taxonomy === 'product_cat') {
            $args['show_in_nav_menus'] = true;
            
            // Mejorar las etiquetas para el menú
            if (!isset($args['labels']['name'])) {
                $args['labels']['name'] = __('Categorías de Productos', 'floresinc');
            }
            
            if (!isset($args['labels']['singular_name'])) {
                $args['labels']['singular_name'] = __('Categoría de Producto', 'floresinc');
            }
            
            if (!isset($args['labels']['menu_name'])) {
                $args['labels']['menu_name'] = __('Categorías de Productos', 'floresinc');
            }
        }
        return $args;
    }, 10, 2);
}
add_action('init', 'floresinc_ensure_product_categories_in_menus', 5); // Prioridad baja para que se ejecute antes de que WooCommerce registre sus taxonomías

/**
 * Añadir información de depuración sobre taxonomías en la pantalla de menús
 */
function floresinc_debug_taxonomies_in_menu_screen() {
    // Solo mostrar en la pantalla de menús
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'nav-menus') {
        return;
    }
    
    // Verificar si WooCommerce está activo
    if (!class_exists('WooCommerce')) {
        echo '<div class="notice notice-error"><p><strong>Error:</strong> WooCommerce no está activado. Las categorías de productos no estarán disponibles en los menús.</p></div>';
        return;
    }
    
    // Obtener todas las taxonomías registradas
    $taxonomies = get_taxonomies(array(), 'objects');
    
    // Verificar si product_cat está registrada
    if (!isset($taxonomies['product_cat'])) {
        echo '<div class="notice notice-error"><p><strong>Error:</strong> La taxonomía product_cat no está registrada. Esto puede indicar un problema con la instalación de WooCommerce.</p></div>';
        return;
    }
    
    // Verificar si product_cat está configurada para aparecer en los menús
    $product_cat = $taxonomies['product_cat'];
    if (!$product_cat->show_in_nav_menus) {
        echo '<div class="notice notice-warning"><p><strong>Advertencia:</strong> La taxonomía product_cat no está configurada para aparecer en los menús (show_in_nav_menus = false).</p></div>';
    } else {
        // Contar categorías de productos
        $categories_count = wp_count_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false
        ));
        
        if (is_wp_error($categories_count) || $categories_count == 0) {
            echo '<div class="notice notice-warning"><p><strong>Advertencia:</strong> No hay categorías de productos disponibles. Crea algunas categorías en WooCommerce > Productos > Categorías.</p></div>';
        } else {
            echo '<div class="notice notice-success"><p><strong>Información:</strong> Hay ' . $categories_count . ' categorías de productos disponibles para usar en los menús. Busca "Categorías de Productos" en la columna izquierda.</p></div>';
        }
    }
}
add_action('admin_notices', 'floresinc_debug_taxonomies_in_menu_screen');

/**
 * Registrar endpoint de API REST para obtener categorías de productos
 */
function floresinc_register_product_categories_rest_route() {
    register_rest_route('floresinc/v1', '/product-categories', array(
        'methods' => 'GET',
        'callback' => 'floresinc_get_product_categories_callback',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'floresinc_register_product_categories_rest_route');

/**
 * Callback para el endpoint de categorías de productos
 */
function floresinc_get_product_categories_callback($request) {
    // Verificar si WooCommerce está activo
    if (!class_exists('WooCommerce')) {
        return new WP_Error('woocommerce_inactive', 'WooCommerce no está activo', array('status' => 500));
    }
    
    // Parámetros de la solicitud
    $parent = isset($request['parent']) ? intval($request['parent']) : 0;
    $hide_empty = isset($request['hide_empty']) ? filter_var($request['hide_empty'], FILTER_VALIDATE_BOOLEAN) : false;
    $slug = isset($request['slug']) ? sanitize_text_field($request['slug']) : '';
    
    // Si se proporciona un slug, buscar por slug
    if (!empty($slug)) {
        // Intentar encontrar la categoría por slug exacto primero
        $category = get_term_by('slug', $slug, 'product_cat');
        
        // Si no se encuentra, intentar buscar con slugs normalizados
        if (!$category) {
            // Normalizar el slug de búsqueda (eliminar acentos, convertir a minúsculas, etc.)
            $normalized_search_slug = floresinc_normalize_slug($slug);
            
            // Obtener todas las categorías
            $all_categories = get_terms(array(
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
                'exclude' => get_option('default_product_cat', 0)
            ));
            
            if (!is_wp_error($all_categories)) {
                foreach ($all_categories as $cat) {
                    $normalized_cat_slug = floresinc_normalize_slug($cat->slug);
                    if ($normalized_cat_slug === $normalized_search_slug) {
                        $category = $cat;
                        break;
                    }
                }
            }
        }
        
        if ($category) {
            $category_data = floresinc_format_product_category($category);
            return array($category_data);
        } else {
            return new WP_Error(
                'category_not_found', 
                'Categoría no encontrada: ' . $slug, 
                array(
                    'status' => 404,
                    'slug' => $slug,
                    'normalized_slug' => floresinc_normalize_slug($slug)
                )
            );
        }
    }
    
    // Obtener categorías
    $args = array(
        'taxonomy' => 'product_cat',
        'orderby' => 'name',
        'order' => 'ASC',
        'hide_empty' => $hide_empty,
        'parent' => $parent,
        'exclude' => get_option('default_product_cat', 0) // Excluir "Sin categoría"
    );
    
    $product_categories = get_terms($args);
    
    if (is_wp_error($product_categories)) {
        return new WP_Error('categories_error', 'Error al obtener las categorías de productos', array('status' => 500));
    }
    
    if (empty($product_categories)) {
        // Si no hay categorías con el padre especificado, intentar obtener todas
        if ($parent !== 0) {
            $args['parent'] = 0;
            $product_categories = get_terms($args);
            
            if (is_wp_error($product_categories) || empty($product_categories)) {
                return new WP_Error('no_categories', 'No hay categorías de productos disponibles', array('status' => 404));
            }
        } else {
            return new WP_Error('no_categories', 'No hay categorías de productos disponibles', array('status' => 404));
        }
    }
    
    $categories_data = array();
    
    foreach ($product_categories as $category) {
        $categories_data[] = floresinc_format_product_category($category);
    }
    
    return $categories_data;
}

/**
 * Formatear una categoría de producto para la API
 */
function floresinc_format_product_category($category) {
    $category_data = array(
        'id' => $category->term_id,
        'name' => $category->name,
        'slug' => $category->slug,
        'normalized_slug' => floresinc_normalize_slug($category->slug),
        'description' => $category->description,
        'count' => $category->count,
        'parent' => $category->parent,
        'link' => get_term_link($category->term_id, 'product_cat')
    );
    
    // Obtener imagen de la categoría
    $thumbnail_id = get_term_meta($category->term_id, 'thumbnail_id', true);
    if ($thumbnail_id) {
        $category_data['image'] = wp_get_attachment_url($thumbnail_id);
    }
    
    // Verificar si tiene subcategorías
    $has_children = get_terms(array(
        'taxonomy' => 'product_cat',
        'parent' => $category->term_id,
        'hide_empty' => false,
        'fields' => 'ids',
        'number' => 1 // Solo necesitamos saber si hay al menos una
    ));
    
    $category_data['has_children'] = !empty($has_children) && !is_wp_error($has_children);
    
    return $category_data;
}

/**
 * Normalizar un slug (eliminar acentos, convertir a minúsculas, etc.)
 */
function floresinc_normalize_slug($text) {
    if (empty($text)) {
        return '';
    }
    
    // Convertir a minúsculas
    $text = strtolower($text);
    
    // Eliminar acentos
    $text = remove_accents($text);
    
    // Eliminar caracteres especiales y reemplazar espacios por guiones
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/\s+/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    
    // Eliminar guiones al principio y al final
    $text = trim($text, '-');
    
    return $text;
}
