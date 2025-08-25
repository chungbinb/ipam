<?php
// 数据库配置
$config = [
    'host' => 'localhost',
    'port' => '3306',
    'dbname' => 'ipam',
    'username' => 'ipam',
    'password' => 'pbfPaWaHHaBHKbyH',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
];

try {
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset={$config['charset']}";
    $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    throw new PDOException("数据库连接失败: " . $e->getMessage(), (int)$e->getCode());
}
