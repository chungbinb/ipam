# IT资产管理系统 - 环境检测工具使用说明

## 📋 工具说明

此环境检测工具用于检测服务器是否具备运行IP段ping扫描功能的必要环境。工具会检测PHP版本、系统命令、文件权限、网络功能等关键因素。

## 🔧 工具文件

### 1. environment_check.php
- **适用场景**: 通过Web浏览器访问
- **特点**: 提供完整的HTML格式报告，包含表格、颜色标识
- **使用方法**: 
  ```bash
  # 上传到服务器后，通过浏览器访问
  http://your-server.com/environment_check.php
  ```

### 2. environment_check_cli.php  
- **适用场景**: 命令行运行或Web访问
- **特点**: 支持CLI彩色输出，也可通过Web访问
- **使用方法**:
  ```bash
  # 命令行运行（推荐）
  php environment_check_cli.php
  
  # 或通过Web访问
  http://your-server.com/environment_check_cli.php
  ```

## 🚀 部署步骤

### 1. 上传文件到服务器
```bash
# 将检测工具上传到IT资产管理系统根目录
scp environment_check*.php user@your-server:/path/to/it-asset-management/
```

### 2. 设置权限
```bash
chmod 644 environment_check*.php
```

### 3. 运行检测
```bash
# 方式1: 命令行运行（推荐）
cd /path/to/it-asset-management
php environment_check_cli.php

# 方式2: Web访问
# 打开浏览器访问: http://your-server.com/environment_check.php
```

## 📊 检测项目

### 1. PHP环境检测
- PHP版本 (要求 >= 7.4)
- 必需扩展: json, pdo
- 推荐扩展: pcntl, posix
- 可选扩展: curl, mbstring, openssl

### 2. 系统命令检测
- **ping** (必需): 基础ping功能
- **fping** (强烈推荐): 高性能批量ping
- **nmap** (可选): 高级网络扫描
- **which** (推荐): 命令查找

### 3. 文件权限检测
- 日志目录读写权限
- 会话目录读写权限  
- 系统临时目录权限

### 4. 网络功能测试
- 基本ping连通性测试
- fping批量测试 (如果可用)
- 并行ping性能测试

### 5. 进程控制检测
- proc_open(): 并行处理支持
- exec(): 命令执行支持
- fastcgi_finish_request(): 后台任务支持

### 6. 系统限制检测
- 脚本执行时间限制
- 内存使用限制
- 输入变量限制

## 🎯 性能预期

根据检测结果，ping扫描性能预期如下：

### ⚡ 最优配置 (安装了fping)
- **扫描254个IP**: 5-10秒
- **扫描速度**: 每秒25-50个IP
- **推荐场景**: 生产环境

### 🚀 良好配置 (支持并行ping)
- **扫描254个IP**: 15-30秒  
- **扫描速度**: 每秒8-17个IP
- **适用场景**: 一般服务器环境

### 🐌 基础配置 (仅串行ping)
- **扫描254个IP**: 3-5分钟
- **扫描速度**: 每秒1-2个IP
- **注意**: 非常慢，不推荐用于大网段

## 🔧 常见问题解决

### 1. fping不可用
```bash
# CentOS/RHEL
sudo yum install fping

# Ubuntu/Debian  
sudo apt-get install fping

# 或手动编译安装
wget http://fping.org/dist/fping-4.4.tar.gz
tar -xzf fping-4.4.tar.gz
cd fping-4.4
./configure && make && sudo make install
```

### 2. proc_open被禁用
```bash
# 编辑php.ini
sudo nano /etc/php.ini

# 找到disable_functions行，移除proc_open
# 修改前: disable_functions = proc_open,exec,shell_exec
# 修改后: disable_functions = shell_exec

# 重启Web服务器
sudo systemctl restart apache2  # 或 nginx/httpd
```

### 3. ping权限问题
```bash
# 检查ping命令权限
ls -l /bin/ping

# 如果需要，设置SUID权限
sudo chmod u+s /bin/ping

# 或将Web服务器用户加入特定组
sudo usermod -a -G netdev www-data
```

### 4. 防火墙问题
```bash
# 检查ICMP出站规则
sudo iptables -L OUTPUT | grep icmp

# 允许ICMP出站 (如果被阻止)
sudo iptables -A OUTPUT -p icmp -j ACCEPT
```

## 📈 优化建议优先级

### 🔥 高优先级 (必须解决)
1. 安装fping工具
2. 启用proc_open函数
3. 确保ping命令可用
4. 修复文件权限问题

### ⚠️ 中优先级 (建议解决)  
1. 调整PHP执行时间限制
2. 增加内存限制
3. 启用fastcgi_finish_request

### ℹ️ 低优先级 (可选)
1. 安装可选PHP扩展
2. 安装nmap等高级工具

## 📞 技术支持

如果检测报告显示严重问题或需要技术支持，请：

1. 保存完整的检测报告
2. 记录服务器系统信息 (OS版本、PHP版本等)
3. 联系系统管理员进行环境调整

---

**注意**: 请在生产环境部署前务必运行此检测工具，确保所有关键功能正常工作。
