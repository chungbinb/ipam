<?php
require_once __DIR__ . '/../config/database.php';

class IpSegmentModel {
    private $pdo;

    public function __construct() {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function getAll() {
        $stmt = $this->pdo->query('SELECT * FROM ip_segments ORDER BY id DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 修正方法名为 create，兼容控制器调用
    public function create($data) {
        $stmt = $this->pdo->prepare('INSERT INTO ip_segments (segment, mask, business, department, unused, vlan, tag, remark) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $data['segment'],
            $data['mask'],
            $data['business'] ?? '',
            $data['department'] ?? '',
            $data['unused'] ?? '',
            $data['vlan'] ?? '',
            $data['tag'] ?? '',
            $data['remark'] ?? ''
        ]);
        return $this->pdo->lastInsertId();
    }

    public function getById($id) {
        $stmt = $this->pdo->prepare('SELECT * FROM ip_segments WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 检查IP段是否已存在（只检查segment，忽略mask）
    public function segmentExists($segment, $mask) {
        $stmt = $this->pdo->prepare('SELECT segment, mask FROM ip_segments WHERE segment = ?');
        $stmt->execute([$segment]);
        $existingSegment = $stmt->fetch(PDO::FETCH_ASSOC);
        return $existingSegment ? $existingSegment : false;
    }

    public function delete($id) {
        $stmt = $this->pdo->prepare('DELETE FROM ip_segments WHERE id = ?');
        $result = $stmt->execute([$id]);
        // 返回是否成功删除了记录
        return $result && $stmt->rowCount() > 0;
    }

    public function update($id, $data) {
        $fields = [];
        $values = [];
        foreach ($data as $key => $val) {
            $fields[] = "$key = ?";
            $values[] = $val;
        }
        if (!$fields) return;
        $sql = "UPDATE ip_segments SET " . implode(',', $fields) . " WHERE id = ?";
        $values[] = $id;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
    }
}
?>
