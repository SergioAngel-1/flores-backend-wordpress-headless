<?php
/**
 * Funciones para gestionar Hiperofertas desde WordPress
 * Añadir este código en el archivo functions.php del tema activo
 */

/**
 * Registrar Custom Post Type para Hiperofertas
 */
function register_hiperofertas_post_type() {
    $labels = array(
        'name'               => 'Hiperofertas',
        'singular_name'      => 'Hiperoferta',
        'menu_name'          => 'Hiperofertas',
        'add_new'            => 'Añadir nueva',
        'add_new_item'       => 'Añadir nueva Hiperoferta',
        'edit_item'          => 'Editar Hiperoferta',
        'new_item'           => 'Nueva Hiperoferta',
        'view_item'          => 'Ver Hiperoferta',
        'search_items'       => 'Buscar Hiperofertas',
        'not_found'          => 'No se encontraron Hiperofertas',
        'not_found_in_trash' => 'No se encontraron Hiperofertas en la papelera',
    );

    $args = array(
        'labels'              => $labels,
        'public'              => false,
        'publicly_queryable'  => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'query_var'           => true,
        'rewrite'             => array('slug' => 'hiperoferta'),
        'capability_type'     => 'post',
        'has_archive'         => false,
        'hierarchical'        => false,
        'menu_position'       => 22,
        'menu_icon'           => 'dashicons-tag',
        'supports'            => array('title', 'thumbnail'),
        'show_in_rest'        => false, // Desactivar editor de bloques
    );

    register_post_type('hiperoferta', $args);
}
add_action('init', 'register_hiperofertas_post_type');

/**
 * Añadir metaboxes para los campos personalizados de la hiperoferta
 */
