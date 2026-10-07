#!/usr/bin/env bash
# Smoke test přes Herd: přihlásí demo účet a vypíše HTTP kód každé stránky + počet chybových textů v odpovědi.
#   bash .claude/skills/rentflow-browser/smoke.sh <email> <heslo> /cesta1 /cesta2 ...
#   MODE=api … – přihlášení přes POST /api/v1/login (Sanctum token), cesty relativně k /api/v1 (dokud API existuje).
#   BASE=http://rentflow.test (výchozí) – jiný host přes proměnnou BASE.
# Demo účty (seed, heslo password): landlord@rentflow.cz (pronajímatel), manager@rentflow.cz (správce),
# marie@rentflow.cz a tomas@rentflow.cz (nájemníci).
set -u
BASE=${BASE:-http://rentflow.test}
MODE=${MODE:-web}
if [ $# -lt 3 ]; then
  echo "Použití: [MODE=api] $0 <email> <heslo> /cesta [/cesta ...]" >&2
  exit 1
fi
EMAIL=$1; PASS=$2; shift 2

check_body() {
  # $1 = tělo s posledním řádkem "kód cíl"
  local code errors
  code=$(echo "$1" | tail -1)
  errors=$(echo "$1" | sed '$d' | grep -c -i "Whoops\|exception\|Undefined\|Internal Server Error\|SQLSTATE" || true)
  echo "  $2 -> ${code% } (chybové texty: $errors)"
}

if [ "$MODE" = "api" ]; then
  RESP=$(curl -s -H "Accept: application/json" -H "Content-Type: application/json" \
    -d "{\"email\":\"$EMAIL\",\"password\":\"$PASS\"}" "$BASE/api/v1/login")
  TOKEN=$(echo "$RESP" | grep -o '"token":"[^"]*"' | head -1 | sed 's/"token":"//; s/"$//')
  if [ -z "$TOKEN" ]; then
    echo "API login $EMAIL selhal: $(echo "$RESP" | head -c 200)" >&2
    exit 1
  fi
  echo "API login $EMAIL: OK"
  for path in "$@"; do
    BODY=$(curl -s -w "\n%{http_code} %{redirect_url}" -H "Accept: application/json" \
      -H "Authorization: Bearer $TOKEN" "$BASE/api/v1$path")
    check_body "$BODY" "$path"
  done
  curl -s -o /dev/null -X POST -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" "$BASE/api/v1/logout"
  exit 0
fi

JAR=$(mktemp)
trap 'rm -f "$JAR"' EXIT

TOKEN=$(curl -s -c "$JAR" -b "$JAR" "$BASE/login" | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//; s/"$//')
if [ -z "$TOKEN" ]; then
  echo "Nepodařilo se načíst $BASE/login (502? → problém Herdu / Windows; před fází 4 web login neexistuje → MODE=api)." >&2
  exit 1
fi
LOGIN=$(curl -s -o /dev/null -w "%{http_code} -> %{redirect_url}" -c "$JAR" -b "$JAR" \
  --data-urlencode "_token=$TOKEN" --data-urlencode "email=$EMAIL" --data-urlencode "password=$PASS" "$BASE/login")
echo "login $EMAIL: $LOGIN"

for path in "$@"; do
  BODY=$(curl -s -w "\n%{http_code} %{redirect_url}" -c "$JAR" -b "$JAR" "$BASE$path")
  check_body "$BODY" "$path"
done
