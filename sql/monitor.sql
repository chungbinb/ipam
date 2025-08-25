CREATE TABLE monitor (
    id INT PRIMARY KEY AUTO_INCREMENT,
    brand VARCHAR(100) NOT NULL COMMENT '品牌',
    asset_id VARCHAR(50) NOT NULL COMMENT '资产编号',
    department VARCHAR(100) COMMENT '使用部门',
    user VARCHAR(100) COMMENT '使用人',
    size VARCHAR(20) COMMENT '尺寸',
    model VARCHAR(50) COMMENT '型号',
    spec VARCHAR(100) COMMENT '规格',
    status VARCHAR(20) NOT NULL COMMENT '状态',
    remark VARCHAR(255) COMMENT '备注',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at DATETIME COMMENT '更新时间'
);
