<?php

require_once __DIR__ . '/../config/database.php';

class LogModel
{
    private $pdo;

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;
    }

    /**
     * 创建一条日志
     * @param string $logType 日志类型: operation, login, runtime
     * @param string $level 日志级别: info, warning, error
     * @param string $message 日志信息
     * @param string|null $username 操作用户名
     * @param array $context 附加上下文信息
     * @return bool
     */
    public function create($logType, $level, $message, $username = null, $context = [])
    {
        $sql = "INSERT INTO logs (log_type, level, message, username, context) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $contextJson = empty($context) ? null : json_encode($context, JSON_UNESCAPED_UNICODE);
        return $stmt->execute([$logType, $level, $message, $username, $contextJson]);
    }

    /**
     * 获取日志列表
     * @param array $query 查询参数 (如 log_type)
     * @return array
     */
    public function getList($query = [])
    {
        $sql = "SELECT * FROM logs WHERE 1=1";
        $params = [];

        if (!empty($query['log_type'])) {
            $sql .= " AND log_type = ?";
            $params[] = $query['log_type'];
        }

        $sql .= " ORDER BY id DESC LIMIT 200"; // 默认最多显示最近200条

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 清理旧日志
     * @param int $days 保留天数
     * @return int 清理的记录数
     */
    public function cleanup($days = 30)
    {
        $sql = "DELETE FROM logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }

    /**
     * 获取日志统计信息
     * @return array
     */
    public function getStatistics()
    {
        $stats = [];

        // 按类型统计
        $sql = "SELECT log_type, COUNT(*) as count FROM logs GROUP BY log_type";
        $stmt = $this->pdo->query($sql);
        $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 按级别统计
        $sql = "SELECT level, COUNT(*) as count FROM logs GROUP BY level";
        $stmt = $this->pdo->query($sql);
        $stats['by_level'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 今天的日志数量
        $sql = "SELECT COUNT(*) as count FROM logs WHERE DATE(created_at) = CURDATE()";
        $stmt = $this->pdo->query($sql);
        $stats['today'] = $stmt->fetchColumn();

        // 本周的日志数量
        $sql = "SELECT COUNT(*) as count FROM logs WHERE YEARWEEK(created_at) = YEARWEEK(NOW())";
        $stmt = $this->pdo->query($sql);
        $stats['this_week'] = $stmt->fetchColumn();

        // 最近7天每天的日志数量
        $sql = "SELECT DATE(created_at) as date, COUNT(*) as count 
                FROM logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date DESC";
        $stmt = $this->pdo->query($sql);
        $stats['last_7_days'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $stats;
    }

    /**
     * 根据用户名获取日志
     * @param string $username
     * @param int $limit
     * @return array
     */
    public function getByUsername($username, $limit = 50)
    {
        $sql = "SELECT * FROM logs WHERE username = ? ORDER BY id DESC LIMIT ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$username, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}