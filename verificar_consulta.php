<?php
require_once 'conect.php';

// Verificar consultas registradas
$consultas = $conn->query("SELECT * FROM consulta ORDER BY fecha DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

echo "<h2>Últimas 10 consultas registradas</h2>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Fecha</th><th>Criterio</th><th>Término</th><th>Usuario</th><th>Libro</th></tr>";

foreach ($consultas as $consulta) {
    echo "<tr>";
    echo "<td>".$consulta['id_Consulta']."</td>";
    echo "<td>".$consulta['fecha']."</td>";
    echo "<td>".$consulta['criterio_busqueda']."</td>";
    echo "<td>".$consulta['termino_busqueda']."</td>";
    echo "<td>".$consulta['id_Usuario']."</td>";
    echo "<td>".$consulta['id_Libro']."</td>";
    echo "</tr>";
}

echo "</table>";

// Verificar estructura de la tabla
$estructura = $conn->query("DESCRIBE consulta")->fetch_all(MYSQLI_ASSOC);
echo "<h2>Estructura de la tabla consulta</h2>";
echo "<pre>";
print_r($estructura);
echo "</pre>";