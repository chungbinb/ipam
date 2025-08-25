#!/bin/bash

echo "========================================="
echo "   IT资产管理系统 - 快速部署脚本"
echo "========================================="

# 检查当前目录
if [ ! -f "install.php" ]; then
    echo "错误: 请在项目根目录下运行此脚本"
    exit 1
fi

# 创建必要的目录
echo "创建必要的目录..."
mkdir -p storage/{sessions,logs,uploads}

# 设置目录权限
echo "设置目录权限..."
chmod 755 storage
chmod 755 storage/sessions
chmod 755 storage/logs
chmod 755 storage/uploads

# 检查Web服务器
if command -v nginx &> /dev/null; then
    echo "检测到 Nginx"
    if [ -f "nginx.conf" ]; then
        echo "建议使用项目提供的 nginx.conf 配置文件"
    fi
elif command -v apache2 &> /dev/null || command -v httpd &> /dev/null; then
    echo "检测到 Apache"
    echo "请确保已启用 mod_rewrite 模块"
fi

# 检查PHP
if command -v php &> /dev/null; then
    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    echo "PHP版本: $PHP_VERSION"
    
    # 检查必要的PHP扩展
    echo "检查PHP扩展..."
    php -m | grep -q pdo && echo "✓ PDO" || echo "✗ PDO (必需)"
    php -m | grep -q pdo_mysql && echo "✓ PDO MySQL" || echo "✗ PDO MySQL (必需)"
    php -m | grep -q json && echo "✓ JSON" || echo "✗ JSON (必需)"
    php -m | grep -q curl && echo "✓ cURL" || echo "✗ cURL (推荐)"
else
    echo "错误: 未检测到PHP"
    exit 1
fi

echo ""
echo "========================================="
echo "部署准备完成！"
echo ""
echo "下一步操作:"
echo "1. 配置Web服务器指向当前目录"
echo "2. 访问 http://your-domain/install.php"
echo "3. 按照安装向导完成配置"
echo "4. 安装完成后删除 install.php 文件"
echo ""
echo "默认管理员账号:"
echo "用户名: admin"
echo "密码: 123456"
echo "========================================="
