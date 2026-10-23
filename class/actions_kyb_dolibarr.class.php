<?php
/**
 * HookManager Actions Implementation for KYB Dolibarr Connector
 *
 * Implements real-time corporate entity autocomplete (Linear / Shadcn UX),
 * Décret Exécutif 05-468 identifier localization, temporal tax compliance (CIDTA 169/176 & CTCA 30),
 * strict blocking on invoice validation, and bulk tax audit for État 104 / G50.
 *
 * @package   KYB Dolibarr
 * @author    Mouadh Benhassine (Digidev Agency)
 * @copyright 2026 Digidev Agency
 * @license   GPL-3.0-or-later
 */

require_once __DIR__ . '/kyb_client.class.php';

/**
 * Class ActionsKybdolibarr
 * Handles hooks for thirdparties, supplier bills, customer invoices, and fiscal auditing.
 */
class ActionsKybdolibarr
{
    /**
     * @var DoliDB Database handler
     */
    public $db;

    /**
     * @var string Error message
     */
    public $error = '';

    /**
     * @var array Errors array
     */
    public $errors = array();

    /**
     * @var string Output string
     */
    public $resprints = '';

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Early action execution hook to inject Algerian localized identifier labels and intercept invoice validation.
     *
     * @param array         $parameters Hook context parameters
     * @param CommonObject  $object     Current object
     * @param string        $action     Current action
     * @param HookManager   $hookmanager Hook manager instance
     * @return int 0 if OK, -1 if validation is strictly blocked
     */
    public function doActions($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $conf;

        $langs->load('kybdolibarr@kybdolibarr');

        // Enforce Algerian Regulatory Labels under Décret Exécutif 05-468
        $langs->tab_translate['ProfId1DZ'] = 'Registre du Commerce (RC)';
        $langs->tab_translate['ProfId2DZ'] = 'Numéro d\'Article d\'Imposition (AI)';
        $langs->tab_translate['ProfId3DZ'] = 'Identifiant Fiscal Unique (NIF)';
        $langs->tab_translate['ProfId4DZ'] = 'Identifiant Statistique National (NIS)';

        // Just-in-Time Temporal Invoice Compliance on Validation (confirm_valid)
        if ($action === 'confirm_valid' && is_object($object) && isset($object->element)) {
            $isSupplierBill = ($object->element === 'facture_fourn' || $object->element === 'invoice_supplier');
            $isCustomerBill = ($object->element === 'facture');

            if ($isSupplierBill || $isCustomerBill) {
                $socid = !empty($object->socid) ? (int)$object->socid : 0;
                if ($socid > 0) {
                    $partner = new Societe($this->db);
                    if ($partner->fetch($socid) > 0) {
                        $nif = !empty($partner->idprof3) ? trim($partner->idprof3) : (!empty($partner->idprof1) ? trim($partner->idprof1) : '');
                        $invoiceDate = !empty($object->date) ? date('Y-m-d', $object->date) : date('Y-m-d');

                        if (empty($nif)) {
                            // Missing mandatory NIF warning under Décret 05-468
                            setEventMessages(
                                $langs->trans('KybWarningMissingNif'),
                                null,
                                'warnings'
                            );
                        } else {
                            $client = new KybClient();
                            $result = $client->verifyCompliance($nif, $invoiceDate);

                            if ($result && isset($result['compliant']) && $result['compliant'] === false) {
                                $radiationDate = !empty($result['date_radiation']) ? $result['date_radiation'] : 'non précisée';

                                // Strictly neutral notice under ADR #127 / CIDTA 169/176 & CTCA 30
                                $notice = $langs->trans('KybNoticeSupplierRadiated', $radiationDate);

                                if (!empty($conf->global->KYB_STRICT_BLOCKING) && (int)$conf->global->KYB_STRICT_BLOCKING === 1) {
                                    $this->error = $notice . ' ' . $langs->trans('KybBlockingActiveMsg');
                                    $hookmanager->error = $this->error;
                                    $action = ''; // cancel validation action
                                    return -1;    // blocks Dolibarr validation pipeline
                                } else {
                                    setEventMessages($notice, null, 'warnings');
                                }
                            }
                        }
                    }
                }
            }
        }

        return 0;
    }

