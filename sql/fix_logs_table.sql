-- 修复logs表结构，确保与accounts表一致
-- 如果logs表中有user_id字段但应该是account_id，则重命名
-- 如果没有则添加account_id字段

-- 检查并添加account_id字段（如果不存在）
ALTER TABLE logs 
ADD COLUMN IF NOT EXISTS account_id INT NULL 
COMMENT '关联的账户ID' 
AFTER id;

-- 如果存在user_id字段，将数据迁移到account_id然后删除user_id
-- UPDATE logs SET account_id = user_id WHERE user_id IS NOT NULL;
-- ALTER TABLE logs DROP COLUMN IF EXISTS user_id;

-- 添加外键约束（可选）
-- ALTER TABLE logs 
-- ADD CONSTRAINT fk_logs_account_id 
-- FOREIGN KEY (account_id) REFERENCES accounts(id) 
-- ON DELETE SET NULL ON UPDATE CASCADE;

-- 验证表结构
DESCRIBE logs;
DESCRIBE accounts;
