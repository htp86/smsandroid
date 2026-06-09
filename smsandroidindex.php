<?php
/**
 * Page d'accueil et test d'envoi SMS - Module SMS Android
 */
$VERSION = date('Ymd', filemtime(__FILE__));
$BUILD = date('Hi', filemtime(__FILE__));
$PATHFILE = __FILE__;
$DEBUG_LIGHT = true;
$DEBUG_ERRORS = false;

if ($DEBUG_ERRORS) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Chargement Dolibarr
$res = 0;
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res) die("main.inc.php not found");

require_once __DIR__ . '/lib/smsandroid.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

$langs->loadLangs(array('smsandroid@smsandroid'));
if (!$user->admin) { accessforbidden(); }

global $db, $conf, $langs, $user;
$form = new Form($db);

// Traitement suppression historique
if (GETPOST('delete', 'alpha')) {
    $delId = (int) GETPOST('delid', 'int');
    if ($delId > 0) {
        $db->query("DELETE FROM ".$db->prefix()."smsandroid_log WHERE rowid = ".$delId);
    }
}

// Traitement envoi SMS
$result = null;
if (GETPOST('send', 'alpha')) {
    $token = GETPOST('token', 'alpha');
    if (empty($_SESSION['newtoken']) || $token !== $_SESSION['newtoken']) {
        $result = array('success' => false, 'message' => $langs->trans('ErrorSecurityToken'));
    } else {
        $phone = GETPOST('phone', 'alpha');
        $message = GETPOST('message', 'alphanohtml');
        if ($phone && $message) {
            $result = htp_send_sms($phone, $message);
        } else {
            $result = array('success' => false, 'message' => $langs->trans('SmsandroidErrorNoPhone'));
        }
    }
}

