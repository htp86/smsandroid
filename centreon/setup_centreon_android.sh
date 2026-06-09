Plugin check_android_gateway v1.1.0
A installer sur Centreon avant tout :
/usr/lib64/nagios/plugins/check_android_gateway

Contenu du plugin (commande cat > à archiver dans OneNote) :

cat > /usr/lib64/nagios/plugins/check_android_gateway << 'SCRIPT'
#!/bin/bash
VERSION="1.1.0"
STATE_OK=0; STATE_WARNING=1; STATE_CRITICAL=2; STATE_UNKNOWN=3
HOST=""; PORT="8080"; AUTH=""; MODE="battery"
while [ $# -gt 0 ]; do
    case "$1" in
        --mode=*) MODE="${1#*=}" ;;
        -H=*|--host=*) HOST="${1#*=}" ;;
        -H|--host) HOST="$2"; shift ;;
        -p=*|--port=*) PORT="${1#*=}" ;;
        -p|--port) PORT="$2"; shift ;;
        -a=*|--auth=*) AUTH="${1#*=}" ;;
        -a|--auth) AUTH="$2"; shift ;;
        --help|-h)
            echo "check_android_gateway v$VERSION"
            echo "Modes: battery, charging, cellular, internet, failed1h, messages"
            exit $STATE_UNKNOWN ;;
        *) echo "UNKNOWN - $1"; exit $STATE_UNKNOWN ;;
    esac; shift
