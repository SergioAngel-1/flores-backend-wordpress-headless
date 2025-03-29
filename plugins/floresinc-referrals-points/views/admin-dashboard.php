<div class="floresinc-rp-dashboard">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="floresinc-rp-stats-cards">
        <div class="floresinc-rp-card">
            <div class="card-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="card-content">
                <h3><?php _e('Usuarios con Puntos', 'floresinc-rp'); ?></h3>
                <div class="card-value"><?php echo esc_html($users_with_points); ?></div>
            </div>
        </div>
        
        <div class="floresinc-rp-card">
            <div class="card-icon">
                <span class="dashicons dashicons-star-filled"></span>
            </div>
            <div class="card-content">
                <h3><?php _e('Puntos Activos', 'floresinc-rp'); ?></h3>
                <div class="card-value"><?php echo esc_html(number_format($total_active_points)); ?></div>
            </div>
        </div>
        
        <div class="floresinc-rp-card">
            <div class="card-icon">
                <span class="dashicons dashicons-chart-line"></span>
            </div>
            <div class="card-content">
                <h3><?php _e('Transacciones', 'floresinc-rp'); ?></h3>
                <div class="card-value"><?php echo esc_html(number_format($total_transactions)); ?></div>
            </div>
        </div>
        
        <div class="floresinc-rp-card">
            <div class="card-icon">
                <span class="dashicons dashicons-networking"></span>
            </div>
            <div class="card-content">
                <h3><?php _e('Relaciones de Referidos', 'floresinc-rp'); ?></h3>
                <div class="card-value"><?php echo esc_html(number_format($total_referrals)); ?></div>
            </div>
        </div>
    </div>
    
    <div class="floresinc-rp-dashboard-widgets">
        <!-- Transacciones recientes -->
        <div class="floresinc-rp-widget">
            <h2><?php _e('Transacciones Recientes', 'floresinc-rp'); ?></h2>
            
            <?php if (empty($recent_transactions)) : ?>
                <p><?php _e('No hay transacciones recientes.', 'floresinc-rp'); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Usuario', 'floresinc-rp'); ?></th>
                            <th><?php _e('Fecha', 'floresinc-rp'); ?></th>
                            <th><?php _e('Tipo', 'floresinc-rp'); ?></th>
                            <th><?php _e('Puntos', 'floresinc-rp'); ?></th>
                            <th><?php _e('Descripción', 'floresinc-rp'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_transactions as $transaction) : ?>
                            <tr>
                                <td>
                                    <a href="<?php echo get_edit_user_link($transaction->user_id); ?>">
                                        <?php echo esc_html($transaction->display_name); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($transaction->created_at)); ?>
                                </td>
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
                                        case 'admin_add':
                                            _e('Añadido por admin', 'floresinc-rp');
                                            break;
                                        case 'admin_deduct':
                                            _e('Deducido por admin', 'floresinc-rp');
                                            break;
                                        case 'referral':
                                            _e('Comisión de referido', 'floresinc-rp');
                                            break;
                                        default:
                                            echo ucfirst($transaction->type);
                                    }
                                    ?>
                                </td>
                                <td>
                                    <span style="color: <?php echo $transaction->points >= 0 ? 'green' : 'red'; ?>">
                                        <?php echo $transaction->points; ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($transaction->description); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <p class="floresinc-rp-view-all">
                    <a href="<?php echo admin_url('admin.php?page=floresinc-rp-transactions'); ?>" class="button button-secondary">
                        <?php _e('Ver Todas las Transacciones', 'floresinc-rp'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
        
        <!-- Usuarios con más puntos -->
        <div class="floresinc-rp-widget">
            <h2><?php _e('Usuarios con Más Puntos', 'floresinc-rp'); ?></h2>
            
            <?php if (empty($top_users)) : ?>
                <p><?php _e('No hay usuarios con puntos.', 'floresinc-rp'); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Usuario', 'floresinc-rp'); ?></th>
                            <th><?php _e('Email', 'floresinc-rp'); ?></th>
                            <th><?php _e('Saldo de Puntos', 'floresinc-rp'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_users as $user) : ?>
                            <tr>
                                <td>
                                    <a href="<?php echo get_edit_user_link($user->user_id); ?>">
                                        <?php echo esc_html($user->display_name); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html($user->user_email); ?></td>
                                <td>
                                    <strong><?php echo number_format($user->points); ?></strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- Mejores referidores -->
        <div class="floresinc-rp-widget">
            <h2><?php _e('Mejores Referidores', 'floresinc-rp'); ?></h2>
            
            <?php if (empty($top_referrers)) : ?>
                <p><?php _e('No hay referidores activos.', 'floresinc-rp'); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Usuario', 'floresinc-rp'); ?></th>
                            <th><?php _e('Referidos', 'floresinc-rp'); ?></th>
                            <th><?php _e('Acciones', 'floresinc-rp'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_referrers as $referrer) : ?>
                            <tr>
                                <td>
                                    <a href="<?php echo get_edit_user_link($referrer->referrer_id); ?>">
                                        <?php echo esc_html($referrer->display_name); ?>
                                    </a>
                                </td>
                                <td>
                                    <strong><?php echo number_format($referrer->total_referrals); ?></strong>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=floresinc-rp-network&referrer=' . $referrer->referrer_id); ?>" class="button button-small">
                                        <?php _e('Ver Referidos', 'floresinc-rp'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <p class="floresinc-rp-view-all">
                    <a href="<?php echo admin_url('admin.php?page=floresinc-rp-network'); ?>" class="button button-secondary">
                        <?php _e('Ver Red de Referidos', 'floresinc-rp'); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
        
        <!-- Formulario para asignar puntos a usuarios -->
        <div class="floresinc-rp-widget">
            <h2><?php _e('Asignar Puntos a Usuarios', 'floresinc-rp'); ?></h2>
            
            <?php
            // Mostrar errores/mensajes
            settings_errors('floresinc_rp_admin_points');
            ?>
            
            <form method="post" action="" class="floresinc-rp-admin-points-form">
                <?php wp_nonce_field('floresinc_rp_admin_points_action', 'floresinc_rp_admin_points_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="user_id"><?php _e('Usuario', 'floresinc-rp'); ?></label>
                        </th>
                        <td>
                            <select name="user_id" id="user_id" class="regular-text" required>
                                <option value=""><?php _e('Seleccionar usuario...', 'floresinc-rp'); ?></option>
                                <?php
                                // Obtener usuarios
                                $users = get_users([
                                    'orderby' => 'display_name',
                                    'order' => 'ASC',
                                    'fields' => ['ID', 'display_name', 'user_email']
                                ]);
                                
                                foreach ($users as $user) {
                                    printf(
                                        '<option value="%d">%s (%s)</option>',
                                        $user->ID,
                                        $user->display_name,
                                        $user->user_email
                                    );
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="action_type"><?php _e('Acción', 'floresinc-rp'); ?></label>
                        </th>
                        <td>
                            <select name="action_type" id="action_type" required>
                                <option value="add"><?php _e('Añadir puntos', 'floresinc-rp'); ?></option>
                                <option value="deduct"><?php _e('Deducir puntos', 'floresinc-rp'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="points"><?php _e('Cantidad de puntos', 'floresinc-rp'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="points" id="points" class="regular-text" min="1" step="1" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="description"><?php _e('Descripción', 'floresinc-rp'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="description" id="description" class="regular-text" required>
                            <p class="description"><?php _e('Motivo por el que se añaden/deducen los puntos', 'floresinc-rp'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Asignar Puntos', 'floresinc-rp'); ?>">
                </p>
            </form>
        </div>
    </div>
</div>

<style type="text/css">
    .floresinc-rp-dashboard {
        margin: 20px 0;
    }
    
    .floresinc-rp-stats-cards {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .floresinc-rp-card {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        flex: 1;
        min-width: 200px;
        padding: 20px;
        display: flex;
        align-items: center;
    }
    
    .card-icon {
        margin-right: 15px;
    }
    
    .card-icon .dashicons {
        font-size: 36px;
        width: 36px;
        height: 36px;
        color: #0073aa;
    }
    
    .card-content h3 {
        margin: 0 0 10px 0;
        font-size: 14px;
        color: #23282d;
    }
    
    .card-value {
        font-size: 24px;
        font-weight: bold;
        color: #0073aa;
    }
    
    .floresinc-rp-dashboard-widgets {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(500px, 1fr));
        gap: 20px;
    }
    
    .floresinc-rp-widget {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .floresinc-rp-widget h2 {
        margin-top: 0;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }
    
    .floresinc-rp-view-all {
        margin-top: 15px;
        text-align: right;
    }
    
    @media screen and (max-width: 782px) {
        .floresinc-rp-dashboard-widgets {
            grid-template-columns: 1fr;
        }
    }
</style>
