<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>CORE Vota</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap CSS -->
  <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <!-- Estilos propios -->
  <link href="/corevota/public/css/style.css" rel="stylesheet">
</head>
<body>

  <!-- Aquí irá el sidebar / navbar -->
  <?php include __DIR__ . '/pages/menu.php'; ?>

  <main class="container-fluid p-4">
    <?php echo $content; // aquí se carga la vista ?>
  </main>

  <!-- Bootstrap JS -->
  <script src="/corevota/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <!-- Scripts propios -->
  <script src="/corevota/public/js/app.js"></script>
</body>
</html>
