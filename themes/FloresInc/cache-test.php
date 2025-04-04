<?php
/**
 * Cache Test Script
 * 
 * Este script permite verificar que el sistema de caché de la API está funcionando correctamente.
 * Proporciona información sobre el estado del caché, las rutas cacheadas y permite realizar pruebas
 * de rendimiento para comparar tiempos de respuesta con y sin caché.
 * 
 * Uso: Acceder a /wp-content/themes/FloresInc/cache-test.php desde el navegador
 */

// Asegurarse de que solo se pueda acceder desde el navegador y no desde una solicitud a la API
if (php_sapi_name() === 'cli') {
    die('Este script debe ejecutarse desde un navegador web.');
}

// Cargar WordPress
// Buscar el archivo wp-load.php subiendo en la jerarquía de directorios
$wp_load_path = __FILE__;
$max_levels = 10; // Límite de seguridad para evitar bucles infinitos

for ($i = 0; $i < $max_levels; $i++) {
    $wp_load_path = dirname($wp_load_path);
    if (file_exists($wp_load_path . '/wp-load.php')) {
        require_once($wp_load_path . '/wp-load.php');
        break;
    }
}

// Verificar si se cargó WordPress
if (!defined('ABSPATH')) {
    die('No se pudo cargar WordPress. Por favor, verifica la estructura de directorios.');
}

// Verificar si el usuario tiene permisos de administrador
if (!current_user_can('manage_options')) {
    wp_die('Acceso denegado. Debe ser administrador para acceder a esta página.');
}

// Incluir el archivo de optimización de API para acceder a las funciones de caché
// El archivo ya debería estar cargado a través de functions.php, pero lo incluimos por seguridad
if (!function_exists('flores_api_cache')) {
    $api_optimization_path = dirname(__FILE__) . '/inc/api-optimization.php';
    if (file_exists($api_optimization_path)) {
        require_once($api_optimization_path);
    } else {
        die('No se pudo encontrar el archivo de optimización de API.');
    }
}

// Función para formatear el tiempo en milisegundos
function format_time($time) {
    return number_format($time * 1000, 2) . ' ms';
}

