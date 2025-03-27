<?php
/**
 * Funciones para gestionar banners desde WordPress
 * Añadir este código en el archivo functions.php del tema activo
 */

/**
 * Registrar Custom Post Type para Banners
 */
function floresinc_register_banner_post_type() {
    $labels = array(
        'name'               => 'Banners',
        'singular_name'      => 'Banner',
        'menu_name'          => 'Banners',
        'name_admin_bar'     => 'Banner',
        'add_new'            => 'Añadir nuevo',
        'add_new_item'       => 'Añadir nuevo Banner',
        'new_item'           => 'Nuevo Banner',
        'edit_item'          => 'Editar Banner',
        'view_item'          => 'Ver Banner',
        'all_items'          => 'Todos los Banners',
        'search_items'       => 'Buscar Banners',
        'parent_item_colon'  => 'Banner padre:',
        'not_found'          => 'No se encontraron banners',
        'not_found_in_trash' => 'No se encontraron banners en la papelera'
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array('slug' => 'banner'),
        'capability_type'    => 'post',
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => 20,
        'menu_icon'          => 'dashicons-images-alt2',
        'supports'           => array('title'), 
        'show_in_rest'       => true,
    );

    register_post_type('banner', $args);
}
add_action('init', 'floresinc_register_banner_post_type');

/**
 * Añadir metabox para los campos personalizados del banner
 */
