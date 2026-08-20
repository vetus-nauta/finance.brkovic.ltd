#!/usr/bin/env node

const fs = require("node:fs");
const https = require("node:https");
const path = require("node:path");

const root = path.resolve(__dirname, "..");
const projectRef = process.env.SUPABASE_PROJECT_REF || process.argv[2] || "";
const accessToken = process.env.SUPABASE_ACCESS_TOKEN || "";
const templatePath = path.join(root, "supabase/auth-email-templates/magic-link-otp.html");

if (!projectRef) {
  console.error("SUPABASE_PROJECT_REF is required.");
  process.exit(1);
}

if (!accessToken) {
  console.error("SUPABASE_ACCESS_TOKEN is required. Create it in Supabase Account Tokens.");
  process.exit(1);
}

const template = fs.readFileSync(templatePath, "utf8");
const payload = JSON.stringify({
  mailer_subjects_magic_link: "Код входа в FinDesk: {{ .Token }}",
  mailer_templates_magic_link_content: template
});

const request = https.request(
  {
    method: "PATCH",
    hostname: "api.supabase.com",
    path: `/v1/projects/${encodeURIComponent(projectRef)}/config/auth`,
    headers: {
      Authorization: `Bearer ${accessToken}`,
      "Content-Type": "application/json",
      "Content-Length": Buffer.byteLength(payload)
    }
  },
  (response) => {
    let body = "";
    response.setEncoding("utf8");
    response.on("data", (chunk) => {
      body += chunk;
    });
    response.on("end", () => {
      if (response.statusCode >= 200 && response.statusCode < 300) {
        console.log("Supabase magic-link email template now sends OTP code.");
        return;
      }

      console.error(`Supabase template update failed: HTTP ${response.statusCode}`);
      console.error(body);
      process.exit(1);
    });
  }
);

request.on("error", (error) => {
  console.error(error.message);
  process.exit(1);
});

request.write(payload);
request.end();
