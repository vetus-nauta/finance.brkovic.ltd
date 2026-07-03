<?php
declare(strict_types=1);

namespace FinDesk\V2;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

final class Repository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?: Database::pdo();
    }

    public function listWorkspaces(): array
    {
        $rows = $this->db->query("
            SELECT * FROM workspaces
            WHERE archived_at IS NULL
            ORDER BY created_at DESC, name ASC
        ")->fetchAll();

        return array_map([$this, 'workspaceRow'], $rows);
    }

    public function getWorkspace(string $id): array
    {
        $workspace = $this->findWorkspace(assert_uuid($id, 'workspace_id'));

        if (!$workspace) {
            throw new NotFoundException('workspace_not_found');
        }

        return $workspace;
    }

    public function createWorkspace(array $input): array
    {
        $type = (string)($input['type'] ?? 'yacht');
        if (!in_array($type, ['yacht', 'family', 'personal', 'business', 'trip', 'custom'], true)) {
            throw new InvalidArgumentException('type_invalid');
        }

        $id = uuid_v4();
        $createdBy = optional_uuid($input['created_by'] ?? null, 'created_by');
        $values = [
            $id,
            clean_string($input['name'] ?? '', 'name', 160),
            $type,
            strtoupper(clean_string($input['currency'] ?? 'EUR', 'currency', 3)),
            clean_string($input['locale'] ?? 'ru', 'locale', 12),
            $createdBy,
        ];

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO workspaces (id, name, type, currency, locale, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute($values);

            if ($createdBy !== null) {
                $member = $this->db->prepare("
                    INSERT INTO workspace_members (id, workspace_id, user_id, role, created_at)
                    VALUES (?, ?, ?, 'owner', NOW())
                    ON DUPLICATE KEY UPDATE role = role
                ");
                $member->execute([uuid_v4(), $id, $createdBy]);
            }

            $this->seedCategories($id);
            $workspace = $this->getWorkspaceInTx($id);
            $this->writeAudit($id, 'workspace', $id, 'create', null, $workspace, $createdBy);
            $this->db->commit();

            return $workspace;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function listFlows(string $workspaceId): array
    {
        $workspaceId = assert_uuid($workspaceId, 'workspace_id');
        $this->requireWorkspace($workspaceId);

        $stmt = $this->db->prepare("
            SELECT * FROM flows
            WHERE workspace_id = ?
            ORDER BY is_default DESC, type ASC, created_at ASC
        ");
        $stmt->execute([$workspaceId]);

        return array_map([$this, 'flowRow'], $stmt->fetchAll());
    }

    public function createFlow(string $workspaceId, array $input): array
    {
        $workspaceId = assert_uuid($workspaceId, 'workspace_id');
        $this->requireWorkspace($workspaceId);

        $type = (string)($input['type'] ?? '');
        if (!in_array($type, ['cash', 'card'], true)) {
            throw new InvalidArgumentException('flow_type_invalid');
        }

        $id = uuid_v4();
        $performedBy = optional_uuid($input['created_by'] ?? null, 'created_by');
        $hasLiveBalance = array_key_exists('has_live_balance', $input) ? (bool)$input['has_live_balance'] : $type === 'cash';
        $values = [
            $id,
            $workspaceId,
            clean_string($input['name'] ?? ucfirst($type), 'name', 80),
            $type,
            $hasLiveBalance ? 1 : 0,
            !empty($input['is_default']) ? 1 : 0,
        ];

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO flows (id, workspace_id, name, type, has_live_balance, is_default, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute($values);

            $flow = $this->getFlowInTx($id);
            $this->writeAudit($workspaceId, 'flow', $id, 'create', null, $flow, $performedBy);
            $this->db->commit();

            return $flow;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function listEntries(string $workspaceId, array $query): array
    {
        $workspaceId = assert_uuid($workspaceId, 'workspace_id');
        $this->requireWorkspace($workspaceId);

        $where = ['e.workspace_id = ?', 'e.archived_at IS NULL'];
        $params = [$workspaceId];
        $period = null;

        if (($query['year'] ?? '') !== '' || ($query['month'] ?? '') !== '') {
            $year = (int)($query['year'] ?? 0);
            $month = (int)($query['month'] ?? 0);

            if ($year < 1900 || $year > 2200 || $month < 1 || $month > 12) {
                throw new InvalidArgumentException('period_invalid');
            }

            $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
            $where[] = 'e.date >= ?';
            $where[] = 'e.date < ?';
            $params[] = $start->format('Y-m-d');
            $params[] = $start->modify('+1 month')->format('Y-m-d');
            $period = ['year' => $year, 'month' => $month];
        }

        $stmt = $this->db->prepare("
            SELECT e.*, f.name AS flow_name, f.type AS flow_type
            FROM entries e
            JOIN flows f ON f.id = e.flow_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY e.date DESC, e.created_at DESC, e.id DESC
        ");
        $stmt->execute($params);

        return ['period' => $period, 'items' => array_map([$this, 'entryRow'], $stmt->fetchAll())];
    }

    public function createEntry(string $workspaceId, array $input): array
    {
        $workspaceId = assert_uuid($workspaceId, 'workspace_id');
        $this->requireWorkspace($workspaceId);

        $flowId = assert_uuid((string)($input['flow_id'] ?? ''), 'flow_id');
        if (!$this->findFlow($flowId, $workspaceId)) {
            throw new InvalidArgumentException('flow_not_in_workspace');
        }

        $id = uuid_v4();
        $createdBy = optional_uuid($input['created_by'] ?? null, 'created_by');
        $values = [
            $id,
            $workspaceId,
            $flowId,
            $createdBy,
            iso_date($input['date'] ?? ''),
            clean_string($input['raw_text'] ?? '', 'raw_text', 2000),
            optional_clean_string($input['notes'] ?? null, 1000),
        ];

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO entries (
                    id, workspace_id, flow_id, created_by, date, raw_text, sign, amount,
                    direction, entry_type, status, source_type, notes, matched_rules,
                    created_at, updated_at
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?, NULL, NULL,
                    'none', 'unrecognized', 'unrecognized', 'manual', ?, '[]',
                    NOW(), NOW()
                )
            ");
            $stmt->execute($values);

            $entry = $this->getEntryInTx($id);
            $this->writeAudit($workspaceId, 'entry', $id, 'create', null, $entry, $createdBy);
            $this->db->commit();

            return $entry;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateEntry(string $entryId, array $input): array
    {
        $entryId = assert_uuid($entryId, 'entry_id');
        $performedBy = optional_uuid($input['updated_by'] ?? ($input['performed_by'] ?? null), 'performed_by');

        $this->db->beginTransaction();
        try {
            $before = $this->getEntryForUpdate($entryId);
            $workspaceId = (string)$before['workspace_id'];
            $sets = [];
            $params = [];

            if (array_key_exists('date', $input)) {
                $sets[] = 'date = ?';
                $params[] = iso_date($input['date']);
            }

            if (array_key_exists('raw_text', $input)) {
                $sets[] = 'raw_text = ?';
                $params[] = clean_string($input['raw_text'], 'raw_text', 2000);
            }

            if (array_key_exists('notes', $input)) {
                $sets[] = 'notes = ?';
                $params[] = optional_clean_string($input['notes'], 1000);
            }

            if (array_key_exists('status', $input)) {
                $status = (string)$input['status'];
                $allowed = ['recognized', 'unrecognized', 'other_review', 'excluded', 'imported', 'assistant_pending', 'accepted', 'rejected', 'corrected', 'duplicate_suspect'];

                if (!in_array($status, $allowed, true)) {
                    throw new InvalidArgumentException('status_invalid');
                }

                $sets[] = 'status = ?';
                $params[] = $status;
            }

            if (array_key_exists('category_id', $input)) {
                $categoryId = optional_uuid($input['category_id'], 'category_id');

                if ($categoryId !== null && !$this->categoryBelongsToWorkspace($categoryId, $workspaceId)) {
                    throw new InvalidArgumentException('category_not_in_workspace');
                }

                $sets[] = 'category_id = ?';
                $params[] = $categoryId;
            }

            if (!$sets) {
                throw new InvalidArgumentException('no_update_fields');
            }

            $sets[] = 'updated_at = NOW()';
            $params[] = $entryId;

            $stmt = $this->db->prepare('UPDATE entries SET ' . implode(', ', $sets) . ' WHERE id = ?');
            $stmt->execute($params);

            $after = $this->getEntryInTx($entryId);
            $this->writeAudit($workspaceId, 'entry', $entryId, 'update', $before, $after, $performedBy);
            $this->db->commit();

            return $after;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function listCategories(string $workspaceId): array
    {
        $workspaceId = assert_uuid($workspaceId, 'workspace_id');
        $this->requireWorkspace($workspaceId);
        $this->seedCategories($workspaceId);

        $stmt = $this->db->prepare("
            SELECT * FROM categories
            WHERE workspace_id = ? AND is_active = 1
            ORDER BY sort_order ASC, code ASC
        ");
        $stmt->execute([$workspaceId]);

        return array_map([$this, 'categoryRow'], $stmt->fetchAll());
    }

    private function seedCategories(string $workspaceId): void
    {
        $seedPath = dirname(__DIR__, 2) . '/FinDesk v2.0/schemas/categories.seed.json';
        $items = json_decode((string)file_get_contents($seedPath), true);

        if (!is_array($items)) {
            throw new InvalidArgumentException('category_seed_invalid');
        }

        $stmt = $this->db->prepare("
            INSERT INTO categories (
                id, workspace_id, code, name, direction, parent_code,
                sort_order, is_system, is_active, created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, NOW())
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                direction = VALUES(direction),
                sort_order = VALUES(sort_order),
                is_system = 1,
                is_active = 1
        ");

        foreach ($items as $item) {
            if (!is_array($item) || !isset($item['code'], $item['name'], $item['direction'])) {
                continue;
            }

            $stmt->execute([
                uuid_v4(),
                $workspaceId,
                (string)$item['code'],
                json_encode_db($item['name']),
                (string)$item['direction'],
                $item['parent_code'] ?? null,
                (int)($item['sort_order'] ?? 100),
            ]);
        }
    }

    private function writeAudit(?string $workspaceId, string $entityType, string $entityId, string $action, ?array $before, ?array $after, ?string $performedBy): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO audit_log (
                id, workspace_id, entity_type, entity_id, action,
                before_json, after_json, performed_by, created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            uuid_v4(),
            $workspaceId,
            $entityType,
            $entityId,
            $action,
            $before === null ? null : json_encode_db($before),
            $after === null ? null : json_encode_db($after),
            $performedBy,
        ]);
    }

    private function requireWorkspace(string $workspaceId): void
    {
        if (!$this->findWorkspace($workspaceId)) {
            throw new NotFoundException('workspace_not_found');
        }
    }

    private function findWorkspace(string $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM workspaces WHERE id = ? AND archived_at IS NULL LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? $this->workspaceRow($row) : null;
    }

    private function findFlow(string $id, string $workspaceId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM flows WHERE id = ? AND workspace_id = ? LIMIT 1");
        $stmt->execute([$id, $workspaceId]);
        $row = $stmt->fetch();

        return $row ? $this->flowRow($row) : null;
    }

    private function categoryBelongsToWorkspace(string $categoryId, string $workspaceId): bool
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM categories
            WHERE id = ?
              AND (workspace_id = ? OR workspace_id IS NULL)
              AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$categoryId, $workspaceId]);

        return (bool)$stmt->fetch();
    }

    private function getWorkspaceInTx(string $id): array
    {
        $workspace = $this->findWorkspace($id);
        if (!$workspace) {
            throw new NotFoundException('workspace_not_found');
        }

        return $workspace;
    }

    private function getFlowInTx(string $id): array
    {
        $stmt = $this->db->prepare("SELECT * FROM flows WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new NotFoundException('flow_not_found');
        }

        return $this->flowRow($row);
    }

    private function getEntryInTx(string $id): array
    {
        $stmt = $this->db->prepare("
            SELECT e.*, f.name AS flow_name, f.type AS flow_type
            FROM entries e
            JOIN flows f ON f.id = e.flow_id
            WHERE e.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new NotFoundException('entry_not_found');
        }

        return $this->entryRow($row);
    }

    private function getEntryForUpdate(string $id): array
    {
        $stmt = $this->db->prepare("
            SELECT e.*, f.name AS flow_name, f.type AS flow_type
            FROM entries e
            JOIN flows f ON f.id = e.flow_id
            WHERE e.id = ?
              AND e.archived_at IS NULL
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new NotFoundException('entry_not_found');
        }

        return $this->entryRow($row);
    }

    private function workspaceRow(array $row): array
    {
        return [
            'id' => (string)$row['id'],
            'name' => (string)$row['name'],
            'type' => (string)$row['type'],
            'currency' => (string)$row['currency'],
            'locale' => (string)$row['locale'],
            'created_by' => $row['created_by'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'archived_at' => $row['archived_at'] ?? null,
        ];
    }

    private function flowRow(array $row): array
    {
        return [
            'id' => (string)$row['id'],
            'workspace_id' => (string)$row['workspace_id'],
            'name' => (string)$row['name'],
            'type' => (string)$row['type'],
            'has_live_balance' => (bool)$row['has_live_balance'],
            'is_default' => (bool)$row['is_default'],
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    private function entryRow(array $row): array
    {
        return [
            'id' => (string)$row['id'],
            'workspace_id' => (string)$row['workspace_id'],
            'flow_id' => (string)$row['flow_id'],
            'flow' => [
                'id' => (string)$row['flow_id'],
                'name' => (string)($row['flow_name'] ?? ''),
                'type' => (string)($row['flow_type'] ?? ''),
            ],
            'created_by' => $row['created_by'] ?? null,
            'actor_id' => $row['actor_id'] ?? null,
            'date' => (string)$row['date'],
            'raw_text' => (string)$row['raw_text'],
            'sign' => $row['sign'] ?? null,
            'amount' => $row['amount'] === null ? null : (string)$row['amount'],
            'direction' => (string)$row['direction'],
            'entry_type' => (string)$row['entry_type'],
            'category_id' => $row['category_id'] ?? null,
            'status' => (string)$row['status'],
            'balance_after' => $row['balance_after'] === null ? null : (string)$row['balance_after'],
            'source_type' => (string)$row['source_type'],
            'source_id' => $row['source_id'] ?? null,
            'source_row_id' => $row['source_row_id'] ?? null,
            'notes' => $row['notes'] ?? null,
            'confidence' => $row['confidence'] === null ? null : (string)$row['confidence'],
            'matched_rules' => decode_json_field($row['matched_rules'] ?? null, []),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'archived_at' => $row['archived_at'] ?? null,
        ];
    }

    private function categoryRow(array $row): array
    {
        return [
            'id' => (string)$row['id'],
            'workspace_id' => $row['workspace_id'] ?? null,
            'code' => (string)$row['code'],
            'name' => decode_json_field($row['name'] ?? null, []),
            'direction' => (string)$row['direction'],
            'parent_code' => $row['parent_code'] ?? null,
            'sort_order' => (int)$row['sort_order'],
            'is_system' => (bool)$row['is_system'],
            'is_active' => (bool)$row['is_active'],
            'created_at' => $row['created_at'] ?? null,
        ];
    }
}

final class NotFoundException extends \RuntimeException
{
}