function banner_add_meta_box() {
    add_meta_box(
        'banner_metabox',
        'Configuración del Banner',
        'banner_metabox_callback',
        'banner',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'banner_add_meta_box');

/**
 * Callback para el metabox
 */
function banner_metabox_callback($post) {
    // Obtener valores guardados
    $subtitle = get_post_meta($post->ID, '_banner_subtitle', true);
    $cta = get_post_meta($post->ID, '_banner_cta', true);
    $link = get_post_meta($post->ID, '_banner_link', true);
    $order = get_post_meta($post->ID, '_banner_order', true);
    $image = get_post_meta($post->ID, '_banner_image', true);
    $image_mobile = get_post_meta($post->ID, '_banner_image_mobile', true);
    $type = get_post_meta($post->ID, '_banner_type', true);
    $social_networks = get_post_meta($post->ID, '_banner_social_networks', true);
    $carousel_images = get_post_meta($post->ID, '_banner_carousel_images', true);
    
    // Valores por defecto
    if ($order === '') {
        $order = 0;
    }
    
    if ($type === '') {
        $type = 'main';
    }
    
    // Nonce para seguridad
    wp_nonce_field('floresinc_banner_metabox', 'floresinc_banner_metabox_nonce');
    
    // Asegurar que wp.media esté disponible
    wp_enqueue_media();
    
    // Estilos y campos
    ?>
    <style>
        .form-field {
            margin-bottom: 20px;
        }
        .form-field label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .form-field input[type="text"],
        .form-field input[type="number"],
        .form-field textarea,
        .form-field select {
            width: 100%;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .image-preview {
            margin-top: 10px;
            max-width: 400px;
        }
        .image-preview img {
            max-width: 100%;
            height: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
            background: #f9f9f9;
        }
        .banner-image-url,
        .banner-image-mobile-url {
            margin-bottom: 5px;
        }
        .banner-image-upload,
        .banner-image-mobile-upload,
        .banner-image-remove,
        .banner-image-mobile-remove {
            margin-right: 5px;
        }
        /* Estilos para redes sociales */
        .social-networks-container {
            margin-top: 20px;
        }
        .social-network-item {
            background-color: #fff;
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 4px;
            position: relative;
        }
        .social-network-item h4 {
            margin-top: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .social-network-item .remove-social {
            color: #a00;
            text-decoration: none;
            cursor: pointer;
            float: right;
        }
        .social-network-item .remove-social:hover {
            color: #dc3232;
        }
        .add-social-network {
            margin-top: 15px;
            margin-bottom: 15px;
        }
        .social-network-preview {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .social-network-preview .icon-preview {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            color: white;
        }
        .social-network-preview .text-preview {
            flex: 1;
        }
    </style>
    
    <div class="form-field">
        <label for="banner_type">Tipo de Banner:</label>
        <select name="banner_type" id="banner_type">
            <option value="main" <?php selected($type, 'main'); ?>>Principal (Carrusel)</option>
            <option value="middle" <?php selected($type, 'middle'); ?>>Intermedio</option>
            <option value="bottom" <?php selected($type, 'bottom'); ?>>Inferior (Redes Sociales)</option>
        </select>
        <p class="description">El tipo de banner determina dónde se mostrará en la página de inicio.</p>
    </div>
    
    <!-- Campos estándar para todos los tipos excepto 'main' -->
    <div class="standard-fields" id="standard_fields" style="<?php echo $type === 'main' ? 'display: none;' : ''; ?>">
        <div class="form-field">
            <label for="banner_subtitle">Subtítulo:</label>
            <input type="text" name="banner_subtitle" id="banner_subtitle" value="<?php echo esc_attr($subtitle); ?>" />
            <p class="description">Texto secundario que aparecerá en el banner.</p>
        </div>
        
        <div class="form-field">
            <label for="banner_cta">Texto del botón (CTA):</label>
            <input type="text" name="banner_cta" id="banner_cta" value="<?php echo esc_attr($cta); ?>" />
            <p class="description">Texto que aparecerá en el botón de llamada a la acción.</p>
        </div>
        
        <div class="form-field">
            <label for="banner_link">Enlace:</label>
            <input type="text" name="banner_link" id="banner_link" value="<?php echo esc_url($link); ?>" />
            <p class="description">URL a la que se dirigirá al hacer clic en el banner.</p>
        </div>
    </div>
    
    <div class="form-field">
        <label for="banner_order">Orden:</label>
        <input type="number" name="banner_order" id="banner_order" value="<?php echo esc_attr($order); ?>" min="0" step="1" />
        <p class="description">Orden de aparición del banner (menor número = mayor prioridad).</p>
    </div>
    
    <!-- Campos específicos para banners principales (carrusel) -->
    <div class="main-banner-fields" id="main_banner_fields" style="<?php echo $type !== 'main' ? 'display: none;' : ''; ?>">
        <h3>Imágenes del Carrusel</h3>
        <p class="description">Estas imágenes se mostrarán en un carrusel en la parte superior de la página.</p>
        
        <div class="carousel-images-container" id="carousel_images_container">
            <?php
            if (!empty($carousel_images) && is_array($carousel_images)) {
                foreach ($carousel_images as $index => $img) {
                    ?>
                    <div class="carousel-image-item" data-index="<?php echo $index; ?>">
                        <h4>
                            <span class="image-name">Imagen <?php echo $index + 1; ?></span>
                            <a class="remove-image button button-link-delete" title="Eliminar esta imagen" style="color: #a00; text-decoration: none; font-weight: bold; cursor: pointer; float: right;">Eliminar</a>
                        </h4>
                        
                        <div class="form-field image-fields">
                            <label>Imagen:</label>
                            <input type="text" name="carousel_images[<?php echo $index; ?>][url]" value="<?php echo esc_url($img['url']); ?>" class="carousel-image-url" readonly />
                            <button type="button" class="button button-secondary carousel-image-upload">Seleccionar imagen</button>
                            <button type="button" class="button button-secondary carousel-image-remove" <?php echo empty($img['url']) ? 'style="display:none"' : ''; ?>>Quitar imagen</button>
                            <div class="image-preview">
                                <?php if (!empty($img['url'])) : ?>
                                    <img src="<?php echo esc_url($img['url']); ?>" alt="Vista previa" style="max-width: 100%; max-height: 200px;" />
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-field">
                            <label>Título (opcional):</label>
                            <input type="text" name="carousel_images[<?php echo $index; ?>][title]" value="<?php echo esc_attr($img['title']); ?>" />
                        </div>
                        
                        <div class="form-field">
                            <label>Enlace (opcional):</label>
                            <input type="text" name="carousel_images[<?php echo $index; ?>][link]" value="<?php echo esc_url($img['link']); ?>" />
                        </div>

                        <div class="form-field">
                            <label>Descripción:</label>
                            <textarea name="carousel_images[<?php echo $index; ?>][description]" rows="3"><?php echo isset($img['description']) ? esc_textarea($img['description']) : ''; ?></textarea>
                        </div>
                        
                        <div class="form-field">
                            <label>Texto del botón (CTA):</label>
                            <input type="text" name="carousel_images[<?php echo $index; ?>][cta]" value="<?php echo isset($img['cta']) ? esc_attr($img['cta']) : ''; ?>" />
                        </div>
                        
                        <div class="form-field">
                            <label>Subtítulo:</label>
                            <input type="text" name="carousel_images[<?php echo $index; ?>][subtitle]" value="<?php echo isset($img['subtitle']) ? esc_attr($img['subtitle']) : ''; ?>" />
                        </div>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
        
        <button type="button" class="button button-secondary add-carousel-image" id="add_carousel_image">Añadir Imagen al Carrusel</button>
    </div>
    
    <!-- Campos para imagen única (banners intermedios y redes sociales) -->
    <div class="single-image-fields" id="single_image_fields" style="<?php echo $type === 'main' ? 'display: none;' : ''; ?>">
        <div class="form-field image-fields">
            <label for="banner_image">Imagen del Banner:</label>
            <input type="text" name="banner_image" id="banner_image" value="<?php echo esc_url($image); ?>" class="banner-image-url" readonly />
            <button type="button" class="button button-secondary banner-image-upload">Seleccionar imagen</button>
            <button type="button" class="button button-secondary banner-image-remove" <?php echo empty($image) ? 'style="display:none"' : ''; ?>>Quitar imagen</button>
            <div class="image-preview">
                <?php if (!empty($image)) : ?>
                    <img src="<?php echo esc_url($image); ?>" alt="Vista previa" style="max-width: 100%; max-height: 200px;" />
                <?php endif; ?>
            </div>
            <p class="description">Imagen principal del banner. Tamaño recomendado: 1920x600px.</p>
        </div>
        
        <div class="form-field image-fields">
            <label for="banner_image_mobile">Imagen para móviles (opcional):</label>
            <input type="text" name="banner_image_mobile" id="banner_image_mobile" value="<?php echo esc_url($image_mobile); ?>" class="banner-image-mobile-url" readonly />
            <button type="button" class="button button-secondary banner-image-mobile-upload">Seleccionar imagen</button>
            <button type="button" class="button button-secondary banner-image-mobile-remove" <?php echo empty($image_mobile) ? 'style="display:none"' : ''; ?>>Quitar imagen</button>
            <div class="image-preview">
                <?php if (!empty($image_mobile)) : ?>
                    <img src="<?php echo esc_url($image_mobile); ?>" alt="Vista previa móvil" style="max-width: 100%; max-height: 200px;" />
                <?php endif; ?>
            </div>
            <p class="description">Versión optimizada para dispositivos móviles. Tamaño recomendado: 768x500px.</p>
        </div>
    </div>
    
    <!-- Campos específicos para redes sociales (solo visibles cuando el tipo es 'bottom') -->
    <div class="social-fields" id="social_fields">
        <h3>Configuración de Redes Sociales</h3>
        <p class="description">Estos campos solo se utilizan para banners de tipo "Inferior (Redes Sociales)".</p>
        
        <div class="social-networks-container" id="social_networks_container">
            <?php
            if (!empty($social_networks) && is_array($social_networks)) {
                foreach ($social_networks as $index => $network) {
                    ?>
                    <div class="social-network-item" data-index="<?php echo $index; ?>">
                        <h4>
                            <span class="social-name">Red Social <?php echo $index + 1; ?></span>
                            <a class="remove-social button button-link-delete" title="Eliminar esta red social" style="color: #a00; text-decoration: none; font-weight: bold; cursor: pointer; float: right;">Eliminar</a>
                        </h4>
                        
                        <div class="social-network-preview">
                            <div class="icon-preview" style="background-color: <?php echo esc_attr($network['color']); ?>;">
                                <?php 
                                $icon_class = 'dashicons-share'; // Icono por defecto
                                
                                switch($network['icon']) {
                                    case 'facebook':
                                        $icon_class = 'dashicons-facebook';
                                        break;
                                    case 'instagram':
                                        $icon_class = 'dashicons-instagram';
                                        break;
                                    case 'whatsapp':
                                        $icon_class = 'dashicons-whatsapp';
                                        break;
                                    case 'telegram':
                                        $icon_class = 'dashicons-share'; // Usamos un icono similar para Telegram
                                        break;
                                }
                                ?>
                                <i class="dashicons <?php echo esc_attr($icon_class); ?>"></i>
                            </div>
                            <div class="text-preview">
                                <strong><?php echo esc_html($network['title']); ?></strong>
                            </div>
                        </div>
                        
                        <div class="form-field">
                            <label>Título:</label>
                            <input type="text" name="social_networks[<?php echo $index; ?>][title]" value="<?php echo esc_attr($network['title']); ?>" class="social-title" />
                        </div>
                        
                        <div class="form-field">
                            <label>Subtítulo:</label>
                            <input type="text" name="social_networks[<?php echo $index; ?>][subtitle]" value="<?php echo esc_attr($network['subtitle']); ?>" />
                        </div>
                        
                        <div class="form-field">
                            <label>Texto del botón:</label>
                            <input type="text" name="social_networks[<?php echo $index; ?>][cta]" value="<?php echo esc_attr($network['cta']); ?>" />
                        </div>
                        
                        <div class="form-field">
                            <label>Enlace:</label>
                            <input type="text" name="social_networks[<?php echo $index; ?>][link]" value="<?php echo esc_attr($network['link']); ?>" />
                        </div>
                        
                        <div class="form-field">
                            <label>Icono:</label>
                            <select name="social_networks[<?php echo $index; ?>][icon]" class="social-icon">
                                <option value="facebook" <?php selected($network['icon'], 'facebook'); ?>>Facebook</option>
                                <option value="instagram" <?php selected($network['icon'], 'instagram'); ?>>Instagram</option>
                                <option value="whatsapp" <?php selected($network['icon'], 'whatsapp'); ?>>WhatsApp</option>
                                <option value="telegram" <?php selected($network['icon'], 'telegram'); ?>>Telegram</option>
                            </select>
                        </div>
                        
                        <div class="form-field">
                            <label>Color:</label>
                            <input type="color" class="social-color-picker" value="<?php echo esc_attr($network['color']); ?>" />
                            <input type="text" name="social_networks[<?php echo $index; ?>][color]" value="<?php echo esc_attr($network['color']); ?>" class="social-color" />
                        </div>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
        
        <button type="button" class="button button-secondary add-social-network">Añadir Red Social</button>
    </div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Mostrar/ocultar campos según el tipo de banner
    $('#banner_type').on('change', function() {
        var selectedType = $(this).val();
        
        // Mostrar/ocultar campos estándar
        if (selectedType === 'main') {
            $('#standard_fields').hide();
        } else {
            $('#standard_fields').show();
        }
        
        // Mostrar/ocultar campos de redes sociales
        if (selectedType === 'bottom') {
            $('#social_fields').show();
        } else {
            $('#social_fields').hide();
        }
        
        // Mostrar/ocultar campos de imagen única vs carrusel
        if (selectedType === 'main') {
            $('#main_banner_fields').show();
            $('#single_image_fields').hide();
        } else {
            $('#main_banner_fields').hide();
            $('#single_image_fields').show();
        }
    }).trigger('change');
    
    // Contador para las redes sociales
    var socialNetworkCounter = <?php echo !empty($social_networks) ? count($social_networks) : 0; ?>;
    
    // Plantilla para nueva red social
    function getSocialNetworkTemplate(index) {
        return `
            <div class="social-network-item" data-index="${index}">
                <h4>
                    <span class="social-name">Red Social ${index + 1}</span>
                    <a class="remove-social button button-link-delete" title="Eliminar esta red social" style="color: #a00; text-decoration: none; font-weight: bold; cursor: pointer; float: right;">Eliminar</a>
                </h4>
                
                <div class="social-network-preview">
                    <div class="icon-preview" style="background-color: #3b5998;">
                        <i class="dashicons dashicons-facebook"></i>
                    </div>
                    <div class="text-preview">
                        <strong>Nueva Red Social</strong>
                    </div>
                </div>
                
                <div class="form-field">
                    <label>Título:</label>
                    <input type="text" name="social_networks[${index}][title]" value="Nueva Red Social" class="social-title" />
                </div>
                
                <div class="form-field">
                    <label>Subtítulo:</label>
                    <input type="text" name="social_networks[${index}][subtitle]" value="Síguenos en nuestras redes" />
                </div>
                
                <div class="form-field">
                    <label>Texto del botón:</label>
                    <input type="text" name="social_networks[${index}][cta]" value="Seguir" />
                </div>
                
                <div class="form-field">
                    <label>Enlace:</label>
                    <input type="text" name="social_networks[${index}][link]" value="#" />
                </div>
                
                <div class="form-field">
                    <label>Icono:</label>
                    <select name="social_networks[${index}][icon]" class="social-icon">
                        <option value="facebook" selected>Facebook</option>
                        <option value="instagram">Instagram</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="telegram">Telegram</option>
                    </select>
                </div>
                
                <div class="form-field">
                    <label>Color:</label>
                    <input type="color" class="social-color-picker" value="#3b5998" />
                    <input type="text" name="social_networks[${index}][color]" value="#3b5998" class="social-color" />
                </div>
            </div>
        `;
    }
    
    // Añadir nueva red social
    $('.add-social-network').click(function() {
        $('#social_networks_container').append(getSocialNetworkTemplate(socialNetworkCounter));
        initSocialNetworkEvents();
        
        socialNetworkCounter++;
    });
    
    // Inicializar eventos para redes sociales
    function initSocialNetworkEvents() {
        // Eliminar red social
        $('.remove-social').off('click').on('click', function(e) {
            e.preventDefault();
            
            if (confirm('¿Estás seguro de que deseas eliminar esta red social?')) {
                $(this).closest('.social-network-item').fadeOut(300, function() {
                    $(this).remove();
                    updateSocialNetworkNames();
                });
            }
        });
        
        // Actualizar previsualización cuando cambia el título
        $('.social-title').off('input').on('input', function() {
            var title = $(this).val() || 'Sin título';
            $(this).closest('.social-network-item').find('.text-preview strong').text(title);
        });
        
        // Sincronizar color picker con campo de texto
        $('.social-color-picker').off('input').on('input', function() {
            var color = $(this).val();
            $(this).closest('.color-field').find('.social-color').val(color);
            $(this).closest('.social-network-item').find('.icon-preview').css('background-color', color);
        });
        
        $('.social-color').off('input').on('input', function() {
            var color = $(this).val();
            $(this).closest('.color-field').find('.social-color-picker').val(color);
            $(this).closest('.social-network-item').find('.icon-preview').css('background-color', color);
        });
        
        // Cambiar color según la red social seleccionada
        $('.social-icon').off('change').on('change', function() {
            var icon = $(this).val();
            var color = '';
            var iconClass = '';
            
            switch(icon) {
                case 'facebook':
                    color = '#3b5998';
                    iconClass = 'dashicons-facebook';
                    break;
                case 'instagram':
                    color = '#e1306c';
                    iconClass = 'dashicons-instagram';
                    break;
                case 'whatsapp':
                    color = '#25d366';
                    iconClass = 'dashicons-whatsapp';
                    break;
                case 'telegram':
                    color = '#0088cc';
                    iconClass = 'dashicons-share'; // Usamos un icono similar para Telegram
                    break;
            }
            
            if (color) {
                var $item = $(this).closest('.social-network-item');
                $item.find('.social-color').val(color).trigger('input');
                $item.find('.social-color-picker').val(color);
                $item.find('.icon-preview').css('background-color', color);
                
                // Actualizar el icono
                $item.find('.icon-preview i').attr('class', 'dashicons ' + iconClass);
            }
        });
    }
    
    // Actualizar nombres de redes sociales
    function updateSocialNetworkNames() {
        $('.social-network-item').each(function(index) {
            $(this).find('.social-name').text('Red Social ' + (index + 1));
            
            // Actualizar índices en los nombres de los campos
            $(this).find('input, select').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    name = name.replace(/social_networks\[\d+\]/, 'social_networks[' + index + ']');
                    $(this).attr('name', name);
                }
            });
            
            $(this).attr('data-index', index);
        });
    }
    
    // Inicializar eventos para redes sociales existentes
    initSocialNetworkEvents();
    
    // Imagen principal
    $('.banner-image-upload').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var customUploader = wp.media({
            title: 'Seleccionar imagen',
            library: { type: 'image' },
            button: { text: 'Usar esta imagen' },
            multiple: false
        });
        
        customUploader.on('select', function() {
            var attachment = customUploader.state().get('selection').first().toJSON();
            button.prev('.banner-image-url').val(attachment.url);
            button.next('.banner-image-remove').show();
            button.parent().find('.image-preview').html('<img src="' + attachment.url + '" alt="Vista previa" style="max-width: 100%; max-height: 200px;" />');
        });
        
        customUploader.open();
    });

    $('.banner-image-remove').on('click', function(e) {
        e.preventDefault();
        $(this).prev('.banner-image-upload').prev('.banner-image-url').val('');
        $(this).hide();
        $(this).parent().find('.image-preview').html('');
    });

    // Imagen móvil
    $('.banner-image-mobile-upload').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var customUploader = wp.media({
            title: 'Seleccionar imagen para móviles',
            library: { type: 'image' },
            button: { text: 'Usar esta imagen' },
            multiple: false
        });
        
        customUploader.on('select', function() {
            var attachment = customUploader.state().get('selection').first().toJSON();
            button.prev('.banner-image-mobile-url').val(attachment.url);
            button.next('.banner-image-mobile-remove').show();
            button.parent().find('.image-preview').html('<img src="' + attachment.url + '" alt="Vista previa móvil" style="max-width: 100%; max-height: 200px;" />');
        });
        
        customUploader.open();
    });

    $('.banner-image-mobile-remove').on('click', function(e) {
        e.preventDefault();
        $(this).prev('.banner-image-mobile-upload').prev('.banner-image-mobile-url').val('');
        $(this).hide();
        $(this).parent().find('.image-preview').html('');
    });
    
    // Carrusel de imágenes
    var carouselImageCounter = <?php echo !empty($carousel_images) ? count($carousel_images) : 0; ?>;
    
    // Plantilla para nueva imagen del carrusel
    function getCarouselImageTemplate(index) {
        return `
            <div class="carousel-image-item" data-index="${index}">
                <h4>
                    <span class="image-name">Imagen ${index + 1}</span>
                    <a class="remove-image button button-link-delete" title="Eliminar esta imagen" style="color: #a00; text-decoration: none; font-weight: bold; cursor: pointer; float: right;">Eliminar</a>
                </h4>
                
                <div class="form-field image-fields">
                    <label>Imagen:</label>
                    <input type="text" name="carousel_images[${index}][url]" value="" class="carousel-image-url" readonly />
                    <button type="button" class="button button-secondary carousel-image-upload">Seleccionar imagen</button>
                    <button type="button" class="button button-secondary carousel-image-remove" style="display: none;">Quitar imagen</button>
                    <div class="image-preview">
                    </div>
                </div>
                
                <div class="form-field">
                    <label>Título (opcional):</label>
                    <input type="text" name="carousel_images[${index}][title]" value="" />
                </div>
                
                <div class="form-field">
                    <label>Enlace (opcional):</label>
                    <input type="text" name="carousel_images[${index}][link]" value="" />
                </div>

                <div class="form-field">
                    <label>Descripción:</label>
                    <textarea name="carousel_images[${index}][description]" rows="3"></textarea>
                </div>
                
                <div class="form-field">
                    <label>Texto del botón (CTA):</label>
                    <input type="text" name="carousel_images[${index}][cta]" value="" />
                </div>
                
                <div class="form-field">
                    <label>Subtítulo:</label>
                    <input type="text" name="carousel_images[${index}][subtitle]" value="" />
                </div>
            </div>
        `;
    }
    
    // Añadir nueva imagen al carrusel
    $('#add_carousel_image').on('click', function() {
        // Verificar si ya hay 5 imágenes
        if ($('.carousel-image-item').length >= 5) {
            alert('Solo se permiten un máximo de 5 imágenes en el carrusel.');
            return;
        }
        
        $('#carousel_images_container').append(getCarouselImageTemplate(carouselImageCounter));
        initCarouselImageEvents();
        
        carouselImageCounter++;
    });
    
    // Inicializar eventos para imágenes del carrusel
    function initCarouselImageEvents() {
        // Eliminar imagen del carrusel
        $('.remove-image').off('click').on('click', function(e) {
            e.preventDefault();
            
            if (confirm('¿Estás seguro de que deseas eliminar esta imagen?')) {
                $(this).closest('.carousel-image-item').fadeOut(300, function() {
                    $(this).remove();
                    updateCarouselImageNames();
                });
            }
        });
        
        // Seleccionar imagen del carrusel
        $('.carousel-image-upload').off('click').on('click', function(e) {
            e.preventDefault();
            
            var button = $(this);
            var customUploader = wp.media({
                title: 'Seleccionar imagen',
                library: { type: 'image' },
                button: { text: 'Usar esta imagen' },
                multiple: false
            });
            
            customUploader.on('select', function() {
                var attachment = customUploader.state().get('selection').first().toJSON();
                button.prev('.carousel-image-url').val(attachment.url);
                button.next('.carousel-image-remove').show();
                button.parent().find('.image-preview').html('<img src="' + attachment.url + '" alt="Vista previa" style="max-width: 100%; max-height: 200px;" />');
            });
            
            customUploader.open();
        });
        
        // Quitar imagen del carrusel
        $('.carousel-image-remove').off('click').on('click', function(e) {
            e.preventDefault();
            $(this).prev('.carousel-image-upload').prev('.carousel-image-url').val('');
            $(this).hide();
            $(this).parent().find('.image-preview').html('');
        });
    }
    
    // Actualizar nombres de imágenes del carrusel
    function updateCarouselImageNames() {
        $('.carousel-image-item').each(function(index) {
            $(this).find('.image-name').text('Imagen ' + (index + 1));
            
            // Actualizar índices en los nombres de los campos
            $(this).find('input').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    name = name.replace(/carousel_images\[\d+\]/, 'carousel_images[' + index + ']');
                    $(this).attr('name', name);
                }
            });
            
            $(this).attr('data-index', index);
        });
    }
    
    // Inicializar eventos para imágenes del carrusel existentes
    initCarouselImageEvents();
});
</script>
    <?php
}

