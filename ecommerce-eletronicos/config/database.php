<?php
// =====================================================
//  SESSION HANDLER — deve vir ANTES do session_start()
// =====================================================
class DBSessionHandler implements SessionHandlerInterface {
    private PDO $conn;

    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }

    public function open($path, $name): bool { return true; }
    public function close(): bool { return true; }

    public function read($id): string {
        $stmt = $this->conn->prepare(
            "SELECT data FROM php_sessions WHERE id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['data'] : '';
    }

    public function write($id, $data): bool {
        $stmt = $this->conn->prepare("
            INSERT INTO php_sessions (id, data, last_access)
            VALUES (?, ?, ?)
            ON CONFLICT (id) DO UPDATE
            SET data        = EXCLUDED.data,
                last_access = EXCLUDED.last_access
        ");
        return $stmt->execute([$id, $data, time()]);
    }

    public function destroy($id): bool {
        $stmt = $this->conn->prepare(
            "DELETE FROM php_sessions WHERE id = ?"
        );
        return $stmt->execute([$id]);
    }

    public function gc($max_lifetime): int|false {
        $stmt = $this->conn->prepare(
            "DELETE FROM php_sessions WHERE last_access < ?"
        );
        $stmt->execute([time() - $max_lifetime]);
        return $stmt->rowCount();
    }
}

// =====================================================
//  CONEXÃO — usa variáveis de ambiente (Render/Docker)
// =====================================================
ini_set('session.gc_maxlifetime', 86400); // 24 horas

define('DB_HOST',     getenv('DB_HOST')     ?: 'localhost');
define('DB_PORT',     getenv('DB_PORT')     ?: '5432');
define('DB_NAME',     getenv('DB_NAME')     ?: 'ecommerce_eletronicos');
define('DB_USER',     getenv('DB_USER')     ?: 'postgres');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: 'Allan2021.');

try {
    $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT
         . ";dbname=" . DB_NAME . ";sslmode=require";

    $conn = new PDO($dsn, DB_USER, DB_PASSWORD, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $conn->exec("SET NAMES 'UTF8'");

} catch (PDOException $e) {
    die(json_encode([
        'error' => true,
        'msg'   => 'Erro de conexão com o banco: ' . $e->getMessage()
    ]));
}

// =====================================================
//  REGISTRA HANDLER E INICIA SESSÃO
// =====================================================
$handler = new DBSessionHandler($conn);
session_set_save_handler($handler, true);
session_start();

// =====================================================
//  TENANT
// =====================================================
require_once __DIR__ . '/tenant.php';
carregar_tenant($conn);

// =====================================================
//  FUNÇÕES AUXILIARES
// =====================================================
function sanitize($conn, $value) {
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}

function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) !== 11 || preg_match('/(\d)\1{10}/', $cpf)) return false;
    for ($t = 9; $t < 11; $t++) {
        $soma = 0;
        for ($i = 0; $i < $t; $i++) {
            $soma += $cpf[$i] * ($t + 1 - $i);
        }
        $r = ((10 * $soma) % 11) % 10;
        if ($cpf[$t] != $r) return false;
    }
    return true;
}

function registrar_log($conn, $tipo, $titulo, $detalhe = '', $valor = 0, $ref_id = null, $feito_por = null) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $conn->prepare("
            INSERT INTO logs (tipo, titulo, detalhe, valor, ref_id, feito_por, ip, criado_em)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([$tipo, $titulo, $detalhe, $valor, $ref_id, $feito_por, $ip]);
    } catch (\Throwable $e) {
        // Falha silenciosa
    }
}

function img_src(string $imagem): string {
    if (empty($imagem)) return 'uploads/placeholder.jpg';
    if (str_starts_with($imagem, 'http')) return $imagem;
    return 'uploads/' . $imagem;
}

function redirecionar_login() {
    $sub = $_SESSION['subdominio'] ?? '';
    $url = $sub ? 'login.php?tenant=' . urlencode($sub) : 'login.php';
    header('Location: ' . $url);
    exit;
}
