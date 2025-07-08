<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: login.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Página Principal - TESVG</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', sans-serif;
    }

    body {
      background-color: #fafafa;
    }

    header {
      background-color: #f8f8f8;
      padding: 1rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 2px solid #ccc;
    }

    .logo-container {
      display: flex;
      align-items: center;
      gap: 20px;
    }

    .header-logo {
      height: 60px;
      width: auto;
      max-width: 100%;
    }

    .user-info {
      color: #560c1a;
      font-weight: bold;
      text-align: right;
      margin-right: 20px;
    }

    .logout-btn {
      background-color: #560c1a;
      color: white;
      padding: 5px 10px;
      border-radius: 5px;
      text-decoration: none;
      font-size: 0.9rem;
      cursor: pointer;
    }

    .logout-btn:hover {
      background-color: #7e2231;
    }

    nav {
      background-color: #560c1a;
      color: white;
      display: flex;
      justify-content: center;
      padding: 0.5rem;
      font-size: 0.95rem;
    }

    nav ul {
      list-style: none;
      display: flex;
      gap: 1.5rem;
      flex-wrap: wrap;
      justify-content: center;
    }

    nav ul li {
      cursor: pointer;
    }

    main {
      padding: 2rem;
      display: flex;
      justify-content: center;
      background-color: #fafafa;
    }

    .carousel-container {
      position: relative;
      width: 90%;
      max-width: 800px;
      overflow: hidden;
      border-radius: 8px;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    }

    .carousel-images {
      display: flex;
      transition: transform 0.5s ease-in-out;
    }

    .carousel-images img {
      width: 100%;
      flex-shrink: 0;
    }

    .carousel-btn {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      background-color: rgba(0, 0, 0, 0.4);
      color: white;
      border: none;
      padding: 0.5rem 1rem;
      cursor: pointer;
      font-size: 1.5rem;
      z-index: 10;
    }

    .prev-btn {
      left: 10px;
    }

    .next-btn {
      right: 10px;
    }

    .botones-container {
      display: flex;
      justify-content: center;
      flex-wrap: wrap;
      gap: 2rem;
      padding: 2rem;
      background-color: #560c1a;
    }

    .boton-link {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      text-decoration: none;
      color: white;
      padding: 1rem;
      border-radius: 12px;
      background-color: #7e2231;
      width: 160px;
      transition: background-color 0.3s, transform 0.2s;
    }

    .boton-link:hover {
      background-color: #a43845;
      transform: scale(1.05);
    }

    .boton-link i {
      font-size: 2rem;
      margin-bottom: 0.5rem;
      color: #f4c542;
    }

    footer {
      background-color: #333;
      color: white;
      padding: 1rem;
      text-align: center;
      font-size: 0.9rem;
    }

    /* Estilos para el modal de logout */
    #logoutModal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0,0,0,0.5);
      z-index: 1000;
      justify-content: center;
      align-items: center;
    }

    .modal-content {
      background: white;
      padding: 2rem;
      border-radius: 10px;
      text-align: center;
      max-width: 300px;
      width: 90%;
    }

    .spinner {
      margin: 0 auto 1rem;
      width: 50px;
      height: 50px;
      border: 5px solid #f3f3f3;
      border-top: 5px solid #560c1a;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
  </style>
</head>
<body>
<header>
  <div class="logo-container">
    <img src="GBMX.png" alt="Gobierno de México" class="header-logo">
    <img src="tecnm.png" alt="Tecnológico Nacional de México" class="header-logo">
    <img src="TESVG.jpeg" alt="TESVG" class="header-logo">
  </div>
  <div class="user-info">
    Bienvenido: <?php echo htmlspecialchars($_SESSION['nombre']); ?><br>
    Sesión #<?php echo $_SESSION['login_count']; ?><br>
    <a href="#" onclick="confirmLogout()" class="logout-btn">Cerrar sesión</a>
  </div>
</header>

<style>
  header {
    background-color: #f8f8f8;
    padding: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid #ccc;
    position: relative;
    flex-wrap: wrap; /* Permite que los elementos se ajusten en pantallas pequeñas */
  }

  .logo-container {
    display: flex;
    justify-content: flex-start; /* Alinea los logos a la izquierda */
    align-items: center;
    gap: 20px; /* Espacio uniforme entre logos */
    flex-grow: 1; /* Ocupa el espacio disponible */
    max-width: calc(100% - 250px); /* Deja espacio para la info de usuario */
  }

  .header-logo {
    height: 60px;
    width: auto;
    max-width: 100%;
  }

  .user-info {
    color: #560c1a;
    font-weight: bold;
    text-align: right;
    min-width: 200px; /* Ancho mínimo para la info de usuario */
    background-color: #f8f8f8; /* Fondo sólido para mejor legibilidad */
    padding: 5px 10px;
    border-radius: 5px;
    margin-left: 20px; /* Espacio entre logos y user-info */
  }

  .logout-btn {
    background-color: #560c1a;
    color: white;
    padding: 5px 10px;
    border-radius: 5px;
    text-decoration: none;
    font-size: 0.9rem;
    cursor: pointer;
    display: inline-block;
    margin-top: 5px;
  }

  .logout-btn:hover {
    background-color: #7e2231;
  }

  /* Responsive para pantallas pequeñas */
  @media (max-width: 768px) {
    .logo-container {
      justify-content: center;
      max-width: 100%;
      margin-bottom: 10px;
    }
    
    .user-info {
      margin-left: 0;
      text-align: center;
      width: 100%;
    }
  }
</style>

  <nav>
    <ul>
      <li>Conócenos</li>
      <li>Convocatorias</li>
      <li>Oferta Educativa</li>
      <li>Estudiantes</li>
      <li>Docentes</li>
      <li>Egresados</li>
      <li>Transparencia</li>
    </ul>
  </nav>

  <main>
    <div class="carousel-container">
      <div class="carousel-images" id="carouselImages">
        <img src="convocatoria2025_1.jpg" alt="Convocatoria 1">
        <img src="convocatoria2025_2.jpg" alt="Convocatoria 2">
        <img src="convocatoria2025_3.jpg" alt="Convocatoria 3">
      </div>
      <button class="carousel-btn prev-btn" onclick="moveCarousel(-1)"><i class="fas fa-chevron-left"></i></button>
      <button class="carousel-btn next-btn" onclick="moveCarousel(1)"><i class="fas fa-chevron-right"></i></button>
    </div>
  </main>

  <section class="botones-container">
    <a class="boton-link" href="https://biblioteca.ejemplo.edu.mx" target="_blank">
      <i class="fas fa-book-open"></i>
      Biblioteca Digital
    </a>
    <a class="boton-link" href="https://repositorios.ejemplo.edu.mx" target="_blank">
      <i class="fas fa-database"></i>
      Repositorios
    </a>
    <a class="boton-link" href="https://fisica.ejemplo.edu.mx" target="_blank">
      <i class="fas fa-university"></i>
      Biblioteca Física
    </a>
    <a class="boton-link" href="https://cultura.ejemplo.edu.mx" target="_blank">
      <i class="fas fa-theater-masks"></i>
      Actividades Culturales
    </a>
  </section>

  <footer>
    Derechos Reservados &copy; 2025 - Tecnológico de Estudios Superiores de Villa Guerrero
  </footer>

  <!-- Modal para el logout -->
  <div id="logoutModal">
    <div class="modal-content">
      <div class="spinner"></div>
      <p>Cerrando sesión...</p>
    </div>
  </div>

  <script>
    // Carousel functionality
    let currentIndex = 0;
    const images = document.getElementById('carouselImages');
    const totalImages = images.children.length;

    function moveCarousel(direction) {
      currentIndex += direction;
      if (currentIndex < 0) currentIndex = totalImages - 1;
      if (currentIndex >= totalImages) currentIndex = 0;
      const offset = -currentIndex * 100;
      images.style.transform = `translateX(${offset}%)`;
    }

    // Auto-rotate carousel
    setInterval(() => moveCarousel(1), 6000);

    // Logout confirmation
    function confirmLogout() {
      // Mostrar el modal con el spinner
      document.getElementById('logoutModal').style.display = 'flex';
      
      // Redirigir después de 1 segundo (para que se vea el spinner)
      setTimeout(function() {
        window.location.href = 'logout.php';
      }, 1000);
      
      return false;
    }
  </script>
</body>
</html>