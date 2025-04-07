<?php
/**
 * Personalizaciones de la tabla de pedidos de WooCommerce
 * 
 * Este archivo implementa las personalizaciones para la vista de pedidos
 * en el panel de administración de WooCommerce.
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase para manejar las personalizaciones de la tabla de pedidos
 */
class FloresInc_WooCommerce_Orders_Table {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Inicializar hooks
        $this->init_hooks();
    }
    
    /**
     * Inicializar todos los hooks necesarios
     */
    private function init_hooks() {
        // Hooks para personalizar columnas (compatibles con HPOS)
        add_filter('manage_edit-shop_order_columns', array($this, 'customize_order_columns'), 999);
        add_filter('woocommerce_shop_order_list_table_columns', array($this, 'customize_order_columns'), 999);
        
        // Hooks para renderizar el contenido de las columnas
        add_action('manage_shop_order_posts_custom_column', array($this, 'render_order_column_content'), 20, 2);
        add_action('woocommerce_shop_order_list_table_custom_column', array($this, 'render_order_column_content'), 20, 2);
        
        // Hooks para hacer las columnas ordenables
        add_filter('manage_edit-shop_order_sortable_columns', array($this, 'make_columns_sortable'));
        add_filter('woocommerce_shop_order_list_table_sortable_columns', array($this, 'make_columns_sortable'));
        
        // Añadir estilos CSS
        add_action('admin_head', array($this, 'add_admin_styles'));
        
        // Mensaje de depuración
        add_action('admin_notices', array($this, 'debug_notice'));
    }
    
    /**
     * Mostrar un mensaje de depuración en la pantalla de pedidos
     */
    public function debug_notice() {
        $screen = get_current_screen();
        if ($screen && ($screen->id === 'edit-shop_order' || $screen->id === 'woocommerce_page_wc-orders')) {
            echo '<div class="notice notice-info is-dismissible"><p>Personalizaciones de la tabla de pedidos de FloresInc activadas.</p></div>';
        }
    }
    
    /**
     * Personalizar las columnas de la tabla de pedidos
     */
    public function customize_order_columns($columns) {
        // Guardar las columnas originales para depuración
        error_log('FloresInc: Columnas originales: ' . print_r($columns, true));
        
        // Definir las columnas personalizadas
        $custom_columns = array(
            'cb'                => isset($columns['cb']) ? $columns['cb'] : '<input type="checkbox" />',
            'order_number'      => __('Pedido', 'floresinc'),
            'order_date'        => __('Fecha', 'floresinc'),
            'order_status'      => __('Estado del pedido', 'floresinc'),
            'payment_method'    => __('Método de pago', 'floresinc'),
            'shipping_method'   => __('Opción de envío', 'floresinc'),
            'order_total'       => __('Total', 'floresinc'),
        );
        
        // Añadir la columna de acciones si existe
        if (isset($columns['wc_actions'])) {
            $custom_columns['wc_actions'] = $columns['wc_actions'];
        }
        
        // Registrar las columnas personalizadas para depuración
        error_log('FloresInc: Columnas personalizadas: ' . print_r($custom_columns, true));
        
        return $custom_columns;
    }
    
    /**
     * Renderizar el contenido de las columnas personalizadas
     */
    public function render_order_column_content($column, $order_id) {
        // Obtener el objeto de pedido
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        switch ($column) {
            case 'payment_method':
                $this->render_payment_method_column($order);
                break;
                
            case 'shipping_method':
                $this->render_shipping_method_column($order);
                break;
        }
    }
    
    /**
     * Renderizar la columna de método de pago
     */
    private function render_payment_method_column($order) {
        $payment_method = $order->get_payment_method_title();
        $payment_method_id = $order->get_payment_method();
        
        // Registrar información para depuración
        error_log('FloresInc: Método de pago: ' . $payment_method_id . ' - ' . $payment_method);
        
        // Si es transferencia bancaria, mostrar el tipo específico
        if ($payment_method_id === 'bacs' || $payment_method_id === 'bank_transfer') {
            // Intentar obtener información adicional
            $payment_details = $order->get_meta('_payment_method_title');
            $payment_type = $order->get_meta('_bank_transfer_type');
            
            if (!empty($payment_type)) {
                // Si tenemos el tipo de transferencia, mostrarlo
                echo '<strong>' . esc_html($payment_method) . '</strong><br>';
                echo '<span class="bank-transfer-type">' . esc_html($payment_type) . '</span>';
            } else if (strpos($payment_details, '-') !== false) {
                // Si el título contiene un guión, asumimos que incluye el tipo
                echo esc_html($payment_details);
            } else {
                echo esc_html($payment_method);
            }
        } else {
            echo esc_html($payment_method);
        }
    }
    
    /**
     * Renderizar la columna de método de envío
     */
    private function render_shipping_method_column($order) {
        $shipping_methods = $order->get_shipping_methods();
        
        if (empty($shipping_methods)) {
            echo '<span class="na">&ndash;</span>';
            return;
        }
        
        $shipping_html = array();
        
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
    }
    
    /**
     * Hacer que las columnas sean ordenables
     */
    public function make_columns_sortable($columns) {
        $columns['payment_method'] = 'payment_method';
        $columns['shipping_method'] = 'shipping_method';
        return $columns;
    }
    
    /**
     * Añadir estilos CSS para la vista de pedidos
     */
    public function add_admin_styles() {
        $screen = get_current_screen();
        
        // Solo aplicar en la pantalla de pedidos
        if ($screen && ($screen->id === 'edit-shop_order' || $screen->id === 'woocommerce_page_wc-orders')) {
            ?>
            <style type="text/css">
                .premium-shipping {
                    color: #9c27b0;
                    font-weight: bold;
                }
                
                .bank-transfer-type {
                    color: #2196f3;
                    font-style: italic;
                }
                
                /* Ajustar el ancho de las columnas */
                .widefat .column-order_number,
                .woocommerce-orders-table .column-order_number {
                    width: 10%;
                }
                .widefat .column-order_date,
                .woocommerce-orders-table .column-order_date {
                    width: 12%;
                }
                .widefat .column-order_status,
                .woocommerce-orders-table .column-order_status {
                    width: 12%;
                }
                .widefat .column-payment_method,
                .woocommerce-orders-table .column-payment_method {
                    width: 18%;
                }
                .widefat .column-shipping_method,
                .woocommerce-orders-table .column-shipping_method {
                    width: 15%;
                }
                .widefat .column-order_total,
                .woocommerce-orders-table .column-order_total {
                    width: 10%;
                }
            </style>
            <?php
        }
    }
}

// Inicializar la clase
new FloresInc_WooCommerce_Orders_Table();
