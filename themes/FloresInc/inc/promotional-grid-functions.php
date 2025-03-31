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
    $is_active = !empty($is_active) ? 1 : 0;
    
    // Obtener la categoría asociada (si existe)
    $associated_category = get_post_meta($post->ID, '_promotional_grid_category', true);
    
    // Obtener todas las categorías de producto
    $product_categories = get_terms(array(
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
    ));
    
    ?>
    <div class="promotional-grid-status">
        <p>
            <label>
                <input type="checkbox" name="promotional_grid_active" value="1" <?php checked($is_active, 1); ?>>
                <?php _e('Grilla activa', 'floresinc'); ?>
            </label>
        </p>
        
        <p>
            <label for="promotional_grid_category">
                <?php _e('Asociar a categoría:', 'floresinc'); ?>
            </label>
            <select name="promotional_grid_category" id="promotional_grid_category">
                <option value=""><?php _e('-- Grilla por defecto --', 'floresinc'); ?></option>
                <?php foreach ($product_categories as $category) : ?>
                    <option value="<?php echo $category->term_id; ?>" <?php selected($associated_category, $category->term_id); ?>>
                        <?php echo $category->name; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        
        <p class="description">
            <?php _e('Si asocias esta grilla a una categoría, se mostrará en las páginas de esa categoría. Si no seleccionas ninguna categoría, se considerará como la grilla por defecto.', 'floresinc'); ?>
        </p>
        
        <?php if ($is_active) : ?>
            <p class="grid-status active">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php 
                if (!empty($associated_category)) {
                    $cat_name = '';
                    $term = get_term($associated_category, 'product_cat');
                    if (!is_wp_error($term) && $term) {
                        $cat_name = $term->name;
                    }
                    _e('Esta grilla está activa para la categoría: ', 'floresinc');
                    echo '<strong>' . esc_html($cat_name) . '</strong>';
                } else {
                    _e('Esta grilla está activa como grilla por defecto', 'floresinc');
                }
                ?>
            </p>
        <?php else : ?>
            <p class="grid-status inactive">
                <span class="dashicons dashicons-no-alt"></span>
                <?php _e('Esta grilla está inactiva', 'floresinc'); ?>
            </p>
        <?php endif; ?>
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
        
        // Guardar categoría asociada
        $category_id = isset($_POST['promotional_grid_category']) ? sanitize_text_field($_POST['promotional_grid_category']) : '';
        update_post_meta($post_id, '_promotional_grid_category', $category_id);
        
        // Si está activa, actualizar las opciones según el tipo de grilla
        if ($is_active) {
            if (empty($category_id)) {
                // Es una grilla por defecto
                update_option('floresinc_active_default_grid', $post_id);
                
                // Desactivar otras grillas por defecto
                $args = array(
                    'post_type'      => 'promotional_grid',
                    'posts_per_page' => -1,
                    'post__not_in'   => array($post_id),
                    'meta_query'     => array(
                        'relation' => 'AND',
                        array(
                            'key'     => '_promotional_grid_active',
                            'value'   => '1',
                            'compare' => '=',
                        ),
                        array(
                            'key'     => '_promotional_grid_category',
                            'value'   => '',
                            'compare' => '=',
                        ),
                    ),
                );
                
                $other_active_default_grids = get_posts($args);
                
                foreach ($other_active_default_grids as $grid) {
                    update_post_meta($grid->ID, '_promotional_grid_active', 0);
                }
            } else {
                // Es una grilla de categoría
                // Desactivar otras grillas para la misma categoría
                $args = array(
                    'post_type'      => 'promotional_grid',
                    'posts_per_page' => -1,
                    'post__not_in'   => array($post_id),
                    'meta_query'     => array(
                        'relation' => 'AND',
                        array(
                            'key'     => '_promotional_grid_active',
                            'value'   => '1',
                            'compare' => '=',
                        ),
                        array(
                            'key'     => '_promotional_grid_category',
                            'value'   => $category_id,
                            'compare' => '=',
                        ),
                    ),
                );
                
                $other_active_category_grids = get_posts($args);
                
                foreach ($other_active_category_grids as $grid) {
                    update_post_meta($grid->ID, '_promotional_grid_active', 0);
                }
            }
        } else {
            // Si se está desactivando la grilla activa por defecto, limpiar la opción
            if (empty($category_id)) {
                $active_default_grid_id = get_option('floresinc_active_default_grid', 0);
                if ($active_default_grid_id == $post_id) {
                    update_option('floresinc_active_default_grid', 0);
                }
            }
        }
    }
}
add_action('save_post_promotional_grid', 'floresinc_save_promotional_grid_meta');

