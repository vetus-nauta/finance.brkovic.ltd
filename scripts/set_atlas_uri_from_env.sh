#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET="${FINDESK_MONGO_URI_FILE:-$ROOT/storage/secrets/mongodb_uri}"
URI="${FINDESK_MONGO_URI:-}"

if [[ -z "$URI" && $# -gt 0 ]]; then
  if [[ -f "$1" ]]; then
    URI="$(<"$1")"
  else
    URI="$1"
  fi
fi

URI="$(printf '%s' "$URI" | tr -d '\r' | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')"

if [[ -z "$URI" ]]; then
  echo "Set FINDESK_MONGO_URI or pass a file/path argument with the MongoDB Atlas URI." >&2
  exit 2
fi

case "$URI" in
  mongodb://*|mongodb+srv://*) ;;
  *)
    echo "Refusing to write: URI must start with mongodb:// or mongodb+srv://." >&2
    exit 2
    ;;
esac

mkdir -p "$(dirname "$TARGET")"
tmp="$(mktemp)"
printf '%s\n' "$URI" > "$tmp"
install -m 600 "$tmp" "$TARGET"
rm -f "$tmp"

echo "MongoDB URI stored at $TARGET"
echo "Secret value was not printed."
npm run check:atlas
