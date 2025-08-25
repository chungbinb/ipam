<?php
require_once __DIR__ . '/../config/database.php';

class ConsumableModel
{
    private $pdo;

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function getList($query = [])
    {
        $sql = "SELECT * FROM consumable WHERE 1=1";
        $params = [];
        if (!empty($query['keyword'])) {
            $kw = '%' . $query['keyword'] . '%';
            $sql .= " AND (name LIKE ? OR type LIKE ? OR model LIKE ? OR spec LIKE ? OR barcode LIKE ? OR remark LIKE ?)";
            for ($i = 0; $i < 6; $i++) $params[] = $kw;
        }
        foreach (['name','type','model','spec','barcode','remark'] as $f) {
            if (!empty($query[$f])) {
                $sql .= " AND `$f` LIKE ?";
                $params[] = '%' . $query[$f] . '%';
            }
        }
        $sql .= " ORDER BY id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM consumable WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data)
    {
        try {
            $this->pdo->beginTransaction();

            $sql = "INSERT INTO consumable (name, type, model, spec, barcode, stock, updated_at, remark, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, NOW())";
            $stmt = $this->pdo->prepare($sql);
            $ok = $stmt->execute([
                $data['name'] ?? '',
                $data['type'] ?? '',
                $data['model'] ?? '',
                $data['spec'] ?? '',
                $data['barcode'] ?? '',
                $data['stock'] ?? 0,
                $data['remark'] ?? ''
            ]);
            if (!$ok) {
                $this->pdo->rollBack();
                return false;
            }

            $consumableId = $this->pdo->lastInsertId();
            $inCount = isset($data['stock']) ? (int)$data['stock'] : 0;

            // 新增入库明细：备注为“添加耗材”
            $ins = $this->pdo->prepare("
                INSERT INTO consumable_in_detail
                    (consumable_id, name, type, model, spec, in_count, remark, created_at)
                VALUES (?, ?, ?, ?, ?, ?, '添加耗材', NOW())
            ");
            $ok2 = $ins->execute([
                $consumableId,
                $data['name'] ?? '',
                $data['type'] ?? '',
                $data['model'] ?? '',
                $data['spec'] ?? '',
                $inCount
            ]);

            if (!$ok2) {
                $this->pdo->rollBack();
                return false;
            }

            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return false;
        }
    }

    public function update($id, $data)
    {
        // 仅允许更新这些字段（不允许直接编辑库存）
        $allowed = ['name','type','model','spec','barcode','remark'];

        try {
            // 读取旧值
            $stmt = $this->pdo->prepare("SELECT * FROM consumable WHERE id=?");
            $stmt->execute([$id]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$old) return false;

            // 合并新值（忽略未允许的字段）
            $update = [];
            foreach ($allowed as $f) {
                $update[$f] = array_key_exists($f, $data) ? $data[$f] : $old[$f];
            }

            $this->pdo->beginTransaction();

            // 更新主表
            $sql = "UPDATE consumable SET name=?, type=?, model=?, spec=?, barcode=?, remark=?, updated_at=NOW() WHERE id=?";
            $okMain = $this->pdo->prepare($sql)->execute([
                $update['name'],
                $update['type'],
                $update['model'],
                $update['spec'],
                $update['barcode'],
                $update['remark'],
                $id
            ]);

            if (!$okMain) {
                $this->pdo->rollBack();
                return false;
            }

            // 如 name/type/model/spec 有变化，同步到入库/出库明细（不修改备注）
            $needSync = (
                $update['name']  !== $old['name']  ||
                $update['type']  !== $old['type']  ||
                $update['model'] !== $old['model'] ||
                $update['spec']  !== $old['spec']
            );

            if ($needSync) {
                // 入库明细同步
                $okIn = $this->pdo->prepare("
                    UPDATE consumable_in_detail
                    SET name=?, type=?, model=?, spec=?
                    WHERE consumable_id=?
                ")->execute([
                    $update['name'], $update['type'], $update['model'], $update['spec'], $id
                ]);

                // 出库明细同步
                $okOut = $this->pdo->prepare("
                    UPDATE consumable_out_detail
                    SET name=?, type=?, model=?, spec=?
                    WHERE consumable_id=?
                ")->execute([
                    $update['name'], $update['type'], $update['model'], $update['spec'], $id
                ]);

                if (!$okIn || !$okOut) {
                    $this->pdo->rollBack();
                    return false;
                }
            }

            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return false;
        }
    }

