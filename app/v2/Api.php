<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/Repository.php';

final class FinDeskV2Api
{
    private FinDeskV2Repository $repo;

    public function __construct()
    {
        $this->repo = new FinDeskV2Repository(FinDeskV2Database::pdo());
    }

    public function handle(string $method, string $route, array $input, array $query): array
    {
        $user = $this->requireUser();
        $userId = (int)$user['id'];
        $route = FinDeskV2Support::normalizeRoute($route);
        $this->guardScopedWorkspaceRoute($method, $route, $input, $userId);

        if ($method === 'GET' && $route === '/api/workspaces') {
            return ['ok' => true, 'workspaces' => $this->repo->listWorkspaces($userId)];
        }

        if ($method === 'POST' && $route === '/api/workspaces') {
            return ['ok' => true, 'workspace' => $this->repo->createWorkspace($input, $userId)];
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})$#i', $route, $match) === 1) {
            if ($method === 'GET') {
                return ['ok' => true, 'workspace' => $this->repo->getWorkspace($match[1], $userId)];
            }
            if ($method === 'PATCH') {
                return ['ok' => true, 'workspace' => $this->repo->updateWorkspace($match[1], $input, $userId)];
            }
            if ($method === 'DELETE') {
                return ['ok' => true, 'workspace' => $this->repo->deleteWorkspace($match[1], $userId)];
            }
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/flows$#i', $route, $match) === 1) {
            if ($method === 'GET') {
                return ['ok' => true, 'flows' => $this->repo->listFlows($match[1], $userId)];
            }
            if ($method === 'POST') {
                return ['ok' => true, 'flow' => $this->repo->createFlow($match[1], $input, $userId)];
            }
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/invites$#i', $route, $match) === 1) {
            if ($method === 'GET') {
                return ['ok' => true, 'invites' => $this->repo->listWorkspaceInvites($match[1], $userId)];
            }
            if ($method === 'POST') {
                return ['ok' => true, 'invite' => $this->repo->createWorkspaceInvite($match[1], $input, $userId)];
            }
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/invites/([a-f0-9-]{36})/revoke$#i', $route, $match) === 1 && $method === 'POST') {
            return ['ok' => true, 'invite' => $this->repo->revokeWorkspaceInvite($match[1], $match[2], $userId)];
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/employee-mode$#i', $route, $match) === 1 && $method === 'GET') {
            return ['ok' => true] + $this->repo->getEmployeeWorkspaceMode($match[1], $userId);
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/accountable-dashboard$#i', $route, $match) === 1 && $method === 'GET') {
            return ['ok' => true, 'dashboard' => $this->repo->getAccountableDashboard($match[1], $userId)];
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/accountable-offers$#i', $route, $match) === 1) {
            if ($method === 'GET') {
                return ['ok' => true, 'offers' => $this->repo->listAccountableOffers($match[1], $userId)];
            }
            if ($method === 'POST') {
                return ['ok' => true, 'offer' => $this->repo->createAccountableOffer($match[1], $input, $userId)];
            }
        }

        if (preg_match('#^/api/accountable-offers/([a-f0-9-]{36})/accept$#i', $route, $match) === 1 && $method === 'POST') {
            return ['ok' => true, 'offer' => $this->repo->acceptAccountableOffer($match[1], $userId)];
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/accountable-reports$#i', $route, $match) === 1) {
            if ($method === 'GET') {
                return ['ok' => true, 'reports' => $this->repo->listAccountableReports($match[1], $query, $userId)];
            }
            if ($method === 'POST') {
                return ['ok' => true, 'report' => $this->repo->createAccountableReport($match[1], $input, $userId)];
            }
        }

        if (preg_match('#^/api/accountable-reports/([a-f0-9-]{36})/submit$#i', $route, $match) === 1 && $method === 'POST') {
            return ['ok' => true, 'report' => $this->repo->submitAccountableReport($match[1], $userId)];
        }

        if (preg_match('#^/api/accountable-reports/([a-f0-9-]{36})$#i', $route, $match) === 1 && $method === 'GET') {
            return ['ok' => true, 'report' => $this->repo->getAccountableReport($match[1], $userId)];
        }

        if (preg_match('#^/api/accountable-reports/([a-f0-9-]{36})/review-preview$#i', $route, $match) === 1 && $method === 'POST') {
            return ['ok' => true, 'preview' => $this->repo->previewAccountableReportReview($match[1], $input, $userId)];
        }

        if (preg_match('#^/api/accountable-reports/([a-f0-9-]{36})/accept$#i', $route, $match) === 1 && $method === 'POST') {
            return ['ok' => true, 'result' => $this->repo->acceptAccountableReportByAdmin($match[1], $input, $userId)];
        }

        if (preg_match('#^/api/accountable-reports/([a-f0-9-]{36})/materialization$#i', $route, $match) === 1 && $method === 'GET') {
            return ['ok' => true, 'materialization' => $this->repo->getAccountableReportMaterialization($match[1], $userId)];
        }

        if (preg_match('#^/api/accountable-reports/([a-f0-9-]{36})/materialization-preview$#i', $route, $match) === 1 && $method === 'POST') {
            return ['ok' => true, 'preview' => $this->repo->previewAccountableReportMaterialization($match[1], $userId)];
        }

        if (preg_match('#^/api/accountable-reports/([a-f0-9-]{36})/materialize$#i', $route, $match) === 1 && $method === 'POST') {
            return ['ok' => true, 'result' => $this->repo->materializeAccountableReport($match[1], $userId)];
        }

        if (preg_match('#^/api/accountable-settlements/([a-f0-9-]{36})/cash-resolve$#i', $route, $match) === 1 && $method === 'POST') {
            return ['ok' => true, 'result' => $this->repo->resolveAccountableSettlementWithCashMovement($match[1], $input, $userId)];
        }

        if ($route === '/api/workspace-invites/preview' && $method === 'POST') {
            return ['ok' => true] + $this->repo->getWorkspaceInviteByToken(
                FinDeskV2Support::requireString($input, 'token', 80),
                $userId
            );
        }

        if ($route === '/api/workspace-invites/accept' && $method === 'POST') {
            return ['ok' => true] + $this->repo->acceptWorkspaceInvite(
                FinDeskV2Support::requireString($input, 'token', 80),
                $userId
            );
        }

        if (preg_match('#^/api/workspace-invites/([a-f0-9]{48})$#i', $route, $match) === 1 && $method === 'GET') {
            return ['ok' => true] + $this->repo->getWorkspaceInviteByToken($match[1], $userId);
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/summary$#i', $route, $match) === 1 && $method === 'GET') {
            return ['ok' => true, 'summary' => $this->repo->getWorkspaceSummary($match[1], $userId)];
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/reports/monthly$#i', $route, $match) === 1 && $method === 'GET') {
            return ['ok' => true, 'report' => $this->repo->getMonthlyReport($match[1], $query, $userId)];
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/reports/layer1-summary$#i', $route, $match) === 1 && $method === 'GET') {
            return ['ok' => true, 'report' => $this->repo->getLayer1SummaryReport($match[1], $query, $userId)];
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/reports/layer1-source-entries$#i', $route, $match) === 1 && $method === 'GET') {
            return ['ok' => true] + $this->repo->getLayer1SourceEntries($match[1], $query, $userId);
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/reports/layer1-snapshots$#i', $route, $match) === 1) {
            if ($method === 'GET') {
                return ['ok' => true, 'snapshots' => $this->repo->listLayer1SummarySnapshots($match[1], $query, $userId)];
            }
            if ($method === 'POST') {
                return ['ok' => true, 'snapshot' => $this->repo->createLayer1SummarySnapshot($match[1], $input, $userId)];
            }
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/reports/batch-preview$#i', $route, $match) === 1 && $method === 'POST') {
            return ['ok' => true, 'preview' => $this->repo->previewReportBatch($match[1], $input, $userId)];
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/reports/batches$#i', $route, $match) === 1) {
            if ($method === 'GET') {
                return ['ok' => true, 'reports' => $this->repo->listReportBatches($match[1], $query, $userId)];
            }
            if ($method === 'POST') {
                return ['ok' => true, 'report' => $this->repo->createReportBatch($match[1], $input, $userId)];
            }
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/reports/batches/([a-f0-9-]{36})$#i', $route, $match) === 1 && $method === 'GET') {
            return ['ok' => true, 'report' => $this->repo->getReportBatch($match[1], $match[2], $userId)];
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/reports/operational-fragments$#i', $route, $match) === 1) {
            if ($method === 'GET') {
                return ['ok' => true, 'fragments' => $this->repo->listOperationalReportFragments($match[1], $query, $userId)];
            }
            if ($method === 'POST') {
                return ['ok' => true, 'fragment' => $this->repo->createOperationalReportFragment($match[1], $input, $userId)];
            }
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/reports/operational-fragments/preview$#i', $route, $match) === 1 && $method === 'POST') {
            return ['ok' => true] + $this->repo->previewOperationalReportFragment($match[1], $input, $userId);
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/reports/operational-fragments/([a-f0-9-]{36})$#i', $route, $match) === 1) {
            if ($method === 'GET') {
                return ['ok' => true, 'fragment' => $this->repo->getOperationalReportFragment($match[1], $match[2], $userId)];
            }
            if ($method === 'PATCH') {
                return ['ok' => true, 'fragment' => $this->repo->updateOperationalReportFragment($match[1], $match[2], $input, $userId)];
            }
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/reports/operational-fragments/([a-f0-9-]{36})/html-snapshots$#i', $route, $match) === 1) {
            if ($method === 'GET') {
                return ['ok' => true, 'snapshots' => $this->repo->listOperationalReportFragmentHtmlSnapshots($match[1], $match[2], $query, $userId)];
            }
            if ($method === 'POST') {
                return ['ok' => true, 'snapshot' => $this->repo->createOperationalReportFragmentHtmlSnapshot($match[1], $match[2], $input, $userId)];
            }
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/reports/operational-fragments/([a-f0-9-]{36})/html-snapshots/([a-f0-9-]{36})$#i', $route, $match) === 1 && $method === 'GET') {
            return ['ok' => true, 'snapshot' => $this->repo->getOperationalReportFragmentHtmlSnapshot($match[1], $match[2], $match[3], $userId)];
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/reports/operational-packages$#i', $route, $match) === 1) {
            if ($method === 'GET') {
                return ['ok' => true, 'packages' => $this->repo->listOperationalReportPackages($match[1], $query, $userId)];
            }
            if ($method === 'POST') {
                return ['ok' => true, 'package' => $this->repo->createOperationalReportPackage($match[1], $input, $userId)];
            }
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/reports/operational-packages/([a-f0-9-]{36})$#i', $route, $match) === 1 && $method === 'GET') {
            return ['ok' => true, 'package' => $this->repo->getOperationalReportPackage($match[1], $match[2], $userId)];
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/reports/category-matrix$#i', $route, $match) === 1 && $method === 'GET') {
            return ['ok' => true, 'matrix' => $this->repo->getCategoryMatrixReport($match[1], $query, $userId)];
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/reports/other-review$#i', $route, $match) === 1 && $method === 'GET') {
            return ['ok' => true, 'report' => $this->repo->getOtherReviewReport($match[1], $userId)];
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/dictionary-review-queue$#i', $route, $match) === 1 && $method === 'GET') {
            return ['ok' => true, 'queue' => $this->repo->getDictionaryReviewQueue($match[1], $query, $userId)];
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/raw-history$#i', $route, $match) === 1 && $method === 'GET') {
            return ['ok' => true, 'history' => $this->repo->getRawHistory($match[1], $query, $userId)];
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/raw-history/convert$#i', $route, $match) === 1 && $method === 'POST') {
            return ['ok' => true, 'conversion' => $this->repo->convertRawHistoryBatch($match[1], $input, $userId)];
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/dictionary-training-decisions$#i', $route, $match) === 1) {
            if ($method === 'GET') {
                return ['ok' => true, 'decisions' => $this->repo->listDictionaryTrainingDecisions($match[1], $query, $userId)];
            }
            if ($method === 'POST') {
                return ['ok' => true, 'decision' => $this->repo->decideDictionaryTraining($match[1], $input, $userId)];
            }
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/assistant-settings$#i', $route, $match) === 1) {
            if ($method === 'GET') {
                return ['ok' => true, 'settings' => $this->repo->getWorkspaceAssistantSettings($match[1], $userId)];
            }
            if ($method === 'PATCH') {
                return ['ok' => true, 'settings' => $this->repo->updateWorkspaceAssistantSettings($match[1], $input, $userId)];
            }
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/dictionary-training-internet-reference$#i', $route, $match) === 1 && $method === 'POST') {
            return ['ok' => true, 'reference' => $this->repo->previewDictionaryInternetReference($match[1], $input, $userId)];
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/dictionary-training-internet-reference/lookups$#i', $route, $match) === 1 && $method === 'GET') {
            return ['ok' => true, 'lookups' => $this->repo->listDictionaryInternetReferenceLookups($match[1], $query, $userId)];
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/dictionary-training-internet-reference/lookups/([a-f0-9-]{36})$#i', $route, $match) === 1 && $method === 'PATCH') {
            return ['ok' => true, 'lookup' => $this->repo->updateDictionaryInternetReferenceFeedback($match[1], $match[2], $input, $userId)];
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/imports/excel$#i', $route, $match) === 1 && $method === 'POST') {
            return ['ok' => true, 'import' => $this->repo->createLegacyExcelImport($match[1], $input, $userId)];
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/imports/([a-f0-9-]{36})/review$#i', $route, $match) === 1 && $method === 'GET') {
            return ['ok' => true, 'review' => $this->repo->getLegacyImportReview($match[1], $match[2], $userId)];
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/imports/([a-f0-9-]{36})/accept$#i', $route, $match) === 1 && $method === 'POST') {
            return ['ok' => true, 'review' => $this->repo->acceptLegacyImport($match[1], $match[2], $input, $userId)];
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/other-expenses$#i', $route, $match) === 1 && $method === 'GET') {
            return ['ok' => true, 'entries' => $this->repo->listOtherExpenseQueue($match[1], $userId)];
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/quick-notes$#i', $route, $match) === 1) {
            if ($method === 'GET') {
                return ['ok' => true, 'notes' => $this->repo->listQuickNotes($match[1], $query, $userId)];
            }
            if ($method === 'POST') {
                return ['ok' => true, 'note' => $this->repo->createQuickNote($match[1], $input, $userId)];
            }
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/quick-notes/([a-f0-9-]{36})$#i', $route, $match) === 1) {
            if ($method === 'PATCH') {
                return ['ok' => true, 'note' => $this->repo->updateQuickNote($match[1], $match[2], $input, $userId)];
            }
            if ($method === 'DELETE') {
                return ['ok' => true, 'note' => $this->repo->deleteQuickNote($match[1], $match[2], $userId)];
            }
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/quick-notes/([a-f0-9-]{36})/preview$#i', $route, $match) === 1 && $method === 'POST') {
            return ['ok' => true] + $this->repo->previewQuickNoteConversion($match[1], $match[2], $input, $userId);
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/quick-notes/([a-f0-9-]{36})/convert$#i', $route, $match) === 1 && $method === 'POST') {
            return ['ok' => true] + $this->repo->convertQuickNote($match[1], $match[2], $input, $userId);
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/entries$#i', $route, $match) === 1) {
            if ($method === 'GET') {
                return ['ok' => true, 'entries' => $this->repo->listEntries($match[1], $query, $userId)];
            }
            if ($method === 'POST') {
                return ['ok' => true, 'entry' => $this->repo->createEntry($match[1], $this->entryCreateInput($input), $userId)];
            }
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/parse-preview$#i', $route, $match) === 1 && $method === 'POST') {
            return ['ok' => true, 'preview' => $this->repo->previewEntryParse($match[1], $input, $userId)];
        }

        if ($method === 'POST' && $route === '/api/parse-entry-preview') {
            $workspaceId = FinDeskV2Support::requireString($input, 'workspace_id', 36);
            return ['ok' => true, 'preview' => $this->repo->previewEntryParse($workspaceId, $input, $userId)];
        }

        if (preg_match('#^/api/entries/([a-f0-9-]{36})$#i', $route, $match) === 1) {
            if ($method === 'PATCH') {
                return ['ok' => true, 'entry' => $this->repo->updateEntry($match[1], $input, $userId)];
            }
            if ($method === 'DELETE') {
                return ['ok' => true, 'entry' => $this->repo->deleteEntry($match[1], $userId, $input)];
            }
        }

        if (preg_match('#^/api/entries/([a-f0-9-]{36})/attachments$#i', $route, $match) === 1) {
            if ($method === 'GET') {
                return ['ok' => true, 'attachments' => $this->repo->listEntryAttachments($match[1], $userId)];
            }
            if ($method === 'POST') {
                return ['ok' => true, 'attachment' => $this->repo->createEntryAttachment($match[1], $input, $userId)];
            }
        }

        if (preg_match('#^/api/attachments/([a-f0-9-]{36})$#i', $route, $match) === 1 && $method === 'DELETE') {
            return ['ok' => true, 'attachment' => $this->repo->deleteAttachment($match[1], $userId)];
        }

        if (preg_match('#^/api/entries/([a-f0-9-]{36})/category$#i', $route, $match) === 1 && $method === 'PATCH') {
            return ['ok' => true, 'entry' => $this->repo->updateEntryCategory($match[1], $input, $userId)];
        }

        if (preg_match('#^/api/entries/([a-f0-9-]{36})/category/closed-month-decision$#i', $route, $match) === 1 && $method === 'POST') {
            return ['ok' => true] + $this->repo->decideClosedMonthCategoryCorrection($match[1], $input, $userId);
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/months/([0-9]{4})/([0-9]{1,2})/close$#i', $route, $match) === 1 && $method === 'POST') {
            return ['ok' => true] + $this->repo->closeMonth($match[1], (int)$match[2], (int)$match[3], $input, $userId);
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/months/([0-9]{4})/([0-9]{1,2})/reopen$#i', $route, $match) === 1 && $method === 'POST') {
            return ['ok' => true] + $this->repo->reopenMonth($match[1], (int)$match[2], (int)$match[3], $input, $userId);
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/months/([0-9]{4})/([0-9]{1,2})/correction$#i', $route, $match) === 1 && $method === 'POST') {
            return ['ok' => true, 'entry' => $this->repo->createMonthCorrection($match[1], (int)$match[2], (int)$match[3], $input, $userId)];
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/categories$#i', $route, $match) === 1 && $method === 'GET') {
            return ['ok' => true, 'categories' => $this->repo->listCategories($match[1], $userId)];
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/category-rules$#i', $route, $match) === 1 && $method === 'POST') {
            return ['ok' => true, 'category_rule' => $this->repo->createCategoryRule($match[1], $input, $userId)];
        }

        throw new FinDeskV2HttpError(404, 'route_not_found');
    }

    private function requireUser(): array
    {
        $user = ql_current_user();
        if (!$user) {
            throw new FinDeskV2HttpError(401, 'not_authenticated');
        }

        return $user;
    }

    private function guardScopedWorkspaceRoute(string $method, string $route, array $input, int $userId): void
    {
        if ($method === 'POST' && $route === '/api/parse-entry-preview') {
            $workspaceId = FinDeskV2Support::requireString($input, 'workspace_id', 36);
            $access = $this->repo->getWorkspaceAccess($workspaceId, $userId);
            if (!$access['can_read_workspace']) {
                throw new FinDeskV2HttpError(403, 'workspace_read_only');
            }
            return;
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})(?:$|/)#i', $route, $match) !== 1) {
            return;
        }

        $workspaceId = $match[1];
        $access = $this->repo->getWorkspaceAccess($workspaceId, $userId);
        if ($access['can_read_workspace']) {
            return;
        }

        if (preg_match('#^/api/workspaces/[a-f0-9-]{36}/invites(?:$|/)#i', $route) === 1) {
            throw new FinDeskV2HttpError(403, 'workspace_admin_required');
        }

        if ($method === 'POST' && preg_match('#^/api/workspaces/[a-f0-9-]{36}/accountable-offers$#i', $route) === 1) {
            throw new FinDeskV2HttpError(403, 'workspace_admin_required');
        }

        if ($method === 'GET' && preg_match('#^/api/workspaces/[a-f0-9-]{36}$#i', $route) === 1) {
            return;
        }

        if ($method === 'POST'
            && preg_match('#^/api/workspaces/[a-f0-9-]{36}/(?:entries|parse-preview)$#i', $route) === 1
        ) {
            throw new FinDeskV2HttpError(403, 'workspace_read_only');
        }

        $scopedRoutes = [
            '#^/api/workspaces/[a-f0-9-]{36}/entries$#i' => ['GET'],
            '#^/api/workspaces/[a-f0-9-]{36}/employee-mode$#i' => ['GET'],
            '#^/api/workspaces/[a-f0-9-]{36}/accountable-offers$#i' => ['GET'],
            '#^/api/workspaces/[a-f0-9-]{36}/accountable-reports$#i' => ['GET', 'POST'],
        ];
        foreach ($scopedRoutes as $pattern => $methods) {
            if (in_array($method, $methods, true) && preg_match($pattern, $route) === 1) {
                if (!$access['can_read_entries'] && (string)$access['role'] !== 'employee') {
                    throw new FinDeskV2HttpError(403, 'workspace_scope_required');
                }
                return;
            }
        }

        throw new FinDeskV2HttpError(403, 'workspace_scope_required');
    }

    private function entryCreateInput(array $input): array
    {
        $allowed = ['flow_id', 'date', 'raw_text', 'category_code', 'amount', 'closed_month_decision'];

        return array_intersect_key($input, array_fill_keys($allowed, true));
    }
}