/**
 * Verificar si ya existe un banner del tipo seleccionado
 */
function check_existing_banner_type() {
    if (!isset($_POST['banner_type']) || !isset($_POST['post_type']) || $_POST['post_type'] !== 'banner') {
        return;
    }
    
    $selected_type = sanitize_text_field($_POST['banner_type']);
    $post_id = isset($_POST['post_ID']) ? intval($_POST['post_ID']) : 0;
    
    // Buscar banners existentes del mismo tipo
    $args = array(
        'post_type' => 'banner',
        'posts_per_page' => -1,
        'meta_key' => '_banner_type',
        'meta_value' => $selected_type,
        'post_status' => 'publish',
        'post__not_in' => array($post_id) // Excluir el banner actual
    );
    
    $existing_banners = get_posts($args);
    
    if (!empty($existing_banners)) {
        // Ya existe un banner de este tipo
        wp_die(
            sprintf(
                'Ya existe un banner de tipo "%s". Solo se permite un banner por tipo. <a href="%s">Volver</a>',
                $selected_type === 'main' ? 'Principal (Superior)' : ($selected_type === 'middle' ? 'Intermedio' : 'Inferior (Redes Sociales)'),
                admin_url('edit.php?post_type=banner')
            )
        );
    }
}
add_action('admin_init', 'check_existing_banner_type');