// Traitement AJAX health check
if (GETPOST('action', 'aZ09') === 'health_check_ajax') {
    try {
        $token = GETPOST('token', 'alpha');
        if (empty($_SESSION['newtoken']) || $token !== $_SESSION['newtoken']) {
            printJson(array('success' => false, 'message' => 'Token de sécurité invalide'));
        }
        $apiUrl = getDolGlobalString('SMSANDROID_API_URL');
        $apiUser = getDolGlobalString('SMSANDROID_API_USER');
        $apiPass = getDolGlobalString('SMSANDROID_API_PASS');
        if (empty($apiUrl) || empty($apiUser) || empty($apiPass)) {
            printJson(array('success' => false, 'message' => 'Configuration API incomplète'));
        }
        $json = htp_get_health($apiUrl, $apiUser, $apiPass);
        printJson($json);
    } catch (Throwable $e) {
        printJson(array('success' => false, 'message' => $e->getMessage()));
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

// Récupération des messages API pour affichage
$apiMessages = null;
$apiMessagesError = '';
if (getDolGlobalString('SMSANDROID_API_URL')) {
    $apiUrl = getDolGlobalString('SMSANDROID_API_URL');
    $apiUser = getDolGlobalString('SMSANDROID_API_USER');
    $apiPass = getDolGlobalString('SMSANDROID_API_PASS');
    $resultGet = htp_get_messages($apiUrl, $apiUser, $apiPass);
    if ($resultGet['success']) {
        $apiMessages = $resultGet['messages'];
    } else {
        $apiMessagesError = $resultGet['message'];
    }
}

// Affichage page
if ($DEBUG_LIGHT) {
    print '<div style="background:#e7f3ff;padding:8px;margin:10px;border-left:4px solid #007bff;font-family:monospace;font-size:11px;">';
    print '<strong> SMS Android</strong> | v'.$VERSION.' | '.$BUILD.' | '.basename($PATHFILE);
    print '</div>';
}

// ============================================================================
// ✅ DEBUG MODE VERBOSE
// ============================================================================
if ($DEBUG_ERRORS) {
    print '<div style="background:#fff3cd;padding:10px;margin:10px;border-left:4px solid #ffc107;font-family:monospace;font-size:10px;max-height:400px;overflow:auto;">';
    print '<strong> DEBUG MODE VERBOSE</strong> | Fichier: '.basename($PATHFILE).' | Action: '.dol_escape_htmltag(GETPOST('send', 'alpha') ? 'send' : 'view').'<br><br>';
    print '<strong> POST data ('.count($_POST).' keys):</strong><br>';
    foreach ($_POST as $k => $v) {
        if (stripos($k, 'pass') !== false || stripos($k, 'token') !== false) {
            print '  '.dol_escape_htmltag($k).' = ***<br>';
        } else {
            print '  '.dol_escape_htmltag($k).' = '.dol_escape_htmltag($v).'<br>';
        }
    }
    print '<strong> GET data ('.count($_GET).' keys):</strong><br>';
    foreach ($_GET as $k => $v) {
        print '  '.dol_escape_htmltag($k).' = '.dol_escape_htmltag($v).'<br>';
    }
    if ($result) {
        print '<strong> Resultat envoi:</strong><br>';
        print '  success = '.($result['success'] ? 'true' : 'false').'<br>';
        print '  message = '.dol_escape_htmltag($result['message']).'<br>';
        print '  error_code = '.dol_escape_htmltag($result['error_code'] ?? 'none').'<br>';
    }

    if ($apiMessagesError) {
        print '<strong> Erreur chargement messages API:</strong><br>';
        print '  '.dol_escape_htmltag($apiMessagesError).'<br>';
    }
    if (is_array($apiMessages)) {
        print '<strong> Messages API:</strong> '.count($apiMessages).' chargés<br>';
    }
    print '<strong> Infos environnement:</strong><br>';
    print '  PHP: '.phpversion().' | Dolibarr: '.(defined('DOL_VERSION') ? DOL_VERSION : '?').'<br>';
    print '  Server IP: '.dol_escape_htmltag($_SERVER['SERVER_ADDR'] ?? '?').' | Client: '.dol_escape_htmltag($_SERVER['REMOTE_ADDR'] ?? '?').'<br>';
    print '</div>';
}

llxHeader('', 'SMS Android - Test d\'envoi', '', '', 0, 0, '', '', '', 'mod-smsandroid page-index');
print load_fiche_titre('Test d\'envoi de SMS', '', 'smsandroid@smsandroid');
print dol_get_fiche_head();

// Formulaire
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" id="smsForm">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<table class="border centpercent">';

// Ligne 1: Selection Tiers (AJAX = plus rapide avec bcp de tiers)
print '<tr><td class="fieldrequired">Tiers</td><td>';
$conf->global->COMPANY_USE_SEARCH_TO_SELECT = 1;
print $form->select_company('', 'fk_soc', '', 1, 0, 1, array(), 20, 'minwidth300');
print ' <span id="phone-status" style="margin-left:10px;font-weight:bold;"></span>';
print '</td></tr>';

// Ligne 2: Numero de telephone
print '<tr><td class="fieldrequired">Numero de telephone *</td><td>';
print '<input type="text" name="phone" id="phone" value="" class="minwidth300" placeholder="+33612345678" required>';
print ' <small class="opacitymedium">(format: 06... ou +33...)</small>';
print '</td></tr>';

// Ligne 3: Modèles de messages (dropdown)
$tpls = array();
for ($i = 1; $i <= 10; $i++) {
    $tpl = getDolGlobalString('SMSANDROID_TEMPLATE_'.$i);
    if (!empty($tpl)) $tpls[$i] = $tpl;
}
if (!empty($tpls)) {
    print '<tr><td>Modèles</td><td>';
    print '<select id="template-select" class="minwidth300">';
    print '<option value="">-- Sélectionner un modèle --</option>';
    foreach ($tpls as $num => $txt) {
        $encoded = htmlspecialchars($txt, ENT_QUOTES, 'UTF-8');
        print '<option value="'.$encoded.'">'.dol_escape_htmltag($txt).'</option>';
    }
    print '</select>';
    print '</td></tr>';
}

// Ligne 4: Message
print '<tr><td class="fieldrequired">Message *</td><td>';
print '<textarea name="message" id="message" class="quatrevingtpercent" rows="4" placeholder="Votre message ici..." required></textarea>';
print '</td></tr>';

// Ligne 5: Bouton
print '<tr><td colspan="2">';
print '<input type="submit" name="send" class="butAction" value=" Envoyer le SMS">';
print '</td></tr>';

print '</table>';
print '</form>';

// JavaScript auto-remplissage
print '<script>
jQuery(document).ready(function() {
    jQuery("#template-select").on("change", function() {
        jQuery("#message").val(jQuery(this).val());
    });
    jQuery("#fk_soc").on("change", function() {
        var socId = jQuery(this).val();
        var phoneField = jQuery("#phone");
        var statusSpan = jQuery("#phone-status");
        
        if (socId > 0) {
            statusSpan.html("...");
            jQuery.ajax({
                url: "'.dol_buildpath('/smsandroid/ajax/getphone.php', 1).'",
                method: "POST",
                data: { id: socId, token: "'.newToken().'" },
                dataType: "json",
                success: function(data) {
                    if (data.success) {
                        if (data.phone_mobile) {
                            phoneField.val(data.phone_mobile);
                            statusSpan.html("OK").css("color", "#28a745");
                        } else if (data.phone_fixe) {
                            phoneField.val("");
                            statusSpan.html("Portable absent (fixe: "+data.phone_fixe+")").css("color", "#ffc107");
                        } else {
                            phoneField.val("");
                            statusSpan.html("Aucun numéro").css("color", "#dc3545");
                        }
                    } else {
                        statusSpan.html("Erreur").css("color", "#ffc107");
                    }
                },
                error: function() {
                    statusSpan.html("Erreur").css("color", "#ffc107");
                }
            });
        } else {
            phoneField.val("");
            statusSpan.html("");
        }
    });
});
</script>';

// Résultat envoi
if ($result) {
    $borderColor = $result['success'] ? '#28a745' : '#dc3545';
    $icon = $result['success'] ? 'OK' : 'KO';
    print '<br><div style="padding:10px;margin:10px;border:1px solid '.$borderColor.';background:#f8f9fa;border-left:4px solid '.$borderColor.';">';
    print '<strong>['.$icon.']</strong> <strong>'.$result['message'].'</strong>';
    if ($result['error_code']) {
        print ' <small class="opacitymedium">('.$result['error_code'].')</small>';
    }
    print '</div>';
}

// Historique
$historyLimit = getDolGlobalInt('SMSANDROID_QUICK_HISTORY', 10);
print '<br><strong>Historique ('.((int) $historyLimit).' derniers envois)</strong><br>';
$sql = "SELECT rowid, date_creation, phone, message, status, error_message FROM ".$db->prefix()."smsandroid_log ORDER BY rowid DESC LIMIT ".((int) $historyLimit);
$resql = $db->query($sql);
if ($resql && $db->num_rows($resql) > 0) {
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre"><td>Date</td><td>Tel</td><td>Message</td><td>Statut</td><td>Erreur</td><td></td></tr>';
    while ($obj = $db->fetch_object($resql)) {
        $color = $obj->status === 'Pending' ? '#ffc107' : ($obj->status === 'Sent' ? '#28a745' : '#dc3545');
        print '<tr class="oddeven">';
        print '<td class="nowrap">'.dol_print_date($db->jdate($obj->date_creation), 'dayhour').'</td>';
        print '<td class="nowrap">'.dol_escape_htmltag($obj->phone).'</td>';
        print '<td class="small opacitymedium">'.dol_trunc(dol_escape_htmltag($obj->message), 40).'</td>';
        print '<td><span style="color:'.$color.';font-weight:bold">'.$obj->status.'</span></td>';
        print '<td class="small opacitymedium">'.dol_escape_htmltag($obj->error_message).'</td>';
        print '<td>';
        if ($obj->status === 'Pending') {
            print '<form method="POST" style="display:inline;">';
            print '<input type="hidden" name="token" value="'.newToken().'">';
            print '<input type="hidden" name="delid" value="'.$obj->rowid.'">';
            print '<button type="submit" name="delete" class="butActionDelete" value="1" onclick="return confirm(\'Supprimer cet envoi ?\')">Suppr</button>';
            print '</form>';
        }
        print '</td>';
        print '</tr>';
    }
    print '</table>';
} else {
    print '<span class="opacitymedium">Aucun envoi enregistré</span>';
}

// Test signal GSM
print '<br><br>';
print '<strong>État du téléphone</strong><br>';
print '<div id="health-result" style="margin:8px 0;padding:10px;background:#f8f9fa;border-left:4px solid #6c757d;"></div>';
print '<button type="button" id="btn-health" class="butAction" onclick="checkHealth()">';
print '<span id="health-btn-icon">&#x1F4F6;</span> Test signal GSM</button>';

print '<script>
function checkHealth() {
    var resultDiv = document.getElementById("health-result");
    var btnIcon = document.getElementById("health-btn-icon");
    resultDiv.style.display = "block";
    resultDiv.style.borderLeftColor = "#007bff";
    resultDiv.innerHTML = "Vérification en cours...";
    btnIcon.innerHTML = "&#x23F3;";

    var formData = new FormData();
    formData.append("action", "health_check_ajax");
    formData.append("token", "'.newToken().'");

    fetch("'.$_SERVER['PHP_SELF'].'", {
        method: "POST",
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        btnIcon.innerHTML = "&#x1F4F6;";
        if (data.success && data.details) {
            var html = "<table style=\"width:auto;\">";
            for (var i = 0; i < data.details.length; i++) {
                var d = data.details[i];
                var color = d.status === "pass" ? "#28a745" : (d.status === "warn" ? "#ffc107" : "#dc3545");
                html += "<tr><td style=\"padding:2px 12px 2px 0;font-weight:bold;\">" + d.label + "</td>";
                html += "<td style=\"padding:2px 12px 2px 0;\">" + d.value + "</td>";
                html += "<td style=\"padding:2px 0;\"><span style=\"color:" + color + ";font-weight:bold;\">" + d.status + "</span></td></tr>";
            }
            html += "</table>";
            resultDiv.innerHTML = html;
            resultDiv.style.borderLeftColor = "#28a745";
        } else {
            resultDiv.innerHTML = "<strong style=\"color:#dc3545;\">" + (data.message || "Erreur inconnue") + "</strong>";
            resultDiv.style.borderLeftColor = "#dc3545";
        }
    })
    .catch(function(e) {
        btnIcon.innerHTML = "&#x1F4F6;";
        resultDiv.innerHTML = "<strong style=\"color:#dc3545;\">Erreur: " + e.message + "</strong>";
        resultDiv.style.borderLeftColor = "#dc3545";
    });
}
</script>';

print '<br><br>';
print '<strong>'.$langs->trans('SmsandroidApiMessages').'</strong><br>';
print '<span class="opacitymedium">'.$langs->trans('SmsandroidApiMessagesDesc').'</span><br>';

if ($apiMessagesError) {
    print '<div style="padding:8px;margin:8px 0;border-left:4px solid #dc3545;background:#f8f9fa;">';
    print 'Erreur: '.dol_escape_htmltag($apiMessagesError);
    print '</div>';
}

if (is_array($apiMessages)) {
    if (count($apiMessages) > 0) {
        print '<table class="noborder centpercent" style="margin-top:8px;">';
        print '<tr class="liste_titre">';
        print '<td>'.$langs->trans('SmsandroidApiMsgId').'</td>';
        print '<td>'.$langs->trans('SmsandroidApiMsgDate').'</td>';
        print '<td>'.$langs->trans('SmsandroidApiMsgTo').'</td>';
        print '<td>'.$langs->trans('SmsandroidApiMsgText').'</td>';
        print '<td>'.$langs->trans('SmsandroidApiMsgState').'</td>';
        print '</tr>';
        $msgs = array_reverse($apiMessages);
        foreach ($msgs as $m) {
            $state = !empty($m['state']) ? $m['state'] : 'Unknown';
            $color = $state === 'Delivered' ? '#28a745' : ($state === 'Failed' ? '#dc3545' : ($state === 'Pending' ? '#ffc107' : '#6c757d'));
            $to = '';
            if (!empty($m['recipients']) && is_array($m['recipients'])) {
                $nums = array();
                foreach ($m['recipients'] as $r) {
                    if (!empty($r['phoneNumber'])) $nums[] = $r['phoneNumber'];
                }
                $to = implode(', ', $nums);
            }
            if (empty($to)) $to = '-';
            $text = '-';
            $ts = '';
            if (!empty($m['states']) && is_array($m['states'])) {
                $states = $m['states'];
                $lastState = end($states);
                if ($lastState) $ts = $lastState;
            }
            $msgId = !empty($m['id']) ? $m['id'] : '';
            print '<tr class="oddeven">';
            print '<td class="nowrap">'.dol_escape_htmltag($msgId).'</td>';
            print '<td class="nowrap small">'.dol_escape_htmltag($ts).'</td>';
            print '<td class="nowrap">'.dol_escape_htmltag($to).'</td>';
            print '<td class="small opacitymedium">'.dol_trunc(dol_escape_htmltag($text), 50).'</td>';
            print '<td><span style="color:'.$color.';font-weight:bold">'.dol_escape_htmltag($state).'</span></td>';
            print '</tr>';
        }
        print '</table>';
    } else {
        print '<br><span class="opacitymedium">'.$langs->trans('SmsandroidApiNoMessages').'</span>';
    }
}

print dol_get_fiche_end();
llxFooter();
$db->close();