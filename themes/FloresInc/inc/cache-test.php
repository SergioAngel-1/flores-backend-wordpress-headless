<?php
/**
 * Cache Test Script
 * 
 * Este script permite verificar que el sistema de caché de la API está funcionando correctamente.
 * Para usar, añade este código al final de functions.php o crea una página de plantilla en el tema.
 */

// Evitar acceso directo al archivo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Agrega una página de administración para probar el caché de la API
 */
function flores_add_cache_test_page() {
    add_management_page(
        'Test de Caché API', 
        'Test de Caché API',
        'manage_options',
        'flores-cache-test',
        'flores_cache_test_page'
    );
}
add_action('admin_menu', 'flores_add_cache_test_page');

/**
 * Función para formatear el tiempo en milisegundos
 */
function flores_format_time($time) {
    return number_format($time * 1000, 2) . ' ms';
}

/**
 * Función para realizar una solicitud a un endpoint y medir el tiempo
 */
function flores_test_endpoint($endpoint, $clear_cache = false) {
    global $wpdb;
    
    // Si se solicita, limpiar la caché antes de la prueba
    if ($clear_cache) {
        // Eliminar todos los transients relacionados con el caché de API
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '%_transient_flores_api_cache_%'");
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '%_transient_timeout_flores_api_cache_%'");
    }
    
    // Realizar la primera solicitud (sin caché si se limpió)
    $start_time = microtime(true);
    $response = wp_remote_get(site_url('/wp-json' . $endpoint), [
        'headers' => ['Cache-Control' => 'no-cache, no-store, must-revalidate'],
        'timeout' => 30 // Aumentar el tiempo de espera para evitar timeouts
    ]);
    $first_request_time = microtime(true) - $start_time;
    
    if (is_wp_error($response)) {
        return [
            'endpoint' => $endpoint,
            'error' => $response->get_error_message(),
            'first_request_time' => 0,
            'second_request_time' => 0,
            'from_cache_first' => 'Error',
            'from_cache_second' => 'Error',
            'improvement' => 0
        ];
    }
    
    // Verificar si la respuesta tiene el encabezado de caché
    $headers = wp_remote_retrieve_headers($response);
    $from_cache_first = isset($headers['x-wp-from-cache']) ? 'Sí' : 'No';
    
    // Esperar un momento para asegurar que el caché se haya guardado
    sleep(1);
    
    // Realizar una segunda solicitud (debería usar caché si está habilitada)
    $start_time = microtime(true);
    $response = wp_remote_get(site_url('/wp-json' . $endpoint), [
        'timeout' => 30 // Aumentar el tiempo de espera para evitar timeouts
    ]);
    $second_request_time = microtime(true) - $start_time;
    
    if (is_wp_error($response)) {
        return [
            'endpoint' => $endpoint,
            'error' => $response->get_error_message(),
            'first_request_time' => $first_request_time,
            'second_request_time' => 0,
            'from_cache_first' => $from_cache_first,
            'from_cache_second' => 'Error',
            'improvement' => 0
        ];
    }
    
    // Verificar si la respuesta tiene el encabezado de caché
    $headers = wp_remote_retrieve_headers($response);
    $from_cache_second = isset($headers['x-wp-from-cache']) ? 'Sí' : 'No';
    
    // Calcular la mejora de rendimiento
    $improvement = $first_request_time > 0 ? (($first_request_time - $second_request_time) / $first_request_time) * 100 : 0;
    
    return [
        'endpoint' => $endpoint,
        'first_request_time' => $first_request_time,
        'second_request_time' => $second_request_time,
        'from_cache_first' => $from_cache_first,
        'from_cache_second' => $from_cache_second,
        'improvement' => $improvement
    ];
}

/**
 * Función para obtener estadísticas de caché
 */