done
[ -z "$HOST" ] && { echo "UNKNOWN - Missing --host"; exit $STATE_UNKNOWN; }
[ -z "$AUTH" ] && { echo "UNKNOWN - Missing --auth"; exit $STATE_UNKNOWN; }
get_health() {
    local json=$(curl -s --noproxy '*' --connect-timeout 5 --max-time 10 -u "$AUTH" "http://${HOST}:${PORT}/health")
    [ $? -ne 0 ] && { echo "CRITICAL - Cannot connect to API"; exit $STATE_CRITICAL; }
    echo "$json"
}
mode_battery() {
    local json=$(get_health)
    local val=$(echo "$json" | grep -o '"battery:level"[^}]*' | grep -o '"observedValue":[0-9]*' | cut -d: -f2)
    [ -z "$val" ] && val=-1
    if [ "$val" -le 10 ] 2>/dev/null; then echo "CRITICAL - Battery: ${val}% | battery=${val}%;20;10;0;100"; exit $STATE_CRITICAL
    elif [ "$val" -le 20 ] 2>/dev/null; then echo "WARNING - Battery: ${val}% | battery=${val}%;20;10;0;100"; exit $STATE_WARNING
    else echo "OK - Battery: ${val}% | battery=${val}%;20;10;0;100"; exit $STATE_OK; fi
}
mode_charging() {
    local json=$(get_health)
    local val=$(echo "$json" | grep -o '"battery:charging"[^}]*' | grep -o '"observedValue":[0-9]*' | cut -d: -f2)
    [ -z "$val" ] && val=-1
    if [ "$val" = "1" ]; then echo "OK - Phone is charging | charging=1;;;0;1"; exit $STATE_OK
    else echo "CRITICAL - Phone is NOT charging | charging=0;;;0;1"; exit $STATE_CRITICAL; fi
}
mode_cellular() {
    local json=$(get_health)
    local val=$(echo "$json" | grep -o '"connection:cellular"[^}]*' | grep -o '"observedValue":[0-9]*' | cut -d: -f2)
    [ -z "$val" ] && val=-1
    local labels=""; case "$val" in 0) labels="None";; 1) labels="Unknown";; 2) labels="2G";; 3) labels="3G";; 4) labels="4G";; 5) labels="5G";; *) labels="N/A";; esac
    if [ "$val" -le 3 ] 2>/dev/null; then echo "CRITICAL - Cellular: ${labels} (≤3G) | cellular=${val};;;0;5"; exit $STATE_CRITICAL
    elif [ "$val" -eq 4 ] 2>/dev/null; then echo "WARNING - Cellular: ${labels} (4G) | cellular=${val};;;0;5"; exit $STATE_WARNING
    elif [ "$val" -eq 5 ] 2>/dev/null; then echo "OK - Cellular: ${labels} (5G) | cellular=${val};;;0;5"; exit $STATE_OK
    else echo "CRITICAL - Cellular: ${labels} | cellular=${val};;;0;5"; exit $STATE_CRITICAL; fi
}
mode_internet() {
    local json=$(get_health)
    local val=$(echo "$json" | grep -o '"connection:status"[^}]*' | grep -o '"observedValue":[0-9]*' | cut -d: -f2)
    if [ "$val" = "1" ]; then echo "OK - Internet connected | internet=1;;;0;1"; exit $STATE_OK
    else echo "CRITICAL - No internet | internet=0;;;0;1"; exit $STATE_CRITICAL; fi
}
mode_failed1h() {
    local json=$(get_health)
    local val=$(echo "$json" | grep -o '"messages:failed"[^}]*' | grep -o '"observedValue":[0-9]*' | cut -d: -f2)
    [ -z "$val" ] && val=0
    if [ "$val" -ge 5 ] 2>/dev/null; then echo "CRITICAL - Failed(1h): ${val} | failed_1h=${val};1;5;0;"; exit $STATE_CRITICAL
    elif [ "$val" -ge 1 ] 2>/dev/null; then echo "WARNING - Failed(1h): ${val} | failed_1h=${val};1;5;0;"; exit $STATE_WARNING
    else echo "OK - Failed(1h): ${val} | failed_1h=${val};1;5;0;"; exit $STATE_OK; fi
}
mode_messages() {
    local json=$(curl -s --noproxy '*' --connect-timeout 5 --max-time 10 -u "$AUTH" "http://${HOST}:${PORT}/message")
    [ $? -ne 0 ] && { echo "CRITICAL - Cannot connect"; exit $STATE_CRITICAL; }
    local stats=$(echo "$json" | python -c "
import json, sys
try:
    msgs = json.load(sys.stdin)
except:
    print 'PARSE_ERROR'
    sys.exit(1)
total=len(msgs);d=f=p=s=pr=0
for m in msgs:
    st=m.get('state','')
    if st=='Delivered': d+=1
    elif st=='Failed': f+=1
    elif st=='Pending': p+=1
    elif st=='Sent': s+=1
    elif st=='Processed': pr+=1
print '%d|%d|%d|%d|%d|%d' % (total,d,f,p,s,pr)
" 2>&1)
    [ "$stats" = "PARSE_ERROR" ] && { echo "CRITICAL - Parse error"; exit $STATE_CRITICAL; }
    IFS='|' read total delivered failed pending sent processed <<< "$stats"
    : ${total:=0} ${delivered:=0} ${failed:=0} ${pending:=0} ${sent:=0} ${processed:=0}
    if [ "$failed" -ge 5 ] 2>/dev/null; then echo "CRITICAL - ${total} msgs, ${delivered} ok, ${failed} failed | total=${total};;;0; delivered=${delivered};;;0; failed=${failed};1;5;0; pending=${pending};;;0; sent=${sent};;;0; processed=${processed};;;0;"; exit $STATE_CRITICAL
    elif [ "$failed" -ge 1 ] 2>/dev/null; then echo "WARNING - ${total} msgs, ${delivered} ok, ${failed} failed | total=${total};;;0; delivered=${delivered};;;0; failed=${failed};1;5;0; pending=${pending};;;0; sent=${sent};;;0; processed=${processed};;;0;"; exit $STATE_WARNING
    else echo "OK - ${total} msgs, ${delivered} ok, ${failed} failed | total=${total};;;0; delivered=${delivered};;;0; failed=${failed};1;5;0; pending=${pending};;;0; sent=${sent};;;0; processed=${processed};;;0;"; exit $STATE_OK; fi
}
case "$MODE" in
    battery)  mode_battery ;;
    charging) mode_charging ;;
    cellular) mode_cellular ;;
    internet) mode_internet ;;
    failed1h) mode_failed1h ;;
    messages) mode_messages ;;
    *) echo "UNKNOWN - Mode: $MODE"; exit $STATE_UNKNOWN ;;
esac
SCRIPT
chmod +x /usr/lib64/nagios/plugins/check_android_gateway


