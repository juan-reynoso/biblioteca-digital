<?php
session_start();

if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once 'conexion.php';

// [El código de procesamiento del formulario permanece igual...]
// Procesamiento del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar datos requeridos
        if (empty($_POST['titulo'])) {
            $_SESSION['error'] = "El título del libro es obligatorio";
            header("Location: registro_libro.php");
            exit();
        }

        // Iniciar transacción
        $conn->beginTransaction();

        // 1. Procesar editorial (requerida por la FK en Libros)
        if (empty($_POST['editorial'])) {
            $_SESSION['error'] = "Debe especificar una editorial";
            header("Location: registro_libro.php");
            exit();
        }

        // Buscar o insertar editorial
        $stmt = $conn->prepare("SELECT id_Editorial FROM Editoriales WHERE Nombre = ?");
        $stmt->execute([$_POST['editorial']]);
        $idEditorial = $stmt->fetchColumn();
        
        if (!$idEditorial) {
            // Obtener nuevo ID para editorial (ya que no es autoincremental)
            $stmt = $conn->query("SELECT MAX(id_Editorial) FROM Editoriales");
            $maxId = $stmt->fetchColumn();
            $newId = $maxId ? $maxId + 1 : 1;
            
            $stmt = $conn->prepare("INSERT INTO Editoriales (id_Editorial, Nombre) VALUES (?, ?)");
            $stmt->execute([$newId, $_POST['editorial']]);
            $idEditorial = $newId;
        }

        // 2. Insertar el libro principal
        $stmt = $conn->prepare("INSERT INTO Libros (titulo, id_Editorial, año_Adquisicion, fecha_Registro) 
                               VALUES (?, ?, ?, CURDATE())");
        $stmt->execute([
            $_POST['titulo'],
            $idEditorial,
            !empty($_POST['anio_adquisicion']) ? (int)$_POST['anio_adquisicion'] : null
        ]);
        $idLibro = $conn->lastInsertId();

        // 3. Procesar autores (opcional)
        if (!empty($_POST['autores'])) {
            $autores = array_filter(array_map('trim', explode(',', $_POST['autores'])));
            
            foreach ($autores as $autorNombre) {
                // Buscar o insertar autor
                $stmt = $conn->prepare("SELECT id_Autor FROM Autores WHERE Nombre_Autor = ?");
                $stmt->execute([$autorNombre]);
                $idAutor = $stmt->fetchColumn();
                
                if (!$idAutor) {
                    $stmt = $conn->prepare("INSERT INTO Autores (Nombre_Autor) VALUES (?)");
                    $stmt->execute([$autorNombre]);
                    $idAutor = $conn->lastInsertId();
                }
                
                // Insertar relación libro-autor
                $stmt = $conn->prepare("INSERT IGNORE INTO Detalle_autor (id_Autor, id_Libro) VALUES (?, ?)");
                $stmt->execute([$idAutor, $idLibro]);
            }
        }

        // 4. Procesar carreras (opcional)
        if (!empty($_POST['carreras'])) {
            $carreras = array_filter(array_map('trim', explode(',', $_POST['carreras'])));
            
            foreach ($carreras as $carreraNombre) {
                // Verificar si la carrera existe (debe existir previamente)
                $stmt = $conn->prepare("SELECT id_Carrera FROM Carrera WHERE Nombre = ?");
                $stmt->execute([$carreraNombre]);
                $idCarrera = $stmt->fetchColumn();
                
                if (!$idCarrera) {
                    $conn->rollBack();
                    $_SESSION['error'] = "La carrera '$carreraNombre' no existe. Debe crearla primero.";
                    header("Location: registro_libro.php");
                    exit();
                }
                
                // Insertar relación libro-carrera
                $stmt = $conn->prepare("INSERT IGNORE INTO Libros_Carreras (id_Libro, id_Carrera) VALUES (?, ?)");
                $stmt->execute([$idLibro, $idCarrera]);
            }
        }

        // Confirmar transacción
        $conn->commit();

        // Redirigir con mensaje de éxito
        $_SESSION['success'] = "Libro registrado exitosamente con ID: $idLibro";
        header("Location: admin.php");
        exit();

    } catch (PDOException $e) {
        // Revertir transacción en caso de error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $_SESSION['error'] = "Error al registrar el libro: " . $e->getMessage();
        header("Location: registro_libro.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Libros | Sistema Bibliotecario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --color-primario: #800020;
            --color-secundario: #5c0018;
            --color-accento: #a30029;
            --color-fondo: #f8f9fa;
            --color-texto: #212529;
            --color-header: #ffffff;
            --color-nav: var(--color-primario);
        }
        
        body {
            background-color: var(--color-fondo);
            color: var(--color-texto);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 70px;
        }
        
        .navbar {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            background-color: var(--color-nav) !important;
        }
        
        .navbar-brand {
            font-weight: 700;
            color: var(--color-header) !important;
        }
        
        .nav-link {
            color: rgba(255,255,255,0.85) !important;
            font-weight: 500;
            margin: 0 10px;
            transition: all 0.3s;
        }
        
        .nav-link:hover {
            color: white !important;
            transform: translateY(-2px);
        }
        
        .form-container {
            max-width: 800px;
            margin: 40px auto;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .form-title {
            color: var(--color-primario);
            margin-bottom: 30px;
            text-align: center;
            font-weight: 700;
            position: relative;
            padding-bottom: 15px;
        }
        
        .form-title:after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: var(--color-accento);
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--color-texto);
        }
        
        .form-label.required:after {
            content: " *";
            color: #e74c3c;
        }
        
        .form-control {
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid #ddd;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--color-accento);
            box-shadow: 0 0 0 0.25rem rgba(163, 0, 41, 0.25);
        }
        
        .btn-primary {
            background-color: var(--color-primario);
            border: none;
            padding: 12px 25px;
            font-weight: 600;
            letter-spacing: 0.5px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background-color: var(--color-secundario);
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            border: none;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .suggestions {
            position: absolute;
            z-index: 1000;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            display: none;
        }
        
        .suggestion-item {
            padding: 10px 15px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .suggestion-item:hover {
            background-color: #f8f9fa;
            color: var(--color-primario);
        }
        
        .form-text {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .alert {
            border-radius: 8px;
        }
        
        .input-group-text {
            background-color: var(--color-primario);
            color: white;
            border: none;
        }
        
        footer {
            background-color: var(--color-secundario);
            color: white;
            padding: 20px 0;
            margin-top: 50px;
        }
        
        .navbar-toggler {
            border-color: rgba(255,255,255,0.5);
        }
        
        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg viewBox='0 0 30 30' xmlns='http://www.w3.org/2000/svg'%3e%3cpath stroke='rgba(255, 255, 255, 0.8)' stroke-width='2' stroke-linecap='round' stroke-miterlimit='10' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }
    </style>
</head>
<body>
    <!-- Barra de navegación -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="admin.php">
                <i class="fas fa-book-open me-2"></i>Biblioteca
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="registro_libro.php">
                            <i class="fas fa-plus-circle me-1"></i> Nuevo Libro
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin.php">
                            <i class="fas fa-tachometer-alt me-1"></i> Panel
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i> Salir
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="form-container">
            <h2 class="form-title">
                <i class="fas fa-book me-2"></i>Registro de Nuevo Libro
            </h2>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= $_SESSION['error'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <form method="post" id="libroForm">
                <div class="row mb-4">
                    <div class="col-md-12">
                        <label for="titulo" class="form-label required">Título del Libro</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-heading"></i></span>
                            <input type="text" class="form-control" id="titulo" name="titulo" required
                                   placeholder="Ingrese el título completo del libro">
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label for="editorial" class="form-label">Editorial</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-building"></i></span>
                            <input type="text" class="form-control" id="editorial" name="editorial" 
                                   list="editorialesList" placeholder="Nombre de la editorial">
                            <datalist id="editorialesList">
                                <?php 
                                $editoriales = $conn->query("SELECT Nombre FROM Editoriales ORDER BY Nombre")->fetchAll(PDO::FETCH_COLUMN);
                                foreach ($editoriales as $editorial): ?>
                                    <option value="<?= htmlspecialchars($editorial) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="form-text mt-1">Seleccione una editorial existente o ingrese una nueva</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="anio_adquisicion" class="form-label">Año de Adquisición</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                            <input type="number" class="form-control" id="anio_adquisicion" name="anio_adquisicion" 
                                   min="1900" max="<?= date('Y') ?>" placeholder="<?= date('Y') ?>">
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="autores" class="form-label">Autores</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user-edit"></i></span>
                        <input type="text" class="form-control" id="autores" name="autores" 
                               placeholder="Ej: Gabriel García Márquez, Mario Vargas Llosa">
                    </div>
                    <div class="form-text mt-1">Separe múltiples autores con comas</div>
                    <div id="autoresSuggestions" class="suggestions"></div>
                </div>
                
                <div class="mb-4">
                    <label for="carreras" class="form-label">Carreras Relacionadas</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-graduation-cap"></i></span>
                        <input type="text" class="form-control" id="carreras" name="carreras" 
                               placeholder="Ej: Literatura, Ingeniería Civil, Medicina">
                    </div>
                    <div class="form-text mt-1">Separe múltiples carreras con comas</div>
                    <div id="carrerasSuggestions" class="suggestions"></div>
                </div>
                
                <div class="d-grid gap-3 mt-5">
                    <button type="submit" class="btn btn-primary btn-lg py-3">
                        <i class="fas fa-save me-2"></i> REGISTRAR LIBRO
                    </button>
                    <a href="admin.php" class="btn btn-secondary btn-lg py-3">
                        <i class="fas fa-arrow-left me-2"></i> VOLVER AL PANEL
                    </a>
                </div>
            </form>
        </div>
    </div>

    <footer class="text-center">
        <div class="container">
            <p class="mb-0">Sistema Bibliotecario &copy; <?= date('Y') ?> | Todos los derechos reservados</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script>
        $(document).ready(function() {
            // Cargar sugerencias de autores
            $.get('get_autores.php', function(data) {
                const autores = JSON.parse(data);
                $('#autores').on('input', function() {
                    const input = $(this).val().toLowerCase();
                    const lastTerm = input.split(',').pop().trim();
                    
                    if (lastTerm.length > 1) {
                        const matches = autores.filter(autor => 
                            autor.toLowerCase().includes(lastTerm.toLowerCase())
                        );
                        
                        const suggestions = $('#autoresSuggestions');
                        suggestions.empty();
                        
                        if (matches.length > 0) {
                            matches.slice(0, 5).forEach(match => {
                                suggestions.append(
                                    `<div class="suggestion-item">${match}</div>`
                                );
                            });
                            suggestions.show();
                        } else {
                            suggestions.hide();
                        }
                    } else {
                        $('#autoresSuggestions').hide();
                    }
                });
                
                $(document).on('click', '#autoresSuggestions .suggestion-item', function() {
                    const currentValue = $('#autores').val();
                    const terms = currentValue.split(',');
                    terms[terms.length - 1] = $(this).text();
                    $('#autores').val(terms.join(',') + (terms.length > 0 ? ', ' : ''));
                    $('#autoresSuggestions').hide();
                    $('#autores').focus();
                });
            });
            
            // Cargar sugerencias de carreras
            $.get('get_carreras.php', function(data) {
                const carreras = JSON.parse(data);
                $('#carreras').on('input', function() {
                    const input = $(this).val().toLowerCase();
                    const lastTerm = input.split(',').pop().trim();
                    
                    if (lastTerm.length > 1) {
                        const matches = carreras.filter(carrera => 
                            carrera.toLowerCase().includes(lastTerm.toLowerCase())
                        );
                        
                        const suggestions = $('#carrerasSuggestions');
                        suggestions.empty();
                        
                        if (matches.length > 0) {
                            matches.slice(0, 5).forEach(match => {
                                suggestions.append(
                                    `<div class="suggestion-item">${match}</div>`
                                );
                            });
                            suggestions.show();
                        } else {
                            suggestions.hide();
                        }
                    } else {
                        $('#carrerasSuggestions').hide();
                    }
                });
                
                $(document).on('click', '#carrerasSuggestions .suggestion-item', function() {
                    const currentValue = $('#carreras').val();
                    const terms = currentValue.split(',');
                    terms[terms.length - 1] = $(this).text();
                    $('#carreras').val(terms.join(',') + (terms.length > 0 ? ', ' : ''));
                    $('#carrerasSuggestions').hide();
                    $('#carreras').focus();
                });
            });
            
            // Ocultar sugerencias al hacer clic fuera
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#autores, #autoresSuggestions').length) {
                    $('#autoresSuggestions').hide();
                }
                if (!$(e.target).closest('#carreras, #carrerasSuggestions').length) {
                    $('#carrerasSuggestions').hide();
                }
            });
            
            // Validación del formulario
            $('#libroForm').submit(function(e) {
                let valid = true;
                
                // Validar título
                if ($('#titulo').val().trim() === '') {
                    $('#titulo').addClass('is-invalid');
                    valid = false;
                } else {
                    $('#titulo').removeClass('is-invalid');
                }
                
                if (!valid) {
                    e.preventDefault();
                    alert('Por favor complete los campos requeridos');
                }
            });
        });
    </script>
</body>
</html>