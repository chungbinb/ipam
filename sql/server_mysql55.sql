-- MySQL 5.5兼容的服务器管理表
-- 解决方案：只使用一个TIMESTAMP字段，另一个使用DATETIME手动管理

DROP TABLE IF EXISTS `server`;

CREATE TABLE `server` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hostname VARCHAR(255) NOT NULL COMMENT '主机名',
    ip VARCHAR(45) NOT NULL COMMENT 'IP地址',
    os VARCHAR(100) DEFAULT NULL COMMENT '操作系统',
    type VARCHAR(50) DEFAULT NULL COMMENT '类型',
    model VARCHAR(255) DEFAULT NULL COMMENT '型号',
    spec VARCHAR(500) DEFAULT NULL COMMENT '规格配置',
    buy_date DATE DEFAULT NULL COMMENT '购买日期',
    buy_price VARCHAR(50) DEFAULT NULL COMMENT '购买价格',
    business VARCHAR(255) DEFAULT NULL COMMENT '使用业务',
    status VARCHAR(20) DEFAULT '离线' COMMENT '状态',
    owner VARCHAR(100) DEFAULT NULL COMMENT '负责人',
    ports VARCHAR(500) DEFAULT NULL COMMENT '开放端口',
    remark VARCHAR(1000) DEFAULT NULL COMMENT '备注',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at DATETIME DEFAULT NULL COMMENT '更新时间',
    
    KEY idx_hostname (hostname),
    KEY idx_ip (ip),
    KEY idx_status (status),
    KEY idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='服务器管理表';

-- 插入示例数据
INSERT INTO `server` (hostname, ip, os, type, model, spec, buy_date, buy_price, business, status, owner, ports, remark) VALUES
('srv-01', '192.168.1.10', 'Windows Server', '物理机', 'Dell R730', '2*E5-2630/64G/2T', '2021-03-01', '¥32000', 'OA', '在线', '张三', '80,443,3389', ''),
('srv-02', '192.168.1.11', 'Linux', '虚拟机', 'VMware', '4C/8G/200G', '2022-06-15', '¥8000', 'ERP', '离线', '李四', '22,8080', '维护中'),
('srv-03', '192.168.1.12', 'CentOS', '云主机', '阿里云ECS', '2C/4G/100G', '2023-01-10', '¥3000', '网站', '在线', '王五', '22,3306', ''),
('srv-04', '192.168.1.13', 'Ubuntu', '物理机', 'HP DL380', '2*E5-2620/32G/1T', '2020-09-20', '¥28000', '邮件', '在线', '赵六', '22', ''),
('srv-05', '192.168.1.14', 'Linux', '虚拟机', 'KVM', '2C/4G/100G', '2021-12-05', '¥5000', '测试', '离线', '孙七', '', '故障');
