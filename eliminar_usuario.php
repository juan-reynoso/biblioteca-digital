<?php
session_start();
require_once 'Conect.php';

if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    die(json_encode(['success' => false, 'message' => 'Acceso no autorizado']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    
    try {
        // Verificar que el usuario no sea admin
        $stmt = $conn->prepare("SELECT tipo_usuario FROM Usuarios WHERE id_Usuario = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            die(json_encode(['success' => false, 'message' => 'Usuario no encontrado']));
        }
        
        $user = $result->fetch_assoc();
        if ($user['tipo_usuario'] === 'admin') {
            die(json_encode(['success' => false, 'message' => 'No puedes eliminar un administrador']));
        }
        
        // Eliminar el usuario
        $stmt = $conn->prepare("DELETE FROM Usuarios WHERE id_Usuario = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Usuario eliminado correctamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al eliminar el usuario']);
        }
    } catch (mysqli_sql_exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error en la base de datos: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Solicitud inválida']);
}
?>