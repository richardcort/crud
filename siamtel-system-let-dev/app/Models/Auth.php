<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class Auth extends Model
{
    private string $cedula = '';
    private string $user = '';
    private string $password = '';
    private string $rol = 'CLIENTE';
    private int $estatus = 1;
    private ?array $currentUser = null;

    public function setCedula(string $cedula): void
    {
        $this->cedula = trim($cedula);
    }

    public function getCedula(): string
    {
        return $this->cedula;
    }

    public function setUser(string $user): void
    {
        $this->user = trim($user);
    }

    public function getUser(): string
    {
        return $this->user;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setRol(string $rol): void
    {
        $this->rol = strtoupper(trim($rol));
    }

    public function getRol(): string
    {
        return $this->rol;
    }

    public function setEstatus(int $estatus): void
    {
        $this->estatus = $estatus;
    }

    public function getEstatus(): int
    {
        return $this->estatus;
    }

    /**
     * ÚNICO método público para acceder a las operaciones del modelo (SOLID & DRY)
     */
    public function Transaccion(array $params = []): array
    {
        $peticion = $params['peticion'] ?? '';

        return match ($peticion) {
            'sesion'           => $this->iniciarSesion(),
            'perfil'           => $this->obtenerPerfil(),
            'verificar_cedula' => $this->verificarCedula(),
            'registrar'        => $this->registrarUsuario(),
            default => [
                'response' => [
                    'resultado' => 400,
                    'mensaje'   => "Petición no reconocida: '{$peticion}'",
                ],
            ]
        };
    }

    /**
     * Extrae variantes seguras para buscar por cédula o nombre de usuario sin mutilar usernames que inicien con V/E/J/P/G
     */
    private function buildIdentifierVariants(string $raw): array
    {
        $raw = trim($raw);
        $clean = $raw;

        // Solo quitar prefijo si realmente corresponde a una cédula (ej: V-00000000 o V00000000)
        if (preg_match('/^[VEJPG]-?(\d{5,10})$/i', $raw, $m)) {
            $clean = $m[1];
            $raw = strtoupper($raw[0]) . '-' . $m[1];
        } elseif (preg_match('/^[VEJPG]-(.+)$/i', $raw, $m)) {
            // Caso donde el formulario antepuso el prefijo del select a un nombre de usuario (ej: V-admin -> admin)
            $clean = trim($m[1]);
        }

        return [
            'raw'   => $raw,
            'clean' => $clean,
        ];
    }

    /**
     * Valida credenciales e inicia sesión
     */
    private function iniciarSesion(): array
    {
        if (Database::getConnection() === null) {
            return [
                'response' => [
                    'resultado'    => 500,
                    'mensaje'      => 'No se pudo conectar con la base de datos. Verifique que el servicio MariaDB esté activo.',
                    'verificacion' => false,
                ],
            ];
        }

        $ids = $this->buildIdentifierVariants($this->cedula);

        $sql = "SELECT u.*, p.nombre, p.apellido, p.correo
                FROM Usuario u
                LEFT JOIN persona p ON u.cedula = p.cedula
                WHERE u.cedula = :cedula_raw
                   OR u.cedula = :cedula_clean
                   OR u.user = :user_raw
                   OR u.user = :user_clean
                LIMIT 1";

        $registro = $this->fetchOne($sql, [
            ':cedula_raw'   => $ids['raw'],
            ':cedula_clean' => $ids['clean'],
            ':user_raw'     => $ids['raw'],
            ':user_clean'   => $ids['clean'],
        ]);

        if (!$registro) {
            return [
                'response' => [
                    'resultado'    => 404,
                    'mensaje'      => 'Usuario no encontrado',
                    'verificacion' => false,
                ],
            ];
        }

        // Verificar si la cuenta está inhabilitada
        if (isset($registro['estatus']) && (int)$registro['estatus'] === 0) {
            return [
                'response' => [
                    'resultado'    => 403,
                    'mensaje'      => 'Su cuenta se encuentra inhabilitada.',
                    'verificacion' => false,
                ],
            ];
        }

        // Verificar contraseña (soporta hash bcrypt y texto plano del seed)
        $storedHash = (string)($registro['password'] ?? '');
        $isPasswordValid = false;

        if (password_verify($this->password, $storedHash)) {
            $isPasswordValid = true;
        } elseif ($storedHash !== '' && hash_equals($storedHash, $this->password)) {
            $isPasswordValid = true;
            // Migrar automáticamente a hash seguro bcrypt
            try {
                $newHash = password_hash($this->password, PASSWORD_BCRYPT);
                $this->execute("UPDATE Usuario SET password = :hash WHERE cedula = :cedula", [
                    ':hash'   => $newHash,
                    ':cedula' => $registro['cedula'],
                ]);
                $registro['password'] = $newHash;
            } catch (\Throwable $th) {
                error_log('[Auth Password Migration] ' . $th->getMessage());
            }
        }

        if (!$isPasswordValid) {
            return [
                'response' => [
                    'resultado'    => 401,
                    'mensaje'      => 'Cédula/usuario o contraseña incorrectos.',
                    'verificacion' => false,
                ],
            ];
        }

        // Registrar fecha/hora de último acceso
        try {
            $this->execute("UPDATE Usuario SET ultimo_acceso = NOW() WHERE cedula = :cedula", [
                ':cedula' => $registro['cedula'],
            ]);
        } catch (\Throwable $th) {
            error_log('[Auth Last Access Update] ' . $th->getMessage());
        }

        // Guardar usuario autenticado internamente
        $nombreCompleto = trim(((string)($registro['nombre'] ?? '')) . ' ' . ((string)($registro['apellido'] ?? '')));
        $this->currentUser = [
            'cedula'          => $registro['cedula'],
            'user'            => $registro['user'],
            'rol'             => $registro['rol'],
            'estatus'         => (int)$registro['estatus'],
            'nombre'          => $registro['nombre'] ?? '',
            'apellido'        => $registro['apellido'] ?? '',
            'nombre_completo' => $nombreCompleto !== '' ? $nombreCompleto : $registro['user'],
            'correo'          => $registro['correo'] ?? '',
        ];

        return [
            'response' => [
                'resultado'    => 200,
                'mensaje'      => 'Inicio de sesión exitoso.',
                'verificacion' => true,
                'datos'        => $this->currentUser,
            ],
        ];
    }

    /**
     * Retorna los datos del perfil activo
     */
    private function obtenerPerfil(): array
    {
        if ($this->currentUser !== null) {
            return [
                'response' => [
                    'resultado' => 200,
                    'datos'     => $this->currentUser,
                ],
            ];
        }

        $ids = $this->buildIdentifierVariants($this->cedula);

        $sql = "SELECT u.cedula, u.user, u.rol, u.estatus, p.nombre, p.apellido, p.correo
                FROM Usuario u
                LEFT JOIN persona p ON u.cedula = p.cedula
                WHERE u.cedula = :c_raw OR u.cedula = :c_clean OR u.user = :u_raw OR u.user = :u_clean
                LIMIT 1";

        $registro = $this->fetchOne($sql, [
            ':c_raw'   => $ids['raw'],
            ':c_clean' => $ids['clean'],
            ':u_raw'   => $ids['raw'],
            ':u_clean' => $ids['clean'],
        ]);

        if ($registro) {
            $nombreCompleto = trim(((string)($registro['nombre'] ?? '')) . ' ' . ((string)($registro['apellido'] ?? '')));
            return [
                'response' => [
                    'resultado' => 200,
                    'datos'     => [
                        'cedula'          => $registro['cedula'],
                        'user'            => $registro['user'],
                        'rol'             => $registro['rol'],
                        'estatus'         => (int)$registro['estatus'],
                        'nombre'          => $registro['nombre'] ?? '',
                        'apellido'        => $registro['apellido'] ?? '',
                        'nombre_completo' => $nombreCompleto !== '' ? $nombreCompleto : $registro['user'],
                        'correo'          => $registro['correo'] ?? '',
                    ],
                ],
            ];
        }

        return [
            'response' => [
                'resultado' => 404,
                'mensaje'   => 'Perfil no encontrado',
            ],
        ];
    }

    /**
     * Verifica si una cédula ya está registrada
     */
    private function verificarCedula(): array
    {
        $ids = $this->buildIdentifierVariants($this->cedula);

        $sql = "SELECT COUNT(*) as total FROM Usuario WHERE cedula = :raw OR cedula = :clean";
        $row = $this->fetchOne($sql, [
            ':raw'   => $ids['raw'],
            ':clean' => $ids['clean'],
        ]);

        $existe = ($row && (int)$row['total'] > 0);

        return [
            'response' => [
                'resultado' => 200,
                'existe'    => $existe,
            ],
        ];
    }

    /**
     * Registra un nuevo usuario
     */
    private function registrarUsuario(): array
    {
        if (empty($this->cedula) || empty($this->password) || empty($this->user)) {
            return [
                'response' => [
                    'resultado' => 400,
                    'mensaje'   => 'Faltan campos obligatorios para el registro.',
                ],
            ];
        }

        $existe = $this->verificarCedula()['response']['existe'] ?? false;
        if ($existe) {
            return [
                'response' => [
                    'resultado' => 409,
                    'mensaje'   => 'El usuario o cédula ya se encuentra registrado.',
                ],
            ];
        }

        $hash = password_hash($this->password, PASSWORD_BCRYPT);
        $sql = "INSERT INTO Usuario (cedula, user, password, rol, estatus)
                VALUES (:cedula, :user, :password, :rol, :estatus)";

        $rows = $this->execute($sql, [
            ':cedula'   => $this->cedula,
            ':user'     => $this->user,
            ':password' => $hash,
            ':rol'      => $this->rol,
            ':estatus'  => $this->estatus,
        ]);

        if ($rows > 0) {
            return [
                'response' => [
                    'resultado' => 200,
                    'mensaje'   => 'Usuario registrado exitosamente.',
                ],
            ];
        }

        return [
            'response' => [
                'resultado' => 500,
                'mensaje'   => 'Error al registrar el usuario en el sistema.',
            ],
        ];
    }
}

// Compatibilidad con namespaces alternativos
if (!class_exists('app\model\Auth', false)) {
    class_alias(Auth::class, 'app\model\Auth');
}
if (!class_exists('App\model\Auth', false)) {
    class_alias(Auth::class, 'App\model\Auth');
}
