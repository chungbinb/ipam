CREATE TABLE laptop (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL COMMENT '名称',
    ip VARCHAR(50) NOT NULL COMMENT 'IP地址',
    asset_id VARCHAR(50) NOT NULL COMMENT '资产编号',
    value DECIMAL(12,2) DEFAULT 0 COMMENT '资产价值',
    currency VARCHAR(10) DEFAULT 'CNY' COMMENT '币种',
    acquire_date DATE COMMENT '取得日期',
    region VARCHAR(50) NOT NULL COMMENT '所在地区',
    department VARCHAR(100) NOT NULL COMMENT '部门',
    user VARCHAR(100) NOT NULL COMMENT '使用人',
    status VARCHAR(20) NOT NULL COMMENT '状态',
    remark VARCHAR(255) COMMENT '备注',
    created_at DATETIME COMMENT '创建时间',
    updated_at DATETIME COMMENT '更新时间'
);
