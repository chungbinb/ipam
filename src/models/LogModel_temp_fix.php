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
     * 创建一条日志（临时修复版本，不包含user_id）
     */
    public function create($data)
    {
        $logType = $data['log_type'] ?? 'runtime';
        $level = $data['level'] ?? 'info';
        $message = $data['message'] ?? '';
        $username = $data['username'] ?? null;
        $context = $data['context'] ?? null;
        // 临时注释掉user_id字段
        // $userId = $data['user_id'] ?? null;

        // 临时修改SQL，移除user_id字段
        $sql = "INSERT INTO logs (log_type, level, message, username, context) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        
        $contextJson = is_null($context) ? null : json_encode($context, JSON_UNESCAPED_UNICODE);
        
        // 临时修改参数，移除userId
        return $stmt->execute([$logType, $level, $message, $username, $contextJson]);
    }

    // ...existing code... (其他方法保持不变)
}