function hiperofertas_add_meta_boxes() {
    add_meta_box(
        'hiperoferta_details',
        'Detalles de la Hiperoferta',
        'hiperoferta_details_callback',
        'hiperoferta',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'hiperofertas_add_meta_boxes');

/**
 * Callback para mostrar los campos personalizados
 */
function hiperoferta_details_callback($post) {
    wp_nonce_field(basename(__FILE__), 'hiperoferta_nonce');
    
    // Obtener valores guardados
    $product_id = get_post_meta($post->ID, '_hiperoferta_product_id', true);
    $regular_price = get_post_meta($post->ID, '_hiperoferta_regular_price', true);
    $sale_price = get_post_meta($post->ID, '_hiperoferta_sale_price', true);
    $discount_percentage = get_post_meta($post->ID, '_hiperoferta_discount_percentage', true);
    $start_date = get_post_meta($post->ID, '_hiperoferta_start_date', true);
    $end_date = get_post_meta($post->ID, '_hiperoferta_end_date', true);
    $active = get_post_meta($post->ID, '_hiperoferta_active', true);
    $featured = get_post_meta($post->ID, '_hiperoferta_featured', true);
    
    // Establecer valores por defecto
    if (empty($active) && $active !== '0') $active = 1;
    if (empty($featured) && $featured !== '0') $featured = 0;
    if (empty($start_date)) $start_date = date('Y-m-d');
    if (empty($end_date)) $end_date = date('Y-m-d', strtotime('+7 days'));
    
    // Obtener todos los productos de WooCommerce
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    );
    $products = get_posts($args);
    
    ?>
    <style>
        .hiperoferta-field {
            margin-bottom: 15px;
        }
        .hiperoferta-field label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .hiperoferta-field input[type="text"],
        .hiperoferta-field input[type="number"],
        .hiperoferta-field input[type="date"],
        .hiperoferta-field select {
            width: 100%;
            padding: 8px;
        }
        .hiperoferta-field .description {
            color: #666;
            font-style: italic;
            margin-top: 5px;
        }
        .hiperoferta-field-inline {
            display: flex;
            align-items: center;
        }
        .hiperoferta-field-inline label {
            margin-right: 10px;
            margin-bottom: 0;
        }
        .price-fields {
            display: flex;
            gap: 15px;
        }
        .price-fields > div {
            flex: 1;
        }
    </style>
    
    <div class="hiperoferta-field">
        <label for="hiperoferta_product_id">Producto:</label>
        <select id="hiperoferta_product_id" name="hiperoferta_product_id" required>
            <option value="">Seleccionar un producto</option>
            <?php foreach ($products as $product) : ?>
                <option value="<?php echo esc_attr($product->ID); ?>" <?php selected($product_id, $product->ID); ?>>
                    <?php echo esc_html($product->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">Selecciona el producto de WooCommerce para esta oferta.</p>
    </div>
    
    <div class="price-fields">
        <div class="hiperoferta-field">
            <label for="hiperoferta_regular_price">Precio regular:</label>
            <input type="number" id="hiperoferta_regular_price" name="hiperoferta_regular_price" value="<?php echo esc_attr($regular_price); ?>" step="0.01" min="0" />
            <p class="description">Precio normal del producto.</p>
        </div>
        
        <div class="hiperoferta-field">
            <label for="hiperoferta_sale_price">Precio de oferta:</label>
            <input type="number" id="hiperoferta_sale_price" name="hiperoferta_sale_price" value="<?php echo esc_attr($sale_price); ?>" step="0.01" min="0" />
            <p class="description">Precio con descuento.</p>
        </div>
        
        <div class="hiperoferta-field">
            <label for="hiperoferta_discount_percentage">Porcentaje de descuento:</label>
            <input type="number" id="hiperoferta_discount_percentage" name="hiperoferta_discount_percentage" value="<?php echo esc_attr($discount_percentage); ?>" step="1" min="0" max="100" />
            <p class="description">Ej: 20 para un 20% de descuento.</p>
        </div>
    </div>
    
    <div class="price-fields">
        <div class="hiperoferta-field">
            <label for="hiperoferta_start_date">Fecha de inicio:</label>
            <input type="date" id="hiperoferta_start_date" name="hiperoferta_start_date" value="<?php echo esc_attr($start_date); ?>" />
        </div>
        
        <div class="hiperoferta-field">
            <label for="hiperoferta_end_date">Fecha de fin:</label>
            <input type="date" id="hiperoferta_end_date" name="hiperoferta_end_date" value="<?php echo esc_attr($end_date); ?>" />
        </div>
    </div>
    
    <div class="hiperoferta-field hiperoferta-field-inline">
        <label for="hiperoferta_active">Activa:</label>
        <input type="checkbox" id="hiperoferta_active" name="hiperoferta_active" value="1" <?php checked($active, '1'); ?> />
        <p class="description" style="margin-left: 10px;">Desmarcar para ocultar esta oferta sin eliminarla.</p>
    </div>
    
    <div class="hiperoferta-field hiperoferta-field-inline">
        <label for="hiperoferta_featured">Destacada:</label>
        <input type="checkbox" id="hiperoferta_featured" name="hiperoferta_featured" value="1" <?php checked($featured, '1'); ?> />
        <p class="description" style="margin-left: 10px;">Marcar para mostrar esta oferta en posiciones destacadas.</p>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Calcular automáticamente el porcentaje de descuento
        $('#hiperoferta_regular_price, #hiperoferta_sale_price').on('change', function() {
            var regularPrice = parseFloat($('#hiperoferta_regular_price').val()) || 0;
            var salePrice = parseFloat($('#hiperoferta_sale_price').val()) || 0;
            
            if (regularPrice > 0 && salePrice > 0 && salePrice < regularPrice) {
                var discount = ((regularPrice - salePrice) / regularPrice) * 100;
                $('#hiperoferta_discount_percentage').val(Math.round(discount));
            }
        });
        
        // Calcular automáticamente el precio de oferta
        $('#hiperoferta_regular_price, #hiperoferta_discount_percentage').on('change', function() {
            var regularPrice = parseFloat($('#hiperoferta_regular_price').val()) || 0;
            var discount = parseFloat($('#hiperoferta_discount_percentage').val()) || 0;
            
            if (regularPrice > 0 && discount > 0 && discount <= 100) {
                var salePrice = regularPrice * (1 - (discount / 100));
                $('#hiperoferta_sale_price').val(salePrice.toFixed(2));
            }
        });
        
        // Cargar datos del producto seleccionado
        $('#hiperoferta_product_id').on('change', function() {
            var productId = $(this).val();
            if (productId) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_product_price',
                        product_id: productId,
                        nonce: '<?php echo wp_create_nonce('get_product_price_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#hiperoferta_regular_price').val(response.data.regular_price);
                            if (response.data.sale_price) {
                                $('#hiperoferta_sale_price').val(response.data.sale_price);
                                
                                // Calcular porcentaje
                                var regularPrice = parseFloat(response.data.regular_price);
                                var salePrice = parseFloat(response.data.sale_price);
                                if (regularPrice > 0 && salePrice > 0) {
                                    var discount = ((regularPrice - salePrice) / regularPrice) * 100;
                                    $('#hiperoferta_discount_percentage').val(Math.round(discount));
                                }
                            }
                        }
                    }
                });
            }
        });
    });
    </script>
    <?php
}

/**
 * AJAX para obtener el precio de un producto
 */
function get_product_price_callback() {
    check_ajax_referer('get_product_price_nonce', 'nonce');
    
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    
    if ($product_id) {
        $product = wc_get_product($product_id);
        if ($product) {
            wp_send_json_success(array(
                'regular_price' => $product->get_regular_price(),
                'sale_price' => $product->get_sale_price()
            ));
        }
    }
    
    wp_send_json_error();
}
add_action('wp_ajax_get_product_price', 'get_product_price_callback');

