<?php
/**
 * API Endpoints para la generación de PDFs de catálogos
 */

// Evitar acceso directo al archivo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Inicializar los endpoints para la generación de PDFs
 */
function floresinc_init_catalog_pdf_api() {
    add_action('rest_api_init', 'floresinc_register_catalog_pdf_endpoints');
}

/**
 * Registrar los endpoints de API para generación de PDFs
 */
function floresinc_register_catalog_pdf_endpoints() {
    // Namespace base para nuestros endpoints
    $namespace = 'floresinc/v1';
    
    // Endpoint para generar un PDF del catálogo
    register_rest_route($namespace, '/catalogs/(?P<id>\d+)/pdf', [
        'methods' => 'GET',
        'callback' => 'floresinc_generate_catalog_pdf_endpoint',
        'permission_callback' => function() {
            return is_user_logged_in();
        }
    ]);
}

/**
 * Endpoint: Generar un PDF del catálogo
 * 
 * Utiliza la librería TCPDF para generar un PDF con los productos del catálogo
 */
function floresinc_generate_catalog_pdf_endpoint(WP_REST_Request $request) {
    global $wpdb;
    
    $catalog_id = $request->get_param('id');
    $user_id = get_current_user_id();
    
    $catalog_table = $wpdb->prefix . 'floresinc_catalogs';
    $catalog_products_table = $wpdb->prefix . 'floresinc_catalog_products';
    
    // Verificar que el catálogo exista y pertenezca al usuario
    $catalog = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM $catalog_table WHERE id = %d AND user_id = %d
    ", $catalog_id, $user_id), ARRAY_A);
    
    if (!$catalog) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'Catálogo no encontrado o no tienes permiso para acceder'
        ], 404);
    }
    
    // Obtener los productos del catálogo
    $products = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $catalog_products_table WHERE catalog_id = %d ORDER BY id ASC
    ", $catalog_id), ARRAY_A);
    
    if (empty($products)) {
        return new WP_REST_Response([
            'status' => 'error',
            'message' => 'El catálogo no tiene productos'
        ], 400);
    }
    
    // Obtener los IDs de los productos
    $product_ids = array_map(function($product) {
        return $product['product_id'];
    }, $products);
    
    // Verificar si existe la librería TCPDF
    if (file_exists(ABSPATH . 'wp-content/plugins/tcpdf/tcpdf.php')) {
        require_once(ABSPATH . 'wp-content/plugins/tcpdf/tcpdf.php');
        
        try {
            // Crear nueva instancia de TCPDF
            $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            
            // Establecer información del documento
            $pdf->SetCreator('FloresInc');
            $pdf->SetAuthor(wp_get_current_user()->display_name);
            $pdf->SetTitle($catalog['name']);
            $pdf->SetSubject('Catálogo de productos');
            $pdf->SetKeywords('Catálogo, Productos, FloresInc');
            
            // Eliminar encabezado y pie de página predeterminados
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            
            // Establecer márgenes
            $pdf->SetMargins(10, 10, 10);
            $pdf->SetAutoPageBreak(true, 10);
            
            // Agregar una página
            $pdf->AddPage();
            
            // Logo del catálogo
            if (!empty($catalog['logo_url'])) {
                $pdf->Image($catalog['logo_url'], 10, 10, 30, 0, '', '', '', false, 300);
                $pdf->Ln(15);
            }
            
            // Título del catálogo
            $pdf->SetFont('helvetica', 'B', 18);
            $pdf->Cell(0, 10, $catalog['name'], 0, 1, 'C');
            $pdf->Ln(5);
            
            // Información adicional
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 5, 'Fecha de generación: ' . date('d/m/Y'), 0, 1, 'R');
            $pdf->Ln(10);
            
            // Encabezado de tabla
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->Cell(25, 7, 'Imagen', 1, 0, 'C', true);
            $pdf->Cell(75, 7, 'Producto', 1, 0, 'C', true);
            $pdf->Cell(30, 7, 'SKU', 1, 0, 'C', true);
            $pdf->Cell(30, 7, 'Precio', 1, 0, 'C', true);
            $pdf->Cell(30, 7, 'Precio Catálogo', 1, 1, 'C', true);
            
            // Contenido de la tabla
            $pdf->SetFont('helvetica', '', 10);
            foreach ($products as $product) {
                // Obtener información del producto
                $product_image = $product['catalog_image'];
                $product_name = $product['catalog_name'];
                $product_sku = $product['catalog_sku'];
                $product_price = $product['product_price'];
                $catalog_price = $product['catalog_price'];
                
                // Descripción corta
                $short_description = $product['catalog_short_description'];
                
                // Altura de la fila
                $row_height = 25;
                
                // Imagen del producto
                $y_before = $pdf->GetY();
                if (!empty($product_image)) {
                    $pdf->Cell(25, $row_height, '', 1, 0, 'C');
                    $img_width = 20;
                    $img_height = 20;
                    $x = $pdf->GetX() - $img_width - 2.5;
                    $y = $pdf->GetY() + 2.5;
                    $pdf->Image($product_image, $x, $y, $img_width, $img_height, '', '', '', false, 300);
                } else {
                    $pdf->Cell(25, $row_height, 'Sin imagen', 1, 0, 'C');
                }
                
                // Información del producto
                $pdf->SetXY($pdf->GetX(), $y_before);
                $pdf->MultiCell(75, $row_height, $product_name . "\n" . substr($short_description, 0, 100) . (strlen($short_description) > 100 ? '...' : ''), 1, 'L');
                $pdf->SetXY($pdf->GetX() + 100, $y_before);
                $pdf->Cell(30, $row_height, $product_sku, 1, 0, 'C');
                $pdf->Cell(30, $row_height, '$' . number_format($product_price, 2), 1, 0, 'C');
                $pdf->Cell(30, $row_height, '$' . number_format($catalog_price, 2), 1, 1, 'C');
            }
            
            // Pie de página
            $pdf->Ln(10);
            $pdf->SetFont('helvetica', 'I', 8);
            $pdf->Cell(0, 5, 'Este catálogo fue generado automáticamente. Los precios y disponibilidad pueden variar.', 0, 1, 'C');
            
            // Nombre del archivo
            $file_name = sanitize_title($catalog['name']) . '_' . date('Ymd') . '.pdf';
            
            // Directorio para guardar el PDF
            $upload_dir = wp_upload_dir();
            $pdf_dir = $upload_dir['basedir'] . '/catalogs/';
            
            // Crear directorio si no existe
            if (!file_exists($pdf_dir)) {
                wp_mkdir_p($pdf_dir);
            }
            
            // Ruta completa del archivo
            $file_path = $pdf_dir . $file_name;
            
            // Guardar el PDF
            $pdf->Output($file_path, 'F');
            
            // URL del archivo PDF
            $file_url = $upload_dir['baseurl'] . '/catalogs/' . $file_name;
            
            return new WP_REST_Response([
                'status' => 'success',
                'message' => 'PDF generado con éxito',
                'file_url' => $file_url
            ], 200);
            
        } catch (Exception $e) {
            error_log('Error al generar PDF: ' . $e->getMessage());
            
            // Si falla TCPDF, usar el método de respaldo
            return floresinc_generate_simple_pdf($catalog, $products);
        }
    } else {
        // Si no está instalado TCPDF, usar el método de respaldo
        return floresinc_generate_simple_pdf($catalog, $products);
    }
}

