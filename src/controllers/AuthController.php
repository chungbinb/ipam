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
            $input = json_decode(file_get_contents('php://input'), true);
            $username = trim($input['username'] ?? '');
            $password = $input['password'] ?? '';
            
            if (!$username || !$password) {
                http_response_code(400);
                echo json_encode(['code' => 1, 'msg' => '用户名和密码不能为空'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // 查找用户
            $user = $this->accountModel->findByUsername($username);
            
            if (!$user) {
                // 记录登录失败日志
                $this->logService->logLogin($username, false);
                http_response_code(401);
                echo json_encode(['code' => 1, 'msg' => '用户名或密码错误'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // 验证密码 - 添加兼容性检查
            $isValidPassword = false;
            if (!empty($user['password_hash'])) {
                // 尝试标准的password_verify
                $isValidPassword = password_verify($password, $user['password_hash']);
                
                // 如果标准验证失败，检查是否是明文密码（开发环境兼容）
                if (!$isValidPassword && $password === $user['password_hash']) {
                    $isValidPassword = true;
                    
                    // 如果是明文密码，更新为哈希密码
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $this->accountModel->update($user['id'], ['password_hash' => $newHash]);
                }
            }
            
            if (!$isValidPassword) {
                // 记录密码错误日志
                $this->logService->logLogin($username, false);
                http_response_code(401);
                echo json_encode(['code' => 1, 'msg' => '用户名或密码错误'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // 检查账号状态
            if ($user['status'] !== 'active') {
                // 记录账号禁用状态登录尝试
                $this->logService->logLogin($username, false);
                http_response_code(403);
                echo json_encode(['code' => 1, 'msg' => '账号已被禁用'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // 创建会话（会自动设置 Cookie）
            $sessionId = $this->sessionModel->createSession(
                $user['id'], 
                $user['username'], 
                $user['role']
            );
            
            // 记录登录成功日志
            $this->logService->logLogin($username, true);
            
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
            error_log("Login error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['code' => 1, 'msg' => '登录过程中发生错误'], JSON_UNESCAPED_UNICODE);
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
