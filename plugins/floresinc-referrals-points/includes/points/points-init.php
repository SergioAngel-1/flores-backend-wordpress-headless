<?php
/**
 * Inicialización del sistema de puntos
 * 
 * Funciones para inicializar y configurar el sistema de puntos.
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
