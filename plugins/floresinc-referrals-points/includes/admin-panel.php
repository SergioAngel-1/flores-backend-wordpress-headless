<?php
/**
 * Panel administrativo para el sistema de referidos y puntos
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Inicializar panel administrativo
 */
function floresinc_rp_init_admin() {
    // Añadir página de administración
    add_action('admin_menu', 'floresinc_rp_admin_menu');
    
    // Registrar configuraciones
    add_action('admin_init', 'floresinc_rp_register_settings');
    
    // Añadir campos personalizados en el perfil de usuario
    add_action('show_user_profile', 'floresinc_rp_user_profile_fields');
    add_action('edit_user_profile', 'floresinc_rp_user_profile_fields');
    
    // Guardar campos personalizados del perfil de usuario
    add_action('personal_options_update', 'floresinc_rp_save_user_profile_fields');
    add_action('edit_user_profile_update', 'floresinc_rp_save_user_profile_fields');
    
    // Columna de puntos en la tabla de usuarios
    add_filter('manage_users_columns', 'floresinc_rp_modify_user_table');
    add_filter('manage_users_custom_column', 'floresinc_rp_modify_user_table_row', 10, 3);
    add_filter('manage_users_sortable_columns', 'floresinc_rp_sortable_columns');
    
    // Columna de puntos en los pedidos
    add_filter('manage_edit-shop_order_columns', 'floresinc_rp_order_points_column');
    add_action('manage_shop_order_posts_custom_column', 'floresinc_rp_order_points_column_content', 10, 2);
}

/**
 * Añadir menú de administración
 */
function floresinc_rp_admin_menu() {
    add_menu_page(
        __('Referidos y Puntos', 'floresinc-rp'),
        __('Referidos y Puntos', 'floresinc-rp'),
        'manage_woocommerce',
        'floresinc-referrals-points',
        'floresinc_rp_admin_page',
        'dashicons-groups',
        56
    );
    
    // Submenús
    add_submenu_page(
        'floresinc-referrals-points',
        __('Panel de Control', 'floresinc-rp'),
        __('Panel de Control', 'floresinc-rp'),
        'manage_woocommerce',
        'floresinc-referrals-points',
        'floresinc_rp_admin_page'
    );
    
    add_submenu_page(
        'floresinc-referrals-points',
        __('Configuración', 'floresinc-rp'),
        __('Configuración', 'floresinc-rp'),
        'manage_options',
        'floresinc-rp-settings',
        'floresinc_rp_settings_page'
    );
    
    add_submenu_page(
        'floresinc-referrals-points',
        __('Transacciones', 'floresinc-rp'),
        __('Transacciones', 'floresinc-rp'),
        'manage_woocommerce',
        'floresinc-rp-transactions',
        'floresinc_rp_transactions_page'
    );
    
    add_submenu_page(
        'floresinc-referrals-points',
        __('Red de Referidos', 'floresinc-rp'),
        __('Red de Referidos', 'floresinc-rp'),
        'manage_woocommerce',
        'floresinc-rp-network',
        'floresinc_rp_network_page'
    );
}

/**
 * Registrar configuraciones
 */
