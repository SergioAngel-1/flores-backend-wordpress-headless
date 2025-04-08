<?php
/**
 * Gestión de relaciones entre referidos
 * 
 * Funciones para gestionar las relaciones entre usuarios referidores y referidos.
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Procesar relación de referidos cuando un usuario se registra
 */
function floresinc_rp_process_referral_relationship($user_id) {
    global $wpdb;
    
    // Obtener el código de referido de la cookie
    $referral_code = isset($_COOKIE['floresinc_referral']) ? sanitize_text_field($_COOKIE['floresinc_referral']) : '';
    
    // También verificar si se envió directamente en el formulario de registro
    if (empty($referral_code) && isset($_POST['referralCode'])) {
        $referral_code = sanitize_text_field($_POST['referralCode']);
    }
    
    error_log("Procesando relación de referido para usuario ID $user_id, Código: " . ($referral_code ? $referral_code : "ninguno"));
    
    if (empty($referral_code)) {
        return false;
    }
    
    // Obtener ID del referidor por el código
    $table = $wpdb->prefix . 'floresinc_referrals';
    $referrer_id = $wpdb->get_var($wpdb->prepare("
        SELECT user_id FROM $table WHERE referral_code = %s
    ", $referral_code));
    
    // Log para depuración
    error_log("Referidor encontrado para código $referral_code: " . ($referrer_id ? "ID $referrer_id" : "ninguno"));
    
    if (!$referrer_id || $referrer_id == $user_id) {
        return false;
    }
    
    // Verificar si el usuario ya tiene un registro en la tabla de referidos
    $user_exists = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM $table WHERE user_id = %d
    ", $user_id));
    
    if ($user_exists) {
        // Actualizar el registro del usuario con su referidor
        $result = $wpdb->update(
            $table,
            ['referrer_id' => $referrer_id],
            ['user_id' => $user_id]
        );
        
        // Registrar información para depuración
        error_log("Actualizando relación de referido: Usuario ID $user_id, Referidor ID $referrer_id, Resultado: " . ($result !== false ? 'Éxito' : 'Error'));
    } else {
        // El usuario no tiene registro en la tabla de referidos, crear uno nuevo
        $code = substr(md5($user_id . time()), 0, 8);
        
        $result = $wpdb->insert(
            $table,
            [
                'user_id' => $user_id,
                'referrer_id' => $referrer_id,
                'referral_code' => $code,
                'signup_date' => current_time('mysql')
            ]
        );
        
        // Registrar información para depuración
        error_log("Creando nuevo registro de referido: Usuario ID $user_id, Referidor ID $referrer_id, Código $code, Resultado: " . ($result !== false ? 'Éxito' : 'Error'));
    }
    
    // Notificar sobre el nuevo referido
    do_action('floresinc_rp_referral_processed', $referrer_id, $user_id);
    
    // Asignar puntos pendientes para cuando el usuario sea aprobado
    update_user_meta($user_id, '_floresinc_pending_referral_points', $referrer_id);
    error_log("Puntos pendientes guardados para usuario ID $user_id, Referidor ID $referrer_id");
    
    // Limpiar cookie
    setcookie('floresinc_referral', '', time() - 3600, '/');
    
    return true;
}

/**
 * Obtener referidos de un usuario
 * 
 * @param int $user_id ID del usuario
 * @param string $type Tipo de referidos a obtener (all, direct, indirect)
 * @param bool $include_user_data Si es true, incluye datos del usuario
 * @return array Array de referidos
 */
function floresinc_rp_get_user_referrals($user_id, $type = 'all', $include_user_data = false) {
    global $wpdb;
    
    if (!$user_id) {
        return [];
    }
    
    $table = $wpdb->prefix . 'floresinc_referrals';
    $referrals = [];
    
    // Obtener referidos directos
    if ($type == 'all' || $type == 'direct') {
        $direct_referrals = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table WHERE referrer_id = %d ORDER BY signup_date DESC
        ", $user_id));
        
        if ($direct_referrals) {
            foreach ($direct_referrals as $referral) {
                $referral_data = [
                    'id' => $referral->id,
                    'user_id' => $referral->user_id,
                    'signup_date' => $referral->signup_date,
                    'level' => 1
                ];
                
                if ($include_user_data) {
                    $user = get_userdata($referral->user_id);
                    if ($user) {
                        $referral_data['user_data'] = [
                            'display_name' => $user->display_name,
                            'user_email' => $user->user_email,
                            'user_registered' => $user->user_registered
                        ];
                    }
                }
                
                $referrals[] = $referral_data;
            }
        }
    }
    
    // Obtener referidos indirectos (de segundo nivel)
    if ($type == 'all' || $type == 'indirect') {
        $direct_user_ids = $wpdb->get_col($wpdb->prepare("
            SELECT user_id FROM $table WHERE referrer_id = %d
        ", $user_id));
        
        if (!empty($direct_user_ids)) {
            $direct_ids_string = implode(',', array_map('intval', $direct_user_ids));
            
            $indirect_referrals = $wpdb->get_results("
                SELECT * FROM $table WHERE referrer_id IN ($direct_ids_string) ORDER BY signup_date DESC
            ");
            
            if ($indirect_referrals) {
                foreach ($indirect_referrals as $referral) {
                    $referral_data = [
                        'id' => $referral->id,
                        'user_id' => $referral->user_id,
                        'referrer_id' => $referral->referrer_id,
                        'signup_date' => $referral->signup_date,
                        'level' => 2
                    ];
                    
                    if ($include_user_data) {
                        $user = get_userdata($referral->user_id);
                        if ($user) {
                            $referral_data['user_data'] = [
                                'display_name' => $user->display_name,
                                'user_email' => $user->user_email,
                                'user_registered' => $user->user_registered
                            ];
                        }
                    }
                    
                    $referrals[] = $referral_data;
                }
            }
        }
    }
    
    return $referrals;
}

/**
 * Obtener el referidor de un usuario
 * 
 * @param int $user_id ID del usuario
 * @return array|false Datos del referidor o false si no tiene
 */
function floresinc_rp_get_user_referrer($user_id) {
    global $wpdb;
    
    if (!$user_id) {
        return false;
    }
    
    $table = $wpdb->prefix . 'floresinc_referrals';
    
    $referrer_id = $wpdb->get_var($wpdb->prepare("
        SELECT referrer_id FROM $table WHERE user_id = %d
    ", $user_id));
    
    if (!$referrer_id) {
        return false;
    }
    
    // Obtener información del usuario referidor
    $user = get_userdata($referrer_id);
    if (!$user) {
        return false;
    }
    
    return array(
        'id' => $referrer_id,
        'name' => $user->display_name,
        'email' => $user->user_email,
        'login' => $user->user_login
    );
}

/**
 * Actualizar el referidor de un usuario
 * 
 * @param int $user_id ID del usuario
 * @param int $referrer_id ID del nuevo referidor
 * @return bool True si se actualizó correctamente, false en caso contrario
 */
function floresinc_rp_update_user_referrer($user_id, $referrer_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'floresinc_referrals';
    
    // Registrar para depuración
    error_log("Actualizando referidor: Usuario ID $user_id, Nuevo referidor ID $referrer_id");
    
    // Verificar si el usuario ya tiene un registro en la tabla de referidos
    $user_exists = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM $table WHERE user_id = %d
    ", $user_id));
    
    if ($user_exists) {
        // Actualizar referidor
        $result = $wpdb->update(
            $table,
            ['referrer_id' => $referrer_id],
            ['user_id' => $user_id]
        );
        
        error_log("Referidor actualizado para usuario ID $user_id: $referrer_id (Resultado: " . ($result !== false ? 'Éxito' : 'Error') . ")");
        return ($result !== false);
    } else {
        // El usuario no tiene registro en la tabla, generar código y crear registro
        $code = substr(md5($user_id . time()), 0, 8);
        
        $result = $wpdb->insert(
            $table,
            [
                'user_id' => $user_id,
                'referrer_id' => $referrer_id,
                'referral_code' => $code,
                'signup_date' => current_time('mysql')
            ]
        );
        
        error_log("Nuevo registro de referido creado para usuario ID $user_id con código $code y referidor $referrer_id (Resultado: " . ($result !== false ? 'Éxito' : 'Error') . ")");
        return ($result !== false);
    }
}