# ============================================================
# 1 - TEST des commandes (vérifier les valeurs)
# ============================================================
/usr/lib64/nagios/plugins/check_android_gateway --mode=battery -H=192.168.2.209 -p=8080 -a=sms:xxhtphtpxx
/usr/lib64/nagios/plugins/check_android_gateway --mode=charging -H=192.168.2.209 -p=8080 -a=sms:xxhtphtpxx
/usr/lib64/nagios/plugins/check_android_gateway --mode=cellular -H=192.168.2.209 -p=8080 -a=sms:xxhtphtpxx
/usr/lib64/nagios/plugins/check_android_gateway --mode=internet -H=192.168.2.209 -p=8080 -a=sms:xxhtphtpxx
/usr/lib64/nagios/plugins/check_android_gateway --mode=failed1h -H=192.168.2.209 -p=8080 -a=sms:xxhtphtpxx
/usr/lib64/nagios/plugins/check_android_gateway --mode=messages -H=192.168.2.209 -p=8080 -a=sms:xxhtphtpxx


# ============================================================
# 2 - CRÉATION des commandes
# ============================================================
# Commande battery
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o CMD -a ADD -v 'check_android_battery;check;/usr/lib64/nagios/plugins/check_android_gateway --mode=battery -H=$HOSTADDRESS$ -p=$_HOSTPORT$ -a=$_HOSTAPIAUTH$'

# Commande charging
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o CMD -a ADD -v 'check_android_charging;check;/usr/lib64/nagios/plugins/check_android_gateway --mode=charging -H=$HOSTADDRESS$ -p=$_HOSTPORT$ -a=$_HOSTAPIAUTH$'

# Commande cellular
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o CMD -a ADD -v 'check_android_cellular;check;/usr/lib64/nagios/plugins/check_android_gateway --mode=cellular -H=$HOSTADDRESS$ -p=$_HOSTPORT$ -a=$_HOSTAPIAUTH$'

# Commande internet
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o CMD -a ADD -v 'check_android_internet;check;/usr/lib64/nagios/plugins/check_android_gateway --mode=internet -H=$HOSTADDRESS$ -p=$_HOSTPORT$ -a=$_HOSTAPIAUTH$'

# Commande failed1h
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o CMD -a ADD -v 'check_android_failed1h;check;/usr/lib64/nagios/plugins/check_android_gateway --mode=failed1h -H=$HOSTADDRESS$ -p=$_HOSTPORT$ -a=$_HOSTAPIAUTH$'

# Commande messages
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o CMD -a ADD -v 'check_android_messages;check;/usr/lib64/nagios/plugins/check_android_gateway --mode=messages -H=$HOSTADDRESS$ -p=$_HOSTPORT$ -a=$_HOSTAPIAUTH$'


# ============================================================
# 3 - CRÉATION des service templates (6 services)
# ============================================================

# --- stpl-android-battery ---
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a add -v "stpl-android-battery;android-battery;generic-active-service"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-battery;check_command;check_android_battery"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-battery;check_period;24x7"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-battery;notification_period;24x7"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-battery;service_max_check_attempts;3"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-battery;service_normal_check_interval;5"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-battery;service_retry_check_interval;1"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-battery;graphtemplate;Default_Graph"

# --- stpl-android-charging ---
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a add -v "stpl-android-charging;android-charging;generic-active-service"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-charging;check_command;check_android_charging"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-charging;check_period;24x7"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-charging;notification_period;24x7"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-charging;service_max_check_attempts;3"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-charging;service_normal_check_interval;5"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-charging;service_retry_check_interval;1"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-charging;graphtemplate;Default_Graph"

# --- stpl-android-cellular ---
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a add -v "stpl-android-cellular;android-cellular;generic-active-service"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-cellular;check_command;check_android_cellular"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-cellular;check_period;24x7"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-cellular;notification_period;24x7"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-cellular;service_max_check_attempts;3"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-cellular;service_normal_check_interval;5"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-cellular;service_retry_check_interval;1"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-cellular;graphtemplate;Default_Graph"

# --- stpl-android-internet ---
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a add -v "stpl-android-internet;android-internet;generic-active-service"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-internet;check_command;check_android_internet"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-internet;check_period;24x7"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-internet;notification_period;24x7"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-internet;service_max_check_attempts;3"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-internet;service_normal_check_interval;5"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-internet;service_retry_check_interval;1"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-internet;graphtemplate;Default_Graph"