/**
 * Método de respaldo para generar un PDF simple sin TCPDF
 */
function floresinc_generate_simple_pdf($catalog, $products) {
    // Utilizar dompdf si está disponible
    if (class_exists('Dompdf\\Dompdf')) {
        // Generar PDF con dompdf
        $dompdf = new Dompdf\Dompdf();
        
        // Contenido HTML
        $html = '
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1 { text-align: center; color: #333; }
                .date { text-align: right; font-size: 12px; margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th { background-color: #f2f2f2; padding: 8px; text-align: left; border: 1px solid #ddd; }
                td { padding: 8px; border: 1px solid #ddd; }
                img { max-width: 50px; max-height: 50px; }
                .footer { text-align: center; font-size: 11px; color: #666; }
            </style>
        </head>
        <body>
            <h1>' . $catalog['name'] . '</h1>
            <div class="date">Fecha de generación: ' . date('d/m/Y') . '</div>
            
            <table>
                <tr>
                    <th>Imagen</th>
                    <th>Producto</th>
                    <th>SKU</th>
                    <th>Precio</th>
                    <th>Precio Catálogo</th>
                </tr>';
        
        foreach ($products as $product) {
            $html .= '
                <tr>
                    <td>' . (!empty($product['catalog_image']) ? '<img src="' . $product['catalog_image'] . '" alt="Producto">' : 'Sin imagen') . '</td>
                    <td>
                        <strong>' . $product['catalog_name'] . '</strong><br>
                        ' . substr($product['catalog_short_description'], 0, 100) . (strlen($product['catalog_short_description']) > 100 ? '...' : '') . '
                    </td>
                    <td>' . $product['catalog_sku'] . '</td>
                    <td>$' . number_format($product['product_price'], 2) . '</td>
                    <td>$' . number_format($product['catalog_price'], 2) . '</td>
                </tr>';
        }
        
        $html .= '
            </table>
            
            <div class="footer">
                Este catálogo fue generado automáticamente. Los precios y disponibilidad pueden variar.
            </div>
        </body>
        </html>';
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Nombre del archivo
        $file_name = sanitize_title($catalog['name']) . '_' . date('Ymd') . '.pdf';
        
        // Directorio para guardar el PDF
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/catalogs/';
        
        // Crear directorio si no existe
        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
        }
        
        // Ruta completa del archivo
        $file_path = $pdf_dir . $file_name;
        
        // Guardar el PDF
        file_put_contents($file_path, $dompdf->output());
        
        // URL del archivo PDF
        $file_url = $upload_dir['baseurl'] . '/catalogs/' . $file_name;
        
        return new WP_REST_Response([
            'status' => 'success',
            'message' => 'PDF generado con éxito (dompdf)',
            'file_url' => $file_url
        ], 200);
    }
    
    // Si tampoco está disponible dompdf, generar un HTML para descargar
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . $catalog['name'] . '</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { text-align: center; color: #333; }
            .date { text-align: right; font-size: 12px; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th { background-color: #f2f2f2; padding: 8px; text-align: left; border: 1px solid #ddd; }
            td { padding: 8px; border: 1px solid #ddd; }
            img { max-width: 100px; max-height: 100px; }
            .footer { text-align: center; font-size: 11px; color: #666; }
            @media print {
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="no-print" style="text-align: center; margin-bottom: 20px;">
            <button onclick="window.print()">Imprimir Catálogo</button>
        </div>
        
        <h1>' . $catalog['name'] . '</h1>
        <div class="date">Fecha de generación: ' . date('d/m/Y') . '</div>
        
        <table>
            <tr>
                <th>Imagen</th>
                <th>Producto</th>
                <th>SKU</th>
                <th>Precio</th>
                <th>Precio Catálogo</th>
            </tr>';
    
    foreach ($products as $product) {
        $html .= '
            <tr>
                <td>' . (!empty($product['catalog_image']) ? '<img src="' . $product['catalog_image'] . '" alt="Producto">' : 'Sin imagen') . '</td>
                <td>
                    <strong>' . $product['catalog_name'] . '</strong><br>
                    ' . substr($product['catalog_short_description'], 0, 100) . (strlen($product['catalog_short_description']) > 100 ? '...' : '') . '
                </td>
                <td>' . $product['catalog_sku'] . '</td>
                <td>$' . number_format($product['product_price'], 2) . '</td>
                <td>$' . number_format($product['catalog_price'], 2) . '</td>
            </tr>';
    }
    
    $html .= '
        </table>
        
        <div class="footer">
            Este catálogo fue generado automáticamente. Los precios y disponibilidad pueden variar.
        </div>
    </body>
    </html>';
    
    // Nombre del archivo
    $file_name = sanitize_title($catalog['name']) . '_' . date('Ymd') . '.html';
    
    // Directorio para guardar el HTML
    $upload_dir = wp_upload_dir();
    $html_dir = $upload_dir['basedir'] . '/catalogs/';
    
    // Crear directorio si no existe
    if (!file_exists($html_dir)) {
        wp_mkdir_p($html_dir);
    }
    
    // Ruta completa del archivo
    $file_path = $html_dir . $file_name;
    
    // Guardar el HTML
    file_put_contents($file_path, $html);
    
    // URL del archivo HTML
    $file_url = $upload_dir['baseurl'] . '/catalogs/' . $file_name;
    
    return new WP_REST_Response([
        'status' => 'success',
        'message' => 'Página HTML generada (no se pudo crear PDF)',
        'file_url' => $file_url,
        'is_html' => true
    ], 200);
}
