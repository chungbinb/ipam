<?php
require_once __DIR__ . '/../config/database.php';

class IpAddressModel {
    public $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function getAll() {
        $stmt = $this->pdo->query('SELECT * FROM ip_addresses ORDER BY id DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare('SELECT * FROM ip_addresses WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function normalize(array $data): array {
        foreach ($data as $k => $v) {
            if ($v === null) $data[$k] = '';
        }
        return $data;
    }

    public function insert($data) {
        $fields = ['ip','mac','hostname','business','department','user','status','manual','ping','ping_time','remark','segment','asset_number'];
        $data = $this->normalize($data);
        $cols = []; $holders = []; $values = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $cols[] = $f; $holders[] = '?'; $values[] = $data[$f];
            }
        }
        if (empty($cols)) return false;
        $sql = 'INSERT INTO ip_addresses (' . implode(',', $cols) . ') VALUES (' . implode(',', $holders) . ')';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }

    public function update($id, $data) {
        $fields = ['mac','hostname','business','department','user','status','manual','remark','asset_number','ping','ping_time','segment'];
        $data = $this->normalize($data);
        $sets = []; $values = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "`$f` = ?"; $values[] = $data[$f];
            }
        }
        if (empty($sets)) return 0;
        $values[] = $id;
        $sql = 'UPDATE ip_addresses SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
        return $stmt->rowCount();
    }

    public function updatePingStatus($id, $data) {
        $fields = ['ping','ping_time','status'];
        $data = $this->normalize($data);
        $sets = []; $values = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "`$f` = ?"; $values[] = $data[$f];
            }
        }
        if (empty($sets)) return 0;
        $values[] = $id;
        $sql = 'UPDATE ip_addresses SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
        return $stmt->rowCount();
    }

    public function deleteBySegment($segment) {
        $stmt = $this->pdo->prepare('DELETE FROM ip_addresses WHERE segment = ?');
        $stmt->execute([$segment]);
        return $stmt->rowCount();
    }
}
?>
