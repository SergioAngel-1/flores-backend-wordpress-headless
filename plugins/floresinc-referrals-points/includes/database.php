<?php
/**
 * Funciones de base de datos para el sistema de referidos y puntos
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Crear tablas necesarias para el plugin
 */
function floresinc_rp_create_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Tabla para los puntos de los usuarios
    $table_points = $wpdb->prefix . 'floresinc_user_points';
    $sql_points = "CREATE TABLE IF NOT EXISTS $table_points (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        points int(11) NOT NULL DEFAULT 0,
        points_used int(11) NOT NULL DEFAULT 0,
        last_update datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY user_id (user_id)
    ) $charset_collate;";
    
    // Tabla para transacciones de puntos
    $table_transactions = $wpdb->prefix . 'floresinc_points_transactions';
    $sql_transactions = "CREATE TABLE IF NOT EXISTS $table_transactions (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        points int(11) NOT NULL DEFAULT 0,
        type varchar(50) NOT NULL,
        description text NOT NULL,
        reference_id bigint(20) DEFAULT NULL,
        expiration_date datetime DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY type (type),
        KEY expiration_date (expiration_date)
    ) $charset_collate;";
    
    // Tabla para relaciones de referidos
    $table_referrals = $wpdb->prefix . 'floresinc_referrals';
    $sql_referrals = "CREATE TABLE IF NOT EXISTS $table_referrals (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        referrer_id bigint(20) DEFAULT NULL,
        referral_code varchar(20) NOT NULL,
        signup_date datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY referral_code (referral_code),
        UNIQUE KEY user_id (user_id),
        KEY referrer_id (referrer_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_points);
    dbDelta($sql_transactions);
    dbDelta($sql_referrals);
    
    // Log de creación de tablas
    error_log('Tablas FloresInc Referrals & Points creadas');
}

/**
 * Obtener balance de puntos de un usuario
 */