function floresinc_rp_register_settings() {
    register_setting('floresinc_rp_settings', 'floresinc_referrals_points_options');
    
    add_settings_section(
        'floresinc_rp_general',
        __('Configuración General', 'floresinc-rp'),
        'floresinc_rp_general_section_callback',
        'floresinc_rp_settings'
    );
    
    add_settings_section(
        'floresinc_rp_referrals',
        __('Configuración de Referidos', 'floresinc-rp'),
        'floresinc_rp_referrals_section_callback',
        'floresinc_rp_settings'
    );
    
    add_settings_section(
        'floresinc_rp_points',
        __('Configuración de Puntos', 'floresinc-rp'),
        'floresinc_rp_points_section_callback',
        'floresinc_rp_settings'
    );
    
    // Campos generales
    add_settings_field(
        'floresinc_rp_enabled',
        __('Estado del Sistema', 'floresinc-rp'),
        'floresinc_rp_field_enabled_callback',
        'floresinc_rp_settings',
        'floresinc_rp_general'
    );
    
    // Campos de referidos
    add_settings_field(
        'floresinc_rp_commission_level1',
        __('Comisión Nivel 1 (%)', 'floresinc-rp'),
        'floresinc_rp_field_commission_level1_callback',
        'floresinc_rp_settings',
        'floresinc_rp_referrals'
    );
    
    add_settings_field(
        'floresinc_rp_commission_level2',
        __('Comisión Nivel 2 (%)', 'floresinc-rp'),
        'floresinc_rp_field_commission_level2_callback',
        'floresinc_rp_settings',
        'floresinc_rp_referrals'
    );
    
    add_settings_field(
        'floresinc_rp_referral_signup_points',
        __('Puntos por Nuevo Referido', 'floresinc-rp'),
        'floresinc_rp_field_referral_signup_points_callback',
        'floresinc_rp_settings',
        'floresinc_rp_referrals'
    );
    
    // Campos de puntos
    add_settings_field(
        'floresinc_rp_points_per_currency',
        __('Puntos por Unidad de Moneda', 'floresinc-rp'),
        'floresinc_rp_field_points_per_currency_callback',
        'floresinc_rp_settings',
        'floresinc_rp_points'
    );
    
    add_settings_field(
        'floresinc_rp_points_conversion_rate',
        __('Tasa de Conversión (puntos por $1)', 'floresinc-rp'),
        'floresinc_rp_field_points_conversion_rate_callback',
        'floresinc_rp_settings',
        'floresinc_rp_points'
    );
    
    add_settings_field(
        'floresinc_rp_points_expiration_days',
        __('Días para Expiración de Puntos', 'floresinc-rp'),
        'floresinc_rp_field_points_expiration_days_callback',
        'floresinc_rp_settings',
        'floresinc_rp_points'
    );
}

/**
 * Callback para la sección general
 */
function floresinc_rp_general_section_callback() {
    echo '<p>' . __('Configuración general del sistema de referidos y puntos.', 'floresinc-rp') . '</p>';
}

/**
 * Callback para la sección de referidos
 */
function floresinc_rp_referrals_section_callback() {
    echo '<p>' . __('Configura el sistema de referidos y comisiones.', 'floresinc-rp') . '</p>';
}

/**
 * Callback para la sección de puntos
 */
function floresinc_rp_points_section_callback() {
    echo '<p>' . __('Configura el sistema de puntos y recompensas.', 'floresinc-rp') . '</p>';
}

/**
 * Callback para el campo de estado
 */
function floresinc_rp_field_enabled_callback() {
    $options = FloresInc_RP()->get_options();
    $enabled = isset($options['enabled']) ? $options['enabled'] : true;
    
    echo '<label><input type="checkbox" name="floresinc_rp_options[enabled]" value="1" ' . checked(1, $enabled, false) . '/>';
    echo ' ' . __('Activar sistema de referidos y puntos', 'floresinc-rp') . '</label>';
}

/**
 * Callback para comisión nivel 1
 */
function floresinc_rp_field_commission_level1_callback() {
    $options = FloresInc_RP()->get_options();
    $commission = isset($options['referral_commission_level1']) ? $options['referral_commission_level1'] : 10;
    
    echo '<input type="number" min="0" max="100" step="1" name="floresinc_rp_options[referral_commission_level1]" value="' . esc_attr($commission) . '" class="small-text" />';
    echo ' <span class="description">' . __('Porcentaje de puntos que recibe el referidor directo.', 'floresinc-rp') . '</span>';
}

/**
 * Callback para comisión nivel 2
 */
function floresinc_rp_field_commission_level2_callback() {
    $options = FloresInc_RP()->get_options();
    $commission = isset($options['referral_commission_level2']) ? $options['referral_commission_level2'] : 5;
    
    echo '<input type="number" min="0" max="100" step="1" name="floresinc_rp_options[referral_commission_level2]" value="' . esc_attr($commission) . '" class="small-text" />';
    echo ' <span class="description">' . __('Porcentaje de puntos que recibe el referidor indirecto.', 'floresinc-rp') . '</span>';
}

/**
 * Callback para puntos por unidad de moneda
 */
