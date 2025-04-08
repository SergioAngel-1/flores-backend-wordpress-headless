<?php
/**
 * Integración con WooCommerce
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Inicializar integración con WooCommerce
 */
function floresinc_rp_init_woocommerce_integration() {
    // Añadir campo de puntos en la página de producto
    add_action('woocommerce_product_options_general_product_data', 'floresinc_rp_add_product_points_field');
    add_action('woocommerce_process_product_meta', 'floresinc_rp_save_product_points_field');
    
    // Mostrar puntos en la página de producto
    add_action('woocommerce_before_add_to_cart_button', 'floresinc_rp_display_product_points');
    
    // Cargar estilos CSS en el frontend
    add_action('wp_enqueue_scripts', 'floresinc_rp_enqueue_frontend_styles');
    
    // Añadir campo de código de referido en checkout
    add_filter('woocommerce_checkout_fields', 'floresinc_rp_add_referral_code_field');
    add_action('woocommerce_checkout_update_order_meta', 'floresinc_rp_save_referral_code_to_order');
    
    // Mostrar puntos en la página Mi Cuenta
    add_action('woocommerce_account_dashboard', 'floresinc_rp_display_points_in_account');
    
    // Añadir pestaña de Puntos y Referidos en Mi Cuenta
    add_filter('woocommerce_account_menu_items', 'floresinc_rp_add_account_menu_items');
    add_action('woocommerce_account_puntos-referidos_endpoint', 'floresinc_rp_account_points_content');
    add_action('init', 'floresinc_rp_add_endpoints');
    add_filter('query_vars', 'floresinc_rp_add_query_vars');
    
    // Mostrar puntos ganados en el correo de confirmación
    add_action('woocommerce_email_order_details', 'floresinc_rp_email_show_points', 10, 4);
}

/**
 * Añadir campo de puntos en el panel de productos
 */
function floresinc_rp_add_product_points_field() {
    global $post;
    
    echo '<div class="options_group">';
    
    woocommerce_wp_text_input([
        'id' => '_floresinc_product_points',
        'label' => __('Puntos por compra', 'floresinc-rp'),
        'description' => __('Puntos que el cliente gana al comprar este producto', 'floresinc-rp'),
        'desc_tip' => true,
        'type' => 'number',
        'custom_attributes' => [
            'step' => '1',
            'min' => '0'
        ]
    ]);
    
    echo '</div>';
}

/**
 * Guardar campo de puntos del producto
 */
function floresinc_rp_save_product_points_field($post_id) {
    if (isset($_POST['_floresinc_product_points'])) {
        $points = absint($_POST['_floresinc_product_points']);
        update_post_meta($post_id, '_floresinc_product_points', $points);
    }
}

/**
 * Mostrar puntos en la página de producto
 */
function floresinc_rp_display_product_points() {
    global $product;
    
    if (!$product) {
        return;
    }
    
    $points = get_post_meta($product->get_id(), '_floresinc_product_points', true);
    
    if (!$points) {
        return;
    }
    
    echo '<div class="product-points-info">';
    echo '<div class="points-badge">';
    echo sprintf(
        __('<strong>%d puntos</strong> al comprar este producto', 'floresinc-rp'),
        (int) $points
    );
    echo '</div>';
    echo '</div>';
    
    // Estilo CSS
    ?>
    <!-- Los estilos ahora se cargan desde el archivo floresinc-styles.css -->
    <?php
}

/**
 * Añadir campo de código de referido en checkout
 */
function floresinc_rp_add_referral_code_field($fields) {
    // Solo mostrar si el usuario no está logueado
    if (is_user_logged_in()) {
        return $fields;
    }
    
    // Verificar si ya hay un código en la cookie
    $has_cookie = isset($_COOKIE['floresinc_referral']) && !empty($_COOKIE['floresinc_referral']);
    
    $fields['order']['floresinc_referral_code'] = [
        'type' => 'text',
        'label' => __('Código de referido (opcional)', 'floresinc-rp'),
        'placeholder' => __('Si tienes un código, ingrésalo aquí', 'floresinc-rp'),
        'required' => false,
        'class' => ['form-row-wide'],
        'priority' => 120,
        'default' => $has_cookie ? $_COOKIE['floresinc_referral'] : '',
        'custom_attributes' => $has_cookie ? ['readonly' => 'readonly'] : []
    ];
    
    return $fields;
}

