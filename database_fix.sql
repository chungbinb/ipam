-- 修复logs表，添加缺失的user_id字段
ALTER TABLE logs ADD COLUMN user_id INT DEFAULT NULL AFTER id;

-- 创建users表的外键关联（如果需要的话）
-- ALTER TABLE logs ADD CONSTRAINT fk_logs_user_id 
-- FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

-- 验证表结构
DESCRIBE logs;