/**
 * Guardar los datos de los campos personalizados
 */
function hiperoferta_save_meta($post_id) {
    // Verificar nonce
    if (!isset($_POST['hiperoferta_nonce']) || !wp_verify_nonce($_POST['hiperoferta_nonce'], basename(__FILE__))) {
        return $post_id;
    }
    
    // Verificar autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return $post_id;
    }
    
    // Verificar permisos
    if ('hiperoferta' == $_POST['post_type'] && !current_user_can('edit_post', $post_id)) {
        return $post_id;
    }
    
    // Guardar campos
    $fields = array(
        'hiperoferta_product_id' => '_hiperoferta_product_id',
        'hiperoferta_regular_price' => '_hiperoferta_regular_price',
        'hiperoferta_sale_price' => '_hiperoferta_sale_price',
        'hiperoferta_discount_percentage' => '_hiperoferta_discount_percentage',
        'hiperoferta_start_date' => '_hiperoferta_start_date',
        'hiperoferta_end_date' => '_hiperoferta_end_date'
    );
    
    foreach ($fields as $field => $meta_key) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$field]));
        }
    }
    
    // Guardar checkboxes (requieren manejo especial)
    $active = isset($_POST['hiperoferta_active']) ? '1' : '0';
    update_post_meta($post_id, '_hiperoferta_active', $active);
    
    $featured = isset($_POST['hiperoferta_featured']) ? '1' : '0';
    update_post_meta($post_id, '_hiperoferta_featured', $featured);
}
add_action('save_post', 'hiperoferta_save_meta');

/**
 * Personalizar columnas en la lista de hiperofertas
 */
function hiperoferta_custom_columns($columns) {
    $new_columns = array(
        'cb' => $columns['cb'],
        'title' => $columns['title'],
        'product' => 'Producto',
        'price' => 'Precios',
        'discount' => 'Descuento',
        'dates' => 'Fechas',
        'active' => 'Activa',
        'featured' => 'Destacada',
        'date' => $columns['date']
    );
    return $new_columns;
}
add_filter('manage_hiperoferta_posts_columns', 'hiperoferta_custom_columns');

/**
 * Mostrar contenido en las columnas personalizadas
 */
function hiperoferta_custom_column_content($column, $post_id) {
    switch ($column) {
        case 'product':
            $product_id = get_post_meta($post_id, '_hiperoferta_product_id', true);
            if ($product_id) {
                $product = get_post($product_id);
                if ($product) {
                    echo esc_html($product->post_title);
                } else {
                    echo 'Producto no encontrado';
                }
            } else {
                echo 'No seleccionado';
            }
            break;
            
        case 'price':
            $regular_price = get_post_meta($post_id, '_hiperoferta_regular_price', true);
            $sale_price = get_post_meta($post_id, '_hiperoferta_sale_price', true);
            
            if ($regular_price) {
                echo '<strong>Regular:</strong> $' . esc_html(number_format($regular_price, 2));
            }
            
            if ($sale_price) {
                echo '<br><strong>Oferta:</strong> $' . esc_html(number_format($sale_price, 2));
            }
            break;
            
        case 'discount':
            $discount = get_post_meta($post_id, '_hiperoferta_discount_percentage', true);
            if ($discount) {
                echo '<span style="font-weight: bold; color: #d9534f;">' . esc_html($discount) . '%</span>';
            } else {
                echo '0%';
            }
            break;
            
        case 'dates':
            $start_date = get_post_meta($post_id, '_hiperoferta_start_date', true);
            $end_date = get_post_meta($post_id, '_hiperoferta_end_date', true);
            
            if ($start_date) {
                echo '<strong>Inicio:</strong> ' . esc_html(date('d/m/Y', strtotime($start_date)));
            }
            
            if ($end_date) {
                echo '<br><strong>Fin:</strong> ' . esc_html(date('d/m/Y', strtotime($end_date)));
            }
            break;
            
        case 'active':
            $active = get_post_meta($post_id, '_hiperoferta_active', true);
            echo ($active == '1') ? '✅' : '❌';
            break;
            
        case 'featured':
            $featured = get_post_meta($post_id, '_hiperoferta_featured', true);
            echo ($featured == '1') ? '⭐' : '—';
            break;
    }
}
add_action('manage_hiperoferta_posts_custom_column', 'hiperoferta_custom_column_content', 10, 2);

/**
 * Hacer ordenables las columnas personalizadas
 */
function hiperoferta_sortable_columns($columns) {
    $columns['discount'] = 'discount';
    $columns['active'] = 'active';
    $columns['featured'] = 'featured';
    return $columns;
}
add_filter('manage_edit-hiperoferta_sortable_columns', 'hiperoferta_sortable_columns');

