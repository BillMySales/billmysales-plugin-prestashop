#!/usr/bin/env bash
#
# BillMySales for PrestaShop: end-to-end tests (make e2e).
#
# Clones the PrestaShop Docker stack into var/e2e/stack, starts it with the
# module loaded, and runs each test case against a local webhook receiver
# (tests/e2e/receiver.php, standing in for BillMySales): the case does
# something in PrestaShop (through the real storefront checkout, the back
# office, or the module's own cron controller), the module sends the order,
# and tests/e2e/check.php checks what arrived (signature, headers, payload,
# the order's JSON Schema). Two phases: the module mounted from plugin/
# (cases 1-10), then the built zip installed through the console (cases
# 11-12). At the end the stack (containers, volumes, clone) and the
# receiver are removed; the results stay in var/e2e until the next run or
# make clean: webhooks/<case>-<time>.body (raw body) and .json (headers,
# decoded payload, status answered), real deliveries to look at or to
# replay against BillMySales; stack.log. E2E_KEEP=1 also keeps the stack
# running (then make e2e-clean).
#
# tests/e2e/fixtures.php, copied into the "prestashop" container, bootstraps
# PrestaShop itself for the setup steps that need it (creating a product,
# changing an order's state through
# Order::setCurrentState() so actionOrderStatusPostUpdate fires as it would
# from the back office). Everything a real shopper or merchant would do
# (checkout, admin settings, resending an order) goes through plain HTTP
# with curl, cookies and the page's own CSRF tokens, exactly as a browser
# would submit them.
#
# Needs only Docker on the host. The stack's development ports and the
# receiver's (8099) must be free: stop the PrestaShop development stack.
#
# Environment: TOOLS_IMAGE (set by the Makefile), STACK_REPO, STACK_REF
# (default master), E2E_KEEP, E2E_STACK_ENV (extra "NAME=value" lines, one per
# line, appended to the stack's .env: e.g. PS_VERSION, PS_SHA256 and
# PHP_VERSION to test another PrestaShop version), E2E_RECEIVER_PORT (default
# 8099, for when another end-to-end run holds it).

set -euo pipefail

PLATFORM=prestashop
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
E2E="${ROOT}/var/e2e"
STACK="${E2E}/stack"
PROJECT="${PLATFORM}-e2e"
STACK_REPO="${STACK_REPO:-https://github.com/BillMySales/billmysales-docker-${PLATFORM}.git}"
STACK_REF="${STACK_REF:-master}"
TOOLS_IMAGE="${TOOLS_IMAGE:?Run it with make e2e}"
RECEIVER="${PROJECT}-receiver"
RECEIVER_PORT="${E2E_RECEIVER_PORT:-8099}"
RECEIVER_URL="http://host.docker.internal:${RECEIVER_PORT}/"
SECRET="e2e-secret"
# A secret with characters that must survive the settings form and JSON.
ADMIN_SECRET='e2e "secret"\x'
VERSION="$(sed -n "s/^ *\$this->version *= *'\(.*\)';\$/\1/p" "${ROOT}/plugin/billmysales.php")"
ZIP="${ROOT}/dist/billmysales-${PLATFORM}-${VERSION}.zip"
FAILED=0
FROM=0

# --- Helpers -----------------------------------------------------------------

say() { printf '\n==> %s\n' "$*"; }
die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
fail() { printf '    ✘ %s\n' "$*"; FAILED=1; }
pass() { printf '    ✔ %s\n' "$*"; }
expect() { if [ "$1" = "$2" ]; then pass "$3"; else fail "$3 (got \"$1\", expected \"$2\")"; fi; }

