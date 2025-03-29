<?php
/**
 * Funcionalidad para configurar permisos del Gestor de la tienda
 */

// Evitar acceso directo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Añadir sección de configuración al perfil de usuario
 */
function floresinc_add_shop_manager_settings($user) {
    // Verificar si el usuario es administrador
    if (!current_user_can('manage_options')) {
        return;
    }

    // Verificar si el usuario actual es un Gestor de la tienda
    if (in_array('shop_manager', (array) $user->roles)) {
        // Obtener la configuración actual
        $settings = get_user_meta($user->ID, '_floresinc_shop_manager_settings', true);
        if (!is_array($settings)) {
            $settings = array();
        }

        // Asegurarse de que los menús estén cargados
        if (!did_action('admin_menu')) {
            do_action('admin_menu');
        }

        global $menu, $submenu;

        // Mostrar el formulario
        ?>
        <h3>Configuración del Gestor de la tienda</h3>
        <table class="form-table">
            <tr>
                <th><label>Secciones visibles</label></th>
                <td>
                    <div style="max-height: 500px; overflow-y: auto; padding: 10px; border: 1px solid #ddd;">
                        <ul style="list-style-type: none; margin: 0; padding: 0;">
                            <?php 
                            // Recorrer el menú principal
                            foreach ($menu as $menu_item) {
                                if (empty($menu_item[0]) || empty($menu_item[2])) continue;
                                
                                $parent_slug = $menu_item[2];
                                $parent_label = strip_tags($menu_item[0]);
                                
                                // Ignorar separadores
                                if ($parent_label === '') continue;
                                ?>
                                <li style="margin-bottom: 10px;">
                                    <label>
                                        <input type="checkbox" name="floresinc_shop_manager_settings[]" value="<?php echo esc_attr($parent_slug); ?>" <?php checked(in_array($parent_slug, $settings)); ?> />
                                        <strong><?php echo esc_html($parent_label); ?></strong>
                                    </label>
                                    
                                    <?php 
                                    // Mostrar submenús si existen
                                    if (isset($submenu[$parent_slug]) && is_array($submenu[$parent_slug]) && !empty($submenu[$parent_slug])) {
                                        ?>
                                        <ul style="list-style-type: none; margin: 5px 0 5px 20px; padding: 0;">
                                            <?php foreach ($submenu[$parent_slug] as $submenu_item) {
                                                if (empty($submenu_item[0]) || empty($submenu_item[2])) continue;
                                                
                                                $submenu_slug = $submenu_item[2];
                                                $submenu_label = strip_tags($submenu_item[0]);
                                                ?>
                                                <li>
                                                    <label>
                                                        <input type="checkbox" name="floresinc_shop_manager_settings[]" value="<?php echo esc_attr($submenu_slug); ?>" <?php checked(in_array($submenu_slug, $settings)); ?> />
                                                        <?php echo esc_html($submenu_label); ?>
                                                    </label>
                                                </li>
                                            <?php } ?>
                                        </ul>
                                    <?php } ?>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                    <p class="description">Selecciona las secciones que este Gestor de la tienda podrá ver en la barra lateral.</p>
                </td>
            </tr>
        </table>
        <?php
    }
}

/**
 * Guardar la configuración del Gestor de la tienda
 */
function floresinc_save_shop_manager_settings($user_id) {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_POST['floresinc_shop_manager_settings'])) {
        $settings = array_map('sanitize_text_field', $_POST['floresinc_shop_manager_settings']);
        update_user_meta($user_id, '_floresinc_shop_manager_settings', $settings);
    } else {
        // Si no hay configuración, guardar un array vacío para indicar que no tiene permisos
        update_user_meta($user_id, '_floresinc_shop_manager_settings', array());
    }
}

/**
 * Aplicar los permisos configurados
 */
function floresinc_apply_shop_manager_settings() {
    $user = wp_get_current_user();
    if (in_array('shop_manager', (array) $user->roles)) {
        global $menu, $submenu;
        
        // Obtener la configuración guardada
        $settings = get_user_meta($user->ID, '_floresinc_shop_manager_settings', true);
        
        // Si no hay configuración específica, ocultar todo excepto el perfil
        if (!is_array($settings) || empty($settings)) {
            // Ocultar todo el menú excepto el perfil
            foreach ($menu as $index => $menu_item) {
                if (!empty($menu_item[2]) && $menu_item[2] !== 'profile.php') {
                    remove_menu_page($menu_item[2]);
                }
            }
            return;
        }
        
        // Ocultar las páginas principales no seleccionadas
        foreach ($menu as $index => $menu_item) {
            if (!empty($menu_item[2]) && !in_array($menu_item[2], $settings)) {
                remove_menu_page($menu_item[2]);
            }
        }
        
        // Ocultar los submenús no seleccionados
        if (is_array($submenu)) {
            foreach ($submenu as $parent_slug => $submenu_items) {
                // Si el menú principal está visible
                if (in_array($parent_slug, $settings)) {
                    foreach ($submenu_items as $index => $submenu_item) {
                        if (!empty($submenu_item[2]) && !in_array($submenu_item[2], $settings)) {
                            remove_submenu_page($parent_slug, $submenu_item[2]);
                        }
                    }
                }
            }
        }
    }
}

// Añadir los hooks
add_action('show_user_profile', 'floresinc_add_shop_manager_settings');
add_action('edit_user_profile', 'floresinc_add_shop_manager_settings');
add_action('personal_options_update', 'floresinc_save_shop_manager_settings');
add_action('edit_user_profile_update', 'floresinc_save_shop_manager_settings');
add_action('admin_menu', 'floresinc_apply_shop_manager_settings', 999);
