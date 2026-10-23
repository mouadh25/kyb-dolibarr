<?php
/**
 * KYB Instance Telemetry Collector for Dolibarr
 *
 * Gathers aggregate instance metrics (fully Loi 25-11 compliant — zero PII)
 * and sends them to the KYB Sovereign Gateway for fleet observability and
 * autonomous update distribution (ADR #187).
 *
 * Parallel to Odoo's _gather_instance_telemetry() + action_check_kyb_update()
 * in res_config_settings.py.
 *
 * @package   KYB Dolibarr
 * @author    Mouadh Benhassine (Digidev Agency)
 * @copyright 2026 Digidev Agency
 * @license   GPL-3.0-or-later
 */

require_once __DIR__ . '/kyb_client.class.php';

if (!function_exists('dolibarr_set_const') && defined('DOL_DOCUMENT_ROOT')) {
    require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
}

/**
 * Class KybTelemetry
 * Collects aggregate, non-PII Dolibarr instance metrics and submits them
 * to the Sovereign KYB Gateway heartbeat endpoint.
 */
class KybTelemetry
{
    /**
     * @var DoliDB Database handler
     */
    private $db;

    /**
     * @var string Connector version reported in telemetry
     */
    const CONNECTOR_VERSION = '2.0.0';

    /**
     * @var string Client type identifier sent to gateway fleet
     */
    const CLIENT_TYPE = 'dolibarr';

    /**
     * Constructor.
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Gathers aggregate, Loi 25-11-compliant instance metadata.
     *
     * Mirrors Odoo's _gather_instance_telemetry() exactly:
     * - Zero PII (no names, emails, or phone numbers)
     * - Only aggregate counts and configuration posture
     *
     * @return array Payload array ready for POST /api/v1/client/telemetry
     */
    public function gatherInstanceTelemetry()
    {
        global $conf, $langs;

        // --- Instance identity (non-PII) ---
        $dolibarrVersion = DOL_VERSION;
        $phpVersion = PHP_VERSION;
        $platform = PHP_OS . ' PHP/' . PHP_VERSION;

        // Dolibarr installation UUID (stored in llx_const, never changes after install)
        $instanceUuid = $this->getDolibarrConst('MAIN_UNIQUE_ID');
        if (empty($instanceUuid)) {
            // Fallback: derive a stable UUID from the database name
            $instanceUuid = md5($conf->db->name . $conf->db->host);
        }

        $instanceUrl = $this->getDolibarrConst('MAIN_URL_ROOT', 'https://unknown');
        $instanceName = $this->getDolibarrConst('MAIN_INFO_SOCIETE_NOM', 'unknown');
        $currency = !empty($conf->currency) ? $conf->currency : 'DZD';

        // --- Module footprint ---
        $installedModules = $this->getInstalledModules();
        $isEnterprise = in_array('mDoliWoo', $installedModules)
            || in_array('mDoliPayment', $installedModules)
            || $this->getDolibarrConst('DOLIWOO_ACTIVATED') === '1';

        // --- Company & user scale ---
        $companyCount = $this->countRows('llx_societe', 'client > 0 OR fournisseur > 0');
        $activeUsers = $this->countRows('llx_user', 'statut = 1 AND employee = 1');

        // --- Thirdparty compliance distribution (aggregate counts only) ---
        $totalThirdparties = $this->countRows('llx_societe', '1=1');
        // Partners verified = those with a non-empty idprof3 (NIF set by KYB)
        $verifiedThirdparties = $this->countRows('llx_societe', "idprof3 != '' AND idprof3 IS NOT NULL");
        // Active = those KYB has set a special note or where status is known
        // (we use idprof3 presence as proxy — true kyb_statut not stored in Dolibarr core)
        $activeThirdparties = $verifiedThirdparties; // conservative — same as verified
        $unverifiedCandidates = $this->countRows(
            'llx_societe',
            "(idprof3 IS NULL OR idprof3 = '') AND (idprof1 != '' OR fournisseur = 1)"
        );

        // --- Invoice audit metrics ---
        $auditedInvoices = 0;
        $compliantInvoices = 0;
        $blockedInvoices = 0;
        // Note: Dolibarr v1.0.0 does not store per-invoice KYB audit status in DB.
        // These remain 0 until a future schema extension adds llx_facture_fournisseur.kyb_audit_status.

        // --- Configuration security posture ---
        $strictBlocking = $this->getDolibarrConst('KYB_STRICT_BLOCKING', '0');
        $sandboxMode = $this->getDolibarrConst('KYB_SANDBOX_MODE', '0');
        $autoUpdatePatches = $this->getDolibarrConst('KYB_AUTO_UPDATE_PATCHES', '1'); // default ON

        $configPosture = array(
            'invoice_blocking_policy' => ((int)$strictBlocking === 1) ? 'strict_block' : 'soft_warn',
            'sandbox_mode'            => (bool)(int)$sandboxMode,
            'auto_update_patches'     => (bool)(int)$autoUpdatePatches,
            'sovereign_autocomplete'  => true, // always enabled in Dolibarr connector
        );

        return array(
            'client_type'       => self::CLIENT_TYPE,
            'connector_version' => self::CONNECTOR_VERSION,
            'odoo_version'      => null,
            'dolibarr_version'  => $dolibarrVersion,
            'database_uuid'     => $instanceUuid,
            'database_name'     => $conf->db->name,
            'instance_url'      => $instanceUrl,
            'python_version'    => null,
            'php_version'       => $phpVersion,
            'platform'          => $platform,
            'is_enterprise'     => $isEnterprise,
            'company_count'     => $companyCount,
            'active_users'      => $activeUsers,
            'currency'          => $currency,
            'installed_apps'    => $installedModules,
            'metrics'           => array(
                'total_partners'       => $totalThirdparties,
                'verified_partners'    => $verifiedThirdparties,
                'active_partners'      => $activeThirdparties,
                'radie_partners'       => 0,
                'suspendu_partners'    => 0,
                'unverified_candidates'=> $unverifiedCandidates,
                'audited_invoices'     => $auditedInvoices,
                'compliant_invoices'   => $compliantInvoices,
                'blocked_invoices'     => $blockedInvoices,
            ),
            'configuration' => $configPosture,
        );
    }

