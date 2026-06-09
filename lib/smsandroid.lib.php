<?php
/**
 * Bibliothèque du module SMS Android
 */

/**
 * Prepare admin pages header
 */
function smsandroidAdminPrepareHead()
{
    global $langs, $conf;
    $langs->load("smsandroid@smsandroid");
    $h = 0;
    $head = array();
    $head[$h][0] = dol_buildpath("/smsandroid/admin/setup.php", 1);
    $head[$h][1] = $langs->trans("Settings");
    $head[$h][2] = 'settings';
    $h++;
    $head[$h][0] = dol_buildpath("/smsandroid/admin/about.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;
    complete_head_from_modules($conf, $langs, null, $head, $h, 'smsandroid@smsandroid');
    complete_head_from_modules($conf, $langs, null, $head, $h, 'smsandroid@smsandroid', 'remove');
    return $head;
}

/**
 * Envoi de SMS via API Android (SMS Gateway)
 *
 * @param string $phone Numéro de téléphone (sera normalisé)
 * @param string $message Contenu du SMS
 * @param array $config Configuration optionnelle (sinon utilise getDolGlobalString)
 * @return array ['success'=>bool, 'message'=>string, 'response'=>array|null, 'error_code'=>string|null]
 */
function htp_send_sms($phone, $message, $config = array())
{
    global $conf, $langs, $db, $user;
    
    $apiUrl = !empty($config['api_url']) ? $config['api_url'] : getDolGlobalString('SMSANDROID_API_URL');
    $apiUser = !empty($config['api_user']) ? $config['api_user'] : getDolGlobalString('SMSANDROID_API_USER');
    $apiPass = !empty($config['api_pass']) ? $config['api_pass'] : getDolGlobalString('SMSANDROID_API_PASS');
    $phoneFormat = !empty($config['phone_format']) ? $config['phone_format'] : getDolGlobalString('SMSANDROID_PHONE_FORMAT', 'international');
    $logLevel = getDolGlobalInt('SMSANDROID_LOG_LEVEL', 2);
    
    $result = array('success' => false, 'message' => '', 'response' => null, 'error_code' => null);
    $status = 'Failed';
    $errorMessage = '';
    $apiResponseRaw = '';
    
    if (empty($apiUrl)) {
        $result['message'] = $langs->trans('SmsandroidErrorApiUrl');
        $result['error_code'] = 'CONFIG_API_URL_MISSING';
        $errorMessage = $result['message'];
        if ($logLevel >= 1) error_log("[SMSANDROID ERROR] ".$result['message']);
        htp_log_sms($phone, $message, $status, '', $errorMessage);
        return $result;
    }
    
    if (empty($apiUser) || empty($apiPass)) {
        $result['message'] = $langs->trans('SmsandroidErrorApiToken');
        $result['error_code'] = 'CONFIG_AUTH_MISSING';
        $errorMessage = $result['message'];
        if ($logLevel >= 1) error_log("[SMSANDROID ERROR] ".$result['message']);
        htp_log_sms($phone, $message, $status, '', $errorMessage);
        return $result;
    }
    
    if (empty($phone)) {
        $result['message'] = $langs->trans('SmsandroidErrorNoPhone');
        $result['error_code'] = 'INPUT_PHONE_MISSING';
        $errorMessage = $result['message'];
        htp_log_sms($phone, $message, $status, '', $errorMessage);
        return $result;
    }
    
    if (empty($message)) {
        $result['message'] = $langs->trans('SmsandroidErrorNoMessage');
        $result['error_code'] = 'INPUT_MESSAGE_MISSING';
        $errorMessage = $result['message'];
        htp_log_sms($phone, $message, $status, '', $errorMessage);
        return $result;
    }
    
    $normalizedPhone = htp_normalize_phone($phone, $phoneFormat);
    if ($logLevel >= 3) error_log("[SMSANDROID DEBUG] Phone normalized: $phone -> $normalizedPhone");
    
    $endpoint = rtrim($apiUrl, '/') . '/message';
    $postData = json_encode(array(
        'textMessage' => array('text' => $message),
        'phoneNumbers' => array($normalizedPhone)
    ), JSON_UNESCAPED_UNICODE);
    
    if ($logLevel >= 3) error_log("[SMSANDROID DEBUG] POST to $endpoint | Data: $postData");
    
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $endpoint,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERPWD => "$apiUser:$apiPass",
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Accept: application/json'
        ),
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ));
    
    $startTime = microtime(true);
    $rawResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    $execTime = round(microtime(true) - $startTime, 3);
    curl_close($ch);
    
    if ($logLevel >= 2) {
        error_log("[SMSANDROID INFO] API call: HTTP $httpCode | Time: {$execTime}s | Error: ".($curlError ?: 'none'));
    }
    
    if ($curlErrno !== 0) {
        $result['message'] = $langs->trans('SmsandroidErrorConnection').': '.$curlError;
        $result['error_code'] = 'CURL_ERROR_'.$curlErrno;
        $errorMessage = $curlError;
        if ($logLevel >= 1) error_log("[SMSANDROID ERROR] CURL #{$curlErrno}: $curlError");
        htp_log_sms($normalizedPhone, $message, $status, $rawResponse, $errorMessage);
        return $result;
    }
    
    $apiResponseRaw = $rawResponse;
    $jsonResponse = json_decode($rawResponse, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($jsonResponse)) {
        $result['message'] = $langs->trans('SmsandroidErrorInvalidResponse');
        $result['error_code'] = 'API_INVALID_JSON';
        $errorMessage = 'Invalid JSON response';
        if ($logLevel >= 1) error_log("[SMSANDROID ERROR] Invalid JSON: ".substr($rawResponse, 0, 200));
        htp_log_sms($normalizedPhone, $message, $status, $apiResponseRaw, $errorMessage);
        return $result;
    }
    
    $result['response'] = $jsonResponse;
    
    $apiState = !empty($jsonResponse['state']) ? $jsonResponse['state'] : 'Unknown';
    $recipients = !empty($jsonResponse['recipients']) ? $jsonResponse['recipients'] : array();
    
    if (!empty($jsonResponse['error'])) {
        $errorMsg = is_array($jsonResponse['error']) ? json_encode($jsonResponse['error']) : $jsonResponse['error'];
        $result['message'] = $langs->trans('SmsandroidErrorApiPrefix').' '.$errorMsg;
        $result['error_code'] = 'API_ERROR';
        $errorMessage = $errorMsg;
        
        if (stripos($errorMsg, 'subscriptionManager') !== false || stripos($errorMsg, 'activeSubscriptionInfoList') !== false || stripos($errorMsg, 'no sim') !== false) {
            $result['error_code'] = 'ANDROID_NO_SIM';
            $result['message'] = $langs->trans('SmsandroidErrorNoSim');
        }
        
        if ($logLevel >= 1) error_log("[SMSANDROID ERROR] API error: $errorMsg");
        htp_log_sms($normalizedPhone, $message, $status, $apiResponseRaw, $errorMessage);
        return $result;
    }
    
    if ($apiState === 'Failed') {
        $recipientError = '';
        foreach ($recipients as $r) {
            if (!empty($r['errorMessage'])) {
                $recipientError = $r['errorMessage'];
                break;
            }
        }
        $result['message'] = $langs->trans('SmsandroidErrorSendFailed').' '.($recipientError ?: '');
        $result['error_code'] = 'API_STATE_FAILED';
        $errorMessage = $recipientError ?: 'Unknown error';
        
        if (stripos($recipientError, 'no sim') !== false || stripos($recipientError, 'subscription') !== false) {
            $result['error_code'] = 'ANDROID_NO_SIM';
            $result['message'] = $langs->trans('SmsandroidErrorNoSim');
        }
        
        if ($logLevel >= 1) error_log("[SMSANDROID ERROR] API state=Failed: $recipientError");
        htp_log_sms($normalizedPhone, $message, $status, $apiResponseRaw, $errorMessage);
        return $result;
    }
    
    if (in_array($apiState, array('Pending', 'Processed', 'Sent'))) {
        $result['success'] = true;
        $result['message'] = ($apiState === 'Pending')
            ? $langs->trans('SmsandroidSentPending')
            : $langs->trans('SmsandroidSentSuccess');
        $status = $apiState;
        if ($logLevel >= 2) error_log("[SMSANDROID INFO] SMS accepted | State: $apiState | ID: ".($jsonResponse['id'] ?? 'N/A'));
        $errorMessage = ($apiState === 'Pending') ? 'En attente (pas de SIM ?)' : '';
        htp_log_sms($normalizedPhone, $message, $status, $apiResponseRaw, $errorMessage);
        return $result;
    }
    
    $result['message'] = $langs->trans('SmsandroidErrorUnknownState').' '.$apiState;
    $result['error_code'] = 'API_UNKNOWN_STATE';
    $errorMessage = 'Unknown state: '.$apiState;
    if ($logLevel >= 1) error_log("[SMSANDROID WARN] Unknown API state: $apiState");
    htp_log_sms($normalizedPhone, $message, $status, $apiResponseRaw, $errorMessage);
    return $result;
}

