#!/usr/bin/env bash
set -euo pipefail

# Local tenant bootstrap for docker-compose dev.
# Creates tenant databases, registers tenants/domains in control plane,
# and runs tenant migrations.

TENANTS=("acme" "demo")
DOMAINS=()
CREATE_USERS=false
SEED_INITIAL=false
SEED_TEST=false
RUN_SETUP=false
TENANTS_SET=false
DOMAINS_SET=false
TENANT_DB_PASSWORD="${TENANT_DB_PASSWORD:-}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASS="${ADMIN_PASS:-Admin.3000}"
COMPANY_RUC="${COMPANY_RUC:-20123456789}"
COMPANY_NAME="${COMPANY_NAME:-Empresa Demo}"
COMPANY_TRADE="${COMPANY_TRADE:-Demo}"
COMPANY_ADDRESS="${COMPANY_ADDRESS:-Av. Principal 123}"
COMPANY_PHONE="${COMPANY_PHONE:-999999999}"
BRANCH_NAME="${BRANCH_NAME:-Principal}"
BRANCH_ADDRESS="${BRANCH_ADDRESS:-Av. Principal 123}"
BRANCH_PHONE="${BRANCH_PHONE:-999999999}"

DOCKER_COMPOSE="${DOCKER_COMPOSE:-docker compose}"
DB_CONTAINER="${DB_CONTAINER:-database}"
APP_CONTAINER="${APP_CONTAINER:-web}"
DB_ROOT_USER="${DB_ROOT_USER:-root}"
DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-root}"
CONTROL_PLANE_DB="${CONTROL_PLANE_DB:-efact}"

APP_PHP="${APP_PHP:-php}"

usage() {
  cat <<'EOF'
Usage: scripts/local-tenant-setup.sh [options]

Options:
  --tenants "a,b,c"        Comma-separated tenant names (default: acme,demo)
  --domains "a.local,b.local"  Comma-separated domains (default: acme.localhost,demo.localhost)
  --create-users           Create DB users per tenant (user/password = tenant name)
  --seed-initial           Run app:seed:initial-data per tenant
  --seed-test              Run app:seed:test-data per tenant (dev only)
  -h, --help               Show this help

Env overrides:
  DB_CONTAINER, DB_ROOT_USER, DB_ROOT_PASSWORD, CONTROL_PLANE_DB, APP_PHP
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --tenants)
      IFS=',' read -r -a TENANTS <<< "${2:-}"
      TENANTS_SET=true
      shift 2
      ;;
    --domains)
      IFS=',' read -r -a DOMAINS <<< "${2:-}"
      DOMAINS_SET=true
      shift 2
      ;;
    --create-users)
      CREATE_USERS=true
      shift
      ;;
    --seed-initial)
      SEED_INITIAL=true
      shift
      ;;
    --seed-test)
      SEED_TEST=true
      shift
      ;;
    --setup)
      RUN_SETUP=true
      shift
      ;;
    --admin-user)
      ADMIN_USER="${2:-admin}"
      shift 2
      ;;
    --admin-pass)
      ADMIN_PASS="${2:-Admin.3000}"
      shift 2
      ;;
    --company-ruc)
      COMPANY_RUC="${2:-20123456789}"
      shift 2
      ;;
    --company-name)
      COMPANY_NAME="${2:-Empresa Demo}"
      shift 2
      ;;
    --company-trade)
      COMPANY_TRADE="${2:-Demo}"
      shift 2
      ;;
    --company-address)
      COMPANY_ADDRESS="${2:-Av. Principal 123}"
      shift 2
      ;;
    --company-phone)
      COMPANY_PHONE="${2:-999999999}"
      shift 2
      ;;
    --branch-name)
      BRANCH_NAME="${2:-Principal}"
      shift 2
      ;;
    --branch-address)
      BRANCH_ADDRESS="${2:-Av. Principal 123}"
      shift 2
      ;;
    --branch-phone)
      BRANCH_PHONE="${2:-999999999}"
      shift 2
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown option: $1"
      usage
      exit 1
      ;;
  esac
done

if [[ -t 0 ]]; then
  if [[ "${TENANTS_SET}" == "false" ]]; then
    read -r -p "Tenants (comma-separated) [acme,demo]: " tenants_input
    if [[ -n "${tenants_input}" ]]; then
      IFS=',' read -r -a TENANTS <<< "${tenants_input}"
    fi
  fi

  if [[ "${DOMAINS_SET}" == "false" ]]; then
    read -r -p "Domains (comma-separated) [auto]: " domains_input
    if [[ -n "${domains_input}" ]]; then
      IFS=',' read -r -a DOMAINS <<< "${domains_input}"
      DOMAINS_SET=true
    fi
  fi

  if [[ "${CREATE_USERS}" == "false" ]]; then
    read -r -p "Create DB user for each tenant? (y/N): " create_users_input
    if [[ "${create_users_input}" =~ ^[Yy]$ ]]; then
      CREATE_USERS=true
    fi
  fi

  if [[ "${CREATE_USERS}" == "true" && -z "${TENANT_DB_PASSWORD}" ]]; then
    read -r -s -p "Tenant DB password (shared) [Admin.3000]: " tenant_pass_input
    echo
    if [[ -n "${tenant_pass_input}" ]]; then
      TENANT_DB_PASSWORD="${tenant_pass_input}"
    else
      TENANT_DB_PASSWORD="Admin.3000"
    fi
  fi

  if [[ "${SEED_INITIAL}" == "false" ]]; then
    read -r -p "Seed initial data? (Y/n): " seed_initial_input
    if [[ "${seed_initial_input}" =~ ^[Nn]$ ]]; then
      SEED_INITIAL=false
    else
      SEED_INITIAL=true
    fi
  fi

  if [[ "${SEED_TEST}" == "false" ]]; then
    read -r -p "Seed test data? (y/N): " seed_test_input
    if [[ "${seed_test_input}" =~ ^[Yy]$ ]]; then
      SEED_TEST=true
    fi
  fi

  if [[ "${RUN_SETUP}" == "false" ]]; then
    read -r -p "Run initial setup (admin/company/branch)? (Y/n): " setup_input
    if [[ "${setup_input}" =~ ^[Nn]$ ]]; then
      RUN_SETUP=false
    else
      RUN_SETUP=true
    fi
  fi
