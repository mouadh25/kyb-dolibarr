# Changelog — KYB Dolibarr Connector

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-10-22

### Added
- **Autonomous Telemetry Engine (GAP 3):** `class/kyb_telemetry.class.php` — gathers aggregate, Loi 25-11-compliant instance metrics (Dolibarr version, PHP, company/user counts, idprof3 compliance distribution) and POSTs them to `POST /api/v1/client/telemetry`. Mirrors Odoo's `_gather_instance_telemetry()` exactly.
- **`sendTelemetry()` in `KybClient`:** Authenticated heartbeat to `/api/v1/client/telemetry`. Falls back to anonymous `checkVersion()` when no API key is configured.
- **`checkVersion()` in `KybClient`:** Anonymous `GET /api/v1/version` with `client=dolibarr`, `version`, `dolibarr_version`, and `db_uuid` params. Returns `{has_update, latest_version, force_upgrade, changelog}`.
- **Dolibarr Native Cron Job (GAP 1):** `$this->cronjobs` registered in `modKybDolibarr.class.php` — executes `KybCronTelemetry::run()` daily via Dolibarr's built-in job scheduler (Admin → System → Cron). No external crontab required.
- **`cron/kyb_cron_telemetry.php` (GAP 5):** Cron entrypoint that syncs telemetry, reads `force_upgrade` from gateway response, and auto-applies patches when `KYB_AUTO_UPDATE_PATCHES=1` or `force_upgrade` is signaled — fully autonomous, zero admin click required.
- **1-Click In-Place Upgrade Engine (GAP 2):** `KybTelemetry::executeUpgrade()` — downloads `kyb_dolibarr.zip` from `/api/v1/downloads/kyb_dolibarr.zip`, validates the ZIP contains expected module files, extracts into a temp dir, copies over the existing module directory, then resets `KYB_HAS_UPDATE` and `KYB_FORCE_UPGRADE` flags.
- **Gateway Download Endpoint (GAP 7):** `GET /api/v1/downloads/kyb_dolibarr.zip` added to `apps/gateway/src/routes/version.ts` using the same multi-path resolution strategy as `kyb_odoo.zip`.
- **Version & Telemetry Admin Panel (GAP 4):** New section in `admin/setup.php` displaying installed version, latest available version with update badge, `force_upgrade` emergency banner, last telemetry sync timestamp, and gateway changelog notes.
- **"Vérifier les Mises à Jour" Button:** Manual heartbeat trigger in `admin/setup.php` (`?action=check_update`) — calls `KybTelemetry::syncWithGateway()` and refreshes all version constants.
- **"Mettre à Jour 1-Clic" Button:** Upgrade button in `admin/setup.php` (`?action=upgrade`) — only shown when `KYB_HAS_UPDATE=1` or `KYB_FORCE_UPGRADE=1`. Executes `KybTelemetry::executeUpgrade()`.
- **Auto-Update Patches Toggle (GAP 6):** `KYB_AUTO_UPDATE_PATCHES` setting in `admin/setup.php` — controls whether the daily cron auto-applies patches (autonomous) or defers to the admin 1-click button.
- **8 New Constants in Module Descriptor:** `KYB_AUTO_UPDATE_PATCHES`, `KYB_LATEST_VERSION`, `KYB_HAS_UPDATE`, `KYB_LAST_TELEMETRY`, `KYB_INSTALLED_VERSION`, `KYB_FORCE_UPGRADE`, `KYB_CHANGELOG` — all initialized safely on module install/upgrade.
- **21 New Localization Keys (FR + AR):** Full bilingual (French + Arabic) coverage for all new version, telemetry, and upgrade UI strings.

## [1.0.0] - 2026-10-19

### Added
- **Initial Production Release (v1.0.0)** supporting Dolibarr 19.0, 20.0, 21.0, and 22.0.
- **Instant NIF & RC Auto-Completion:** Interactive JavaScript auto-lookup on thirdparty creation/edit (`formCreateThirdpartyOptions`), populating Raison Sociale, Sigle, RC, NIF, NIS, Article d'Imposition, and address.
- **Décret Exécutif 05-468 RC Mapping:** Native synchronization of the 4 Algerian mandatory identifiers via `idprof1` (RC), `idprof2` (Article), `idprof3` (NIF), and `idprof4` (NIS).
- **Temporal Vendor Bill Compliance Audit:** Hook into `validateInvoice` auditing supplier strike-off status under CIDTA Articles 169 & 176 and CTCA Article 30.
- **Neutral Legal Notices:** Strictly non-punitive, objective compliance notices eliminating legal liability risks.
- **Sleek Monochromatic Thirdparty Status Card:** Visual CNRC status badge (🟢 ACTIF / 🔴 RADIÉ / ⚪ NON VÉRIFIÉ).
- **Admin Configuration Cockpit:** Setup interface (`admin/setup.php`) supporting custom API URL, secure API key storage, configurable network timeout (2–15s), strict validation blocking toggle, sandbox mode switch, and live connection test.
- **Bilingual Localization:** Complete French (`langs/fr_FR/kybdolibarr.lang`) and Arabic (`langs/ar_SA/kybdolibarr.lang`) language packs.
- **Multi-Version Testing Matrix (`infra/dolibarr/`):** Docker Compose testing harness supporting MariaDB 10.11 and Dolibarr 19.0, 20.0, 21.0, and 22.0.
