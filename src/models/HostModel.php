<?php
require_once __DIR__ . '/../config/database.php';
// 新增：引入 IP 地址模型
require_once __DIR__ . '/IpAddressModel.php';

class HostModel
{
    private $pdo;

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function getList($query = [])
    {
        $sql = "SELECT * FROM host WHERE 1=1";
        $params = [];
        if (!empty($query['keyword'])) {
            $kw = '%' . $query['keyword'] . '%';
            $sql .= " AND (department LIKE ? OR user LIKE ? OR cpu LIKE ? OR memory LIKE ? OR disk LIKE ? OR ip LIKE ? OR mac LIKE ? OR host_number LIKE ? OR monitor_number LIKE ? OR printer_number LIKE ? OR account LIKE ? OR supplier LIKE ? OR remark LIKE ?)";
            for ($i = 0; $i < 13; $i++) $params[] = $kw;
        }
        // 可扩展更多精确字段
        foreach (['department','user','cpu','memory','disk','ip','mac','host_number','monitor_number','printer_number','account','supplier','remark'] as $f) {
            if (!empty($query[$f])) {
                $sql .= " AND `$f` LIKE ?";
                $params[] = '%' . $query[$f] . '%';
            }
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($data)
    {
        try {
            // 新增：前置校验（仅在提供了合法IP时校验）
            $ip = isset($data['ip']) ? trim($data['ip']) : '';
            if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
                try {
                    if (!$this->ipExists($ip)) {
                        $seg = $this->findSegmentForIp($ip);
                        if (!$seg) {
                            // 同时没有IP和网段 -> 阻止保存
                            return ['error' => '请先去"IP地址管理"添加该IP所属的IP段'];
                        }
                    }
                } catch (\Throwable $e) {
                    // IP校验失败不阻止创建，只记录错误
                    error_log('Host IP validation error: ' . $e->getMessage());
                }
            }

            $sql = "INSERT INTO host (department, user, cpu, memory, disk, ip, mac, host_number, monitor_number, printer_number, account, supplier, remark, updated_at, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $this->pdo->prepare($sql);
            
            // 处理updated_at字段，如果为空则使用当前时间
            $updatedAt = $data['updated_at'] ?? '';
            if (empty($updatedAt) || $updatedAt === '') {
                $updatedAt = date('Y-m-d H:i:s');
            }
            
            $ok = $stmt->execute([
                $data['department'] ?? '',
                $data['user'] ?? '',
                $data['cpu'] ?? '',
                $data['memory'] ?? '',
                $data['disk'] ?? '',
                $data['ip'] ?? '',
                $data['mac'] ?? '',
                $data['host_number'] ?? '',
                $data['monitor_number'] ?? '',
                $data['printer_number'] ?? '',
                $data['account'] ?? '',
                $data['supplier'] ?? '',
                $data['remark'] ?? '',
                $updatedAt
            ]);

            // 新增：成功后同步到 IP 地址管理和显示器管理（包装在try-catch中，避免影响主流程）
            if ($ok) {
                try {
                    $this->syncIpAddress($data);
                } catch (\Throwable $e) {
                    error_log('Host syncIpAddress error: ' . $e->getMessage());
                }
                
                try {
                    $this->syncMonitorFromHost($data);
                } catch (\Throwable $e) {
                    error_log('Host syncMonitorFromHost error: ' . $e->getMessage());
                }
            }
            return $ok;
        } catch (\Throwable $e) {
            error_log('HostModel create error: ' . $e->getMessage());
            error_log('HostModel create data: ' . json_encode($data));
            return ['error' => '数据库插入失败: ' . $e->getMessage()];
        }
    }

    public function update($id, $data)
    {
        try {
            $fields = [
                'department','user','cpu','memory','disk','ip','mac','host_number','monitor_number','printer_number','account','supplier','remark'
            ];
            $stmt = $this->pdo->prepare("SELECT * FROM host WHERE id=?");
            $stmt->execute([$id]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$old) return false;
            
            $update = [];
            foreach ($fields as $f) {
                $update[$f] = isset($data[$f]) ? $data[$f] : $old[$f];
            }

            // 新增：前置校验（仅在提供了合法IP时校验）
            $ip = isset($update['ip']) ? trim($update['ip']) : '';
            if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
                if (!$this->ipExists($ip)) {
                    $seg = $this->findSegmentForIp($ip);
                    if (!$seg) {
                        return ['error' => '请先去"IP地址管理"添加该IP所属的IP段'];
                    }
                }
            }

            // MySQL 5.5兼容：手动设置更新时间
            $sql = "UPDATE host SET department=?, user=?, cpu=?, memory=?, disk=?, ip=?, mac=?, host_number=?, monitor_number=?, printer_number=?, account=?, supplier=?, remark=?, updated_at=NOW() WHERE id=?";
            $stmt = $this->pdo->prepare($sql);
            $ok = $stmt->execute([
                $update['department'],
                $update['user'],
                $update['cpu'],
                $update['memory'],
                $update['disk'],
                $update['ip'],
                $update['mac'],
                $update['host_number'],
                $update['monitor_number'],
                $update['printer_number'],
                $update['account'],
                $update['supplier'],
                $update['remark'],
                $id
            ]);

            // 新增：成功后同步到 IP 地址管理（包含 IP、MAC、部门、使用人员、主机编号->资产编号、备注）
            if ($ok) {
                $this->syncIpAddress($update);
                // 新增：成功后同步到显示器管理（仅部门、使用人，且仅非空覆盖）
                $this->syncMonitorFromHost($update);
            }
            return $ok;
        } catch (\Throwable $e) {
            error_log('HostModel update error: ' . $e->getMessage());
            error_log('HostModel update data: ' . json_encode($data));
            return ['error' => '数据库更新失败: ' . $e->getMessage()];
        }
    }

