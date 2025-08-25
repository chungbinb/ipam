<?php
require_once __DIR__ . '/../config/database.php';

class BrandModel
{
    private $pdo;

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function getList($query = [])
    {
        $sql = "SELECT * FROM brand WHERE 1=1";
        $params = [];
        if (!empty($query['name'])) {
            $sql .= " AND `name` LIKE ?";
            $params[] = '%' . $query['name'] . '%';
        }
        if (!empty($query['type'])) {
            $sql .= " AND `type` = ?";
            $params[] = $query['type'];
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM brand WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data)
    {
        $sql = "INSERT INTO brand (name, type, created_at, updated_at) VALUES (?, ?, NOW(), NOW())";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['name'] ?? '',
            $data['type'] ?? ''
        ]);
    }

    public function update($id, $data)
    {
        $old = $this->getById($id);
        if (!$old) return false;
        $name = isset($data['name']) ? $data['name'] : $old['name'];
        $type = isset($data['type']) ? $data['type'] : $old['type'];
        $sql = "UPDATE brand SET name=?, type=?, updated_at=NOW() WHERE id=?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$name, $type, $id]);
    }

    public function delete($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM brand WHERE id=?");
        return $stmt->execute([$id]);
    }

    public function batchDelete($ids)
    {
        if (!is_array($ids) || empty($ids)) return false;
        $in = implode(',', array_fill(0, count($ids), '?'));
        $sql = "DELETE FROM brand WHERE id IN ($in)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($ids);
    }
}
?>
