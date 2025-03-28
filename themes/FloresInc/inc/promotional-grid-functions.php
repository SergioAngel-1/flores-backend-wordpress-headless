<?php
/**
 * Funciones para la grilla publicitaria de productos
 *
 * @package FloresInc
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Registrar el tipo de post personalizado para la grilla publicitaria
 */
function floresinc_register_promotional_grid_post_type() {
    $labels = array(
        'name'                  => _x('Grillas Publicitarias', 'Post Type General Name', 'floresinc'),
        'singular_name'         => _x('Grilla Publicitaria', 'Post Type Singular Name', 'floresinc'),
        'menu_name'             => __('Grillas Publicitarias', 'floresinc'),
        'name_admin_bar'        => __('Grilla Publicitaria', 'floresinc'),
        'archives'              => __('Archivos de Grilla', 'floresinc'),
        'attributes'            => __('Atributos de Grilla', 'floresinc'),
        'parent_item_colon'     => __('Grilla Padre:', 'floresinc'),
        'all_items'             => __('Todas las Grillas', 'floresinc'),
        'add_new_item'          => __('Añadir Nueva Grilla', 'floresinc'),
        'add_new'               => __('Añadir Nueva', 'floresinc'),
        'new_item'              => __('Nueva Grilla', 'floresinc'),
        'edit_item'             => __('Editar Grilla', 'floresinc'),
        'update_item'           => __('Actualizar Grilla', 'floresinc'),
        'view_item'             => __('Ver Grilla', 'floresinc'),
        'view_items'            => __('Ver Grillas', 'floresinc'),
        'search_items'          => __('Buscar Grilla', 'floresinc'),
        'not_found'             => __('No encontrado', 'floresinc'),
        'not_found_in_trash'    => __('No encontrado en la papelera', 'floresinc'),
        'featured_image'        => __('Imagen Destacada', 'floresinc'),
        'set_featured_image'    => __('Establecer imagen destacada', 'floresinc'),
        'remove_featured_image' => __('Eliminar imagen destacada', 'floresinc'),
        'use_featured_image'    => __('Usar como imagen destacada', 'floresinc'),
        'insert_into_item'      => __('Insertar en la grilla', 'floresinc'),
        'uploaded_to_this_item' => __('Subido a esta grilla', 'floresinc'),
        'items_list'            => __('Lista de grillas', 'floresinc'),
        'items_list_navigation' => __('Navegación de lista de grillas', 'floresinc'),
        'filter_items_list'     => __('Filtrar lista de grillas', 'floresinc'),
    );
    
    $args = array(
        'label'                 => __('Grilla Publicitaria', 'floresinc'),
        'description'           => __('Grillas publicitarias para mostrar productos destacados', 'floresinc'),
        'labels'                => $labels,
        'supports'              => array('title'),
        'hierarchical'          => false,
        'public'                => false,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 25,
        'menu_icon'             => 'dashicons-grid-view',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => false,
        'can_export'            => true,
        'has_archive'           => false,
        'exclude_from_search'   => true,
        'publicly_queryable'    => false,
        'capability_type'       => 'page',
        'show_in_rest'          => true,
    );
    
    register_post_type('promotional_grid', $args);
}
add_action('init', 'floresinc_register_promotional_grid_post_type');

/**
 * Añadir metaboxes para la grilla publicitaria
 */