/**
 * Callback para el endpoint de la API REST por categoría
 */
function floresinc_get_promotional_grid_by_category_rest($request) {
    $category_id = $request['id'];
    
    // Validar que la categoría exista
    $term = get_term($category_id, 'product_cat');
    if (is_wp_error($term) || !$term) {
        error_log("Categoría no encontrada: $category_id");
        return new WP_REST_Response(array(), 200);
    }
    
    error_log("Procesando solicitud de grilla para categoría: " . $term->name . " (ID: $category_id)");
    
    // Buscar directamente grillas para esta categoría específica
    $args = array(
        'post_type'      => 'promotional_grid',
        'posts_per_page' => 1,
        'meta_query'     => array(
            'relation' => 'AND',
            array(
                'key'     => '_promotional_grid_active',
                'value'   => '1',
                'compare' => '=',
            ),
            array(
                'key'     => '_promotional_grid_category',
                'value'   => $category_id,
                'compare' => '=',
            ),
        ),
    );
    
    $category_grids = get_posts($args);
    
    // Si encontramos una grilla específica para esta categoría
    if (!empty($category_grids)) {
        $grid_id = $category_grids[0]->ID;
        error_log("Encontrada grilla específica para categoría $category_id: Grid ID $grid_id");
        
        // Obtener productos de esta grilla
        $products = floresinc_get_products_from_grid($grid_id);
        error_log("Productos encontrados en grilla específica: " . count($products));
        
        return new WP_REST_Response($products, 200);
    }
    
    // Si no hay grilla específica, buscar en categorías ancestras
    $ancestors = get_ancestors($category_id, 'product_cat', 'taxonomy');
    if (!empty($ancestors)) {
        error_log("Buscando en categorías ancestras: " . implode(', ', $ancestors));
        
        foreach ($ancestors as $ancestor_id) {
            $args = array(
                'post_type'      => 'promotional_grid',
                'posts_per_page' => 1,
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'     => '_promotional_grid_active',
                        'value'   => '1',
                        'compare' => '=',
                    ),
                    array(
                        'key'     => '_promotional_grid_category',
                        'value'   => $ancestor_id,
                        'compare' => '=',
                    ),
                ),
            );
            
            $ancestor_grids = get_posts($args);
            
            if (!empty($ancestor_grids)) {
                $grid_id = $ancestor_grids[0]->ID;
                $ancestor_term = get_term($ancestor_id, 'product_cat');
                $ancestor_name = !is_wp_error($ancestor_term) && $ancestor_term ? $ancestor_term->name : 'desconocida';
                
                error_log("Encontrada grilla para categoría ancestral '$ancestor_name' (ID: $ancestor_id): Grid ID $grid_id");
                
                // Obtener productos de esta grilla
                $products = floresinc_get_products_from_grid($grid_id);
                error_log("Productos encontrados en grilla ancestral: " . count($products));
                
                return new WP_REST_Response($products, 200);
            }
        }
    }
    
    // Si llegamos aquí, no se encontró ninguna grilla específica ni ancestral
    // En este caso, vamos a devolver los productos de la grilla por defecto para garantizar contenido
    $default_products = floresinc_get_default_promotional_grid_products();
    
    if (!empty($default_products)) {
        error_log("No se encontró grilla específica para categoría $category_id - Usando grilla por defecto como fallback");
        return new WP_REST_Response($default_products, 200);
    } else {
        error_log("No se encontró ninguna grilla específica o por defecto para categoría $category_id");
        return new WP_REST_Response(array(), 200);
    }
}

