<?php
session_start();
require_once 'Conect.php';

// Verificar permisos
if (!isset($_SESSION['tipo_usuario']) || $_SESSION['tipo_usuario'] !== 'admin') {
    header("HTTP/1.1 403 Forbidden");
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit();
}

// Configurar cabeceras para JSON
header('Content-Type: application/json');

// Obtener datos del usuario a editar
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    try {
        $stmt = $conn->prepare("
            SELECT u.id_Usuario, u.Nombre, u.CURP, u.No_control_social, u.tipo_usuario, u.id_carrera, 
                   c.nombre as nombre_carrera 
            FROM Usuarios u
            LEFT JOIN carrera c ON u.id_carrera = c.id_carrera
            WHERE u.id_Usuario = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Error en la preparación de la consulta: " . $conn->error);
        }
        
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $usuario = $result->fetch_assoc();
            echo json_encode($usuario);
        } else {
            echo json_encode(['error' => 'Usuario no encontrado']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit();
}

// Actualizar datos del usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar datos recibidos
    $required_fields = ['id', 'nombre', 'curp', 'no_control', 'tipo_usuario'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field])) {
            echo json_encode(['success' => false, 'message' => "Campo requerido faltante: $field"]);
            exit();
        }
    }
    
    $id = (int)$_POST['id'];
    $nombre = trim($_POST['nombre']);
    $curp = trim($_POST['curp']);
    $no_control = trim($_POST['no_control']);
    $tipo_usuario = trim($_POST['tipo_usuario']);
    $id_carrera = isset($_POST['id_carrera']) ? (int)$_POST['id_carrera'] : null;
    
    // Validaciones adicionales
    if (empty($nombre)) {
        echo json_encode(['success' => false, 'message' => 'El nombre no puede estar vacío']);
        exit();
    }
    
   if (!in_array($tipo_usuario, ['alumno', 'docente', 'administrativo'])) {
    echo json_encode(['success' => false, 'message' => 'Tipo de usuario no válido']);
    exit();
}
    
    try {
        $conn->begin_transaction();
        
        // Verificar si el número de control ya existe para otro usuario
        $stmt_check = $conn->prepare("SELECT id_Usuario FROM Usuarios WHERE No_control_social = ? AND id_Usuario != ?");
        $stmt_check->bind_param("si", $no_control, $id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            throw new Exception("El número de control ya está en uso por otro usuario");
        }
        
        // Actualizar usuario
        $stmt = $conn->prepare("
            UPDATE Usuarios 
            SET Nombre = ?, CURP = ?, No_control_social = ?, tipo_usuario = ?, id_carrera = ?
            WHERE id_Usuario = ?
        ");
        
        if (!$stmt) {
            throw new Exception("Error en la preparación de la consulta: " . $conn->error);
        }
        
        $stmt->bind_param("ssssii", $nombre, $curp, $no_control, $tipo_usuario, $id_carrera, $id);
        
        if ($stmt->execute()) {
            // Registrar acción en bitácora
            $usuario_admin = $_SESSION['nombre'] ?? 'Sistema';
            $accion = "Actualización de usuario ID $id";
            $stmt_bitacora = $conn->prepare("INSERT INTO bitacora (usuario, accion) VALUES (?, ?)");
            $stmt_bitacora->bind_param("ss", $usuario_admin, $accion);
            $stmt_bitacora->execute();
            
            $conn->commit();
            echo json_encode([
                'success' => true, 
                'message' => 'Usuario actualizado correctamente',
                'data' => [
                    'id' => $id,
                    'nombre' => $nombre,
                    'tipo_usuario' => $tipo_usuario
                ]
            ]);
        } else {
            throw new Exception("Error al ejecutar la actualización: " . $stmt->error);
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}

// Si no es GET ni POST válido
echo json_encode(['success' => false, 'message' => 'Método no permitido']);
?>