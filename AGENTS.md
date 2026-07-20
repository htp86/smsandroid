# Projet SMS Android — Contexte permanent

## Infrastructure
- **Serveur Dolibarr 1 (dev) :** `\\htp_ds418\web\dolibarr_test\` → https://dolibarr.test/
- **Serveur Dolibarr 2 (prod) :** `\\htp_ds418\web\htpservicesdolibarr\` → https://192.168.2.198:12345/
- **Base MariaDB :** `dolibarr_test`, préfixe `llx_`, sur le NAS Synology
- **Phone Android :** IP `192.168.2.209:8080`, MAC `40:b0:76:8f:44:cb`
- **Centreon VM :** `192.168.2.107` (CentOS 7)
- **PC utilisateur :** `192.168.2.198`

## API SMS Gateway (capcom6/android-sms-gateway)
- **Family :** `htpmultimedia` | **Numéro :** `500503`
- **URL :** `http://192.168.2.209:8080/`
- **Auth :** Basic Auth, user=`sms`, password=`xxhtphtpxx`
- **Version :** v1.63.0
- **Docs :** https://docs.sms-gate.app/
- **Endpoints utilisés :**
  - `GET /health` → battery, charging (bitmask, ≥1=en charge), cellular (0=none…5=5G), internet (0/1), failed_1h
  - `POST /message` → `{ "recipients": ["+33612345678"], "message": "texte" }`
  - `GET /message` → paginé (50 max), pas de champ `text` dans la réponse
  - `DELETE /message/{id}` → **pas supporté en v1.63.0** (404)
- **cURL test :** `curl -u sms:xxhtphtpxx http://192.168.2.209:8080/health`

## droidVNC (contrôle à distance)
- **Port :** 5901 sur le phone
- **Client :** TightVNC Viewer : `"C:\Program Files\TightVNC\tvnviewer.exe" 192.168.2.209:5901`

## Module smsandroid — Structure
```
custom/smsandroid/
├── smsandroidindex.php          # Page principale : envoi, health, messages API
├── admin/
│   ├── setup.php                # Configuration + test API
│   └── about.php                # Infos module
├── ajax/getphone.php            # Endpoint JSON pour envoi SMS
├── lib/smsandroid.lib.php       # Fonctions : htp_send_sms(), htp_get_health(), htp_get_messages()
├── core/modules/modSmsandroid.class.php  # Descripteur module Dolibarr
├── sql/llx_smsandroid.sql       # Structure table
└── AGENTS.md                    # Ce fichier
```

## Conventions de code (portabilité)
- **Tous les fichiers PHP pages** DOIVENT commencer par :
  ```php
  $VERSION = date('Ymd', filemtime(__FILE__));
  $BUILD = date('Hi', filemtime(__FILE__));
  $PATHFILE = __FILE__;
  $DEBUG_LIGHT = true;
  $DEBUG_ERRORS = false;
  if ($DEBUG_ERRORS) { ini_set('display_errors', 1); error_reporting(E_ALL); }
  ```
- **DEBUG_LIGHT :** Affiche une barre fixe en haut de page (version/build/chemin)
- **DEBUG_ERRORS :** Affiche une pre block en bas de page avec $_POST/$_GET/$_SERVER/$user/DOL_VERSION
- **Mettre `$DEBUG_LIGHT = false` et `$DEBUG_ERRORS = false` avant production**
- **Pas d'émoticônes** dans le code sauf demande explicite
- **Pas de `ini_set` en dur** — toujours conditionnel
- **Pas de commentaires** dans le code sauf si nécessaire

## Nagios Plugin
- **Chemin :** `/usr/lib64/nagios/plugins/check_android_gateway` (Python 2 compatible)
- **Version :** v1.1.0
- **Modes :** battery, charging, cellular, internet, failed, messages
- **Seuils :** battery warn=20 crit=10, failed warn=1 crit=5, cellular 4G=ok ≤3G=crit, charging=crit if 0
- **Fix charging :** `if [ "$val" -ge 1 ] 2>/dev/null;` (bitmask, ≥1 = en charge)
- **6 services Centreon :** battery, charging, cellular, internet, failed_1h, messages
- **Commande :** `$USER1$/check_android_gateway -H 192.168.2.209 -u sms -p xxhtphtpxx -M <mode> -w <warn> -c <crit>`

## Conventions OneNote
- Sections : `# ======` pour les titres
- Sous-étapes : indentation 3 espaces
- Commandes exactes sans modification
- Catégories : Installation, Configuration, Dépannage, Scripts, Notes

## Table SQL
- Table `llx_smsandroid_log` dans `dolibarr_test` (préfixe `llx_`)
- Créée automatiquement via `init()` du module

## Limitations
- 50 messages max retournés par l'API (pagination par défaut)
- DELETE non supporté → impossible de supprimer les messages depuis l'API
- Pas de champ `text` dans GET /message
- Phone actuellement sans SIM → cellular = None (0), envoi SMS impossible tant qu'une SIM 3G/4G+ n'est pas insérée

## Anthologie des décisions
- droidVNC plutôt que TeamViewer/AnyDesk (direct LAN, pas de cloud)
- 6 services Centreon séparés plutôt qu'un service combiné
- ARP statique sur Centreon VM via `/etc/rc.d/rc.local`
- Module auto-portable : pas de création manuelle de fichiers ou SQL
- Double instance Dolibarr : test + prod
