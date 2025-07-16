<?php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'diego');
define('DB_PASSWORD', 'D13GO109');
define('DB_NAME', 'bibliotecadig');

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    error_log("Error de conexión: " . $conn->connect_error);
    die("Error al conectar con la base de datos. Por favor, intente más tarde.");
}

// Establecer el conjunto de caracteres
if (!$conn->set_charset("utf8mb4")) {
    error_log("Error al establecer charset: " . $conn->error);
}

// Función para ejecutar consultas de forma segura
function ejecutarConsulta($conn, $sql, $params = [], $types = '') {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Error en preparación: " . $conn->error);
        return false;
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        error_log("Error en ejecución: " . $stmt->error);
        return false;
    }
    
    return $stmt->get_result();
}

// Función para cerrar conexión
function cerrarConexion($conn) {
    if ($conn) {
        $conn->close();
    }
}
?>