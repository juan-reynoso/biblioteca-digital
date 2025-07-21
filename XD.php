<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Búsqueda de Libros</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #560c1a;
            --secondary-color: #7e2231;
            --accent-color: #9d2933;
            --light-bg: #f5f5f5;
        }
        
        .header-title {
            font-family: 'Playfair Display', serif;
            color: var(--primary-color);
            position: relative;
            padding-bottom: 15px;
        }
        
        .header-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: var(--primary-color);
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
        
        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--light-bg);
        }
        
        .search-card {
            border-radius: 10px;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .form-control, .form-select {
            border-radius: 6px;
            padding: 10px 15px;
        }
        
        .container {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.5s ease;
        }

        /* Solo ajusté esto para la imagen */
        .header-image {
            width: 200spx; /* Reduje solo 50px del original (350px) */
            height: 300px;
            object-fit: contain;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <header class="text-center mb-5">
                    <h1 class="header-title mb-3">Búsqueda de Libros</h1>
                    <div class="d-flex justify-content-center mb-3">
                        <img src="Logos/Halcon.png" alt="Libros" class="header-image">
                    </div>
                    <p class="text-muted">Encuentra el material bibliográfico que necesitas</p>
                </header>
                
                <!-- El resto del código se mantiene exactamente igual -->
                <div class="card search-card mb-4">
                    <div class="card-body p-4">
                        <form action="buscar.php" method="get" class="search-form" autocomplete="off">
                            <div class="mb-3">
                                <label for="criterio" class="form-label fw-medium">Buscar por:</label>
                                <select name="criterio" id="criterio" class="form-select" onchange="toggleCarreraField()" autocomplete="off">
                                    <option value="titulo">Título del libro</option>
                                    <option value="autor">Autor</option>
                                    <option value="carrera">Carrera asociada</option>
                                </select>
                            </div>
                            
                            <div class="mb-3" id="termino-group">
                                <label for="termino" class="form-label fw-medium">Término de búsqueda:</label>
                                <input type="text" name="termino" id="termino" class="form-control" placeholder="Ingrese su búsqueda..." required autocomplete="off">
                            </div>
                            
                            <div class="mb-3" id="carrera-group" style="display: none;">
                                <label for="carrera" class="form-label fw-medium">Seleccione carrera:</label>
                                <select name="carrera" id="carrera" class="form-select" autocomplete="off">
                                    <?php
                                    require_once 'conexion.php';
                                    try {
                                        $stmt = $conn->query("SELECT * FROM carrera");
                                        $carreras = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($carreras as $carrera) {
                                            echo '<option value="' . htmlspecialchars($carrera['id_Carrera']) . '">' . 
                                                 htmlspecialchars($carrera['Nombre']) . '</option>';
                                        }
                                    } catch(PDOException $e) {
                                        echo '<option value="">Error al cargar carreras</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-burgundy w-100 py-2">
                                <i class="fas fa-search me-2"></i> Buscar Libros
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="text-center">
                    <a href="logout.php" class="btn btn-burgundy px-4">
                        <i class="fas fa-home me-2"></i> Cerrar Sesión
                    </a>
                </div>
                
                <footer class="text-center text-muted small mt-4">
                    <p>Sistema de Biblioteca &copy; <?php echo date('Y'); ?> | Tecnológico de Estudios Superiores de Villa Guerrero</p>
                </footer>
            </div>
        </div>
    </div>

    <script>
        function toggleCarreraField() {
            const criterio = document.getElementById('criterio').value;
            const terminoGroup = document.getElementById('termino-group');
            const carreraGroup = document.getElementById('carrera-group');
            
            if (criterio === 'carrera') {
                terminoGroup.style.display = 'none';
                carreraGroup.style.display = 'block';
                document.getElementById('termino').required = false;
                document.getElementById('carrera').required = true;
            } else {
                terminoGroup.style.display = 'block';
                carreraGroup.style.display = 'none';
                document.getElementById('termino').required = true;
                document.getElementById('carrera').required = false;
            }
        }
        
        window.onload = function() {
            toggleCarreraField();
            document.querySelector('.container').style.opacity = '1';
            document.querySelector('.container').style.transform = 'translateY(0)';
        };
    </script>
</body>
</html>
