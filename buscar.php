<?php
session_start();
require_once 'Conexion.php';

// Verificar y crear tabla consulta si no existe
$conn->exec("
    CREATE TABLE IF NOT EXISTS consulta (
        id_Consulta INT PRIMARY KEY AUTO_INCREMENT,
        fecha DATETIME NOT NULL,
        criterio_busqueda VARCHAR(20) NOT NULL,
        termino_busqueda VARCHAR(255) NOT NULL,
        id_Usuario INT,
        id_Libro INT,
        carrera_usuario VARCHAR(100),
        FOREIGN KEY (id_Usuario) REFERENCES Usuarios(id_Usuario) ON DELETE SET NULL,
        FOREIGN KEY (id_Libro) REFERENCES Libros(id_Libro) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Obtener parámetros de búsqueda
$criterio = $_GET['criterio'] ?? 'titulo';
$termino = $_GET['termino'] ?? '';
$carrera = $_GET['carrera'] ?? '';

// Si el criterio es carrera, usamos el valor del select
if ($criterio === 'carrera') {
    $termino = $carrera;
}

// Validar criterio
$columnasPermitidas = ['titulo', 'autor', 'carrera'];
if (!in_array($criterio, $columnasPermitidas)) {
    die("Criterio de búsqueda no válido");
}

try {
    // Consulta para obtener libros
    $sql = "SELECT l.*, 
           GROUP_CONCAT(DISTINCT a.Nombre_Autor SEPARATOR ', ') as autores, 
           GROUP_CONCAT(DISTINCT c.Nombre SEPARATOR ', ') as carreras,
           e.Nombre as editorial
    FROM libros l
    LEFT JOIN detalle_autor da ON l.id_Libro = da.id_Libro
    LEFT JOIN autores a ON da.id_Autor = a.id_Autor
    LEFT JOIN libros_carreras dc ON l.id_Libro = dc.id_Libro
    LEFT JOIN carrera c ON dc.id_Carrera = c.id_Carrera
    LEFT JOIN editoriales e ON l.id_Editorial = e.id_Editorial";

    if ($criterio === 'titulo') {
        $sql .= " WHERE l.titulo LIKE :termino";
        $terminoBusqueda = "%".trim($termino)."%";
    } 
    elseif ($criterio === 'autor') {
        $sql .= " WHERE a.Nombre_Autor LIKE :termino";
        $terminoBusqueda = "%".trim($termino)."%";
    }
    elseif ($criterio === 'carrera') {
        $sql .= " WHERE c.id_Carrera = :termino";
        $terminoBusqueda = trim($termino);
    }
    
    $sql .= " GROUP BY l.id_Libro";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':termino', $terminoBusqueda, $criterio === 'carrera' ? PDO::PARAM_INT : PDO::PARAM_STR);
    $stmt->execute();
    $libros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Registrar la consulta (incluso para usuarios no autenticados)
// Modificar la sección de registro de consultas (buscar.php):

// Modificar la sección de registro de consultas
if (!empty($termino)) {
    $idUsuario = $_SESSION['id_Usuario'] ?? null;
    $carreraUsuario = null;
    
    // Verificar si el usuario existe
    if ($idUsuario !== null) {
        try {
            $stmtCheck = $conn->prepare("SELECT id_Usuario FROM usuarios WHERE id_Usuario = ?");
            $stmtCheck->execute([$idUsuario]);
            if (!$stmtCheck->fetch()) {
                $idUsuario = null;
            } else {
                // Obtener información del usuario si existe
                $stmtUser = $conn->prepare("
                    SELECT u.id_Usuario, u.Nombre, c.Nombre as carrera 
                    FROM usuarios u
                    LEFT JOIN carrera c ON u.id_carrera = c.id_Carrera
                    WHERE u.id_Usuario = ?
                ");
                $stmtUser->execute([$idUsuario]);
                $userInfo = $stmtUser->fetch(PDO::FETCH_ASSOC);
                
                if ($userInfo) {
                    $carreraUsuario = $userInfo['carrera'];
                }
            }
        } catch(PDOException $e) {
            error_log("Error al obtener info de usuario: " . $e->getMessage());
            $idUsuario = null;
        }
    }

    if (!empty($libros)) {
        foreach ($libros as $libro) {
            $stmt = $conn->prepare("INSERT INTO consulta 
                (fecha, criterio_busqueda, termino_busqueda, id_Usuario, id_Libro, carrera_usuario) 
                VALUES (NOW(), ?, ?, ?, ?, ?)");
            $stmt->execute([
                $criterio, 
                $termino, 
                $idUsuario,
                $libro['id_Libro'],
                $carreraUsuario
            ]);
        }
    } else {
        // Búsqueda sin resultados
        $stmt = $conn->prepare("INSERT INTO consulta 
            (fecha, criterio_busqueda, termino_busqueda, id_Usuario, id_Libro, carrera_usuario) 
            VALUES (NOW(), ?, ?, ?, NULL, ?)");
        $stmt->execute([
            $criterio, 
            $termino, 
            $idUsuario,
            $carreraUsuario
        ]);
    }
}
    
} catch(PDOException $e) {
    die("Error en la consulta: ".$e->getMessage());
}

$filtro_criterio = $_GET['filtro_criterio'] ?? '';
$filtro_termino = $_GET['filtro_termino'] ?? '';

// Consulta base para el historial
$sql_consultas = "
    SELECT c.*, u.Nombre as usuario, l.titulo as libro 
    FROM consulta c
    LEFT JOIN Usuarios u ON c.id_Usuario = u.id_Usuario
    LEFT JOIN Libros l ON c.id_Libro = l.id_Libro
";

// Aplicar filtros si existen
$where = [];
$params = [];

if ($filtro_criterio) {
    $where[] = "c.criterio_busqueda = :filtro_criterio";
    $params[':filtro_criterio'] = $filtro_criterio;
}

if ($filtro_termino) {
    $where[] = "c.termino_busqueda LIKE :filtro_termino";
    $params[':filtro_termino'] = "%$filtro_termino%";
}

if (!empty($where)) {
    $sql_consultas .= " WHERE " . implode(" AND ", $where);
}

$sql_consultas .= " ORDER BY c.fecha DESC LIMIT 50";

// Preparar y ejecutar con PDO
$stmt_consultas = $conn->prepare($sql_consultas);
$stmt_consultas->execute($params);
$consultas = $stmt_consultas->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultados de Búsqueda</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #560c1a;
            --secondary-color: #7e2231;
            --accent-color: #9d2933;
            --light-bg: #f5f5f5;
        }
        
        body {
            background-color: var(--light-bg);
            font-family: 'Roboto', sans-serif;
            padding-bottom: 120px;
        }
        
        .header-title {
            font-family: 'Playfair Display', serif;
            color: var(--primary-color);
        }
        
        .search-info {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .table thead {
            background-color: var(--primary-color);
            color: white;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(86, 12, 26, 0.05);
        }
        
        .btn-burgundy {
            background-color: var(--primary-color);
            color: white;
            border: none;
        }
        
        .btn-burgundy:hover {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .no-results-icon {
            color: var(--accent-color);
            font-size: 3rem;
        }
        
        .text-primary {
            color: var(--primary-color) !important;
        }
        
        .fw-semibold {
            font-weight: 500;
        }
        
        .floating-buttons {
            position: fixed;
            bottom: 20px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: center;
            gap: 10px;
            z-index: 1000;
            flex-wrap: wrap;
            padding: 0 15px;
        }
        
        .floating-buttons .btn {
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            padding: 10px 15px;
            border-radius: 50px;
            font-weight: 500;
            transition: all 0.3s ease;
            flex-grow: 1;
            max-width: 200px;
            text-align: center;
            white-space: nowrap;
        }
        
        .floating-buttons .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.2);
        }
        
        .carrera-list {
            margin: 0;
            padding-left: 0;
            list-style-type: none;
        }
        
        .carrera-list li {
            padding: 2px 0;
        }
        
        @media (max-width: 768px) {
            .floating-buttons {
                gap: 8px;
            }
            .floating-buttons .btn {
                padding: 8px 12px;
                font-size: 0.9rem;
                max-width: calc(33% - 8px);
            }
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <header class="text-center mb-4">
                    <h1 class="header-title mb-3">Resultados de Búsqueda</h1>
                </header>
                
                <div class="search-info row p-3 mb-4">
                    <div class="col-md-4 mb-3 mb-md-0">
                        <div class="fw-bold text-primary small">CRITERIO:</div>
                        <div class="fw-semibold">
                            <?php 
                                $criterioTexto = [
                                    'titulo' => 'Título',
                                    'autor' => 'Autor',
                                    'carrera' => 'Carrera'
                                ];
                                echo $criterioTexto[$criterio] ?? ucfirst($criterio); 
                            ?>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3 mb-md-0">
                        <div class="fw-bold text-primary small">BÚSQUEDA:</div>
                        <div class="fw-semibold">
                            <?php 
                                if ($criterio === 'carrera') {
                                    // Obtener nombre de la carrera
                                    $stmt = $conn->prepare("SELECT Nombre FROM carrera WHERE id_Carrera = ?");
                                    $stmt->execute([$termino]);
                                    $nombreCarrera = $stmt->fetchColumn();
                                    echo htmlspecialchars($nombreCarrera ? $nombreCarrera : 'Desconocida');
                                } else {
                                    echo htmlspecialchars($termino);
                                }
                            ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="fw-bold text-primary small">RESULTADOS:</div>
                        <div class="fw-semibold"><?php echo count($libros); ?></div>
                    </div>
                </div>
                
                <?php if (count($libros) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Título</th>
                                <th>Autor(es)</th>
                                <th>Editorial</th>
                                <th>Carrera(s)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($libros as $libro): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($libro['id_Libro']); ?></td>
                                <td><?php echo htmlspecialchars($libro['titulo']); ?></td>
                                <td><?php echo htmlspecialchars($libro['autores'] ?? 'Desconocido'); ?></td>
                                <td><?php echo htmlspecialchars($libro['editorial'] ?? 'Desconocida'); ?></td>
                                <td>
                                    <?php if (!empty($libro['carreras'])): ?>
                                        <ul class="carrera-list">
                                            <?php 
                                            $carreras = explode(', ', $libro['carreras']);
                                            foreach ($carreras as $carrera): 
                                            ?>
                                                <li><?php echo htmlspecialchars(trim($carrera)); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        General
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center p-5 bg-white rounded">
                    <div class="no-results-icon mb-3">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <h3 class="mb-3">No se encontraron resultados</h3>
                    <p class="mb-4">No hay libros que coincidan con tu búsqueda.</p>
                    <?php if ($criterio === 'carrera'): ?>
                    <div class="text-start mx-auto" style="max-width: 500px;">
                        <p class="fw-medium mb-2">Sugerencias:</p>
                        <ul class="text-start">
                            <li>Verifica que hayas seleccionado una carrera válida</li>
                            <li>Intenta con un criterio de búsqueda diferente</li>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <footer class="text-center text-muted small mt-5 pt-3">
                    <p>Sistema de Biblioteca &copy; <?php echo date('Y'); ?> | Consulta realizada el <?php echo date('d/m/Y H:i:s'); ?></p>
                </footer>
            </div>
        </div>
    </div>

    <!-- Botones flotantes -->
    <div class="floating-buttons">
        <a href="XD.php" class="btn btn-burgundy">
            <i class="fas fa-search me-2"></i> Nueva Búsqueda
        </a>
        <a href="Principal.php" class="btn btn-burgundy">
            <i class="fas fa-book me-2"></i> Página Principal
        </a>
        <a href="logout.php" class="btn btn-burgundy">
            <i class="fas fa-home me-2"></i> Cerrar Sesión
        </a>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>