<?php
/**
 * Funciones para el sistema de referidos
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Inicializar sistema de referidos
 */
function floresinc_rp_init_referrals() {
    // Generar código de referido al crear usuario
    add_action('user_register', 'floresinc_rp_generate_referral_code', 10);
    // Procesar la relación de referidos después de generar el código
    add_action('user_register', 'floresinc_rp_process_referral_relationship', 20);
    
    // Añadir shortcode para mostrar enlaces de referido
    add_shortcode('floresinc_referral_link', 'floresinc_rp_referral_link_shortcode');
    
    // Gestionar cookies de referido
    add_action('init', 'floresinc_rp_track_referral');
    
    // Registrar endpoint para obtener información del referidor por código
    add_action('rest_api_init', 'floresinc_rp_register_referrer_endpoint');
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
 * Rastrear referido a través de URL
 */
function floresinc_rp_track_referral() {
    // Verificar si el usuario está logeado
    if (is_user_logged_in()) {
        return;
    }
    
    // Verificar si hay código de referido en la URL
    $ref = isset($_GET['ref']) ? sanitize_text_field($_GET['ref']) : '';
    
    if (empty($ref)) {
        return;
    }
    
    // Verificar que el código exista
    global $wpdb;
    $table = $wpdb->prefix . 'floresinc_referrals';
    $exists = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM $table WHERE referral_code = %s
    ", $ref));
    
    if ($exists > 0) {
        // Guardar en cookie por 30 días
        setcookie('floresinc_referral', $ref, time() + (86400 * 30), '/');
        
        // Log para depuración
        error_log("Cookie de referido establecida: $ref");
    }
}

/**
 * Obtener código de referido de un usuario
 */
function floresinc_rp_get_user_referral_code($user_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'floresinc_referrals';
    $code = $wpdb->get_var($wpdb->prepare("
        SELECT referral_code FROM $table WHERE user_id = %d
    ", $user_id));
    
    if (!$code) {
        $code = floresinc_rp_generate_referral_code($user_id);
    }
    
    return $code;
}

/**
 * Obtener URL de referido
 */
function floresinc_rp_get_referral_url($user_id) {
    $code = floresinc_rp_get_user_referral_code($user_id);
    
    if (!$code) {
        return false;
    }
    
    return add_query_arg('ref', $code, home_url());
}

/**
 * Obtener información de un referido por código
 */
function floresinc_rp_get_referral_by_code($code) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'floresinc_referrals';
    $user_id = $wpdb->get_var($wpdb->prepare("
        SELECT user_id FROM $table WHERE referral_code = %s
    ", $code));
    
    if (!$user_id) {
        return false;
    }
    
    $user = get_userdata($user_id);
    
    if (!$user) {
        return false;
    }
    
    return [
        'user_id' => $user_id,
        'name' => $user->display_name,
        'code' => $code
    ];
}

/**
 * Obtener referidos de un usuario
 */
function floresinc_rp_get_user_referrals($user_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'floresinc_referrals';
    
    // Referidos directos (nivel 1)
    $direct_referrals = $wpdb->get_results($wpdb->prepare("
        SELECT r.user_id, r.signup_date
        FROM $table AS r
        WHERE r.referrer_id = %d
    ", $user_id));
    
    $referrals = [];
    
    foreach ($direct_referrals as $ref) {
        $user = get_userdata($ref->user_id);
        
        if (!$user) {
            continue;
        }
        
        // Calcular puntos generados
        $points_table = $wpdb->prefix . 'floresinc_points_transactions';
        $points_generated = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(points) FROM $points_table
            WHERE user_id = %d AND type = 'referral' AND reference_id = %d
        ", $user_id, $ref->user_id));
        
        $referrals[] = [
            'id' => $ref->user_id,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'registration_date' => $ref->signup_date,
            'total_points_generated' => (int) $points_generated ?: 0,
            'level' => 1
        ];
        
        // Referidos indirectos (nivel 2)
        $indirect_referrals = $wpdb->get_results($wpdb->prepare("
            SELECT r.user_id, r.signup_date
            FROM $table AS r
            WHERE r.referrer_id = %d
        ", $ref->user_id));
        
        foreach ($indirect_referrals as $indirect_ref) {
            $indirect_user = get_userdata($indirect_ref->user_id);
            
            if (!$indirect_user) {
                continue;
            }
            
            // Calcular puntos generados
            $indirect_points = $wpdb->get_var($wpdb->prepare("
                SELECT SUM(points) FROM $points_table
                WHERE user_id = %d AND type = 'referral' AND reference_id = %d
            ", $user_id, $indirect_ref->user_id));
            
            $referrals[] = [
                'id' => $indirect_ref->user_id,
                'name' => $indirect_user->display_name,
                'email' => $indirect_user->user_email,
                'registration_date' => $indirect_ref->signup_date,
                'total_points_generated' => (int) $indirect_points ?: 0,
                'level' => 2
            ];
        }
    }
    
    return $referrals;
}

/**
 * Obtener el referidor de un usuario
 */
function floresinc_rp_get_user_referrer($user_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'floresinc_referrals';
    
    // Obtener ID del referidor
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
        // Actualizar el registro existente
        $result = $wpdb->update(
            $table,
            ['referrer_id' => $referrer_id],
            ['user_id' => $user_id]
        );
        
        error_log("Actualización de referidor (registro existente): " . ($result !== false ? "Éxito" : "Error: " . $wpdb->last_error));
        return $result !== false;
    } else {
        // Si no existe, crear un nuevo registro
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
        
        error_log("Creación de nuevo registro de referido: " . ($result !== false ? "Éxito, código $code" : "Error: " . $wpdb->last_error));
        return $result !== false;
    }
}

/**
 * Actualizar el código de referido de un usuario
 */
function floresinc_rp_update_user_referral_code($user_id, $new_code) {
    global $wpdb;
    $table = $wpdb->prefix . 'floresinc_referrals';
    
    // Registrar para depuración
    error_log("Actualizando código de referido: Usuario ID $user_id, Nuevo código $new_code");
    
    // Verificar si el código ya está en uso por otro usuario
    $existing_user = $wpdb->get_var($wpdb->prepare("
        SELECT user_id FROM $table WHERE referral_code = %s AND user_id != %d
    ", $new_code, $user_id));
    
    if ($existing_user) {
        error_log("Error: El código $new_code ya está en uso por el usuario ID $existing_user");
        return false;
    }
    
    // Verificar si el usuario ya tiene un registro en la tabla de referidos
    $user_exists = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM $table WHERE user_id = %d
    ", $user_id));
    
    if ($user_exists) {
        // Actualizar el registro existente
        $result = $wpdb->update(
            $table,
            ['referral_code' => $new_code],
            ['user_id' => $user_id]
        );
        
        error_log("Actualización de código de referido (registro existente): " . ($result !== false ? "Éxito" : "Error: " . $wpdb->last_error));
        return $result !== false;
    } else {
        // Si no existe, crear un nuevo registro
        $result = $wpdb->insert(
            $table,
            [
                'user_id' => $user_id,
                'referrer_id' => 0,
                'referral_code' => $new_code,
                'signup_date' => current_time('mysql')
            ]
        );
        
        error_log("Creación de nuevo registro con código de referido: " . ($result !== false ? "Éxito" : "Error: " . $wpdb->last_error));
        return $result !== false;
    }
}

/**
 * Shortcode para mostrar enlace de referido
 */
function floresinc_rp_referral_link_shortcode($atts) {
    if (!is_user_logged_in()) {
        return '';
    }
    
    $user_id = get_current_user_id();
    $referral_url = floresinc_rp_get_referral_url($user_id);
    
    if (!$referral_url) {
        return '';
    }
    
    $atts = shortcode_atts([
        'text' => 'Mi enlace de referido'
    ], $atts, 'floresinc_referral_link');
    
    return '<a href="' . esc_url($referral_url) . '">' . esc_html($atts['text']) . '</a>';
}

/**
 * Registrar endpoint para obtener información del referidor por código
 */
function floresinc_rp_register_referrer_endpoint() {
    register_rest_route('floresinc/v1', '/referrals/referrer', array(
        'methods' => 'GET',
        'callback' => 'floresinc_rp_get_referrer_by_code',
        'permission_callback' => function() {
            return true; // Permitir acceso público
        }
    ));
}

/**
 * Obtener información del referidor por código
 */
function floresinc_rp_get_referrer_by_code($request) {
    $code = isset($request['code']) ? sanitize_text_field($request['code']) : '';
    
    if (empty($code)) {
        return new WP_Error('missing_code', 'Código de referido no proporcionado', array('status' => 400));
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'floresinc_referrals';
    
    // Obtener ID del usuario referidor
    $referrer_id = $wpdb->get_var($wpdb->prepare("
        SELECT user_id FROM $table WHERE referral_code = %s
    ", $code));
    
    if (!$referrer_id) {
        return new WP_Error('invalid_code', 'Código de referido no válido', array('status' => 404));
    }
    
    // Obtener información del usuario
    $user = get_userdata($referrer_id);
    if (!$user) {
        return new WP_Error('user_not_found', 'Usuario no encontrado', array('status' => 404));
    }
    
    // Devolver información básica del referidor
    return array(
        'name' => $user->display_name,
        'id' => $referrer_id
    );
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
    
    if (!$user_id) {
        // Registrar el error para propósitos de depuración
        error_log("No se encontró usuario con el código de referido: $code");
        return false;
    }
    
    return $user_id;
}