/**
 * Callback para el endpoint de la API REST por defecto
 */
function floresinc_get_promotional_grid_rest() {
    // Obtener la grilla por defecto
    $grid_id = get_option('floresinc_active_default_grid', 0);
    
    if (empty($grid_id)) {
        // Buscar la primera grilla por defecto activa
        $args = array(
            'post_type'      => 'promotional_grid',
            'posts_per_page' => 1,
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'     => '_promotional_grid_active',
                    'value'   => '1',
                    'compare' => '=',
                ),
                array(
                    'key'     => '_promotional_grid_category',
                    'value'   => '',
                    'compare' => '=',
                ),
            ),
        );
        
        $default_grids = get_posts($args);
        
        if (!empty($default_grids)) {
            $grid_id = $default_grids[0]->ID;
            // Actualizar la opción global
            update_option('floresinc_active_default_grid', $grid_id);
        }
    }
    
    if (empty($grid_id)) {
        error_log("No se encontró ninguna grilla por defecto activa");
        return new WP_REST_Response(array(), 200);
    }
    
    error_log("Usando grilla por defecto: ID $grid_id");
    
    // Obtener productos de la grilla por defecto
    $products = floresinc_get_products_from_grid($grid_id);
    error_log("Productos encontrados en grilla por defecto: " . count($products));
    
    return new WP_REST_Response($products, 200);
}

/**
 * Función auxiliar para obtener productos de una grilla
 */
function floresinc_get_products_from_grid($grid_id) {
    if (empty($grid_id)) {
        error_log("floresinc_get_products_from_grid: grid_id está vacío");
        return array();
    }
    
    // Obtener los IDs de productos
    $product_ids = get_post_meta($grid_id, '_promotional_grid_products', true);
    
    error_log("Grid ID: $grid_id - Productos raw: " . print_r($product_ids, true));
    
    if (empty($product_ids) || !is_array($product_ids)) {
        error_log("No se encontraron productos para la grid ID: $grid_id o no es un array válido");
        return array();
    }
    
    $products = array();
    
    foreach ($product_ids as $product_id) {
        if (empty($product_id)) {
            error_log("ID de producto vacío encontrado en grid $grid_id");
            continue;
        }
        
        $product = wc_get_product($product_id);
        
        if (!$product) {
            error_log("Producto no encontrado para ID: $product_id");
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
            'name'          => $product->get_name(),
            'price'         => $formatted_price,
            'raw_price'     => $price, // Añadir precio sin formato para cálculos en el frontend
            'regular_price' => $formatted_regular_price,
            'raw_regular_price' => $regular_price,
            'sale_price'    => $has_sale ? 'COP ' . number_format($sale_price, 0, ',', '.') : '',
            'raw_sale_price' => $sale_price,
            'images'        => array(
                array(
                    'src' => wp_get_attachment_url($product->get_image_id())
                )
            ),
            'permalink'     => get_permalink($product_id),
            'categories'    => array_map(function($term) {
                return array(
                    'id'   => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug
                );
            }, wp_get_post_terms($product_id, 'product_cat'))
        );
        
        error_log("Añadido producto a resultados: " . $product->get_name() . " (ID: $product_id)");
    }
    
    error_log("Total de productos procesados para grid $grid_id: " . count($products));
    
    return $products;
}

/**
 * Función auxiliar para obtener los productos de la grilla por defecto
 */
