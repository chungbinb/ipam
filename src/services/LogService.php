<?php

require_once __DIR__ . '/../models/LogModel.php';
require_once __DIR__ . '/../models/SessionModel.php';

/**
 * 日志服务类 - 统一管理系统日志记录
 */
class LogService
{
    private $logModel;
    private $sessionModel;

    public function __construct()
    {
        $this->logModel = new LogModel();
        $this->sessionModel = new SessionModel();
    }

    /**
     * 记录登录日志
     * @param string $username 用户名
     * @param bool $success 是否成功
     * @param string $ip IP地址
     * @param string $userAgent 用户代理
     * @return bool
     */
    public function logLogin($username, $success, $ip = null, $userAgent = null)
    {
        $level = $success ? 'info' : 'warning';
        $message = $success ? "用户登录成功" : "用户登录失败";
        
        $context = [
            'action' => 'login',
            'success' => $success,
            'ip' => $ip ?: $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $userAgent ?: $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ];

        return $this->logModel->create('login', $level, $message, $username, $context);
    }

    /**
     * 记录注销日志
     * @param string $username 用户名
     * @param string $ip IP地址
     * @return bool
     */
    public function logLogout($username, $ip = null)
    {
        $context = [
            'action' => 'logout',
            'ip' => $ip ?: $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ];

        return $this->logModel->create('login', 'info', "用户注销", $username, $context);
    }

    /**
     * 记录操作日志
     * @param string $action 操作动作
     * @param string $resource 操作资源
     * @param string $message 操作信息
     * @param array $data 操作数据
     * @param string $level 日志级别
     * @return bool
     */
    public function logOperation($action, $resource, $message, $data = [], $level = 'info')
    {
        $session = $this->sessionModel->getSession();
        $username = $session ? $session['username'] : 'anonymous';

        $context = [
            'action' => $action,
            'resource' => $resource,
            'data' => $data,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ];

        return $this->logModel->create('operation', $level, $message, $username, $context);
    }

    /**
     * 记录账号操作日志
     * @param string $action 操作类型: create, update, delete
     * @param int $targetId 目标账号ID
     * @param string $targetUsername 目标账号用户名
     * @param array $changes 变更信息
     * @return bool
     */
    public function logAccountOperation($action, $targetId, $targetUsername, $changes = [])
    {
        $actionMap = [
            'create' => '创建账号',
            'update' => '修改账号',
            'delete' => '删除账号'
        ];

        $message = ($actionMap[$action] ?? '操作账号') . ": {$targetUsername}";
        
        $context = [
            'action' => $action,
            'target_id' => $targetId,
            'target_username' => $targetUsername,
            'changes' => $changes
        ];

        return $this->logOperation($action . '_account', 'account', $message, $context);
    }

    /**
     * 记录IP段操作日志
     * @param string $action 操作类型
     * @param string $segment IP段
     * @param array $data 操作数据
     * @return bool
     */
    public function logIpSegmentOperation($action, $segment, $data = [])
    {
        $actionMap = [
            'create' => '创建IP段',
            'update' => '修改IP段',
            'delete' => '删除IP段'
        ];

        $message = ($actionMap[$action] ?? '操作IP段') . ": {$segment}";
        
        return $this->logOperation($action . '_ip_segment', 'ip_segment', $message, $data);
    }

    /**
     * 记录IP地址操作日志
     * @param string $action 操作类型
     * @param string $ip IP地址
     * @param array $data 操作数据
     * @return bool
     */
    public function logIpAddressOperation($action, $ip, $data = [])
    {
        $actionMap = [
            'create' => '创建IP地址',
            'update' => '修改IP地址',
            'delete' => '删除IP地址'
        ];

        $message = ($actionMap[$action] ?? '操作IP地址') . ": {$ip}";
        
        return $this->logOperation($action . '_ip_address', 'ip_address', $message, $data);
    }

    /**
     * 记录运行时日志
     * @param string $level 日志级别
     * @param string $message 日志信息  
     * @param array $context 上下文信息
     * @return bool
     */
    public function logRuntime($level, $message, $context = [])
    {
        $session = $this->sessionModel->getSession();
        $username = $session ? $session['username'] : null;

        $context['timestamp'] = date('Y-m-d H:i:s');
        $context['file'] = debug_backtrace()[1]['file'] ?? 'unknown';
        $context['line'] = debug_backtrace()[1]['line'] ?? 'unknown';

        return $this->logModel->create('runtime', $level, $message, $username, $context);
    }

    /**
     * 记录错误日志
     * @param string $message 错误信息
     * @param \Exception|null $exception 异常对象
     * @param array $context 上下文信息
     * @return bool
     */
    public function logError($message, $exception = null, $context = [])
    {
        if ($exception) {
            $context['exception'] = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }

        return $this->logRuntime('error', $message, $context);
    }
}