function floresinc_add_promotional_grid_meta_boxes() {
    add_meta_box(
        'promotional_grid_products',
        __('Productos de la Grilla', 'floresinc'),
        'floresinc_promotional_grid_products_callback',
        'promotional_grid',
        'normal',
        'high'
    );
    
    add_meta_box(
        'promotional_grid_status',
        __('Estado de la Grilla', 'floresinc'),
        'floresinc_promotional_grid_status_callback',
        'promotional_grid',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'floresinc_add_promotional_grid_meta_boxes');

/**
 * Callback para el metabox de estado de la grilla
 */
function floresinc_promotional_grid_status_callback($post) {
    wp_nonce_field('floresinc_promotional_grid_status_nonce', 'promotional_grid_status_nonce');
    
    // Obtener el estado actual
    $is_active = get_post_meta($post->ID, '_promotional_grid_active', true);
    $is_active = !empty($is_active) ? true : false;
    
    // Obtener la grilla activa actual
    $active_grid_id = get_option('floresinc_active_promotional_grid', 0);
    $is_current_active = ($active_grid_id == $post->ID);
    
    ?>
    <div class="promotional-grid-status">
        <p>
            <label>
                <input type="checkbox" name="promotional_grid_active" value="1" <?php checked($is_active, true); ?>>
                <?php _e('Activar esta grilla', 'floresinc'); ?>
            </label>
        </p>
        
        <?php if ($is_current_active) : ?>
            <div class="active-grid-notice" style="background-color: #e7f7e3; border-left: 4px solid #46b450; padding: 8px 12px; margin-top: 10px;">
                <p style="margin: 0;">
                    <strong><?php _e('Esta grilla está actualmente activa', 'floresinc'); ?></strong>
                </p>
            </div>
        <?php else : ?>
            <?php if ($active_grid_id > 0 && $is_active) : ?>
                <div class="notice-warning" style="background-color: #fff8e5; border-left: 4px solid #ffb900; padding: 8px 12px; margin-top: 10px;">
                    <p style="margin: 0;">
                        <?php _e('Al activar esta grilla, se desactivará la grilla actualmente activa.', 'floresinc'); ?>
                    </p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <p class="description">
            <?php _e('Solo una grilla puede estar activa a la vez. Al activar esta grilla, se desactivará cualquier otra grilla activa.', 'floresinc'); ?>
        </p>
    </div>
    <?php
}

/**
 * Callback para el metabox de productos de la grilla
 */
function floresinc_promotional_grid_products_callback($post) {
    wp_nonce_field('floresinc_promotional_grid_nonce', 'promotional_grid_nonce');
    
    // Obtener valores guardados
    $products = get_post_meta($post->ID, '_promotional_grid_products', true);
    $products = !empty($products) ? $products : array('', '', '');
    
    // Asegurarse de que siempre hay 3 productos
    while (count($products) < 3) {
        $products[] = '';
    }
    
    // Obtener todos los productos de WooCommerce
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    );
    
    $all_products = get_posts($args);
    ?>
    
    <p><?php _e('Selecciona 3 productos para mostrar en la grilla publicitaria.', 'floresinc'); ?></p>
    
    <table class="form-table">
        <?php for ($i = 0; $i < 3; $i++) : ?>
            <tr>
                <th>
                    <label for="promotional_grid_product_<?php echo $i; ?>">
                        <?php printf(__('Producto %d', 'floresinc'), $i + 1); ?>
                    </label>
                </th>
                <td>
                    <select name="promotional_grid_products[]" id="promotional_grid_product_<?php echo $i; ?>" class="regular-text">
                        <option value=""><?php _e('-- Seleccionar Producto --', 'floresinc'); ?></option>
                        <?php foreach ($all_products as $product) : ?>
                            <option value="<?php echo $product->ID; ?>" <?php selected($products[$i], $product->ID); ?>>
                                <?php echo $product->post_title; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <?php if (!empty($products[$i])) : 
                        $product_image = get_the_post_thumbnail_url($products[$i], 'thumbnail');
                        if ($product_image) : ?>
                            <div style="margin-top: 10px;">
                                <img src="<?php echo esc_url($product_image); ?>" alt="<?php echo get_the_title($products[$i]); ?>" style="max-width: 100px; height: auto;">
                            </div>
                        <?php endif;
                    endif; ?>
                </td>
            </tr>
        <?php endfor; ?>
    </table>
    
    <?php
}

/**
 * Guardar los datos del metabox
 */
function floresinc_save_promotional_grid_meta($post_id) {
    // Verificar nonce para productos
    if (isset($_POST['promotional_grid_nonce']) && wp_verify_nonce($_POST['promotional_grid_nonce'], 'floresinc_promotional_grid_nonce')) {
        // Verificar autoguardado
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Guardar productos
        if (isset($_POST['promotional_grid_products'])) {
            $products = array_map('sanitize_text_field', $_POST['promotional_grid_products']);
            update_post_meta($post_id, '_promotional_grid_products', $products);
        }
    }
    
    // Verificar nonce para estado
    if (isset($_POST['promotional_grid_status_nonce']) && wp_verify_nonce($_POST['promotional_grid_status_nonce'], 'floresinc_promotional_grid_status_nonce')) {
        // Verificar autoguardado
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Guardar estado
        $is_active = isset($_POST['promotional_grid_active']) ? 1 : 0;
        update_post_meta($post_id, '_promotional_grid_active', $is_active);
        
        // Si está activa, actualizar la opción global y desactivar las demás
        if ($is_active) {
            update_option('floresinc_active_promotional_grid', $post_id);
            
            // Desactivar otras grillas
            $args = array(
                'post_type'      => 'promotional_grid',
                'posts_per_page' => -1,
                'post__not_in'   => array($post_id),
                'meta_query'     => array(
                    array(
                        'key'     => '_promotional_grid_active',
                        'value'   => '1',
                        'compare' => '=',
                    ),
                ),
            );
            
            $other_active_grids = get_posts($args);
            
            foreach ($other_active_grids as $grid) {
                update_post_meta($grid->ID, '_promotional_grid_active', 0);
            }
        } else {
            // Si se está desactivando la grilla activa, limpiar la opción global
            $active_grid_id = get_option('floresinc_active_promotional_grid', 0);
            if ($active_grid_id == $post_id) {
                update_option('floresinc_active_promotional_grid', 0);
            }
        }
    }
}
add_action('save_post_promotional_grid', 'floresinc_save_promotional_grid_meta');

