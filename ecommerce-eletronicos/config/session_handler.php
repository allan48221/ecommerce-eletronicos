<?php
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
            SET data = EXCLUDED.data,
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
