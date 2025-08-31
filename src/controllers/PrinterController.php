<?php
require_once __DIR__ . '/../models/PrinterModel.php';
require_once __DIR__ . '/../services/LogService.php';

class PrinterController
{
    private $model;
    private $logService;

    public function __construct()
    {
        $this->model = new PrinterModel();
        $this->logService = new LogService();
    }
    public function getList()
    {
        try {
            $query = $_GET;
            $data = $this->model->getList($query);
            if (!headers_sent()) header('Content-Type: application/json');
            echo json_encode(['code' => 0, 'data' => $data]);
        } catch (\Throwable $e) {
            if (!headers_sent()) header('Content-Type: application/json');
            echo json_encode(['code' => 1, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    public function create()
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) $input = $_POST;
            $result = $this->model->create($input);
            
            if (!headers_sent()) header('Content-Type: application/json');
            
            if (is_array($result) && isset($result['error'])) {
                echo json_encode(['code' => 1, 'msg' => $result['error']]);
            } elseif ($result === true) {
                // 记录打印机创建日志
                $printerName = $input['model'] ?? $input['brand'] ?? $input['asset_number'] ?? 'Unknown Printer';
                $this->logService->logOperation('create_printer', 'printer', "创建打印机: {$printerName}", $input);
                echo json_encode(['code' => 0]);
            } else {
                $errorInfo = isset($this->model->pdo) && $this->model->pdo instanceof PDO ? $this->model->pdo->errorInfo() : [];
                echo json_encode(['code' => 1, 'msg' => '数据库插入失败', 'errorInfo' => $errorInfo]);
            }
        } catch (\Throwable $e) {
            error_log('PrinterController create exception: ' . $e->getMessage());
            if (!headers_sent()) header('Content-Type: application/json');
            echo json_encode(['code' => 1, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    public function update($id)
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) $input = $_POST;
            
            // 获取原始数据用于日志记录
            $originalData = $this->model->getById($id);
            
            $result = $this->model->update($id, $input);
            
            if (!headers_sent()) header('Content-Type: application/json');
            
            if (is_array($result) && isset($result['error'])) {
                echo json_encode(['code' => 1, 'msg' => $result['error']]);
            } else {
                if ($result && $originalData) {
                    // 记录打印机修改日志
                    $printerName = $originalData['model'] ?? $originalData['brand'] ?? $originalData['asset_number'] ?? "ID:{$id}";
                    $changes = [];
                    foreach ($input as $key => $value) {
                        $oldValue = $originalData[$key] ?? '';
                        if ($oldValue != $value) {
                            $changes[$key] = ['from' => $oldValue, 'to' => $value];
                        }
                    }
                    if (!empty($changes)) {
                        $this->logService->logOperation('update_printer', 'printer', "修改打印机: {$printerName}", [
                            'id' => $id,
                            'changes' => $changes
                        ]);
                    }
                }
                echo json_encode(['code' => $result ? 0 : 1]);
            }
        } catch (\Throwable $e) {
            if (!headers_sent()) header('Content-Type: application/json');
            echo json_encode(['code' => 1, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    public function delete($id)
    {
        try {
            // 获取要删除的打印机信息用于日志记录
            $printerData = $this->model->getById($id);
            
            $result = $this->model->delete($id);
            
            if (!headers_sent()) header('Content-Type: application/json');
            
            if ($result && $printerData) {
                // 记录打印机删除日志
                $printerName = $printerData['model'] ?? $printerData['brand'] ?? $printerData['asset_number'] ?? "ID:{$id}";
                $this->logService->logOperation('delete_printer', 'printer', "删除打印机: {$printerName}", [
                    'id' => $id,
                    'deleted_data' => $printerData
                ]);
            }
            
            echo json_encode(['code' => $result ? 0 : 1]);
        } catch (\Throwable $e) {
            if (!headers_sent()) header('Content-Type: application/json');
            echo json_encode(['code' => 1, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    public function batchDelete()
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) $input = $_POST;
            $ids = isset($input['ids']) ? $input['ids'] : [];
            
            // 获取要删除的打印机信息用于日志记录
            $deletedPrinters = [];
            foreach ($ids as $id) {
                $printerData = $this->model->getById($id);
                if ($printerData) {
                    $deletedPrinters[] = $printerData;
                }
            }
            
            $result = $this->model->batchDelete($ids);
            
            if (!headers_sent()) header('Content-Type: application/json');
            
            if ($result && !empty($deletedPrinters)) {
                // 记录打印机批量删除日志
                $printerNames = array_map(function($printer) {
                    return $printer['model'] ?? $printer['brand'] ?? $printer['asset_number'] ?? "ID:{$printer['id']}";
                }, $deletedPrinters);
                
                $this->logService->logOperation('batch_delete_printer', 'printer', 
                    "批量删除打印机: " . implode(', ', $printerNames) . " (共" . count($deletedPrinters) . "台)", [
                    'ids' => $ids,
                    'count' => count($deletedPrinters),
                    'deleted_printers' => $deletedPrinters
                ]);
            }
            
            echo json_encode(['code' => $result ? 0 : 1]);
        } catch (\Throwable $e) {
            if (!headers_sent()) header('Content-Type: application/json');
            echo json_encode(['code' => 1, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    public function export()
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) $input = $_POST;
            $ids = isset($input['ids']) ? $input['ids'] : [];
            if (!is_array($ids) || empty($ids)) {
                http_response_code(400);
                echo 'No data';
                exit;
            }
            $data = $this->model->getByIds($ids);
            
            // 记录打印机导出日志
            $this->logService->logOperation('export_printer', 'printer', 
                "导出打印机数据 (共" . count($data) . "条记录)", [
                'ids' => $ids,
                'count' => count($data)
            ]);
            
            // 生成xlsx
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $header = ['部门','使用人员','品牌','型号','颜色','类型','功能','资产编号','状态','更新时间','购入价','币种'];
            $sheet->fromArray($header, null, 'A1');
            $row = 2;
            foreach ($data as $item) {
                $sheet->fromArray([
                    $item['department'],
                    $item['user'],
                    $item['brand'],
                    $item['model'],
                    $item['color'],
                    $item['type'],
                    $item['function'],
                    $item['asset_number'],
                    $item['status'],
                    $item['updated_at'],
                    $item['price'],
                    $item['currency']
                ], null, 'A' . $row++);
            }
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="printer_export.xlsx"');
            header('Cache-Control: max-age=0');
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        } catch (\Throwable $e) {
            http_response_code(500);
            echo '导出失败: ' . $e->getMessage();
        }
        exit;
    }

    // 批量导入接口
    public function batchImport()
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $list = isset($input['list']) ? $input['list'] : [];
            
            error_log('PrinterController batchImport: 收到导入请求，数据条数: ' . count($list));
            error_log('PrinterController batchImport: 原始数据: ' . json_encode($input, JSON_UNESCAPED_UNICODE));
            
            if (empty($list)) {
                if (!headers_sent()) header('Content-Type: application/json');
                echo json_encode(['code' => 1, 'msg' => '导入数据为空']);
                exit;
            }
            
            $result = $this->model->batchCreate($list);
            $success = $result['success'];
            $fail = $result['failed'];
            $errors = $result['errors'] ?? [];
            
            error_log("PrinterController batchImport: 导入完成，成功: $success, 失败: $fail");
            if (!empty($errors)) {
                error_log("PrinterController batchImport: 错误详情: " . json_encode($errors, JSON_UNESCAPED_UNICODE));
            }
            
            // 记录打印机批量导入日志
            $this->logService->logOperation('batch_import_printer', 'printer', 
                "批量导入打印机数据: 成功{$success}条, 失败{$fail}条", [
                'total' => count($list),
                'success' => $success,
                'fail' => $fail,
                'errors' => array_slice($errors, 0, 10) // 只记录前10个错误
            ]);
            
            if (!headers_sent()) header('Content-Type: application/json');
            echo json_encode([
                'code' => 0, 
                'success' => $success, 
                'fail' => $fail,
                'errors' => $errors
            ]);
        } catch (\Throwable $e) {
            error_log('PrinterController batchImport exception: ' . $e->getMessage());
            error_log('PrinterController batchImport stack trace: ' . $e->getTraceAsString());
            if (!headers_sent()) header('Content-Type: application/json');
            echo json_encode(['code' => 1, 'msg' => $e->getMessage()]);
        }
        exit;
    }
}
