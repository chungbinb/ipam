<?php
require_once __DIR__ . '/../models/AccountModel.php';
require_once __DIR__ . '/../models/SessionModel.php';
require_once __DIR__ . '/../services/LogService.php';

class AuthController
{
    private $accountModel;
    private $sessionModel;
    private $logService;
    
    public function __construct()
    {
        try {
            $this->accountModel = new AccountModel();
            $this->sessionModel = new SessionModel();
            $this->logService = new LogService();
            error_log("AuthController initialized successfully");
            
            // 测试LogService是否可用
            $this->testLogService();
        } catch (Exception $e) {
            error_log("AuthController initialization failed: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            throw new Exception("认证控制器初始化失败: " . $e->getMessage());
        }
    }
    
    /**
     * 测试LogService是否正常工作
     */
    private function testLogService()
    {
        try {
            if (!$this->logService) {
                error_log("LogService is null");
                return;
            }
            
            // 测试数据库连接
            error_log("Testing LogService database connection...");
            
            // 尝试调用LogService的方法来测试是否正常
            $reflection = new ReflectionClass($this->logService);
            error_log("LogService class: " . $reflection->getName());
            
            $methods = $reflection->getMethods();
            $methodNames = array_map(function($method) {
                return $method->getName();
            }, $methods);
            error_log("LogService available methods: " . implode(', ', $methodNames));
            
        } catch (Exception $e) {
            error_log("LogService test failed: " . $e->getMessage());
            error_log("LogService test stack trace: " . $e->getTraceAsString());
        }
    }
    
    public function login()
    {
        $this->setJsonHeader();
        
        // 清理任何可能的输出缓冲
        if (ob_get_level()) {
            ob_clean();
        }
        
        try {
            // 记录登录开始
            error_log("Login attempt started at " . date('Y-m-d H:i:s'));
            
            $input = json_decode(file_get_contents('php://input'), true);
            $username = trim($input['username'] ?? '');
            $password = $input['password'] ?? '';
            
            error_log("Login attempt for username: " . $username);
            
            if (!$username || !$password) {
                error_log("Login failed: empty username or password");
                // 尝试记录失败日志
                $this->tryLogLogin($username, false, "空用户名或密码");
                http_response_code(400);
                echo json_encode(['code' => 1, 'msg' => '用户名和密码不能为空'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // 查找用户 - 添加错误检查
            try {
                error_log("Attempting to find user: " . $username);
                $user = $this->accountModel->findByUsername($username);
                error_log("User found: " . ($user ? 'Yes' : 'No'));
            } catch (Exception $e) {
                error_log("Error finding user: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                $this->tryLogLogin($username, false, "数据库查询错误: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['code' => 1, 'msg' => '数据库查询错误: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            if (!$user) {
                // 记录登录失败日志
                $this->tryLogLogin($username, false, "用户不存在");
                http_response_code(401);
                echo json_encode(['code' => 1, 'msg' => '用户名或密码错误'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            error_log("User data: " . json_encode([
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role'],
                'status' => $user['status'],
                'has_password_hash' => !empty($user['password_hash'])
            ]));
            
            // 验证密码 - 添加兼容性检查
            $isValidPassword = false;
            try {
                if (!empty($user['password_hash'])) {
                    error_log("Verifying password for user: " . $username);
                    // 尝试标准的password_verify
                    $isValidPassword = password_verify($password, $user['password_hash']);
                    error_log("Password verify result: " . ($isValidPassword ? 'Valid' : 'Invalid'));
                    
                    // 如果标准验证失败，检查是否是明文密码（开发环境兼容）
                    if (!$isValidPassword && $password === $user['password_hash']) {
                        $isValidPassword = true;
                        error_log("Password is plain text, updating to hash");
                        
                        // 如果是明文密码，更新为哈希密码
                        try {
                            $newHash = password_hash($password, PASSWORD_DEFAULT);
                            $this->accountModel->update($user['id'], ['password_hash' => $newHash]);
                            error_log("Password hash updated successfully");
                        } catch (Exception $e) {
                            error_log("Error updating password hash: " . $e->getMessage());
                        }
                    }
                } else {
                    error_log("No password hash found for user: " . $username);
                }
            } catch (Exception $e) {
                error_log("Error during password verification: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                $this->tryLogLogin($username, false, "密码验证错误: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['code' => 1, 'msg' => '密码验证错误: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            if (!$isValidPassword) {
                // 记录密码错误日志
                $this->tryLogLogin($username, false, "密码错误");
                http_response_code(401);
                echo json_encode(['code' => 1, 'msg' => '用户名或密码错误'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // 检查账号状态
            if ($user['status'] !== 'active') {
                error_log("User account is not active. Status: " . $user['status']);
                // 记录账号禁用状态登录尝试
                $this->tryLogLogin($username, false, "账号状态: " . $user['status']);
                http_response_code(403);
                echo json_encode(['code' => 1, 'msg' => '账号已被禁用'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // 创建会话（会自动设置 Cookie）
            try {
                error_log("Creating session for user: " . $username);
                $sessionId = $this->sessionModel->createSession(
                    $user['id'], 
                    $user['username'], 
                    $user['role']
                );
                error_log("Session created successfully. Session ID: " . $sessionId);
            } catch (Exception $e) {
                error_log("Error creating session: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                $this->tryLogLogin($username, false, "创建会话失败: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['code' => 1, 'msg' => '创建会话失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // 记录登录成功日志
            $this->tryLogLogin($username, true, "登录成功");
            error_log("Login successful for user: " . $username);
            
            echo json_encode([
                'code' => 0, 
                'msg' => '登录成功',
                'data' => [
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'role' => $user['role']
                    ]
                    // 不再返回 session_id，因为已经设置在 Cookie 中
                ]
            ], JSON_UNESCAPED_UNICODE);
            
        } catch (Exception $e) {
            error_log("Unexpected login error: " . $e->getMessage());
            error_log("File: " . $e->getFile() . " Line: " . $e->getLine());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            // 记录请求详情用于调试
            error_log("Request details: " . json_encode([
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
                'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]));
            
            // 尝试记录系统错误日志
            $this->tryLogLogin($username ?? 'unknown', false, "系统错误: " . $e->getMessage());
            
            http_response_code(500);
            echo json_encode([
                'code' => 1, 
                'msg' => '登录过程中发生错误',
                'debug' => [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
    
    public function logout()
    {
        $this->setJsonHeader();
        
        // 获取当前用户信息用于日志记录
        $session = $this->sessionModel->getSession();
        $username = $session ? $session['username'] : 'unknown';
        
        // 销毁会话
        $this->sessionModel->destroySession();
        
        // 记录注销日志
        $this->tryLogLogout($username);
        
        echo json_encode(['code' => 0, 'msg' => '退出成功']);
        exit;
    }
    
    public function check()
    {
        $this->setJsonHeader();
        
        $session = $this->sessionModel->getSession();
        if ($session) {
            echo json_encode([
                'code' => 0,
                'data' => [
                    'logged_in' => true,
                    'user' => [
                        'id' => $session['user_id'],
                        'username' => $session['username'],
                        'role' => $session['role']
                    ]
                ]
            ]);
        } else {
            echo json_encode([
                'code' => 0,
                'data' => ['logged_in' => false]
            ]);
        }
        exit;
    }
    
    private function setJsonHeader()
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
    }
    
    /**
     * 安全地尝试记录登录日志
     */
    private function tryLogLogin($username, $success, $details = '')
    {
        try {
            error_log("=== 开始记录登录日志 ===");
            error_log("Attempting to log login: username={$username}, success=" . ($success ? 'true' : 'false') . ", details={$details}");
            
            if (!$this->logService) {
                error_log("ERROR: LogService is not available (null)");
                $this->fallbackLogLogin($username, $success, $details);
                return;
            }
            
            // 获取客户端信息
            $clientInfo = $this->getClientInfo();
            error_log("Client info: " . json_encode($clientInfo));
            
            // 检查LogService是否有logLogin方法
            if (!method_exists($this->logService, 'logLogin')) {
                error_log("ERROR: LogService does not have logLogin method");
                $this->fallbackLogLogin($username, $success, $details);
                return;
            }
            
            // 调用LogService记录日志
            error_log("Calling LogService::logLogin...");
            $result = $this->logService->logLogin($username, $success);
            error_log("LogService::logLogin result: " . ($result ? 'true' : 'false'));
            
            if ($result) {
                error_log("Login log recorded successfully to database");
            } else {
                error_log("Login log recording returned false");
                $this->fallbackLogLogin($username, $success, $details);
            }
            
        } catch (Exception $e) {
            error_log("ERROR: Failed to log login attempt: " . $e->getMessage());
            error_log("LogService error file: " . $e->getFile() . " line: " . $e->getLine());
            error_log("LogService error stack trace: " . $e->getTraceAsString());
            
            // 使用备用日志记录
            $this->fallbackLogLogin($username, $success, $details);
        }
        
        error_log("=== 登录日志记录完成 ===");
    }
    
    /**
     * 安全地尝试记录注销日志
     */
    private function tryLogLogout($username)
    {
        try {
            error_log("=== 开始记录注销日志 ===");
            error_log("Attempting to log logout: username={$username}");
            
            if (!$this->logService) {
                error_log("ERROR: LogService is not available (null)");
                $this->fallbackLogLogout($username);
                return;
            }
            
            // 检查LogService是否有logLogout方法
            if (!method_exists($this->logService, 'logLogout')) {
                error_log("ERROR: LogService does not have logLogout method");
                $this->fallbackLogLogout($username);
                return;
            }
            
            // 调用LogService记录日志
            error_log("Calling LogService::logLogout...");
            $result = $this->logService->logLogout($username);
            error_log("LogService::logLogout result: " . ($result ? 'true' : 'false'));
            
            if ($result) {
                error_log("Logout log recorded successfully to database");
            } else {
                error_log("Logout log recording returned false");
                $this->fallbackLogLogout($username);
            }
            
        } catch (Exception $e) {
            error_log("ERROR: Failed to log logout attempt: " . $e->getMessage());
            error_log("LogService error file: " . $e->getFile() . " line: " . $e->getLine());
            error_log("LogService error stack trace: " . $e->getTraceAsString());
            
            // 使用备用日志记录
            $this->fallbackLogLogout($username);
        }
        
        error_log("=== 注销日志记录完成 ===");
    }
    
    /**
     * 备用登录日志记录（文件日志）
     */
    private function fallbackLogLogin($username, $success, $details)
    {
        $clientInfo = $this->getClientInfo();
        $logMessage = date('Y-m-d H:i:s') . " - Login attempt: username={$username}, success=" . ($success ? 'SUCCESS' : 'FAILED') . ", details={$details}, IP={$clientInfo['ip']}, UserAgent={$clientInfo['user_agent']}";
        error_log("FALLBACK LOGIN LOG: " . $logMessage);
        
        // 尝试写入到专门的日志文件
        $this->writeToLogFile('login', $logMessage);
    }
    
    /**
     * 备用注销日志记录（文件日志）
     */
    private function fallbackLogLogout($username)
    {
        $clientInfo = $this->getClientInfo();
        $logMessage = date('Y-m-d H:i:s') . " - Logout: username={$username}, IP={$clientInfo['ip']}, UserAgent={$clientInfo['user_agent']}";
        error_log("FALLBACK LOGOUT LOG: " . $logMessage);
        
        // 尝试写入到专门的日志文件
        $this->writeToLogFile('logout', $logMessage);
    }
    
    /**
     * 获取客户端信息
     */
    private function getClientInfo()
    {
        return [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * 写入到专门的日志文件
     */
    private function writeToLogFile($type, $message)
    {
        try {
            $logDir = __DIR__ . '/../../storage/logs';
            if (!file_exists($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            $logFile = $logDir . '/' . $type . '_' . date('Y-m-d') . '.log';
            $fullMessage = "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL;
            
            file_put_contents($logFile, $fullMessage, FILE_APPEND | LOCK_EX);
            error_log("Fallback log written to: " . $logFile);
        } catch (Exception $e) {
            error_log("Failed to write fallback log file: " . $e->getMessage());
        }
    }
    
    /**
     * 测试数据库连接和日志表
     */
    public function testDatabaseLogging()
    {
        $this->setJsonHeader();
        
        try {
            error_log("=== 测试数据库日志功能 ===");
            
            // 测试LogService
            if (!$this->logService) {
                throw new Exception("LogService not initialized");
            }
            
            // 尝试记录一条测试日志
            $testResult = $this->logService->logLogin('test_user', true);
            
            echo json_encode([
                'code' => 0,
                'msg' => '数据库日志测试完成',
                'data' => [
                    'logservice_available' => $this->logService ? true : false,
                    'test_result' => $testResult,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ], JSON_UNESCAPED_UNICODE);
            
        } catch (Exception $e) {
            error_log("Database logging test failed: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            http_response_code(500);
            echo json_encode([
                'code' => 1,
                'msg' => '数据库日志测试失败: ' . $e->getMessage(),
                'debug' => [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
}
