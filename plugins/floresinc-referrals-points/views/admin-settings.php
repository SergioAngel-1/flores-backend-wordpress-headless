<div class="wrap floresinc-rp-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="floresinc-rp-tabs">
        <a href="<?php echo admin_url('admin.php?page=floresinc-rp-dashboard'); ?>" class="tab">
            <?php _e('Dashboard', 'floresinc-rp'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=floresinc-rp-transactions'); ?>" class="tab">
            <?php _e('Transacciones', 'floresinc-rp'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=floresinc-rp-network'); ?>" class="tab">
            <?php _e('Red de Referidos', 'floresinc-rp'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=floresinc-rp-settings'); ?>" class="tab active">
            <?php _e('Configuración', 'floresinc-rp'); ?>
        </a>
    </div>
    
    <?php
    // Mostrar mensajes de éxito o error
    if (isset($_GET['settings-updated'])) {
        add_settings_error('floresinc_rp_messages', 'floresinc_rp_message', __('Configuración guardada.', 'floresinc-rp'), 'updated');
    }
    settings_errors('floresinc_rp_messages');
    ?>
    
    <form method="post" action="options.php">
        <?php
        settings_fields('floresinc_rp_settings');
        ?>
        
        <div class="floresinc-rp-settings-container">
            <!-- Pestañas de configuración -->
            <div class="nav-tab-wrapper wp-clearfix">
                <a href="#general-settings" class="nav-tab nav-tab-active"><?php _e('General', 'floresinc-rp'); ?></a>
                <a href="#points-settings" class="nav-tab"><?php _e('Puntos', 'floresinc-rp'); ?></a>
                <a href="#referral-settings" class="nav-tab"><?php _e('Referidos', 'floresinc-rp'); ?></a>
                <a href="#display-settings" class="nav-tab"><?php _e('Visualización', 'floresinc-rp'); ?></a>
            </div>
            
            <!-- Sección General -->
            <div id="general-settings" class="tab-content active">
                <h2><?php _e('Configuración General', 'floresinc-rp'); ?></h2>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Activar sistema de puntos', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="checkbox" name="floresinc_rp_settings[enable_points]" value="1" 
                                <?php checked(1, $options['enable_points'] ?? 0); ?> />
                            <p class="description">
                                <?php _e('Habilita o deshabilita todo el sistema de puntos.', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Activar sistema de referidos', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="checkbox" name="floresinc_rp_settings[enable_referrals]" value="1" 
                                <?php checked(1, $options['enable_referrals'] ?? 0); ?> />
                            <p class="description">
                                <?php _e('Habilita o deshabilita todo el sistema de referidos.', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Roles de usuario permitidos', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <?php
                            $all_roles = get_editable_roles();
                            $allowed_roles = $options['allowed_roles'] ?? ['customer'];
                            
                            foreach ($all_roles as $role_id => $role_info) {
                                $checked = in_array($role_id, $allowed_roles) ? 'checked' : '';
                                ?>
                                <label>
                                    <input type="checkbox" name="floresinc_rp_settings[allowed_roles][]" value="<?php echo esc_attr($role_id); ?>" <?php echo $checked; ?> />
                                    <?php echo esc_html($role_info['name']); ?>
                                </label><br>
                                <?php
                            }
                            ?>
                            <p class="description">
                                <?php _e('Selecciona qué roles de usuario pueden participar en el programa de puntos y referidos.', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Sección de Puntos -->
            <div id="points-settings" class="tab-content">
                <h2><?php _e('Configuración de Puntos', 'floresinc-rp'); ?></h2>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Tasa de conversión de puntos', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="number" name="floresinc_rp_settings[points_conversion_rate]" step="0.01" min="0" 
                                value="<?php echo esc_attr($options['points_conversion_rate'] ?? 0.1); ?>" />
                            <p class="description">
                                <?php _e('Valor monetario de cada punto (por ejemplo, 0.1 significa que 10 puntos = $1).', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Puntos por gasto', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="number" name="floresinc_rp_settings[points_per_currency]" step="0.1" min="0" 
                                value="<?php echo esc_attr($options['points_per_currency'] ?? 1); ?>" />
                            <p class="description">
                                <?php _e('Cantidad de puntos otorgados por cada unidad de moneda gastada (por defecto: 1 punto por $1).', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Mínimo de puntos para canjear', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="number" name="floresinc_rp_settings[min_points_redemption]" min="0" 
                                value="<?php echo esc_attr($options['min_points_redemption'] ?? 100); ?>" />
                            <p class="description">
                                <?php _e('Cantidad mínima de puntos que un cliente debe acumular antes de poder canjearlos.', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Máximo de puntos por orden', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="number" name="floresinc_rp_settings[max_points_per_order]" min="0" 
                                value="<?php echo esc_attr($options['max_points_per_order'] ?? 0); ?>" />
                            <p class="description">
                                <?php _e('Máximo de puntos que se pueden usar en una sola orden (0 = sin límite).', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Expiración de puntos', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="number" name="floresinc_rp_settings[points_expiry_days]" min="0" 
                                value="<?php echo esc_attr($options['points_expiry_days'] ?? 365); ?>" />
                            <p class="description">
                                <?php _e('Número de días después de los cuales expiran los puntos (0 = nunca expiran).', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Otorgar puntos para', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <?php
                            $point_triggers = [
                                'purchase' => __('Compras', 'floresinc-rp'),
                                'registration' => __('Registro', 'floresinc-rp'),
                                'review' => __('Escribir reseñas', 'floresinc-rp'),
                                'birthday' => __('Cumpleaños', 'floresinc-rp')
                            ];
                            
                            $enabled_triggers = $options['point_triggers'] ?? ['purchase'];
                            
                            foreach ($point_triggers as $trigger_id => $trigger_name) {
                                $checked = in_array($trigger_id, $enabled_triggers) ? 'checked' : '';
                                ?>
                                <label>
                                    <input type="checkbox" name="floresinc_rp_settings[point_triggers][]" value="<?php echo esc_attr($trigger_id); ?>" <?php echo $checked; ?> />
                                    <?php echo esc_html($trigger_name); ?>
                                </label><br>
                                <?php
                            }
                            ?>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Puntos por registro', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="number" name="floresinc_rp_settings[points_registration]" min="0" 
                                value="<?php echo esc_attr($options['points_registration'] ?? 100); ?>" />
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Puntos por reseña', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="number" name="floresinc_rp_settings[points_review]" min="0" 
                                value="<?php echo esc_attr($options['points_review'] ?? 50); ?>" />
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Puntos por cumpleaños', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="number" name="floresinc_rp_settings[points_birthday]" min="0" 
                                value="<?php echo esc_attr($options['points_birthday'] ?? 200); ?>" />
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Sección de Referidos -->
            <div id="referral-settings" class="tab-content">
                <h2><?php _e('Configuración de Referidos', 'floresinc-rp'); ?></h2>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Comisión por referido (primera compra)', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="number" name="floresinc_rp_settings[referral_commission_first]" step="0.01" min="0" max="100" 
                                value="<?php echo esc_attr($options['referral_commission_first'] ?? 10); ?>" />%
                            <p class="description">
                                <?php _e('Porcentaje de comisión otorgada al referidor por la primera compra del referido.', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Comisión por referido (compras siguientes)', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="number" name="floresinc_rp_settings[referral_commission_subsequent]" step="0.01" min="0" max="100" 
                                value="<?php echo esc_attr($options['referral_commission_subsequent'] ?? 5); ?>" />%
                            <p class="description">
                                <?php _e('Porcentaje de comisión otorgada al referidor por las compras posteriores del referido.', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Duración de la comisión', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="number" name="floresinc_rp_settings[referral_commission_duration]" min="0" 
                                value="<?php echo esc_attr($options['referral_commission_duration'] ?? 365); ?>" />
                            <p class="description">
                                <?php _e('Número de días durante los cuales un referidor recibe comisiones por compras del referido (0 = sin límite).', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Habilitar referidos de nivel 2', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="checkbox" name="floresinc_rp_settings[enable_second_level]" value="1" 
                                <?php checked(1, $options['enable_second_level'] ?? 0); ?> />
                            <p class="description">
                                <?php _e('Permitir comisiones por referidos de segundo nivel (los referidos de tus referidos).', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Comisión por referido de nivel 2', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="number" name="floresinc_rp_settings[second_level_commission]" step="0.01" min="0" max="100" 
                                value="<?php echo esc_attr($options['second_level_commission'] ?? 2); ?>" />%
                            <p class="description">
                                <?php _e('Porcentaje de comisión otorgada por compras de referidos de segundo nivel.', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Puntos por referido', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="number" name="floresinc_rp_settings[points_per_referral]" min="0" 
                                value="<?php echo esc_attr($options['points_per_referral'] ?? 100); ?>" />
                            <p class="description">
                                <?php _e('Puntos otorgados por cada nuevo usuario referido (independiente de compras).', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Sección de Visualización -->
            <div id="display-settings" class="tab-content">
                <h2><?php _e('Configuración de Visualización', 'floresinc-rp'); ?></h2>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Mostrar puntos en productos', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="checkbox" name="floresinc_rp_settings[display_points_product]" value="1" 
                                <?php checked(1, $options['display_points_product'] ?? 1); ?> />
                            <p class="description">
                                <?php _e('Mostrar los puntos que se ganarán al comprar en las páginas de productos.', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Mostrar puntos en checkout', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="checkbox" name="floresinc_rp_settings[display_points_checkout]" value="1" 
                                <?php checked(1, $options['display_points_checkout'] ?? 1); ?> />
                            <p class="description">
                                <?php _e('Mostrar los puntos que se ganarán al finalizar la compra.', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Título en páginas de producto', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="text" name="floresinc_rp_settings[product_points_text]" class="regular-text" 
                                value="<?php echo esc_attr($options['product_points_text'] ?? __('Gane {points} puntos al comprar este producto', 'floresinc-rp')); ?>" />
                            <p class="description">
                                <?php _e('Texto mostrado en las páginas de producto. Use {points} como marcador para la cantidad de puntos.', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Texto para canjear puntos', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="text" name="floresinc_rp_settings[redeem_points_text]" class="regular-text" 
                                value="<?php echo esc_attr($options['redeem_points_text'] ?? __('Usar mis puntos disponibles ({points} puntos)', 'floresinc-rp')); ?>" />
                            <p class="description">
                                <?php _e('Texto mostrado en el checkout para canjear puntos. Use {points} como marcador para el saldo de puntos.', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <?php submit_button(); ?>
    </form>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        // Mostrar/ocultar pestañas
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            
            // Activar pestaña
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            // Mostrar contenido
            $('.tab-content').removeClass('active');
            $($(this).attr('href')).addClass('active');
        });
    });
</script>

<style type="text/css">
    .floresinc-rp-settings {
        margin: 20px 0;
    }
    
    .floresinc-rp-tabs {
        display: flex;
        margin-bottom: 20px;
        border-bottom: 1px solid #ccc;
    }
    
    .floresinc-rp-tabs .tab {
        padding: 10px 15px;
        margin-right: 5px;
        background-color: #f1f1f1;
        text-decoration: none;
        color: #555;
        border: 1px solid #ccc;
        border-bottom: none;
        border-radius: 3px 3px 0 0;
    }
    
    .floresinc-rp-tabs .tab.active {
        background-color: #fff;
        border-bottom: 1px solid #fff;
        position: relative;
        bottom: -1px;
        font-weight: bold;
    }
    
    .floresinc-rp-settings-container {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .form-table th {
        width: 250px;
    }
</style>
