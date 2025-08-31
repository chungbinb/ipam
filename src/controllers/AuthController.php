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
        } catch (Exception $e) {
            error_log("AuthController initialization failed: " . $e->getMessage());
            throw new Exception("认证控制器初始化失败: " . $e->getMessage());
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
                http_response_code(500);
                echo json_encode(['code' => 1, 'msg' => '数据库查询错误: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            if (!$user) {
                // 记录登录失败日志
                try {
                    $this->logService->logLogin($username, false);
                } catch (Exception $e) {
                    error_log("Error logging failed login: " . $e->getMessage());
                }
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
                http_response_code(500);
                echo json_encode(['code' => 1, 'msg' => '密码验证错误: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            if (!$isValidPassword) {
                // 记录密码错误日志
                try {
                    $this->logService->logLogin($username, false);
                } catch (Exception $e) {
                    error_log("Error logging failed login (wrong password): " . $e->getMessage());
                }
                http_response_code(401);
                echo json_encode(['code' => 1, 'msg' => '用户名或密码错误'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // 检查账号状态
            if ($user['status'] !== 'active') {
                error_log("User account is not active. Status: " . $user['status']);
                // 记录账号禁用状态登录尝试
                try {
                    $this->logService->logLogin($username, false);
                } catch (Exception $e) {
                    error_log("Error logging failed login (inactive account): " . $e->getMessage());
                }
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
                http_response_code(500);
                echo json_encode(['code' => 1, 'msg' => '创建会话失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // 记录登录成功日志
            try {
                $this->logService->logLogin($username, true);
                error_log("Login successful for user: " . $username);
            } catch (Exception $e) {
                error_log("Error logging successful login: " . $e->getMessage());
                // 不中断登录流程，只记录错误
            }
            
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
        $this->logService->logLogout($username);
        
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
}