/**
 * Guardar los datos de los campos personalizados
 */
function banner_save_meta($post_id) {
    // Verificar nonce
    if (!isset($_POST['floresinc_banner_metabox_nonce']) || !wp_verify_nonce($_POST['floresinc_banner_metabox_nonce'], 'floresinc_banner_metabox')) {
        return $post_id;
    }

    // Verificar autoguardado
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return $post_id;
    }

    // Verificar permisos
    if ('banner' == $_POST['post_type'] && !current_user_can('edit_post', $post_id)) {
        return $post_id;
    }

    // Guardar el tipo de banner primero
    if (isset($_POST['banner_type'])) {
        $banner_type = sanitize_text_field($_POST['banner_type']);
        update_post_meta($post_id, '_banner_type', $banner_type);
        
        // Si es un banner de tipo "bottom" (redes sociales), eliminar cualquier imagen asociada
        if ($banner_type === 'bottom') {
            update_post_meta($post_id, '_banner_image', '');
            update_post_meta($post_id, '_banner_image_mobile', '');
        }
    }

    // Guardar campos
    $fields = array(
        '_banner_subtitle' => 'banner_subtitle',
        '_banner_cta' => 'banner_cta',
        '_banner_link' => 'banner_link',
        '_banner_order' => 'banner_order',
    );

    foreach ($fields as $meta_key => $post_key) {
        if (isset($_POST[$post_key])) {
            update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$post_key]));
        }
    }
    
    // Guardar imágenes solo si no es un banner de tipo "bottom"
    if (!isset($banner_type) || $banner_type !== 'bottom') {
        if (isset($_POST['banner_image'])) {
            update_post_meta($post_id, '_banner_image', esc_url_raw($_POST['banner_image']));
        }
        
        if (isset($_POST['banner_image_mobile'])) {
            update_post_meta($post_id, '_banner_image_mobile', esc_url_raw($_POST['banner_image_mobile']));
        }
    }

    // Guardar imágenes del carrusel
    if (isset($_POST['carousel_images'])) {
        $carousel_images = array();
        foreach ($_POST['carousel_images'] as $index => $img) {
            $carousel_images[$index] = array(
                'url' => sanitize_text_field($img['url']),
                'title' => sanitize_text_field($img['title']),
                'link' => sanitize_text_field($img['link']),
                'description' => sanitize_text_field($img['description']),
                'cta' => sanitize_text_field($img['cta']),
                'subtitle' => sanitize_text_field($img['subtitle']),
            );
        }
        update_post_meta($post_id, '_banner_carousel_images', $carousel_images);
    } else {
        delete_post_meta($post_id, '_banner_carousel_images');
    }

    // Guardar redes sociales
    if (isset($_POST['social_networks'])) {
        $social_networks = array();
        foreach ($_POST['social_networks'] as $index => $network) {
            $social_networks[$index] = array(
                'title' => sanitize_text_field($network['title']),
                'subtitle' => sanitize_text_field($network['subtitle']),
                'cta' => sanitize_text_field($network['cta']),
                'link' => sanitize_text_field($network['link']),
                'icon' => sanitize_text_field($network['icon']),
                'color' => sanitize_text_field($network['color']),
            );
        }
        update_post_meta($post_id, '_banner_social_networks', $social_networks);
    } else {
        delete_post_meta($post_id, '_banner_social_networks');
    }
}
add_action('save_post', 'banner_save_meta');

