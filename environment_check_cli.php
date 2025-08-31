<?php
/**
 * IT资产管理系统 - 环境检测工具 (CLI版本)
 * 适用于命令行运行，无HTML依赖
 */

class EnvironmentCheckerCLI {
    private $results = [];
    private $warnings = [];
    private $errors = [];
    private $isCLI;
    
    public function __construct() {
        $this->isCLI = (php_sapi_name() === 'cli');
        $this->printHeader();
    }
    
    public function runAllChecks() {
        $this->print("\n🔍 开始环境检测...\n", 'header');
        
        $this->checkPhpVersion();
        $this->checkPhpExtensions();
        $this->checkSystemCommands();
        $this->checkFilePermissions();
        $this->checkNetworkCommands();
        $this->checkProcessControl();
        $this->checkSystemLimits();
        $this->performPingTest();
        
        $this->displaySummary();
        $this->generateRecommendations();
    }
    
    private function printHeader() {
        $this->print("=====================================", 'info');
        $this->print("IT资产管理系统 - 环境检测报告", 'header');
        $this->print("检测时间: " . date('Y-m-d H:i:s'), 'info');
        $this->print("操作系统: " . PHP_OS, 'info');
        $this->print("PHP SAPI: " . php_sapi_name(), 'info');
        $this->print("=====================================", 'info');
    }
    
    private function checkPhpVersion() {
        $this->print("\n📋 PHP版本检测", 'section');
        
        $version = phpversion();
        $minVersion = '7.4.0';
        
        if (version_compare($version, $minVersion, '>=')) {
            $this->print("✅ PHP版本: {$version} (要求: >= {$minVersion})", 'success');
            $this->results['php_version'] = true;
        } else {
            $this->print("❌ PHP版本: {$version} (要求: >= {$minVersion}) - 版本过低", 'error');
            $this->errors[] = "PHP版本过低，建议升级到7.4或更高版本";
            $this->results['php_version'] = false;
        }
    }
    
    private function checkPhpExtensions() {
        $this->print("\n🔧 PHP扩展检测", 'section');
        
        $extensions = [
            'json' => ['必需', '处理JSON数据'],
            'pdo' => ['必需', '数据库连接'],
            'pcntl' => ['推荐', '进程控制'],
            'posix' => ['推荐', 'POSIX函数'],
            'curl' => ['可选', 'HTTP请求'],
            'mbstring' => ['可选', '多字节字符串'],
        ];
        
        foreach ($extensions as $ext => $info) {
            if (extension_loaded($ext)) {
                $this->print("✅ {$ext}: 已安装 ({$info[1]})", 'success');
                $this->results['ext_' . $ext] = true;
            } else {
                $level = $info[0];
                if ($level === '必需') {
                    $this->print("❌ {$ext}: 未安装 ({$info[1]}) - 必需扩展", 'error');
                    $this->errors[] = "{$ext}扩展未安装，系统可能无法正常工作";
                } elseif ($level === '推荐') {
                    $this->print("⚠️  {$ext}: 未安装 ({$info[1]}) - 推荐安装", 'warning');
                    $this->warnings[] = "{$ext}扩展未安装，可能影响性能";
                } else {
                    $this->print("ℹ️  {$ext}: 未安装 ({$info[1]}) - 可选扩展", 'info');
                }
                $this->results['ext_' . $ext] = false;
            }
        }
    }
    