function floresinc_rp_get_user_points($user_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'floresinc_user_points';
    $result = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM $table WHERE user_id = %d
    ", $user_id));
    
    if (!$result) {
        // Crear registro si no existe
        $wpdb->insert($table, [
            'user_id' => $user_id,
            'points' => 0,
            'points_used' => 0,
            'last_update' => current_time('mysql')
        ]);
        
        return [
            'balance' => 0,
            'used' => 0,
            'total_earned' => 0,
            'last_update' => current_time('mysql')
        ];
    }
    
    // Calcular total ganado (incluir todas las transacciones positivas)
    $transactions_table = $wpdb->prefix . 'floresinc_points_transactions';
    $total_earned = $wpdb->get_var($wpdb->prepare("
        SELECT SUM(points) FROM $transactions_table 
        WHERE user_id = %d AND points > 0 AND type NOT IN ('used', 'expired', 'transferred_out')
    ", $user_id));
    
    return [
        'balance' => (int) $result->points,
        'used' => (int) $result->points_used,
        'total_earned' => (int) $total_earned,
        'last_update' => $result->last_update
    ];
}

/**
 * Añadir puntos a un usuario
 */
function floresinc_rp_add_points($user_id, $points, $type, $description, $reference_id = null, $expiration_days = null) {
    global $wpdb;
    
    if ($points <= 0) {
        return false;
    }
    
    // Actualizar balance
    $points_table = $wpdb->prefix . 'floresinc_user_points';
    $user_exists = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM $points_table WHERE user_id = %d
    ", $user_id));
    
    if ($user_exists) {
        $wpdb->query($wpdb->prepare("
            UPDATE $points_table SET 
                points = points + %d,
                last_update = %s
            WHERE user_id = %d
        ", $points, current_time('mysql'), $user_id));
    } else {
        $wpdb->insert($points_table, [
            'user_id' => $user_id,
            'points' => $points,
            'points_used' => 0,
            'last_update' => current_time('mysql')
        ]);
    }
    
    // Calcular fecha de expiración
    $expiration_date = null;
    if ($expiration_days) {
        $expiration_date = date('Y-m-d H:i:s', strtotime("+{$expiration_days} days"));
    }
    
    // Registrar transacción
    $transactions_table = $wpdb->prefix . 'floresinc_points_transactions';
    $result = $wpdb->insert($transactions_table, [
        'user_id' => $user_id,
        'points' => $points,
        'type' => $type,
        'description' => $description,
        'reference_id' => $reference_id,
        'expiration_date' => $expiration_date,
        'created_at' => current_time('mysql')
    ]);
    
    if ($result) {
        do_action('floresinc_rp_points_added', $user_id, $points, $type, $description);
        return true;
    }
    
    return false;
}

/**
 * Deducir puntos a un usuario
 * 
 * @param int $user_id ID del usuario
 * @param int $points Cantidad de puntos a deducir (número positivo)
 * @param string $type Tipo de transacción
 * @param string $description Descripción de la transacción
 * @param int|null $reference_id ID de referencia (pedido, producto, etc.)
 * @return bool True si se deducen correctamente, false en caso contrario
 */
function floresinc_rp_deduct_points($user_id, $points, $type, $description, $reference_id = null) {
    global $wpdb;
    
    if ($points <= 0) {
        return false;
    }
    
    // Verificar que el usuario tiene suficientes puntos
    $user_points = floresinc_rp_get_user_points($user_id);
    if ($user_points['balance'] < $points) {
        return false;
    }
    
    // Actualizar balance
    $points_table = $wpdb->prefix . 'floresinc_user_points';
    $result = $wpdb->query($wpdb->prepare("
        UPDATE $points_table SET 
            points = points - %d,
            points_used = points_used + %d,
            last_update = %s
        WHERE user_id = %d
    ", $points, $points, current_time('mysql'), $user_id));
    
    if (!$result) {
        error_log("Error al actualizar puntos para el usuario $user_id: " . $wpdb->last_error);
        return false;
    }
    
    // Registrar transacción (con valor negativo para indicar deducción)
    $transactions_table = $wpdb->prefix . 'floresinc_points_transactions';
    $result = $wpdb->insert($transactions_table, [
        'user_id' => $user_id,
        'points' => -$points, // Valor negativo para indicar deducción
        'type' => $type,
        'description' => $description,
        'reference_id' => $reference_id,
        'expiration_date' => null, // No hay expiración para deducciones
        'created_at' => current_time('mysql')
    ]);
    
    if ($result) {
        do_action('floresinc_rp_points_deducted', $user_id, $points, $type, $description);
        return true;
    }
    
    return false;
}

/**
 * Usar puntos de un usuario
 */
function floresinc_rp_use_points($user_id, $points, $description, $reference_id = null) {
    global $wpdb;
    
    if ($points <= 0) {
        return false;
    }
    
    // Verificar balance
    $user_points = floresinc_rp_get_user_points($user_id);
    if ($user_points['balance'] < $points) {
        return false;
    }
    
    // Actualizar balance
    $points_table = $wpdb->prefix . 'floresinc_user_points';
    $wpdb->query($wpdb->prepare("
        UPDATE $points_table SET 
            points = points - %d,
            points_used = points_used + %d,
            last_update = %s
        WHERE user_id = %d
    ", $points, $points, current_time('mysql'), $user_id));
    
    // Registrar transacción
    $transactions_table = $wpdb->prefix . 'floresinc_points_transactions';
    $result = $wpdb->insert($transactions_table, [
        'user_id' => $user_id,
        'points' => -$points, // Negativo porque se están usando
        'type' => 'used',
        'description' => $description,
        'reference_id' => $reference_id,
        'created_at' => current_time('mysql')
    ]);
    
    if ($result) {
        do_action('floresinc_rp_points_used', $user_id, $points, $description);
        return true;
    }
    
    return false;
}

/**
 * Obtener transacciones de puntos de un usuario
 */
function floresinc_rp_get_user_transactions($user_id, $limit = 20, $offset = 0) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'floresinc_points_transactions';
    $results = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $table 
        WHERE user_id = %d
        ORDER BY created_at DESC
        LIMIT %d OFFSET %d
    ", $user_id, $limit, $offset));
    
    return $results;
}

/**
 * Obtener el total de transacciones de un usuario
 */
function floresinc_rp_get_user_transactions_count($user_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'floresinc_points_transactions';
    return $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM $table WHERE user_id = %d
    ", $user_id));
}

/**
 * Procesar la expiración de puntos
 */
function floresinc_rp_process_points_expiration() {
    global $wpdb;
    
    $transactions_table = $wpdb->prefix . 'floresinc_points_transactions';
    $points_table = $wpdb->prefix . 'floresinc_user_points';
    
    // Encontrar transacciones expiradas que no han sido procesadas
    $expired_transactions = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $transactions_table
        WHERE type = 'earned' 
        AND expiration_date IS NOT NULL
        AND expiration_date < %s
        AND points > 0
    ", current_time('mysql')));
    
    foreach ($expired_transactions as $transaction) {
        // Verificar si hay suficientes puntos para expirar
        $user_points = floresinc_rp_get_user_points($transaction->user_id);
        $points_to_expire = min($transaction->points, $user_points['balance']);
        
        if ($points_to_expire <= 0) {
            continue;
        }
        
        // Actualizar balance
        $wpdb->query($wpdb->prepare("
            UPDATE $points_table SET 
                points = points - %d,
                last_update = %s
            WHERE user_id = %d
        ", $points_to_expire, current_time('mysql'), $transaction->user_id));
        
        // Registrar transacción de expiración
        $wpdb->insert($transactions_table, [
            'user_id' => $transaction->user_id,
            'points' => -$points_to_expire,
            'type' => 'expired',
            'description' => 'Puntos expirados',
            'reference_id' => $transaction->id,
            'created_at' => current_time('mysql')
        ]);
        
        // Marcar transacción original como expirada (actualizar puntos a 0)
        $wpdb->update(
            $transactions_table,
            ['points' => 0],
            ['id' => $transaction->id]
        );
        
        do_action('floresinc_rp_points_expired', $transaction->user_id, $points_to_expire);
    }
}
