<?php
/**
 * Funciones para gestionar el menú principal desde WordPress
 * Añadir este código en el archivo functions.php del tema activo
 */

/**
 * Registrar ubicaciones de menú
 */
function floresinc_register_menus() {
    register_nav_menus(
        array(
            'main-menu' => __('Menú Principal', 'floresinc'),
            'footer-menu' => __('Menú de Pie de Página', 'floresinc')
        )
    );
}
add_action('init', 'floresinc_register_menus');

/**
 * Añadir soporte para categorías de WooCommerce en el menú de WordPress
 */
function floresinc_add_product_categories_to_menu() {
    // Verificar si WooCommerce está activo
    if (!class_exists('WooCommerce')) {
        return;
    }

    // Asegurarse de que la taxonomía product_cat sea visible en los menús
    // Este es el punto clave: WordPress solo muestra taxonomías con show_in_nav_menus = true
    global $wp_taxonomies;
    if (isset($wp_taxonomies['product_cat'])) {
        $wp_taxonomies['product_cat']->show_in_nav_menus = true;
        
        // También asegurarse de que la taxonomía tenga etiquetas adecuadas
        if (!isset($wp_taxonomies['product_cat']->labels->name)) {
            $wp_taxonomies['product_cat']->labels->name = __('Categorías de Productos', 'floresinc');
        }
    }
    
    // Verificar si hay categorías de productos y crear algunas de ejemplo si no hay
    floresinc_check_product_categories();
}
add_action('init', 'floresinc_add_product_categories_to_menu', 20); // Prioridad 20 para asegurarnos de que se ejecute después de que WooCommerce registre sus taxonomías

/**
 * Verificar si hay categorías de productos y crear algunas de ejemplo si no hay
 */
function floresinc_check_product_categories() {
    // Verificar si WooCommerce está activo
    if (!class_exists('WooCommerce')) {
        return;
    }
    
    // Contar categorías de productos (excluyendo "Sin categoría")
    $args = array(
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'exclude' => get_option('default_product_cat', 0) // Excluir "Sin categoría"
    );
    $product_categories = get_terms($args);
    
    // Si no hay categorías o hay un error, crear algunas de ejemplo
    if (empty($product_categories) || is_wp_error($product_categories)) {
        // Categorías de ejemplo para crear
        $example_categories = array(
            'Flores' => 'Categoría principal para flores',
            'Extractos' => 'Extractos y concentrados',
            'Comestibles' => 'Productos comestibles',
            'Accesorios' => 'Accesorios y herramientas'
        );
        
        // Crear cada categoría si no existe
        foreach ($example_categories as $name => $description) {
            // Verificar si la categoría ya existe
            $term = term_exists($name, 'product_cat');
            
            // Si no existe, crearla
            if (!$term) {
                wp_insert_term(
                    $name,
                    'product_cat',
                    array(
                        'description' => $description,
                        'slug' => sanitize_title($name)
                    )
                );
            }
        }
        
        // Añadir subcategorías a Flores
        $parent_term = term_exists('Flores', 'product_cat');
        if ($parent_term && !is_wp_error($parent_term)) {
            $parent_id = $parent_term['term_id'];
            
            $subcategories = array(
                'Flores Super Premium' => 'Flores de la más alta calidad',
                'Flores Alta Gama' => 'Flores de alta calidad',
                'Flores Funcionales' => 'Flores con efectos específicos',
                'Porros' => 'Porros pre-enrollados'
            );
            
            foreach ($subcategories as $name => $description) {
                // Verificar si la subcategoría ya existe
                $term = term_exists($name, 'product_cat');
                
                // Si no existe, crearla
                if (!$term) {
                    wp_insert_term(
                        $name,
                        'product_cat',
                        array(
                            'description' => $description,
                            'slug' => sanitize_title($name),
                            'parent' => $parent_id
                        )
                    );
                }
            }
        }
    }
}

/**
 * Callback para el metabox de categorías de productos
 */
