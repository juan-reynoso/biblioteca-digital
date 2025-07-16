<?php
session_start();
date_default_timezone_set("America/Mexico_City");

// Configuración de seguridad
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

// Función para generar CAPTCHA
function generarCaptcha() {
    $caracteres = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $longitud = 6;
    $captcha = '';
    for ($i = 0; $i < $longitud; $i++) {
        $captcha .= $caracteres[rand(0, strlen($caracteres) - 1)];
    }
    $_SESSION['captcha'] = $captcha;
    return $captcha;
}

// Función para validar CAPTCHA
function validarCaptcha($input) {
    if (empty($_SESSION['captcha'])) {
        return false;
    }
    return strtoupper(trim($input)) === $_SESSION['captcha'];
}

// Función para validar usuario
function validarUsuario($conn, $tabla, $columna_id, $no_social, $curp) {
    $sql = "SELECT * FROM $tabla WHERE $columna_id = ? AND Curp = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Error en la consulta: " . $conn->error);
    }
    $stmt->bind_param("is", $no_social, $curp);
    $stmt->execute();
    $result = $stmt->get_result();
    return ($result->num_rows === 1) ? $result->fetch_assoc() : false;
}

function registrarVisita($conn, $usuario_id, $tipo_usuario, $pagina) {
    try {
        // Para todos los tipos de usuario excepto admin
        if($tipo_usuario != 'admin') {
            $sql = "INSERT INTO visitas (usuario_id, tipo_usuario, pagina, fecha) 
                    VALUES (?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iss", $usuario_id, $tipo_usuario, $pagina);
        } else {
            // Para admins que no están en la tabla Usuarios
            $sql = "INSERT INTO visitas (tipo_usuario, pagina, fecha) 
                    VALUES (?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $tipo_usuario, $pagina);
        }
        $stmt->execute();
    } catch (Exception $e) {
        // Opcional: Registrar el error sin interrumpir el flujo
        error_log("Error al registrar visita: " . $e->getMessage());
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verificar CAPTCHA primero
    if (!isset($_POST['captcha']) || !validarCaptcha($_POST['captcha'])) {
        echo "<script>
            alert('El código CAPTCHA ingresado es incorrecto');
            window.location.href = 'login.php';
        </script>";
        exit();
    }

    $conn = new mysqli("localhost", "diego", "D13GO109", "bibliotecadig");
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }

    $no_social = $_POST['numero_control'];
    $curp = $_POST['contrasena'];

    // 1. Verificar si es administrador
    $usuario = validarUsuario($conn, "administrador", "no_Social", $no_social, $curp);
    if ($usuario) {
    $_SESSION['usuario'] = $usuario;
    $_SESSION['tipo_usuario'] = 'admin';
    $_SESSION['id_Usuario'] = $usuario['no_Social'];
    $_SESSION['nombre'] = $usuario['Nombre'] ?? 'Administrador';
    $_SESSION['login_time'] = time(); // Añadimos tiempo de login
    $_SESSION['admin_logged_in'] = true; 
    // Configurar la duración de la sesión (8 horas = 28800 segundos)
    ini_set('session.gc_maxlifetime', 28800);
    session_set_cookie_params(28800);
    
    registrarVisita($conn, $_SESSION['id_Usuario'], $_SESSION['tipo_usuario'], 'login');
    header("Location: admin.php");
    exit();
    }

    // 2. Verificar si es usuario general
    $usuario = validarUsuario($conn, "Usuarios", "No_control_social", $no_social, $curp);
    if ($usuario) {
        $_SESSION['usuario'] = $usuario;
        $_SESSION['tipo_usuario'] = $usuario['tipo_usuario'] ?? 'alumno';
        $_SESSION['id_Usuario'] = $usuario['id_Usuario'];
        $_SESSION['nombre'] = $usuario['Nombre'] ?? 'Usuario';
        $_SESSION['id_Carrera'] = $usuario['id_carrera'] ?? null;
        $_SESSION['login_count'] = ($_SESSION['login_count'] ?? 0) + 1;
        
        registrarVisita($conn, $_SESSION['id_Usuario'], $_SESSION['tipo_usuario'], 'login');
        
        switch ($_SESSION['tipo_usuario']) {
            case 'admin':
                header("Location: admin.php");
                exit();
            case 'bibliotecario':
                header("Location: bibliotecario.php");
                exit();
            case 'alumno':
            default:
                header("Location: principal.php");
                exit();
        }
    }

    echo "<script>
        alert('Número de control o CURP incorrectos');
        window.location.href = 'login.php';
    </script>";
    exit();
}

// Generar nuevo CAPTCHA al cargar la página
$captcha_text = generarCaptcha();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Iniciar Sesión - Instituto Tecnológico</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap');
    
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', 'Segoe UI', sans-serif;
    }
    
    body { 
      margin: 0; 
      background: #f5f8ff; 
      min-height: 100vh;
    }
    
    .container { 
      display: flex; 
      min-height: 100vh;
    }
    
    .left-side { 
      flex: 1;
      background: url('Logos/IMG_1430.JPEG') no-repeat center center/cover;
      color: white;
      padding: 2rem;
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
      text-shadow: 0 1px 4px rgba(0,0,0,0.6);
    }
    
    .left-side h1 {
      font-size: 1.8rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
      line-height: 1.3;
    }
    
    .right-side {
      flex: 1;
      background-color: #ffffff;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem;
      box-shadow: -8px 0 20px rgba(0, 65, 179, 0.1);
    }
    
    .login-box {
      width: 100%;
      max-width: 400px;
      text-align: center;
      background-color: #ffffff;
      padding: 2.5rem;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .logo-container {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 2rem;
      width: 100%;
    }
    
    .logo-container img {
      width: 80px;
      height: auto;
      user-select: none;
    }
    
    h2 {
      font-weight: 600;
      color: #000104;
      margin-bottom: 0.25rem;
      font-size: 1.5rem;
    }
    
    h3 {
      font-weight: 400;
      color: #666;
      margin-bottom: 2rem;
      font-size: 0.95rem;
    }
    
    .input-wrapper {
      position: relative;
      margin-bottom: 1.5rem;
      text-align: left;
    }
    
    .input-field {
      width: 100%;
      padding: 0.85rem 3rem 0.85rem 3rem;
      font-size: 1rem;
      border: 1.8px solid #ddd;
      border-radius: 8px;
      transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }
    
    .input-field:focus {
      border-color: #0041b3;
      outline: none;
      box-shadow: 0 0 6px rgba(0, 65, 179, 0.2);
    }
    
    .input-icon {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #888;
    }
    
    .password-toggle {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: #888;
      background: none;
      border: none;
      padding: 0.5rem;
      z-index: 2;
    }
    
    .login-button {
      width: 100%;
      padding: 0.85rem;
      background-color: #0041b3;
      color: white;
      font-size: 1rem;
      font-weight: 600;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-top: 0.5rem;
    }
    
    .login-button:hover {
      background-color: #00308a;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 65, 179, 0.3);
    }
    
    .footer-text {
      margin-top: 1.5rem;
      font-size: 0.8rem;
      color: #888;
      user-select: none;
    }
    
    /* Estilos para el CAPTCHA - Versión mejorada */
.captcha-container {
  display: flex;
  align-items: center;
  margin-bottom: 1.5rem;
  gap: 8px;
  background: #f8f9fa;
  padding: 10px;
  border-radius: 8px;
  border: 1px solid #ddd;
}

.captcha-code {
  flex: 1;
  padding: 8px;
  text-align: center;
  font-family: 'Courier New', monospace;
  font-size: 0.9rem;
  font-weight: bold;
  letter-spacing: 2px;
  color: #0041b3;
  background: #fff;
  border-radius: 4px;
  border: 1px dashed #ccc;
  user-select: none;
  min-width: 0; /* Permite que el texto se ajuste */
  overflow: hidden;
  text-overflow: ellipsis;
}

.captcha-input {
  flex: 1.5;
  padding: 0.75rem;
  font-size: 0.9rem;
  border: 1.8px solid #ddd;
  border-radius: 6px;
  transition: border-color 0.3s ease;
  min-width: 0; /* Para evitar desbordamiento */
}

.captcha-input:focus {
  border-color: #0041b3;
  outline: none;
}

.refresh-captcha {
  background: none;
  border: none;
  color: #0041b3;
  cursor: pointer;
  font-size: 1rem;
  padding: 5px;
  flex-shrink: 0;
}

/* Ajustes para móviles */
@media (max-width: 768px) {
  .captcha-container {
    flex-direction: row;
    flex-wrap: wrap;
  }
  
  .captcha-code {
    flex: 1 0 100%;
    margin-bottom: 8px;
    font-size: 0.8rem;
    padding: 6px;
  }
  
  .captcha-input {
    flex: 1;
    min-width: 120px;
  }
}
  </style>
</head>
<body>
  <div class="container">
    <div class="left-side">
      <h1>Tecnológico de Estudios Superiores de Villa Guerrero</h1>
    </div>

    <div class="right-side">
      <div class="login-box">
        <div class="logo-container">
          <img src="Logos/TECNM.png" alt="TECNM" />
          <img src="Logos/TESVG.jpeg" alt="Biblioteca Digital" />
        </div>

        <h2>Inicia Sesión</h2>
        <h3> Tecnológico de Estudios Superiores de Villa Guerrero</h3>

        <form method="POST" action="login.php" autocomplete="off">
          <div class="input-wrapper">
            <i class="fas fa-user input-icon"></i>
            <input type="text" name="numero_control" class="input-field" placeholder="NÚMERO DE CONTROL" required />
          </div>
          
          <div class="input-wrapper">
            <i class="fas fa-lock input-icon"></i>
            <input type="password" name="contrasena" id="password" class="input-field" placeholder="CURP" required />
            <button type="button" class="password-toggle" onclick="togglePassword()">
              <i class="fas fa-eye"></i>
            </button>
          </div>
          
          <!-- Sección CAPTCHA - Versión mejorada -->
<div class="captcha-container">
  <div class="captcha-code" id="captcha-text" title="Código CAPTCHA"><?php echo $captcha_text; ?></div>
  <input type="text" name="captcha" class="captcha-input" placeholder="Ingrese el código" required />
  <button type="button" class="refresh-captcha" onclick="refreshCaptcha()" title="Actualizar CAPTCHA">
    <i class="fas fa-sync-alt"></i>
  </button>
</div>
          
          <button type="submit" class="login-button">Ingresar</button>
        </form>

        <div class="footer-text">
          &copy; 2025 Tecnológico Superiores de Villa Guerrero
        </div>
      </div>
    </div>
  </div>

  <script>
    function togglePassword() {
      const passwordField = document.getElementById('password');
      const toggleIcon = document.querySelector('.password-toggle i');
      
      if (passwordField.type === 'password') {
        passwordField.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
      } else {
        passwordField.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
      }
    }
    
    function refreshCaptcha() {
      fetch('login.php?refresh_captcha=1')
        .then(response => response.text())
        .then(html => {
          // Crear un documento temporal para parsear la respuesta
          const parser = new DOMParser();
          const doc = parser.parseFromString(html, 'text/html');
          const newCaptcha = doc.getElementById('captcha-text').textContent;
          document.getElementById('captcha-text').textContent = newCaptcha;
          document.querySelector('input[name="captcha"]').value = '';
        })
        .catch(error => console.error('Error al actualizar CAPTCHA:', error));
    }
    
    // Limpiar campos al recargar la página
    window.onload = function() {
      document.querySelector('form').reset();
    };
  </script>
</body>
</html>