compose() { (cd "${STACK}" && docker compose "$@"); }
# The stack's console service (bin/console), e.g. module install/uninstall.
console() { compose run --rm console "$@" 2>> "${E2E}/stack.log"; }
# tests/e2e/fixtures.php inside the "prestashop" container (copy_fixtures()):
# product creation, an order's state, reading/writing Configuration values.
# Vendor code loaded along with PrestaShop's bootstrap can print PHP
# deprecation notices to stdout ahead of the command's own output: only the
# last line is the result a caller should ever read.
fx() { compose exec -T prestashop php e2e-fixtures.php "$@" 2>> "${E2E}/stack.log" | tail -1; }
cron() { curl -fsS "${PS_URL}/index.php?fc=module&module=billmysales&controller=cron&token=${CRON_TOKEN}"; }
received() { find "${E2E}/webhooks" -name '*.json' | wc -l | tr -d ' '; }
respond() { echo "$1" > "${E2E}/respond"; }
port_in_use() { (exec 3<> "/dev/tcp/127.0.0.1/$1") 2> /dev/null; }
env_value() { { printf '%s\n' "${E2E_STACK_ENV:-}"; cat "${STACK}/.env.dev.example"; } | sed -n "s/^$1=//p" | head -1; }

# Starts a test case: what the receiver got before it isn't checked.
case_start() { printf '\n[%s] %s\n' "$1" "$2"; printf '%02d' "$1" > "${E2E}/case"; FROM="$(received)"; }

# Checks the requests received since case_start (tests/e2e/check.php).
check() {
    docker run --rm -u "$(id -u):$(id -g)" -e HOME=/tmp -v "${ROOT}:/app" -w /app "${TOOLS_IMAGE}" \
        php tests/e2e/check.php var/e2e/webhooks "${FROM}" "$1" || FAILED=1
}

# Expectations of a delivered order (check.php's JSON).
order_expectations() { # <order id> <order state id> [extra JSON members]
    printf '{"count": 1, "secret": "%s", "platform": "%s", "plugin_version": "%s", "source": "%s", "event": "%s", "schema": "tests/e2e/order-schema.json", "order_id": %s, "status": %s%s}' \
        "${SECRET}" "${PLATFORM}" "${VERSION}" "${PS_URL}" "${EVENT:-order.status_changed}" "$1" "$2" "${3:+, $3}"
}

# Copies tests/e2e/fixtures.php into the "prestashop" container.
copy_fixtures() { compose cp "${ROOT}/tests/e2e/fixtures.php" prestashop:/var/www/html/e2e-fixtures.php; }

# Sets a Configuration value through fixtures.php (fx set-config), so the
# module's own Settings/CheckoutFields shapes stay the single source of
# truth for what gets stored.
set_config() { fx set-config "$1" "$2" > /dev/null; }

