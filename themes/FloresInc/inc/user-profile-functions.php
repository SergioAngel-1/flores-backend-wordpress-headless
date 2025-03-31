<?php
/**
 * Funciones para el manejo del perfil de usuario
 * 
 * Optimizadas para un mejor rendimiento en integración con React
 */

// Incluir las funciones de optimización si no están ya incluidas
if (!function_exists('flores_api_cache')) {
    // Usar la ruta absoluta completa en lugar de get_template_directory()
    require_once dirname(__FILE__) . '/api-optimization.php';
}

// Registrar endpoint para actualizar perfil de usuario
function register_user_profile_endpoints() {
    // Endpoint para obtener perfil con selección de campos (GET)
    register_rest_route('floresinc/v1', '/user/profile', array(
        'methods' => 'GET',
        'callback' => 'get_user_profile_callback',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'args' => array(
            'fields' => array(
                'default' => 'all',
                'description' => 'Campos específicos a devolver (separados por comas)'
            )
        )
    ));

    // Endpoint para actualizar perfil (POST)
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
 * Callback para obtener perfil de usuario con soporte para campos específicos
 * Implementa una versión "GraphQL-like" para solo devolver los campos solicitados
 */
function get_user_profile_callback($request) {
    $user_id = get_current_user_id();
    if (!$user_id) {
        return new WP_Error('not_logged_in', 'Usuario no autenticado', array('status' => 401));
    }
    
    // Comprobar si hay datos en caché
    $fields_param = $request->get_param('fields');
    $cache_key = flores_api_cache()->build_cache_key('user', $user_id, ['fields' => $fields_param]);
    $cached_data = get_transient($cache_key);
    
    if ($cached_data !== false) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("User profile cache hit for user ID: {$user_id}");
        }
        return rest_ensure_response($cached_data);
    }
    
    // Procesar campos solicitados
    $fields = $fields_param === 'all' ? null : explode(',', $fields_param);
    
    $user = get_userdata($user_id);
    
    // Construir respuesta con todos los campos posibles
    $full_profile = array(
        'id' => $user->ID,
        'firstName' => $user->first_name,
        'lastName' => $user->last_name,
        'displayName' => $user->display_name,
        'email' => $user->user_email,
        'username' => $user->user_login,
        'phone' => get_user_meta($user_id, 'phone', true),
        'birthDate' => get_user_meta($user_id, 'birthDate', true),
        'gender' => get_user_meta($user_id, 'gender', true),
        'newsletter' => (bool) get_user_meta($user_id, 'newsletter', true),
        'active' => (bool) get_user_meta($user_id, 'active', true)
    );
    
    // Si se solicitan campos específicos, filtrar la respuesta
    if ($fields !== null) {
        $filtered_profile = array();
        
        foreach ($fields as $field) {
            $field = trim($field);
            if (isset($full_profile[$field])) {
                $filtered_profile[$field] = $full_profile[$field];
            }
        }
        
        // Siempre incluir ID para referencia
        if (!isset($filtered_profile['id'])) {
            $filtered_profile['id'] = $user_id;
        }
        
        $response_data = $filtered_profile;
    } else {
        $response_data = $full_profile;
    }
    
    // Guardar en caché (10 minutos)
    set_transient($cache_key, $response_data, 600);
    
    return rest_ensure_response($response_data);
}

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
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Parámetros recibidos en update_user_profile_callback: ' . print_r($params, true));
    }
    
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
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Actualizando email a: ' . $params['email']);
        }
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
    
    // Invalidar caché del usuario
    flores_api_cache()->invalidate('user', $user_id);
    
    // Obtener los datos actualizados del usuario para devolverlos en la respuesta
    $user = get_userdata($user_id);
    
    // Verificar que el email se haya actualizado correctamente
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Email después de la actualización: ' . $user->user_email);
    }
    
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
 * Ahora con optimización de caché
 */
function add_profile_fields_to_user_response($response, $user, $request) {
    if (!empty($user)) {
        $user_id = $user->ID;
        
        // Verificar si hay versión en caché primero
        $cache_key = flores_api_cache()->build_cache_key('user_rest', $user_id, []);
        $cached_fields = get_transient($cache_key);
        
        if ($cached_fields !== false) {
            // Añadir campos desde la caché
            foreach ($cached_fields as $field => $value) {
                $response->data[$field] = $value;
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("User REST fields cache hit for user ID: {$user_id}");
            }
            
            return $response;
        }
        
        // No hay caché, obtener datos directamente
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Preparando respuesta REST para usuario ID: ' . $user_id);
            error_log('Email del usuario en la respuesta: ' . $user->user_email);
        }
        
        // Preparar campos a cachear
        $cached_fields = array(
            'firstName' => get_user_meta($user_id, 'firstName', true) ?: $user->first_name,
            'lastName' => get_user_meta($user_id, 'lastName', true) ?: $user->last_name,
            'email' => $user->user_email,
            'phone' => get_user_meta($user_id, 'phone', true),
            'birthDate' => get_user_meta($user_id, 'birthDate', true),
            'gender' => get_user_meta($user_id, 'gender', true),
            'newsletter' => (bool) get_user_meta($user_id, 'newsletter', true),
            'active' => (bool) get_user_meta($user_id, 'active', true)
        );
        
        // Cachear para futuras solicitudes (10 minutos)
        set_transient($cache_key, $cached_fields, 600);
        
        // Añadir campos a la respuesta
        foreach ($cached_fields as $field => $value) {
            $response->data[$field] = $value;
        }
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

// Invalidar caché de usuario cuando se actualiza su perfil o metadatos
function invalidate_user_profile_cache($meta_id, $user_id, $meta_key, $meta_value) {
    // Lista de claves meta relacionadas con el perfil
    $profile_meta_keys = array(
        'firstName', 'lastName', 'phone', 'birthDate', 
        'gender', 'newsletter', 'active', 'account_active'
    );
    
    // Solo invalidar para claves relevantes
    if (in_array($meta_key, $profile_meta_keys)) {
        flores_api_cache()->invalidate('user', $user_id);
        
        // También invalidar la caché para la versión REST
        $cache_key = flores_api_cache()->build_cache_key('user_rest', $user_id, []);
        delete_transient($cache_key);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Invalidated user cache for ID: {$user_id}, meta key: {$meta_key}");
        }
    }
}
add_action('updated_user_meta', 'invalidate_user_profile_cache', 10, 4);
add_action('added_user_meta', 'invalidate_user_profile_cache', 10, 4);
add_action('deleted_user_meta', 'invalidate_user_profile_cache', 10, 4);