/**
 * Añadir columnas personalizadas a la lista de banners
 */
function banner_custom_columns($columns) {
    $new_columns = array();
    foreach ($columns as $key => $value) {
        if ($key == 'date') {
            $new_columns['banner_image'] = 'Imagen';
            $new_columns['banner_type'] = 'Tipo';
            $new_columns['banner_order'] = 'Orden';
        }
        $new_columns[$key] = $value;
    }
    return $new_columns;
}
add_filter('manage_banner_posts_columns', 'banner_custom_columns');

/**
 * Mostrar contenido de columnas personalizadas
 */
function banner_custom_column_content($column, $post_id) {
    switch ($column) {
        case 'banner_type':
            $type = get_post_meta($post_id, '_banner_type', true);
            $type_labels = [
                'main' => 'Principal',
                'middle' => 'Intermedio',
                'bottom' => 'Redes Sociales'
            ];
            echo isset($type_labels[$type]) ? $type_labels[$type] : $type;
            break;
        case 'banner_order':
            $order = get_post_meta($post_id, '_banner_order', true);
            echo $order !== '' ? $order : '0';
            break;
        case 'banner_image':
            $type = get_post_meta($post_id, '_banner_type', true);
            
            if ($type === 'main') {
                // Para banners principales, mostrar imágenes del carrusel
                $carousel_images = get_post_meta($post_id, '_banner_carousel_images', true);
                
                if (!empty($carousel_images) && is_array($carousel_images)) {
                    echo '<div style="display: flex; flex-wrap: wrap; gap: 5px;">';
                    
                    $count = 0;
                    $total = count($carousel_images);
                    
                    foreach ($carousel_images as $img) {
                        if ($count < 4) {
                            if (!empty($img['url'])) {
                                echo '<img src="' . esc_url($img['url']) . '" alt="Carrusel" style="width: 60px; height: 60px; object-fit: cover; border-radius: 3px;" />';
                            }
                            $count++;
                        } else {
                            break;
                        }
                    }
                    
                    if ($total > 4) {
                        echo '<div style="width: 60px; height: 60px; background-color: #f0f0f0; border-radius: 3px; display: flex; justify-content: center; align-items: center; font-weight: bold; color: #555;">+' . ($total - 4) . '</div>';
                    }
                    
                    echo '</div>';
                } else {
                    echo '<span style="color: #dd3333;">Sin imágenes en el carrusel</span>';
                }
            } else {
                // Para otros tipos de banner, mostrar la imagen única
                $image = get_post_meta($post_id, '_banner_image', true);
                if (!empty($image)) {
                    echo '<img src="' . esc_url($image) . '" alt="Banner" style="max-width: 100px; max-height: 60px; object-fit: cover; border-radius: 3px;" />';
                } else {
                    echo '<span style="color: #dd3333;">Sin imagen</span>';
                }
            }
            break;
    }
}
add_action('manage_banner_posts_custom_column', 'banner_custom_column_content', 10, 2);

