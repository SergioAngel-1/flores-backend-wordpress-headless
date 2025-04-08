<?php
/**
 * API REST para el sistema de referidos
 * 
 * Funciones para los endpoints de la API REST relacionados con referidos.
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Endpoint: Obtener información de referidos del usuario actual
 */
function floresinc_rp_get_user_referrals_endpoint() {
    $user_id = get_current_user_id();
    $referrals = floresinc_rp_get_user_referrals($user_id, 'all', true);
    
    // Agrupar por nivel
    $direct_referrals = array_filter($referrals, function($ref) {
        return $ref['level'] == 1;
    });
    
    $indirect_referrals = array_filter($referrals, function($ref) {
        return $ref['level'] == 2;
    });
    
    $response = [
        'direct' => array_values($direct_referrals),
        'indirect' => array_values($indirect_referrals),
        'total_count' => count($referrals)
    ];
    
    return new WP_REST_Response($response, 200);
}

/**
 * Endpoint: Obtener estadísticas de referidos del usuario actual
 */
function floresinc_rp_get_user_referral_stats_endpoint() {
    $user_id = get_current_user_id();
    $stats = floresinc_rp_get_referral_stats($user_id);
    
    if (!$stats) {
        $stats = [
            'total_referrals' => 0,
            'total_earnings' => 0,
            'direct_referrals' => 0,
            'indirect_referrals' => 0
        ];
    }
    
    return new WP_REST_Response($stats, 200);
}

/**
 * Endpoint: Obtener el código y enlace de referido del usuario actual
 */
function floresinc_rp_get_user_referral_code_endpoint() {
    $user_id = get_current_user_id();
    $code = floresinc_rp_get_user_referral_code($user_id);
    
    if (!$code) {
        // Si el usuario no tiene código, generar uno
        $code = floresinc_rp_generate_referral_code($user_id);
    }
    
    $referral_url = home_url('?ref=' . $code);
    
    return new WP_REST_Response([
        'code' => $code,
        'url' => $referral_url
    ], 200);
}

/**
 * Endpoint: Obtener la configuración del programa de referidos
 */
function floresinc_rp_get_referral_config_endpoint() {
    $options = FloresInc_RP()->get_options();
    
    $config = [
        'signup_points' => isset($options['referral_signup_points']) ? intval($options['referral_signup_points']) : 0,
        'level1_commission' => isset($options['referral_commission_level1']) ? intval($options['referral_commission_level1']) : 0,
        'level2_commission' => isset($options['referral_commission_level2']) ? intval($options['referral_commission_level2']) : 0,
        'points_value' => isset($options['points_value']) ? floatval($options['points_value']) : 0,
        'points_per_currency' => isset($options['points_per_currency']) ? floatval($options['points_per_currency']) : 0,
        'currency_symbol' => get_woocommerce_currency_symbol()
    ];
    
    return new WP_REST_Response($config, 200);
}

/**
 * Endpoint para validar un código de referido
 * 
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function floresinc_rp_validate_referral_code_endpoint($request) {
    $params = $request->get_params();
    
    if (!isset($params['code'])) {
        return new WP_Error(
            'missing_code', 
            'Falta el parámetro de código de referido', 
            ['status' => 400]
        );
    }
    
    $code = sanitize_text_field($params['code']);
    $user_id = get_current_user_id();
    
    // Verificar si el código existe
    $referrer_id = floresinc_rp_get_user_by_referral_code($code);
    
    if (!$referrer_id) {
        return new WP_Error(
            'invalid_code', 
            'Código de referido no válido', 
            ['status' => 400]
        );
    }
    
    // Verificar que no sea el propio código del usuario
    $user_code = floresinc_rp_get_user_referral_code($user_id);
    if ($user_code && $user_code === $code) {
        return new WP_Error(
            'own_code', 
            'No puedes usar tu propio código de referido', 
            ['status' => 400]
        );
    }
    
    // Verificar que el usuario no sea ya referido por otro usuario
    global $wpdb;
    $table = $wpdb->prefix . 'floresinc_referrals';
    $existing_referrer = $wpdb->get_var($wpdb->prepare("
        SELECT referrer_id FROM $table WHERE user_id = %d AND referrer_id IS NOT NULL
    ", $user_id));
    
    // Obtener información del referente
    $referrer = get_userdata($referrer_id);
    
    $response = [
        'valid' => true,
        'referrer' => [
            'id' => $referrer_id,
            'name' => $referrer ? $referrer->display_name : 'Usuario',
        ],
        'has_existing_referrer' => !empty($existing_referrer)
    ];
    
    return new WP_REST_Response($response, 200);
}

/**
 * Obtener estadísticas de referidos
 * 
 * @param int $user_id ID del usuario
 * @return array Estadísticas de referidos
 */
