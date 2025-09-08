<?php
// 设置错误报告 - 生产环境配置
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once './src/config/database.php';
require_once './src/controllers/AssetController.php';
require_once './src/controllers/IpSegmentController.php';
require_once './src/controllers/IpAddressController.php';
require_once './src/controllers/LaptopController.php';
require_once './src/controllers/MonitorController.php';
require_once './src/controllers/BrandController.php';
require_once './src/controllers/DepartmentController.php';
require_once './src/controllers/HostController.php';
require_once './src/controllers/PrinterController.php';
require_once './src/controllers/ConsumableController.php';
require_once './src/controllers/ServerController.php'; // 新增
require_once './src/controllers/LogController.php';
require_once './src/controllers/OverviewController.php';
require_once './src/controllers/AccountController.php';
require_once './src/controllers/AuthController.php'; // 新增

require_once './src/middleware/AuthMiddleware.php'; // 新增

$controller = new AssetController();
$ipSegmentController = new IpSegmentController();
$ipAddressController = new IpAddressController();
$laptopController = new LaptopController();
$monitorController = new MonitorController();
$brandController = new BrandController();
$departmentController = new DepartmentController();
$hostController = new HostController();
$printerController = new PrinterController();
$consumableController = new ConsumableController();
$serverController = new ServerController(); // 新增
$logController = new LogController();
$overviewController = new OverviewController();
$accountController = new AccountController();
$authController = new AuthController(); // 新增

$authMiddleware = new AuthMiddleware(); // 新增

// CORS 处理
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$requestUri = strtok($_SERVER['REQUEST_URI'], '?');

// 新增：简易处理 favicon，避免 404
if ($requestUri === '/favicon.ico') {
    header('Content-Type: image/x-icon');
    // 返回一个1x1像素的透明图标
    echo base64_decode('AAABAAEAEBAAAAEAIABoBAAAFgAAACgAAAAQAAAAIAAAAAEAIAAAAAAAAAQAABILAAASCwAAAAAAAAAAAAD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wD///8A////AP///wAAAAA=');
    exit;
}

// 移动设备检测函数
function isMobileDevice() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return preg_match('/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $userAgent);
}

// 静态文件处理（仅处理根路径）
if ($requestUri === '/') {
    // 检查登录状态
    try {
        $session = $authMiddleware->checkAuth();
        if ($session) {
            // 已登录，检查是否为移动设备
            if (isMobileDevice() && !isset($_GET['desktop'])) {
                // 移动设备重定向到手机版页面
                if (!headers_sent()) {
                    header('Location: /public/mobile.html');
                }
                exit;
            } else {
                // 桌面设备或强制桌面版，重定向到主页
                if (!headers_sent()) {
                    header('Location: /public/index.html');
                }
                exit;
            }
        }
    } catch (Exception $e) {
        // 认证失败，检查是否为移动设备
        if (isMobileDevice() && !isset($_GET['desktop'])) {
            // 移动设备重定向到登录页面（移动版会自动检测）
            if (!headers_sent()) {
                header('Location: /public/login.html?mobile=1');
            }
            exit;
        } else {
            // checkAuth方法会处理重定向到登录页面
            return;
        }
    }
}

// 登录页面和手机版页面不需要验证
if ($requestUri === '/public/login.html' || $requestUri === '/public/mobile.html') {
    readfile(__DIR__ . '/public/' . basename($requestUri));
    return;
}

if (preg_match('#^/public/(.+)$#', $requestUri, $matches)) {
    $file = __DIR__ . '/public/' . $matches[1];
    if (file_exists($file)) {
        // 新增：除了登录页和手机版页面，其他页面都需要验证
        if ($matches[1] !== 'login.html' && $matches[1] !== 'mobile.html') {
            $authMiddleware->checkAuth();
        }

        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $mimeTypes = [
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
        ];
        header('Content-Type: ' . (isset($mimeTypes[$ext]) ? $mimeTypes[$ext] : 'application/octet-stream'));
        readfile($file);
    } else {
        http_response_code(404);
        echo '404 Not Found';
    }
    return;
}

