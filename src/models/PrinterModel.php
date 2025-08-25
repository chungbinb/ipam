<?php
require_once __DIR__ . '/../config/database.php';

class PrinterModel
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
            $sql = "SELECT * FROM printer WHERE 1=1";
            $params = [];
            foreach (['department','user','brand','model','asset_number','status'] as $f) {
                if (!empty($query[$f])) {
                    $sql .= " AND `$f` LIKE ?";
                    $params[] = '%' . $query[$f] . '%';
                }
            }
            $sql .= " ORDER BY id DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('PrinterModel getList error: ' . $e->getMessage());
            return [];
        }
    }

    public function create($data)
    {
        try {
            // 资产编号唯一性校验
            if (!empty($data['asset_number'])) {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM printer WHERE asset_number = ?");
                $stmt->execute([$data['asset_number']]);
                if ($stmt->fetchColumn() > 0) {
                    return ['error' => '资产编号已存在'];
                }
            }
            // 字段预处理，空字符串转为 null
            foreach (['type', 'function', 'status'] as $f) {
                if (isset($data[$f]) && $data[$f] === '') {
                    $data[$f] = null;
                }
            }
            // 新增：处理 updated_at 字段（如果传入的是 YYYY/MM/DD，转为 Y-m-d 00:00:00）
            if (!empty($data['updated_at'])) {
                $val = $data['updated_at'];
                // 支持 YYYY/MM/DD 或 YYYY-M-D
                if (preg_match('/^\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}$/', $val)) {
                    $dt = date_create(str_replace('/', '-', $val));
                    if ($dt) {
                        $data['updated_at'] = $dt->format('Y-m-d 00:00:00');
                    }
                }
            } else {
                $data['updated_at'] = date('Y-m-d H:i:s');
            }
            $sql = "INSERT INTO printer (department, user, brand, model, color, type, `function`, asset_number, status, price, currency, updated_at, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $this->pdo->prepare($sql);
            $ok = $stmt->execute([
                $data['department'] ?? '',
                $data['user'] ?? '',
                $data['brand'] ?? '',
                $data['model'] ?? '',
                $data['color'] ?? '',
                $data['type'] ?? null,
                $data['function'] ?? null,
                $data['asset_number'] ?? '',
                $data['status'] ?? null,
                $data['price'] ?? '',
                $data['currency'] ?? 'CNY',
                $data['updated_at'] ?? date('Y-m-d H:i:s')
            ]);
            if (!$ok) {
                error_log('PrinterModel create failed: ' . json_encode($stmt->errorInfo()));
            }
            return $ok;
        } catch (\Throwable $e) {
            error_log('PrinterModel create exception: ' . $e->getMessage());
            return false;
        }
    }

    public function update($id, $data)
    {
        try {
            // 资产编号唯一性校验（排除自身）
            if (!empty($data['asset_number'])) {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM printer WHERE asset_number = ? AND id != ?");
                $stmt->execute([$data['asset_number'], $id]);
                if ($stmt->fetchColumn() > 0) {
                    return ['error' => '资产编号已存在'];
                }
            }
            $fields = ['department','user','brand','model','color','type','function','asset_number','status','price','currency'];
            $stmt = $this->pdo->prepare("SELECT * FROM printer WHERE id=?");
            $stmt->execute([$id]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$old) return false;
            $update = [];
            foreach ($fields as $f) {
                $update[$f] = isset($data[$f]) ? $data[$f] : $old[$f];
            }
            $sql = "UPDATE printer SET department=?, user=?, brand=?, model=?, color=?, type=?, `function`=?, asset_number=?, status=?, price=?, currency=?, updated_at=NOW() WHERE id=?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                $update['department'],
                $update['user'],
                $update['brand'],
                $update['model'],
                $update['color'],
                $update['type'],
                $update['function'],
                $update['asset_number'],
                $update['status'],
                $update['price'],
                $update['currency'],
                $id
            ]);
        } catch (\Throwable $e) {
            error_log('PrinterModel update error: ' . $e->getMessage());
            return false;
        }
    }

    public function delete($id)
    {
        try {
            $sql = "DELETE FROM printer WHERE id=?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$id]);
        } catch (\Throwable $e) {
            error_log('PrinterModel delete error: ' . $e->getMessage());
            return false;
        }
    }

    public function batchDelete($ids)
    {
        try {
            if (!is_array($ids) || empty($ids)) return false;
            $in = implode(',', array_fill(0, count($ids), '?'));
            $sql = "DELETE FROM printer WHERE id IN ($in)";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($ids);
        } catch (\Throwable $e) {
            error_log('PrinterModel batchDelete error: ' . $e->getMessage());
            return false;
        }
    }

    public function getById($id)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM printer WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('PrinterModel getById error: ' . $e->getMessage());
            return false;
        }
    }

    public function getByIds($ids)
    {
        try {
            if (!is_array($ids) || empty($ids)) return [];
            $in = implode(',', array_fill(0, count($ids), '?'));
            $sql = "SELECT * FROM printer WHERE id IN ($in) ORDER BY FIELD(id, $in)";
            $stmt = $this->pdo->prepare($sql);
            // 两次 $ids 是为了 FIELD 排序
            $stmt->execute(array_merge($ids, $ids));
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('PrinterModel getByIds error: ' . $e->getMessage());
            return [];
        }
    }
}
