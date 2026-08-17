#!/usr/bin/env python3
import argparse
import ftplib
import hashlib
import json
import os
import time
from pathlib import Path


V2_RUNTIME_FILES = [
    ".htaccess",
    "index.php",
    "app.php",
    "api.php",
    "v2.php",
    "v2-api.php",
    "v2-report.php",
    "public/.htaccess",
    "public/index.php",
    "public/app.php",
    "public/api.php",
    "public/manifest.webmanifest",
    "public/service-worker.js",
    "public/v2.php",
    "public/v2-api.php",
    "public/v2-report.php",
    "public/assets/v2/app.css",
    "public/assets/v2/app.js",
    "public/assets/v2/findesk-logo.svg",
    "public/assets/v2/findesk-logo-white.svg",
    "public/assets/v2/findesk-mark.svg",
    "app/auth.php",
    "app/v2/Api.php",
    "app/v2/Database.php",
    "app/v2/InternetReferenceProvider.php",
    "app/v2/LegacyExcelImporter.php",
    "app/v2/Repository.php",
    "app/v2/ReportSpreadsheetExporter.php",
    "app/v2/Support.php",
]

V1_DECOMMISSIONED_FILES = [
    "public/assets/app.css",
    "public/assets/app.js",
    "public/assets/i18n.js",
    "public/assets/donate.js",
    "public/assets/notifications.js",
    "app/advances.php",
    "app/ai.php",
    "app/business.php",
    "app/data/yacht_price_sources.json",
    "app/data/yacht_provisioning/categories.json",
    "app/data/yacht_provisioning/filters.json",
    "app/data/yacht_provisioning/provision_catalog.json",
    "app/data/yacht_provisioning/request.schema.json",
    "app/data/yacht_provisioning/response.schema.json",
    "app/findesk_phase2.php",
    "app/groups.php",
    "app/ledger.php",
    "app/messages.php",
    "app/on_the_go.php",
    "app/openai_provider.php",
    "app/yacht_prices.php",
    "app/yacht_provisioning.php",
    "deploy/advances_foundation.sql",
    "deploy/business_desk_foundation.sql",
    "deploy/categories_foundation.sql",
    "deploy/create_temp_unread_message.php",
    "deploy/delete_temp_unread_message.php",
    "deploy/findesk_phase2_foundation.sql",
    "deploy/group_access_levels.sql",
    "deploy/group_trash_retention.sql",
    "deploy/group_workspace_type.sql",
    "deploy/groups_foundation.sql",
    "deploy/ledger_foundation.sql",
    "deploy/messages_foundation.sql",
    "deploy/on_the_go_foundation.sql",
    "deploy/on_the_go_sessions_runtime.sql",
    "scripts/findesk_runtime_audit.cjs",
    "scripts/local-smoke.php",
    "scripts/yacht_price_ai_refresh.cjs",
    "scripts/yacht_price_ai_refresh.php",
    "tests/findesk-runtime-audit.spec.js",
]