    /**
     * Sends telemetry payload to gateway and returns version check result.
     * Mirrors Odoo's action_check_kyb_update() + cron_kyb_sync_telemetry().
     *
     * @return array|null {has_update: bool, latest_version: string, force_upgrade: bool, changelog: string}
     */
    public function syncWithGateway()
    {
        $client = new KybClient();
        $payload = $this->gatherInstanceTelemetry();

        // Step 1: POST authenticated telemetry heartbeat (if API key set)
        $versionInfo = $client->sendTelemetry($payload);

        // Step 2: If no API key (anonymous), fall back to GET /api/v1/version
        if (empty($versionInfo)) {
            $versionInfo = $client->checkVersion(self::CONNECTOR_VERSION, self::CLIENT_TYPE, $payload['database_uuid']);
        }

        if (empty($versionInfo)) {
            dol_syslog('KYB Telemetry: gateway unreachable — skipping sync.', LOG_WARNING);
            return null;
        }

        // Step 3: Persist results in Dolibarr constants for UI display and autonomous upgrade
        $hasUpdate   = !empty($versionInfo['has_update']);
        $latestVer   = !empty($versionInfo['latest_version']) ? $versionInfo['latest_version'] : self::CONNECTOR_VERSION;
        $forceUpgrade = !empty($versionInfo['force_upgrade']);
        $changelog   = !empty($versionInfo['changelog']) ? $versionInfo['changelog'] : '';

        global $conf;
        dolibarr_set_const($this->db, 'KYB_LATEST_VERSION', $latestVer, 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($this->db, 'KYB_HAS_UPDATE', $hasUpdate ? '1' : '0', 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($this->db, 'KYB_FORCE_UPGRADE', $forceUpgrade ? '1' : '0', 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($this->db, 'KYB_LAST_TELEMETRY', dol_now(), 'chaine', 0, '', $conf->entity);
        if (!empty($changelog)) {
            dolibarr_set_const($this->db, 'KYB_CHANGELOG', $changelog, 'chaine', 0, '', $conf->entity);
        }

        dol_syslog(sprintf(
            'KYB Telemetry: sync OK. has_update=%s, latest=%s, force_upgrade=%s',
            $hasUpdate ? 'true' : 'false',
            $latestVer,
            $forceUpgrade ? 'true' : 'false'
        ), LOG_INFO);

        return array(
            'has_update'     => $hasUpdate,
            'latest_version' => $latestVer,
            'force_upgrade'  => $forceUpgrade,
            'changelog'      => $changelog,
        );
    }

    /**
     * Executes autonomous in-place module upgrade.
     * Downloads the release ZIP from gateway, validates, extracts into module directory,
     * and triggers Dolibarr module re-initialization.
     *
     * Mirrors Odoo's action_upgrade_kyb_module() exactly.
     *
     * @param string $modulePath Absolute path to the kybdolibarr module directory
     * @return bool True on success, false on failure
     */
    public function executeUpgrade($modulePath)
    {
        global $conf;

        $apiUrl = $this->getDolibarrConst('KYB_API_URL', 'https://kyb.digidevagency.dz');
        $downloadUrl = rtrim($apiUrl, '/') . '/api/v1/downloads/kyb_dolibarr.zip';

        dol_syslog('KYB Upgrade: downloading from ' . $downloadUrl, LOG_INFO);

        // 1. Download ZIP from sovereign gateway
        $ch = curl_init($downloadUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_USERAGENT, 'KYB-Dolibarr-Connector/' . self::CONNECTOR_VERSION . ' (Dolibarr ' . DOL_VERSION . ')');

        $apiKey = $this->getDolibarrConst('KYB_API_KEY', '');
        if (!empty($apiKey)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $apiKey));
        }

        $zipContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($zipContent === false || !empty($curlError) || $httpCode !== 200) {
            dol_syslog('KYB Upgrade: download failed (HTTP ' . $httpCode . ' / cURL: ' . $curlError . ')', LOG_ERR);
            return false;
        }

        // 2. Write ZIP to temp file
        $tmpZip = DOL_DATA_ROOT . '/kyb_dolibarr_upgrade_tmp.zip';
        if (file_put_contents($tmpZip, $zipContent) === false) {
            dol_syslog('KYB Upgrade: failed to write temp ZIP to ' . $tmpZip, LOG_ERR);
            return false;
        }

        // 3. Validate it is a real ZIP with __manifest__.py or kyb_client.class.php
        if (!class_exists('ZipArchive')) {
            dol_syslog('KYB Upgrade: PHP ZipArchive extension not available.', LOG_ERR);
            @unlink($tmpZip);
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpZip) !== true) {
            dol_syslog('KYB Upgrade: downloaded file is not a valid ZIP.', LOG_ERR);
            @unlink($tmpZip);
            return false;
        }

        $hasManifest = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (strpos($name, 'modKybDolibarr.class.php') !== false || strpos($name, 'kyb_client.class.php') !== false) {
                $hasManifest = true;
                break;
            }
        }

