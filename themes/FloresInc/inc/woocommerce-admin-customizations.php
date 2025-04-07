<?php
/**
 * Personalizaciones del panel de administración de WooCommerce
 * 
 * Este archivo contiene funciones para personalizar la interfaz de administración
 * de WooCommerce, especialmente la vista de pedidos.
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Registrar un mensaje de depuración para confirmar que el archivo se está cargando
add_action('admin_notices', function() {
    $screen = get_current_screen();
    if ($screen && $screen->id === 'edit-shop_order') {
        echo '<div class="notice notice-info is-dismissible"><p>Personalizaciones de pedidos cargadas correctamente.</p></div>';
    }
});

/**
 * Personalizar las columnas en la lista de pedidos de WooCommerce
 */
function floresinc_customize_shop_order_columns($columns) {
    // Definir las columnas que queremos mostrar y su orden
    $new_columns = array(
        'cb'                => $columns['cb'], // Checkbox
        'order_number'      => __('Pedido', 'floresinc'),
        'order_date'        => __('Fecha', 'floresinc'),
        'order_status'      => __('Estado del pedido', 'floresinc'),
        'payment_method'    => __('Método de pago', 'floresinc'),
        'shipping_method'   => __('Opción de envío', 'floresinc'),
        'order_total'       => __('Total', 'floresinc'),
        'wc_actions'        => $columns['wc_actions'], // Acciones
    );
    
    // Registrar en el log para depuración
    error_log('FloresInc: Personalizando columnas de pedidos');
    error_log('FloresInc: Columnas originales: ' . print_r($columns, true));
    error_log('FloresInc: Nuevas columnas: ' . print_r($new_columns, true));
    
    return $new_columns;
}
// Usar ambos hooks para mayor compatibilidad
add_filter('manage_edit-shop_order_columns', 'floresinc_customize_shop_order_columns', 99);
add_filter('woocommerce_shop_order_list_table_columns', 'floresinc_customize_shop_order_columns', 99);

/**
 * Renderizar el contenido de las columnas personalizadas
 */
function floresinc_render_shop_order_columns($column, $post_id) {
    // Registrar en el log para depuración
    error_log('FloresInc: Renderizando columna: ' . $column . ' para el pedido: ' . $post_id);
    
    $order = wc_get_order($post_id);
    
    if (!$order) {
        error_log('FloresInc: No se pudo obtener el pedido: ' . $post_id);
        return;
    }
    
    switch ($column) {
        case 'payment_method':
            $payment_method = $order->get_payment_method_title();
            $payment_method_id = $order->get_payment_method();
            
            error_log('FloresInc: Método de pago: ' . $payment_method_id . ' - ' . $payment_method);
            
            // Si es transferencia bancaria, mostrar el submétodo específico
            if ($payment_method_id === 'bank_transfer' || $payment_method_id === 'bacs') {
                // Intentar obtener el submétodo desde los metadatos del pedido
                $payment_details = $order->get_meta('_payment_method_title');
                if (strpos($payment_details, '-') !== false) {
                    // Si el título contiene un guión, asumimos que incluye el submétodo
                    echo esc_html($payment_details);
                } else {
                    echo esc_html($payment_method);
                }
            } else {
                echo esc_html($payment_method);
            }
            break;
            
        case 'shipping_method':
            $shipping_methods = $order->get_shipping_methods();
            $shipping_html = array();
            
            error_log('FloresInc: Métodos de envío: ' . print_r($shipping_methods, true));
            
            if (!empty($shipping_methods)) {
                foreach ($shipping_methods as $shipping_method) {
                    $method_name = $shipping_method->get_method_title();
                    $method_id = $shipping_method->get_method_id();
                    
                    // Verificar si es envío premium
                    if ($method_id === 'flat_rate' && strpos(strtolower($method_name), 'premium') !== false) {
                        $shipping_html[] = '<span class="premium-shipping">' . esc_html($method_name) . '</span>';
                    } else {
                        $shipping_html[] = esc_html($method_name);
                    }
                }
                echo implode(', ', $shipping_html);
            } else {
                echo '<span class="na">&ndash;</span>';
            }
            break;
    }
}
// Usar ambos hooks para mayor compatibilidad (HPOS y sistema tradicional)
add_action('manage_shop_order_posts_custom_column', 'floresinc_render_shop_order_columns', 20, 2);
add_action('manage_woocommerce_page_wc-orders_custom_column', 'floresinc_render_shop_order_columns', 20, 2);
add_action('woocommerce_shop_order_list_table_custom_column', 'floresinc_render_shop_order_columns', 20, 2);

/**
 * Añadir estilos CSS para la vista de pedidos
 */
function floresinc_admin_order_styles() {
    $screen = get_current_screen();
    
    // Registrar en el log para depuración
    error_log('FloresInc: Pantalla actual: ' . ($screen ? $screen->id : 'No screen'));
    
    // Solo aplicar en la pantalla de pedidos (compatible con HPOS)
    if ($screen && ($screen->id === 'edit-shop_order' || $screen->id === 'woocommerce_page_wc-orders')) {
        ?>
        <style type="text/css">
            .premium-shipping {
                color: #9c27b0;
                font-weight: bold;
            }
            
            /* Ajustar el ancho de las columnas */
            .widefat .column-order_number {
                width: 10%;
            }
            .widefat .column-order_date {
                width: 12%;
            }
            .widefat .column-order_status {
                width: 12%;
            }
            .widefat .column-payment_method {
                width: 18%;
            }
            .widefat .column-shipping_method {
                width: 15%;
            }
            .widefat .column-order_total {
                width: 10%;
            }
        </style>
        <?php
    }
}
add_action('admin_head', 'floresinc_admin_order_styles');

/**
 * Hacer que las columnas sean ordenables
 */
function floresinc_shop_order_sortable_columns($columns) {
    $columns['payment_method'] = 'payment_method';
    $columns['shipping_method'] = 'shipping_method';
    return $columns;
}
add_filter('manage_edit-shop_order_sortable_columns', 'floresinc_shop_order_sortable_columns');
add_filter('woocommerce_shop_order_list_table_sortable_columns', 'floresinc_shop_order_sortable_columns');

/**
 * Manejar la ordenación de columnas personalizadas
 */
function floresinc_shop_order_request($vars) {
    if (isset($vars['orderby'])) {
        if ('payment_method' === $vars['orderby']) {
            $vars = array_merge($vars, array(
                'meta_key'  => '_payment_method_title',
                'orderby'   => 'meta_value'
            ));
        }
        
        // La ordenación por método de envío es más compleja y podría requerir una consulta personalizada
        // Por ahora, lo dejamos pendiente
    }
    
    return $vars;
}
add_filter('request', 'floresinc_shop_order_request');
