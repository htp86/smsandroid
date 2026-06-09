<?php
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

$res = 0;
if (!$res && file_exists("../../main.inc.php")) { $res = @include "../../main.inc.php"; }
if (!$res && file_exists("../../../main.inc.php")) { $res = @include "../../../main.inc.php"; }
if (!$res) {
    die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once '../lib/smsandroid.lib.php';

$langs->loadLangs(array("errors", "admin", "smsandroid@smsandroid"));

if (!$user->admin) {
    accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

$form = new Form($db);

$help_url = '';
$title = "SmsandroidSetup";

if ($DEBUG_LIGHT) {
    print '<div style="background:#e7f3ff;padding:8px;margin:10px;border-left:4px solid #007bff;font-family:monospace;font-size:11px;">';
    print '<strong> SMS Android</strong> | v'.$VERSION.' | '.$BUILD.' | '.basename($PATHFILE);
    print '</div>';
}

if ($DEBUG_ERRORS) {
    print '<div style="background:#fff3cd;padding:10px;margin:10px;border-left:4px solid #ffc107;font-family:monospace;font-size:10px;max-height:400px;overflow:auto;">';
    print '<strong> DEBUG MODE VERBOSE</strong> | Fichier: '.basename($PATHFILE).' | Action: '.dol_escape_htmltag($action).'<br><br>';
    print '<strong> POST data ('.count($_POST).' keys):</strong><br>';
    foreach ($_POST as $k => $v) {
        if (stripos($k, 'pass') !== false || stripos($k, 'token') !== false) {
            print '  '.dol_escape_htmltag($k).' = ***<br>';
        } else {
            print '  '.dol_escape_htmltag($k).' = '.dol_escape_htmltag(is_array($v) ? json_encode($v) : $v).'<br>';
        }
    }
    print '<strong> GET data ('.count($_GET).' keys):</strong><br>';
    foreach ($_GET as $k => $v) {
        if (stripos($k, 'pass') !== false || stripos($k, 'token') !== false) {
            print '  '.dol_escape_htmltag($k).' = ***<br>';
        } else {
            print '  '.dol_escape_htmltag($k).' = '.dol_escape_htmltag($v).'<br>';
        }
    }
    print '<strong> Infos environnement:</strong><br>';
    print '  PHP: '.phpversion().' | Dolibarr: '.(defined('DOL_VERSION') ? DOL_VERSION : '?').'<br>';
    print '  Server IP: '.dol_escape_htmltag($_SERVER['SERVER_ADDR'] ?? '?').' | Client: '.dol_escape_htmltag($_SERVER['REMOTE_ADDR'] ?? '?').'<br>';
    print '</div>';
}

llxHeader('', $langs->trans($title), $help_url, '', 0, 0, '', '', '', 'mod-smsandroid page-admin_about');

$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.img_picto($langs->trans("BackToModuleList"), 'back', 'class="pictofixedwidth"').'<span class="hideonsmartphone">'.$langs->trans("BackToModuleList").'</span></a>';

print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

$head = smsandroidAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $langs->trans($title), 0, 'smsandroid@smsandroid');

$langs->load("smsandroid@smsandroid");
print $langs->trans("ModuleSmsandroidDescLong");

print dol_get_fiche_end();
llxFooter();
$db->close();
