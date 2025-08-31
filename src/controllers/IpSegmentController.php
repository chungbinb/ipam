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
            
            // 生成所有需要扫描的IP地址
            $ipList = [];
            for ($i = 1; $i <= $hostCount; $i++) {
                $ipList[] = "{$ipParts[0]}.{$ipParts[1]}.{$ipParts[2]}.{$i}";
            }
            
            // 记录运行日志：开始批量扫描
            $this->logService->logRuntime(
                'info',
                "开始批量扫描IP段: {$segmentIdentifier}，共{$hostCount}个IP",
                ['segment_id' => $segmentId, 'total_count' => $hostCount, 'action' => 'batch_ping_start']
            );
            
            // 使用并行ping扫描
            $pingResults = $this->parallelPing($ipList);
            
            // 处理扫描结果
            foreach ($pingResults as $ip => $isSuccess) {
                $pingResult = $isSuccess ? '通' : '不通';
                $pingStatus = $isSuccess ? 'success' : 'failed';
                
                if ($isSuccess) {
                    $successCount++;
                } else {
                    $failCount++;
                }

                // 记录运行日志：扫描结果
                $this->logService->logRuntime(
                    $isSuccess ? 'info' : 'warning',
                    "扫描IP结果: {$ip} - {$pingResult}",
                    [
                        'segment_id' => $segmentId, 
                        'ip' => $ip, 
                        'result' => $pingResult,
                        'status' => $pingStatus,
                        'action' => 'ping_result'
                    ]
                );

                // 更新或创建IP地址记录
                if (method_exists($this->ipAddressModel, 'updateIpStatus')) {
                    $this->ipAddressModel->updateIpStatus($segmentId, $ip, $pingResult);
                }
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

    /**
     * 并行ping多个IP地址，大幅提升扫描速度
     * @param array $ipList IP地址列表
     * @return array 返回IP => 是否ping通的关联数组
     */
    private function parallelPing(array $ipList)
    {
        $results = [];
        
        // 方法1: 尝试使用fping（更高效）
        if ($this->isFpingAvailable()) {
            return $this->fpingMethod($ipList);
        }
        
        // 方法2: 使用优化的并行ping
        return $this->optimizedParallelPing($ipList);
    }
    
    /**
     * 检查系统是否安装了fping
     */
    private function isFpingAvailable()
    {
        exec('which fping 2>/dev/null', $output, $returnCode);
        return $returnCode === 0;
    }
    
    /**
     * 使用fping进行批量ping（最快的方法）
     */
    private function fpingMethod(array $ipList)
    {
        $results = [];
        
        // 将IP列表写入临时文件
        $tempFile = tempnam(sys_get_temp_dir(), 'ping_ips_');
        file_put_contents($tempFile, implode("\n", $ipList));
        
        // 使用fping批量ping，超时0.5秒
        $cmd = "fping -t 500 -f " . escapeshellarg($tempFile) . " 2>&1";
        exec($cmd, $output, $returnCode);
        
        // 解析fping输出
        foreach ($output as $line) {
            if (preg_match('/^(\d+\.\d+\.\d+\.\d+)\s+is\s+(alive|unreachable)/', $line, $matches)) {
                $ip = $matches[1];
                $status = $matches[2];
                $results[$ip] = ($status === 'alive');
            }
        }
        
        // 对于没有结果的IP，标记为不通
        foreach ($ipList as $ip) {
            if (!isset($results[$ip])) {
                $results[$ip] = false;
            }
        }
        
        // 清理临时文件
        unlink($tempFile);
        
        return $results;
    }
    
    /**
     * 优化的并行ping实现
     */
    private function optimizedParallelPing(array $ipList)
    {
        $results = [];
        $batchSize = 30; // 每批处理30个IP
        $batches = array_chunk($ipList, $batchSize);
        
        foreach ($batches as $batch) {
            $descriptorspec = [
                0 => ['pipe', 'r'],  // stdin
                1 => ['pipe', 'w'],  // stdout
                2 => ['pipe', 'w']   // stderr
            ];
            
            $processes = [];
            $pipes = [];
            
            // 启动并行ping进程，使用更短的超时时间
            foreach ($batch as $ip) {
                // 使用更激进的ping参数：1次ping，0.3秒超时
                if (stripos(PHP_OS, 'WIN') === 0) {
                    $cmd = "ping -n 1 -w 300 " . escapeshellarg($ip);
                } else {
                    $cmd = "ping -c 1 -W 0.3 -i 0.1 " . escapeshellarg($ip);
                }
                
                $process = proc_open($cmd, $descriptorspec, $pipes[$ip]);
                
                if (is_resource($process)) {
                    $processes[$ip] = $process;
                    // 关闭stdin
                    fclose($pipes[$ip][0]);
                }
            }
            
            // 设置非阻塞模式并等待所有进程完成
            $timeout = time() + 2; // 最多等待2秒
            while (!empty($processes) && time() < $timeout) {
                foreach ($processes as $ip => $process) {
                    $status = proc_get_status($process);
                    if (!$status['running']) {
                        // 进程已完成
                        $output = stream_get_contents($pipes[$ip][1]);
                        fclose($pipes[$ip][1]);
                        fclose($pipes[$ip][2]);
                        
                        $exitCode = proc_close($process);
                        $results[$ip] = ($exitCode === 0);
                        
                        unset($processes[$ip]);
                        unset($pipes[$ip]);
                    }
                }
                
                if (!empty($processes)) {
                    usleep(10000); // 等待10ms
                }
            }
            
            // 清理剩余的进程
            foreach ($processes as $ip => $process) {
                proc_terminate($process);
                fclose($pipes[$ip][1]);
                fclose($pipes[$ip][2]);
                proc_close($process);
                $results[$ip] = false; // 超时的IP标记为不通
            }
        }
        
        return $results;
    }
}
?>