    private function checkSystemCommands() {
        $this->print("\n⚡ 系统命令检测", 'section');
        
        $commands = [
            'ping' => ['必需', 'IP连通性测试'],
            'fping' => ['推荐', '高性能批量ping'],
            'nmap' => ['可选', '网络扫描工具'],
            'which' => ['推荐', '查找命令路径'],
        ];
        
        foreach ($commands as $cmd => $info) {
            $path = $this->findCommand($cmd);
            if ($path) {
                $this->print("✅ {$cmd}: 可用 ({$path})", 'success');
                $this->results['cmd_' . $cmd] = true;
            } else {
                $level = $info[0];
                if ($level === '必需') {
                    $this->print("❌ {$cmd}: 不可用 - {$info[1]}", 'error');
                    $this->errors[] = "{$cmd}命令不可用，ping功能无法工作";
                } elseif ($level === '推荐') {
                    $this->print("⚠️  {$cmd}: 不可用 - {$info[1]}", 'warning');
                    $this->warnings[] = "{$cmd}命令不可用，建议安装以提升性能";
                } else {
                    $this->print("ℹ️  {$cmd}: 不可用 - {$info[1]}", 'info');
                }
                $this->results['cmd_' . $cmd] = false;
            }
        }
    }
    
    private function checkFilePermissions() {
        $this->print("\n📁 文件权限检测", 'section');
        
        $paths = [
            __DIR__ . '/storage/logs' => '日志目录',
            __DIR__ . '/storage/sessions' => '会话目录',
            sys_get_temp_dir() => '系统临时目录',
        ];
        
        foreach ($paths as $path => $purpose) {
            $readable = is_readable($path);
            $writable = is_writable($path);
            
            if ($readable && $writable) {
                $this->print("✅ {$purpose}: {$path} (读写正常)", 'success');
            } else {
                $this->print("❌ {$purpose}: {$path} (权限不足)", 'error');
                $this->errors[] = "目录权限不足: {$path}";
            }
        }
    }
    
    private function checkNetworkCommands() {
        $this->print("\n🌐 网络功能测试", 'section');
        
        // 测试基本ping
        $testIp = '8.8.8.8';
        $this->print("正在测试ping {$testIp}...", 'info');
        
        $pingResult = $this->testBasicPing($testIp);
        if ($pingResult['success']) {
            $this->print("✅ 基本ping测试: 成功 (用时: {$pingResult['time']}ms)", 'success');
            $this->results['basic_ping'] = true;
        } else {
            $this->print("❌ 基本ping测试: 失败 - {$pingResult['error']}", 'error');
            $this->errors[] = "基本ping功能不可用";
            $this->results['basic_ping'] = false;
        }
        
        // 测试fping
        if ($this->results['cmd_fping']) {
            $this->print("正在测试fping功能...", 'info');
            $fpingResult = $this->testFping(['8.8.8.8', '8.8.4.4']);
            if ($fpingResult['success']) {
                $this->print("✅ fping测试: 成功 (用时: {$fpingResult['time']}ms)", 'success');
                $this->results['fping_test'] = true;
            } else {
                $this->print("⚠️  fping测试: 失败 - {$fpingResult['error']}", 'warning');
                $this->warnings[] = "fping功能异常";
                $this->results['fping_test'] = false;
            }
        }
    }
    
    private function checkProcessControl() {
        $this->print("\n⚙️ 进程控制检测", 'section');
        
        $functions = [
            'proc_open' => '支持并行ping',
            'exec' => '执行系统命令',
            'shell_exec' => '备用命令执行',
            'fastcgi_finish_request' => '后台任务处理',
        ];
        
        foreach ($functions as $func => $purpose) {
            if (function_exists($func)) {
                $this->print("✅ {$func}(): 可用 - {$purpose}", 'success');
                $this->results[$func] = true;
            } else {
                if ($func === 'proc_open' || $func === 'exec') {
                    $this->print("❌ {$func}(): 不可用 - {$purpose}", 'error');
                    $this->errors[] = "{$func}函数不可用，影响核心功能";
                } else {
                    $this->print("⚠️  {$func}(): 不可用 - {$purpose}", 'warning');
                    $this->warnings[] = "{$func}函数不可用，可能影响性能";
                }
                $this->results[$func] = false;
            }
        }
    }
    
