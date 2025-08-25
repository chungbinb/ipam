<?php
/**
 * IT资产管理系统 - 安装程序
 * 版本: 1.0
 * 日期: 2025-08-23
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置执行时间限制
set_time_limit(300);

// 辅助函数定义
function createDatabaseConnection($config) {
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function checkStorageWritable() {
    $dir = './storage';
    if (!is_dir($dir)) {
        return @mkdir($dir, 0755, true);
    }
    return is_writable($dir);
}

// 处理AJAX请求 - 必须在任何输出之前
if (isset($_GET['action']) && $_GET['action'] === 'test_db') {
    header('Content-Type: application/json');
    
    try {
        $config = [
            'host' => $_POST['db_host'] ?? '',
            'port' => $_POST['db_port'] ?? '3306',
            'dbname' => $_POST['db_name'] ?? '',
            'username' => $_POST['db_user'] ?? '',
            'password' => $_POST['db_pass'] ?? ''
        ];
        
        // 验证必填字段
        if (empty($config['host']) || empty($config['dbname']) || empty($config['username'])) {
            throw new Exception('请填写完整的数据库连接信息');
        }
        
        $pdo = createDatabaseConnection($config);
        echo json_encode(['success' => true, 'message' => '连接成功'], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 检查是否已经安装
if (file_exists('./install.lock')) {
    die('系统已经安装完成！如需重新安装，请删除 install.lock 文件。');
}

// 安装步骤
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT资产管理系统 - 安装向导</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Microsoft YaHei', Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .installer { background: white; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); width: 90%; max-width: 600px; overflow: hidden; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
        .header h1 { font-size: 24px; margin-bottom: 5px; }
        .header p { opacity: 0.9; font-size: 14px; }
        .content { padding: 30px; }
        .step-indicator { display: flex; justify-content: center; margin-bottom: 30px; }
        .step { width: 30px; height: 30px; border-radius: 50%; background: #ddd; color: #666; display: flex; align-items: center; justify-content: center; margin: 0 10px; font-weight: bold; }
        .step.active { background: #667eea; color: white; }
        .step.completed { background: #28a745; color: white; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; color: #333; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.1); }
        .btn { background: #667eea; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; transition: background 0.3s; }
        .btn:hover { background: #5a6fd8; }
        .btn:disabled { background: #ccc; cursor: not-allowed; }
        .btn-group { text-align: center; margin-top: 30px; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .requirements { list-style: none; }
        .requirements li { padding: 8px 0; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; }
        .status { font-weight: bold; }
        .status.ok { color: #28a745; }
        .status.error { color: #dc3545; }
        .status.warning { color: #ffc107; }
        .progress { background: #f0f0f0; border-radius: 10px; height: 20px; margin: 20px 0; overflow: hidden; }
        .progress-bar { background: #667eea; height: 100%; transition: width 0.3s; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; font-size: 12px; white-space: pre-wrap; max-height: 300px; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="installer">
        <div class="header">
            <h1>IT资产管理系统</h1>
            <p>安装向导 - 步骤 <?php echo $step; ?> / 4</p>
        </div>
        
        <div class="content">
            <div class="step-indicator">
                <div class="step <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : ''; ?>">1</div>
                <div class="step <?php echo $step >= 2 ? ($step > 2 ? 'completed' : 'active') : ''; ?>">2</div>
                <div class="step <?php echo $step >= 3 ? ($step > 3 ? 'completed' : 'active') : ''; ?>">3</div>
                <div class="step <?php echo $step >= 4 ? 'active' : ''; ?>">4</div>
            </div>

            <?php
            switch ($step) {
                case 1:
                    showWelcome();
                    break;
                case 2:
                    showRequirements();
                    break;
                case 3:
                    showDatabaseConfig();
                    break;
                case 4:
                    performInstallation();
                    break;
            }
            ?>
        </div>
    </div>

    <script>
        function nextStep() {
            const currentStep = <?php echo $step; ?>;
            window.location.href = 'install.php?step=' + (currentStep + 1);
        }
        
        function testDatabase() {
            const formData = new FormData(document.getElementById('dbForm'));
            const resultDiv = document.getElementById('db-test-result');
            
            // 显示测试中状态
            resultDiv.innerHTML = '<div class="alert alert-info">正在测试数据库连接...</div>';
            
            fetch('install.php?action=test_db', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // 检查响应是否为JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('服务器返回了非JSON格式的响应，请检查PHP错误日志');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = '<div class="alert alert-success">数据库连接测试成功！</div>';
                    document.getElementById('installBtn').disabled = false;
                } else {
                    resultDiv.innerHTML = '<div class="alert alert-error">连接失败: ' + (data.message || '未知错误') + '</div>';
                    document.getElementById('installBtn').disabled = true;
                }
            })
            .catch(error => {
                console.error('Database test error:', error);
                resultDiv.innerHTML = '<div class="alert alert-error">测试失败: ' + error.message + '</div>';
                document.getElementById('installBtn').disabled = true;
            });
        }
    </script>
</body>
</html>

<?php

function showWelcome() {
    ?>
    <h2>欢迎使用 IT资产管理系统</h2>
    <div class="alert alert-info">
        <strong>安装前请确保：</strong>
        <ul style="margin-top: 10px; padding-left: 20px;">
            <li>已准备好MySQL数据库</li>
            <li>PHP版本 >= 7.4</li>
            <li>已开启PDO MySQL扩展</li>
            <li>Web服务器已正确配置</li>
        </ul>
    </div>
    
    <h3>系统功能特性</h3>
    <ul style="margin: 15px 0; padding-left: 20px;">
        <li>🖥️ 笔记本电脑资产管理</li>
        <li>📺 显示器设备管理</li>
        <li>🖨️ 打印机设备管理</li>
        <li>🖥️ 主机设备管理</li>
        <li>🏢 服务器资产管理</li>
        <li>📦 耗材库存管理</li>
        <li>🌐 IP地址段管理</li>
        <li>👥 多角色权限管理</li>
        <li>📊 资产统计分析</li>
        <li>📝 操作日志记录</li>
    </ul>
    
    <div class="btn-group">
        <button class="btn" onclick="nextStep()">开始安装</button>
    </div>
    <?php
}

function showRequirements() {
    ?>
    <h2>环境检测</h2>
    <p>正在检查系统环境是否满足安装要求...</p>
    
    <ul class="requirements">
        <?php
        $checks = [
            'PHP版本 >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
            'PDO扩展' => extension_loaded('pdo'),
            'PDO MySQL扩展' => extension_loaded('pdo_mysql'),
            'JSON扩展' => extension_loaded('json'),
            'cURL扩展' => extension_loaded('curl'),
            '文件写入权限' => is_writable('.'),
            'storage目录可写' => checkStorageWritable(),
        ];
        
        $allPassed = true;
        foreach ($checks as $item => $status) {
            $statusClass = $status ? 'ok' : 'error';
            $statusText = $status ? '✓ 通过' : '✗ 失败';
            if (!$status) $allPassed = false;
            
            echo "<li>$item <span class=\"status $statusClass\">$statusText</span></li>";
        }
        ?>
    </ul>
    
    <?php if ($allPassed): ?>
        <div class="alert alert-success">
            <strong>恭喜！</strong> 系统环境检测全部通过，可以继续安装。
        </div>
        <div class="btn-group">
            <button class="btn" onclick="nextStep()">下一步</button>
        </div>
    <?php else: ?>
        <div class="alert alert-error">
            <strong>环境检测失败！</strong> 请解决上述问题后重新检测。
        </div>
        <div class="btn-group">
            <button class="btn" onclick="location.reload()">重新检测</button>
        </div>
    <?php endif; ?>
    <?php
}

function showDatabaseConfig() {
    ?>
    <h2>数据库配置</h2>
    <p>请填写数据库连接信息：</p>
    
    <form id="dbForm" method="post" action="install.php?step=4">
        <div class="form-group">
            <label for="db_host">数据库主机</label>
            <input type="text" id="db_host" name="db_host" value="localhost" required>
        </div>
        
        <div class="form-group">
            <label for="db_port">端口号</label>
            <input type="number" id="db_port" name="db_port" value="3306" required>
        </div>
        
        <div class="form-group">
            <label for="db_name">数据库名</label>
            <input type="text" id="db_name" name="db_name" value="it_asset_management" required>
        </div>
        
        <div class="form-group">
            <label for="db_user">用户名</label>
            <input type="text" id="db_user" name="db_user" value="root" required>
        </div>
        
        <div class="form-group">
            <label for="db_pass">密码</label>
            <input type="password" id="db_pass" name="db_pass">
        </div>
        
        <div id="db-test-result"></div>
        
        <div class="btn-group">
            <button type="button" class="btn" onclick="testDatabase()">测试连接</button>
            <button type="submit" class="btn" id="installBtn" disabled>开始安装</button>
        </div>
    </form>
    <?php
}

function performInstallation() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $config = [
            'host' => $_POST['db_host'],
            'port' => $_POST['db_port'],
            'dbname' => $_POST['db_name'],
            'username' => $_POST['db_user'],
            'password' => $_POST['db_pass']
        ];
        
        echo '<h2>正在安装系统...</h2>';
        echo '<div class="progress"><div class="progress-bar" id="progressBar" style="width: 0%"></div></div>';
        echo '<div id="installLog"><pre id="logContent"></pre></div>';
        
        echo '<script>
        let progress = 0;
        let logContent = document.getElementById("logContent");
        let progressBar = document.getElementById("progressBar");
        
        function updateProgress(percent, message) {
            progress = percent;
            progressBar.style.width = percent + "%";
            logContent.textContent += "[" + new Date().toLocaleTimeString() + "] " + message + "\n";
            logContent.scrollTop = logContent.scrollHeight;
        }
        
        updateProgress(10, "开始安装...");
        </script>';
        
        flush();
        
        try {
            // 1. 测试数据库连接
            echo '<script>updateProgress(20, "测试数据库连接...");</script>';
            flush();
            
            $pdo = createDatabaseConnection($config);
            
            // 2. 创建数据库配置文件
            echo '<script>updateProgress(30, "创建配置文件...");</script>';
            flush();
            
            createConfigFile($config);
            
            // 3. 创建目录结构
            echo '<script>updateProgress(40, "创建目录结构...");</script>';
            flush();
            
            createDirectories();
            
            // 4. 导入数据库结构
            echo '<script>updateProgress(50, "导入数据库结构...");</script>';
            flush();
            
            importDatabase($pdo);
            
            // 5. 创建默认管理员账号
            echo '<script>updateProgress(80, "创建默认管理员账号...");</script>';
            flush();
            
            createDefaultAdmin($pdo);
            
            // 6. 创建安装锁定文件
            echo '<script>updateProgress(90, "完成安装...");</script>';
            flush();
            
            file_put_contents('./install.lock', date('Y-m-d H:i:s'));
            
            echo '<script>updateProgress(100, "安装完成！");</script>';
            flush();
            
            // 显示安装完成信息
            echo '
            <div class="alert alert-success" style="margin-top: 20px;">
                <h3>🎉 安装完成！</h3>
                <p><strong>默认管理员账号：</strong></p>
                <ul>
                    <li>用户名: admin</li>
                    <li>密码: 123456</li>
                </ul>
                <p><strong>重要提示：</strong></p>
                <ul>
                    <li>请立即登录系统修改默认密码</li>
                    <li>请删除或重命名 install.php 文件以确保安全</li>
                    <li>建议定期备份数据库数据</li>
                </ul>
            </div>
            
            <div class="btn-group">
                <a href="public/index.html" class="btn">进入系统</a>
            </div>';
            
        } catch (Exception $e) {
            echo '<script>updateProgress(0, "安装失败: ' . addslashes($e->getMessage()) . '");</script>';
            echo '<div class="alert alert-error">安装过程中发生错误，请检查配置后重试。</div>';
        }
    }
}

function createConfigFile($config) {
    $configContent = "<?php
// 数据库配置
\$config = [
    'host' => '{$config['host']}',
    'port' => '{$config['port']}',
    'dbname' => '{$config['dbname']}',
    'username' => '{$config['username']}',
    'password' => '{$config['password']}',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
];

try {
    \$dsn = \"mysql:host={\$config['host']};port={\$config['port']};dbname={\$config['dbname']};charset={\$config['charset']}\";
    \$pdo = new PDO(\$dsn, \$config['username'], \$config['password'], \$config['options']);
} catch (PDOException \$e) {
    error_log(\"Database connection failed: \" . \$e->getMessage());
    throw new PDOException(\"数据库连接失败: \" . \$e->getMessage(), (int)\$e->getCode());
}
";
    
    if (!file_put_contents('./src/config/database.php', $configContent)) {
        throw new Exception('无法创建数据库配置文件');
    }
}

function createDirectories() {
    $dirs = [
        './storage',
        './storage/sessions',
        './storage/logs',
        './storage/uploads'
    ];
    
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                throw new Exception("无法创建目录: $dir");
            }
        }
    }
}

function importDatabase($pdo) {
    $sqlFiles = [
        './sql/accounts.sql',
        './sql/brand.sql',
        './sql/department.sql',
        './sql/laptop.sql',
        './sql/monitor.sql',
        './sql/printer.sql',
        './sql/host.sql',
        './sql/server.sql',
        './sql/consumable.sql',
        './sql/ip_segments.sql',
        './sql/ip_addresses.sql',
        './sql/logs.sql',
        './sql/asset_numbers.sql'
    ];
    
    foreach ($sqlFiles as $file) {
        if (file_exists($file)) {
            $sql = file_get_contents($file);
            $pdo->exec($sql);
        }
    }
}

function createDefaultAdmin($pdo) {
    // 检查是否已存在管理员账号
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE role = 'sysadmin'");
    $stmt->execute();
    
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO accounts (username, password_hash, role, status, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([
            'admin',
            password_hash('123456', PASSWORD_DEFAULT),
            'sysadmin',
            'active'
        ]);
    }
}
?>
