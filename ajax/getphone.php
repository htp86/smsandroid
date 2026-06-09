<?php
/**
 * Endpoint AJAX pour récupérer le téléphone d'un tiers
 */
$VERSION = date('Ymd', filemtime(__FILE__));
$BUILD = date('Hi', filemtime(__FILE__));
$PATHFILE = __FILE__;
$DEBUG_LIGHT = true;
$DEBUG_ERRORS = false;
// Chargement Dolibarr
$res = 0;
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res && file_exists("../../../../main.inc.php")) { $res = @include "../../../../main.inc.php"; }
if (!$res) die("main.inc.php not found");

// Vérifications sécurité
if (!$user->admin) { http_response_code(403); echo json_encode(['error'=>'Forbidden']); exit; }
$token = GETPOST('token', 'alpha');
if (empty($_SESSION['newtoken']) || $token !== $_SESSION['newtoken']) { http_response_code(403); echo json_encode(['error'=>'Invalid token']); exit; }

// Récupération téléphone (colonnes vérifiées : phone, phone_mobile)
$socid = GETPOST('id', 'int');
$sql = "SELECT phone, phone_mobile FROM ".MAIN_DB_PREFIX."societe WHERE rowid = ".(int)$socid;
$resql = $db->query($sql);
$obj = $resql ? $db->fetch_object($resql) : null;

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success'      => ($resql !== false),
    'phone_mobile' => !empty($obj->phone_mobile) ? $obj->phone_mobile : '',
    'phone_fixe'   => !empty($obj->phone) ? $obj->phone : ''
]);
exit;