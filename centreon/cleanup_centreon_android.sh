#!/bin/bash
#========================================================================
# cleanup_centreon_android.sh
# Description: Removes all Centreon objects created for Android SMS
#              Gateway monitoring. Reverse of setup_centreon_android.sh.
#
# Usage:
#   ./cleanup_centreon_android.sh                # Interactive
#   ./cleanup_centreon_android.sh --force         # Skip confirmations
#   ./cleanup_centreon_android.sh --dry-run       # Show commands only
#========================================================================

# ============================================================
# CONFIGURATION - Must match setup script
# ============================================================
CENTREON_USER="admin"
CENTREON_PASS="xxhtp@1998HTPXX"
CENTREON_POLLER="Central"
ANDROID_HOST_NAME="htp86_ANDROID_SMSGATEWAY"
CENTREON_BIN="/usr/share/centreon/bin/centreon"

# ============================================================
# FUNCTIONS
# ============================================================
DRY_RUN=false
FORCE=false

for arg in "$@"; do
    case "$arg" in
        --dry-run) DRY_RUN=true ;;
        --force) FORCE=true ;;
    esac
done

if $DRY_RUN; then
    echo "=== DRY RUN MODE - Commands will be displayed only ==="
fi

if ! $FORCE && ! $DRY_RUN; then
    echo "WARNING: This will delete the following from Centreon:"
    echo "  - Host:        ${ANDROID_HOST_NAME}"
    echo "  - Host template: htpl-android-smsgateway"
    echo "  - Service templates: stpl-android-gateway-health, stpl-android-sms-stats"
    echo "  - Commands:    check_android_health, check_android_messages"
    echo ""
    read -p "Are you sure? (yes/NO): " CONFIRM
    if [ "$CONFIRM" != "yes" ]; then
        echo "Cancelled."
        exit 1
    fi
fi

centreon_cmd() {
    local desc="$1"
    shift
    if $DRY_RUN; then
        echo ""
        echo "# $desc"
        echo "$CENTREON_BIN" "$@"
    else
        echo "# $desc"
        "$CENTREON_BIN" "$@" 2>&1
    fi
}

echo ""
echo "============================================================"
echo "  Cleanup Centreon Android SMS Gateway Monitoring"
echo "============================================================"
echo ""

# ============================================================
# 1. Delete service templates (must remove host associations first)
# ============================================================
echo "Step 1: Remove service templates from host template associations"

centreon_cmd "Remove stpl-android-gateway-health from htpl-android-smsgateway" \
    -u ${CENTREON_USER} -p ${CENTREON_PASS} \
    -o STPL -a delhost \
    -v "stpl-android-gateway-health;htpl-android-smsgateway"

centreon_cmd "Remove stpl-android-sms-stats from htpl-android-smsgateway" \
    -u ${CENTREON_USER} -p ${CENTREON_PASS} \
    -o STPL -a delhost \
    -v "stpl-android-sms-stats;htpl-android-smsgateway"

echo ""

# ============================================================
# 2. Remove host template from host
# ============================================================
echo "Step 2: Remove host template from host"

# Note: Centreon applytpl with an empty template list might not exist.
# Instead we'll delete the host directly.
# The host template removal happens via the API differently.
# Let's just delete the host and host template.

# ============================================================
# 3. Delete host
# ============================================================
echo "Step 3: Delete host ${ANDROID_HOST_NAME}"

centreon_cmd "Delete host ${ANDROID_HOST_NAME}" \
    -u ${CENTREON_USER} -p ${CENTREON_PASS} \
    -o HOST -a del \
    -v "${ANDROID_HOST_NAME}"

echo ""

# ============================================================
# 4. Delete host template
# ============================================================
echo "Step 4: Delete host template htpl-android-smsgateway"

centreon_cmd "Delete host template htpl-android-smsgateway" \
    -u ${CENTREON_USER} -p ${CENTREON_PASS} \
    -o HTPL -a del \
    -v "htpl-android-smsgateway"

echo ""

# ============================================================
# 5. Delete service templates
# ============================================================
echo "Step 5: Delete service templates"

centreon_cmd "Delete service template stpl-android-gateway-health" \
    -u ${CENTREON_USER} -p ${CENTREON_PASS} \
    -o STPL -a del \
    -v "stpl-android-gateway-health"

centreon_cmd "Delete service template stpl-android-sms-stats" \
    -u ${CENTREON_USER} -p ${CENTREON_PASS} \
    -o STPL -a del \
    -v "stpl-android-sms-stats"

echo ""

# ============================================================
# 6. Delete commands
# ============================================================
echo "Step 6: Delete commands"

centreon_cmd "Delete command check_android_health" \
    -u ${CENTREON_USER} -p ${CENTREON_PASS} \
    -o CMD -a del \
    -v "check_android_health"

centreon_cmd "Delete command check_android_messages" \
    -u ${CENTREON_USER} -p ${CENTREON_PASS} \
    -o CMD -a del \
    -v "check_android_messages"

echo ""

# ============================================================
# 7. Deploy configuration
# ============================================================
echo "Step 7: Deploy configuration"

centreon_cmd "Deploy configuration to poller ${CENTREON_POLLER}" \
    -u ${CENTREON_USER} -p ${CENTREON_PASS} \
    -a APPLYCFG \
    -v "${CENTREON_POLLER}"

echo ""
echo "============================================================"
echo "  Cleanup complete!"
echo "============================================================"
echo ""

