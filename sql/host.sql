CREATE TABLE host (
    id INT PRIMARY KEY AUTO_INCREMENT,
    department VARCHAR(100) COMMENT '部门',
    user VARCHAR(100) COMMENT '使用人员',
    cpu VARCHAR(100) COMMENT 'CPU',
    memory VARCHAR(100) COMMENT '内存',
    disk VARCHAR(100) COMMENT '硬盘',
    ip VARCHAR(50) COMMENT 'IP地址',
    mac VARCHAR(50) COMMENT 'MAC地址',
    host_number VARCHAR(50) COMMENT '主机编号',
    monitor_number VARCHAR(50) COMMENT '显示器编号',
    printer_number VARCHAR(50) COMMENT '打印机/编号',
    account VARCHAR(100) COMMENT '0.1/10.15共享账号',
    supplier VARCHAR(100) COMMENT '供应商',
    remark VARCHAR(255) COMMENT '备注',
    updated_at DATETIME COMMENT '更新时间',
    created_at DATETIME COMMENT '创建时间'
);
