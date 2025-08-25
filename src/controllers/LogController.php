<?php

require_once __DIR__ . '/../models/LogModel.php';
require_once __DIR__ . '/../models/SessionModel.php';
require_once __DIR__ . '/../services/LogService.php';

class LogController
{
    private $logModel;
    private $sessionModel;
    private $logService;

    public function __construct()
    {
        $this->logModel = new LogModel();
        $this->sessionModel = new SessionModel();
        $this->logService = new LogService();
    }

    public function getList()
    {
        $this->setJsonHeader();
        
        // 检查用户是否已登录
        $session = $this->sessionModel->getSession();
        if (!$session) {
            http_response_code(401);
            echo json_encode(['code' => 1, 'msg' => '未登录']);
            exit;
        }

        $query = $_GET;
        $data = $this->logModel->getList($query);
        echo json_encode(['code' => 0, 'data' => $data]);
        exit;
    }

    /**
     * 清理旧日志
     */
    public function cleanup()
    {
        $this->setJsonHeader();
        
        // 检查用户权限（只有管理员以上权限可以清理日志，网管不能清理）
        $session = $this->sessionModel->getSession();
        if (!$session || !in_array($session['role'], ['sysadmin', 'admin'])) {
            http_response_code(403);
            echo json_encode(['code' => 1, 'msg' => '权限不足，只有管理员以上权限才能清理日志']);
            exit;
        }

        // 清理30天前的日志
        $days = $_GET['days'] ?? 30;
        $count = $this->logModel->cleanup($days);
        
        // 记录清理操作日志
        $this->logService->logOperation('cleanup_logs', 'logs', "清理了 {$count} 条 {$days} 天前的日志", [
            'days' => $days,
            'count' => $count
        ]);

        echo json_encode(['code' => 0, 'msg' => "成功清理 {$count} 条日志"]);
        exit;
    }

    /**
     * 获取日志统计信息
     */
    public function statistics()
    {
        $this->setJsonHeader();
        
        $session = $this->sessionModel->getSession();
        if (!$session) {
            http_response_code(401);
            echo json_encode(['code' => 1, 'msg' => '未登录']);
            exit;
        }

        $stats = $this->logModel->getStatistics();
        echo json_encode(['code' => 0, 'data' => $stats]);
        exit;
    }

    private function setJsonHeader()
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
    }
}