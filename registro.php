<?php
session_start();
require_once 'Conect.php';

// Verificar permisos (solo admin puede registrar usuarios)
if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Función para limpiar y validar datos
function limpiarDatos($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Procesar el formulario de registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Limpiar y validar datos del formulario
    $nombre = limpiarDatos($_POST['nombre']);
    $curp = limpiarDatos($_POST['curp']);
    $id_carrera = isset($_POST['id_carrera']) ? (int)$_POST['id_carrera'] : 0;
    $no_control = limpiarDatos($_POST['no_control']);
    $tipo_usuario = isset($_POST['tipo_usuario']) ? limpiarDatos($_POST['tipo_usuario']) : '';
    
    // Validar CURP (expresión regular para formato básico)
    $patron_curp = '/^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[0-9A-Z]{2}$/';
    
    // Validaciones
       $errores = [];
    
    if (empty($nombre) || strlen($nombre) > 255) {
        $errores[] = "El nombre es requerido y no debe exceder 255 caracteres";
    }
    
    if (empty($curp) || !preg_match($patron_curp, strtoupper($curp))) {
        $errores[] = "La CURP debe tener un formato válido (18 caracteres alfanuméricos)";
    } else {
        $curp = strtoupper($curp); // Normalizar a mayúsculas
    }
    
    if ($id_carrera <= 0) {
        $errores[] = "Selecciona una carrera válida";
    }
    
    if (empty($no_control) || !is_numeric($no_control)) {
        $errores[] = "El número de control es requerido y debe ser numérico";
    }
    
    if (empty($tipo_usuario) || !in_array($tipo_usuario, ['alumno', 'docente', 'administrativo'])) {
        $errores[] = "Selecciona un tipo de usuario válido";
    }
    
    // Verificar si el número de control ya existe
    if (empty($errores)) {
        $stmt = $conn->prepare("SELECT id_Usuario FROM usuarios WHERE No_control_social = ?");
        $stmt->bind_param("s", $no_control);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errores[] = "El número de control ya está registrado";
        }
        $stmt->close();
    }
    
    // Si no hay errores, insertar en la base de datos
    if (empty($errores)) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO usuarios (Nombre, CURP, id_Carrera, No_control_social, tipo_usuario)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssiis", $nombre, $curp, $id_carrera, $no_control, $tipo_usuario);
            
            if ($stmt->execute()) {
                $_SESSION['mensaje_exito'] = "Usuario registrado exitosamente";
                header("Location: registro.php");
                exit();
            } else {
                $errores[] = "Error al registrar el usuario: " . $conn->error;
            }
        } catch (mysqli_sql_exception $e) {
            $errores[] = "Error en la base de datos: " . $e->getMessage();
        } finally {
            if (isset($stmt)) {
                $stmt->close();
            }
        }
    }
    
    // Si hay errores, guardarlos en sesión para mostrarlos
    if (!empty($errores)) {
        $_SESSION['errores_registro'] = $errores;
        $_SESSION['datos_formulario'] = [
            'nombre' => $nombre,
            'curp' => $curp,
            'id_carrera' => $id_carrera,
            'no_control' => $no_control,
            'tipo_usuario' => $tipo_usuario
        ];
        header("Location: registro.php");
        exit();
    }
}

