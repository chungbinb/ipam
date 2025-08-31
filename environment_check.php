<?php
/**
 * IT资产管理系统 - 环境检测工具
 * 检测ping扫描功能所需的系统环境和依赖
 */

class EnvironmentChecker {
    private $results = [];
    private $warnings = [];
    private $errors = [];
    
    public function __construct() {
        echo "<h1>IT资产管理系统 - 环境检测报告</h1>\n";
        echo "<style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .success { color: green; font-weight: bold; }
            .warning { color: orange; font-weight: bold; }
            .error { color: red; font-weight: bold; }
            .info { color: blue; }
            table { border-collapse: collapse; width: 100%; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
            th { background-color: #f2f2f2; }
            .recommendation { background-color: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 10px 0; }
        </style>\n";
    }
    
    public function runAllChecks() {
        echo "<h2>🔍 开始环境检测...</h2>\n";
        
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
    
    private function checkPhpVersion() {
        echo "<h3>📋 PHP版本检测</h3>\n";
        $version = phpversion();
        $minVersion = '7.4.0';
        
        echo "<table>\n";
        echo "<tr><th>项目</th><th>当前值</th><th>要求</th><th>状态</th></tr>\n";
        
        if (version_compare($version, $minVersion, '>=')) {
            echo "<tr><td>PHP版本</td><td>{$version}</td><td>>= {$minVersion}</td><td class='success'>✅ 通过</td></tr>\n";
            $this->results['php_version'] = true;
        } else {
            echo "<tr><td>PHP版本</td><td>{$version}</td><td>>= {$minVersion}</td><td class='error'>❌ 版本过低</td></tr>\n";
            $this->errors[] = "PHP版本过低，建议升级到7.4或更高版本";
            $this->results['php_version'] = false;
        }
        
        echo "<tr><td>操作系统</td><td>" . PHP_OS . "</td><td>Linux/Unix/Windows</td><td class='info'>ℹ️ 信息</td></tr>\n";
        echo "<tr><td>SAPI</td><td>" . php_sapi_name() . "</td><td>任意</td><td class='info'>ℹ️ 信息</td></tr>\n";
        echo "</table>\n";
    }
    
    private function checkPhpExtensions() {
        echo "<h3>🔧 PHP扩展检测</h3>\n";
        
        $requiredExtensions = [
            'json' => '处理JSON数据',
            'pdo' => '数据库连接',
            'pcntl' => '进程控制（推荐）',
            'posix' => 'POSIX函数（推荐）',
        ];
        
        $optionalExtensions = [
            'curl' => 'HTTP请求',
            'mbstring' => '多字节字符串处理',
            'openssl' => 'SSL/TLS支持',
        ];
        
        echo "<table>\n";
        echo "<tr><th>扩展名</th><th>用途</th><th>状态</th><th>备注</th></tr>\n";
        
        foreach ($requiredExtensions as $ext => $purpose) {
            if (extension_loaded($ext)) {
                echo "<tr><td>{$ext}</td><td>{$purpose}</td><td class='success'>✅ 已安装</td><td>-</td></tr>\n";
                $this->results['ext_' . $ext] = true;
            } else {
                echo "<tr><td>{$ext}</td><td>{$purpose}</td><td class='warning'>⚠️ 未安装</td><td>";
                if ($ext === 'pcntl' || $ext === 'posix') {
                    echo "可选，但会影响ping性能";
                    $this->warnings[] = "{$ext}扩展未安装，可能影响ping扫描性能";
                } else {
                    echo "必需扩展";
                    $this->errors[] = "{$ext}扩展未安装，系统可能无法正常工作";
                }
                echo "</td></tr>\n";
                $this->results['ext_' . $ext] = false;
            }
        }
        
        foreach ($optionalExtensions as $ext => $purpose) {
            if (extension_loaded($ext)) {
                echo "<tr><td>{$ext}</td><td>{$purpose}</td><td class='success'>✅ 已安装</td><td>可选扩展</td></tr>\n";
            } else {
                echo "<tr><td>{$ext}</td><td>{$purpose}</td><td class='info'>ℹ️ 未安装</td><td>可选扩展，不影响基本功能</td></tr>\n";
            }
        }
        
        echo "</table>\n";
    }
    
    private function checkSystemCommands() {
        echo "<h3>⚡ 系统命令检测</h3>\n";
        
        $commands = [
            'ping' => ['必需', 'IP连通性测试的基础命令'],
            'fping' => ['推荐', '高性能批量ping工具，大幅提升扫描速度'],
            'nmap' => ['可选', '网络扫描工具，可用于高级网络发现'],
            'which' => ['推荐', '用于查找命令路径'],
        ];
        
        echo "<table>\n";
        echo "<tr><th>命令</th><th>重要性</th><th>用途</th><th>状态</th><th>路径</th></tr>\n";
        
        foreach ($commands as $cmd => $info) {
            $path = $this->findCommand($cmd);
            if ($path) {
                echo "<tr><td>{$cmd}</td><td>{$info[0]}</td><td>{$info[1]}</td><td class='success'>✅ 可用</td><td>{$path}</td></tr>\n";
                $this->results['cmd_' . $cmd] = true;
            } else {
                $status = ($info[0] === '必需') ? 'error' : (($info[0] === '推荐') ? 'warning' : 'info');
                $icon = ($info[0] === '必需') ? '❌' : (($info[0] === '推荐') ? '⚠️' : 'ℹ️');
                echo "<tr><td>{$cmd}</td><td>{$info[0]}</td><td>{$info[1]}</td><td class='{$status}'>{$icon} 不可用</td><td>-</td></tr>\n";
                
                if ($info[0] === '必需') {
                    $this->errors[] = "{$cmd}命令不可用，ping功能无法工作";
                } elseif ($info[0] === '推荐') {
                    $this->warnings[] = "{$cmd}命令不可用，建议安装以提升性能";
                }
                $this->results['cmd_' . $cmd] = false;
            }
        }
        
        echo "</table>\n";
    }
    
    private function checkFilePermissions() {
        echo "<h3>📁 文件权限检测</h3>\n";
        
        $paths = [
            __DIR__ . '/storage/logs' => '日志目录',
            __DIR__ . '/storage/sessions' => '会话目录', 
            sys_get_temp_dir() => '系统临时目录',
        ];
        
        echo "<table>\n";
        echo "<tr><th>路径</th><th>用途</th><th>读权限</th><th>写权限</th><th>状态</th></tr>\n";
        
        foreach ($paths as $path => $purpose) {
            $readable = is_readable($path);
            $writable = is_writable($path);
            
            $readStatus = $readable ? '✅' : '❌';
            $writeStatus = $writable ? '✅' : '❌';
            $overallStatus = ($readable && $writable) ? 'success' : 'error';
            $overallIcon = ($readable && $writable) ? '✅ 正常' : '❌ 权限不足';
            
            echo "<tr><td>{$path}</td><td>{$purpose}</td><td>{$readStatus}</td><td>{$writeStatus}</td><td class='{$overallStatus}'>{$overallIcon}</td></tr>\n";
            
            if (!$readable || !$writable) {
                $this->errors[] = "目录权限不足: {$path}";
            }
        }
        
        echo "</table>\n";
    }
    
    private function checkNetworkCommands() {
        echo "<h3>🌐 网络功能测试</h3>\n";
        
        echo "<table>\n";
        echo "<tr><th>测试项目</th><th>结果</th><th>状态</th><th>备注</th></tr>\n";
        
        // 测试基本ping功能
        $testIp = '8.8.8.8'; // Google DNS
        $pingResult = $this->testBasicPing($testIp);
        
        if ($pingResult['success']) {
            echo "<tr><td>基本ping测试</td><td>成功ping {$testIp}</td><td class='success'>✅ 通过</td><td>用时: {$pingResult['time']}ms</td></tr>\n";
            $this->results['basic_ping'] = true;
        } else {
            echo "<tr><td>基本ping测试</td><td>ping {$testIp} 失败</td><td class='error'>❌ 失败</td><td>{$pingResult['error']}</td></tr>\n";
            $this->errors[] = "基本ping功能不可用: " . $pingResult['error'];
            $this->results['basic_ping'] = false;
        }
        
        // 测试fping功能
        if ($this->results['cmd_fping']) {
            $fpingResult = $this->testFping([$testIp, '8.8.4.4']);
            if ($fpingResult['success']) {
                echo "<tr><td>fping批量测试</td><td>成功测试多个IP</td><td class='success'>✅ 通过</td><td>用时: {$fpingResult['time']}ms</td></tr>\n";
                $this->results['fping_test'] = true;
            } else {
                echo "<tr><td>fping批量测试</td><td>fping测试失败</td><td class='warning'>⚠️ 失败</td><td>{$fpingResult['error']}</td></tr>\n";
                $this->warnings[] = "fping功能异常: " . $fpingResult['error'];
                $this->results['fping_test'] = false;
            }
        } else {
            echo "<tr><td>fping批量测试</td><td>fping不可用</td><td class='info'>ℹ️ 跳过</td><td>fping未安装</td></tr>\n";
            $this->results['fping_test'] = false;
        }
        
        echo "</table>\n";
    }
    
    private function checkProcessControl() {
        echo "<h3>⚙️ 进程控制检测</h3>\n";
        
        echo "<table>\n";
        echo "<tr><th>功能</th><th>状态</th><th>备注</th></tr>\n";
        
        // 检查proc_open
        if (function_exists('proc_open')) {
            echo "<tr><td>proc_open()</td><td class='success'>✅ 可用</td><td>支持并行ping</td></tr>\n";
            $this->results['proc_open'] = true;
        } else {
            echo "<tr><td>proc_open()</td><td class='error'>❌ 不可用</td><td>无法进行并行ping，性能会很差</td></tr>\n";
            $this->errors[] = "proc_open函数不可用，ping扫描将非常缓慢";
            $this->results['proc_open'] = false;
        }
        
        // 检查exec
        if (function_exists('exec')) {
            echo "<tr><td>exec()</td><td class='success'>✅ 可用</td><td>支持执行系统命令</td></tr>\n";
            $this->results['exec'] = true;
        } else {
            echo "<tr><td>exec()</td><td class='error'>❌ 不可用</td><td>无法执行ping命令</td></tr>\n";
            $this->errors[] = "exec函数不可用，ping功能无法工作";
            $this->results['exec'] = false;
        }
        
        // 检查shell_exec
        if (function_exists('shell_exec')) {
            echo "<tr><td>shell_exec()</td><td class='success'>✅ 可用</td><td>备用命令执行方式</td></tr>\n";
        } else {
            echo "<tr><td>shell_exec()</td><td class='warning'>⚠️ 不可用</td><td>部分功能可能受限</td></tr>\n";
        }
        
        // 检查fastcgi_finish_request
        if (function_exists('fastcgi_finish_request')) {
            echo "<tr><td>fastcgi_finish_request()</td><td class='success'>✅ 可用</td><td>支持后台任务处理</td></tr>\n";
            $this->results['fastcgi_finish'] = true;
        } else {
            echo "<tr><td>fastcgi_finish_request()</td><td class='warning'>⚠️ 不可用</td><td>前端可能需要等待扫描完成</td></tr>\n";
            $this->warnings[] = "fastcgi_finish_request不可用，前端体验可能受影响";
            $this->results['fastcgi_finish'] = false;
        }
        
        echo "</table>\n";
    }
    
    private function checkSystemLimits() {
        echo "<h3>🔢 系统限制检测</h3>\n";
        
        echo "<table>\n";
        echo "<tr><th>配置项</th><th>当前值</th><th>建议值</th><th>状态</th></tr>\n";
        
        $maxExecutionTime = ini_get('max_execution_time');
        echo "<tr><td>max_execution_time</td><td>{$maxExecutionTime}s</td><td>>= 300s</td><td>";
        if ($maxExecutionTime == 0 || $maxExecutionTime >= 300) {
            echo "class='success'>✅ 充足</td></tr>\n";
        } else {
            echo "class='warning'>⚠️ 可能不足</td></tr>\n";
            $this->warnings[] = "脚本执行时间限制较短，大网段扫描可能超时";
        }
        
        $memoryLimit = ini_get('memory_limit');
        echo "<tr><td>memory_limit</td><td>{$memoryLimit}</td><td>>= 128M</td><td>";
        $memoryBytes = $this->parseBytes($memoryLimit);
        if ($memoryBytes >= 128 * 1024 * 1024) {
            echo "class='success'>✅ 充足</td></tr>\n";
        } else {
            echo "class='warning'>⚠️ 可能不足</td></tr>\n";
            $this->warnings[] = "内存限制较小，大批量操作可能失败";
        }
        
        $maxInputVars = ini_get('max_input_vars');
        echo "<tr><td>max_input_vars</td><td>{$maxInputVars}</td><td>>= 1000</td><td>";
        if ($maxInputVars >= 1000) {
            echo "class='success'>✅ 充足</td></tr>\n";
        } else {
            echo "class='info'>ℹ️ 一般</td></tr>\n";
        }
        
        echo "</table>\n";
    }
    
    private function performPingTest() {
        echo "<h3>🚀 Ping性能测试</h3>\n";
        
        $testIps = ['8.8.8.8', '8.8.4.4', '1.1.1.1', '114.114.114.114'];
        
        echo "<h4>串行ping测试 (传统方式)</h4>\n";
        $startTime = microtime(true);
        $serialResults = [];
        
        foreach ($testIps as $ip) {
            $result = $this->testBasicPing($ip);
            $serialResults[$ip] = $result['success'];
        }
        
        $serialTime = round((microtime(true) - $startTime) * 1000, 2);
        echo "<p>串行ping {count($testIps)} 个IP用时: <strong>{$serialTime}ms</strong></p>\n";
        
        if ($this->results['proc_open']) {
            echo "<h4>并行ping测试 (优化方式)</h4>\n";
            $startTime = microtime(true);
            $parallelResults = $this->testParallelPing($testIps);
            $parallelTime = round((microtime(true) - $startTime) * 1000, 2);
            
            echo "<p>并行ping {count($testIps)} 个IP用时: <strong>{$parallelTime}ms</strong></p>\n";
            
            if ($parallelTime < $serialTime) {
                $improvement = round(($serialTime - $parallelTime) / $serialTime * 100, 1);
                echo "<p class='success'>✅ 并行ping性能提升: {$improvement}%</p>\n";
            }
        }
        
        if ($this->results['cmd_fping']) {
            echo "<h4>fping测试 (最优方式)</h4>\n";
            $startTime = microtime(true);
            $fpingResult = $this->testFping($testIps);
            $fpingTime = round((microtime(true) - $startTime) * 1000, 2);
            
            echo "<p>fping {count($testIps)} 个IP用时: <strong>{$fpingTime}ms</strong></p>\n";
            
            if ($fpingTime < $serialTime) {
                $improvement = round(($serialTime - $fpingTime) / $serialTime * 100, 1);
                echo "<p class='success'>✅ fping性能提升: {$improvement}%</p>\n";
            }
        }
    }
    
    private function displaySummary() {
        echo "<h2>📊 检测结果汇总</h2>\n";
        
        $totalChecks = count($this->results);
        $passedChecks = count(array_filter($this->results));
        $passRate = round($passedChecks / $totalChecks * 100, 1);
        
        echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;'>\n";
        echo "<h3>总体评估</h3>\n";
        echo "<p><strong>通过率:</strong> {$passedChecks}/{$totalChecks} ({$passRate}%)</p>\n";
        echo "<p><strong>错误:</strong> " . count($this->errors) . " 个</p>\n";
        echo "<p><strong>警告:</strong> " . count($this->warnings) . " 个</p>\n";
        
        if (count($this->errors) == 0) {
            if (count($this->warnings) == 0) {
                echo "<p class='success'>🎉 系统环境完全满足要求，ping扫描功能可以正常使用！</p>\n";
            } else {
                echo "<p class='warning'>⚠️ 系统基本可用，但有一些性能优化建议。</p>\n";
            }
        } else {
            echo "<p class='error'>❌ 系统环境存在问题，需要修复后才能正常使用ping扫描功能。</p>\n";
        }
        echo "</div>\n";
        
        if (!empty($this->errors)) {
            echo "<h3 class='error'>🚨 必须解决的问题:</h3>\n";
            echo "<ul>\n";
            foreach ($this->errors as $error) {
                echo "<li class='error'>{$error}</li>\n";
            }
            echo "</ul>\n";
        }
        
        if (!empty($this->warnings)) {
            echo "<h3 class='warning'>⚠️ 建议改进的项目:</h3>\n";
            echo "<ul>\n";
            foreach ($this->warnings as $warning) {
                echo "<li class='warning'>{$warning}</li>\n";
            }
            echo "</ul>\n";
        }
    }
    
    private function generateRecommendations() {
        echo "<h2>💡 优化建议</h2>\n";
        
        echo "<div class='recommendation'>\n";
        echo "<h3>性能优化建议</h3>\n";
        echo "<ol>\n";
        
        if (!$this->results['cmd_fping']) {
            echo "<li><strong>安装fping:</strong> 这是最重要的性能优化。\n";
            echo "<code>sudo yum install fping</code> (CentOS/RHEL) 或 <code>sudo apt-get install fping</code> (Ubuntu/Debian)</li>\n";
        }
        
        if (!$this->results['proc_open']) {
            echo "<li><strong>启用proc_open:</strong> 检查php.ini中的disable_functions配置，移除proc_open。</li>\n";
        }
        
        if (!$this->results['fastcgi_finish']) {
            echo "<li><strong>使用PHP-FPM:</strong> 建议使用nginx + php-fpm组合以支持后台任务处理。</li>\n";
        }
        
        echo "<li><strong>调整PHP配置:</strong> 在php.ini中设置:\n";
        echo "<ul>\n";
        echo "<li><code>max_execution_time = 300</code> (或更高)</li>\n";
        echo "<li><code>memory_limit = 256M</code> (或更高)</li>\n";
        echo "<li>移除exec、shell_exec、proc_open等函数的限制</li>\n";
        echo "</ul></li>\n";
        
        echo "<li><strong>防火墙配置:</strong> 确保服务器可以向外发送ICMP包（ping）。</li>\n";
        
        echo "<li><strong>权限设置:</strong> 确保Web服务器用户有执行ping命令的权限。</li>\n";
        
        echo "</ol>\n";
        echo "</div>\n";
        
        echo "<div class='recommendation'>\n";
        echo "<h3>预期性能表现</h3>\n";
        echo "<ul>\n";
        
        if ($this->results['cmd_fping']) {
            echo "<li><strong>使用fping:</strong> 扫描254个IP约需要5-10秒</li>\n";
        } elseif ($this->results['proc_open']) {
            echo "<li><strong>使用并行ping:</strong> 扫描254个IP约需要15-30秒</li>\n";
        } else {
            echo "<li><strong>使用串行ping:</strong> 扫描254个IP约需要3-5分钟（非常慢）</li>\n";
        }
        
        echo "</ul>\n";
        echo "</div>\n";
    }
    
    // 辅助方法
    private function findCommand($command) {
        $paths = ['/usr/bin/', '/bin/', '/usr/local/bin/', '/sbin/', '/usr/sbin/'];
        
        // 首先尝试使用which
        if (function_exists('exec')) {
            $output = [];
            $returnCode = 0;
            @exec("which {$command} 2>/dev/null", $output, $returnCode);
            if ($returnCode === 0 && !empty($output[0])) {
                return $output[0];
            }
        }
        
        // 手动检查常见路径
        foreach ($paths as $path) {
            $fullPath = $path . $command;
            if (file_exists($fullPath) && is_executable($fullPath)) {
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
        
        if ($returnCode === 0) {
            return ['success' => true, 'error' => null, 'time' => $time];
        } else {
            return ['success' => false, 'error' => implode(' ', $output), 'time' => $time];
        }
    }
    
    private function testFping($ips) {
        if (!$this->results['cmd_fping'] || !function_exists('exec')) {
            return ['success' => false, 'error' => 'fping不可用或exec函数被禁用', 'time' => 0];
        }
        
        $startTime = microtime(true);
        
        $tempFile = tempnam(sys_get_temp_dir(), 'ping_test_');
        file_put_contents($tempFile, implode("\n", $ips));
        
        $cmd = "fping -t 1000 -f {$tempFile}";
        $output = [];
        $returnCode = 0;
        @exec($cmd . ' 2>&1', $output, $returnCode);
        
        unlink($tempFile);
        
        $time = round((microtime(true) - $startTime) * 1000, 2);
        
        // fping的返回码为0表示所有IP都通，1表示部分通，2表示错误
        if ($returnCode <= 1) {
            return ['success' => true, 'error' => null, 'time' => $time];
        } else {
            return ['success' => false, 'error' => implode(' ', $output), 'time' => $time];
        }
    }
    
    private function testParallelPing($ips) {
        if (!function_exists('proc_open')) {
            return [];
        }
        
        $results = [];
        $processes = [];
        $pipes = [];
        
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        
        // 启动所有ping进程
        foreach ($ips as $ip) {
            if (stripos(PHP_OS, 'WIN') === 0) {
                $cmd = "ping -n 1 -w 1000 {$ip}";
            } else {
                $cmd = "ping -c 1 -W 1 {$ip}";
            }
            
            $process = proc_open($cmd, $descriptorspec, $pipes[$ip]);
            if (is_resource($process)) {
                $processes[$ip] = $process;
                fclose($pipes[$ip][0]);
            }
        }
        
        // 等待所有进程完成
        $timeout = time() + 5;
        while (!empty($processes) && time() < $timeout) {
            foreach ($processes as $ip => $process) {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    fclose($pipes[$ip][1]);
                    fclose($pipes[$ip][2]);
                    $exitCode = proc_close($process);
                    $results[$ip] = ($exitCode === 0);
                    unset($processes[$ip]);
                    unset($pipes[$ip]);
                }
            }
            if (!empty($processes)) {
                usleep(10000);
            }
        }
        
        // 清理剩余进程
        foreach ($processes as $ip => $process) {
            proc_terminate($process);
            fclose($pipes[$ip][1]);
            fclose($pipes[$ip][2]);
            proc_close($process);
            $results[$ip] = false;
        }
        
        return $results;
    }
    
    private function parseBytes($size) {
        if (is_numeric($size)) {
            return $size;
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

// 执行检测
$checker = new EnvironmentChecker();
$checker->runAllChecks();

echo "<hr><p><small>检测完成时间: " . date('Y-m-d H:i:s') . "</small></p>\n";
echo "<p><small>建议将此报告保存并发送给系统管理员以便进行环境优化。</small></p>\n";
?>
