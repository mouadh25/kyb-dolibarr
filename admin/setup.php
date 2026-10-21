<?php
/**
 * Administration Setup Page for KYB Dolibarr Connector
 *
 * @package   KYB Dolibarr
 * @author    Mouadh Benhassine (Digidev Agency)
 * @copyright 2026 Digidev Agency
 * @license   GPL-3.0-or-later
 */

// Load Dolibarr environment
$res = 0;
if (! $res && file_exists("../../../main.inc.php")) $res = @include '../../../main.inc.php';
if (! $res && file_exists("../../../../main.inc.php")) $res = @include '../../../../main.inc.php';
if (! $res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once __DIR__ . '/../class/kyb_client.class.php';

global $langs, $user, $conf, $db;

$langs->load("admin");
$langs->load("kybdolibarr@kybdolibarr");

if (!$user->admin) {
    accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$testResult = null;

// Handle Save Actions
if ($action == 'update') {
    $apiUrl = GETPOST('KYB_API_URL', 'alpha');
    $apiKey = GETPOST('KYB_API_KEY', 'alpha');
    $timeout = GETPOST('KYB_TIMEOUT', 'int');
    $strict = GETPOST('KYB_STRICT_BLOCKING', 'int');
    $sandbox = GETPOST('KYB_SANDBOX_MODE', 'int');

    dolibarr_set_const($db, 'KYB_API_URL', trim($apiUrl), 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'KYB_API_KEY', trim($apiKey), 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'KYB_TIMEOUT', (int)$timeout, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'KYB_STRICT_BLOCKING', (int)$strict, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'KYB_SANDBOX_MODE', (int)$sandbox, 'chaine', 0, '', $conf->entity);

    setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
}

// Handle Test Connection
if ($action == 'test_connection') {
    $client = new KybClient();
    $testResult = $client->testConnection();
}

$page_name = "Configuration du Connecteur KYB Algérie";
llxHeader('', $langs->trans($page_name));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

print '<span class="opacitymedium">'.$langs->trans("KybSetupDescription").'</span><br><br>';

if ($testResult !== null) {
    if ($testResult['success']) {
        print '<div class="ok" style="padding: 12px; margin-bottom: 15px; border-radius: 6px; background-color: #ecfdf5; border: 1px solid #10b981; color: #065f46;">';
        print '<span class="fa fa-check-circle" style="margin-right: 8px;"></span><strong>'.$testResult['message'].'</strong>';
        print '</div>';
    } else {
        print '<div class="error" style="padding: 12px; margin-bottom: 15px; border-radius: 6px; background-color: #fef2f2; border: 1px solid #ef4444; color: #991b1b;">';
        print '<span class="fa fa-exclamation-triangle" style="margin-right: 8px;"></span><strong>'.$testResult['message'].'</strong>';
        print '</div>';
    }
}

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans("Parameter").'</th>';
print '<th>'.$langs->trans("Value").'</th>';
print '</tr>';

// KYB_API_URL
print '<tr class="oddeven">';
print '<td class="fieldrequired"><span class="fa fa-globe"></span> '.$langs->trans("KybParamApiUrl").'</td>';
print '<td>';
print '<input type="text" class="flat minwidth400" name="KYB_API_URL" value="'.dol_escape_htmltag($conf->global->KYB_API_URL ?? 'https://kyb.digidevagency.dz').'">';
print '<br><small class="opacitymedium">URL de l\'API de vérification KYB (ex: https://kyb.digidevagency.dz)</small>';
print '</td>';
print '</tr>';

// KYB_API_KEY
print '<tr class="oddeven">';
print '<td class="fieldrequired"><span class="fa fa-key"></span> '.$langs->trans("KybParamApiKey").'</td>';
print '<td>';
print '<input type="password" class="flat minwidth400" name="KYB_API_KEY" value="'.dol_escape_htmltag($conf->global->KYB_API_KEY ?? '').'" autocomplete="off">';
print '<br><small class="opacitymedium">Clé secrète de production (ex: sk_live_...) ou de test (ex: sk_test_...)</small>';
print '</td>';
print '</tr>';

// KYB_TIMEOUT
print '<tr class="oddeven">';
print '<td><span class="fa fa-clock-o"></span> '.$langs->trans("KybParamTimeout").'</td>';
print '<td>';
print '<input type="number" class="flat width50" name="KYB_TIMEOUT" value="'.((int)($conf->global->KYB_TIMEOUT ?? 5)).'" min="2" max="15"> secondes';
print '<br><small class="opacitymedium">Délai maximal d\'attente avant reprise du contrôle par Dolibarr (recommandé: 5s)</small>';
print '</td>';
print '</tr>';

// KYB_STRICT_BLOCKING
print '<tr class="oddeven">';
print '<td><span class="fa fa-ban"></span> '.$langs->trans("KybParamStrictBlocking").'</td>';
print '<td>';
print '<select name="KYB_STRICT_BLOCKING" class="flat">';
print '<option value="0"'.(empty($conf->global->KYB_STRICT_BLOCKING) ? ' selected' : '').'>Non (Avertissement réglementaire non bloquant - Recommandé)</option>';
print '<option value="1"'.(!empty($conf->global->KYB_STRICT_BLOCKING) ? ' selected' : '').'>Oui (Bloquer strictement la validation des factures fournisseurs radiés)</option>';
print '</select>';
print '</td>';
print '</tr>';

// KYB_SANDBOX_MODE
print '<tr class="oddeven">';
print '<td><span class="fa fa-flask"></span> '.$langs->trans("KybParamSandboxMode").'</td>';
print '<td>';
print '<select name="KYB_SANDBOX_MODE" class="flat">';
print '<option value="0"'.(empty($conf->global->KYB_SANDBOX_MODE) ? ' selected' : '').'>Non (Production live - Requêtes réelles de vérification)</option>';
print '<option value="1"'.(!empty($conf->global->KYB_SANDBOX_MODE) ? ' selected' : '').'>Oui (Mode Bac à Sable - Requêtes simulées sans déduction de crédits)</option>';
print '</select>';
print '</td>';
print '</tr>';

print '</table>';

print '<br><div class="center">';
print '<input type="submit" class="button button-primary" value="'.$langs->trans("Save").'">';
print '&nbsp;&nbsp;';
print '<a href="'.$_SERVER["PHP_SELF"].'?action=test_connection" class="button">'.$langs->trans("KybBtnTestConnection").'</a>';
print '</div>';
print '</form>';

// Regulatory & Mapping Informational Card
print '<br><br>';
print '<div class="fichecenter">';
print '<table class="border centpercent">';
print '<tr class="liste_titre"><td colspan="2"><span class="fa fa-info-circle"></span> Mappage Réglementaire Décret Exécutif 05-468 (Algérie)</td></tr>';
print '<tr><td class="titlefieldfield"><strong>idprof1</strong></td><td>Registre du Commerce (RC) normalisé <code>WW/00-XXXXXXX[A|B|C|D]YY</code></td></tr>';
print '<tr><td><strong>idprof2</strong></td><td>Numéro d\'Article d\'Imposition (N° de Rôle fiscal - Secret Fiscal Art. 114 CPF)</td></tr>';
print '<tr><td><strong>idprof3</strong></td><td>Identifiant Fiscal Unique (NIF à 15-20 chiffres)</td></tr>';
print '<tr><td><strong>idprof4</strong></td><td>Identifiant Statistique National (NIS - ONS)</td></tr>';
print '<tr><td><strong>Conformité Données</strong></td><td>Hébergement Datacenter Algérie Télécom (Strictement conforme Loi 25-11)</td></tr>';
print '</table>';
print '</div>';

llxFooter();
$db->close();