/**
 * Hacer las columnas ordenables
 */
function banner_sortable_columns($columns) {
    $columns['banner_type'] = 'banner_type';
    $columns['banner_order'] = 'banner_order';
    return $columns;
}
add_filter('manage_edit-banner_sortable_columns', 'banner_sortable_columns');

/**
 * Personalizar la consulta para ordenar por las columnas personalizadas
 */
function banner_custom_orderby($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    if ($query->get('post_type') !== 'banner') {
        return;
    }

    $orderby = $query->get('orderby');

    if ('banner_type' === $orderby) {
        $query->set('meta_key', '_banner_type');
        $query->set('orderby', 'meta_value');
    }

    if ('banner_order' === $orderby) {
        $query->set('meta_key', '_banner_order');
        $query->set('orderby', 'meta_value_num');
    }
}
add_action('pre_get_posts', 'banner_custom_orderby');

/**
 * Registrar endpoint REST API para banners
 */
function floresinc_register_banner_rest_route() {
    register_rest_route('floresinc/v1', '/banners', array(
        'methods' => 'GET',
        'callback' => 'floresinc_get_banners',
        'permission_callback' => '__return_true',
    ));
    
    // Endpoint para obtener banners por tipo
    register_rest_route('floresinc/v1', '/banners/(?P<type>[a-zA-Z0-9-]+)', array(
        'methods' => 'GET',
        'callback' => 'floresinc_get_banners_by_type',
        'permission_callback' => '__return_true',
        'args' => array(
            'type' => array(
                'validate_callback' => function($param) {
                    return is_string($param);
                }
            ),
        ),
    ));
}
add_action('rest_api_init', 'floresinc_register_banner_rest_route', 10);

