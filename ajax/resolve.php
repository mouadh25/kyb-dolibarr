<?php
/**
 * AJAX Controller for Asynchronous Corporate Identity Resolution & Name Autocomplete
 *
 * @package   KYB Dolibarr
 * @author    Mouadh Benhassine (Digidev Agency)
 * @copyright 2026 Digidev Agency
 * @license   GPL-3.0-or-later
 */

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', 1);
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}
if (!defined('NOREQUIRESOC')) {
    define('NOREQUIRESOC', '1');
}

// Load Dolibarr environment
$res = 0;
if (! $res && file_exists("../../../main.inc.php")) $res = @include '../../../main.inc.php';
if (! $res && file_exists("../../../../main.inc.php")) $res = @include '../../../../main.inc.php';
if (! $res) die("Include of main fails");

require_once __DIR__ . '/../class/kyb_client.class.php';

top_httphead('application/json');

// Check user login and permissions
if (empty($user->id)) {
    echo json_encode(array('success' => false, 'error' => 'Unauthorized'));
    exit;
}

if (empty($user->rights->societe->lire) && empty($user->rights->societe->creer)) {
    echo json_encode(array('success' => false, 'error' => 'Permission denied'));
    exit;
}

$mode = GETPOST('mode', 'alpha');
if (empty($mode)) {
    $mode = (GETPOST('q', 'nohtml') !== '') ? 'candidates' : 'resolve';
}

$client = new KybClient();

// Mode 1: Search Candidates for Name Autocomplete (PM Only, Loi 18-07 / Loi 25-11)
if ($mode === 'candidates') {
    $q = trim(GETPOST('q', 'nohtml'));
    if (strlen($q) < 3) {
        echo json_encode(array(
            'success' => true,
            'candidates' => array(),
            'total' => 0
        ));
        exit;
    }

    $limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : 10;
    $candidates = $client->searchCandidates($q, $limit);

    echo json_encode(array(
        'success' => true,
        'candidates' => $candidates,
        'total' => count($candidates)
    ));
    exit;
}

// Mode 2: Resolve Entity by Identifier (NIF, RC, or NIS)
$identifier = trim(GETPOST('identifier', 'nohtml'));
if (empty($identifier)) {
    $identifier = trim(GETPOST('q', 'nohtml'));
}

if (empty($identifier)) {
    echo json_encode(array('success' => false, 'error' => 'Identifiant manquant'));
    exit;
}

$result = $client->resolveEntity($identifier);

if ($result) {
    echo json_encode(array(
        'success' => true,
        'data' => $result
    ));
} else {
    echo json_encode(array(
        'success' => false,
        'error' => 'Entité non trouvée ou erreur API'
    ));
}

exit;