        if (!$hasManifest) {
            dol_syslog('KYB Upgrade: ZIP does not contain expected module files — aborting.', LOG_ERR);
            $zip->close();
            @unlink($tmpZip);
            return false;
        }

        // 4. Extract into temp directory
        $tmpDir = DOL_DATA_ROOT . '/kyb_dolibarr_upgrade_extract';
        if (is_dir($tmpDir)) {
            dol_delete_dir_recursive($tmpDir);
        }
        dol_mkdir($tmpDir);
        $zip->extractTo($tmpDir);
        $zip->close();

        // 5. Determine source root (handles flat or nested kyb_dolibarr/ layout)
        $sourceRoot = $tmpDir;
        if (is_dir($tmpDir . '/kyb_dolibarr') && is_file($tmpDir . '/kyb_dolibarr/core/modules/modKybDolibarr.class.php')) {
            $sourceRoot = $tmpDir . '/kyb_dolibarr';
        }

        // 6. Copy extracted files over existing module files
        $success = $this->copyDirRecursive($sourceRoot, $modulePath);

        // 7. Cleanup
        dol_delete_dir_recursive($tmpDir);
        @unlink($tmpZip);

        if (!$success) {
            dol_syslog('KYB Upgrade: file copy into module directory failed.', LOG_ERR);
            return false;
        }