    private function checkSystemLimits() {
        $this->print("\n🔢 系统限制检测", 'section');
        
        $maxExecutionTime = ini_get('max_execution_time');
        if ($maxExecutionTime == 0 || $maxExecutionTime >= 300) {
            $this->print("✅ max_execution_time: {$maxExecutionTime}s (充足)", 'success');
        } else {
            $this->print("⚠️  max_execution_time: {$maxExecutionTime}s (建议>=300s)", 'warning');
            $this->warnings[] = "脚本执行时间限制较短";
        }
        
        $memoryLimit = ini_get('memory_limit');
        $memoryBytes = $this->parseBytes($memoryLimit);
        if ($memoryBytes >= 128 * 1024 * 1024) {
            $this->print("✅ memory_limit: {$memoryLimit} (充足)", 'success');
        } else {
            $this->print("⚠️  memory_limit: {$memoryLimit} (建议>=128M)", 'warning');
            $this->warnings[] = "内存限制较小";
        }
    }
    
    private function performPingTest() {
        $this->print("\n🚀 Ping性能测试", 'section');
        
        $testIps = ['8.8.8.8', '8.8.4.4', '1.1.1.1'];
        $this->print("测试IP: " . implode(', ', $testIps), 'info');
        
        // 串行ping测试
        $this->print("\n串行ping测试 (传统方式):", 'info');
        $startTime = microtime(true);
        foreach ($testIps as $ip) {
            $this->testBasicPing($ip);
        }
        $serialTime = round((microtime(true) - $startTime) * 1000, 2);
        $this->print("串行ping用时: {$serialTime}ms", 'info');
        
        // 并行ping测试
        if ($this->results['proc_open']) {
            $this->print("\n并行ping测试 (优化方式):", 'info');
            $startTime = microtime(true);
            $this->testParallelPing($testIps);
            $parallelTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->print("并行ping用时: {$parallelTime}ms", 'info');
            
            if ($parallelTime < $serialTime) {
                $improvement = round(($serialTime - $parallelTime) / $serialTime * 100, 1);
                $this->print("✅ 性能提升: {$improvement}%", 'success');
            }
        }
        
        // fping测试
        if ($this->results['cmd_fping']) {
            $this->print("\nfping测试 (最优方式):", 'info');
            $startTime = microtime(true);
            $this->testFping($testIps);
            $fpingTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->print("fping用时: {$fpingTime}ms", 'info');
            
            if ($fpingTime < $serialTime) {
                $improvement = round(($serialTime - $fpingTime) / $serialTime * 100, 1);
                $this->print("✅ 性能提升: {$improvement}%", 'success');
            }
        }
    }
    
    private function displaySummary() {
        $this->print("\n" . str_repeat("=", 50), 'info');
        $this->print("📊 检测结果汇总", 'header');
        $this->print(str_repeat("=", 50), 'info');
        
        $totalChecks = count($this->results);
        $passedChecks = count(array_filter($this->results));
        $passRate = round($passedChecks / $totalChecks * 100, 1);
        
        $this->print("\n总体评估:", 'section');
        $this->print("通过率: {$passedChecks}/{$totalChecks} ({$passRate}%)", 'info');
        $this->print("错误: " . count($this->errors) . " 个", 'info');
        $this->print("警告: " . count($this->warnings) . " 个", 'info');
        
        if (count($this->errors) == 0) {
            if (count($this->warnings) == 0) {
                $this->print("\n🎉 系统环境完全满足要求，ping扫描功能可以正常使用！", 'success');
            } else {
                $this->print("\n⚠️  系统基本可用，但有一些性能优化建议。", 'warning');
            }
        } else {
            $this->print("\n❌ 系统环境存在问题，需要修复后才能正常使用ping扫描功能。", 'error');
        }
        
        if (!empty($this->errors)) {
            $this->print("\n🚨 必须解决的问题:", 'section');
            foreach ($this->errors as $i => $error) {
                $this->print(($i + 1) . ". {$error}", 'error');
            }
        }
        
        if (!empty($this->warnings)) {
            $this->print("\n⚠️  建议改进的项目:", 'section');
            foreach ($this->warnings as $i => $warning) {
                $this->print(($i + 1) . ". {$warning}", 'warning');
            }
        }
    }
    
