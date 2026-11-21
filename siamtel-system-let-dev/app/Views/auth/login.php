<?php
$oldParticle = $_SESSION['old_login_particle'] ?? 'V-';
$oldCi = $_SESSION['old_login_ci'] ?? '';
unset($_SESSION['old_login_particle'], $_SESSION['old_login_ci']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo ?? 'Iniciar Sesión - SIAMTEL') ?></title>
    <link rel="stylesheet" href="/css/auth/login.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <div style="display:inline-flex; align-items:center; justify-content:center; width:52px; height:52px; background:rgba(30,60,114,0.1); border-radius:12px; margin-bottom:12px; color:#1e3c72; font-size:26px;">
                <i class="bi bi-shield-check"></i>
            </div>
            <h1>SIAMTEL</h1>
            <p>Gobernación del Estado Lara</p>
        </div>

        <!-- Contenedor de alertas (Flash inicial + respuestas AJAX dinámicas) -->
        <div id="loginAlertContainer" aria-live="polite">
            <?php if (!empty($_SESSION['error_login'])): ?>
                <div class="alert-box alert-danger">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <span><?= htmlspecialchars($_SESSION['error_login']) ?></span>
                </div>
                <?php unset($_SESSION['error_login']); ?>
            <?php endif; ?>

            <?php if (!empty($_SESSION['success_login'])): ?>
                <div class="alert-box alert-success">
                    <i class="bi bi-check-circle-fill"></i>
                    <span><?= htmlspecialchars($_SESSION['success_login']) ?></span>
                </div>
                <?php unset($_SESSION['success_login']); ?>
            <?php endif; ?>
        </div>

        <form method="POST" action="/login" id="loginForm" autocomplete="on" novalidate>
            <input type="hidden" name="peticion" value="sesion">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="CI">Cédula de Identidad o Usuario</label>
                <div style="display: flex; gap: 8px;">
                    <select name="particle" id="particle" aria-label="Prefijo de cédula" style="width: 75px; padding: 12px; border: 1px solid #ddd; border-radius: 6px; background: #fff; font-size: 14px;">
                        <?php foreach (['V-', 'E-', 'J-', 'G-', 'P-'] as $pref): ?>
                            <option value="<?= $pref ?>" <?= ($oldParticle === $pref) ? 'selected' : '' ?>><?= $pref ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" id="CI" name="CI" value="<?= htmlspecialchars($oldCi, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ej: 00000000 o admin" minlength="3" maxlength="60" style="flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;" required autofocus autocomplete="username">
                </div>
                <small class="field-hint">Puede ingresar su cédula (6 a 9 dígitos) o su nombre de usuario.</small>
                <small id="error_CI" class="field-error" role="alert"></small>
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <div style="position: relative;">
                    <input type="password" id="password" name="password" placeholder="••••••••" minlength="4" maxlength="128" required style="width: 100%; padding-right: 40px;" autocomplete="current-password">
                    <button type="button" onclick="togglePassword()" aria-label="Mostrar u ocultar contraseña" style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#777; font-size:16px;">
                        <i id="pwdToggleIcon" class="bi bi-eye"></i>
                    </button>
                </div>
                <small id="error_password" class="field-error" role="alert"></small>
            </div>

            <button type="submit" id="btnLoginSubmit" class="btn-submit">
                <i class="bi bi-box-arrow-in-right" style="margin-right: 6px;"></i> Ingresar al Sistema
            </button>
        </form>

        <div class="login-footer">
            <p>Sistema Integral de Atención y Gestión</p>
        </div>
    </div>

    <script>
        function togglePassword() {
            const pwd = document.getElementById('password');
            const icon = document.getElementById('pwdToggleIcon');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                pwd.type = 'password';
                icon.className = 'bi bi-eye';
            }
        }

        // Validaciones Frontend + Envío AJAX (Fetch) del Login
        (function initLoginAjax() {
            const form = document.getElementById('loginForm');
            const ciInput = document.getElementById('CI');
            const pwdInput = document.getElementById('password');
            const submitBtn = document.getElementById('btnLoginSubmit');
            const alertContainer = document.getElementById('loginAlertContainer');
            const defaultBtnHtml = submitBtn.innerHTML;

            function showAlert(type, message) {
                const iconClass = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill';
                const box = document.createElement('div');
                box.className = `alert-box alert-${type}`;

                const icon = document.createElement('i');
                icon.className = `bi ${iconClass}`;

                const span = document.createElement('span');
                span.textContent = message;

                box.appendChild(icon);
                box.appendChild(span);

                alertContainer.innerHTML = '';
                alertContainer.appendChild(box);
            }

            function clearAlert() {
                alertContainer.innerHTML = '';
            }

            function setFieldError(input, errorId, message) {
                const errEl = document.getElementById(errorId);
                if (message) {
                    input.classList.add('input-invalid');
                    input.classList.remove('input-valid');
                    if (errEl) {
                        errEl.textContent = message;
                        errEl.style.display = 'block';
                    }
                    return false;
                }
                input.classList.remove('input-invalid');
                input.classList.add('input-valid');
                if (errEl) {
                    errEl.textContent = '';
                    errEl.style.display = 'none';
                }
                return true;
            }

            function validateCI() {
                const val = ciInput.value.trim();
                if (!val) {
                    return setFieldError(ciInput, 'error_CI', 'Ingrese su cédula o nombre de usuario.');
                }
                if (/\s/.test(val)) {
                    return setFieldError(ciInput, 'error_CI', 'No se permiten espacios en la cédula o usuario.');
                }
                if (/^\d+$/.test(val)) {
                    if (val.length < 6 || val.length > 9) {
                        return setFieldError(ciInput, 'error_CI', 'La cédula numérica debe tener entre 6 y 9 dígitos.');
                    }
                    return setFieldError(ciInput, 'error_CI', '');
                }
                if (/^[VEJPG]-?\d+$/i.test(val)) {
                    if (!/^[VEJPG]-?\d{6,9}$/i.test(val)) {
                        return setFieldError(ciInput, 'error_CI', 'La cédula con prefijo debe tener entre 6 y 9 dígitos.');
                    }
                    return setFieldError(ciInput, 'error_CI', '');
                }
                if (val.length < 3 || val.length > 50) {
                    return setFieldError(ciInput, 'error_CI', 'El nombre de usuario debe tener entre 3 y 50 caracteres.');
                }
                if (!/^[a-zA-Z0-9._-]+$/.test(val)) {
                    return setFieldError(ciInput, 'error_CI', 'Solo se permiten letras, números, punto (.), guión (-) y guión bajo (_).');
                }
                return setFieldError(ciInput, 'error_CI', '');
            }

            function validatePassword() {
                const val = pwdInput.value;
                if (!val) {
                    return setFieldError(pwdInput, 'error_password', 'Ingrese su contraseña.');
                }
                if (val.length < 4) {
                    return setFieldError(pwdInput, 'error_password', 'La contraseña debe tener al menos 4 caracteres.');
                }
                if (val.length > 128) {
                    return setFieldError(pwdInput, 'error_password', 'La contraseña excede la longitud máxima permitida.');
                }
                return setFieldError(pwdInput, 'error_password', '');
            }

            ciInput.addEventListener('blur', validateCI);
            ciInput.addEventListener('input', () => {
                if (ciInput.classList.contains('input-invalid')) validateCI();
            });

            pwdInput.addEventListener('blur', validatePassword);
            pwdInput.addEventListener('input', () => {
                if (pwdInput.classList.contains('input-invalid')) validatePassword();
            });

            form.addEventListener('submit', async function (e) {
                e.preventDefault();

                const okCI = validateCI();
                const okPwd = validatePassword();
                if (!okCI || !okPwd) {
                    if (!okCI) ciInput.focus();
                    else pwdInput.focus();
                    return;
                }

                clearAlert();
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split" style="margin-right: 6px;"></i> Verificando...';

                try {
                    const formData = new FormData(form);
                    const response = await fetch(form.getAttribute('action') || '/login', {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });

                    const data = await response.json();

                    // Sincronizar token CSRF si el servidor devolvió uno actualizado
                    if (data.csrf_token) {
                        const csrfInput = form.querySelector('input[name="csrf_token"]');
                        if (csrfInput) csrfInput.value = data.csrf_token;
                    }

                    if (response.ok && data.ok) {
                        showAlert('success', data.mensaje || 'Inicio de sesión exitoso. Redirigiendo...');
                        window.location.href = data.redirect || '/usuarios';
                        return;
                    }

                    showAlert('danger', data.mensaje || 'No se pudo iniciar sesión. Verifique sus datos.');
                    if (response.status === 401) {
                        pwdInput.focus();
                        pwdInput.select();
                    } else if (response.status === 422) {
                        ciInput.focus();
                    }
                } catch (err) {
                    showAlert('danger', 'Error de conexión con el servidor. Verifique su red e intente nuevamente.');
                } finally {
                    // Restaurar botón solo si no estamos redirigiendo
                    if (!alertContainer.querySelector('.alert-success')) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = defaultBtnHtml;
                    }
                }
            });
        })();
    </script>
</body>
</html>