function flores_get_cache_stats() {
    global $wpdb;
    
    // Contar el número total de entradas en caché
    $total_cache_entries = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->options WHERE option_name LIKE '%_transient_flores_api_cache_%' AND option_name NOT LIKE '%_transient_timeout_%'");
    
    // Obtener el tamaño total de la caché
    $cache_size = $wpdb->get_var("SELECT SUM(LENGTH(option_value)) FROM $wpdb->options WHERE option_name LIKE '%_transient_flores_api_cache_%' AND option_name NOT LIKE '%_transient_timeout_%'");
    
    // Obtener las entradas más recientes
    $recent_entries = $wpdb->get_results("
        SELECT 
            REPLACE(option_name, '_transient_flores_api_cache_', '') AS cache_key,
            option_id,
            LENGTH(option_value) AS size
        FROM 
            $wpdb->options 
        WHERE 
            option_name LIKE '%_transient_flores_api_cache_%' 
            AND option_name NOT LIKE '%_transient_timeout_%'
        ORDER BY 
            option_id DESC
        LIMIT 10
    ");
    
    return [
        'total_entries' => $total_cache_entries,
        'cache_size' => $cache_size ? round($cache_size / 1024, 2) : 0, // Convertir a KB
        'recent_entries' => $recent_entries
    ];
}

/**
 * Función para verificar si un tipo de contenido está configurado en el caché
 */
function flores_check_content_type($type) {
    // Definimos manualmente los valores TTL conocidos basados en el código de API_Cache_Manager
    $ttl_values = [
        'product' => 3600,      // 1 hora
        'products' => 3600,     // 1 hora
        'category' => 7200,     // 2 horas
        'categories' => 7200,   // 2 horas
        'user' => 86400,        // 24 horas
        'catalog' => 86400,     // 24 horas
        'banner' => 86400,      // 24 horas
        'homeSection' => 3600,  // 1 hora
        'menu' => 1800          // 30 minutos
    ];
    
    return isset($ttl_values[$type]) ? $ttl_values[$type] : false;
}

/**
 * Renderiza la página de prueba de caché
 */
function flores_cache_test_page() {
    // Verificar permisos
    if (!current_user_can('manage_options')) {
        wp_die('Acceso denegado');
    }
    
    // Procesar acciones
    $message = '';
    $test_results = [];
    
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'clear_cache') {
            global $wpdb;
            $count = $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '%_transient_flores_api_cache_%'");
            $message = "Caché limpiado. Se eliminaron $count entradas.";
        } elseif ($_POST['action'] === 'test_endpoint' && !empty($_POST['endpoint'])) {
            $endpoint = sanitize_text_field($_POST['endpoint']);
            $clear_cache = isset($_POST['clear_cache']) && $_POST['clear_cache'] === 'yes';
            $test_results[] = flores_test_endpoint($endpoint, $clear_cache);
            $message = "Prueba completada para el endpoint: $endpoint";
        } elseif ($_POST['action'] === 'test_all') {
            // Probar endpoints comunes
            $endpoints = [
                '/floresinc/v1/featured-categories',
                '/floresinc/v1/menu',
                '/floresinc/v1/promotional-grid',
                '/wc/v3/products',
                '/wc/v3/products/categories'
            ];
            
            $clear_cache = isset($_POST['clear_cache']) && $_POST['clear_cache'] === 'yes';
            
            if ($clear_cache) {
                global $wpdb;
                $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '%_transient_flores_api_cache_%'");
            }
            
            foreach ($endpoints as $endpoint) {
                $test_results[] = flores_test_endpoint($endpoint, false); // No limpiar caché entre pruebas
            }
            
            $message = "Pruebas completadas para todos los endpoints.";
        }
    }
    
    // Obtener estadísticas de caché
    $cache_stats = flores_get_cache_stats();
    
    // Verificar el estado de optimización de API
    $optimization_status = defined('DISABLE_API_OPTIMIZATION') && DISABLE_API_OPTIMIZATION ? 'Desactivado' : 'Activado';
    
    // Verificar tipos de contenido configurados
    $content_types = [
        'product', 'products', 'category', 'categories', 
        'user', 'catalog', 'banner', 'homeSection', 'menu'
    ];
    
    // Estilo CSS para la página
    ?>
    <style>
        .wrap {
            max-width: 1200px;
        }
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 3px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,0.04);
        }
        .message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 3px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .improvement-positive {
            color: green;
        }
        .improvement-negative {
            color: red;
        }
        .status-ok {
            color: green;
        }
        .status-warning {
            color: orange;
        }
        .status-error {
            color: red;
        }
        .flex-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .flex-item {
            flex: 1;
            min-width: 300px;
        }
        .form-group {
            margin-bottom: 15px;
        }
    </style>
    
    <div class="wrap">
        <h1>Prueba de Caché de API - FloresInc</h1>
        
        <?php if (!empty($message)): ?>
        <div class="message"><?php echo esc_html($message); ?></div>
        <?php endif; ?>
        
        <div class="flex-container">
            <div class="flex-item card">
                <h2>Estado del Sistema</h2>
                <table>
                    <tr>
                        <th>Optimización de API</th>
                        <td class="<?php echo $optimization_status === 'Activado' ? 'status-ok' : 'status-error'; ?>">
                            <?php echo esc_html($optimization_status); ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Total de entradas en caché</th>
                        <td><?php echo esc_html($cache_stats['total_entries']); ?></td>
                    </tr>
                    <tr>
                        <th>Tamaño total de caché</th>
                        <td><?php echo esc_html($cache_stats['cache_size']); ?> KB</td>
                    </tr>
                </table>
                
                <h3>Tipos de Contenido Configurados</h3>
                <table>
                    <tr>
                        <th>Tipo</th>
                        <th>TTL (segundos)</th>
                        <th>Estado</th>
                    </tr>
                    <?php foreach ($content_types as $type): ?>
                    <tr>
                        <td><?php echo esc_html($type); ?></td>
                        <td>
                            <?php 
                            $ttl = flores_check_content_type($type);
                            echo $ttl !== false ? esc_html($ttl) : 'No configurado';
                            ?>
                        </td>
                        <td class="<?php echo $ttl !== false ? 'status-ok' : 'status-error'; ?>">
                            <?php echo $ttl !== false ? 'OK' : 'No configurado'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            
            <div class="flex-item card">
                <h2>Pruebas de Rendimiento</h2>
                
                <form method="post" action="">
                    <div class="form-group">
                        <input type="hidden" name="action" value="test_endpoint">
                        <label for="endpoint">Endpoint a probar:</label><br>
                        <input type="text" id="endpoint" name="endpoint" placeholder="/floresinc/v1/menu" class="regular-text" required><br>
                    </div>
                    
                    <div class="form-group">
                        <input type="checkbox" id="clear_cache" name="clear_cache" value="yes">
                        <label for="clear_cache">Limpiar caché antes de la prueba</label>
                    </div>
                    
                    <div class="form-group">
                        <input type="submit" value="Probar Endpoint" class="button button-primary">
                    </div>
                </form>
                
                <hr>
                
                <form method="post" action="">
                    <div class="form-group">
                        <input type="hidden" name="action" value="test_all">
                        <input type="checkbox" id="clear_cache_all" name="clear_cache" value="yes">
                        <label for="clear_cache_all">Limpiar caché antes de las pruebas</label>
                    </div>
                    
                    <div class="form-group">
                        <input type="submit" value="Probar Todos los Endpoints" class="button button-primary">
                    </div>
                </form>
                
                <hr>
                
                <form method="post" action="">
                    <input type="hidden" name="action" value="clear_cache">
                    <input type="submit" value="Limpiar Todo el Caché" class="button button-secondary">
                </form>
            </div>
        </div>
        
        <?php if (!empty($test_results)): ?>
        <div class="card">
            <h2>Resultados de Pruebas</h2>
            <table>
                <tr>
                    <th>Endpoint</th>
                    <th>Tipo de Contenido</th>
                    <th>Primera Solicitud</th>
                    <th>Desde Caché</th>
                    <th>Segunda Solicitud</th>
                    <th>Desde Caché</th>
                    <th>Mejora</th>
                </tr>
                <?php foreach ($test_results as $result): ?>
                <tr>
                    <td><?php echo esc_html($result['endpoint']); ?></td>
                    <td>
                        <?php 
                        $content_type = identify_content_type_from_route($result['endpoint']);
                        echo $content_type ? esc_html($content_type) : 'No identificado';
                        ?>
                    </td>
                    <td><?php echo flores_format_time($result['first_request_time']); ?></td>
                    <td><?php echo esc_html($result['from_cache_first']); ?></td>
                    <td><?php echo flores_format_time($result['second_request_time']); ?></td>
                    <td><?php echo esc_html($result['from_cache_second']); ?></td>
                    <td class="<?php echo $result['improvement'] > 0 ? 'improvement-positive' : 'improvement-negative'; ?>">
                        <?php echo number_format($result['improvement'], 2); ?>%
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Entradas Recientes en Caché</h2>
            <table>
                <tr>
                    <th>Clave de Caché</th>
                    <th>Tamaño</th>
                </tr>
                <?php foreach ($cache_stats['recent_entries'] as $entry): ?>
                <tr>
                    <td><?php echo esc_html($entry->cache_key); ?></td>
                    <td><?php echo esc_html(round($entry->size / 1024, 2)); ?> KB</td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
    <?php
}