    private function generateRecommendations() {
        $this->print("\n" . str_repeat("=", 50), 'info');
        $this->print("💡 优化建议", 'header');
        $this->print(str_repeat("=", 50), 'info');
        
        $this->print("\n🔧 系统优化建议:", 'section');
        
        if (!$this->results['cmd_fping']) {
            $this->print("1. 安装fping (重要性: ⭐⭐⭐⭐⭐)", 'info');
            $this->print("   CentOS/RHEL: sudo yum install fping", 'info');
            $this->print("   Ubuntu/Debian: sudo apt-get install fping", 'info');
            $this->print("   效果: 将扫描速度提升80-90%\n", 'info');
        }
        
        if (!$this->results['proc_open']) {
            $this->print("2. 启用proc_open函数 (重要性: ⭐⭐⭐⭐)", 'info');
            $this->print("   编辑php.ini，在disable_functions中移除proc_open", 'info');
            $this->print("   然后重启Web服务器\n", 'info');
        }
        
        $this->print("3. PHP配置优化:", 'info');
        $this->print("   max_execution_time = 300", 'info');
        $this->print("   memory_limit = 256M", 'info');
        $this->print("   移除exec、shell_exec等函数限制\n", 'info');
        
        $this->print("4. 网络配置:", 'info');
        $this->print("   确保防火墙允许ICMP出站流量", 'info');
        $this->print("   确保Web服务器用户有执行ping的权限\n", 'info');
        
        $this->print("\n📈 预期性能表现:", 'section');
        if ($this->results['cmd_fping']) {
            $this->print("使用fping: 扫描254个IP约需要5-10秒 ⚡", 'success');
        } elseif ($this->results['proc_open']) {
            $this->print("使用并行ping: 扫描254个IP约需要15-30秒 🚀", 'warning');
        } else {
            $this->print("使用串行ping: 扫描254个IP约需要3-5分钟 🐌", 'error');
        }
        
        $this->print("\n" . str_repeat("=", 50), 'info');
        $this->print("检测完成! 建议保存此报告供系统管理员参考。", 'info');
        $this->print(str_repeat("=", 50), 'info');
    }
    
    // 辅助方法
    private function print($message, $type = 'info') {
        if ($this->isCLI) {
            // CLI模式，使用颜色
            $colors = [
                'header' => "\033[1;36m", // 青色粗体
                'section' => "\033[1;33m", // 黄色粗体
                'success' => "\033[0;32m", // 绿色
                'warning' => "\033[0;33m", // 黄色
                'error' => "\033[0;31m", // 红色
                'info' => "\033[0;37m", // 白色
                'reset' => "\033[0m" // 重置
            ];
            
            $color = $colors[$type] ?? $colors['info'];
            echo $color . $message . $colors['reset'] . "\n";
        } else {
            // Web模式，使用HTML
            $classes = [
                'header' => 'font-weight: bold; color: #0066cc; font-size: 1.2em;',
                'section' => 'font-weight: bold; color: #ff9900;',
                'success' => 'color: green;',
                'warning' => 'color: orange;',
                'error' => 'color: red;',
                'info' => 'color: #333;'
            ];
            
            $style = $classes[$type] ?? $classes['info'];
            echo "<p style='{$style}'>{$message}</p>\n";
        }
    }
    
    private function findCommand($command) {
        // 优先使用which命令，避免open_basedir警告
        if (function_exists('exec')) {
            $output = [];
            $returnCode = 0;
            @exec("which {$command} 2>/dev/null", $output, $returnCode);
            if ($returnCode === 0 && !empty($output[0])) {
                return $output[0];
            }
        }
        
        // 如果which失败，尝试常见路径（可能会触发open_basedir警告，但功能正常）
        $paths = ['/usr/bin/', '/bin/', '/usr/local/bin/', '/sbin/', '/usr/sbin/'];
        foreach ($paths as $path) {
            $fullPath = $path . $command;
            // 使用@抑制open_basedir警告
            if (@file_exists($fullPath) && @is_executable($fullPath)) {
                return $fullPath;
            }
        }
        
        return false;
    }
    