# --- stpl-android-failed1h ---
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a add -v "stpl-android-failed1h;android-failed1h;generic-active-service"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-failed1h;check_command;check_android_failed1h"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-failed1h;check_period;24x7"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-failed1h;notification_period;24x7"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-failed1h;service_max_check_attempts;3"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-failed1h;service_normal_check_interval;5"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-failed1h;service_retry_check_interval;1"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-failed1h;graphtemplate;Default_Graph"

# --- stpl-android-sms-stats ---
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a add -v "stpl-android-sms-stats;android-sms-stats;generic-active-service"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-sms-stats;check_command;check_android_messages"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-sms-stats;check_period;24x7"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-sms-stats;notification_period;24x7"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-sms-stats;service_max_check_attempts;3"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-sms-stats;service_normal_check_interval;5"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-sms-stats;service_retry_check_interval;1"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a setparam -v "stpl-android-sms-stats;graphtemplate;Default_Graph"


# ============================================================
# 4 - CRÉATION du host template
# ============================================================
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o HTPL -a add -v "htpl-android-smsgateway;htpl-android-smsgateway;;;;"

/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o HTPL -a setparam -v "htpl-android-smsgateway;check_period;24x7"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o HTPL -a setparam -v "htpl-android-smsgateway;notification_period;24x7"

/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o HTPL -a setmacro -v "htpl-android-smsgateway;PORT;8080;;"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o HTPL -a setmacro -v "htpl-android-smsgateway;APIAUTH;sms:xxhtphtpxx;;"


# ============================================================
# 5 - ASSOCIATION services -> host template
# ============================================================
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a addhost -v "stpl-android-battery;htpl-android-smsgateway"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a addhost -v "stpl-android-charging;htpl-android-smsgateway"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a addhost -v "stpl-android-cellular;htpl-android-smsgateway"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a addhost -v "stpl-android-internet;htpl-android-smsgateway"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a addhost -v "stpl-android-failed1h;htpl-android-smsgateway"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a addhost -v "stpl-android-sms-stats;htpl-android-smsgateway"


# ============================================================
# 6 - CRÉATION du host
# ============================================================
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o HOST -a add -v "htp86_ANDROID_SMSGATEWAY;Asus Zenfone Max Pro M2 (SMS Gateway);192.168.2.209;generic-active-host;Central;htp86"

/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o HOST -a addtemplate -v "htp86_ANDROID_SMSGATEWAY;htpl-android-smsgateway"

/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o HOST -a setparam -v "htp86_ANDROID_SMSGATEWAY;check_period;24x7"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o HOST -a setparam -v "htp86_ANDROID_SMSGATEWAY;notification_period;24x7"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o HOST -a setparam -v "htp86_ANDROID_SMSGATEWAY;timezone;Europe/Paris"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o HOST -a setparam -v "htp86_ANDROID_SMSGATEWAY;icon_image;hardware/modem4.png"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o HOST -a setparam -v "htp86_ANDROID_SMSGATEWAY;action_url;http://192.168.2.209:8080/health"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o HOST -a setparam -v "htp86_ANDROID_SMSGATEWAY;host_comment;Android SMS Gateway - capcom6/android-sms-gateway v1.63.0 - Asus Zenfone Max Pro M2 - IP 192.168.2.209:8080 - SIM slot 2"

/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o HOST -a setmacro -v "htp86_ANDROID_SMSGATEWAY;PORT;8080;;"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o HOST -a setmacro -v "htp86_ANDROID_SMSGATEWAY;APIAUTH;sms:xxhtphtpxx;;"


# ============================================================
# 7 - APPLICATION et DÉPLOIEMENT
# ============================================================
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o HOST -a applytpl -v "htp86_ANDROID_SMSGATEWAY"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -a APPLYCFG -v Central


