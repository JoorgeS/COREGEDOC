<?php
// app/config/Constants.php

// Definición de Roles de Usuario (IDs según tu base de datos)
if (!defined('ROL_CONSEJERO')) define('ROL_CONSEJERO', 1);
if (!defined('ROL_SECRETARIO_TECNICO')) define('ROL_SECRETARIO_TECNICO', 2);
if (!defined('ROL_PRESIDENTE_COMISION')) define('ROL_PRESIDENTE_COMISION', 3);
if (!defined('ROL_ADMINISTRADOR')) define('ROL_ADMINISTRADOR', 6); // ID 6 es Admin

// Otras constantes
define('APP_NAME', 'COREGEDOC');