    private function testBasicPing($ip) {
        if (!function_exists('exec')) {
            return ['success' => false, 'error' => 'exec函数不可用', 'time' => 0];
        }
        
        $startTime = microtime(true);
        
        if (stripos(PHP_OS, 'WIN') === 0) {
            $cmd = "ping -n 1 -w 1000 {$ip}";
        } else {
            $cmd = "ping -c 1 -W 1 {$ip}";
        }
        
        $output = [];
        $returnCode = 0;
        @exec($cmd . ' 2>&1', $output, $returnCode);
        
        $time = round((microtime(true) - $startTime) * 1000, 2);
        
        return [
            'success' => ($returnCode === 0),
            'error' => $returnCode === 0 ? null : 'ping失败',
            'time' => $time
        ];
    }
    
    private function testFping($ips) {
        if (!function_exists('exec')) {
            return ['success' => false, 'error' => 'exec函数不可用', 'time' => 0];
        }
        
        $startTime = microtime(true);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'ping_test_');
        file_put_contents($tempFile, implode("\n", $ips));
        
        $cmd = "fping -t 1000 -f {$tempFile}";
        $output = [];
        $returnCode = 0;
        @exec($cmd . ' 2>&1', $output, $returnCode);
        
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
        
        $time = round((microtime(true) - $startTime) * 1000, 2);
        
        return [
            'success' => ($returnCode <= 1), // fping返回0或1都是正常的
            'error' => ($returnCode <= 1) ? null : 'fping执行失败',
            'time' => $time
        ];
    }
    
    private function testParallelPing($ips) {
        if (!function_exists('proc_open')) {
            return [];
        }
        
        $results = [];
        $processes = [];
        $pipes = [];
        
        foreach ($ips as $ip) {
            if (stripos(PHP_OS, 'WIN') === 0) {
                $cmd = "ping -n 1 -w 1000 {$ip}";
            } else {
                $cmd = "ping -c 1 -W 1 {$ip}";
            }
            
            $descriptorspec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ];
            
            $process = @proc_open($cmd, $descriptorspec, $pipes[$ip]);
            if (is_resource($process)) {
                $processes[$ip] = $process;
                fclose($pipes[$ip][0]);
            }
        }
        
        // 等待进程完成
        $timeout = time() + 3;
        while (!empty($processes) && time() < $timeout) {
            foreach ($processes as $ip => $process) {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    if (isset($pipes[$ip][1])) fclose($pipes[$ip][1]);
                    if (isset($pipes[$ip][2])) fclose($pipes[$ip][2]);
                    $exitCode = proc_close($process);
                    $results[$ip] = ($exitCode === 0);
                    unset($processes[$ip]);
                }
            }
            if (!empty($processes)) {
                usleep(10000);
            }
        }
        
        // 清理剩余进程
        foreach ($processes as $ip => $process) {
            proc_terminate($process);
            if (isset($pipes[$ip][1])) fclose($pipes[$ip][1]);
            if (isset($pipes[$ip][2])) fclose($pipes[$ip][2]);
            proc_close($process);
            $results[$ip] = false;
        }
        
        return $results;
    }
    
    private function parseBytes($size) {
        if (is_numeric($size)) {
            return (int)$size;
        }
        
        $unit = strtolower(substr($size, -1));
        $value = (int)$size;
        
        switch ($unit) {
            case 'g': return $value * 1024 * 1024 * 1024;
            case 'm': return $value * 1024 * 1024;
            case 'k': return $value * 1024;
            default: return $value;
        }
    }
}

// 检查运行模式并设置适当的头部
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/html; charset=UTF-8');
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>环境检测报告</title></head><body>\n";
}

// 执行检测
$checker = new EnvironmentCheckerCLI();
$checker->runAllChecks();

if (php_sapi_name() !== 'cli') {
    echo "</body></html>\n";
}
?>
