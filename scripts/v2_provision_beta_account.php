<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/v2/Repository.php';

final class FinDeskV2BetaAccountProvisioner
{
    private PDO $db;
    private FinDeskV2Repository $repo;

    public function __construct()
    {
        $this->db = ql_db();
        $this->repo = new FinDeskV2Repository($this->db);
    }

    public function run(array $argv): void
    {
        $email = $this->arg($argv, '--email') ?? getenv('FINDESK_V2_BETA_EMAIL') ?: 'vetus.nauta@gmail.com';
        $workspaceName = $this->arg($argv, '--workspace') ?? getenv('FINDESK_V2_BETA_WORKSPACE') ?: 'Claudia Z';
        $displayName = $this->arg($argv, '--display-name') ?? getenv('FINDESK_V2_BETA_DISPLAY_NAME') ?: 'Vetus Nauta';
        $openingCash = $this->arg($argv, '--opening-cash') ?? getenv('FINDESK_V2_BETA_OPENING_CASH') ?: '0.00';

        $email = mb_strtolower(trim((string)$email));
        $workspaceName = trim((string)$workspaceName);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid beta account email.');
        }
        if ($workspaceName === '') {
            throw new RuntimeException('Workspace name is required.');
        }

        $userId = $this->ensureUser($email, (string)$displayName);
        $workspace = $this->findWorkspaceByName($workspaceName);
        $createdWorkspace = false;
        if ($workspace === null) {
            $workspace = $this->repo->createWorkspace([
                'name' => $workspaceName,
                'type' => 'yacht',
                'currency' => 'EUR',
                'locale' => 'ru',
                'opening_cash' => $openingCash,
            ], $userId);
            $createdWorkspace = true;
        } else {
            $this->ensureWorkspaceOwner($workspace['id'], $userId);
        }

        $archiveWorkspace = $this->findWorkspaceByName($workspaceName . ' Archive Raw History');
        if ($archiveWorkspace !== null) {
            $this->ensureWorkspaceOwner($archiveWorkspace['id'], $userId);
        }

        $summary = [
            'email' => $email,
            'user_id' => $userId,
            'workspace' => $this->workspaceSummary((string)$workspace['id']),
            'workspace_created' => $createdWorkspace,
            'archive_workspace' => $archiveWorkspace === null ? null : $this->workspaceSummary((string)$archiveWorkspace['id']),
            'login_note' => 'No password was created. Existing email-code authentication can use this users.email later.',
        ];

        echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }

    private function ensureUser(string $email, string $displayName): int
    {
        $stmt = $this->db->prepare("SELECT id, status FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if ($row) {
            if ((string)$row['status'] !== 'active') {
                $this->db->prepare("
                    UPDATE users
                    SET status = 'active', deleted_at = NULL, display_name = ?
                    WHERE id = ?
                ")->execute([$displayName, (int)$row['id']]);
            } else {
                $this->db->prepare("
                    UPDATE users
                    SET display_name = ?
                    WHERE id = ?
                ")->execute([$displayName, (int)$row['id']]);
            }

            return (int)$row['id'];
        }

        $this->db->prepare("
            INSERT INTO users (email, display_name, preferred_language, timezone, status)
            VALUES (?, ?, 'ru', 'Europe/Podgorica', 'active')
        ")->execute([$email, $displayName]);

        return (int)$this->db->lastInsertId();
    }

    private function findWorkspaceByName(string $name): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                w.*,
                COUNT(e.id) AS entries_count
            FROM v2_workspaces w
            LEFT JOIN v2_entries e ON e.workspace_id = w.id AND e.archived_at IS NULL
            WHERE w.name = ?
              AND w.archived_at IS NULL
            GROUP BY w.id
            ORDER BY entries_count DESC, w.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$name]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function ensureWorkspaceOwner(string $workspaceId, int $userId): void
    {
        $stmt = $this->db->prepare("
            SELECT id, role
            FROM v2_workspace_members
            WHERE workspace_id = ?
              AND user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$workspaceId, $userId]);
        $row = $stmt->fetch();
        if ($row) {
            if ((string)$row['role'] !== 'owner') {
                $this->db->prepare("
                    UPDATE v2_workspace_members
                    SET role = 'owner'
                    WHERE id = ?
                ")->execute([(string)$row['id']]);
            }
            return;
        }

        $this->db->prepare("
            INSERT INTO v2_workspace_members (id, workspace_id, user_id, role)
            VALUES (?, ?, ?, 'owner')
        ")->execute([FinDeskV2Support::uuid(), $workspaceId, $userId]);
    }

    private function workspaceSummary(string $workspaceId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                w.id,
                w.name,
                w.type,
                w.currency,
                COUNT(DISTINCT e.id) AS entries_count,
                COUNT(DISTINCT s.id) AS import_sources_count,
                COUNT(DISTINCT r.id) AS import_rows_count
            FROM v2_workspaces w
            LEFT JOIN v2_entries e ON e.workspace_id = w.id AND e.archived_at IS NULL
            LEFT JOIN v2_import_sources s ON s.workspace_id = w.id
            LEFT JOIN v2_import_rows r ON r.import_source_id = s.id
            WHERE w.id = ?
            GROUP BY w.id
            LIMIT 1
        ");
        $stmt->execute([$workspaceId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('Workspace disappeared during provisioning.');
        }

        return [
            'id' => (string)$row['id'],
            'name' => (string)$row['name'],
            'type' => (string)$row['type'],
            'currency' => (string)$row['currency'],
            'entries_count' => (int)$row['entries_count'],
            'import_sources_count' => (int)$row['import_sources_count'],
            'import_rows_count' => (int)$row['import_rows_count'],
        ];
    }

    private function arg(array $argv, string $name): ?string
    {
        foreach ($argv as $index => $value) {
            if ($value === $name && isset($argv[$index + 1])) {
                return (string)$argv[$index + 1];
            }
            if (str_starts_with((string)$value, $name . '=')) {
                return substr((string)$value, strlen($name) + 1);
            }
        }

        return null;
    }
}

(new FinDeskV2BetaAccountProvisioner())->run($argv);