    public function delete($id)
    {
        try {
            $this->pdo->beginTransaction();

            // 锁定待删除记录
            $stmt = $this->pdo->prepare("SELECT * FROM consumable WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$old) {
                $this->pdo->rollBack();
                return false;
            }

            // 插入出库明细（备注：删除；数量：当前库存）
            $ins = $this->pdo->prepare("
                INSERT INTO consumable_out_detail
                    (consumable_id, name, type, model, spec, out_count, remark, created_at)
                VALUES (?, ?, ?, ?, ?, ?, '删除', NOW())
            ");
            $ok1 = $ins->execute([
                $old['id'],
                $old['name'],
                $old['type'],
                $old['model'],
                $old['spec'],
                (int)($old['stock'] ?? 0)
            ]);

            // 删除主表记录
            $del = $this->pdo->prepare("DELETE FROM consumable WHERE id = ?");
            $ok2 = $del->execute([$id]);

            if ($ok1 && $ok2) {
                $this->pdo->commit();
                return true;
            } else {
                $this->pdo->rollBack();
                return false;
            }
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return false;
        }
    }

    public function inStock($id, $count, $remark = '')
    {
        if ($count <= 0) return ['error' => '入库数量必须大于0'];
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare("SELECT * FROM consumable WHERE id=? FOR UPDATE");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) { $this->pdo->rollBack(); return ['error' => '耗材不存在']; }

            // 写入入库明细
            $ins = $this->pdo->prepare("
                INSERT INTO consumable_in_detail
                    (consumable_id, name, type, model, spec, in_count, remark, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $ok1 = $ins->execute([
                $row['id'], $row['name'], $row['type'], $row['model'], $row['spec'],
                $count, ($remark === '' ? '入库' : $remark)
            ]);

            // 更新库存
            $upd = $this->pdo->prepare("UPDATE consumable SET stock = stock + ?, updated_at = NOW() WHERE id = ?");
            $ok2 = $upd->execute([$count, $id]);

            if ($ok1 && $ok2) {
                $this->pdo->commit();
                return true;
            }
            $this->pdo->rollBack();
            return false;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return false;
        }
    }

    public function outStock($id, $count, $remark = '')
    {
        if ($count <= 0) return ['error' => '出库数量必须大于0'];
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare("SELECT * FROM consumable WHERE id=? FOR UPDATE");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) { $this->pdo->rollBack(); return ['error' => '耗材不存在']; }
            $stock = (int)($row['stock'] ?? 0);
            if ($count > $stock) {
                $this->pdo->rollBack();
                return ['error' => '库存不足'];
            }

            // 写入出库明细
            $ins = $this->pdo->prepare("
                INSERT INTO consumable_out_detail
                    (consumable_id, name, type, model, spec, out_count, remark, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $ok1 = $ins->execute([
                $row['id'], $row['name'], $row['type'], $row['model'], $row['spec'],
                $count, ($remark === '' ? '出库' : $remark)
            ]);

            // 更新库存
            $upd = $this->pdo->prepare("UPDATE consumable SET stock = stock - ?, updated_at = NOW() WHERE id = ?");
            $ok2 = $upd->execute([$count, $id]);

            if ($ok1 && $ok2) {
                $this->pdo->commit();
                return true;
            }
            $this->pdo->rollBack();
            return false;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            return false;
        }
    }

    public function getInDetail($query = [])
    {
        $sql = "SELECT d.*, c.barcode 
                FROM consumable_in_detail d 
                LEFT JOIN consumable c ON c.id = d.consumable_id 
                WHERE 1=1";
        $params = [];
        if (!empty($query['keyword'])) {
            $kw = '%' . $query['keyword'] . '%';
            $sql .= " AND (d.name LIKE ? OR d.type LIKE ? OR d.model LIKE ? OR d.spec LIKE ? OR d.remark LIKE ? OR c.barcode LIKE ?)";
            for ($i = 0; $i < 6; $i++) $params[] = $kw;
        }
        if (!empty($query['start'])) {
            $sql .= " AND d.created_at >= ?";
            $params[] = $query['start'] . ' 00:00:00';
        }
        if (!empty($query['end'])) {
            $sql .= " AND d.created_at <= ?";
            $params[] = $query['end'] . ' 23:59:59';
        }
        $sql .= " ORDER BY d.created_at DESC, d.id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOutDetail($query = [])
    {
        $sql = "SELECT d.*, c.barcode 
                FROM consumable_out_detail d 
                LEFT JOIN consumable c ON c.id = d.consumable_id 
                WHERE 1=1";
        $params = [];
        if (!empty($query['keyword'])) {
            $kw = '%' . $query['keyword'] . '%';
            $sql .= " AND (d.name LIKE ? OR d.type LIKE ? OR d.model LIKE ? OR d.spec LIKE ? OR d.remark LIKE ? OR c.barcode LIKE ?)";
            for ($i = 0; $i < 6; $i++) $params[] = $kw;
        }
        if (!empty($query['start'])) {
            $sql .= " AND d.created_at >= ?";
            $params[] = $query['start'] . ' 00:00:00';
        }
        if (!empty($query['end'])) {
            $sql .= " AND d.created_at <= ?";
            $params[] = $query['end'] . ' 23:59:59';
        }
        $sql .= " ORDER BY d.created_at DESC, d.id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
