<?php
require_once __DIR__ . '/../models/BrandModel.php';
require_once __DIR__ . '/../services/LogService.php';

class BrandController
{
    private $model;
    private $logService;

    public function __construct()
    {
        $this->model = new BrandModel();
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
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $result = $this->model->create($input);
        
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        if ($result) {
            // 记录品牌创建日志
            $brandName = $input['name'] ?? 'Unknown Brand';
            $this->logService->logOperation('create_brand', 'brand', "创建品牌: {$brandName}", $input);
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
        
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        if ($result && $originalData) {
            // 记录品牌修改日志
            $brandName = $originalData['name'] ?? "ID:{$id}";
            $changes = [];
            foreach ($input as $key => $value) {
                $oldValue = $originalData[$key] ?? '';
                if ($oldValue != $value) {
                    $changes[$key] = ['from' => $oldValue, 'to' => $value];
                }
            }
            if (!empty($changes)) {
                $this->logService->logOperation('update_brand', 'brand', "修改品牌: {$brandName}", [
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
        // 获取要删除的品牌信息用于日志记录
        $brandData = $this->model->getById($id);
        
        $result = $this->model->delete($id);
        
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        if ($result && $brandData) {
            // 记录品牌删除日志
            $brandName = $brandData['name'] ?? "ID:{$id}";
            $this->logService->logOperation('delete_brand', 'brand', "删除品牌: {$brandName}", [
                'id' => $id,
                'deleted_data' => $brandData
            ]);
        }
        
        echo json_encode(['code' => $result ? 0 : 1]);
        exit;
    }

    public function batchDelete()
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $ids = isset($input['ids']) ? $input['ids'] : [];
        
        // 获取要删除的品牌信息用于日志记录
        $deletedBrands = [];
        foreach ($ids as $id) {
            $brandData = $this->model->getById($id);
            if ($brandData) {
                $deletedBrands[] = $brandData;
            }
        }
        
        $result = $this->model->batchDelete($ids);
        
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        if ($result && !empty($deletedBrands)) {
            // 记录品牌批量删除日志
            $brandNames = array_map(function($brand) {
                return $brand['name'] ?? "ID:{$brand['id']}";
            }, $deletedBrands);
            
            $this->logService->logOperation('batch_delete_brand', 'brand', 
                "批量删除品牌: " . implode(', ', $brandNames) . " (共" . count($deletedBrands) . "个)", [
                'ids' => $ids,
                'count' => count($deletedBrands),
                'deleted_brands' => $deletedBrands
            ]);
        }
        
        echo json_encode(['code' => $result ? 0 : 1]);
        exit;
    }
}
?>