/**
 * Guardar código de referido en el pedido
 */
function floresinc_rp_save_referral_code_to_order($order_id) {
    if (isset($_POST['floresinc_referral_code']) && !empty($_POST['floresinc_referral_code'])) {
        $code = sanitize_text_field($_POST['floresinc_referral_code']);
        
        // Verificar que el código existe
        global $wpdb;
        $table = $wpdb->prefix . 'floresinc_referrals';
        $exists = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $table WHERE referral_code = %s
        ", $code));
        
        if ($exists > 0) {
            update_post_meta($order_id, '_floresinc_referral_code', $code);
            
            // Si es un pedido para registrar usuario, guardar también en cookie
            if (!is_user_logged_in()) {
                setcookie('floresinc_referral', $code, time() + (86400 * 30), '/');
            }
        }
    }
}

/**
 * Mostrar puntos en la página de Mi Cuenta
 */
function floresinc_rp_display_points_in_account() {
    if (!is_user_logged_in()) {
        return;
    }
    
    $user_id = get_current_user_id();
    $user_points = floresinc_rp_get_user_points($user_id);
    
    if (!$user_points || $user_points['balance'] <= 0) {
        return;
    }
    
    echo '<div class="floresinc-account-points">';
    echo '<h3>' . __('Mis Puntos', 'floresinc-rp') . '</h3>';
    echo '<p>' . sprintf(
        __('Tienes <strong>%d puntos</strong> disponibles', 'floresinc-rp'),
        $user_points['balance']
    ) . '</p>';
    echo '<p><a href="' . wc_get_account_endpoint_url('puntos-referidos') . '" class="button">';
    echo __('Ver mis puntos y referidos', 'floresinc-rp');
    echo '</a></p>';
    echo '</div>';
    
    // Estilo CSS
    ?>
    <!-- Los estilos ahora se cargan desde el archivo floresinc-styles.css -->
    <?php
}

/**
 * Añadir pestaña de Puntos y Referidos en Mi Cuenta
 */
function floresinc_rp_add_account_menu_items($items) {
    // Insertar después de "Pedidos"
    $new_items = [];
    
    foreach ($items as $key => $value) {
        $new_items[$key] = $value;
        
        if ($key === 'orders') {
            $new_items['puntos-referidos'] = __('Puntos y Referidos', 'floresinc-rp');
        }
    }
    
    return $new_items;
}

/**
 * Añadir endpoint para la pestaña de Puntos y Referidos
 */
function floresinc_rp_add_endpoints() {
    add_rewrite_endpoint('puntos-referidos', EP_ROOT | EP_PAGES);
}

/**
 * Añadir variable de consulta
 */
function floresinc_rp_add_query_vars($vars) {
    $vars[] = 'puntos-referidos';
    return $vars;
}

/**
 * Contenido de la página de Puntos y Referidos
 */
