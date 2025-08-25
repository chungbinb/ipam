CREATE TABLE ip_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(64) NOT NULL,
    mac VARCHAR(64),
    hostname VARCHAR(128),
    business VARCHAR(64),
    department VARCHAR(64),
    user VARCHAR(64),
    status VARCHAR(32),
    manual VARCHAR(32),
    ping VARCHAR(32),
    ping_time VARCHAR(32),
    remark VARCHAR(255),
    segment VARCHAR(64),
    asset_number VARCHAR(32)
);
