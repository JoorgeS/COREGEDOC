<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-id-card me-2"></i>Mi Perfil</h5>
                </div>
                <div class="card-body">
                    
                    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'foto_ok'): ?>
                        <div class="alert alert-success">¡Foto actualizada correctamente!</div>
                    <?php endif; ?>

                    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'error_upload'): ?>
                        <div class="alert alert-danger">Error al subir la imagen. Intente de nuevo.</div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-4 text-center mb-4">
                            <?php 
                                // Lógica de imagen con fallback al logo
                                $rutaDB = $data['usuario']['foto_perfil'] ?? '';
                                $img = (!empty($rutaDB) && file_exists($rutaDB)) ? $rutaDB : 'public/img/logoCore1.png';
                            ?>
                            
                            <img src="<?php echo $img; ?>" class="img-fluid rounded shadow border" style="width: 100%; max-width: 200px; height: auto; object-fit: cover; background: #fff;">
                            
                            <form action="index.php?action=update_perfil" method="POST" enctype="multipart/form-data" class="mt-3">
                                <div class="d-grid gap-2">
                                    <label class="btn btn-outline-primary btn-sm btn-file">
                                        <i class="fas fa-camera"></i> Cambiar Foto 
                                        <input type="file" name="fotoPerfil" style="display: none;" onchange="this.form.submit()">
                                    </label>
                                </div>
                            </form>
                        </div>

                        <div class="col-md-8">
                            <h4 class="mb-3">
                                <?php 
                                    // Nombres Completos (incluyendo 2do nombre si existe)
                                    echo htmlspecialchars($data['usuario']['pNombre'] ?? '') . ' ' . 
                                         htmlspecialchars($data['usuario']['sNombre'] ?? '') . ' ' . 
                                         htmlspecialchars($data['usuario']['aPaterno'] ?? '') . ' ' . 
                                         htmlspecialchars($data['usuario']['aMaterno'] ?? ''); 
                                ?>
                            </h4>
                            
                            <div class="list-group list-group-flush">
                                <div class="list-group-item px-0">
                                    <small class="text-muted d-block">Correo Electrónico</small>
                                    <strong><?php echo htmlspecialchars($data['usuario']['correo'] ?? 'Sin correo'); ?></strong>
                                </div>

                                <div class="list-group-item px-0">
                                    <small class="text-muted d-block">Rol</small>
                                    <span class="badge bg-info text-dark">
                                        <?php 
                                            // Si el modelo trajo la descripción del rol, úsala; si no, calcula manual
                                            if (!empty($data['usuario']['descTipoUsuario'])) {
                                                echo htmlspecialchars($data['usuario']['descTipoUsuario']);
                                            } else {
                                                $rolID = $data['usuario']['tipoUsuario_id'] ?? 0;
                                                if ($rolID == 1) echo 'Administrador';
                                                elseif ($rolID == 2) echo 'Secretario Técnico';
                                                elseif ($rolID == 3) echo 'Presidente Comisión';
                                                elseif ($rolID == 4) echo 'Consejero';
                                                else echo 'Usuario';
                                            }
                                        ?>
                                    </span>
                                </div>

                                <div class="list-group-item px-0">
                                    <small class="text-muted d-block">Partido Político</small>
                                    <strong>
                                        <?php 
                                            echo htmlspecialchars($data['usuario']['nombrePartido'] ?? 'Independiente / No especificado'); 
                                        ?>
                                    </strong>
                                </div>

                                <div class="list-group-item px-0">
                                    <small class="text-muted d-block">Provincia / Territorio</small>
                                    <strong>
                                        <?php 
                                            echo htmlspecialchars($data['usuario']['nombreProvincia'] ?? 'Regional'); 
                                        ?>
                                    </strong>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>