    public function delete($id)
    {
        $sql = "DELETE FROM host WHERE id=?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id]);
    }

    public function getById($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM host WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function batchDelete($ids)
    {
        if (!is_array($ids) || empty($ids)) return false;
        $in = implode(',', array_fill(0, count($ids), '?'));
        $sql = "DELETE FROM host WHERE id IN ($in)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($ids);
    }

    /**
     * 按 IP 更新主机的部门、使用人、MAC、主机编号（asset_number -> host_number）
     */
    public function updateByIp(string $ip, array $data): int
    {
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) return 0;

        $map = [
            'department'   => 'department',
            'user'         => 'user',
            'mac'          => 'mac',
            'asset_number' => 'host_number' // 映射
        ];
        $sets = [];
        $values = [];
        foreach ($map as $in => $col) {
            if (array_key_exists($in, $data)) {
                $val = is_null($data[$in]) ? '' : trim((string)$data[$in]);
                if ($val !== '') { // 仅非空才覆盖
                    $sets[] = "`$col` = ?";
                    $values[] = $val;
                }
            }
        }
        if (empty($sets)) return 0;

        $values[] = $ip;
        $sql = 'UPDATE host SET ' . implode(', ', $sets) . ' WHERE ip = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
        return $stmt->rowCount();
    }

    public function updateByMonitorNumber(string $monitorNumber, array $data): int
    {
        if (!$monitorNumber) return 0;
        $sets = [];
        $values = [];
        if (array_key_exists('department', $data)) {
            $val = trim((string)$data['department']);
            if ($val !== '') { $sets[] = "department = ?"; $values[] = $val; }
        }
        if (array_key_exists('user', $data)) {
            $val = trim((string)$data['user']);
            if ($val !== '') { $sets[] = "`user` = ?"; $values[] = $val; }
        }
        if (empty($sets)) return 0;

        $values[] = $monitorNumber;
        $sql = "UPDATE host SET " . implode(', ', $sets) . " WHERE monitor_number = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
        return $stmt->rowCount();
    }

