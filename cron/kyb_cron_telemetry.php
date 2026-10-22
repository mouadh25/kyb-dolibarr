<?php
/**
 * Dolibarr Native Cron Entrypoint — KYB Telemetry & Autonomous Update Engine
 *
 * This class is invoked by Dolibarr's built-in cron scheduler (Admin > System > Cron)
 * exactly once per day via the job registered in modKybDolibarr.class.php.
 *
 * Execution flow (mirrors Odoo's ir_cron_kyb_telemetry_sync):
 *   1. Gather aggregate instance metrics (Loi 25-11 compliant, zero PII).
 *   2. POST telemetry heartbeat to /api/v1/client/telemetry (authenticated).
 *   3. Fallback: GET /api/v1/version (anonymous) if no API key.
 *   4. Persist latest_version, has_update, force_upgrade into Dolibarr constants.
 *   5. If KYB_AUTO_UPDATE_PATCHES=1 (or force_upgrade signaled): execute autonomous upgrade.
 *
 * @package   KYB Dolibarr
 * @author    Mouadh Benhassine (Digidev Agency)
 * @copyright 2026 Digidev Agency
 * @license   GPL-3.0-or-later
 */

require_once __DIR__ . '/../class/kyb_telemetry.class.php';

/**
 * Class KybCronTelemetry
 * Registered as a Dolibarr cron job in modKybDolibarr::$cronjobs.
 * Called as KybCronTelemetry::run() by the Dolibarr cron dispatcher.
 */
class KybCronTelemetry
{
    /**
     * @var DoliDB Database handler injected by Dolibarr cron dispatcher
     */
    public $db;

    /**
     * @var string Error message populated on failure
     */
    public $error = '';

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
     * Main cron entry point — called daily by Dolibarr's job scheduler.
     *
     * @return int 0 on success, 1 on error (Dolibarr convention)
     */
    public function run()
    {
        global $conf;

        dol_syslog('KYB Cron: telemetry + update check started.', LOG_INFO);

        // ── Step 1: Collect and sync telemetry with gateway ──────────────────
        $telemetry = new KybTelemetry($this->db);
        $versionInfo = $telemetry->syncWithGateway();

        if (empty($versionInfo)) {
            // Gateway unreachable — non-fatal, do not block cron
            dol_syslog('KYB Cron: gateway sync returned null — connectivity issue or no API key. Will retry next run.', LOG_WARNING);
            return 0;
        }

        dol_syslog(sprintf(
            'KYB Cron: version check complete. latest=%s, has_update=%s, force_upgrade=%s',
            $versionInfo['latest_version'],
            $versionInfo['has_update'] ? 'yes' : 'no',
            $versionInfo['force_upgrade'] ? 'yes' : 'no'
        ), LOG_INFO);

        // ── Step 2: Decide whether to auto-apply upgrade ──────────────────────
        $autoUpdate  = !empty($conf->global->KYB_AUTO_UPDATE_PATCHES) && (int)$conf->global->KYB_AUTO_UPDATE_PATCHES === 1;
        $forceUpgrade = !empty($versionInfo['force_upgrade']);
        $hasUpdate    = !empty($versionInfo['has_update']);

        if (($hasUpdate && $autoUpdate) || $forceUpgrade) {
            dol_syslog(
                'KYB Cron: autonomous upgrade triggered (auto_update_patches=' . ($autoUpdate ? '1' : '0')
                . ', force_upgrade=' . ($forceUpgrade ? 'true' : 'false') . ').',
                LOG_INFO
            );

            // Locate module directory (resolve kybdolibarr root from this cron file)
            $modulePath = realpath(__DIR__ . '/..');

            if (!$modulePath || !is_dir($modulePath)) {
                $this->error = 'KYB Cron: cannot resolve module path for in-place upgrade.';
                dol_syslog($this->error, LOG_ERR);
                return 1;
            }

            $success = $telemetry->executeUpgrade($modulePath);

            if (!$success) {
                $this->error = 'KYB Cron: autonomous upgrade execution failed. See system log for details.';
                dol_syslog($this->error, LOG_ERR);
                return 1;
            }

            dol_syslog('KYB Cron: autonomous upgrade completed successfully.', LOG_INFO);
        } else {
            dol_syslog('KYB Cron: no autonomous upgrade required (auto_update_patches disabled and no force_upgrade).', LOG_INFO);
        }

        return 0;
    }
}