// API 路由
$requestMethod = $_SERVER['REQUEST_METHOD'];
try {
    switch ($requestMethod) {
        case 'GET':
            // 认证相关API不需要登录验证
            if ($requestUri === '/api/auth/check') {
                $authController->check();
            } elseif ($requestUri === '/assets') {
                $authMiddleware->checkAuth(); // 新增验证
                $controller->getAsset();
            } elseif ($requestUri === '/api/ip-segments') {
                $authMiddleware->checkAuth(); // 新增验证
                $ipSegmentController->getList();
            } elseif ($requestUri === '/api/ip-addresses') {
                $authMiddleware->checkAuth(); // 新增验证
                $ipAddressController->getList();
            } elseif ($requestUri === '/api/ping-ip') {
                $authMiddleware->checkAuth(); // 新增验证
                $ipAddressController->pingIp();
            } elseif ($requestUri === '/api/laptop') {
                $authMiddleware->checkAuth(); // 新增验证
                $laptopController->getList();
            } elseif ($requestUri === '/api/monitor') {
                $authMiddleware->checkAuth(); // 新增验证
                $monitorController->getList();
            } elseif ($requestUri === '/api/brand') {
                $authMiddleware->checkAuth(); // 新增验证
                $brandController->getList();
            } elseif ($requestUri === '/api/department') {
                $authMiddleware->checkAuth(); // 新增验证
                $departmentController->getList();
            } elseif ($requestUri === '/api/host') {
                $authMiddleware->checkAuth(); // 新增验证
                $hostController->getList();
            } elseif ($requestUri === '/api/server') {
                $authMiddleware->checkAuth(); // 新增验证
                $serverController->getList();
            } elseif ($requestUri === '/api/printer') {
                $authMiddleware->checkAuth(); // 新增验证
                $printerController->getList();
            } elseif ($requestUri === '/api/consumable/in-detail') {
                $authMiddleware->checkAuth(); // 新增验证
                $consumableController->getInDetail();
            } elseif ($requestUri === '/api/consumable/out-detail') {
                $authMiddleware->checkAuth(); // 新增验证
                $consumableController->getOutDetail();
            } elseif ($requestUri === '/api/consumable') {
                $authMiddleware->checkAuth(); // 新增验证
                $consumableController->getList();
            } elseif ($requestUri === '/api/logs') {
                $authMiddleware->checkAuth(); // 新增验证
                $logController->getList();
            } elseif ($requestUri === '/api/logs/statistics') {
                $authMiddleware->checkAuth(); // 新增验证
                $logController->statistics();
            } elseif ($requestUri === '/api/overview/stats') {
                $authMiddleware->checkAuth(); // 新增验证
                $overviewController->getStats();
            } elseif ($requestUri === '/api/account/profile') {
                $authMiddleware->checkAuth(); // 新增验证
                $accountController->getProfile();
            } elseif ($requestUri === '/api/accounts') {
                $authMiddleware->checkAuth(); // 新增验证
                $accountController->list();
            } elseif ($requestUri === '/api/account/me') {
                $authMiddleware->checkAuth(); // 新增验证
                $accountController->me();
            } elseif ($requestUri === '/api/asset-numbers') {
                $authMiddleware->checkAuth(); // 新增验证
                // 返回资产编号列表 - 可以后续实现具体逻辑
                echo json_encode(['code' => 0, 'data' => []]);
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'Not Found']);
            }
            break;
        case 'POST':
            // 登录API不需要验证
            if ($requestUri === '/api/auth/login') {
                $authController->login();
            } elseif ($requestUri === '/api/auth/logout') {
                $authController->logout();
            } elseif ($requestUri === '/assets') {
                $authMiddleware->checkAuth(); // 新增验证
                $controller->createAsset();
            } elseif ($requestUri === '/api/ip-segments') {
                $authMiddleware->checkAuth(); // 新增验证
                $ipSegmentController->create();
            } elseif ($requestUri === '/api/laptop') {
                $authMiddleware->checkAuth(); // 新增验证
                $laptopController->create();
            } elseif ($requestUri === '/api/laptop/batch') {
                $authMiddleware->checkAuth(); // 新增验证
                $laptopController->batchDelete();
            } elseif ($requestUri === '/api/ping-ip-batch') {
                $authMiddleware->checkAuth(); // 新增验证
                $ipAddressController->pingIpBatch();
            } elseif ($requestUri === '/api/ping-ip-segment') {
                $authMiddleware->checkAuth(); // 新增验证
                $ipSegmentController->pingIpSegment();
            } elseif ($requestUri === '/api/monitor') {
                $authMiddleware->checkAuth(); // 新增验证
                $monitorController->create();
            } elseif ($requestUri === '/api/monitor/batch') {
                $authMiddleware->checkAuth(); // 新增验证
                $monitorController->batchDelete();
            } elseif ($requestUri === '/api/brand') {
                $authMiddleware->checkAuth(); // 新增验证
                $brandController->create();
            } elseif ($requestUri === '/api/brand/batch') {
                $authMiddleware->checkAuth(); // 新增验证
                $brandController->batchDelete();
            } elseif ($requestUri === '/api/department') {
                $authMiddleware->checkAuth(); // 新增验证
                $departmentController->create();
            } elseif ($requestUri === '/api/department/batch') {
                $authMiddleware->checkAuth(); // 新增验证
                $departmentController->batchDelete();
            } elseif ($requestUri === '/api/host') {
                $authMiddleware->checkAuth(); // 新增验证
                $hostController->create();
            } elseif ($requestUri === '/api/host/batch') {
                $authMiddleware->checkAuth(); // 新增验证
                $hostController->batchDelete();
            } elseif ($requestUri === '/api/server') {
                $authMiddleware->checkAuth(); // 新增验证
                $serverController->create();
            } elseif ($requestUri === '/api/server/batch') {
                $authMiddleware->checkAuth(); // 新增验证
                $serverController->batchDelete();
            } elseif (preg_match('#^/api/server/(\d+)/connect$#', $requestUri, $matches)) {
                $authMiddleware->checkAuth(); // 新增验证
                $serverController->connect($matches[1]);
            } elseif (preg_match('#^/api/server/(\d+)/scan$#', $requestUri, $matches)) {
                $authMiddleware->checkAuth(); // 新增验证
                $serverController->scanPorts($matches[1]);
            } elseif ($requestUri === '/api/printer') {
                $authMiddleware->checkAuth(); // 新增验证
                $printerController->create();
            } elseif ($requestUri === '/api/printer/batch') {
                $authMiddleware->checkAuth(); // 新增验证
                $printerController->batchDelete();
            } elseif ($requestUri === '/api/printer/import') {
                $authMiddleware->checkAuth(); // 新增验证
                $printerController->batchImport();
            } elseif (preg_match('#^/api/consumable/(\d+)/(in|out)$#', $requestUri, $m)) {
                $authMiddleware->checkAuth(); // 新增验证
                $id = (int)$m[1];
                $action = $m[2];
                if ($action === 'in') {
                    $consumableController->in($id);
                } else {
                    $consumableController->out($id);
                }
            } elseif ($requestUri === '/api/consumable') {
                $authMiddleware->checkAuth(); // 新增验证
                $consumableController->create();
            } elseif ($requestUri === '/api/account/profile') {
                $authMiddleware->checkAuth(); // 新增验证
                $accountController->saveProfile();
            } elseif ($requestUri === '/api/account/password') {
                $authMiddleware->checkAuth(); // 新增验证
                $accountController->changePassword();
            } elseif ($requestUri === '/api/accounts') {
                $authMiddleware->checkAuth(); // 新增验证
                $accountController->create();
            } elseif ($requestUri === '/api/logs/cleanup') {
                $authMiddleware->checkAuth(); // 新增验证
                $logController->cleanup();
            } elseif ($requestUri === '/api/logs') {
                $authMiddleware->checkAuth(); // 新增验证
                $logController->create();
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'Not Found']);
            }
            break;
        case 'PUT':
            // 所有PUT请求都需要验证
            $authMiddleware->checkAuth();
            if (preg_match('/\/api\/ip-segments\/(\d+)/', $requestUri, $matches)) {
                $ipSegmentController->update($matches[1]);
            } elseif (preg_match('/\/api\/ip-addresses\/(\d+)/', $requestUri, $matches)) {
                $ipAddressController->update($matches[1]);
            } elseif (preg_match('/\/assets\/(\d+)/', $requestUri, $matches)) {
                $controller->updateAsset($matches[1]);
            } elseif (preg_match('/\/api\/laptop\/(\d+)/', $requestUri, $matches)) {
                $laptopController->update($matches[1]);
            } elseif (preg_match('/\/api\/monitor\/(\d+)/', $requestUri, $matches)) {
                $monitorController->update($matches[1]);
            } elseif (preg_match('#^/api/brand/(\d+)$#', $requestUri, $matches)) {
                $brandController->update($matches[1]);
            } elseif (preg_match('#^/api/department/(\d+)$#', $requestUri, $matches)) {
                $departmentController->update($matches[1]);
            } elseif (preg_match('/\/api\/host\/(\d+)/', $requestUri, $matches)) {
                $hostController->update($matches[1]);
            } elseif (preg_match('#^/api/server/(\d+)$#', $requestUri, $matches)) {
                $serverController->update($matches[1]);
            } elseif (preg_match('#^/api/printer/(\d+)$#', $requestUri, $matches)) {
                $printerController->update($matches[1]);
            } elseif (preg_match('#^/api/consumable/(\d+)$#', $requestUri, $matches)) {
                $consumableController->update($matches[1]);
            } elseif (preg_match('#^/api/accounts/(\d+)$#', $requestUri, $matches)) {
                $accountController->update((int)$matches[1]);
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'Not Found']);
            }
            break;
        case 'DELETE':
            // 所有DELETE请求都需要验证
            $authMiddleware->checkAuth();
            if (preg_match('/\/api\/ip-segments\/(\d+)/', $requestUri, $matches)) {
                $ipSegmentController->delete($matches[1]);
            } elseif (preg_match('/\/assets\/(\d+)/', $requestUri, $matches)) {
                $controller->deleteAsset($matches[1]);
            } elseif (preg_match('/\/api\/laptop\/(\d+)/', $requestUri, $matches)) {
                $laptopController->delete($matches[1]);
            } elseif (preg_match('/\/api\/monitor\/(\d+)/', $requestUri, $matches)) {
                $monitorController->delete($matches[1]);
            } elseif (preg_match('#^/api/brand/(\d+)$#', $requestUri, $matches)) {
                $brandController->delete($matches[1]);
            } elseif (preg_match('#^/api/department/(\d+)$#', $requestUri, $matches)) {
                $departmentController->delete($matches[1]);
            } elseif (preg_match('/\/api\/host\/(\d+)/', $requestUri, $matches)) {
                $hostController->delete($matches[1]);
            } elseif (preg_match('#^/api/server/(\d+)$#', $requestUri, $matches)) {
                $serverController->delete($matches[1]);
            } elseif (preg_match('#^/api/printer/(\d+)$#', $requestUri, $matches)) {
                $printerController->delete($matches[1]);
            } elseif (preg_match('#^/api/consumable/(\d+)$#', $requestUri, $matches)) {
                $consumableController->delete($matches[1]);
            } elseif (preg_match('#^/api/accounts/(\d+)$#', $requestUri, $matches)) {
                $accountController->delete((int)$matches[1]);
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'Not Found']);
            }
            break;
        default:
            http_response_code(405);
            echo json_encode(['message' => 'Method Not Allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Server Error', 'error' => $e->getMessage()]);
}