/**
 * Enregistre un SMS dans l'historique (llx_smsandroid_log)
 *
 * @param string $phone Numéro de téléphone
 * @param string $message Contenu du SMS
 * @param string $status Statut (Pending/Sent/Failed)
 * @param string $apiResponse Réponse JSON brute de l'API
 * @param string $errorMessage Message d'erreur éventuel
 * @return int rowid inséré ou 0
 */
function htp_log_sms($phone, $message, $status, $apiResponse = '', $errorMessage = '')
{
    global $db, $user, $conf;
    
    if (!is_object($db) || $db->connected == 0) {
        return 0;
    }
    
    $entity = !empty($conf->entity) ? (int) $conf->entity : 1;
    $fkUserAuthor = !empty($user->id) ? (int) $user->id : 0;
    
    $sql = "INSERT INTO ".$db->prefix()."smsandroid_log (date_creation, phone, message, status, api_response, error_message, fk_user_author, entity) VALUES (";
    $sql .= "'".$db->idate(dol_now())."',";
    $sql .= "'".$db->escape($phone)."',";
    $sql .= "'".$db->escape($message)."',";
    $sql .= "'".$db->escape($status)."',";
    $sql .= "'".$db->escape($apiResponse)."',";
    $sql .= "'".$db->escape($errorMessage)."',";
    $sql .= $fkUserAuthor.",";
    $sql .= $entity;
    $sql .= ")";
    
    $resql = $db->query($sql);
    if ($resql) {
        return $db->last_insert_id($db->prefix()."smsandroid_log");
    }
    return 0;
}

