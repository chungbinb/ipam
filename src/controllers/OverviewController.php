<?php
require_once __DIR__ . '/../models/OverviewModel.php';

class OverviewController
{
    public function getStats()
    {
        try {
            $model = new OverviewModel();
            $data = $model->getStats();
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['code' => 0, 'data' => $data]);
        } catch (\Throwable $e) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            http_response_code(500);
            echo json_encode(['code' => 1, 'msg' => '获取统计数据失败: ' . $e->getMessage()]);
        }
        exit;
    }
}