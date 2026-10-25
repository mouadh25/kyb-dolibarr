# Changelog — KYB Dolibarr Connector

All notable changes to the **KYB Dolibarr Connector** (`kybdolibarr`) are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html) alongside Dolibarr's native versioning matrix.

---

## Dolibarr Version Matrix & Release Mapping

The KYB connector maintains a unified, backwards-compatible codebase supporting Algerian corporate ERP deployments across supported Long-Term Support (LTS) foundations and modern release generations.

When deployed into a Dolibarr instance, the universal semantic version maps to the corresponding major version:

| Dolibarr Target Version | Module Version Code | Market Tier | Compatibility Status |
| :--- | :--- | :--- | :---: |
| **Dolibarr 22.0** | `2.0.4` | Latest Generation (PHP 8.2–8.3 Strict) | **🟢 Locked & Certified** |
| **Dolibarr 21.0** | `2.0.4` | Modern Enterprise Standard (2025–2026) | **🟢 Locked & Certified** |
| **Dolibarr 20.0** | `2.0.4` | Reference Target (Doliflex Constantine) | **🟢 Locked & Certified** |
| **Dolibarr 19.0** | `2.0.4` | Installed SME Enterprise Base (2023–2024)| **🟢 Locked & Certified** |

---

## [2.0.4] - 2026-10-25

### Added
* **11-Attribute Certified Corporate Identity Card:**
  Full institutional verification card on thirdparty view (`Societe` view mode) displaying Statut CNRC (Actif / Radié), Official Raison Sociale, NIF (Identifiant Fiscal Unique), Numéro RC officiel normalisé (Décret 05-468), NIS (Statistique National ONS), Article d'Imposition (Numéro de Rôle fiscal), Forme Juridique certifiée, Capital Social certifié, Gérant / Dirigeant Légal, Activité Principale (Code NAE + Libellé), Date d'Immatriculation / Radiation, et horodatage certifié Datacenter Algérie Télécom (Loi 25-11).
* **Autonomous Fleet Telemetry Heartbeat:**
  Instance telemetry heartbeat sending non-PII instance metrics (Dolibarr version, PHP environment, entity scale, idprof3 compliance distribution) to the sovereign gateway and retrieving client-specific version governance and changelog notes.
* **Automated ITIL Update Policy Governance:**
  Formalized update policy modes in Admin Setup (`Manual` vs `Automatic via Scheduled Job`) with daily automated heartbeat synchronization and 1-click in-app upgrades.

### Changed
* **Official Décret 05-468 RC Canonical Normalization:**
  Enforced canonical 16-character Trade Register formatting (`WW/00-XXXXXXX[A|B|C|D]YY`) on field exit and thirdparty card display with automatic zero-padding and bidirectional registry synchronization.
* **Safe Unverified State Presentation:**
  Replaced empty "Vérifié via KYB" notices on non-certified records with clean, neutral status indicators (`⚪ Non vérifié dans le référentiel national`), ensuring zero confusion for newly created thirdparties pending certification.
* **Objective Temporal Invoice Compliance Notices:**
  Standardized vendor invoice compliance notices under CIDTA Articles 169 & 176 and CTCA Article 30 to use objective regulatory language without punitive or criminal assertions.

### Fixed
* **Pre-2000 Algerian Trade Register (RC) Recognition (Wilayas 80–99):**
  Extended the Trade Register validator across the thick core and connector to accept historic pre-2000 Algerian business registrations carrying Wilaya codes 80 to 99 (e.g. `98B0102835` / `98/00-0102835B`), enabling instantaneous autocomplete and certification for historic commercial enterprises without lookup failures.
* **Strict SemVer Version Comparison & False Update Suppression:**
  Replaced raw string inequality comparisons with native PHP `version_compare($latestVer, self::CONNECTOR_VERSION, '>')` in telemetry sync and admin setup, eliminating spurious yellow update warning banners when running the current release.
* **Defense-in-Depth Personne Physique Category Exclusion Regex:**
  Hardened candidate filtering regex across both compressed (`16A1234567`) and official slash-delimited (`16/00-0012345A21`) formats to strictly exclude categories A (Commerçant), C (Artisan), and D (Prof. libérale) under Loi 18-07 / Loi 25-11.
* **Multi-Version Universal Compatibility:**
  Certified 100% operational parity and passing automated test suites across all 4 Dolibarr generations: Dolibarr 19.0 LTS, 20.0 (Reference), 21.0, and 22.0.

---

## [2.0.0] - 2026-10-22

### Added
* **Autonomous Telemetry Engine:**
  Implemented aggregate, Loi 25-11-compliant instance metrics gathering (Dolibarr version, PHP environment, entity scale, idprof3 compliance distribution) and authenticated heartbeat transmission to the sovereign gateway.
* **Dolibarr Native Scheduled Job (Cron):**
  Registered native Dolibarr background job (`KybCronTelemetry::run()`) executing daily via Dolibarr's built-in job scheduler without requiring external operating system crontabs.
* **1-Click In-Place Upgrade Engine:**
  Implemented automated in-place module upgrade downloading certified release packages, verifying package integrity, extracting safely into place, and refreshing active version constants.
* **Version & Telemetry Cockpit in Admin Setup:**
  Integrated dedicated version governance panel in module configuration displaying active version, latest certified version, live update badges, telemetry sync timestamps, and official changelog release notes.
* **Multi-Language Localization Expansion:**
  Added comprehensive French and Arabic language catalog coverage for version control, telemetry alerts, and automated maintenance workflows.

### Changed
* **Configurable Patch Delivery Policy:**
  Added administrative toggle (`KYB_AUTO_UPDATE_PATCHES`) empowering enterprises to choose between autonomous patch deployment during scheduled jobs or manual 1-click approval.

---

## [1.0.0] - 2026-10-19

### Added
* **Initial Production Release (v1.0.0):**
  Comprehensive corporate identity resolution and temporal invoice compliance module supporting Dolibarr 19.0, 20.0, 21.0, and 22.0.
* **Instant NIF & RC Auto-Completion:**
  Interactive asynchronous auto-lookup on thirdparty creation/edit (`formCreateThirdpartyOptions`), populating Raison Sociale, Sigle, RC, NIF, NIS, Article d'Imposition, and legal address.
* **Décret Exécutif 05-468 RC Mapping:**
  Native synchronization of the 4 Algerian mandatory identifiers via `idprof1` (RC), `idprof2` (Article d'Imposition), `idprof3` (NIF), and `idprof4` (NIS).
* **Temporal Vendor Bill Compliance Audit:**
  Automated pre-validation hook on supplier invoice validation auditing vendor strike-off status under CIDTA Articles 169 & 176 and CTCA Article 30.
* **Neutral Legal Compliance Notices:**
  Objective, non-punitive compliance warnings eliminating civil liability risks during accounting audits.
* **Sleek Monochromatic Thirdparty Status Card:**
  Institutional visual CNRC status badge (🟢 ACTIF / 🔴 RADIÉ / ⚪ NON VÉRIFIÉ).
* **Admin Configuration Cockpit:**
  Setup interface supporting custom API endpoint configuration, secure API key storage, configurable network timeout, strict validation blocking toggle, sandbox mode switch, and live connection test.
* **Bilingual French & Arabic Localization:**
  Complete language catalogs (`langs/fr_FR/kybdolibarr.lang` and `langs/ar_SA/kybdolibarr.lang`).
* **Multi-Version Testing Matrix:**
  Containerized testing harness certifying compatibility against MariaDB 10.11 and Dolibarr 19.0, 20.0, 21.0, and 22.0.
