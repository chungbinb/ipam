<?php
require_once __DIR__ . '/../config/database.php';

class OverviewModel
{
    private $pdo;

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;
    }

    private function getCount($tableName)
    {
        try {
            // 检查表是否存在
            $result = $this->pdo->query("SHOW TABLES LIKE '$tableName'");
            if ($result->rowCount() == 0) {
                return 0; // 表不存在，返回0
            }
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM `$tableName`");
            return (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            error_log("Error getting count for table $tableName: " . $e->getMessage());
            return 0;
        }
    }

    public function getStats()
    {
        $stats = [];

        // 资产分布 (用于饼图)
        $stats['asset_distribution'] = [
            '主机' => $this->getCount('host'),
            '笔记本' => $this->getCount('laptop'),
            '显示器' => $this->getCount('monitor'),
            '打印机' => $this->getCount('printer'),
            '服务器' => $this->getCount('server'),
        ];

        // 按部门IP使用情况 (用于柱状图)
        $stats['ip_usage_by_department'] = [];
        try {
            $result = $this->pdo->query("SHOW TABLES LIKE 'ip_addresses'");
            if ($result->rowCount() > 0) {
                $stmt = $this->pdo->query("
                    SELECT department, COUNT(*) as count 
                    FROM `ip_addresses` 
                    WHERE `status` = 'used' AND department IS NOT NULL AND department != ''
                    GROUP BY department 
                    ORDER BY count DESC 
                    LIMIT 10
                ");
                $stats['ip_usage_by_department'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (\Throwable $e) {
            error_log("Error getting IP usage by department: " . $e->getMessage());
        }

        // 库存最多的5种耗材 (用于水平柱状图)
        $stats['top_consumables'] = [];
        try {
            $result = $this->pdo->query("SHOW TABLES LIKE 'consumable'");
            if ($result->rowCount() > 0) {
                $stmt = $this->pdo->query("
                    SELECT name, stock 
                    FROM `consumable` 
                    WHERE stock > 0
                    ORDER BY stock DESC 
                    LIMIT 5
                ");
                $stats['top_consumables'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (\Throwable $e) {
            error_log("Error getting top consumables: " . $e->getMessage());
        }

        return $stats;
    }
}