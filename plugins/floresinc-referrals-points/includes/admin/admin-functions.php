<?php
/**
 * Funciones administrativas para el plugin de Referidos y Puntos
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Inicializar funcionalidades de administración
 */
function floresinc_rp_init_admin() {
    add_action('admin_menu', 'floresinc_rp_register_admin_menu');
    // Comentado para evitar conflicto con admin-panel.php que ya registra configuraciones
    // add_action('admin_init', 'floresinc_rp_register_settings');
    add_action('admin_init', 'floresinc_rp_process_admin_points_assignment');
    add_action('admin_footer', 'floresinc_rp_admin_footer_scripts');
    
    // Cargar estilos CSS para el panel de administración
    add_action('admin_enqueue_scripts', 'floresinc_rp_admin_styles');
    
    // Filtros para añadir columna de puntos en la lista de usuarios
    add_filter('manage_users_columns', 'floresinc_rp_add_user_columns');
    add_filter('manage_users_custom_column', 'floresinc_rp_user_column_content', 10, 3);
    add_filter('manage_users_sortable_columns', 'floresinc_rp_user_sortable_columns');
    
    // Acciones para usuarios
    add_action('show_user_profile', 'floresinc_rp_add_user_profile_fields');
    add_action('edit_user_profile', 'floresinc_rp_add_user_profile_fields');
    add_action('personal_options_update', 'floresinc_rp_save_user_profile_fields');
    add_action('edit_user_profile_update', 'floresinc_rp_save_user_profile_fields');
}

/**
 * Cargar estilos CSS para el panel de administración
 */
function floresinc_rp_admin_styles($hook) {
    // Páginas donde cargar los estilos
    $pages = array(
        'toplevel_page_floresinc-rp-dashboard',
        'referidos-y-puntos_page_floresinc-rp-transactions',
        'referidos-y-puntos_page_floresinc-rp-network',
        'referidos-y-puntos_page_floresinc-rp-settings'
    );
    
    // Cargar estilos solo en las páginas del plugin
    if (in_array($hook, $pages)) {
        wp_enqueue_style(
            'floresinc-rp-admin-tabs', 
            plugins_url('/assets/css/plugin.css', dirname(dirname(__FILE__))),
            array(),
            FLORESINC_RP_VERSION
        );
    }
}

/**
 * Registrar menús de administración
 */
function floresinc_rp_register_admin_menu() {
    // Menú principal
    add_menu_page(
        __('Referidos y Puntos', 'floresinc-rp'),
        __('Referidos y Puntos', 'floresinc-rp'),
        'manage_woocommerce',
        'floresinc-rp-dashboard',
        'floresinc_rp_dashboard_page',
        'dashicons-chart-area',
        56
    );
    
    // Submenús
    add_submenu_page(
        'floresinc-rp-dashboard',
        __('Dashboard de Referidos y Puntos', 'floresinc-rp'),
        __('Dashboard', 'floresinc-rp'),
        'manage_woocommerce',
        'floresinc-rp-dashboard',
        'floresinc_rp_dashboard_page'
    );
    
    add_submenu_page(
        'floresinc-rp-dashboard',
        __('Transacciones de Puntos', 'floresinc-rp'),
        __('Transacciones', 'floresinc-rp'),
        'manage_woocommerce',
        'floresinc-rp-transactions',
        'floresinc_rp_transactions_page'
    );
    
    add_submenu_page(
        'floresinc-rp-dashboard',
        __('Red de Referidos', 'floresinc-rp'),
        __('Red de Referidos', 'floresinc-rp'),
        'manage_woocommerce',
        'floresinc-rp-network',
        'floresinc_rp_network_page'
    );
    
    add_submenu_page(
        'floresinc-rp-dashboard',
        __('Configuración de Referidos y Puntos', 'floresinc-rp'),
        __('Configuración', 'floresinc-rp'),
        'manage_options',
        'floresinc-rp-settings',
        'floresinc_rp_settings_page'
    );
}

/**
 * Página de dashboard
 */