/**
 * Obtener los productos de la grilla publicitaria
 */
function floresinc_get_promotional_grid_products($grid_id = null) {
    // Si no se proporciona un ID, obtener la grilla activa
    if (empty($grid_id)) {
        $grid_id = get_option('floresinc_active_promotional_grid', 0);
        
        // Si no hay grilla activa, buscar la primera grilla con estado activo
        if (empty($grid_id)) {
            $args = array(
                'post_type'      => 'promotional_grid',
                'posts_per_page' => 1,
                'meta_key'       => '_promotional_grid_active',
                'meta_value'     => '1',
            );
            
            $active_grids = get_posts($args);
            
            if (!empty($active_grids)) {
                $grid_id = $active_grids[0]->ID;
                // Actualizar la opción global
                update_option('floresinc_active_promotional_grid', $grid_id);
            } else {
                // Si no hay grillas activas, usar la más reciente
                $args = array(
                    'post_type'      => 'promotional_grid',
                    'posts_per_page' => 1,
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                );
                
                $grids = get_posts($args);
                
                if (empty($grids)) {
                    return array();
                }
                
                $grid_id = $grids[0]->ID;
            }
        }
    }
    
    // Obtener los IDs de productos
    $product_ids = get_post_meta($grid_id, '_promotional_grid_products', true);
    
    if (empty($product_ids)) {
        return array();
    }
    
    // Obtener datos de productos
    $products = array();
    
    foreach ($product_ids as $product_id) {
        if (empty($product_id)) {
            continue;
        }
        
        $product = wc_get_product($product_id);
        
        if (!$product) {
            continue;
        }
        
        // Formatear el precio en COP
        $price = $product->get_price();
        $formatted_price = 'COP ' . number_format($price, 0, ',', '.');
        
        // Si hay precio de oferta, obtenerlo también
        $regular_price = $product->get_regular_price();
        $sale_price = $product->get_sale_price();
        $has_sale = !empty($sale_price) && $sale_price < $regular_price;
        
        $formatted_regular_price = '';
        if ($has_sale) {
            $formatted_regular_price = 'COP ' . number_format($regular_price, 0, ',', '.');
        }
        
        $products[] = array(
            'id'            => $product_id,
            'title'         => $product->get_name(),
            'image'         => wp_get_attachment_url($product->get_image_id()),
            'url'           => get_permalink($product_id),
            'price'         => $formatted_price,
            'regular_price' => $formatted_regular_price,
            'has_sale'      => $has_sale
        );
    }
    
    return $products;
}

/**
 * Endpoint de la API REST para obtener los productos de la grilla publicitaria
 */
