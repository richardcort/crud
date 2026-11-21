<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo ?? 'Mi Cuenta - SIAMTEL') ?></title>
    <link rel="stylesheet" href="/css/dashboard.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>

    <!-- SIDEBAR -->
    <?php require BASE_PATH . '/app/Views/layout/sidebar.php'; ?>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="dash-main">
        <!-- TOPBAR -->
        <?php require BASE_PATH . '/app/Views/layout/topbar.php'; ?>

        <div class="dash-content">

            <?php if (!empty($_SESSION['perfil_error'])): ?>
                <div class="alert-banner alert-banner-danger">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div style="flex: 1;"><?= htmlspecialchars($_SESSION['perfil_error']) ?></div>
                    <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:#991b1b;"><i class="bi bi-x-lg"></i></button>
                </div>
                <?php unset($_SESSION['perfil_error']); ?>
            <?php endif; ?>

            <div class="content-card" style="max-width: 640px;">
                <div class="card-header-custom">
                    <div class="card-header-title">
                        <i class="bi bi-person-badge-fill" style="color: #1e3c72;"></i>
                        <span>Información de Mi Cuenta</span>
                    </div>
                    <span class="badge-status badge-active">
                        <i class="bi bi-check-circle-fill"></i> Activo
                    </span>
                </div>

                <div class="card-body-custom">
                    <ul class="info-list">
                        <li class="info-item">
                            <span class="info-item-label"><i class="bi bi-card-heading"></i> Cédula</span>
                            <span class="info-item-value"><?= htmlspecialchars($currentUser['cedula'] ?? '--') ?></span>
                        </li>
                        <li class="info-item">
                            <span class="info-item-label"><i class="bi bi-person"></i> Usuario</span>
                            <span class="info-item-value"><?= htmlspecialchars($currentUser['user'] ?? '--') ?></span>
                        </li>
                        <li class="info-item">
                            <span class="info-item-label"><i class="bi bi-person-vcard"></i> Nombre</span>
                            <span class="info-item-value"><?= htmlspecialchars($currentUser['nombre_completo'] ?? ($currentUser['user'] ?? '--')) ?></span>
                        </li>
                        <li class="info-item">
                            <span class="info-item-label"><i class="bi bi-shield"></i> Rol</span>
                            <span class="info-item-value">
                                <span class="badge-role"><?= htmlspecialchars($currentUser['rol'] ?? 'USUARIO') ?></span>
                            </span>
                        </li>
                        <li class="info-item">
                            <span class="info-item-label"><i class="bi bi-envelope"></i> Correo</span>
                            <span class="info-item-value"><?= htmlspecialchars(!empty($currentUser['correo']) ? $currentUser['correo'] : '--') ?></span>
                        </li>
                    </ul>

                    <div style="margin-top: 20px; padding: 14px; background: #f8fafc; border: 1px solid var(--border-color); border-radius: 8px; font-size: 13px; color: var(--text-secondary); display: flex; align-items: center; gap: 10px;">
                        <i class="bi bi-info-circle-fill" style="color: #0284c7; font-size: 16px;"></i>
                        <span>Sesión iniciada correctamente. La administración de usuarios es exclusiva del rol <strong>ADMIN</strong>.</span>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        function updateClock() {
            const now = new Date();
            const el = document.getElementById('liveClock');
            if (el) el.textContent = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0') + ':' + String(now.getSeconds()).padStart(2, '0');
        }
        setInterval(updateClock, 1000);
        updateClock();
    </script>
</body>
</html>

