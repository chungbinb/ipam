-- MySQL 5.5兼容的服务器管理表
-- 解决方案：只使用一个TIMESTAMP字段，另一个使用DATETIME手动管理

DROP TABLE IF EXISTS `server`;

CREATE TABLE `server` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hostname VARCHAR(255) NOT NULL COMMENT '主机名',
    ip VARCHAR(45) NOT NULL COMMENT 'IP地址',
    os VARCHAR(100) DEFAULT NULL COMMENT '操作系统',
    `type` VARCHAR(50) DEFAULT NULL COMMENT '类型',
    model VARCHAR(255) DEFAULT NULL COMMENT '型号',
    spec VARCHAR(500) DEFAULT NULL COMMENT '规格配置',
    buy_date DATE DEFAULT NULL COMMENT '购买日期',
    buy_price VARCHAR(50) DEFAULT NULL COMMENT '购买价格',
    business VARCHAR(255) DEFAULT NULL COMMENT '使用业务',
    `status` VARCHAR(20) DEFAULT '离线' COMMENT '状态',
    owner VARCHAR(100) DEFAULT NULL COMMENT '负责人',
    ports VARCHAR(500) DEFAULT NULL COMMENT '开放端口',
    remark VARCHAR(1000) DEFAULT NULL COMMENT '备注',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at DATETIME DEFAULT NULL COMMENT '更新时间',
    
    KEY idx_hostname (hostname),
    KEY idx_ip (ip),
    KEY idx_status (`status`),
    KEY idx_type (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='服务器管理表';

