#!/usr/bin/env node

const fs = require("node:fs");
const https = require("node:https");
const path = require("node:path");
const { spawnSync } = require("node:child_process");

const root = path.resolve(__dirname, "..");
const projectRef = process.env.SUPABASE_PROJECT_REF || process.argv[2] || "";
const accessToken = process.env.SUPABASE_ACCESS_TOKEN || "";
const configPath = process.env.FINDESK_PROD_CONFIG || path.join(root, "storage/secrets/prod-config.local.php");
const templatePath = path.join(root, "supabase/auth-email-templates/magic-link-otp.html");

if (!projectRef) {
  console.error("SUPABASE_PROJECT_REF is required.");
  process.exit(1);
}

if (!accessToken) {
  console.error("SUPABASE_ACCESS_TOKEN is required.");
  process.exit(1);
}

if (!fs.existsSync(configPath)) {
  console.error(`Production config not found: ${configPath}`);
  process.exit(1);
}

const php = spawnSync(
  "php",
  [
    "-r",
    `
      $cfg = require $argv[1];
      $mail = $cfg["mail"] ?? [];
      echo json_encode([
        "mode" => $mail["mode"] ?? "",
        "host" => $mail["host"] ?? "",
        "port" => (int)($mail["port"] ?? 0),
        "secure" => $mail["secure"] ?? "",
        "username" => $mail["username"] ?? "",
        "password" => $mail["password"] ?? "",
        "from_email" => $mail["from_email"] ?? "",
        "from_name" => $mail["from_name"] ?? "FinDesk",
      ], JSON_UNESCAPED_UNICODE);
    `,
    configPath
  ],
  { encoding: "utf8" }
);

if (php.status !== 0) {
  console.error(php.stderr || "Unable to read PHP production config.");
  process.exit(1);
}

const smtp = JSON.parse(php.stdout);
const missing = ["host", "port", "username", "password", "from_email"].filter((key) => !smtp[key]);
if (smtp.mode !== "smtp" || missing.length > 0) {
  console.error(`Incomplete SMTP config. Missing: ${missing.join(", ") || "mode=smtp"}`);
  process.exit(1);
}

const template = fs.readFileSync(templatePath, "utf8");

function patchAuthConfig(payload) {
  const body = JSON.stringify(payload);

  return new Promise((resolve, reject) => {
    const request = https.request(
      {
        method: "PATCH",
        hostname: "api.supabase.com",
        path: `/v1/projects/${encodeURIComponent(projectRef)}/config/auth`,
        headers: {
          Authorization: `Bearer ${accessToken}`,
          "Content-Type": "application/json",
          "Content-Length": Buffer.byteLength(body)
        }
      },
      (response) => {
        let text = "";
        response.setEncoding("utf8");
        response.on("data", (chunk) => {
          text += chunk;
        });
        response.on("end", () => {
          if (response.statusCode >= 200 && response.statusCode < 300) {
            resolve(text);
            return;
          }
          reject(new Error(`HTTP ${response.statusCode}: ${text}`));
        });
      }
    );

    request.on("error", reject);
    request.write(body);
    request.end();
  });
}

(async () => {
  await patchAuthConfig({
    external_email_enabled: true,
    mailer_secure_email_change_enabled: true,
    mailer_autoconfirm: false,
    smtp_admin_email: smtp.from_email,
    smtp_host: smtp.host,
    smtp_port: String(smtp.port),
    smtp_user: smtp.username,
    smtp_pass: smtp.password,
    smtp_sender_name: smtp.from_name
  });
  console.log("Supabase custom SMTP enabled from production config.");

  await patchAuthConfig({
    mailer_subjects_magic_link: "Код входа в FinDesk: {{ .Token }}",
    mailer_templates_magic_link_content: template,
    mailer_otp_length: 6
  });
  console.log("Supabase magic-link email template now sends a 6-digit OTP code.");
})().catch((error) => {
  console.error(`Supabase auth SMTP/OTP setup failed: ${error.message}`);
  process.exit(1);
});
