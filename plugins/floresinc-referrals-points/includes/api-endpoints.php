<?php
/**
 * Endpoints de API REST para el sistema de referidos y Flores Coins
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registrar endpoints de API
 */
function floresinc_rp_register_api_endpoints() {
    add_action('rest_api_init', 'floresinc_rp_register_routes');
}

/**
 * Registrar rutas de API
 */
function floresinc_rp_register_routes() {
    // Namespace base para nuestros endpoints
    $namespace = 'floresinc/v1';
    
    // Ruta para obtener Flores Coins del usuario actual
    register_rest_route($namespace, '/points', [
        'methods' => 'GET',
        'callback' => 'floresinc_rp_get_current_user_points_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
    
    // Ruta para obtener transacciones de Flores Coins del usuario actual
    register_rest_route($namespace, '/points/transactions', [
        'methods' => 'GET',
        'callback' => 'floresinc_rp_get_user_transactions_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
    
    // Ruta para obtener información de referidos del usuario actual
    register_rest_route($namespace, '/referrals', [
        'methods' => 'GET',
        'callback' => 'floresinc_rp_get_user_referrals_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
    
    // Ruta para obtener estadísticas de referidos del usuario actual
    register_rest_route($namespace, '/referrals/stats', [
        'methods' => 'GET',
        'callback' => 'floresinc_rp_get_user_referral_stats_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
    
    // Ruta para obtener el código y enlace de referido del usuario actual
    register_rest_route($namespace, '/referrals/code', [
        'methods' => 'GET',
        'callback' => 'floresinc_rp_get_user_referral_code_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
    
    // Ruta para validar un código de referido
    register_rest_route($namespace, '/referrals/validate-code', [
        'methods' => 'GET',
        'callback' => 'floresinc_rp_validate_referral_code_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
    
    // Ruta para obtener información de Flores Coins de un producto (pública)
    register_rest_route($namespace, '/product/(?P<id>\d+)/points', [
        'methods' => 'GET',
        'callback' => 'floresinc_rp_get_product_points_endpoint',
        'permission_callback' => '__return_true'
    ]);
    
    // Ruta para obtener resumen de estadísticas (solo admin)
    register_rest_route($namespace, '/admin/stats', [
        'methods' => 'GET',
        'callback' => 'floresinc_rp_get_admin_stats_endpoint',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ]);
    
    // Ruta para transferir Flores Coins a otro usuario usando código de referido
    register_rest_route($namespace, '/points/transfer', [
        'methods' => 'POST',
        'callback' => 'floresinc_rp_transfer_points_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
}

/**
 * Endpoint: Obtener Flores Coins del usuario actual
 */
function floresinc_rp_get_current_user_points_endpoint() {
    $user_id = get_current_user_id();
    $user_points = floresinc_rp_get_user_points($user_id);
    
    if (!$user_points) {
        $user_points = [
            'balance' => 0,
            'total_earned' => 0,
            'used' => 0
        ];
    }
    
    // Calcular valor monetario
    $options = FloresInc_RP()->get_options();
    $conversion_rate = $options['points_conversion_rate'];
    $points_value = $user_points['balance'] / $conversion_rate;
    
    $response = [
        'balance' => $user_points['balance'],
        'total_earned' => $user_points['total_earned'],
        'used' => $user_points['used'],
        'monetary_value' => $points_value,
        'conversion_rate' => $conversion_rate
    ];
    
    return new WP_REST_Response($response, 200);
}

/**
 * Endpoint: Obtener transacciones de Flores Coins del usuario actual
 */
function floresinc_rp_get_user_transactions_endpoint(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    $page = isset($request['page']) ? absint($request['page']) : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;
    
    global $wpdb;
    $transactions_table = $wpdb->prefix . 'floresinc_points_transactions';
    
    // Obtener transacciones
    $transactions = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $transactions_table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d
    ", $user_id, $per_page, $offset));
    $total_transactions = floresinc_rp_count_user_transactions($user_id);
    
    if (!$transactions) {
        $transactions = [];
    }
    
    // Formatear para la respuesta
    $formatted_transactions = [];
    foreach ($transactions as $transaction) {
        $formatted_transactions[] = [
            'id' => $transaction->id,
            'date' => $transaction->created_at,
            'type' => $transaction->type,
            'points' => $transaction->points,
            'description' => $transaction->description,
            'expires_at' => $transaction->expiration_date
        ];
    }
    
    $response = [
        'transactions' => $formatted_transactions,
        'total' => $total_transactions,
        'pages' => ceil($total_transactions / $per_page)
    ];
    
    return new WP_REST_Response($response, 200);
}

/**
 * Endpoint: Obtener información de referidos del usuario actual
 */
function floresinc_rp_get_user_referrals_endpoint() {
    $user_id = get_current_user_id();
    $referrals = floresinc_rp_get_user_referrals($user_id);
    
    if (!$referrals) {
        $referrals = [];
    }
    
    // Obtener resumen
    $stats = floresinc_rp_get_referral_stats($user_id);
    
    $response = [
        'referrals' => $referrals,
        'total_referrals' => $stats['total_referrals'],
        'direct_referrals' => $stats['direct_referrals'],
        'indirect_referrals' => $stats['indirect_referrals'],
        'total_points_generated' => $stats['total_points_generated']
    ];
    
    return new WP_REST_Response($response, 200);
}

/**
 * Endpoint: Obtener estadísticas de referidos del usuario actual
 */
function floresinc_rp_get_user_referral_stats_endpoint() {
    $user_id = get_current_user_id();
    $stats = floresinc_rp_get_referral_stats($user_id);
    
    $response = [
        'total_referrals' => $stats['total_referrals'],
        'direct_referrals' => $stats['direct_referrals'],
        'indirect_referrals' => $stats['indirect_referrals'],
        'total_points_generated' => $stats['total_points_generated']
    ];
    
    return new WP_REST_Response($response, 200);
}

/**
 * Endpoint: Obtener el código y enlace de referido del usuario actual
 */
function floresinc_rp_get_user_referral_code_endpoint() {
    $user_id = get_current_user_id();
    $referral_code = floresinc_rp_get_user_referral_code($user_id);
    $referral_url = floresinc_rp_get_referral_url($user_id);
    
    $response = [
        'code' => $referral_code,
        'url' => $referral_url
    ];
    
    return new WP_REST_Response($response, 200);
}

/**
 * Endpoint: Obtener información de Flores Coins de un producto
 */
function floresinc_rp_get_product_points_endpoint($request) {
    $product_id = $request['id'];
    
    $points = floresinc_rp_calculate_product_points($product_id);
    
    $response = [
        'product_id' => $product_id,
        'points' => $points
    ];
    
    return new WP_REST_Response($response, 200);
}

/**
 * Endpoint: Obtener resumen de estadísticas para administradores
 */
function floresinc_rp_get_admin_stats_endpoint() {
    // Verificar permisos
    if (!current_user_can('manage_options')) {
        return new WP_Error('rest_forbidden', 'No tienes permisos para acceder a esta información', ['status' => 403]);
    }
    
    global $wpdb;
    $users_table = $wpdb->prefix . 'users';
    $points_table = $wpdb->prefix . 'floresinc_user_points';
    $table = $wpdb->prefix . 'floresinc_points_transactions';
    
    // Total de usuarios con Flores Coins
    $users_with_points = $wpdb->get_var("
        SELECT COUNT(DISTINCT user_id) FROM $points_table
    ");
    
    // Total de Flores Coins activos
    $total_active_points = $wpdb->get_var("
        SELECT SUM(balance) FROM $points_table
    ");
    
    // Total de transacciones
    $total_transactions = $wpdb->get_var("
        SELECT COUNT(*) FROM $table
    ");
    
    // Total de referidos
    $total_referrals = $wpdb->get_var("
        SELECT COUNT(*) FROM {$wpdb->prefix}floresinc_referrals WHERE referrer_id IS NOT NULL
    ");
    
    // Transacciones por tipo
    $transactions_by_type = $wpdb->get_results("
        SELECT type, COUNT(*) as count, SUM(points) as points_sum
        FROM $table
        GROUP BY type
    ", ARRAY_A);
    
    $response = [
        'users_with_points' => (int) $users_with_points,
        'total_active_points' => (int) $total_active_points,
        'total_transactions' => (int) $total_transactions,
        'total_referrals' => (int) $total_referrals,
        'transactions_by_type' => $transactions_by_type
    ];
    
    return new WP_REST_Response($response, 200);
}

/**
 * Contar transacciones de un usuario
 */
function floresinc_rp_count_user_transactions($user_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'floresinc_points_transactions';
    
    return $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM $table WHERE user_id = %d
    ", $user_id));
}

/**
 * Obtener estadísticas de referidos
 */
function floresinc_rp_get_referral_stats($user_id) {
    global $wpdb;
    $referrals_table = $wpdb->prefix . 'floresinc_referrals';
    $transactions_table = $wpdb->prefix . 'floresinc_points_transactions';
    
    // Total de referidos directos
    $direct_referrals = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM $referrals_table WHERE referrer_id = %d
    ", $user_id));
    
    // Total de referidos indirectos
    $indirect_referrals = 0;
    $direct_referral_ids = $wpdb->get_col($wpdb->prepare("
        SELECT user_id FROM $referrals_table WHERE referrer_id = %d
    ", $user_id));
    
    if (!empty($direct_referral_ids)) {
        $direct_ids_string = implode(',', array_map('intval', $direct_referral_ids));
        $indirect_referrals = $wpdb->get_var("
            SELECT COUNT(*) FROM $referrals_table 
            WHERE referrer_id IN ($direct_ids_string)
        ");
    }
    
    // Total de Flores Coins generados
    $query = $wpdb->prepare("
        SELECT SUM(points) FROM $transactions_table 
        WHERE user_id = %d AND (type = 'referral' OR type = 'referral_signup')
    ", $user_id);
    
    // Registrar la consulta para depuración
    error_log("Consulta de Flores Coins generados por referidos: " . $query);
    
    $total_points_generated = $wpdb->get_var($query);
    
    // Registrar el resultado para depuración
    error_log("Resultado de Flores Coins generados por referidos para usuario ID $user_id: " . ($total_points_generated ?: '0'));
    
    // Verificar si hay transacciones de tipo referral_signup
    $signup_count = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM $transactions_table 
        WHERE user_id = %d AND type = 'referral_signup'
    ", $user_id));
    
    // Registrar el conteo para depuración
    error_log("Número de transacciones de tipo 'referral_signup' para usuario ID $user_id: $signup_count");
    
    // Verificar si hay transacciones de tipo referral
    $referral_count = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM $transactions_table 
        WHERE user_id = %d AND type = 'referral'
    ", $user_id));
    
    // Registrar el conteo para depuración
    error_log("Número de transacciones de tipo 'referral' para usuario ID $user_id: $referral_count");
    
    return [
        'total_referrals' => $direct_referrals + $indirect_referrals,
        'direct_referrals' => $direct_referrals,
        'indirect_referrals' => $indirect_referrals,
        'total_points_generated' => $total_points_generated ? $total_points_generated : 0
    ];
}

/**
 * Endpoint para transferir Flores Coins a otro usuario usando código de referido
 * 
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function floresinc_rp_transfer_points_endpoint($request) {
    // Obtener parámetros de la petición
    $recipient_code = $request->get_param('recipient_code');
    $points_amount = intval($request->get_param('points_amount'));
    $notes = sanitize_text_field($request->get_param('notes') ?? '');
    
    // Validar la entrada
    if (empty($recipient_code)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('Se requiere un código de referido válido.', 'floresinc-rp')
        ], 400);
    }
    
    if ($points_amount <= 0) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('La cantidad de puntos debe ser mayor que cero.', 'floresinc-rp')
        ], 400);
    }
    
    // Obtener el usuario actual
    $sender_id = get_current_user_id();
    $sender_points = floresinc_rp_get_user_points($sender_id);
    
    // Verificar que el usuario tiene suficientes puntos
    if ($sender_points['balance'] < $points_amount) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('No tienes suficientes Flores Coins para realizar esta transferencia.', 'floresinc-rp')
        ], 400);
    }
    
    // Obtener el usuario destinatario a partir del código de referido
    $recipient_id = floresinc_rp_get_user_by_referral_code($recipient_code);
    
    if (!$recipient_id) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('El código de referido no corresponde a ningún usuario.', 'floresinc-rp')
        ], 400);
    }
    
    // Verificar que no está intentando transferirse a sí mismo
    if ($recipient_id == $sender_id) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('No puedes transferir Flores Coins a tu propia cuenta.', 'floresinc-rp')
        ], 400);
    }
    
    // Realizar la transferencia
    global $wpdb;
    $wpdb->query('START TRANSACTION');
    
    try {
        // Reducir puntos del remitente
        $deduct_result = floresinc_rp_deduct_points($sender_id, $points_amount, 'transfer_sent', sprintf(__('Transferencia enviada a %s', 'floresinc-rp'), $recipient_code));
        
        if (!$deduct_result) {
            throw new Exception(__('Error al deducir los puntos de tu cuenta.', 'floresinc-rp'));
        }
        
        // Añadir puntos al destinatario
        $recipient_user = get_userdata($recipient_id);
        $add_result = floresinc_rp_add_points($recipient_id, $points_amount, 'transfer_received', sprintf(__('Transferencia recibida de %s', 'floresinc-rp'), get_userdata($sender_id)->user_login) . ($notes ? ': ' . $notes : ''));
        
        if (!$add_result) {
            throw new Exception(__('Error al añadir los puntos a la cuenta del destinatario.', 'floresinc-rp'));
        }
        
        $wpdb->query('COMMIT');
        
        return new WP_REST_Response([
            'success' => true,
            'message' => sprintf(__('Transferencia de %d Flores Coins realizada con éxito a %s.', 'floresinc-rp'), $points_amount, $recipient_user->user_login),
            'new_balance' => floresinc_rp_get_user_points($sender_id)['balance']
        ]);
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        
        return new WP_REST_Response([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}

/**
 * Endpoint para validar un código de referido
 * 
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function floresinc_rp_validate_referral_code_endpoint($request) {
    $code = $request->get_param('code');
    
    if (empty($code)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('El código de referido es requerido.', 'floresinc-rp')
        ], 400);
    }
    
    $user_id = floresinc_rp_get_user_by_referral_code($code);
    
    if (!$user_id) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('El código de referido no es válido.', 'floresinc-rp')
        ], 404);
    }
    
    $user = get_userdata($user_id);
    
    // Verificar que no es el propio usuario
    if ($user_id == get_current_user_id()) {
        return new WP_REST_Response([
            'success' => false,
            'message' => __('No puedes usar tu propio código de referido.', 'floresinc-rp')
        ], 400);
    }
    
    return new WP_REST_Response([
        'success' => true,
        'user' => [
            'id' => $user_id,
            'name' => $user->display_name,
            'username' => $user->user_login
        ]
    ]);
}
