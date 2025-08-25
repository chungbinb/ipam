CREATE TABLE printer (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department VARCHAR(255) NOT NULL,
    user VARCHAR(255) NOT NULL,
    brand VARCHAR(255) NOT NULL,
    model VARCHAR(255),
    color VARCHAR(50),
    type VARCHAR(50) DEFAULT NULL,
    `function` VARCHAR(50) DEFAULT NULL,
    asset_number VARCHAR(100),
    status ENUM('在用', '维修中', '未用', '报废') DEFAULT NULL,
    price DECIMAL(10, 2),
    currency ENUM('CNY', 'THB', 'USD', 'EUR', 'JPY', 'HKD') DEFAULT 'CNY',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL
);