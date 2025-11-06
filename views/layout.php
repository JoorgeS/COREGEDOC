<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>CORE Vota</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="/corevota/public/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="/corevota/public/css/style.css" rel="stylesheet">

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

  <script src="/corevota/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

  <script src="/corevota/public/js/app.js"></script>

</head>

<body>

  <?php include __DIR__ . '/pages/menu.php'; ?>

  <main class="container-fluid p-4">
    <?php echo $content; // aquí se carga la vista (que incluye el script de la página) 
    ?>
  </main>

</body>

</html>