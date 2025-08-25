<?php

class SessionModel
{
    private $sessionDir;
    
    public function __construct()
    {
        $this->sessionDir = dirname(__DIR__, 2) . '/storage/sessions';
        if (!is_dir($this->sessionDir)) {
            @mkdir($this->sessionDir, 0777, true);
        }
    }
    
    public function createSession($userId, $username, $role)
    {
        $sessionId = $this->generateSessionId();
        $sessionData = [
            'user_id' => $userId,
            'username' => $username,
            'role' => $role,
            'login_time' => date('Y-m-d H:i:s'),
            'last_activity' => time(),
            'session_id' => $sessionId
        ];
        
        // 保存到文件（以 sessionId 为文件名）
        $sessionFile = $this->sessionDir . '/' . $sessionId . '.json';
        $this->saveSessionToFile($sessionFile, $sessionData);
        
        // 设置 Cookie（7天过期）
        $this->setSessionCookie($sessionId);
        
        return $sessionId;
    }
    
    public function getSession()
    {
        $sessionId = $this->getSessionIdFromCookie();
        if (!$sessionId) {
            return null;
        }
        
        $sessionFile = $this->sessionDir . '/' . $sessionId . '.json';
        if (!file_exists($sessionFile)) {
            // 会话文件不存在，清除 Cookie
            $this->clearSessionCookie();
            return null;
        }
        
        $data = @file_get_contents($sessionFile);
        if (!$data) {
            $this->clearSessionCookie();
            return null;
        }
        
        $session = json_decode($data, true);
        if (!$session) {
            $this->clearSessionCookie();
            return null;
        }
        
        // 检查会话是否过期（7天）
        if (time() - $session['last_activity'] > 604800) { // 7天 = 7 * 24 * 60 * 60
            $this->destroySession();
            return null;
        }
        
        // 更新最后活动时间
        $session['last_activity'] = time();
        $this->saveSessionToFile($sessionFile, $session);
        
        return $session;
    }
    
    public function destroySession()
    {
        $sessionId = $this->getSessionIdFromCookie();
        if ($sessionId) {
            // 删除会话文件
            $sessionFile = $this->sessionDir . '/' . $sessionId . '.json';
            if (file_exists($sessionFile)) {
                @unlink($sessionFile);
            }
        }
        
        // 清除 Cookie
        $this->clearSessionCookie();
    }
    
    private function saveSessionToFile($file, $data)
    {
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE));
    }
    
    private function generateSessionId()
    {
        return bin2hex(random_bytes(32));
    }
    
    private function setSessionCookie($sessionId)
    {
        // 设置 Cookie，7天过期，HttpOnly 和 Secure
        $expire = time() + 604800; // 7天
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        
        setcookie(
            'ITAM_SESSION', 
            $sessionId, 
            $expire, 
            '/', 
            '', 
            $secure, 
            true // HttpOnly
        );
    }
    
    private function getSessionIdFromCookie()
    {
        return $_COOKIE['ITAM_SESSION'] ?? null;
    }
    
    private function clearSessionCookie()
    {
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        
        setcookie(
            'ITAM_SESSION', 
            '', 
            time() - 3600, // 设置为过去的时间
            '/', 
            '', 
            $secure, 
            true
        );
    }
}