/**
 * Ordenar por columnas personalizadas
 */
function hiperoferta_column_orderby($query) {
    if (!is_admin()) return;
    
    $orderby = $query->get('orderby');
    
    if ('discount' == $orderby) {
        $query->set('meta_key', '_hiperoferta_discount_percentage');
        $query->set('orderby', 'meta_value_num');
    }
    
    if ('active' == $orderby) {
        $query->set('meta_key', '_hiperoferta_active');
        $query->set('orderby', 'meta_value');
    }
    
    if ('featured' == $orderby) {
        $query->set('meta_key', '_hiperoferta_featured');
        $query->set('orderby', 'meta_value');
    }
}
add_action('pre_get_posts', 'hiperoferta_column_orderby');

/**
 * Crear endpoint de API REST para obtener hiperofertas
 */
function register_hiperofertas_rest_route() {
    register_rest_route('floresinc/v1', '/hiperofertas', array(
        'methods' => 'GET',
        'callback' => 'get_hiperofertas_callback',
        'permission_callback' => '__return_true'
    ));
    
    // Log para depuración
    error_log('Endpoint de hiperofertas registrado: /wp-json/floresinc/v1/hiperofertas');
}
add_action('rest_api_init', 'register_hiperofertas_rest_route', 10);

/**
 * Callback para el endpoint de hiperofertas
 */
function get_hiperofertas_callback($request) {
    $featured_only = isset($request['featured']) && $request['featured'] === 'true';
    $limit = isset($request['limit']) ? intval($request['limit']) : -1;
    
    $args = array(
        'post_type' => 'hiperoferta',
        'posts_per_page' => $limit,
        'meta_key' => '_hiperoferta_discount_percentage',
        'orderby' => 'meta_value_num',
        'order' => 'DESC',
        'meta_query' => array(
            array(
                'key' => '_hiperoferta_active',
                'value' => '1',
                'compare' => '='
            ),
            array(
                'key' => '_hiperoferta_start_date',
                'value' => date('Y-m-d'),
                'compare' => '<=',
                'type' => 'DATE'
            ),
            array(
                'key' => '_hiperoferta_end_date',
                'value' => date('Y-m-d'),
                'compare' => '>=',
                'type' => 'DATE'
            )
        )
    );
    
    // Filtrar por destacadas si se solicita
    if ($featured_only) {
        $args['meta_query'][] = array(
            'key' => '_hiperoferta_featured',
            'value' => '1',
            'compare' => '='
        );
    }
    
    $hiperofertas = array();
    $query = new WP_Query($args);
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            $product_id = get_post_meta($post_id, '_hiperoferta_product_id', true);
            
            // Verificar que el producto exista
            $product = wc_get_product($product_id);
            if (!$product) continue;
            
            $regular_price = get_post_meta($post_id, '_hiperoferta_regular_price', true);
            $sale_price = get_post_meta($post_id, '_hiperoferta_sale_price', true);
            $discount = get_post_meta($post_id, '_hiperoferta_discount_percentage', true);
            $start_date = get_post_meta($post_id, '_hiperoferta_start_date', true);
            $end_date = get_post_meta($post_id, '_hiperoferta_end_date', true);
            $featured = get_post_meta($post_id, '_hiperoferta_featured', true) == '1';
            
            // Obtener datos del producto
            $product_data = array(
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'slug' => $product->get_slug(),
                'permalink' => get_permalink($product->get_id()),
                'image' => wp_get_attachment_url($product->get_image_id()),
                'gallery' => array(),
                'short_description' => $product->get_short_description(),
                'categories' => array(),
                'stock_status' => $product->get_stock_status(),
                'stock_quantity' => $product->get_stock_quantity(),
            );
            
            // Obtener galería de imágenes
            $attachment_ids = $product->get_gallery_image_ids();
            foreach ($attachment_ids as $attachment_id) {
                $product_data['gallery'][] = wp_get_attachment_url($attachment_id);
            }
            
            // Obtener categorías
            $terms = get_the_terms($product->get_id(), 'product_cat');
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $product_data['categories'][] = array(
                        'id' => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug
                    );
                }
            }
            
            $hiperoferta = array(
                'id' => $post_id,
                'title' => get_the_title(),
                'product' => $product_data,
                'regular_price' => (float) $regular_price,
                'sale_price' => (float) $sale_price,
                'discount_percentage' => (int) $discount,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'featured' => $featured,
                'days_remaining' => floor((strtotime($end_date) - time()) / (60 * 60 * 24))
            );
            
            $hiperofertas[] = $hiperoferta;
        }
    }
    
    wp_reset_postdata();
    
    return $hiperofertas;
}
