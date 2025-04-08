<?php
/**
 * Integración del sistema de puntos con WooCommerce
 * 
 * Funciones para procesar puntos relacionados con pedidos y productos de WooCommerce.
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Procesamiento de puntos por compra de productos
 */
function floresinc_rp_process_order_points($order_id) {
    // Obtener datos del pedido
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }
    
    $user_id = $order->get_user_id();
    
    // Verificar si es un usuario registrado
    if (!$user_id) {
        return;
    }
    
    $options = FloresInc_RP()->get_options();
    $order_total = $order->get_total();
    $total_points_earned = 0;
    
    // Procesar puntos por producto
    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        $quantity = $item->get_quantity();
        
        // Obtener puntos del producto
        $product_points = get_post_meta($product_id, '_floresinc_product_points', true);
        
        if ($product_points) {
            $points = (int) $product_points * $quantity;
            $total_points_earned += $points;
            
            // Descripción para la transacción
            $product_name = $item->get_name();
            $description = sprintf(
                'Puntos por compra de %d "%s"', 
                $quantity, 
                $product_name
            );
            
            // Añadir puntos
            floresinc_rp_add_points(
                $user_id, 
                $points, 
                'earned', 
                $description, 
                $order_id, 
                $options['points_expiration_days']
            );
        }
    }
    
    // Actualizar metadatos del pedido
    update_post_meta($order_id, '_floresinc_points_earned', $total_points_earned);
    
    // Procesar puntos para referidos
    floresinc_rp_process_referral_points($user_id, $order_id, $total_points_earned);
    
    // Procesar puntos utilizados
    $points_used = get_post_meta($order_id, '_floresinc_points_used', true);
    
    if ($points_used > 0) {
        $description = sprintf('Puntos utilizados en el pedido #%d', $order_id);
        floresinc_rp_use_points($user_id, $points_used, $description, $order_id);
    }
}

/**
 * Calcular cuántos puntos vale un producto
 */
function floresinc_rp_calculate_product_points($product_id) {
    // Obtener puntos personalizados del producto
    $product_points = get_post_meta($product_id, '_floresinc_product_points', true);
    
    // Si no hay puntos definidos, usar cálculo basado en precio
    if (!$product_points) {
        $product = wc_get_product($product_id);
        if ($product) {
            $price = $product->get_price();
            
            // Por cada $1 dar 10 puntos (configurable)
            $options = FloresInc_RP()->get_options();
            $points_per_currency = isset($options['points_per_currency']) ? $options['points_per_currency'] : 10;
            
            $product_points = floor($price * $points_per_currency);
        }
    }
    
    return $product_points ? $product_points : 0;
}

/**
 * Mostrar campo de canje de puntos en checkout
 */
function floresinc_rp_points_redemption_field() {
    if (!is_user_logged_in()) {
        return;
    }
    
    $user_id = get_current_user_id();
    $available_points = floresinc_rp_get_user_points($user_id);
    
    // Si no hay puntos disponibles, no mostrar campo
    if ($available_points <= 0) {
        return;
    }
    
    $options = FloresInc_RP()->get_options();
    $points_value = isset($options['points_value']) ? $options['points_value'] : 0.01;
    $max_points_discount = isset($options['max_points_discount']) ? $options['max_points_discount'] : 50;
    
    // Convertir puntos a moneda
    $points_currency_value = $available_points * $points_value;
    
    // Calcular el total del carrito
    $cart_total = WC()->cart->get_cart_contents_total() + WC()->cart->get_shipping_total();
    
    // Calcular el máximo de puntos que se pueden usar (limitado por el porcentaje máximo)
    $max_points_value = ($cart_total * $max_points_discount) / 100;
    $max_points = floor($max_points_value / $points_value);
    
    // Limitar a los puntos disponibles
    $max_points = min($max_points, $available_points);
    
    // Recuperar valor de sesión si existe
    $current_points = WC()->session->get('floresinc_points_to_use', 0);
    ?>
    <div class="floresinc-points-redemption">
        <h3><?php _e('Usar puntos', 'floresinc-rp'); ?></h3>
        <p>
            <?php 
            printf(
                __('Tienes %d puntos disponibles (valor: %s)', 'floresinc-rp'),
                $available_points,
                wc_price($points_currency_value)
            ); 
            ?>
        </p>
        <p>
            <label for="floresinc_points_to_use"><?php _e('Puntos a utilizar:', 'floresinc-rp'); ?></label>
            <input type="number" 
                   min="0" 
                   max="<?php echo $max_points; ?>" 
                   step="1" 
                   id="floresinc_points_to_use" 
                   name="floresinc_points_to_use" 
                   value="<?php echo $current_points; ?>" 
                   class="input-text" />
            <span class="description">
                <?php 
                printf(
                    __('Máximo: %d puntos (descuento: %s)', 'floresinc-rp'),
                    $max_points,
                    wc_price($max_points * $points_value)
                ); 
                ?>
            </span>
        </p>
        <button type="button" class="button" id="floresinc_apply_points"><?php _e('Aplicar', 'floresinc-rp'); ?></button>
    </div>
    <script>
    jQuery(document).ready(function($) {
        $('#floresinc_apply_points').on('click', function() {
            var points = $('#floresinc_points_to_use').val();
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'floresinc_rp_update_points_to_use',
                    points: points,
                    security: '<?php echo wp_create_nonce('floresinc-rp-points'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $('body').trigger('update_checkout');
                    }
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * Aplicar descuento de puntos al carrito
 */
function floresinc_rp_apply_points_discount($cart) {
    if (!is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    
    if (!is_user_logged_in()) {
        return;
    }
    
    // Verificar si hay puntos para usar
    $points_to_use = WC()->session->get('floresinc_points_to_use', 0);
    
    if ($points_to_use <= 0) {
        return;
    }
    
    $user_id = get_current_user_id();
    $available_points = floresinc_rp_get_user_points($user_id);
    
    // Asegurarse de que el usuario tiene suficientes puntos
    if ($points_to_use > $available_points) {
        $points_to_use = $available_points;
        WC()->session->set('floresinc_points_to_use', $points_to_use);
    }
    
    // Calcular valor del descuento
    $options = FloresInc_RP()->get_options();
    $points_value = isset($options['points_value']) ? $options['points_value'] : 0.01;
    $discount_amount = $points_to_use * $points_value;
    
    if ($discount_amount > 0) {
        // Añadir el descuento al carrito
        $cart->add_fee(
            sprintf(__('Descuento por %d puntos', 'floresinc-rp'), $points_to_use),
            -$discount_amount,
            false
        );
    }
}

/**
 * Guardar puntos utilizados en el pedido
 */
function floresinc_rp_save_order_points_used($order_id) {
    // Verificar si hay puntos para usar
    $points_to_use = WC()->session->get('floresinc_points_to_use', 0);
    
    if ($points_to_use > 0) {
        // Verificar que el usuario tiene suficientes puntos
        $user_id = get_current_user_id();
        $available_points = floresinc_rp_get_user_points($user_id);
        
        if ($points_to_use > $available_points) {
            $points_to_use = $available_points;
        }
        
        // Guardar en metadatos del pedido
        update_post_meta($order_id, '_floresinc_points_used', $points_to_use);
        
        // Calcular valor del descuento
        $options = FloresInc_RP()->get_options();
        $points_value = isset($options['points_value']) ? $options['points_value'] : 0.01;
        $discount_amount = $points_to_use * $points_value;
        
        update_post_meta($order_id, '_floresinc_points_discount', $discount_amount);
        
        // Limpiar la sesión
        WC()->session->set('floresinc_points_to_use', 0);
    }
}
