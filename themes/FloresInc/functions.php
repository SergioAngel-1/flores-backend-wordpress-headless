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

// Incluir el archivo de gestión de roles
require_once __DIR__ . '/inc/role-manager-functions.php';

// Incluir el archivo de configuración del Gestor de la tienda
require_once __DIR__ . '/inc/shop-manager-settings.php';

// Incluir el archivo de optimización de API
require_once __DIR__ . '/inc/api-optimization.php';

// Incluir el archivo de funciones de catálogos
require_once __DIR__ . '/inc/catalog-functions.php';

/**
 * Registro de usuarios - Endpoint personalizado
 * Añade un endpoint para el registro de nuevos usuarios con estado pendiente
 */
function custom_user_register_endpoint() {
    // Endpoint original (mantener por compatibilidad)
    register_rest_route('wp/v2', '/users/register', array(
        'methods' => 'POST',
        'callback' => 'custom_register_user',
        'permission_callback' => function() {
            return true; // Permitir acceso público
        }
    ));
    
    // Nuevo endpoint para el frontend React
    register_rest_route('floresinc/v1', '/register', array(
        'methods' => 'POST',
        'callback' => 'custom_register_user',
        'permission_callback' => function() {
            return true; // Permitir acceso público
        }
    ));
}
add_action('rest_api_init', 'custom_user_register_endpoint', 20);

/**
 * Función para registrar un nuevo usuario con validación
 */
function custom_register_user($request) {
    $username = sanitize_user($request['username']);
    $email = sanitize_email($request['email']);
    $password = $request['password'];
    $name = isset($request['name']) ? sanitize_text_field($request['name']) : $username;
    $phone = isset($request['phone']) ? sanitize_text_field($request['phone']) : '';
    $referral_code = isset($request['referralCode']) ? sanitize_text_field($request['referralCode']) : '';

    // Validar campos
    if (empty($username) || empty($email) || empty($password)) {
        return new WP_Error('missing_fields', 'Todos los campos son obligatorios', array('status' => 400));
    }

    // Validar email
    if (!is_email($email)) {
        return new WP_Error('invalid_email', 'El email no es válido', array('status' => 400));
    }

    // Verificar si el usuario ya existe
    if (username_exists($username) || email_exists($email)) {
        return new WP_Error('user_exists', 'El usuario o email ya está registrado', array('status' => 400));
    }

    // Si hay código de referido, guardarlo en cookie para que el sistema de referidos lo procese
    if (!empty($referral_code)) {
        setcookie('floresinc_referral', $referral_code, time() + (86400 * 30), '/');
        error_log("Código de referido recibido durante registro: $referral_code");
        
        // También lo guardamos en $_POST para que esté disponible inmediatamente
        $_POST['referralCode'] = $referral_code;
    }

    // Crear el usuario con estado pendiente
    $user_data = array(
        'user_login' => $username,
        'user_email' => $email,
        'user_pass' => $password,
        'display_name' => $name,
        'first_name' => $name,  // Añadir nombre como first_name
        'nickname' => $name,    // Añadir nombre como nickname
        'role' => 'subscriber'
    );

    $user_id = wp_insert_user($user_data);

    if (is_wp_error($user_id)) {
        return $user_id;
    }

    // Guardar el estado pendiente y otros datos
    update_user_meta($user_id, 'pending_approval', true);
    
    // Guardar el teléfono si está disponible
    if (!empty($phone)) {
        update_user_meta($user_id, 'phone', $phone);
    }
    
    // Forzar la generación del código de referido y procesamiento de la relación
    if (function_exists('floresinc_rp_generate_referral_code')) {
        floresinc_rp_generate_referral_code($user_id);
    }
    
    if (function_exists('floresinc_rp_process_referral_relationship')) {
        floresinc_rp_process_referral_relationship($user_id);
    }

    // Enviar notificación al administrador
    send_admin_notification_new_user($user_id, $username, $email);

    return array(
        'status' => 'success',
        'message' => 'Usuario registrado correctamente. Un administrador revisará tu cuenta pronto.',
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
        $pending = get_user_meta($user_id, 'pending_approval', true);
        
        if ($pending && current_user_can('edit_users')) {
            $approve_link = admin_url("users.php?action=approve&user_id={$user_id}");
            $approve_nonce = wp_create_nonce("approve-user_{$user_id}");
            
            return '<span style="color:red; display:block; margin-bottom:5px;">Pendiente</span>' .
                   '<a href="' . $approve_link . '&_wpnonce=' . $approve_nonce . '" ' .
                   'class="button button-primary" style="display:inline-block;">Aprobar</a>';
        } else if ($pending) {
            return '<span style="color:red;">Pendiente</span>';
        } else {
            return '<span style="color:green;">Aprobado</span>';
        }
    }
    
    return $value;
}
add_filter('manage_users_custom_column', 'show_pending_status_column_content', 10, 3);

/**
 * Añadir un botón de aprobación en la administración de usuarios
 */
function add_approve_user_button($actions, $user_object) {
    // Verificar si el usuario tiene el meta pending_approval
    $pending = get_user_meta($user_object->ID, 'pending_approval', true);
    $role = $user_object->roles[0] ?? 'no-role';
    
    // Registrar información para depuración
    error_log("Usuario ID: {$user_object->ID}, Rol: {$role}, Pending: " . ($pending ? 'true' : 'false'));
    
    // Mostrar el botón de aprobación si el usuario está pendiente
    if (current_user_can('edit_users') && $pending) {
        $approve_link = admin_url("users.php?action=approve&user_id={$user_object->ID}");
        $approve_nonce = wp_create_nonce("approve-user_{$user_object->ID}");
        $actions['approve'] = "<a href='{$approve_link}&_wpnonce={$approve_nonce}' class='button button-primary' style='color: white; font-weight: bold; margin-right: 5px;'>Aprobar</a>";
        
        // Registrar que se añadió el botón
        error_log("Botón de aprobación añadido para usuario ID: {$user_object->ID}");
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
        
        // Cambiar rol de usuario a customer
        $user = new WP_User($user_id);
        $user->set_role('customer');
        
        // Notificar al usuario
        $user_data = get_userdata($user_id);
        if ($user_data) {
            $site_name = get_bloginfo('name');
            $login_url = wp_login_url();
            
            $subject = "[$site_name] Tu cuenta ha sido aprobada";
            $message = "Hola {$user_data->display_name},\n\n";
            $message .= "Tu cuenta en $site_name ha sido aprobada por un administrador.\n";
            $message .= "Ya puedes iniciar sesión en: $login_url\n\n";
            $message .= "Saludos,\n";
            $message .= "El equipo de $site_name";
            
            wp_mail($user_data->user_email, $subject, $message);
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