// 修正API路由，支持/api/laptop路径（去除域名影响）
$apiPrefix = '/api/laptop';
if (strpos($requestUri, $apiPrefix) === 0) {
    $subPath = substr($requestUri, strlen($apiPrefix));
    switch ($requestMethod) {
        case 'GET':
            if ($subPath === '' || $subPath === '/') {
                $laptopController->getList();
            }
            break;
        case 'POST':
            if ($subPath === '' || $subPath === '/') {
                $laptopController->create();
            } elseif ($subPath === '/batch') {
                $laptopController->batchDelete();
            }
            break;
        case 'PUT':
            if (preg_match('#^/(\d+)$#', $subPath, $matches)) {
                $laptopController->update($matches[1]);
            }
            break;
        case 'DELETE':
            if (preg_match('#^/(\d+)$#', $subPath, $matches)) {
                $laptopController->delete($matches[1]);
            }
            break;
        default:
            http_response_code(405);
            echo json_encode(['message' => 'Method Not Allowed']);
            break;
    }
    return;
}

// 修正API路由，支持/api/monitor路径（去除域名影响）
$apiPrefix = '/api/monitor';
if (strpos($requestUri, $apiPrefix) === 0) {
    $subPath = substr($requestUri, strlen($apiPrefix));
    switch ($requestMethod) {
        case 'GET':
            if ($subPath === '' || $subPath === '/') {
                $monitorController->getList();
            }
            break;
        case 'POST':
            if ($subPath === '' || $subPath === '/') {
                $monitorController->create();
            } elseif ($subPath === '/batch') {
                $monitorController->batchDelete();
            }
            break;
        case 'PUT':
            if (preg_match('#^/(\d+)$#', $subPath, $matches)) {
                $monitorController->update($matches[1]);
                return;
            }
            break;
        case 'DELETE':
            if (preg_match('#^/(\d+)$#', $subPath, $matches)) {
                $monitorController->delete($matches[1]);
            }
            break;
        default:
            http_response_code(405);
            echo json_encode(['message' => 'Method Not Allowed']);
            break;
    }
    return;
}

// 修正API路由，支持/api/printer路径（去除域名影响）
$apiPrefix = '/api/printer';
if (strpos($requestUri, $apiPrefix) === 0) {
    $subPath = substr($requestUri, strlen($apiPrefix));
    switch ($requestMethod) {
        case 'GET':
            if ($subPath === '' || $subPath === '/') {
                $printerController->getList();
            }
            break;
        case 'POST':
            if ($subPath === '' || $subPath === '/') {
                $printerController->create();
            } elseif ($subPath === '/batch') {
                $printerController->batchDelete();
            } elseif ($subPath === '/export') {
                $printerController->export();
            } elseif ($subPath === '/import') {
                $printerController->batchImport();
            }
            break;
        case 'PUT':
            if (preg_match('#^/(\d+)$#', $subPath, $matches)) {
                $printerController->update($matches[1]);
            }
            break;
        case 'DELETE':
            if (preg_match('#^/(\d+)$#', $subPath, $matches)) {
                $printerController->delete($matches[1]);
            }
            break;
        default:
            http_response_code(405);
            echo json_encode(['message' => 'Method Not Allowed']);
            break;
    }
    return;
}