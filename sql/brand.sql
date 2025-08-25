CREATE TABLE brand (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL COMMENT '品牌名称',
    type VARCHAR(20) NOT NULL COMMENT '所属类型（monitor/server/host/laptop/camera/consumable）',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at TIMESTAMP NULL COMMENT '更新时间'
);
-- 注意：updated_at 需在应用层用 UPDATE 语句手动设置为 NOW()
