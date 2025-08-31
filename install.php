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
    // MySQL 8.4.5 兼容性配置
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, sql_mode = ''"
    ];
    $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
    return $pdo;
}

function checkStorageWritable() {
    $dir = './storage';
    if (!is_dir($dir)) {
        return @mkdir($dir, 0755, true);
    }
    return is_writable($dir);
}

function checkConfigWritable() {
    $dir = './src/config';
    if (!is_dir($dir)) {
        return @mkdir($dir, 0755, true);
    }
    return is_writable($dir);
}

function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

function checkCommandExists($command) {
    // 检查exec函数是否被禁用
    if (in_array('exec', explode(',', ini_get('disable_functions')))) {
        return false;
    }
    
    // 检查命令是否存在
    $output = null;
    $return_var = null;
    
    // 根据操作系统使用不同的命令检测方式
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows系统
        exec("where $command 2>nul", $output, $return_var);
    } else {
        // Unix/Linux/macOS系统
        exec("which $command 2>/dev/null", $output, $return_var);
    }
    
    return $return_var === 0 && !empty($output);
}

function checkExecFunction() {
    // 检查exec函数是否可用
    if (in_array('exec', explode(',', ini_get('disable_functions')))) {
        return false;
    }
    
    // 尝试执行一个简单的命令
    $output = null;
    $return_var = null;
    
    try {
        exec('echo "test" 2>/dev/null', $output, $return_var);
        return $return_var === 0;
    } catch (Exception $e) {
        return false;
    }
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
        
        // 获取MySQL版本信息
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $matches);
        $majorVersion = (int)$matches[1];
        $minorVersion = (int)$matches[2];
        
        // 检查版本兼容性
        $isCompatible = ($majorVersion > 5) || ($majorVersion == 5 && $minorVersion >= 7);
        if (!$isCompatible) {
            throw new Exception("MySQL版本过低 ({$version})，要求 MySQL 5.7+ 或 MySQL 8.0+");
        }
        
        // 检查数据库权限
        $requiredPrivileges = ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'ALTER', 'INDEX', 'DROP'];
        $stmt = $pdo->query("SHOW GRANTS FOR CURRENT_USER()");
        $grants = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $hasAllPrivileges = false;
        foreach ($grants as $grant) {
            if (strpos($grant, 'ALL PRIVILEGES') !== false || 
                strpos($grant, 'GRANT ALL') !== false) {
                $hasAllPrivileges = true;
                break;
            }
        }
        
        // 检查字符集支持
        $stmt = $pdo->query("SHOW CHARACTER SET LIKE 'utf8mb4'");
        $utf8mb4Support = $stmt->fetch() !== false;
        
        // 检查存储引擎
        $stmt = $pdo->query("SHOW ENGINES WHERE Engine = 'InnoDB' AND Support IN ('YES', 'DEFAULT')");
        $innodbSupport = $stmt->fetch() !== false;
        
        $warnings = [];
        if (!$utf8mb4Support) {
            $warnings[] = "数据库不支持 UTF8MB4 字符集";
        }
        if (!$innodbSupport) {
            $warnings[] = "InnoDB 存储引擎不可用";
        }
        if (!$hasAllPrivileges) {
            // 检查具体权限
            $missingPrivileges = [];
            foreach ($requiredPrivileges as $privilege) {
                $found = false;
                foreach ($grants as $grant) {
                    if (strpos(strtoupper($grant), strtoupper($privilege)) !== false) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $missingPrivileges[] = $privilege;
                }
            }
            if (!empty($missingPrivileges)) {
                $warnings[] = "可能缺少权限: " . implode(', ', $missingPrivileges);
            }
        }
        
        $message = "数据库连接成功！\\n";
        $message .= "MySQL版本: {$version}\\n";
        $message .= "字符集支持: " . ($utf8mb4Support ? "✓ UTF8MB4" : "⚠ 有限") . "\\n";
        $message .= "存储引擎: " . ($innodbSupport ? "✓ InnoDB" : "⚠ 有限") . "\\n";
        
        if (!empty($warnings)) {
            $message .= "\\n注意事项:\\n" . implode("\\n", array_map(function($w) { return "• " . $w; }, $warnings));
        }
        
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'version' => $version,
            'warnings' => $warnings
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
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
        body { font-family: 'Microsoft YaHei', Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; margin: 0; }
        .installer { background: white; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); width: 100%; max-width: 1400px; overflow: hidden; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
        .header h1 { font-size: 24px; margin-bottom: 5px; }
        .header p { opacity: 0.9; font-size: 14px; }
        .content { padding: 40px; max-width: none; }
        .step-indicator { display: flex; justify-content: center; margin-bottom: 40px; }
        .step { width: 30px; height: 30px; border-radius: 50%; background: #ddd; color: #666; display: flex; align-items: center; justify-content: center; margin: 0 10px; font-weight: bold; }
        .step.active { background: #667eea; color: white; }
        .step.completed { background: #28a745; color: white; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #333; font-size: 15px; }
        .form-group input, .form-group select { width: 100%; padding: 12px 16px; border: 1px solid #ddd; border-radius: 6px; font-size: 15px; transition: border-color 0.3s ease; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.1); }
        .btn { background: #667eea; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; transition: background 0.3s; display: inline-block; text-decoration: none; }
        .btn:hover { background: #5a6fd8; }
        .btn:disabled { background: #ccc; cursor: not-allowed; }
        .btn-group { text-align: center; margin-top: 30px; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .requirements { list-style: none; }
        .requirements li { 
            padding: 12px 0; 
            border-bottom: 1px solid #eee; 
            display: flex; 
            justify-content: space-between; 
            align-items: flex-start;
            line-height: 1.5;
        }
        .requirements li:last-child { border-bottom: none; }
        .requirements h3 { margin: 30px 0 15px 0; padding: 12px 0; border-bottom: 2px solid #667eea; color: #667eea; font-size: 18px; font-weight: 600; }
        .status { font-weight: bold; }
        .status.ok { color: #28a745; }
        .status.error { color: #dc3545; }
        .status.warning { color: #ffc107; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .progress { background: #f0f0f0; border-radius: 10px; height: 20px; margin: 20px 0; overflow: hidden; }
        .progress-bar { background: #667eea; height: 100%; transition: width 0.3s; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; font-size: 12px; white-space: pre-wrap; max-height: 300px; overflow-y: auto; }
        .form-group small { display: block; margin-top: 5px; }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .requirements h3:first-of-type { margin-top: 0; }
        .requirements li small { color: #666; font-size: 11px; margin-top: 2px; }
        .grid-info { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        .grid-info h4 { margin: 0 0 12px 0; font-size: 15px; font-weight: 600; }
        
        /* 环境检测结果的多列布局 */
        .requirements-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); 
            gap: 25px; 
            margin-top: 20px;
        }
        .requirements-section {
            background: #fafafa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        .requirements-section h3 {
            margin-top: 0;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #667eea;
            color: #667eea;
            font-size: 16px;
            font-weight: 600;
        }
        
        /* 数据库配置表单的网格布局 */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-grid .form-group {
            margin-bottom: 0;
        }
        
        /* 超大屏幕优化 */
        @media (min-width: 1600px) {
            .installer { max-width: 1500px; }
            .content { padding: 50px 60px; }
            .requirements-grid { 
                grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); 
                gap: 35px; 
            }
            .form-grid { gap: 30px; }
            .grid-info { gap: 40px; }
        }
        
        @media (max-width: 1200px) { 
            .installer { max-width: 95vw; }
            .content { padding: 35px; }
        }
        @media (max-width: 1024px) { 
            .installer { max-width: 98vw; }
            .content { padding: 30px; }
            .requirements-grid { grid-template-columns: 1fr; gap: 20px; }
            .form-grid { grid-template-columns: 1fr; gap: 15px; }
        }
        @media (max-width: 768px) { 
            .grid-info { grid-template-columns: 1fr; gap: 20px; }
            .content { padding: 25px; }
            .installer { width: 100%; }
            .requirements li { flex-direction: column; align-items: flex-start; gap: 5px; }
            .requirements-grid { gap: 15px; }
        }
        @media (max-width: 600px) { 
            body { padding: 8px; }
            .content { padding: 20px; }
            .installer { width: 100%; border-radius: 8px; }
            .header { padding: 15px; }
            .header h1 { font-size: 20px; }
            .requirements-section { padding: 15px; }
            .form-group label { font-size: 14px; }
            .form-group input, .form-group select { padding: 10px 12px; font-size: 14px; }
        }
        @media (max-width: 480px) {
            body { padding: 5px; }
            .content { padding: 15px; }
            .header { padding: 12px; }
            .header h1 { font-size: 18px; }
            .header p { font-size: 13px; }
            .step { width: 26px; height: 26px; font-size: 13px; }
            .requirements-section { padding: 12px; }
            .requirements-section h3 { font-size: 15px; }
        }
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
        
        function recheckEnvironment() {
            const btn = event.target;
            btn.disabled = true;
            btn.innerHTML = '🔄 重新检测中...';
            
            setTimeout(() => {
                location.reload();
            }, 1000);
        }
        
        function testDatabase() {
            const formData = new FormData(document.getElementById('dbForm'));
            const resultDiv = document.getElementById('db-test-result');
            const testBtn = document.querySelector('button[onclick="testDatabase()"]');
            const installBtn = document.getElementById('installBtn');
            
            // 禁用按钮并显示测试中状态
            testBtn.disabled = true;
            testBtn.innerHTML = '🔄 正在测试...';
            installBtn.disabled = true;
            resultDiv.innerHTML = '<div class="alert alert-info">🔍 正在测试数据库连接，请稍候...</div>';
            
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
                testBtn.disabled = false;
                testBtn.innerHTML = '🔍 测试数据库连接';
                
                if (data.success) {
                    let alertClass = 'alert-success';
                    let icon = '✅';
                    let title = '数据库连接测试成功！';
                    
                    if (data.warnings && data.warnings.length > 0) {
                        alertClass = 'alert-warning';
                        icon = '⚠️';
                        title = '数据库连接成功，但有注意事项';
                    }
                    
                    let messageHtml = data.message.replace(/\\n/g, '<br>');
                    resultDiv.innerHTML = `
                        <div class="alert ${alertClass}">
                            <strong>${icon} ${title}</strong><br>
                            <div style="margin-top: 10px; font-family: monospace; font-size: 13px; white-space: pre-line;">${messageHtml}</div>
                        </div>
                    `;
                    
                    installBtn.disabled = false;
                    installBtn.style.background = '#28a745';
                    installBtn.innerHTML = '✅ 开始安装 →';
                } else {
                    resultDiv.innerHTML = `
                        <div class="alert alert-error">
                            <strong>❌ 数据库连接失败</strong><br>
                            <div style="margin-top: 10px;">${data.message || '未知错误'}</div>
                            <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px; font-size: 13px;">
                                <strong>常见解决方案：</strong><br>
                                • 检查数据库服务是否启动<br>
                                • 验证主机名和端口号<br>
                                • 确认用户名和密码正确<br>
                                • 检查数据库是否存在<br>
                                • 确认网络连接正常
                            </div>
                        </div>
                    `;
                    installBtn.disabled = true;
                    installBtn.style.background = '#6c757d';
                    installBtn.innerHTML = '开始安装 →';
                }
            })
            .catch(error => {
                console.error('Database test error:', error);
                testBtn.disabled = false;
                testBtn.innerHTML = '🔍 测试数据库连接';
                resultDiv.innerHTML = `
                    <div class="alert alert-error">
                        <strong>❌ 测试过程发生错误</strong><br>
                        <div style="margin-top: 10px;">${error.message}</div>
                        <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px; font-size: 13px;">
                            请检查：<br>
                            • PHP错误日志<br>
                            • 服务器网络连接<br>
                            • PHP PDO扩展是否正常<br>
                            • 防火墙设置
                        </div>
                    </div>
                `;
                installBtn.disabled = true;
                installBtn.style.background = '#6c757d';
                installBtn.innerHTML = '开始安装 →';
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
            <li>已准备好MySQL 5.7+ 或 MySQL 8.0+ 数据库</li>
            <li>PHP版本 >= 7.4 (推荐 PHP 8.0+)</li>
            <li>已开启必需的PHP扩展 (PDO, MySQL, JSON, cURL等)</li>
            <li>Web服务器已正确配置 (Apache/Nginx)</li>
            <li>具有目录写入权限</li>
        </ul>
    </div>
    
    <div class="grid-info">
        <div>
            <h3>系统功能特性</h3>
            <ul style="margin: 15px 0; padding-left: 20px; line-height: 1.8;">
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
        </div>
        
        <div>
            <h3>最低系统要求</h3>
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 15px 0;">
                <div style="margin-bottom: 20px;">
                    <h4 style="color: #667eea; margin-bottom: 12px; font-size: 15px; font-weight: 600;">服务器环境</h4>
                    <ul style="margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.6;">
                        <li>PHP 7.4+ (推荐 8.0+)</li>
                        <li>MySQL 5.7+ / MySQL 8.0+</li>
                        <li>Apache 2.4+ / Nginx 1.18+</li>
                        <li>128MB+ PHP内存限制</li>
                        <li>启用exec系统函数</li>
                    </ul>
                </div>
                <div style="margin-bottom: 20px;">
                    <h4 style="color: #667eea; margin-bottom: 12px; font-size: 15px; font-weight: 600;">系统工具</h4>
                    <ul style="margin: 0; padding-left: 20px; font-size: 13px; line-height: 1.6;">
                        <li>ping (网络连通测试)</li>
                        <li>fping (批量ping工具)</li>
                        <li>nmap (端口扫描)</li>
                        <li>arp (ARP表查看)</li>
                    </ul>
                </div>
                <div>
                    <h4 style="color: #667eea; margin-bottom: 12px; font-size: 15px; font-weight: 600;">PHP扩展要求</h4>
                    <div style="font-size: 13px; color: #666; line-height: 1.6;">
                        PDO, PDO-MySQL, JSON, cURL, OpenSSL, MBString, Hash扩展
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="alert alert-warning">
        <strong>重要提示：</strong>
        <p style="margin: 5px 0;">安装过程将自动检测系统环境，只有通过所有必需检测项才能继续安装。如有问题，请联系系统管理员解决。</p>
        <p style="margin: 5px 0; font-size: 13px;"><strong>系统工具说明：</strong>本系统需要ping、fping等网络工具来实现IP段扫描和设备发现功能。如果缺少这些工具，部分功能将无法正常使用。</p>
    </div>
    
    <div class="btn-group">
        <button class="btn" onclick="nextStep()" style="font-size: 16px; padding: 15px 40px;">开始环境检测</button>
    </div>
    
    <style>
        @media (max-width: 768px) {
            [style*="grid-template-columns: 1fr 1fr"] {
                grid-template-columns: 1fr !important;
                gap: 25px !important;
            }
        }
    </style>
    <?php
}

function showRequirements() {
    ?>
    <h2>环境检测</h2>
    <p style="font-size: 16px; color: #666; margin-bottom: 25px;">正在检查系统环境是否满足安装要求，请确保所有必需项目都通过检测...</p>
    
    <div class="alert alert-info">
        <strong>系统要求：</strong>
        <ul style="margin-top: 10px; padding-left: 20px;">
            <li>PHP 7.4+ (推荐 PHP 8.0+)</li>
            <li>MySQL 5.7+ 或 MySQL 8.0+</li>
            <li>Web服务器 (Apache/Nginx)</li>
            <li>至少 128MB PHP 内存限制</li>
            <li>文件上传大小至少 16MB</li>
            <li>启用exec函数用于系统命令执行</li>
            <li>系统工具：ping、fping、nmap、arp等</li>
        </ul>
    </div>
    
    <div class="requirements-grid">
        <!-- PHP环境检测 -->
        <div class="requirements-section">
            <h3>PHP环境检测</h3>
            <ul class="requirements">
                <?php
                // PHP版本检测
                $phpVersion = PHP_VERSION;
                $phpVersionOk = version_compare($phpVersion, '7.4.0', '>=');
                $phpRecommended = version_compare($phpVersion, '8.0.0', '>=');
                
                echo '<li><span>PHP版本 (当前: ' . $phpVersion . ')</span> <span class="status ' . 
                     ($phpVersionOk ? ($phpRecommended ? 'ok' : 'warning') : 'error') . '">' . 
                     ($phpVersionOk ? ($phpRecommended ? '✓ 优秀' : '⚠ 可用') : '✗ 版本过低') . '</span></li>';
                
                // PHP扩展检测
                $requiredExtensions = [
                    'PDO' => 'pdo',
                    'PDO MySQL' => 'pdo_mysql',
                    'JSON' => 'json',
                    'cURL' => 'curl',
                    'OpenSSL' => 'openssl',
                    'MBString' => 'mbstring',
                    'Hash' => 'hash',
                ];
                
                $allExtensionsLoaded = true;
                foreach ($requiredExtensions as $name => $ext) {
                    $loaded = extension_loaded($ext);
                    if (!$loaded) $allExtensionsLoaded = false;
                    
                    $statusClass = $loaded ? 'ok' : 'error';
                    $statusText = $loaded ? '✓ 已加载' : '✗ 未安装';
                    
                    echo "<li><span>{$name}扩展</span> <span class=\"status $statusClass\">$statusText</span></li>";
                }
                ?>
            </ul>
        </div>
        
        <!-- PHP配置检测 -->
        <div class="requirements-section">
            <h3>PHP配置检测</h3>
            <ul class="requirements">
                <?php
                // PHP配置检测
                $memoryLimit = ini_get('memory_limit');
                $memoryLimitBytes = return_bytes($memoryLimit);
                $memoryOk = $memoryLimitBytes >= 128 * 1024 * 1024; // 128MB
                
                echo '<li><span>内存限制 (当前: ' . $memoryLimit . ')</span> <span class="status ' . 
                     ($memoryOk ? 'ok' : 'warning') . '">' . 
                     ($memoryOk ? '✓ 充足' : '⚠ 建议≥128M') . '</span></li>';
                
                $uploadMaxFilesize = ini_get('upload_max_filesize');
                $uploadBytes = return_bytes($uploadMaxFilesize);
                $uploadOk = $uploadBytes >= 16 * 1024 * 1024; // 16MB
                
                echo '<li><span>文件上传限制 (当前: ' . $uploadMaxFilesize . ')</span> <span class="status ' . 
                     ($uploadOk ? 'ok' : 'warning') . '">' . 
                     ($uploadOk ? '✓ 充足' : '⚠ 建议≥16M') . '</span></li>';
                
                $maxExecutionTime = ini_get('max_execution_time');
                $executionOk = $maxExecutionTime == 0 || $maxExecutionTime >= 60;
                
                echo '<li><span>脚本执行时间 (当前: ' . ($maxExecutionTime == 0 ? '无限制' : $maxExecutionTime . 's') . ')</span> <span class="status ' . 
                     ($executionOk ? 'ok' : 'warning') . '">' . 
                     ($executionOk ? '✓ 充足' : '⚠ 建议≥60s') . '</span></li>';
                ?>
            </ul>
        </div>
        
        <!-- 系统函数检测 -->
        <div class="requirements-section">
            <h3>系统函数检测</h3>
            <ul class="requirements">
                <?php
                // 系统函数检测
                $execEnabled = checkExecFunction();
                echo '<li><span>exec函数</span> <span class="status ' . 
                     ($execEnabled ? 'ok' : 'error') . '">' . 
                     ($execEnabled ? '✓ 可用' : '✗ 被禁用') . '</span></li>';
                
                // 检查其他可能被禁用的危险函数
                $systemFunctions = [
                    'shell_exec' => function_exists('shell_exec') && !in_array('shell_exec', explode(',', ini_get('disable_functions'))),
                    'system' => function_exists('system') && !in_array('system', explode(',', ini_get('disable_functions'))),
                    'passthru' => function_exists('passthru') && !in_array('passthru', explode(',', ini_get('disable_functions'))),
                ];
                
                $systemFunctionAvailable = false;
                foreach ($systemFunctions as $func => $available) {
                    if ($available) {
                        $systemFunctionAvailable = true;
                        break;
                    }
                }
                
                echo '<li><span>系统执行函数</span> <span class="status ' . 
                     ($systemFunctionAvailable ? 'ok' : 'warning') . '">' . 
                     ($systemFunctionAvailable ? '✓ 可用' : '⚠ 受限') . '</span></li>';
                ?>
            </ul>
        </div>
        
        <!-- 系统工具检测 -->
        <div class="requirements-section">
            <h3>系统工具检测</h3>
            <ul class="requirements">
                <?php
                // 系统工具检测
                $requiredTools = [
                    'ping' => [
                        'name' => 'ping命令',
                        'description' => '网络连通性测试',
                        'required' => true,
                        'windows_alt' => 'ping'
                    ],
                    'fping' => [
                        'name' => 'fping命令', 
                        'description' => '快速批量ping工具',
                        'required' => false,
                        'install_hint' => 'yum install fping 或 apt-get install fping'
                    ],
                    'nmap' => [
                        'name' => 'nmap命令',
                        'description' => '网络端口扫描工具',
                        'required' => false,
                        'install_hint' => 'yum install nmap 或 apt-get install nmap'
                    ],
                    'arp' => [
                        'name' => 'arp命令',
                        'description' => 'ARP表查看工具',
                        'required' => true,
                        'windows_alt' => 'arp'
                    ]
                ];
                
                $allRequiredToolsAvailable = true;
                $toolsAvailable = 0;
                $totalTools = count($requiredTools);
                
                foreach ($requiredTools as $tool => $config) {
                    $available = false;
                    
                    if ($execEnabled) {
                        $available = checkCommandExists($tool);
                        // Windows系统的特殊处理
                        if (!$available && !empty($config['windows_alt']) && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                            $available = checkCommandExists($config['windows_alt']);
                        }
                    }
                    
                    if ($available) {
                        $toolsAvailable++;
                    }
                    
                    if ($config['required'] && !$available) {
                        $allRequiredToolsAvailable = false;
                    }
                    
                    $statusClass = $available ? 'ok' : ($config['required'] ? 'error' : 'warning');
                    $statusText = $available ? '✓ 可用' : ($config['required'] ? '✗ 缺失' : '⚠ 建议安装');
                    
                    $toolInfo = '<span>' . $config['name'] . '<br><small style="color: #666; font-size: 12px;">' . $config['description'] . '</small>';
                    if (!$available && !empty($config['install_hint'])) {
                        $toolInfo .= '<br><small style="color: #ff6b35; font-size: 11px;">' . $config['install_hint'] . '</small>';
                    }
                    $toolInfo .= '</span>';
                    
                    echo "<li>$toolInfo <span class=\"status $statusClass\">$statusText</span></li>";
                }
                
                // 显示工具可用性统计
                echo '<li><span><strong>工具可用性统计</strong></span> <span class="status ' . 
                     ($toolsAvailable >= ($totalTools * 0.75) ? 'ok' : 'warning') . '">' . 
                     "可用 {$toolsAvailable}/{$totalTools} 个工具" . '</span></li>';
                ?>
            </ul>
        </div>
        
        <!-- 目录权限检测 -->
        <div class="requirements-section">
            <h3>目录权限检测</h3>
            <ul class="requirements">
                <?php
                // 目录权限检测
                $permissionChecks = [
                    '根目录写入权限' => is_writable('.'),
                    'storage目录' => checkStorageWritable(),
                    'src/config目录' => checkConfigWritable(),
                ];
                
                $allPermissionsOk = true;
                foreach ($permissionChecks as $item => $status) {
                    if (!$status) $allPermissionsOk = false;
                    
                    $statusClass = $status ? 'ok' : 'error';
                    $statusText = $status ? '✓ 可写' : '✗ 无权限';
                    
                    echo "<li><span>$item</span> <span class=\"status $statusClass\">$statusText</span></li>";
                }
                ?>
            </ul>
        </div>
        
        <!-- 必需文件检测 -->
        <div class="requirements-section">
            <h3>必需文件检测</h3>
            <ul class="requirements">
                <?php
                // 文件检测
                $requiredFiles = [
                    'SQL文件目录' => is_dir('./sql'),
                    'accounts.sql' => file_exists('./sql/accounts.sql'),
                    'server.sql' => file_exists('./sql/server.sql'),
                    'src目录结构' => is_dir('./src') && is_dir('./src/controllers') && is_dir('./src/models'),
                ];
                
                $allFilesExist = true;
                foreach ($requiredFiles as $item => $status) {
                    if (!$status) $allFilesExist = false;
                    
                    $statusClass = $status ? 'ok' : 'error';
                    $statusText = $status ? '✓ 存在' : '✗ 缺失';
                    
                    echo "<li><span>$item</span> <span class=\"status $statusClass\">$statusText</span></li>";
                }
                ?>
            </ul>
        </div>
    </div>
    
    <?php
    // 综合评估
    $allPassed = $phpVersionOk && $allExtensionsLoaded && $allPermissionsOk && $allFilesExist && $execEnabled && $allRequiredToolsAvailable;
    $hasWarnings = !$phpRecommended || !$memoryOk || !$uploadOk || !$executionOk || !$systemFunctionAvailable || ($toolsAvailable < $totalTools);
    ?>
    
    <?php if ($allPassed): ?>
        <?php if ($hasWarnings): ?>
            <div class="alert alert-warning">
                <strong>环境检测通过，但有建议优化项！</strong> 
                <p>系统可以正常安装，但建议优化标记为"⚠"的配置项以获得更好的性能。</p>
            </div>
        <?php else: ?>
            <div class="alert alert-success">
                <strong>恭喜！</strong> 系统环境检测全部通过，配置优秀，可以继续安装。
            </div>
        <?php endif; ?>
        <div class="btn-group">
            <button class="btn" onclick="nextStep()">下一步 - 数据库配置</button>
        </div>
    <?php else: ?>
        <div class="alert alert-error">
            <strong>环境检测失败！</strong> 
            <p>发现以下严重问题，必须解决后才能继续安装：</p>
            <ul style="margin-top: 10px; padding-left: 20px;">
                <?php if (!$phpVersionOk): ?>
                    <li>PHP版本过低，请升级到7.4或更高版本</li>
                <?php endif; ?>
                <?php if (!$allExtensionsLoaded): ?>
                    <li>缺少必需的PHP扩展，请安装标记为"✗"的扩展</li>
                <?php endif; ?>
                <?php if (!$allPermissionsOk): ?>
                    <li>目录权限不足，请设置相关目录的写入权限</li>
                <?php endif; ?>
                <?php if (!$allFilesExist): ?>
                    <li>缺少必需文件，请检查安装包完整性</li>
                <?php endif; ?>
                <?php if (!$execEnabled): ?>
                    <li>exec函数被禁用，请修改PHP配置启用exec函数</li>
                <?php endif; ?>
                <?php if (!$allRequiredToolsAvailable): ?>
                    <li>缺少必需的系统工具，请安装标记为"✗"的工具</li>
                <?php endif; ?>
            </ul>
            <p><strong>解决方案：</strong></p>
            <ol style="margin-top: 10px; padding-left: 20px;">
                <li>联系服务器管理员解决环境问题</li>
                <li>检查PHP配置和扩展安装</li>
                <li>设置正确的目录权限 (chmod 755)</li>
                <li>确保安装包文件完整</li>
                <li>启用exec函数：从php.ini的disable_functions中移除exec</li>
                <li>安装系统工具：使用包管理器安装ping、fping、nmap等工具</li>
                <li>确保系统PATH环境变量包含工具路径</li>
            </ol>
        </div>
        <div class="btn-group">
            <button class="btn" onclick="recheckEnvironment()" style="background:#17a2b8;">🔄 重新检测环境</button>
            <button class="btn" onclick="window.location.href='install.php?step=1'" style="background:#6c757d;">← 返回首页</button>
        </div>
    <?php endif; ?>
    <?php
}

function showDatabaseConfig() {
    ?>
    <h2>数据库配置</h2>
    <p style="font-size: 16px; color: #666; margin-bottom: 25px;">请填写数据库连接信息。系统将测试连接并检查数据库权限：</p>
    
    <div class="alert alert-info">
        <strong>数据库要求：</strong>
        <ul style="margin-top: 10px; padding-left: 20px;">
            <li>MySQL 5.7+ 或 MySQL 8.0+ 数据库</li>
            <li>数据库用户具有 CREATE, ALTER, INSERT, SELECT, UPDATE, DELETE 权限</li>
            <li>推荐使用独立的数据库，避免与其他应用混用</li>
            <li>确保数据库字符集支持 UTF8MB4</li>
        </ul>
    </div>
    
    <form id="dbForm" method="post" action="install.php?step=4">
        <div class="form-grid">
            <div class="form-group">
                <label for="db_host">数据库主机</label>
                <input type="text" id="db_host" name="db_host" value="localhost" required placeholder="localhost 或 IP地址">
            </div>
            
            <div class="form-group">
                <label for="db_port">端口号</label>
                <input type="number" id="db_port" name="db_port" value="3306" required placeholder="默认: 3306">
            </div>
        </div>
        
        <div class="form-group">
            <label for="db_name">数据库名</label>
            <input type="text" id="db_name" name="db_name" value="it_asset_management" required placeholder="数据库名称">
            <small style="color: #666; font-size: 13px; margin-top: 5px; display: block;">建议使用独立的数据库，系统将创建多个数据表</small>
        </div>
        
        <div class="form-grid">
            <div class="form-group">
                <label for="db_user">用户名</label>
                <input type="text" id="db_user" name="db_user" value="root" required placeholder="数据库用户名">
            </div>
            
            <div class="form-group">
                <label for="db_pass">密码</label>
                <input type="password" id="db_pass" name="db_pass" placeholder="数据库密码">
            </div>
        </div>
        
        <div id="db-test-result" style="margin: 25px 0;"></div>
        
        <div class="btn-group">
            <button type="button" class="btn" onclick="testDatabase()" style="background: #17a2b8; margin-right: 15px;">🔍 测试数据库连接</button>
            <button type="submit" class="btn" id="installBtn" disabled>开始安装 →</button>
        </div>
    </form>
    
    <div class="alert alert-warning" style="margin-top: 30px;">
        <strong>安全提示：</strong>
        <ul style="margin-top: 10px; padding-left: 20px;">
            <li>生产环境请不要使用 root 用户</li>
            <li>建议创建专用数据库用户并设置强密码</li>
            <li>定期备份数据库数据</li>
            <li>安装完成后请妥善保管数据库配置信息</li>
        </ul>
    </div>
    
    <style>
        @media (max-width: 768px) {
            .form-group:has(#db_host),
            .form-group:has(#db_port),
            .form-group:has(#db_user),
            .form-group:has(#db_pass) {
                grid-column: 1 / -1;
            }
            [style*="grid-template-columns: 1fr 1fr"] {
                grid-template-columns: 1fr !important;
                gap: 20px !important;
            }
        }
    </style>
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
            
            // 5. 验证表创建
            echo '<script>updateProgress(78, "验证数据库表...");</script>';
            flush();
            
            $requiredTables = [
                'accounts', 'brand', 'department', 'laptop', 'monitor', 
                'printer', 'host', 'consumable', 'ip_segments', 'ip_addresses', 
                'logs', 'asset_numbers', 'server'
            ];
            
            $missingTables = [];
            foreach ($requiredTables as $table) {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                if (!$stmt->fetch()) {
                    $missingTables[] = $table;
                }
            }
            
            if (!empty($missingTables)) {
                throw new Exception("以下数据库表创建失败: " . implode(', ', $missingTables) . "。请检查数据库权限和SQL文件。");
            }
            
            echo '<script>updateProgress(79, "✓ 所有数据库表创建成功");</script>';
            flush();
            
            // 6. 创建默认管理员账号
            echo '<script>updateProgress(85, "创建默认管理员账号...");</script>';
            flush();
            
            createDefaultAdmin($pdo);
            
            // 7. 创建安装锁定文件
            echo '<script>updateProgress(95, "完成安装...");</script>';
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
            echo '<div class="alert alert-error" style="margin-top: 20px;">
                <h3>❌ 安装失败</h3>
                <p><strong>错误信息:</strong></p>
                <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; white-space: pre-wrap;">' . htmlspecialchars($e->getMessage()) . '</pre>
                <p><strong>故障排除建议:</strong></p>
                <ul>
                    <li>检查数据库连接参数是否正确</li>
                    <li>确认数据库用户具有创建表的权限</li>
                    <li>检查 MySQL 版本兼容性</li>
                    <li>查看服务器错误日志获取更多信息</li>
                    <li>如果是表创建失败，可能是权限问题或SQL语法问题</li>
                </ul>
                <p><strong>调试步骤:</strong></p>
                <ol>
                    <li>手动连接数据库测试权限</li>
                    <li>检查相关 SQL 文件是否存在</li>
                    <li>尝试手动执行失败的 SQL 语句</li>
                </ol>
            </div>
            
            <div class="btn-group">
                <button type="button" class="btn" onclick="location.reload()">重新安装</button>
            </div>';
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
    // 检测MySQL版本
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $matches);
    $majorVersion = (int)$matches[1];
    $minorVersion = (int)$matches[2];
    
    $isMySQL8 = $majorVersion >= 8;
    $isMySQL55 = $majorVersion == 5 && $minorVersion == 5;
    
    echo '<script>updateProgress(52, "检测到 MySQL 版本: ' . $version . ($isMySQL8 ? ' (MySQL 8.x 兼容模式)' : ($isMySQL55 ? ' (MySQL 5.5 兼容模式)' : '')) . '");</script>';
    flush();
    
    // 设置合适的SQL模式
    try {
        if ($isMySQL8) {
            $pdo->exec("SET sql_mode = ''");
            echo '<script>updateProgress(54, "设置 MySQL 8.x 兼容模式");</script>';
        } elseif ($isMySQL55) {
            $pdo->exec("SET sql_mode = ''");
            echo '<script>updateProgress(54, "设置 MySQL 5.5 兼容模式");</script>';
        } else {
            $pdo->exec("SET sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'");
            echo '<script>updateProgress(54, "设置标准 SQL 模式");</script>';
        }
        flush();
    } catch (PDOException $e) {
        throw new Exception("设置 SQL 模式失败: " . $e->getMessage());
    }
    
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
    
    // 根据MySQL版本选择server表SQL文件
    if ($isMySQL55 && file_exists('./sql/server_mysql55.sql')) {
        $sqlFiles[] = './sql/server_mysql55.sql';
        echo '<script>updateProgress(56, "使用 MySQL 5.5 兼容的 server 表");</script>';
    } else {
        $sqlFiles[] = './sql/server.sql';
        echo '<script>updateProgress(56, "使用标准 server 表");</script>';
    }
    flush();
    
    $totalFiles = count($sqlFiles);
    $processedFiles = 0;
    
    foreach ($sqlFiles as $file) {
        $processedFiles++;
        $progress = 56 + (20 * $processedFiles / $totalFiles); // 56-76%
        
        if (file_exists($file)) {
            $fileName = basename($file);
            echo '<script>updateProgress(' . $progress . ', "正在导入: ' . $fileName . '");</script>';
            flush();
            
            $sql = file_get_contents($file);
            
            // MySQL 8.x 兼容性处理
            if ($isMySQL8) {
                $sql = str_replace('int(11)', 'int', $sql);
                $sql = str_replace('int(10)', 'int', $sql);
                $sql = str_replace('bigint(20)', 'bigint', $sql);
            }
            
            // 分割SQL语句并逐个执行
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            
            foreach ($statements as $statementIndex => $statement) {
                if (!empty($statement)) {
                    try {
                        $pdo->exec($statement);
                    } catch (PDOException $e) {
                        // 抛出详细错误信息，不再忽略
                        $errorMsg = "执行 SQL 文件 {$fileName} 第 " . ($statementIndex + 1) . " 条语句时出错: " . $e->getMessage();
                        $errorMsg .= "\n问题语句: " . substr($statement, 0, 100) . (strlen($statement) > 100 ? '...' : '');
                        throw new Exception($errorMsg);
                    }
                }
            }
            
            echo '<script>updateProgress(' . $progress . ', "✓ 成功导入: ' . $fileName . '");</script>';
            flush();
        } else {
            echo '<script>updateProgress(' . $progress . ', "⚠ 跳过缺失文件: ' . basename($file) . '");</script>';
            flush();
        }
    }
    
    echo '<script>updateProgress(76, "数据库结构导入完成");</script>';
    flush();
}

function createDefaultAdmin($pdo) {
    try {
        // 检查是否已存在管理员账号
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM accounts WHERE role = 'sysadmin'");
        $stmt->execute();
        
        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("INSERT INTO accounts (username, password_hash, role, status, created_at) VALUES (?, ?, ?, ?, NOW())");
            $success = $stmt->execute([
                'admin',
                password_hash('123456', PASSWORD_DEFAULT),
                'sysadmin',
                'active'
            ]);
            
            if (!$success) {
                throw new Exception("创建默认管理员账号失败");
            }
            
            echo '<script>updateProgress(87, "✓ 默认管理员账号创建成功");</script>';
        } else {
            echo '<script>updateProgress(87, "✓ 管理员账号已存在，跳过创建");</script>';
        }
        flush();
    } catch (PDOException $e) {
        throw new Exception("创建默认管理员账号时数据库错误: " . $e->getMessage());
    }
}
?>
