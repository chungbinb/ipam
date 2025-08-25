<?php
require_once __DIR__ . '/../config/database.php';
// 新增：用于同步主机信息
require_once __DIR__ . '/HostModel.php';

class MonitorModel
{
    private $pdo;

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function getList($query = [])
    {
        // 兼容 asset_number/assetId 查询参数
        if (!empty($query['asset_number']) && empty($query['asset_id'])) {
            $query['asset_id'] = $query['asset_number'];
        }
        if (!empty($query['assetId']) && empty($query['asset_id'])) {
            $query['asset_id'] = $query['assetId'];
        }

        $sql = "SELECT * FROM monitor WHERE 1=1";
        $params = [];
        foreach (['department','user','brand','model','asset_id','status'] as $f) {
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

    public function create($data)
    {
        // 兼容 asset_number/assetId 写入
        if (empty($data['asset_id'])) {
            if (!empty($data['asset_number'])) $data['asset_id'] = $data['asset_number'];
            if (!empty($data['assetId'])) $data['asset_id'] = $data['assetId'];
        }

        // 保证所有字段都有值
        $fields = [
            'brand','asset_id','department','user','size','model','spec','status','remark'
        ];
        foreach ($fields as $f) {
            if (!isset($data[$f])) $data[$f] = '';
        }
        if (!isset($data['updated_at']) || !$data['updated_at']) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $sql = "INSERT INTO monitor (brand, asset_id, department, user, size, model, spec, status, remark, updated_at, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute([
            $data['brand'],
            $data['asset_id'],
            $data['department'],
            $data['user'],
            $data['size'],
            $data['model'],
            $data['spec'],
            $data['status'],
            $data['remark'],
            $data['updated_at']
        ]);

        // 新增成功后，同步到主机（仅同步本次提交中非空的部门与使用人）
        if ($ok) {
            $assetId = isset($data['asset_id']) ? trim((string)$data['asset_id']) : '';
            if ($assetId !== '') {
                $payload = [];
                if (isset($data['department']) && trim((string)$data['department']) !== '') {
                    $payload['department'] = trim((string)$data['department']);
                }
                if (isset($data['user']) && trim((string)$data['user']) !== '') {
                    $payload['user'] = trim((string)$data['user']);
                }
                if (!empty($payload)) {
                    $hostModel = new HostModel();
                    $hostModel->updateByMonitorNumber($assetId, $payload);
                }
            }
        }
        return $ok;
    }

    public function update($id, $data)
    {
        // 兼容 asset_number/assetId 输入
        if (empty($data['asset_id'])) {
            if (!empty($data['asset_number'])) $data['asset_id'] = $data['asset_number'];
            if (!empty($data['assetId'])) $data['asset_id'] = $data['assetId'];
        }

        $fields = ['brand','asset_id','department','user','size','model','spec','status','remark'];
        $stmt = $this->pdo->prepare("SELECT * FROM monitor WHERE id=?");
        $stmt->execute([$id]);
        $old = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$old) return false;

        $update = [];
        foreach ($fields as $f) {
            $update[$f] = array_key_exists($f, $data) ? $data[$f] : $old[$f];
        }

        $sql = "UPDATE monitor SET brand=?, asset_id=?, department=?, user=?, size=?, model=?, spec=?, status=?, remark=?, updated_at=NOW() WHERE id=?";
        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute([
            $update['brand'],
            $update['asset_id'],
            $update['department'],
            $update['user'],
            $update['size'],
            $update['model'],
            $update['spec'],
            $update['status'],
            $update['remark'],
            $id
        ]);

        // 更新成功后，仅将“本次提交的非空 department、user”同步到主机（按 monitor_number = asset_id）
        if ($ok) {
            $assetId = isset($update['asset_id']) ? trim((string)$update['asset_id']) : '';
            if ($assetId !== '') {
                $payload = [];
                if (array_key_exists('department', $data) && trim((string)$data['department']) !== '') {
                    $payload['department'] = trim((string)$data['department']);
                }
                if (array_key_exists('user', $data) && trim((string)$data['user']) !== '') {
                    $payload['user'] = trim((string)$data['user']);
                }
                if (!empty($payload)) {
                    $hostModel = new HostModel();
                    $hostModel->updateByMonitorNumber($assetId, $payload);
                }
            }
        }

        return $ok;
    }

    public function delete($id)
    {
        $sql = "DELETE FROM monitor WHERE id=?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id]);
    }

    public function batchDelete($ids)
    {
        if (!is_array($ids) || empty($ids)) return false;
        $in = implode(',', array_fill(0, count($ids), '?'));
        $sql = "DELETE FROM monitor WHERE id IN ($in)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($ids);
    }
}
?>
