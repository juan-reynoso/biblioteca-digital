<?php
$servername = "localhost";
$username = "diego";
$password = "D13GO109";
$dbname = "bibliotecadig";
try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    
    // Configurar el modo de error de PDO a excepción
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Configurar el modo de fetch por defecto a asociativo
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // echo "Conexión exitosa"; // (Puedes comentar esto después de probar)
    
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>