function floresinc_rp_account_points_content() {
    $user_id = get_current_user_id();
    $user_points = floresinc_rp_get_user_points($user_id);
    $transactions = floresinc_rp_get_user_transactions($user_id, 10);
    $referrals = floresinc_rp_get_user_referrals($user_id);
    $referral_code = floresinc_rp_get_user_referral_code($user_id);
    // La URL de referido ahora se maneja en el frontend
    
    // Convertir a valor monetario
    $options = FloresInc_RP()->get_options();
    $conversion_rate = $options['points_conversion_rate'];
    $points_value = $user_points['balance'] / $conversion_rate;
    
    ?>
    <div class="floresinc-points-dashboard">
        <!-- Resumen de puntos -->
        <div class="points-summary">
            <h2><?php _e('Mi Saldo de Puntos', 'floresinc-rp'); ?></h2>
            <div class="points-balance">
                <span class="points-number"><?php echo $user_points['balance']; ?></span>
                <span class="points-label"><?php _e('puntos disponibles', 'floresinc-rp'); ?></span>
                <?php if ($points_value > 0) : ?>
                <span class="points-value">
                    <?php echo sprintf(__('(Equivalente a %s)', 'floresinc-rp'), wc_price($points_value)); ?>
                </span>
                <?php endif; ?>
            </div>
            <div class="points-stats">
                <div class="stat">
                    <span class="stat-label"><?php _e('Total ganado:', 'floresinc-rp'); ?></span>
                    <span class="stat-value"><?php echo $user_points['total_earned']; ?></span>
                </div>
                <div class="stat">
                    <span class="stat-label"><?php _e('Total usado:', 'floresinc-rp'); ?></span>
                    <span class="stat-value"><?php echo $user_points['used']; ?></span>
                </div>
            </div>
        </div>
        
        <!-- Compartir referido -->
        <div class="referral-share">
            <h2><?php _e('Invita a tus amigos', 'floresinc-rp'); ?></h2>
            <p><?php printf(
                __('Invita a tus amigos a unirse usando tu código de referido y gana %d%% en puntos por sus compras.', 'floresinc-rp'),
                $options['referral_commission_level1']
            ); ?></p>
            
            <div class="referral-code-display">
                <div class="code-label"><?php _e('Tu código de referido:', 'floresinc-rp'); ?></div>
                <div class="code-value"><?php echo $referral_code; ?></div>
            </div>
            
            <div class="referral-link">
                <div class="link-label"><?php _e('Tu enlace de referido:', 'floresinc-rp'); ?></div>
                <div class="link-value">
                    <input type="text" readonly value="<?php echo esc_url(add_query_arg('ref', $referral_code, home_url())); ?>" 
                           onClick="this.select();" style="width: 100%;">
                </div>
                <button class="copy-button" onclick="copyToClipboard(this)">
                    <?php _e('Copiar enlace', 'floresinc-rp'); ?>
                </button>
            </div>
            
            <div class="share-buttons">
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($referral_url); ?>" 
                   target="_blank" class="share-button facebook">
                    <?php _e('Compartir en Facebook', 'floresinc-rp'); ?>
                </a>
                <a href="https://wa.me/?text=<?php echo urlencode(__('Usa mi código de referido para obtener beneficios: ', 'floresinc-rp') . $referral_url); ?>" 
                   target="_blank" class="share-button whatsapp">
                    <?php _e('Compartir por WhatsApp', 'floresinc-rp'); ?>
                </a>
            </div>
        </div>
        
        <!-- Historial de transacciones -->
        <div class="transactions-history">
            <h2><?php _e('Historial de Transacciones', 'floresinc-rp'); ?></h2>
            <?php if (empty($transactions)) : ?>
                <p><?php _e('No tienes transacciones de puntos aún.', 'floresinc-rp'); ?></p>
            <?php else : ?>
                <table class="transactions-table">
                    <thead>
                        <tr>
                            <th><?php _e('Fecha', 'floresinc-rp'); ?></th>
                            <th><?php _e('Tipo', 'floresinc-rp'); ?></th>
                            <th><?php _e('Puntos', 'floresinc-rp'); ?></th>
                            <th><?php _e('Descripción', 'floresinc-rp'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction) : ?>
                            <tr>
                                <td><?php echo date_i18n(get_option('date_format'), strtotime($transaction->created_at)); ?></td>
                                <td>
                                    <?php 
                                    switch ($transaction->type) {
                                        case 'earned':
                                            _e('Ganado', 'floresinc-rp');
                                            break;
                                        case 'used':
                                            _e('Usado', 'floresinc-rp');
                                            break;
                                        case 'expired':
                                            _e('Expirado', 'floresinc-rp');
                                            break;
                                        case 'referral':
                                            _e('Referido', 'floresinc-rp');
                                            break;
                                        default:
                                            echo ucfirst($transaction->type);
                                    }
                                    ?>
                                </td>
                                <td class="<?php echo $transaction->points >= 0 ? 'positive' : 'negative'; ?>">
                                    <?php echo $transaction->points > 0 ? '+' . $transaction->points : $transaction->points; ?>
                                </td>
                                <td><?php echo $transaction->description; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Mis referidos -->
        <div class="my-referrals">
            <h2><?php _e('Mis Referidos', 'floresinc-rp'); ?></h2>
            <?php if (empty($referrals)) : ?>
                <p><?php _e('No tienes referidos aún. ¡Comparte tu código y comienza a ganar puntos!', 'floresinc-rp'); ?></p>
            <?php else : ?>
                <table class="referrals-table">
                    <thead>
                        <tr>
                            <th><?php _e('Nombre', 'floresinc-rp'); ?></th>
                            <th><?php _e('Fecha', 'floresinc-rp'); ?></th>
                            <th><?php _e('Nivel', 'floresinc-rp'); ?></th>
                            <th><?php _e('Puntos generados', 'floresinc-rp'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($referrals as $referral) : ?>
                            <tr>
                                <td><?php echo $referral['name']; ?></td>
                                <td><?php echo date_i18n(get_option('date_format'), strtotime($referral['registration_date'])); ?></td>
                                <td>
                                    <?php echo $referral['level'] == 1 ? 
                                        __('Directo', 'floresinc-rp') : 
                                        __('Indirecto', 'floresinc-rp'); ?>
                                </td>
                                <td><?php echo $referral['total_points_generated']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Estilos CSS -->
    <!-- Los estilos ahora se cargan desde el archivo floresinc-styles.css -->
    
    <!-- JavaScript para copiar al portapapeles -->
    <script type="text/javascript">
    function copyToClipboard(button) {
        var input = button.previousElementSibling.querySelector('input');
        input.select();
        document.execCommand('copy');
        
        var originalText = button.textContent;
        button.textContent = '¡Copiado!';
        button.style.backgroundColor = '#4CAF50';
        
        setTimeout(function() {
            button.textContent = originalText;
            button.style.backgroundColor = '#2e7d32';
        }, 2000);
    }
    </script>
    <?php
}

/**
 * Cargar estilos CSS en el frontend de WooCommerce
 */
function floresinc_rp_enqueue_frontend_styles() {
    // Cargar estilos solo en las páginas de WooCommerce donde se necesitan
    if (is_product() || is_account_page() || is_checkout()) {
        wp_enqueue_style(
            'floresinc-rp-styles', 
            plugins_url('/assets/css/floresinc-styles.css', dirname(dirname(__FILE__))),
            array(),
            FLORESINC_RP_VERSION
        );
    }
}

/**
 * Mostrar puntos ganados en el correo de confirmación
 */
function floresinc_rp_email_show_points($order, $sent_to_admin, $plain_text, $email) {
    // Solo en correos al cliente y pedidos completados
    if ($sent_to_admin || !$order->has_status('completed')) {
        return;
    }
    
    $points_earned = get_post_meta($order->get_id(), '_floresinc_points_earned', true);
    
    if (!$points_earned || $points_earned <= 0) {
        return;
    }
    
    if ($plain_text) {
        echo "\n" . sprintf(
            __('Has ganado %d puntos con esta compra.', 'floresinc-rp'),
            $points_earned
        ) . "\n\n";
    } else {
        echo '<div style="margin-bottom: 40px; padding: 12px; border-left: 4px solid #2e7d32; background-color: #f9f9f9;">';
        echo '<h3 style="margin: 0 0 10px; color: #2e7d32;">' . __('Tus Puntos', 'floresinc-rp') . '</h3>';
        echo '<p style="margin: 0;">' . sprintf(
            __('Has ganado <strong>%d puntos</strong> con esta compra.', 'floresinc-rp'),
            $points_earned
        ) . '</p>';
        echo '</div>';
    }
}
