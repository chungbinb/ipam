<?php
require_once __DIR__ . '/../models/IpAddressModel.php';
require_once __DIR__ . '/../models/HostModel.php';
require_once __DIR__ . '/../services/LogService.php';

class IpAddressController {
    private $model;
    private $logService;

    public function __construct() {
        $this->model = new IpAddressModel();
        $this->logService = new LogService();
    }

    public function getList() {
        $list = $this->model->getAll();
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(is_array($list) ? $list : []);
        exit;
    }

    public function update($id) {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $originalRow = $this->model->getById($id); // 获取原始数据用于日志记录
        
        $affected = $this->model->update($id, $data);
        $row = $this->model->getById($id);

        // 记录IP地址修改日志
        if ($affected >= 0 && $row) {
            $changes = [];
            foreach ($data as $key => $value) {
                $oldValue = $originalRow[$key] ?? '';
                $newValue = $value;
                if ($oldValue != $newValue) {
                    $changes[$key] = ['from' => $oldValue, 'to' => $newValue];
                }
            }
            
            if (!empty($changes)) {
                $this->logService->logIpAddressOperation('update', $row['ip'], [
                    'id' => $id,
                    'changes' => $changes
                ]);
            }
        }

        // 按 IP 同步到主机，仅同步本次提交的非空字段
        if ($row && !empty($row['ip']) && filter_var($row['ip'], FILTER_VALIDATE_IP)) {
            try {
                $sync = [];
                if (isset($data['department']) && trim((string)$data['department']) !== '') {
                    $sync['department'] = trim((string)$data['department']);
                }
                if (isset($data['user']) && trim((string)$data['user']) !== '') {
                    $sync['user'] = trim((string)$data['user']);
                }
                if (isset($data['mac']) && trim((string)$data['mac']) !== '') {
                    $sync['mac'] = trim((string)$data['mac']);
                }
                if (isset($data['asset_number']) && trim((string)$data['asset_number']) !== '') {
                    $sync['asset_number'] = trim((string)$data['asset_number']); // HostModel中映射为host_number
                }
                if (!empty($sync)) {
                    $hostModel = new HostModel();
                    $hostModel->updateByIp($row['ip'], $sync);
                }
            } catch (\Throwable $e) {
                // 忽略同步异常
            }
        }

        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['success' => $affected >= 0]);
        exit;
    }

    public function pingIp() {
        $ip = $_GET['ip'] ?? '';
        $success = false;
        $output = '';
        if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
            if (stripos(PHP_OS, 'WIN') === 0) {
                $cmd = "ping -n 1 -w 2000 " . escapeshellarg($ip);
            } else {
                $cmd = "ping -c 1 -W 2 " . escapeshellarg($ip);
            }
            @exec($cmd, $out, $status);
            $output = implode("\n", (array)$out);
            $success = ($status === 0);
        }
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'output' => $output]);
        exit;
    }

    public function pingIpBatch() {
        $list = $this->model->getAll();
        foreach ($list as $row) {
            $ip = $row['ip'] ?? '';
            $id = $row['id'] ?? 0;
            $success = false;
            if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
                if (stripos(PHP_OS, 'WIN') === 0) {
                    $cmd = "ping -n 1 -w 2000 " . escapeshellarg($ip);
                } else {
                    $cmd = "ping -c 1 -W 2 " . escapeshellarg($ip);
                }
                @exec($cmd, $out, $status);
                $success = ($status === 0);
            }
            $this->model->updatePingStatus($id, [
                'ping' => $success ? '通' : '不通',
                'ping_time' => date('Y-m-d H:i:s'),
                'status' => $success ? '使用中' : ($row['status'] ?? '')
            ]);
        }
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
}
?>
