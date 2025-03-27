<?php
/**
 * Funciones para gestionar categorías destacadas desde WordPress
 * Añadir este código en el archivo functions.php del tema activo
 */

/**
 * Añadir página de opciones para categorías destacadas
 */
function register_featured_categories_page() {
    add_menu_page(
        'Categorías Destacadas',
        'Categorías Destacadas',
        'manage_options',
        'featured-categories',
        'featured_categories_page_callback',
        'dashicons-star-filled',
        21
    );
}
add_action('admin_menu', 'register_featured_categories_page');

/**
 * Callback para la página de opciones
 */
function featured_categories_page_callback() {
    // Guardar cambios si se envió el formulario
    if (isset($_POST['save_featured_categories']) && check_admin_referer('save_featured_categories_nonce')) {
        $selected_categories = isset($_POST['featured_categories']) ? $_POST['featured_categories'] : array();
        $selected_categories = array_map('intval', $selected_categories);
        
        // Limitar a 12 categorías
        $selected_categories = array_slice($selected_categories, 0, 12);
        
        // Guardar en las opciones
        update_option('floresinc_featured_categories', $selected_categories);
        
        echo '<div class="notice notice-success is-dismissible"><p>Categorías destacadas guardadas correctamente.</p></div>';
    }
    
    // Obtener categorías seleccionadas
    $featured_categories = get_option('floresinc_featured_categories', array());
    
    // Obtener todas las categorías de WooCommerce
    $args = array(
        'taxonomy'   => 'product_cat',
        'orderby'    => 'name',
        'order'      => 'ASC',
        'hide_empty' => false,
    );
    $product_categories = get_terms($args);
    
    ?>
    <div class="wrap">
        <h1>Categorías Destacadas</h1>
        <p>Selecciona hasta 12 categorías para mostrar en la sección principal de la página de inicio.</p>
        
        <form method="post" action="">
            <?php wp_nonce_field('save_featured_categories_nonce'); ?>
            
            <div class="featured-categories-container" style="margin-top: 20px;">
                <p><strong>Categorías disponibles:</strong></p>
                <p class="description">Selecciona hasta 12 categorías. Las categorías seleccionadas aparecerán en la página principal en el orden que elijas.</p>
                
                <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th style="width: 50px;">Seleccionar</th>
                            <th>Categoría</th>
                            <th>Imagen</th>
                            <th style="width: 100px;">Orden</th>
                        </tr>
                    </thead>
                    <tbody id="sortable-categories">
                        <?php 
                        if (!empty($product_categories) && !is_wp_error($product_categories)) {
                            foreach ($product_categories as $category) {
                                $category_id = $category->term_id;
                                $is_selected = in_array($category_id, $featured_categories);
                                $order_index = array_search($category_id, $featured_categories);
                                $order = $is_selected ? $order_index + 1 : '';
                                
                                // Obtener imagen de la categoría
                                $thumbnail_id = get_term_meta($category_id, 'thumbnail_id', true);
                                $image = wp_get_attachment_url($thumbnail_id);
                                
                                ?>
                                <tr class="category-row <?php echo $is_selected ? 'selected' : ''; ?>">
                                    <td>
                                        <input 
                                            type="checkbox" 
                                            name="featured_categories[]" 
                                            value="<?php echo esc_attr($category_id); ?>" 
                                            <?php checked($is_selected); ?> 
                                            class="featured-category-checkbox"
                                        >
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($category->name); ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($image) : ?>
                                            <img src="<?php echo esc_url($image); ?>" style="max-width: 50px; max-height: 50px;">
                                        <?php else : ?>
                                            <span>Sin imagen</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <input 
                                            type="number" 
                                            name="category_order[<?php echo esc_attr($category_id); ?>]" 
                                            value="<?php echo esc_attr($order); ?>" 
                                            min="1" 
                                            max="12" 
                                            class="small-text category-order"
                                            <?php echo !$is_selected ? 'disabled' : ''; ?>
                                        >
                                    </td>
                                </tr>
                                <?php
                            }
                        }
                        ?>
                    </tbody>
                </table>
                
                <p class="description" style="margin-top: 10px;">
                    <strong>Nota:</strong> Si no se especifica un orden, las categorías se mostrarán en el orden en que fueron seleccionadas.
                </p>
            </div>
            
            <p class="submit">
                <input type="submit" name="save_featured_categories" class="button button-primary" value="Guardar Cambios">
            </p>
        </form>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Contador para limitar selecciones
        function updateSelectedCount() {
            var selectedCount = $('.featured-category-checkbox:checked').length;
            if (selectedCount >= 12) {
                $('.featured-category-checkbox:not(:checked)').prop('disabled', true);
            } else {
                $('.featured-category-checkbox:not(:checked)').prop('disabled', false);
            }
            
            // Actualizar mensaje de conteo
            $('#selected-count').text(selectedCount);
        }
        
        // Inicializar conteo
        updateSelectedCount();
        
        // Actualizar al cambiar selección
        $('.featured-category-checkbox').on('change', function() {
            var checkbox = $(this);
            var row = checkbox.closest('tr');
            var orderInput = row.find('.category-order');
            
            if (checkbox.is(':checked')) {
                row.addClass('selected');
                orderInput.prop('disabled', false);
                
                // Asignar próximo número de orden disponible
                if (orderInput.val() === '') {
                    var nextOrder = $('.category-order:enabled').length;
                    orderInput.val(nextOrder);
                }
            } else {
                row.removeClass('selected');
                orderInput.prop('disabled', true);
                orderInput.val('');
            }
            
            updateSelectedCount();
        });
    });
    </script>
    
    <style>
    .category-row.selected {
        background-color: #f7fcfe;
    }
    .category-order {
        width: 60px !important;
    }
    </style>
    <?php
}

/**
 * Crear endpoint de API REST para obtener categorías destacadas
 */
function register_featured_categories_rest_route() {
    register_rest_route('floresinc/v1', '/featured-categories', array(
        'methods' => 'GET',
        'callback' => 'get_featured_categories_callback',
        'permission_callback' => '__return_true'
    ));
    
    // Log para depuración
    error_log('Endpoint de categorías destacadas registrado: /wp-json/floresinc/v1/featured-categories');
}
add_action('rest_api_init', 'register_featured_categories_rest_route', 10);

/**
 * Callback para el endpoint de categorías destacadas
 */
function get_featured_categories_callback($request) {
    $featured_category_ids = get_option('floresinc_featured_categories', array());
    
    if (empty($featured_category_ids)) {
        return array();
    }
    
    $featured_categories = array();
    
    foreach ($featured_category_ids as $category_id) {
        $term = get_term($category_id, 'product_cat');
        
        if (!is_wp_error($term) && $term) {
            // Obtener imagen de la categoría
            $thumbnail_id = get_term_meta($category_id, 'thumbnail_id', true);
            $image = wp_get_attachment_url($thumbnail_id);
            
            $category = array(
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'count' => $term->count,
                'image' => $image ? $image : '',
                'description' => $term->description,
                'link' => get_term_link($term->term_id, 'product_cat')
            );
            
            $featured_categories[] = $category;
        }
    }
    
    return $featured_categories;
}