/**
 * Callback para el endpoint de banners
 */
function floresinc_get_banners() {
    $args = array(
        'post_type' => 'banner',
        'posts_per_page' => -1,
        'orderby' => 'meta_value_num',
        'meta_key' => '_banner_order',
        'order' => 'ASC',
        'post_status' => 'publish',
    );
    
    $banners = get_posts($args);
    
    if (empty($banners)) {
        return new WP_REST_Response(array(), 200);
    }
    
    $data = array();
    
    foreach ($banners as $banner) {
        // Obtener la URL de la imagen destacada (thumbnail)
        $featured_image_url = '';
        
        if (has_post_thumbnail($banner->ID)) {
            // Obtener la URL de la imagen destacada en tamaño completo
            $featured_image_url = get_the_post_thumbnail_url($banner->ID, 'full');
        }
        
        // Usar los campos personalizados si están definidos, o la imagen destacada como respaldo
        $image = get_post_meta($banner->ID, '_banner_image', true);
        $image_mobile = get_post_meta($banner->ID, '_banner_image_mobile', true);
        
        // Si no hay imagen en el campo personalizado, usar la imagen destacada
        if (empty($image) && !empty($featured_image_url)) {
            $image = $featured_image_url;
        }
        
        // Si no hay imagen móvil, usar la imagen normal o la destacada
        if (empty($image_mobile)) {
            $image_mobile = $image;
        }
        
        $banner_data = array(
            'id' => $banner->ID,
            'title' => $banner->post_title,
            'subtitle' => get_post_meta($banner->ID, '_banner_subtitle', true),
            'cta' => get_post_meta($banner->ID, '_banner_cta', true),
            'link' => get_post_meta($banner->ID, '_banner_link', true),
            'image' => $image,
            'imageMobile' => $image_mobile,
            'order' => (int) get_post_meta($banner->ID, '_banner_order', true),
            'type' => get_post_meta($banner->ID, '_banner_type', true),
            'socialNetworks' => get_post_meta($banner->ID, '_banner_social_networks', true),
            'carouselImages' => get_post_meta($banner->ID, '_banner_carousel_images', true),
        );
        
        $data[] = $banner_data;
    }
    
    return new WP_REST_Response($data, 200);
}