function floresinc_rp_field_points_per_currency_callback() {
    $options = FloresInc_RP()->get_options();
    $points = isset($options['points_per_currency']) ? $options['points_per_currency'] : 10;
    
    echo '<input type="number" min="0.1" step="0.1" name="floresinc_referrals_points_options[points_per_currency]" value="' . esc_attr($points) . '" class="small-text" />';
    echo ' <span class="description">' . __('Cuántos puntos dar por cada unidad de moneda gastada.', 'floresinc-rp') . '</span>';
}

/**
 * Callback para tasa de conversión
 */
function floresinc_rp_field_points_conversion_rate_callback() {
    $options = FloresInc_RP()->get_options();
    $rate = isset($options['points_conversion_rate']) ? $options['points_conversion_rate'] : 100;
    
    echo '<input type="number" min="1" step="1" name="floresinc_referrals_points_options[points_conversion_rate]" value="' . esc_attr($rate) . '" class="small-text" />';
    echo ' <span class="description">' . __('Cuántos puntos equivalen a 1 unidad de moneda.', 'floresinc-rp') . '</span>';
}

/**
 * Callback para días de expiración
 */
function floresinc_rp_field_points_expiration_days_callback() {
    $options = FloresInc_RP()->get_options();
    $days = isset($options['points_expiration_days']) ? $options['points_expiration_days'] : 365;
    
    echo '<input type="number" min="0" step="1" name="floresinc_referrals_points_options[points_expiration_days]" value="' . esc_attr($days) . '" class="small-text" />';
    echo ' <span class="description">' . __('Días hasta que expiran los puntos (0 = sin expiración).', 'floresinc-rp') . '</span>';
}

/**
 * Callback para el campo de puntos por nuevo referido
 */
function floresinc_rp_field_referral_signup_points_callback() {
    $options = FloresInc_RP()->get_options();
    $value = isset($options['referral_signup_points']) ? $options['referral_signup_points'] : 100;
    ?>
    <input type="number" name="floresinc_rp_options[referral_signup_points]" value="<?php echo esc_attr($value); ?>" min="0" step="1" />
    <p class="description">
        <?php _e('Cantidad de puntos que recibe un usuario cuando uno de sus referidos es aprobado.', 'floresinc-rp'); ?>
    </p>
    <?php
}

/**
 * Página principal de administración
 */