function floresinc_product_categories_metabox() {
    // Verificar si WooCommerce está activado
    if (!class_exists('WooCommerce')) {
        echo '<p>Error: WooCommerce no está activado. Por favor, activa WooCommerce para usar esta función.</p>';
        return;
    }
    
    // Verificar si la taxonomía product_cat existe
    if (!taxonomy_exists('product_cat')) {
        echo '<p>Error: La taxonomía "product_cat" no existe. Esto puede indicar un problema con la instalación de WooCommerce.</p>';
        return;
    }
    
    // Información de depuración
    echo '<div style="margin-bottom: 10px; padding: 5px; background-color: #f8f8f8; border: 1px solid #ddd; border-radius: 3px;">';
    echo '<p><strong>Información de depuración:</strong></p>';
    echo '<ul>';
    echo '<li>WooCommerce activo: ' . (class_exists('WooCommerce') ? 'Sí' : 'No') . '</li>';
    echo '<li>Taxonomía product_cat registrada: ' . (taxonomy_exists('product_cat') ? 'Sí' : 'No') . '</li>';
    
    // Obtener el ID de la categoría "Sin categoría"
    $uncategorized_id = get_option('default_product_cat', 0);
    echo '<li>ID de categoría "Sin categoría": ' . $uncategorized_id . '</li>';
    
    // Contar todas las categorías
    $all_categories_count = wp_count_terms(array(
        'taxonomy' => 'product_cat',
        'hide_empty' => false
    ));
    echo '<li>Total de categorías (incluyendo "Sin categoría"): ' . (is_wp_error($all_categories_count) ? 'Error al contar' : $all_categories_count) . '</li>';
    echo '</ul>';
    echo '</div>';
    
    // Obtener todas las categorías de WooCommerce directamente usando WP_Term_Query
    $term_query = new WP_Term_Query(array(
        'taxonomy' => 'product_cat',
        'orderby' => 'name',
        'order' => 'ASC',
        'hide_empty' => false,
        'exclude' => get_option('default_product_cat', 0) // Excluir "Sin categoría"
    ));
    
    $product_categories = $term_query->terms;

    if (empty($product_categories)) {
        echo '<p>' . __('No hay categorías de productos disponibles.', 'floresinc') . '</p>';
        
        // Información de depuración
        echo '<p>No se encontraron categorías. Verifica que WooCommerce esté activado y que existan categorías de productos.</p>';
        
        // Mostrar un botón para crear categorías de ejemplo
        ?>
        <p>
            <button type="button" class="button" id="create-example-categories">
                Crear categorías de ejemplo
            </button>
        </p>
        <script>
            jQuery(document).ready(function($) {
                $('#create-example-categories').on('click', function() {
                    $(this).prop('disabled', true).text('Creando categorías...');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'floresinc_create_example_categories',
                            nonce: '<?php echo wp_create_nonce('floresinc_create_categories_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('Categorías creadas correctamente. Recarga la página para verlas.');
                                location.reload();
                            } else {
                                alert('Error al crear categorías: ' + response.data);
                                $('#create-example-categories').prop('disabled', false).text('Crear categorías de ejemplo');
                            }
                        },
                        error: function() {
                            alert('Error al comunicarse con el servidor.');
                            $('#create-example-categories').prop('disabled', false).text('Crear categorías de ejemplo');
                        }
                    });
                });
            });
        </script>
        <?php
        return;
    }

    ?>
    <div id="product-category-menu-items" class="categorydiv">
        <ul class="categorychecklist form-no-clear">
            <?php
            $i = -1;
            foreach ($product_categories as $category) {
                // Saltar la categoría "Sin categoría" (uncategorized)
                if ($category->slug === 'uncategorized') {
                    continue;
                }
                
                $i++;
                ?>
                <li>
                    <label class="menu-item-title">
                        <input type="checkbox" class="menu-item-checkbox" name="menu-item[<?php echo esc_attr($i); ?>][menu-item-object-id]" value="<?php echo esc_attr($category->term_id); ?>">
                        <?php echo esc_html($category->name); ?> (<?php echo esc_html($category->count); ?>)
                    </label>
                    <input type="hidden" class="menu-item-type" name="menu-item[<?php echo esc_attr($i); ?>][menu-item-type]" value="taxonomy">
                    <input type="hidden" class="menu-item-object" name="menu-item[<?php echo esc_attr($i); ?>][menu-item-object]" value="product_cat">
                    <input type="hidden" class="menu-item-title" name="menu-item[<?php echo esc_attr($i); ?>][menu-item-title]" value="<?php echo esc_attr($category->name); ?>">
                </li>
                <?php
            }
            ?>
        </ul>
        <p class="button-controls">
            <span class="add-to-menu">
                <input type="submit" class="button-secondary submit-add-to-menu right" value="<?php esc_attr_e('Añadir al menú', 'floresinc'); ?>" name="add-post-type-menu-item" id="submit-product-category-menu-items">
                <span class="spinner"></span>
            </span>
        </p>
    </div>
    <?php
}

