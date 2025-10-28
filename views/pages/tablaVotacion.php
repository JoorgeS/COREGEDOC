<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['idUsuario'])) {
  header("Location: /corevota/views/pages/login.php");
  exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Votación en Curso</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
body {
  background-color: #f8f9fa;
  color: #212529;
  font-family: "Segoe UI", Arial, sans-serif;
}

.container {
  max-width: 1000px; /* 🔹 ancho máximo más reducido */
}

.header-votacion {
  background-color: #198754;
  color: #fff;
  padding: 20px;
  border-radius: 8px;
  text-align: center;
  margin-bottom: 25px;
  box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}

.header-votacion h3 {
  margin: 0;
  font-weight: 700;
  font-size: 1.5rem;
}

.contadores {
  margin-top: 10px;
  font-size: 1.1rem;
}

.contadores span {
  margin: 0 10px;
  font-weight: 600;
}

.si-count { color: #070707ff; }
.no-count { color: #161515ff; }
.abs-count { color: #121313ff; }

.tabla-container {
  display: flex;
  justify-content: center;
  gap: 20px;
  flex-wrap: wrap;
}

.table {
  background-color: #fff;
  border-radius: 10px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.08);
  overflow: hidden;
  width: 45%;
}

.table thead {
  background-color: #e9ecef;
  text-align: center;
  font-weight: 600;
  color: #000;
}

.table tbody td {
  font-size: 0.95rem;
  padding: 8px 10px;
  vertical-align: middle;
}

.voto-si { color: #198754; font-weight: 700; }
.voto-no { color: #dc3545; font-weight: 700; }
.voto-abs { color: #0dcaf0; font-weight: 700; }
.voto-sin { color: #6c757d; }
</style>
</head>

<body>
<div class="container mt-4">
  <div class="header-votacion">
    <h3 id="tituloVotacion">Votación en curso</h3>
    <div class="contadores">
      SI: <span class="si-count" id="countSi">0</span> |
      NO: <span class="no-count" id="countNo">0</span> |
      ABS: <span class="abs-count" id="countAbs">0</span>
    </div>
  </div>

  <div class="tabla-container">
    <table class="table table-hover align-middle" id="tablaIzquierda">
      <thead>
        <tr><th>CONSEJERO</th><th>VOTO</th></tr>
      </thead>
      <tbody id="columnaIzquierda">
        <tr><td colspan="2" class="text-center text-muted">Cargando...</td></tr>
      </tbody>
    </table>

    <table class="table table-hover align-middle" id="tablaDerecha">
      <thead>
        <tr><th>CONSEJERO</th><th>VOTO</th></tr>
      </thead>
      <tbody id="columnaDerecha">
        <tr><td colspan="2" class="text-center text-muted">Cargando...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<script>
function cargarTabla() {
  fetch('/corevota/controllers/fetch_votos.php')
    .then(res => res.json())
    .then(data => {
      const left = document.getElementById('columnaIzquierda');
      const right = document.getElementById('columnaDerecha');
      const titulo = document.getElementById('tituloVotacion');
      left.innerHTML = '';
      right.innerHTML = '';

      let si = 0, no = 0, abs = 0;

      // 🔹 Mostrar nombre de la votación
      if (data.nombreVotacion) titulo.textContent = data.nombreVotacion;

      if (data.status !== 'success' || !data.data.length) {
        left.innerHTML = `<tr><td colspan="2" class="text-center text-muted">No hay registros</td></tr>`;
        right.innerHTML = '';
        return;
      }

      const mitad = Math.ceil(data.data.length / 2);
      const izquierda = data.data.slice(0, mitad);
      const derecha = data.data.slice(mitad);

      const crearFila = (fila) => {
        let clase = 'voto-sin';
        let votoTexto = fila.opcionVoto || 'Sin votar';
        if (votoTexto === 'SI') { clase = 'voto-si'; si++; }
        else if (votoTexto === 'NO') { clase = 'voto-no'; no++; }
        else if (votoTexto === 'ABSTENCION') { clase = 'voto-abs'; abs++; }
        return `<tr><td>${fila.nombre}</td><td class="${clase} text-center">${votoTexto}</td></tr>`;
      };

      izquierda.forEach(fila => left.innerHTML += crearFila(fila));
      derecha.forEach(fila => right.innerHTML += crearFila(fila));

      document.getElementById('countSi').textContent = si;
      document.getElementById('countNo').textContent = no;
      document.getElementById('countAbs').textContent = abs;
    })
    .catch(err => {
      document.getElementById('columnaIzquierda').innerHTML = 
        `<tr><td colspan="2" class="text-danger text-center">Error: ${err}</td></tr>`;
    });
}

// Primera carga
cargarTabla();

// Actualizar cada 5 segundos
setInterval(cargarTabla, 5000);
</script>
</body>
</html>
