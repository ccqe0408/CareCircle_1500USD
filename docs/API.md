# CareCircle API v1

前缀 `/api/v1`，JSON 请求，`Accept: application/json`。移动端除 login/register/health 外使用 `Authorization: Bearer <token>`。Token 最长 7 天，退出撤销当前 Token，账号暂停拒绝访问。

| 模块 | 接口 |
|---|---|
| 账号 | POST /register、POST /login、GET/PATCH /me、POST /logout |
| 配置与家庭 | GET /meta；GET/POST /beneficiaries；PATCH /beneficiaries/{id}；GET/POST /family-authorizations；POST /family-authorizations/{id}/revoke |
| 志愿者与邀请 | GET/PUT /volunteer-profile；GET /offers；POST /offers/{id}/accept、decline |
| 请求与交接 | GET/POST /requests；GET /requests/{id}；POST /requests/{id}/start、arrive、cancel、handover-code、confirm、report |
| 消息 | GET /inbox；POST /inbox/{id}/read |

创建请求字段：beneficiary_id、service_category_id、title、pickup_address、scheduled_start、scheduled_end、prepaid_confirmed=true，notes 可选。时间采用含时区的 ISO 8601，存储为 UTC，展示为马来西亚时间。服务窗口最多 4 小时且在未来 30 天内。

接单后仅当前被指派志愿者获得必要地址。邀请摘要不含地址、电话及受助人姓名。家属访问需要当前有效授权，撤销后开放任务进入管理审核。

业务拒绝返回 422 和 errors；未登录 401、无权限 403、记录不存在 404、频率限制 429。客户端不能任意 PATCH 任务状态。取消 reason 至少 5 字符，投诉 description 至少 10 字符。确认接口接收六位 code，明文不写入日志或事件记录。

Admin 不另行实现一套状态更新接口，Filament Action 调用 app/Services/CareService.php。后台用户登录不赋予手机端注册用户管理员权限。
