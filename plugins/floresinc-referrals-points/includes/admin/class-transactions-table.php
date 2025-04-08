<?php
/**
 * Tabla de transacciones para el panel de administración
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Verificar que la clase padre existe
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

/**
 * Clase para la tabla de transacciones
 */
class FloresInc_RP_Transactions_Table extends WP_List_Table {
    
    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct([
            'singular' => __('Transacción', 'floresinc-rp'),
            'plural'   => __('Transacciones', 'floresinc-rp'),
            'ajax'     => false
        ]);
    }
    
    /**
     * Obtener columnas
     */
    public function get_columns() {
        $columns = [
            'id'          => __('ID', 'floresinc-rp'),
            'user'        => __('Usuario', 'floresinc-rp'),
            'date'        => __('Fecha', 'floresinc-rp'),
            'type'        => __('Tipo', 'floresinc-rp'),
            'points'      => __('Puntos', 'floresinc-rp'),
            'description' => __('Descripción', 'floresinc-rp'),
            'expiration'  => __('Expiración', 'floresinc-rp')
        ];
        
        return $columns;
    }
    
    /**
     * Columnas que se pueden ordenar
     */
    public function get_sortable_columns() {
        $sortable_columns = [
            'id'          => ['id', true],
            'date'        => ['created_at', false],
            'user'        => ['user_id', false],
            'type'        => ['type', false],
            'points'      => ['points', false],
            'expiration'  => ['expiration_date', false]
        ];
        
        return $sortable_columns;
    }
    
    /**
     * Procesamiento por defecto para las columnas
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'id':
                return $item->id;
            case 'date':
                return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->created_at));
            case 'type':
                return $this->get_transaction_type_label($item->type);
            case 'points':
                return '<span style="color:' . ($item->points >= 0 ? 'green' : 'red') . '">' . $item->points . '</span>';
            case 'description':
                return $item->description;
            case 'expiration':
                return isset($item->expiration_date) && $item->expiration_date ? date_i18n(get_option('date_format'), strtotime($item->expiration_date)) : __('No expira', 'floresinc-rp');
            default:
                return isset($item->$column_name) ? $item->$column_name : '';
        }
    }
    
    /**
     * Columna de usuario
     */
    public function column_user($item) {
        $user = get_user_by('id', $item->user_id);
        
        if (!$user) {
            return __('Usuario no encontrado', 'floresinc-rp');
        }
        
        $user_edit_link = get_edit_user_link($item->user_id);
        $user_name = $user->display_name . ' (' . $user->user_email . ')';
        
        return '<a href="' . esc_url($user_edit_link) . '">' . esc_html($user_name) . '</a>';
    }
    
    /**
     * Obtener etiqueta legible para el tipo de transacción
     */
    private function get_transaction_type_label($type) {
        $labels = [
            'earned'      => __('Ganado por compra', 'floresinc-rp'),
            'used'        => __('Usado en compra', 'floresinc-rp'),
            'expired'     => __('Expirado', 'floresinc-rp'),
            'admin_add'   => __('Añadido por admin', 'floresinc-rp'),
            'admin_deduct'=> __('Deducido por admin', 'floresinc-rp'),
            'referral'    => __('Comisión de referido', 'floresinc-rp')
        ];
        
        return isset($labels[$type]) ? $labels[$type] : ucfirst($type);
    }
    
    /**
     * No hay elementos
     */
    public function no_items() {
        _e('No se encontraron transacciones.', 'floresinc-rp');
    }
    
    /**
     * Preparar elementos
     */
    public function prepare_items() {
        global $wpdb;
        
        $per_page = 20;
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        
        $this->_column_headers = [$columns, $hidden, $sortable];
        
        $table = $wpdb->prefix . 'floresinc_points_transactions';
        $users_table = $wpdb->prefix . 'users';
        
        // Búsqueda
        $search = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        
        // Filtros
        $user_id = isset($_REQUEST['user_id']) ? intval($_REQUEST['user_id']) : 0;
        $type = isset($_REQUEST['type']) ? sanitize_text_field($_REQUEST['type']) : '';
        
        // Construir la consulta
        $query = "SELECT t.* FROM $table t";
        
        // Join con usuarios para búsqueda
        if (!empty($search)) {
            $query .= " LEFT JOIN $users_table u ON t.user_id = u.ID";
        }
        
        // Condiciones WHERE
        $where = [];
        $where_args = [];
        
        // Filtro por usuario
        if ($user_id > 0) {
            $where[] = "t.user_id = %d";
            $where_args[] = $user_id;
        }
        
        // Filtro por tipo
        if (!empty($type)) {
            $where[] = "t.type = %s";
            $where_args[] = $type;
        }
        
        // Búsqueda
        if (!empty($search)) {
            $where[] = "(t.description LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s)";
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where_args[] = $search_like;
            $where_args[] = $search_like;
            $where_args[] = $search_like;
        }
        
        // Añadir condiciones a la consulta
        if (!empty($where)) {
            $query .= " WHERE " . implode(' AND ', $where);
        }
        
        // Ordenar
        $orderby = !empty($_REQUEST['orderby']) ? sanitize_sql_orderby($_REQUEST['orderby']) : 'id';
        $order = !empty($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'DESC';
        
        if (!empty($orderby) && !empty($order)) {
            $query .= " ORDER BY $orderby $order";
        }
        
        // Paginación
        $current_page = $this->get_pagenum();
        $total_items = $wpdb->get_var($this->prepare_query("SELECT COUNT(*) FROM ($query) as t", $where_args));
        
        $query .= " LIMIT %d OFFSET %d";
        $where_args[] = $per_page;
        $where_args[] = ($current_page - 1) * $per_page;
        
        // Preparar query final
        $prepared_query = $this->prepare_query($query, $where_args);
        
        // Obtener datos
        $this->items = $wpdb->get_results($prepared_query);
        
        // Configurar paginación
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);
    }
    
    /**
     * Preparar consulta con argumentos
     */
    private function prepare_query($query, $args = []) {
        global $wpdb;
        
        if (empty($args)) {
            return $query;
        }
        
        return $wpdb->prepare($query, $args);
    }
    
    /**
     * Filtros adicionales
     */
    public function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }
        
        // Filtro de tipo de transacción
        $types = [
            'earned'      => __('Ganado por compra', 'floresinc-rp'),
            'used'        => __('Usado en compra', 'floresinc-rp'),
            'expired'     => __('Expirado', 'floresinc-rp'),
            'admin_add'   => __('Añadido por admin', 'floresinc-rp'),
            'admin_deduct'=> __('Deducido por admin', 'floresinc-rp'),
            'referral'    => __('Comisión de referido', 'floresinc-rp')
        ];
        
        $selected_type = isset($_REQUEST['type']) ? sanitize_text_field($_REQUEST['type']) : '';
        ?>
        <div class="alignleft actions">
            <select name="type">
                <option value=""><?php _e('Todos los tipos', 'floresinc-rp'); ?></option>
                <?php foreach ($types as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($selected_type, $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php submit_button(__('Filtrar', 'floresinc-rp'), '', 'filter_action', false); ?>
        </div>
        <?php
    }
}
