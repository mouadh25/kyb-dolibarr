<?php
/**
 * Sovereign KYB HTTP API Client for Dolibarr
 *
 * @package   KYB Dolibarr
 * @author    Mouadh Benhassine (Digidev Agency)
 * @copyright 2026 Digidev Agency
 * @license   GPL-3.0-or-later
 */

class KybClient
{
    /**
     * @var string Base API URL
     */
    private $apiUrl;

    /**
     * @var string API Key (sk_live_... or sk_test_...)
     */
    private $apiKey;

    /**
     * @var int HTTP Request timeout in seconds
     */
    private $timeout;

    /**
     * @var bool Sandbox mode flag
     */
    private $sandboxMode;

    /**
     * Constructor.
     *
     * @param string|null $apiUrl  Optional custom API URL
     * @param string|null $apiKey  Optional custom API Key
     * @param int|null    $timeout Optional custom timeout
     */
    public function __construct($apiUrl = null, $apiKey = null, $timeout = null)
    {
        global $conf;

        $this->apiUrl = !empty($apiUrl) ? rtrim($apiUrl, '/') : (!empty($conf->global->KYB_API_URL) ? rtrim($conf->global->KYB_API_URL, '/') : 'https://kyb.digidevagency.dz');
        $this->apiKey = !empty($apiKey) ? trim($apiKey) : (!empty($conf->global->KYB_API_KEY) ? trim($conf->global->KYB_API_KEY) : '');
        $this->timeout = !empty($timeout) ? (int)$timeout : (!empty($conf->global->KYB_TIMEOUT) ? (int)$conf->global->KYB_TIMEOUT : 5);
        $this->sandboxMode = !empty($conf->global->KYB_SANDBOX_MODE) ? (bool)$conf->global->KYB_SANDBOX_MODE : false;

        // Force timeout boundary [2s, 15s]
        if ($this->timeout < 2) {
            $this->timeout = 2;
        } elseif ($this->timeout > 15) {
            $this->timeout = 15;
        }
    }

    /**
     * Resolves single corporate identifier (NIF, RC, or Raison Sociale).
     *
     * @param string $identifier NIF or RC number
     * @return array|null Parsed entity record or null on failure
     */
    public function resolveEntity($identifier)
    {
        $cleanId = trim($identifier);
        if (empty($cleanId)) {
            return null;
        }

        $endpoint = '/api/v1/entity/resolve?q=' . urlencode($cleanId);
        $response = $this->request('GET', $endpoint);

        $rawEntity = null;
        if ($response && isset($response['entity'])) {
            $rawEntity = $response['entity'];
        } elseif ($response && isset($response['data'])) {
            $rawEntity = $response['data'];
        } elseif ($response && isset($response['success']) && $response['success'] === true && isset($response['data'])) {
            $rawEntity = $response['data'];
        }

        if ($rawEntity && is_array($rawEntity)) {
            $mainActivity = '';
            if (!empty($rawEntity['activities']) && is_array($rawEntity['activities']) && count($rawEntity['activities']) > 0) {
                $act0 = $rawEntity['activities'][0];
                $mainActivity = (isset($act0['code']) ? $act0['code'] : '') . ' - ' . (isset($act0['libelle']) ? $act0['libelle'] : '');
            } elseif (!empty($rawEntity['nae_codes']) && is_array($rawEntity['nae_codes']) && count($rawEntity['nae_codes']) > 0) {
                $mainActivity = $rawEntity['nae_codes'][0];
            }

            return array(
                'nom' => isset($rawEntity['nom']) ? $rawEntity['nom'] : (isset($rawEntity['raison_sociale']) ? $rawEntity['raison_sociale'] : ''),
                'raison_sociale' => isset($rawEntity['raison_sociale']) ? $rawEntity['raison_sociale'] : (isset($rawEntity['nom']) ? $rawEntity['nom'] : ''),
                'sigle' => isset($rawEntity['sigle']) ? $rawEntity['sigle'] : (isset($rawEntity['name_alias']) ? $rawEntity['name_alias'] : ''),
                'nif' => isset($rawEntity['nif']) ? $rawEntity['nif'] : '',
                'rc' => isset($rawEntity['rc_number_official']) ? $rawEntity['rc_number_official'] : (isset($rawEntity['rc_number']) ? $rawEntity['rc_number'] : (isset($rawEntity['rc']) ? $rawEntity['rc'] : '')),
                'rc_official' => isset($rawEntity['rc_number_official']) ? $rawEntity['rc_number_official'] : '',
                'article_imposition' => isset($rawEntity['article_imposition']) ? $rawEntity['article_imposition'] : '',
                'nis' => isset($rawEntity['nis']) ? $rawEntity['nis'] : '',
                'address' => isset($rawEntity['adresse']) ? $rawEntity['adresse'] : (isset($rawEntity['address']) ? $rawEntity['address'] : ''),
                'adresse' => isset($rawEntity['adresse']) ? $rawEntity['adresse'] : (isset($rawEntity['address']) ? $rawEntity['address'] : ''),
                'status' => isset($rawEntity['statut_cnrc']) ? $rawEntity['statut_cnrc'] : (isset($rawEntity['status']) ? $rawEntity['status'] : 'ACTIF'),
                'statut_cnrc' => isset($rawEntity['statut_cnrc']) ? $rawEntity['statut_cnrc'] : (isset($rawEntity['status']) ? $rawEntity['status'] : 'ACTIF'),
                'date_radiation' => isset($rawEntity['date_radiation']) ? $rawEntity['date_radiation'] : null,
                'date_immatriculation' => isset($rawEntity['date_immatriculation']) ? $rawEntity['date_immatriculation'] : null,
                'forme_juridique' => isset($rawEntity['forme_juridique']) ? $rawEntity['forme_juridique'] : '',
                'capital_social' => isset($rawEntity['capital_social']) ? $rawEntity['capital_social'] : '',
                'representant_legal' => isset($rawEntity['representant_legal']) ? $rawEntity['representant_legal'] : '',
                'nae_codes' => isset($rawEntity['nae_codes']) ? $rawEntity['nae_codes'] : array(),
                'activities' => isset($rawEntity['activities']) ? $rawEntity['activities'] : array(),
                'activite_principale' => $mainActivity,
                'wilaya' => isset($rawEntity['wilaya']) ? $rawEntity['wilaya'] : '',
                'verified_at' => date('Y-m-d H:i:s'), // timestamp of this live KYB API query
                'source_updated_at' => isset($rawEntity['updated_at']) ? substr($rawEntity['updated_at'], 0, 10) : (isset($rawEntity['updatedAt']) ? substr($rawEntity['updatedAt'], 0, 10) : ''),
                'raw' => $rawEntity
            );
        }

        return null;
    }