def sha256_bytes(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def connect() -> ftplib.FTP:
    host = os.environ["FINDESK_FTP_HOST"]
    user = os.environ["FINDESK_FTP_USER"]
    password = os.environ["FINDESK_FTP_PASS"]
    ftp = ftplib.FTP(host, timeout=60)
    ftp.login(user, password)
    ftp.voidcmd("TYPE I")
    return ftp


def remote_root() -> str:
    return os.environ.get("FINDESK_FTP_ROOT", "/finance.brkovic.ltd").rstrip("/")


def remote_path(rel_path: str) -> str:
    return remote_root() + "/" + rel_path.strip("/")


def ensure_remote_dir(ftp: ftplib.FTP, remote_dir: str) -> None:
    current = ""
    for part in remote_dir.strip("/").split("/"):
        if not part:
            continue
        current += "/" + part
        try:
            ftp.mkd(current)
        except ftplib.error_perm as exc:
            if not str(exc).startswith("550"):
                raise


def download_bytes(ftp: ftplib.FTP, remote: str) -> bytes | None:
    chunks: list[bytes] = []
    try:
        ftp.retrbinary(f"RETR {remote}", chunks.append)
    except ftplib.error_perm as exc:
        if str(exc).startswith("550"):
            return None
        raise
    return b"".join(chunks)


def upload_file(ftp: ftplib.FTP, local: Path, remote: str) -> int:
    ensure_remote_dir(ftp, remote.rsplit("/", 1)[0])
    with local.open("rb") as handle:
        ftp.storbinary(f"STOR {remote}", handle)
    return local.stat().st_size


def delete_remote_file(ftp: ftplib.FTP, remote: str) -> bool:
    try:
        ftp.delete(remote)
        return True
    except ftplib.error_perm as exc:
        if str(exc).startswith("550"):
            return False
        raise


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--local-root", default=".")
    parser.add_argument("--stamp", default=time.strftime("%Y%m%d-%H%M%S", time.localtime()))
    parser.add_argument("--dry-run", action="store_true")
    parser.add_argument("--delete-v1-runtime", action="store_true")
    args = parser.parse_args()

    local_root = Path(args.local_root).resolve()
    audit_root = local_root / "storage" / "production-audits"
    backup_dir = audit_root / f"prod-v2-sync-{args.stamp}"
    log_path = audit_root / f"prod-v2-sync-{args.stamp}.ftp.log"
    manifest_path = audit_root / f"prod-v2-sync-{args.stamp}.manifest"
    hash_path = audit_root / f"prod-v2-hash-verify-{args.stamp}.txt"
    audit_root.mkdir(parents=True, exist_ok=True)
    manifest_path.write_text("\n".join(V2_RUNTIME_FILES) + "\n", encoding="utf-8")

    result = {
        "stamp": args.stamp,
        "dry_run": args.dry_run,
        "delete_v1_runtime": args.delete_v1_runtime,
        "remote_root": remote_root(),
        "files": [],
        "deleted_v1_files": [],
    }

    with connect() as ftp, log_path.open("w", encoding="utf-8") as log:
        for rel_path in V2_RUNTIME_FILES:
            local = local_root / rel_path
            if not local.is_file():
                raise FileNotFoundError(str(local))
            remote = remote_path(rel_path)
            before = download_bytes(ftp, remote)
            if before is None:
                log.write(f"BACKUP_MISSING {rel_path}\n")
            else:
                backup = backup_dir / rel_path
                backup.parent.mkdir(parents=True, exist_ok=True)
                backup.write_bytes(before)
                log.write(f"BACKUP_OK {rel_path}\n")
            local_hash = sha256_file(local)
            if args.dry_run:
                remote_after_hash = sha256_bytes(before) if before is not None else None
                uploaded = 0
                log.write(f"DRY_RUN {rel_path}\n")
            else:
                uploaded = upload_file(ftp, local, remote)
                log.write(f"UPLOAD_OK {rel_path}\n")
                after = download_bytes(ftp, remote)
                remote_after_hash = sha256_bytes(after or b"")
                if remote_after_hash != local_hash:
                    raise RuntimeError(f"hash mismatch after upload: {rel_path}")
            result["files"].append({
                "path": rel_path,
                "bytes": local.stat().st_size,
                "uploaded_bytes": uploaded,
                "local_sha256": local_hash,
                "remote_sha256": remote_after_hash,
                "backup": before is not None,
            })

        if args.delete_v1_runtime:
            for rel_path in V1_DECOMMISSIONED_FILES:
                remote = remote_path(rel_path)
                before = download_bytes(ftp, remote)
                if before is None:
                    log.write(f"V1_DELETE_MISSING {rel_path}\n")
                    result["deleted_v1_files"].append({
                        "path": rel_path,
                        "deleted": False,
                        "backup": False,
                    })
                    continue

                backup = backup_dir / rel_path
                backup.parent.mkdir(parents=True, exist_ok=True)
                backup.write_bytes(before)

                if args.dry_run:
                    deleted = False
                    log.write(f"V1_DELETE_DRY_RUN {rel_path}\n")
                else:
                    deleted = delete_remote_file(ftp, remote)
                    log.write(("V1_DELETE_OK " if deleted else "V1_DELETE_MISSING_AFTER_BACKUP ") + rel_path + "\n")

                result["deleted_v1_files"].append({
                    "path": rel_path,
                    "deleted": deleted,
                    "backup": True,
                    "sha256": sha256_bytes(before),
                })

    with hash_path.open("w", encoding="utf-8") as handle:
        for item in result["files"]:
            status = "OK" if item["local_sha256"] == item["remote_sha256"] else "DIFF"
            handle.write(f"{status} {item['path']} local={item['local_sha256']} remote={item['remote_sha256']}\n")

    print(json.dumps({
        "ok": True,
        "stamp": args.stamp,
        "dry_run": args.dry_run,
        "backup_dir": str(backup_dir),
        "log": str(log_path),
        "manifest": str(manifest_path),
        "hash_verify": str(hash_path),
        "files": len(result["files"]),
        "deleted_v1_files": len([item for item in result["deleted_v1_files"] if item.get("deleted")]),
    }, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
