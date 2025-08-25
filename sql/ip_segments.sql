CREATE TABLE ip_segments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    segment VARCHAR(64) NOT NULL,
    mask VARCHAR(32) NOT NULL,
    business VARCHAR(64),
    department VARCHAR(64),
    unused VARCHAR(32),
    vlan VARCHAR(32),
    tag VARCHAR(64),
    remark VARCHAR(255)
);
