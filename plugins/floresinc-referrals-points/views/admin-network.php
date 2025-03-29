<div class="wrap floresinc-rp-network">
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
    
    <!-- Formulario de búsqueda -->
    <form id="referrals-filter" method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
        <?php
        // Si hay un referrer_id, mantenerlo en la búsqueda
        if (isset($_REQUEST['referrer']) && !empty($_REQUEST['referrer'])) {
            echo '<input type="hidden" name="referrer" value="' . esc_attr($_REQUEST['referrer']) . '" />';
        }
        ?>
        
        <div class="search-box">
            <input type="search" id="referrals-search-input" name="s" 
                   value="<?php echo isset($_REQUEST['s']) ? esc_attr($_REQUEST['s']) : ''; ?>" 
                   placeholder="<?php esc_attr_e('Buscar por nombre, email o código...', 'floresinc-rp'); ?>" />
            <?php submit_button(__('Buscar', 'floresinc-rp'), 'button', false, false, array('id' => 'search-submit')); ?>
        </div>
    </form>
    
    <!-- Estadísticas de referidos -->
    <div class="floresinc-rp-stats-cards">
        <div class="floresinc-rp-card">
            <div class="card-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="card-content">
                <h3><?php _e('Total Usuarios', 'floresinc-rp'); ?></h3>
                <div class="card-value"><?php echo esc_html($total_users); ?></div>
            </div>
        </div>
        
        <div class="floresinc-rp-card">
            <div class="card-icon">
                <span class="dashicons dashicons-networking"></span>
            </div>
            <div class="card-content">
                <h3><?php _e('Con Referidor', 'floresinc-rp'); ?></h3>
                <div class="card-value"><?php echo esc_html($users_with_referrer); ?></div>
            </div>
        </div>
        
        <div class="floresinc-rp-card">
            <div class="card-icon">
                <span class="dashicons dashicons-chart-bar"></span>
            </div>
            <div class="card-content">
                <h3><?php _e('Tasa de Referidos', 'floresinc-rp'); ?></h3>
                <div class="card-value"><?php echo esc_html($referral_rate); ?>%</div>
            </div>
        </div>
    </div>
    
    <!-- Tabla de usuarios con referido -->
    <div class="floresinc-rp-table-container">
        <?php
        // Mostrar la tabla
        $referrals_table = new FloresInc_RP_Referrals_Table();
        $referrals_table->prepare_items();
        $referrals_table->display();
        ?>
    </div>
</div>

<style type="text/css">
    .floresinc-rp-network {
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
    
    .floresinc-rp-table-container {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        padding: 20px;
    }
    
    .search-box {
        float: right;
        margin-bottom: 15px;
    }
    
    @media screen and (max-width: 782px) {
        .floresinc-rp-stats-cards {
            flex-direction: column;
        }
        
        .search-box {
            float: none;
            margin-bottom: 20px;
        }
    }
</style>
