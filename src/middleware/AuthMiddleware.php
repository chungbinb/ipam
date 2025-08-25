<?php
require_once __DIR__ . '/../models/SessionModel.php';

class AuthMiddleware
{
    private $sessionModel;
    
    public function __construct()
    {
        $this->sessionModel = new SessionModel();
    }
    
    public function checkAuth()
    {
        $session = $this->sessionModel->getSession();
        if (!$session) {
            $this->redirectToLogin();
            return false;
        }
        return $session;
    }
    
    private function redirectToLogin()
    {
        // 如果是API请求，返回JSON
        if ($this->isApiRequest()) {
            if (!headers_sent()) {
                header('Content-Type: application/json');
                http_response_code(401);
            }
            echo json_encode(['code' => 401, 'msg' => '未登录或会话已过期']);
            exit;
        }
        
        // 页面请求，重定向到登录页
        if (!headers_sent()) {
            header('Location: /public/login.html');
        }
        exit;
    }
    
    private function isApiRequest()
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return strpos($uri, '/api/') === 0;
    }
}
