<?php
require_once __DIR__ . '/../models/DepartmentModel.php';
require_once __DIR__ . '/../services/LogService.php';

class DepartmentController
{
    private $model;
    private $logService;

    public function __construct()
    {
        $this->model = new DepartmentModel();
        $this->logService = new LogService();
    }

    public function getList()
    {
        $data = $this->model->getList();
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
            // 记录部门创建日志
            $departmentName = $input['name'] ?? 'Unknown Department';
            $this->logService->logOperation('create_department', 'department', "创建部门: {$departmentName}", $input);
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
            // 记录部门修改日志
            $departmentName = $originalData['name'] ?? "ID:{$id}";
            $changes = [];
            foreach ($input as $key => $value) {
                $oldValue = $originalData[$key] ?? '';
                if ($oldValue != $value) {
                    $changes[$key] = ['from' => $oldValue, 'to' => $value];
                }
            }
            if (!empty($changes)) {
                $this->logService->logOperation('update_department', 'department', "修改部门: {$departmentName}", [
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
        // 获取要删除的部门信息用于日志记录
        $departmentData = $this->model->getById($id);
        
        $result = $this->model->delete($id);
        
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        if ($result && $departmentData) {
            // 记录部门删除日志
            $departmentName = $departmentData['name'] ?? "ID:{$id}";
            $this->logService->logOperation('delete_department', 'department', "删除部门: {$departmentName}", [
                'id' => $id,
                'deleted_data' => $departmentData
            ]);
        }
        
        echo json_encode(['code' => $result ? 0 : 1]);
        exit;
    }

    public function batchDelete()
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $ids = isset($input['ids']) ? $input['ids'] : [];
        
        // 获取要删除的部门信息用于日志记录
        $deletedDepartments = [];
        foreach ($ids as $id) {
            $departmentData = $this->model->getById($id);
            if ($departmentData) {
                $deletedDepartments[] = $departmentData;
            }
        }
        
        $result = $this->model->batchDelete($ids);
        
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        if ($result && !empty($deletedDepartments)) {
            // 记录部门批量删除日志
            $departmentNames = array_map(function($dept) {
                return $dept['name'] ?? "ID:{$dept['id']}";
            }, $deletedDepartments);
            
            $this->logService->logOperation('batch_delete_department', 'department', 
                "批量删除部门: " . implode(', ', $departmentNames) . " (共" . count($deletedDepartments) . "个)", [
                'ids' => $ids,
                'count' => count($deletedDepartments),
                'deleted_departments' => $deletedDepartments
            ]);
        }
        
        echo json_encode(['code' => $result ? 0 : 1]);
        exit;
    }
}
?>
