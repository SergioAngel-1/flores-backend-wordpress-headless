<?php
/**
 * Funciones para el manejo del perfil de usuario
 */

// Registrar endpoint para actualizar perfil de usuario
function register_user_profile_endpoints() {
    register_rest_route('floresinc/v1', '/user/profile', array(
        'methods' => 'POST',
        'callback' => 'update_user_profile_callback',
        'permission_callback' => function () {
            return is_user_logged_in();
        }
    ));
}
add_action('rest_api_init', 'register_user_profile_endpoints');

/**
 * Callback para actualizar el perfil del usuario
 */
function update_user_profile_callback($request) {
    $user_id = get_current_user_id();
    if (!$user_id) {
        return new WP_Error('not_logged_in', 'Usuario no autenticado', array('status' => 401));
    }
    
    $params = $request->get_params();
    $response = array('success' => false);
    
    // Log para depuración
    error_log('Parámetros recibidos en update_user_profile_callback: ' . print_r($params, true));
    
    // Datos básicos del usuario para wp_update_user
    $userdata = array(
        'ID' => $user_id
    );
    
    // Actualizar nombre y apellido en los campos estándar de WordPress
    if (isset($params['firstName'])) {
        $userdata['first_name'] = sanitize_text_field($params['firstName']);
    }
    
    if (isset($params['lastName'])) {
        $userdata['last_name'] = sanitize_text_field($params['lastName']);
    }
    
    // Actualizar email si se proporciona
    if (isset($params['email']) && is_email($params['email'])) {
        $userdata['user_email'] = sanitize_email($params['email']);
        // Log para depuración
        error_log('Actualizando email a: ' . $params['email']);
    }
    
    // Actualizar los datos básicos del usuario
    $user_id = wp_update_user($userdata);
    
    if (is_wp_error($user_id)) {
        error_log('Error al actualizar usuario: ' . $user_id->get_error_message());
        return new WP_Error('update_failed', $user_id->get_error_message(), array('status' => 500));
    }
    
    // Actualizar campos personalizados
    if (isset($params['phone'])) {
        update_user_meta($user_id, 'phone', sanitize_text_field($params['phone']));
    }
    
    if (isset($params['birthDate'])) {
        update_user_meta($user_id, 'birthDate', sanitize_text_field($params['birthDate']));
    }
    
    if (isset($params['gender'])) {
        update_user_meta($user_id, 'gender', sanitize_text_field($params['gender']));
    }
    
    if (isset($params['newsletter'])) {
        update_user_meta($user_id, 'newsletter', (bool) $params['newsletter']);
    }
    
    if (isset($params['active'])) {
        update_user_meta($user_id, 'active', (bool) $params['active']);
    }
    
    // Obtener los datos actualizados del usuario para devolverlos en la respuesta
    $user = get_userdata($user_id);
    
    // Verificar que el email se haya actualizado correctamente
    error_log('Email después de la actualización: ' . $user->user_email);
    
    // Preparar datos para la respuesta
    $user_data = array(
        'id' => $user->ID,
        'firstName' => $user->first_name,
        'lastName' => $user->last_name,
        'email' => $user->user_email, // Usar user_email directamente
        'phone' => get_user_meta($user_id, 'phone', true),
        'birthDate' => get_user_meta($user_id, 'birthDate', true),
        'gender' => get_user_meta($user_id, 'gender', true),
        'newsletter' => (bool) get_user_meta($user_id, 'newsletter', true),
        'active' => (bool) get_user_meta($user_id, 'active', true)
    );
    
    $response = array(
        'success' => true,
        'message' => 'Perfil actualizado correctamente',
        'user' => $user_data
    );
    
    return rest_ensure_response($response);
}

/**
 * Añadir campos de perfil personalizados a la respuesta de la API REST
 */
function add_profile_fields_to_user_response($response, $user, $request) {
    if (!empty($user)) {
        $user_id = $user->ID;
        
        // Log para depuración
        error_log('Preparando respuesta REST para usuario ID: ' . $user_id);
        error_log('Email del usuario en la respuesta: ' . $user->user_email);
        
        // Añadir campos de perfil
        $response->data['firstName'] = get_user_meta($user_id, 'firstName', true) ?: $user->first_name;
        $response->data['lastName'] = get_user_meta($user_id, 'lastName', true) ?: $user->last_name;
        // Asegurarse de que el email siempre esté presente en la respuesta
        $response->data['email'] = $user->user_email;
        $response->data['phone'] = get_user_meta($user_id, 'phone', true);
        $response->data['birthDate'] = get_user_meta($user_id, 'birthDate', true);
        $response->data['gender'] = get_user_meta($user_id, 'gender', true);
        $response->data['newsletter'] = (bool) get_user_meta($user_id, 'newsletter', true);
        $response->data['active'] = (bool) get_user_meta($user_id, 'active', true);
    }
    
    return $response;
}
add_filter('rest_prepare_user', 'add_profile_fields_to_user_response', 10, 3);

/**
 * Verificar si la cuenta del usuario está activa antes de permitir el login
 */
function check_user_account_status($user, $username, $password) {
    // Si ya hay un error, no hacer nada
    if (is_wp_error($user)) {
        return $user;
    }
    
    // Verificar si la cuenta está desactivada
    $account_active = get_user_meta($user->ID, 'account_active', true);
    
    // Si account_active es false (explícitamente), bloquear el acceso
    if ($account_active === '0' || $account_active === false) {
        return new WP_Error(
            'account_inactive',
            'Tu cuenta ha sido desactivada. Por favor, contacta al administrador.'
        );
    }
    
    return $user;
}
add_filter('authenticate', 'check_user_account_status', 30, 3);

// Establecer cuenta como activa por defecto para nuevos usuarios
function set_default_account_status($user_id) {
    update_user_meta($user_id, 'account_active', true);
}
add_action('user_register', 'set_default_account_status');
