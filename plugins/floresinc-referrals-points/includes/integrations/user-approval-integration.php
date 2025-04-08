<?php
/**
 * Integración con el sistema de aprobación de usuarios
 * 
 * Este archivo maneja la integración entre el sistema de aprobación/rechazo de usuarios
 * y el sistema de puntos por referidos.
 */

if (!defined('ABSPATH')) {
    exit; // Acceso directo no permitido
}

/**
 * Procesar puntos por referidos cuando un usuario es aprobado por primera vez
 */
function floresinc_process_referral_points_on_first_approval($user_id) {
    global $wpdb;
    
    // Registrar información
    error_log("Procesando puntos por referidos para usuario ID: {$user_id} (primera aprobación)");
    
    // Verificar si existe el meta de referidor pendiente
    $referrer_id = get_user_meta($user_id, '_floresinc_pending_referral_points', true);
    
    if (!$referrer_id) {
        error_log("No hay referidor pendiente para el usuario ID: {$user_id}");
        return;
    }
    
    // Obtener configuración de puntos
    $options = FloresInc_RP()->get_options();
    $signup_points = isset($options['referral_signup_points']) ? intval($options['referral_signup_points']) : 100;
    
    // Verificar que el referidor exista y esté activo
    $referrer_data = get_userdata($referrer_id);
    if (!$referrer_data) {
        error_log("El referidor ID: {$referrer_id} no existe o fue eliminado");
        delete_user_meta($user_id, '_floresinc_pending_referral_points');
        return;
    }
    
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
        
        error_log("Actualizando relación de referido: Usuario ID {$user_id}, Referidor ID {$referrer_id}, Resultado: " . ($result !== false ? 'Éxito' : 'Error'));
    } else {
        // Si no existe, crear un registro nuevo
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
        error_log("Creando nuevo registro de referido: Usuario ID {$user_id}, Referidor ID {$referrer_id}, Código {$code}");
    }
    
    if ($signup_points > 0) {
        // Obtener información del usuario referido
        $user = get_userdata($user_id);
        $user_name = $user ? $user->display_name : "Usuario #{$user_id}";
        
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
        
        // Marcar como procesado para evitar duplicados
        delete_user_meta($user_id, '_floresinc_pending_referral_points');
        
        // Invalidar el caché de referidos y puntos
        do_action('floresinc_rp_data_modified');
        
        // Registrar en el log
        error_log(sprintf('Puntos por referido asignados: %d puntos para usuario #%d por referir a #%d', 
            $signup_points, $referrer_id, $user_id));
    }
}
add_action('floresinc_user_first_approval', 'floresinc_process_referral_points_on_first_approval');

/**
 * Evitar la asignación automática de puntos por cambio de rol
 * 
 * Esta función desactiva el hook existente que asigna puntos
 * cuando un usuario cambia de rol, ya que ahora usamos nuestro
 * propio sistema basado en la aprobación manual.
 */
function floresinc_disable_automatic_role_points() {
    // Si existe la función floresinc_rp_check_user_approval, la desconectamos
    if (function_exists('floresinc_rp_check_user_approval')) {
        remove_action('set_user_role', 'floresinc_rp_check_user_approval', 10);
    }
}
add_action('init', 'floresinc_disable_automatic_role_points', 20);
