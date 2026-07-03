<?php
declare(strict_types=1);

namespace FinDesk\V2;

use InvalidArgumentException;
use Throwable;

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Support.php';
require_once __DIR__ . '/Repository.php';

final class Api
{
    private Repository $repo;

    public function __construct(?Repository $repo = null)
    {
        $this->repo = $repo ?: new Repository();
    }

    public static function describeRoutes(): array
    {
        return [
            'GET /workspaces',
            'POST /workspaces',
            'GET /workspaces/{workspaceId}',
            'GET /workspaces/{workspaceId}/flows',
            'POST /workspaces/{workspaceId}/flows',
            'GET /workspaces/{workspaceId}/entries?year=YYYY&month=M',
            'POST /workspaces/{workspaceId}/entries',
            'PATCH /entries/{entryId}',
            'GET /workspaces/{workspaceId}/categories',
        ];
    }

    public function handle(): void
    {
        try {
            $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
            $segments = $this->pathSegments();
            $input = input_json();

            if ($method === 'GET' && $segments === ['workspaces']) {
                json_response(['ok' => true, 'items' => $this->repo->listWorkspaces()]);
            }

            if ($method === 'POST' && $segments === ['workspaces']) {
                json_response(['ok' => true, 'workspace' => $this->repo->createWorkspace($input)], 201);
            }

            if ($method === 'GET' && count($segments) === 2 && $segments[0] === 'workspaces') {
                json_response(['ok' => true, 'workspace' => $this->repo->getWorkspace($segments[1])]);
            }

            if (count($segments) === 3 && $segments[0] === 'workspaces' && $segments[2] === 'flows') {
                if ($method === 'GET') {
                    json_response(['ok' => true, 'items' => $this->repo->listFlows($segments[1])]);
                }

                if ($method === 'POST') {
                    json_response(['ok' => true, 'flow' => $this->repo->createFlow($segments[1], $input)], 201);
                }
            }

            if (count($segments) === 3 && $segments[0] === 'workspaces' && $segments[2] === 'entries') {
                if ($method === 'GET') {
                    $result = $this->repo->listEntries($segments[1], $_GET);
                    json_response(['ok' => true, 'period' => $result['period'], 'items' => $result['items']]);
                }

                if ($method === 'POST') {
                    json_response(['ok' => true, 'entry' => $this->repo->createEntry($segments[1], $input)], 201);
                }
            }

            if ($method === 'GET' && count($segments) === 3 && $segments[0] === 'workspaces' && $segments[2] === 'categories') {
                json_response(['ok' => true, 'items' => $this->repo->listCategories($segments[1])]);
            }

            if ($method === 'PATCH' && count($segments) === 2 && $segments[0] === 'entries') {
                json_response(['ok' => true, 'entry' => $this->repo->updateEntry($segments[1], $input)]);
            }

            json_response(['ok' => false, 'error' => 'route_not_found'], 404);
        } catch (NotFoundException $e) {
            json_response(['ok' => false, 'error' => $e->getMessage()], 404);
        } catch (InvalidArgumentException $e) {
            json_response(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'error' => 'server_error', 'message' => $e->getMessage()], 500);
        }
    }

    private function pathSegments(): array
    {
        $path = (string)($_GET['path'] ?? ($_SERVER['PATH_INFO'] ?? ''));

        if ($path === '') {
            $uriPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
            $marker = '/v2-api.php';
            $pos = strpos($uriPath, $marker);

            if ($pos !== false) {
                $path = substr($uriPath, $pos + strlen($marker));
            }
        }

        $path = trim($path, '/');

        if ($path === '') {
            return [];
        }

        return array_values(array_filter(explode('/', $path), static fn($part) => $part !== ''));
    }
}