function floresinc_rp_dashboard_page() {
    // Verificar permisos
    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('No tienes permisos para acceder a esta página.', 'floresinc-rp'));
    }
    
    // Obtener datos para el dashboard
    global $wpdb;
    
    // Estadísticas generales
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
        SELECT SUM(points) FROM $points_table
    ");
    if (!$total_active_points) $total_active_points = 0;
    
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
        SELECT u.ID, u.display_name, u.user_email, p.points
        FROM $users_table u
        JOIN $points_table p ON u.ID = p.user_id
        ORDER BY p.points DESC
        LIMIT 10
    ");
    
    // Top referidores
    $top_referrers = $wpdb->get_results("
        SELECT 
            u.ID, 
            u.display_name, 
            COUNT(r.user_id) as total_referrals
        FROM 
            $users_table u
        JOIN 
            $referrals_table r ON u.ID = r.referrer_id
        GROUP BY 
            r.referrer_id
        ORDER BY 
            total_referrals DESC
        LIMIT 5
    ");
    
    // Iniciar el contenido de la página con las pestañas
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="floresinc-rp-tabs">
            <a href="<?php echo admin_url('admin.php?page=floresinc-rp-dashboard'); ?>" class="tab active">
                <?php _e('Dashboard', 'floresinc-rp'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=floresinc-rp-transactions'); ?>" class="tab">
                <?php _e('Transacciones', 'floresinc-rp'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=floresinc-rp-network'); ?>" class="tab">
                <?php _e('Red de Referidos', 'floresinc-rp'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=floresinc-rp-settings'); ?>" class="tab">
                <?php _e('Configuración', 'floresinc-rp'); ?>
            </a>
        </div>
    <?php
    
    // Incluir vista
    include_once plugin_dir_path(__FILE__) . 'views/admin-dashboard.php';
    
    // Cerrar el div.wrap
    echo '</div>';
    
    // Los estilos de las pestañas ahora se cargan desde floresinc-styles.css
}

/**
 * Página de transacciones
 */
function floresinc_rp_transactions_page() {
    // Verificar permisos
    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('No tienes permisos para acceder a esta página.', 'floresinc-rp'));
    }
    
    // Incluir clase de la tabla si no está incluida
    if (!class_exists('FloresInc_RP_Transactions_Table')) {
        require_once plugin_dir_path(__FILE__) . 'class-transactions-table.php';
    }
    
    // Renderizar la tabla
    $transactions_table = new FloresInc_RP_Transactions_Table();
    $transactions_table->prepare_items();
    
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="floresinc-rp-tabs">
            <a href="<?php echo admin_url('admin.php?page=floresinc-rp-dashboard'); ?>" class="tab">
                <?php _e('Dashboard', 'floresinc-rp'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=floresinc-rp-transactions'); ?>" class="tab active">
                <?php _e('Transacciones', 'floresinc-rp'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=floresinc-rp-network'); ?>" class="tab">
                <?php _e('Red de Referidos', 'floresinc-rp'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=floresinc-rp-settings'); ?>" class="tab">
                <?php _e('Configuración', 'floresinc-rp'); ?>
            </a>
        </div>
        
        <form id="transactions-filter" method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
            <?php $transactions_table->display(); ?>
        </form>
    </div>
    <?php
}

/**
 * Página de red de referidos
 */
function floresinc_rp_network_page() {
    // Verificar permisos
    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('No tienes permisos para acceder a esta página.', 'floresinc-rp'));
    }
    
    // Incluir clase de la tabla si no está incluida
    if (!class_exists('FloresInc_RP_Referrals_Table')) {
        require_once plugin_dir_path(__FILE__) . 'class-referrals-table.php';
    }
    
    global $wpdb;
    
    // Obtener estadísticas
    $total_users = count_users()['total_users'];
    
    $users_with_referrer = $wpdb->get_var("
        SELECT COUNT(*) FROM {$wpdb->prefix}floresinc_referrals
        WHERE referrer_id > 0
    ");
    
    $referral_rate = $total_users > 0 ? round(($users_with_referrer / $total_users) * 100, 2) : 0;
    
    // Renderizar la tabla
    $referrals_table = new FloresInc_RP_Referrals_Table();
    $referrals_table->prepare_items();
    
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="floresinc-rp-tabs">
            <a href="<?php echo admin_url('admin.php?page=floresinc-rp-dashboard'); ?>" class="tab">
                <?php _e('Dashboard', 'floresinc-rp'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=floresinc-rp-transactions'); ?>" class="tab">
                <?php _e('Transacciones', 'floresinc-rp'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=floresinc-rp-network'); ?>" class="tab active">
                <?php _e('Red de Referidos', 'floresinc-rp'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=floresinc-rp-settings'); ?>" class="tab">
                <?php _e('Configuración', 'floresinc-rp'); ?>
            </a>
        </div>
        
        <div class="floresinc-rp-network-stats">
            <div class="stat-box">
                <span class="stat-label"><?php _e('Total de Usuarios', 'floresinc-rp'); ?></span>
                <span class="stat-value"><?php echo number_format($total_users); ?></span>
            </div>
            <div class="stat-box">
                <span class="stat-label"><?php _e('Usuarios con Referidor', 'floresinc-rp'); ?></span>
                <span class="stat-value"><?php echo number_format($users_with_referrer); ?></span>
            </div>
            <div class="stat-box">
                <span class="stat-label"><?php _e('Tasa de Referidos', 'floresinc-rp'); ?></span>
                <span class="stat-value"><?php echo $referral_rate; ?>%</span>
            </div>
        </div>
        
        <form id="referrals-filter" method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
            <?php $referrals_table->display(); ?>
        </form>
    </div>
    <?php
}

/**
 * Página de configuración
 */
function floresinc_rp_settings_page() {
    // Verificar permisos (mantener esta página solo para administradores)
    if (!current_user_can('manage_options')) {
        wp_die(__('No tienes permisos para acceder a esta página.', 'floresinc-rp'));
    }
    
    // Opciones
    $options = get_option('floresinc_rp_settings', []);
    
    // Incluir vista
    include_once plugin_dir_path(__FILE__) . 'views/admin-settings.php';
}

/**
 * Registrar ajustes
 */
function floresinc_rp_register_settings() {
    register_setting(
        'floresinc_rp_settings',
        'floresinc_rp_settings',
        [
            'sanitize_callback' => 'floresinc_rp_sanitize_settings',
            'default' => [
                // Opciones generales
                'enable_points' => 1,
                'enable_referrals' => 1,
                'allowed_roles' => ['customer'],
                
                // Opciones de puntos
                'points_conversion_rate' => 0.1,
                'points_per_currency' => 1,
                'min_points_redemption' => 100,
                'max_points_per_order' => 1000,
                'points_expiry_days' => 365,
                'point_triggers' => ['purchase'],
                'points_registration' => 50,
                'points_review' => 10,
                'points_birthday' => 25,
                
                // Opciones de referidos
                'referral_commission_first' => 10,
                'referral_commission_subsequent' => 5,
                'referral_commission_duration' => 12,
                'enable_second_level' => 0,
                'second_level_commission' => 2,
                'points_per_referral' => 100,
                
                // Opciones de visualización
                'display_points_product' => 1,
                'display_points_checkout' => 1,
            ]
        ]
    );
}

/**
 * Sanitizar ajustes
 */
function floresinc_rp_sanitize_settings($input) {
    $sanitized = [];
    
    // Checkboxes
    $sanitized['enable_points'] = isset($input['enable_points']) ? 1 : 0;
    $sanitized['enable_referrals'] = isset($input['enable_referrals']) ? 1 : 0;
    $sanitized['enable_second_level'] = isset($input['enable_second_level']) ? 1 : 0;
    $sanitized['display_points_product'] = isset($input['display_points_product']) ? 1 : 0;
    $sanitized['display_points_checkout'] = isset($input['display_points_checkout']) ? 1 : 0;
    
    // Roles permitidos
    $sanitized['allowed_roles'] = isset($input['allowed_roles']) && is_array($input['allowed_roles']) 
        ? array_map('sanitize_text_field', $input['allowed_roles']) 
        : ['customer'];
    
    // Puntos por eventos
    $sanitized['point_triggers'] = isset($input['point_triggers']) && is_array($input['point_triggers']) 
        ? array_map('sanitize_text_field', $input['point_triggers']) 
        : ['purchase'];
    
    // Configuración numérica
    $numeric_fields = [
        'points_conversion_rate', 'points_per_currency', 'min_points_redemption',
        'max_points_per_order', 'points_expiry_days', 'points_registration',
        'points_review', 'points_birthday', 'referral_commission_first',
        'referral_commission_subsequent', 'referral_commission_duration',
        'second_level_commission', 'points_per_referral'
    ];
    
    foreach ($numeric_fields as $field) {
        $sanitized[$field] = isset($input[$field]) 
            ? floatval($input[$field]) 
            : 0;
    }
    
    // Textos
    $text_fields = [
        'product_points_text', 'redeem_points_text'
    ];
    
    foreach ($text_fields as $field) {
        $sanitized[$field] = isset($input[$field]) 
            ? wp_kses_post($input[$field]) 
            : '';
    }
    
    return $sanitized;
}

/**
 * Agregar campo de puntos a la tabla de usuarios
 */
function floresinc_rp_add_user_columns($columns) {
    $columns['floresinc_points'] = __('Puntos', 'floresinc-rp');
    $columns['floresinc_referral_code'] = __('Referido por', 'floresinc-rp');
    return $columns;
}

/**
 * Contenido de las columnas personalizadas de usuarios
 */
function floresinc_rp_user_column_content($content, $column_name, $user_id) {
    switch ($column_name) {
        case 'floresinc_points':
            $points = floresinc_rp_get_user_points($user_id);
            return $points ? $points['balance'] : 0;
            
        case 'floresinc_referral_code':
            // Obtener el referidor del usuario usando la función implementada
            $referrer = floresinc_rp_get_user_referrer($user_id);
            
            if ($referrer && isset($referrer['id'])) {
                // Mostrar el nombre del referidor con enlace a su perfil
                return '<a href="' . get_edit_user_link($referrer['id']) . '">' . 
                       esc_html($referrer['name']) . '</a>';
            }
            
            return '—';
    }
    
    return $content;
}

/**
 * Hacer columnas ordenables
 */
function floresinc_rp_user_sortable_columns($columns) {
    $columns['floresinc_points'] = 'floresinc_points';
    $columns['floresinc_referral_code'] = 'floresinc_referral_code';
    return $columns;
}

/**
 * Añadir campos al perfil de usuario
 */
function floresinc_rp_add_user_profile_fields($user) {
    // Verificar permisos
    if (!current_user_can('manage_woocommerce')) {
        return;
    }
    
    $points = floresinc_rp_get_user_points($user->ID);
    $referral_code = floresinc_rp_get_user_referral_code($user->ID);
    $referrer = floresinc_rp_get_user_referrer($user->ID);
    
    ?>
    <h2><?php _e('Referidos y Puntos', 'floresinc-rp'); ?></h2>
    
    <table class="form-table">
        <tr>
            <th><label for="floresinc_points"><?php _e('Puntos', 'floresinc-rp'); ?></label></th>
            <td>
                <input type="number" name="floresinc_points" id="floresinc_points" 
                    value="<?php echo esc_attr($points ? $points['balance'] : 0); ?>" 
                    class="regular-text" />
                <p class="description">
                    <?php _e('Saldo actual de puntos del usuario.', 'floresinc-rp'); ?>
                </p>
            </td>
        </tr>
        
        <tr>
            <th><label for="floresinc_referral_code"><?php _e('Código de Referido', 'floresinc-rp'); ?></label></th>
            <td>
                <input type="text" name="floresinc_referral_code" id="floresinc_referral_code" 
                    value="<?php echo esc_attr($referral_code); ?>" 
                    class="regular-text" />
                <p class="description">
                    <?php _e('Código de referido único del usuario.', 'floresinc-rp'); ?>
                </p>
            </td>
        </tr>
        
        <tr>
            <th><label for="floresinc_referrer_id"><?php _e('Referido por', 'floresinc-rp'); ?></label></th>
            <td>
                <select name="floresinc_referrer_id" id="floresinc_referrer_id">
                    <option value=""><?php _e('Ninguno', 'floresinc-rp'); ?></option>
                    <?php
                    $users = get_users([
                        'exclude' => [$user->ID],
                        'fields' => ['ID', 'display_name', 'user_email']
                    ]);
                    
                    foreach ($users as $u) {
                        $selected = $referrer && $referrer['id'] == $u->ID ? 'selected' : '';
                        echo '<option value="' . esc_attr($u->ID) . '" ' . $selected . '>';
                        echo esc_html($u->display_name) . ' (' . esc_html($u->user_email) . ')';
                        echo '</option>';
                    }
                    ?>
                </select>
                <p class="description">
                    <?php _e('Usuario que refirió a este usuario.', 'floresinc-rp'); ?>
                </p>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * Guardar campos del perfil de usuario
 */
function floresinc_rp_save_user_profile_fields($user_id) {
    // Verificar permisos
    if (!current_user_can('manage_woocommerce')) {
        return false;
    }
    
    // Guardar puntos
    if (isset($_POST['floresinc_points'])) {
        $old_points = floresinc_rp_get_user_points($user_id);
        $new_points = intval($_POST['floresinc_points']);
        
        if ($old_points && $old_points['balance'] != $new_points) {
            $difference = $new_points - $old_points['balance'];
            $type = $difference >= 0 ? 'admin_add' : 'admin_deduct';
            
            floresinc_rp_update_user_points(
                $user_id,
                abs($difference),
                $type,
                __('Ajuste manual por administrador', 'floresinc-rp')
            );
        }
    }
    
    // Guardar código de referido
    if (isset($_POST['floresinc_referral_code'])) {
        $new_code = sanitize_text_field($_POST['floresinc_referral_code']);
        floresinc_rp_update_user_referral_code($user_id, $new_code);
    }
    
    // Guardar referidor
    if (isset($_POST['floresinc_referrer_id'])) {
        $referrer_id = intval($_POST['floresinc_referrer_id']);
        floresinc_rp_update_user_referrer($user_id, $referrer_id);
    }
}

/**
 * Scripts para el footer del admin
 */
function floresinc_rp_admin_footer_scripts() {
    $screen = get_current_screen();
    
    // Solo en pantallas relevantes
    if (!$screen || !in_array($screen->id, [
        'floresinc-rp-dashboard',
        'floresinc-rp-settings',
        'floresinc-rp-transactions',
        'floresinc-rp-network'
    ])) {
        return;
    }
    
    ?>
    <script type="text/javascript">
        // Scripts específicos para el admin
        jQuery(document).ready(function($) {
            // Inicializar datepickers si existen
            if ($.fn.datepicker) {
                $('.floresinc-date-picker').datepicker({
                    dateFormat: 'yy-mm-dd'
                });
            }
        });
    </script>
    <?php
}

/**
 * Procesar la asignación de puntos por el administrador
 */
function floresinc_rp_process_admin_points_assignment() {
    // Verificar permisos
    if (!current_user_can('manage_woocommerce')) {
        return;
    }
    
    // Verificar nonce
    if (!isset($_POST['floresinc_rp_admin_points_nonce']) || 
        !wp_verify_nonce($_POST['floresinc_rp_admin_points_nonce'], 'floresinc_rp_admin_points_action')) {
        return;
    }
    
    // Obtener datos del formulario
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $points = isset($_POST['points']) ? intval($_POST['points']) : 0;
    $description = isset($_POST['description']) ? sanitize_text_field($_POST['description']) : '';
    $action_type = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : 'add';
    
    // Validar datos
    if ($user_id <= 0 || $points <= 0 || empty($description)) {
        add_settings_error(
            'floresinc_rp_admin_points',
            'invalid_data',
            __('Por favor, complete todos los campos correctamente.', 'floresinc-rp'),
            'error'
        );
        return;
    }
    
    // Verificar que el usuario existe
    $user = get_user_by('id', $user_id);
    if (!$user) {
        add_settings_error(
            'floresinc_rp_admin_points',
            'invalid_user',
            __('El usuario seleccionado no existe.', 'floresinc-rp'),
            'error'
        );
        return;
    }
    
    $result = false;
    
    // Procesar según el tipo de acción
    if ($action_type === 'add') {
        // Añadir puntos
        $result = floresinc_rp_add_points(
            $user_id,
            $points,
            'admin_add',
            $description,
            null,
            null // Sin fecha de expiración para puntos asignados por admin
        );
    } else if ($action_type === 'deduct') {
        // Verificar si el usuario tiene suficientes puntos
        $user_points = floresinc_rp_get_user_points($user_id);
        if ($user_points['balance'] < $points) {
            add_settings_error(
                'floresinc_rp_admin_points',
                'insufficient_points',
                sprintf(
                    __('El usuario solo tiene %d puntos disponibles.', 'floresinc-rp'),
                    $user_points['balance']
                ),
                'error'
            );
            return;
        }
        
        // Deducir puntos
        $result = floresinc_rp_use_points(
            $user_id,
            $points,
            $description,
            null
        );
    }
    
    // Mostrar mensaje de resultado
    if ($result) {
        $action_text = $action_type === 'add' ? 'añadidos' : 'deducidos';
        add_settings_error(
            'floresinc_rp_admin_points',
            'success',
            sprintf(
                __('Se han %s %d puntos al usuario %s correctamente.', 'floresinc-rp'),
                $action_text,
                $points,
                $user->display_name
            ),
            'success'
        );
    } else {
        add_settings_error(
            'floresinc_rp_admin_points',
            'error',
            __('Ha ocurrido un error al procesar los puntos.', 'floresinc-rp'),
            'error'
        );
    }
}
