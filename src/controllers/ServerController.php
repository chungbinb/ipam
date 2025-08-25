<?php
require_once __DIR__ . '/../models/ServerModel.php';
require_once __DIR__ . '/../services/LogService.php';

class ServerController
{
    private $model;
    private $logService;

    public function __construct()
    {
        $this->model = new ServerModel();
        $this->logService = new LogService();
    }

    public function getList()
    {
        try {
            $query = $_GET;
            $data = $this->model->getList($query);
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['code' => 0, 'data' => $data]);
        } catch (\Throwable $e) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['code' => 1, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    public function create()
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }
            
            $result = $this->model->create($input);
            
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            
            if (is_array($result) && isset($result['error'])) {
                echo json_encode(['code' => 1, 'msg' => $result['error']]);
            } elseif ($result === true) {
                // 记录服务器创建日志
                $serverName = $input['hostname'] ?? $input['ip'] ?? 'Unknown Server';
                $this->logService->logOperation('create_server', 'server', "创建服务器: {$serverName}", $input);
                echo json_encode(['code' => 0]);
            } else {
                echo json_encode(['code' => 1, 'msg' => '创建失败']);
            }
        } catch (\Throwable $e) {
            error_log('ServerController create exception: ' . $e->getMessage());
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['code' => 1, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    public function update($id)
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
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
                    // 记录服务器修改日志
                    $serverName = $originalData['hostname'] ?? $originalData['ip'] ?? "ID:{$id}";
                    $changes = [];
                    foreach ($input as $key => $value) {
                        $oldValue = $originalData[$key] ?? '';
                        if ($oldValue != $value) {
                            $changes[$key] = ['from' => $oldValue, 'to' => $value];
                        }
                    }
                    if (!empty($changes)) {
                        $this->logService->logOperation('update_server', 'server', "修改服务器: {$serverName}", [
                            'id' => $id,
                            'changes' => $changes
                        ]);
                    }
                }
                echo json_encode(['code' => $result ? 0 : 1]);
            }
        } catch (\Throwable $e) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['code' => 1, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    public function delete($id)
    {
        try {
            // 获取要删除的服务器信息用于日志记录
            $serverData = $this->model->getById($id);
            
            $result = $this->model->delete($id);
            
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            
            if ($result && $serverData) {
                // 记录服务器删除日志
                $serverName = $serverData['hostname'] ?? $serverData['ip'] ?? "ID:{$id}";
                $this->logService->logOperation('delete_server', 'server', "删除服务器: {$serverName}", [
                    'id' => $id,
                    'deleted_data' => $serverData
                ]);
            }
            
            echo json_encode(['code' => $result ? 0 : 1]);
        } catch (\Throwable $e) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['code' => 1, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    public function batchDelete()
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }
            $ids = isset($input['ids']) ? $input['ids'] : [];
            
            // 获取要删除的服务器信息用于日志记录
            $deletedServers = [];
            foreach ($ids as $id) {
                $serverData = $this->model->getById($id);
                if ($serverData) {
                    $deletedServers[] = $serverData;
                }
            }
            
            $result = $this->model->batchDelete($ids);
            
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            
            if ($result && !empty($deletedServers)) {
                // 记录服务器批量删除日志
                $serverNames = array_map(function($server) {
                    return $server['hostname'] ?? $server['ip'] ?? "ID:{$server['id']}";
                }, $deletedServers);
                
                $this->logService->logOperation('batch_delete_server', 'server', 
                    "批量删除服务器: " . implode(', ', $serverNames) . " (共" . count($deletedServers) . "台)", [
                    'ids' => $ids,
                    'count' => count($deletedServers),
                    'deleted_servers' => $deletedServers
                ]);
            }
            
            echo json_encode(['code' => $result ? 0 : 1]);
        } catch (\Throwable $e) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['code' => 1, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    public function export()
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }
            $ids = isset($input['ids']) ? $input['ids'] : [];
            
            if (!is_array($ids) || empty($ids)) {
                http_response_code(400);
                echo 'No data';
                exit;
            }
            
            $data = $this->model->getByIds($ids);
            
            // 记录服务器导出日志
            $this->logService->logOperation('export_server', 'server', 
                "导出服务器数据 (共" . count($data) . "条记录)", [
                'ids' => $ids,
                'count' => count($data)
            ]);
            
            // 生成xlsx
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $header = ['主机名', 'IP地址', '操作系统', '类型', '型号', '规格', '购买日期', '购买价格', '使用业务', '状态', '负责人', '开放端口', '备注'];
            $sheet->fromArray($header, null, 'A1');
            
            $row = 2;
            foreach ($data as $item) {
                $sheet->fromArray([
                    $item['hostname'],
                    $item['ip'],
                    $item['os'],
                    $item['type'],
                    $item['model'],
                    $item['spec'],
                    $item['buy_date'],
                    $item['buy_price'],
                    $item['business'],
                    $item['status'],
                    $item['owner'],
                    $item['ports'],
                    $item['remark']
                ], null, 'A' . $row++);
            }
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="server_export.xlsx"');
            header('Cache-Control: max-age=0');
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        } catch (\Throwable $e) {
            http_response_code(500);
            echo '导出失败: ' . $e->getMessage();
        }
        exit;
    }

    public function batchImport()
    {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $list = isset($input['list']) ? $input['list'] : [];
        $success = 0;
        $fail = 0;
        
        foreach ($list as $item) {
            if ($this->model->create($item)) {
                $success++;
            } else {
                $fail++;
            }
        }
        
        // 记录服务器批量导入日志
        $this->logService->logOperation('batch_import_server', 'server', 
            "批量导入服务器数据: 成功{$success}条, 失败{$fail}条", [
            'total' => count($list),
            'success' => $success,
            'fail' => $fail
        ]);
        
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['code' => 0, 'success' => $success, 'fail' => $fail]);
        exit;
    }

    /**
     * 连接服务器（模拟功能）
     */
    public function connect($id)
    {
        try {
            $serverData = $this->model->getById($id);
            
            if (!$serverData) {
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                }
                echo json_encode(['code' => 1, 'msg' => '服务器不存在']);
                exit;
            }
            
            // 记录连接操作日志
            $serverName = $serverData['hostname'] ?? $serverData['ip'] ?? "ID:{$id}";
            $this->logService->logOperation('connect_server', 'server', "连接服务器: {$serverName}", [
                'id' => $id,
                'server_info' => $serverData
            ]);
            
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['code' => 0, 'msg' => '连接操作已记录']);
        } catch (\Throwable $e) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['code' => 1, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * 端口扫描（模拟功能）
     */
    public function scanPorts($id)
    {
        try {
            $serverData = $this->model->getById($id);
            
            if (!$serverData) {
                if (!headers_sent()) {
                    header('Content-Type: application/json');
                }
                echo json_encode(['code' => 1, 'msg' => '服务器不存在']);
                exit;
            }
            
            // 记录端口扫描操作日志
            $serverName = $serverData['hostname'] ?? $serverData['ip'] ?? "ID:{$id}";
            $this->logService->logOperation('scan_ports_server', 'server', "端口扫描: {$serverName}", [
                'id' => $id,
                'server_info' => $serverData
            ]);
            
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['code' => 0, 'msg' => '端口扫描操作已记录']);
        } catch (\Throwable $e) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['code' => 1, 'msg' => $e->getMessage()]);
        }
        exit;
    }
}
