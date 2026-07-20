<?php
/**
 * Description du module Smsandroid
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modSmsandroid extends DolibarrModules
{
    public function __construct($db)
    {
        global $conf, $langs;
        $this->db = $db;

        $this->numero = 500503;
        $this->rights_class = 'smsandroid';
        $this->family = "htpmultimedia";
        $this->module_position = '90';
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = "ModuleSmsandroidDesc";
        $this->descriptionlong = "ModuleSmsandroidDescLong";
        $this->editor_name = 'Joël PICOU';
        $this->editor_url = '';
        $this->editor_squarred_logo = '';
        $this->version = '1.0';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->picto = 'fa-sms';
        
        $this->module_parts = array(
            'triggers' => 0, 'login' => 0, 'substitutions' => 0, 'menus' => 0, 'tpl' => 0,
            'barcode' => 0, 'models' => 0, 'printing' => 0, 'theme' => 0,
            'css' => array(), 'js' => array(), 'hooks' => array(),
            'moduleforexternal' => 0, 'websitetemplates' => 0, 'captcha' => 0
        );

        $this->dirs = array("/smsandroid/temp");
        $this->config_page_url = array("setup.php@smsandroid");
        $this->hidden = false;
        $this->depends = array();
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->langfiles = array("smsandroid@smsandroid");
        $this->phpmin = array(7, 2);
        $this->need_dolibarr_version = array(19, -3);
        $this->need_javascript_ajax = 0;
        $this->warnings_activation = array();
        $this->warnings_activation_ext = array();
        
        // ============================================================================
        // ✅ CONSTANTES PAR DÉFAUT (créées dans llx_const à l'activation)
        // ============================================================================
        $this->const = array(
            0 => array('SMSANDROID_API_URL', 'chaine', 'http://192.168.2.209:8080', 'URL API Android', 1, 'current', 0),
            1 => array('SMSANDROID_API_USER', 'chaine', 'sms', 'Utilisateur API', 1, 'current', 0),
            2 => array('SMSANDROID_API_PASS', 'chaine', '', 'Mot de passe API', 1, 'current', 0),
            3 => array('SMSANDROID_PHONE_FORMAT', 'chaine', 'international', 'Format numéros', 1, 'current', 0),
            4 => array('SMSANDROID_LOG_LEVEL', 'chaine', '2', 'Niveau de log', 1, 'current', 0),
            5 => array('SMSANDROID_SHOW_LOGS', 'chaine', '1', 'Afficher logs', 1, 'current', 0),
            6 => array('SMSANDROID_TEMPLATE_1', 'chaine', '', 'Modèle message 1', 1, 'current', 0),
            7 => array('SMSANDROID_TEMPLATE_2', 'chaine', '', 'Modèle message 2', 1, 'current', 0),
            8 => array('SMSANDROID_TEMPLATE_3', 'chaine', '', 'Modèle message 3', 1, 'current', 0),
			9 => array('SMSANDROID_QUICK_HISTORY', 'chaine', '10', 'Nombre SMS historique rapide', 1, 'current', 0),
        );

        if (!isModEnabled("smsandroid")) {
            $conf->smsandroid = new stdClass();
            $conf->smsandroid->enabled = 0;
        }

        $this->tabs = array();
        $this->dictionaries = array();
        $this->boxes = array();
        $this->cronjobs = array();
        $this->rights = array();
        
        $this->menu = array();
        $r = 0;
        $this->menu[$r++] = array(
            'fk_menu' => '', 'type' => 'top', 'titre' => 'ModuleSmsandroidName',
            'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
            'mainmenu' => 'smsandroid', 'leftmenu' => '', 'url' => '/smsandroid/smsandroidindex.php',
            'langs' => 'smsandroid@smsandroid', 'position' => 1000 + $r,
            'enabled' => "isModEnabled('smsandroid')", 'perms' => '1', 'target' => '', 'user' => 2,
        );

        // Logs debug (desactiver en production)
        if (getDolGlobalInt('SMSANDROID_LOG_LEVEL', 2) >= 3) {
            error_log("[SMSANDROID] modSmsandroid.class.php loaded | version {$this->version}");
            foreach ($this->const as $c) {
                error_log("[SMSANDROID]   Const: {$c[0]} = ".(empty($c[2])?'(empty)':preg_replace('/:[^@]+@/', ':***@', $c[2])));
            }
        }
    }

    public function init($options = '')
    {
        global $conf, $langs;
        
        $sql = array();
        
        // Creation table historique (portable : pas de dependance _load_tables)
        $sql[0] = "CREATE TABLE IF NOT EXISTS ".$this->db->prefix()."smsandroid_log (
            rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
            date_creation DATETIME DEFAULT NULL,
            tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            phone VARCHAR(30) NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(20) DEFAULT 'Pending',
            api_response TEXT DEFAULT NULL,
            error_message VARCHAR(255) DEFAULT NULL,
            fk_user_author INTEGER DEFAULT NULL,
            fk_user_modif INTEGER DEFAULT NULL,
            entity INTEGER DEFAULT 1 NOT NULL,
            INDEX idx_smsandroid_phone (phone),
            INDEX idx_smsandroid_status (status),
            INDEX idx_smsandroid_date (date_creation),
            INDEX idx_smsandroid_entity (entity)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        return $this->_init($sql, $options);
    }

    public function remove($options = '')
    {
        $sql = array();
        $sql[0] = "DROP TABLE IF EXISTS ".$this->db->prefix()."smsandroid_log;";
        return $this->_remove($sql, $options);
    }
}