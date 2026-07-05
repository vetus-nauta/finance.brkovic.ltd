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
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/flows$#i', $route, $match) === 1) {
            if ($method === 'GET') {
                return ['ok' => true, 'flows' => $this->repo->listFlows($match[1], $userId)];
            }
            if ($method === 'POST') {
                return ['ok' => true, 'flow' => $this->repo->createFlow($match[1], $input, $userId)];
            }
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/summary$#i', $route, $match) === 1 && $method === 'GET') {
            return ['ok' => true, 'summary' => $this->repo->getWorkspaceSummary($match[1], $userId)];
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/reports/monthly$#i', $route, $match) === 1 && $method === 'GET') {
            return ['ok' => true, 'report' => $this->repo->getMonthlyReport($match[1], $query, $userId)];
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/reports/category-matrix$#i', $route, $match) === 1 && $method === 'GET') {
            return ['ok' => true, 'matrix' => $this->repo->getCategoryMatrixReport($match[1], $query, $userId)];
        }

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/reports/other-review$#i', $route, $match) === 1 && $method === 'GET') {
            return ['ok' => true, 'report' => $this->repo->getOtherReviewReport($match[1], $userId)];
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

        if (preg_match('#^/api/workspaces/([a-f0-9-]{36})/entries$#i', $route, $match) === 1) {
            if ($method === 'GET') {
                return ['ok' => true, 'entries' => $this->repo->listEntries($match[1], $query, $userId)];
            }
            if ($method === 'POST') {
                return ['ok' => true, 'entry' => $this->repo->createEntry($match[1], $input, $userId)];
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
                return ['ok' => true, 'entry' => $this->repo->deleteEntry($match[1], $userId)];
            }
        }

        if (preg_match('#^/api/entries/([a-f0-9-]{36})/category$#i', $route, $match) === 1 && $method === 'PATCH') {
            return ['ok' => true, 'entry' => $this->repo->updateEntryCategory($match[1], $input, $userId)];
        }

        if (preg_match('#^/api/entries/([a-f0-9-]{36})/category/closed-month-decision$#i', $route, $match) === 1 && $method === 'POST') {
            return ['ok' => true] + $this->repo->decideClosedMonthCategoryCorrection($match[1], $input, $userId);
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
}