# Places an order through the real storefront checkout (guest, offline bank
# transfer): adds the product, creates the guest account, saves the address
# with the custom fields given ("key=value" pairs, e.g. billmysales_rut=...),
# picks the only carrier and pays. Prints the order id, or nothing if the
# address step never reached the delivery step (refused). The pages fetched
# along the way are kept in var/e2e/checkout-*.html.
checkout() { # <jar file> <billing name> [custom field "name=value" args...]
    local jar="$1" name="$2" page token
    local jar_path="${E2E}/${jar}"
    shift 2
    rm -f "${jar_path}"
    # The very first storefront requests after a fresh install (or after
    # the admin-folder fix restarts PHP-FPM, below) can 404 briefly: a
    # route or object cache still warming up in that worker.
    page="$(curl -fsS --retry 20 --retry-delay 3 --retry-all-errors -c "${jar_path}" -b "${jar_path}" -L "${PS_URL}/${PRODUCT_ID}-.html")"
    token="$(printf '%s' "${page}" | grep -o 'name="token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//')"
    curl -fsS -c "${jar_path}" -b "${jar_path}" -o /dev/null -X POST "${PS_URL}/carrito" \
        --data-urlencode "token=${token}" --data-urlencode "id_product=${PRODUCT_ID}" \
        --data-urlencode "id_customization=0" --data-urlencode "qty=1" --data-urlencode "add=1"
    curl -fsS -c "${jar_path}" -b "${jar_path}" -o /dev/null -X POST "${PS_URL}/pedido" \
        --data-urlencode "token=${token}" --data-urlencode "id_gender=1" \
        --data-urlencode "firstname=${name%% *}" --data-urlencode "lastname=${name#* }" \
        --data-urlencode "email=${jar}@example.com" --data-urlencode "password=" --data-urlencode "birthday=" \
        --data-urlencode "customer_privacy=1" --data-urlencode "psgdpr=1" --data-urlencode "submitCreate=1"
    page="$(curl -fsS -c "${jar_path}" -b "${jar_path}" "${PS_URL}/pedido")"
    echo "${page}" > "${E2E}/checkout-address.html"
    token="$(printf '%s' "${page}" | sed -n '/id_address=0/,/<\/form>/p' | grep -o 'name="token"[^>]*value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//')"
    local args=(--data-urlencode "token=${token}" --data-urlencode "firstname=${name%% *}" --data-urlencode "lastname=${name#* }" \
        --data-urlencode "address1=Av. Siempre Viva 123" --data-urlencode "postcode=832-0000" --data-urlencode "city=Santiago" \
        --data-urlencode "id_country=${CHILE_ID}" --data-urlencode "phone=+56911111111" \
        --data-urlencode "saveAddress=delivery" --data-urlencode "use_same_address=1" --data-urlencode "confirm-addresses=1")
    for field in "$@"; do
        args+=(--data-urlencode "billmysales_${field}")
    done
    local status
    status="$(curl -fsS -c "${jar_path}" -b "${jar_path}" -o "${E2E}/checkout-address.html" -w '%{http_code}' \
        -X POST "${PS_URL}/pedido?id_address=0" "${args[@]}")"
    # A rejected address (a required field missing) is re-rendered directly
    # (200); an accepted one redirects to the next step (302).
    if [ "${status}" != 302 ]; then
        return 0
    fi
    page="$(curl -fsS -c "${jar_path}" -b "${jar_path}" "${PS_URL}/pedido")"
    local option delivery_option delivery_value
    option="$(printf '%s' "${page}" | grep -o 'name="delivery_option\[[0-9]*\]" id="[^"]*" value="[^"]*"' | head -1)"
    delivery_option="$(printf '%s' "${option}" | grep -o 'name="delivery_option\[[0-9]*\]"' | sed 's/name="//;s/"$//')"
    delivery_value="$(printf '%s' "${option}" | grep -o 'value="[^"]*"' | sed 's/value="//;s/"$//')"
    curl -fsS -c "${jar_path}" -b "${jar_path}" -o /dev/null -X POST "${PS_URL}/pedido" \
        --data-urlencode "${delivery_option}=${delivery_value}" --data-urlencode "delivery_message=" --data-urlencode "confirmDeliveryOption=1"
    local headers
    headers="$(curl -fsS -c "${jar_path}" -b "${jar_path}" -D - -o /dev/null -X POST "${PS_URL}/module/ps_wirepayment/validation")"
    printf '%s' "${headers}" | sed -n 's/.*id_order=\([0-9]*\).*/\1/p' | tr -d '\r'
}

# Changes an order's state (fx set-order-state): the merchant confirming
# payment, cancelling, etc. Runs the cron controller right after, so a
# notifying state is delivered within the same call.
set_order_state() { # <order id> <order state id>
    fx set-order-state "$1" "$2" > /dev/null
    cron > /dev/null
}

# Logs in to the back office as the development admin (ADMIN_JAR) and reads
# the modules page's link from the dashboard: its _token is the one every
# Symfony back office page takes (an order's page, a module's settings). The
# login form is a Symfony one in PrestaShop 9 and the classic AdminLogin
# controller's in 8.
admin_login() {
    local jar="${E2E}/admin-cookies" login token dashboard
    rm -f "${jar}"
    # A module can lazily create a cache file as root the first time the
    # back office touches it (a stray permission left from the image
    # build): fix ownership before the first admin request, not just once
    # at stack start, since it can appear later too.
    compose exec -T -u root prestashop chown -R www-data:www-data var/cache >> "${E2E}/stack.log" 2>&1 || true
    login="$(curl -fsS --retry 10 --retry-delay 3 --retry-all-errors -c "${jar}" -b "${jar}" -L "${PS_URL_ADMIN}/")"
    if [[ "${login}" != *'id="login_form"'* ]]; then
        token="$(printf '%s' "${login}" | sed -n '/submit_login/,/<\/form>/p' | grep -o 'name="_token"[^>]*value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//')"
        dashboard="$(curl -fsS --retry 10 --retry-delay 3 --retry-all-errors -c "${jar}" -b "${jar}" -L -X POST "${PS_URL_ADMIN}/login?_token=" \
            --data-urlencode "email=$(env_value PS_ADMIN_EMAIL)" --data-urlencode "passwd=$(env_value PS_ADMIN_PASSWORD)" \
            --data-urlencode "submit_login=1" --data-urlencode "_token=${token}")"
    else
        dashboard="$(curl -fsS --retry 10 --retry-delay 3 --retry-all-errors -c "${jar}" -b "${jar}" -L -X POST "${PS_URL_ADMIN}/index.php?controller=AdminLogin" \
            --data-urlencode "email=$(env_value PS_ADMIN_EMAIL)" --data-urlencode "passwd=$(env_value PS_ADMIN_PASSWORD)" \
            --data-urlencode "submitLogin=1" --data-urlencode "controller=AdminLogin" --data-urlencode "redirect=${PS_URL_ADMIN}/")"
    fi
    MODULES_LINK="$(printf '%s' "${dashboard}" | grep -o 'href="[^"]*improve/modules/manage?_token=[^"]*"' | head -1 | sed 's/^href="//;s/"$//' || true)"
    [ -n "${MODULES_LINK}" ] || die "the back office login failed: no link to the modules list on the dashboard"
    case "${MODULES_LINK}" in /*) MODULES_LINK="${PS_URL}${MODULES_LINK}" ;; esac
}

# The back office page of an order: the modules page's link with its route
# replaced (same _token).
order_page_url() { # <order id>
    printf '%s' "${MODULES_LINK}" | sed "s#improve/modules/manage#sell/orders/$1/view#"
}

# GET/POST as the admin (admin_login's cookies).
admin_get() { curl -fsS -c "${E2E}/admin-cookies" -b "${E2E}/admin-cookies" -L "$@"; }
admin_post() { curl -fsS -c "${E2E}/admin-cookies" -b "${E2E}/admin-cookies" -L -X POST "$@"; }

# "compose up -d --wait" can report failure (e.g. it doesn't like the
# one-shot "setup" container exiting, even successfully) although every
# long-running service settles healthy moments later: start it, then poll
# the services that matter ourselves instead of trusting its exit code.
up_and_wait() {
    compose up -d >> "${E2E}/stack.log" 2>&1
    local i=0
    for service in db prestashop caddy; do
        i=0
        while [ "$(compose ps "${service}" --format '{{.Health}}')" != healthy ]; do
            i=$((i + 1))
            [ "${i}" -ge 30 ] && die "${service} never became healthy (var/e2e/stack.log)"
            sleep 2
        done
    done
    # Neither prestashop nor caddy actually depends_on setup completing
    # (only the backup profile does): wait for it directly, since it's the
    # one that installs/configures the app they serve.
    i=0
    while [ "$(compose ps -a setup --format '{{.State}}')" != exited ]; do
        i=$((i + 1))
        [ "${i}" -ge 30 ] && die "setup never finished (var/e2e/stack.log)"
        sleep 2
    done
}

# Writes the stack's .env: the development template, this project name and,
# with "mount", the module override pointing to plugin/.
stack_env() { # mount|zip
    {
        cat "${STACK}/.env.dev.example"
        if [ -n "${E2E_STACK_ENV:-}" ]; then
            printf '%s\n' "${E2E_STACK_ENV}"
        fi
        echo "COMPOSE_PROJECT_NAME=${PROJECT}"
        if [ "$1" = mount ]; then
            echo "COMPOSE_FILE=compose.yaml:overrides/module.yaml"
            echo "MODULE_PATH=${ROOT}/plugin"
            echo "MODULE_NAME=billmysales"
        fi
    } > "${STACK}/.env"
}

# shellcheck disable=SC2329 # called by the EXIT trap
cleanup() {
    local status=$?
    if [ "${E2E_KEEP:-0}" = 1 ]; then
        say "Stack kept running (E2E_KEEP=1), results in var/e2e; remove with: make e2e-clean"
        return
    fi
    say "Removing the stack and the receiver; results kept in var/e2e (webhooks/, stack.log)"
    [ -f "${STACK}/compose.yaml" ] && compose down -v --remove-orphans > /dev/null 2>&1 || true
    docker rm -f "${RECEIVER}" > /dev/null 2>&1 || true
    rm -rf "${STACK}" "${E2E}/case" "${E2E}/respond"
    exit "${status}"
}

# --- Preparation ---------------------------------------------------------------

[ -f "${ZIP}" ] || die "${ZIP} not found (make e2e builds it)"
if docker ps -aq --filter "name=^${RECEIVER}$" | grep . > /dev/null \
    || docker ps -aq --filter "label=com.docker.compose.project=${PROJECT}" | grep . > /dev/null; then
    die "the stack of a previous run is still there: make e2e-clean"
fi
port_in_use "${RECEIVER_PORT}" && die "port ${RECEIVER_PORT} (webhook receiver) is in use"

rm -rf "${E2E}"
mkdir -p "${E2E}/webhooks"
trap cleanup EXIT

say "Cloning ${STACK_REPO} (${STACK_REF})"
git clone -q --depth 1 --branch "${STACK_REF}" "${STACK_REPO}" "${STACK}"
PS_URL="$(env_value PS_URL)"
for port in "$(env_value HTTP_PORT)" "$(env_value HTTPS_PORT)" "$(env_value MAILPIT_PORT)"; do
    port_in_use "${port}" && die "port ${port} is in use: stop the ${PLATFORM} development stack (docker compose down)"
done

say "Starting the webhook receiver (port ${RECEIVER_PORT})"
docker run -d --name "${RECEIVER}" -u "$(id -u):$(id -g)" -p "${RECEIVER_PORT}:${RECEIVER_PORT}" \
    -v "${ROOT}/tests/e2e/receiver.php:/receiver/index.php:ro" -v "${E2E}:/e2e" \
    "${TOOLS_IMAGE}" php -S "0.0.0.0:${RECEIVER_PORT}" -t /receiver > /dev/null

say "Starting the stack, module mounted from plugin/ (log: var/e2e/stack.log)"
stack_env mount
up_and_wait
# A fresh install leaves a few var/cache files root-owned (a stray leftover
# from the image build): www-data can't rewrite or delete them, which
# crashes the installer's own post-install cache clear (aborting setup.sh
# before it renames the installer-randomized admin folder to
# PS_FOLDER_ADMIN) and, later, any request touching them (e.g. ps_accounts'
# own cache file, hit from the back office). Fix ownership outright.
compose exec -T -u root prestashop chown -R www-data:www-data var/cache >> "${E2E}/stack.log" 2>&1 || true
# setup is safe to repeat (skips reinstalling once app/config/parameters.php
# exists) and completes the folder rename on a second try.
if ! compose exec -T prestashop sh -c "[ -d $(env_value PS_FOLDER_ADMIN) ]" 2>/dev/null; then
    i=0
    while ! compose exec -T prestashop sh -c "[ -d $(env_value PS_FOLDER_ADMIN) ]" 2>/dev/null; do
        i=$((i + 1))
        [ "${i}" -ge 5 ] && die "setup never got past the post-install cache clear (var/e2e/stack.log)"
        compose run --rm setup >> "${E2E}/stack.log" 2>&1 || true
    done
    # Each already-running PHP-FPM worker keeps the pre-clear routes/
    # containers compiled in its own opcache: restart so every worker
    # starts fresh, or requests flap between a stale worker (404) and a
    # recompiled one (200) until they happen to recycle on their own.
    # (Not "compose up -d --wait": that runs setup again, which manages to
    # fail differently on a container it didn't expect to already be set up.)
    compose restart prestashop >> "${E2E}/stack.log" 2>&1
    j=0
    while [ "$(compose ps prestashop --format '{{.Health}}')" != healthy ]; do
        j=$((j + 1))
        [ "${j}" -ge 15 ] && die "prestashop never became healthy again after restart (var/e2e/stack.log)"
        sleep 2
    done
fi
PS_URL_ADMIN="${PS_URL}/$(env_value PS_FOLDER_ADMIN)"
copy_fixtures
console prestashop:module install billmysales > /dev/null

# The country id (Chile) and the cron token are read once.
CHILE_ID="$(fx country-id CL | tr -d '\r\n')"
CRON_TOKEN="$(fx get-config BILLMYSALES_CRON_TOKEN | tr -d '\r\n')"

PRODUCT_ID="$(fx create-product 'Polera E2E' 9990 | tr -d '\r\n')"

set_config BILLMYSALES_WEBHOOK "${RECEIVER_URL}"
set_config BILLMYSALES_TOKEN "${SECRET}"
set_config BILLMYSALES_ACTIVE 1
set_config BILLMYSALES_NOTIFY_STATUSES '["2","3"]'
set_config BILLMYSALES_PAYLOAD_FORMAT legacy
set_config BILLMYSALES_CUSTOM_FIELDS '[{"key":"rut","label":"RUT","values":[],"required":true},{"key":"documento","label":"Documento","values":["Boleta","Factura"],"required":false}]'
FIELDS_META='{"rut": "11.111.111-1", "documento": "Factura"}'

# --- Cases: module mounted -------------------------------------------------------

case_start 1 "Order through the checkout, with the custom fields (awaiting payment: not sent yet)"
ORDER="$(checkout jar1 "Ana Perez" "rut=11.111.111-1" "documento=Factura")"
[ -n "${ORDER}" ] || fail "checkout refused: $(tail -c 300 "${E2E}/checkout-address.html")"
check '{"count": 0}'

case_start 2 "Order marked as paid (Pago aceptado)"
set_order_state "${ORDER}" 2
check "$(order_expectations "${ORDER}" 2 "\"meta\": ${FIELDS_META}")"

case_start 3 "Status changed to one not selected (Cancelado)"
set_order_state "${ORDER}" 6
check '{"count": 0}'

case_start 4 "Checkout without the required field (RUT)"
ORDER4="$(checkout jar4 "Marco Diaz" "documento=Boleta")"
expect "${ORDER4}" "" "checkout refused"
if grep -q 'field-billmysales_rut' "${E2E}/checkout-address.html"; then pass "the error names the field"; else fail "no error for billmysales_rut"; fi
check '{"count": 0}'

case_start 5 "Deliveries deactivated in the settings"
set_config BILLMYSALES_ACTIVE 0
ORDER5="$(checkout jar5 "Elena Rios" "rut=33.333.333-3")"
set_order_state "${ORDER5}" 2
check '{"count": 0}'
set_config BILLMYSALES_ACTIVE 1

case_start 6 "BillMySales answers 503: retried with the same delivery"
respond 503
ORDER6="$(checkout jar6 "Pedro Vera" "rut=44.444.444-4")"
set_order_state "${ORDER6}" 2 # first attempt: 503, retry scheduled a minute out
fx force-retry-now "${ORDER6}" > /dev/null
respond 200
cron > /dev/null # the retry is due now: delivered
check "$(order_expectations "${ORDER6}" 2 '"same_delivery": true' | sed 's/"count": 1/"count": 2/')"

case_start 7 "BillMySales answers 401: not retried"
respond 401
ORDER7="$(checkout jar7 "Sofia Nunez" "rut=55.555.555-5")"
set_order_state "${ORDER7}" 2
respond 200
cron > /dev/null
check "$(order_expectations "${ORDER7}" 2)"

case_start 8 "Sent again from the order page (\"Send to BillMySales\")"
admin_login
admin_post "$(order_page_url "${ORDER7}")" \
    --data-urlencode "billmysales_id_order=${ORDER7}" --data-urlencode "submitBillMySalesResend=1" > /dev/null
cron > /dev/null
EVENT=order.resent check "$(EVENT=order.resent order_expectations "${ORDER7}" 2)"

case_start 9 "Payload format: webservice"
set_config BILLMYSALES_PAYLOAD_FORMAT webservice
ORDER9="$(checkout jar9 "Tomas Leon" "rut=66.666.666-6")"
set_order_state "${ORDER9}" 2
check '{"count": 1, "secret": "'"${SECRET}"'", "webservice": true, "schema": "tests/e2e/order-schema-webservice.json", "order_id": '"${ORDER9}"', "status": 2, "meta": {"rut": "66.666.666-6"}}'
set_config BILLMYSALES_PAYLOAD_FORMAT legacy

case_start 10 "Settings saved through the admin form"
[ -n "${MODULES_LINK:-}" ] || fail "no link to the modules list on the dashboard"
CONFIGURE_URL="$(admin_get "${MODULES_LINK}" | grep -o 'href="[^"]*action/configure/billmysales?_token=[^"]*"' | head -1 | sed 's/^href="//;s/"$//;s/&amp;/\&/g')"
[ -n "${CONFIGURE_URL:-}" ] || fail "no link to the module's configure page"
case "${CONFIGURE_URL}" in /*) CONFIGURE_URL="${PS_URL}${CONFIGURE_URL}" ;; esac
# The <form ...> tag (with its action) comes before BILLMYSALES_WEBHOOK in
# the page; reversing the lines turns "nearest form tag before it" into a
# plain forward range (portable: works with BSD and GNU sed alike).
FORM_ACTION="$(admin_get "${CONFIGURE_URL}" | sed '1!G;h;$!d' | sed -n '/BILLMYSALES_WEBHOOK/,/<form/p' | sed '1!G;h;$!d' | grep -o 'action="[^"]*"' | head -1 | sed 's/^action="//;s/"$//;s/&amp;/\&/g')"
case "${FORM_ACTION}" in /*) FORM_ACTION="${PS_URL}${FORM_ACTION}" ;; esac
admin_post "${FORM_ACTION}" \
    --data-urlencode "BILLMYSALES_ACTIVE=1" --data-urlencode "BILLMYSALES_WEBHOOK=${RECEIVER_URL}" \
    --data-urlencode "BILLMYSALES_TOKEN=${ADMIN_SECRET}" --data-urlencode "BILLMYSALES_PAYLOAD_FORMAT=legacy" \
    --data-urlencode "BILLMYSALES_NOTIFY_STATUSES_2=1" --data-urlencode "BILLMYSALES_NOTIFY_STATUSES_3=1" \
    --data-urlencode "submitBillMySalesSettings=1" > /dev/null
SAVED="$(fx get-config BILLMYSALES_TOKEN | tr -d '\r\n')"
expect "${SAVED}" "${ADMIN_SECRET}" "secret kept as typed"
set_config BILLMYSALES_TOKEN "${SECRET}" # restored for the remaining cases

# --- Cases: the built zip ----------------------------------------------------------

say "Installing $(basename "${ZIP}") through the console (no mount)"
console prestashop:module uninstall billmysales > /dev/null
stack_env zip
up_and_wait
TMP_ZIP="/tmp/$(basename "${ZIP}")"
compose cp "${ZIP}" "prestashop:${TMP_ZIP}"
compose exec -T prestashop sh -c "cd modules && unzip -oq ${TMP_ZIP} && rm ${TMP_ZIP}" >> "${E2E}/stack.log" 2>&1
console prestashop:module install billmysales > /dev/null
copy_fixtures
set_config BILLMYSALES_WEBHOOK "${RECEIVER_URL}"
set_config BILLMYSALES_TOKEN "${SECRET}"
set_config BILLMYSALES_ACTIVE 1
set_config BILLMYSALES_NOTIFY_STATUSES '["2","3"]'
set_config BILLMYSALES_CUSTOM_FIELDS '[{"key":"rut","label":"RUT","values":[],"required":true},{"key":"documento","label":"Documento","values":["Boleta","Factura"],"required":false}]'
CRON_TOKEN="$(fx get-config BILLMYSALES_CRON_TOKEN | tr -d '\r\n')"

case_start 11 "Order through the checkout, module installed from the zip"
ORDER11="$(checkout jar11 "Diego Rojas" "rut=77.777.777-7")"
[ -n "${ORDER11}" ] || fail "checkout refused: $(tail -c 300 "${E2E}/checkout-address.html")"
set_order_state "${ORDER11}" 2
check "$(order_expectations "${ORDER11}" 2 "\"meta\": {\"rut\": \"77.777.777-7\"}")"

case_start 12 "Uninstall"
console prestashop:module uninstall billmysales > /dev/null
TABLES="$(fx count-tables | tr -d '\r\n')"
expect "${TABLES}" "0" "the module's tables are gone"
CONFIG_LEFT="$(fx count-config | tr -d '\r\n')"
expect "${CONFIG_LEFT}" "0" "settings removed"

# --- Result ------------------------------------------------------------------------

if [ "${FAILED}" = 0 ]; then
    say "All end-to-end cases passed"
else
    say "Some end-to-end cases FAILED"
fi
exit "${FAILED}"
