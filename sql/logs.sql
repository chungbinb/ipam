CREATE TABLE `logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `log_type` enum('operation','login','runtime') NOT NULL COMMENT '日志类型',
  `level` enum('info','warning','error') NOT NULL DEFAULT 'info' COMMENT '日志级别',
  `username` varchar(100) DEFAULT NULL COMMENT '操作用户',
  `message` text NOT NULL COMMENT '日志信息',
  `context` text DEFAULT NULL COMMENT '上下文信息（如IP、请求参数等）',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `log_type` (`log_type`),
  KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT='系统日志表';
