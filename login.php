<?php
session_start();
date_default_timezone_set("America/Mexico_City");

// Conexión a la base de datos
$conn = new mysqli("localhost", "diego", "D13GO109", "bibliotecadig");

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Captura de datos del formulario
$no_control_social = $_POST['numero_control']; // Recibe el No_control_social
$contrasena = $_POST['contrasena']; // Recibe el CURP

// Validación de usuario
$sql = "SELECT * FROM usuarios WHERE No_control_social = ? AND CURP = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $no_control_social, $contrasena);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $usuario = $result->fetch_assoc();

    // Inicializar contador de sesiones
    if (!isset($_SESSION['login_count'])) {
        $_SESSION['login_count'] = 1;
    } else {
        $_SESSION['login_count']++;
    }

    // Guardar datos del usuario en sesión
    $_SESSION['usuario'] = $usuario;
    $_SESSION['tipo_usuario'] = $usuario['tipo_usuario'] ?? 'alumno';
    $_SESSION['numero_control'] = $usuario['No_control_social'];
    $_SESSION['nombre'] = $usuario['Nombre'] ?? 'Usuario';

    // Mensaje de bienvenida simple
    echo "<script>
        alert('Bienvenido, " . addslashes($usuario['Nombre'] ?? 'Usuario') . "!\\nEsta es tu sesión número: " . $_SESSION['login_count'] . "');
        window.location.href = 'Principal.php';
    </script>";
} else {
    echo "<script>
        alert('Número de control social o CURP incorrectos');
        window.location.href = 'login.html';
    </script>";
}

$conn->close();
?>