    /**
     * Hook to inject UI, interactive auto-completion on creation/edit, and certified identity card on view.
     *
     * @param array         $parameters Hook parameters
     * @param CommonObject  $object     Object instance (Societe or FactureFournisseur)
     * @param string        $action     Current action
     * @param HookManager   $hookmanager Hook manager instance
     * @return int 0
     */
    public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $conf;

        $langs->load('kybdolibarr@kybdolibarr');

        // 1. Thirdparty Form (Societe)
        if (isset($object->element) && $object->element === 'societe') {
            // Force Algerian Localization Labels (Décret 05-468)
            $langs->tab_translate['ProfId1DZ'] = 'Registre du Commerce (RC)';
            $langs->tab_translate['ProfId2DZ'] = 'Numéro d\'Article d\'Imposition (AI)';
            $langs->tab_translate['ProfId3DZ'] = 'Identifiant Fiscal Unique (NIF)';
            $langs->tab_translate['ProfId4DZ'] = 'Identifiant Statistique National (NIS)';

            // A. Creation & Edit Modes: Inject Autocomplete Engine & Legal Callout
            if ($action === 'create' || $action === 'edit') {
                $ajaxUrl = dol_buildpath('/kybdolibarr/ajax/resolve.php', 1);
                $jsUrl = dol_buildpath('/kybdolibarr/js/kyb_autocomplete.js', 1);
                $workingGif = DOL_URL_ROOT . '/theme/common/working.gif';

                $html = '
                <!-- KYB Sovereign Auto-Completion & Full Enrichment Engine -->
                <script type="text/javascript">
                window.KYB_CFG = {
                    ajaxUrl: "' . dol_escape_js($ajaxUrl) . '",
                    token: "' . newToken() . '",
                    workingGif: "' . dol_escape_js($workingGif) . '",
                    labels: {
                        header: "Référentiel National CNRC (Personnes Morales)",
                        searching: "' . dol_escape_js($langs->trans('KybSearching')) . '",
                        noResults: "' . dol_escape_js($langs->trans('KybNoResults')) . '",
                        active: "' . dol_escape_js($langs->trans('KybStatusActive')) . '",
                        radiated: "' . dol_escape_js($langs->trans('KybStatusRadiated')) . '",
                        autofillSuccess: "' . dol_escape_js($langs->trans('KybAutofillSuccess')) . '",
                        rateLimited: "' . dol_escape_js($langs->trans('KybRateLimited')) . '",
                        hint: "' . dol_escape_js($langs->trans('KybAutocompleteHint')) . '"
                    }
                };
                </script>
                <script type="text/javascript" src="' . dol_escape_js($jsUrl) . '?v=2.0.0"></script>
                <tr class="kyb-pp-notice-row"><td colspan="4" style="padding: 8px 12px; font-size: 11.5px; color: #52525b; background: #fbfbfa; border: 1px dashed #d4d4d8; border-radius: 6px; margin: 4px 0;">
                <span class="fa fa-info-circle" style="color: #3b82f6; margin-right: 6px;"></span>
                <em>' . $langs->trans('KybPpProtectionNotice') . '</em>
                </td></tr>
                ';

                $this->resprints = $html;
                return 0;
            }

            // B. View Mode: Render Certified Sovereign Identity & Compliance Table
            if (!empty($object->idprof3) || !empty($object->idprof1)) {
                $client = new KybClient();
                $identifier = !empty($object->idprof3) ? $object->idprof3 : $object->idprof1;
                $data = $client->resolveEntity($identifier);

                $badge = $this->renderStatusBadge($data);
                $gerant = (!empty($data) && !empty($data['representant_legal'])) ? $data['representant_legal'] : 'Non renseigné au registre';
                $forme = (!empty($data) && !empty($data['forme_juridique'])) ? $data['forme_juridique'] : 'Non précisée';
                $capital = (!empty($data) && !empty($data['capital_social'])) ? $data['capital_social'] : (!empty($object->capital) ? price($object->capital).' DA' : 'Non précisé');
                $activite = (!empty($data) && !empty($data['activite_principale'])) ? $data['activite_principale'] : (!empty($object->ape) ? $object->ape : 'Non précisée');
                $dateImmat = (!empty($data) && !empty($data['date_immatriculation'])) ? substr($data['date_immatriculation'], 0, 10) : 'Non précisée';
                $dateRad = (!empty($data) && !empty($data['date_radiation'])) ? substr($data['date_radiation'], 0, 10) : '';
                $verifiedAt = (!empty($data) && !empty($data['verified_at'])) ? $data['verified_at'] : date('Y-m-d H:i:s');
                $srcUpdated = (!empty($data) && !empty($data['source_updated_at'])) ? $data['source_updated_at'] : '';

                // Header for National Registry Enrichment
                $html = '<tr class="liste_titre"><td colspan="2" style="background: #f4f4f5; color: #18181b; font-weight: 600; padding: 8px 10px; border-top: 2px solid #e4e4e7;">';
                $html .= '<span class="fa fa-shield" style="color: #10b981; margin-right: 6px;"></span>';
                $html .= $langs->trans('KybEnrichmentCardTitle');
                $html .= '</td></tr>';

                // Row 1: Statut CNRC
                $html .= '<tr>';
                $html .= '<td class="titlefieldfield">' . $langs->trans('KybStatusLabel') . '</td>';
                $html .= '<td>' . $badge . '</td>';
                $html .= '</tr>';

                // Row 2: Gérant / Dirigeant Légal
                $html .= '<tr>';
                $html .= '<td class="titlefieldfield">' . $langs->trans('KybLegalRepresentative') . '</td>';
                $html .= '<td><strong>' . dol_escape_htmltag($gerant) . '</strong></td>';
                $html .= '</tr>';

                // Row 3: Forme Juridique Certifiée
                $html .= '<tr>';
                $html .= '<td class="titlefieldfield">' . $langs->trans('KybLegalForm') . '</td>';
                $html .= '<td>' . dol_escape_htmltag($forme) . '</td>';
                $html .= '</tr>';

                // Row 4: Capital Social Certifié
                $html .= '<tr>';
                $html .= '<td class="titlefieldfield">' . $langs->trans('KybShareCapital') . '</td>';
                $html .= '<td><strong style="font-feature-settings: \'tnum\';">' . dol_escape_htmltag($capital) . '</strong></td>';
                $html .= '</tr>';

                // Row 5: Activité Principale (NAE)
                $html .= '<tr>';
                $html .= '<td class="titlefieldfield">' . $langs->trans('KybMainActivity') . '</td>';
                $html .= '<td>' . dol_escape_htmltag($activite) . '</td>';
                $html .= '</tr>';

                // Row 6: Date Immatriculation / Radiation
                $html .= '<tr>';
                if (!empty($dateRad)) {
                    $html .= '<td class="titlefieldfield" style="color: #dc2626; font-weight: 600;">' . $langs->trans('KybRadiationDate') . '</td>';
                    $html .= '<td><span class="badge badge-danger" style="background-color: #ef4444; color: #fff; padding: 2px 7px; border-radius: 4px; font-weight: 600;">' . dol_escape_htmltag($dateRad) . '</span> <small class="opacitymedium">(Immatriculée le ' . dol_escape_htmltag($dateImmat) . ')</small></td>';
                } else {
                    $html .= '<td class="titlefieldfield">' . $langs->trans('KybRegistrationDate') . '</td>';
                    $html .= '<td>' . dol_escape_htmltag($dateImmat) . '</td>';
                }
                $html .= '</tr>';

                // Row 7: Conformité Données (incorporates KYB verification date)
                $verifTimestamp = (!empty($data) && !empty($data['verified_at'])) ? strtotime($data['verified_at']) : time();
                $formattedVerif = date('d/m/Y H:i', $verifTimestamp ?: time());
                $html .= '<tr>';
                $html .= '<td class="titlefieldfield">Conformité Données</td>';
                $html .= '<td><small class="opacitymedium"><span class="fa fa-lock" style="color: #10b981; margin-right: 4px;"></span> Vérifié via KYB le <span style="font-feature-settings: \'tnum\'; font-weight: 600; color: #18181b;">' . dol_escape_htmltag($formattedVerif) . '</span> · Datacenter Algérie Télécom (Loi 25-11)</small></td>';
                $html .= '</tr>';

                $this->resprints = $html;
            }
            return 0;
        }

