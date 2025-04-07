<?php
/**
 * Personalizaciones de la página de detalles del pedido en WooCommerce
 * 
 * Este archivo implementa mejoras en la visualización de los detalles del pedido
 * en el panel de administración de WooCommerce.
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clase para personalizar la página de detalles del pedido
 */
class FloresInc_WooCommerce_Order_Details {
    
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
        // Añadir metabox personalizado para el método de pago
        add_action('add_meta_boxes', array($this, 'add_payment_method_metabox'), 10);
        
        // Añadir estilos CSS para la página de detalles
        add_action('admin_head', array($this, 'add_admin_styles'));
    }
    
    /**
     * Añadir metabox para mostrar detalles del método de pago
     */
    public function add_payment_method_metabox() {
        add_meta_box(
            'floresinc_payment_method_details',
            __('Detalles del método de pago', 'floresinc'),
            array($this, 'render_payment_method_metabox'),
            'shop_order',
            'side',
            'high'
        );
        
        // Compatibilidad con HPOS
        add_meta_box(
            'floresinc_payment_method_details',
            __('Detalles del método de pago', 'floresinc'),
            array($this, 'render_payment_method_metabox'),
            wc_get_page_screen_id('shop-order'),
            'side',
            'high'
        );
    }
    
    /**
     * Renderizar el contenido del metabox de método de pago
     */
    public function render_payment_method_metabox($post) {
        // Obtener el ID del pedido
        $order_id = $post->ID ?? $post;
        
        // Obtener el objeto de pedido
        $order = wc_get_order($order_id);
        
        if (!$order) {
            echo '<p>' . __('No se pudo cargar la información del pedido.', 'floresinc') . '</p>';
            return;
        }
        
        // Obtener información del método de pago
        $payment_method = $order->get_payment_method_title();
        $payment_method_id = $order->get_payment_method();
        
        echo '<div class="floresinc-payment-details">';
        
        // Mostrar el método de pago principal
        echo '<p><strong>' . __('Método de pago:', 'floresinc') . '</strong> ' . esc_html($payment_method) . '</p>';
        
        // Si es transferencia bancaria, mostrar detalles adicionales
        if ($payment_method_id === 'bacs' || $payment_method_id === 'bank_transfer') {
            $payment_type = $order->get_meta('_bank_transfer_type');
            $payment_details = $order->get_meta('_payment_method_title');
            
            if (!empty($payment_type)) {
                echo '<p><strong>' . __('Tipo de transferencia:', 'floresinc') . '</strong> <span class="payment-type">' . esc_html($payment_type) . '</span></p>';
            } else if (strpos($payment_details, '-') !== false) {
                $parts = explode('-', $payment_details, 2);
                if (count($parts) > 1) {
                    echo '<p><strong>' . __('Tipo de transferencia:', 'floresinc') . '</strong> <span class="payment-type">' . esc_html(trim($parts[1])) . '</span></p>';
                }
            }
            
            // Mostrar instrucciones de pago si existen
            $instructions = $order->get_meta('_bacs_instructions');
            if (!empty($instructions)) {
                echo '<div class="payment-instructions">';
                echo '<h4>' . __('Instrucciones de pago:', 'floresinc') . '</h4>';
                echo '<p>' . nl2br(esc_html($instructions)) . '</p>';
                echo '</div>';
            }
        } else if ($payment_method_id === 'cod') {
            // Si es pago contra entrega, mostrar información adicional
            echo '<p class="cod-info">' . __('El pago se realizará al momento de la entrega.', 'floresinc') . '</p>';
        }
        
        // Mostrar información de estado de pago
        $is_paid = $order->is_paid();
        echo '<p class="payment-status ' . ($is_paid ? 'paid' : 'pending') . '">';
        echo '<strong>' . __('Estado del pago:', 'floresinc') . '</strong> ';
        echo $is_paid ? __('Pagado', 'floresinc') : __('Pendiente', 'floresinc');
        echo '</p>';
        
        echo '</div>';
    }
    
    /**
     * Añadir estilos CSS para la página de detalles del pedido
     */
    public function add_admin_styles() {
        $screen = get_current_screen();
        
        // Solo aplicar en la pantalla de detalles del pedido
        if ($screen && ($screen->id === 'shop_order' || $screen->id === wc_get_page_screen_id('shop-order'))) {
            ?>
            <style type="text/css">
                .floresinc-payment-details {
                    padding: 5px 0;
                }
                
                .floresinc-payment-details .payment-type {
                    color: #2196f3;
                    font-weight: bold;
                }
                
                .floresinc-payment-details .payment-instructions {
                    margin-top: 10px;
                    padding: 8px;
                    background-color: #f8f8f8;
                    border-left: 3px solid #ddd;
                }
                
                .floresinc-payment-details .payment-instructions h4 {
                    margin: 0 0 5px 0;
                }
                
                .floresinc-payment-details .payment-status {
                    margin-top: 10px;
                    padding-top: 10px;
                    border-top: 1px solid #eee;
                }
                
                .floresinc-payment-details .payment-status.paid {
                    color: #4CAF50;
                }
                
                .floresinc-payment-details .payment-status.pending {
                    color: #FF9800;
                }
                
                .floresinc-payment-details .cod-info {
                    font-style: italic;
                    color: #607D8B;
                }
            </style>
            <?php
        }
    }
}

// Inicializar la clase
new FloresInc_WooCommerce_Order_Details();
