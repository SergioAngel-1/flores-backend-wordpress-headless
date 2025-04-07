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
    
    // Ruta para obtener la configuración del programa de referidos
    register_rest_route($namespace, '/referrals/config', [
        'methods' => 'GET',
        'callback' => 'floresinc_rp_get_referral_config_endpoint',
        'permission_callback' => '__return_true'
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
    $points_per_currency = $options['points_per_currency'] ?? 1;
    
    // Asegurarnos de que points_per_currency sea un número (puede ser decimal)
    $points_per_currency = floatval($points_per_currency);
    
    // Calcular el valor monetario basado en points_per_currency
    // Si points_per_currency = 0.1, significa que cada punto vale 0.1 unidades monetarias
    // Por lo tanto, multiplicamos el balance por points_per_currency
    $points_value = $user_points['balance'] * $points_per_currency;
    
    // Registrar valores para depuración
    error_log(sprintf(
        'Calculando valor monetario: Balance=%s, ConversionRate=%s, PointsPerCurrency=%s, Valor=%s',
        $user_points['balance'],
        $conversion_rate,
        $points_per_currency,
        $points_value
    ));
    
    $response = [
        'balance' => $user_points['balance'],
        'total_earned' => $user_points['total_earned'],
        'used' => $user_points['used'],
        'monetary_value' => $points_value,
        'conversion_rate' => $conversion_rate,
        'points_per_currency' => $points_per_currency
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
        'total_points_generated' => $stats['total_points_generated'],
        'points_from_level1' => $stats['points_from_level1'] ?? 0,
        'points_from_level2' => $stats['points_from_level2'] ?? 0
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
    
    // Total de Flores Coins generados - NUEVA LÓGICA CON DIFERENCIACIÓN DE NIVELES
    // Obtener las opciones del plugin para saber cuántos puntos se dan por registro en cada nivel
    $options = get_option('floresinc_rp_settings', array());
    $points_level1 = isset($options['signup_points_level1']) ? intval($options['signup_points_level1']) : 100;
    $points_level2 = isset($options['signup_points_level2']) ? intval($options['signup_points_level2']) : 50;
    
    // Inicializar contadores
    $total_points_generated = 0;
    $points_from_level1 = 0;
    $points_from_level2 = 0;
    $count_level1 = count($direct_referral_ids);
    $count_level2 = 0;
    
    // Calcular puntos por referidos de primer nivel
    if (!empty($direct_referral_ids)) {
        $points_from_level1 = $count_level1 * $points_level1;
        
        // Obtener referidos de segundo nivel
        $direct_ids_string = implode(',', array_map('intval', $direct_referral_ids));
        $indirect_referral_ids = $wpdb->get_col("SELECT user_id FROM $referrals_table WHERE referrer_id IN ($direct_ids_string)");
        
        // Calcular puntos por referidos de segundo nivel
        if (!empty($indirect_referral_ids)) {
            $count_level2 = count($indirect_referral_ids);
            $points_from_level2 = $count_level2 * $points_level2;
        }
    }
    
    // Sumar los puntos de ambos niveles
    $total_points_generated = $points_from_level1 + $points_from_level2;
    
    // Registrar para depuración
    error_log("Puntos generados por referidos de primer nivel: $points_from_level1 ($count_level1 referidos x $points_level1 puntos)");
    error_log("Puntos generados por referidos de segundo nivel: $points_from_level2 ($count_level2 referidos x $points_level2 puntos)");
    error_log("Total de Flores Coins generados por referidos para usuario ID $user_id: $total_points_generated");
    
    return [
        'total_referrals' => $direct_referrals + $indirect_referrals,
        'direct_referrals' => $direct_referrals,
        'indirect_referrals' => $indirect_referrals,
        'total_points_generated' => $total_points_generated ? $total_points_generated : 0,
        'points_from_level1' => $points_from_level1,
        'points_from_level2' => $points_from_level2
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

/**
 * Endpoint: Obtener la configuración del programa de referidos
 */
function floresinc_rp_get_referral_config_endpoint() {
    $options = get_option('floresinc_rp_settings', array());
    
    // Valores por defecto si no están configurados
    $points_level1 = isset($options['signup_points_level1']) ? intval($options['signup_points_level1']) : 100;
    $points_level2 = isset($options['signup_points_level2']) ? intval($options['signup_points_level2']) : 50;
    $first_level_commission = isset($options['first_level_commission']) ? floatval($options['first_level_commission']) : 5;
    $second_level_commission = isset($options['second_level_commission']) ? floatval($options['second_level_commission']) : 2;
    
    $response = [
        'points_level1' => $points_level1,
        'points_level2' => $points_level2,
        'first_level_commission' => $first_level_commission,
        'second_level_commission' => $second_level_commission
    ];
    
    return new WP_REST_Response($response, 200);
}
