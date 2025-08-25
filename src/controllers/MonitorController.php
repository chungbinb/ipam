<?php
require_once __DIR__ . '/../models/MonitorModel.php';
require_once __DIR__ . '/../services/LogService.php';

class MonitorController
{
    private $model;
    private $logService;

    public function __construct()
    {
        $this->model = new MonitorModel();
        $this->logService = new LogService();
    }
    public function getList()
    {
        $query = $_GET;
        $data = $this->model->getList($query);
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['code' => 0, 'data' => $data]);
        exit;
    }

    public function create()
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $result = $this->model->create($input);
            
            if (!headers_sent()) header('Content-Type: application/json');
            
            if (is_array($result) && isset($result['error'])) {
                echo json_encode(['code' => 1, 'msg' => $result['error']]);
            } else {
                if ($result) {
                    // 记录显示器创建日志
                    $monitorName = $input['model'] ?? $input['brand'] ?? 'Unknown Monitor';
                    $this->logService->logOperation('create_monitor', 'monitor', "创建显示器: {$monitorName}", $input);
                }
                echo json_encode(['code' => $result ? 0 : 1]);
            }
        } catch (\Throwable $e) {
            if (!headers_sent()) header('Content-Type: application/json');
            echo json_encode(['code' => 1, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    public function update($id)
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        // 获取原始数据用于日志记录
        $originalData = $this->model->getById($id);
        
        $result = $this->model->update($id, $input);
        
        if (!headers_sent()) header('Content-Type: application/json');
        
        if (is_array($result) && isset($result['error'])) {
            echo json_encode(['code' => 1, 'msg' => $result['error']]);
        } else {
            if ($result && $originalData) {
                // 记录显示器修改日志
                $monitorName = $originalData['model'] ?? $originalData['brand'] ?? "ID:{$id}";
                $changes = [];
                foreach ($input as $key => $value) {
                    $oldValue = $originalData[$key] ?? '';
                    if ($oldValue != $value) {
                        $changes[$key] = ['from' => $oldValue, 'to' => $value];
                    }
                }
                if (!empty($changes)) {
                    $this->logService->logOperation('update_monitor', 'monitor', "修改显示器: {$monitorName}", [
                        'id' => $id,
                        'changes' => $changes
                    ]);
                }
            }
            echo json_encode(['code' => $result ? 0 : 1]);
        }
        exit;
    }

    public function delete($id)
    {
        // 获取待删除的显示器信息用于日志记录
        $monitorData = $this->model->getById($id);
        
        $ok = $this->model->delete($id);
        
        if (!headers_sent()) header('Content-Type: application/json');
        
        if ($ok && $monitorData) {
            // 记录显示器删除日志
            $monitorName = $monitorData['model'] ?? $monitorData['brand'] ?? "ID:{$id}";
            $this->logService->logOperation('delete_monitor', 'monitor', "删除显示器: {$monitorName}", [
                'id' => $id,
                'deleted_data' => $monitorData
            ]);
        }
        
        echo json_encode(['code' => $ok ? 0 : 1]);
        exit;
    }

    public function batchDelete()
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $ids = isset($input['ids']) ? $input['ids'] : [];
        
        // 获取待删除的显示器信息用于日志记录
        $deletedMonitors = [];
        foreach ($ids as $id) {
            $monitorData = $this->model->getById($id);
            if ($monitorData) {
                $deletedMonitors[] = $monitorData;
            }
        }
        
        $ok = $this->model->batchDelete($ids);
        
        if (!headers_sent()) header('Content-Type: application/json');
        
        if ($ok && !empty($deletedMonitors)) {
            // 记录批量删除显示器日志
            $monitorNames = array_map(function($monitor) {
                return $monitor['model'] ?? $monitor['brand'] ?? "ID:{$monitor['id']}";
            }, $deletedMonitors);
            
            $this->logService->logOperation('batch_delete_monitor', 'monitor', 
                "批量删除显示器: " . implode(', ', $monitorNames), [
                'ids' => $ids,
                'count' => count($deletedMonitors),
                'deleted_monitors' => $deletedMonitors
            ]);
        }
        
        echo json_encode(['code' => $ok ? 0 : 1]);
        exit;
    }
}
?>
