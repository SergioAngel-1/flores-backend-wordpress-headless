<?php
/**
 * Gestión de códigos de referido
 * 
 * Funciones para generar, obtener y actualizar códigos de referido.
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generar código de referido para un usuario
 */
function floresinc_rp_generate_referral_code($user_id) {
    global $wpdb;
    
    // Obtener información del usuario
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }
    
    // Generar código basado en el username
    $username = $user->user_login;
    $base = sanitize_title($username);
    $random = mt_rand(1000, 9999);
    $code = $base . $random;
    
    // Verificar que no exista
    $table = $wpdb->prefix . 'floresinc_referrals';
    $exists = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM $table WHERE referral_code = %s
    ", $code));
    
    // Si existe, generar otro
    if ($exists > 0) {
        $random = mt_rand(1000, 9999);
        $code = $base . $random;
    }
    
    // Verificar si el usuario ya tiene un registro
    $user_exists = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM $table WHERE user_id = %d
    ", $user_id));
    
    if ($user_exists > 0) {
        // Actualizar código
        $wpdb->update(
            $table,
            ['referral_code' => $code],
            ['user_id' => $user_id]
        );
        
        // Log para depuración
        error_log("Código de referido actualizado para usuario ID $user_id: $code");
    } else {
        // Crear nuevo registro
        $wpdb->insert($table, [
            'user_id' => $user_id,
            'referral_code' => $code,
            'signup_date' => current_time('mysql')
        ]);
        
        // Log para depuración
        error_log("Nuevo código de referido generado para usuario ID $user_id: $code");
    }
    
    return $code;
}

/**
 * Obtener el código de referido de un usuario
 * 
 * @param int $user_id ID del usuario
 * @return string|false Código de referido o false si no se encuentra
 */
function floresinc_rp_get_user_referral_code($user_id = 0) {
    global $wpdb;
    
    // Si no se especifica usuario, usar el actual
    if (!$user_id && is_user_logged_in()) {
        $user_id = get_current_user_id();
    }
    
    if (!$user_id) {
        return false;
    }
    
    $table = $wpdb->prefix . 'floresinc_referrals';
    
    $code = $wpdb->get_var($wpdb->prepare("
        SELECT referral_code FROM $table WHERE user_id = %d
    ", $user_id));
    
    return $code;
}

/**
 * Actualizar el código de referido de un usuario
 * 
 * @param int $user_id ID del usuario
 * @param string $new_code Nuevo código de referido
 * @return bool True si se actualizó correctamente, false en caso contrario
 */
function floresinc_rp_update_user_referral_code($user_id, $new_code) {
    global $wpdb;
    
    // Validar usuario
    if (!$user_id || !get_userdata($user_id)) {
        error_log("Error al actualizar código de referido: Usuario ID $user_id no existe");
        return false;
    }
    
    // Validar código
    if (empty($new_code) || !preg_match('/^[a-z0-9\-]+$/', $new_code)) {
        error_log("Error al actualizar código de referido: Código '$new_code' inválido");
        return false;
    }
    
    $table = $wpdb->prefix . 'floresinc_referrals';
    
    // Verificar que el código no exista para otro usuario
    $exists = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM $table WHERE referral_code = %s AND user_id != %d
    ", $new_code, $user_id));
    
    if ($exists > 0) {
        error_log("Error al actualizar código de referido: Código '$new_code' ya está en uso");
        return false;
    }
    
    // Verificar si el usuario ya tiene un registro
    $user_exists = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM $table WHERE user_id = %d
    ", $user_id));
    
    if ($user_exists > 0) {
        // Actualizar código
        $result = $wpdb->update(
            $table,
            ['referral_code' => $new_code],
            ['user_id' => $user_id]
        );
        
        error_log("Código de referido actualizado para usuario ID $user_id: $new_code (Resultado: " . ($result !== false ? 'Éxito' : 'Error') . ")");
        return ($result !== false);
    } else {
        // Crear registro nuevo
        $result = $wpdb->insert(
            $table,
            [
                'user_id' => $user_id,
                'referral_code' => $new_code,
                'signup_date' => current_time('mysql')
            ]
        );
        
        error_log("Nuevo registro de referido creado para usuario ID $user_id con código $new_code (Resultado: " . ($result !== false ? 'Éxito' : 'Error') . ")");
        return ($result !== false);
    }
}

/**
 * Obtener ID de usuario por código de referido
 * 
 * @param string $code Código de referido
 * @return int|false ID del usuario o false si no se encuentra
 */
function floresinc_rp_get_user_by_referral_code($code) {
    global $wpdb;
    
    if (empty($code)) {
        return false;
    }
    
    $table = $wpdb->prefix . 'floresinc_referrals';
    
    $user_id = $wpdb->get_var($wpdb->prepare("
        SELECT user_id FROM $table WHERE referral_code = %s
    ", $code));
    
    return $user_id ? intval($user_id) : false;
}