function floresinc_register_promotional_grid_rest_route() {
    register_rest_route('floresinc/v1', '/promotional-grid', array(
        'methods'  => 'GET',
        'callback' => 'floresinc_get_promotional_grid_rest',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'floresinc_register_promotional_grid_rest_route');

/**
 * Callback para el endpoint de la API REST
 */
function floresinc_get_promotional_grid_rest() {
    $products = floresinc_get_promotional_grid_products();
    
    return new WP_REST_Response($products, 200);
}

/**
 * Añadir columna de estado en la lista de grillas
 */
function floresinc_add_promotional_grid_columns($columns) {
    $new_columns = array();
    
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        
        if ($key === 'title') {
            $new_columns['status'] = __('Estado', 'floresinc');
        }
    }
    
    return $new_columns;
}
add_filter('manage_promotional_grid_posts_columns', 'floresinc_add_promotional_grid_columns');

/**
 * Mostrar el contenido de la columna de estado
 */
function floresinc_show_promotional_grid_column_content($column, $post_id) {
    if ($column === 'status') {
        $is_active = get_post_meta($post_id, '_promotional_grid_active', true);
        $active_grid_id = get_option('floresinc_active_promotional_grid', 0);
        
        if ($active_grid_id == $post_id) {
            echo '<span class="active-status" style="color: #46b450; font-weight: bold;">' . __('Activa', 'floresinc') . '</span>';
        } elseif ($is_active) {
            echo '<span class="pending-status" style="color: #ffb900;">' . __('Activada (pendiente)', 'floresinc') . '</span>';
        } else {
            echo '<span class="inactive-status" style="color: #dc3232;">' . __('Inactiva', 'floresinc') . '</span>';
        }
    }
}
add_action('manage_promotional_grid_posts_custom_column', 'floresinc_show_promotional_grid_column_content', 10, 2);

/**
 * Añadir acciones rápidas para activar/desactivar grillas
 */
function floresinc_add_promotional_grid_row_actions($actions, $post) {
    if ($post->post_type === 'promotional_grid') {
        $is_active = get_post_meta($post->ID, '_promotional_grid_active', true);
        $active_grid_id = get_option('floresinc_active_promotional_grid', 0);
        
        if ($active_grid_id != $post->ID) {
            $actions['activate'] = sprintf(
                '<a href="%s">%s</a>',
                wp_nonce_url(admin_url('admin-post.php?action=activate_promotional_grid&grid_id=' . $post->ID), 'activate_grid_' . $post->ID),
                __('Activar', 'floresinc')
            );
        } else {
            $actions['deactivate'] = sprintf(
                '<a href="%s">%s</a>',
                wp_nonce_url(admin_url('admin-post.php?action=deactivate_promotional_grid&grid_id=' . $post->ID), 'deactivate_grid_' . $post->ID),
                __('Desactivar', 'floresinc')
            );
        }
    }
    
    return $actions;
}
add_filter('post_row_actions', 'floresinc_add_promotional_grid_row_actions', 10, 2);

/**
 * Manejar la acción de activar grilla
 */
function floresinc_handle_activate_promotional_grid() {
    if (!isset($_GET['grid_id']) || !isset($_GET['_wpnonce'])) {
        wp_die(__('Parámetros inválidos', 'floresinc'));
    }
    
    $grid_id = intval($_GET['grid_id']);
    
    if (!wp_verify_nonce($_GET['_wpnonce'], 'activate_grid_' . $grid_id)) {
        wp_die(__('Nonce inválido', 'floresinc'));
    }
    
    if (!current_user_can('edit_post', $grid_id)) {
        wp_die(__('No tienes permisos para realizar esta acción', 'floresinc'));
    }
    
    // Activar esta grilla
    update_post_meta($grid_id, '_promotional_grid_active', 1);
    update_option('floresinc_active_promotional_grid', $grid_id);
    
    // Desactivar otras grillas
    $args = array(
        'post_type'      => 'promotional_grid',
        'posts_per_page' => -1,
        'post__not_in'   => array($grid_id),
        'meta_query'     => array(
            array(
                'key'     => '_promotional_grid_active',
                'value'   => '1',
                'compare' => '=',
            ),
        ),
    );
    
    $other_active_grids = get_posts($args);
    
    foreach ($other_active_grids as $grid) {
        update_post_meta($grid->ID, '_promotional_grid_active', 0);
    }
    
    // Redirigir de vuelta a la lista
    wp_redirect(admin_url('edit.php?post_type=promotional_grid&activated=1'));
    exit;
}
add_action('admin_post_activate_promotional_grid', 'floresinc_handle_activate_promotional_grid');

/**
 * Manejar la acción de desactivar grilla
 */
function floresinc_handle_deactivate_promotional_grid() {
    if (!isset($_GET['grid_id']) || !isset($_GET['_wpnonce'])) {
        wp_die(__('Parámetros inválidos', 'floresinc'));
    }
    
    $grid_id = intval($_GET['grid_id']);
    
    if (!wp_verify_nonce($_GET['_wpnonce'], 'deactivate_grid_' . $grid_id)) {
        wp_die(__('Nonce inválido', 'floresinc'));
    }
    
    if (!current_user_can('edit_post', $grid_id)) {
        wp_die(__('No tienes permisos para realizar esta acción', 'floresinc'));
    }
    
    // Desactivar esta grilla
    update_post_meta($grid_id, '_promotional_grid_active', 0);
    
    // Actualizar la opción global
    $active_grid_id = get_option('floresinc_active_promotional_grid', 0);
    if ($active_grid_id == $grid_id) {
        update_option('floresinc_active_promotional_grid', 0);
    }
    
    // Redirigir de vuelta a la lista
    wp_redirect(admin_url('edit.php?post_type=promotional_grid&deactivated=1'));
    exit;
}
add_action('admin_post_deactivate_promotional_grid', 'floresinc_handle_deactivate_promotional_grid');

/**
 * Mostrar mensajes de notificación
 */
function floresinc_promotional_grid_admin_notices() {
    $screen = get_current_screen();
    
    if ($screen->id === 'edit-promotional_grid') {
        if (isset($_GET['activated'])) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Grilla activada correctamente.', 'floresinc'); ?></p>
            </div>
            <?php
        }
        
        if (isset($_GET['deactivated'])) {
            ?>
            <div class="notice notice-info is-dismissible">
                <p><?php _e('Grilla desactivada correctamente.', 'floresinc'); ?></p>
            </div>
            <?php
        }
    }
}
add_action('admin_notices', 'floresinc_promotional_grid_admin_notices');
