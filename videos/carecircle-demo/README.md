# CareCircle 演示视频工程

交付影片：142.4 秒，1920×1080，30fps，H.264。影片采用真实 Android 模拟器与本地管理后台录屏，配中文说明字幕，无旁白或音乐。

## 编辑与重新导出

1. 在此目录执行 `npm run dev`，或 `npx hyperframes preview --background`，打开输出的 Studio 地址。可直接编辑文字和时间线。
2. 主场景在 `compositions/frames/`，字幕源在 `captions.zh-CN.srt`，剪辑来源与速度在 `edit-decisions.json`。字幕为人工编排的操作说明，不是语音转写。
3. 素材在 `assets/raw/` 和 `assets/screens/`；`native-flow.mp4` 是连续的实际跨角色操作。后台段为真实页面录制，放慢播放以便阅读，结尾使用录制后的真实截图停留。
4. 执行 `npm run check`；确认后执行 `npm run render -- --output carecircle-demo.mp4`。本地导出无需账号，不会发布或上传。

中文字体来自 Noto Sans SC，许可为 assets/fonts/OFL.txt。当前导出使用 112 KB 子集字体。若新增未包含的中文，可重新制作字体子集，或把场景 @font-face 的 src 改成随附完整 NotoSansSC.ttf。完整字体在本地工程中可用，但会产生不内联大字体的提示。

可使用 `scripts/build-video-project.py` 从项目根目录重新生成既定场景。该脚本会重建 HTML，因此手工编辑场景后不要随意运行它覆盖修改。

原始 capture 帧及命令输出保留在项目 outputs/；源码交付包包含重新剪辑所需的 MP4 原始素材和截图，不包含临时缓存。
