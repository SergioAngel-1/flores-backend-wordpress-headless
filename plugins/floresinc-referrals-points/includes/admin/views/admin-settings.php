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
                <a href="#points-settings" class="nav-tab"><?php _e('Flores Coins', 'floresinc-rp'); ?></a>
                <a href="#referral-settings" class="nav-tab"><?php _e('Referidos', 'floresinc-rp'); ?></a>
                <a href="#display-settings" class="nav-tab"><?php _e('Visualización', 'floresinc-rp'); ?></a>
            </div>
            
            <!-- Sección General -->
            <div id="general-settings" class="tab-content active">
                <h2><?php _e('Configuración General del Sistema', 'floresinc-rp'); ?></h2>
                <p class="description"><?php _e('Estas opciones controlan la activación o desactivación de los módulos principales del sistema.', 'floresinc-rp'); ?></p>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Sistema de Flores Coins', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="checkbox" name="floresinc_rp_settings[enable_points]" value="1" 
                                <?php checked(1, $options['enable_points'] ?? 1); ?> />
                            <p class="description">
                                <?php _e('Cuando esta casilla está <strong>marcada</strong>, el sistema de Flores Coins está <strong>activo</strong>. Los clientes podrán ganar y canjear Flores Coins. Si la desactivas, todas las funciones relacionadas con Flores Coins dejarán de estar disponibles.', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Programa de Referidos', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="checkbox" name="floresinc_rp_settings[enable_referrals]" value="1" 
                                <?php checked(1, $options['enable_referrals'] ?? 1); ?> />
                            <p class="description">
                                <?php _e('Cuando esta casilla está <strong>marcada</strong>, el programa de referidos está <strong>activo</strong>. Los clientes podrán compartir su código de referido y ganar Flores Coins cuando otros usuarios se registren usando su código. Si la desactivas, todas las funciones de referidos dejarán de estar disponibles.', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Roles de usuario participantes', 'floresinc-rp'); ?>
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
                                <?php _e('Selecciona los roles de usuario que pueden participar en el programa de Flores Coins y referidos. <strong>Marca las casillas</strong> de los roles que deseas incluir. Por defecto, solo los clientes ("Customer") pueden participar.', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Sección de Flores Coins -->
            <div id="points-settings" class="tab-content">
                <h2><?php _e('Configuración de Flores Coins', 'floresinc-rp'); ?></h2>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Tasa de conversión de Flores Coins', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="number" name="floresinc_rp_settings[points_conversion_rate]" step="0.01" min="0" 
                                value="<?php echo esc_attr($options['points_conversion_rate'] ?? 0.1); ?>" />
                            <p class="description">
                                <?php _e('Valor monetario de cada Flores Coin (por ejemplo, 0.1 significa que 10 Flores Coins = $1).', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Flores Coins por compra', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="number" name="floresinc_rp_settings[points_per_currency]" step="0.1" min="0" 
                                value="<?php echo esc_attr($options['points_per_currency'] ?? 1); ?>" />
                            <p class="description">
                                <?php _e('Cantidad de Flores Coins otorgados por cada peso gastado en la tienda (por defecto: 1 Flores Coin por $1).', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Mínimo de Flores Coins para canjear', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="number" name="floresinc_rp_settings[min_points_redemption]" min="0" 
                                value="<?php echo esc_attr($options['min_points_redemption'] ?? 100); ?>" />
                            <p class="description">
                                <?php _e('Cantidad mínima de Flores Coins que un cliente debe acumular antes de poder canjearlos por descuentos.', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Máximo de Flores Coins por pedido', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="number" name="floresinc_rp_settings[max_points_per_order]" min="0" 
                                value="<?php echo esc_attr($options['max_points_per_order'] ?? 0); ?>" />
                            <p class="description">
                                <?php _e('Número máximo de Flores Coins que un cliente puede canjear en un solo pedido (0 = sin límite).', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Expiración de Flores Coins', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="number" name="floresinc_rp_settings[points_expiry_days]" min="0" 
                                value="<?php echo esc_attr($options['points_expiry_days'] ?? 365); ?>" />
                            <p class="description">
                                <?php _e('Número de días después de los cuales expiran los Flores Coins no utilizados (0 = nunca expiran).', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Otorgar Flores Coins por', 'floresinc-rp'); ?>
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
                            <?php _e('Flores Coins por registro', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="number" name="floresinc_rp_settings[points_registration]" min="0" 
                                value="<?php echo esc_attr($options['points_registration'] ?? 100); ?>" />
                            <p class="description">
                                <?php _e('Cantidad de Flores Coins otorgados cuando un nuevo usuario se registra en la tienda.', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Flores Coins por reseña', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="number" name="floresinc_rp_settings[points_review]" min="0" 
                                value="<?php echo esc_attr($options['points_review'] ?? 50); ?>" />
                            <p class="description">
                                <?php _e('Cantidad de Flores Coins otorgados cuando un cliente escribe una reseña de producto.', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Flores Coins por cumpleaños', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="number" name="floresinc_rp_settings[points_birthday]" min="0" 
                                value="<?php echo esc_attr($options['points_birthday'] ?? 200); ?>" />
                            <p class="description">
                                <?php _e('Cantidad de Flores Coins otorgados automáticamente a un cliente en su fecha de cumpleaños.', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Sección de Referidos -->
            <div id="referral-settings" class="tab-content">
                <h2><?php _e('Configuración del Programa de Referidos', 'floresinc-rp'); ?></h2>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Comisión por referido (primera compra)', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="number" name="floresinc_rp_settings[referral_commission_first]" step="0.01" min="0" max="100" 
                                value="<?php echo esc_attr($options['referral_commission_first'] ?? 10); ?>" />%
                            <p class="description">
                                <?php _e('Porcentaje del valor de la compra que se convierte en Flores Coins para el referidor cuando el referido realiza su primera compra.', 'floresinc-rp'); ?>
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
                                <?php _e('Porcentaje del valor de la compra que se convierte en Flores Coins para el referidor cuando el referido realiza compras posteriores a la primera.', 'floresinc-rp'); ?>
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
                                <?php _e('Número de días durante los cuales un referidor recibe Flores Coins por las compras que realiza su referido (0 = sin límite de tiempo).', 'floresinc-rp'); ?>
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
                                <?php _e('Permitir ganar Flores Coins por referidos de segundo nivel (cuando los usuarios que has referido traen a otros usuarios).', 'floresinc-rp'); ?>
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
                                <?php _e('Porcentaje del valor de la compra que se convierte en Flores Coins cuando los referidos de tus referidos realizan compras.', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Flores Coins por referido de primer nivel', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="number" name="floresinc_rp_settings[signup_points_level1]" min="0" 
                                value="<?php echo esc_attr($options['signup_points_level1'] ?? 100); ?>" />
                            <p class="description">
                                <?php _e('Cantidad de Flores Coins otorgados por cada nuevo usuario que se registra directamente usando tu código de referido (primer nivel).', 'floresinc-rp'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">
                            <?php _e('Flores Coins por referido de segundo nivel', 'floresinc-rp'); ?>
                        </th>
                        <td>
                            <input type="number" name="floresinc_rp_settings[signup_points_level2]" min="0" 
                                value="<?php echo esc_attr($options['signup_points_level2'] ?? 50); ?>" />
                            <p class="description">
                                <?php _e('Cantidad de Flores Coins otorgados por cada nuevo usuario que se registra usando el código de uno de tus referidos (segundo nivel).', 'floresinc-rp'); ?>
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

<!-- Los estilos ahora se cargan desde el archivo floresinc-styles.css -->
