<?php

require_once __DIR__ . '/../config/database.php';

class AccountModel
{
    private $pdo;

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;
        
        // 确保默认管理员账号存在
        $this->ensureDefaultAdmin();
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
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 调试信息
        error_log("Finding user '$username', result: " . ($result ? 'found' : 'not found'));
        if ($result) {
            error_log("User data: " . json_encode([
                'id' => $result['id'],
                'username' => $result['username'],
                'role' => $result['role'],
                'status' => $result['status'],
                'has_password' => !empty($result['password_hash'])
            ]));
        }
        
        return $result;
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

    private function ensureDefaultAdmin()
    {
        try {
            // 检查是否存在admin用户
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM accounts WHERE username = 'admin'");
            $stmt->execute();
            $count = $stmt->fetchColumn();
            
            if ($count == 0) {
                // 创建默认admin账号
                $passwordHash = password_hash('admin', PASSWORD_DEFAULT);
                $stmt = $this->pdo->prepare("
                    INSERT INTO accounts (username, password_hash, role, status, created_at) 
                    VALUES ('admin', ?, 'sysadmin', 'active', NOW())
                ");
                $stmt->execute([$passwordHash]);
                error_log("Created default admin account");
            } else {
                // 检查现有admin账号的密码格式
                $stmt = $this->pdo->prepare("SELECT id, password_hash FROM accounts WHERE username = 'admin'");
                $stmt->execute();
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($admin && strlen($admin['password_hash']) < 20) {
                    // 可能是明文密码，更新为哈希
                    $passwordHash = password_hash('admin', PASSWORD_DEFAULT);
                    $stmt = $this->pdo->prepare("UPDATE accounts SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$passwordHash, $admin['id']]);
                    error_log("Updated admin password to hash format");
                }
            }
        } catch (\Throwable $e) {
            error_log("Error ensuring default admin: " . $e->getMessage());
        }
    }
}