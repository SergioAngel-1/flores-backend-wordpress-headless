<?php
/**
 * Plugin Name: FloresInc Home Sections
 * Description: Plugin para gestionar las secciones de productos en la página de inicio
 * Version: 1.0
 * Author: FloresInc
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Asegurarse de que WP_REST_Response esté disponible
if (!class_exists('WP_REST_Response')) {
    require_once ABSPATH . 'wp-includes/rest-api/class-wp-rest-response.php';
}

// Clase principal del plugin
class FloresInc_Home_Sections {
    
    // Opciones de secciones disponibles
    private $sections = array(
        'section_top_1' => 'Sección Superior 1',
        'section_top_2' => 'Sección Superior 2',
        'section_middle_1' => 'Sección Intermedia 1',
        'section_middle_2' => 'Sección Intermedia 2',
        'section_bottom_1' => 'Sección Final 1',
        'section_bottom_2' => 'Sección Final 2',
    );
    
    // Cantidades fijas de productos por sección
    private $section_limits = array(
        'section_top_1' => 5,
        'section_top_2' => 8,
        'section_middle_1' => 5,
        'section_middle_2' => 8,
        'section_bottom_1' => 4,
        'section_bottom_2' => 4,
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        // Registrar menú de administración
        add_action('admin_menu', array($this, 'register_admin_menu'));
        
        // Registrar configuraciones
        add_action('admin_init', array($this, 'register_settings'));
        
        // Registrar endpoint de API REST
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Debug para verificar que el plugin está cargado
        add_action('init', function() {
            error_log('FloresInc Home Sections plugin inicializado');
        });
    }
    
    /**
     * Registrar el menú de administración
     */
    public function register_admin_menu() {
        add_menu_page(
            'Secciones de Inicio', 
            'Secciones de Inicio', 
            'manage_options', 
            'floresinc-home-sections', 
            array($this, 'admin_page'), 
            'dashicons-layout', 
            30
        );
    }
    
    /**
     * Registrar configuraciones
     */
    public function register_settings() {
        register_setting('floresinc_home_sections', 'floresinc_home_sections_options');
        
        add_settings_section(
            'floresinc_home_sections_main',
            'Configuración de Secciones de Productos',
            array($this, 'section_callback'),
            'floresinc-home-sections'
        );
        
        // Registrar campos para cada sección
        foreach ($this->sections as $section_id => $section_name) {
            // Añadir separador antes de cada nueva sección (excepto la primera)
            if ($section_id !== 'section_top_1') {
                add_settings_field(
                    $section_id . '_separator',
                    '',
                    array($this, 'separator_callback'),
                    'floresinc-home-sections',
                    'floresinc_home_sections_main',
                    array(
                        'section_id' => $section_id,
                    )
                );
            }
            
            add_settings_field(
                $section_id . '_category',
                $section_name,
                array($this, 'category_field_callback'),
                'floresinc-home-sections',
                'floresinc_home_sections_main',
                array(
                    'section_id' => $section_id,
                    'label_for' => $section_id . '_category'
                )
            );
            
            add_settings_field(
                $section_id . '_title',
                $section_name . ' - Título',
                array($this, 'title_field_callback'),
                'floresinc-home-sections',
                'floresinc_home_sections_main',
                array(
                    'section_id' => $section_id,
                    'label_for' => $section_id . '_title'
                )
            );
            
            add_settings_field(
                $section_id . '_subtitle',
                $section_name . ' - Subtítulo',
                array($this, 'subtitle_field_callback'),
                'floresinc-home-sections',
                'floresinc_home_sections_main',
                array(
                    'section_id' => $section_id,
                    'label_for' => $section_id . '_subtitle'
                )
            );
            
            add_settings_field(
                $section_id . '_random',
                $section_name . ' - Productos Aleatorios',
                array($this, 'random_field_callback'),
                'floresinc-home-sections',
                'floresinc_home_sections_main',
                array(
                    'section_id' => $section_id,
                    'label_for' => $section_id . '_random'
                )
            );
        }
    }
    
    /**
     * Callback para la sección
     */
    public function section_callback() {
        echo '<p>Configure las categorías y títulos para cada sección de productos en la página de inicio.</p>';
    }
    
    /**
     * Callback para el separador
     */
    public function separator_callback($args) {
        echo '<hr style="border: 0; height: 1px; background: #ccc; margin: 20px 0;">';
    }
    
    /**
     * Callback para los campos de categoría
     */
    public function category_field_callback($args) {
        $options = get_option('floresinc_home_sections_options');
        $section_id = $args['section_id'];
        $field_name = $section_id . '_category';
        $value = isset($options[$field_name]) ? $options[$field_name] : '';
        
        // Obtener todas las categorías de productos
        $product_categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ));
        
        echo '<select id="' . esc_attr($args['label_for']) . '" name="floresinc_home_sections_options[' . esc_attr($field_name) . ']">';
        echo '<option value="">Seleccionar categoría</option>';
        
        foreach ($product_categories as $category) {
            echo '<option value="' . esc_attr($category->term_id) . '" ' . selected($value, $category->term_id, false) . '>' . esc_html($category->name) . '</option>';
        }
        
        echo '</select>';
        echo '<p class="description">Seleccione la categoría de productos para esta sección.</p>';
    }
    
    /**
     * Callback para los campos de título
     */
    public function title_field_callback($args) {
        $options = get_option('floresinc_home_sections_options');
        $section_id = $args['section_id'];
        $field_name = $section_id . '_title';
        $value = isset($options[$field_name]) ? $options[$field_name] : '';
        
        echo '<input type="text" id="' . esc_attr($args['label_for']) . '" name="floresinc_home_sections_options[' . esc_attr($field_name) . ']" value="' . esc_attr($value) . '" class="regular-text">';
        echo '<p class="description">Título personalizado para esta sección (opcional).</p>';
    }
    
    /**
     * Callback para los campos de subtítulo
     */
    public function subtitle_field_callback($args) {
        $options = get_option('floresinc_home_sections_options');
        $section_id = $args['section_id'];
        $field_name = $section_id . '_subtitle';
        $value = isset($options[$field_name]) ? $options[$field_name] : '';
        
        echo '<input type="text" id="' . esc_attr($args['label_for']) . '" name="floresinc_home_sections_options[' . esc_attr($field_name) . ']" value="' . esc_attr($value) . '" class="regular-text">';
        echo '<p class="description">Subtítulo personalizado para esta sección (opcional).</p>';
    }
    
    /**
     * Callback para los campos de productos aleatorios
     */
    public function random_field_callback($args) {
        $options = get_option('floresinc_home_sections_options');
        $section_id = $args['section_id'];
        $field_name = $section_id . '_random';
        $value = isset($options[$field_name]) ? $options[$field_name] : '';
        
        echo '<input type="checkbox" id="' . esc_attr($args['label_for']) . '" name="floresinc_home_sections_options[' . esc_attr($field_name) . ']" value="1" ' . checked('1', $value, false) . '>';
        echo '<label for="' . esc_attr($args['label_for']) . '">Mostrar productos aleatorios de esta categoría</label>';
        echo '<p class="description">Si se marca esta opción, se mostrarán productos aleatorios de la categoría seleccionada.</p>';
    }
    
    /**
     * Página de administración
     */
    public function admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Guardar configuración
        if (isset($_GET['settings-updated'])) {
            add_settings_error('floresinc_home_sections_messages', 'floresinc_home_sections_message', 'Configuración guardada.', 'updated');
        }
        
        // Mostrar errores/mensajes
        settings_errors('floresinc_home_sections_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('floresinc_home_sections');
                do_settings_sections('floresinc-home-sections');
                submit_button('Guardar cambios');
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Registrar rutas de API REST
     */
    public function register_rest_routes() {
        // Debug para verificar que la función se está ejecutando
        error_log('Registrando rutas REST API para FloresInc Home Sections');
        
        register_rest_route('floresinc/v1', '/home-sections', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_home_sections'),
            'permission_callback' => '__return_true',
        ));
        
        register_rest_route('floresinc/v1', '/home-sections/(?P<section_id>[a-zA-Z0-9_-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_section_products'),
            'permission_callback' => '__return_true',
            'args' => array(
                'section_id' => array(
                    'validate_callback' => function($param) {
                        return array_key_exists($param, $this->sections);
                    }
                ),
            ),
        ));
        
        // Ruta de prueba simple para verificar que la API está funcionando
        register_rest_route('floresinc/v1', '/test', array(
            'methods' => 'GET',
            'callback' => function() {
                return new WP_REST_Response(array('status' => 'ok', 'message' => 'API de FloresInc Home Sections está funcionando'), 200);
            },
            'permission_callback' => '__return_true',
        ));
    }
    
    /**
     * Callback para obtener todas las secciones
     */
    public function get_home_sections() {
        // Registrar en el log para depuración
        error_log('Ejecutando get_home_sections');
        
        $options = get_option('floresinc_home_sections_options');
        $sections = array();
        
        foreach ($this->sections as $section_id => $section_name) {
            $category_id = isset($options[$section_id . '_category']) ? $options[$section_id . '_category'] : '';
            
            if (empty($category_id)) {
                continue;
            }
            
            $category = get_term($category_id, 'product_cat');
            
            if (!$category || is_wp_error($category)) {
                continue;
            }
            
            $sections[$section_id] = array(
                'id' => $section_id,
                'name' => $section_name,
                'category_id' => $category_id,
                'category_name' => $category->name,
                'category_slug' => $category->slug,
                'title' => isset($options[$section_id . '_title']) ? $options[$section_id . '_title'] : $category->name,
                'subtitle' => isset($options[$section_id . '_subtitle']) ? $options[$section_id . '_subtitle'] : '',
                'random' => isset($options[$section_id . '_random']) && $options[$section_id . '_random'] == '1',
                'limit' => $this->section_limits[$section_id],
            );
        }
        
        // Configurar encabezados CORS para permitir encabezados de caché
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type, Accept, Origin, Authorization, Cache-Control, Pragma');
        header('Access-Control-Expose-Headers: X-WP-Cache-Status, Cache-Control');
        
        // Si es una solicitud OPTIONS (preflight), terminar aquí
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            status_header(200);
            exit();
        }
        
        // Agregar encabezados de caché
        header('Cache-Control: public, max-age=1800'); // 30 minutos
        header('X-WP-Cache-Status: MISS');
        
        // Convertir a array indexado para que se serialice como JSON array en lugar de objeto
        $sections_array = array_values($sections);
        
        // Registrar en el log la cantidad de secciones encontradas
        error_log('Secciones encontradas: ' . count($sections_array));
        
        return new WP_REST_Response($sections_array, 200);
    }
    
    /**
     * Callback para obtener productos de una sección específica
     */
    public function get_section_products($request) {
        // Registrar en el log para depuración
        $section_id = $request->get_param('section_id');
        error_log("Obteniendo productos para la sección: {$section_id}");
        
        $options = get_option('floresinc_home_sections_options');
        
        $category_id = isset($options[$section_id . '_category']) ? $options[$section_id . '_category'] : '';
        
        if (empty($category_id)) {
            error_log("Error: No hay categoría configurada para la sección {$section_id}");
            return new WP_REST_Response(array(
                'error' => 'No hay categoría configurada para esta sección',
            ), 404);
        }
        
        // Usar el límite fijo para esta sección
        $limit = $this->section_limits[$section_id];
        
        // Verificar si se deben mostrar productos aleatorios
        $random = isset($options[$section_id . '_random']) && $options[$section_id . '_random'] == '1';
        
        // Consultar productos de la categoría
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => $limit,
            'tax_query' => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $category_id,
                ),
            ),
            'post_status' => 'publish',
        );
        
        // Si se solicitan productos aleatorios, modificar la consulta
        if ($random) {
            $args['orderby'] = 'rand';
        }
        
        $query = new WP_Query($args);
        $products = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product_id = get_the_ID();
                $product = wc_get_product($product_id);
                
                if (!$product) {
                    continue;
                }
                
                $image_id = $product->get_image_id();
                $image_url = wp_get_attachment_image_url($image_id, 'full');
                
                $products[] = array(
                    'id' => $product_id,
                    'name' => $product->get_name(),
                    'slug' => $product->get_slug(),
                    'permalink' => get_permalink($product_id),
                    'date_created' => $product->get_date_created() ? $product->get_date_created()->date('c') : '',
                    'date_modified' => $product->get_date_modified() ? $product->get_date_modified()->date('c') : '',
                    'type' => $product->get_type(),
                    'status' => $product->get_status(),
                    'featured' => $product->is_featured(),
                    'catalog_visibility' => $product->get_catalog_visibility(),
                    'description' => $product->get_description(),
                    'short_description' => $product->get_short_description(),
                    'sku' => $product->get_sku(),
                    'price' => $product->get_price(),
                    'regular_price' => $product->get_regular_price(),
                    'sale_price' => $product->get_sale_price(),
                    'date_on_sale_from' => $product->get_date_on_sale_from() ? $product->get_date_on_sale_from()->date('c') : null,
                    'date_on_sale_to' => $product->get_date_on_sale_to() ? $product->get_date_on_sale_to()->date('c') : null,
                    'on_sale' => $product->is_on_sale(),
                    'purchasable' => $product->is_purchasable(),
                    'total_sales' => $product->get_total_sales(),
                    'virtual' => $product->is_virtual(),
                    'downloadable' => $product->is_downloadable(),
                    'downloads' => $product->get_downloads(),
                    'download_limit' => $product->get_download_limit(),
                    'download_expiry' => $product->get_download_expiry(),
                    'tax_status' => $product->get_tax_status(),
                    'tax_class' => $product->get_tax_class(),
                    'manage_stock' => $product->get_manage_stock(),
                    'stock_quantity' => $product->get_stock_quantity(),
                    'stock_status' => $product->get_stock_status(),
                    'backorders' => $product->get_backorders(),
                    'backorders_allowed' => $product->backorders_allowed(),
                    'backordered' => $product->is_on_backorder(),
                    'sold_individually' => $product->is_sold_individually(),
                    'weight' => $product->get_weight(),
                    'dimensions' => array(
                        'length' => $product->get_length(),
                        'width' => $product->get_width(),
                        'height' => $product->get_height(),
                    ),
                    'shipping_required' => $product->needs_shipping(),
                    'shipping_taxable' => $product->is_shipping_taxable(),
                    'shipping_class' => $product->get_shipping_class(),
                    'shipping_class_id' => $product->get_shipping_class_id(),
                    'reviews_allowed' => $product->get_reviews_allowed(),
                    'average_rating' => $product->get_average_rating(),
                    'rating_count' => $product->get_rating_count(),
                    'related_ids' => $product->get_related(),
                    'upsell_ids' => $product->get_upsell_ids(),
                    'cross_sell_ids' => $product->get_cross_sell_ids(),
                    'parent_id' => $product->get_parent_id(),
                    'purchase_note' => $product->get_purchase_note(),
                    'categories' => array_map(function($term) {
                        return array(
                            'id' => $term->term_id,
                            'name' => $term->name,
                            'slug' => $term->slug,
                        );
                    }, get_the_terms($product_id, 'product_cat') ?: array()),
                    'tags' => array_map(function($term) {
                        return array(
                            'id' => $term->term_id,
                            'name' => $term->name,
                            'slug' => $term->slug,
                        );
                    }, get_the_terms($product_id, 'product_tag') ?: array()),
                    'images' => array(
                        array(
                            'id' => $image_id,
                            'src' => $image_url,
                            'name' => basename($image_url),
                            'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true),
                        ),
                    ),
                    'attributes' => array(),
                    'default_attributes' => array(),
                    'variations' => array(),
                    'grouped_products' => array(),
                    'menu_order' => $product->get_menu_order(),
                );
            }
            
            wp_reset_postdata();
        }
        
        $category = get_term($category_id, 'product_cat');
        
        // Configurar encabezados CORS para permitir encabezados de caché
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: X-Requested-With, Content-Type, Accept, Origin, Authorization, Cache-Control, Pragma');
        header('Access-Control-Expose-Headers: X-WP-Cache-Status, Cache-Control');
        
        // Si es una solicitud OPTIONS (preflight), terminar aquí
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            status_header(200);
            exit();
        }
        
        // Agregar encabezados de caché
        header('Cache-Control: public, max-age=1800'); // 30 minutos
        header('X-WP-Cache-Status: MISS');
        
        $response = array(
            'id' => $section_id,
            'name' => $this->sections[$section_id],
            'category_id' => $category_id,
            'category_name' => $category->name,
            'category_slug' => $category->slug,
            'title' => isset($options[$section_id . '_title']) ? $options[$section_id . '_title'] : $category->name,
            'subtitle' => isset($options[$section_id . '_subtitle']) ? $options[$section_id . '_subtitle'] : '',
            'products' => $products,
        );
        
        // Registrar en el log la cantidad de productos encontrados
        error_log("Productos encontrados para la sección {$section_id}: " . count($products));
        
        return new WP_REST_Response($response, 200);
    }
}

// Inicializar el plugin
$floresinc_home_sections = new FloresInc_Home_Sections();
