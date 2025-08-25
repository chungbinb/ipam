# 宝塔面板部署指南

## 🚀 快速部署步骤

### 1. 上传项目文件
将整个 `it-asset-management` 项目文件夹上传到宝塔面板的网站根目录：
```
/www/wwwroot/127.0.0.1/
```

### 2. 修改 Nginx 配置

在宝塔面板中：
1. 打开【网站】→ 找到你的站点 → 点击【设置】
2. 选择【配置文件】标签
3. 在现有配置中找到这一行：
   ```nginx
   #REWRITE-START URL重写规则引用,修改后将导致面板设置的伪静态规则失效
   ```
4. **在这一行的前面**添加以下配置：

```nginx
# ===== IT资产管理系统 自定义配置开始 =====

# API 路由重写 - 关键配置
location /api/ {
    rewrite ^/api/(.*)$ /index.php?route=$1 last;
}

# 处理默认首页，重定向到登录页面
location = / {
    try_files /public/login.html =404;
}

# 处理 /login 路径，重定向到登录页面
location = /login {
    return 301 /public/login.html;
}

# 处理主应用路径
location = /app {
    return 301 /public/index.html;
}

# 处理 public 目录下的静态文件
location /public/ {
    try_files $uri $uri/ =404;
    
    # 处理 HTML 文件，禁用缓存
    location ~ \.html$ {
        add_header Cache-Control "no-cache, no-store, must-revalidate";
        add_header Pragma "no-cache";
        add_header Expires "0";
    }
}

# 安全配置：阻止直接访问敏感目录和文件
location ~ ^/(src|sql) {
    deny all;
    return 404;
}

# ===== IT资产管理系统 自定义配置结束 =====
```

### 3. 重载 Nginx 配置
添加配置后，点击【保存】，Nginx 会自动重载配置。

### 4. 设置数据库
1. 在宝塔面板中创建 MySQL 数据库
2. **根据MySQL版本选择安装方式：**
   
   **方式一：版本检测（推荐）**
   - 访问 `http://你的域名/check_mysql.php` 检测MySQL版本
   - 根据检测结果选择合适的安装工具
   
   **方式二：兼容性修复工具**
   - 访问 `http://你的域名/mysql8_fix.php` 
   - 自动检测MySQL版本并使用兼容的SQL文件
   - 支持 MySQL 5.5, 5.7, 8.0, 8.4.5 等版本
   
   **方式三：标准安装向导**
   - 访问 `http://你的域名/install.php` 
   - 适用于大多数MySQL版本
   
3. 按照向导完成数据库配置和管理员账户创建

### 5. 完成部署
安装完成后，访问：
- 登录页面：`http://你的域名/public/login.html`
- 或直接访问：`http://你的域名/` （会自动跳转到登录页面）

## 🔧 配置详解

### 关键配置说明：

#### 1. API路由重写
```nginx
location /api/ {
    rewrite ^/api/(.*)$ /index.php?route=$1 last;
}
```
这个配置将所有 `/api/*` 请求重写到 `index.php`，解决前端API调用404的问题。

#### 2. 默认首页设置
```nginx
location = / {
    try_files /public/login.html =404;
}
```
访问根路径时自动显示登录页面。

#### 3. 静态文件处理
```nginx
location /public/ {
    try_files $uri $uri/ =404;
}
```
确保 public 目录下的静态文件可以正常访问。

## 🛠️ 故障排除

### 问题1：API调用404错误
**解决方案：** 
1. 确保已正确添加API路由重写配置到nginx
2. 重载Nginx配置
3. 使用API测试工具检查：访问 `http://你的域名/test_api.php`
4. 检查nginx错误日志确认重写规则是否生效

### 问题2：登录成功后跳转404
**症状：** 登录成功但访问主页时出现404错误
**原因：** 登录页面跳转路径配置错误
**解决方案：**
1. 确认登录页面跳转到 `/public/index.html` 而不是 `/index.html`
2. 使用Session调试工具：访问 `http://你的域名/debug_session.php`
3. 检查storage/sessions目录权限（需要可读写）
4. 清除浏览器Cookie重新登录
5. 确认public/index.html文件存在

### 问题3：页面无法访问
**解决方案：** 检查文件上传是否完整，确保 public 目录存在。

### 问题4：MySQL版本兼容性问题
**症状：** 安装过程中提示"安装过程中发生错误，请检查配置后重试"
**原因：** 不同MySQL版本对SQL语法要求不同
**解决方案：** 
1. **先检测版本：** 访问 `http://你的域名/check_mysql.php`
2. **MySQL 8.4+：** 使用 `mysql8_fix.php` 兼容性修复工具
3. **MySQL 5.5：** 会自动使用 `server_mysql55.sql` 兼容文件
4. **MySQL 5.7/8.0：** 可以使用标准安装向导
5. **手动修复：** 将SQL文件中的 `int(11)` 改为 `int`

### 问题4：密码错误但确认密码正确
**原因：** AccountModel构造函数中的自动密码检查逻辑可能导致密码被重复哈希
**解决方案：** 
1. 运行 `php fix_password.php` 修复密码
2. 最新版本已移除了问题代码，重新上传文件即可

### 问题5：数据库连接错误
**解决方案：** 
1. 确保MySQL服务正常运行
2. 检查数据库配置信息是否正确
3. 确认数据库用户权限

### 问题5：PHP错误
**解决方案：**
1. 确保PHP版本为7.4+
2. 检查PHP扩展：mysqli, session, json
3. 查看错误日志：`/www/wwwlogs/127.0.0.1.error.log`

## 📋 验证清单

部署完成后，请验证以下功能：
- [ ] MySQL版本检测正常（访问 `check_mysql.php`）
- [ ] API路径测试正常（访问 `test_api.php`）
- [ ] 访问根路径能正常跳转到登录页面
- [ ] 登录功能正常（使用默认账号：admin/123456）
- [ ] 登录后能正常跳转到主页面
- [ ] 各功能模块页面能正常加载
- [ ] API接口能正常响应（无404错误）
- [ ] IP段和IP地址管理功能正常

## 🔐 安全建议

1. **修改默认管理员密码**
2. **设置防火墙规则**
3. **启用HTTPS**（如果有SSL证书）
4. **定期备份数据库**
5. **监控错误日志**

## 📞 支持

如果遇到问题，请检查：
1. Nginx错误日志：`/www/wwwlogs/127.0.0.1.error.log`
2. PHP错误日志：通过宝塔面板查看
3. 数据库连接状态

---
**部署完成！** 🎉