    /**
     * Temporal invoice compliance check under CIDTA Art. 169/176 & CTCA Art. 30.
     *
     * @param string $nif         Vendor NIF
     * @param string $invoiceDate Invoice date (YYYY-MM-DD)
     * @return array|null Compliance result
     */
    public function verifyCompliance($nif, $invoiceDate)
    {
        $cleanNif = trim($nif);
        if (empty($cleanNif)) {
            return null;
        }

        $payload = array(
            'nif' => $cleanNif,
            'invoice_date' => $invoiceDate
        );

        $response = $this->request('POST', '/api/v1/compliance/verify', $payload);

        if ($response) {
            // If async job returned, poll it (with short timeout loop, max 3s)
            if (isset($response['job_id']) && (!isset($response['results']) || empty($response['results']))) {
                $jobId = $response['job_id'];
                $startTime = time();
                while ((time() - $startTime) < 3) {
                    usleep(300000); // 300ms
                    $poll = $this->request('GET', '/api/v1/compliance/job/' . $jobId);
                    if ($poll && isset($poll['status']) && $poll['status'] === 'completed') {
                        $response = $poll;
                        break;
                    }
                }
            }

            if (isset($response['results']) && is_array($response['results']) && count($response['results']) > 0) {
                $first = $response['results'][0];
                $isCompliant = true;
                if (isset($first['risk']['level']) && ($first['risk']['level'] === 'critical' || $first['risk']['level'] === 'high')) {
                    $isCompliant = false;
                } elseif (isset($first['verdict']) && ($first['verdict'] === 'NON_CONFORME' || $first['verdict'] === 'REVIEW_NEEDED')) {
                    if (isset($first['statut_cnrc']) && strpos(strtoupper($first['statut_cnrc']), 'RADI') !== false) {
                        $isCompliant = false;
                    }
                }
                return array(
                    'compliant' => $isCompliant,
                    'date_radiation' => isset($first['radiation_date']) ? substr($first['radiation_date'], 0, 10) : null,
                    'statut_cnrc' => isset($first['statut_cnrc']) ? $first['statut_cnrc'] : 'ACTIF',
                    'message' => isset($first['risk']['message_fr']) ? $first['risk']['message_fr'] : '',
                    'raw' => $first
                );
            }

            if (isset($response['data'])) {
                return $response['data'];
            }
            return $response;
        }

        return null;
    }

    /**
     * Autocomplete search across Algerian entities.
     *
     * @param string $query Search query (company name or commercial sign)
     * @param int    $limit Maximum results
     * @return array List of candidate records
     */
    public function searchCandidates($query, $limit = 10)
    {
        $cleanQuery = trim($query);
        if (strlen($cleanQuery) < 3) {
            return array();
        }
        if (strlen($cleanQuery) > 100) {
            $cleanQuery = substr($cleanQuery, 0, 100);
        }

        $limit = (int)$limit;
        if ($limit < 1) $limit = 10;
        if ($limit > 25) $limit = 25;

        $endpoint = '/api/v1/entity/candidates?q=' . urlencode($cleanQuery) . '&limit=' . $limit;
        $response = $this->request('GET', $endpoint);

        $rawList = null;
        if ($response && isset($response['candidates']) && is_array($response['candidates'])) {
            $rawList = $response['candidates'];
        } elseif ($response && isset($response['data']) && is_array($response['data'])) {
            $rawList = $response['data'];
        } elseif ($response && isset($response['results']) && is_array($response['results'])) {
            $rawList = $response['results'];
        }

        if ($rawList && is_array($rawList)) {
            // Defense-in-depth: exclude Personnes Physiques (Loi 18-07 / Loi 25-11 / ADR #118)
            $filtered = array();
            foreach ($rawList as $c) {
                $forme = isset($c['forme_juridique']) ? (string)$c['forme_juridique'] : '';
                $rc = isset($c['rc_number']) ? (string)$c['rc_number'] : '';
                $entityType = isset($c['entity_type']) ? (string)$c['entity_type'] : '';

                if (strcasecmp($entityType, 'PP') === 0) continue;
                if (stripos($forme, 'physique') !== false || strtoupper(trim($forme)) === 'PP') continue;
                // Exclude Personne Physique categories A (Commerçant), C (Artisan), D (Prof. libérale) across compressed and official formats
                if (preg_match('/^(?:0[0-9]|[1-6]\d|8[0-9]|9[0-9]|[0-9])(?:\/00-\d+|[A-Za-z0-9\s\/-]*?)[ACDacd]/i', $rc)) continue;

                $filtered[] = $c;
            }
            return $filtered;
        }

        return array();
    }