/**
 * Crear endpoint AJAX para crear categorías de ejemplo
 */
add_action('wp_ajax_floresinc_create_example_categories', 'floresinc_ajax_create_example_categories');

function floresinc_ajax_create_example_categories() {
    // Verificar nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'floresinc_create_categories_nonce')) {
        wp_send_json_error('Verificación de seguridad fallida.');
        return;
    }
    
    // Verificar permisos
    if (!current_user_can('manage_options')) {
        wp_send_json_error('No tienes permisos para realizar esta acción.');
        return;
    }
    
    // Verificar si WooCommerce está activo
    if (!class_exists('WooCommerce')) {
        wp_send_json_error('WooCommerce no está activado.');
        return;
    }
    
    // Categorías de ejemplo para crear
    $example_categories = array(
        'Flores' => 'Categoría principal para flores',
        'Extractos' => 'Extractos y concentrados',
        'Comestibles' => 'Productos comestibles',
        'Accesorios' => 'Accesorios y herramientas'
    );
    
    $created_categories = array();
    
    // Crear cada categoría si no existe
    foreach ($example_categories as $name => $description) {
        // Verificar si la categoría ya existe
        $term = term_exists($name, 'product_cat');
        
        // Si no existe, crearla
        if (!$term) {
            $result = wp_insert_term(
                $name,
                'product_cat',
                array(
                    'description' => $description,
                    'slug' => sanitize_title($name)
                )
            );
            
            if (!is_wp_error($result)) {
                $created_categories[] = $name;
            }
        }
    }
    
    // Añadir subcategorías a Flores
    $parent_term = term_exists('Flores', 'product_cat');
    if ($parent_term && !is_wp_error($parent_term)) {
        $parent_id = $parent_term['term_id'];
        
        $subcategories = array(
            'Flores Super Premium' => 'Flores de la más alta calidad',
            'Flores Alta Gama' => 'Flores de alta calidad',
            'Flores Funcionales' => 'Flores con efectos específicos',
            'Porros' => 'Porros pre-enrollados'
        );
        
        foreach ($subcategories as $name => $description) {
            // Verificar si la subcategoría ya existe
            $term = term_exists($name, 'product_cat');
            
            // Si no existe, crearla
            if (!$term) {
                $result = wp_insert_term(
                    $name,
                    'product_cat',
                    array(
                        'description' => $description,
                        'slug' => sanitize_title($name),
                        'parent' => $parent_id
                    )
                );
                
                if (!is_wp_error($result)) {
                    $created_categories[] = $name . ' (subcategoría de Flores)';
                }
            }
        }
    }
    
    if (empty($created_categories)) {
        wp_send_json_success('No se crearon nuevas categorías porque ya existían.');
    } else {
        wp_send_json_success('Se crearon las siguientes categorías: ' . implode(', ', $created_categories));
    }
}

/**
 * Crear endpoint de API REST para obtener el menú principal
 */
