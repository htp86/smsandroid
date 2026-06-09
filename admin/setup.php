<?php
/**
 * Page de configuration du module SMS Android
 */
$VERSION = date('Ymd', filemtime(__FILE__));
$BUILD = date('Hi', filemtime(__FILE__));
$PATHFILE = __FILE__;
$DEBUG_LIGHT = true;
$DEBUG_ERRORS = false;
ob_start();
if ($DEBUG_ERRORS) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

$res = 0;
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res && file_exists("../../../../main.inc.php")) { $res = @include "../../../../main.inc.php"; }
if (!$res) die("main.inc.php not found");

// ============================================================================
// ✅ DEBUG LIGHT : Affichage visuel en haut de page (skip si AJAX)
// ============================================================================
$isAjax = (GETPOST('action', 'aZ09') === 'test_api_connection_ajax');
if ($DEBUG_LIGHT && !$isAjax) {
    print '<div style="background:#e7f3ff;padding:8px;margin:10px;border-left:4px solid #007bff;font-family:monospace;font-size:11px;">';
    print '<strong>🔍 Smsandroid Setup</strong> | v'.$VERSION.' | '.$BUILD.' | '.basename($PATHFILE);
    print '</div>';
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once '../lib/smsandroid.lib.php';

$langs->loadLangs(array('admin', 'smsandroid@smsandroid'));

global $db, $conf, $langs, $user;

$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'aZ09');

// ============================================================================
// ✅ HANDLER AJAX TEST API - PLACÉ ICI, AVANT accessforbidden()
// ============================================================================
if ($action == 'test_api_connection_ajax') {
    try {
        $token = GETPOST('token', 'alpha');
        if (empty($_SESSION['newtoken']) || $token !== $_SESSION['newtoken']) {
            printJson(['success' => false, 'message' => 'Token de sécurité invalide']);
        }
        
        $apiUrl = GETPOST('test_api_url', 'alphanohtml');
        $apiUser = GETPOST('test_api_user', 'alphanohtml');
        $apiPass = GETPOST('test_api_pass', 'alphanohtml');
        
        if (!function_exists('htp_test_android_api')) {
            require_once __DIR__ . '/../lib/smsandroid.lib.php';
        }
        $testResult = htp_test_android_api($apiUrl, $apiUser, $apiPass);
        printJson($testResult);
    } catch (Throwable $e) {
        printJson(['success' => false, 'message' => $e->getMessage()]);
    }
}

function printJson($data) {
    while (ob_get_level()) ob_end_clean();
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data);
    exit;
}

if (!$user->admin) { accessforbidden(); }


if (!class_exists('FormSetup')) {
    require_once DOL_DOCUMENT_ROOT.'/core/class/html.formsetup.class.php';
}
$formSetup = new FormSetup($db);

// ============================================================================
// ✅ PARAMÈTRES DE CONFIGURATION SMS ANDROID
// ============================================================================

// --- Section : Connexion API Android ---
$item = $formSetup->newItem('SMSANDROID_API_URL');
$item->setAsUrl();
$item->fieldAttr['placeholder'] = 'http://192.168.2.209:8080';
$item->fieldAttr['class'] = 'minwidth500';
$item->helpText = $langs->trans('SMSANDROID_API_URLTooltip');

$item = $formSetup->newItem('SMSANDROID_API_USER');
$item->fieldAttr['placeholder'] = 'sms';
$item->fieldAttr['class'] = 'minwidth200';
$item->helpText = $langs->trans('SMSANDROID_API_USERTooltip');

$item = $formSetup->newItem('SMSANDROID_API_PASS');
$item->setAsSecureKey();
$item->fieldAttr['placeholder'] = 'Mot de passe API';
$item->fieldAttr['class'] = 'minwidth200';
$item->helpText = $langs->trans('SMSANDROID_API_PASSTooltip');