function floresinc_get_default_promotional_grid_products() {
    // Obtener la grilla por defecto
    $grid_id = get_option('floresinc_active_default_grid', 0);
    
    if (empty($grid_id)) {
        // Buscar la primera grilla por defecto activa
        $args = array(
            'post_type'      => 'promotional_grid',
            'posts_per_page' => 1,
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'     => '_promotional_grid_active',
                    'value'   => '1',
                    'compare' => '=',
                ),
                array(
                    'key'     => '_promotional_grid_category',
                    'value'   => '',
                    'compare' => '=',
                ),
            ),
        );
        
        $default_grids = get_posts($args);
        
        if (!empty($default_grids)) {
            $grid_id = $default_grids[0]->ID;
            // Actualizar la opción global
            update_option('floresinc_active_default_grid', $grid_id);
        }
    }
    
    if (empty($grid_id)) {
        error_log("No se encontró ninguna grilla por defecto activa");
        return array();
    }
    
    error_log("Obteniedo productos de la grilla por defecto: ID $grid_id");
    
    // Obtener productos de la grilla por defecto
    $products = floresinc_get_products_from_grid($grid_id);
    error_log("Productos encontrados en la grilla por defecto: " . count($products));
    
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
    
    // Endpoint para obtener la grilla por categoría
    register_rest_route('floresinc/v1', '/promotional-grid/category/(?P<id>\d+)', array(
        'methods'  => 'GET',
        'callback' => 'floresinc_get_promotional_grid_by_category_rest',
        'permission_callback' => '__return_true',
        'args' => array(
            'id' => array(
                'validate_callback' => function($param) {
                    return is_numeric($param);
                }
            ),
        ),
    ));
}

/**
 * Añadir columna de estado en la lista de grillas
 */
function floresinc_add_promotional_grid_columns($columns) {
    $new_columns = array();
    
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        
        // Añadir columna después del título
        if ($key === 'title') {
            $new_columns['status'] = __('Estado', 'floresinc');
            $new_columns['category'] = __('Categoría', 'floresinc');
        }
    }
    
    return $new_columns;
}
add_filter('manage_promotional_grid_posts_columns', 'floresinc_add_promotional_grid_columns');

/**
 * Mostrar contenido de las columnas personalizadas
 */
function floresinc_show_promotional_grid_columns($column, $post_id) {
    switch ($column) {
        case 'status':
            $is_active = get_post_meta($post_id, '_promotional_grid_active', true);
            
            if ($is_active) {
                echo '<span class="dashicons dashicons-yes-alt" style="color: green;"></span> ';
                echo __('Activa', 'floresinc');
            } else {
                echo '<span class="dashicons dashicons-no-alt" style="color: red;"></span> ';
                echo __('Inactiva', 'floresinc');
            }
            break;
            
        case 'category':
            $category_id = get_post_meta($post_id, '_promotional_grid_category', true);
            
            if (!empty($category_id)) {
                $term = get_term($category_id, 'product_cat');
                if (!is_wp_error($term) && $term) {
                    echo esc_html($term->name);
                } else {
                    echo __('Categoría no encontrada', 'floresinc');
                }
            } else {
                echo '<strong>' . __('Grilla por defecto', 'floresinc') . '</strong>';
            }
            break;
    }
}
add_action('manage_promotional_grid_posts_custom_column', 'floresinc_show_promotional_grid_columns', 10, 2);

/**
 * Añadir acciones rápidas para activar/desactivar grillas
 */
function floresinc_add_promotional_grid_row_actions($actions, $post) {
    if ($post->post_type === 'promotional_grid') {
        $is_active = get_post_meta($post->ID, '_promotional_grid_active', true);
        $active_grid_id = get_option('floresinc_active_default_grid', 0);
        
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
    update_option('floresinc_active_default_grid', $grid_id);
    
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
    $active_grid_id = get_option('floresinc_active_default_grid', 0);
    if ($active_grid_id == $grid_id) {
        update_option('floresinc_active_default_grid', 0);
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
