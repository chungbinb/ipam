<?php

require_once __DIR__ . '/../models/AccountModel.php';
require_once __DIR__ . '/../models/SessionModel.php';
require_once __DIR__ . '/../services/LogService.php';

class AccountController
{
    private $model;
    private $sessionModel;
    private $logService;

    public function __construct()
    {
        $this->model = new AccountModel();
        $this->sessionModel = new SessionModel();
        $this->logService = new LogService();
    }

    private function getCurrentUserId(): ?int
    {
        $session = $this->sessionModel->getSession();
        return $session ? (int)$session['user_id'] : null;
    }

    public function me(): void
    {
        $this->setJsonHeader();
        $session = $this->sessionModel->getSession();
        if (!$session) {
            http_response_code(401);
            echo json_encode(['code' => 1, 'msg' => '未登录']);
            exit;
        }
        
        $user = $this->model->findById($session['user_id']);
        if (!$user) {
            http_response_code(401);
            echo json_encode(['code' => 1, 'msg' => '用户不存在']);
            exit;
        }
        
        echo json_encode(['code' => 0, 'data' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'status' => $user['status']
        ]]);
        exit;
    }

    public function list(): void
    {
        $this->setJsonHeader();
        $currentUserId = $this->getCurrentUserId();
        if (!$currentUserId) {
            http_response_code(401);
            echo json_encode(['code' => 1, 'msg' => '未登录']);
            exit;
        }
        
        $currentUser = $this->model->findById($currentUserId);
        if (!$currentUser) {
            http_response_code(401);
            echo json_encode(['code' => 1, 'msg' => '用户不存在']);
            exit;
        }
        
        $accounts = $this->model->getAll();
        
        // 根据当前用户角色过滤账号列表
        $filteredAccounts = $this->filterAccountsByRole($accounts, $currentUser);
        
        // 确保返回的是数组而不是对象
        $filteredAccounts = array_values($filteredAccounts);
        
        echo json_encode(['code' => 0, 'data' => $filteredAccounts]);
        exit;
    }
    
    /**
     * 根据用户角色过滤账号列表
     */
    private function filterAccountsByRole(array $accounts, array $currentUser): array
    {
        $role = $currentUser['role'];
        $userId = (int)$currentUser['id'];
        
        switch ($role) {
            case 'sysadmin':
                // 系统管理员可以看到所有账号
                return $accounts;
                
            case 'admin':
                // 管理员只能看到自己的账号和网管账号
                return array_filter($accounts, function($account) use ($userId) {
                    return (int)$account['id'] === $userId || $account['role'] === 'netadmin';
                });
                
            case 'netadmin':
                // 网管只能看到自己的账号
                return array_filter($accounts, function($account) use ($userId) {
                    return (int)$account['id'] === $userId;
                });
                
            default:
                // 其他角色不显示任何账号
                return [];
        }
    }

    public function create(): void
    {
        $this->setJsonHeader();
        $currentUserId = $this->getCurrentUserId();
        if (!$currentUserId) {
            http_response_code(401);
            echo json_encode(['code' => 1, 'msg' => '未登录']);
            exit;
        }
        
        $actor = $this->model->findById($currentUserId);
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        
        $username = trim((string)($payload['username'] ?? ''));
        $password = (string)($payload['password'] ?? '');
        $role = (string)($payload['role'] ?? '');
        $status = (string)($payload['status'] ?? 'active');

        if ($username === '' || strlen($password) < 6) {
            http_response_code(400);
            echo json_encode(['code' => 1, 'msg' => '用户名必填，密码至少6位']);
            exit;
        }
        
        if (!in_array($role, ['admin','netadmin'], true)) {
            http_response_code(400);
            echo json_encode(['code' => 1, 'msg' => '只允许创建管理员或网管']);
            exit;
        }
        
        if (!$this->canCreateRole($actor['role'] ?? '', $role)) {
            http_response_code(403);
            echo json_encode(['code' => 1, 'msg' => '无权创建该角色']);
            exit;
        }

        if ($this->model->usernameExists($username)) {
            http_response_code(400);
            echo json_encode(['code' => 1, 'msg' => '用户名已存在']);
            exit;
        }

        $data = [
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'status' => in_array($status, ['active','disabled'], true) ? $status : 'active'
        ];

        if ($this->model->create($data)) {
            // 记录创建账号日志
            $this->logService->logAccountOperation('create', null, $username, [
                'role' => $role,
                'status' => $data['status']
            ]);
            echo json_encode(['code' => 0, 'msg' => 'ok']);
        } else {
            http_response_code(500);
            echo json_encode(['code' => 1, 'msg' => '创建失败']);
        }
        exit;
    }

    public function update(int $id): void
    {
        $this->setJsonHeader();
        $currentUserId = $this->getCurrentUserId();
        if (!$currentUserId) {
            http_response_code(401);
            echo json_encode(['code' => 1, 'msg' => '未登录']);
            exit;
        }
        
        $actor = $this->model->findById($currentUserId);
        $target = $this->model->findById($id);
        
        if (!$actor) {
            http_response_code(401);
            echo json_encode(['code' => 1, 'msg' => '当前用户不存在']);
            exit;
        }
        
        if (!$target) {
            http_response_code(404);
            echo json_encode(['code' => 1, 'msg' => '账号不存在']);
            exit;
        }

        if (!$this->canEdit($actor, $target)) {
            http_response_code(403);
            echo json_encode(['code' => 1, 'msg' => '无权编辑该账号']);
            exit;
        }

        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        $updateData = [];

        // 更新用户名
        if (isset($payload['username'])) {
            $newUsername = trim((string)$payload['username']);
            if ($newUsername === '') {
                http_response_code(400);
                echo json_encode(['code' => 1, 'msg' => '用户名不能为空']);
                exit;
            }
            
            // 只有当用户名实际发生变化时才进行检查
            if ($newUsername !== $target['username']) {
                if ($target['role'] === 'sysadmin') {
                    http_response_code(400);
                    echo json_encode(['code' => 1, 'msg' => '系统管理员用户名不可修改']);
                    exit;
                }
                if ($this->model->usernameExists($newUsername, $id)) {
                    http_response_code(400);
                    echo json_encode(['code' => 1, 'msg' => '用户名已存在']);
                    exit;
                }
                $updateData['username'] = $newUsername;
            }
        }

        // 更新状态
        if (isset($payload['status'])) {
            $st = (string)$payload['status'];
            if (in_array($st, ['active','disabled'], true)) {
                $updateData['status'] = $st;
            }
        }

        // 更新密码
        if (isset($payload['password'])) {
            $pwd = (string)$payload['password'];
            if (strlen($pwd) < 6) {
                http_response_code(400);
                echo json_encode(['code' => 1, 'msg' => '密码至少6位']);
                exit;
            }
            
            // 权限检查：只有以下情况可以修改密码
            // 1. 修改自己的密码
            // 2. sysadmin 可以修改任何人的密码
            // 3. admin 可以修改 netadmin 的密码
            $actorRole = $actor['role'] ?? '';
            $targetRole = $target['role'] ?? '';
            $isSelfEdit = ($actor['id'] ?? 0) === $target['id'];
            
            $canChangePassword = false;
            if ($isSelfEdit) {
                $canChangePassword = true; // 可以修改自己的密码
            } elseif ($actorRole === 'sysadmin') {
                $canChangePassword = true; // sysadmin 可以修改任何人的密码
            } elseif ($actorRole === 'admin' && $targetRole === 'netadmin') {
                $canChangePassword = true; // admin 可以修改 netadmin 的密码
            }
            
            if (!$canChangePassword) {
                http_response_code(403);
                echo json_encode(['code' => 1, 'msg' => '无权修改该账号密码']);
                exit;
            }
            
            $updateData['password_hash'] = password_hash($pwd, PASSWORD_DEFAULT);
        }

        if (empty($updateData)) {
            http_response_code(400);
            echo json_encode(['code' => 1, 'msg' => '没有要更新的数据']);
            exit;
        }

        if ($this->model->update($id, $updateData)) {
            // 记录修改账号日志
            $changes = [];
            if (isset($updateData['username'])) {
                $changes['username'] = ['from' => $target['username'], 'to' => $updateData['username']];
            }
            if (isset($updateData['password_hash'])) {
                $changes['password'] = 'changed';
            }
            if (isset($updateData['status'])) {
                $changes['status'] = ['from' => $target['status'], 'to' => $updateData['status']];
            }
            
            $this->logService->logAccountOperation('update', $id, $target['username'], $changes);
            
            echo json_encode(['code' => 0, 'msg' => 'ok']);
        } else {
            http_response_code(500);
            echo json_encode(['code' => 1, 'msg' => '更新失败']);
        }
        exit;
    }

    public function delete(int $id): void
    {
        $this->setJsonHeader();
        $currentUserId = $this->getCurrentUserId();
        if (!$currentUserId) {
            http_response_code(401);
            echo json_encode(['code' => 1, 'msg' => '未登录']);
            exit;
        }
        
        $actor = $this->model->findById($currentUserId);
        $target = $this->model->findById($id);
        
        if (!$target) {
            http_response_code(404);
            echo json_encode(['code' => 1, 'msg' => '账号不存在']);
            exit;
        }

        if (!$this->canDelete($actor, $target)) {
            http_response_code(403);
            echo json_encode(['code' => 1, 'msg' => '无权删除该账号']);
            exit;
        }

        if ($this->model->delete($id)) {
            // 记录删除账号日志
            $this->logService->logAccountOperation('delete', $id, $target['username'], [
                'role' => $target['role'],
                'status' => $target['status']
            ]);
            
            echo json_encode(['code' => 0, 'msg' => 'ok']);
        } else {
            http_response_code(500);
            echo json_encode(['code' => 1, 'msg' => '删除失败']);
        }
        exit;
    }

    private function setJsonHeader(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
    }

    private function canCreateRole(string $actorRole, string $targetRole): bool
    {
        if ($actorRole === 'sysadmin') return in_array($targetRole, ['admin','netadmin'], true);
        if ($actorRole === 'admin') return $targetRole === 'netadmin';
        return false;
    }

    private function canDelete(array $actor, array $target): bool
    {
        if ($target['role'] === 'sysadmin') return false;
        if (($actor['role'] ?? '') === 'sysadmin') return true;
        if (($actor['role'] ?? '') === 'admin') return $target['role'] === 'netadmin';
        return false;
    }

    private function canEdit(array $actor, array $target): bool
    {
        $ar = $actor['role'] ?? '';
        if ($ar === 'sysadmin') return $target['role'] !== 'sysadmin' || $actor['id'] === $target['id'];
        if ($ar === 'admin') return $target['role'] === 'netadmin' || $actor['id'] === $target['id'];
        return $actor['id'] === $target['id'];
    }
}