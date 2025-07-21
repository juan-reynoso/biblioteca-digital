<?php
session_start();

// Configurar headers de seguridad
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

// Verificar si es una solicitud AJAX para cierre silencioso
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Destruir todas las variables de sesión
$_SESSION = array();

// Borrar la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destruir la sesión
session_destroy();

// Si es AJAX, solo responder con JSON
if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cierre de sesión - Sistema Biblioteca</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --color-vino: #800020;
            --color-vino-claro: #a04560;
            --color-blanco: #ffffff;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--color-vino);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            overflow: hidden;
            color: var(--color-blanco);
        }
        
        .logout-container {
            text-align: center;
            z-index: 100;
            max-width: 500px;
            padding: 2rem;
        }
        
        .checkmark-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: inline-block;
            stroke-width: 2;
            stroke: var(--color-blanco);
            stroke-miterlimit: 10;
            margin: 0 auto 30px;
            animation: fill .2s ease-in-out .2s forwards, scale .15s ease-in-out .4s both;
            box-shadow: 0 0 0 rgba(255, 255, 255, 0.4);
        }
        
        .checkmark {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            display: block;
            stroke-width: 2;
            stroke: var(--color-blanco);
            stroke-miterlimit: 10;
            animation: stroke .3s cubic-bezier(0.65, 0, 0.45, 1) forwards;
        }
        
        .checkmark-check {
            transform-origin: 50% 50%;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            animation: stroke .15s cubic-bezier(0.65, 0, 0.45, 1) .3s forwards;
        }
        
        @keyframes stroke {
            100% { stroke-dashoffset: 0; }
        }
        
        @keyframes scale {
            0%, 100% { transform: none; }
            50% { transform: scale3d(1.1, 1.1, 1); }
        }
        
        @keyframes fill {
            100% { box-shadow: inset 0 0 0 100px rgba(255, 255, 255, 0.1); }
        }
        
        h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            opacity: 0;
            animation: fadeIn .3s ease-in-out .5s forwards;
        }
        
        p {
            font-size: 1.2rem;
            opacity: 0;
            animation: fadeIn .3s ease-in-out .7s forwards;
            margin-bottom: 2rem;
        }
        
        .progress-container {
            width: 100%;
            height: 6px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 3px;
            overflow: hidden;
            margin-top: 30px;
            opacity: 0;
            animation: fadeIn .3s ease-in-out .9s forwards;
        }
        
        .progress-bar {
            height: 100%;
            width: 0;
            background: var(--color-blanco);
            animation: progress 1.5s linear forwards;
        }
        
        @keyframes progress {
            0% { width: 0; }
            100% { width: 100%; }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="checkmark-circle">
            <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                <circle class="checkmark-circle-bg" cx="26" cy="26" r="25" fill="none"/>
                <path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
            </svg>
        </div>
        
        <h1>Sesión finalizada</h1>
        <p>Has cerrado sesión exitosamente. Redirigiendo...</p>
        
        <div class="progress-container">
            <div class="progress-bar"></div>
        </div>
    </div>

    <script>
        // Redirigir después de 1.5 segundos (1500ms)
        setTimeout(() => {
            window.location.href = 'login.php';
        }, 1500);
    </script>
</body>
</html>