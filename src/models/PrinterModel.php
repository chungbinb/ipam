<?php
require_once __DIR__ . '/../config/database.php';

class PrinterModel
{
    private $pdo;

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;
    }

    public function getList($query = [])
    {
        try {
            $sql = "SELECT * FROM printer WHERE 1=1";
            $params = [];
            foreach (['department','user','brand','model','asset_number','status'] as $f) {
                if (!empty($query[$f])) {
                    $sql .= " AND `$f` LIKE ?";
                    $params[] = '%' . $query[$f] . '%';
                }
            }
            $sql .= " ORDER BY id DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('PrinterModel getList error: ' . $e->getMessage());
            return [];
        }
    }

    public function create($data)
    {
        try {
            // 资产编号唯一性校验
            if (!empty($data['asset_number'])) {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM printer WHERE asset_number = ?");
                $stmt->execute([$data['asset_number']]);
                if ($stmt->fetchColumn() > 0) {
                    return ['error' => '资产编号已存在'];
                }
            }
            // 字段预处理，空字符串转为 null
            foreach (['type', 'function', 'status'] as $f) {
                if (isset($data[$f]) && $data[$f] === '') {
                    $data[$f] = null;
                }
            }
            
            // 价格字段处理
            if (isset($data['price'])) {
                $price = trim($data['price']);
                if ($price === '' || $price === null) {
                    $data['price'] = null;
                } else {
                    // 移除非数字字符（保留小数点和负号）
                    $price = preg_replace('/[^\d.-]/', '', $price);
                    $data['price'] = is_numeric($price) ? $price : null;
                }
            }
            // 新增：处理 updated_at 字段（如果传入的是 YYYY/MM/DD，转为 Y-m-d 00:00:00）
            if (!empty($data['updated_at'])) {
                $val = $data['updated_at'];
                // 支持 YYYY/MM/DD 或 YYYY-M-D
                if (preg_match('/^\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}$/', $val)) {
                    $dt = date_create(str_replace('/', '-', $val));
                    if ($dt) {
                        $data['updated_at'] = $dt->format('Y-m-d 00:00:00');
                    }
                }
            } else {
                $data['updated_at'] = date('Y-m-d H:i:s');
            }
            $sql = "INSERT INTO printer (department, user, brand, model, color, type, `function`, asset_number, status, price, currency, updated_at, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $this->pdo->prepare($sql);
            $ok = $stmt->execute([
                $data['department'] ?? '',
                $data['user'] ?? '',
                $data['brand'] ?? '',
                $data['model'] ?? '',
                $data['color'] ?? '',
                $data['type'] ?? null,
                $data['function'] ?? null,
                $data['asset_number'] ?? '',
                $data['status'] ?? null,
                $data['price'] ?? null,  // 修改：使用null而不是空字符串
                $data['currency'] ?? 'CNY',
                $data['updated_at'] ?? date('Y-m-d H:i:s')
            ]);
            if (!$ok) {
                error_log('PrinterModel create failed: ' . json_encode($stmt->errorInfo()));
            }
            return $ok;
        } catch (\Throwable $e) {
            error_log('PrinterModel create exception: ' . $e->getMessage());
            return false;
        }
    }

    public function update($id, $data)
    {
        try {
            // 资产编号唯一性校验（排除自身）
            if (!empty($data['asset_number'])) {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM printer WHERE asset_number = ? AND id != ?");
                $stmt->execute([$data['asset_number'], $id]);
                if ($stmt->fetchColumn() > 0) {
                    return ['error' => '资产编号已存在'];
                }
            }
            $fields = ['department','user','brand','model','color','type','function','asset_number','status','price','currency'];
            $stmt = $this->pdo->prepare("SELECT * FROM printer WHERE id=?");
            $stmt->execute([$id]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$old) return false;
            $update = [];
            foreach ($fields as $f) {
                $update[$f] = isset($data[$f]) ? $data[$f] : $old[$f];
            }
            $sql = "UPDATE printer SET department=?, user=?, brand=?, model=?, color=?, type=?, `function`=?, asset_number=?, status=?, price=?, currency=?, updated_at=NOW() WHERE id=?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                $update['department'],
                $update['user'],
                $update['brand'],
                $update['model'],
                $update['color'],
                $update['type'],
                $update['function'],
                $update['asset_number'],
                $update['status'],
                $update['price'],
                $update['currency'],
                $id
            ]);
        } catch (\Throwable $e) {
            error_log('PrinterModel update error: ' . $e->getMessage());
            return false;
        }
    }

    public function delete($id)
    {
        try {
            $sql = "DELETE FROM printer WHERE id=?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$id]);
        } catch (\Throwable $e) {
            error_log('PrinterModel delete error: ' . $e->getMessage());
            return false;
        }
    }

    public function batchDelete($ids)
    {
        try {
            if (!is_array($ids) || empty($ids)) return false;
            $in = implode(',', array_fill(0, count($ids), '?'));
            $sql = "DELETE FROM printer WHERE id IN ($in)";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($ids);
        } catch (\Throwable $e) {
            error_log('PrinterModel batchDelete error: ' . $e->getMessage());
            return false;
        }
    }

    public function batchCreate($dataList)
    {
        if (empty($dataList)) return ['success' => 0, 'failed' => 0];
        
        $success = 0;
        $failed = 0;
        $errors = [];
        
        error_log('PrinterModel batchCreate: 开始处理 ' . count($dataList) . ' 条记录');
        
        try {
            // 开始事务
            $this->pdo->beginTransaction();
            
            // 预先获取所有已存在的资产编号，避免重复查询
            $existingAssetNumbers = [];
            $stmt = $this->pdo->query("SELECT asset_number FROM printer WHERE asset_number IS NOT NULL AND asset_number != ''");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $existingAssetNumbers[$row['asset_number']] = true;
            }
            error_log('PrinterModel batchCreate: 已存在的资产编号数量: ' . count($existingAssetNumbers));
            
            $sql = "INSERT INTO printer (department, user, brand, model, color, type, `function`, asset_number, status, price, currency, updated_at, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $this->pdo->prepare($sql);
            
            foreach ($dataList as $index => $data) {
                try {
                    error_log("PrinterModel batchCreate: 处理第 " . ($index + 1) . " 条记录: " . json_encode($data, JSON_UNESCAPED_UNICODE));
                    
                    // 资产编号唯一性校验（跳过重复的）
                    if (!empty($data['asset_number']) && isset($existingAssetNumbers[$data['asset_number']])) {
                        $failed++;
                        $errorMsg = "资产编号 {$data['asset_number']} 已存在，跳过";
                        $errors[] = "第 " . ($index + 1) . " 行: " . $errorMsg;
                        error_log("PrinterModel batchCreate: " . $errorMsg);
                        continue;
                    }
                    
                    // 字段预处理
                    foreach (['type', 'function'] as $f) {
                        if (isset($data[$f]) && $data[$f] === '') {
                            $data[$f] = null;
                        }
                    }
                    
                    // 价格字段处理 - 空字符串转为null
                    if (isset($data['price'])) {
                        $price = trim($data['price']);
                        if ($price === '' || $price === null) {
                            $data['price'] = null;
                        } else {
                            // 移除非数字字符（保留小数点和负号）
                            $price = preg_replace('/[^\d.-]/', '', $price);
                            $data['price'] = is_numeric($price) ? $price : null;
                        }
                    }
                    
                    // 状态值映射
                    if (isset($data['status'])) {
                        $data['status'] = $this->mapStatusValue($data['status']);
                    }
                    
                    // 货币值映射
                    if (isset($data['currency'])) {
                        $data['currency'] = $this->mapCurrencyValue($data['currency']);
                    }
                    
                    // 处理更新时间
                    if (!empty($data['updated_at'])) {
                        $val = $data['updated_at'];
                        if (preg_match('/^\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}$/', $val)) {
                            $dt = date_create(str_replace('/', '-', $val));
                            if ($dt) {
                                $data['updated_at'] = $dt->format('Y-m-d 00:00:00');
                            }
                        }
                    } else {
                        $data['updated_at'] = date('Y-m-d H:i:s');
                    }
                    
                    $params = [
                        $data['department'] ?? '',
                        $data['user'] ?? '',
                        $data['brand'] ?? '',
                        $data['model'] ?? '',
                        $data['color'] ?? '',
                        $data['type'] ?? null,
                        $data['function'] ?? null,
                        $data['asset_number'] ?? '',
                        $data['status'] ?? null,
                        $data['price'] ?? null,  // 修改：确保price为null而不是空字符串
                        $data['currency'] ?? 'CNY',
                        $data['updated_at']
                    ];
                    
                    error_log("PrinterModel batchCreate: 执行SQL参数: " . json_encode($params, JSON_UNESCAPED_UNICODE));
                    
                    $ok = $stmt->execute($params);
                    
                    if ($ok) {
                        $success++;
                        // 记录已成功插入的资产编号，避免后续重复
                        if (!empty($data['asset_number'])) {
                            $existingAssetNumbers[$data['asset_number']] = true;
                        }
                        error_log("PrinterModel batchCreate: 第 " . ($index + 1) . " 条记录插入成功");
                    } else {
                        $failed++;
                        $errorInfo = $stmt->errorInfo();
                        $errorMsg = "SQL执行失败: " . json_encode($errorInfo);
                        $errors[] = "第 " . ($index + 1) . " 行: " . $errorMsg;
                        error_log("PrinterModel batchCreate: 第 " . ($index + 1) . " 条记录插入失败: " . $errorMsg);
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $errorMsg = "异常: " . $e->getMessage();
                    $errors[] = "第 " . ($index + 1) . " 行: " . $errorMsg;
                    error_log("PrinterModel batchCreate: 第 " . ($index + 1) . " 条记录异常: " . $errorMsg);
                }
            }
            
            // 提交事务
            $this->pdo->commit();
            error_log("PrinterModel batchCreate: 事务提交成功，成功: $success, 失败: $failed");
            
        } catch (\Throwable $e) {
            // 回滚事务
            $this->pdo->rollBack();
            $errorMsg = "事务失败: " . $e->getMessage();
            error_log("PrinterModel batchCreate: " . $errorMsg);
            return ['success' => 0, 'failed' => count($dataList), 'errors' => [$errorMsg]];
        }
        
        return ['success' => $success, 'failed' => $failed, 'errors' => $errors];
    }

    public function getById($id)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM printer WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('PrinterModel getById error: ' . $e->getMessage());
            return false;
        }
    }

    public function getByIds($ids)
    {
        try {
            if (!is_array($ids) || empty($ids)) return [];
            $in = implode(',', array_fill(0, count($ids), '?'));
            $sql = "SELECT * FROM printer WHERE id IN ($in) ORDER BY FIELD(id, $in)";
            $stmt = $this->pdo->prepare($sql);
            // 两次 $ids 是为了 FIELD 排序
            $stmt->execute(array_merge($ids, $ids));
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('PrinterModel getByIds error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 映射状态值到数据库枚举值
     */
    private function mapStatusValue($value)
    {
        if (empty($value)) return null;
        
        // 状态值映射表
        $statusMap = [
            // 中文状态
            '在用' => '在用',
            '维修中' => '维修中', 
            '未用' => '未用',
            '报废' => '报废',
            // 英文状态
            'active' => '在用',
            'in_use' => '在用',
            'using' => '在用',
            'used' => '在用',
            'maintenance' => '维修中',
            'repairing' => '维修中',
            'repair' => '维修中',
            'unused' => '未用',
            'idle' => '未用',
            'free' => '未用',
            'scrapped' => '报废',
            'disposed' => '报废',
            'retired' => '报废',
            // 数字状态
            '1' => '在用',
            '2' => '维修中',
            '3' => '未用',
            '4' => '报废',
            // 其他可能的变体
            'normal' => '在用',
            'working' => '在用',
            'broken' => '维修中',
            'new' => '未用',
            'available' => '未用'
        ];
        
        $value = trim(strtolower($value));
        
        // 直接匹配
        if (isset($statusMap[$value])) {
            return $statusMap[$value];
        }
        
        // 模糊匹配
        foreach ($statusMap as $key => $mappedValue) {
            if (strpos($value, $key) !== false || strpos($key, $value) !== false) {
                return $mappedValue;
            }
        }
        
        // 如果都不匹配，返回默认值
        error_log("PrinterModel: 未知状态值 '$value'，设置为null");
        return null;
    }

    /**
     * 映射货币值到数据库枚举值
     */
    private function mapCurrencyValue($value)
    {
        if (empty($value)) return 'CNY'; // 默认人民币
        
        // 货币值映射表
        $currencyMap = [
            // 标准代码
            'CNY' => 'CNY',
            'THB' => 'THB', 
            'USD' => 'USD',
            'EUR' => 'EUR',
            'JPY' => 'JPY',
            'HKD' => 'HKD',
            // 中文名称
            '人民币' => 'CNY',
            '泰铢' => 'THB',
            '美元' => 'USD',
            '欧元' => 'EUR',
            '日元' => 'JPY',
            '港币' => 'HKD',
            '港元' => 'HKD',
            // 英文名称
            'yuan' => 'CNY',
            'rmb' => 'CNY',
            'baht' => 'THB',
            'dollar' => 'USD',
            'euro' => 'EUR',
            'yen' => 'JPY',
            'hk dollar' => 'HKD',
            'hong kong dollar' => 'HKD',
            // 符号
            '¥' => 'CNY',
            '$' => 'USD',
            '€' => 'EUR',
            '฿' => 'THB'
        ];
        
        $value = trim(strtoupper($value));
        
        // 直接匹配
        if (isset($currencyMap[$value])) {
            return $currencyMap[$value];
        }
        
        // 模糊匹配
        $valueLower = strtolower($value);
        foreach ($currencyMap as $key => $mappedValue) {
            $keyLower = strtolower($key);
            if (strpos($valueLower, $keyLower) !== false || strpos($keyLower, $valueLower) !== false) {
                return $mappedValue;
            }
        }
        
        // 如果都不匹配，返回默认值
        error_log("PrinterModel: 未知货币值 '$value'，设置为CNY");
        return 'CNY';
    }
}
