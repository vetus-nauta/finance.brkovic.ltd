#!/usr/bin/env sh
set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
KEY_DIR="$ROOT_DIR/storage/secrets"
KEY_FILE="$KEY_DIR/openai_api_key"

status() {
    if [ -s "$KEY_FILE" ]; then
        perms=$(ls -l "$KEY_FILE" | awk '{print $1}')
        printf 'OpenAI key file: present (%s)\n' "$perms"
    else
        printf 'OpenAI key file: absent\n'
    fi
}

case "${1:-}" in
    --status)
        status
        exit 0
        ;;
    --remove)
        rm -f "$KEY_FILE"
        printf 'OpenAI key file removed.\n'
        exit 0
        ;;
    --help|-h)
        printf 'Usage: sh scripts/install_openai_key.sh [--status|--remove]\n'
        exit 0
        ;;
esac

printf 'OpenAI API key: ' >&2
old_stty=$(stty -g 2>/dev/null || true)
stty -echo 2>/dev/null || true
IFS= read -r key
if [ -n "$old_stty" ]; then
    stty "$old_stty" 2>/dev/null || true
else
    stty echo 2>/dev/null || true
fi
printf '\n' >&2

key=$(printf '%s' "$key" | tr -d '\r\n')
if [ -z "$key" ]; then
    printf 'Empty key. Nothing changed.\n' >&2
    exit 1
fi

case "$key" in
    sk-*) ;;
    *)
        printf 'Warning: key does not start with sk-. Saving anyway.\n' >&2
        ;;
esac

umask 077
mkdir -p "$KEY_DIR"
printf '%s\n' "$key" > "$KEY_FILE"
chmod 600 "$KEY_FILE"
printf 'OpenAI key installed into storage/secrets/openai_api_key.\n'
