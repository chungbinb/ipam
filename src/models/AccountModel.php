<?php

require_once __DIR__ . '/../config/database.php';

class AccountModel
{
    private $pdo;

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function findById($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM accounts WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByUsername($username)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM accounts WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAll()
    {
        $stmt = $this->pdo->query("SELECT id, username, role, status, created_at, updated_at FROM accounts ORDER BY id");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($data)
    {
        $sql = "INSERT INTO accounts (username, password_hash, role, status) VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['username'],
            $data['password_hash'],
            $data['role'],
            $data['status']
        ]);
    }

    public function update($id, $data)
    {
        $fields = [];
        $values = [];
        
        if (isset($data['username'])) {
            $fields[] = "username = ?";
            $values[] = $data['username'];
        }
        if (isset($data['password_hash'])) {
            $fields[] = "password_hash = ?";
            $values[] = $data['password_hash'];
        }
        if (isset($data['status'])) {
            $fields[] = "status = ?";
            $values[] = $data['status'];
        }
        
        if (empty($fields)) return true;
        
        $values[] = $id;
        $sql = "UPDATE accounts SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }

    public function delete($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM accounts WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function usernameExists($username, $excludeId = null)
    {
        if ($excludeId) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM accounts WHERE username = ? AND id != ?");
            $stmt->execute([$username, $excludeId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM accounts WHERE username = ?");
            $stmt->execute([$username]);
        }
        return $stmt->fetchColumn() > 0;
    }
}