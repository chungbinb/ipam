SET FOREIGN_KEY_CHECKS = 0;

-- 清理旧表（顺序：先明细表，后主表）
DROP TABLE IF EXISTS consumable_in_detail;
DROP TABLE IF EXISTS consumable_out_detail;
DROP TABLE IF EXISTS consumable;

-- 主表：耗材
CREATE TABLE IF NOT EXISTS consumable (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(100) NOT NULL,
    model VARCHAR(255) DEFAULT NULL,
    spec VARCHAR(255) DEFAULT NULL,
    barcode VARCHAR(255) DEFAULT NULL,
    stock INT NOT NULL DEFAULT 0,
    remark VARCHAR(500) DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- 兼容旧版 MySQL：DATETIME 不使用 DEFAULT CURRENT_TIMESTAMP
    created_at DATETIME NOT NULL,
    KEY idx_type (type),
    KEY idx_barcode (barcode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 入库明细（与后端模型一致：不含 barcode、operator 等可选字段）
CREATE TABLE IF NOT EXISTS consumable_in_detail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consumable_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(100) NOT NULL,
    model VARCHAR(255) DEFAULT NULL,
    spec VARCHAR(255) DEFAULT NULL,
    in_count INT NOT NULL,
    remark VARCHAR(500) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_consumable_id (consumable_id),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 出库明细
CREATE TABLE IF NOT EXISTS consumable_out_detail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    consumable_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(100) NOT NULL,
    model VARCHAR(255) DEFAULT NULL,
    spec VARCHAR(255) DEFAULT NULL,
    out_count INT NOT NULL,
    remark VARCHAR(500) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_consumable_id (consumable_id),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- 说明：
-- 1) DATETIME 不设置默认值，应用层插入时使用 NOW()（后端已实现）。
-- 2) 不设外键以保留历史明细（删除 consumable 不会联动删除 *_detail）。
