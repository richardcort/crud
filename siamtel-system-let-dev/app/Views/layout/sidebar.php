<?php
/**
 * Layout de Navegación Lateral (Sidebar) - SIAMTEL
 * Solo expone Gestión de Usuarios (ADMIN) o Mi Cuenta (otros roles).
 */
$sessionRol = strtoupper((string)($_SESSION['user']['rol'] ?? ''));
$esAdmin = ($sessionRol === 'ADMIN');
?>
<aside class="dash-sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo-icon" style="background: linear-gradient(135deg, #0284c7, #1e3c72);">
            <i class="bi bi-shield-lock-fill"></i>
        </div>
        <div class="sidebar-brand">
            <h2>SIAMTEL</h2>
            <p>Gob. Estado Lara</p>
        </div>
    </div>

    <ul class="sidebar-menu">
        <?php if ($esAdmin): ?>
            <li class="menu-category">Administración</li>
            <li class="menu-item active">
                <a href="/usuarios">
                    <i class="bi bi-person-gear"></i>
                    <span>Gestión de Usuarios</span>
                </a>
            </li>
        <?php else: ?>
            <li class="menu-category">Cuenta</li>
            <li class="menu-item active">
                <a href="/usuarios">
                    <i class="bi bi-person-badge"></i>
                    <span>Mi Cuenta</span>
                </a>
            </li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-footer">
        <a href="/logout" class="btn-logout">
            <i class="bi bi-box-arrow-right"></i>
            <span>Cerrar Sesión</span>
        </a>
    </div>
</aside>
