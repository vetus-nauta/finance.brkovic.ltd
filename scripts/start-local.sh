#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PORT="${FINANCE_LOCAL_PORT:-18889}"
HOST="127.0.0.1"
URL="http://${HOST}:${PORT}/reset-local.php"
LOG_DIR="$ROOT/storage/logs"
LOG_FILE="$LOG_DIR/finance-local-${PORT}.log"

mkdir -p "$LOG_DIR"

if ! ss -ltn 2>/dev/null | grep -q "${HOST}:${PORT}"; then
  setsid php -S "${HOST}:${PORT}" -t "$ROOT/public" > "$LOG_FILE" 2>&1 < /dev/null &
  sleep 0.5
fi

if command -v google-chrome >/dev/null 2>&1; then
  exec google-chrome --class=FinDesk --name=FinDesk --app="$URL"
fi

exec xdg-open "$URL"
