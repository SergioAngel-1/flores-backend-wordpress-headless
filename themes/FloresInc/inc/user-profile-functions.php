<?php
/**
 * Funciones para el manejo del perfil de usuario
 */

// Registrar endpoint para actualizar perfil de usuario
function register_profile_endpoints() {
    register_rest_route('floresinc/v1', '/user/profile', array(
        'methods' => 'POST',
        'callback' => 'update_user_profile_callback',
        'permission_callback' => function () {
            return is_user_logged_in();
        }
    ));
}
add_action('rest_api_init', 'register_profile_endpoints');

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
    
    // Verificar si el usuario es menor de edad
    if (isset($params['active']) && $params['active'] === false) {
        // Desactivar la cuenta del usuario
        update_user_meta($user_id, 'account_active', false);
        
        // Opcional: Enviar notificación al administrador
        $user_data = get_userdata($user_id);
        $admin_email = get_option('admin_email');
        $subject = 'Cuenta desactivada por usuario menor de edad';
        $message = "El usuario {$user_data->user_login} (ID: {$user_id}) ha sido desactivado por ser menor de edad.";
        wp_mail($admin_email, $subject, $message);
        
        $response['message'] = 'Cuenta desactivada por ser menor de edad';
    } else {
        // Actualizar cuenta como activa si se especifica
        if (isset($params['active']) && $params['active'] === true) {
            update_user_meta($user_id, 'account_active', true);
        }
    }
    
    // Actualizar campos del perfil
    $profile_fields = array(
        'firstName', 'lastName', 'phone', 'birthDate', 'gender', 'newsletter'
    );
    
    foreach ($profile_fields as $field) {
        if (isset($params[$field])) {
            update_user_meta($user_id, $field, sanitize_text_field($params[$field]));
        }
    }
    
    $response['success'] = true;
    return $response;
}

/**
 * Añadir campos de perfil a la respuesta del usuario
 */
function add_profile_fields_to_user_response($response, $user, $request) {
    if (!empty($response->data)) {
        $user_id = $user->ID;
        
        // Añadir campos de perfil
        $response->data['firstName'] = get_user_meta($user_id, 'firstName', true);
        $response->data['lastName'] = get_user_meta($user_id, 'lastName', true);
        $response->data['phone'] = get_user_meta($user_id, 'phone', true);
        $response->data['birthDate'] = get_user_meta($user_id, 'birthDate', true);
        $response->data['gender'] = get_user_meta($user_id, 'gender', true);
        $response->data['newsletter'] = (bool) get_user_meta($user_id, 'newsletter', true);
        $response->data['active'] = (bool) get_user_meta($user_id, 'account_active', true);
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
            'Tu cuenta ha sido desactivada por ser menor de edad. Por favor, contacta con soporte para más información.'
        );
    }
    
    return $user;
}
add_filter('authenticate', 'check_user_account_status', 30, 3);

/**
 * Establecer cuenta como activa por defecto para nuevos usuarios
 */
function set_default_account_status($user_id) {
    update_user_meta($user_id, 'account_active', true);
}
add_action('user_register', 'set_default_account_status');
