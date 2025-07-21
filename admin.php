<?php
// Inicio simple de sesión
session_start();

$autoloadPath = __DIR__ . '/vendor/autoload.php';
require $autoloadPath;
use Dompdf\Dompdf; 


// Verificación mejorada de administrador
if (!isset($_SESSION['admin_logged_in'])) {
    // Destruir sesión previa por seguridad
    session_unset();
    session_destroy();
    
    // Redirigir a login sin crear bucle
    header("Location: login.php?redirect=admin");
    exit();
}
// =============================================
// 👤 MANEJO DE DATOS DE USUARIO
// =============================================

// Inicializar y limpiar variables de usuario
$nombre_usuario = trim($_SESSION['nombre'] ?? 'Administrador');
$iniciales = '';

// Generar iniciales del usuario
if (!empty($nombre_usuario)) {
    $name_parts = array_filter(explode(' ', $nombre_usuario)); // Elimina elementos vacíos
    $name_parts = array_slice($name_parts, 0, 2); // Limita a 2 partes máximo
    
    foreach ($name_parts as $part) {
        $iniciales .= strtoupper(substr(trim($part), 0, 1));
    }
    
    // Si solo hay una inicial, tomar las dos primeras letras del nombre
    if (strlen($iniciales) < 2) {
        $iniciales = strtoupper(substr($nombre_usuario, 0, 2));
    }
}

// =============================================
// 📊 MANEJO DE CONSULTAS Y BASE DE DATOS
// =============================================

require_once 'Conect.php';

// Inicializar variables para filtros
$filtro_criterio = isset($_GET['filtro_criterio']) ? 
    htmlspecialchars(trim($_GET['filtro_criterio'])) : '';
$filtro_termino = isset($_GET['filtro_termino']) ? 
    htmlspecialchars(trim($_GET['filtro_termino'])) : '';

// Inicializar array de consultas
$consultas = [];
$error_db = null;

// Cargar datos de consultas desde la base de datos
if ($conn) {
    try {
        // Consulta base con joins
        $sql = "SELECT c.*, 
                u.Nombre as usuario, 
                l.titulo as libro, 
                COALESCE(c.carrera_usuario, u_carrera.Nombre) as carrera
                FROM consulta c
                LEFT JOIN Usuarios u ON c.id_Usuario = u.id_Usuario
                LEFT JOIN Libros l ON c.id_Libro = l.id_Libro
                LEFT JOIN Usuarios u2 ON c.id_Usuario = u2.id_Usuario
                LEFT JOIN carrera u_carrera ON u2.id_carrera = u_carrera.id_Carrera";
        
        // Preparar condiciones para filtros
        $conditions = [];
        $params = [];
        $types = '';
        
        if (!empty($filtro_criterio)) {
            $conditions[] = "c.criterio_busqueda = ?";
            $params[] = $filtro_criterio;
            $types .= 's';
        }
        
        if (!empty($filtro_termino)) {
            $conditions[] = "c.termino_busqueda LIKE ?";
            $params[] = "%{$filtro_termino}%";
            $types .= 's';
        }
        
        // Añadir condiciones si existen
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $sql .= " ORDER BY c.fecha DESC";
        
        // Preparar y ejecutar consulta
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
            throw new Exception("Error al preparar la consulta: " . $conn->error);
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $consultas = $result->fetch_all(MYSQLI_ASSOC);
        
        $stmt->close();
    } catch (Exception $e) {
        $error_db = $e->getMessage();
        error_log("Error en consulta de historial: " . $error_db);
    }
}

// =============================================
// 📈 FUNCIÓN PARA GENERAR GRÁFICAS
// =============================================

/**
 * Genera una gráfica usando QuickChart.io con manejo de errores
 * 
 * @param array $config Configuración de la gráfica
 * @return string|false URL de la imagen en base64 o false en caso de error
 */
// Función para generar gráficas usando QuickChart.io (ya existe en tu archivo, pero actualízala con esta versión mejorada)
function generarGraficaQuickChart($config) {
    $chartUrl = 'https://quickchart.io/chart?c=' . urlencode(json_encode($config));
    
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10 // 10 seconds timeout
            ]
        ]);
        
        $imageData = @file_get_contents($chartUrl, false, $context);
        if ($imageData === false) {
            error_log("Error al generar gráfica: " . print_r(error_get_last(), true));
            return false;
        }
        return 'data:image/png;base64,' . base64_encode($imageData);
    } catch (Exception $e) {
        error_log("Excepción al generar gráfica: " . $e->getMessage());
        return false;
    }
}

// ========== MANEJADOR DE EXPORTACIÓN A PDF ==========
if (isset($_GET['export'])) {
    // Verificar conexión a internet primero
    $connected = @fsockopen("www.google.com", 80);
    if (!$connected) {
        $_SESSION['error'] = "Se requiere conexión a internet para generar gráficas";
//        header("Location: admin.php");
        //exit();
    }
    fclose($connected);
    
   // $autoloadPath = __DIR__ . '/vendor/autoload.php';
    
    if (!file_exists($autoloadPath)) {
        die("Error: Primero debes instalar DomPDF con Composer. Ejecuta: composer require dompdf/dompdf");
    }
    
    //require $autoloadPath;
    //echo "Iniciando <br>";
    //var_dump(class_exists('Dompdf\Dompdf'));
    //use Dompdf\Dompdf;

    echo "Todo Bien";
    
    switch ($_GET['export']) {
        case 'consultas':
            generarPdfConsultas($conn, $filtro_criterio, $filtro_termino);
            break;
            
        case 'pdf':
        case 'visitas':
            // [Aquí copia TODO el código de exportación de visitas del primer admin.php]
            break;
            
        default:
//            header("Location: admin.php");
            exit();
    }
}
/**
 * Genera PDF para el historial de consultas/búsquedas
 */
