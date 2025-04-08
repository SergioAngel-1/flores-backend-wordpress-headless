<?php
/**
 * Funciones de puntos relacionadas con referidos
 * 
 * Gestión de puntos otorgados por referidos.
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
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
 * Asignar puntos por registro de un nuevo referido
 */
function floresinc_rp_assign_referral_signup_points($referrer_id, $user_id) {
    // Los puntos se asignarán cuando el usuario sea aprobado
    update_user_meta($user_id, '_floresinc_pending_referral_points', $referrer_id);
}

/**
 * Verificar si un usuario ha sido aprobado para asignar puntos de referido
 */
function floresinc_rp_check_user_approval($user_id, $new_role, $old_roles) {
    // Verificar si el nuevo rol indica que el usuario ha sido aprobado
    $approved_roles = array('customer', 'subscriber', 'author', 'contributor', 'editor', 'administrator');
    
    if (in_array($new_role, $approved_roles)) {
        // Verificar si hay puntos pendientes por asignar
        $referrer_id = get_user_meta($user_id, '_floresinc_pending_referral_points', true);
        
        if ($referrer_id) {
            // Obtener opciones
            $options = FloresInc_RP()->get_options();
            $signup_points = isset($options['referral_signup_points']) ? $options['referral_signup_points'] : 0;
            
            if ($signup_points > 0) {
                // Verificar si el usuario existe en la tabla de referidos
                global $wpdb;
                $table = $wpdb->prefix . 'floresinc_referrals';
                
                $exists = $wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(*) FROM $table WHERE user_id = %d AND referrer_id = %d
                ", $user_id, $referrer_id));
                
                if (!$exists) {
                    // El usuario no tiene registro en la tabla, crear uno
                    $code = substr(md5($user_id . time()), 0, 8);
                    
                    $wpdb->insert(
                        $table,
                        array(
                            'user_id' => $user_id,
                            'referrer_id' => $referrer_id,
                            'referral_code' => $code,
                            'signup_date' => current_time('mysql')
                        )
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
}
