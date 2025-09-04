<?php
require_once __DIR__ . '/../models/HostModel.php';
require_once __DIR__ . '/../services/LogService.php';

class HostController
{
    private $model;
    private $logService;

    public function __construct()
    {
        $this->model = new HostModel();
        $this->logService = new LogService();
    }
    public function getList()
    {
        $query = $_GET;
        $data = $this->model->getList($query);
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['code' => 0, 'data' => $data]);
        exit;
    }

    public function create()
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            
            // 基本数据验证
            if (empty($input['department']) || empty($input['user'])) {
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                }
                echo json_encode(['code' => 1, 'msg' => '部门和使用人员为必填项']);
                exit;
            }
            
            $result = $this->model->create($input);
            
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            
            if (is_array($result) && isset($result['error'])) {
                echo json_encode(['code' => 1, 'msg' => $result['error']]);
            } else {
                if ($result) {
                    // 记录主机创建日志 - 使用正确的字段名
                    $hostName = ($input['department'] ?? '') . '-' . ($input['user'] ?? '') . 
                               (empty($input['ip']) ? '' : '(' . $input['ip'] . ')') ?: 'Unknown';
                    try {
                        $this->logService->logOperation('create_host', 'host', "创建主机: {$hostName}", $input);
                    } catch (\Throwable $logError) {
                        error_log('Host create log failed: ' . $logError->getMessage());
                    }
                }
                echo json_encode(['code' => $result ? 0 : 1, 'msg' => $result ? '创建成功' : '创建失败']);
            }
        } catch (\Throwable $e) {
            error_log('HostController create error: ' . $e->getMessage());
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['code' => 1, 'msg' => '服务器内部错误: ' . $e->getMessage()]);
        }
        exit;
    }

    public function update($id)
    {
        try {
            $input = $_POST;
            if (empty($input)) {
                $input = json_decode(file_get_contents('php://input'), true) ?: [];
            }
            
            // 获取原始数据用于日志记录
            $originalData = $this->model->getById($id);
            
            $result = $this->model->update($id, $input);
            
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            
            if (is_array($result) && isset($result['error'])) {
                echo json_encode(['code' => 1, 'msg' => $result['error']]);
            } else {
                if ($result && $originalData) {
                    // 记录主机修改日志 - 使用正确的字段名
                    $hostName = ($originalData['department'] ?? '') . '-' . ($originalData['user'] ?? '') . 
                               (empty($originalData['ip']) ? '' : '(' . $originalData['ip'] . ')') ?: "ID:{$id}";
                    
                    $changes = [];
                    foreach ($input as $key => $value) {
                        $oldValue = $originalData[$key] ?? '';
                        // 确保比较时都转换为字符串
                        if (strval($oldValue) !== strval($value)) {
                            $changes[$key] = ['from' => $oldValue, 'to' => $value];
                        }
                    }
                    
                    // 无论是否有变更都记录操作日志
                    try {
                        $logMessage = empty($changes) ? 
                            "访问主机编辑: {$hostName}" : 
                            "修改主机: {$hostName}";
                            
                        $this->logService->logOperation(
                            'update_host', 
                            'host', 
                            $logMessage, 
                            [
                                'id' => $id,
                                'changes' => $changes,
                                'input_data' => $input,
                                'has_changes' => !empty($changes)
                            ]
                        );
                    } catch (\Throwable $logError) {
                        // 日志记录失败不应该影响主功能
                        error_log('Host update log failed: ' . $logError->getMessage());
                    }
                }
                echo json_encode(['code' => $result ? 0 : 1]);
            }
        } catch (\Throwable $e) {
            // 记录错误日志
            error_log('HostController update error: ' . $e->getMessage());
            error_log('HostController update stack trace: ' . $e->getTraceAsString());
            
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['code' => 1, 'msg' => '更新失败: ' . $e->getMessage()]);
        }
        exit;
    }

    public function delete($id)
    {
        // 获取待删除的主机信息用于日志记录
        $hostData = $this->model->getById($id);
        
        $result = $this->model->delete($id);
        
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        if ($result && $hostData) {
            // 记录主机删除日志 - 使用正确的字段名
            $hostName = ($hostData['department'] ?? '') . '-' . ($hostData['user'] ?? '') . 
                       (empty($hostData['ip']) ? '' : '(' . $hostData['ip'] . ')') ?: "ID:{$id}";
            try {
                $this->logService->logOperation('delete_host', 'host', "删除主机: {$hostName}", [
                    'id' => $id,
                    'deleted_data' => $hostData
                ]);
            } catch (\Throwable $logError) {
                error_log('Host delete log failed: ' . $logError->getMessage());
            }
        }
        
        echo json_encode(['code' => $result ? 0 : 1]);
        exit;
    }

    public function batchDelete()
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $ids = isset($input['ids']) ? $input['ids'] : [];
        
        // 获取待删除的主机信息用于日志记录
        $deletedHosts = [];
        foreach ($ids as $id) {
            $hostData = $this->model->getById($id);
            if ($hostData) {
                $deletedHosts[] = $hostData;
            }
        }
        
        $result = $this->model->batchDelete($ids);
        
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        if ($result && !empty($deletedHosts)) {
            // 记录批量删除主机日志 - 使用正确的字段名
            $hostNames = array_map(function($host) {
                return ($host['department'] ?? '') . '-' . ($host['user'] ?? '') . 
                       (empty($host['ip']) ? '' : '(' . $host['ip'] . ')') ?: "ID:{$host['id']}";
            }, $deletedHosts);
            
            try {
                $this->logService->logOperation('batch_delete_host', 'host', 
                    "批量删除主机: " . implode(', ', $hostNames), [
                    'ids' => $ids,
                    'count' => count($deletedHosts),
                    'deleted_hosts' => $deletedHosts
                ]);
            } catch (\Throwable $logError) {
                error_log('Host batch delete log failed: ' . $logError->getMessage());
            }
        }
        
        echo json_encode(['code' => $result ? 0 : 1]);
        exit;
    }
}
