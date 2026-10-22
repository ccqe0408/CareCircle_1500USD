# CareCircle

**Everyday errands for older residents, handed over with proof.**

Sime Darby Property University Hackathon 2026 · People Track
Team 1500USD · Southern University College
Choong Qian Er (Team Leader) · Soh Shen · Lim Jie Lyn

▶ **Demo video (4:59):** https://youtu.be/sEotQIwfAAw

---

## What it does

In many townships, an older resident who needs a prepaid grocery order or a guardhouse parcel collected relies on a WhatsApp group: a relative asks, a neighbour volunteers, and the address and phone number sit in the chat. Nobody checks who is helping or whether the items reached the right person.

CareCircle turns that favour into a reviewed, private and verifiable handover, run by the township's own community team:

1. **Request**: the resident, or a family member whose consent has been verified by staff, asks for a prepaid grocery or ordinary parcel collection in a one-hour window.
2. **Match**: one reviewed volunteer is invited at a time, filtered by area, errand type, hours and workload. Every exclusion reason is visible to staff. Matching is rule-based and explainable; we do not claim AI optimisation.
3. **Do**: the volunteer accepts, starts and marks the task ready. The exact address appears only after acceptance.
4. **Prove**: the recipient checks the items and shows a one-time 6-digit code (10-minute expiry, stored only as a hash). Five wrong codes lock the task and alert staff.
5. **Oversee**: staff review volunteers and family consent, handle escalations, and keep an audit trail. Admin-closed requests are never counted as verified handovers.

Out of scope by design: cash, medicine, entering homes, transporting people and emergencies.

## System

| Part | Folder | Stack |
|---|---|---|
| Mobile app (resident, family, volunteer) | `mobile/` | Flutter (Android) |
| API | `backend/` | Laravel 13, Sanctum |
| Community admin portal | `backend/` | Filament |
| Database | | MySQL / MariaDB |
| Background jobs (matching, expiry, escalation) | `backend/` | Laravel queue worker and scheduler |
| Demo video project | `videos/carecircle-demo/` | |

## Run it locally

Full steps, in Chinese: [docs/README.md](docs/README.md). In short:

1. Create the databases `carecircle` and `carecircle_test` (utf8mb4).
2. Copy `backend/.env.example` to `backend/.env` and set your database connection.
3. Run `./scripts/setup-local.sh` to install dependencies, migrate and load fictional demo data.
4. Run `./scripts/start-local.sh` to start the API, queue worker and scheduler. The admin portal is at `http://127.0.0.1:8088/admin`.
5. In `mobile/`, run `flutter run -d <android-device-id>`. The default API address is for the Android emulator (`http://10.0.2.2:8088/api/v1`).

Demo accounts are listed in [docs/README.md](docs/README.md). They are fictional test identities for local use only.

## Testing and limits

- Backend and Flutter test results, and what was and was not tested: [docs/VERIFICATION.md](docs/VERIFICATION.md)
- API reference: [docs/API.md](docs/API.md)
- Development log: [docs/IMPLEMENTATION.md](docs/IMPLEMENTATION.md)
- Third-party licences: [docs/THIRD_PARTY.md](docs/THIRD_PARTY.md)

This is a local demonstration system. It has not been publicly deployed or published to an app store, and it has not been used with real residents. Not yet done before production: HTTPS deployment, push notifications, physical-device testing and iOS signing.

## Data and AI disclosure

- Every person, phone number and address in the prototype and video is fictional and marked DEMO. No real resident data was collected or used.
- We used AI coding assistants (OpenAI Codex and Anthropic Claude) to help write and review code, tests and documentation. The problem choice, product scope, design decisions and final claims were made and reviewed by the team.
