<?php
/**
 * Header / Topbar - SIAMTEL
 */
$hora = (int)date('H');
$saludo = ($hora >= 5 && $hora < 12) ? 'Buenos días' : (($hora >= 12 && $hora < 19) ? 'Buenas tardes' : 'Buenas noches');

$nombreUsuario = htmlspecialchars($currentUser['user'] ?? $_SESSION['user']['user'] ?? 'Usuario');
$rolUsuario = htmlspecialchars($currentUser['rol'] ?? $_SESSION['user']['rol'] ?? 'CLIENTE');

$dias = ['Sunday' => 'Domingo', 'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles', 'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado'];
$meses = ['January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo', 'April' => 'Abril', 'May' => 'Mayo', 'June' => 'Junio', 'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre', 'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'];
$fechaFormateada = ($dias[date('l')] ?? date('l')) . ', ' . date('d') . ' de ' . ($meses[date('F')] ?? date('F')) . ' de ' . date('Y');
?>
<header class="dash-topbar">
    <div class="topbar-left">
        <div>
            <h1 class="topbar-title"><?= $saludo ?>, <?= $nombreUsuario ?></h1>
            <span class="topbar-subtitle">
                <i class="bi bi-shield-check" style="color: #1e3c72;"></i> SIAMTEL &bull; <?= $fechaFormateada ?>
            </span>
        </div>
    </div>

    <div class="topbar-right">
        <!-- Reloj en vivo (Hora legal Venezuela VET) -->
        <div class="clock-badge">
            <i class="bi bi-clock"></i>
            <span id="liveClock">--:--:--</span>
            <span style="font-size: 10px; color: #64748b;">VET</span>
        </div>

        <!-- Perfil activo -->
        <div class="user-profile-badge">
            <div class="user-avatar" style="background: #1e3c72;">
                <?= strtoupper(substr($nombreUsuario, 0, 1)) ?>
            </div>
            <div class="user-info-text">
                <div class="user-info-name"><?= $nombreUsuario ?></div>
                <div class="user-info-role"><?= $rolUsuario ?></div>
            </div>
        </div>
    </div>
</header>