/**
 * Normalisation du numéro de téléphone
 * Note: la conversion 0X -> +33 est un comportement par défaut pour la France.
 * Pour un autre pays, configurez SMSANDROID_PHONE_FORMAT sur 'national'
 * ou passez un numéro déjà au format international (+XX...).
 *
 * @param string $phone Numéro brut
 * @param string $format 'international' (+33...) ou 'national' (06...)
 * @return string Numéro normalisé
 */
function htp_normalize_phone($phone, $format = 'international')
{
    $phone = preg_replace('/[\s\.\-\(\)]/', '', $phone);
    
    if ($format === 'international') {
        if (substr($phone, 0, 1) === '0' && strlen($phone) === 10) {
            $phone = '+33' . substr($phone, 1);
        }
        if (substr($phone, 0, 1) !== '+' && strlen($phone) === 9) {
            $phone = '+33' . $phone;
        }
    } else {
        if (substr($phone, 0, 1) !== '0' && substr($phone, 0, 3) === '+33') {
            $phone = '0' . substr($phone, 3);
        }
    }
    
    return $phone;
}

/**
 * Récupère tous les messages depuis l'API Android (GET /message)
 *
 * @param string $apiUrl URL de l'API
 * @param string $apiUser Utilisateur
 * @param string $apiPass Mot de passe
 * @return array ['success'=>bool, 'messages'=>array, 'message'=>string]
 */
function htp_get_messages($apiUrl, $apiUser, $apiPass)
{
    global $langs;
    $result = array('success' => false, 'messages' => array(), 'message' => '');

    if (empty($apiUrl) || empty($apiUser) || empty($apiPass)) {
        $result['message'] = 'Configuration API incomplète';
        return $result;
    }

    $endpoint = rtrim($apiUrl, '/') . '/message';
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERPWD => "$apiUser:$apiPass",
        CURLOPT_HTTPHEADER => array('Accept: application/json'),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ));

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if (!empty($curlError)) {
        $result['message'] = 'Erreur de connexion: '.$curlError;
        return $result;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $result['message'] = 'HTTP '.$httpCode;
        return $result;
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $result['message'] = 'Réponse JSON invalide';
        return $result;
    }

    $result['success'] = true;
    $result['messages'] = $data;
    $result['message'] = count($data) . ' message(s) récupéré(s)';
    return $result;
}

/**
 * Récupère les infos health depuis l'API Android (GET /health)
 *
 * @param string $apiUrl URL de l'API
 * @param string $apiUser Utilisateur
 * @param string $apiPass Mot de passe
 * @return array ['success'=>bool, 'message'=>string, 'details'=>array]
 */