/**
 * Callback para el endpoint de banners por tipo
 */
function floresinc_get_banners_by_type($request) {
    $type = $request->get_param('type');
    
    if (empty($type)) {
        return new WP_Error('invalid_type', 'Tipo de banner no válido', array('status' => 400));
    }
    
    $args = array(
        'post_type' => 'banner',
        'posts_per_page' => -1,
        'orderby' => 'meta_value_num',
        'meta_key' => '_banner_order',
        'order' => 'ASC',
        'post_status' => 'publish',
        'meta_query' => array(
            array(
                'key' => '_banner_type',
                'value' => $type,
                'compare' => '=',
            ),
        ),
    );
    
    $banners = get_posts($args);
    
    if (empty($banners)) {
        return new WP_REST_Response(array(), 200);
    }
    
    $data = array();
    
    foreach ($banners as $banner) {
        // Obtener la URL de la imagen destacada (thumbnail)
        $featured_image_url = '';
        
        if (has_post_thumbnail($banner->ID)) {
            // Obtener la URL de la imagen destacada en tamaño completo
            $featured_image_url = get_the_post_thumbnail_url($banner->ID, 'full');
        }
        
        // Usar los campos personalizados si están definidos, o la imagen destacada como respaldo
        $image = get_post_meta($banner->ID, '_banner_image', true);
        $image_mobile = get_post_meta($banner->ID, '_banner_image_mobile', true);
        
        // Si no hay imagen en el campo personalizado, usar la imagen destacada
        if (empty($image) && !empty($featured_image_url)) {
            $image = $featured_image_url;
        }
        
        // Si no hay imagen móvil, usar la imagen normal o la destacada
        if (empty($image_mobile)) {
            $image_mobile = $image;
        }
        
        // Obtener imágenes del carrusel si existen
        $carousel_images = get_post_meta($banner->ID, '_banner_carousel_images', true);
        
        // Verificar que las imágenes del carrusel tengan todas las propiedades necesarias
        if (!empty($carousel_images) && is_array($carousel_images)) {
            foreach ($carousel_images as $key => $img) {
                // Asegurar que todas las propiedades estén definidas
                if (!isset($img['description'])) {
                    $carousel_images[$key]['description'] = '';
                }
                if (!isset($img['subtitle'])) {
                    $carousel_images[$key]['subtitle'] = '';
                }
                if (!isset($img['cta'])) {
                    $carousel_images[$key]['cta'] = '';
                }
                
                // Validar que la URL de la imagen exista
                if (empty($img['url'])) {
                    // Eliminar imágenes sin URL
                    unset($carousel_images[$key]);
                }
            }
            
            // Reindexar el array si se eliminaron elementos
            if (count($carousel_images) !== count($carousel_images, COUNT_RECURSIVE)) {
                $carousel_images = array_values($carousel_images);
            }
        }
        
        $banner_data = array(
            'id' => $banner->ID,
            'title' => $banner->post_title,
            'subtitle' => get_post_meta($banner->ID, '_banner_subtitle', true),
            'description' => get_post_meta($banner->ID, '_banner_description', true) ?: '',
            'cta' => get_post_meta($banner->ID, '_banner_cta', true),
            'link' => get_post_meta($banner->ID, '_banner_link', true),
            'image' => $image,
            'imageMobile' => $image_mobile,
            'order' => (int) get_post_meta($banner->ID, '_banner_order', true),
            'type' => get_post_meta($banner->ID, '_banner_type', true),
            'socialNetworks' => get_post_meta($banner->ID, '_banner_social_networks', true),
            'carouselImages' => $carousel_images,
        );
        
        $data[] = $banner_data;
    }
    
    return new WP_REST_Response($data, 200);
}