    /**
     * Sends rich instance telemetry heartbeat to the sovereign gateway.
     * Mirrors Odoo's action_check_kyb_update() POST /api/v1/client/telemetry path.
     *
     * @param array $payload Telemetry payload from KybTelemetry::gatherInstanceTelemetry()
     * @return array|null {has_update, latest_version, force_upgrade, changelog} or null on failure
     */
    public function sendTelemetry(array $payload)
    {
        if (empty($this->apiKey)) {
            return null; // Unauthenticated — fall back to anonymous checkVersion()
        }

        $response = $this->request('POST', '/api/v1/client/telemetry', $payload);

        if ($response && isset($response['latest_version'])) {
            return array(
                'has_update'     => !empty($response['has_update']),
                'latest_version' => $response['latest_version'],
                'force_upgrade'  => !empty($response['force_upgrade']),
                'changelog'      => isset($response['changelog']) ? $response['changelog'] : '',
            );
        }

        return null;
    }

    /**
     * Anonymous version check via GET /api/v1/version (no API key required).
     * Fallback when no API key is configured.
     *
     * @param string $currentVersion Installed connector version
     * @param string $clientType     'dolibarr'
     * @param string $dbUuid         Non-PII database UUID
     * @return array|null {has_update, latest_version, force_upgrade, changelog} or null
     */
    public function checkVersion($currentVersion, $clientType = 'dolibarr', $dbUuid = '')
    {
        $params = '?client=' . urlencode($clientType)
            . '&version=' . urlencode($currentVersion)
            . '&dolibarr_version=' . urlencode(defined('DOL_VERSION') ? DOL_VERSION : 'unknown');

        if (!empty($dbUuid)) {
            $params .= '&db_uuid=' . urlencode($dbUuid);
        }

        $response = $this->request('GET', '/api/v1/version' . $params);

        if ($response && isset($response['latest_version'])) {
            return array(
                'has_update'     => !empty($response['has_update']),
                'latest_version' => $response['latest_version'],
                'force_upgrade'  => !empty($response['force_upgrade']),
                'changelog'      => isset($response['changelog']) ? $response['changelog'] : '',
            );
        }

        return null;
    }

    /**
     * Tests connectivity to the sovereign gateway.
     *
     * @return array Test diagnosis with latency and HTTP status
     */
    public function testConnection()
    {

        $startTime = microtime(true);
        $response = $this->request('GET', '/health');
        $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);

        if ($response && isset($response['status']) && $response['status'] === 'ok') {
            return array(
                'success' => true,
                'latency_ms' => $elapsedMs,
                'message' => 'Connexion réussie à l\'API KYB (' . $elapsedMs . ' ms)'
            );
        }

        return array(
            'success' => false,
            'latency_ms' => $elapsedMs,
            'message' => 'Impossible de joindre le serveur API KYB à ' . $this->apiUrl
        );
    }

    /**
     * Low-level cURL execution engine.
     *
     * @param string     $method   HTTP Method (GET, POST)
     * @param string     $endpoint API route path
     * @param array|null $payload  JSON body data
     * @return array|null Parsed JSON response
     */
    private function request($method, $endpoint, $payload = null)
    {
        $url = $this->apiUrl . $endpoint;

        $headers = array(
            'Accept: application/json',
            'User-Agent: KYB-Dolibarr-Connector/2.0.4 (Dolibarr; PHP ' . PHP_VERSION . ')'
        );

        if (!empty($this->apiKey)) {
            $headers[] = 'Authorization: Bearer ' . $this->apiKey;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            $jsonBody = json_encode($payload ?: new stdClass());
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $rawResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($rawResponse === false || !empty($curlError)) {
            dol_syslog('KYB API cURL Error: ' . $curlError, LOG_ERR);
            return null;
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            $decoded = json_decode($rawResponse, true);
            return is_array($decoded) ? $decoded : null;
        }

        dol_syslog('KYB API HTTP ' . $httpCode . ' Error: ' . $rawResponse, LOG_WARNING);
        return null;
    }
}