function htp_get_health($apiUrl, $apiUser, $apiPass)
{
    $result = array('success' => false, 'message' => '', 'details' => array());

    $endpoint = rtrim($apiUrl, '/') . '/health';
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $endpoint,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERPWD => "$apiUser:$apiPass",
        CURLOPT_HTTPHEADER => array('Accept: application/json'),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ));

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if (!empty($curlError)) {
        $result['message'] = 'Erreur de connexion: '.$curlError;
        return $result;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $result['message'] = 'HTTP '.$httpCode;
        return $result;
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $result['message'] = 'Réponse JSON invalide';
        return $result;
    }

    $details = array();
    $checks = isset($data['checks']) ? $data['checks'] : array();
    $labels = array(
        'battery:level' => 'Batterie',
        'battery:charging' => 'Charge',
        'connection:cellular' => 'Réseau mobile',
        'connection:status' => 'Internet',
        'messages:failed' => 'Échecs (1h)'
    );
    $cellularLabels = array(0 => 'None', 1 => 'Inconnu', 2 => '2G', 3 => '3G', 4 => '4G', 5 => '5G');

    foreach ($checks as $key => $check) {
        $val = isset($check['observedValue']) ? $check['observedValue'] : '?';
        $label = isset($labels[$key]) ? $labels[$key] : $key;
        $rawVal = $val;

        if ($key === 'connection:cellular') {
            $val = isset($cellularLabels[$rawVal]) ? $cellularLabels[$rawVal] : $rawVal;
            if ($rawVal >= 4) $status = 'pass';
            elseif ($rawVal == 3) $status = 'warn';
            else $status = 'crit';
        } elseif ($key === 'battery:charging') {
            $val = ($rawVal >= 1) ? 'Oui' : 'Non';
            $status = $rawVal >= 1 ? 'pass' : 'crit';
        } elseif ($key === 'battery:level') {
            if ($rawVal <= 10) $status = 'crit';
            elseif ($rawVal <= 20) $status = 'warn';
            else $status = 'pass';
        } elseif ($key === 'connection:status') {
            $val = ($rawVal == 1) ? 'Connecté' : 'Coupé';
            $status = $rawVal == 1 ? 'pass' : 'crit';
        } elseif ($key === 'messages:failed') {
            if ($rawVal >= 5) $status = 'crit';
            elseif ($rawVal >= 1) $status = 'warn';
            else $status = 'pass';
        }

        if (isset($labels[$key])) {
            $details[] = array('label' => $label, 'value' => $val, 'status' => $status);
        }
    }

    $result['success'] = true;
    $result['message'] = 'Health check OK';
    $result['details'] = $details;
    return $result;
}

/**
 * Supprime un message via l'API Android (DELETE /message/{id})
 *
 * @param string $apiUrl URL de l'API
 * @param string $apiUser Utilisateur
 * @param string $apiPass Mot de passe
 * @param string $msgId ID du message à supprimer
 * @return array ['success'=>bool, 'message'=>string, 'http_code'=>int, 'raw_response'=>string, 'curl_error'=>string]
 */
function htp_delete_message($apiUrl, $apiUser, $apiPass, $msgId)
{
    $result = array('success' => false, 'message' => '', 'http_code' => 0, 'raw_response' => '', 'curl_error' => '');

    if (empty($msgId)) {
        $result['message'] = 'ID message manquant';
        return $result;
    }

    $endpoint = rtrim($apiUrl, '/') . '/message/' . urlencode($msgId);
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $endpoint,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERPWD => "$apiUser:$apiPass",
        CURLOPT_HTTPHEADER => array('Accept: application/json'),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ));

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $result['http_code'] = $httpCode;
    $result['raw_response'] = $raw;
    $result['curl_error'] = $curlError;

    if (!empty($curlError)) {
        $result['message'] = 'Erreur de connexion: '.$curlError;
        return $result;
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        $result['success'] = true;
        $result['message'] = 'Message supprimé (HTTP '.$httpCode.')';
    } else {
        $result['message'] = 'Échec suppression (HTTP '.$httpCode.')';
    }

    return $result;
}

/**
 * Test de connexion API (pour le bouton de test dans setup.php)
 *
 * @param string $apiUrl URL de l'API
 * @param string $apiUser Utilisateur
 * @param string $apiPass Mot de passe
 * @return array ['success'=>bool, 'message'=>string, 'details'=>array]
 */
function htp_test_android_api($apiUrl, $apiUser, $apiPass)
{
    global $langs;
    $langs->load("smsandroid@smsandroid");
    
    $result = array('success' => false, 'message' => '', 'details' => array());
    
    if (empty($apiUrl) || empty($apiUser) || empty($apiPass)) {
        $result['message'] = $langs->trans('SmsandroidErrorIncompleteConfig');
        return $result;
    }
    
    $endpoint = rtrim($apiUrl, '/') . '/message';
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $endpoint,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERPWD => "$apiUser:$apiPass",
        CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
        CURLOPT_POSTFIELDS => json_encode(array('textMessage' => array('text' => 'test'), 'phoneNumbers' => array('+33000000000'))),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ));
    
    $rawResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    $result['details'] = array(
        'http_code' => $httpCode,
        'curl_error' => $curlError,
        'response_preview' => substr($rawResponse, 0, 300)
    );
    
    if (!empty($curlError)) {
        $result['message'] = $langs->trans('SmsandroidErrorConnection').' '.$curlError;
        return $result;
    }
    
    if (in_array($httpCode, array(200, 201, 202, 400))) {
        $result['success'] = true;
        $result['message'] = $langs->trans('SmsandroidTestSuccess').' (HTTP '.$httpCode.')';
        return $result;
    }
    
    $result['message'] = $langs->trans('SmsandroidTestHttpError').' '.$httpCode;
    return $result;
}