function generarPdfConsultas($conn, $filtro_criterio, $filtro_termino) {


    // Obtener consultas con los mismos filtros que en la vista principal
    $consultas = [];
    $sql = "SELECT c.*, 
            u.Nombre as usuario, 
            l.titulo as libro, 
            COALESCE(c.carrera_usuario, u_carrera.Nombre) as carrera
            FROM consulta c
            LEFT JOIN usuarios u ON c.id_Usuario = u.id_Usuario
            LEFT JOIN libros l ON c.id_Libro = l.id_Libro
            LEFT JOIN usuarios u2 ON c.id_Usuario = u2.id_Usuario
            LEFT JOIN carrera u_carrera ON u2.id_carrera = u_carrera.id_Carrera";
    
    $conditions = [];
    $params = [];
    
    if (!empty($filtro_criterio)) {
        $conditions[] = "c.criterio_busqueda = ?";
        $params[] = $filtro_criterio;
    }
    
    if (!empty($filtro_termino)) {
        $conditions[] = "c.termino_busqueda LIKE ?";
        $params[] = "%{$filtro_termino}%";
    }
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $sql .= " ORDER BY c.fecha DESC";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $consultas = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Generar HTML para el PDF
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    $html .= '<style>body{font-family:Arial;margin:20px}';
    $html .= 'h1{color:#800020;text-align:center}';
    $html .= 'h2{color:#5c0018;border-bottom:1px solid #eee;padding-bottom:5px}';
    $html .= 'table{width:100%;border-collapse:collapse;margin-top:15px}';
    $html .= 'th,td{border:1px solid #ddd;padding:8px;text-align:left}';
    $html .= 'th{background-color:#800020;color:white}';
    $html .= '.footer{margin-top:30px;text-align:right;font-size:12px;color:#666}';
    $html .= '</style>';
    $html .= '<title>Reporte de Consultas</title></head><body>';
    
    $html .= '<h1>Reporte de Consultas/Búsquedas</h1>';
    
    // Mostrar filtros aplicados si existen
    if ($filtro_criterio || $filtro_termino) {
        $html .= '<div style="margin-bottom:20px;padding:10px;background:#f5f5f5;border-radius:5px">';
        $html .= '<strong>Filtros aplicados:</strong><br>';
        if ($filtro_criterio) {
            $html .= '- Criterio: ' . htmlspecialchars($filtro_criterio) . '<br>';
        }
        if ($filtro_termino) {
            $html .= '- Término: ' . htmlspecialchars($filtro_termino) . '<br>';
        }
        $html .= '</div>';
    }
    
    $html .= '<table>';
    $html .= '<tr><th>ID</th><th>Fecha/Hora</th><th>Tipo</th><th>Término</th><th>Usuario</th><th>Carrera</th><th>Libro Encontrado</th></tr>';
    
    foreach ($consultas as $consulta) {
        $html .= '<tr>';
        $html .= '<td>' . $consulta['id_Consulta'] . '</td>';
        $html .= '<td>' . date('d/m/Y H:i', strtotime($consulta['fecha'])) . '</td>';
        $html .= '<td>' . ucfirst($consulta['criterio_busqueda']) . '</td>';
        $html .= '<td>' . htmlspecialchars($consulta['termino_busqueda']) . '</td>';
        $html .= '<td>' . ($consulta['usuario'] ?: 'Anónimo') . '</td>';
        $html .= '<td>' . ($consulta['carrera'] ?: 'No especificada') . '</td>';
        $html .= '<td>' . ($consulta['libro'] ?: 'No encontrado') . '</td>';
        $html .= '</tr>';
    }
    
    $html .= '</table>';
    
    // Pie de página
    $html .= '<div class="footer">';
    $html .= '<p>Generado el: ' . date('d/m/Y H:i:s') . '</p>';
    $html .= '<p>Por: ' . htmlspecialchars($_SESSION['nombre'] ?? 'Sistema') . '</p>';
    $html .= '</div>';
    
    $html .= '</body></html>';
    
    // Generar PDF
    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape'); // Usar landscape para mejor visualización de la tabla
    $dompdf->render();
    $dompdf->stream("reporte_consultas_" . date('Y-m-d') . ".pdf", ["Attachment" => false]);
    exit();


}
// ========== EXPORTACIÓN A PDF ==========
if (isset($_GET['export'])) {
    $autoloadPath = __DIR__ . '/vendor/autoload.php';
    
    if (!file_exists($autoloadPath)) {
        die("Error: Primero debes instalar DomPDF con Composer. Ejecuta: composer require dompdf/dompdf");
    }
    
    require_once $autoloadPath;
    
    // Obtener estadísticas generales
    $visitas_totales = $conn->query("SELECT COUNT(*) as total FROM visitas")->fetch_assoc()['total'] ?? 0;
    $visitas_hoy = $conn->query("SELECT COUNT(*) as hoy FROM visitas WHERE DATE(fecha) = CURDATE()")->fetch_assoc()['hoy'] ?? 0;
    
    // Datos para gráfica de visitas por tipo de usuario
    $visitas_tipo = [];
    $result_tipo = $conn->query("
        SELECT tipo_usuario, COUNT(*) as cantidad 
        FROM visitas 
        GROUP BY tipo_usuario
    ");
    
    if ($result_tipo) {
        $visitas_tipo = $result_tipo->fetch_all(MYSQLI_ASSOC);
    }
    // Estadísticas de visitas
$visitas_totales = 0;
$visitas_hoy = 0;
$visitas_semana = 0;

try {
    // Verificar si la tabla visitas existe
    $tableExists = $conn->query("SELECT 1 FROM visitas LIMIT 1");
    if ($tableExists) {
        $visitas_totales = $conn->query("SELECT COUNT(*) as total FROM visitas")->fetch_assoc()['total'] ?? 0;
        
        $res_hoy = $conn->query("SELECT COUNT(*) as hoy FROM visitas WHERE DATE(fecha) = CURDATE()");
        $visitas_hoy = $res_hoy ? $res_hoy->fetch_assoc()['hoy'] ?? 0 : 0;
        
        $res_semana = $conn->query("SELECT COUNT(*) as semana FROM visitas WHERE YEARWEEK(fecha, 1) = YEARWEEK(CURDATE(), 1)");
        $visitas_semana = $res_semana ? $res_semana->fetch_assoc()['semana'] ?? 0 : 0;
    } else {
        error_log("La tabla 'visitas' no existe o está vacía");
    }
} catch (mysqli_sql_exception $e) {
    error_log("Error en consultas de estadísticas: " . $e->getMessage());
}

// Configuración de la gráfica según el período seleccionado
$periodo = $_GET['periodo'] ?? '7days';
$grafica_labels = [];
$grafica_data = [];

switch($periodo) {
    case 'today':
        // [Mantén todo el código del switch case completo]
        break;
        
    case '7days':
        // [Mantén todo el código del switch case completo]
        break;
        
    case '30days':
        // [Mantén todo el código del switch case completo]
        break;
        
    case 'year':
        // [Mantén todo el código del switch case completo]
        break;
        
    default:
        // [Mantén todo el código del switch case completo]
}
    // Datos para gráfica de visitas de alumnos por carrera (CORRECCIÓN APLICADA)
    $visitas_carrera = [];
    $result_carrera = $conn->query("
        SELECT c.nombre as carrera, COUNT(v.id) as cantidad 
        FROM visitas v
        JOIN Usuarios u ON v.usuario_id = u.id_Usuario
        JOIN carrera c ON u.id_carrera = c.id_carrera
        WHERE v.tipo_usuario = 'alumno' AND c.nombre IS NOT NULL
        GROUP BY c.nombre
        ORDER BY cantidad DESC
    ");
    
    if ($result_carrera) {
        $visitas_carrera = $result_carrera->fetch_all(MYSQLI_ASSOC);
        
        // Si no hay datos, mostrar un mensaje
        if (empty($visitas_carrera)) {
            $visitas_carrera = [['carrera' => 'No hay datos de carreras', 'cantidad' => 1]];
        }
    } else {
        $visitas_carrera = [['carrera' => 'Error al cargar datos', 'cantidad' => 1]];
    }
    
    // Datos para gráfica de visitas por día (últimos 7 días)
    $visitas_7dias = [];
    $result_7dias = $conn->query("
        SELECT DATE(fecha) as dia, COUNT(*) as visitas 
        FROM visitas 
        WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY dia
        ORDER BY dia ASC
    ");
    
    if ($result_7dias) {
        $visitas_7dias = $result_7dias->fetch_all(MYSQLI_ASSOC);
    }
    
    // Configurar y generar gráficas
    $colores_institucionales = ['#800020', '#5c0018', '#a30029', '#cc0033', '#ff3366'];
    
    // Gráfica de visitas por tipo de usuario
    $grafica_tipo = generarGraficaQuickChart([
        'type' => 'pie',
        'data' => [
            'labels' => array_map(function($v) { 
                return ucfirst($v['tipo_usuario']) . ' (' . $v['cantidad'] . ')'; 
            }, $visitas_tipo),
            'datasets' => [[
                'data' => array_column($visitas_tipo, 'cantidad'),
                'backgroundColor' => $colores_institucionales,
                'borderColor' => '#ffffff',
                'borderWidth' => 1
            ]]
        ],
        'options' => [
            'title' => [
                'display' => true,
                'text' => 'Visitas por Tipo de Usuario',
                'fontSize' => 16,
                'fontColor' => '#800020'
            ],
            'legend' => [
                'position' => 'right',
                'labels' => [
                    'fontColor' => '#333',
                    'fontSize' => 12
                ]
            ],
            'plugins' => [
                'datalabels' => [
                    'display' => true,
                    'color' => '#fff',
                    'font' => [
                        'weight' => 'bold'
                    ]
                ]
            ]
        ]
    ]);
    
    // Gráfica de visitas de alumnos por carrera (CON CORRECCIÓN)
    $grafica_carrera = generarGraficaQuickChart([
        'type' => 'pie',
        'data' => [
            'labels' => array_map(function($v) { 
                return $v['carrera'] . ' (' . $v['cantidad'] . ')'; 
            }, $visitas_carrera),
            'datasets' => [[
                'data' => array_column($visitas_carrera, 'cantidad'),
                'backgroundColor' => array_slice($colores_institucionales, 0, count($visitas_carrera)),
                'borderColor' => '#ffffff',
                'borderWidth' => 1
            ]]
        ],
        'options' => [
            'title' => [
                'display' => true,
                'text' => 'Visitas de Alumnos por Carrera',
                'fontSize' => 16,
                'fontColor' => '#800020'
            ],
            'legend' => [
                'position' => 'right',
                'labels' => [
                    'fontColor' => '#333',
                    'fontSize' => 12
                ]
            ]
        ]
    ]);
    
    // Gráfica de visitas por día (últimos 7 días)
    $grafica_7dias = generarGraficaQuickChart([
        'type' => 'bar',
        'data' => [
            'labels' => array_map(function($v) { return date('d/m', strtotime($v['dia'])); }, $visitas_7dias),
            'datasets' => [[
                'label' => 'Visitas',
                'data' => array_column($visitas_7dias, 'visitas'),
                'backgroundColor' => '#800020',
                'borderColor' => '#5c0018',
                'borderWidth' => 1
            ]]
        ],
        'options' => [
            'title' => [
                'display' => true,
                'text' => 'Visitas últimos 7 días',
                'fontSize' => 16,
                'fontColor' => '#800020'
            ],
            'legend' => ['display' => false],
            'scales' => [
                'yAxes' => [[
                    'ticks' => ['beginAtZero' => true]
                ]]
            ]
        ]
    ]);
    
    // Crear instancia de DomPDF
    $dompdf = new Dompdf();
    
    // HTML del reporte
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #800020; text-align: center; }
            .logo { text-align: center; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #800020; color: white; }
            .footer { margin-top: 30px; text-align: right; font-size: 12px; color: #666; }
            .stats-container { display: flex; justify-content: space-between; margin-bottom: 20px; }
            .stat-card { width: 48%; border: 1px solid #eee; padding: 15px; border-radius: 5px; }
            .chart-container { margin: 20px 0; }
            .chart-img { width: 100%; max-width: 500px; margin: 0 auto; display: block; }
            .no-chart { background-color: #f8f9fa; padding: 20px; text-align: center; border: 1px dashed #ccc; }
            .grid-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
            @media (max-width: 768px) {
                .grid-2col { grid-template-columns: 1fr; }
            }
        </style>
    </head>
    <body>
        <h1>Reporte de Visitas - Sistema Bibliotecario</h1>
        
        <div class="stats-container">
            <div class="stat-card">
                <h3 style="color: #800020; margin-top: 0;">Resumen General</h3>
                <table>
                    <tr>
                        <th>Métrica</th>
                        <th>Valor</th>
                    </tr>
                    <tr>
                        <td>Visitas Totales</td>
                        <td>'.$visitas_totales.'</td>
                    </tr>
                    <tr>
                        <td>Visitas Hoy</td>
                        <td>'.$visitas_hoy.'</td>
                    </tr>
                </table>
            </div>
        </div>
        
        <div class="grid-2col">
            <div class="chart-container">
                <h3 style="color: #800020;">Distribución por Tipo de Usuario</h3>';

    if ($grafica_tipo) {
        $html .= '<img src="'.$grafica_tipo.'" class="chart-img" alt="Visitas por tipo de usuario">';
    } else {
        $html .= '<div class="no-chart">No se pudo generar la gráfica</div>';
    }
    
    $html .= '
            </div>
            <div class="chart-container">
                <h3 style="color: #800020;">Alumnos por Carrera</h3>';

    if ($grafica_carrera) {
        $html .= '<img src="'.$grafica_carrera.'" class="chart-img" alt="Visitas de alumnos por carrera">';
    } else {
        $html .= '<div class="no-chart">No se pudo generar la gráfica</div>';
    }
    
    $html .= '
            </div>
        </div>
        
        <div class="chart-container">
            <h3 style="color: #800020;">Visitas últimos 7 días</h3>';

    if ($grafica_7dias) {
        $html .= '<img src="'.$grafica_7dias.'" class="chart-img" alt="Visitas últimos 7 días">';
    } else {
        $html .= '<div class="no-chart">No se pudo generar la gráfica</div>';
    }
    
    $html .= '
        </div>
        
        <h3 style="color: #800020;">Detalle de visitas por día</h3>
        <table>
            <tr>
                <th>Fecha</th>
                <th>Visitas</th>
            </tr>';
    
    foreach ($visitas_7dias as $dia) {
        $html .= '
            <tr>
                <td>'.date('d/m/Y', strtotime($dia['dia'])).'</td>
                <td>'.$dia['visitas'].'</td>
            </tr>';
    }
    
    $html .= '
        </table>
        
        <div class="footer">
            <p>Generado el: '.date('d/m/Y H:i:s').'</p>
            <p>Por: '.htmlspecialchars($_SESSION['nombre'] ?? 'Sistema').'</p>
        </div>
    </body>
    </html>';
    
    // Generar PDF
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream("reporte_visitas_".date('Y-m-d').".pdf", ["Attachment" => true]);
    exit();
}

// Configuración de rutas para imágenes
$baseDir = $_SERVER['DOCUMENT_ROOT'] . '/Web/biblioteca-digital/';
$uploadDir = $baseDir . 'Imagenes-Carussel/';
$webPath = '/Web/biblioteca-digital/Imagenes-Carussel/';

if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0766, true)) {
        $_SESSION['error'] = "No se pudo crear el directorio de imágenes." . $uploadDir;
        header("Location: admin.php");
        exit();
    }
}

// Procesar subida de imágenes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['newImage'])) {
    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $fileType = $_FILES['newImage']['type'];
    
    if (!array_key_exists($fileType, $allowedTypes)) {
        $_SESSION['error'] = "Formato no permitido. Solo JPG, PNG o WEBP";
        header("Location: admin.php");
        exit();
    }

    $originalName = pathinfo($_FILES['newImage']['name'], PATHINFO_FILENAME);
    $extension = $allowedTypes[$fileType];
    $fileName = preg_replace('/[^a-zA-Z0-9-_]/', '', $originalName) . '_' . uniqid() . '.' . $extension;
    $targetPath = $uploadDir . $fileName;

    if (move_uploaded_file($_FILES['newImage']['tmp_name'], $targetPath)) {
        $titulo = $conn->real_escape_string(trim($_POST['imageTitle']));
        $enlace = $conn->real_escape_string(trim($_POST['imageLink'] ?? ''));
        
        $sql = "INSERT INTO carrusel_imagenes (nombre_archivo, titulo, enlace) VALUES ('$fileName', '$titulo', '$enlace')";
        
        if ($conn->query($sql)) {
            $_SESSION['mensaje'] = "✅ Imagen subida correctamente";
        } else {
            $_SESSION['error'] = "❌ Error en BD: " . $conn->error;
            unlink($targetPath);
        }
    } else {
        $_SESSION['error'] = "❌ Error al subir el archivo";
    }
    header("Location: admin.php");
    exit();
}

// Procesar eliminación de imágenes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_id'])) {
    $id = (int)$_POST['eliminar_id'];
    $result = $conn->query("SELECT nombre_archivo FROM carrusel_imagenes WHERE id = $id");
    
    if ($result->num_rows > 0) {
        $img = $result->fetch_assoc();
        $filePath = $uploadDir . $img['nombre_archivo'];
        
        if ($conn->query("DELETE FROM carrusel_imagenes WHERE id = $id")) {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $_SESSION['mensaje'] = "🗑️ Imagen eliminada";
        } else {
            $_SESSION['error'] = "❌ Error al eliminar: " . $conn->error;
        }
    }
    header("Location: admin.php");
    exit();
}

// Obtener imágenes existentes
$imagenes = [];
$result = $conn->query("SELECT * FROM carrusel_imagenes ORDER BY orden, fecha_subida DESC");
if ($result) {
    $imagenes = $result->fetch_all(MYSQLI_ASSOC);
    $totalImagenes = count($imagenes);
} else {
    $totalImagenes = 0;
    $_SESSION['error'] = "Error al cargar imágenes: " . $conn->error;
}


// Obtener usuarios con información de carrera
//  jreynoso
$usuarios = [];
$result_usuarios = $conn->query("
    SELECT u.id_Usuario, u.Nombre, u.tipo_usuario, u.CURP, u.No_control_social, 
           c.id_carrera, c.nombre as nombre_carrera 
    FROM usuarios u
    LEFT JOIN carrera c ON u.id_carrera = c.id_carrera
    ORDER BY u.tipo_usuario DESC
");
if ($result_usuarios) {
    $usuarios = $result_usuarios->fetch_all(MYSQLI_ASSOC);
} else {
    $_SESSION['error'] = "Error al cargar usuarios: " . $conn->error;
}


// Obtener carreras para el modal de edición
$carreras = [];
$result_carreras = $conn->query("SELECT id_carrera, nombre FROM carrera ORDER BY nombre");
if ($result_carreras) {
    $carreras = $result_carreras->fetch_all(MYSQLI_ASSOC);
}

// Estadísticas de visitas (CON CORRECCIÓN PARA MANEJAR POSIBLES ERRORES)
$visitas_totales = 0;
$visitas_hoy = 0;
$visitas_semana = 0;

// Estadísticas de visitas
$visitas_totales = 0;
$visitas_hoy = 0;
$visitas_semana = 0;

try {
    // Verificar si la tabla visitas existe
    $tableExists = $conn->query("SELECT 1 FROM visitas LIMIT 1");
    if ($tableExists) {
        $visitas_totales = $conn->query("SELECT COUNT(*) as total FROM visitas")->fetch_assoc()['total'] ?? 0;
        
        $res_hoy = $conn->query("SELECT COUNT(*) as hoy FROM visitas WHERE DATE(fecha) = CURDATE()");
        $visitas_hoy = $res_hoy ? $res_hoy->fetch_assoc()['hoy'] ?? 0 : 0;
        
        $res_semana = $conn->query("SELECT COUNT(*) as semana FROM visitas WHERE YEARWEEK(fecha, 1) = YEARWEEK(CURDATE(), 1)");
        $visitas_semana = $res_semana ? $res_semana->fetch_assoc()['semana'] ?? 0 : 0;
    } else {
        error_log("La tabla 'visitas' no existe o está vacía");
    }
} catch (mysqli_sql_exception $e) {
    error_log("Error en consultas de estadísticas: " . $e->getMessage());
}

// Configuración de la gráfica según el período seleccionado
$periodo = $_GET['periodo'] ?? '7days';
$grafica_labels = [];
$grafica_data = [];

switch($periodo) {
    case 'today':
        // Datos por hora para hoy
        $result = $conn->query("
            SELECT HOUR(fecha) as hora, COUNT(*) as visitas 
            FROM visitas 
            WHERE DATE(fecha) = CURDATE()
            GROUP BY hora
            ORDER BY hora ASC
        ");
        
        // Rellenar todas las horas (0-23)
        for ($hora = 0; $hora < 24; $hora++) {
            $grafica_labels[] = sprintf("%02d:00", $hora);
            $grafica_data[$hora] = 0;
        }
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $grafica_data[$row['hora']] = $row['visitas'];
            }
        }
        $grafica_data = array_values($grafica_data);
        break;
        
    case '7days':
        // Últimos 7 días
        for ($i = 6; $i >= 0; $i--) {
            $fecha = date('Y-m-d', strtotime("-$i days"));
            $grafica_labels[] = date('d M', strtotime("-$i days"));
            
            $result = $conn->query("
                SELECT COUNT(*) as visitas 
                FROM visitas 
                WHERE DATE(fecha) = '$fecha'
            ");
            
            $grafica_data[] = $result ? $result->fetch_assoc()['visitas'] ?? 0 : 0;
        }
        break;
        
    case '30days':
        // Últimos 30 días agrupados por semana
        for ($i = 4; $i >= 0; $i--) {
            $fecha_inicio = date('Y-m-d', strtotime("-".($i*7)." days"));
            $fecha_fin = date('Y-m-d', strtotime("-".(($i-1)*7)." days"));
            $grafica_labels[] = date('d M', strtotime($fecha_inicio))." - ".date('d M', strtotime($fecha_fin));
            
            $result = $conn->query("
                SELECT COUNT(*) as visitas 
                FROM visitas 
                WHERE fecha >= '$fecha_inicio' AND fecha < '$fecha_fin'
            ");
            
            $grafica_data[] = $result ? $result->fetch_assoc()['visitas'] ?? 0 : 0;
        }
        break;
        
    case 'year':
        // Últimos 12 meses
        for ($i = 11; $i >= 0; $i--) {
            $mes = date('m', strtotime("-$i months"));
            $ano = date('Y', strtotime("-$i months"));
            $grafica_labels[] = date('M Y', strtotime("-$i months"));
            
            $result = $conn->query("
                SELECT COUNT(*) as visitas 
                FROM visitas 
                WHERE YEAR(fecha) = $ano AND MONTH(fecha) = $mes
            ");
            
            $grafica_data[] = $result ? $result->fetch_assoc()['visitas'] ?? 0 : 0;
        }
        break;
        
    default: // 7 días por defecto
        for ($i = 6; $i >= 0; $i--) {
            $fecha = date('Y-m-d', strtotime("-$i days"));
            $grafica_labels[] = date('d M', strtotime("-$i days"));
            
            $result = $conn->query("
                SELECT COUNT(*) as visitas 
                FROM visitas 
                WHERE DATE(fecha) = '$fecha'
            ");
            
            $grafica_data[] = $result ? $result->fetch_assoc()['visitas'] ?? 0 : 0;
        }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel de Administrador - TESVG</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root {
      --color-primario: #800020;
      --color-secundario: #5c0018;
      --color-accento: #a30029;
      --color-fondo: #f8f9fa;
      --color-texto: #212529;
      --color-header: #ffffff;
    }
    
    body {
      background-color: var(--color-fondo);
      color: var(--color-texto);
      padding-top: 140px;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    header {
      background-color: var(--color-header);
      padding: 1.5rem 0;
      position: fixed;
      top: 0;
      width: 100%;
      z-index: 1000;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .logo-container {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 40px;
      width: 100%;
      max-width: 1200px;
    }
    
    .header-logo {
      height: 70px;
      width: auto;
      max-width: 100%;
      object-fit: contain;
    }
    
    /* Sidebar styles */
    #sidebar {
      position: fixed;
      top: 0;
      right: -300px;
      width: 300px;
      height: 100vh;
      background-color: rgba(128, 0, 32, 0.9);
      backdrop-filter: blur(10px);
      box-shadow: -2px 0 15px rgba(0,0,0,0.2);
      transition: right 0.3s ease;
      z-index: 1050;
      overflow-y: auto;
    }
    
    #sidebar.active {
      right: 0;
    }
    
    .sidebar-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: rgba(0,0,0,0.5);
      z-index: 1040;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.3s ease, visibility 0.3s ease;
    }
    
    .sidebar-overlay.active {
      opacity: 1;
      visibility: visible;
    }
    
    .user-info {
      padding: 1.5rem;
      text-align: center;
      border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    
    .user-avatar {
      width: 70px;
      height: 70px;
      border-radius: 50%;
      margin: 0 auto 1rem;
      background-color: rgba(255,255,255,0.1);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.8rem;
      font-weight: bold;
      border: 2px solid rgba(255,255,255,0.2);
    }
    
    .user-name {
      font-weight: 600;
      font-size: 1.2rem;
      margin-bottom: 0.5rem;
      color: white;
    }
    
    .user-welcome {
      color: rgba(255,255,255,0.7);
      font-size: 0.9rem;
    }
    
    #sidebar .nav-link {
      color: white;
      padding: 0.75rem 1.5rem;
      margin: 0.25rem 1rem;
      border-radius: 5px;
      transition: all 0.2s;
    }
    
    #sidebar .nav-link:hover {
      background-color: rgba(255,255,255,0.1);
    }
    
    #sidebar .nav-link i {
      width: 20px;
      text-align: center;
      margin-right: 10px;
    }
    
    /* Estilos específicos del panel de admin */
    .admin-card {
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      margin-bottom: 1.5rem;
      border: none;
    }
    
    .card-header {
      background-color: var(--color-primario);
      color: white;
      padding: 0.75rem 1rem;
    }
    
    .btn-admin {
      background-color: var(--color-secundario);
      color: white;
      border: none;
      font-size: 0.9rem;
      padding: 0.375rem 0.75rem;
      transition: all 0.3s;
    }
    
    .btn-admin:hover {
      background-color: var(--color-accento);
      color: white;
    }
    
    .carousel-preview {
      height: 400px;
      object-fit: cover;
      border-radius: 6px;
    }
    
    .img-thumbnail {
      max-width: 100px;
      max-height: 80px;
      object-fit: cover;
      transition: transform 0.3s;
    }
    
    .img-thumbnail:hover {
      transform: scale(1.1);
    }
    
    .preview-container {
      margin-top: 10px;
      border: 2px dashed #ddd;
      padding: 10px;
      border-radius: 6px;
      text-align: center;
    }
    
    .preview-image {
      max-height: 200px;
      max-width: 100%;
    }
    
    .stats-card {
      background-color: #f8f9fa;
      border-left: 4px solid var(--color-primario);
      margin-bottom: 1rem;
      height: 100%;
    }
    
    .stats-card .card-body {
      padding: 1rem;
    }
    
    .chart-container {
      position: relative;
      height: 300px;
      width: 100%;
    }
    
    .period-filter {
      display: flex;
      gap: 8px;
      margin-bottom: 15px;
    }
    
    .period-filter .btn-period {
      border-radius: 20px;
      padding: 5px 12px;
      font-size: 14px;
      background: #f0f0f0;
      border: none;
      color: #333;
    }
    
    .period-filter .btn-period.active {
      background: #800020;
      color: white;
    }
    
    .export-pdf-btn {
      background: #28a745;
      color: white;
      border: none;
      padding: 8px 15px;
      border-radius: 5px;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      text-decoration: none;
    }
    
    .export-pdf-btn:hover {
      background: #218838;
      color: white;
    }
    
    @media (max-width: 767.98px) {
      body {
        padding-top: 60px;
      }
      
      #sidebar, .sidebar-overlay {
        top: 60px;
        height: calc(100vh - 60px);
      }
      
      .carousel-preview {
        height: 300px;
      }
      
      #sidebar {
        width: 280px;
      }
      
      .chart-container {
        height: 250px;
      }
    }

    .table-dark {
    background-color: var(--color-primario);
    color: white;
}

.table-hover tbody tr:hover {
    background-color: rgba(128, 0, 32, 0.1);
}

.card-header h3 {
    font-size: 1.25rem;
    margin-bottom: 0;
}
  </style>
</head>
<body>
  <!-- Sidebar (Menú lateral) -->
 <div id="sidebar">
    <div class="user-info">
      <div class="user-avatar"><?= $iniciales ?></div>
      <div class="user-welcome">Bienvenido</div>
      <div class="user-name"><?= htmlspecialchars($nombre_usuario) ?></div>
    </div>
    <nav class="nav flex-column">
      <a class="nav-link" href="#"><i class="fas fa-home"></i> Inicio</a>
      <a class="nav-link" href="registro_libro.php"><i class="fas fa-book"></i> Registrar Libro</a>
      <a class="nav-link" href="#"><i class="fas fa-cog"></i> Configuración</a>
      <a class="nav-link" href="#" onclick="return confirmLogout()"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
    </nav>
</div>

  <!-- Overlay -->
  <div class="sidebar-overlay"></div>

  <!-- Botón hamburguesa -->
  <button id="btnMenu" class="btn btn-link text-dark position-fixed" style="right: 20px; top: 15px; z-index: 1051;">
    <i class="fas fa-bars fa-lg"></i>
  </button>

  <header>
    <div class="logo-container">
      <img src="Logos/TECNM.png" alt="Gobierno de México" class="header-logo">
      <img src="Logos/TESVG.jpeg" alt="Tecnológico Nacional de México" class="header-logo">
    </div>
  </header>

  <main class="container py-3">
    <h1 class="text-center mb-4" style="color: var(--color-primario);">Panel de Administración</h1>
    
    <?php if (isset($_SESSION['mensaje'])): ?>
      <div class="alert alert-success alert-dismissible fade show">
        <?= $_SESSION['mensaje'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
      <?php unset($_SESSION['mensaje']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
      <div class="alert alert-danger alert-dismissible fade show">
        <?= $_SESSION['error'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

 <!-- Sección de Estadísticas -->
    <div class="row">
      <div class="col-md-3">
        <div class="card stats-card h-100">
          <div class="card-body">
            <h5 class="card-title"><i class="fas fa-users me-2"></i>Usuarios Registrados</h5>
            <p class="display-6"><?= count($usuarios) ?></p>
            <p class="text-muted mb-0">
              <span class="badge bg-primary"><?= $visitas_hoy ?> hoy</span>
            </p>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card stats-card h-100">
          <div class="card-body">
            <h5 class="card-title"><i class="fas fa-eye me-2"></i>Visitas Hoy</h5>
            <p class="display-6"><?= $visitas_hoy ?></p>
            <p class="text-muted mb-0"><?= $visitas_semana ?> esta semana</p>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card stats-card h-100">
          <div class="card-body">
            <h5 class="card-title"><i class="fas fa-images me-2"></i>Imágenes</h5>
            <p class="display-6"><?= $totalImagenes ?></p>
            <p class="text-muted mb-0">Actualizado: <?= date('d/m/Y') ?></p>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card stats-card h-100">
          <div class="card-body">
            <h5 class="card-title"><i class="fas fa-chart-line me-2"></i>Visitas Totales</h5>
            <p class="display-6"><?= $visitas_totales ?></p>
            <p class="text-muted mb-0">Desde el inicio</p>
          </div>
        </div>
      </div>
    </div>

   <!-- Gráfica de visitas con filtros -->
<!-- Gráfica de visitas con filtros -->
<div class="card admin-card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <i class="fas fa-chart-area me-2"></i> Estadísticas de Visitas
            <div class="period-filter mt-2">
                <a href="?periodo=today" class="btn-period <?= $periodo == 'today' ? 'active' : '' ?>">Hoy</a>
                <a href="?periodo=7days" class="btn-period <?= $periodo == '7days' ? 'active' : '' ?>">7 Días</a>
                <a href="?periodo=30days" class="btn-period <?= $periodo == '30days' ? 'active' : '' ?>">30 Días</a>
                <a href="?periodo=year" class="btn-period <?= $periodo == 'year' ? 'active' : '' ?>">Anual</a>
            </div>
        </div>
        <a href="?export=pdf&periodo=<?= $periodo ?>" class="export-pdf-btn">
            <i class="fas fa-file-pdf"></i> Exportar PDF
        </a>
    </div>
    <div class="card-body">
        <div class="chart-container">
            <canvas id="visitasChart"></canvas>
        </div>
    </div>
</div>

    <!-- Gestión del Carrusel -->
    <div class="row">
      <div class="col-md-6 mb-4">
        <div class="admin-card card h-100">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-images me-2"></i> Vista Previa del Carrusel</span>
            <span class="badge bg-light text-dark"><?= $totalImagenes ?> imágenes</span>
          </div>
          <div class="card-body">
            <?php if (!empty($imagenes)): ?>
              <div id="adminCarousel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-inner">
                  <?php foreach ($imagenes as $index => $img): ?>
                    <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                      <img src="<?= $webPath . htmlspecialchars($img['nombre_archivo']) ?>" 
                           class="d-block w-100 carousel-preview" 
                           alt="<?= htmlspecialchars($img['titulo']) ?>">
                      <div class="carousel-caption bg-dark bg-opacity-75 p-2 rounded">
                        <h5><?= htmlspecialchars($img['titulo']) ?></h5>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#adminCarousel" data-bs-slide="prev">
                  <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                  <span class="visually-hidden">Anterior</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#adminCarousel" data-bs-slide="next">
                  <span class="carousel-control-next-icon" aria-hidden="true"></span>
                  <span class="visually-hidden">Siguiente</span>
                </button>
              </div>
            <?php else: ?>
              <div class="text-center py-4">
                <i class="fas fa-images fa-3x text-muted mb-3"></i>
                <p class="text-muted">No hay imágenes en el carrusel</p>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      
      <div class="col-md-6 mb-4">
        <div class="admin-card card h-100">
          <div class="card-header">
            <i class="fas fa-upload me-2"></i> Subir Nueva Imagen
          </div>
          <div class="card-body">
            <form method="post" enctype="multipart/form-data" id="uploadForm">
              <div class="mb-3">
                <label for="newImage" class="form-label">Seleccionar imagen</label>
                <input class="form-control" type="file" id="newImage" name="newImage" required accept="image/*">
                <div class="form-text">Formatos: JPG, PNG, WEBP. Tamaño máximo: 5MB</div>
                <div class="preview-container mt-2 d-none" id="imagePreview">
                  <img id="previewImage" class="preview-image">
                </div>
              </div>
              <div class="mb-3">
                <label for="imageTitle" class="form-label">Título *</label>
                <input type="text" class="form-control" id="imageTitle" name="imageTitle" required>
              </div>
              <div class="mb-3">
                <label for="imageLink" class="form-label">Enlace (opcional)</label>
                <input type="url" class="form-control" id="imageLink" name="imageLink" placeholder="https://...">
              </div>
              <button type="submit" class="btn btn-admin">
                <i class="fas fa-upload me-1"></i> Subir Imagen
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Lista de imágenes -->
    <div class="admin-card card">
      <div class="card-header">
        <i class="fas fa-list me-2"></i> Imágenes Existentes
      </div>
      <div class="card-body">
        <?php if (!empty($imagenes)): ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead>
                <tr>
                  <th>Miniatura</th>
                  <th>Título</th>
                  <th>Archivo</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($imagenes as $img): ?>
                  <tr>
                    <td>
                      <img src="<?= $webPath . htmlspecialchars($img['nombre_archivo']) ?>" 
                           class="img-thumbnail" 
                           alt="<?= htmlspecialchars($img['titulo']) ?>">
                    </td>
                    <td><?= htmlspecialchars($img['titulo']) ?></td>
                    <td><code><?= htmlspecialchars($img['nombre_archivo']) ?></code></td>
                    <td>
                      <form method="post" class="d-inline">
                        <input type="hidden" name="eliminar_id" value="<?= $img['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger" 
                                onclick="return confirm('¿Eliminar esta imagen permanentemente?')">
                          <i class="fas fa-trash"></i> Eliminar
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="alert alert-warning mb-0">No hay imágenes en el carrusel</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Gestión de usuarios -->
    <div class="card admin-card mt-4">
      <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-center">
          <h2 class="mb-2 mb-md-0"><i class="fas fa-users me-2"></i>Usuarios del Sistema (<?= count($usuarios) ?>)</h2>
          <div class="d-flex">
              <button class="btn btn-sm btn-admin me-2" onclick="location.reload()">
                  <i class="fas fa-sync-alt"></i>
                  <span class="d-none d-md-inline"> Actualizar</span>
              </button>
              <button class="btn btn-sm btn-success" onclick="window.location.href='registro.php'">
                  <i class="fas fa-plus"></i>
                  <span class="d-none d-md-inline"> Nuevo</span>
              </button>
          </div>
      </div>
      <div class="card-body">
          <div class="table-responsive">
              <table class="table table-sm table-hover">
                  <thead>
                      <tr>
                          <th>ID</th>
                          <th>Usuario</th>
                          <th class="d-none d-md-table-cell">CURP</th>
                          <th>No. Control</th>
                          <th>Carrera</th>
                          <th>Rol</th>
                          <th>Acciones</th>
                      </tr>
                  </thead>
                  <tbody>
                      <?php foreach ($usuarios as $usuario): ?>
                          <tr>
                              <td><?= $usuario['id_Usuario'] ?></td>
                              <td><?= htmlspecialchars($usuario['Nombre']) ?></td>
                              <td class="d-none d-md-table-cell"><?= htmlspecialchars($usuario['CURP']) ?></td>
                              <td><?= $usuario['No_control_social'] ?></td>
                              <td><?= $usuario['nombre_carrera'] ?? 'Sin carrera' ?></td>
                              <td>
                                  <span class="badge <?= $usuario['tipo_usuario'] === 'admin' ? 'bg-danger' : 'bg-primary' ?>">
                                      <?= ucfirst($usuario['tipo_usuario']) ?>
                                  </span>
                              </td>
                              <td>
                                  <button class="btn btn-sm btn-admin" title="Editar" onclick="editarUsuario(<?= $usuario['id_Usuario'] ?>)">
                                      <i class="fas fa-edit"></i>
                                  </button>
                                  <?php if($usuario['tipo_usuario'] !== 'admin'): ?>
                                      <button class="btn btn-sm btn-danger" title="Eliminar" onclick="confirmarEliminarUsuario(<?= $usuario['id_Usuario'] ?>)">
                                          <i class="fas fa-trash"></i>
                                      </button>
                                  <?php endif; ?>
                              </td>
                          </tr>
                      <?php endforeach; ?>
                  </tbody>
              </table>
          </div>
      </div>
    </div>
<!-- SECCIÓN DE CONSULTAS - AÑADIR JUSTO ANTES DEL CIERRE DE MAIN -->
<div class="card admin-card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="mb-0"><i class="fas fa-search me-2"></i> Historial de Búsquedas</h3>
        <div>
            <a href="?export=consultas" class="btn btn-admin me-2">
                <i class="fas fa-file-pdf me-1"></i> Exportar Consultas
            </a>
            <button class="btn btn-admin" data-bs-toggle="modal" data-bs-target="#filtrosModal">
                <i class="fas fa-filter me-1"></i> Filtrar
            </button>
        </div>
    </div>
    
    <!-- Modal de Filtros -->
    <div class="modal fade" id="filtrosModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Filtrar Historial</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="get" action="admin.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Criterio de Búsqueda</label>
                            <select name="filtro_criterio" class="form-select">
                                <option value="">Todos</option>
                                <option value="titulo" <?= $filtro_criterio=='titulo'?'selected':'' ?>>Título</option>
                                <option value="autor" <?= $filtro_criterio=='autor'?'selected':'' ?>>Autor</option>
                                <option value="carrera" <?= $filtro_criterio=='carrera'?'selected':'' ?>>Carrera</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Término de Búsqueda</label>
                            <input type="text" name="filtro_termino" class="form-control" 
                                   value="<?= htmlspecialchars($filtro_termino) ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Aplicar Filtros</button>
                        <?php if($filtro_criterio || $filtro_termino): ?>
                            <a href="admin.php" class="btn btn-danger">Limpiar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card-body">
        <?php if($filtro_criterio || $filtro_termino): ?>
            <div class="alert alert-info mb-3">
                Filtros aplicados: 
                <?= $filtro_criterio ? "<strong>Criterio:</strong> ".htmlspecialchars($filtro_criterio) : '' ?>
                <?= $filtro_termino ? "<strong>Término:</strong> ".htmlspecialchars($filtro_termino) : '' ?>
            </div>
        <?php endif; ?>
        
        <div class="table-responsive">
        <table class="table table-hover">
    <thead class="table-dark">
        <tr>
            <th>ID</th>
            <th>Fecha/Hora</th>
            <th>Tipo</th>
            <th>Término</th>
            <th>Usuario</th>
            <th>Carrera</th>
            <th>Libro Encontrado</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($consultas as $consulta): ?>
        <tr>
            <td><?= $consulta['id_Consulta'] ?></td>
            <td><?= date('d/m/Y H:i', strtotime($consulta['fecha'])) ?></td>
            <td><?= ucfirst($consulta['criterio_busqueda']) ?></td>
            <td><?= htmlspecialchars($consulta['termino_busqueda']) ?></td>
            <td><?= $consulta['usuario'] ?: 'Anónimo' ?></td>
            <td><?= $consulta['carrera'] ?: 'No especificada' ?></td>
            <td><?= $consulta['libro'] ?: 'No encontrado' ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
        </div>
    </div>
</div>
</main> <!-- Cierre del main -->

  <!-- Modal para editar usuario -->
<div class="modal fade" id="editarUsuarioModal" tabindex="-1" aria-labelledby="editarUsuarioModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editarUsuarioModalLabel">Editar Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="formEditarUsuario">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="mb-3">
                        <label for="edit_nombre" class="form-label">Nombre completo</label>
                        <input type="text" class="form-control" id="edit_nombre" name="nombre" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_curp" class="form-label">CURP</label>
                        <input type="text" class="form-control" id="edit_curp" name="curp" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_no_control" class="form-label">Número de control</label>
                        <input type="text" class="form-control" id="edit_no_control" name="no_control" required>
                    </div>
                    

    <div class="mb-3">
    <label for="edit_tipo_usuario" class="form-label">Tipo de usuario</label>
    <select class="form-select" id="edit_tipo_usuario" name="tipo_usuario" required>
        <option value="alumno">Alumno</option>
        <option value="docente">Docente</option>
        <option value="administrativo">Administrativo</option>
    </select>
</div>
                    <div class="mb-3">
                        <label for="edit_id_carrera" class="form-label">Carrera</label>
                        <select class="form-select" id="edit_id_carrera" name="id_carrera">
                            <option value="">Selecciona una carrera</option>
                            <?php foreach ($carreras as $carrera): ?>
                                <option value="<?= $carrera['id_carrera'] ?>"><?= htmlspecialchars($carrera['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarCambiosUsuario()">Guardar cambios</button>
            </div>
        </div>
    </div>
</div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
 <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Manejo del sidebar
    document.addEventListener('DOMContentLoaded', function() {
        const btnMenu = document.getElementById('btnMenu');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        
        btnMenu.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            document.body.classList.toggle('overflow-hidden');
        });
        
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.classList.remove('overflow-hidden');
        });
        
        // Previsualización de imágenes
        document.getElementById('newImage').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            const previewContainer = document.getElementById('imagePreview');
            const previewImage = document.getElementById('previewImage');
            
            if (file.size > 5 * 1024 * 1024) {
                alert('El archivo es demasiado grande (máximo 5MB)');
                e.target.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImage.src = e.target.result;
                previewContainer.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        });
    });

    function confirmLogout() {
        if (confirm('¿Estás seguro que deseas cerrar sesión?')) {
            window.location.href = 'logout.php';
        }
        return false;
    }

    // Resto de tus funciones (editarUsuario, guardarCambiosUsuario, etc.)
    function cambiarPeriodo(periodo) {
        window.location.href = 'admin.php?periodo=' + periodo;
    }

    // Gráfica de visitas
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('visitasChart').getContext('2d');
        
        const visitasChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($grafica_labels) ?>,
                datasets: [{
                    label: 'Visitas',
                    data: <?= json_encode($grafica_data) ?>,
                    backgroundColor: 'rgba(128, 0, 32, 0.2)',
                    borderColor: 'rgba(128, 0, 32, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true,
                    pointBackgroundColor: '#800020',
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            title: function(context) {
                                return context[0].label;
                            },
                            label: function(context) {
                                return 'Visitas: ' + context.raw;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    });

    function editarUsuario(id) {
    fetch('editar_usuario.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }
            
            // Llenar el formulario del modal
            document.getElementById('edit_id').value = data.id_Usuario;
            document.getElementById('edit_nombre').value = data.Nombre || '';
            document.getElementById('edit_curp').value = data.CURP || '';
            document.getElementById('edit_no_control').value = data.No_control_social || '';
            
            // Establecer el tipo de usuario (usando los valores correctos)
            const tipoUsuarioSelect = document.getElementById('edit_tipo_usuario');
            tipoUsuarioSelect.value = data.tipo_usuario || 'alumno'; // Valor por defecto
            
            // Establecer la carrera
            document.getElementById('edit_id_carrera').value = data.id_Carrera || '';
            
            // Mostrar el modal
            const modal = new bootstrap.Modal(document.getElementById('editarUsuarioModal'));
            modal.show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar los datos del usuario');
        });
}

   function guardarCambiosUsuario() {
    const formData = new FormData(document.getElementById('formEditarUsuario'));
    
    fetch('editar_usuario.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Usuario actualizado correctamente');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Ocurrió un error al actualizar el usuario');
    });
}

    function confirmarEliminarUsuario(id) {
        if (confirm('¿Estás seguro de eliminar este usuario? Esta acción no se puede deshacer.')) {
            eliminarUsuario(id);
        }
    }

    function eliminarUsuario(id) {
        fetch('eliminar_usuario.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'id=' + id
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Usuario eliminado correctamente');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Ocurrió un error al eliminar el usuario');
        });
    }
</script>
</body>
</html>