        // 8. Persist new version in Dolibarr constants
        dolibarr_set_const($this->db, 'KYB_INSTALLED_VERSION', self::CONNECTOR_VERSION, 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($this->db, 'KYB_HAS_UPDATE', '0', 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($this->db, 'KYB_FORCE_UPGRADE', '0', 'chaine', 0, '', $conf->entity);

        // Ensure all module hooks are registered in Dolibarr
        $hooksList = array(
            'thirdpartycard',
            'thirdpartylist',
            'invoicecard',
            'invoicesuppliercard',
            'propalcard',
            'ordersuppliercard',
            'formObjectOptions',
            'doActions',
            'addMoreMassActions',
            'doMassActions'
        );
        dolibarr_set_const($this->db, 'MAIN_MODULE_KYBDOLIBARR_HOOKS', json_encode($hooksList), 'chaine', 0, '', $conf->entity);

        dol_syslog('KYB Upgrade: in-place upgrade to v' . self::CONNECTOR_VERSION . ' completed successfully.', LOG_INFO);
        return true;
    }

    // ──────────────────────────── Private Helpers ────────────────────────────

    /**
     * Reads a Dolibarr global constant from llx_const.
     *
     * @param string $constName Constant name
     * @param string $default   Default value if not set
     * @return string
     */
    private function getDolibarrConst($constName, $default = '')
    {
        global $conf;
        return isset($conf->global->$constName) && $conf->global->$constName !== ''
            ? (string)$conf->global->$constName
            : $default;
    }

    /**
     * Counts rows in a Dolibarr table with a WHERE clause.
     *
     * @param string $table  Table name (without prefix normalization — use llx_ prefix)
     * @param string $where  SQL WHERE clause fragment
     * @return int
     */
    private function countRows($table, $where)
    {
        $prefix = defined('MAIN_DB_PREFIX') ? MAIN_DB_PREFIX : 'llx_';
        if (strpos($table, $prefix) === 0) {
            $tableName = $table;
        } else {
            $tableName = $prefix . $table;
        }
        $sql = 'SELECT COUNT(*) as total FROM ' . $tableName . ' WHERE ' . $where;
        $resql = $this->db->query($sql);
        if ($resql) {
            $row = $this->db->fetch_object($resql);
            return (int)$row->total;
        }
        return 0;
    }

    /**
     * Returns a list of active Dolibarr module names (mod* classes).
     *
     * @return string[]
     */
    private function getInstalledModules()
    {
        $modules = array();
        $sql = "SELECT name FROM " . MAIN_DB_PREFIX . "const WHERE name LIKE 'MAIN_MODULE_%' AND value = '1' AND name NOT LIKE '%_HOOKS%' AND name NOT LIKE '%_VERSION%'";
        $resql = $this->db->query($sql);
        if ($resql) {
            while ($row = $this->db->fetch_object($resql)) {
                $modules[] = strtolower(str_replace('MAIN_MODULE_', '', $row->name));
            }
        }
        return $modules;
    }

    /**
     * Recursively copies source directory into destination.
     *
     * @param string $src Source directory
     * @param string $dst Destination directory
     * @return bool
     */
    private function copyDirRecursive($src, $dst)
    {
        if (!is_dir($dst)) {
            if (!mkdir($dst, 0755, true)) {
                return false;
            }
        }

        $items = scandir($src);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $srcItem = $src . '/' . $item;
            $dstItem = $dst . '/' . $item;

            if (is_dir($srcItem)) {
                if (!$this->copyDirRecursive($srcItem, $dstItem)) {
                    return false;
                }
            } else {
                if (!@copy($srcItem, $dstItem)) {
                    dol_syslog('KYB Upgrade: failed to copy ' . $srcItem . ' to ' . $dstItem, LOG_WARNING);
                    return false;
                }
            }
        }

        return true;
    }
}
