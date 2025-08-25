<?php
require_once __DIR__ . '/../config/database.php';

class ServerModel
{
    private $pdo;

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function getList($query = [])
    {
        try {
            $sql = "SELECT * FROM `server` WHERE 1=1";
            $params = [];
            
            // 支持多字段搜索
            foreach (['hostname', 'ip', 'os', 'type', 'model', 'business', 'status', 'owner'] as $field) {
                if (!empty($query[$field])) {
                    $sql .= " AND `$field` LIKE ?";
                    $params[] = '%' . $query[$field] . '%';
                }
            }
            
            $sql .= " ORDER BY id DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('ServerModel getList error: ' . $e->getMessage());
            return [];
        }
    }

    public function getById($id)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM `server` WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('ServerModel getById error: ' . $e->getMessage());
            return false;
        }
    }

    public function create($data)
    {
        try {
            // IP地址唯一性校验
            if (!empty($data['ip'])) {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `server` WHERE ip = ?");
                $stmt->execute([$data['ip']]);
                if ($stmt->fetchColumn() > 0) {
                    return ['error' => 'IP地址已存在'];
                }
            }

            $sql = "INSERT INTO `server` (hostname, ip, os, type, model, spec, buy_date, buy_price, business, status, owner, ports, remark)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                $data['hostname'] ?? '',
                $data['ip'] ?? '',
                $data['os'] ?? '',
                $data['type'] ?? '',
                $data['model'] ?? '',
                $data['spec'] ?? '',
                $data['buy_date'] ?? null,
                $data['buy_price'] ?? '',
                $data['business'] ?? '',
                $data['status'] ?? '离线',
                $data['owner'] ?? '',
                $data['ports'] ?? '',
                $data['remark'] ?? ''
            ]);
            
            return $result;
        } catch (\Throwable $e) {
            error_log('ServerModel create error: ' . $e->getMessage());
            return false;
        }
    }

    public function update($id, $data)
    {
        try {
            // IP地址唯一性校验（排除自身）
            if (!empty($data['ip'])) {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `server` WHERE ip = ? AND id != ?");
                $stmt->execute([$data['ip'], $id]);
                if ($stmt->fetchColumn() > 0) {
                    return ['error' => 'IP地址已存在'];
                }
            }

            // 获取原始数据
            $originalData = $this->getById($id);
            if (!$originalData) {
                return false;
            }

            // 构建更新数据
            $fields = ['hostname', 'ip', 'os', 'type', 'model', 'spec', 'buy_date', 'buy_price', 'business', 'status', 'owner', 'ports', 'remark'];
            $updateData = [];
            foreach ($fields as $field) {
                $updateData[$field] = array_key_exists($field, $data) ? $data[$field] : $originalData[$field];
            }

            $sql = "UPDATE `server` SET 
                    hostname = ?, ip = ?, os = ?, type = ?, model = ?, spec = ?, 
                    buy_date = ?, buy_price = ?, business = ?, status = ?, owner = ?, 
                    ports = ?, remark = ?, updated_at = NOW()
                    WHERE id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                $updateData['hostname'],
                $updateData['ip'],
                $updateData['os'],
                $updateData['type'],
                $updateData['model'],
                $updateData['spec'],
                $updateData['buy_date'],
                $updateData['buy_price'],
                $updateData['business'],
                $updateData['status'],
                $updateData['owner'],
                $updateData['ports'],
                $updateData['remark'],
                $id
            ]);
        } catch (\Throwable $e) {
            error_log('ServerModel update error: ' . $e->getMessage());
            return false;
        }
    }

    public function delete($id)
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM `server` WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (\Throwable $e) {
            error_log('ServerModel delete error: ' . $e->getMessage());
            return false;
        }
    }

    public function batchDelete($ids)
    {
        try {
            if (!is_array($ids) || empty($ids)) {
                return false;
            }
            
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "DELETE FROM `server` WHERE id IN ($placeholders)";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($ids);
        } catch (\Throwable $e) {
            error_log('ServerModel batchDelete error: ' . $e->getMessage());
            return false;
        }
    }

    public function getByIds($ids)
    {
        try {
            if (!is_array($ids) || empty($ids)) {
                return [];
            }
            
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "SELECT * FROM `server` WHERE id IN ($placeholders) ORDER BY FIELD(id, $placeholders)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge($ids, $ids));
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('ServerModel getByIds error: ' . $e->getMessage());
            return [];
        }
    }
}