# ============================================================
# Force les checks immédiats (optionnel)
# ============================================================
echo "[$(date +%s)] SCHEDULE_FORCED_SVC_CHECK;htp86_ANDROID_SMSGATEWAY;android-battery;$(date +%s)" >> /var/lib/centreon-engine/rw/centengine.cmd
echo "[$(date +%s)] SCHEDULE_FORCED_SVC_CHECK;htp86_ANDROID_SMSGATEWAY;android-charging;$(date +%s)" >> /var/lib/centreon-engine/rw/centengine.cmd
echo "[$(date +%s)] SCHEDULE_FORCED_SVC_CHECK;htp86_ANDROID_SMSGATEWAY;android-cellular;$(date +%s)" >> /var/lib/centreon-engine/rw/centengine.cmd
echo "[$(date +%s)] SCHEDULE_FORCED_SVC_CHECK;htp86_ANDROID_SMSGATEWAY;android-internet;$(date +%s)" >> /var/lib/centreon-engine/rw/centengine.cmd
echo "[$(date +%s)] SCHEDULE_FORCED_SVC_CHECK;htp86_ANDROID_SMSGATEWAY;android-failed1h;$(date +%s)" >> /var/lib/centreon-engine/rw/centengine.cmd
echo "[$(date +%s)] SCHEDULE_FORCED_SVC_CHECK;htp86_ANDROID_SMSGATEWAY;android-sms-stats;$(date +%s)" >> /var/lib/centreon-engine/rw/centengine.cmd


# ============================================================
# 8 - TABLEAU EXHAUSTIF des 6 services
# ============================================================
┌─────────────────────┬──────────┬──────────────────────┬───────────┬──────────────┬──────────────────────────────────┐
│ Service             │ Mode     │ Métrique             │ Seuil Warn│ Seuil Crit   │ Condition d'alerte               │
├─────────────────────┼──────────┼──────────────────────┼───────────┼──────────────┼──────────────────────────────────┤
│ android-battery     │ battery  │ battery=0-100 (%)    │ ≤ 20      │ ≤ 10         │ Batterie faible                  │
│ android-charging    │ charging │ charging=0 ou 1      │ -         │ 0            │ CRITICAL si téléphone débranché  │
│ android-cellular    │ cellular │ cellular=0 à 5       │ 4 (4G)    │ ≤3 (≤3G)     │ 5G=OK, 4G=WARN, ≤3G=CRITICAL    │
│ android-internet    │ internet │ internet=0 ou 1      │ -         │ 0            │ CRITICAL si pas d'accès internet │
│ android-failed1h    │ failed1h │ failed_1h=0-N        │ ≥ 1       │ ≥ 5          │ Échecs d'envoi dernière heure    │
│ android-sms-stats   │ messages │ total/delivered/     │ failed≥1  │ failed≥5     │ Échecs historiques cumulés       │
│                     │          │ failed/pending/sent/ │           │              │                                  │
│                     │          │ processed            │           │              │                                  │
└─────────────────────┴──────────┴──────────────────────┴───────────┴──────────────┴──────────────────────────────────┘

Note: cellular valeurs → 0=None 1=Unknown 2=2G 3=3G 4=4G 5=5G


# ============================================================
# 9 - SUPPRESSION (si besoin - tout supprimer)
# ============================================================
# Désassocier les services du host template
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a delhost -v "stpl-android-battery;htpl-android-smsgateway"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a delhost -v "stpl-android-charging;htpl-android-smsgateway"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a delhost -v "stpl-android-cellular;htpl-android-smsgateway"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a delhost -v "stpl-android-internet;htpl-android-smsgateway"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a delhost -v "stpl-android-failed1h;htpl-android-smsgateway"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a delhost -v "stpl-android-sms-stats;htpl-android-smsgateway"

# Supprimer le host
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o HOST -a del -v "htp86_ANDROID_SMSGATEWAY"

# Supprimer le host template
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o HTPL -a del -v "htpl-android-smsgateway"

# Supprimer les service templates
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a del -v "stpl-android-battery"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a del -v "stpl-android-charging"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a del -v "stpl-android-cellular"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a del -v "stpl-android-internet"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a del -v "stpl-android-failed1h"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o STPL -a del -v "stpl-android-sms-stats"

# Supprimer les commandes
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o CMD -a del -v "check_android_battery"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o CMD -a del -v "check_android_charging"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o CMD -a del -v "check_android_cellular"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o CMD -a del -v "check_android_internet"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o CMD -a del -v "check_android_failed1h"
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -o CMD -a del -v "check_android_messages"

# Redéployer après suppression
/usr/share/centreon/bin/centreon -u admin -p xxhtp@1998HTPXX -a APPLYCFG -v Central
