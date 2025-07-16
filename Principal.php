<?php
// Inicio simple de sesión
session_start();

// Verificación mejorada de usuario
if (!isset($_SESSION['tipo_usuario'])) {
    // Destruir sesión previa
    session_unset();
    session_destroy();
    
    // Redirigir a login sin crear bucle
    header("Location: login.php?redirect=principal");
    exit();
}

// Obtener información del usuario de la sesión
$nombre_usuario = $_SESSION['nombre'] ?? 'Usuario';
$iniciales = '';
$partes_nombre = explode(' ', $nombre_usuario);
foreach ($partes_nombre as $parte) {
    if (!empty($parte)) {
        $iniciales .= strtoupper(substr($parte, 0, 1));
    }
    if (strlen($iniciales) >= 2) break;
}
if (empty($iniciales)) $iniciales = 'U';

require_once 'Conect.php';

// Registrar visita
$pagina = 'principal.php';
try {
    $sql = "INSERT INTO visitas (usuario_id, tipo_usuario, pagina, carrera, fecha) 
            VALUES (?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    
    // Obtener nombre de la carrera si es alumno
    $carrera_nombre = null;
    if ($_SESSION['tipo_usuario'] === 'alumno' && isset($_SESSION['id_Carrera'])) {
        $stmt_carrera = $conn->prepare("SELECT Nombre FROM carrera WHERE id_Carrera = ?");
        $stmt_carrera->bind_param("i", $_SESSION['id_Carrera']);
        $stmt_carrera->execute();
        $carrera_nombre = $stmt_carrera->get_result()->fetch_assoc()['Nombre'] ?? null;
    }
    
    $stmt->bind_param("isss", $_SESSION['id_Usuario'], $_SESSION['tipo_usuario'], $pagina, $carrera_nombre);
    $stmt->execute();
} catch (Exception $e) {
    error_log("Error al registrar visita: " . $e->getMessage());
}
// Obtener imágenes del carrusel desde la base de datos
$items_carrusel = [];
if ($conn) {
    $result = $conn->query("SELECT * FROM carrusel_imagenes ORDER BY orden, fecha_subida DESC");
    if ($result) {
        $items_carrusel = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        error_log("Error al cargar imágenes del carrusel: " . $conn->error);
    }
}

// Configuración de rutas para imágenes
$baseDir = $_SERVER['DOCUMENT_ROOT'] . '/Web/SISTEMA BIBLIOTECARIO/';
$uploadDir = $baseDir . 'Imagenes Carussel/';
$webPath = '/Web/SISTEMA BIBLIOTECARIO/Imagenes Carussel/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Página Principal - TESVG</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root {
      --color-primario: #800020;
      --color-secundario: #5c0018;
      --color-accento: #a30029;
      --color-fondo: #f8f9fa;
      --color-texto: #212529;
      --carrusel-alto: 400px;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: var(--color-fondo);
      color: var(--color-texto);
      margin: 0;
      padding: 0;
    }

    /* HEADER UNIFICADO */
    .header-completo {
      background: white;
      padding-top: 15px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      position: fixed;
      width: 100%;
      top: 0;
      z-index: 1000;
    }

    .logo-container {
      display: flex;
      justify-content: center;
      gap: 40px;
      align-items: center;
      flex-wrap: wrap;
      padding-bottom: 15px;
      max-width: 1200px;
      margin: 0 auto;
    }

    .header-logo {
      height: 70px;
      width: auto;
      max-width: 180px;
    }

    /* BARRA DE NAVEGACIÓN */
    .nav-principal {
      background: var(--color-primario);
      padding: 12px 0;
    }

    .nav-contenido {
      display: flex;
      justify-content: center;
      flex-wrap: wrap;
      gap: 10px;
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 20px;
    }

    .nav-boton {
      color: white;
      text-decoration: none;
      padding: 8px 15px;
      font-weight: 500;
      transition: all 0.3s;
    }

    .nav-boton:hover {
      opacity: 0.9;
      text-decoration: underline;
    }

    /* BARRA DE SERVICIOS */
    .servicios-container {
      background: var(--color-secundario);
      padding: 15px 0;
    }

    .servicios-contenido {
      display: flex;
      justify-content: center;
      flex-wrap: wrap;
      gap: 20px;
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 20px;
    }

    .servicio-boton {
      display: flex;
      flex-direction: column;
      align-items: center;
      color: white;
      text-decoration: none;
      min-width: 100px;
    }

    .servicio-icono {
      background: var(--color-accento);
      width: 60px;
      height: 60px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 8px;
      transition: transform 0.3s;
    }

    .servicio-boton:hover .servicio-icono {
      transform: scale(1.1);
    }

    /* CONTENIDO PRINCIPAL */
    main {
      max-width: 1200px;
      margin: 280px auto 30px;
      padding: 0 20px;
    }

    /* CARRUSEL (estilos originales) */
    .carousel-container {
      position: relative;
      width: 90%;
      max-width: 1000px;
      margin: 2rem auto;
      overflow: hidden;
      border-radius: 8px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      background-color: #f5f5f5;
    }

    .carousel-images {
      display: flex;
      transition: transform 0.5s ease-in-out;
    }

    .carousel-slide {
      position: relative;
      width: 100%;
      flex-shrink: 0;
    }

    .carousel-slide img {
      width: 100%;
      height: var(--carrusel-alto);
      object-fit: contain;
      object-position: center;
      margin: 0 auto;
      display: block;
      background-color: #f5f5f5;
    }

    .carousel-caption {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      background-color: rgba(0, 0, 0, 0.7);
      color: white;
      padding: 15px 20px;
      text-align: center;
      font-size: 1.1rem;
    }

    .carousel-btn {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      background-color: rgba(255, 255, 255, 0.7);
      color: var(--color-primario);
      border: none;
      padding: 0.8rem;
      cursor: pointer;
      font-size: 1.2rem;
      z-index: 10;
      border-radius: 50%;
      transition: all 0.3s;
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .carousel-btn:hover {
      background-color: rgba(255, 255, 255, 0.9);
    }

    .prev-btn {
      left: 15px;
    }

    .next-btn {
      right: 15px;
    }

    /* FOOTER */
    footer {
      background: var(--color-secundario);
      color: white;
      text-align: center;
      padding: 25px;
      margin-top: 40px;
    }

    footer a {
      color: white;
      text-decoration: none;
      transition: all 0.3s;
    }

    footer a:hover {
      text-decoration: underline;
    }

    /* SIDEBAR (estilos originales) */
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

    /* RESPONSIVE */
    @media (max-width: 992px) {
      main {
        margin-top: 250px;
      }
    }

    @media (max-width: 768px) {
      .header-logo {
        height: 60px;
      }
      
      .nav-contenido, .servicios-contenido {
        gap: 8px;
      }
      
      .servicio-boton {
        font-size: 14px;
      }
      
      main {
        margin-top: 240px;
      }
    }

    @media (max-width: 576px) {
      .logo-container {
        flex-direction: column;
        gap: 15px;
      }
      
      .nav-contenido {
        flex-direction: column;
        align-items: center;
      }
      
      .servicios-contenido {
        gap: 15px;
      }
      
      main {
        margin-top: 380px;
      }
    }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <div id="sidebar">
    <div class="user-info">
      <div class="user-avatar"><?= $iniciales ?></div>
      <div class="user-welcome">Bienvenido</div>
      <div class="user-name"><?= htmlspecialchars($nombre_usuario) ?></div>
    </div>
    <nav class="nav flex-column">
      <a class="nav-link" href="#"><i class="fas fa-home"></i> Inicio</a>
      <a class="nav-link" href="#" onclick="return confirmLogout()"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a>
    </nav>
  </div>

  <div class="sidebar-overlay"></div>

  <button id="btnMenu" class="btn btn-link text-dark position-fixed" style="right: 20px; top: 15px; z-index: 1051;">
    <i class="fas fa-bars fa-lg"></i>
  </button>

  <!-- Header completo -->
  <header class="header-completo">
    <div class="logo-container">
      <img src="Logos/TECNM.png" alt="Tecnológico Nacional de México" class="header-logo">
      <img src="Logos/TESVG.jpeg" alt="TESVG" class="header-logo">
    </div>
    
    <nav class="nav-principal">
      <div class="nav-contenido">
        <a href="https://tesvg.edomex.gob.mx/alumnos" class="nav-boton">Conócenos</a>
        <a href="https://tesvg.edomex.gob.mx/aspirantes_convocatorias" class="nav-boton">Convocatorias</a>
        <a href="https://tesvg.edomex.gob.mx/oferta_educativa" class="nav-boton">Oferta Educativa</a>
        <a href="https://tesvg.edomex.gob.mx/alumnos" class="nav-boton">Alumnos</a>
        <a href="https://tesvg.edomex.gob.mx/docentes" class="nav-boton">Docentes</a>
        <a href="https://tesvg.edomex.gob.mx/registrate" class="nav-boton">Egresados</a>
        <a href="https://tesvg.edomex.gob.mx/vinculacion_extension" class="nav-boton">Vinculación</a>
      </div>
    </nav>
    
    <div class="servicios-container">
      <div class="servicios-contenido">
        <a href="https://biblioteca.ejemplo.edu.mx" class="servicio-boton" target="_blank">
          <div class="servicio-icono">
            <i class="fas fa-book-open"></i>
          </div>
          <span>Biblioteca Digital</span>
        </a>
        
        <a href="https://repositorios.ejemplo.edu.mx" class="servicio-boton" target="_blank">
          <div class="servicio-icono">
            <i class="fas fa-database"></i>
          </div>
          <span>Repositorios</span>
        </a>
        
        <a href="XD.php" class="servicio-boton" target="_blank">
          <div class="servicio-icono">
            <i class="fas fa-university"></i>
          </div>
          <span>Biblioteca Física</span>
        </a>
        
        <a href="https://cultura.ejemplo.edu.mx" class="servicio-boton" target="_blank">
          <div class="servicio-icono">
            <i class="fas fa-theater-masks"></i>
          </div>
          <span>Actividades Culturales</span>
        </a>
      </div>
    </div>
  </header>

  <main>
    <!-- Carrusel -->
<div class="carousel-container">
  <div class="carousel-images" id="carouselImages">
    <?php if (!empty($items_carrusel)): ?>
      <?php foreach ($items_carrusel as $item): ?>
        <div class="carousel-slide">
          <?php if (!empty($item['enlace'])): ?>
            <a href="<?= htmlspecialchars($item['enlace']) ?>" target="_blank">
          <?php endif; ?>
          
          <img src="<?= $webPath . htmlspecialchars($item['nombre_archivo']) ?>" 
               alt="<?= htmlspecialchars($item['titulo'] ?? 'Imagen carrusel') ?>"
               onerror="this.onerror=null; this.src='ruta/imagen_alternativa.jpg';">
          
          <?php if (!empty($item['titulo'])): ?>
            <div class="carousel-caption"><?= htmlspecialchars($item['titulo']) ?></div>
          <?php endif; ?>
          
          <?php if (!empty($item['enlace'])): ?>
            </a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="error-imagen">
        <i class="fas fa-images"></i>
        <p>No hay imágenes disponibles en el carrusel</p>
      </div>
    <?php endif; ?>
  </div>
  
  <?php if (!empty($items_carrusel) && count($items_carrusel) > 1): ?>
    <button class="carousel-btn prev-btn" onclick="moveCarousel(-1)"><i class="fas fa-chevron-left"></i></button>
    <button class="carousel-btn next-btn" onclick="moveCarousel(1)"><i class="fas fa-chevron-right"></i></button>
  <?php endif; ?>
</div>
  </main>

  <footer>
    Derechos Reservados &copy; <?= date('Y') ?> - Tecnológico de Estudios Superiores de Villa Guerrero<br>
    <a href="https://tesvg.edomex.gob.mx/">https://tesvg.edomex.gob.mx/</a>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Carrusel
    let currentIndex = 0;
    const images = document.getElementById('carouselImages');
    const totalImages = images.children.length;
    
    function moveCarousel(direction) {
      currentIndex = (currentIndex + direction + totalImages) % totalImages;
      const offset = -currentIndex * 100;
      images.style.transform = `translateX(${offset}%)`;
    }
    
    <?php if (!empty($items_carrusel)): ?>
      setInterval(() => moveCarousel(1), 6000);
    <?php endif; ?>
    
    // Sidebar
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
    });
    
    function confirmLogout() {
      if (confirm('¿Estás seguro que deseas cerrar sesión?')) {
        window.location.href = 'logout.php';
      }
      return false;
    }
  </script>
</body>
</html>