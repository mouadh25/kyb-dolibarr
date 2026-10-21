# KYB Dolibarr Connector — Algerian Corporate Identity & Temporal Tax Compliance

[![Dolibarr Version](https://img.shields.io/badge/Dolibarr-19.0%20|%2020.0%20|%2021.0%20|%2022.0-007acc.svg)](https://github.com/mouadh25/kyb-dolibarr/releases)
[![License: GPL-3](https://img.shields.io/badge/License-GPL--3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![Sovereign Cloud](https://img.shields.io/badge/Residency-Algérie%20Télécom%20Datacenter-10b981.svg)](https://kyb.digidevagency.dz)
[![Law Compliance](https://img.shields.io/badge/Compliance-Loi%2018--07%20/%2025--11-06b6d4.svg)](https://kyb.digidevagency.dz)
[![PHP](https://img.shields.io/badge/PHP-%E2%89%A5%208.0%20(8.0--8.3)-777bb4.svg)](https://www.php.net)

**Turnkey Dolibarr ERP/CRM module extending thirdparty management (`Societe`) with instant NIF/RC auto-completion (<15ms), real-time CNRC status verification, and temporal invoice deductibility audits under Algerian Fiscal Law (CIDTA Art. 169/176 & CTCA Art. 30).**

Developed and maintained by **Mouadh Benhassine** ([@mouadh25](https://github.com/mouadh25)) for **Digidev Agency** ([digidevagency.dz](https://digidevagency.dz)).  
Official Repository: [github.com/mouadh25/kyb-dolibarr](https://github.com/mouadh25/kyb-dolibarr)

---

## 1. Key Capabilities

* **Instant NIF & RC Auto-Completion (<15ms):** When creating or editing any thirdparty (customer or vendor) in Dolibarr, typing their 15-digit NIF (`idprof3`) or Trade Registry (`idprof1`) automatically resolves and populates:
  * Company Legal Name (*Raison Sociale*) & Commercial Sign (*Sigle*)
  * Trade Registry number formatted to statutory Décret Exécutif 05-468 (`WW/00-XXXXXXX[A|B|C|D]YY`)
  * Statistical Identification Number (*NIS* / `idprof4`)
  * Tax Role Number (*Article d'Imposition* / `idprof2`)
  * Registered Head Office Address (*Adresse Siège Social & Wilaya*)
* **Objective Temporal Status Audit (CIDTA Art. 176 & CTCA Art. 30):** Protects corporate accounting teams against tax audit reassessments (*rejets fiscaux sur État 104*). A vendor strike-off (*Radiation*) disallows deductions on transactions issued *after* the effective date. The connector audits status objectively against the exact invoice date with sanitized, neutral institutional notices (zero legal liability assertions).
* **Visual CNRC Status Badges on Thirdparty Card:** Displays color-coded legal status badges:
  * <span style="color:#10b981">**ACTIF**</span> — Active and legally registered entity in good standing.
  * <span style="color:#ef4444">**RADIÉ**</span> — Deregistered vendor (non-deductible expense warning).
* **Configurable Strict Mode:** Choose between an informative regulatory warning (recommended) or a strict validation veto when processing bills from deregistered suppliers.
* **100% Data Residency in Algeria (Law 18-07 / Law 25-11):** Connects directly to the KYB Sovereign API hosted at the Algérie Télécom Cloud Datacenter in Algiers. Zero data egress to offshore cloud providers.

---

## 2. Dolibarr Version Compatibility & Download Guide

The KYB Dolibarr module is engineered to run seamlessly across all major modern Dolibarr LTS and stable releases. A single unified package supports versions **19.0**, **20.0**, **21.0**, and **22.0**.

### 2.1 Compatibility Matrix

| Dolibarr Version | Supported PHP Runtimes | Status | Deployment Target & Notes | Recommended Download Package |
| :--- | :--- | :---: | :--- | :--- |
| **Dolibarr 19.0.x** | PHP 8.0, 8.1, 8.2 | **Verified** | Installed enterprise base (Algerian SMEs 2023–2024) | [`module_kybdolibarr-1.0.0.zip`](https://github.com/mouadh25/kyb-dolibarr/releases/latest) |
| **Dolibarr 20.0.x** | PHP 8.1, 8.2 | **Verified (Reference)** | Cloud baseline (**Doliflex Constantine** standard) | [`module_kybdolibarr-1.0.0.zip`](https://github.com/mouadh25/kyb-dolibarr/releases/latest) |
| **Dolibarr 21.0.x** | PHP 8.1, 8.2, 8.3 | **Verified** | Modern 2025/2026 enterprise installations | [`module_kybdolibarr-1.0.0.zip`](https://github.com/mouadh25/kyb-dolibarr/releases/latest) |
| **Dolibarr 22.0.x** | PHP 8.2, 8.3 | **Verified** | Latest rolling release, strict PHP typing | [`module_kybdolibarr-1.0.0.zip`](https://github.com/mouadh25/kyb-dolibarr/releases/latest) |

> **Architectural Note:** In Dolibarr, the HookManager API and core object descriptors maintain robust backward and forward compatibility. The module descriptor `modKybDolibarr.class.php` natively declares:
> ```php
> $this->phpmin = array(8, 0);
> $this->need_dolibarr_version = array(19, 0);
> $this->max_dolibarr_version = array(22, 99);
> ```
> As a result, you do not need separate builds for different Dolibarr versions; the official release ZIP works out-of-the-box on 19.0, 20.0, 21.0, and 22.0.

### 2.2 Download Links

* **Official Module ZIP:** [Download `module_kybdolibarr-1.0.0.zip` (Releases)](https://github.com/mouadh25/kyb-dolibarr/releases/latest)
* **Institutional User Manual (PDF):** [Download `USER_MANUAL.pdf`](doc/USER_MANUAL.pdf)
* **Pilot Pricing & Terms Memo (PDF):** [Download `GRILLE_TARIFAIRE_PILOTE.pdf`](doc/GRILLE_TARIFAIRE_PILOTE.pdf)

---

## 3. Installation Guide

### Option A: 1-Click via Dolibarr Web GUI (Recommended for All Versions)

1. Download **`module_kybdolibarr-1.0.0.zip`** from [GitHub Releases](https://github.com/mouadh25/kyb-dolibarr/releases/latest).
2. Log into your Dolibarr instance with an Administrator account.
3. In the left navigation menu, go to:  
   **Accueil → Configuration → Modules/Applications → Déployer/Installer un module externe**  
   *(Home → Setup → Modules/Applications → Deploy external module)*.
4. Drag and drop or upload `module_kybdolibarr-1.0.0.zip`.
5. Dolibarr automatically unpacks the module into `custom/kybdolibarr/`.
6. Navigate to the **Modules financiers** (*Financial Modules*) tab.
7. Locate **KYB Algérie** and click the toggle switch to **ACTIF / ON**.

### Option B: Command-Line / Git Clone (For SysAdmins, Docker & Doliflex Cloud)

1. Ensure the `custom/` directory is enabled in your `htdocs/conf/conf.php`:
   ```php
   $dolibarr_main_url_root_alt = '/custom';
   $dolibarr_main_document_root_alt = '/var/www/html/custom';
   ```

2. Clone the repository into your `custom/` directory:
   ```bash
   cd /var/www/html/custom
   git clone https://github.com/mouadh25/kyb-dolibarr.git kybdolibarr
   chown -R www-data:www-data /var/www/html/custom/kybdolibarr
   chmod -R 755 /var/www/html/custom/kybdolibarr
   ```

   > **Important:** The destination folder inside `custom/` must be named strictly `kybdolibarr` (in lowercase, without hyphens or underscores), matching the Dolibarr module descriptor identifier.

3. Activate the module in Dolibarr: **Accueil → Configuration → Modules/Applications → KYB Algérie**.

---

## 4. Configuration

1. In Dolibarr, click the **Settings (gear icon)** next to **KYB Algérie**, or go to:  
   **Accueil → Configuration → Modules/Applications → KYB Algérie (Configuration)**.
2. Configure the following parameters:

| Setting Parameter | Description | Default / Recommended Value |
| :--- | :--- | :--- |
| **Clé API KYB** | Your Sovereign API Bearer token | `sk_live_...` (or test key `sk_test_kyb_sandbox_key_001`) |
| **URL Serveur Souverain** | Base endpoint of the Sovereign Gateway | `https://kyb.digidevagency.dz` |
| **Délai d'attente (Timeout)** | Network socket timeout in seconds | `5` (configurable from 2 to 15 seconds) |
| **Mode Strict (Factures)** | Block invoice validation if vendor is struck off | Off (Informative warning recommended) |

3. Click **Tester la Connexion API** to execute a live health check and verify sub-100ms TLS connectivity to the gateway.

---

## 5. Free Sandbox Verification & Synthetic Test Fixtures

Test your Dolibarr integration safely without consuming production credits using our deterministic sandbox test fixtures (compliant with Law 18-07 / Law 25-11):

| Test NIF | Expected Legal Name | CNRC Status | Scenario Verified |
| :--- | :--- | :---: | :--- |
| `000000000000001` | **SARL ALGERIE TECH** | `ACTIF` / `RADIÉ` (Invoice) | Active entity on thirdparty resolve; triggers temporal compliance veto on bills dated after `2023-01-01`. |
| `000000000000002` | **SARL ALGERIE TECH TEST** | `ACTIF` | Compliant vendor; passes thirdparty resolution and invoice temporal validation without warnings. |
| `000016001234567` | **SARL DEMO RESELLER** | `ACTIF` | Commercial demonstration and partner onboarding testing entity. |

> **Sandbox Billing Rule:** Any NIF starting with `0000` automatically executes in sandbox mode with 0 credit deduction and synthetic responses.

---

## 6. Algerian Regulatory Standards & Field Mapping

The module integrates natively with Dolibarr's Algerian country dictionary (`c_country = DZ`):

| Dolibarr Field | Algerian Regulatory Identifier | Reference Authority | Legal Validation Standard |
| :--- | :--- | :--- | :--- |
| `idprof1` | **Registre du Commerce (RC)** | Centre National du Registre du Commerce (CNRC) | Format canonique Décret Exécutif 05-468 (`WW/00-XXXXXXX[A\|B\|C\|D]YY`) |
| `idprof2` | **Numéro d'Article d'Imposition (AI)** | Direction Générale des Impôts (DGI / N° de Rôle) | Secret fiscal (Art. 114 CPF), crowd-caching cumulatif |
| `idprof3` | **Identifiant Fiscal Unique (NIF)** | Direction Générale des Impôts (DGI) | 15 ou 20 chiffres (Préfixe wilaya historique `00` accepté) |
| `idprof4` | **Identifiant Statistique (NIS)** | Office National des Statistiques (ONS) | Extrait de la fiche CNRC officielle ou cache certifié |

### Neutral Legal Compliance Notices
In accordance with Algerian tax governance, the connector strictly refrains from making criminal accusations or assertions of tax fraud. Instead, it generates objective, sanitized regulatory notices:
* **Supplier Bill Warning:** *"Notice de Conformité Fiscale : Fournisseur radié au registre public (Date : {date_radiation}). Déductibilité de la charge à vérifier selon les articles 169 & 176 du CIDTA et l'article 30 du CTCA."*

---

## 7. Architecture & Keep-Alive Flow

```
┌───────────────────────────────────────┐                     ┌─────────────────────────────────────────┐
│        Dolibarr ERP Instance          │                     │           KYB Sovereign Gateway         │
│     (Versions 19.0, 20.0, 21.0, 22.0) │                     │        (Algérie Télécom Datacenter)     │
└──────────────────┬────────────────────┘                     └───────────────────┬─────────────────────┘
                   │                                                              │
                   │  1. User enters NIF or RC in Thirdparty Card                 │
                   │     [Sub-20ms AJAX over persistent socket]                   │
                   │─────────────────────────────────────────────────────────────>│
                   │                                                              │
                   │  2. Authoritative Resolution (/api/v1/entity/resolve)        │
                   │<─────────────────────────────────────────────────────────────│
                   │     Returns: Raison Sociale, Sigle, RC Décret 05-468,        │
                   │     NIS, Article d'Imposition, Wilaya, CNRC Status           │
                   │                                                              │
                   │  3. Auto-populates idprof1..4 & displays status badge        │
                   │                                                              │
                   │  4. User validates Supplier Invoice                          │
                   │     [POST /api/v1/compliance/verify]                         │
                   │─────────────────────────────────────────────────────────────>│
                   │                                                              │
                   │  5. Temporal Strike-Off Audit (CIDTA 176 / CTCA 30)          │
                   │<─────────────────────────────────────────────────────────────│
                   │     Returns: Compliant (true/false), Radiation Date, Notice  │
                   ▼                                                              ▼
```

---

## 8. Documentation & Technical Resources

* **Sovereign Gateway Web Portal:** [kyb.digidevagency.dz](https://kyb.digidevagency.dz)
* **Authoritative API Documentation:** [kyb.digidevagency.dz/docs](https://kyb.digidevagency.dz/docs)
* **Interactive Scalar OpenAPI 3.1 Reference:** [kyb.digidevagency.dz/docs/scalar](https://kyb.digidevagency.dz/docs/scalar)
* **Institutional User Manual (PDF):** [`doc/USER_MANUAL.pdf`](doc/USER_MANUAL.pdf)
* **Pilot Pricing & Terms Memo (PDF):** [`doc/GRILLE_TARIFAIRE_PILOTE.pdf`](doc/GRILLE_TARIFAIRE_PILOTE.pdf)
* **Release Changelog:** [`CHANGELOG.md`](CHANGELOG.md)

---

## 9. License & Commercial Support

* **License:** Distributed under the **GNU General Public License v3.0 (GPL-3.0)**.
* **Commercial Inquiries & Integrations:** `contact@digidevagency.dz`
* **Parent Enterprise:** **Digidev Agency** ([digidevagency.dz](https://digidevagency.dz))
* **Author:** **Mouadh Benhassine** ([@mouadh25](https://github.com/mouadh25))