// --- Section : Comportement et logs ---
$formSetup->newItem('NewSectionBehavior')->setAsTitle();

$item = $formSetup->newItem('SMSANDROID_PHONE_FORMAT');
$item->setAsSelect(array(
    'international' => 'International (+33...)',
    'national' => 'National (06...)'
));
$item->defaultFieldValue = 'international';
$item->helpText = $langs->trans('SMSANDROID_PHONE_FORMATTooltip');

$item = $formSetup->newItem('SMSANDROID_LOG_LEVEL');
$item->setAsSelect(array(
    '0' => 'Aucun',
    '1' => 'Erreurs uniquement',
    '2' => 'Info (Envois + Erreurs)',
    '3' => 'Debug complet (JSON API)'
));
$item->defaultFieldValue = '2';
$item->helpText = $langs->trans('SMSANDROID_LOG_LEVELTooltip');

$item = $formSetup->newItem('SMSANDROID_SHOW_LOGS');
$item->setAsYesNo();
$item->defaultFieldValue = 1;
$item->helpText = $langs->trans('SMSANDROID_SHOW_LOGSTooltip');

// --- Section : Modèles de messages ---
$formSetup->newItem('NewSectionTemplates')->setAsTitle();

$item = $formSetup->newItem('SMSANDROID_TEMPLATE_1');
$item->fieldAttr['placeholder'] = 'Ex: Votre rendez-vous est confirmé.';
$item->helpText = $langs->trans('SMSANDROID_TEMPLATE_1Tooltip');

$item = $formSetup->newItem('SMSANDROID_TEMPLATE_2');
$item->fieldAttr['placeholder'] = 'Ex: Rappel de paiement facture n°';
$item->helpText = $langs->trans('SMSANDROID_TEMPLATE_2Tooltip');

$item = $formSetup->newItem('SMSANDROID_TEMPLATE_3');
$item->fieldAttr['placeholder'] = 'Ex: Merci de confirmer votre présence.';
$item->helpText = $langs->trans('SMSANDROID_TEMPLATE_3Tooltip');

// --- Section : Historique rapide ---
$formSetup->newItem('NewSectionQuickHistory')->setAsTitle();

$item = $formSetup->newItem('SMSANDROID_QUICK_HISTORY');
// ← Ne pas appeler setAsInt/setAsText : le type par défaut est texte
$item->fieldAttr['placeholder'] = '10';
$item->fieldAttr['min'] = '1';
$item->fieldAttr['max'] = '100';
$item->fieldAttr['type'] = 'number';  // ← HTML5 input number
$item->fieldAttr['pattern'] = '[0-9]{1,3}';
$item->fieldAttr['style'] = 'width:80px;';
$item->helpText = $langs->trans('SMSANDROID_QUICK_HISTORYTooltip');

// --- Section : Test de connexion ---
$formSetup->newItem('NewSectionTest')->setAsTitle();

