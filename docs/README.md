# CareCircle 本地运行

注意：项目路径不要包含隐藏字符（例如 U+2060），否则 Flutter LSP 会在解析路径时崩溃。请把项目放在普通路径下再运行。

## 环境
PHP 8.3+（具体最低版本以 composer.json / composer.lock 的平台约束为准）、Composer、MySQL 8、Node.js、Flutter、Android SDK。当前机器验证环境和最终测试结果见 VERIFICATION.md。

1. 创建专用数据库 `carecircle` 与 `carecircle_test`，字符集 utf8mb4。测试只运行在后者。
2. 在 `backend/.env` 设置本机数据库连接。配置范例在 `.env.example`，不要提交实际密钥。
3. 执行 `./scripts/setup-local.sh`，安装锁定依赖、迁移和导入虚构演示数据。Seeder 可重复执行，不会清空已有请求。
4. 执行 `./scripts/start-local.sh`，启动独立运行的 API、队列监听器和定时调度。执行 `./scripts/stop-local.sh` 可停止这些服务；脚本只管理此项目创建的进程。Admin 地址为 `http://127.0.0.1:8088/admin`。
5. 在 mobile/ 执行 `flutter run -d <Android设备ID>`。默认 API 为模拟器专用 `http://10.0.2.2:8088/api/v1`。

数据库创建示例（仅适用于本机 root 无密码的开发配置）：
```bash
mysql -h127.0.0.1 -uroot -e 'CREATE DATABASE IF NOT EXISTS carecircle CHARACTER SET utf8mb4; CREATE DATABASE IF NOT EXISTS carecircle_test CHARACTER SET utf8mb4;'
```
其他数据库账号请使用自己的连接方式。测试配置当前指向本机 root；换环境时修改 phpunit.xml 的测试连接。

## 虚构演示账号

以下均为此项目专用测试身份，不能用于真实居民或公开环境。

| 用途 | 邮箱 | 测试密码 |
|---|---|---|
| 管理员 | admin@carecircle.test | CircleAdmin2026! |
| 居民 Mrs Tan | resident@carecircle.test | CircleDemo2026! |
| 获授权家属 Lily | family@carecircle.test | CircleDemo2026! |
| 已审核志愿者 Ali | ali@carecircle.test | CircleDemo2026! |
| 其他志愿者 | mei@carecircle.test、dan@carecircle.test、sofia@carecircle.test | CircleDemo2026! |
| 待核验家属 Jason | jason@carecircle.test | CircleDemo2026! |

初次 seed 的 Sofia 待审核，Daniel 的可用时间与下午演示任务不符。后续验证可能已经审核 Sofia，当前状态以数据库为准。家属授权由管理方核验，不能通过 App 自行批准。

## 设备与构建

Android 模拟器连接主机回环地址；真实手机必须配置可达的 HTTPS API：
```bash
flutter build apk --release --dart-define=API_BASE_URL=https://your-server.example/api/v1
```
首轮演示 APK 采用开发签名与模拟器 API，不能直接宣称适用于任意手机。HTTP 白名单仅允许模拟器回环和 localhost，其他地址必须 HTTPS。没有执行公开部署或上架。

iOS 模拟器使用 `--dart-define=API_BASE_URL=http://127.0.0.1:8088/api/v1`。签名和真机安装需独立验收；不要把模拟器编译通过当作真机测试通过。

## 核心约束

服务仅支持已付款日用品及非贵重包裹取送。无支付、医疗、入屋、载人或紧急救援。没有后台推送，通知在前台刷新与任务详情定期刷新时读取。
邀请按资格、区域、类别、时间与容量筛选，按注册顺序作可解释排序，不宣称距离最短或 AI 优化。所有排除原因可在后台任务详情查看。
确认码仅在待交接阶段由居民／授权家属生成，10 分钟有效，5 次错误后升级管理员。管理员关闭不计作交接验证完成。
