<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coregedoc</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { min-height: 100vh; display: flex; flex-direction: column; }
        .wrapper { display: flex; flex: 1; }
        .sidebar-container { min-width: 250px; max-width: 250px; background: #343a40; color: #fff; }
        .content-container { flex: 1; padding: 20px; background: #f8f9fa; }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/navbar.php'; ?>

<div class="wrapper">
    <div class="sidebar-container">
        <?php 
            // Verificamos si existe antes de cargarlo para evitar errores
            if(file_exists(__DIR__ . '/sidebar.php')){
                require_once __DIR__ . '/sidebar.php'; 
            } else {
                echo "<p class='p-3 text-danger'>Falta sidebar.php</p>";
            }
        ?>
    </div>

    <div class="content-container">