function floresinc_rp_admin_page() {
    global $wpdb;
    
    // Estadísticas
    $users_table = $wpdb->prefix . 'users';
    $points_table = $wpdb->prefix . 'floresinc_user_points';
    $transactions_table = $wpdb->prefix . 'floresinc_points_transactions';
    $referrals_table = $wpdb->prefix . 'floresinc_referrals';
    
    // Total de usuarios con puntos
    $users_with_points = $wpdb->get_var("
        SELECT COUNT(DISTINCT user_id) FROM $points_table
    ");
    
    // Total de puntos activos
    $total_active_points = $wpdb->get_var("
        SELECT SUM(balance) FROM $points_table
    ");
    
    // Total de transacciones
    $total_transactions = $wpdb->get_var("
        SELECT COUNT(*) FROM $transactions_table
    ");
    
    // Total de referidos
    $total_referrals = $wpdb->get_var("
        SELECT COUNT(*) FROM $referrals_table WHERE referrer_id IS NOT NULL
    ");
    
    // Últimas transacciones
    $recent_transactions = $wpdb->get_results("
        SELECT t.*, u.display_name 
        FROM $transactions_table t
        JOIN $users_table u ON t.user_id = u.ID
        ORDER BY t.created_at DESC
        LIMIT 10
    ");
    
    // Top usuarios por puntos
    $top_users = $wpdb->get_results("
        SELECT p.user_id, p.balance, u.display_name, u.user_email
        FROM $points_table p
        JOIN $users_table u ON p.user_id = u.ID
        ORDER BY p.balance DESC
        LIMIT 10
    ");
    
    // Top referidores
    $top_referrers = $wpdb->get_results("
        SELECT r.referrer_id, COUNT(*) as total_referrals, u.display_name
        FROM $referrals_table r
        JOIN $users_table u ON r.referrer_id = u.ID
        WHERE r.referrer_id IS NOT NULL
        GROUP BY r.referrer_id
        ORDER BY total_referrals DESC
        LIMIT 10
    ");
    
    // Incluir vista
    include(plugin_dir_path(dirname(__FILE__)) . 'views/admin-dashboard.php');
}

/**
 * Página de configuración
 */
function floresinc_rp_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('floresinc_rp_settings');
            do_settings_sections('floresinc_rp_settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

/**
 * Página de transacciones
 */
function floresinc_rp_transactions_page() {
    // Incluir clase de listado de tabla WP
    if (!class_exists('WP_List_Table')) {
        require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
    }
    
    // Incluir clase de tabla de transacciones
    require_once(plugin_dir_path(dirname(__FILE__)) . 'includes/class-transactions-table.php');
    
    // Crear instancia de la tabla
    $transactions_table = new FloresInc_RP_Transactions_Table();
    
    // Preparar y mostrar la tabla
    $transactions_table->prepare_items();
    
    ?>
    <div class="wrap">
        <h1><?php _e('Transacciones de Puntos', 'floresinc-rp'); ?></h1>
        
        <form method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
            <?php $transactions_table->search_box(__('Buscar', 'floresinc-rp'), 'floresinc-search'); ?>
            <?php $transactions_table->display(); ?>
        </form>
    </div>
    <?php
}

/**
 * Página de red de referidos
 */
function floresinc_rp_network_page() {
    // Incluir clase de listado de tabla WP
    if (!class_exists('WP_List_Table')) {
        require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
    }
    
    // Incluir clase de tabla de referidos
    require_once(plugin_dir_path(dirname(__FILE__)) . 'includes/class-referrals-table.php');
    
    // Crear instancia de la tabla
    $referrals_table = new FloresInc_RP_Referrals_Table();
    
    // Preparar y mostrar la tabla
    $referrals_table->prepare_items();
    
    ?>
    <div class="wrap">
        <h1><?php _e('Red de Referidos', 'floresinc-rp'); ?></h1>
        
        <form method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
            <?php $referrals_table->search_box(__('Buscar', 'floresinc-rp'), 'floresinc-search'); ?>
            <?php $referrals_table->display(); ?>
        </form>
    </div>
    <?php
}

/**
 * Añadir campos personalizados al perfil de usuario
 */
function floresinc_rp_user_profile_fields($user) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $user_id = $user->ID;
    $user_points = floresinc_rp_get_user_points($user_id);
    $referral_code = floresinc_rp_get_user_referral_code($user_id);
    $referrals = floresinc_rp_get_user_referrals($user_id);
    $referrer = floresinc_rp_get_user_referrer($user_id);
    
    ?>
    <h2><?php _e('Referidos y Puntos', 'floresinc-rp'); ?></h2>
    <table class="form-table">
        <tr>
            <th><label for="floresinc_referral_code"><?php _e('Código de Referido', 'floresinc-rp'); ?></label></th>
            <td>
                <input type="text" name="floresinc_referral_code" id="floresinc_referral_code" 
                       value="<?php echo esc_attr($referral_code); ?>" class="regular-text" />
                <p class="description"><?php _e('Código único de referido para este usuario.', 'floresinc-rp'); ?></p>
            </td>
        </tr>
        <tr>
            <th><label for="floresinc_points_balance"><?php _e('Saldo de Puntos', 'floresinc-rp'); ?></label></th>
            <td>
                <input type="number" name="floresinc_points_balance" id="floresinc_points_balance" 
                       value="<?php echo $user_points ? esc_attr($user_points['balance']) : '0'; ?>" class="regular-text" />
                <p class="description"><?php _e('Saldo actual de puntos del usuario.', 'floresinc-rp'); ?></p>
            </td>
        </tr>
        <?php if ($referrer) : ?>
        <tr>
            <th><?php _e('Referido por', 'floresinc-rp'); ?></th>
            <td>
                <?php
                $referrer_user = get_userdata($referrer);
                echo $referrer_user ? esc_html($referrer_user->display_name) . ' (' . esc_html($referrer_user->user_email) . ')' : __('Usuario no encontrado', 'floresinc-rp');
                ?>
            </td>
        </tr>
        <?php endif; ?>
        <tr>
            <th><?php _e('Total de Referidos', 'floresinc-rp'); ?></th>
            <td>
                <?php echo count($referrals); ?>
                <?php if (!empty($referrals)) : ?>
                <a href="<?php echo admin_url('admin.php?page=floresinc-rp-network&referrer=' . $user_id); ?>" class="button button-secondary">
                    <?php _e('Ver Referidos', 'floresinc-rp'); ?>
                </a>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * Guardar campos personalizados del perfil de usuario
 */
function floresinc_rp_save_user_profile_fields($user_id) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Guardar código de referido
    if (isset($_POST['floresinc_referral_code']) && !empty($_POST['floresinc_referral_code'])) {
        $new_code = sanitize_text_field($_POST['floresinc_referral_code']);
        $old_code = floresinc_rp_get_user_referral_code($user_id);
        
        if ($new_code !== $old_code) {
            global $wpdb;
            $table = $wpdb->prefix . 'floresinc_referrals';
            
            // Actualizar el código en la base de datos
            $wpdb->update(
                $table,
                ['referral_code' => $new_code],
                ['user_id' => $user_id],
                ['%s'],
                ['%d']
            );
        }
    }
    
    // Guardar saldo de puntos
    if (isset($_POST['floresinc_points_balance'])) {
        $new_balance = intval($_POST['floresinc_points_balance']);
        $user_points = floresinc_rp_get_user_points($user_id);
        
        if ($user_points && $new_balance !== $user_points['balance']) {
            $difference = $new_balance - $user_points['balance'];
            
            if ($difference != 0) {
                // Determinar tipo de transacción
                $type = $difference > 0 ? 'admin_add' : 'admin_deduct';
                
                // Crear descripción
                $description = $difference > 0 ? 
                    __('Ajuste administrativo (añadido)', 'floresinc-rp') :
                    __('Ajuste administrativo (deducido)', 'floresinc-rp');
                
                // Añadir transacción
                floresinc_rp_add_transaction(
                    $user_id,
                    abs($difference),
                    $type,
                    $description
                );
                
                // Actualizar saldo
                global $wpdb;
                $table = $wpdb->prefix . 'floresinc_user_points';
                
                $wpdb->update(
                    $table,
                    ['balance' => $new_balance],
                    ['user_id' => $user_id],
                    ['%d'],
                    ['%d']
                );
            }
        }
    }
}

/**
 * Añadir columna de puntos a la tabla de usuarios
 */
function floresinc_rp_modify_user_table($columns) {
    $columns['floresinc_points'] = __('Puntos', 'floresinc-rp');
    return $columns;
}

/**
 * Mostrar datos de puntos en la columna de usuarios
 */
function floresinc_rp_modify_user_table_row($val, $column_name, $user_id) {
    if ($column_name === 'floresinc_points') {
        $user_points = floresinc_rp_get_user_points($user_id);
        
        if ($user_points) {
            return '<strong>' . $user_points['balance'] . '</strong>';
        } else {
            return '0';
        }
    }
    
    return $val;
}

/**
 * Hacer la columna de puntos ordenable
 */
function floresinc_rp_sortable_columns($columns) {
    $columns['floresinc_points'] = 'floresinc_points';
    return $columns;
}

/**
 * Añadir columna de puntos a la tabla de pedidos
 */
function floresinc_rp_order_points_column($columns) {
    $new_columns = [];
    
    foreach ($columns as $key => $column) {
        $new_columns[$key] = $column;
        
        if ($key === 'order_status') {
            $new_columns['floresinc_order_points'] = __('Puntos', 'floresinc-rp');
        }
    }
    
    return $new_columns;
}

/**
 * Mostrar datos de puntos en la columna de pedidos
 */
function floresinc_rp_order_points_column_content($column, $order_id) {
    if ($column === 'floresinc_order_points') {
        $points_earned = get_post_meta($order_id, '_floresinc_points_earned', true);
        $points_used = get_post_meta($order_id, '_floresinc_points_used', true);
        
        if ($points_earned) {
            echo '<span style="color: green;">+' . $points_earned . '</span>';
        }
        
        if ($points_used) {
            echo '<br><span style="color: red;">-' . $points_used . '</span>';
        }
        
        if (!$points_earned && !$points_used) {
            echo '-';
        }
    }
}
