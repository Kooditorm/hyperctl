# hyperctl

用于控制 Hyperf 应用进程生命周期的命令行工具。

**简介**

`hyperctl` 提供启动、停止、重启和查看 Hyperf 服务状态的便捷命令，支持守护进程模式、文件变更监听（watch）自动重启以及清理 runtime 容器等功能。

**特性**

- 启动/停止/重启/查看 Hyperf 服务
- 支持守护进程模式（`-d`）
- 支持文件变更监听并自动重启（`--watch` / `-w`）
- 支持清理 runtime 容器（`--clear` / `-c`）
- 可指定 PHP 可执行文件路径（`--php` / `-p`）

**安装**

项目已发布为 Composer 包；在你的 Hyperf 项目中安装：

```bash
composer require kooditorm/hyperctl --dev
```

安装后，Hyperf 会通过 `ConfigProvider` 自动注册命令（参见 `composer.json` 的 `extra.hyperf.config` 配置）。

**使用**

命令通过 Hyperf 的控制台入口运行，示例：

```bash
# 在项目根目录下运行 Hyperf 控制台
php bin/hyperf.php ctl:start       # 启动服务
php bin/hyperf.php ctl:start -d    # 守护模式启动
php bin/hyperf.php ctl:start -c    # 启动前清理 runtime 容器
php bin/hyperf.php ctl:start -w -t 3   # 监听文件变化并自动重启（间隔 3 秒）
php bin/hyperf.php ctl:start -p /usr/bin/php  # 指定 PHP 可执行路径

php bin/hyperf.php ctl:stop        # 停止服务
php bin/hyperf.php ctl:restart     # 重启服务
php bin/hyperf.php ctl:restart -c  # 重启并清理 runtime 容器
php bin/hyperf.php ctl:status      # 查看服务状态
```

命令名称说明：`ctl:start`、`ctl:stop`、`ctl:restart`、`ctl:status`。

**配置与实现细节**

- 进程 PID 文件位置：`runtime/hyperf.pid`（由命令读取以判断进程状态）
- Watch 模式会监控 `app`、`config` 目录以及 `.env` 文件，支持 `inotify` 扩展优先使用
- 需要 PHP >= 8.1，并依赖 `hyperf/command` 包

### 自定义配置

你可以通过修改配置文件来自定义行为，例如设置日志路径、PID 文件位置等。

## 贡献

欢迎提交 Issue 或 Pull Request 来改进该项目！

### 开发者信息

- **作者**: oswin.hu  
- **邮箱**: [oswin.hu@gmail.com](mailto:oswin.hu@gmail.com)

## 许可证

本项目采用 MIT 许可证，详情请查看 [LICENSE](LICENSE) 文件。

