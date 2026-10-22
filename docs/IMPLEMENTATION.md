# CareCircle implementation ledger

## Required delivery
- [x] Laravel API, Filament administration and MySQL persistent storage
- [x] Flutter resident/family and volunteer application, Android APK
- [x] Authorization, matching, invitations, task lifecycle, handover and incidents
- [x] Queue worker, scheduling and in-app notifications
- [x] Backend integration/concurrency tests, Flutter analysis/tests/build, UI checks
- [x] Real application capture, captioned <=5 minute MP4 and editable composition
- [x] Reproducible installation, demo seed accounts and verification report

## Decisions
- One request aggregate owns lifecycle and current assignment. Invitation history is separate.
- Admin and API use the same domain service. Mobile uses Sanctum tokens, admin session login.
- MySQL is the authoritative integration database. Tests use a separate carecircle_test database.
- Fictional demo identities only. No public deployment or external messages.
- Initial service categories: prepaid grocery collection and non-valuable parcel collection.
- Foreground inbox refresh is available; background push is outside initial delivery.

## Current evidence
- 2026-10-20: PHP 8.5.7, Flutter 3.44.2, MySQL 8.0.46 reachable on localhost.
- Existing work directory was empty. Laravel and Flutter scaffolding started.

## Verified progress
- Backend: Laravel 13.37, Filament 5.10.2, Sanctum 4.3.3 installed and locked.
- MySQL domain migrated and fictional demo seeded. Backend server :8088, queue and scheduler started locally.
- 26 backend tests / 114 assertions passed, including independent-process MySQL competing-capacity test and admin review actions.
- Browser: admin login, volunteer approval and request details verified; approval screenshot saved.
- Flutter: resident/family/volunteer screens implemented, API connected. Analyze passed after resolving default test and lint issues.
- 3 Flutter widget/unit checks passed. Android debug APK built. iOS simulator app built without signing.
- Native integration initially exposed Material/ListTile rendering assertions; CarePanel converted to Material.
- Flutter integration screenshot API hung after navigation. Host-side evidence capture is being used instead; full native flow not yet passed.
- Original working directory name contained U+2060, crashing Flutter LSP path parsing. The project was moved to a plain path without hidden characters.
- HyperFrames project initialized at videos/carecircle-demo; actual footage and final render still pending.


## Delivery verification (final)
- Backend: 28 tests / 130 assertions pass on isolated MySQL.
- Flutter: analyze has no issues; 5 unit/widget checks pass.
- Android: real multi-role native flow and separate real connection-failure/retry test pass.
- Android release arm64 APK (19 MB): installed and cold-launched successfully on the emulator.
- iOS: current source built for simulator without signing; personal development team removed.
- Video: 142.4 s / 1080p / 30 fps H.264, real native and local browser capture, Chinese explanatory captions, deliberate silence. Check and decoded-frame inspection passed.
- Runtime launcher uses detached process sessions; status and repeated start verified across tool calls.
- No physical-device verification, public deployment, app-store submission, real user research or community pilot was performed.
