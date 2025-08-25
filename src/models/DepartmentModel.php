<?php
require_once __DIR__ . '/../config/database.php';

class DepartmentModel
{
    private $pdo;

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function getList()
    {
        $stmt = $this->pdo->query("SELECT * FROM department ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM department WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data)
    {
        $sql = "INSERT INTO department (name, region, remark, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['name'] ?? '',
            $data['region'] ?? '',
            $data['remark'] ?? ''
        ]);
    }

    public function update($id, $data)
    {
        $sql = "UPDATE department SET name=?, region=?, remark=?, updated_at=NOW() WHERE id=?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['name'] ?? '',
            $data['region'] ?? '',
            $data['remark'] ?? '',
            $id
        ]);
    }

    public function delete($id)
    {
        $sql = "DELETE FROM department WHERE id=?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id]);
    }

    public function batchDelete($ids)
    {
        if (!is_array($ids) || empty($ids)) return false;
        $in = implode(',', array_fill(0, count($ids), '?'));
        $sql = "DELETE FROM department WHERE id IN ($in)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($ids);
    }
}
?>
