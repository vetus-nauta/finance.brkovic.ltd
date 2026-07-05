<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Support.php';

final class FinDeskV2Repository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function listWorkspaces(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT w.*
            FROM v2_workspaces w
            INNER JOIN v2_workspace_members m ON m.workspace_id = w.id
            WHERE m.user_id = ? AND w.archived_at IS NULL
            ORDER BY w.created_at DESC
        ");
        $stmt->execute([$userId]);

        return array_map([$this, 'workspaceRow'], $stmt->fetchAll());
    }

    public function createWorkspace(array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($input, $userId): array {
            $id = FinDeskV2Support::uuid();
            $name = FinDeskV2Support::requireString($input, 'name', 190);
            $type = FinDeskV2Support::enum(
                FinDeskV2Support::optionalString($input, 'type', 'yacht', 40) ?? 'yacht',
                ['yacht', 'family', 'personal', 'business', 'trip', 'custom'],
                'type'
            );
            $currency = strtoupper(FinDeskV2Support::optionalString($input, 'currency', 'EUR', 3) ?? 'EUR');
            $locale = FinDeskV2Support::optionalString($input, 'locale', 'ru', 10) ?? 'ru';

            $this->db->prepare("
                INSERT INTO v2_workspaces (id, name, type, currency, locale, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([$id, $name, $type, $currency, $locale, $userId]);

            $this->db->prepare("
                INSERT INTO v2_workspace_members (id, workspace_id, user_id, role)
                VALUES (?, ?, ?, 'owner')
            ")->execute([FinDeskV2Support::uuid(), $id, $userId]);

            $openingCash = FinDeskV2Support::nullableAmount($input['opening_cash'] ?? $input['opening_balance'] ?? null) ?? '0.00';

            $this->createDefaultFlow($id, 'Cash', 'cash', true, true, $openingCash);
            $this->createDefaultFlow($id, 'Card', 'card', false);
            $workspace = $this->getWorkspace($id, $userId);
            $this->audit($id, 'workspace', $id, 'create', null, $workspace, $userId);

            return $workspace;
        });
    }

    public function getWorkspace(string $id, int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT w.*
            FROM v2_workspaces w
            INNER JOIN v2_workspace_members m ON m.workspace_id = w.id
            WHERE w.id = ? AND m.user_id = ? AND w.archived_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$id, $userId]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new FinDeskV2HttpError(404, 'workspace_not_found');
        }

        return $this->workspaceRow($row);
    }

    public function updateWorkspace(string $id, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($id, $input, $userId): array {
            $before = $this->getWorkspace($id, $userId);
            $name = FinDeskV2Support::optionalString($input, 'name', $before['name'], 190) ?? $before['name'];
            $type = FinDeskV2Support::enum(
                FinDeskV2Support::optionalString($input, 'type', $before['type'], 40) ?? $before['type'],
                ['yacht', 'family', 'personal', 'business', 'trip', 'custom'],
                'type'
            );
            $currency = strtoupper(FinDeskV2Support::optionalString($input, 'currency', $before['currency'], 3) ?? $before['currency']);
            $locale = FinDeskV2Support::optionalString($input, 'locale', $before['locale'], 10) ?? $before['locale'];

            $this->db->prepare("
                UPDATE v2_workspaces
                SET name = ?, type = ?, currency = ?, locale = ?
                WHERE id = ?
            ")->execute([$name, $type, $currency, $locale, $id]);

            $after = $this->getWorkspace($id, $userId);
            $this->audit($id, 'workspace', $id, 'update', $before, $after, $userId);

            return $after;
        });
    }

    public function listFlows(string $workspaceId, int $userId): array
    {
        $this->getWorkspace($workspaceId, $userId);
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_flows
            WHERE workspace_id = ?
            ORDER BY is_default DESC, FIELD(type, 'cash', 'card', 'assistant_journal'), name
        ");
        $stmt->execute([$workspaceId]);

        return array_map([$this, 'flowRow'], $stmt->fetchAll());
    }

    public function createFlow(string $workspaceId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $input, $userId): array {
            $this->getWorkspace($workspaceId, $userId);
            $flow = $this->createDefaultFlow(
                $workspaceId,
                FinDeskV2Support::requireString($input, 'name', 120),
                FinDeskV2Support::enum((string)($input['type'] ?? ''), ['cash', 'card', 'assistant_journal'], 'type'),
                (bool)($input['has_live_balance'] ?? false),
                (bool)($input['is_default'] ?? false),
                FinDeskV2Support::nullableAmount($input['opening_balance'] ?? null) ?? '0.00'
            );
            $this->audit($workspaceId, 'flow', $flow['id'], 'create', null, $flow, $userId);

            return $flow;
        });
    }

    public function getWorkspaceSummary(string $workspaceId, int $userId): array
    {
        $this->getWorkspace($workspaceId, $userId);
        $flows = $this->listFlows($workspaceId, $userId);
        $cashFlow = null;
        $cardFlowIds = [];

        foreach ($flows as $flow) {
            if ($flow['type'] === 'cash' && $flow['has_live_balance']) {
                $cashFlow = $flow;
            }
            if ($flow['type'] === 'card') {
                $cardFlowIds[] = $flow['id'];
            }
        }

        $cashNow = $cashFlow === null ? null : $cashFlow['opening_balance'];
        if ($cashFlow !== null) {
            $stmt = $this->db->prepare("
                SELECT balance_after
                FROM v2_entries
                WHERE flow_id = ?
                  AND archived_at IS NULL
                  AND balance_after IS NOT NULL
                ORDER BY date DESC, created_seq DESC
                LIMIT 1
            ");
            $stmt->execute([$cashFlow['id']]);
            $latest = $stmt->fetchColumn();
            if ($latest !== false) {
                $cashNow = (float)$latest;
            }
        }

        $cardExpenseTotal = 0.0;
        if ($cardFlowIds !== []) {
            $placeholders = implode(', ', array_fill(0, count($cardFlowIds), '?'));
            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(amount), 0)
                FROM v2_entries
                WHERE flow_id IN ({$placeholders})
                  AND archived_at IS NULL
                  AND direction = 'out'
                  AND entry_type = 'card_expense'
                  AND status IN ('recognized', 'other_review', 'imported', 'accepted')
                  AND amount IS NOT NULL
            ");
            $stmt->execute($cardFlowIds);
            $cardExpenseTotal = (float)$stmt->fetchColumn();
        }

        return [
            'workspace_id' => $workspaceId,
            'opening_cash' => $cashFlow === null ? null : $cashFlow['opening_balance'],
            'cash_now' => $cashNow,
            'card_expense_total' => $cardExpenseTotal,
        ];
    }

    public function listEntries(string $workspaceId, array $query, int $userId): array
    {
        $this->getWorkspace($workspaceId, $userId);
        $params = [$workspaceId];
        $where = ['e.workspace_id = ?', 'e.archived_at IS NULL'];

        if (!empty($query['year'])) {
            $where[] = 'YEAR(e.date) = ?';
            $params[] = (int)$query['year'];
        }
        if (!empty($query['month'])) {
            $where[] = 'MONTH(e.date) = ?';
            $params[] = (int)$query['month'];
        }

        $stmt = $this->db->prepare("
            SELECT
                e.*,
                f.type AS flow_type,
                f.name AS flow_name,
                c.code AS category_code,
                c.name_json AS category_name_json,
                a.name AS actor_name
            FROM v2_entries e
            INNER JOIN v2_flows f ON f.id = e.flow_id
            LEFT JOIN v2_categories c ON c.id = e.category_id
            LEFT JOIN v2_actors a ON a.id = e.actor_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY e.date ASC, e.created_seq ASC
        ");
        $stmt->execute($params);

        return array_map([$this, 'entryRow'], $stmt->fetchAll());
    }

    public function listOtherExpenseQueue(string $workspaceId, int $userId): array
    {
        $this->getWorkspace($workspaceId, $userId);
        $stmt = $this->db->prepare("
            SELECT
                e.*,
                f.type AS flow_type,
                f.name AS flow_name,
                c.code AS category_code,
                c.name_json AS category_name_json,
                a.name AS actor_name
            FROM v2_entries e
            INNER JOIN v2_flows f ON f.id = e.flow_id
            INNER JOIN v2_categories c ON c.id = e.category_id
            LEFT JOIN v2_actors a ON a.id = e.actor_id
            WHERE e.workspace_id = ?
              AND e.archived_at IS NULL
              AND e.status = 'other_review'
              AND e.entry_type = 'cash_expense'
              AND c.code = 'other'
            ORDER BY e.date ASC, e.created_seq ASC
        ");
        $stmt->execute([$workspaceId]);

        return array_map([$this, 'entryRow'], $stmt->fetchAll());
    }

    public function createEntry(string $workspaceId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $input, $userId): array {
            $this->getWorkspace($workspaceId, $userId);
            $flow = $this->getFlowForWorkspace(FinDeskV2Support::requireString($input, 'flow_id', 36), $workspaceId);
            $entry = $this->normalizeEntryInput($workspaceId, $flow, $input);
            $entry['id'] = FinDeskV2Support::uuid();
            $entry['created_by'] = $userId;

            $this->db->prepare("
                INSERT INTO v2_entries (
                    id, workspace_id, flow_id, created_by, actor_id, date, raw_text, sign, amount, direction,
                    entry_type, category_id, status, source_type, notes, confidence, matched_rules_json
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $entry['id'],
                $workspaceId,
                $flow['id'],
                $userId,
                $entry['actor_id'],
                $entry['date'],
                $entry['raw_text'],
                $entry['sign'],
                $entry['amount'],
                $entry['direction'],
                $entry['entry_type'],
                $entry['category_id'],
                $entry['status'],
                $entry['source_type'],
                $entry['notes'],
                $entry['confidence'],
                FinDeskV2Support::jsonEncode($entry['matched_rules']),
            ]);

            $created = $this->getEntry($entry['id'], $userId);
            $this->recalculateFlowBalance($flow['id']);
            $created = $this->getEntry($entry['id'], $userId);
            $this->audit($workspaceId, 'entry', $entry['id'], 'create', null, $created, $userId);

            return $created;
        });
    }

    public function previewEntryParse(string $workspaceId, array $input, int $userId): array
    {
        $this->getWorkspace($workspaceId, $userId);
        $flow = $this->getFlowForWorkspace(FinDeskV2Support::requireString($input, 'flow_id', 36), $workspaceId);
        $entry = $this->normalizeEntryInput($workspaceId, $flow, $input, false);

        return $this->entryPreviewRow($workspaceId, $flow, $entry);
    }

    public function updateEntry(string $entryId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($entryId, $input, $userId): array {
            $before = $this->getEntry($entryId, $userId);
            $this->guardEntryMonthIsOpen($before);
            $flowId = FinDeskV2Support::optionalString($input, 'flow_id', $before['flow']['id'], 36) ?? $before['flow']['id'];
            $flow = $this->getFlowForWorkspace($flowId, $before['workspace_id']);
            $entry = $this->normalizeEntryInput($before['workspace_id'], $flow, array_merge($before, $input));

            $this->db->prepare("
                UPDATE v2_entries
                SET flow_id = ?, actor_id = ?, date = ?, raw_text = ?, sign = ?, amount = ?, direction = ?,
                    entry_type = ?, category_id = ?, status = ?, source_type = ?, notes = ?,
                    confidence = ?, matched_rules_json = ?
                WHERE id = ?
            ")->execute([
                $flow['id'],
                $entry['actor_id'],
                $entry['date'],
                $entry['raw_text'],
                $entry['sign'],
                $entry['amount'],
                $entry['direction'],
                $entry['entry_type'],
                $entry['category_id'],
                $entry['status'],
                $entry['source_type'],
                $entry['notes'],
                $entry['confidence'],
                FinDeskV2Support::jsonEncode($entry['matched_rules']),
                $entryId,
            ]);

            $after = $this->getEntry($entryId, $userId);
            $this->recalculateFlowBalance($flow['id']);
            if ($before['flow']['id'] !== $flow['id']) {
                $this->recalculateFlowBalance($before['flow']['id']);
            }
            $after = $this->getEntry($entryId, $userId);
            $this->audit($before['workspace_id'], 'entry', $entryId, 'update', $before, $after, $userId);

            return $after;
        });
    }

    public function updateEntryCategory(string $entryId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($entryId, $input, $userId): array {
            $before = $this->getEntry($entryId, $userId);
            $this->guardEntryMonthIsOpen($before);
            $categoryCode = FinDeskV2Support::requireString($input, 'category_code', 80);
            $categoryId = $this->categoryIdByCode($before['workspace_id'], $categoryCode);

            $this->db->prepare("UPDATE v2_entries SET category_id = ? WHERE id = ?")->execute([$categoryId, $entryId]);

            $after = $this->getEntry($entryId, $userId);
            $this->audit($before['workspace_id'], 'entry', $entryId, 'update_category', $before, $after, $userId);

            return $after;
        });
    }

    public function deleteEntry(string $entryId, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($entryId, $userId): array {
            $before = $this->getEntry($entryId, $userId);
            $this->guardEntryMonthIsOpen($before);
            $this->db->prepare("UPDATE v2_entries SET archived_at = NOW() WHERE id = ?")->execute([$entryId]);
            $this->recalculateFlowBalance($before['flow']['id']);
            $this->audit($before['workspace_id'], 'entry', $entryId, 'delete', $before, ['archived' => true], $userId);

            return ['id' => $entryId, 'archived' => true];
        });
    }

    public function closeMonthForFixture(string $workspaceId, int $year, int $month, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $year, $month, $userId): array {
            $this->getWorkspace($workspaceId, $userId);
            $this->assertValidMonth($year, $month);
            $id = FinDeskV2Support::uuid();

            $this->db->prepare("
                INSERT INTO v2_monthly_closures (id, workspace_id, year, month, is_closed, closed_by, closed_at)
                VALUES (?, ?, ?, ?, 1, ?, NOW())
                ON DUPLICATE KEY UPDATE is_closed = 1, closed_by = VALUES(closed_by), closed_at = VALUES(closed_at)
            ")->execute([$id, $workspaceId, $year, $month, $userId]);

            return [
                'workspace_id' => $workspaceId,
                'year' => $year,
                'month' => $month,
                'is_closed' => true,
            ];
        });
    }

    public function listCategories(string $workspaceId, int $userId): array
    {
        $this->getWorkspace($workspaceId, $userId);
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_categories
            WHERE is_active = 1 AND (workspace_id IS NULL OR workspace_id = ?)
            ORDER BY sort_order ASC, code ASC
        ");
        $stmt->execute([$workspaceId]);

        return array_map([$this, 'categoryRow'], $stmt->fetchAll());
    }

    public function createCategoryRule(string $workspaceId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $input, $userId): array {
            $this->getWorkspace($workspaceId, $userId);
            $categoryId = $this->categoryIdByCode($workspaceId, FinDeskV2Support::requireString($input, 'category_code', 80));
            $id = FinDeskV2Support::uuid();
            $pattern = FinDeskV2Support::requireString($input, 'pattern', 255);
            $patternType = FinDeskV2Support::enum(
                FinDeskV2Support::optionalString($input, 'pattern_type', 'keyword', 40) ?? 'keyword',
                ['keyword', 'phrase', 'regex', 'supplier', 'role'],
                'pattern_type'
            );
            $language = FinDeskV2Support::enum(
                FinDeskV2Support::optionalString($input, 'language', 'multi', 10) ?? 'multi',
                ['ru', 'en', 'it', 'es', 'de', 'bcms', 'multi'],
                'language'
            );
            $weight = $this->optionalInt($input, 'weight', 10);
            $negativeWeight = $this->optionalInt($input, 'negative_weight', 0);
            $requiresAny = $this->optionalStringList($input, 'requires_any');
            $excludesAny = $this->optionalStringList($input, 'excludes_any');

            $this->db->prepare("
                INSERT INTO v2_category_rules (
                    id, workspace_id, category_id, pattern, pattern_type, language, weight,
                    negative_weight, requires_any_json, excludes_any_json, created_by_user, is_active
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1)
            ")->execute([
                $id,
                $workspaceId,
                $categoryId,
                $pattern,
                $patternType,
                $language,
                $weight,
                $negativeWeight,
                FinDeskV2Support::jsonEncode($requiresAny),
                FinDeskV2Support::jsonEncode($excludesAny),
            ]);

            $rule = $this->getCategoryRule($id, $workspaceId, $userId);
            $this->audit($workspaceId, 'category_rule', $id, 'create', null, $rule, $userId);

            return $rule;
        });
    }

    private function createDefaultFlow(
        string $workspaceId,
        string $name,
        string $type,
        bool $hasLiveBalance,
        bool $isDefault = true,
        string $openingBalance = '0.00'
    ): array {
        $id = FinDeskV2Support::uuid();
        $this->db->prepare("
            INSERT INTO v2_flows (id, workspace_id, name, type, has_live_balance, opening_balance, is_default)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([$id, $workspaceId, $name, $type, $hasLiveBalance ? 1 : 0, $openingBalance, $isDefault ? 1 : 0]);

        return $this->flowRow([
            'id' => $id,
            'workspace_id' => $workspaceId,
            'name' => $name,
            'type' => $type,
            'has_live_balance' => $hasLiveBalance ? 1 : 0,
            'opening_balance' => $openingBalance,
            'is_default' => $isDefault ? 1 : 0,
            'created_at' => null,
        ]);
    }

    private function getFlowForWorkspace(string $flowId, string $workspaceId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM v2_flows WHERE id = ? AND workspace_id = ? LIMIT 1");
        $stmt->execute([$flowId, $workspaceId]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new FinDeskV2HttpError(404, 'flow_not_found');
        }

        return $this->flowRow($row);
    }

    private function guardEntryMonthIsOpen(array $entry): void
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$entry['date']);
        if (!$date) {
            return;
        }

        $year = (int)$date->format('Y');
        $month = (int)$date->format('n');

        $stmt = $this->db->prepare("
            SELECT is_closed
            FROM v2_monthly_closures
            WHERE workspace_id = ? AND year = ? AND month = ? AND is_closed = 1
            LIMIT 1
        ");
        $stmt->execute([$entry['workspace_id'], $year, $month]);

        if (!$stmt->fetchColumn()) {
            return;
        }

        throw new FinDeskV2HttpError(409, FinDeskV2Support::jsonEncode([
            'error' => 'closed_month_requires_decision',
            'year' => $year,
            'month' => $month,
            'choices' => ['create_correction', 'recalculate_chain', 'cancel'],
        ]));
    }

    private function assertValidMonth(int $year, int $month): void
    {
        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
            throw new FinDeskV2HttpError(422, 'invalid_month');
        }
    }

    private function recalculateFlowBalance(string $flowId): void
    {
        $stmt = $this->db->prepare("SELECT * FROM v2_flows WHERE id = ? LIMIT 1");
        $stmt->execute([$flowId]);
        $flow = $stmt->fetch();

        if (!$flow) {
            return;
        }

        if ((int)$flow['has_live_balance'] !== 1 || (string)$flow['type'] !== 'cash') {
            $this->db->prepare("UPDATE v2_entries SET balance_after = NULL WHERE flow_id = ?")->execute([$flowId]);
            return;
        }

        $balance = (float)$flow['opening_balance'];
        $entries = $this->db->prepare("
            SELECT id, amount, direction, entry_type, status
            FROM v2_entries
            WHERE flow_id = ? AND archived_at IS NULL
            ORDER BY date ASC, created_seq ASC
        ");
        $entries->execute([$flowId]);
        $update = $this->db->prepare("UPDATE v2_entries SET balance_after = ? WHERE id = ?");

        foreach ($entries->fetchAll() as $entry) {
            $delta = $this->cashBalanceDelta($entry);
            if ($delta === null) {
                $update->execute([null, $entry['id']]);
                continue;
            }

            $balance += $delta;
            $update->execute([number_format($balance, 2, '.', ''), $entry['id']]);
        }
    }

    private function cashBalanceDelta(array $entry): ?float
    {
        if ($entry['amount'] === null) {
            return null;
        }

        if (!in_array((string)$entry['status'], ['recognized', 'other_review', 'imported', 'accepted', 'corrected'], true)) {
            return null;
        }

        $amount = (float)$entry['amount'];
        $direction = (string)$entry['direction'];
        $entryType = (string)$entry['entry_type'];

        if ($direction === 'in' && in_array($entryType, ['cash_income', 'correction'], true)) {
            return $amount;
        }

        if ($direction === 'out' && in_array($entryType, ['cash_expense', 'correction'], true)) {
            return -$amount;
        }

        return null;
    }

    private function getEntry(string $entryId, int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                e.*,
                f.type AS flow_type,
                f.name AS flow_name,
                c.code AS category_code,
                c.name_json AS category_name_json,
                a.name AS actor_name
            FROM v2_entries e
            INNER JOIN v2_workspace_members m ON m.workspace_id = e.workspace_id
            INNER JOIN v2_flows f ON f.id = e.flow_id
            LEFT JOIN v2_categories c ON c.id = e.category_id
            LEFT JOIN v2_actors a ON a.id = e.actor_id
            WHERE e.id = ? AND m.user_id = ? AND e.archived_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$entryId, $userId]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new FinDeskV2HttpError(404, 'entry_not_found');
        }

        return $this->entryRow($row);
    }

    private function getCategoryRule(string $ruleId, string $workspaceId, int $userId): array
    {
        $this->getWorkspace($workspaceId, $userId);
        $stmt = $this->db->prepare("
            SELECT r.*, c.code AS category_code
            FROM v2_category_rules r
            INNER JOIN v2_categories c ON c.id = r.category_id
            WHERE r.id = ? AND r.workspace_id = ?
            LIMIT 1
        ");
        $stmt->execute([$ruleId, $workspaceId]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new FinDeskV2HttpError(404, 'category_rule_not_found');
        }

        return $this->categoryRuleRow($row);
    }

    private function normalizeEntryInput(string $workspaceId, array $flow, array $input, bool $persistRelations = true): array
    {
        $rawText = FinDeskV2Support::requireString($input, 'raw_text', 2000);
        $sourceType = FinDeskV2Support::enum(
            FinDeskV2Support::optionalString($input, 'source_type', 'manual', 40) ?? 'manual',
            ['manual', 'import', 'assistant', 'correction'],
            'source_type'
        );
        $sign = null;
        $amount = null;
        $direction = 'none';
        $entryType = 'unrecognized';
        $status = 'unrecognized';
        $actorName = null;
        $actorId = null;
        $matchedRules = is_array($input['matched_rules'] ?? null) ? array_values($input['matched_rules']) : [];

        if (preg_match('/^([+-])\s*([0-9]+(?:[.,][0-9]{1,2})?)/u', $rawText, $match) === 1) {
            $sign = $match[1];
            $amount = number_format((float)str_replace(',', '.', $match[2]), 2, '.', '');
            $direction = $sign === '+' ? 'in' : 'out';
            $entryType = match ($flow['type'] . ':' . $sign) {
                'cash:+' => 'cash_income',
                'cash:-' => 'cash_expense',
                'card:+' => 'card_income',
                'card:-' => 'card_expense',
                default => 'assistant_pending',
            };
            $status = $flow['type'] === 'assistant_journal' ? 'assistant_pending' : 'recognized';

            if ($flow['type'] === 'card' && $sign === '+') {
                if ($sourceType === 'correction') {
                    $entryType = 'correction';
                    $status = 'corrected';
                } else {
                    $amount = null;
                    $direction = 'none';
                    $entryType = 'unrecognized';
                    $status = 'unrecognized';
                }
            }
        }

        if (
            $sign !== null
            && !($flow['type'] === 'card' && $sign === '+' && $sourceType !== 'correction')
            && array_key_exists('amount', $input)
        ) {
            $amount = FinDeskV2Support::nullableAmount($input['amount']);
        }

        $categoryId = null;
        $categoryCode = FinDeskV2Support::optionalString($input, 'category_code', null, 80);
        if ($categoryCode !== null) {
            $categoryId = $this->categoryIdByCode($workspaceId, $categoryCode);
        } elseif ($sign !== null && !($flow['type'] === 'card' && $sign === '+' && $sourceType !== 'correction')) {
            $inferred = $this->inferEntrySemantics($rawText, $flow, $sign);
            if ($inferred['category_code'] !== null) {
                $categoryId = $this->categoryIdByCode($workspaceId, $inferred['category_code']);
            }
            if ($inferred['status'] !== null) {
                $status = $inferred['status'];
            }
            $matchedRules = array_merge($matchedRules, $inferred['matched_rules']);
        }

        if ($sign === null) {
            $amount = null;
            $direction = 'none';
            $entryType = 'unrecognized';
            $status = 'unrecognized';
        }

        if ($sign !== null) {
            $actorName = $this->extractActorName($rawText);
            if ($actorName !== null && $persistRelations) {
                $actorId = $this->getOrCreateActor($workspaceId, $actorName);
            }
        }

        return [
            'date' => FinDeskV2Support::date($input),
            'raw_text' => $rawText,
            'sign' => $sign,
            'amount' => $amount,
            'direction' => $direction,
            'entry_type' => $entryType,
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'category_id' => $categoryId,
            'status' => $sign === null || ($flow['type'] === 'card' && $sign === '+' && $sourceType !== 'correction')
                ? $status
                : FinDeskV2Support::enum(
                    FinDeskV2Support::optionalString($input, 'status', $status, 40) ?? $status,
                    ['recognized', 'unrecognized', 'other_review', 'excluded', 'imported', 'assistant_pending', 'accepted', 'rejected', 'corrected', 'duplicate_suspect'],
                    'status'
                ),
            'source_type' => $sourceType,
            'notes' => FinDeskV2Support::optionalString($input, 'notes', null, 2000),
            'confidence' => FinDeskV2Support::nullableAmount($input['confidence'] ?? null),
            'matched_rules' => $matchedRules,
        ];
    }

    private function inferEntrySemantics(string $rawText, array $flow, string $sign): array
    {
        $text = mb_strtolower($rawText);
        $categoryCode = null;
        $status = null;
        $matchedRules = [];

        if (str_contains($text, 'netflix')) {
            $categoryCode = 'media_comms';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'netflix', 'category_code' => 'media_comms'];
        } elseif (preg_match('/заправ|топлив|fuel/u', $text) === 1) {
            $categoryCode = 'fuel';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'fuel', 'category_code' => 'fuel'];
            if (preg_match('/тузик|tender/u', $text) === 1) {
                $matchedRules[] = ['source' => 'fixture_secondary_marker', 'marker' => 'tender_related'];
            }
        } elseif (preg_match('/кабел|cable/u', $text) === 1) {
            $categoryCode = 'tech_parts';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'cable', 'category_code' => 'tech_parts'];
        } elseif (preg_match('/charter|агентск/u', $text) === 1 && $sign === '+') {
            $categoryCode = 'commercial_income';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'commercial_income', 'category_code' => 'commercial_income'];
        } elseif (preg_match('/аванс/u', $text) === 1 && $this->extractActorName($rawText) !== null && $sign === '-') {
            $categoryCode = 'crew';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'actor_advance', 'category_code' => 'crew'];
        } elseif (preg_match('/какая-то штука|kakaya/u', $text) === 1 && $flow['type'] === 'cash' && $sign === '-') {
            $categoryCode = 'other';
            $status = 'other_review';
            $matchedRules[] = ['source' => 'fixture_fallback', 'pattern' => 'unknown_expense', 'category_code' => 'other'];
        }

        return [
            'category_code' => $categoryCode,
            'status' => $status,
            'matched_rules' => $matchedRules,
        ];
    }

    private function extractActorName(string $rawText): ?string
    {
        if (preg_match('/^[+-]\s*[0-9]+(?:[.,][0-9]{1,2})?\s+([\x{0400}-\x{04FF}][\x{0400}-\x{04FF}\'-]{1,80})\b/u', $rawText, $match) !== 1) {
            return null;
        }

        $name = mb_substr($match[1], 0, 120);
        $knownFixtureActors = ['Вова'];

        return in_array($name, $knownFixtureActors, true) ? $name : null;
    }

    private function getOrCreateActor(string $workspaceId, string $name): string
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM v2_actors
            WHERE workspace_id = ? AND name = ?
            LIMIT 1
        ");
        $stmt->execute([$workspaceId, $name]);
        $id = $stmt->fetchColumn();

        if ($id) {
            return (string)$id;
        }

        $id = FinDeskV2Support::uuid();
        $this->db->prepare("
            INSERT INTO v2_actors (id, workspace_id, name, aliases_json)
            VALUES (?, ?, ?, ?)
        ")->execute([$id, $workspaceId, $name, '[]']);

        return $id;
    }

    private function categoryIdByCode(string $workspaceId, string $code): string
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM v2_categories
            WHERE code = ? AND is_active = 1 AND (workspace_id IS NULL OR workspace_id = ?)
            ORDER BY workspace_id IS NULL ASC
            LIMIT 1
        ");
        $stmt->execute([$code, $workspaceId]);
        $id = $stmt->fetchColumn();

        if (!$id) {
            throw new FinDeskV2HttpError(422, 'unknown_category');
        }

        return (string)$id;
    }

    private function optionalInt(array $input, string $key, int $default): int
    {
        if (!array_key_exists($key, $input) || $input[$key] === '') {
            return $default;
        }

        if (filter_var($input[$key], FILTER_VALIDATE_INT) === false) {
            throw new FinDeskV2HttpError(422, 'invalid_' . $key);
        }

        return (int)$input[$key];
    }

    private function optionalStringList(array $input, string $key): array
    {
        if (!array_key_exists($key, $input) || $input[$key] === null || $input[$key] === '') {
            return [];
        }

        if (!is_array($input[$key])) {
            throw new FinDeskV2HttpError(422, 'invalid_' . $key);
        }

        $values = [];
        foreach ($input[$key] as $value) {
            $value = trim((string)$value);
            if ($value !== '') {
                $values[] = mb_substr($value, 0, 255);
            }
        }

        return array_values(array_unique($values));
    }

    private function audit(
        ?string $workspaceId,
        string $entityType,
        ?string $entityId,
        string $action,
        ?array $before,
        ?array $after,
        int $userId
    ): void {
        $this->db->prepare("
            INSERT INTO v2_audit_log (id, workspace_id, entity_type, entity_id, action, before_json, after_json, performed_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            FinDeskV2Support::uuid(),
            $workspaceId,
            $entityType,
            $entityId,
            $action,
            $before === null ? null : FinDeskV2Support::jsonEncode($before),
            $after === null ? null : FinDeskV2Support::jsonEncode($after),
            $userId,
        ]);
    }

    private function workspaceRow(array $row): array
    {
        return [
            'id' => (string)$row['id'],
            'name' => (string)$row['name'],
            'type' => (string)$row['type'],
            'currency' => (string)$row['currency'],
            'locale' => (string)$row['locale'],
            'created_by' => isset($row['created_by']) ? (int)$row['created_by'] : null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
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
            'opening_balance' => isset($row['opening_balance']) ? (float)$row['opening_balance'] : 0.0,
            'is_default' => (bool)$row['is_default'],
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    private function categoryRow(array $row): array
    {
        return [
            'id' => (string)$row['id'],
            'workspace_id' => $row['workspace_id'] ?? null,
            'code' => (string)$row['code'],
            'name' => FinDeskV2Support::jsonDecode($row['name_json'] ?? '{}', []),
            'direction' => (string)$row['direction'],
            'parent_code' => $row['parent_code'] ?? null,
            'sort_order' => (int)$row['sort_order'],
            'is_system' => (bool)$row['is_system'],
        ];
    }

    private function categoryRuleRow(array $row): array
    {
        return [
            'id' => (string)$row['id'],
            'workspace_id' => $row['workspace_id'] ?? null,
            'category_code' => (string)$row['category_code'],
            'pattern' => (string)$row['pattern'],
            'pattern_type' => (string)$row['pattern_type'],
            'language' => (string)$row['language'],
            'weight' => (int)$row['weight'],
            'negative_weight' => (int)$row['negative_weight'],
            'requires_any' => FinDeskV2Support::jsonDecode($row['requires_any_json'] ?? '[]', []),
            'excludes_any' => FinDeskV2Support::jsonDecode($row['excludes_any_json'] ?? '[]', []),
            'created_by_user' => (bool)$row['created_by_user'],
            'is_active' => (bool)$row['is_active'],
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    private function entryPreviewRow(string $workspaceId, array $flow, array $entry): array
    {
        $categoryCode = null;
        $categoryName = null;
        if ($entry['category_id'] !== null) {
            $stmt = $this->db->prepare("SELECT code, name_json FROM v2_categories WHERE id = ? LIMIT 1");
            $stmt->execute([$entry['category_id']]);
            $category = $stmt->fetch();
            if ($category) {
                $categoryCode = $category['code'];
                $categoryName = FinDeskV2Support::jsonDecode($category['name_json'] ?? null, null);
            }
        }

        return [
            'workspace_id' => $workspaceId,
            'flow' => [
                'id' => (string)$flow['id'],
                'type' => (string)$flow['type'],
                'name' => (string)$flow['name'],
            ],
            'date' => $entry['date'],
            'raw_text' => $entry['raw_text'],
            'sign' => $entry['sign'],
            'amount' => $entry['amount'] === null ? null : (float)$entry['amount'],
            'direction' => $entry['direction'],
            'entry_type' => $entry['entry_type'],
            'actor' => $entry['actor_name'] === null ? null : [
                'id' => null,
                'name' => $entry['actor_name'],
            ],
            'category_code' => $categoryCode,
            'category_name' => $categoryName,
            'status' => $entry['status'],
            'source_type' => $entry['source_type'],
            'notes' => $entry['notes'],
            'confidence' => $entry['confidence'] === null ? null : (float)$entry['confidence'],
            'matched_rules' => $entry['matched_rules'],
            'will_save' => false,
        ];
    }

    private function entryRow(array $row): array
    {
        return [
            'id' => (string)$row['id'],
            'workspace_id' => (string)$row['workspace_id'],
            'flow' => [
                'id' => (string)$row['flow_id'],
                'type' => (string)$row['flow_type'],
                'name' => (string)$row['flow_name'],
            ],
            'date' => (string)$row['date'],
            'raw_text' => (string)$row['raw_text'],
            'sign' => $row['sign'] ?? null,
            'amount' => $row['amount'] === null ? null : (float)$row['amount'],
            'direction' => (string)$row['direction'],
            'entry_type' => (string)$row['entry_type'],
            'actor' => $row['actor_id'] === null ? null : [
                'id' => (string)$row['actor_id'],
                'name' => (string)$row['actor_name'],
            ],
            'category_code' => $row['category_code'] ?? null,
            'category_name' => FinDeskV2Support::jsonDecode($row['category_name_json'] ?? null, null),
            'status' => (string)$row['status'],
            'balance_after' => $row['balance_after'] === null ? null : (float)$row['balance_after'],
            'source_type' => (string)$row['source_type'],
            'notes' => $row['notes'] ?? null,
            'confidence' => $row['confidence'] === null ? null : (float)$row['confidence'],
            'matched_rules' => FinDeskV2Support::jsonDecode($row['matched_rules_json'] ?? '[]', []),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
}
