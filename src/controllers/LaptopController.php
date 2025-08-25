<?php
require_once __DIR__ . '/../models/LaptopModel.php';
require_once __DIR__ . '/../services/LogService.php';

class LaptopController
{
    private $model;
    private $logService;

    public function __construct()
    {
        $this->model = new LaptopModel();
        $this->logService = new LogService();
    }
    public function getList()
    {
        $query = $_GET;
        $data = $this->model->getList($query);
        // 修复多次输出问题，只输出一次JSON
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['code' => 0, 'data' => $data]);
        exit;
    }

    public function create()
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $result = $this->model->create($input);
        
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        if ($result) {
            // 记录笔记本创建日志
            $laptopName = $input['model'] ?? $input['brand'] ?? $input['asset_number'] ?? 'Unknown Laptop';
            $this->logService->logOperation('create_laptop', 'laptop', "创建笔记本: {$laptopName}", $input);
        }
        
        echo json_encode(['code' => $result ? 0 : 1]);
        exit;
    }

    public function update($id)
    {
        // 强制用 $_POST，如果为空再用 JSON
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
        
        if ($result && $originalData) {
            // 记录笔记本修改日志
            $laptopName = $originalData['model'] ?? $originalData['brand'] ?? $originalData['asset_number'] ?? "ID:{$id}";
            $changes = [];
            foreach ($input as $key => $value) {
                $oldValue = $originalData[$key] ?? '';
                if ($oldValue != $value) {
                    $changes[$key] = ['from' => $oldValue, 'to' => $value];
                }
            }
            if (!empty($changes)) {
                $this->logService->logOperation('update_laptop', 'laptop', "修改笔记本: {$laptopName}", [
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
        // 获取要删除的笔记本信息用于日志记录
        $laptopData = $this->model->getById($id);
        
        $result = $this->model->delete($id);
        
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        if ($result && $laptopData) {
            // 记录笔记本删除日志
            $laptopName = $laptopData['model'] ?? $laptopData['brand'] ?? $laptopData['asset_number'] ?? "ID:{$id}";
            $this->logService->logOperation('delete_laptop', 'laptop', "删除笔记本: {$laptopName}", [
                'id' => $id,
                'deleted_data' => $laptopData
            ]);
        }
        
        echo json_encode(['code' => $result ? 0 : 1]);
        exit;
    }

    public function batchDelete()
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $ids = isset($input['ids']) ? $input['ids'] : [];
        
        // 获取要删除的笔记本信息用于日志记录
        $deletedLaptops = [];
        foreach ($ids as $id) {
            $laptopData = $this->model->getById($id);
            if ($laptopData) {
                $deletedLaptops[] = $laptopData;
            }
        }
        
        $result = $this->model->batchDelete($ids);
        
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        if ($result && !empty($deletedLaptops)) {
            // 记录笔记本批量删除日志
            $laptopNames = array_map(function($laptop) {
                return $laptop['model'] ?? $laptop['brand'] ?? $laptop['asset_number'] ?? "ID:{$laptop['id']}";
            }, $deletedLaptops);
            
            $this->logService->logOperation('batch_delete_laptop', 'laptop', 
                "批量删除笔记本: " . implode(', ', $laptopNames) . " (共" . count($deletedLaptops) . "台)", [
                'ids' => $ids,
                'count' => count($deletedLaptops),
                'deleted_laptops' => $deletedLaptops
            ]);
        }
        
        echo json_encode(['code' => $result ? 0 : 1]);
        exit;
    }
}
