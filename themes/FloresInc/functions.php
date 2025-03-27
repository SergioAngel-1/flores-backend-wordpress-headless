<?php

/**
 * Funciones principales del tema FloresInc
 * 
 * Este archivo carga todas las funcionalidades del tema a través del sistema
 * de inicialización en el directorio inc.
 */

/**
 * Incluir archivo de inicialización que carga todas las funcionalidades
 */
require_once __DIR__ . '/inc/init.php';

/**
 * Registro de usuarios - Endpoint personalizado
 * Añade un endpoint para el registro de nuevos usuarios con estado pendiente
 */
function custom_user_register_endpoint() {
    register_rest_route('wp/v2', '/users/register', array(
        'methods' => 'POST',
        'callback' => 'custom_register_user',
        'permission_callback' => function() {
            return true; // Permitir acceso público
        }
    ));
}
add_action('rest_api_init', 'custom_user_register_endpoint');

/**
 * Función para registrar un nuevo usuario con validación
 */
function custom_register_user($request) {
    $username = sanitize_user($request['username']);
    $email = sanitize_email($request['email']);
    $password = $request['password'];
    $phone = sanitize_text_field($request['phone'] ?? '');
    
    // Validación de datos
    if (empty($username)) {
        return new WP_Error('invalid_username', 'El nombre de usuario es obligatorio', array('status' => 400));
    }
    
    if (empty($email) || !is_email($email)) {
        return new WP_Error('invalid_email', 'El correo electrónico es inválido', array('status' => 400));
    }
    
    if (empty($password)) {
        return new WP_Error('invalid_password', 'La contraseña es obligatoria', array('status' => 400));
    }
    
    // Verificar si el usuario o email ya existen
    if (username_exists($username)) {
        return new WP_Error('username_exists', 'Este nombre de usuario ya está en uso', array('status' => 400));
    }
    
    if (email_exists($email)) {
        return new WP_Error('email_exists', 'Este correo electrónico ya está registrado', array('status' => 400));
    }
    
    // Crear el usuario pendiente de aprobación
    $user_id = wp_insert_user(array(
        'user_login' => $username,
        'user_pass' => $password,
        'user_email' => $email,
        'role' => 'customer',
        'user_status' => 0 // Pendiente de aprobación
    ));
    
    if (is_wp_error($user_id)) {
        return new WP_Error('registration_failed', $user_id->get_error_message(), array('status' => 500));
    }
    
    // Guardar el teléfono como metadato del usuario
    if (!empty($phone)) {
        update_user_meta($user_id, 'phone', $phone);
    }
    
    // Marcar como pendiente de aprobación
    update_user_meta($user_id, 'pending_approval', true);
    
    // Enviar notificación al administrador
    send_admin_notification_new_user($user_id, $username, $email);
    
    // Devolver respuesta exitosa
    return array(
        'success' => true,
        'message' => 'Usuario registrado con éxito. Pendiente de aprobación por un administrador.',
        'user_id' => $user_id
    );
}

/**
 * Envía una notificación al administrador cuando se registra un nuevo usuario
 */
function send_admin_notification_new_user($user_id, $username, $email) {
    $admin_email = get_option('admin_email');
    $site_name = get_bloginfo('name');
    $subject = "[$site_name] Nuevo usuario pendiente de aprobación";
    
    $admin_url = admin_url('users.php');
    
    $message = "Un nuevo usuario se ha registrado en tu sitio $site_name y está pendiente de aprobación.\n\n";
    $message .= "Nombre de usuario: $username\n";
    $message .= "Correo electrónico: $email\n\n";
    $message .= "Para aprobar o rechazar este usuario, ve a: $admin_url\n";
    
    wp_mail($admin_email, $subject, $message);
}

/**
 * Modificar respuesta de user/me para incluir si está pendiente de aprobación
 */
function custom_prepare_user_response($response, $user, $request) {
    if (is_object($response) && $request->get_route() === '/wp/v2/users/me') {
        $pending = get_user_meta($user->ID, 'pending_approval', true);
        $data = $response->get_data();
        $data['pending'] = !empty($pending);
        $response->set_data($data);
    }
    return $response;
}
add_filter('rest_prepare_user', 'custom_prepare_user_response', 10, 3);

/**
 * Añadir un botón de aprobación en la administración de usuarios
 */
function add_approve_user_button($actions, $user_object) {
    if (current_user_can('edit_users') && get_user_meta($user_object->ID, 'pending_approval', true)) {
        $approve_link = admin_url("users.php?action=approve&user_id={$user_object->ID}");
        $approve_nonce = wp_create_nonce("approve-user_{$user_object->ID}");
        $actions['approve'] = "<a href='{$approve_link}&_wpnonce={$approve_nonce}' class='approve'>Aprobar</a>";
    }
    return $actions;
}
add_filter('user_row_actions', 'add_approve_user_button', 10, 2);

/**
 * Procesar la aprobación de usuario
 */
function process_user_approval() {
    if (!isset($_GET['action']) || $_GET['action'] !== 'approve') {
        return;
    }
    
    if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
        return;
    }
    
    $user_id = intval($_GET['user_id']);
    
    if (!current_user_can('edit_users')) {
        wp_die(__('No tienes permisos para realizar esta acción.'));
    }
    
    check_admin_referer("approve-user_{$user_id}");
    
    // Verificar si el usuario está pendiente de aprobación
    if (get_user_meta($user_id, 'pending_approval', true)) {
        // Marcar como aprobado
        delete_user_meta($user_id, 'pending_approval');
        
        // Notificar al usuario
        $user = get_userdata($user_id);
        if ($user) {
            $site_name = get_bloginfo('name');
            $login_url = wp_login_url();
            
            $subject = "[$site_name] Tu cuenta ha sido aprobada";
            $message = "Hola {$user->display_name},\n\n";
            $message .= "Tu cuenta en $site_name ha sido aprobada por un administrador.\n";
            $message .= "Ya puedes iniciar sesión en: $login_url\n\n";
            $message .= "Saludos,\n";
            $message .= "El equipo de $site_name";
            
            wp_mail($user->user_email, $subject, $message);
        }
        
        // Mensaje de éxito
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>Usuario aprobado correctamente.</p></div>';
        });
    }
    
    // Redireccionar de vuelta a la lista de usuarios
    wp_redirect(admin_url('users.php'));
    exit;
}
add_action('admin_init', 'process_user_approval');

/**
 * Añadir columna de estado en la lista de usuarios del admin
 */
function add_pending_status_column($columns) {
    $columns['pending_status'] = 'Estado';
    return $columns;
}
add_filter('manage_users_columns', 'add_pending_status_column');

/**
 * Mostrar el estado del usuario en la columna
 */
function show_pending_status_column_content($value, $column_name, $user_id) {
    if ($column_name === 'pending_status') {
        return get_user_meta($user_id, 'pending_approval', true) 
            ? '<span style="color:red;">Pendiente</span>' 
            : '<span style="color:green;">Aprobado</span>';
    }
    return $value;
}
add_filter('manage_users_custom_column', 'show_pending_status_column_content', 10, 3);

/**
 * Bloquear el acceso a la administración para usuarios pendientes de aprobación
 */
function block_pending_users_admin_access() {
    if (!is_admin()) {
        return;
    }
    
    $current_user = wp_get_current_user();
    if (!$current_user->exists()) {
        return;
    }
    
    // Verificar si el usuario está pendiente de aprobación
    if (get_user_meta($current_user->ID, 'pending_approval', true)) {
        wp_redirect(home_url());
        exit;
    }
}
add_action('init', 'block_pending_users_admin_access');