function floresinc_register_menu_rest_route() {
    register_rest_route('floresinc/v1', '/menu', array(
        'methods' => 'GET',
        'callback' => 'floresinc_get_menu_callback',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'floresinc_register_menu_rest_route');

/**
 * Callback para el endpoint de menú
 */
function floresinc_get_menu_callback($request) {
    // Intentar obtener el menú por nombre, slug o ID
    $menu_locations = get_nav_menu_locations();
    $menu = false;
    
    // Método 1: Buscar por ubicación del menú
    if (isset($menu_locations['main-menu'])) {
        $menu = wp_get_nav_menu_object($menu_locations['main-menu']);
    }
    
    // Método 2: Buscar por nombre o slug
    if (!$menu) {
        $menu = wp_get_nav_menu_object('main-menu');
    }
    
    // Método 3: Buscar cualquier menú si no se encuentra el principal
    if (!$menu) {
        $menus = wp_get_nav_menus();
        if (!empty($menus)) {
            $menu = $menus[0]; // Usar el primer menú disponible
        }
    }
    
    // Si no se encuentra ningún menú, devolver un error
    if (!$menu) {
        // Información de depuración
        $debug_info = array(
            'menu_locations' => $menu_locations,
            'available_menus' => wp_get_nav_menus(),
            'registered_locations' => get_registered_nav_menus()
        );
        
        return new WP_Error(
            'no_menu', 
            'No se encontró el menú principal', 
            array(
                'status' => 404,
                'debug_info' => $debug_info
            )
        );
    }
    
    // Obtener los elementos del menú
    $menu_items = wp_get_nav_menu_items($menu->term_id);
    
    if (!$menu_items || empty($menu_items)) {
        return new WP_Error(
            'empty_menu', 
            'El menú principal está vacío', 
            array('status' => 404)
        );
    }
    
    // Procesar los elementos del menú
    $menu_data = array();
    $menu_item_parents = array();
    
    // Primero, recopilar todos los elementos padre
    foreach ($menu_items as $item) {
        if ($item->menu_item_parent == 0) {
            $menu_item = array(
                'id' => $item->ID,
                'name' => $item->title,
                'url' => $item->url,
                'slug' => sanitize_title($item->title),
                'object' => $item->object,
                'object_id' => $item->object_id,
                'subcategories' => array()
            );
            
            // Si es una categoría de producto, añadir información adicional
            if ($item->object == 'product_cat') {
                $term = get_term($item->object_id, 'product_cat');
                if ($term && !is_wp_error($term)) {
                    $menu_item['slug'] = $term->slug;
                    $menu_item['count'] = $term->count;
                    $menu_item['description'] = $term->description;
                    
                    // Obtener imagen de la categoría
                    $thumbnail_id = get_term_meta($term->term_id, 'thumbnail_id', true);
                    if ($thumbnail_id) {
                        $menu_item['image'] = wp_get_attachment_url($thumbnail_id);
                    }
                }
            }
            
            $menu_data[] = $menu_item;
            $menu_item_parents[$item->ID] = count($menu_data) - 1;
        }
    }
    
    // Luego, añadir los elementos hijo
    foreach ($menu_items as $item) {
        if ($item->menu_item_parent != 0 && isset($menu_item_parents[$item->menu_item_parent])) {
            $parent_index = $menu_item_parents[$item->menu_item_parent];
            
            $sub_item = array(
                'id' => $item->ID,
                'name' => $item->title,
                'url' => $item->url,
                'slug' => sanitize_title($item->title),
                'object' => $item->object,
                'object_id' => $item->object_id
            );
            
            // Si es una categoría de producto, añadir información adicional
            if ($item->object == 'product_cat') {
                $term = get_term($item->object_id, 'product_cat');
                if ($term && !is_wp_error($term)) {
                    $sub_item['slug'] = $term->slug;
                    $sub_item['count'] = $term->count;
                    $sub_item['description'] = $term->description;
                    
                    // Obtener imagen de la categoría
                    $thumbnail_id = get_term_meta($term->term_id, 'thumbnail_id', true);
                    if ($thumbnail_id) {
                        $sub_item['image'] = wp_get_attachment_url($thumbnail_id);
                    }
                }
            }
            
            $menu_data[$parent_index]['subcategories'][] = $sub_item;
        }
    }
    
    // Si el menú está vacío después de procesarlo, devolver un error
    if (empty($menu_data)) {
        return new WP_Error(
            'processed_empty_menu', 
            'El menú principal no tiene elementos válidos', 
            array('status' => 404)
        );
    }
    
    return $menu_data;
}

/**
 * Crear un menú principal de ejemplo si no existe ninguno
 */
function floresinc_create_example_menu() {
    // Verificar si ya existe un menú principal
    $menu_exists = wp_get_nav_menu_object('main-menu');
    
    // Si ya existe un menú con ese nombre, no hacer nada
    if ($menu_exists) {
        return;
    }
    
    // Verificar si WooCommerce está activo
    if (!class_exists('WooCommerce')) {
        return;
    }
    
    // Crear el menú principal
    $menu_id = wp_create_nav_menu('Menú Principal');
    
    // Si hay un error al crear el menú, salir
    if (is_wp_error($menu_id)) {
        return;
    }
    
    // Añadir elementos básicos al menú
    wp_update_nav_menu_item($menu_id, 0, array(
        'menu-item-title' => 'Inicio',
        'menu-item-url' => home_url('/'),
        'menu-item-status' => 'publish',
        'menu-item-type' => 'custom',
    ));
    
    wp_update_nav_menu_item($menu_id, 0, array(
        'menu-item-title' => 'Tienda',
        'menu-item-url' => home_url('/shop/'),
        'menu-item-status' => 'publish',
        'menu-item-type' => 'custom',
    ));
    
    // Añadir categorías de productos al menú
    $product_categories = get_terms(array(
        'taxonomy' => 'product_cat',
        'orderby' => 'name',
        'order' => 'ASC',
        'hide_empty' => false,
        'parent' => 0,
        'exclude' => get_option('default_product_cat', 0), // Excluir "Sin categoría"
        'number' => 5 // Limitar a 5 categorías principales
    ));
    
    if (!is_wp_error($product_categories) && !empty($product_categories)) {
        foreach ($product_categories as $category) {
            $parent_item_id = wp_update_nav_menu_item($menu_id, 0, array(
                'menu-item-title' => $category->name,
                'menu-item-object' => 'product_cat',
                'menu-item-object-id' => $category->term_id,
                'menu-item-type' => 'taxonomy',
                'menu-item-status' => 'publish',
            ));
            
            // Añadir subcategorías si existen
            $subcategories = get_terms(array(
                'taxonomy' => 'product_cat',
                'orderby' => 'name',
                'order' => 'ASC',
                'hide_empty' => false,
                'parent' => $category->term_id,
                'number' => 5 // Limitar a 5 subcategorías por categoría principal
            ));
            
            if (!is_wp_error($subcategories) && !empty($subcategories)) {
                foreach ($subcategories as $subcategory) {
                    wp_update_nav_menu_item($menu_id, 0, array(
                        'menu-item-title' => $subcategory->name,
                        'menu-item-object' => 'product_cat',
                        'menu-item-object-id' => $subcategory->term_id,
                        'menu-item-type' => 'taxonomy',
                        'menu-item-status' => 'publish',
                        'menu-item-parent-id' => $parent_item_id,
                    ));
                }
            }
        }
    }
    
    // Añadir más elementos básicos al menú
    wp_update_nav_menu_item($menu_id, 0, array(
        'menu-item-title' => 'Mi Cuenta',
        'menu-item-url' => home_url('/my-account/'),
        'menu-item-status' => 'publish',
        'menu-item-type' => 'custom',
    ));
    
    wp_update_nav_menu_item($menu_id, 0, array(
        'menu-item-title' => 'Contacto',
        'menu-item-url' => home_url('/contact/'),
        'menu-item-status' => 'publish',
        'menu-item-type' => 'custom',
    ));
    
    // Asignar el menú a la ubicación 'main-menu'
    $locations = get_theme_mod('nav_menu_locations');
    $locations['main-menu'] = $menu_id;
    set_theme_mod('nav_menu_locations', $locations);
}
add_action('after_setup_theme', 'floresinc_create_example_menu');

/**
 * Añadir este código al functions.php
 */
function floresinc_include_menu_functions() {
    // Este comentario es solo para recordar añadir esta función al functions.php
    // require_once get_template_directory() . '/inc/menu-functions.php';
}