    // 新增：将主机信息同步到 ip_addresses 表（按 IP 匹配，存在则更新，否则插入）
    private function syncIpAddress(array $data): void
    {
        try {
            $ip = isset($data['ip']) ? trim($data['ip']) : '';
            if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
                return;
            }
            $mac = $data['mac'] ?? '';
            $department = $data['department'] ?? '';
            $user = $data['user'] ?? '';
            $remark = $data['remark'] ?? '';
            $assetNumber = $data['host_number'] ?? '';

            $stmt = $this->pdo->prepare('SELECT id FROM ip_addresses WHERE ip = ? LIMIT 1');
            $stmt->execute([$ip]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $ipModel = new IpAddressModel();
            if ($row && isset($row['id'])) {
                // 仅同步非空字段；同时强制标记手动为“已分配”
                $payload = ['manual' => '已分配'];
                if (trim((string)$mac) !== '') $payload['mac'] = trim((string)$mac);
                if (trim((string)$department) !== '') $payload['department'] = trim((string)$department);
                if (trim((string)$user) !== '') $payload['user'] = trim((string)$user);
                if (trim((string)$remark) !== '') $payload['remark'] = trim((string)$remark);
                if (trim((string)$assetNumber) !== '') $payload['asset_number'] = trim((string)$assetNumber);

                if (count($payload) > 0) {
                    $ipModel->update($row['id'], $payload);
                }
            } else {
                // 插入新记录：允许空值（不写入 NULL），手动标记为“已分配”
                $seg = $this->findSegmentForIp($ip);
                if (!$seg) return;
                $ipModel->insert([
                    'ip' => $ip,
                    'mac' => $mac,
                    'hostname' => '',
                    'business' => '',
                    'department' => $department,
                    'user' => $user,
                    'status' => '',
                    'manual' => '已分配',
                    'ping' => '',
                    'ping_time' => '',
                    'remark' => $remark,
                    'segment' => $seg,
                    'asset_number' => $assetNumber
                ]);
            }
        } catch (\Throwable $e) {
            // 忽略同步异常，避免影响主流程
        }
    }

    // 新增：判断 IP 是否已在 ip_addresses 中存在
    private function ipExists(string $ip): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM ip_addresses WHERE ip = ? LIMIT 1');
        $stmt->execute([$ip]);
        return (bool)$stmt->fetchColumn();
    }

    // 新增：根据 ip_segments 表找到包含该 IP 的网段，返回形如 "192.168.10.0/24" 的字符串；找不到返回 null
    private function findSegmentForIp(string $ip): ?string
    {
        try {
            $ipLong = ip2long($ip);
            if ($ipLong === false) return null;

            $stmt = $this->pdo->query('SELECT segment, mask FROM ip_segments');
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $seg = $row['segment'] ?? '';
                $mask = (int)($row['mask'] ?? 0);
                if (!$seg || $mask < 0 || $mask > 32) continue;

                $segLong = ip2long($seg);
                if ($segLong === false) continue;

                // 计算掩码（PHP为64位整型可用）
                $maskLong = $mask === 0 ? 0 : (-1 << (32 - $mask)) & 0xFFFFFFFF;
                if ( (($ipLong & $maskLong) === ($segLong & $maskLong)) && $mask > 0 ) {
                    return $seg . '/' . $mask;
                }
            }
        } catch (\Throwable $e) {
            return null;
        }
        return null;
    }

    /**
     * 将主机信息同步到显示器表：按 host.monitor_number = monitor.asset_id
     * 仅同步部门、使用人，且仅在传入值非空时覆盖。
     */
    private function syncMonitorFromHost(array $data): void
    {
        try {
            $monitorNumber = isset($data['monitor_number']) ? trim((string)$data['monitor_number']) : '';
            if ($monitorNumber === '') return;

            $dept = isset($data['department']) ? trim((string)$data['department']) : '';
            $user = isset($data['user']) ? trim((string)$data['user']) : '';
            $sets = [];
            $values = [];
            if ($dept !== '') { $sets[] = "department = ?"; $values[] = $dept; }
            if ($user !== '') { $sets[] = "`user` = ?";   $values[] = $user; }
            if (empty($sets)) return;

            $values[] = $monitorNumber;
            // 注意：此处 asset_id 必须与 monitor 表结构一致
            $sql = "UPDATE monitor SET " . implode(', ', $sets) . " WHERE asset_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($values);
        } catch (\Throwable $e) {
            // 忽略同步异常
        }
    }
}