// Obtener carreras para el select
$carreras = [];
$result = $conn->query("SELECT id_Carrera, nombre FROM Carrera ORDER BY nombre");
if ($result) {
    $carreras = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Usuarios | Sistema TESVG</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --color-primario: #800020;
            --color-secundario: #5c0018;
            --color-fondo: #f8f9fa;
        }
        
        body {
            background-color: var(--color-fondo);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .navbar-brand img {
            height: 40px;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        
        .card-header {
            background-color: var(--color-primario);
            color: white;
            padding: 1.5rem;
        }
        
        .btn-primary {
            background-color: var(--color-primario);
            border-color: var(--color-primario);
            padding: 0.5rem 1.5rem;
        }
        
        .btn-primary:hover {
            background-color: var(--color-secundario);
            border-color: var(--color-secundario);
        }
        
        .btn-outline-primary {
            color: var(--color-primario);
            border-color: var(--color-primario);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--color-primario);
            border-color: var(--color-primario);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--color-primario);
            box-shadow: 0 0 0 0.25rem rgba(128, 0, 32, 0.25);
        }
        
        .success-container {
            background-color: rgba(40, 167, 69, 0.1);
            border-radius: 10px;
            padding: 2rem;
        }
        
        @media (max-width: 768px) {
            .card {
                border-radius: 0;
                box-shadow: none;
            }
            
            body {
                background-color: white;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="admin.php">
                <img src="assets/logo.png" alt="TESVG" class="img-fluid">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="admin.php"><i class="bi bi-speedometer2 me-1"></i> Panel</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="registro.php"><i class="bi bi-person-plus me-1"></i> Registrar</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right me-1"></i> Salir</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Contenido principal -->
    <main class="flex-grow-1 py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <?php if (isset($_SESSION['mensaje_exito'])): ?>
                        <div class="success-container text-center mb-5">
                            <div class="mb-4">
                                <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                            </div>
                            <h3 class="mb-3">¡Registro exitoso!</h3>
                            <p class="lead mb-4">El usuario ha sido registrado correctamente en el sistema.</p>
                            <div class="d-flex justify-content-center gap-3">
                                <a href="registro.php" class="btn btn-primary">
                                    <i class="bi bi-person-plus me-2"></i> Registrar otro
                                </a>
                                <a href="admin.php" class="btn btn-outline-primary">
                                    <i class="bi bi-arrow-left me-2"></i> Volver al panel
                                </a>
                            </div>
                        </div>
                        <?php unset($_SESSION['mensaje_exito']); ?>
                    <?php else: ?>
                        <div class="card mb-4">
                            <div class="card-header text-center">
                                <h2 class="mb-0"><i class="bi bi-person-plus me-2"></i>Registro de Usuario</h2>
                            </div>
                            <div class="card-body p-4">
                                <?php if (isset($_SESSION['errores_registro'])): ?>
                                    <div class="alert alert-danger alert-dismissible fade show">
                                        <h5 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>Error en el registro</h5>
                                        <ul class="mb-0">
                                            <?php foreach ($_SESSION['errores_registro'] as $error): ?>
                                                <li><?= $error ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                    <?php unset($_SESSION['errores_registro']); ?>
                                <?php endif; ?>
                                
                                <form method="post" novalidate>
                                    <div class="row g-3">
                                        <!-- Nombre -->
<div class="col-md-12">
    <label for="nombre" class="form-label">Nombre completo</label>
    <input type="text" class="form-control" id="nombre" name="nombre" 
           value="<?= htmlspecialchars($_SESSION['datos_formulario']['nombre'] ?? '') ?>" 
           required autofocus autocomplete="off">
    <div class="invalid-feedback">
        Por favor ingresa un nombre válido.
    </div>
</div>

<!-- CURP -->
<div class="col-md-6">
    <label for="curp" class="form-label">CURP</label>
    <input type="text" class="form-control text-uppercase" id="curp" name="curp" 
           value="<?= htmlspecialchars($_SESSION['datos_formulario']['curp'] ?? '') ?>" 
           maxlength="18" required autocomplete="off">
    <div class="invalid-feedback">
        Ingresa una CURP válida (18 caracteres).
    </div>
</div>

<!-- Número de control -->
<div class="col-md-6">
    <label for="no_control" class="form-label">Número de control</label>
    <input type="text" class="form-control" id="no_control" name="no_control" 
           value="<?= htmlspecialchars($_SESSION['datos_formulario']['no_control'] ?? '') ?>" 
           required autocomplete="off">
    <div class="invalid-feedback">
        Ingresa un número de control válido.
    </div>
</div>
                                        <!-- Carrera -->
                                        <div class="col-md-6">
                                            <label for="id_carrera" class="form-label">Carrera</label>
                                            <select class="form-select" id="id_carrera" name="id_carrera" required>
                                                <option value="">Selecciona una carrera</option>
                                                <?php foreach ($carreras as $carrera): ?>
                                                    <option value="<?= $carrera['id_Carrera'] ?>" 
                                                        <?= isset($_SESSION['datos_formulario']['id_carrera']) && $_SESSION['datos_formulario']['id_carrera'] == $carrera['id_Carrera'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($carrera['nombre']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="invalid-feedback">
                                                Selecciona una carrera.
                                            </div>
                                        </div>
                                        
                                        <!-- Tipo de usuario -->
                                        <div class="col-md-6">
                                            <label for="tipo_usuario" class="form-label">Tipo de usuario</label>
                                            <select class="form-select" id="tipo_usuario" name="tipo_usuario" required>
                                                <option value="">Selecciona un tipo</option>
                                                <option value="alumno" <?= isset($_SESSION['datos_formulario']['tipo_usuario']) && $_SESSION['datos_formulario']['tipo_usuario'] == 'alumno' ? 'selected' : '' ?>>Alumno</option>
                                                <option value="docente" <?= isset($_SESSION['datos_formulario']['tipo_usuario']) && $_SESSION['datos_formulario']['tipo_usuario'] == 'docente' ? 'selected' : '' ?>>Docente</option>
                                                <option value="administrativo" <?= isset($_SESSION['datos_formulario']['tipo_usuario']) && $_SESSION['datos_formulario']['tipo_usuario'] == 'administrativo' ? 'selected' : '' ?>>Administrativo</option>
                                            </select>
                                            <div class="invalid-feedback">
                                                Selecciona un tipo de usuario.
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between mt-4">
                                        <a href="admin.php" class="btn btn-outline-secondary">
                                            <i class="bi bi-arrow-left me-1"></i> Cancelar
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-save me-1"></i> Registrar Usuario
                                        </button>
                                    </div>
                                </form>
                                <?php unset($_SESSION['datos_formulario']); ?>
                            </div>
                        </div>
                        
                        <div class="text-center text-muted small mt-3">
                            <p>Sistema de Gestión TESVG &copy; <?= date('Y') ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    <form method="post" novalidate autocomplete="off">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validación del formulario del lado del cliente
        (function() {
            'use strict';
            
            // Obtener el formulario
            const form = document.querySelector('form');
            
            // Validar al enviar
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                form.classList.add('was-validated');
            }, false);
            
            // Convertir CURP a mayúsculas automáticamente
            const curpInput = document.getElementById('curp');
            if (curpInput) {
                curpInput.addEventListener('input', function() {
                    this.value = this.value.toUpperCase();
                });
            }
            
            // Validar número de control (solo números)
            const noControlInput = document.getElementById('no_control');
            if (noControlInput) {
                noControlInput.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
            }
        })();
    </script>
</body>
</html>