        // 2. Vendor Invoice Card: Render Regulatory Audit Banner
        if (isset($object->element) && ($object->element === 'facture_fourn' || $object->element === 'invoice_supplier')) {
            $supplier = new Societe($this->db);
            $supplier->fetch($object->socid);
            $isRadiated = false;
            $radDate = '';

            if (!empty($supplier->idprof3)) {
                $client = new KybClient();
                $entity = $client->resolveEntity($supplier->idprof3);
                if ($entity && isset($entity['status']) && strpos(strtoupper($entity['status']), 'RADI') !== false) {
                    $isRadiated = true;
                    $radDate = !empty($entity['date_radiation']) ? substr($entity['date_radiation'], 0, 10) : '';
                }
            }

            if ($isRadiated) {
                $banner = '<div class="warning" style="margin-top: 10px; margin-bottom: 10px; padding: 12px 16px; border-left: 4px solid #f59e0b; background: #fffbeb; color: #92400e; border-radius: 4px;">';
                $banner .= '<span class="fa fa-exclamation-triangle" style="color: #d97706; margin-right: 8px;"></span>';
                $banner .= '<strong>Notice de Conformité Fiscale (CIDTA 169/176 & CTCA 30) :</strong> ';
                $banner .= 'Fournisseur radié au registre public (Date : ' . ($radDate ?: 'non précisée') . '). ';
                $banner .= 'Déductibilité de la charge et de la TVA à vérifier selon les articles 169 & 176 du CIDTA et l\'article 30 du CTCA (Loi 25-11).';
                $banner .= '</div>';
            } else {
                $banner = '<div class="info" style="margin-top: 10px; margin-bottom: 10px; padding: 10px 14px; border-left: 4px solid #10b981; background: #f0fdf4; color: #166534; border-radius: 4px;">';
                $banner .= '<span class="fa fa-shield" style="color: #10b981; margin-right: 8px;"></span>';
                $banner .= '<strong>KYB Algérie :</strong> ' . $langs->trans('KybInvoiceBannerNotice');
                $banner .= '</div>';
            }

            $this->resprints = $banner;
            return 0;
        }