function floresinc_rp_get_referral_stats($user_id) {
    if (!$user_id) {
        return false;
    }
    
    global $wpdb;
    $referrals_table = $wpdb->prefix . 'floresinc_referrals';
    $transactions_table = $wpdb->prefix . 'floresinc_points_transactions';
    
    // Obtener número de referidos directos
    $direct_referrals = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM $referrals_table WHERE referrer_id = %d
    ", $user_id));
    
    // Obtener referidos de segundo nivel (indirectos)
    $direct_users = $wpdb->get_col($wpdb->prepare("
        SELECT user_id FROM $referrals_table WHERE referrer_id = %d
    ", $user_id));
    
    $indirect_referrals = 0;
    if (!empty($direct_users)) {
        $ids_string = implode(',', array_map('intval', $direct_users));
        $indirect_referrals = $wpdb->get_var("
            SELECT COUNT(*) FROM $referrals_table WHERE referrer_id IN ($ids_string)
        ");
    }
    
    // Obtener ganancias totales por referidos
    $total_earnings = $wpdb->get_var($wpdb->prepare("
        SELECT SUM(points) FROM $transactions_table 
        WHERE user_id = %d 
        AND (type = 'referral' OR type = 'referral_signup')
    ", $user_id));
    
    // Obtener información de referidos directos (máximo 10 recientes)
    $direct_referrals_data = $wpdb->get_results($wpdb->prepare("
        SELECT r.*, u.display_name, u.user_email, u.user_registered
        FROM $referrals_table r
        JOIN {$wpdb->users} u ON r.user_id = u.ID
        WHERE r.referrer_id = %d
        ORDER BY r.signup_date DESC
        LIMIT 10
    ", $user_id), ARRAY_A);
    
    // Formatear datos para la respuesta
    if ($direct_referrals_data) {
        foreach ($direct_referrals_data as &$ref) {
            // Calcular puntos ganados por este referido
            $ref_earnings = $wpdb->get_var($wpdb->prepare("
                SELECT SUM(points) FROM $transactions_table 
                WHERE user_id = %d 
                AND (type = 'referral' OR type = 'referral_signup')
                AND related_id = %d
            ", $user_id, $ref['user_id']));
            
            $ref['earnings'] = $ref_earnings ? intval($ref_earnings) : 0;
            
            // Categorizar el estado del referido
            $ref['status'] = 'active';
            
            // Obtener primer compra
            $ref['first_purchase'] = null;
            
            // Añadir marcador de tiempo para facilitar ordenamiento
            $ref['timestamp'] = strtotime($ref['signup_date']);
        }
    }
    
    // Calcular referidos activos vs. inactivos
    $active_referrals = 0;
    $pending_referrals = 0;
    $expired_referrals = 0;
    
    if ($direct_referrals_data) {
        foreach ($direct_referrals_data as $ref) {
            if ($ref['status'] === 'active') {
                $active_referrals++;
            } elseif ($ref['status'] === 'pending') {
                $pending_referrals++;
            } else {
                $expired_referrals++;
            }
        }
    }
    
    // Obtener información de referidos indirectos (máximo 10 recientes)
    $indirect_referrals_data = [];
    if (!empty($direct_users)) {
        $ids_string = implode(',', array_map('intval', $direct_users));
        $indirect_referrals_data = $wpdb->get_results("
            SELECT r.*, u.display_name, u.user_email, u.user_registered,
                   r2.user_id as via_user_id, u2.display_name as via_name
            FROM $referrals_table r
            JOIN {$wpdb->users} u ON r.user_id = u.ID
            JOIN $referrals_table r2 ON r.referrer_id = r2.user_id
            JOIN {$wpdb->users} u2 ON r2.user_id = u2.ID
            WHERE r.referrer_id IN ($ids_string)
            ORDER BY r.signup_date DESC
            LIMIT 10
        ", ARRAY_A);
        
        // Formatear datos para la respuesta
        if ($indirect_referrals_data) {
            foreach ($indirect_referrals_data as &$ref) {
                // Calcular puntos ganados por este referido indirecto
                $ref_earnings = $wpdb->get_var($wpdb->prepare("
                    SELECT SUM(points) FROM $transactions_table 
                    WHERE user_id = %d 
                    AND type = 'referral'
                    AND related_id = %d
                ", $user_id, $ref['user_id']));
                
                $ref['earnings'] = $ref_earnings ? intval($ref_earnings) : 0;
                
                // Categorizar el estado del referido
                $ref['status'] = 'active';
                
                // Añadir marcador de tiempo para facilitar ordenamiento
                $ref['timestamp'] = strtotime($ref['signup_date']);
            }
        }
    }
    
    // Preparar respuesta
    $response = [
        'total_referrals' => $direct_referrals + $indirect_referrals,
        'total_earnings' => $total_earnings ? intval($total_earnings) : 0,
        'direct_referrals' => intval($direct_referrals),
        'indirect_referrals' => intval($indirect_referrals),
        'active_referrals' => $active_referrals,
        'pending_referrals' => $pending_referrals,
        'expired_referrals' => $expired_referrals,
        'recent_direct' => $direct_referrals_data ?: [],
        'recent_indirect' => $indirect_referrals_data ?: []
    ];
    
    return $response;
}