fi

if [[ "${DOMAINS_SET}" == "false" || "${#DOMAINS[@]}" -eq 0 ]]; then
  DOMAINS=()
  for tenant in "${TENANTS[@]}"; do
    DOMAINS+=("${tenant}.localhost")
  done
fi

if [[ "${#TENANTS[@]}" -ne "${#DOMAINS[@]}" ]]; then
  echo "TENANTS and DOMAINS count must match."
  exit 1
fi

run_sql() {
  local sql="$1"
  ${DOCKER_COMPOSE} exec -T "${DB_CONTAINER}" \
    mysql -u"${DB_ROOT_USER}" -p"${DB_ROOT_PASSWORD}" \
    -e "${sql}"
}

run_php() {
  local cmd=("$@")
  ${DOCKER_COMPOSE} exec -T "${APP_CONTAINER}" php "${cmd[@]}"
}

echo "==> Running control plane migrations"
run_php bin/console app:control-plane:migrate

echo "==> Creating tenant databases"
for tenant in "${TENANTS[@]}"; do
  run_sql "CREATE DATABASE IF NOT EXISTS \`${tenant}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
done

if [[ "${CREATE_USERS}" == "true" ]]; then
  echo "==> Creating tenant DB users"
  for tenant in "${TENANTS[@]}"; do
    run_sql "CREATE USER IF NOT EXISTS '${tenant}'@'%' IDENTIFIED BY '${TENANT_DB_PASSWORD}';"
    run_sql "ALTER USER '${tenant}'@'%' IDENTIFIED BY '${TENANT_DB_PASSWORD}';"
    run_sql "GRANT ALL PRIVILEGES ON \`${tenant}\`.* TO '${tenant}'@'%';"
  done
  run_sql "FLUSH PRIVILEGES;"
fi

echo "==> Registering tenants in control plane"
for tenant in "${TENANTS[@]}"; do
  run_sql "USE \`${CONTROL_PLANE_DB}\`; \
    INSERT INTO tenants (name, connection, status, created_at, updated_at) \
    VALUES ('${tenant}', '${tenant}', 'active', NOW(), NOW()) \
    ON DUPLICATE KEY UPDATE connection=VALUES(connection), updated_at=NOW();"
done

echo "==> Registering tenant domains"
for i in "${!TENANTS[@]}"; do
  tenant="${TENANTS[$i]}"
  domain="${DOMAINS[$i]}"
  run_sql "USE \`${CONTROL_PLANE_DB}\`; \
    SET @tenant_id := (SELECT id FROM tenants WHERE name='${tenant}' LIMIT 1); \
    INSERT INTO tenant_domains (tenant_id, domain, is_primary) \
    VALUES (@tenant_id, '${domain}', 1) \
    ON DUPLICATE KEY UPDATE is_primary=1;"
done

for i in "${!TENANTS[@]}"; do
  tenant="${TENANTS[$i]}"
  domain="${DOMAINS[$i]}"
  echo "==> Running tenant migrations for: ${tenant}"
  run_php bin/console app:tenants:migrate --tenant="${tenant}"

  if [[ "${SEED_INITIAL}" == "true" ]]; then
    run_php bin/console app:seed:initial-data --tenant="${tenant}"
  fi

  if [[ "${SEED_TEST}" == "true" ]]; then
    run_php bin/console app:seed:test-data --tenant="${tenant}"
  fi

  if [[ "${RUN_SETUP}" == "true" ]]; then
    run_php bin/console app:tenant:setup --tenant="${tenant}" \
      --admin-user="${ADMIN_USER}" \
      --admin-pass="${ADMIN_PASS}" \
      --company-ruc="${COMPANY_RUC}" \
      --company-name="${COMPANY_NAME}" \
      --company-trade="${COMPANY_TRADE}" \
      --company-address="${COMPANY_ADDRESS}" \
      --company-phone="${COMPANY_PHONE}" \
      --branch-name="${BRANCH_NAME}" \
      --branch-address="${BRANCH_ADDRESS}" \
      --branch-phone="${BRANCH_PHONE}"
  fi
done

cat <<EOF

Done.

Test:
  curl -H "Host: acme.localhost" http://localhost:8080/
  curl -H "Host: demo.localhost" http://localhost:8080/

Optional /etc/hosts entries:
  127.0.0.1 acme.localhost
  127.0.0.1 demo.localhost
EOF
