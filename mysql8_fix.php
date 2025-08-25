<?php
/**
 * MySQL 多版本兼容性修复脚本
 * 支持 MySQL 5.5, 5.7, 8.0, 8.4.5 等版本
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>MySQL 多版本兼容性检查和修复</h2>\n";

// 1. 检查数据库连接配置并获取MySQL版本
function testDatabaseConnection() {
    echo "<h3>1. 测试数据库连接</h3>\n";
    
    // 从现有配置文件读取配置
    if (file_exists('./src/config/database.php')) {
        require_once './src/config/database.php';
        
        try {
            // 使用更兼容的连接方式
            $dsn = "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            // 先连接到MySQL服务器（不指定数据库）
            $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
            echo "✅ MySQL服务器连接成功<br>\n";
            
            // 检查MySQL版本
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();
            echo "📋 MySQL版本: {$version}<br>\n";
            
            // 解析版本号
            preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $matches);
            $majorVersion = (int)$matches[1];
            $minorVersion = (int)$matches[2];
            $patchVersion = (int)$matches[3];
            
            $versionInfo = [
                'full' => $version,
                'major' => $majorVersion,
                'minor' => $minorVersion,
                'patch' => $patchVersion,
                'is_mysql8' => $majorVersion >= 8,
                'is_mysql55' => $majorVersion == 5 && $minorVersion == 5
            ];
            
            echo "🔍 版本分析: MySQL {$majorVersion}.{$minorVersion}.{$patchVersion}<br>\n";
            
            // 检查数据库是否存在
            $stmt = $pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
            $stmt->execute([$config['dbname']]);
            
            if ($stmt->rowCount() > 0) {
                echo "✅ 数据库 '{$config['dbname']}' 存在<br>\n";
                
                // 重新连接到具体数据库
                $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4";
                $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
                echo "✅ 数据库连接成功<br>\n";
                
                return ['pdo' => $pdo, 'version' => $versionInfo];
            } else {
                echo "❌ 数据库 '{$config['dbname']}' 不存在<br>\n";
                echo "🔧 正在创建数据库...<br>\n";
                
                // 创建数据库
                $charset = $versionInfo['is_mysql8'] ? 'utf8mb4' : 'utf8mb4';
                $collation = $versionInfo['is_mysql8'] ? 'utf8mb4_unicode_ci' : 'utf8mb4_general_ci';
                
                $pdo->exec("CREATE DATABASE `{$config['dbname']}` CHARACTER SET {$charset} COLLATE {$collation}");
                echo "✅ 数据库创建成功<br>\n";
                
                // 重新连接到新创建的数据库
                $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4";
                $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
                return ['pdo' => $pdo, 'version' => $versionInfo];
            }
            
        } catch (PDOException $e) {
            echo "❌ 数据库连接失败: " . $e->getMessage() . "<br>\n";
            echo "💡 建议检查以下项目:<br>\n";
            echo "&nbsp;&nbsp;- 数据库服务是否启动<br>\n";
            echo "&nbsp;&nbsp;- 用户名和密码是否正确<br>\n";
            echo "&nbsp;&nbsp;- 用户是否有足够权限<br>\n";
            return false;
        }
    } else {
        echo "❌ 数据库配置文件不存在<br>\n";
        return false;
    }
}

// 2. 根据MySQL版本选择合适的SQL文件
function selectSQLFiles($versionInfo) {
    echo "<h3>2. 选择兼容的SQL文件</h3>\n";
    
    $sqlFiles = [
        './sql/accounts.sql',
        './sql/brand.sql',
        './sql/department.sql',
        './sql/laptop.sql',
        './sql/monitor.sql',
        './sql/printer.sql',
        './sql/host.sql',
        './sql/consumable.sql',
        './sql/ip_segments.sql',
        './sql/ip_addresses.sql',
        './sql/logs.sql',
        './sql/asset_numbers.sql'
    ];
    
    // 特殊处理server表
    if ($versionInfo['is_mysql55']) {
        echo "🔧 检测到MySQL 5.5，使用兼容版本的server表<br>\n";
        if (file_exists('./sql/server_mysql55.sql')) {
            $sqlFiles[] = './sql/server_mysql55.sql';
        } else {
            $sqlFiles[] = './sql/server.sql';
        }
    } else {
        echo "✅ 使用标准版本的server表<br>\n";
        $sqlFiles[] = './sql/server.sql';
    }
    
    return $sqlFiles;
}

// 3. 检查和修复SQL文件兼容性
function checkAndFixSQLFiles($versionInfo) {
    echo "<h3>3. 检查SQL文件兼容性</h3>\n";
    
    $sqlFiles = selectSQLFiles($versionInfo);
    
    foreach ($sqlFiles as $file) {
        if (file_exists($file)) {
            echo "✅ 文件存在: {$file}<br>\n";
        } else {
            echo "❌ 文件缺失: {$file}<br>\n";
        }
    }
    
    return $sqlFiles;
}

// 4. 执行数据库安装
function performInstallation($pdo, $versionInfo) {
    echo "<h3>4. 执行数据库安装</h3>\n";
    
    try {
        // 根据MySQL版本设置SQL模式
        if ($versionInfo['is_mysql8']) {
            echo "🔧 设置MySQL 8.x兼容模式<br>\n";
            $pdo->exec("SET sql_mode = ''");
        } elseif ($versionInfo['is_mysql55']) {
            echo "🔧 设置MySQL 5.5兼容模式<br>\n";
            $pdo->exec("SET sql_mode = ''");
        } else {
            echo "🔧 设置MySQL 5.7兼容模式<br>\n";
            $pdo->exec("SET sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'");
        }
        
        $sqlFiles = selectSQLFiles($versionInfo);
        
        foreach ($sqlFiles as $file) {
            if (file_exists($file)) {
                echo "📝 导入: " . basename($file) . "...<br>\n";
                $sql = file_get_contents($file);
                
                // 版本特定的SQL处理
                if ($versionInfo['is_mysql8']) {
                    // MySQL 8.x 处理
                    $sql = str_replace('int(11)', 'int', $sql);
                    $sql = str_replace('int(10)', 'int', $sql);
                    $sql = str_replace('bigint(20)', 'bigint', $sql);
                }
                
                // 分割SQL语句
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                
                foreach ($statements as $statement) {
                    if (!empty($statement)) {
                        try {
                            $pdo->exec($statement);
                        } catch (PDOException $e) {
                            echo "⚠️ 警告 ({$file}): {$e->getMessage()}<br>\n";
                        }
                    }
                }
                echo "✅ 完成<br>\n";
            }
        }
        
        // 创建默认管理员账号
        echo "👤 创建默认管理员账号...<br>\n";
        
        // 检查是否已存在
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE username = 'admin'");
        $stmt->execute();
        
        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("INSERT INTO accounts (username, password_hash, role, status, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([
                'admin',
                password_hash('123456', PASSWORD_DEFAULT),
                'sysadmin', 
                'active'
            ]);
            echo "✅ 默认管理员账号创建成功<br>\n";
        } else {
            echo "ℹ️ 管理员账号已存在<br>\n";
        }
        
        // 创建安装锁定文件
        file_put_contents('./install.lock', date('Y-m-d H:i:s') . " - MySQL {$versionInfo['full']} Compatible");
        echo "🔒 创建安装锁定文件<br>\n";
        
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
        echo "<h4>🎉 安装完成！</h4>\n";
        echo "<p><strong>MySQL版本：</strong> {$versionInfo['full']}</p>\n";
        echo "<p><strong>默认登录信息：</strong></p>\n";
        echo "<ul>\n";
        echo "<li>用户名: admin</li>\n";
        echo "<li>密码: 123456</li>\n";
        echo "</ul>\n";
        echo "<p><a href='public/login.html' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>进入系统</a></p>\n";
        echo "</div>\n";
        
        return true;
        
    } catch (Exception $e) {
        echo "❌ 安装失败: " . $e->getMessage() . "<br>\n";
        return false;
    }
}

// 执行修复和安装
echo "<style>body{font-family: Arial, sans-serif; margin: 20px;} h2,h3{color: #333;} br{line-height: 1.5;}</style>\n";

// 检查是否已安装
if (file_exists('./install.lock')) {
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px;'>\n";
    echo "⚠️ 系统已经安装完成！如需重新安装，请删除 install.lock 文件。\n";
    echo "</div>\n";
    exit;
}

// 执行修复步骤
$result = testDatabaseConnection();

if ($result) {
    $pdo = $result['pdo'];
    $versionInfo = $result['version'];
    
    checkAndFixSQLFiles($versionInfo);
    performInstallation($pdo, $versionInfo);
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "❌ 无法连接数据库，请检查配置后重试。\n";
    echo "</div>\n";
}
?>
