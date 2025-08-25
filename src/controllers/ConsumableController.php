<?php
require_once __DIR__ . '/../models/ConsumableModel.php';
require_once __DIR__ . '/../services/LogService.php';

class ConsumableController
{
    private $model;
    private $logService;

    public function __construct()
    {
        $this->model = new ConsumableModel();
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
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $result = $this->model->create($input);
        
        if (!headers_sent()) header('Content-Type: application/json');
        
        if ($result) {
            // 记录耗材创建日志
            $consumableName = $input['name'] ?? $input['model'] ?? $input['type'] ?? 'Unknown Consumable';
            $this->logService->logOperation('create_consumable', 'consumable', "创建耗材: {$consumableName}", $input);
        }
        
        echo json_encode(['code' => $result ? 0 : 1]);
        exit;
    }

    public function update($id)
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        
        // 获取原始数据用于日志记录
        $originalData = $this->model->getById($id);
        
        $result = $this->model->update($id, $input);
        
        if (!headers_sent()) header('Content-Type: application/json');
        
        if ($result && $originalData) {
            // 记录耗材修改日志
            $consumableName = $originalData['name'] ?? $originalData['model'] ?? $originalData['type'] ?? "ID:{$id}";
            $changes = [];
            foreach ($input as $key => $value) {
                $oldValue = $originalData[$key] ?? '';
                if ($oldValue != $value) {
                    $changes[$key] = ['from' => $oldValue, 'to' => $value];
                }
            }
            if (!empty($changes)) {
                $this->logService->logOperation('update_consumable', 'consumable', "修改耗材: {$consumableName}", [
                    'id' => $id,
                    'changes' => $changes
                ]);
            }
        }
        
        echo json_encode(['code' => $result ? 0 : 1]);
        exit;
    }

    public function delete($id)
    {
        // 获取要删除的耗材信息用于日志记录
        $consumableData = $this->model->getById($id);
        
        $result = $this->model->delete($id);
        
        if (!headers_sent()) header('Content-Type: application/json');
        
        if ($result && $consumableData) {
            // 记录耗材删除日志
            $consumableName = $consumableData['name'] ?? $consumableData['model'] ?? $consumableData['type'] ?? "ID:{$id}";
            $this->logService->logOperation('delete_consumable', 'consumable', "删除耗材: {$consumableName}", [
                'id' => $id,
                'deleted_data' => $consumableData
            ]);
        }
        
        echo json_encode(['code' => $result ? 0 : 1]);
        exit;
    }

    public function in($id)
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $count = isset($input['in_count']) ? (int)$input['in_count'] : 0;
        $remark = isset($input['remark']) ? trim($input['remark']) : '';
        
        // 获取耗材信息用于日志记录
        $consumableData = $this->model->getById($id);
        
        $ret = $this->model->inStock($id, $count, $remark);
        
        if (!headers_sent()) header('Content-Type: application/json');
        
        if (is_array($ret) && isset($ret['error'])) {
            echo json_encode(['code' => 1, 'msg' => $ret['error']]);
        } else {
            if ($ret && $consumableData) {
                // 记录耗材入库日志
                $consumableName = $consumableData['name'] ?? $consumableData['model'] ?? $consumableData['type'] ?? "ID:{$id}";
                $this->logService->logOperation('consumable_in_stock', 'consumable', 
                    "耗材入库: {$consumableName} 数量: {$count}" . ($remark ? " 备注: {$remark}" : ""), [
                    'id' => $id,
                    'in_count' => $count,
                    'remark' => $remark,
                    'consumable_info' => $consumableData
                ]);
            }
            echo json_encode(['code' => $ret ? 0 : 1]);
        }
        exit;
    }

    public function out($id)
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $count = isset($input['out_count']) ? (int)$input['out_count'] : 0;
        $remark = isset($input['remark']) ? trim($input['remark']) : '';
        
        // 获取耗材信息用于日志记录
        $consumableData = $this->model->getById($id);
        
        $ret = $this->model->outStock($id, $count, $remark);
        
        if (!headers_sent()) header('Content-Type: application/json');
        
        if (is_array($ret) && isset($ret['error'])) {
            echo json_encode(['code' => 1, 'msg' => $ret['error']]);
        } else {
            if ($ret && $consumableData) {
                // 记录耗材出库日志
                $consumableName = $consumableData['name'] ?? $consumableData['model'] ?? $consumableData['type'] ?? "ID:{$id}";
                $this->logService->logOperation('consumable_out_stock', 'consumable', 
                    "耗材出库: {$consumableName} 数量: {$count}" . ($remark ? " 备注: {$remark}" : ""), [
                    'id' => $id,
                    'out_count' => $count,
                    'remark' => $remark,
                    'consumable_info' => $consumableData
                ]);
            }
            echo json_encode(['code' => $ret ? 0 : 1]);
        }
        exit;
    }

    public function getInDetail()
    {
        $query = $_GET;
        $data = $this->model->getInDetail($query);
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['code' => 0, 'data' => $data]);
        exit;
    }

    public function getOutDetail()
    {
        $query = $_GET;
        $data = $this->model->getOutDetail($query);
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['code' => 0, 'data' => $data]);
        exit;
    }
}
