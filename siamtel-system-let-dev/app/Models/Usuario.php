<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use Exception;

/**
 * Modelo de Usuario y Gestión de Submódulos/Permisos - SIAMTEL
 * Gobernación del Estado Lara
 *
 * Implementado bajo principios SOLID & DRY:
 * - Todos los métodos de base de datos son PRIVADOS.
 * - Solo existe UN ÚNICO método público de interfaz operacional: Transaccion($params).
 */
class Usuario extends Persona
{
    private string $username = '';
    private string $password = '';
    private string $id_rol = 'CLIENTE';
    private int $estatus = 1;
    private ?string $ultimo_acceso = null;
    private string $tema = 'light';

    public function __construct()
    {
        $this->estatus = 1;
    }

    public function setUsername(string $username): void
    {
        $username = trim($username);
        if (mb_strlen($username) < 3) {
            throw new Exception('El nombre de usuario debe tener al menos 3 caracteres.');
        }
        $this->username = $username;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setPassword(string $password): void
    {
        if (empty($password)) {
            throw new Exception('La contraseña no puede estar vacía.');
        }
        $this->password = $password;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setRol(string $id_rol): void
    {
        $this->id_rol = strtoupper(trim($id_rol));
    }

    public function getRol(): string
    {
        return $this->id_rol;
    }

    public function setEstatus(int $estatus): void
    {
        if (!in_array($estatus, [0, 1], true)) {
            throw new Exception('Estatus no válido (0: Inactivo, 1: Activo).');
        }
        $this->estatus = $estatus;
    }

    public function getEstatus(): int
    {
        return $this->estatus;
    }

    public function setTema(string $tema): void
    {
        $this->tema = in_array($tema, ['light', 'dark'], true) ? $tema : 'light';
    }

    public function getTema(): string
    {
        return $this->tema;
    }

    /**
     * ÚNICO método público para acceder a las operaciones del modelo (SOLID & DRY)
     */
    public function Transaccion(array $params = []): array
    {
        $peticion = $params['peticion'] ?? '';

        return match ($peticion) {
            'registrar'        => $this->registrarUsuario($params['datos'] ?? []),
            'consultar'        => $this->consultarUsuarios(
                $params['busqueda'] ?? null,
                $params['rol'] ?? null,
                $params['estatus'] ?? null
            ),
            'consultar_uno'    => $this->obtenerUsuario((string)($params['cedula'] ?? $this->cedula)),
            'actualizar'       => $this->actualizarUsuario($params['datos'] ?? []),
            'toggle_estatus'   => $this->toggleEstatus((string)($params['cedula'] ?? $this->cedula), (int)($params['nuevo_estatus'] ?? 1)),
            'cambiar_clave'    => $this->cambiarClave((string)($params['cedula'] ?? $this->cedula), (string)($params['nueva_clave'] ?? '')),
            'eliminar'         => $this->eliminarUsuario((string)($params['cedula'] ?? $this->cedula)),
            'submodulos'       => $this->obtenerSubmodulos($params['cedula'] ?? $this->cedula, $params['rol'] ?? $this->id_rol),
            'permisos_rol'     => $this->obtenerPermisosPorRol((string)($params['id_rol'] ?? 'ADMIN')),
            'tiene_permiso'    => $this->tienePermisoAccion(
                (string)($params['submodulo'] ?? ''),
                (string)($params['accion'] ?? 'ver'),
                $params['cedula'] ?? $this->cedula,
                $params['rol'] ?? $this->id_rol
            ),
            'roles'            => $this->listarRoles(),
            'guardar_permisos' => $this->guardarPermisosRol((string)($params['id_rol'] ?? ''), $params['permisos'] ?? []),
            default => [
                'response' => [
                    'resultado' => 400,
                    'mensaje'   => "Petición transaccional no válida: '{$peticion}'",
                ],
                'HTTP_STATUS' => ['codigo' => 400, 'mensaje' => 'Bad Request']
            ]
        };
    }

    // =========================================================================
    // VALIDACIONES BACKEND CENTRALIZADAS
    // =========================================================================

    private function normalizarCedula(string $raw, string $prefijoPorDefecto = 'V-'): ?string
    {
        $raw = strtoupper(trim($raw));
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^([VEJPG])-?(\d{6,9})$/', $raw, $m)) {
            return $m[1] . '-' . $m[2];
        }

        if (preg_match('/^\d{6,9}$/', $raw)) {
            $prefix = in_array(strtoupper($prefijoPorDefecto), ['V-', 'E-', 'J-', 'P-', 'G-'], true)
                ? strtoupper($prefijoPorDefecto)
                : 'V-';
            return $prefix . $raw;
        }

        return null;
    }

    private function validarUsername(string $user): ?string
    {
        if (mb_strlen($user) < 3 || mb_strlen($user) > 50) {
            return 'El nombre de usuario debe tener entre 3 y 50 caracteres.';
        }
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $user)) {
            return 'El nombre de usuario solo puede contener letras, números, punto (.), guión (-) o guión bajo (_).';
        }
        return null;
    }

    private function validarNombrePersona(string $valor, string $campo): ?string
    {
        if (mb_strlen($valor) < 2 || mb_strlen($valor) > 100) {
            return "El campo {$campo} es obligatorio y debe tener entre 2 y 100 caracteres.";
        }
        if (!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ\s'\-.]+$/u", $valor)) {
            return "El campo {$campo} solo puede contener letras, espacios y guiones.";
        }
        return null;
    }

    private function validarPassword(string $password): ?string
    {
        if (trim($password) === '') {
            return 'La contraseña no puede estar vacía ni contener solo espacios.';
        }
        if (mb_strlen($password) < 6 || mb_strlen($password) > 128) {
            return 'La contraseña debe tener entre 6 y 128 caracteres.';
        }
        return null;
    }

    private function validarCorreo(string $correo): ?string
    {
        if ($correo === '') {
            return null;
        }
        if (mb_strlen($correo) > 120 || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return 'El formato del correo electrónico no es válido (máximo 120 caracteres).';
        }
        return null;
    }

    private function validarTelefono(string $telefono): ?string
    {
        if ($telefono === '') {
            return null;
        }
        if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $telefono)) {
            return 'El teléfono debe tener entre 7 y 20 caracteres numéricos válidos (ej: 0414-1234567).';
        }
        return null;
    }

    private function validarDireccion(string $direccion): ?string
    {
        if ($direccion !== '' && mb_strlen($direccion) > 255) {
            return 'La dirección no puede exceder los 255 caracteres.';
        }
        return null;
    }

    private function contarAdminsActivos(?string $excluirCedula = null): int
    {
        if ($excluirCedula !== null && $excluirCedula !== '') {
            $row = $this->fetchOne(
                "SELECT COUNT(*) AS total FROM Usuario WHERE rol = 'ADMIN' AND estatus = 1 AND cedula <> :c",
                [':c' => $excluirCedula]
            );
        } else {
            $row = $this->fetchOne("SELECT COUNT(*) AS total FROM Usuario WHERE rol = 'ADMIN' AND estatus = 1");
        }

        return (int)($row['total'] ?? 0);
    }

    private function rolExiste(string $id_rol): bool
    {
        $row = $this->fetchOne("SELECT COUNT(*) AS total FROM rol WHERE id_rol = :r AND estatus = 1", [
            ':r' => strtoupper(trim($id_rol)),
        ]);
        return !empty($row['total']) && (int)$row['total'] > 0;
    }

    // =========================================================================
    // MÉTODOS PRIVADOS DE OPERACIÓN A BASE DE DATOS
    // =========================================================================

    private function obtenerSubmodulos(?string $cedula = null, ?string $rol = null): array
    {
        $cedula = $cedula ?: $this->cedula;
        $rol = strtoupper(trim((string)($rol ?: $this->id_rol)));

        $sql = "SELECT
                    s.id,
                    s.modulo,
                    s.nombre,
                    s.descripcion,
                    s.icono,
                    s.ruta,
                    s.orden,
                    COALESCE(u.puede_ver, r.puede_ver, 0) AS puede_ver,
                    COALESCE(u.puede_crear, r.puede_crear, 0) AS puede_crear,
                    COALESCE(u.puede_editar, r.puede_editar, 0) AS puede_editar,
                    COALESCE(u.puede_eliminar, r.puede_eliminar, 0) AS puede_eliminar
                FROM submodulo s
                LEFT JOIN rol_submodulo r ON s.id = r.id_submodulo AND r.id_rol = :rol
                LEFT JOIN usuario_submodulo u ON s.id = u.id_submodulo AND u.cedula = :cedula
                ORDER BY s.modulo ASC, s.orden ASC";

        $submodulos = $this->fetchAll($sql, [
            ':rol'    => $rol,
            ':cedula' => $cedula,
        ]);

        $agrupados = [];
        foreach ($submodulos as $sub) {
            $cat = $sub['modulo'] ?: 'General';
            $agrupados[$cat][] = $sub;
        }

        return [
            'response' => [
                'resultado' => 200,
                'lista'     => $submodulos,
                'agrupados' => $agrupados,
            ]
        ];
    }

    private function obtenerPermisosPorRol(string $id_rol): array
    {
        $id_rol = strtoupper(trim($id_rol));

        $sql = "SELECT
                    s.id,
                    s.modulo,
                    s.nombre,
                    s.descripcion,
                    s.icono,
                    s.ruta,
                    s.orden,
                    COALESCE(r.puede_ver, 0) AS puede_ver,
                    COALESCE(r.puede_crear, 0) AS puede_crear,
                    COALESCE(r.puede_editar, 0) AS puede_editar,
                    COALESCE(r.puede_eliminar, 0) AS puede_eliminar
                FROM submodulo s
                LEFT JOIN rol_submodulo r ON s.id = r.id_submodulo AND r.id_rol = :rol
                ORDER BY s.modulo ASC, s.orden ASC";

        $filas = $this->fetchAll($sql, [':rol' => $id_rol]);

        return [
            'response' => [
                'resultado' => 200,
                'id_rol'    => $id_rol,
                'datos'     => $filas,
            ]
        ];
    }

    private function tienePermisoAccion(string $id_submodulo, string $accion = 'ver', ?string $cedula = null, ?string $rol = null): array
    {
        $rolEfectivo = strtoupper(trim((string)($rol ?: $this->id_rol)));
        $cedulaEfectiva = trim((string)($cedula ?: $this->cedula));

        $columna = match ($accion) {
            'crear'    => 'puede_crear',
            'editar'   => 'puede_editar',
            'eliminar' => 'puede_eliminar',
            default    => 'puede_ver',
        };

        $sql = "SELECT COALESCE(u.{$columna}, r.{$columna}, 0) AS permitido
                FROM submodulo s
                LEFT JOIN rol_submodulo r ON s.id = r.id_submodulo AND r.id_rol = :rol
                LEFT JOIN usuario_submodulo u ON s.id = u.id_submodulo AND u.cedula = :cedula
                WHERE s.id = :sub LIMIT 1";

        $res = $this->fetchOne($sql, [
            ':rol'    => $rolEfectivo,
            ':cedula' => $cedulaEfectiva,
            ':sub'    => $id_submodulo,
        ]);

        $permitido = !empty($res['permitido']) && (int)$res['permitido'] === 1;

        return [
            'response' => [
                'resultado' => 200,
                'permitido' => $permitido
            ]
        ];
    }

    private function guardarPermisosRol(string $id_rol, array $permisos): array
    {
        $id_rol = strtoupper(trim($id_rol));
        if ($id_rol === '' || !$this->rolExiste($id_rol)) {
            return ['response' => ['resultado' => 400, 'mensaje' => 'Rol no válido o no especificado.']];
        }

        $pdo = Database::getConnection();
        if ($pdo === null) {
            return ['response' => ['resultado' => 500, 'mensaje' => 'Error de conexión con la base de datos.']];
        }

        try {
            $submodulosValidos = $this->fetchAll("SELECT id FROM submodulo");
            $pdo->beginTransaction();

            $delStmt = $pdo->prepare("DELETE FROM rol_submodulo WHERE id_rol = :rol");
            $delStmt->execute([':rol' => $id_rol]);

            $insStmt = $pdo->prepare("INSERT INTO rol_submodulo (id_rol, id_submodulo, puede_ver, puede_crear, puede_editar, puede_eliminar)
                                      VALUES (:rol, :sub, :ver, :crear, :editar, :eliminar)");

            foreach ($submodulosValidos as $sub) {
                $subId = $sub['id'];
                $perms = $permisos[$subId] ?? [];

                $ver = !empty($perms['ver']) ? 1 : 0;
                $crear = !empty($perms['crear']) ? 1 : 0;
                $editar = !empty($perms['editar']) ? 1 : 0;
                $eliminar = !empty($perms['eliminar']) ? 1 : 0;

                if ($id_rol === 'ADMIN' && in_array($subId, ['usuarios', 'roles_permisos'], true)) {
                    $ver = $crear = $editar = $eliminar = 1;
                }
                if ($crear || $editar || $eliminar) {
                    $ver = 1;
                }

                $insStmt->execute([
                    ':rol'      => $id_rol,
                    ':sub'      => $subId,
                    ':ver'      => $ver,
                    ':crear'    => $crear,
                    ':editar'   => $editar,
                    ':eliminar' => $eliminar,
                ]);
            }

            $pdo->commit();
            return ['response' => ['resultado' => 200, 'mensaje' => "Permisos actualizados correctamente para el rol {$id_rol}."]];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['response' => ['resultado' => 500, 'mensaje' => 'Error al guardar permisos: ' . $e->getMessage()]];
        }
    }

    private function listarRoles(): array
    {
        $roles = $this->fetchAll("SELECT * FROM rol WHERE estatus = 1 ORDER BY FIELD(id_rol, 'ADMIN', 'MEDICO', 'OPERADOR', 'CLIENTE'), id_rol ASC");
        return ['response' => ['resultado' => 200, 'datos' => $roles]];
    }

    /**
     * Registra un nuevo usuario en Usuario y Persona con validaciones completas
     */
    private function registrarUsuario(array $datos = []): array
    {
        $prefijo = strtoupper(trim((string)($datos['particle'] ?? 'V-')));
        if (!in_array($prefijo, ['V-', 'E-', 'J-', 'P-', 'G-'], true)) {
            return ['response' => ['resultado' => 400, 'mensaje' => 'Prefijo de cédula no válido.']];
        }

        $cedulaInput = trim((string)($datos['cedula'] ?? $datos['ci'] ?? $this->cedula));
        $cedula = $this->normalizarCedula($cedulaInput, $prefijo);
        if ($cedula === null) {
            return ['response' => ['resultado' => 400, 'mensaje' => 'Formato de cédula inválido. Debe contener entre 6 y 9 dígitos (ej: V-12345678).']];
        }

        $user = trim((string)($datos['user'] ?? $this->username));
        if ($err = $this->validarUsername($user)) {
            return ['response' => ['resultado' => 400, 'mensaje' => $err]];
        }

        $nombre = trim((string)($datos['nombre'] ?? $this->nombre));
        if ($err = $this->validarNombrePersona($nombre, 'Nombres')) {
            return ['response' => ['resultado' => 400, 'mensaje' => $err]];
        }

        $apellido = trim((string)($datos['apellido'] ?? $this->apellido));
        if ($err = $this->validarNombrePersona($apellido, 'Apellidos')) {
            return ['response' => ['resultado' => 400, 'mensaje' => $err]];
        }

        $password = (string)($datos['password'] ?? $this->password);
        if ($err = $this->validarPassword($password)) {
            return ['response' => ['resultado' => 400, 'mensaje' => $err]];
        }

        $rol = strtoupper(trim((string)($datos['rol'] ?? $this->id_rol)));
        if (!$this->rolExiste($rol)) {
            return ['response' => ['resultado' => 400, 'mensaje' => 'El rol seleccionado no es válido.']];
        }

        $correo = trim((string)($datos['correo'] ?? ''));
        if ($err = $this->validarCorreo($correo)) {
            return ['response' => ['resultado' => 400, 'mensaje' => $err]];
        }

        $telefono = trim((string)($datos['telefono'] ?? ''));
        if ($err = $this->validarTelefono($telefono)) {
            return ['response' => ['resultado' => 400, 'mensaje' => $err]];
        }

        $direccion = trim((string)($datos['direccion'] ?? ''));
        if ($err = $this->validarDireccion($direccion)) {
            return ['response' => ['resultado' => 400, 'mensaje' => $err]];
        }

        $cedulaClean = preg_replace('/^[VEJPG]-/i', '', $cedula);
        $existe = $this->fetchOne(
            "SELECT cedula, user FROM Usuario WHERE cedula = :c1 OR cedula = :c2 OR user = :u LIMIT 1",
            [
                ':c1' => $cedula,
                ':c2' => $cedulaClean,
                ':u'  => $user,
            ]
        );

        if ($existe) {
            $motivo = (strcasecmp((string)$existe['user'], $user) === 0)
                ? "El nombre de usuario '{$user}' ya está en uso."
                : "La cédula {$cedula} ya tiene una cuenta registrada.";
            return ['response' => ['resultado' => 409, 'mensaje' => $motivo]];
        }

        $pdo = Database::getConnection();
        if ($pdo === null) {
            return ['response' => ['resultado' => 500, 'mensaje' => 'Error de conexión con la base de datos.']];
        }

        $fechaNac = trim((string)($datos['fecha_nacimiento'] ?? ''));
        $sexo = strtoupper(trim((string)($datos['sexo'] ?? 'M')));
        if (!in_array($sexo, ['M', 'F'], true)) {
            $sexo = 'M';
        }

        try {
            $pdo->beginTransaction();

            $sqlPersona = "INSERT INTO persona (cedula, nombre, apellido, fecha_nacimiento, telefono, correo, direccion, sexo)
                           VALUES (:c, :nom, :ape, :fnac, :tel, :cor, :dir, :sex)
                           ON DUPLICATE KEY UPDATE
                               nombre = :nom_u,
                               apellido = :ape_u,
                               telefono = COALESCE(NULLIF(:tel_u, ''), telefono),
                               correo = COALESCE(NULLIF(:cor_u, ''), correo)";
            $stmtP = $pdo->prepare($sqlPersona);
            $stmtP->execute([
                ':c'     => $cedula,
                ':nom'   => $nombre,
                ':ape'   => $apellido,
                ':fnac'  => $fechaNac !== '' ? $fechaNac : null,
                ':tel'   => $telefono !== '' ? $telefono : null,
                ':cor'   => $correo !== '' ? strtolower($correo) : null,
                ':dir'   => $direccion !== '' ? $direccion : 'Gobernación del Estado Lara',
                ':sex'   => $sexo,
                ':nom_u' => $nombre,
                ':ape_u' => $apellido,
                ':tel_u' => $telefono,
                ':cor_u' => strtolower($correo),
            ]);

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $sqlUser = "INSERT INTO Usuario (cedula, user, password, rol, estatus, tema)
                        VALUES (:c, :u, :p, :rol, 1, 'light')";
            $stmtU = $pdo->prepare($sqlUser);
            $stmtU->execute([
                ':c'   => $cedula,
                ':u'   => $user,
                ':p'   => $hash,
                ':rol' => $rol,
            ]);

            $pdo->commit();

            return [
                'response' => [
                    'resultado' => 200,
                    'mensaje'   => "Usuario '{$user}' ({$rol}) registrado exitosamente.",
                    'cedula'    => $cedula
                ]
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['response' => ['resultado' => 500, 'mensaje' => 'Error al registrar usuario: ' . $e->getMessage()]];
        }
    }

    /**
     * Consulta el listado de usuarios con sus datos personales y rol
     */
    private function consultarUsuarios(?string $busqueda = null, ?string $rolFiltro = null, ?string $estatusFiltro = null): array
    {
        $sql = "SELECT
                    u.cedula,
                    u.user,
                    u.rol,
                    u.estatus,
                    u.ultimo_acceso,
                    u.created_at,
                    p.nombre,
                    p.apellido,
                    CONCAT(COALESCE(p.nombre, ''), IF(p.apellido IS NOT NULL AND p.apellido <> '', CONCAT(' ', p.apellido), '')) AS nombre_completo,
                    p.correo,
                    p.telefono,
                    p.direccion,
                    r.nombre_rol
                FROM Usuario u
                LEFT JOIN persona p ON u.cedula = p.cedula
                LEFT JOIN rol r ON u.rol = r.id_rol
                WHERE 1=1";

        $params = [];
        if ($busqueda !== null && trim($busqueda) !== '') {
            $term = '%' . mb_substr(trim($busqueda), 0, 100) . '%';
            $sql .= " AND (
                        u.cedula LIKE :q1
                        OR u.user LIKE :q2
                        OR CONCAT(COALESCE(p.nombre, ''), ' ', COALESCE(p.apellido, '')) LIKE :q3
                        OR u.rol LIKE :q4
                        OR COALESCE(p.correo, '') LIKE :q5
                      )";
            $params[':q1'] = $term;
            $params[':q2'] = $term;
            $params[':q3'] = $term;
            $params[':q4'] = $term;
            $params[':q5'] = $term;
        }

        if ($rolFiltro !== null && trim($rolFiltro) !== '') {
            $sql .= " AND u.rol = :rol_filtro";
            $params[':rol_filtro'] = strtoupper(trim($rolFiltro));
        }

        if ($estatusFiltro !== null && ($estatusFiltro === '0' || $estatusFiltro === '1')) {
            $sql .= " AND u.estatus = :estatus_filtro";
            $params[':estatus_filtro'] = (int)$estatusFiltro;
        }

        $sql .= " ORDER BY u.estatus DESC, FIELD(u.rol, 'ADMIN', 'MEDICO', 'OPERADOR', 'CLIENTE'), u.user ASC";

        $usuarios = $this->fetchAll($sql, $params);

        return [
            'response' => [
                'resultado' => 200,
                'datos'     => $usuarios,
                'total'     => count($usuarios)
            ]
        ];
    }

    private function obtenerUsuario(string $cedula): array
    {
        $cedula = trim($cedula);
        $sql = "SELECT u.cedula, u.user, u.rol, u.estatus, u.ultimo_acceso, u.tema,
                       p.nombre, p.apellido, p.correo, p.telefono, p.direccion, r.nombre_rol
                FROM Usuario u
                LEFT JOIN persona p ON u.cedula = p.cedula
                LEFT JOIN rol r ON u.rol = r.id_rol
                WHERE u.cedula = :c LIMIT 1";

        $usuario = $this->fetchOne($sql, [':c' => $cedula]);

        if (!$usuario) {
            return ['response' => ['resultado' => 404, 'mensaje' => 'Usuario no encontrado.']];
        }

        $submodulos = $this->obtenerSubmodulos($cedula, $usuario['rol'])['response']['lista'] ?? [];

        return [
            'response' => [
                'resultado'  => 200,
                'datos'      => $usuario,
                'submodulos' => $submodulos
            ]
        ];
    }

    /**
     * Actualiza información del usuario (Usuario + persona) y su rol con validaciones completas
     */
    private function actualizarUsuario(array $datos): array
    {
        $cedula = trim((string)($datos['cedula'] ?? $this->cedula));
        if ($cedula === '') {
            return ['response' => ['resultado' => 400, 'mensaje' => 'Cédula no proporcionada.']];
        }

        $actual = $this->fetchOne("SELECT cedula, user, rol, estatus FROM Usuario WHERE cedula = :c LIMIT 1", [
            ':c' => $cedula,
        ]);
        if (!$actual) {
            return ['response' => ['resultado' => 404, 'mensaje' => 'El usuario que intenta editar no existe.']];
        }

        $user = trim((string)($datos['user'] ?? $actual['user']));
        if ($err = $this->validarUsername($user)) {
            return ['response' => ['resultado' => 400, 'mensaje' => $err]];
        }

        $nombre = trim((string)($datos['nombre'] ?? ''));
        if ($err = $this->validarNombrePersona($nombre, 'Nombres')) {
            return ['response' => ['resultado' => 400, 'mensaje' => $err]];
        }

        $apellido = trim((string)($datos['apellido'] ?? ''));
        if ($err = $this->validarNombrePersona($apellido, 'Apellidos')) {
            return ['response' => ['resultado' => 400, 'mensaje' => $err]];
        }

        $rol = strtoupper(trim((string)($datos['rol'] ?? $actual['rol'])));
        if (!$this->rolExiste($rol)) {
            return ['response' => ['resultado' => 400, 'mensaje' => 'El rol seleccionado no es válido.']];
        }

        $correo = trim((string)($datos['correo'] ?? ''));
        if ($err = $this->validarCorreo($correo)) {
            return ['response' => ['resultado' => 400, 'mensaje' => $err]];
        }

        $telefono = trim((string)($datos['telefono'] ?? ''));
        if ($err = $this->validarTelefono($telefono)) {
            return ['response' => ['resultado' => 400, 'mensaje' => $err]];
        }

        $direccion = trim((string)($datos['direccion'] ?? ''));
        if ($err = $this->validarDireccion($direccion)) {
            return ['response' => ['resultado' => 400, 'mensaje' => $err]];
        }

        if (strtoupper((string)$actual['rol']) === 'ADMIN' && $rol !== 'ADMIN') {
            if ($this->contarAdminsActivos($cedula) === 0) {
                return [
                    'response' => [
                        'resultado' => 409,
                        'mensaje'   => 'No puede cambiar el rol del único administrador activo del sistema.'
                    ]
                ];
            }
        }

        $dupUser = $this->fetchOne("SELECT COUNT(*) AS tot FROM Usuario WHERE user = :u AND cedula <> :c", [
            ':u' => $user,
            ':c' => $cedula,
        ]);
        if (!empty($dupUser['tot']) && (int)$dupUser['tot'] > 0) {
            return ['response' => ['resultado' => 409, 'mensaje' => "El nombre de usuario '{$user}' ya pertenece a otra cuenta."]];
        }

        $pdo = Database::getConnection();
        if ($pdo === null) {
            return ['response' => ['resultado' => 500, 'mensaje' => 'Error de conexión con la base de datos.']];
        }

        try {
            $pdo->beginTransaction();

            $stmtU = $pdo->prepare("UPDATE Usuario SET user = :user, rol = :rol WHERE cedula = :c");
            $stmtU->execute([
                ':user' => $user,
                ':rol'  => $rol,
                ':c'    => $cedula,
            ]);

            $stmtP = $pdo->prepare(
                "INSERT INTO persona (cedula, nombre, apellido, telefono, correo, direccion, sexo)
                 VALUES (:c, :nom, :ape, :tel, :cor, :dir, 'M')
                 ON DUPLICATE KEY UPDATE
                     nombre = :nom_u,
                     apellido = :ape_u,
                     telefono = :tel_u,
                     correo = :cor_u,
                     direccion = :dir_u"
            );
            $stmtP->execute([
                ':c'     => $cedula,
                ':nom'   => $nombre,
                ':ape'   => $apellido,
                ':tel'   => $telefono !== '' ? $telefono : null,
                ':cor'   => $correo !== '' ? strtolower($correo) : null,
                ':dir'   => $direccion !== '' ? $direccion : null,
                ':nom_u' => $nombre,
                ':ape_u' => $apellido,
                ':tel_u' => $telefono !== '' ? $telefono : null,
                ':cor_u' => $correo !== '' ? strtolower($correo) : null,
                ':dir_u' => $direccion !== '' ? $direccion : null,
            ]);

            $pdo->commit();

            return [
                'response' => [
                    'resultado' => 200,
                    'mensaje'   => "Usuario '{$user}' actualizado correctamente.",
                ]
            ];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['response' => ['resultado' => 500, 'mensaje' => 'Error al actualizar usuario: ' . $e->getMessage()]];
        }
    }

    private function toggleEstatus(string $cedula, int $nuevoEstatus): array
    {
        $cedula = trim($cedula);
        if ($cedula === '') {
            return ['response' => ['resultado' => 400, 'mensaje' => 'Cédula de usuario no especificada.']];
        }

        $nuevoEstatus = ($nuevoEstatus === 1) ? 1 : 0;

        $actual = $this->fetchOne("SELECT cedula, user, rol, estatus FROM Usuario WHERE cedula = :c LIMIT 1", [
            ':c' => $cedula,
        ]);
        if (!$actual) {
            return ['response' => ['resultado' => 404, 'mensaje' => 'Usuario no encontrado.']];
        }

        if ($nuevoEstatus === 0 && strtoupper((string)$actual['rol']) === 'ADMIN' && $this->contarAdminsActivos($cedula) === 0) {
            return [
                'response' => [
                    'resultado' => 409,
                    'mensaje'   => 'No puede inhabilitar al único administrador activo del sistema.'
                ]
            ];
        }

        $this->execute("UPDATE Usuario SET estatus = :e WHERE cedula = :c", [
            ':e' => $nuevoEstatus,
            ':c' => $cedula
        ]);

        $msg = ($nuevoEstatus === 1)
            ? "Usuario '{$actual['user']}' activado exitosamente."
            : "Usuario '{$actual['user']}' inhabilitado exitosamente.";

        return ['response' => ['resultado' => 200, 'mensaje' => $msg]];
    }

    private function eliminarUsuario(string $cedula): array
    {
        $cedula = trim($cedula);
        if ($cedula === '') {
            return ['response' => ['resultado' => 400, 'mensaje' => 'Cédula de usuario no especificada.']];
        }

        $actual = $this->fetchOne("SELECT cedula, user, rol FROM Usuario WHERE cedula = :c LIMIT 1", [
            ':c' => $cedula,
        ]);
        if (!$actual) {
            return ['response' => ['resultado' => 404, 'mensaje' => 'Usuario no encontrado.']];
        }

        if (strtoupper((string)$actual['rol']) === 'ADMIN' && $this->contarAdminsActivos($cedula) === 0) {
            return [
                'response' => [
                    'resultado' => 409,
                    'mensaje'   => 'No puede eliminar al único administrador activo del sistema.'
                ]
            ];
        }

        $filas = $this->execute("DELETE FROM Usuario WHERE cedula = :c", [':c' => $cedula]);
        if ($filas > 0) {
            return ['response' => ['resultado' => 200, 'mensaje' => "Cuenta de usuario '{$actual['user']}' eliminada correctamente."]];
        }

        return ['response' => ['resultado' => 500, 'mensaje' => 'No se pudo eliminar la cuenta de usuario.']];
    }

    private function cambiarClave(string $cedula, string $nuevaClave): array
    {
        $cedula = trim($cedula);
        if ($cedula === '') {
            return ['response' => ['resultado' => 400, 'mensaje' => 'Cédula de usuario no especificada.']];
        }

        if ($err = $this->validarPassword($nuevaClave)) {
            return ['response' => ['resultado' => 400, 'mensaje' => $err]];
        }

        $existe = $this->fetchOne("SELECT user FROM Usuario WHERE cedula = :c LIMIT 1", [':c' => $cedula]);
        if (!$existe) {
            return ['response' => ['resultado' => 404, 'mensaje' => 'Usuario no encontrado.']];
        }

        $hash = password_hash($nuevaClave, PASSWORD_BCRYPT);
        $this->execute("UPDATE Usuario SET password = :p WHERE cedula = :c", [
            ':p' => $hash,
            ':c' => $cedula
        ]);

        return ['response' => ['resultado' => 200, 'mensaje' => "Contraseña de '{$existe['user']}' actualizada correctamente."]];
    }
}

// Alias de compatibilidad con SICGOV y namespaces alternativos
if (!class_exists('App\Models\Security\Usuario', false)) {
    class_alias(Usuario::class, 'App\Models\Security\Usuario');
}
if (!class_exists('App\model\Usuario', false)) {
    class_alias(Usuario::class, 'App\model\Usuario');
}
if (!class_exists('Usuario', false)) {
    class_alias(Usuario::class, 'Usuario');
}