// Función para realizar una solicitud a un endpoint y medir el tiempo
function test_endpoint($endpoint, $clear_cache = false) {
    global $wpdb;
    
    // Si se solicita, limpiar la caché antes de la prueba
    if ($clear_cache) {
        // Eliminar todos los transients relacionados con el caché de API
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '%_transient_flores_api_cache_%'");
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '%_transient_timeout_flores_api_cache_%'");
    }
    
    // Realizar la primera solicitud (sin caché si se limpió)
    $start_time = microtime(true);
    $response = wp_remote_get(site_url('/wp-json' . $endpoint));
    $first_request_time = microtime(true) - $start_time;
    
    // Verificar si la respuesta tiene el encabezado de caché
    $headers = wp_remote_retrieve_headers($response);
    $from_cache_first = isset($headers['X-WP-From-Cache']) ? 'Sí' : 'No';
    
    // Realizar una segunda solicitud (debería usar caché si está habilitada)
    $start_time = microtime(true);
    $response = wp_remote_get(site_url('/wp-json' . $endpoint));
    $second_request_time = microtime(true) - $start_time;
    
    // Verificar si la respuesta tiene el encabezado de caché
    $headers = wp_remote_retrieve_headers($response);
    $from_cache_second = isset($headers['X-WP-From-Cache']) ? 'Sí' : 'No';
    
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

// Función para obtener estadísticas de caché
function get_cache_stats() {
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

// Función para verificar si un tipo de contenido está configurado en el caché
function check_content_type($type) {
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

// Función para verificar si una ruta está excluida del caché
function is_route_excluded($route) {
    $instance = call_user_func(['API_Cache_Manager', 'instance']);
    return $instance->should_exclude_route($route);
}

// Función para identificar el tipo de contenido de una ruta
function get_content_type_for_route($route) {
    return identify_content_type_from_route($route);
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
        $test_results[] = test_endpoint($endpoint, $clear_cache);
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
            $test_results[] = test_endpoint($endpoint, false); // No limpiar caché entre pruebas
        }
        
        $message = "Pruebas completadas para todos los endpoints.";
    }
}

// Obtener estadísticas de caché
$cache_stats = get_cache_stats();

// Verificar el estado de optimización de API
$optimization_status = defined('DISABLE_API_OPTIMIZATION') && DISABLE_API_OPTIMIZATION ? 'Desactivado' : 'Activado';

// Verificar tipos de contenido configurados
$content_types = [
    'product', 'products', 'category', 'categories', 
    'user', 'catalog', 'banner', 'homeSection', 'menu'
];

// HTML para la página
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Prueba de Caché de API - FloresInc</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        h1, h2, h3 {
            color: #2c3e50;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .card {
            background: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 10px;
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
        form {
            margin-bottom: 20px;
        }
        input[type="text"] {
            padding: 8px;
            width: 300px;
        }
        input[type="submit"], button {
            padding: 8px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
        }
        input[type="submit"]:hover, button:hover {
            background-color: #45a049;
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
            justify-content: space-between;
            flex-wrap: wrap;
        }
        .flex-item {
            flex: 1;
            min-width: 300px;
            margin-right: 20px;
        }
        .flex-item:last-child {
            margin-right: 0;
        }
    </style>
</head>
<body>
    <div class="container">
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
                            $ttl = check_content_type($type);
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
                    <input type="hidden" name="action" value="test_endpoint">
                    <label for="endpoint">Endpoint a probar:</label><br>
                    <input type="text" id="endpoint" name="endpoint" placeholder="/floresinc/v1/menu" required><br><br>
                    <input type="checkbox" id="clear_cache" name="clear_cache" value="yes">
                    <label for="clear_cache">Limpiar caché antes de la prueba</label><br><br>
                    <input type="submit" value="Probar Endpoint">
                </form>
                
                <form method="post" action="">
                    <input type="hidden" name="action" value="test_all">
                    <input type="checkbox" id="clear_cache_all" name="clear_cache" value="yes">
                    <label for="clear_cache_all">Limpiar caché antes de las pruebas</label><br><br>
                    <input type="submit" value="Probar Todos los Endpoints">
                </form>
                
                <form method="post" action="">
                    <input type="hidden" name="action" value="clear_cache">
                    <input type="submit" value="Limpiar Todo el Caché">
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
                        $content_type = get_content_type_for_route($result['endpoint']);
                        echo $content_type ? esc_html($content_type) : 'No identificado';
                        ?>
                    </td>
                    <td><?php echo format_time($result['first_request_time']); ?></td>
                    <td><?php echo esc_html($result['from_cache_first']); ?></td>
                    <td><?php echo format_time($result['second_request_time']); ?></td>
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
        
        <div class="card">
            <h2>Verificación de Rutas</h2>
            <form method="get" action="" id="route-check-form">
                <input type="text" id="route_to_check" name="route_to_check" placeholder="/floresinc/v1/menu" required>
                <button type="button" onclick="checkRoute()">Verificar Ruta</button>
            </form>
            
            <div id="route-check-result" style="margin-top: 20px; display: none;">
                <h3>Resultado de la Verificación</h3>
                <div id="route-check-content"></div>
            </div>
            
            <script>
                function checkRoute() {
                    const route = document.getElementById('route_to_check').value;
                    const resultDiv = document.getElementById('route-check-result');
                    const contentDiv = document.getElementById('route-check-content');
                    
                    // Realizar la verificación del lado del servidor
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4 && xhr.status === 200) {
                            const response = JSON.parse(xhr.responseText);
                            
                            let html = '<table>';
                            html += '<tr><th>Propiedad</th><th>Valor</th></tr>';
                            html += '<tr><td>Ruta</td><td>' + response.route + '</td></tr>';
                            html += '<tr><td>Tipo de Contenido</td><td>' + (response.content_type || 'No identificado') + '</td></tr>';
                            html += '<tr><td>Excluido del Caché</td><td>' + (response.is_excluded ? 'Sí' : 'No') + '</td></tr>';
                            html += '<tr><td>TTL (si aplica)</td><td>' + (response.ttl || 'N/A') + '</td></tr>';
                            html += '</table>';
                            
                            contentDiv.innerHTML = html;
                            resultDiv.style.display = 'block';
                        }
                    };
                    xhr.send('action=check_route&route=' + encodeURIComponent(route));
                }
            </script>
        </div>
    </div>
</body>
</html>
<?php

// Registrar la función AJAX para verificar rutas
if (!function_exists('ajax_check_route')) {
    function ajax_check_route() {
        if (!current_user_can('manage_options')) {
            wp_die('Acceso denegado');
        }
        
        $route = isset($_POST['route']) ? sanitize_text_field($_POST['route']) : '';
        
        $content_type = identify_content_type_from_route($route);
        $is_excluded = flores_api_cache()->should_exclude_route($route);
        $ttl = $content_type ? check_content_type($content_type) : false;
        
        wp_send_json([
            'route' => $route,
            'content_type' => $content_type,
            'is_excluded' => $is_excluded,
            'ttl' => $ttl
        ]);
    }
}

// Registrar la función AJAX solo si estamos en una página administrativa
if (is_admin()) {
    add_action('wp_ajax_check_route', 'ajax_check_route');
}
