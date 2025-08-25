<?php
require_once __DIR__ . '/../config/database.php';

class LaptopModel
{
    private $pdo;

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function getList($query = [])
    {
        $sql = "SELECT * FROM laptop WHERE 1=1";
        $params = [];
        if (!empty($query['name'])) {
            $sql .= " AND `name` LIKE ?";
            $params[] = '%' . $query['name'] . '%';
        }
        if (!empty($query['ip'])) {
            $sql .= " AND `ip` LIKE ?";
            $params[] = '%' . $query['ip'] . '%';
        }
        if (!empty($query['assetId'])) {
            $sql .= " AND `asset_id` LIKE ?";
            $params[] = '%' . $query['assetId'] . '%';
        }
        if (!empty($query['currency'])) {
            $sql .= " AND `currency` = ?";
            $params[] = $query['currency'];
        }
        if (!empty($query['valueMin'])) {
            $sql .= " AND `value` >= ?";
            $params[] = $query['valueMin'];
        }
        if (!empty($query['valueMax'])) {
            $sql .= " AND `value` <= ?";
            $params[] = $query['valueMax'];
        }
        if (!empty($query['region'])) {
            $sql .= " AND `region` LIKE ?";
            $params[] = '%' . $query['region'] . '%';
        }
        if (!empty($query['department'])) {
            $sql .= " AND `department` LIKE ?";
            $params[] = '%' . $query['department'] . '%';
        }
        if (!empty($query['user'])) {
            $sql .= " AND `user` LIKE ?";
            $params[] = '%' . $query['user'] . '%';
        }
        if (!empty($query['status'])) {
            $sql .= " AND `status` = ?";
            $params[] = $query['status'];
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($data)
    {
        $sql = "INSERT INTO laptop (name, ip, asset_id, value, currency, acquire_date, region, department, user, status, remark, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['name'] ?? '',
            $data['ip'] ?? '',
            $data['assetId'] ?? '', // 注意这里是 assetId
            $data['value'] ?? 0,
            $data['currency'] ?? 'CNY',
            $data['acquireDate'] ?? null,
            $data['region'] ?? '',
            $data['department'] ?? '',
            $data['user'] ?? '',
            $data['status'] ?? '',
            $data['remark'] ?? ''
        ]);
    }

    public function update($id, $data)
    {
        // 只更新有提交的字段，未提交的字段保持原值
        $fields = [
            'name', 'ip', 'asset_id', 'value', 'currency', 'acquire_date',
            'region', 'department', 'user', 'status', 'remark'
        ];
        // 先查出原数据
        $stmt = $this->pdo->prepare("SELECT * FROM laptop WHERE id=?");
        $stmt->execute([$id]);
        $old = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$old) return false;

        // 合并新旧数据
        $update = [];
        foreach ($fields as $f) {
            if (isset($data[$f])) {
                $update[$f] = $data[$f];
            } elseif ($f === 'asset_id' && isset($data['assetId'])) {
                $update[$f] = $data['assetId'];
            } else {
                $update[$f] = $old[$f];
            }
        }

        $sql = "UPDATE laptop SET name=?, ip=?, asset_id=?, value=?, currency=?, acquire_date=?, region=?, department=?, user=?, status=?, remark=?, updated_at=NOW() WHERE id=?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $update['name'],
            $update['ip'],
            $update['asset_id'],
            $update['value'],
            $update['currency'],
            $update['acquire_date'],
            $update['region'],
            $update['department'],
            $update['user'],
            $update['status'],
            $update['remark'],
            $id
        ]);
    }

    public function delete($id)
    {
        $sql = "DELETE FROM laptop WHERE id=?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id]);
    }

    public function batchDelete($ids)
    {
        if (!is_array($ids) || empty($ids)) return false;
        $in = implode(',', array_fill(0, count($ids), '?'));
        $sql = "DELETE FROM laptop WHERE id IN ($in)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($ids);
    }
}
