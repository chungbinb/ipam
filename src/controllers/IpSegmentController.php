<?php
require_once __DIR__ . '/../models/IpSegmentModel.php';
require_once __DIR__ . '/../models/IpAddressModel.php';
require_once __DIR__ . '/../services/LogService.php';
require_once __DIR__ . '/../models/SessionModel.php';

class IpSegmentController {
    private $ipSegmentModel;
    private $ipAddressModel;
    private $logService;
    private $sessionModel;

    public function __construct() {
        $this->ipSegmentModel = new IpSegmentModel();
        $this->ipAddressModel = new IpAddressModel();
        $this->logService = new LogService();
        $this->sessionModel = new SessionModel();
    }

    public function getList() {
        $list = $this->ipSegmentModel->getAll();
        header('Content-Type: application/json');
        echo json_encode($list);
    }

    public function create()
    {
        // 保证只输出一次JSON，避免PHP警告或错误导致输出HTML
        ob_start();
        $model = new IpSegmentModel();
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;
        
        $ipSuccess = true;
        $errorMessage = '';
        
        // 检查必要字段
        if (!isset($input['segment']) || !isset($input['mask'])) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'IP段和掩码不能为空']);
            exit;
        }
        
        $segment = $input['segment'];
        $mask = intval($input['mask']);
        
        // 检查IP段是否已存在
        $existingSegment = $model->segmentExists($segment, $mask);
        if ($existingSegment) {
            $existingMask = $existingSegment['mask'];
            if ($existingMask == $mask) {
                $message = "IP段 {$segment}/{$mask} 已存在，不能重复添加";
            } else {
                $message = "IP段 {$segment} 已存在（掩码为 /{$existingMask}），请先删除已存在的IP段才能添加相同IP段的不同掩码";
            }
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        }
        
        // 创建IP段
        $result = $model->create($input);
        
        // 新增时自动生成IP地址
        if ($result && isset($input['segment'], $input['mask'])) {
            $business = $input['business'] ?? '';
            $department = $input['department'] ?? '';
            $remark = $input['remark'] ?? '';
            
            // 只支持如192.168.1
            $ipParts = explode('.', $segment);
            if (count($ipParts) === 3 && $mask >= 8 && $mask <= 30) {
                $count = pow(2, 32 - $mask) - 2;
                $ipAddressModel = new IpAddressModel();
                
                // 先检查是否已存在该IP段的IP地址，如果存在则先删除
                try {
                    $existingCount = $ipAddressModel->deleteBySegment($segment);
                    if ($existingCount > 0) {
                        error_log("删除了 {$existingCount} 个已存在的IP地址，IP段：{$segment}");
                    }
                } catch (Exception $e) {
                    error_log("删除已存在IP地址时出错：" . $e->getMessage());
                }
                
                // 生成新的IP地址
                for ($i = 1; $i <= $count; $i++) {
                    $ip = "{$ipParts[0]}.{$ipParts[1]}.{$ipParts[2]}.$i";
                    try {
                        $ok = $ipAddressModel->insert([
                            'ip' => $ip,
                            'segment' => $segment,
                            'mask' => $mask,
                            'business' => $business,
                            'department' => $department,
                            'remark' => $remark
                        ]);
                        if (!$ok) {
                            $ipSuccess = false;
                            $errorMessage = "创建IP地址失败：{$ip}";
                            break;
                        }
                    } catch (Exception $e) {
                        $ipSuccess = false;
                        $errorMessage = "创建IP地址时出错：" . $e->getMessage();
                        break;
                    }
                }
            } else {
                $ipSuccess = false;
                $errorMessage = "IP段格式或掩码不正确";
            }
        }

        // 清理所有输出，只输出JSON
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        ob_end_clean();
        
        if ($result && $ipSuccess) {
            // 记录IP段创建成功日志
            $this->logService->logIpSegmentOperation('create', "{$segment}/{$mask}", [
                'business' => $business,
                'department' => $department,
                'count' => $count,
                'remark' => $remark
            ]);
            echo json_encode(['success' => true, 'message' => 'IP段创建成功']);
        } else {
            echo json_encode(['success' => false, 'message' => $errorMessage ?: 'IP段创建失败']);
        }
        exit;
    }

    public function delete($id) {
        // 获取要删除的IP段
        $segmentRow = $this->ipSegmentModel->getById($id);
        if (!$segmentRow) {
            if (!headers_sent()) header('Content-Type: application/json');
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'IP段不存在']);
            return;
        }
        
        $segment = $segmentRow['segment'];  // 例如：192.168.200
        
        try {
            // 先删除该IP段下的所有IP地址
            $deletedIpCount = $this->ipAddressModel->deleteBySegment($segment);
            
            // 再删除IP段本身
            $segmentDeleted = $this->ipSegmentModel->delete($id);
            
            if (!headers_sent()) header('Content-Type: application/json');
            
            if ($segmentDeleted) {
                // 记录IP段删除成功日志
                $this->logService->logIpSegmentOperation('delete', $segment, [
                    'deleted_ip_count' => $deletedIpCount,
                    'segment_info' => $segmentRow
                ]);
                
                echo json_encode([
                    'success' => true, 
                    'message' => "IP段删除成功，同时删除了 {$deletedIpCount} 个IP地址"
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'IP段删除失败']);
            }
        } catch (Exception $e) {
            if (!headers_sent()) header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => '删除失败：' . $e->getMessage()]);
        }
    }

    public function update($id) {
        // 保证只输出一次JSON，避免PHP警告或错误导致输出HTML
        ob_start();
        $data = json_decode(file_get_contents('php://input'), true);
        // 只允许更新非 segment 和 mask 字段
        $fields = ['business', 'department', 'unused', 'vlan', 'tag', 'remark'];
        $updateData = [];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }
        $this->ipSegmentModel->update($id, $updateData);

        // 同步更新该IP段下所有IP地址的业务、部门、备注
        $segmentRow = $this->ipSegmentModel->getById($id);
        $ipSuccess = true;
        $ipErrorMsg = '';
        if ($segmentRow) {
            $segment = $segmentRow['segment'];
            // 注意：ip_addresses表没有mask字段，只能用segment字段
            $ipAddressModel = new IpAddressModel();
            try {
                // 检查是否有对应IP地址
                $stmtCheck = $ipAddressModel->pdo->prepare('SELECT COUNT(*) FROM ip_addresses WHERE segment=?');
                $stmtCheck->execute([$segment]);
                $ipCount = $stmtCheck->fetchColumn();
                if ($ipCount > 0) {
                    $stmt = $ipAddressModel->pdo->prepare(
                        'UPDATE ip_addresses SET business=?, department=?, remark=? WHERE segment=?'
                    );
                    $ok = $stmt->execute([
                        $updateData['business'] ?? $segmentRow['business'] ?? '',
                        $updateData['department'] ?? $segmentRow['department'] ?? '',
                        $updateData['remark'] ?? $segmentRow['remark'] ?? '',
                        $segment
                    ]);
                    if ($ok === false) {
                        $ipSuccess = false;
                        $ipErrorMsg = 'IP地址表更新失败';
                    }
                } else {
                    $ipSuccess = false;
                    $ipErrorMsg = '该IP段下没有IP地址数据，无法同步更新';
                }
            } catch (\Throwable $e) {
                $ipSuccess = false;
                $ipErrorMsg = $e->getMessage();
            }
        } else {
            $ipSuccess = false;
            $ipErrorMsg = '未找到IP段';
        }

        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        ob_end_clean();
        echo json_encode([
            'success' => $ipSuccess,
            'message' => $ipSuccess ? '保存成功' : ('保存失败: ' . $ipErrorMsg)
        ]);
        exit;
    }

    // 补齐IP段为192.168.0.0格式
    private function formatSegment($segment) {
        $parts = explode('.', $segment);
        while (count($parts) < 4) {
            $parts[] = '0';
        }
        return implode('.', $parts);
    }

    // 生成网段内所有IP地址（去除网络地址和广播地址）
    private function generateIpList($segment, $mask) {
        $ips = [];
        $baseIp = $this->formatSegment($segment);
        $base = ip2long($baseIp);
        $maskInt = intval($mask);
        $count = pow(2, 32 - $maskInt);
        for ($i = 1; $i < $count - 1; $i++) { // 跳过网络地址和广播地址
            $ips[] = long2ip($base + $i);
        }
        return $ips;
    }

    /**
     * 异步扫描IP段
     */
    public function pingIpSegment()
    {
        $this->setJsonHeader();

        // 检查会话
        $session = $this->sessionModel->getSession();
        if (!$session) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => '未授权']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $segmentId = $input['segment_id'] ?? null;

        if (!$segmentId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => '缺少 segment_id']);
            exit;
        }

        $segment = $this->ipSegmentModel->getById($segmentId);
        if (!$segment) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'IP段未找到']);
            exit;
        }

        // 立即响应前端，告知任务已开始
        echo json_encode(['success' => true, 'message' => '后台扫描任务已启动']);
        
        // 如果可用，刷新PHP的输出缓冲，让后台继续执行
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        // --- 后台处理开始 ---

        $segmentIdentifier = $segment['segment'] . '/' . $segment['mask'];
        
        // 记录运行日志：开始扫描
        $this->logService->logRuntime(
            'info',
            "开始扫描IP段: {$segmentIdentifier}",
            ['segment_id' => $segmentId, 'status' => 'started']
        );

        $baseIp = $segment['segment'];
        $mask = intval($segment['mask']);
        $ipParts = explode('.', $baseIp);

        if (count($ipParts) === 3 && $mask >= 24 && $mask <= 30) {
            $hostCount = pow(2, 32 - $mask) - 2;
            $successCount = 0;
            $failCount = 0;
            
            for ($i = 1; $i <= $hostCount; $i++) {
                $currentIp = "{$ipParts[0]}.{$ipParts[1]}.{$ipParts[2]}.{$i}";
                
                // 记录运行日志：正在扫描单个IP
                $this->logService->logRuntime(
                    'info',
                    "正在扫描IP: {$currentIp}",
                    ['segment_id' => $segmentId, 'ip' => $currentIp]
                );

                // 执行ping操作
                exec("ping -c 1 -W 1 " . escapeshellarg($currentIp) . " 2>/dev/null", $output, $status);
                $pingResult = ($status === 0) ? '通' : '不通';
                
                if ($status === 0) {
                    $successCount++;
                } else {
                    $failCount++;
                }

                // 更新或创建IP地址记录
                if (method_exists($this->ipAddressModel, 'updateIpStatus')) {
                    $this->ipAddressModel->updateIpStatus($segmentId, $currentIp, $pingResult);
                }
                
                // 清理输出缓冲
                $output = [];
            }
            
            // 记录运行日志：扫描完成，包含统计信息
            $this->logService->logRuntime(
                'info',
                "IP段扫描完成: {$segmentIdentifier}，成功: {$successCount}，失败: {$failCount}",
                [
                    'segment_id' => $segmentId, 
                    'status' => 'completed',
                    'total' => $hostCount,
                    'success' => $successCount,
                    'failed' => $failCount
                ]
            );
        } else {
            // 记录运行日志：不支持的IP段格式
            $this->logService->logRuntime(
                'warning',
                "IP段格式不支持扫描: {$segmentIdentifier}",
                ['segment_id' => $segmentId, 'status' => 'unsupported']
            );
        }
    }

    private function setJsonHeader()
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
    }
}
?>