$testApiHtml = '<div id="test-api-result" style="margin:10px 0;padding:10px;background:#f8f9fa;border-left:4px solid #007bff;"></div>';
$testApiHtml .= '<button type="button" id="btn-test-api" class="butAction" onclick="testAndroidApi()">';
$testApiHtml .= img_picto('', 'fa-plug', 'class="paddingright"').$langs->trans('SmsandroidTestButton').'</button>';
$testApiHtml .= '<script>
function testAndroidApi() {
    var url = document.querySelector(\'input[name="SMSANDROID_API_URL"]\').value;
    var user = document.querySelector(\'input[name="SMSANDROID_API_USER"]\').value;
    var pass = document.querySelector(\'input[name="SMSANDROID_API_PASS"]\').value;
    var resultDiv = document.getElementById("test-api-result");
    
    resultDiv.style.display = "block";
    resultDiv.style.borderLeftColor = "#007bff";
    resultDiv.innerHTML = "Test en cours...";
    
    var formData = new FormData();
    formData.append("action", "test_api_connection_ajax");
    formData.append("token", "'.newToken().'");
    formData.append("test_api_url", url);
    formData.append("test_api_user", user);
    formData.append("test_api_pass", pass);
    
    fetch("'.dol_buildpath('/smsandroid/admin/setup.php', 1).'", {
        method: "POST",
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            resultDiv.style.borderLeftColor = "#28a745";
            resultDiv.innerHTML = "<strong>" + data.message + "</strong>";
        } else {
            resultDiv.style.borderLeftColor = "#dc3545";
            resultDiv.innerHTML = "<strong>" + data.message + "</strong>";
        }
    })
    .catch(function(e) {
        resultDiv.style.borderLeftColor = "#dc3545";
        resultDiv.innerHTML = "<strong>Erreur JavaScript: " + e.message + "</strong>";
    });
}
</script>';

// Le bouton test est affiché plus bas (hors FormSetup) pour éviter le label redondant
$setupnotempty = count($formSetup->items);

// ============================================================================
// ✅ TRAITEMENT DES ACTIONS (POST) - C'EST ICI QUE LES CONSTANTES SONT SAUVEGARDÉES
// ============================================================================
include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

// ============================================================================
// ✅ DEBUG ERRORS : Affichage VERBOSE à l'écran (pas juste dans les logs)
// ============================================================================
if ($DEBUG_ERRORS) {
    print '<div style="background:#fff3cd;padding:10px;margin:10px;border-left:4px solid #ffc107;font-family:monospace;font-size:10px;max-height:400px;overflow:auto;">';
    print '<strong>⚠️ DEBUG MODE VERBOSE</strong> | Fichier: '.basename($PATHFILE).' | Action: '.dol_escape_htmltag($action).'<br><br>';
    
    // --- POST data ---
    print '<strong>📥 POST data ('.count($_POST).' keys):</strong><br>';
    foreach ($_POST as $k => $v) {
        if (stripos($k, 'pass') !== false || stripos($k, 'token') !== false) {
            print '  '.dol_escape_htmltag($k).' = ***<br>';
        } else {
            print '  '.dol_escape_htmltag($k).' = '.dol_escape_htmltag(is_array($v) ? json_encode($v) : $v).'<br>';
        }
    }
    if (empty($_POST)) print '  (aucun)<br>';
    print '<br>';
    
    // --- GET data ---
    print '<strong>📤 GET data ('.count($_GET).' keys):</strong><br>';
    foreach ($_GET as $k => $v) {
        if (stripos($k, 'pass') !== false || stripos($k, 'token') !== false) {
            print '  '.dol_escape_htmltag($k).' = ***<br>';
        } else {
            print '  '.dol_escape_htmltag($k).' = '.dol_escape_htmltag(is_array($v) ? json_encode($v) : $v).'<br>';
        }
    }
    if (empty($_GET)) print '  (aucun)<br>';
    print '<br>';
    
    // --- Constantes Dolibarr actuelles ---
    print '<strong>⚙️ Constantes SMSANDROID (après chargement):</strong><br>';
    $apiUrl = getDolGlobalString('SMSANDROID_API_URL');
    $apiUser = getDolGlobalString('SMSANDROID_API_USER');
    $apiPass = getDolGlobalString('SMSANDROID_API_PASS');
    $phoneFmt = getDolGlobalString('SMSANDROID_PHONE_FORMAT', 'international');
    $logLevel = getDolGlobalInt('SMSANDROID_LOG_LEVEL', 2);
    $showLogs = getDolGlobalInt('SMSANDROID_SHOW_LOGS', 1);
    
    print '  SMSANDROID_API_URL = '.(empty($apiUrl)?'<em style="color:#dc3545">(vide)</em>':preg_replace('/:[^@]+@/', ':***@', dol_escape_htmltag($apiUrl))).'<br>';
    print '  SMSANDROID_API_USER = '.(empty($apiUser)?'<em style="color:#dc3545">(vide)</em>':dol_escape_htmltag($apiUser)).'<br>';
    print '  SMSANDROID_API_PASS = '.(empty($apiPass)?'<em style="color:#dc3545">(vide)</em>':'***').'<br>';
    print '  SMSANDROID_PHONE_FORMAT = '.dol_escape_htmltag($phoneFmt).'<br>';
    print '  SMSANDROID_LOG_LEVEL = '.dol_escape_htmltag($logLevel).'<br>';
    print '  SMSANDROID_SHOW_LOGS = '.($showLogs?'Oui':'Non').'<br>';
    print '<br>';
    
    // --- Test API rapide si URL configurée ---
    if (!empty($apiUrl) && !empty($apiUser) && !empty($apiPass)) {
        print '<strong>🔌 Test API en direct:</strong><br>';
        $testUrl = rtrim($apiUrl, '/').'/message';
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $testUrl,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_USERPWD => "$apiUser:$apiPass",
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode(['textMessage' => ['text' => 'debug'], 'phoneNumbers' => ['+33000000000']]),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        $time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        curl_close($ch);
        
        print '  Endpoint: '.dol_escape_htmltag($testUrl).'<br>';
        print '  HTTP Code: <strong style="color:'.($code >= 200 && $code < 400 ? '#28a745':'#dc3545').'">'.$code.'</strong><br>';
        print '  Time: '.round($time*1000, 0).' ms<br>';
        if (!empty($err)) {
            print '  cURL Error: <strong style="color:#dc3545">'.dol_escape_htmltag($err).'</strong><br>';
        }
        if (!empty($resp)) {
            $preview = strlen($resp) > 300 ? substr($resp, 0, 300).'...' : $resp;
            print '  Response preview: <pre style="background:#fff;padding:5px;margin:5px 0;font-size:9px;">'.dol_escape_htmltag($preview).'</pre>';
        }
        print '<br>';
    } else {
        print '<strong>🔌 Test API:</strong> <em style="color:#6c757d">Skippé (config incomplète)</em><br><br>';
    }
    
    // --- Infos serveur ---
    print '<strong>🖥️ Infos environnement:</strong><br>';
    print '  PHP Version: '.phpversion().'<br>';
    print '  Dolibarr Version: '.(defined('DOL_VERSION') ? DOL_VERSION : 'inconnue').'<br>';
    print '  User agent: '.dol_escape_htmltag($_SERVER['HTTP_USER_AGENT'] ?? 'inconnu').'<br>';
    print '  Server IP: '.dol_escape_htmltag($_SERVER['SERVER_ADDR'] ?? 'inconnue').'<br>';
    print '  Client IP: '.dol_escape_htmltag($_SERVER['REMOTE_ADDR'] ?? 'inconnue').'<br>';
    
    print '</div>';
}

// ============================================================================
// ✅ AFFICHAGE DE LA PAGE
// ============================================================================
llxHeader('', $langs->trans('SmsandroidSetup'), '', '', 0, 0, '', '', '', 'mod-smsandroid page-admin');

$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.img_picto($langs->trans("BackToModuleList"), 'back', 'class="pictofixedwidth"').'<span class="hideonsmartphone">'.$langs->trans("BackToModuleList").'</span></a>';
print load_fiche_titre($langs->trans('SmsandroidSetup'), $linkback, 'title_setup');

$head = smsandroidAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans('SmsandroidSetup'), -1, "smsandroid@smsandroid");

if (!empty($formSetup->items)) {
    print $formSetup->generateOutput(true).'<br>';
}
if (empty($setupnotempty)) {
    print '<br>'.$langs->trans("NothingToSetup");
}

// --- Bouton test API (hors FormSetup, pas de label redondant) ---
print $testApiHtml;

print dol_get_fiche_end();
llxFooter();
$db->close();