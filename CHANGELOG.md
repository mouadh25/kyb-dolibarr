# Changelog — KYB Dolibarr Connector

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