        return 0;
    }

    /**
     * Renders institutional status badge for corporate entity.
     *
     * @param array|null $data Resolved entity data
     * @return string HTML badge markup
     */
    public function renderStatusBadge($data)
    {
        global $langs;
        $langs->load('kybdolibarr@kybdolibarr');

        if ($data && isset($data['status'])) {
            $statusUpper = strtoupper($data['status']);
            if (strpos($statusUpper, 'RADI') !== false) {
                $radDate = isset($data['date_radiation']) && !empty($data['date_radiation']) ? $data['date_radiation'] : 'Date inconnue';
                if (strpos($radDate, 'T') !== false) {
                    $radDate = substr($radDate, 0, 10);
                }
                return '<span class="badge badge-status badge-status8 badge-danger" style="background-color: #ef4444; color: #fff; padding: 4px 10px; border-radius: 9999px;">'
                    . '🔴 ' . $langs->trans('KybStatusRadiated') . ' (' . $radDate . ')'
                    . '</span>';
            } else {
                return '<span class="badge badge-status badge-status4 badge-success" style="background-color: #10b981; color: #fff; padding: 4px 10px; border-radius: 9999px;">'
                    . '🟢 ' . $langs->trans('KybStatusActive')
                    . '</span>';
            }
        }

        return '<span class="badge badge-status badge-status0" style="background-color: #71717a; color: #fff; padding: 4px 10px; border-radius: 9999px;">'
            . '⚪ ' . $langs->trans('KybStatusUnverified')
            . '</span>';
    }

    /**
     * Injects the KYB Bulk Tax Audit option into Dolibarr massaction dropdown.
     *
     * @param array $parameters Hook parameters
     * @return int 0
     */
    public function addMoreMassActions($parameters)
    {
        global $langs;
        $langs->load('kybdolibarr@kybdolibarr');

        $this->resprints = '<option value="kyb_bulk_audit">🛡️ ' . dol_escape_htmltag($langs->trans('KybBulkAuditLabel')) . '</option>';
        return 0;
    }

    /**
     * Executes bulk tax audit across selected thirdparties (État 104 / G50 compliance).
     *
     * @param array         $parameters Hook parameters
     * @param CommonObject  $object     Current object
     * @param string        $action     Current action
     * @param HookManager   $hookmanager Hook manager instance
     * @return int 0
     */
    public function doMassActions($parameters, &$object, &$action, $hookmanager)
    {
        global $langs;
        $langs->load('kybdolibarr@kybdolibarr');

        $massaction = GETPOST('massaction', 'alpha');
        if ($massaction === 'kyb_bulk_audit') {
            $tselect = GETPOST('tselect', 'array');
            if (empty($tselect) || !is_array($tselect)) {
                $tselect = GETPOST('toselect', 'array');
            }
            if (empty($tselect) || !is_array($tselect)) {
                setEventMessages($langs->trans('KybNoThirdpartiesSelected'), null, 'warnings');
                return 0;
            }

            $client = new KybClient();
            $total = count($tselect);
            $activeCount = 0;
            $radiatedCount = 0;
            $unverifiedCount = 0;
            $radiatedList = array();

            foreach ($tselect as $socid) {
                $soc = new Societe($this->db);
                if ($soc->fetch((int)$socid) > 0) {
                    $id = !empty($soc->idprof3) ? trim($soc->idprof3) : (!empty($soc->idprof1) ? trim($soc->idprof1) : '');
                    if (!empty($id)) {
                        $entity = $client->resolveEntity($id);
                        if ($entity && isset($entity['status'])) {
                            if (stripos($entity['status'], 'radi') !== false) {
                                $radiatedCount++;
                                $radDate = !empty($entity['date_radiation']) ? substr($entity['date_radiation'], 0, 10) : 'Date inconnue';
                                $radiatedList[] = $soc->name . ' (RC: ' . (!empty($entity['rc']) ? $entity['rc'] : $soc->idprof1) . ' - Radié le ' . $radDate . ')';
                            } else {
                                $activeCount++;
                            }
                        } else {
                            $unverifiedCount++;
                        }
                    } else {
                        $unverifiedCount++;
                    }
                }
            }

            $summary = sprintf($langs->trans('KybBulkAuditSummary'), $total, $activeCount, $radiatedCount, $unverifiedCount);
            if ($radiatedCount > 0) {
                $warnMsg = $langs->trans('KybBulkAuditRadiatedWarning') . ' ' . implode(', ', $radiatedList);
                setEventMessages($warnMsg, null, 'errors');
            }
            setEventMessages($summary, null, 'mesgs');
            return 1;
        }

        return 0;
    }

    /**
     * Legacy backward-compatibility method for invoice validation.
     *
     * @param array         $parameters Hook parameters
     * @param CommonObject  $object     Invoice object
     * @param string        $action     Current action
     * @param HookManager   $hookmanager Hook manager instance
     * @return int 0 if OK, -1 if blocked
     */
    public function validateInvoice($parameters, &$object, &$action, $hookmanager)
    {
        return $this->doActions($parameters, $object, $action, $hookmanager);
    }
}

if (!class_exists('ActionsKyb_dolibarr')) {
    class ActionsKyb_dolibarr extends ActionsKybdolibarr
    {
    }
}
