<?php
/**
 * Funciones para el sistema de puntos
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Inicializar el sistema de puntos
 */
function floresinc_rp_init_points() {
    // Programar verificación diaria de puntos expirados
    add_action('floresinc_rp_daily_maintenance', 'floresinc_rp_process_points_expiration');
    
    // Actualizar puntos al completar un pedido
    add_action('woocommerce_payment_complete', 'floresinc_rp_process_order_points');
    
    // Añadir campo de puntos en la página de checkout
    add_action('woocommerce_review_order_before_payment', 'floresinc_rp_points_redemption_field');
    add_action('woocommerce_cart_calculate_fees', 'floresinc_rp_apply_points_discount');
    
    // Guardar puntos a usar en el pedido
    add_action('woocommerce_checkout_update_order_meta', 'floresinc_rp_save_order_points_used');
    
    // Asignar puntos cuando un usuario referido es aprobado
    add_action('floresinc_rp_referral_processed', 'floresinc_rp_assign_referral_signup_points', 10, 2);
    add_action('set_user_role', 'floresinc_rp_check_user_approval', 10, 3);
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
 * Procesar puntos para referidos según la compra
 */
function floresinc_rp_process_referral_points($user_id, $order_id, $total_points_earned) {
    if ($total_points_earned <= 0) {
        return;
    }
    
    global $wpdb;
    $options = FloresInc_RP()->get_options();
    
    // Obtener referidor (nivel 1)
    $referrals_table = $wpdb->prefix . 'floresinc_referrals';
    $referrer_id = $wpdb->get_var($wpdb->prepare("
        SELECT referrer_id FROM $referrals_table WHERE user_id = %d AND referrer_id IS NOT NULL
    ", $user_id));
    
    if ($referrer_id) {
        // Calcular puntos para referidor nivel 1
        $level1_percentage = $options['referral_commission_level1'];
        $level1_points = floor(($total_points_earned * $level1_percentage) / 100);
        
        if ($level1_points > 0) {
            $description = sprintf(
                'Comisión por compra de referido: %s (Pedido #%d)', 
                get_user_meta($user_id, 'first_name', true), 
                $order_id
            );
            
            floresinc_rp_add_points(
                $referrer_id, 
                $level1_points, 
                'referral', 
                $description, 
                $user_id, 
                $options['points_expiration_days']
            );
        }
        
        // Obtener referidor nivel 2
        $level2_referrer_id = $wpdb->get_var($wpdb->prepare("
            SELECT referrer_id FROM $referrals_table WHERE user_id = %d AND referrer_id IS NOT NULL
        ", $referrer_id));
        
        if ($level2_referrer_id) {
            // Calcular puntos para referidor nivel 2
            $level2_percentage = $options['referral_commission_level2'];
            $level2_points = floor(($total_points_earned * $level2_percentage) / 100);
            
            if ($level2_points > 0) {
                $description = sprintf(
                    'Comisión por compra de referido indirecto: %s (Pedido #%d)', 
                    get_user_meta($user_id, 'first_name', true), 
                    $order_id
                );
                
                floresinc_rp_add_points(
                    $level2_referrer_id, 
                    $level2_points, 
                    'referral', 
                    $description, 
                    $user_id, 
                    $options['points_expiration_days']
                );
            }
        }
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
    $user_points = floresinc_rp_get_user_points($user_id);
    
    if ($user_points['balance'] <= 0) {
        return;
    }
    
    $options = FloresInc_RP()->get_options();
    $conversion_rate = $options['points_conversion_rate'];
    $points_value = $user_points['balance'] / $conversion_rate;
    
    // Obtener total del carrito
    $cart_total = WC()->cart->total;
    
    // No mostrar si el valor de los puntos es muy pequeño
    if ($points_value < 1) {
        return;
    }
    
    // Limitar el valor máximo al total del carrito
    $max_points_value = min($points_value, $cart_total);
    $max_points = floor($max_points_value * $conversion_rate);
    
    woocommerce_form_field('floresinc_use_points', array(
        'type' => 'checkbox',
        'class' => array('floresinc-points-redemption'),
        'label' => sprintf(
            __('Usar %d puntos para obtener un descuento de %s', 'floresinc-rp'),
            $max_points,
            wc_price($max_points_value)
        ),
    ), WC()->session->get('floresinc_use_points'));
    
    echo '<input type="hidden" name="floresinc_points_amount" value="' . esc_attr($max_points) . '">';
    
    // JavaScript para manejar la actualización del checkout
    ?>
    <script type="text/javascript">
    jQuery(function($){
        $('form.checkout').on('change', 'input[name="floresinc_use_points"]', function(){
            $('body').trigger('update_checkout');
        });
    });
    </script>
    <?php
}

/**
 * Aplicar descuento de puntos al carrito
 */
function floresinc_rp_apply_points_discount($cart) {
    if (!is_user_logged_in() || !isset($_POST['floresinc_use_points'])) {
        return;
    }
    
    $use_points = wc_clean($_POST['floresinc_use_points']);
    
    // Guardar en sesión
    WC()->session->set('floresinc_use_points', $use_points);
    
    if ($use_points !== '1') {
        return;
    }
    
    // Obtener cantidad de puntos a usar
    $points_amount = isset($_POST['floresinc_points_amount']) ? 
                     absint($_POST['floresinc_points_amount']) : 0;
    
    if ($points_amount <= 0) {
        return;
    }
    
    $user_id = get_current_user_id();
    $user_points = floresinc_rp_get_user_points($user_id);
    
    // Verificar que el usuario tenga suficientes puntos
    if ($user_points['balance'] < $points_amount) {
        return;
    }
    
    $options = FloresInc_RP()->get_options();
    $conversion_rate = $options['points_conversion_rate'];
    $discount_amount = $points_amount / $conversion_rate;
    
    // Aplicar descuento
    if ($discount_amount > 0) {
        $cart->add_fee(
            sprintf(__('Descuento por %d puntos', 'floresinc-rp'), $points_amount),
            -$discount_amount
        );
        
        // Guardar cantidad de puntos para usarla después
        WC()->session->set('floresinc_points_to_use', $points_amount);
    }
}

/**
 * Guardar puntos utilizados en el pedido
 */
function floresinc_rp_save_order_points_used($order_id) {
    if (!WC()->session->get('floresinc_use_points')) {
        return;
    }
    
    $points_to_use = WC()->session->get('floresinc_points_to_use', 0);
    
    if ($points_to_use > 0) {
        update_post_meta($order_id, '_floresinc_points_used', $points_to_use);
        
        // Calcular valor monetario de los puntos
        $options = FloresInc_RP()->get_options();
        $conversion_rate = $options['points_conversion_rate'];
        $discount_amount = $points_to_use / $conversion_rate;
        
        update_post_meta($order_id, '_floresinc_points_discount', $discount_amount);
        
        // Limpiar sesión
        WC()->session->set('floresinc_use_points', false);
        WC()->session->set('floresinc_points_to_use', 0);
    }
}

/**
 * Asignar puntos por registro de un nuevo referido
 */
function floresinc_rp_assign_referral_signup_points($referrer_id, $user_id) {
    // Guardar en meta que este usuario fue referido pero aún no se han asignado puntos
    // porque necesita ser aprobado primero
    update_user_meta($user_id, '_floresinc_pending_referral_points', $referrer_id);
}

/**
 * Verificar si un usuario ha sido aprobado para asignar puntos de referido
 */
function floresinc_rp_check_user_approval($user_id, $new_role, $old_roles) {
    global $wpdb;
    
    // Registrar información para depuración
    error_log("Verificando aprobación de usuario: ID $user_id, Nuevo rol: $new_role, Roles anteriores: " . implode(', ', $old_roles));
    
    // Verificar si el usuario está siendo aprobado (cambiado de subscriber a customer)
    if ($new_role === 'customer' && in_array('subscriber', $old_roles)) {
        // Verificar si hay puntos de referido pendientes
        $referrer_id = get_user_meta($user_id, '_floresinc_pending_referral_points', true);
        
        error_log("Usuario ID $user_id aprobado. Referidor pendiente: " . ($referrer_id ? "ID $referrer_id" : "ninguno"));
        
        if ($referrer_id) {
            // Obtener configuración de puntos
            $options = FloresInc_RP()->get_options();
            $signup_points = isset($options['referral_signup_points']) ? intval($options['referral_signup_points']) : 100;
            
            // Asegurarse de que la relación de referidos esté correctamente guardada en la base de datos
            $referrals_table = $wpdb->prefix . 'floresinc_referrals';
            
            // Verificar si el usuario ya tiene un registro en la tabla de referidos
            $user_exists = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM $referrals_table WHERE user_id = %d
            ", $user_id));
            
            if ($user_exists) {
                // Actualizar el registro del usuario con su referidor
                $result = $wpdb->update(
                    $referrals_table,
                    ['referrer_id' => $referrer_id],
                    ['user_id' => $user_id]
                );
                
                // Registrar información para depuración
                error_log("Actualizando relación de referido durante aprobación: Usuario ID $user_id, Referidor ID $referrer_id, Resultado: " . ($result !== false ? 'Éxito' : 'Error'));
            } else {
                // Si no existe, intentar crear un registro nuevo
                $code = substr(md5($user_id . time()), 0, 8);
                $wpdb->insert(
                    $referrals_table,
                    [
                        'user_id' => $user_id,
                        'referrer_id' => $referrer_id,
                        'referral_code' => $code,
                        'signup_date' => current_time('mysql')
                    ]
                );
                error_log("Creando nuevo registro de referido durante aprobación: Usuario ID $user_id, Referidor ID $referrer_id, Código $code");
            }
            
            if ($signup_points > 0) {
                // Obtener información del usuario referido
                $user = get_userdata($user_id);
                $user_name = $user ? $user->display_name : "Usuario #$user_id";
                
                // Descripción para la transacción
                $description = sprintf('Puntos por nuevo referido: %s', $user_name);
                
                // Añadir puntos al referidor
                floresinc_rp_add_points(
                    $referrer_id,
                    $signup_points,
                    'referral_signup',
                    $description,
                    $user_id,
                    $options['points_expiration_days']
                );
                
                // Marcar como procesado
                delete_user_meta($user_id, '_floresinc_pending_referral_points');
                
                // Registrar en el log
                error_log(sprintf('Puntos de referido asignados: %d puntos para usuario #%d por referir a #%d', 
                    $signup_points, $referrer_id, $user_id));
            }
        }
    }
}
