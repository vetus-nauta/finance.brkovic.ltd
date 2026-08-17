<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/InternetReferenceProvider.php';
require_once __DIR__ . '/LegacyExcelImporter.php';
require_once __DIR__ . '/Support.php';

final class FinDeskV2Repository
{
    private const ATTACHMENT_MAX_BYTES = 8388608;
    private const ATTACHMENT_ALLOWED_MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    private const WORKSPACE_ROLE_LABELS = [
        'owner' => 'Владелец',
        'admin' => 'Администратор',
        'assistant' => 'Финансист',
        'finance' => 'Финансист',
        'employee' => 'Сотрудник',
        'viewer' => 'Только просмотр',
    ];
    private const WORKSPACE_FULL_READ_ROLES = ['owner', 'admin', 'assistant', 'finance', 'viewer'];
    private const WORKSPACE_WRITER_ROLES = ['owner', 'admin', 'assistant', 'finance'];
    private const WORKSPACE_ADMIN_ROLES = ['owner', 'admin'];
    private const WORKSPACE_ACCESS_SCOPES = ['workspace', 'own_entries', 'assigned_actor', 'none'];
    private const DICTIONARY_CATEGORY_RULES = [
        'cash_topup_from_card' => '/снял кеш|снял с карты|снятие с карты|банкомат|atm|cash withdrawal|card to cash/u',
        'commercial_income' => '/чартер|оплата чартера|аренд[^,.;]*яхт|ареда яхты|яхт[^,.;]*аренд|сдач[аеи]?[^,.;]*яхт|charter|yacht[^,.;]*(rental|booking)/u',
        'dry_dock' => '/сухой док|антифоулинг|подъем|подъём|подьем|спуск|haul.?out|launch/u',
        'berth' => '/стоянк|зимовк|склад|гараж|электричеств|муринг|mooring|berth|vez/u',
        'marina_ports' => '/марин|порт|паром|выход в море|переход коринф|проход через коринф|tepai|такс[аы] по вход|luka|harbou?r/u',
        'service_water' => '/сервис|обслуж|мастер|ремонт|репарац|механик|токарь|водолаз|diver|диагност|опреснител|спас.?плот|пересертифик|дайвер|электрик|откачка серых вод|откачка черн[а-я]* танк|черн[а-я]* танк|откачк[а-я]* вод|откачк[а-я]* грязн[а-я]* вод|выкачк[а-я]* танк|замен|монтаж|варк|консервац|тест систем|огнетуш|(?:^|\s)то(?:\s|$)/u',
        'tech_parts' => '/аккумулятор|аккум|кабел|насос|мотор|детал|запчаст|инструмент|фильтр|анод|клей|реле|навигац|шлиф|машинк|пылесос|шланг|сантехник|расходник|расходники|крюк|переходник|генератор|батаре[яи]|батарейк|безопа[сст]+ност[а-я]* плаван|материал[а-я]* по тику|пропитк[аеи]? тик[а]?|расходники? по тику|расходники? тик|расодники? тик|для тика|тик.?клинер|тик.?силер|тик.?вандер|силер для платформы|средств[ао] для тика|очистител[ья]* тика|пятновыводител[ья]* тик|дезинфектор тик|обработк[а-я]* тика|щетк[а-я]*.*тик|тик.*щетк|трюмн|помп|подрульк|пордрульк|лебедк|смазк[а-я]* для лебед|компрессор|диммер|гелькоут|кранц|кранец|швартов|веревк|регулятор давления|контрольк|конде[яй]?|подгонк[а-я]*.*контрол[её]к.*кондиц|блок управления туалет|петл[яи].*(?:холодильн|хододильн)|амортизатор[а-я]*.*люк|люк[иа].*танк|датчик.*танк|ролик[а-я]* цепи|маркер[а-я]* цепи|подстаканник|экран на флай|кругов[а-я]* огонь|фонар[а-я]* на корм|плоттер|навионикс|навион|удлинитель|хомут|адаптер|болт|крепеж|крепеж[а-я]* гайк|втулк[а-я]* под стапел|строительн[а-я]* фен|мультиметр|предохранитель|сикафлекс|sikaflex|шарнир[а-я]*|шуруп[а-я]*|чертеж[а-я]* для 3д/u',
        'tender' => '/тузик|тендер|dinghy|tender|williams|outboard|seabob|сибоб|сапы?|sup/u',
        'fuel' => '/заправ|топлив|дизел|бензин|fuel|diesel|petrol|gorivo|nafta/u',
        'guest_trip_support' => '/айфон|iphone|самокат|скутер|параплан|музыкант|прогулк[а-я]* гост|нац парк|вход в музей|снаст|зарядк[а-я]* шефу|маски$|маски ласты|подводн[а-я]* маск|перья на сап|весло сап|набор для ныряния|отел[ьяеи]?|гостиниц/u',
        'guest_cash_issued' => '/^(?:[+-]?\s*\d+(?:[.,]\d+)?\s+)?(?:лв|леонид владимирович)$|расходы лв|общая потраченная сумма лв|игра лв|(?:передал|отдал|дал|выдал)\s+(?:лв|леонид владимирович|арику?|саше?|гост)/u',
        'representation_expenses' => '/представительск|подарок|подарк[а-я]* служб|презент|розы|сувенир|украшен[а-я]* др|делов\p{L}*[^,.;]*(обед|ужин|встреч)|(?:обед|ужин|ланч|встреча)[^,.;]*(?:\sс\s|\sдля\s)[\p{L}]|hospitality|business lunch|business dinner|lunch with|dinner with/u',
        'provisions' => '/продукт|продуукт|рыб|стейк|мяс|баранин|хлеб|фрукт|овощ|напит|вино|пиво|кола|сок|сироп|сладост|коктел|коктейл|устриц|скамп|шкамп|краб|кальмар|лангустин|осминог|лосось|тунец|салмон|сыр|морож|инжир|яйц|орех|мед|соус|острог|перекус|еда|ресторан|цветы|алкоголь|виски|водк|шампан|грей.?гус|моет|moet|вдова клико|аберлоу|ликер|кофе(?![\\s-]?машин)|холодн[а-я]* чай|рынок|клубник|монтефиш|обед|кафе|докупк[а-я]* необходим[а-я]* в поход|закупк[а-я]* в поход|косметик|гигиен|шампун|аптечк|аптек|лекарств|(?:^|\s)вода(?!\s+электричеств)(?:\s|$)|вода (?:на|в) лодк/u',
        'interior' => '/ковр|текстил|полотен|обувь|судоч|нож|посуд|матрас|игрушк|linen|towels|кухонн[^,.;]*принадлежн|кухонн[а-я]* расход|инвентарь по кухне|кухн[а-я]*.*интерьер|кухн[а-я]*.*обновлен|утварь.*кухн|перешив.*подуш|подушк|чехл|скатерт|нарды|шезлонг|кофе[\\s-]?машин|кофемашин|блендер|соковыжималк|микроволновк|печка|капучинатор|графин|пепельниц|жалюзи|одеял|наволочк|плед|комплект постельн|мешк[иа]|контейнер|замк[иа] на дверц|на кухню/u',
        'cleaning' => '/хим|мойк|моющ[а-я]* средств[а-я]*|салф|тряпк|пена|полиров|уборк|химчист|clean|laundry|detergent|прачк|прачеч|полирол|пенообразователь|керхер|мусор|вывоз мусора|отбеливател|плесен|грибк|распылител|щетк[а-я]*(?: для лодк)?/u',
        'media_comms' => '/netflix|нетфликс|apple|ivi|иви|старлинк|starlink|hipo|сим.?карт|интернет|инет|интенрнет|wifi|связ|telekom|картина.?тв|\bтв\b|телевиз|sonos|сонос|модем|роуминг|сайт[а-я]* клауди|домен|хостинг|платн[а-я]* погод|прогноз погод|прогнох погод|обновлен[а-я]* карт|hdmi|шнур[а-я]* телефон|чехол телефон/u',
        'current_boat_expenses' => '/брендир|(?:^|[\s-])форм[а-я]*|одежд[аы]? экипаж|спец.?одеж|спецодеж|агент|магазин|хоз.?товар|принтер|(?:^|\s)инвентарь(?!\s+по\s+кухне)(?:\s|$)|банковск[а-я]* перевод|комисси[яи] банк|банковск[а-я]* комисс|банковск[а-я]* процент[а-я]*.*перевод|забрал свои|bank fee|bank commission/u',
        'transport_expenses' => '/такси|трансфер|аренда авто|арендованн[а-я]* авто|рентакар|билеты?|перел[её]т|авиа|поезд|автобус|самол[её]т|air serbia|логистик|забрал гостей|дорожн[а-я]* расход|запра[вк][а-я]* авто|парковк|курьер|доставк|почт[а-я]* в сербию|велосипед[а-я]* млет|перевозк[а-я]* гидроцикл|taxi|transfer|car rental|tickets|delivery/u',
        'admin_legal' => '/тур.?регистрац|тамож|дьюти|документ|печат[ьи]|налог|ндс|страхов|регистрац|юрист|адвокат|license|insurance|customs|виньет|лиценз|леценз|sanada|такса|такс[аы] банк перевод|траст компани|внж|крулист|crew.?list|виза|судебн[а-я]* перевод|открытие счета|обеспечение счета|берегов[а-я]* служб|морск[а-я]* сертиф[а-я]*|сертифиткат|разрешен[а-я]* на вход|флаг[а-я]* итали|флаг[а-я]* кайман|границ|просрочк[а-я]* нахождения/u',
        'crew' => '/\bзп\b|зарплат|аванс|капитан|хостесс|помощник|экипаж|работник в помощь|sailor|crew|salary|повар|чаев/u',
        'other' => '/планшет|обезналич|консьерж|книжк[а-я]* моряка|подставк[а-я]* под динги/u',
    ];

    private ?array $archiveLowerAccountingExceptions = null;
    private ?bool $reportBatchSchemaAvailable = null;
    private ?bool $operationalHtmlSnapshotSchemaAvailable = null;
    private ?bool $operationalPackageSchemaAvailable = null;
    private ?bool $workspaceMemberAccessScopeAvailable = null;
    private ?bool $workspaceMemberAssignedActorAvailable = null;
    private ?bool $workspaceInviteSchemaAvailable = null;
    private ?bool $accountableOfferSchemaAvailable = null;
    private ?bool $accountableReportSchemaAvailable = null;
    private ?bool $quickNoteSchemaAvailable = null;
    private ?bool $workspaceLiabilityOpeningSchemaAvailable = null;

    public function __construct(private readonly PDO $db)
    {
        $this->ensureWorkspaceMembershipAccessSchema();
        $this->ensureWorkspaceInviteSchema();
        $this->ensureAccountableOfferSchema();
        $this->ensureAccountableReportSchema();
        $this->ensureOperationalReportStatusSchema();
        $this->ensureQuickNoteSchema();
        $this->ensureWorkspaceLiabilityOpeningSchema();
    }

    public function listWorkspaces(int $userId): array
    {
        $memberProjection = $this->workspaceMemberProjectionSql('m');
        $stmt = $this->db->prepare("
            SELECT w.*, {$memberProjection}
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
        $memberProjection = $this->workspaceMemberProjectionSql('m');
        $stmt = $this->db->prepare("
            SELECT w.*, {$memberProjection}
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

    public function getWorkspaceAccess(string $workspaceId, int $userId): array
    {
        return $this->workspaceAccess($workspaceId, $userId);
    }

    public function updateWorkspace(string $id, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($id, $input, $userId): array {
            $before = $this->getWorkspace($id, $userId);
            $this->requireWorkspaceWriter($id, $userId);
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

    public function deleteWorkspace(string $id, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($id, $userId): array {
            $before = $this->getWorkspace($id, $userId);
            $this->requireWorkspaceOwnerAdmin($id, $userId);

            $this->db->prepare("
                UPDATE v2_workspaces
                SET archived_at = NOW()
                WHERE id = ? AND archived_at IS NULL
            ")->execute([$id]);

            $after = $before + [
                'archived' => true,
                'archived_at' => date('c'),
                'trash_retention_days' => 60,
            ];
            $this->audit($id, 'workspace', $id, 'delete_to_trash', $before, $after, $userId);

            return $after;
        });
    }

    public function listWorkspaceInvites(string $workspaceId, int $userId): array
    {
        $this->requireWorkspaceOwnerAdmin($workspaceId, $userId);
        $this->ensureWorkspaceInviteSchema();
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_workspace_invites
            WHERE workspace_id = ?
            ORDER BY start_date ASC, end_date ASC, created_at ASC
            LIMIT 100
        ");
        $stmt->execute([$workspaceId]);

        return array_map(fn (array $row): array => $this->workspaceInviteRow($row, false), $stmt->fetchAll());
    }

    public function createWorkspaceInvite(string $workspaceId, array $input, int $userId): array
    {
        $this->ensureWorkspaceInviteSchema();

        return FinDeskV2Database::transact(function () use ($workspaceId, $input, $userId): array {
            $workspace = $this->getWorkspace($workspaceId, $userId);
            $this->requireWorkspaceOwnerAdmin($workspaceId, $userId);
            $role = FinDeskV2Support::enum(
                FinDeskV2Support::optionalString($input, 'role', 'employee', 40) ?? 'employee',
                ['employee'],
                'role'
            );
            $accessScope = FinDeskV2Support::enum(
                FinDeskV2Support::optionalString($input, 'access_scope', 'own_entries', 40) ?? 'own_entries',
                ['own_entries'],
                'access_scope'
            );
            $emailKey = array_key_exists('invited_email', $input) ? 'invited_email' : 'email';
            $email = $this->normalizeRequiredEmail(FinDeskV2Support::requireString($input, $emailKey, 190));
            $name = FinDeskV2Support::optionalString($input, 'name', null, 190);
            $expiresDays = max(1, min(30, $this->optionalInt($input, 'expires_days', 7)));
            $token = bin2hex(random_bytes(24));
            $tokenHash = hash('sha256', $token);
            $inviteId = FinDeskV2Support::uuid();

            $this->db->prepare("
                INSERT INTO v2_workspace_invites (
                    id, workspace_id, token_hash, token_hint, invited_email, invited_name,
                    role, access_scope, status, expires_at, created_by
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL {$expiresDays} DAY), ?)
            ")->execute([
                $inviteId,
                $workspaceId,
                $tokenHash,
                substr($token, 0, 8),
                $email,
                $name,
                $role,
                $accessScope,
                $userId,
            ]);

            $row = $this->workspaceInviteById($inviteId, $workspaceId);
            $invite = $this->workspaceInviteRow($row, true, $token);
            $this->audit($workspaceId, 'workspace_invite', $inviteId, 'create', null, $this->workspaceInviteAuditPayload($invite), $userId);

            return $invite + [
                'workspace' => [
                    'id' => $workspace['id'],
                    'name' => $workspace['name'],
                ],
            ];
        });
    }

    public function getWorkspaceInviteByToken(string $token, int $userId): array
    {
        $this->ensureWorkspaceInviteSchema();
        $row = $this->workspaceInviteByToken($token);
        $this->assertWorkspaceInvitePending($row);
        $workspace = $this->workspaceRowById((string)$row['workspace_id']);
        $email = $this->userEmail($userId);

        return [
            'invite' => $this->workspaceInviteRow($row, false),
            'workspace' => [
                'id' => (string)$workspace['id'],
                'name' => (string)$workspace['name'],
                'type' => (string)$workspace['type'],
            ],
            'email_matches' => $row['invited_email'] === null || $email === (string)$row['invited_email'],
        ];
    }

    public function acceptWorkspaceInvite(string $token, int $userId): array
    {
        $this->ensureWorkspaceInviteSchema();

        return FinDeskV2Database::transact(function () use ($token, $userId): array {
            $row = $this->workspaceInviteByToken($token, true);
            $this->assertWorkspaceInvitePending($row);
            $workspaceId = (string)$row['workspace_id'];
            $email = $this->userEmail($userId);
            if ($row['invited_email'] !== null && $email !== (string)$row['invited_email']) {
                throw new FinDeskV2HttpError(403, 'invite_email_mismatch');
            }
            if ($this->workspaceMemberExists($workspaceId, $userId)) {
                throw new FinDeskV2HttpError(409, 'workspace_member_exists');
            }

            try {
                $this->db->prepare("
                    INSERT INTO v2_workspace_members (id, workspace_id, user_id, role, access_scope, assigned_actor_id)
                    VALUES (?, ?, ?, 'employee', 'own_entries', NULL)
                ")->execute([
                    FinDeskV2Support::uuid(),
                    $workspaceId,
                    $userId,
                ]);
            } catch (PDOException $e) {
                if ($this->isDuplicateKey($e)) {
                    throw new FinDeskV2HttpError(409, 'workspace_member_exists');
                }
                throw $e;
            }
            $this->db->prepare("
                UPDATE v2_workspace_invites
                SET status = 'accepted',
                    accepted_at = NOW(),
                    accepted_by = ?
                WHERE id = ?
            ")->execute([$userId, $row['id']]);

            $updated = $this->workspaceInviteById((string)$row['id'], $workspaceId);
            $workspace = $this->getWorkspace($workspaceId, $userId);
            $payload = [
                'invite' => $this->workspaceInviteRow($updated, false),
                'workspace' => $workspace,
            ];
            $this->audit($workspaceId, 'workspace_invite', (string)$row['id'], 'accept', $this->workspaceInviteAuditPayload($this->workspaceInviteRow($row, false)), $this->workspaceInviteAuditPayload($payload['invite']), $userId);

            return $payload;
        });
    }

    public function revokeWorkspaceInvite(string $workspaceId, string $inviteId, int $userId): array
    {
        $this->ensureWorkspaceInviteSchema();

        return FinDeskV2Database::transact(function () use ($workspaceId, $inviteId, $userId): array {
            $this->requireWorkspaceOwnerAdmin($workspaceId, $userId);
            $row = $this->workspaceInviteById($inviteId, $workspaceId);
            if ((string)$row['status'] !== 'pending') {
                throw new FinDeskV2HttpError(409, 'invite_not_pending');
            }
            $this->db->prepare("
                UPDATE v2_workspace_invites
                SET status = 'revoked',
                    revoked_at = NOW(),
                    revoked_by = ?
                WHERE id = ?
            ")->execute([$userId, $inviteId]);
            $updated = $this->workspaceInviteById($inviteId, $workspaceId);
            $invite = $this->workspaceInviteRow($updated, false);
            $this->audit($workspaceId, 'workspace_invite', $inviteId, 'revoke', $this->workspaceInviteAuditPayload($this->workspaceInviteRow($row, false)), $this->workspaceInviteAuditPayload($invite), $userId);

            return $invite;
        });
    }

    public function getEmployeeWorkspaceMode(string $workspaceId, int $userId): array
    {
        $workspace = $this->getWorkspace($workspaceId, $userId);
        $access = $this->getWorkspaceAccess($workspaceId, $userId);
        if ($access['can_read_workspace']) {
            throw new FinDeskV2HttpError(422, 'employee_mode_not_required');
        }

        $offers = $this->listEmployeeAccountableOffers($workspaceId, $userId);
        $reports = $this->listAccountableReports($workspaceId, [], $userId);
        $pendingTotal = 0.0;
        $acceptedTotal = 0.0;
        foreach ($offers as $offer) {
            if ((string)$offer['status'] === 'pending_offer') {
                $pendingTotal += (float)$offer['amount'];
            }
            if ((string)$offer['status'] === 'accepted_by_employee') {
                $acceptedTotal += (float)$offer['amount'];
            }
        }

        return [
            'workspace' => $workspace,
            'offers' => $offers,
            'reports' => $reports,
            'summary' => [
                'pending_total' => $pendingTotal,
                'accepted_total' => $acceptedTotal,
                'draft_reports' => count(array_filter($reports, static fn (array $report): bool => (string)$report['status'] === 'draft')),
                'submitted_reports' => count(array_filter($reports, static fn (array $report): bool => (string)$report['status'] === 'submitted')),
                'open_offers' => count(array_filter($offers, static fn (array $offer): bool => in_array((string)$offer['status'], ['pending_offer', 'accepted_by_employee'], true))),
            ],
        ];
    }

    public function listAccountableOffers(string $workspaceId, int $userId): array
    {
        $this->ensureAccountableOfferSchema();
        $access = $this->getWorkspaceAccess($workspaceId, $userId);
        if (!$access['can_admin']) {
            if ((string)$access['role'] !== 'employee') {
                throw new FinDeskV2HttpError(403, 'workspace_admin_required');
            }
            return $this->listEmployeeAccountableOffers($workspaceId, $userId);
        }
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_accountable_offers
            WHERE workspace_id = ?
            ORDER BY created_at DESC, id DESC
            LIMIT 200
        ");
        $stmt->execute([$workspaceId]);

        return array_map([$this, 'accountableOfferRow'], $stmt->fetchAll());
    }

    public function getAccountableDashboard(string $workspaceId, int $userId): array
    {
        $this->ensureAccountableReportSchema();
        $workspace = $this->getWorkspace($workspaceId, $userId);
        $this->requireWorkspaceOwnerAdmin($workspaceId, $userId);

        $offersStmt = $this->db->prepare("
            SELECT *
            FROM v2_accountable_offers
            WHERE workspace_id = ?
            ORDER BY created_at DESC, id DESC
            LIMIT 300
        ");
        $offersStmt->execute([$workspaceId]);
        $offers = array_map([$this, 'accountableOfferRow'], $offersStmt->fetchAll());

        $reportsStmt = $this->db->prepare("
            SELECT *
            FROM v2_accountable_reports
            WHERE workspace_id = ?
            ORDER BY FIELD(status, 'submitted', 'accepted_by_admin', 'draft', 'rework_requested', 'rejected', 'cancelled'),
                     submitted_at DESC, reviewed_at DESC, created_at DESC, id DESC
            LIMIT 300
        ");
        $reportsStmt->execute([$workspaceId]);
        $reports = [];
        foreach ($reportsStmt->fetchAll() as $row) {
            $report = $this->accountableReportRow($row, false);
            $report['settlement'] = $this->accountableSettlementForReport((string)$row['id']);
            $reports[] = $report;
        }

        $employees = [];
        $offersById = [];
        $summary = [
            'currency' => (string)$workspace['currency'],
            'policy' => 'cash_card_effect_none_read_model',
            'cash_delta' => 0.0,
            'card_delta' => 0.0,
            'pending_offer_total' => 0.0,
            'issued_total' => 0.0,
            'submitted_report_total' => 0.0,
            'accepted_report_total' => 0.0,
            'accepted_cash_expenses_total' => 0.0,
            'accepted_noncash_expenses_total' => 0.0,
            'not_materialized_total' => 0.0,
            'materialized_total' => 0.0,
            'return_due_total' => 0.0,
            'reimburse_due_total' => 0.0,
            'return_due_gross_total' => 0.0,
            'reimburse_due_gross_total' => 0.0,
            'settled_return_total' => 0.0,
            'settled_reimburse_total' => 0.0,
            'open_position_total' => 0.0,
            'offer_count' => count($offers),
            'report_count' => count($reports),
            'submitted_report_count' => 0,
            'accepted_report_count' => 0,
            'not_materialized_report_count' => 0,
            'materialized_report_count' => 0,
        ];

        foreach ($offers as $offer) {
            $offersById[(string)$offer['id']] = $offer;
            $key = $this->accountableEmployeeKey($offer['employee_user_id'] ?? null, $offer['employee_email'] ?? '');
            if (!isset($employees[$key])) {
                $employees[$key] = $this->emptyAccountableDashboardEmployee($offer['employee_user_id'] ?? null, (string)($offer['employee_email'] ?? ''), (string)$workspace['currency']);
            }
            $employees[$key]['offers'][] = $this->accountableDashboardOfferRow($offer);

            $status = (string)$offer['status'];
            $amount = (float)$offer['amount'];
            if ($status === 'pending_offer') {
                $employees[$key]['metrics']['pending_offer_total'] += $amount;
                $summary['pending_offer_total'] += $amount;
            }
            if ($status === 'accepted_by_employee') {
                $employees[$key]['metrics']['issued_total'] += $amount;
                $summary['issued_total'] += $amount;
            }
        }

        foreach ($reports as $report) {
            $offer = $offersById[(string)($report['offer_id'] ?? '')] ?? null;
            $key = $this->accountableEmployeeKey(
                $offer['employee_user_id'] ?? ($report['employee_user_id'] ?? null),
                $offer['employee_email'] ?? ''
            );
            if (!isset($employees[$key])) {
                $employees[$key] = $this->emptyAccountableDashboardEmployee(
                    $offer['employee_user_id'] ?? ($report['employee_user_id'] ?? null),
                    (string)($offer['employee_email'] ?? ''),
                    (string)$workspace['currency']
                );
            }
            $employees[$key]['reports'][] = $this->accountableDashboardReportRow($report);

            $status = (string)$report['status'];
            $ledgerStatus = (string)($report['ledger_materialization_status'] ?? 'not_materialized');
            if ($status === 'submitted') {
                $employees[$key]['metrics']['submitted_report_total'] += (float)$report['total_amount'];
                $employees[$key]['metrics']['submitted_report_count']++;
                $summary['submitted_report_total'] += (float)$report['total_amount'];
                $summary['submitted_report_count']++;
            }
            if ($status === 'accepted_by_admin') {
                $acceptedAmount = (float)$report['accepted_total_amount'];
                $employees[$key]['metrics']['accepted_report_total'] += $acceptedAmount;
                $employees[$key]['metrics']['accepted_cash_expenses_total'] += (float)$report['accepted_cash_expenses'];
                $employees[$key]['metrics']['accepted_noncash_expenses_total'] += (float)$report['accepted_noncash_expenses'];
                $employees[$key]['metrics']['accepted_report_count']++;
                $summary['accepted_report_total'] += $acceptedAmount;
                $summary['accepted_cash_expenses_total'] += (float)$report['accepted_cash_expenses'];
                $summary['accepted_noncash_expenses_total'] += (float)$report['accepted_noncash_expenses'];
                $summary['accepted_report_count']++;
                if ($ledgerStatus === 'materialized') {
                    $employees[$key]['metrics']['materialized_total'] += $acceptedAmount;
                    $employees[$key]['metrics']['materialized_report_count']++;
                    $summary['materialized_total'] += $acceptedAmount;
                    $summary['materialized_report_count']++;
                } else {
                    $employees[$key]['metrics']['not_materialized_total'] += $acceptedAmount;
                    $employees[$key]['metrics']['not_materialized_report_count']++;
                    $summary['not_materialized_total'] += $acceptedAmount;
                    $summary['not_materialized_report_count']++;
                }
            }

            $settlement = $report['settlement'] ?? null;
            if (is_array($settlement)) {
                $returnDueGross = (float)($settlement['return_due_amount'] ?? 0);
                $reimburseDueGross = (float)($settlement['reimburse_due_amount'] ?? 0);
                $resolvedAmount = (string)($settlement['resolution_status'] ?? 'open') === 'resolved'
                    ? (float)($settlement['resolved_amount'] ?? 0)
                    : 0.0;
                $settledReturn = (string)($settlement['status'] ?? '') === 'return_due'
                    ? min($returnDueGross, $resolvedAmount)
                    : 0.0;
                $settledReimburse = (string)($settlement['status'] ?? '') === 'reimburse_due'
                    ? min($reimburseDueGross, $resolvedAmount)
                    : 0.0;
                $returnDue = max($returnDueGross - $settledReturn, 0.0);
                $reimburseDue = max($reimburseDueGross - $settledReimburse, 0.0);
                $employees[$key]['metrics']['return_due_total'] += $returnDue;
                $employees[$key]['metrics']['reimburse_due_total'] += $reimburseDue;
                $employees[$key]['metrics']['return_due_gross_total'] += $returnDueGross;
                $employees[$key]['metrics']['reimburse_due_gross_total'] += $reimburseDueGross;
                $employees[$key]['metrics']['settled_return_total'] += $settledReturn;
                $employees[$key]['metrics']['settled_reimburse_total'] += $settledReimburse;
                $summary['return_due_total'] += $returnDue;
                $summary['reimburse_due_total'] += $reimburseDue;
                $summary['return_due_gross_total'] += $returnDueGross;
                $summary['reimburse_due_gross_total'] += $reimburseDueGross;
                $summary['settled_return_total'] += $settledReturn;
                $summary['settled_reimburse_total'] += $settledReimburse;
            }
        }

        foreach ($employees as &$employee) {
            $employee['metrics']['open_position_total'] = max(
                $employee['metrics']['issued_total'] - $employee['metrics']['accepted_report_total'] - $employee['metrics']['settled_return_total'],
                0
            );
            $summary['open_position_total'] += $employee['metrics']['open_position_total'];
            $employee['offer_count'] = count($employee['offers']);
            $employee['report_count'] = count($employee['reports']);
        }
        unset($employee);

        usort($employees, static function (array $left, array $right): int {
            $leftHot = ($left['metrics']['submitted_report_count'] * 1000)
                + ($left['metrics']['not_materialized_report_count'] * 100)
                + (int)round($left['metrics']['open_position_total']);
            $rightHot = ($right['metrics']['submitted_report_count'] * 1000)
                + ($right['metrics']['not_materialized_report_count'] * 100)
                + (int)round($right['metrics']['open_position_total']);

            return $rightHot <=> $leftHot;
        });

        return [
            'workspace_id' => $workspaceId,
            'workspace_name' => (string)$workspace['name'],
            'currency' => (string)$workspace['currency'],
            'summary' => $summary,
            'employees' => array_values($employees),
        ];
    }

    public function createAccountableOffer(string $workspaceId, array $input, int $userId): array
    {
        $this->ensureAccountableOfferSchema();

        return FinDeskV2Database::transact(function () use ($workspaceId, $input, $userId): array {
            $workspace = $this->getWorkspace($workspaceId, $userId);
            $this->requireWorkspaceOwnerAdmin($workspaceId, $userId);
            $target = $this->normalizeAccountableOfferTarget($workspaceId, $input);
            $amount = FinDeskV2Support::nullableAmount($input['amount'] ?? null);
            if ($amount === null || (float)$amount <= 0) {
                throw new FinDeskV2HttpError(422, 'invalid_amount');
            }
            $currency = strtoupper(FinDeskV2Support::optionalString($input, 'currency', (string)$workspace['currency'], 3) ?? (string)$workspace['currency']);
            if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
                throw new FinDeskV2HttpError(422, 'invalid_currency');
            }
            if (!array_key_exists('purpose', $input) && !array_key_exists('comment', $input)) {
                throw new FinDeskV2HttpError(422, 'missing_purpose');
            }
            $purposeKey = array_key_exists('purpose', $input) ? 'purpose' : 'comment';
            $purpose = FinDeskV2Support::requireString($input, $purposeKey, 1000);
            $offerId = FinDeskV2Support::uuid();

            $this->db->prepare("
                INSERT INTO v2_accountable_offers (
                    id, workspace_id, employee_user_id, employee_email, amount, currency, purpose,
                    status, created_by
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_offer', ?)
            ")->execute([
                $offerId,
                $workspaceId,
                $target['employee_user_id'],
                $target['employee_email'],
                $amount,
                $currency,
                $purpose,
                $userId,
            ]);

            $offer = $this->accountableOfferById($offerId);
            $row = $this->accountableOfferRow($offer);
            $this->audit($workspaceId, 'accountable_offer', $offerId, 'create', null, $row, $userId);

            return $row;
        });
    }

    public function acceptAccountableOffer(string $offerId, int $userId): array
    {
        $this->ensureAccountableOfferSchema();

        return FinDeskV2Database::transact(function () use ($offerId, $userId): array {
            $offer = $this->accountableOfferById($offerId, true);
            $this->assertAccountableOfferVisibleToEmployee($offer, $userId);
            if ((string)$offer['status'] !== 'pending_offer') {
                throw new FinDeskV2HttpError(409, 'accountable_offer_not_pending');
            }

            $before = $this->accountableOfferRow($offer);
            $this->db->prepare("
                UPDATE v2_accountable_offers
                SET status = 'accepted_by_employee',
                    employee_user_id = COALESCE(employee_user_id, ?),
                    accepted_at = NOW(),
                    accepted_by = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ")->execute([$userId, $userId, $offerId]);
            $after = $this->accountableOfferRow($this->accountableOfferById($offerId));
            $this->audit((string)$offer['workspace_id'], 'accountable_offer', $offerId, 'accept_by_employee', $before, $after, $userId);

            return $after;
        });
    }

    public function listAccountableReports(string $workspaceId, array $query, int $userId): array
    {
        $this->ensureAccountableReportSchema();
        $access = $this->getWorkspaceAccess($workspaceId, $userId);
        if (!$access['can_admin']) {
            if ((string)$access['role'] !== 'employee') {
                throw new FinDeskV2HttpError(403, 'workspace_admin_required');
            }

            $status = FinDeskV2Support::optionalString($query, 'status', null, 40);
            if ($status !== null) {
                $status = FinDeskV2Support::enum($status, ['draft', 'submitted', 'cancelled'], 'status');
            }
            $params = [$workspaceId, $userId];
            $statusSql = '';
            if ($status !== null) {
                $statusSql = ' AND r.status = ?';
                $params[] = $status;
            }
            $stmt = $this->db->prepare("
                SELECT r.*
                FROM v2_accountable_reports r
                WHERE r.workspace_id = ?
                  AND r.employee_user_id = ?
                  {$statusSql}
                ORDER BY r.created_at DESC, r.id DESC
                LIMIT 100
            ");
            $stmt->execute($params);

            return array_map(fn (array $row): array => $this->accountableReportRow($row, true), $stmt->fetchAll());
        }

        $status = FinDeskV2Support::optionalString($query, 'status', null, 40);
        $params = [$workspaceId];
        $statusSql = "AND status = 'submitted'";
        if ($status !== null) {
            $status = FinDeskV2Support::enum($status, ['submitted', 'accepted_by_admin', 'hall_open'], 'status');
            if ($status === 'hall_open') {
                $statusSql = "AND status IN ('submitted', 'accepted_by_admin')";
            } else {
                $statusSql = 'AND status = ?';
                $params[] = $status;
            }
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_accountable_reports
            WHERE workspace_id = ?
              {$statusSql}
            ORDER BY FIELD(status, 'submitted', 'accepted_by_admin'), submitted_at DESC, reviewed_at DESC, created_at DESC, id DESC
            LIMIT 200
        ");
        $stmt->execute($params);

        return array_map(fn (array $row): array => $this->accountableReportRow($row, true), $stmt->fetchAll());
    }

    public function createAccountableReport(string $workspaceId, array $input, int $userId): array
    {
        $this->ensureAccountableReportSchema();

        return FinDeskV2Database::transact(function () use ($workspaceId, $input, $userId): array {
            $access = $this->getWorkspaceAccess($workspaceId, $userId);
            if ((string)$access['role'] !== 'employee') {
                throw new FinDeskV2HttpError(403, 'employee_scope_required');
            }
            if (!(bool)$access['can_write_scoped_entries']) {
                throw new FinDeskV2HttpError(403, 'workspace_scope_required');
            }

            $offerId = FinDeskV2Support::requireString($input, 'offer_id', 36);
            $offer = $this->accountableOfferById($offerId, true);
            if ((string)$offer['workspace_id'] !== $workspaceId) {
                throw new FinDeskV2HttpError(404, 'accountable_offer_not_found');
            }
            $this->assertAccountableOfferVisibleToEmployee($offer, $userId);
            if ((string)$offer['status'] !== 'accepted_by_employee') {
                throw new FinDeskV2HttpError(409, 'accountable_offer_not_accepted');
            }

            $rows = $this->accountableReportInputRows($input, (string)$offer['currency']);
            $reportId = FinDeskV2Support::uuid();
            $title = FinDeskV2Support::optionalString($input, 'title', null, 190)
                ?? FinDeskV2Support::optionalString($input, 'comment', null, 190)
                ?? 'Accountable expense report';
            $total = array_sum(array_map(static fn (array $row): float => (float)$row['amount'], $rows));

            $this->db->prepare("
                INSERT INTO v2_accountable_reports (
                    id, workspace_id, offer_id, employee_user_id, title, status,
                    currency, total_amount, row_count, created_by
                )
                VALUES (?, ?, ?, ?, ?, 'draft', ?, ?, ?, ?)
            ")->execute([
                $reportId,
                $workspaceId,
                $offerId,
                $userId,
                $title,
                (string)$offer['currency'],
                number_format($total, 2, '.', ''),
                count($rows),
                $userId,
            ]);

            foreach ($rows as $index => $row) {
                $this->db->prepare("
                    INSERT INTO v2_accountable_report_rows (
                        id, report_id, `row_number`, expense_date, description,
                        amount, currency, category_code, notes
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    FinDeskV2Support::uuid(),
                    $reportId,
                    $index + 1,
                    $row['date'],
                    $row['description'],
                    $row['amount'],
                    $row['currency'],
                    $row['category_code'],
                    $row['notes'],
                ]);
            }

            $report = $this->accountableReportRow($this->accountableReportById($reportId), true);
            $this->audit($workspaceId, 'accountable_report', $reportId, 'create_draft', null, $report, $userId);

            return $report;
        });
    }

    public function submitAccountableReport(string $reportId, int $userId): array
    {
        $this->ensureAccountableReportSchema();

        return FinDeskV2Database::transact(function () use ($reportId, $userId): array {
            $report = $this->accountableReportById($reportId, true);
            $this->assertAccountableReportOwnedByEmployee($report, $userId);
            if ((string)$report['status'] !== 'draft') {
                throw new FinDeskV2HttpError(409, 'accountable_report_not_draft');
            }
            if ((int)$report['row_count'] < 1) {
                throw new FinDeskV2HttpError(422, 'accountable_report_empty');
            }

            $before = $this->accountableReportRow($report, true);
            $this->db->prepare("
                UPDATE v2_accountable_reports
                SET status = 'submitted',
                    submitted_at = NOW(),
                    submitted_by = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ")->execute([$userId, $reportId]);
            $after = $this->accountableReportRow($this->accountableReportById($reportId), true);
            $this->audit((string)$report['workspace_id'], 'accountable_report', $reportId, 'submit', $before, $after, $userId);

            return $after;
        });
    }

    public function getAccountableReport(string $reportId, int $userId): array
    {
        $this->ensureAccountableReportSchema();
        $report = $this->accountableReportById($reportId);
        $this->assertAccountableReportVisibleForReview($report, $userId);

        return $this->accountableReportRow($report, true);
    }

    public function previewAccountableReportReview(string $reportId, array $input, int $userId): array
    {
        $this->ensureAccountableReportSchema();
        $report = $this->accountableReportById($reportId);
        $this->requireAccountableReportAdmin($report, $userId);
        $offer = $this->accountableOfferById((string)$report['offer_id']);

        return $this->accountableReportReviewPlan($report, $offer, $input);
    }

    public function acceptAccountableReportByAdmin(string $reportId, array $input, int $userId): array
    {
        $this->ensureAccountableReportSchema();

        return FinDeskV2Database::transact(function () use ($reportId, $input, $userId): array {
            $report = $this->accountableReportById($reportId, true);
            $this->requireAccountableReportAdmin($report, $userId);
            if ((string)$report['status'] !== 'submitted') {
                throw new FinDeskV2HttpError(409, 'accountable_report_not_submitted');
            }

            $offer = $this->accountableOfferById((string)$report['offer_id'], true);
            $before = $this->accountableReportRow($report, true);
            $plan = $this->accountableReportReviewPlan($report, $offer, $input);
            $note = FinDeskV2Support::optionalString($input, 'review_note', null, 1000)
                ?? FinDeskV2Support::optionalString($input, 'note', null, 1000);

            $this->db->prepare("
                UPDATE v2_accountable_reports
                SET status = 'accepted_by_admin',
                    reviewed_at = NOW(),
                    reviewed_by = ?,
                    review_note = ?,
                    accepted_total_amount = ?,
                    rejected_total_amount = ?,
                    accepted_cash_expenses = ?,
                    accepted_noncash_expenses = ?,
                    settlement_status = ?,
                    materialized_at = NOW(),
                    no_financial_mutation = 1,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ")->execute([
                $userId,
                $note,
                $plan['accepted_total_amount'],
                $plan['rejected_total_amount'],
                $plan['accepted_cash_expenses'],
                $plan['accepted_noncash_expenses'],
                $plan['settlement']['status'],
                $reportId,
            ]);

            $updateRow = $this->db->prepare("
                UPDATE v2_accountable_report_rows
                SET review_status = ?,
                    accepted_amount = ?,
                    accepted_category_code = ?,
                    payment_method = ?,
                    review_note = ?
                WHERE id = ? AND report_id = ?
            ");
            foreach ($plan['rows'] as $row) {
                $updateRow->execute([
                    $row['review_status'],
                    $row['accepted_amount'],
                    $row['accepted_category_code'],
                    $row['payment_method'],
                    $row['review_note'],
                    $row['id'],
                    $reportId,
                ]);
            }

            $settlement = $plan['settlement'];
            $settlementId = FinDeskV2Support::uuid();
            $resolutionStatus = $settlement['status'] === 'closed' ? 'resolved' : 'open';
            $this->db->prepare("
                INSERT INTO v2_accountable_settlements (
                    id, workspace_id, offer_id, report_id, employee_user_id, issued_amount,
                    accepted_cash_expenses, accepted_noncash_expenses, expected_remaining,
                    actual_remaining, return_due_amount, reimburse_due_amount, difference_amount,
                    status, resolution_status, created_by
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $settlementId,
                (string)$report['workspace_id'],
                (string)$report['offer_id'],
                $reportId,
                (int)$report['employee_user_id'],
                $settlement['issued_amount'],
                $settlement['accepted_cash_expenses'],
                $settlement['accepted_noncash_expenses'],
                $settlement['expected_remaining'],
                $settlement['actual_remaining'],
                $settlement['return_due_amount'],
                $settlement['reimburse_due_amount'],
                $settlement['difference_amount'],
                $settlement['status'],
                $resolutionStatus,
                $userId,
            ]);

            $after = $this->accountableReportRow($this->accountableReportById($reportId), true);
            $this->audit((string)$report['workspace_id'], 'accountable_report', $reportId, 'accept_by_admin', $before, [
                'report' => $after,
                'settlement' => $after['settlement'] ?? null,
                'materialized_entries' => [],
            ], $userId);

            return [
                'report' => $after,
                'settlement' => $after['settlement'] ?? null,
                'materialized_entries' => [],
            ];
        });
    }

    public function getAccountableReportMaterialization(string $reportId, int $userId): array
    {
        $this->ensureAccountableReportSchema();
        $report = $this->accountableReportById($reportId);
        $this->requireAccountableReportAdmin($report, $userId);

        return $this->accountableReportMaterializationResult($reportId, $userId);
    }

    public function previewAccountableReportMaterialization(string $reportId, int $userId): array
    {
        $this->ensureAccountableReportSchema();
        $report = $this->accountableReportById($reportId);
        $this->requireAccountableReportAdmin($report, $userId);

        return $this->accountableReportMaterializationPlan($report);
    }

    public function materializeAccountableReport(string $reportId, int $userId): array
    {
        $this->ensureAccountableReportSchema();

        return FinDeskV2Database::transact(function () use ($reportId, $userId): array {
            $report = $this->accountableReportById($reportId, true);
            $this->requireAccountableReportAdmin($report, $userId);
            if ((string)$report['status'] !== 'accepted_by_admin') {
                throw new FinDeskV2HttpError(409, 'accountable_report_not_accepted_by_admin');
            }

            $before = $this->accountableReportMaterializationResult($reportId, $userId);
            $plan = $this->accountableReportMaterializationPlan($report);
            if ($plan['eligible_row_count'] < 1) {
                throw new FinDeskV2HttpError(422, 'accountable_report_no_materializable_rows');
            }

            $existing = $this->accountableReportEntryLinksByRow($reportId);
            $createdEntries = [];
            $flow = $this->accountableProjectionFlowForWorkspace((string)$report['workspace_id'], $userId);
            foreach ($plan['rows'] as $row) {
                if (!$row['materializable']) {
                    continue;
                }
                if (isset($existing[$row['id']])) {
                    continue;
                }

                $entryId = FinDeskV2Support::uuid();
                $categoryId = $this->categoryIdByCode((string)$report['workspace_id'], (string)$row['category_code']);
                $matchedRules = [[
                    'source' => 'accountable_report_materialization',
                    'report_id' => $reportId,
                    'report_row_id' => $row['id'],
                    'offer_id' => (string)$report['offer_id'],
                    'payment_method' => $row['payment_method'],
                    'cash_effect' => 'none',
                ]];
                $this->guardWorkspaceMonthIsOpen((string)$report['workspace_id'], (string)$row['expense_date'], ['allow_locked_entries' => false]);
                $this->db->prepare("
                    INSERT INTO v2_entries (
                        id, workspace_id, flow_id, created_by, actor_id, date, raw_text, sign, amount, direction,
                        entry_type, category_id, status, source_type, source_id, source_row_id, notes, confidence, matched_rules_json
                    )
                    VALUES (?, ?, ?, ?, NULL, ?, ?, '-', ?, 'out',
                        'accountable_expense', ?, 'accepted', 'accountable_report', NULL, NULL, ?, 1.000, ?)
                ")->execute([
                    $entryId,
                    (string)$report['workspace_id'],
                    $flow['id'],
                    $userId,
                    (string)$row['expense_date'],
                    '-' . number_format((float)$row['accepted_amount'], 2, '.', '') . ' ' . (string)$row['description'],
                    number_format((float)$row['accepted_amount'], 2, '.', ''),
                    $categoryId,
                    'Projection from employee accountable report. Cash effect: none.',
                    FinDeskV2Support::jsonEncode($matchedRules),
                ]);

                $linkId = FinDeskV2Support::uuid();
                $this->db->prepare("
                    INSERT INTO v2_accountable_report_entry_links (
                        id, workspace_id, report_id, report_row_id, entry_id, idempotency_key,
                        cash_effect, payment_method, accepted_amount, category_code, created_by
                    )
                    VALUES (?, ?, ?, ?, ?, ?, 'none', ?, ?, ?, ?)
                ")->execute([
                    $linkId,
                    (string)$report['workspace_id'],
                    $reportId,
                    $row['id'],
                    $entryId,
                    $row['idempotency_key'],
                    $row['payment_method'],
                    number_format((float)$row['accepted_amount'], 2, '.', ''),
                    $row['category_code'],
                    $userId,
                ]);
                $this->db->prepare("
                    UPDATE v2_accountable_report_rows
                    SET operational_entry_id = ?
                    WHERE id = ? AND report_id = ?
                ")->execute([$entryId, $row['id'], $reportId]);
                $createdEntries[] = $this->getEntry($entryId, $userId);
            }

            $afterLinks = $this->accountableReportEntryLinks($reportId);
            $hash = hash('sha256', FinDeskV2Support::jsonEncode(array_map(static fn (array $link): string => (string)$link['idempotency_key'], $afterLinks)));
            $this->db->prepare("
                UPDATE v2_accountable_reports
                SET ledger_materialization_status = 'materialized',
                    ledger_materialized_at = COALESCE(ledger_materialized_at, NOW()),
                    ledger_materialized_by = COALESCE(ledger_materialized_by, ?),
                    ledger_materialization_hash = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ")->execute([$userId, $hash, $reportId]);

            $after = $this->accountableReportMaterializationResult($reportId, $userId);
            $this->audit((string)$report['workspace_id'], 'accountable_report', $reportId, 'ledger_project', $before, $after, $userId);

            return [
                'materialization' => $after,
                'created_entries' => $createdEntries,
            ];
        });
    }

    public function resolveAccountableSettlement(string $settlementId, array $input, int $userId): array
    {
        $this->ensureAccountableReportSchema();

        return FinDeskV2Database::transact(function () use ($settlementId, $input, $userId): array {
            $settlement = $this->accountableSettlementById($settlementId, true);
            $workspaceId = (string)$settlement['workspace_id'];
            $this->requireWorkspaceOwnerAdmin($workspaceId, $userId);

            if ((string)$settlement['resolution_status'] === 'resolved') {
                return $this->accountableSettlementRow($settlement);
            }

            $status = (string)$settlement['status'];
            if (!in_array($status, ['return_due', 'reimburse_due'], true)) {
                throw new FinDeskV2HttpError(409, 'accountable_settlement_not_open');
            }

            $entryId = FinDeskV2Support::requireString($input, 'entry_id', 36);
            $entry = $this->getEntry($entryId, $userId);
            if ((string)$entry['workspace_id'] !== $workspaceId) {
                throw new FinDeskV2HttpError(422, 'settlement_entry_workspace_mismatch');
            }
            if (($entry['flow']['type'] ?? null) !== 'cash') {
                throw new FinDeskV2HttpError(422, 'settlement_entry_cash_required');
            }
            if (($entry['category_code'] ?? null) !== null) {
                throw new FinDeskV2HttpError(422, 'settlement_entry_must_be_balance_only');
            }

            $expectedAmount = $status === 'return_due'
                ? (float)$settlement['return_due_amount']
                : (float)$settlement['reimburse_due_amount'];
            $expectedDirection = $status === 'return_due' ? 'in' : 'out';
            if (($entry['direction'] ?? null) !== $expectedDirection) {
                throw new FinDeskV2HttpError(422, 'settlement_entry_direction_mismatch');
            }
            if (abs((float)$entry['amount'] - $expectedAmount) > 0.004) {
                throw new FinDeskV2HttpError(422, 'settlement_entry_amount_mismatch');
            }

            return $this->resolveAccountableSettlementInCurrentTransaction($settlement, $entry, $input, $userId);
        });
    }

    public function resolveAccountableSettlementWithCashMovement(string $settlementId, array $input, int $userId): array
    {
        $this->ensureAccountableReportSchema();

        return FinDeskV2Database::transact(function () use ($settlementId, $input, $userId): array {
            $settlement = $this->accountableSettlementById($settlementId, true);
            $workspaceId = (string)$settlement['workspace_id'];
            $this->requireWorkspaceOwnerAdmin($workspaceId, $userId);

            if ((string)$settlement['resolution_status'] === 'resolved') {
                return [
                    'settlement' => $this->accountableSettlementRow($settlement),
                    'entry' => null,
                ];
            }

            $status = (string)$settlement['status'];
            if (!in_array($status, ['return_due', 'reimburse_due'], true)) {
                throw new FinDeskV2HttpError(409, 'accountable_settlement_not_open');
            }

            $cashFlow = $this->cashFlowForWorkspace($workspaceId, $userId);
            if ($cashFlow === null) {
                throw new FinDeskV2HttpError(422, 'cash_flow_required');
            }

            $amount = $status === 'return_due'
                ? (float)$settlement['return_due_amount']
                : (float)$settlement['reimburse_due_amount'];
            $sign = $status === 'return_due' ? '+' : '-';
            $defaultText = $status === 'return_due'
                ? 'возврат подотчетного остатка'
                : 'физическое возмещение перерасхода сотруднику';
            $rawText = FinDeskV2Support::optionalString($input, 'raw_text', null, 2000)
                ?? ($sign . number_format($amount, 2, '.', '') . ' ' . $defaultText);
            if (preg_match('/^[+-]\s*[0-9]+(?:[.,][0-9]{1,2})?/u', $rawText) !== 1) {
                $rawText = $sign . number_format($amount, 2, '.', '') . ' ' . $rawText;
            }

            $entry = $this->createEntryInCurrentTransaction($workspaceId, [
                'flow_id' => $cashFlow['id'],
                'date' => FinDeskV2Support::date($input),
                'raw_text' => $rawText,
                'status' => 'recognized',
                'notes' => FinDeskV2Support::optionalString($input, 'note', null, 1000)
                    ?? 'Physical cash settlement for accountable report.',
                'matched_rules' => [[
                    'source' => 'accountable_settlement_resolution',
                    'settlement_id' => $settlementId,
                    'cash_effect' => $status === 'return_due' ? 'cash_in' : 'cash_out',
                ]],
            ], $userId);

            $resolved = $this->resolveAccountableSettlementInCurrentTransaction($settlement, $entry, $input, $userId);

            return [
                'settlement' => $resolved,
                'entry' => $entry,
            ];
        });
    }

    public function listFlows(string $workspaceId, int $userId): array
    {
        $this->requireWorkspaceFullReader($workspaceId, $userId);
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_flows
            WHERE workspace_id = ?
            ORDER BY is_default DESC, FIELD(type, 'cash', 'card', 'assistant_journal', 'accountable'), name
        ");
        $stmt->execute([$workspaceId]);

        return array_map([$this, 'flowRow'], $stmt->fetchAll());
    }

    public function createFlow(string $workspaceId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $input, $userId): array {
            $this->getWorkspace($workspaceId, $userId);
            $this->requireWorkspaceWriter($workspaceId, $userId);
            $flow = $this->createDefaultFlow(
                $workspaceId,
                FinDeskV2Support::requireString($input, 'name', 120),
                FinDeskV2Support::enum((string)($input['type'] ?? ''), ['cash', 'card', 'assistant_journal', 'accountable'], 'type'),
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
        $this->requireWorkspaceFullReader($workspaceId, $userId);
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

        $cardBalance = 0.0;
        $cardBalanceAvailable = false;
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
                  AND status IN (" . $this->countedStatusSqlList() . ")
                  AND amount IS NOT NULL
            ");
            $stmt->execute($cardFlowIds);
            $cardExpenseTotal = (float)$stmt->fetchColumn();
        }
        foreach ($flows as $flow) {
            if ($flow['type'] !== 'card' || !$flow['has_live_balance']) {
                continue;
            }
            $cardBalanceAvailable = true;
            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(
                    CASE
                        WHEN direction = 'in' THEN amount
                        WHEN direction = 'out' THEN 0 - amount
                        ELSE 0
                    END
                ), 0)
                FROM v2_entries
                WHERE flow_id = ?
                  AND archived_at IS NULL
                  AND status IN (" . $this->countedStatusSqlList() . ")
                  AND amount IS NOT NULL
            ");
            $stmt->execute([(string)$flow['id']]);
            $cardBalance += (float)$flow['opening_balance'] + (float)$stmt->fetchColumn();
        }

        $stmt = $this->db->prepare("
            SELECT MAX(date)
            FROM v2_entries
            WHERE workspace_id = ?
              AND archived_at IS NULL
        ");
        $stmt->execute([$workspaceId]);
        $latestEntryDate = $stmt->fetchColumn() ?: null;

        return [
            'workspace_id' => $workspaceId,
            'opening_cash' => $cashFlow === null ? null : $cashFlow['opening_balance'],
            'cash_now' => $cashNow,
            'card_balance' => round($cardBalance, 2),
            'card_balance_available' => $cardBalanceAvailable,
            'card_expense_total' => $cardExpenseTotal,
            'latest_entry_date' => $latestEntryDate,
        ];
    }

    public function getDictionaryReviewQueue(string $workspaceId, array $query, int $userId): array
    {
        $this->requireWorkspaceFullReader($workspaceId, $userId);
        $workspace = $this->getWorkspace($workspaceId, $userId);
        $archiveWorkspace = $this->dictionaryArchiveWorkspace($workspace, $userId);
        $exampleLimit = max(1, min(10, $this->optionalInt($query, 'examples', 4)));
        $groupLimit = max(1, min(500, $this->optionalInt($query, 'limit', 120)));
        $needsReviewOnly = (string)($query['needs_review'] ?? '') === '1';

        $stmt = $this->db->prepare("
            SELECT
                s.id AS source_id,
                s.file_name,
                r.id AS source_row_id,
                r.sheet_name,
                r.row_number,
                r.raw_json,
                r.parse_status,
                r.parse_notes
            FROM v2_import_rows r
            INNER JOIN v2_import_sources s ON s.id = r.import_source_id
            WHERE s.workspace_id = ?
              AND s.include_decision = 'included'
            ORDER BY s.file_name ASC, r.sheet_name ASC, r.row_number ASC
        ");
        $stmt->execute([$archiveWorkspace['id']]);

        $groups = [];
        $rowsTotal = 0;
        $rowsWithMoney = 0;
        $rowsNeedsReview = 0;

        while ($row = $stmt->fetch()) {
            $rowsTotal++;
            $raw = FinDeskV2Support::jsonDecode($row['raw_json'] ?? null, []);
            if (!is_array($raw)) {
                $raw = [];
            }
            $description = $this->dictionaryDescription($raw);
            $money = $this->dictionaryMoney($raw);
            $guess = $money === null ? ['category_code' => null, 'pattern' => null] : $this->dictionaryCategoryGuess($description, $money['flow_type'], $money['sign']);
            $rawText = trim(($money['sign'] ?? '') . ($money === null ? '' : number_format((float)$money['amount'], 2, '.', '')) . ' ' . $description);
            $markers = $money === null ? [] : $this->semanticMarkersFromRules(
                $this->inferSemanticMarkers($rawText, (string)$money['flow_type'], (string)$money['sign'], $guess['category_code'])
            );
            $hasDictionaryReviewMarker = $this->semanticMarkerArrayHas($markers, 'weak_dictionary_context')
                || $this->semanticMarkerArrayHas($markers, 'mixed_dictionary_context');
            $needsReview = $description === ''
                || $money === null
                || $guess['category_code'] === null
                || (string)$row['parse_status'] === 'unrecognized'
                || $hasDictionaryReviewMarker
                || $this->dictionaryNeedsReviewOverride($description);
            if ($money !== null) {
                $rowsWithMoney++;
            }
            if ($needsReview) {
                $rowsNeedsReview++;
            }
            if ($needsReviewOnly && !$needsReview) {
                continue;
            }

            $group = $this->dictionaryReviewGroup($markers, $guess['category_code'], $needsReview);
            if (!isset($groups[$group['key']])) {
                $groups[$group['key']] = $group + [
                    'count' => 0,
                    'amount_abs_total' => 0.0,
                    'cash_count' => 0,
                    'card_count' => 0,
                    'income_count' => 0,
                    'expense_count' => 0,
                    'examples' => [],
                ];
            }

            $groups[$group['key']]['count']++;
            $groups[$group['key']]['needs_review'] = (bool)$groups[$group['key']]['needs_review'] || $needsReview;
            if ($money !== null) {
                $groups[$group['key']]['amount_abs_total'] += (float)$money['amount'];
                $groups[$group['key']][$money['flow_type'] . '_count']++;
                $groups[$group['key']][$money['sign'] === '+' ? 'income_count' : 'expense_count']++;
            }
            if (count($groups[$group['key']]['examples']) < $exampleLimit) {
                $groups[$group['key']]['examples'][] = $this->dictionaryReviewExample($row, $raw, $description, $money, $guess, $markers);
            }
        }

        $groups = array_values($groups);
        usort($groups, static function (array $a, array $b): int {
            return [$b['count'], $b['amount_abs_total'], $a['label']] <=> [$a['count'], $a['amount_abs_total'], $b['label']];
        });
        $groups = array_slice($groups, 0, $groupLimit);

        return [
            'workspace_id' => $archiveWorkspace['id'],
            'workspace_name' => $archiveWorkspace['name'],
            'source_workspace_id' => $workspace['id'],
            'source_workspace_name' => $workspace['name'],
            'generated_at' => date(DATE_ATOM),
            'purpose' => 'Read-only dictionary training queue. Does not create operational entries.',
            'note' => 'Amounts are review metadata only and are not used as finance-report totals.',
            'rows_total' => $rowsTotal,
            'rows_with_money' => $rowsWithMoney,
            'rows_needs_review' => $rowsNeedsReview,
            'groups_total' => count($groups),
            'groups' => $groups,
        ];
    }

    public function getRawHistory(string $workspaceId, array $query, int $userId): array
    {
        $this->requireWorkspaceFullReader($workspaceId, $userId);
        $workspace = $this->getWorkspace($workspaceId, $userId);
        $archiveWorkspace = $this->dictionaryArchiveWorkspace($workspace, $userId);
        $sourceLimit = max(1, min(200, $this->optionalInt($query, 'sources', 80)));
        $sampleLimit = max(1, min(10, $this->optionalInt($query, 'samples', 3)));

        $stmt = $this->db->prepare("
            SELECT
                COUNT(DISTINCT s.id) AS sources_total,
                COUNT(r.id) AS rows_total
            FROM v2_import_sources s
            LEFT JOIN v2_import_rows r ON r.import_source_id = s.id
            WHERE s.workspace_id = ?
              AND s.include_decision = 'included'
        ");
        $stmt->execute([$archiveWorkspace['id']]);
        $totals = $stmt->fetch() ?: ['sources_total' => 0, 'rows_total' => 0];

        $stmt = $this->db->prepare("
            SELECT
                s.id,
                s.file_name,
                s.status,
                s.include_decision,
                s.reason,
                s.created_at,
                COUNT(r.id) AS row_count,
                MIN(r.row_number) AS first_row,
                MAX(r.row_number) AS last_row
            FROM v2_import_sources s
            LEFT JOIN v2_import_rows r ON r.import_source_id = s.id
            WHERE s.workspace_id = ?
              AND s.include_decision = 'included'
            GROUP BY s.id
            ORDER BY COALESCE(s.file_name, '') ASC, s.created_at ASC
            LIMIT {$sourceLimit}
        ");
        $stmt->execute([$archiveWorkspace['id']]);
        $sources = [];
        foreach ($stmt->fetchAll() as $source) {
            $sampleStmt = $this->db->prepare("
                SELECT id, sheet_name, `row_number`, raw_json, parse_status, parse_notes
                FROM v2_import_rows
                WHERE import_source_id = ?
                ORDER BY `row_number` ASC, id ASC
                LIMIT {$sampleLimit}
            ");
            $sampleStmt->execute([(string)$source['id']]);
            $samples = [];
            foreach ($sampleStmt->fetchAll() as $row) {
                $raw = FinDeskV2Support::jsonDecode($row['raw_json'] ?? null, []);
                if (!is_array($raw)) {
                    $raw = [];
                }
                $money = $this->dictionaryMoney($raw);
                $samples[] = [
                    'source_row_id' => (string)$row['id'],
                    'sheet_name' => $row['sheet_name'] === null ? null : (string)$row['sheet_name'],
                    'row_number' => $row['row_number'] === null ? null : (int)$row['row_number'],
                    'description' => $this->dictionaryDescription($raw),
                    'flow_type' => $money['flow_type'] ?? null,
                    'sign' => $money['sign'] ?? null,
                    'amount' => $money['amount'] ?? null,
                    'parse_status' => (string)$row['parse_status'],
                    'parse_notes' => $row['parse_notes'] === null ? null : (string)$row['parse_notes'],
                ];
            }
            $sources[] = [
                'id' => (string)$source['id'],
                'file_name' => $source['file_name'] === null ? null : (string)$source['file_name'],
                'status' => (string)$source['status'],
                'include_decision' => (string)$source['include_decision'],
                'reason' => $source['reason'] === null ? null : (string)$source['reason'],
                'created_at' => (string)$source['created_at'],
                'row_count' => (int)$source['row_count'],
                'first_row' => $source['first_row'] === null ? null : (int)$source['first_row'],
                'last_row' => $source['last_row'] === null ? null : (int)$source['last_row'],
                'samples' => $samples,
            ];
        }

        return [
            'workspace_id' => (string)$archiveWorkspace['id'],
            'workspace_name' => (string)$archiveWorkspace['name'],
            'source_workspace_id' => (string)$workspace['id'],
            'source_workspace_name' => (string)$workspace['name'],
            'purpose' => 'Read-only imported raw history for user review and dictionary training.',
            'sources_total' => (int)$totals['sources_total'],
            'rows_total' => (int)$totals['rows_total'],
            'sources' => $sources,
        ];
    }

    public function convertRawHistoryBatch(string $workspaceId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $input, $userId): array {
            $workspace = $this->getWorkspace($workspaceId, $userId);
            $this->requireWorkspaceWriter($workspaceId, $userId);
            $archiveWorkspace = $this->dictionaryArchiveWorkspace($workspace, $userId);
            if ((string)$archiveWorkspace['id'] === $workspaceId) {
                throw new FinDeskV2HttpError(422, 'raw_history_requires_operational_workspace');
            }

            $mode = FinDeskV2Support::enum(
                FinDeskV2Support::optionalString($input, 'mode', 'preview', 20) ?? 'preview',
                ['preview', 'commit'],
                'mode'
            );
            $limit = max(1, min(100, (int)($input['limit'] ?? 25)));
            $sourceId = FinDeskV2Support::optionalString($input, 'source_id', null, 36);
            $flows = $this->flowsByType($workspaceId, $userId);
            $seen = $this->existingLegacyEntryKeys($workspaceId);
            $params = [(string)$archiveWorkspace['id']];
            $where = [
                's.workspace_id = ?',
                "s.include_decision = 'included'",
                'r.entry_id IS NULL',
                "(r.parse_status IS NULL OR r.parse_status NOT IN ('imported', 'duplicate_suspect', 'unrecognized', 'ignored', 'summary_ignored'))",
            ];
            if ($sourceId !== null) {
                $where[] = 's.id = ?';
                $params[] = $sourceId;
            }

            $stmt = $this->db->prepare("
                SELECT
                    s.id AS source_id,
                    s.file_name,
                    r.id AS source_row_id,
                    r.sheet_name,
                    r.row_number,
                    r.raw_json,
                    r.parse_status,
                    r.parse_notes
                FROM v2_import_rows r
                INNER JOIN v2_import_sources s ON s.id = r.import_source_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY COALESCE(s.file_name, '') ASC, r.sheet_name ASC, r.row_number ASC
                LIMIT {$limit}
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            $result = [
                'mode' => $mode,
                'workspace_id' => $workspaceId,
                'workspace_name' => (string)$workspace['name'],
                'archive_workspace_id' => (string)$archiveWorkspace['id'],
                'archive_workspace_name' => (string)$archiveWorkspace['name'],
                'limit' => $limit,
                'scanned' => 0,
                'convertible' => 0,
                'converted' => 0,
                'duplicates' => 0,
                'unrecognized' => 0,
                'skipped' => 0,
                'rows' => [],
            ];

            foreach ($rows as $row) {
                $result['scanned']++;
                $raw = FinDeskV2Support::jsonDecode($row['raw_json'] ?? null, []);
                if (!is_array($raw)) {
                    $raw = [];
                }
                $parsed = $this->parseLegacyImportRow($raw, $row, $seen);
                $entry = $parsed['entry'];
                $rowResult = [
                    'source_id' => (string)$row['source_id'],
                    'source_row_id' => (string)$row['source_row_id'],
                    'file_name' => (string)$row['file_name'],
                    'sheet_name' => $row['sheet_name'] === null ? null : (string)$row['sheet_name'],
                    'row_number' => $row['row_number'] === null ? null : (int)$row['row_number'],
                    'parse_status' => (string)$parsed['parse_status'],
                    'parse_notes' => $parsed['parse_notes'],
                    'duplicate_suspect' => (bool)$parsed['duplicate_suspect'],
                    'entry_preview' => $entry,
                    'entry_id' => null,
                ];

                if ($entry === null) {
                    $result[(string)$parsed['parse_status'] === 'unrecognized' ? 'unrecognized' : 'skipped']++;
                    if ($mode === 'commit') {
                        $this->updateLegacyImportRowStatus((string)$row['source_row_id'], (string)$parsed['parse_status'], null, $parsed['parse_notes']);
                    }
                    $result['rows'][] = $rowResult;
                    continue;
                }

                if ($parsed['duplicate_suspect']) {
                    $result['duplicates']++;
                    if ($mode === 'commit') {
                        $this->updateLegacyImportRowStatus((string)$row['source_row_id'], 'duplicate_suspect', null, $parsed['parse_notes'] ?? 'duplicate suspect');
                    }
                    $result['rows'][] = $rowResult;
                    continue;
                }

                $flow = $flows[$entry['flow_type']] ?? null;
                if ($flow === null) {
                    $result['unrecognized']++;
                    if ($mode === 'commit') {
                        $this->updateLegacyImportRowStatus((string)$row['source_row_id'], 'unrecognized', null, 'missing flow');
                    }
                    $rowResult['parse_status'] = 'unrecognized';
                    $rowResult['parse_notes'] = 'missing flow';
                    $result['rows'][] = $rowResult;
                    continue;
                }

                $result['convertible']++;
                if ($mode === 'commit') {
                    $created = $this->createEntryInCurrentTransaction($workspaceId, [
                        'flow_id' => $flow['id'],
                        'date' => $entry['date'],
                        'raw_text' => $entry['raw_text'],
                        'amount' => number_format((float)$entry['amount'], 2, '.', ''),
                        'category_code' => $entry['category_code'],
                        'status' => 'imported',
                        'source_type' => 'import',
                        'source_id' => (string)$row['source_id'],
                        'source_row_id' => (string)$row['source_row_id'],
                        'closed_month_decision' => 'recalculate_chain',
                        'matched_rules' => [[
                            'source' => 'raw_history_gradual_conversion',
                            'archive_workspace_id' => (string)$archiveWorkspace['id'],
                            'file_name' => (string)$row['file_name'],
                            'sheet_name' => $row['sheet_name'],
                            'row_number' => (int)$row['row_number'],
                        ]],
                    ], $userId);
                    $rowResult['entry_id'] = (string)$created['id'];
                    $this->updateLegacyImportRowStatus((string)$row['source_row_id'], 'imported', (string)$created['id'], $parsed['parse_notes']);
                    $result['converted']++;
                }
                $result['rows'][] = $rowResult;
            }

            if ($mode === 'commit' && $result['converted'] > 0) {
                $this->audit($workspaceId, 'raw_history', (string)$archiveWorkspace['id'], 'raw_history_batch_convert', null, $result, $userId);
            }

            return $result;
        });
    }

    public function listDictionaryTrainingDecisions(string $workspaceId, array $query, int $userId): array
    {
        $this->requireWorkspaceFullReader($workspaceId, $userId);
        $limit = max(1, min(500, $this->optionalInt($query, 'limit', 120)));

        $stmt = $this->db->prepare("
            SELECT d.*, c.code AS category_code
            FROM v2_dictionary_training_decisions d
            LEFT JOIN v2_categories c ON c.id = d.category_id
            WHERE d.workspace_id = ?
            ORDER BY d.decided_at DESC, d.updated_at DESC
            LIMIT {$limit}
        ");
        $stmt->execute([$workspaceId]);

        return array_map([$this, 'dictionaryTrainingDecisionRow'], $stmt->fetchAll());
    }

    public function decideDictionaryTraining(string $workspaceId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $input, $userId): array {
            $workspace = $this->getWorkspace($workspaceId, $userId);
            $this->requireWorkspaceWriter($workspaceId, $userId);
            $archiveWorkspace = $this->dictionaryArchiveWorkspace($workspace, $userId);
            $decisionType = $this->dictionaryTrainingDecisionType($input);
            $decisionScope = FinDeskV2Support::enum(
                FinDeskV2Support::optionalString($input, 'decision_scope', 'row', 20) ?? 'row',
                ['row', 'group'],
                'decision_scope'
            );
            $groupKey = FinDeskV2Support::optionalString($input, 'group_key', null, 190);
            $sourceRowId = FinDeskV2Support::optionalString($input, 'source_row_id', null, 36);
            $sourceRowIds = $this->optionalStringList($input, 'source_row_ids');
            if ($sourceRowId !== null && !in_array($sourceRowId, $sourceRowIds, true)) {
                array_unshift($sourceRowIds, $sourceRowId);
            }
            $sourceRowIds = array_values(array_unique($sourceRowIds));

            if ($decisionScope === 'row' && $sourceRowId === null) {
                throw new FinDeskV2HttpError(422, 'missing_source_row_id');
            }
            if ($decisionScope === 'group' && $groupKey === null && $sourceRowIds === []) {
                throw new FinDeskV2HttpError(422, 'missing_group_key');
            }

            $sourceRow = null;
            $snapshot = is_array($input['source_snapshot'] ?? null) ? $input['source_snapshot'] : [];
            if ($sourceRowId !== null) {
                $sourceRow = $this->dictionaryTrainingSourceRow($archiveWorkspace['id'], $sourceRowId);
                $snapshot = $this->dictionaryTrainingSnapshotFromSourceRow($sourceRow);
            }

            $classificationDecision = is_array($snapshot['classification_decision'] ?? null) ? $snapshot['classification_decision'] : [];
            $blockers = $this->dictionaryTrainingStringList($input['blockers'] ?? ($snapshot['blockers'] ?? ($classificationDecision['blockers'] ?? [])));
            $matchedSignals = is_array($input['matched_signals'] ?? null)
                ? array_values($input['matched_signals'])
                : (is_array($snapshot['matched_signals'] ?? null) ? array_values($snapshot['matched_signals']) : ($classificationDecision['matched_signals'] ?? []));
            $semanticMarkers = is_array($input['semantic_markers'] ?? null)
                ? array_values($input['semantic_markers'])
                : (is_array($snapshot['semantic_markers'] ?? null) ? array_values($snapshot['semantic_markers']) : []);
            $reviewReason = FinDeskV2Support::optionalString($input, 'review_reason', $snapshot['review_reason'] ?? ($classificationDecision['review_reason'] ?? null), 80);
            $currentRuleGuess = FinDeskV2Support::optionalString($input, 'current_rule_guess', $snapshot['current_rule_guess'] ?? ($classificationDecision['category_code'] ?? null), 80);
            $confidence = FinDeskV2Support::nullableAmount($input['confidence'] ?? ($snapshot['confidence'] ?? ($classificationDecision['confidence'] ?? null)));
            $note = FinDeskV2Support::optionalString($input, 'note', null, 2000);
            $pattern = FinDeskV2Support::optionalString($input, 'pattern', null, 255);
            $patternType = FinDeskV2Support::optionalString($input, 'pattern_type', null, 40);
            $language = FinDeskV2Support::enum(
                FinDeskV2Support::optionalString($input, 'language', 'multi', 10) ?? 'multi',
                ['ru', 'en', 'it', 'es', 'de', 'bcms', 'multi'],
                'language'
            );
            $weight = array_key_exists('weight', $input) ? $this->optionalInt($input, 'weight', 10) : null;
            $negativeWeight = array_key_exists('negative_weight', $input) ? $this->optionalInt($input, 'negative_weight', 0) : null;
            $requiresAny = $this->optionalStringList($input, 'requires_any');
            $excludesAny = $this->optionalStringList($input, 'excludes_any');
            $targetCategoryCode = FinDeskV2Support::optionalString($input, 'target_category_code', null, 80)
                ?? FinDeskV2Support::optionalString($input, 'category_code', null, 80);

            $existing = $sourceRowId === null ? null : $this->dictionaryTrainingDecisionRawBySourceRow($workspaceId, $sourceRowId);
            $categoryRule = null;
            $categoryRuleId = null;
            $categoryId = null;

            if (in_array($decisionType, ['approve_existing_guess_local', 'correct_category_local'], true)) {
                $this->assertDictionaryTrainingRuleAllowed($reviewReason, $blockers);
                if ($targetCategoryCode === null && $decisionType === 'approve_existing_guess_local') {
                    $targetCategoryCode = $currentRuleGuess;
                }
                if ($targetCategoryCode === null) {
                    throw new FinDeskV2HttpError(422, 'missing_target_category_code');
                }
                if ($pattern === null) {
                    throw new FinDeskV2HttpError(422, 'missing_pattern');
                }
                $patternType = FinDeskV2Support::enum($patternType ?? 'keyword', ['keyword', 'phrase', 'regex', 'supplier', 'role'], 'pattern_type');
                $categoryId = $this->categoryIdByCode($workspaceId, $targetCategoryCode);
                $ruleInput = [
                    'category_code' => $targetCategoryCode,
                    'pattern' => $pattern,
                    'pattern_type' => $patternType,
                    'language' => $language,
                    'weight' => $weight ?? 10,
                    'negative_weight' => $negativeWeight ?? 0,
                    'requires_any' => $requiresAny,
                    'excludes_any' => $excludesAny,
                ];

                if ($existing !== null && $this->dictionaryTrainingExistingRuleMatches($existing, $ruleInput, $workspaceId, $userId)) {
                    $categoryRuleId = (string)$existing['category_rule_id'];
                    $categoryRule = $this->getCategoryRule($categoryRuleId, $workspaceId, $userId);
                } else {
                    $categoryRule = $this->createCategoryRuleInCurrentTransaction($workspaceId, $ruleInput, $userId);
                    $categoryRuleId = $categoryRule['id'];
                    $this->audit($workspaceId, 'category_rule', $categoryRuleId, 'create', null, $categoryRule, $userId);
                }
            } elseif ($decisionType === 'propose_universal_candidate') {
                $targetCategoryCode = $targetCategoryCode ?? $currentRuleGuess;
                $categoryId = $targetCategoryCode === null ? null : $this->categoryIdByCode($workspaceId, $targetCategoryCode);
            } elseif ($targetCategoryCode !== null) {
                $categoryId = $this->categoryIdByCode($workspaceId, $targetCategoryCode);
            }

            $decisionId = $existing === null ? FinDeskV2Support::uuid() : (string)$existing['id'];
            $sourceId = $sourceRow['source_id'] ?? null;
            $before = $existing === null ? null : $this->dictionaryTrainingDecisionRow($existing);
            $baseValues = [
                $decisionId,
                $workspaceId,
                $archiveWorkspace['id'],
                $sourceId,
                $sourceRowId,
                $decisionScope,
                $groupKey,
                FinDeskV2Support::jsonEncode($sourceRowIds),
                $decisionType,
                $currentRuleGuess,
                $categoryId,
                $categoryRuleId,
                $pattern,
                $patternType,
                $language,
                $weight,
                $negativeWeight,
                FinDeskV2Support::jsonEncode($requiresAny),
                FinDeskV2Support::jsonEncode($excludesAny),
                $confidence,
                $reviewReason,
                FinDeskV2Support::jsonEncode($blockers),
                FinDeskV2Support::jsonEncode($matchedSignals),
                FinDeskV2Support::jsonEncode($semanticMarkers),
                FinDeskV2Support::jsonEncode($snapshot),
                $note,
                $userId,
            ];

            if ($existing === null) {
                $this->db->prepare("
                    INSERT INTO v2_dictionary_training_decisions (
                        id, workspace_id, archive_workspace_id, source_id, source_row_id, decision_scope,
                        group_key, source_row_ids_json, decision_type, current_rule_guess, category_id,
                        category_rule_id, pattern, pattern_type, language, weight, negative_weight,
                        requires_any_json, excludes_any_json, confidence, review_reason, blockers_json,
                        matched_signals_json, semantic_markers_json, example_snapshot_json, note, decided_by
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute($baseValues);
            } else {
                $this->db->prepare("
                    UPDATE v2_dictionary_training_decisions
                    SET archive_workspace_id = ?, source_id = ?, decision_scope = ?, group_key = ?,
                        source_row_ids_json = ?, decision_type = ?, current_rule_guess = ?, category_id = ?,
                        category_rule_id = ?, pattern = ?, pattern_type = ?, language = ?, weight = ?,
                        negative_weight = ?, requires_any_json = ?, excludes_any_json = ?, confidence = ?,
                        review_reason = ?, blockers_json = ?, matched_signals_json = ?, semantic_markers_json = ?,
                        example_snapshot_json = ?, note = ?, decided_by = ?
                    WHERE id = ? AND workspace_id = ?
                ")->execute([
                    $archiveWorkspace['id'],
                    $sourceId,
                    $decisionScope,
                    $groupKey,
                    FinDeskV2Support::jsonEncode($sourceRowIds),
                    $decisionType,
                    $currentRuleGuess,
                    $categoryId,
                    $categoryRuleId,
                    $pattern,
                    $patternType,
                    $language,
                    $weight,
                    $negativeWeight,
                    FinDeskV2Support::jsonEncode($requiresAny),
                    FinDeskV2Support::jsonEncode($excludesAny),
                    $confidence,
                    $reviewReason,
                    FinDeskV2Support::jsonEncode($blockers),
                    FinDeskV2Support::jsonEncode($matchedSignals),
                    FinDeskV2Support::jsonEncode($semanticMarkers),
                    FinDeskV2Support::jsonEncode($snapshot),
                    $note,
                    $userId,
                    $decisionId,
                    $workspaceId,
                ]);
            }

            $decision = $this->dictionaryTrainingDecisionById($decisionId, $workspaceId);
            $after = $this->dictionaryTrainingDecisionRow($decision);
            if ($categoryRule !== null) {
                $after['category_rule'] = $categoryRule;
            }
            $this->audit($workspaceId, 'dictionary_training_decision', $decisionId, $existing === null ? 'create' : 'update', $before, $after, $userId);

            return $after;
        });
    }

    public function getWorkspaceAssistantSettings(string $workspaceId, int $userId): array
    {
        $this->requireWorkspaceFullReader($workspaceId, $userId);
        $settings = $this->workspaceAssistantSettingsRaw($workspaceId);

        if ($settings === null) {
            return $this->workspaceAssistantSettingsDefaults($workspaceId);
        }

        return $this->workspaceAssistantSettingsRow($settings);
    }

    public function updateWorkspaceAssistantSettings(string $workspaceId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $input, $userId): array {
            $this->getWorkspace($workspaceId, $userId);
            $this->requireWorkspaceOwnerAdmin($workspaceId, $userId);
            $before = $this->getWorkspaceAssistantSettings($workspaceId, $userId);

            $mode = FinDeskV2Support::enum(
                FinDeskV2Support::optionalString($input, 'internet_reference_mode', $before['internet_reference_mode'], 40) ?? $before['internet_reference_mode'],
                ['disabled', 'per_request', 'workspace_enabled'],
                'internet_reference_mode'
            );
            $provider = FinDeskV2Support::enum(
                FinDeskV2Support::optionalString($input, 'provider_key', $before['provider_key'], 80) ?? $before['provider_key'],
                $this->internetReferenceProviderKeys(),
                'provider_key'
            );
            $retentionDays = max(1, min(365, $this->optionalInt($input, 'retention_days', (int)$before['retention_days'])));
            $enabled = array_key_exists('mr_smith_enabled', $input)
                ? (bool)$input['mr_smith_enabled']
                : (bool)$before['mr_smith_enabled'];

            $this->db->prepare("
                INSERT INTO v2_workspace_assistant_settings (
                    workspace_id, mr_smith_enabled, internet_reference_mode, provider_key, retention_days, updated_by
                )
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    mr_smith_enabled = VALUES(mr_smith_enabled),
                    internet_reference_mode = VALUES(internet_reference_mode),
                    provider_key = VALUES(provider_key),
                    retention_days = VALUES(retention_days),
                    updated_by = VALUES(updated_by)
            ")->execute([$workspaceId, $enabled ? 1 : 0, $mode, $provider, $retentionDays, $userId]);

            $after = $this->getWorkspaceAssistantSettings($workspaceId, $userId);
            $this->audit($workspaceId, 'workspace_assistant_settings', $workspaceId, 'update', $before, $after, $userId);

            return $after;
        });
    }

    public function previewDictionaryInternetReference(string $workspaceId, array $input, int $userId): array
    {
        $workspace = $this->getWorkspace($workspaceId, $userId);
        $this->requireWorkspaceWriter($workspaceId, $userId);
        $settings = $this->getWorkspaceAssistantSettings($workspaceId, $userId);

        $consent = ($input['lookup_consent'] ?? $input['consent'] ?? false) === true;
        $mode = (string)$settings['internet_reference_mode'];
        if ($mode === 'disabled') {
            throw new FinDeskV2HttpError(422, 'internet_reference_disabled');
        }
        if ($mode === 'per_request' && !$consent) {
            throw new FinDeskV2HttpError(422, 'internet_reference_consent_required');
        }
        if ($mode === 'workspace_enabled' && !$settings['mr_smith_enabled'] && !$consent) {
            throw new FinDeskV2HttpError(422, 'internet_reference_consent_required');
        }
        foreach (['raw_text', 'raw_row', 'source_snapshot', 'amount', 'balance', 'balance_after', 'report', 'entries', 'rows'] as $unsafeKey) {
            if (array_key_exists($unsafeKey, $input)) {
                throw new FinDeskV2HttpError(422, 'unsafe_internet_reference_payload');
            }
        }

        $query = FinDeskV2Support::optionalString($input, 'sanitized_query', null, 190)
            ?? FinDeskV2Support::optionalString($input, 'query', null, 190);
        if ($query === null) {
            throw new FinDeskV2HttpError(422, 'missing_sanitized_query');
        }

        $sourceRowId = FinDeskV2Support::optionalString($input, 'source_row_id', null, 36);
        if ($sourceRowId !== null) {
            $archiveWorkspace = $this->dictionaryArchiveWorkspace($workspace, $userId);
            $this->dictionaryTrainingSourceRow($archiveWorkspace['id'], $sourceRowId);
        }
        $sanitizedQuery = $this->sanitizeDictionaryInternetQuery($query);
        if ($sanitizedQuery === '') {
            throw new FinDeskV2HttpError(422, 'missing_sanitized_query');
        }
        $candidateUrl = FinDeskV2Support::optionalString($input, 'candidate_url', null, 2000);

        $requestId = FinDeskV2Support::uuid();
        $queryHash = hash('sha256', $workspaceId . '|' . mb_strtolower($sanitizedQuery));
        $maskedFields = ['amounts', 'balances', 'raw_rows', 'private_notes'];
        $provider = $this->internetReferenceProvider((string)$settings['provider_key']);
        $providerResult = $provider->lookup([
            'sanitized_query' => $sanitizedQuery,
            'candidate_url' => $candidateUrl,
        ]);
        $matches = is_array($providerResult['matches'] ?? null) ? array_values($providerResult['matches']) : [];
        $providerRequestId = FinDeskV2Support::optionalString($providerResult, 'provider_request_id', null, 120);
        $providerKey = FinDeskV2Support::enum(
            FinDeskV2Support::optionalString($providerResult, 'provider_key', 'stub', 80) ?? 'stub',
            $this->internetReferenceProviderKeys(),
            'provider_key'
        );
        $resultStatus = FinDeskV2Support::enum(
            FinDeskV2Support::optionalString($providerResult, 'result_status', 'stub', 40) ?? 'stub',
            ['stub', 'ok', 'error', 'timeout'],
            'result_status'
        );
        $latencyMs = max(0, $this->optionalInt($providerResult, 'latency_ms', 0));
        $consentSource = $consent ? 'request' : 'workspace_setting';
        $retentionDeleteAfter = (new DateTimeImmutable())->modify('+' . (int)$settings['retention_days'] . ' days')->format('Y-m-d H:i:s');

        $this->db->prepare("
            INSERT INTO v2_internet_reference_lookups (
                id, workspace_id, source_row_id, provider_key, provider_request_id, consent_source, sanitized_query,
                query_hash, masked_fields_json, result_status, latency_ms, matches_json,
                selected_match_json, no_financial_mutation, created_by, retention_delete_after
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, 1, ?, ?)
        ")->execute([
            $requestId,
            $workspaceId,
            $sourceRowId,
            $providerKey,
            $providerRequestId,
            $consentSource,
            $sanitizedQuery,
            $queryHash,
            FinDeskV2Support::jsonEncode($maskedFields),
            $resultStatus,
            $latencyMs,
            FinDeskV2Support::jsonEncode($matches),
            $userId,
            $retentionDeleteAfter,
        ]);

        return [
            'request_id' => $requestId,
            'lookup_id' => $requestId,
            'workspace_id' => $workspaceId,
            'source_row_id' => $sourceRowId,
            'sanitized_query' => $sanitizedQuery,
            'query_hash' => $queryHash,
            'masked_fields' => $maskedFields,
            'provider_key' => $providerKey,
            'provider_request_id' => $providerRequestId,
            'result_status' => $resultStatus,
            'consent_source' => $consentSource,
            'matches' => $matches,
            'suggested_reviewer_question' => 'Confirm this public resource manually before creating any training decision.',
            'no_financial_mutation' => true,
        ];
    }

    public function listDictionaryInternetReferenceLookups(string $workspaceId, array $query, int $userId): array
    {
        $this->requireWorkspaceFullReader($workspaceId, $userId);
        $limit = max(1, min(200, $this->optionalInt($query, 'limit', 50)));

        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_internet_reference_lookups
            WHERE workspace_id = ?
            ORDER BY created_at DESC
            LIMIT {$limit}
        ");
        $stmt->execute([$workspaceId]);

        return array_map([$this, 'dictionaryInternetReferenceLookupRow'], $stmt->fetchAll());
    }

    public function updateDictionaryInternetReferenceFeedback(string $workspaceId, string $lookupId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $lookupId, $input, $userId): array {
            $this->getWorkspace($workspaceId, $userId);
            $this->requireWorkspaceWriter($workspaceId, $userId);

            foreach (['raw_text', 'raw_row', 'source_snapshot', 'amount', 'balance', 'balance_after', 'report', 'entries', 'rows'] as $unsafeKey) {
                if (array_key_exists($unsafeKey, $input)) {
                    throw new FinDeskV2HttpError(422, 'unsafe_internet_reference_feedback_payload');
                }
            }

            $stmt = $this->db->prepare("
                SELECT *
                FROM v2_internet_reference_lookups
                WHERE workspace_id = ?
                  AND id = ?
                LIMIT 1
            ");
            $stmt->execute([$workspaceId, $lookupId]);
            $row = $stmt->fetch();
            if (!$row) {
                throw new FinDeskV2HttpError(404, 'internet_reference_lookup_not_found');
            }

            $verdict = FinDeskV2Support::enum(
                FinDeskV2Support::requireString($input, 'verdict', 40),
                ['useful', 'unclear', 'not_useful'],
                'verdict'
            );
            $matches = FinDeskV2Support::jsonDecode($row['matches_json'] ?? '[]', []);
            $matches = is_array($matches) ? array_values($matches) : [];
            $matchIndex = max(0, min(4, $this->optionalInt($input, 'match_index', 0)));
            $selectedMatch = $matches[$matchIndex] ?? null;
            $note = FinDeskV2Support::optionalString($input, 'note', null, 240);
            $selection = [
                'verdict' => $verdict,
                'match_index' => $selectedMatch === null ? null : $matchIndex,
                'match' => $selectedMatch,
                'note' => $note,
                'selected_at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
                'selected_by' => $userId,
                'no_financial_mutation' => true,
                'no_training_mutation' => true,
            ];

            $this->db->prepare("
                UPDATE v2_internet_reference_lookups
                SET selected_match_json = ?
                WHERE workspace_id = ?
                  AND id = ?
            ")->execute([
                FinDeskV2Support::jsonEncode($selection),
                $workspaceId,
                $lookupId,
            ]);

            $stmt->execute([$workspaceId, $lookupId]);
            $updated = $stmt->fetch();
            if (!$updated) {
                throw new FinDeskV2HttpError(404, 'internet_reference_lookup_not_found');
            }

            return $this->dictionaryInternetReferenceLookupRow($updated);
        });
    }

    public function getMonthlyReport(string $workspaceId, array $query, int $userId): array
    {
        $this->requireWorkspaceFullReader($workspaceId, $userId);
        $year = $this->optionalInt($query, 'year', (int)date('Y'));
        $month = $this->optionalInt($query, 'month', (int)date('n'));
        $this->assertValidMonth($year, $month);

        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $monthEnd = (new DateTimeImmutable($monthStart))->modify('first day of next month')->format('Y-m-d');
        $cashFlow = $this->cashFlowForWorkspace($workspaceId, $userId);
        $openingCash = $cashFlow === null
            ? null
            : (float)$cashFlow['opening_balance'] + $this->cashDeltaBefore($cashFlow['id'], $monthStart);

        $report = [
            'workspace_id' => $workspaceId,
            'year' => $year,
            'month' => $month,
            'month_key' => sprintf('%04d-%02d', $year, $month),
            'source_files' => $this->sourceFilesForMonth($workspaceId, $monthStart, $monthEnd),
            'opening_cash' => $openingCash,
            'discrepancy_with_previous' => 0.0,
            'external_cash_income' => 0.0,
            'commercial_income' => 0.0,
            'cash_expense' => 0.0,
            'card_expense' => 0.0,
            'cash_topup_from_card_card_side' => 0.0,
            'cash_topup_from_card_cash_side' => 0.0,
            'other_expenses' => 0.0,
            'corrections' => 0.0,
            'ending_cash' => $openingCash,
            'comment' => null,
            'is_closed' => false,
            'counts' => [
                'entries' => 0,
                'counted' => 0,
                'unrecognized' => 0,
                'other_review' => 0,
            ],
        ];

        $entries = $this->db->prepare("
            SELECT
                e.amount,
                e.direction,
                e.entry_type,
                e.status,
                e.raw_text,
                e.matched_rules_json,
                f.type AS flow_type,
                c.code AS category_code
            FROM v2_entries e
            INNER JOIN v2_flows f ON f.id = e.flow_id
            LEFT JOIN v2_categories c ON c.id = e.category_id
            WHERE e.workspace_id = ?
              AND e.archived_at IS NULL
              AND e.date >= ?
              AND e.date < ?
        ");
        $entries->execute([$workspaceId, $monthStart, $monthEnd]);

        $monthCashDelta = 0.0;
        foreach ($entries->fetchAll() as $entry) {
            $report['counts']['entries']++;
            if ((string)$entry['status'] === 'unrecognized') {
                $report['counts']['unrecognized']++;
            }
            if (!$this->isCountedStatus((string)$entry['status']) || $entry['amount'] === null) {
                continue;
            }

            $report['counts']['counted']++;
            $amount = (float)$entry['amount'];
            $flowType = (string)$entry['flow_type'];
            $direction = (string)$entry['direction'];
            $entryType = (string)$entry['entry_type'];
            $categoryCode = $entry['category_code'] === null ? null : (string)$entry['category_code'];
            $semanticMarkers = $this->semanticMarkersFromRules(FinDeskV2Support::jsonDecode($entry['matched_rules_json'] ?? '[]', []));
            $accounting = $this->accountingClassification($categoryCode, $semanticMarkers, (string)$entry['raw_text']);
            $isLowerAccounting = $accounting['section'] === 'lower_accounting';
            $isAdminDebt = $accounting['section'] === 'admin_debt';
            $isUncategorizedReview = !$isLowerAccounting && !$isAdminDebt && $entryType !== 'correction' && $categoryCode === null;
            $isOtherReview = ((string)$entry['status'] === 'other_review'
                && $entryType === 'cash_expense'
                && $categoryCode === 'other')
                || $isUncategorizedReview;

            if ($isOtherReview) {
                $report['counts']['other_review']++;
                if ($direction === 'out') {
                    $report['other_expenses'] += $amount;
                }
            }

            if ($flowType === 'cash') {
                $cashDelta = $this->cashBalanceDelta($entry);
                if ($cashDelta !== null) {
                    $monthCashDelta += $cashDelta;
                }
            }

            if ($flowType === 'cash' && $direction === 'in' && $entryType === 'cash_income') {
                if ($categoryCode === 'commercial_income') {
                    $report['commercial_income'] += $amount;
                } elseif ($categoryCode === 'cash_topup_from_card') {
                    $report['cash_topup_from_card_cash_side'] += $amount;
                } else {
                    $report['external_cash_income'] += $amount;
                }
            }

            if ($flowType === 'cash' && $direction === 'out' && $entryType === 'cash_expense') {
                $report['cash_expense'] += $amount;
            }

            if ($flowType === 'card' && $direction === 'out' && $entryType === 'card_expense') {
                $report['card_expense'] += $amount;
                if ($categoryCode === 'cash_topup_from_card') {
                    $report['cash_topup_from_card_card_side'] += $amount;
                }
            }

            if (!$isOtherReview && $direction === 'out' && $categoryCode === 'other') {
                $report['other_expenses'] += $amount;
            }

            if ($entryType === 'correction') {
                $report['corrections'] += $direction === 'out' ? -$amount : $amount;
            }
        }

        if ($openingCash !== null) {
            $report['ending_cash'] = $openingCash + $monthCashDelta;
        }

        $closure = $this->monthClosure($workspaceId, $year, $month);
        if ($closure !== null) {
            $report['is_closed'] = (int)$closure['is_closed'] === 1;
            $report['comment'] = $closure['comment'] === null ? null : (string)$closure['comment'];
            if ($closure['opening_balance'] !== null) {
                $report['discrepancy_with_previous'] = $openingCash === null
                    ? 0.0
                    : (float)$closure['opening_balance'] - $openingCash;
            }
        }

        return $report;
    }

    public function getLayer1SummaryReport(string $workspaceId, array $query, int $userId): array
    {
        $this->requireWorkspaceFullReader($workspaceId, $userId);
        $workspace = $this->getWorkspace($workspaceId, $userId);
        $year = $this->optionalInt($query, 'year', (int)date('Y'));
        $month = $this->optionalInt($query, 'month', (int)date('n'));
        $this->assertValidMonth($year, $month);
        $fromYear = $this->optionalInt($query, 'from_year', $year);
        $fromMonth = $this->optionalInt($query, 'from_month', $month);
        $toYear = $this->optionalInt($query, 'to_year', $year);
        $toMonth = $this->optionalInt($query, 'to_month', $month);
        $this->assertValidMonth($fromYear, $fromMonth);
        $this->assertValidMonth($toYear, $toMonth);
        if (($fromYear * 12 + $fromMonth) > ($toYear * 12 + $toMonth)) {
            [$fromYear, $toYear] = [$toYear, $fromYear];
            [$fromMonth, $toMonth] = [$toMonth, $fromMonth];
        }

        $monthStart = sprintf('%04d-%02d-01', $fromYear, $fromMonth);
        $toMonthStart = sprintf('%04d-%02d-01', $toYear, $toMonth);
        $monthEnd = (new DateTimeImmutable($toMonthStart))->modify('first day of next month')->format('Y-m-d');
        $monthlyStart = $this->getMonthlyReport($workspaceId, ['year' => $fromYear, 'month' => $fromMonth], $userId);
        $monthlyEnd = $this->getMonthlyReport($workspaceId, ['year' => $toYear, 'month' => $toMonth], $userId);
        $isSingleMonth = $fromYear === $toYear && $fromMonth === $toMonth;
        $cashFlow = $this->cashFlowForWorkspace($workspaceId, $userId);
        $openingCashSourceIds = $cashFlow === null ? [] : $this->cashSourceEntryIdsBefore($cashFlow['id'], $monthStart);
        $flowOpeningBalance = $cashFlow === null ? null : (float)$cashFlow['opening_balance'];
        $priorCashDelta = $flowOpeningBalance === null || $monthlyStart['opening_cash'] === null
            ? null
            : (float)$monthlyStart['opening_cash'] - $flowOpeningBalance;

        $sourceTrace = [
            'totals' => [
                'opening_cash' => $openingCashSourceIds,
                'total_cash_income' => [],
                'cash_income' => [],
                'cash_expense' => [],
                'card_expense' => [],
                'commercial_income' => [],
                'other_review_total' => [],
                'lower_accounting_total' => [],
                'admin_debt_total' => [],
                'corrections_total' => [],
                'ending_cash' => [],
            ],
            'categories' => [],
            'basis' => [
                'opening_cash' => $cashFlow === null ? null : [
                    'type' => 'cash_flow_opening_balance_plus_prior_entries',
                    'flow_id' => $cashFlow['id'],
                    'flow_name' => $cashFlow['name'],
                    'flow_opening_balance' => $flowOpeningBalance,
                    'prior_cash_delta' => $priorCashDelta,
                    'prior_entry_ids' => $openingCashSourceIds,
                    'total' => $monthlyStart['opening_cash'] === null ? null : (float)$monthlyStart['opening_cash'],
                    'amount' => $flowOpeningBalance,
                    'period_start' => $monthStart,
                    'period_end_exclusive' => $monthEnd,
                    'label' => 'Cash flow opening balance',
                    'note' => 'Non-entry basis for opening cash',
                ],
            ],
        ];
        $sourceTrace['totals']['ending_cash'] = $sourceTrace['totals']['opening_cash'];

        $categories = [];
        foreach ($this->listCategories($workspaceId, $userId) as $category) {
            $categories[$category['code']] = [
                'category_code' => $category['code'],
                'category_name' => $category['name'],
                'direction' => $category['direction'],
                'cash_total' => 0.0,
                'card_total' => 0.0,
                'total' => 0.0,
                'entry_count' => 0,
                'review_count' => 0,
                'source_entry_ids' => [],
            ];
        }
        $categories['uncategorized_review'] = $this->uncategorizedReviewCategoryRow();

        $cardByCategory = [];
        $otherReviewEntries = [];
        $lowerAccountingEntries = [];
        $adminDebtEntries = [];
        $cardExpenseCount = 0;
        $cardReviewCount = 0;
        $periodEntryCount = 0;
        $periodTotals = [
            'cash_income' => 0.0,
            'cash_expense' => 0.0,
            'card_expense' => 0.0,
            'commercial_income' => 0.0,
            'cash_topup_from_card_cash_side' => 0.0,
            'cash_topup_from_card_card_side' => 0.0,
            'corrections' => 0.0,
        ];

        $stmt = $this->db->prepare("
            SELECT
                e.id,
                e.amount,
                e.direction,
                e.entry_type,
                e.status,
                e.date,
                e.raw_text,
                e.source_type,
                e.source_id,
                e.source_row_id,
                e.matched_rules_json,
                f.type AS flow_type,
                a.name AS actor_name,
                c.code AS category_code,
                c.name_json AS category_name_json,
                c.direction AS category_direction
            FROM v2_entries e
            INNER JOIN v2_flows f ON f.id = e.flow_id
            LEFT JOIN v2_actors a ON a.id = e.actor_id
            LEFT JOIN v2_categories c ON c.id = e.category_id
            WHERE e.workspace_id = ?
              AND e.archived_at IS NULL
              AND e.date >= ?
              AND e.date < ?
            ORDER BY e.date ASC, e.created_seq ASC
        ");
        $stmt->execute([$workspaceId, $monthStart, $monthEnd]);

        foreach ($stmt->fetchAll() as $entry) {
            $periodEntryCount++;
            $id = (string)$entry['id'];
            $amount = $entry['amount'] === null ? null : (float)$entry['amount'];
            $flowType = (string)$entry['flow_type'];
            $direction = (string)$entry['direction'];
            $entryType = (string)$entry['entry_type'];
            $status = (string)$entry['status'];
            $categoryCode = $entry['category_code'] === null ? null : (string)$entry['category_code'];
            $semanticMarkers = $this->semanticMarkersFromRules(FinDeskV2Support::jsonDecode($entry['matched_rules_json'] ?? '[]', []));
            $accounting = $this->accountingClassification($categoryCode, $semanticMarkers, (string)$entry['raw_text']);
            $isLowerAccounting = $accounting['section'] === 'lower_accounting';
            $isAdminDebt = $accounting['section'] === 'admin_debt';
            $isCountedEntry = $this->isCountedStatus($status) && $amount !== null;
            $isUncategorizedReview = $isCountedEntry && !$isLowerAccounting && !$isAdminDebt && $entryType !== 'correction' && $categoryCode === null;
            $isOtherReview = ($status === 'other_review'
                && $entryType === 'cash_expense'
                && $categoryCode === 'other')
                || $isUncategorizedReview;

            if ($flowType === 'card' && $status === 'other_review') {
                $cardReviewCount++;
            }

            if ($isOtherReview) {
                $otherReviewEntries[] = [
                    'id' => $id,
                    'date' => (string)$entry['date'],
                    'raw_text' => (string)$entry['raw_text'],
                    'amount' => $amount,
                    'direction' => $direction,
                    'entry_type' => $entryType,
                    'category_code' => $categoryCode ?? 'uncategorized_review',
                    'status' => $status,
                    'source_type' => (string)$entry['source_type'],
                    'source_id' => $entry['source_id'] ?? null,
                    'source_row_id' => $entry['source_row_id'] ?? null,
                ];
                $this->appendSourceEntryId($sourceTrace['totals']['other_review_total'], $id);
            }

            if (!$isCountedEntry) {
                continue;
            }

            if ($isLowerAccounting) {
                $settlement = $this->lowerAccountingSettlementEntry(
                    (string)$entry['raw_text'],
                    $direction,
                    $amount,
                    $accounting,
                    $semanticMarkers,
                    $entry['actor_name'] === null ? null : (string)$entry['actor_name']
                );
                $archiveException = $this->archiveLowerAccountingExceptionForEntry($workspace, $entry);
                if ($archiveException !== null && isset($archiveException['counterparty']) && trim((string)$archiveException['counterparty']) !== '') {
                    $settlement['counterparty'] = trim((string)$archiveException['counterparty']);
                }
                $lowerAccountingEntries[] = [
                    'id' => $id,
                    'date' => (string)$entry['date'],
                    'raw_text' => (string)$entry['raw_text'],
                    'amount' => $amount,
                    'flow_type' => $flowType,
                    'direction' => $direction,
                    'entry_type' => $entryType,
                    'category_code' => $categoryCode,
                    'status' => $status,
                    'accounting_section' => $accounting['section'],
                    'accounting_type' => $accounting['type'],
                    'accounting_label' => $accounting['label'],
                    'settlement_counterparty' => $settlement['counterparty'],
                    'settlement_effect' => $settlement['effect'],
                    'settlement_direction' => $settlement['direction'],
                    'settlement_archive_exception' => $archiveException,
                    'semantic_markers' => $semanticMarkers,
                    'source_type' => (string)$entry['source_type'],
                    'source_id' => $entry['source_id'] ?? null,
                    'source_row_id' => $entry['source_row_id'] ?? null,
                ];
                $this->appendSourceEntryId($sourceTrace['totals']['lower_accounting_total'], $id);
            }

            if ($isAdminDebt) {
                $adminDebtEntries[] = [
                    'id' => $id,
                    'date' => (string)$entry['date'],
                    'raw_text' => (string)$entry['raw_text'],
                    'amount' => $amount,
                    'flow_type' => $flowType,
                    'direction' => $direction,
                    'entry_type' => $entryType,
                    'category_code' => $categoryCode,
                    'status' => $status,
                    'accounting_section' => $accounting['section'],
                    'accounting_type' => $accounting['type'],
                    'accounting_label' => $accounting['label'],
                    'semantic_markers' => $semanticMarkers,
                    'source_type' => (string)$entry['source_type'],
                    'source_id' => $entry['source_id'] ?? null,
                    'source_row_id' => $entry['source_row_id'] ?? null,
                ];
                $this->appendSourceEntryId($sourceTrace['totals']['admin_debt_total'], $id);
            }

            $cashDelta = $flowType === 'cash' ? $this->cashBalanceDelta($entry) : null;
            if ($cashDelta !== null) {
                $this->appendSourceEntryId($sourceTrace['totals']['ending_cash'], $id);
            }

            if ($isLowerAccounting || $isAdminDebt) {
                continue;
            }

            if ($flowType === 'cash' && $direction === 'in' && $entryType === 'cash_income') {
                if ($categoryCode === 'commercial_income') {
                    $periodTotals['commercial_income'] += $amount;
                    $this->appendSourceEntryId($sourceTrace['totals']['commercial_income'], $id);
                    $this->appendSourceEntryId($sourceTrace['totals']['total_cash_income'], $id);
                } elseif ($categoryCode !== 'cash_topup_from_card') {
                    $periodTotals['cash_income'] += $amount;
                    $this->appendSourceEntryId($sourceTrace['totals']['cash_income'], $id);
                    $this->appendSourceEntryId($sourceTrace['totals']['total_cash_income'], $id);
                } else {
                    $periodTotals['cash_topup_from_card_cash_side'] += $amount;
                }
            }

            if ($flowType === 'cash' && $direction === 'out' && $entryType === 'cash_expense') {
                $periodTotals['cash_expense'] += $amount;
                $this->appendSourceEntryId($sourceTrace['totals']['cash_expense'], $id);
            }

            if ($flowType === 'card' && $direction === 'out' && $entryType === 'card_expense') {
                $cardExpenseCount++;
                $periodTotals['card_expense'] += $amount;
                $this->appendSourceEntryId($sourceTrace['totals']['card_expense'], $id);
                if ($categoryCode === 'cash_topup_from_card') {
                    $periodTotals['cash_topup_from_card_card_side'] += $amount;
                }
            }

            if ($entryType === 'correction') {
                $periodTotals['corrections'] += $direction === 'out' ? -$amount : $amount;
                $this->appendSourceEntryId($sourceTrace['totals']['corrections_total'], $id);
            }

            $effectiveCategoryCode = $categoryCode ?? 'uncategorized_review';
            if (!$isLowerAccounting && isset($categories[$effectiveCategoryCode])) {
                $categories[$effectiveCategoryCode]['entry_count']++;
                $categories[$effectiveCategoryCode]['review_count'] += ($status === 'other_review' || $categoryCode === null) ? 1 : 0;
                $categories[$effectiveCategoryCode]['total'] += $amount;
                if ($flowType === 'cash') {
                    $categories[$effectiveCategoryCode]['cash_total'] += $amount;
                }
                if ($flowType === 'card') {
                    $categories[$effectiveCategoryCode]['card_total'] += $amount;
                }
                $this->appendSourceEntryId($categories[$effectiveCategoryCode]['source_entry_ids'], $id);
                $sourceTrace['categories'][$effectiveCategoryCode] = $categories[$effectiveCategoryCode]['source_entry_ids'];

                if ($flowType === 'card' && $direction === 'out' && $entryType === 'card_expense') {
                    if (!isset($cardByCategory[$effectiveCategoryCode])) {
                        $cardByCategory[$effectiveCategoryCode] = [
                            'category_code' => $effectiveCategoryCode,
                            'category_name' => $categories[$effectiveCategoryCode]['category_name'],
                            'total' => 0.0,
                            'entry_count' => 0,
                            'source_entry_ids' => [],
                        ];
                    }
                    $cardByCategory[$effectiveCategoryCode]['total'] += $amount;
                    $cardByCategory[$effectiveCategoryCode]['entry_count']++;
                    $this->appendSourceEntryId($cardByCategory[$effectiveCategoryCode]['source_entry_ids'], $id);
                }
            }
        }

        $categoryRows = array_values(array_filter(
            $categories,
            static fn (array $row): bool => $row['entry_count'] > 0 || abs((float)$row['total']) > 0.0001
        ));
        usort($categoryRows, static fn (array $a, array $b): int => strcmp((string)$a['category_code'], (string)$b['category_code']));

        $categoryTotalRow = [
            'cash_total' => 0.0,
            'card_total' => 0.0,
            'total' => 0.0,
            'entry_count' => 0,
            'review_count' => 0,
            'source_entry_ids' => [],
        ];
        foreach ($categoryRows as $row) {
            $categoryTotalRow['cash_total'] += (float)$row['cash_total'];
            $categoryTotalRow['card_total'] += (float)$row['card_total'];
            $categoryTotalRow['total'] += (float)$row['total'];
            $categoryTotalRow['entry_count'] += (int)$row['entry_count'];
            $categoryTotalRow['review_count'] += (int)$row['review_count'];
            foreach ($row['source_entry_ids'] as $entryId) {
                $this->appendSourceEntryId($categoryTotalRow['source_entry_ids'], (string)$entryId);
            }
        }

        $otherReviewTotal = 0.0;
        foreach ($otherReviewEntries as $entry) {
            if ($entry['amount'] !== null) {
                $otherReviewTotal += (float)$entry['amount'];
            }
        }
        $lowerAccountingTotal = 0.0;
        foreach ($lowerAccountingEntries as $entry) {
            if ($entry['amount'] !== null) {
                $lowerAccountingTotal += (float)$entry['amount'];
            }
        }
        $lowerAccountingSettlements = $this->lowerAccountingSettlementSummary($lowerAccountingEntries);
        $lowerAccountingOpenTotal = (float)($lowerAccountingSettlements['net_open_total'] ?? $lowerAccountingTotal);
        $adminDebt = $this->adminDebtSummary($workspaceId, $monthStart, $monthEnd, $adminDebtEntries);

        $accountableDashboard = $this->getAccountableDashboard($workspaceId, $userId);

        $totals = [
            'opening_cash' => $monthlyStart['opening_cash'],
            'total_cash_income' => $periodTotals['cash_income'] + $periodTotals['commercial_income'],
            'cash_income' => $periodTotals['cash_income'],
            'cash_expense' => $periodTotals['cash_expense'],
            'card_expense' => $periodTotals['card_expense'],
            'commercial_income' => $periodTotals['commercial_income'],
            'other_review_total' => $otherReviewTotal,
            'lower_accounting_total' => $lowerAccountingOpenTotal,
            'admin_debt_total' => $adminDebt['total'],
            'corrections_total' => $periodTotals['corrections'],
            'ending_cash' => $monthlyEnd['ending_cash'],
        ];
        $moneyPosition = $this->reportMoneyPosition($totals['ending_cash'], $accountableDashboard['summary'] ?? []);

        return [
            'header' => [
                'workspace' => [
                    'id' => $workspace['id'],
                    'name' => $workspace['name'],
                    'type' => $workspace['type'],
                ],
                'period' => [
                    'year' => $fromYear,
                    'month' => $fromMonth,
                    'month_key' => $isSingleMonth ? $monthlyStart['month_key'] : sprintf('%04d-%02d - %04d-%02d', $fromYear, $fromMonth, $toYear, $toMonth),
                    'from_year' => $fromYear,
                    'from_month' => $fromMonth,
                    'to_year' => $toYear,
                    'to_month' => $toMonth,
                    'from_month_key' => sprintf('%04d-%02d', $fromYear, $fromMonth),
                    'to_month_key' => sprintf('%04d-%02d', $toYear, $toMonth),
                    'start_date' => $monthStart,
                    'end_date_exclusive' => $monthEnd,
                    'is_range' => !$isSingleMonth,
                ],
                'currency' => $workspace['currency'] ?: 'EUR',
                'status' => $monthlyEnd['is_closed'] ? 'closed' : 'open',
                'is_closed' => (bool)$monthlyEnd['is_closed'],
                'generated_at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
                'entries_count' => $periodEntryCount,
                'review_count' => count($otherReviewEntries),
            ],
            'totals' => $totals,
            'money_position' => $moneyPosition,
            'blocks' => [
                'cash' => [
                    'opening_cash' => $totals['opening_cash'],
                    'opening_cash_basis' => $sourceTrace['basis']['opening_cash'],
                    'cash_income' => $totals['cash_income'],
                    'cash_topup_from_card' => $periodTotals['cash_topup_from_card_cash_side'],
                    'commercial_income' => $totals['commercial_income'],
                    'cash_expense' => $totals['cash_expense'],
                    'corrections_total' => $totals['corrections_total'],
                    'ending_cash' => $totals['ending_cash'],
                    'source_entry_ids' => $sourceTrace['totals']['ending_cash'],
                ],
                'money_position' => $moneyPosition,
                'card' => [
                    'card_expense' => $totals['card_expense'],
                    'cash_topup_to_cash' => $periodTotals['cash_topup_from_card_card_side'],
                    'entries_count' => $cardExpenseCount,
                    'review_count' => $cardReviewCount,
                    'by_category' => array_values($cardByCategory),
                    'source_entry_ids' => $sourceTrace['totals']['card_expense'],
                ],
                'categories' => [
                    'rows' => $categoryRows,
                    'total_row' => $categoryTotalRow,
                ],
                'other_review' => [
                    'count' => count($otherReviewEntries),
                    'total' => $otherReviewTotal,
                    'entries' => $otherReviewEntries,
                    'source_entry_ids' => $sourceTrace['totals']['other_review_total'],
                ],
                'lower_accounting' => [
                    'count' => count($lowerAccountingEntries),
                    'total' => $lowerAccountingOpenTotal,
                    'issued_total' => $lowerAccountingTotal,
                    'entries' => $lowerAccountingEntries,
                    'settlements' => $lowerAccountingSettlements,
                    'source_entry_ids' => $sourceTrace['totals']['lower_accounting_total'],
                ],
                'admin_debt' => $adminDebt,
            ],
            'source_trace' => $sourceTrace,
        ];
    }

    public function getLayer1SourceEntries(string $workspaceId, array $query, int $userId): array
    {
        $this->requireWorkspaceFullReader($workspaceId, $userId);
        $this->getWorkspace($workspaceId, $userId);
        $ids = $this->sourceEntryIdsFromQuery($query);
        if ($ids === []) {
            return [
                'entries' => [],
                'missing_ids' => [],
            ];
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("
            SELECT
                e.*,
                w.name AS workspace_name,
                f.type AS flow_type,
                f.name AS flow_name,
                c.code AS category_code,
                c.name_json AS category_name_json,
                a.name AS actor_name
            FROM v2_entries e
            INNER JOIN v2_workspaces w ON w.id = e.workspace_id
            INNER JOIN v2_flows f ON f.id = e.flow_id
            LEFT JOIN v2_categories c ON c.id = e.category_id
            LEFT JOIN v2_actors a ON a.id = e.actor_id
            WHERE e.workspace_id = ?
              AND e.archived_at IS NULL
              AND e.id IN ({$placeholders})
        ");
        $stmt->execute(array_merge([$workspaceId], $ids));

        $byId = [];
        foreach ($stmt->fetchAll() as $row) {
            $byId[(string)$row['id']] = $this->entryRow($row);
        }

        $entries = [];
        $missing = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $entries[] = $byId[$id];
            } else {
                $missing[] = $id;
            }
        }

        return [
            'entries' => $entries,
            'missing_ids' => $missing,
        ];
    }

    public function listLayer1SummarySnapshots(string $workspaceId, array $query, int $userId): array
    {
        $this->requireWorkspaceFullReader($workspaceId, $userId);
        $params = [$workspaceId];
        $where = ['workspace_id = ?', "report_type = 'layer1_summary'"];

        if (array_key_exists('year', $query) && $query['year'] !== '') {
            $year = $this->optionalInt($query, 'year', (int)date('Y'));
            $where[] = 'year = ?';
            $params[] = $year;
        }
        if (array_key_exists('month', $query) && $query['month'] !== '') {
            $month = $this->optionalInt($query, 'month', (int)date('n'));
            $where[] = 'month = ?';
            $params[] = $month;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_report_snapshots
            WHERE " . implode(' AND ', $where) . "
            ORDER BY year DESC, month DESC, version DESC, stored_at DESC
        ");
        $stmt->execute($params);

        return array_map([$this, 'reportSnapshotRow'], $stmt->fetchAll());
    }

    public function createLayer1SummarySnapshot(string $workspaceId, array $input, int $userId): array
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return FinDeskV2Database::transact(function () use ($workspaceId, $input, $userId): array {
                    $this->getWorkspace($workspaceId, $userId);
                    $this->requireWorkspaceWriter($workspaceId, $userId);
                    $year = $this->optionalInt($input, 'year', (int)date('Y'));
                    $month = $this->optionalInt($input, 'month', (int)date('n'));
                    $this->assertValidMonth($year, $month);

                    $report = $this->getLayer1SummaryReport($workspaceId, ['year' => $year, 'month' => $month], $userId);
                    $sourceTrace = $report['source_trace'] ?? [];
                    $sourceEntryIds = $this->flattenSourceEntryIds($sourceTrace);
                    $correctionIds = array_values(array_map(
                        'strval',
                        $sourceTrace['totals']['corrections_total'] ?? []
                    ));
                    $attachmentRefs = $this->attachmentRefsForEntryIds($workspaceId, $sourceEntryIds);
                    $status = FinDeskV2Support::optionalString($input, 'status', null, 40);
                    if ($status === null) {
                        $status = ($report['header']['is_closed'] ?? false) ? 'closed' : 'stored';
                    }
                    $status = FinDeskV2Support::enum($status, ['draft', 'stored', 'closed'], 'status');
                    if ($status === 'closed' && !($report['header']['is_closed'] ?? false)) {
                        throw new FinDeskV2HttpError(422, 'month_not_closed');
                    }
                    $comment = FinDeskV2Support::optionalString($input, 'comment', null, 1000);
                    $version = $this->nextReportSnapshotVersion($workspaceId, 'layer1_summary', $year, $month);
                    $generatedAt = $this->atomToSqlDateTime((string)($report['header']['generated_at'] ?? ''));
                    $closedAt = $status === 'closed' ? $this->closedAtForMonth($workspaceId, $year, $month) : null;
                    $snapshotId = FinDeskV2Support::uuid();
                    $contentPayload = [
                        'report_type' => 'layer1_summary',
                        'workspace_id' => $workspaceId,
                        'year' => $year,
                        'month' => $month,
                        'version' => $version,
                        'status' => $status,
                        'summary' => $report,
                        'source_entry_ids' => $sourceEntryIds,
                        'correction_ids' => $correctionIds,
                        'attachment_refs' => $attachmentRefs,
                        'forecast_snapshot' => null,
                    ];
                    $contentHash = hash('sha256', FinDeskV2Support::jsonEncode($contentPayload));

                    $this->db->prepare("
                        INSERT INTO v2_report_snapshots (
                            id, workspace_id, report_type, year, month, version, status,
                            generated_at, closed_at, comment, summary_json, source_trace_json,
                            source_entry_ids_json, correction_ids_json, attachment_refs_json,
                            forecast_snapshot_json, content_hash, created_by
                        )
                        VALUES (?, ?, 'layer1_summary', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?)
                    ")->execute([
                        $snapshotId,
                        $workspaceId,
                        $year,
                        $month,
                        $version,
                        $status,
                        $generatedAt,
                        $closedAt,
                        $comment,
                        FinDeskV2Support::jsonEncode($report),
                        FinDeskV2Support::jsonEncode($sourceTrace),
                        FinDeskV2Support::jsonEncode($sourceEntryIds),
                        FinDeskV2Support::jsonEncode($correctionIds),
                        FinDeskV2Support::jsonEncode($attachmentRefs),
                        $contentHash,
                        $userId,
                    ]);

                    $snapshot = $this->reportSnapshotById($snapshotId);
                    $this->audit($workspaceId, 'report_snapshot', $snapshotId, 'layer1_snapshot_create', null, $snapshot, $userId);

                    return $snapshot;
                });
            } catch (PDOException $e) {
                if (!$this->isReportSnapshotVersionConflict($e)) {
                    throw $e;
                }
                usleep(25000 * ($attempt + 1));
            }
        }

        throw new FinDeskV2HttpError(409, 'snapshot_version_conflict');
    }

    public function listOperationalReportFragments(string $workspaceId, array $query, int $userId): array
    {
        $this->requireWorkspaceFullReader($workspaceId, $userId);
        $limit = max(1, min(100, $this->optionalInt($query, 'limit', 30)));
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_report_batches
            WHERE workspace_id = ?
              AND batch_type = 'operational_fragment'
              AND status <> 'superseded'
            ORDER BY created_at DESC
            LIMIT {$limit}
        ");
        $stmt->execute([$workspaceId]);

        return array_map([$this, 'reportBatchRow'], $stmt->fetchAll());
    }

    public function getOperationalReportFragment(string $workspaceId, string $batchId, int $userId): array
    {
        $this->requireWorkspaceFullReader($workspaceId, $userId);
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_report_batches
            WHERE workspace_id = ?
              AND id = ?
              AND batch_type = 'operational_fragment'
            LIMIT 1
        ");
        $stmt->execute([$workspaceId, $batchId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new FinDeskV2HttpError(404, 'report_fragment_not_found');
        }

        return $this->reportBatchRow($row);
    }

    public function updateOperationalReportFragment(string $workspaceId, string $batchId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $batchId, $input, $userId): array {
            $this->getWorkspace($workspaceId, $userId);
            $this->requireWorkspaceWriter($workspaceId, $userId);
            $before = $this->getOperationalReportFragment($workspaceId, $batchId, $userId);

            $status = array_key_exists('status', $input)
                ? FinDeskV2Support::enum(
                    FinDeskV2Support::optionalString($input, 'status', (string)$before['status'], 40) ?? (string)$before['status'],
                    ['created', 'sent', 'requires_update', 'returned_for_revision', 'superseded'],
                    'status'
                )
                : (string)$before['status'];
            $title = FinDeskV2Support::optionalString($input, 'title', (string)$before['title'], 190) ?? (string)$before['title'];
            $closedAt = $before['closed_at'] ?? null;
            if (array_key_exists('closed_date', $input) || array_key_exists('close_date', $input)) {
                $closedDate = FinDeskV2Support::date([
                    'date' => $input['closed_date'] ?? $input['close_date'],
                ]);
                if ($closedDate < (string)$before['start_date']) {
                    throw new FinDeskV2HttpError(422, 'invalid_closed_date');
                }
                $closedAt = $closedDate . ' 23:59:59';
            }
            if (!empty($input['rebuild_from_entries'])) {
                $sourceEntryIds = array_values(array_map('strval', $before['source_entry_ids'] ?? []));
                if ($sourceEntryIds === []) {
                    throw new FinDeskV2HttpError(422, 'missing_entry_ids');
                }
                $report = $this->buildOperationalFragmentReport($workspaceId, $sourceEntryIds, $userId, $batchId);
                $header = $report['header'];
                $sourceTrace = $report['source_trace'];
                $sourceEntryIds = array_values(array_map('strval', $sourceTrace['fragment_entry_ids'] ?? []));
                $entrySnapshots = $report['entries'] ?? [];
                $status = $status === 'superseded' ? 'superseded' : 'created';
                $generatedAt = $this->atomToSqlDateTime((string)$header['generated_at']);
                $contentPayload = [
                    'report_type' => 'operational_fragment',
                    'workspace_id' => $workspaceId,
                    'batch_id' => $batchId,
                    'title' => $title,
                    'status' => $status,
                    'closed_at' => $closedAt,
                    'summary' => $report,
                    'source_entry_ids' => $sourceEntryIds,
                    'entry_snapshot' => $entrySnapshots,
                ];
                $contentHash = hash('sha256', FinDeskV2Support::jsonEncode($contentPayload));

                $this->db->prepare("
                    UPDATE v2_report_batches
                    SET title = ?,
                        status = ?,
                        start_date = ?,
                        end_date = ?,
                        from_entry_id = ?,
                        to_entry_id = ?,
                        entry_count = ?,
                        generated_at = ?,
                        closed_at = ?,
                        summary_json = ?,
                        source_trace_json = ?,
                        source_entry_ids_json = ?,
                        entry_snapshot_json = ?,
                        content_hash = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                      AND workspace_id = ?
                      AND batch_type = 'operational_fragment'
                    LIMIT 1
                ")->execute([
                    $title,
                    $status,
                    $header['start_date'],
                    $header['end_date'],
                    $header['from_entry_id'],
                    $header['to_entry_id'],
                    count($sourceEntryIds),
                    $generatedAt,
                    $closedAt,
                    FinDeskV2Support::jsonEncode($report),
                    FinDeskV2Support::jsonEncode($sourceTrace),
                    FinDeskV2Support::jsonEncode($sourceEntryIds),
                    FinDeskV2Support::jsonEncode($entrySnapshots),
                    $contentHash,
                    $batchId,
                    $workspaceId,
                ]);

                $this->db->prepare("DELETE FROM v2_report_batch_entries WHERE batch_id = ?")->execute([$batchId]);
                $insertEntry = $this->db->prepare("
                    INSERT INTO v2_report_batch_entries (id, batch_id, entry_id, `row_number`, entry_snapshot_json)
                    VALUES (?, ?, ?, ?, ?)
                ");
                foreach ($entrySnapshots as $index => $entry) {
                    $insertEntry->execute([
                        FinDeskV2Support::uuid(),
                        $batchId,
                        (string)$entry['id'],
                        $index + 1,
                        FinDeskV2Support::jsonEncode($entry),
                    ]);
                }

                $after = $this->getOperationalReportFragment($workspaceId, $batchId, $userId);
                $workspace = $this->getWorkspace($workspaceId, $userId);
                $this->writeOperationalReportHtmlFile($after, $workspace);
                $this->storeOperationalReportFragmentHtmlSnapshot($workspaceId, $after, $workspace, $userId, 'stored', 'Snapshot after report revision save', false);
                $this->audit($workspaceId, 'report_batch', $batchId, 'operational_fragment_rebuild', $before, $after, $userId);

                return $after;
            }
            if (in_array($status, ['returned_for_revision', 'superseded'], true)) {
                $closedAt = null;
            }

            $stmt = $this->db->prepare("
                UPDATE v2_report_batches
                SET title = ?,
                    status = ?,
                    closed_at = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
                  AND workspace_id = ?
                  AND batch_type = 'operational_fragment'
                LIMIT 1
            ");
            $stmt->execute([$title, $status, $closedAt, $batchId, $workspaceId]);

            $after = $this->getOperationalReportFragment($workspaceId, $batchId, $userId);
            $contentHash = hash('sha256', FinDeskV2Support::jsonEncode([
                'report_type' => 'operational_fragment',
                'workspace_id' => $workspaceId,
                'batch_id' => $batchId,
                'title' => $after['title'],
                'status' => $after['status'],
                'closed_at' => $after['closed_at'],
                'summary' => $after['summary'],
                'source_entry_ids' => $after['source_entry_ids'],
                'entry_snapshot' => $after['entry_snapshot'],
            ]));
            $this->db->prepare("
                UPDATE v2_report_batches
                SET content_hash = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
                  AND workspace_id = ?
                  AND batch_type = 'operational_fragment'
                LIMIT 1
            ")->execute([$contentHash, $batchId, $workspaceId]);
            $after = $this->getOperationalReportFragment($workspaceId, $batchId, $userId);
            $workspace = $this->getWorkspace($workspaceId, $userId);
            $this->writeOperationalReportHtmlFile($after, $workspace);
            $this->storeOperationalReportFragmentHtmlSnapshot($workspaceId, $after, $workspace, $userId, 'stored', 'Auto snapshot after operational fragment update', false);
            $this->audit($workspaceId, 'report_batch', $batchId, 'operational_fragment_update', $before, $after, $userId);

            return $after;
        });
    }

    public function previewOperationalReportFragment(string $workspaceId, array $input, int $userId): array
    {
        $this->requireWorkspaceFullReader($workspaceId, $userId);
        $entryIds = $this->operationalFragmentEntryIds($input);
        $report = $this->buildOperationalFragmentReport($workspaceId, $entryIds, $userId);

        return [
            'report' => $report,
            'entry_ids' => $report['source_trace']['fragment_entry_ids'] ?? [],
            'can_create' => count($report['source_trace']['locked_entry_ids'] ?? []) === 0,
        ];
    }

    public function createOperationalReportFragment(string $workspaceId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $input, $userId): array {
            $workspace = $this->getWorkspace($workspaceId, $userId);
            $this->requireWorkspaceWriter($workspaceId, $userId);
            $entryIds = $this->operationalFragmentEntryIds($input);
            $report = $this->buildOperationalFragmentReport($workspaceId, $entryIds, $userId);
            $locked = $report['source_trace']['locked_entry_ids'] ?? [];
            if ($locked !== [] && empty($input['allow_locked_entries'])) {
                throw new FinDeskV2HttpError(409, FinDeskV2Support::jsonEncode([
                    'error' => 'report_fragment_contains_locked_entries',
                    'locked_entry_ids' => $locked,
                ]));
            }

            $header = $report['header'];
            $sourceTrace = $report['source_trace'];
            $sourceEntryIds = array_values(array_map('strval', $sourceTrace['fragment_entry_ids'] ?? []));
            $entrySnapshots = $report['entries'] ?? [];
            $supersededDuplicateIds = $this->supersedeDuplicateOperationalReportFragments($workspaceId, $sourceEntryIds, $userId);
            $batchId = FinDeskV2Support::uuid();
            $title = FinDeskV2Support::optionalString($input, 'title', null, 190)
                ?? ('Отчетный фрагмент ' . (string)$header['range_label']);
            $status = FinDeskV2Support::enum(
                FinDeskV2Support::optionalString($input, 'status', 'created', 40) ?? 'created',
                ['draft', 'created', 'sent'],
                'status'
            );
            $closedAt = null;
            if (array_key_exists('closed_date', $input) || array_key_exists('close_date', $input)) {
                $closedDate = FinDeskV2Support::date([
                    'date' => $input['closed_date'] ?? $input['close_date'],
                ]);
                if ($closedDate < (string)$header['start_date']) {
                    throw new FinDeskV2HttpError(422, 'invalid_closed_date');
                }
                $closedAt = $closedDate . ' 23:59:59';
            }
            $generatedAt = $this->atomToSqlDateTime((string)$header['generated_at']);
            $contentPayload = [
                'report_type' => 'operational_fragment',
                'workspace_id' => $workspaceId,
                'batch_id' => $batchId,
                'title' => $title,
                'status' => $status,
                'closed_at' => $closedAt,
                'summary' => $report,
                'source_entry_ids' => $sourceEntryIds,
                'entry_snapshot' => $entrySnapshots,
            ];
            $contentHash = hash('sha256', FinDeskV2Support::jsonEncode($contentPayload));
            $htmlFilename = $this->operationalReportHtmlRelativePath($workspaceId, $batchId);

            $this->db->prepare("
                INSERT INTO v2_report_batches (
                    id, workspace_id, batch_type, title, status, start_date, end_date,
                    from_entry_id, to_entry_id, entry_count, generated_at, closed_at,
                    html_filename, summary_json, source_trace_json, source_entry_ids_json,
                    entry_snapshot_json, content_hash, created_by
                )
                VALUES (?, ?, 'operational_fragment', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $batchId,
                $workspaceId,
                $title,
                $status,
                $header['start_date'],
                $header['end_date'],
                $header['from_entry_id'],
                $header['to_entry_id'],
                count($sourceEntryIds),
                $generatedAt,
                $closedAt,
                $htmlFilename,
                FinDeskV2Support::jsonEncode($report),
                FinDeskV2Support::jsonEncode($sourceTrace),
                FinDeskV2Support::jsonEncode($sourceEntryIds),
                FinDeskV2Support::jsonEncode($entrySnapshots),
                $contentHash,
                $userId,
            ]);

            $insertEntry = $this->db->prepare("
                INSERT INTO v2_report_batch_entries (id, batch_id, entry_id, `row_number`, entry_snapshot_json)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($entrySnapshots as $index => $entry) {
                $insertEntry->execute([
                    FinDeskV2Support::uuid(),
                    $batchId,
                    (string)$entry['id'],
                    $index + 1,
                    FinDeskV2Support::jsonEncode($entry),
                ]);
            }

            $batch = $this->getOperationalReportFragment($workspaceId, $batchId, $userId);
            $this->writeOperationalReportHtmlFile($batch, $workspace);
            $this->storeOperationalReportFragmentHtmlSnapshot($workspaceId, $batch, $workspace, $userId, 'stored', 'Initial operational fragment HTML snapshot', false);
            $this->audit($workspaceId, 'report_batch', $batchId, 'operational_fragment_create', null, $batch + [
                'superseded_duplicate_report_ids' => $supersededDuplicateIds,
            ], $userId);

            return $batch;
        });
    }

    public function listOperationalReportFragmentHtmlSnapshots(string $workspaceId, string $batchId, array $query, int $userId): array
    {
        $this->getOperationalReportFragment($workspaceId, $batchId, $userId);
        if (!$this->operationalHtmlSnapshotSchemaIsAvailable()) {
            return [];
        }

        $limit = max(1, min(100, $this->optionalInt($query, 'limit', 30)));
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_report_batch_html_snapshots
            WHERE workspace_id = ?
              AND batch_id = ?
            ORDER BY version DESC, created_at DESC
            LIMIT {$limit}
        ");
        $stmt->execute([$workspaceId, $batchId]);

        return array_map(fn (array $row): array => $this->reportBatchHtmlSnapshotRow($row, false), $stmt->fetchAll());
    }

    public function getOperationalReportFragmentHtmlSnapshot(string $workspaceId, string $batchId, string $snapshotId, int $userId): array
    {
        $this->getOperationalReportFragment($workspaceId, $batchId, $userId);
        if (!$this->operationalHtmlSnapshotSchemaIsAvailable()) {
            throw new FinDeskV2HttpError(503, 'report_html_snapshot_schema_missing');
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_report_batch_html_snapshots
            WHERE id = ?
              AND workspace_id = ?
              AND batch_id = ?
            LIMIT 1
        ");
        $stmt->execute([$snapshotId, $workspaceId, $batchId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new FinDeskV2HttpError(404, 'report_html_snapshot_not_found');
        }

        return $this->reportBatchHtmlSnapshotRow($row, true);
    }

    public function createOperationalReportFragmentHtmlSnapshot(string $workspaceId, string $batchId, array $input, int $userId): array
    {
        $this->ensureReportPackageSchema();

        return FinDeskV2Database::transact(function () use ($workspaceId, $batchId, $input, $userId): array {
            $workspace = $this->getWorkspace($workspaceId, $userId);
            $this->requireWorkspaceWriter($workspaceId, $userId);
            $batch = $this->getOperationalReportFragment($workspaceId, $batchId, $userId);
            $status = FinDeskV2Support::enum(
                FinDeskV2Support::optionalString($input, 'status', $batch['closed_at'] === null ? 'stored' : 'closed', 40)
                    ?? ($batch['closed_at'] === null ? 'stored' : 'closed'),
                ['stored', 'closed'],
                'status'
            );
            $comment = FinDeskV2Support::optionalString($input, 'comment', null, 1000);
            $snapshot = $this->storeOperationalReportFragmentHtmlSnapshot($workspaceId, $batch, $workspace, $userId, $status, $comment, true);
            $this->audit($workspaceId, 'report_html_snapshot', $snapshot['id'], 'operational_fragment_html_snapshot_create', null, $snapshot, $userId);

            return $snapshot;
        });
    }

    public function listOperationalReportPackages(string $workspaceId, array $query, int $userId): array
    {
        $this->requireWorkspaceFullReader($workspaceId, $userId);
        $this->ensureReportPackageSchema();

        $limit = max(1, min(100, $this->optionalInt($query, 'limit', 30)));
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_report_packages
            WHERE workspace_id = ?
              AND package_type = 'operational_fragment_package'
            ORDER BY created_at DESC
            LIMIT {$limit}
        ");
        $stmt->execute([$workspaceId]);

        return array_map([$this, 'reportPackageRow'], $stmt->fetchAll());
    }

    public function getOperationalReportPackage(string $workspaceId, string $packageId, int $userId): array
    {
        $this->requireWorkspaceFullReader($workspaceId, $userId);
        $this->ensureReportPackageSchema();

        $package = $this->reportPackageById($workspaceId, $packageId);
        if ($package === null) {
            throw new FinDeskV2HttpError(404, 'report_package_not_found');
        }

        return $package;
    }

    public function createOperationalReportPackage(string $workspaceId, array $input, int $userId): array
    {
        $this->ensureReportPackageSchema();

        return FinDeskV2Database::transact(function () use ($workspaceId, $input, $userId): array {
            $workspace = $this->getWorkspace($workspaceId, $userId);
            $this->requireWorkspaceWriter($workspaceId, $userId);

            $fragmentIds = $this->operationalPackageFragmentIds($input);
            $fragments = $this->operationalPackageFragments($workspaceId, $fragmentIds);
            foreach ($fragments as $fragment) {
                if (!$this->operationalFragmentIsClosed($fragment)) {
                    throw new FinDeskV2HttpError(422, 'report_package_requires_closed_fragments');
                }
            }

            usort($fragments, static function (array $a, array $b): int {
                $date = strcmp((string)$a['start_date'], (string)$b['start_date']);
                if ($date !== 0) {
                    return $date;
                }
                return strcmp((string)$a['created_at'], (string)$b['created_at']);
            });

            $htmlSnapshots = [];
            foreach ($fragments as $fragment) {
                $snapshot = $this->latestOperationalHtmlSnapshotForBatch((string)$fragment['id']);
                if ($snapshot === null) {
                    $snapshot = $this->storeOperationalReportFragmentHtmlSnapshot($workspaceId, $fragment, $workspace, $userId, 'closed', 'Package freeze HTML snapshot', true);
                }
                $htmlSnapshots[(string)$fragment['id']] = $snapshot;
            }

            $packageId = FinDeskV2Support::uuid();
            $startDate = (string)$fragments[0]['start_date'];
            $endDate = (string)$fragments[count($fragments) - 1]['end_date'];
            $title = FinDeskV2Support::optionalString($input, 'title', null, 190)
                ?? ('Пакет отчетных фрагментов ' . $startDate . ' - ' . $endDate);
            $status = FinDeskV2Support::enum(
                FinDeskV2Support::optionalString($input, 'status', 'created', 40) ?? 'created',
                ['created', 'sent'],
                'status'
            );
            $comment = FinDeskV2Support::optionalString($input, 'comment', null, 1000);
            $summary = $this->operationalReportPackageSummary($workspaceId, $fragments, $htmlSnapshots, $title, $comment);
            $sourceEntryIds = $summary['source_entry_ids'];
            $orderedFragmentIds = array_map(static fn (array $fragment): string => (string)$fragment['id'], $fragments);
            $htmlFilename = $this->reportPackageHtmlRelativePath($workspaceId, $packageId);
            $contentPayload = [
                'package_type' => 'operational_fragment_package',
                'workspace_id' => $workspaceId,
                'package_id' => $packageId,
                'title' => $title,
                'status' => $status,
                'summary' => $summary,
                'fragment_ids' => $orderedFragmentIds,
                'html_snapshot_ids' => array_map(static fn (array $snapshot): string => (string)$snapshot['id'], array_values($htmlSnapshots)),
                'source_entry_ids' => $sourceEntryIds,
            ];
            $contentHash = hash('sha256', FinDeskV2Support::jsonEncode($contentPayload));

            $this->db->prepare("
                INSERT INTO v2_report_packages (
                    id, workspace_id, package_type, title, status, start_date, end_date,
                    fragment_count, entry_count, generated_at, closed_at, comment, html_filename,
                    summary_json, fragment_ids_json,
                    source_entry_ids_json, content_hash, created_by
                )
                VALUES (?, ?, 'operational_fragment_package', ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $packageId,
                $workspaceId,
                $title,
                $status,
                $startDate,
                $endDate,
                count($fragments),
                (int)($summary['header']['entries_count'] ?? 0),
                $summary['header']['closed_at'] ?? null,
                $comment,
                $htmlFilename,
                FinDeskV2Support::jsonEncode($summary),
                FinDeskV2Support::jsonEncode($orderedFragmentIds),
                FinDeskV2Support::jsonEncode($sourceEntryIds),
                $contentHash,
                $userId,
            ]);

            $insertFragment = $this->db->prepare("
                INSERT INTO v2_report_package_items (
                    id, package_id, batch_id, html_snapshot_id, item_order,
                    fragment_snapshot_json, html_snapshot_json
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($fragments as $index => $fragment) {
                $snapshot = $htmlSnapshots[(string)$fragment['id']];
                $insertFragment->execute([
                    FinDeskV2Support::uuid(),
                    $packageId,
                    (string)$fragment['id'],
                    (string)$snapshot['id'],
                    $index + 1,
                    FinDeskV2Support::jsonEncode($fragment),
                    FinDeskV2Support::jsonEncode($snapshot),
                ]);
            }

            $package = $this->getOperationalReportPackage($workspaceId, $packageId, $userId);
            $this->writeReportPackageHtmlFile($package, $workspace);
            $this->storeReportVersion($workspaceId, $packageId, 'operational_package', $status, $htmlFilename, $contentHash, $package, $userId);
            $this->audit($workspaceId, 'report_package', $packageId, 'operational_fragment_package_create', null, $package, $userId);

            return $package;
        });
    }

    public function renderOperationalReportFragmentHtml(string $workspaceId, string $batchId, int $userId, bool $autoPrint = false): string
    {
        $batch = $this->getOperationalReportFragment($workspaceId, $batchId, $userId);
        $workspace = $this->getWorkspace($workspaceId, $userId);

        return $this->operationalReportHtml($batch, $workspace, $autoPrint);
    }

    public function renderOperationalReportPackageHtml(string $workspaceId, string $packageId, int $userId, bool $autoPrint = false): string
    {
        $package = $this->getOperationalReportPackage($workspaceId, $packageId, $userId);
        $workspace = $this->getWorkspace($workspaceId, $userId);

        return $this->reportPackageHtml($package, $workspace, $autoPrint);
    }

    public function previewReportBatch(string $workspaceId, array $input, int $userId): array
    {
        return $this->previewOperationalReportFragment($workspaceId, $input, $userId);
    }

    public function listReportBatches(string $workspaceId, array $query, int $userId): array
    {
        return $this->listOperationalReportFragments($workspaceId, $query, $userId);
    }

    public function getReportBatch(string $workspaceId, string $reportId, int $userId): array
    {
        return $this->getOperationalReportFragment($workspaceId, $reportId, $userId);
    }

    public function createReportBatch(string $workspaceId, array $input, int $userId): array
    {
        return $this->createOperationalReportFragment($workspaceId, $input, $userId);
    }

    public function getCategoryMatrixReport(string $workspaceId, array $query, int $userId): array
    {
        $this->requireWorkspaceFullReader($workspaceId, $userId);
        $year = $this->optionalInt($query, 'year', (int)date('Y'));
        $months = array_fill_keys(array_map('strval', range(1, 12)), 0.0);
        $rows = [];

        foreach ($this->listCategories($workspaceId, $userId) as $category) {
            $rows[$category['code']] = [
                'category_code' => $category['code'],
                'category_name' => $category['name'],
                'direction' => $category['direction'],
                'months' => $months,
                'breakdown' => [],
                'total' => 0.0,
            ];
        }

        $stmt = $this->db->prepare("
            SELECT
                MONTH(e.date) AS report_month,
                f.type AS flow_type,
                e.direction,
                c.code AS category_code,
                COALESCE(SUM(e.amount), 0) AS total
            FROM v2_entries e
            INNER JOIN v2_flows f ON f.id = e.flow_id
            INNER JOIN v2_categories c ON c.id = e.category_id
            WHERE e.workspace_id = ?
              AND e.archived_at IS NULL
              AND YEAR(e.date) = ?
              AND e.amount IS NOT NULL
              AND e.status IN (" . $this->countedStatusSqlList() . ")
            GROUP BY MONTH(e.date), f.type, e.direction, c.code
            ORDER BY c.code ASC, report_month ASC, f.type ASC, e.direction ASC
        ");
        $stmt->execute([$workspaceId, $year]);

        foreach ($stmt->fetchAll() as $row) {
            $categoryCode = (string)$row['category_code'];
            if (!isset($rows[$categoryCode])) {
                continue;
            }

            $month = (string)(int)$row['report_month'];
            $flowType = (string)$row['flow_type'];
            $direction = (string)$row['direction'];
            $total = (float)$row['total'];
            $breakdownKey = "{$flowType}:{$direction}";

            $rows[$categoryCode]['months'][$month] += $total;
            $rows[$categoryCode]['total'] += $total;
            $rows[$categoryCode]['breakdown'][$month][$breakdownKey] = $total;
        }

        return [
            'workspace_id' => $workspaceId,
            'year' => $year,
            'months' => range(1, 12),
            'rows' => array_values($rows),
        ];
    }

    public function getOtherReviewReport(string $workspaceId, int $userId): array
    {
        $entries = $this->listOtherExpenseQueue($workspaceId, $userId);
        $total = 0.0;
        foreach ($entries as $entry) {
            if ($entry['amount'] !== null) {
                $total += (float)$entry['amount'];
            }
        }

        return [
            'workspace_id' => $workspaceId,
            'count' => count($entries),
            'total' => $total,
            'entries' => $entries,
        ];
    }

    public function listEntries(string $workspaceId, array $query, int $userId): array
    {
        $access = $this->workspaceAccess($workspaceId, $userId);
        $visibility = $this->entryVisibilitySql('e', $access, $userId, 'entries_forbidden');
        $params = [$workspaceId];
        $where = ['e.workspace_id = ?', 'e.archived_at IS NULL'];
        if ($visibility['sql'] !== null) {
            $where[] = $visibility['sql'];
            array_push($params, ...$visibility['params']);
        }
        $fromDate = null;
        $toDate = null;

        if (!empty($query['from']) || !empty($query['date_from'])) {
            $fromDate = FinDeskV2Support::date([
                'date' => $query['from'] ?? $query['date_from'],
            ]);
        }
        if (!empty($query['to']) || !empty($query['date_to'])) {
            $toDate = FinDeskV2Support::date([
                'date' => $query['to'] ?? $query['date_to'],
            ]);
        }

        if ($fromDate !== null || $toDate !== null) {
            if ($fromDate !== null && $toDate !== null && strcmp($fromDate, $toDate) > 0) {
                [$fromDate, $toDate] = [$toDate, $fromDate];
            }
            if ($fromDate !== null) {
                $where[] = 'e.date >= ?';
                $params[] = $fromDate;
            }
            if ($toDate !== null) {
                $where[] = 'e.date <= ?';
                $params[] = $toDate;
            }
        } elseif (!empty($query['year'])) {
            $where[] = 'YEAR(e.date) = ?';
            $params[] = (int)$query['year'];
            if (!empty($query['month'])) {
                $where[] = 'MONTH(e.date) = ?';
                $params[] = (int)$query['month'];
            }
        }

        $stmt = $this->db->prepare("
            SELECT
                e.*,
                w.name AS workspace_name,
                f.type AS flow_type,
                f.name AS flow_name,
                c.code AS category_code,
                c.name_json AS category_name_json,
                a.name AS actor_name
            FROM v2_entries e
            INNER JOIN v2_workspaces w ON w.id = e.workspace_id
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
        $this->requireWorkspaceFullReader($workspaceId, $userId);
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
            WHERE e.workspace_id = ?
              AND e.archived_at IS NULL
              AND (
                (e.status = 'other_review' AND e.entry_type = 'cash_expense' AND c.code = 'other')
                OR (
                  e.category_id IS NULL
                  AND e.amount IS NOT NULL
                  AND e.entry_type <> 'correction'
                  AND e.status IN (" . $this->countedStatusSqlList() . ")
                )
              )
            ORDER BY e.date ASC, e.created_seq ASC
        ");
        $stmt->execute([$workspaceId]);

        return array_values(array_filter(
            array_map([$this, 'entryRow'], $stmt->fetchAll()),
            static fn (array $entry): bool => ($entry['accounting_section'] ?? null) !== 'lower_accounting'
        ));
    }

    public function createEntry(string $workspaceId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $input, $userId): array {
            return $this->createEntryInCurrentTransaction($workspaceId, $input, $userId);
        });
    }

    public function previewEntryParse(string $workspaceId, array $input, int $userId): array
    {
        $this->getWorkspace($workspaceId, $userId);
        $this->requireWorkspaceWriter($workspaceId, $userId);
        $flow = $this->getFlowForWorkspace(FinDeskV2Support::requireString($input, 'flow_id', 36), $workspaceId);
        $entry = $this->normalizeEntryInput($workspaceId, $flow, $input, false);

        return $this->entryPreviewRow($workspaceId, $flow, $entry);
    }

    public function listQuickNotes(string $workspaceId, array $query, int $userId): array
    {
        $this->ensureQuickNoteSchema();
        $this->getWorkspace($workspaceId, $userId);
        $this->requireWorkspaceFullReader($workspaceId, $userId);
        $status = FinDeskV2Support::optionalString($query, 'status', null, 40);
        $where = ['workspace_id = ?', 'archived_at IS NULL'];
        $params = [$workspaceId];
        if ($status !== null) {
            $where[] = 'status = ?';
            $params[] = $status;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_quick_notes
            WHERE " . implode(' AND ', $where) . "
            ORDER BY note_date DESC, updated_at DESC
            LIMIT 80
        ");
        $stmt->execute($params);

        return array_map([$this, 'quickNoteRow'], $stmt->fetchAll());
    }

    public function createQuickNote(string $workspaceId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $input, $userId): array {
            $this->ensureQuickNoteSchema();
            $this->getWorkspace($workspaceId, $userId);
            $this->requireWorkspaceEntryWriter($workspaceId, $userId);
            $id = FinDeskV2Support::uuid();
            $noteDate = FinDeskV2Support::date($input, 'note_date');
            $rawText = FinDeskV2Support::requireString($input, 'raw_text', 8000);
            $title = FinDeskV2Support::optionalString($input, 'title', null, 190)
                ?? 'Заметка от ' . date('d.m.y', strtotime($noteDate));

            $this->db->prepare("
                INSERT INTO v2_quick_notes (id, workspace_id, created_by, note_date, title, raw_text, status)
                VALUES (?, ?, ?, ?, ?, ?, 'draft')
            ")->execute([$id, $workspaceId, $userId, $noteDate, $title, $rawText]);

            $note = $this->getQuickNote($workspaceId, $id, $userId);
            $this->audit($workspaceId, 'quick_note', $id, 'create', null, $note, $userId);

            return $note;
        });
    }

    public function updateQuickNote(string $workspaceId, string $noteId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $noteId, $input, $userId): array {
            $before = $this->getQuickNote($workspaceId, $noteId, $userId);
            $this->requireWorkspaceEntryWriter($workspaceId, $userId);
            $noteDate = FinDeskV2Support::date(['note_date' => $input['note_date'] ?? $before['note_date']], 'note_date');
            $rawText = FinDeskV2Support::requireString(['raw_text' => $input['raw_text'] ?? $before['raw_text']], 'raw_text', 8000);
            $title = FinDeskV2Support::optionalString($input, 'title', $before['title'], 190) ?? $before['title'];
            $status = FinDeskV2Support::enum(
                FinDeskV2Support::optionalString($input, 'status', $before['status'], 40) ?? $before['status'],
                ['draft', 'reviewed', 'converted'],
                'status'
            );

            $this->db->prepare("
                UPDATE v2_quick_notes
                SET note_date = ?, title = ?, raw_text = ?, status = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND workspace_id = ?
            ")->execute([$noteDate, $title, $rawText, $status, $noteId, $workspaceId]);

            $after = $this->getQuickNote($workspaceId, $noteId, $userId);
            $this->audit($workspaceId, 'quick_note', $noteId, 'update', $before, $after, $userId);

            return $after;
        });
    }

    public function deleteQuickNote(string $workspaceId, string $noteId, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $noteId, $userId): array {
            $before = $this->getQuickNote($workspaceId, $noteId, $userId);
            $this->requireWorkspaceEntryWriter($workspaceId, $userId);
            $this->db->prepare("UPDATE v2_quick_notes SET archived_at = CURRENT_TIMESTAMP WHERE id = ? AND workspace_id = ?")
                ->execute([$noteId, $workspaceId]);
            $after = $before;
            $after['archived'] = true;
            $this->audit($workspaceId, 'quick_note', $noteId, 'archive', $before, $after, $userId);

            return $after;
        });
    }

    public function previewQuickNoteConversion(string $workspaceId, string $noteId, array $input, int $userId): array
    {
        $note = $this->getQuickNote($workspaceId, $noteId, $userId);
        $this->requireWorkspaceEntryWriter($workspaceId, $userId);
        $flow = $this->getFlowForWorkspace(FinDeskV2Support::requireString($input, 'flow_id', 36), $workspaceId);
        $date = FinDeskV2Support::date(['date' => $input['date'] ?? $note['note_date']]);
        $lines = $this->quickNoteLines($note['raw_text']);
        $items = [];
        foreach ($lines as $index => $line) {
            $entry = $this->normalizeEntryInput($workspaceId, $flow, [
                'date' => $date,
                'raw_text' => $line,
                'source_type' => 'assistant',
                'source_id' => $noteId,
            ], false);
            $preview = $this->entryPreviewRow($workspaceId, $flow, $entry);
            $items[] = [
                'line_index' => $index,
                'raw_text' => $line,
                'preview' => $preview,
                'duplicate_candidates' => $this->quickNoteDuplicateCandidates($workspaceId, $preview),
                'enabled' => true,
            ];
        }

        $this->db->prepare("
            UPDATE v2_quick_notes
            SET status = 'reviewed', smith_preview_json = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND workspace_id = ?
        ")->execute([FinDeskV2Support::jsonEncode($items), $noteId, $workspaceId]);

        return [
            'note' => $this->getQuickNote($workspaceId, $noteId, $userId),
            'items' => $items,
        ];
    }

    public function convertQuickNote(string $workspaceId, string $noteId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $noteId, $input, $userId): array {
            $note = $this->getQuickNote($workspaceId, $noteId, $userId);
            $this->requireWorkspaceEntryWriter($workspaceId, $userId);
            $flow = $this->getFlowForWorkspace(FinDeskV2Support::requireString($input, 'flow_id', 36), $workspaceId);
            $date = FinDeskV2Support::date(['date' => $input['date'] ?? $note['note_date']]);
            $selected = is_array($input['items'] ?? null) ? $input['items'] : [];
            $selectedByIndex = [];
            foreach ($selected as $item) {
                if (!is_array($item) || empty($item['enabled'])) {
                    continue;
                }
                $selectedByIndex[(int)($item['line_index'] ?? -1)] = [
                    'category_code' => FinDeskV2Support::optionalString($item, 'category_code', null, 80),
                ];
            }

            $this->ensureQuickNoteImportSource($workspaceId, $note);
            $created = [];
            foreach ($this->quickNoteLines($note['raw_text']) as $index => $line) {
                if (!isset($selectedByIndex[$index])) {
                    continue;
                }
                $payload = [
                    'flow_id' => $flow['id'],
                    'date' => $date,
                    'raw_text' => $line,
                    'source_type' => 'assistant',
                    'source_id' => $noteId,
                    'matched_rules' => [[
                        'source' => 'mr_smith_quick_note',
                        'note_id' => $noteId,
                        'line_index' => $index,
                    ]],
                ];
                if ($selectedByIndex[$index]['category_code'] !== null) {
                    $payload['category_code'] = $selectedByIndex[$index]['category_code'];
                }
                $created[] = $this->createEntryInCurrentTransaction($workspaceId, $payload, $userId);
            }

            $this->db->prepare("
                UPDATE v2_quick_notes
                SET status = 'converted', converted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND workspace_id = ?
            ")->execute([$noteId, $workspaceId]);

            $after = $this->getQuickNote($workspaceId, $noteId, $userId);
            $this->audit($workspaceId, 'quick_note', $noteId, 'convert', $note, [
                'note' => $after,
                'created_entries' => $created,
            ], $userId);

            return [
                'note' => $after,
                'entries' => $created,
            ];
        });
    }

    public function createLegacyExcelImport(string $workspaceId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $input, $userId): array {
            $this->getWorkspace($workspaceId, $userId);
            $this->requireWorkspaceWriter($workspaceId, $userId);
            $fileName = FinDeskV2Support::requireString($input, 'file_name', 255);
            $fileId = FinDeskV2Support::optionalString($input, 'file_id', null, 190);
            $fileUrl = FinDeskV2Support::optionalString($input, 'file_url', null, 2000);
            $fileUpdatedDate = FinDeskV2Support::optionalString($input, 'file_updated_date', null, 20);
            $content = FinDeskV2Support::requireString($input, 'content_base64', 20_000_000);

            if (!str_ends_with(mb_strtolower($fileName), '.xlsx')) {
                throw new FinDeskV2HttpError(422, 'xlsx_required');
            }

            $excludeReason = $this->legacyExcludeReason($fileName);
            $sourceId = FinDeskV2Support::uuid();
            $includeDecision = $excludeReason === null ? 'included' : 'excluded_by_title_marker';
            $decoded = base64_decode($content, true);
            if ($decoded === false) {
                throw new FinDeskV2HttpError(422, 'invalid_base64');
            }

            $this->db->prepare("
                INSERT INTO v2_import_sources (
                    id, workspace_id, source_type, file_name, file_url, file_id, status,
                    include_decision, reason
                )
                VALUES (?, ?, 'excel', ?, ?, ?, ?, ?, ?)
            ")->execute([
                $sourceId,
                $workspaceId,
                $fileName,
                $fileUrl,
                $fileId,
                $excludeReason === null ? 'review_ready' : 'excluded',
                $includeDecision,
                $excludeReason,
            ]);

            if ($excludeReason === null) {
                $sheets = (new FinDeskV2LegacyExcelImporter())->read($decoded);
                $this->storeLegacyImportRows($workspaceId, $sourceId, $sheets, $fileName, $fileUpdatedDate);
            }

            $review = $this->getLegacyImportReview($workspaceId, $sourceId, $userId);
            $this->audit($workspaceId, 'import_source', $sourceId, 'create_import', null, $review, $userId);

            return $review;
        });
    }

    public function getLegacyImportReview(string $workspaceId, string $importId, int $userId): array
    {
        $this->requireWorkspaceFullReader($workspaceId, $userId);
        $source = $this->legacyImportSource($workspaceId, $importId);
        $rows = $this->legacyImportRows($importId);
        $review = $this->emptyLegacyImportReview($source);
        $seen = [];

        foreach ($rows as $row) {
            $raw = FinDeskV2Support::jsonDecode($row['raw_json'], []);
            $parsed = $this->parseLegacyImportRow($raw, $row, $seen);
            $this->accumulateLegacyImportReview($review, $parsed, $row);
        }

        $review['sheets_scanned'] = count($review['_sheet_names'] ?? []);
        $review['months_covered'] = array_values(array_keys($review['_months_covered'] ?? []));
        sort($review['months_covered']);
        unset($review['_sheet_names'], $review['_months_covered']);

        $comparisonSource = array_sum($review['source_summary_totals']) > 0
            ? $review['source_summary_totals']
            : $review['source_totals'];
        $review['source_total_comparison'] = [
            'cash_income' => $comparisonSource['cash_income'] - $review['normalized_totals']['cash_income'],
            'cash_expense' => $comparisonSource['cash_expense'] - $review['normalized_totals']['cash_expense'],
            'card_income' => $comparisonSource['card_income'] - $review['normalized_totals']['card_income'],
            'card_expense' => $comparisonSource['card_expense'] - $review['normalized_totals']['card_expense'],
        ];

        return $review;
    }

    public function acceptLegacyImport(string $workspaceId, string $importId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $importId, $input, $userId): array {
            FinDeskV2Support::enum(
                FinDeskV2Support::optionalString($input, 'decision', 'accept', 40) ?? 'accept',
                ['accept'],
                'decision'
            );
            $this->getWorkspace($workspaceId, $userId);
            $this->requireWorkspaceWriter($workspaceId, $userId);
            $source = $this->legacyImportSource($workspaceId, $importId);
            if ((string)$source['include_decision'] !== 'included') {
                throw new FinDeskV2HttpError(422, 'import_excluded');
            }

            $flows = $this->flowsByType($workspaceId, $userId);
            $rows = $this->legacyImportRows($importId);
            $seen = [];

            foreach ($rows as $row) {
                if ($row['entry_id'] !== null) {
                    continue;
                }

                $raw = FinDeskV2Support::jsonDecode($row['raw_json'], []);
                $parsed = $this->parseLegacyImportRow($raw, $row, $seen);
                if ($parsed['entry'] === null) {
                    $this->updateLegacyImportRowStatus($row['id'], $parsed['parse_status'], null, $parsed['parse_notes']);
                    continue;
                }

                $flow = $flows[$parsed['entry']['flow_type']] ?? null;
                if ($flow === null) {
                    $this->updateLegacyImportRowStatus($row['id'], 'unrecognized', null, 'missing flow');
                    continue;
                }

                $status = $parsed['duplicate_suspect'] ? 'duplicate_suspect' : 'imported';
                $entry = $this->createEntryInCurrentTransaction($workspaceId, [
                    'flow_id' => $flow['id'],
                    'date' => $parsed['entry']['date'],
                    'raw_text' => $parsed['entry']['raw_text'],
                    'amount' => number_format($parsed['entry']['amount'], 2, '.', ''),
                    'category_code' => $parsed['entry']['category_code'],
                    'status' => $status,
                    'source_type' => 'import',
                    'source_id' => $importId,
                    'source_row_id' => $row['id'],
                    'matched_rules' => [[
                        'source' => 'legacy_excel_import',
                        'sheet_name' => $row['sheet_name'],
                        'row_number' => (int)$row['row_number'],
                    ]],
                ], $userId);

                $this->updateLegacyImportRowStatus($row['id'], $status, $entry['id'], $parsed['parse_notes']);
            }

            $this->db->prepare("UPDATE v2_import_sources SET status = 'accepted' WHERE id = ?")->execute([$importId]);
            $review = $this->getLegacyImportReview($workspaceId, $importId, $userId);
            $this->audit($workspaceId, 'import_source', $importId, 'accept_import', $source, $review, $userId);

            return $review;
        });
    }

    public function updateEntry(string $entryId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($entryId, $input, $userId): array {
            $before = $this->getEntry($entryId, $userId);
            $this->requireWorkspaceEntryWriter($before['workspace_id'], $userId);
            $this->guardAccountableProjectionEntryMutable($before);
            $this->guardEntryReportLock($before, $input);
            $this->guardEntryMonthIsOpen($before, $input);
            $flowId = FinDeskV2Support::optionalString($input, 'flow_id', $before['flow']['id'], 36) ?? $before['flow']['id'];
            $flow = $this->getFlowForWorkspace($flowId, $before['workspace_id']);
            $entryInput = [
                'flow_id' => $flow['id'],
                'date' => $input['date'] ?? $before['date'],
                'raw_text' => $input['raw_text'] ?? $before['raw_text'],
                'category_code' => $input['category_code'] ?? $before['category_code'],
                'status' => $input['status'] ?? $before['status'],
                'source_type' => $input['source_type'] ?? $before['source_type'],
                'source_id' => $input['source_id'] ?? $before['source_id'],
                'source_row_id' => $input['source_row_id'] ?? $before['source_row_id'],
                'notes' => $input['notes'] ?? $before['notes'],
                'confidence' => $input['confidence'] ?? $before['confidence'],
                'matched_rules' => $input['matched_rules'] ?? $before['matched_rules'],
            ];
            if (array_key_exists('amount', $input)) {
                $entryInput['amount'] = $input['amount'];
            }
            $entry = $this->normalizeEntryInput($before['workspace_id'], $flow, $entryInput);
            $this->guardWorkspaceMonthIsOpen($before['workspace_id'], $entry['date'], $input);

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
            $this->markOperationalReportsRequiringUpdateForEntry($before, $userId, 'entry_update');
            $after = $this->getEntry($entryId, $userId);
            $this->audit($before['workspace_id'], 'entry', $entryId, 'update', $before, $after, $userId);

            return $after;
        });
    }

    public function updateEntryCategory(string $entryId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($entryId, $input, $userId): array {
            $before = $this->getEntry($entryId, $userId);
            $this->requireWorkspaceEntryWriter($before['workspace_id'], $userId);
            $this->guardAccountableProjectionEntryMutable($before);
            $this->guardEntryReportLock($before, $input);
            $this->guardEntryMonthIsOpen($before, $input);
            $categoryCode = FinDeskV2Support::requireString($input, 'category_code', 80);
            $forceOperational = !empty($input['force_operational']);
            $this->applyEntryCategory($before, $categoryCode, $forceOperational);
            $this->markOperationalReportsRequiringUpdateForEntry($before, $userId, 'entry_category_update');

            $after = $this->getEntry($entryId, $userId);
            $this->audit($before['workspace_id'], 'entry', $entryId, 'update_category', $before, $after, $userId);

            return $after;
        });
    }

    public function decideClosedMonthCategoryCorrection(string $entryId, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($entryId, $input, $userId): array {
            $before = $this->getEntry($entryId, $userId);
            $this->requireWorkspaceWriter($before['workspace_id'], $userId);
            $month = $this->entryMonthParts($before);
            if (!$this->isEntryMonthClosed($before)) {
                throw new FinDeskV2HttpError(422, 'month_is_not_closed');
            }

            $decision = FinDeskV2Support::enum(
                FinDeskV2Support::requireString($input, 'decision', 40),
                ['cancel', 'create_correction', 'recalculate_chain'],
                'decision'
            );
            $categoryCode = FinDeskV2Support::requireString($input, 'category_code', 80);
            $reason = FinDeskV2Support::optionalString($input, 'reason', null, 500);
            $meta = [
                'decision' => $decision,
                'requested_category_code' => $categoryCode,
                'year' => $month['year'],
                'month' => $month['month'],
                'reason' => $reason,
            ];

            if ($decision === 'cancel') {
                $this->audit($before['workspace_id'], 'entry', $entryId, 'closed_month_category_cancel', $before + ['decision' => $meta], $before + ['decision' => $meta], $userId);
                return [
                    'decision' => $decision,
                    'entry' => $before,
                    'changed' => false,
                ];
            }

            if ($decision === 'create_correction') {
                $this->categoryIdByCode($before['workspace_id'], $categoryCode);
                $this->audit($before['workspace_id'], 'entry', $entryId, 'closed_month_category_correction_requested', $before + ['decision' => $meta], $before + ['decision' => $meta], $userId);
                return [
                    'decision' => $decision,
                    'entry' => $before,
                    'changed' => false,
                    'requires_followup' => true,
                ];
            }

            $forceOperational = !empty($input['force_operational']);
            $this->applyEntryCategory($before, $categoryCode, $forceOperational);
            $this->recalculateFlowBalance($before['flow']['id']);
            $this->markOperationalReportsRequiringUpdateForEntry($before, $userId, 'closed_month_category_recalculate');
            $after = $this->getEntry($entryId, $userId);
            $this->audit($before['workspace_id'], 'entry', $entryId, 'closed_month_category_recalculate', $before + ['decision' => $meta], $after + ['decision' => $meta], $userId);

            return [
                'decision' => $decision,
                'entry' => $after,
                'changed' => true,
            ];
        });
    }

    public function deleteEntry(string $entryId, int $userId, array $input = []): array
    {
        return FinDeskV2Database::transact(function () use ($entryId, $userId, $input): array {
            $before = $this->getEntry($entryId, $userId);
            $this->requireWorkspaceEntryWriter($before['workspace_id'], $userId);
            $this->guardAccountableProjectionEntryMutable($before);
            $this->guardEntryReportLock($before, $input);
            $this->guardEntryMonthIsOpen($before, $input);
            $this->db->prepare("UPDATE v2_entries SET archived_at = NOW() WHERE id = ?")->execute([$entryId]);
            $this->recalculateFlowBalance($before['flow']['id']);
            $this->markOperationalReportsRequiringUpdateForEntry($before, $userId, 'entry_delete');
            $this->audit($before['workspace_id'], 'entry', $entryId, 'delete', $before, ['archived' => true], $userId);

            return ['id' => $entryId, 'archived' => true];
        });
    }

    public function listEntryAttachments(string $entryId, int $userId): array
    {
        $this->getEntry($entryId, $userId);
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_attachments
            WHERE entry_id = ?
            ORDER BY created_at ASC, id ASC
        ");
        $stmt->execute([$entryId]);

        return array_map([$this, 'attachmentRow'], $stmt->fetchAll());
    }

    public function createEntryAttachment(string $entryId, array $input, int $userId): array
    {
        $absolutePath = null;

        try {
            return FinDeskV2Database::transact(function () use ($entryId, $input, $userId, &$absolutePath): array {
                $entry = $this->getEntry($entryId, $userId);
                $this->requireWorkspaceEntryWriter($entry['workspace_id'], $userId);
                $this->guardEntryReportLock($entry, $input);
                $payload = $this->normalizeAttachmentPayload($input);
                $attachmentId = FinDeskV2Support::uuid();
                $extension = self::ATTACHMENT_ALLOWED_MIME_EXTENSIONS[$payload['mime_type']];
                $relativePath = 'storage/v2/attachments/'
                    . $entry['workspace_id'] . '/'
                    . $entryId . '/'
                    . $attachmentId . '.' . $extension;
                $absolutePath = $this->attachmentWritePath($relativePath);

                if (@file_put_contents($absolutePath, $payload['content']) === false) {
                    throw new FinDeskV2HttpError(500, 'attachment_store_failed');
                }

                $this->db->prepare("
                    INSERT INTO v2_attachments (id, entry_id, file_name, file_url, mime_type, size_bytes, image_mode)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $attachmentId,
                    $entryId,
                    $payload['file_name'],
                    $relativePath,
                    $payload['mime_type'],
                    $payload['size_bytes'],
                    $payload['image_mode'],
                ]);

                $attachment = $this->getAttachmentForUser($attachmentId, $userId);
                $this->audit($entry['workspace_id'], 'attachment', $attachmentId, 'create', null, [
                    'attachment' => $attachment,
                    'closed_month' => $this->isEntryMonthClosed($entry),
                ], $userId);

                return $attachment;
            });
        } catch (Throwable $e) {
            if ($absolutePath !== null && is_file($absolutePath)) {
                @unlink($absolutePath);
            }
            throw $e;
        }
    }

    public function deleteAttachment(string $attachmentId, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($attachmentId, $userId): array {
            $attachment = $this->getAttachmentForUser($attachmentId, $userId);
            $entry = $this->getEntry($attachment['entry_id'], $userId);
            $this->requireWorkspaceEntryWriter($entry['workspace_id'], $userId);
            $this->guardEntryReportLock($entry);
            $fileDeleted = $this->deleteAttachmentFile($attachment['file_url']);

            $this->db->prepare("DELETE FROM v2_attachments WHERE id = ?")->execute([$attachmentId]);
            $after = [
                'id' => $attachmentId,
                'entry_id' => $attachment['entry_id'],
                'deleted' => true,
                'file_deleted' => $fileDeleted,
                'closed_month' => $this->isEntryMonthClosed($entry),
            ];
            $this->audit($entry['workspace_id'], 'attachment', $attachmentId, 'delete', $attachment, $after, $userId);

            return $after;
        });
    }

    public function closeMonth(string $workspaceId, int $year, int $month, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $year, $month, $input, $userId): array {
            $this->getWorkspace($workspaceId, $userId);
            $this->requireWorkspaceWriter($workspaceId, $userId);
            $this->assertValidMonth($year, $month);
            $before = $this->monthClosure($workspaceId, $year, $month);
            $reportBeforeClose = $this->getMonthlyReport($workspaceId, ['year' => $year, 'month' => $month], $userId);
            $id = $before ? (string)$before['id'] : FinDeskV2Support::uuid();
            $comment = FinDeskV2Support::optionalString($input, 'comment', null, 1000);
            $openingBalance = $reportBeforeClose['opening_cash'] === null
                ? null
                : number_format((float)$reportBeforeClose['opening_cash'], 2, '.', '');
            $closingBalance = $reportBeforeClose['ending_cash'] === null
                ? null
                : number_format((float)$reportBeforeClose['ending_cash'], 2, '.', '');

            $this->db->prepare("
                INSERT INTO v2_monthly_closures (
                    id, workspace_id, year, month, opening_balance, closing_balance,
                    is_closed, comment, closed_by, closed_at
                )
                VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    opening_balance = VALUES(opening_balance),
                    closing_balance = VALUES(closing_balance),
                    is_closed = 1,
                    comment = VALUES(comment),
                    closed_by = VALUES(closed_by),
                    closed_at = VALUES(closed_at)
            ")->execute([$id, $workspaceId, $year, $month, $openingBalance, $closingBalance, $comment, $userId]);

            $after = $this->monthClosureRow($this->monthClosure($workspaceId, $year, $month));
            $this->audit($workspaceId, 'month_closure', $id, 'month_close', $before === null ? null : $this->monthClosureRow($before), $after, $userId);

            return [
                'closure' => $after,
                'report' => $this->getMonthlyReport($workspaceId, ['year' => $year, 'month' => $month], $userId),
            ];
        });
    }

    public function reopenMonth(string $workspaceId, int $year, int $month, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $year, $month, $input, $userId): array {
            $this->getWorkspace($workspaceId, $userId);
            $this->requireWorkspaceWriter($workspaceId, $userId);
            $this->assertValidMonth($year, $month);
            $before = $this->monthClosure($workspaceId, $year, $month);
            if (!$before || (int)$before['is_closed'] !== 1) {
                throw new FinDeskV2HttpError(422, 'month_not_closed');
            }

            $comment = FinDeskV2Support::optionalString($input, 'comment', null, 1000);
            $this->db->prepare("
                UPDATE v2_monthly_closures
                SET is_closed = 0, comment = ?
                WHERE id = ?
            ")->execute([$comment, $before['id']]);

            $after = $this->monthClosureRow($this->monthClosure($workspaceId, $year, $month));
            $this->audit($workspaceId, 'month_closure', (string)$before['id'], 'month_reopen', $this->monthClosureRow($before), $after, $userId);

            return [
                'closure' => $after,
                'report' => $this->getMonthlyReport($workspaceId, ['year' => $year, 'month' => $month], $userId),
            ];
        });
    }

    public function createMonthCorrection(string $workspaceId, int $year, int $month, array $input, int $userId): array
    {
        return FinDeskV2Database::transact(function () use ($workspaceId, $year, $month, $input, $userId): array {
            $this->getWorkspace($workspaceId, $userId);
            $this->requireWorkspaceWriter($workspaceId, $userId);
            $this->assertValidMonth($year, $month);
            $flow = $this->getFlowForWorkspace(FinDeskV2Support::requireString($input, 'flow_id', 36), $workspaceId);
            $date = FinDeskV2Support::date($input);
            if (substr($date, 0, 7) !== sprintf('%04d-%02d', $year, $month)) {
                throw new FinDeskV2HttpError(422, 'invalid_correction_date');
            }

            $rawText = FinDeskV2Support::requireString($input, 'raw_text', 2000);
            $signed = $this->strictSignedAmount($rawText, 'correction');
            $reason = FinDeskV2Support::optionalString($input, 'reason', null, 1000)
                ?? FinDeskV2Support::optionalString($input, 'comment', null, 1000);
            $referenceEntryId = FinDeskV2Support::optionalString($input, 'reference_entry_id', null, 36);
            if ($referenceEntryId !== null) {
                $reference = $this->getEntry($referenceEntryId, $userId);
                if ($reference['workspace_id'] !== $workspaceId) {
                    throw new FinDeskV2HttpError(404, 'entry_not_found');
                }
            }

            $id = FinDeskV2Support::uuid();
            $matchedRules = [[
                'source' => 'month_correction',
                'year' => $year,
                'month' => $month,
                'reference_entry_id' => $referenceEntryId,
            ]];

            $this->db->prepare("
                INSERT INTO v2_entries (
                    id, workspace_id, flow_id, created_by, actor_id, date, raw_text, sign, amount, direction,
                    entry_type, category_id, status, source_type, source_id, source_row_id, notes, confidence, matched_rules_json
                )
                VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, 'correction', NULL, 'corrected', 'correction', NULL, NULL, ?, NULL, ?)
            ")->execute([
                $id,
                $workspaceId,
                $flow['id'],
                $userId,
                $date,
                $rawText,
                $signed['sign'],
                $signed['amount'],
                $signed['direction'],
                $reason,
                FinDeskV2Support::jsonEncode($matchedRules),
            ]);

            $this->recalculateFlowBalance($flow['id']);
            $entry = $this->getEntry($id, $userId);
            $this->audit($workspaceId, 'entry', $id, 'month_correction_create', null, [
                'entry' => $entry,
                'year' => $year,
                'month' => $month,
                'reference_entry_id' => $referenceEntryId,
            ], $userId);

            return $entry;
        });
    }

    public function closeMonthForFixture(string $workspaceId, int $year, int $month, int $userId): array
    {
        return $this->closeMonth($workspaceId, $year, $month, [], $userId)['closure'];
    }

    public function listCategories(string $workspaceId, int $userId): array
    {
        $this->requireWorkspaceFullReader($workspaceId, $userId);
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
            $this->requireWorkspaceWriter($workspaceId, $userId);
            $rule = $this->createCategoryRuleInCurrentTransaction($workspaceId, $input, $userId);
            $this->audit($workspaceId, 'category_rule', $rule['id'], 'create', null, $rule, $userId);

            return $rule;
        });
    }

    private function storeLegacyImportRows(string $workspaceId, string $sourceId, array $sheets, string $fileName, ?string $fileUpdatedDate): void
    {
        $insert = $this->db->prepare("
            INSERT INTO v2_import_rows (id, import_source_id, sheet_name, `row_number`, raw_json, parse_status, parse_notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $seen = [];
        $filenameDate = $this->legacyFilenameDate($fileName);

        foreach ($sheets as $sheet) {
            $headers = [];
            $lastDate = null;
            foreach ($sheet['rows'] as $rowNumber => $cells) {
                if ($headers === []) {
                    $candidateHeaders = $this->legacyHeaderMap($cells);
                    if (!$this->legacyLooksLikeHeader($candidateHeaders)) {
                        continue;
                    }
                    $headers = $candidateHeaders;
                    continue;
                }

                $raw = $this->legacyRawRow($headers, $cells);
                if ($raw === []) {
                    continue;
                }

                $raw['_date_context'] = [
                    'inherited_previous_row_date' => $lastDate,
                    'filename_date' => $filenameDate,
                    'file_updated_date' => $fileUpdatedDate,
                ];
                $date = $this->legacyRowDate($raw, null);
                if ($date !== null && ($raw['дата'] ?? '') !== '') {
                    $lastDate = $date;
                }
                $raw['_date_context']['inherited_previous_row_date'] = $lastDate;

                $parsed = $this->parseLegacyImportRow($raw, [
                    'id' => null,
                    'sheet_name' => $sheet['name'],
                    'row_number' => $rowNumber,
                ], $seen);

                $insert->execute([
                    FinDeskV2Support::uuid(),
                    $sourceId,
                    $sheet['name'],
                    $rowNumber,
                    FinDeskV2Support::jsonEncode($raw),
                    $parsed['parse_status'],
                    $parsed['parse_notes'],
                ]);
            }
        }
    }

    private function createEntryInCurrentTransaction(string $workspaceId, array $input, int $userId): array
    {
        $this->getWorkspace($workspaceId, $userId);
        $this->requireWorkspaceEntryWriter($workspaceId, $userId);
        $flow = $this->getFlowForWorkspace(FinDeskV2Support::requireString($input, 'flow_id', 36), $workspaceId);
        $entry = $this->normalizeEntryInput($workspaceId, $flow, $input);
        if ($entry['source_type'] !== 'correction') {
            $this->guardWorkspaceMonthIsOpen($workspaceId, $entry['date'], $input);
        }
        $entry['id'] = FinDeskV2Support::uuid();
        $entry['created_by'] = $userId;

        $this->db->prepare("
            INSERT INTO v2_entries (
                id, workspace_id, flow_id, created_by, actor_id, date, raw_text, sign, amount, direction,
                entry_type, category_id, status, source_type, source_id, source_row_id, notes, confidence, matched_rules_json
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
            $entry['source_id'],
            $entry['source_row_id'],
            $entry['notes'],
            $entry['confidence'],
            FinDeskV2Support::jsonEncode($entry['matched_rules']),
        ]);

        $created = $this->getEntry($entry['id'], $userId);
        $this->recalculateFlowBalance($flow['id']);
        $created = $this->getEntry($entry['id'], $userId);
        $this->audit($workspaceId, 'entry', $entry['id'], 'create', null, $created, $userId);

        return $created;
    }

    private function legacyHeaderMap(array $cells): array
    {
        $headers = [];
        foreach ($cells as $index => $cell) {
            $normalized = mb_strtolower(trim((string)$cell));
            if ($normalized !== '') {
                $headers[$index] = $normalized;
            }
        }

        return $headers;
    }

    private function legacyLooksLikeHeader(array $headers): bool
    {
        $values = array_fill_keys(array_values($headers), true);
        $hasDescription = isset($values['описание платежа'])
            || isset($values['description'])
            || isset($values['описание']);
        $hasOldMoneyColumn = isset($values['приход кеш'])
            || isset($values['приход кэш'])
            || isset($values['расход кеш'])
            || isset($values['расход кэш'])
            || isset($values['приход карта'])
            || isset($values['приход карты'])
            || isset($values['расход карта'])
            || isset($values['расход карты']);
        $hasChronologyMoneyColumn = isset($values['приход']) || isset($values['расход']);

        return $hasDescription && ($hasOldMoneyColumn || $hasChronologyMoneyColumn);
    }

    private function legacyRawRow(array $headers, array $cells): array
    {
        $raw = [];
        foreach ($headers as $index => $header) {
            $value = trim((string)($cells[$index] ?? ''));
            if ($value !== '') {
                $raw[$header] = $value;
            }
        }

        return $raw;
    }

    private function parseLegacyImportRow(array $raw, array $row, array &$seen): array
    {
        $description = trim((string)($raw['описание платежа'] ?? $raw['description'] ?? $raw['описание'] ?? ''));
        $text = mb_strtolower($description);
        $date = $this->legacyRowDate($raw, null);
        $dateSource = $this->legacyDateSource($raw);
        $amounts = [
            'cash_income' => $this->legacyAmount($raw['приход кеш'] ?? $raw['приход кэш'] ?? $raw['cash income'] ?? $raw['приход'] ?? null),
            'cash_expense' => $this->legacyAmount($raw['расход кеш'] ?? $raw['расход кэш'] ?? $raw['cash expense'] ?? $raw['расход'] ?? null),
            'card_income' => $this->legacyAmount($raw['приход карта'] ?? $raw['приход карты'] ?? $raw['card income'] ?? null),
            'card_expense' => $this->legacyAmount($raw['расход карта'] ?? $raw['расход карты'] ?? $raw['card expense'] ?? null),
        ];
        $nonZero = array_filter($amounts, static fn (?float $amount): bool => $amount !== null && abs($amount) > 0.0001);
        $isSummary = str_contains($text, 'свод') || str_contains($text, 'summary') || isset($raw['сводные данные']);
        $isInfo = str_contains($text, 'информационная') || str_contains($text, 'не считается') || str_contains($text, 'comment') || str_contains($text, 'info');
        $isOpening = str_contains($text, 'остаток') || str_contains($text, 'переход') || str_contains($text, 'opening balance') || str_contains($text, 'balance brought forward');

        if ($isSummary) {
            return $this->legacyParsedRow('summary_ignored', null, $amounts, false, 'summary row ignored');
        }
        if ($isInfo || $isOpening) {
            return $this->legacyParsedRow('ignored', null, $amounts, false, $isOpening ? 'opening balance row ignored' : 'info row ignored');
        }
        if ($date === null || $nonZero === []) {
            return $this->legacyParsedRow('unrecognized', null, $amounts, false, 'missing date or amount');
        }
        if (count($nonZero) > 1) {
            return $this->legacyParsedRow('unrecognized', null, $amounts, false, 'multiple money columns in one row');
        }

        $kind = array_key_first($nonZero);
        $amount = (float)$nonZero[$kind];
        $flowType = str_starts_with((string)$kind, 'card_') ? 'card' : 'cash';
        $sign = str_ends_with((string)$kind, '_expense') ? '-' : '+';
        $categoryCode = $this->legacyCategoryCode($description, $flowType, $sign);
        $rawText = $sign . number_format($amount, 2, '.', '') . ($description === '' ? ' imported row' : ' ' . $description);
        $duplicateKey = implode('|', [$date, $flowType, $sign, number_format($amount, 2, '.', ''), mb_strtolower($description)]);
        $duplicate = isset($seen[$duplicateKey]);
        $seen[$duplicateKey] = true;

        return $this->legacyParsedRow('parsed', [
            'date' => $date,
            'date_source' => $dateSource,
            'flow_type' => $flowType,
            'raw_text' => $rawText,
            'amount' => $amount,
            'category_code' => $categoryCode,
        ], $amounts, $duplicate, $duplicate ? 'duplicate suspect' : null);
    }

    private function legacyParsedRow(string $status, ?array $entry, array $amounts, bool $duplicate, ?string $notes): array
    {
        if ($duplicate) {
            $status = 'duplicate_suspect';
        }

        return [
            'parse_status' => $status,
            'entry' => $entry,
            'source_totals' => [
                'cash_income' => $amounts['cash_income'] ?? 0.0,
                'cash_expense' => $amounts['cash_expense'] ?? 0.0,
                'card_income' => $amounts['card_income'] ?? 0.0,
                'card_expense' => $amounts['card_expense'] ?? 0.0,
            ],
            'date_source' => $entry['date_source'] ?? null,
            'duplicate_suspect' => $duplicate,
            'parse_notes' => $notes,
        ];
    }

    /** @return array<string, bool> */
    private function existingLegacyEntryKeys(string $workspaceId): array
    {
        $stmt = $this->db->prepare("
            SELECT e.date, f.type AS flow_type, e.sign, e.amount, e.raw_text
            FROM v2_entries e
            INNER JOIN v2_flows f ON f.id = e.flow_id
            WHERE e.workspace_id = ?
              AND e.archived_at IS NULL
              AND e.sign IS NOT NULL
              AND e.amount IS NOT NULL
        ");
        $stmt->execute([$workspaceId]);

        $keys = [];
        foreach ($stmt->fetchAll() as $row) {
            $description = preg_replace('/^[+-]\s*[0-9]+(?:[.,][0-9]{1,2})?\s*/u', '', (string)$row['raw_text']);
            $keys[implode('|', [
                (string)$row['date'],
                (string)$row['flow_type'],
                (string)$row['sign'],
                number_format((float)$row['amount'], 2, '.', ''),
                mb_strtolower(trim((string)$description)),
            ])] = true;
        }

        return $keys;
    }

    private function legacyRowDate(array $raw, ?string $fallback): ?string
    {
        foreach ([
            $raw['дата'] ?? $raw['date'] ?? null,
            $raw['_date_context']['inherited_previous_row_date'] ?? null,
            $raw['_date_context']['filename_date'] ?? null,
            $raw['_date_context']['file_updated_date'] ?? null,
            $fallback,
        ] as $value) {
            $date = $this->legacyNormalizeDate($value);
            if ($date !== null) {
                return $date;
            }
        }

        return null;
    }

    private function legacyDateSource(array $raw): ?string
    {
        $sources = [
            'row_date' => $raw['дата'] ?? $raw['date'] ?? null,
            'inherited_previous_row_date' => $raw['_date_context']['inherited_previous_row_date'] ?? null,
            'filename_date' => $raw['_date_context']['filename_date'] ?? null,
            'file_updated_date' => $raw['_date_context']['file_updated_date'] ?? null,
        ];
        foreach ($sources as $source => $value) {
            if ($this->legacyNormalizeDate($value) !== null) {
                return $source;
            }
        }

        return null;
    }

    private function legacyNormalizeDate($value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return DateTimeImmutable::createFromFormat('!Y-m-d', '1899-12-30')
                ->modify('+' . (int)$value . ' days')
                ->format('Y-m-d');
        }

        foreach (['!Y-m-d', '!d.m.Y', '!d/m/Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function legacyFilenameDate(string $fileName): ?string
    {
        if (preg_match('/(20[0-9]{2})[-_. ]?([01]?[0-9])[-_. ]?([0-3]?[0-9])/', $fileName, $match) === 1) {
            $date = DateTimeImmutable::createFromFormat('!Y-n-j', "{$match[1]}-{$match[2]}-{$match[3]}");
            return $date ? $date->format('Y-m-d') : null;
        }

        if (preg_match('/([0-3]?[0-9])[-_. ]([01]?[0-9])[-_. ]([0-9]{2}|20[0-9]{2})/', $fileName, $match) !== 1) {
            return null;
        }

        $year = (int)$match[3];
        if ($year < 100) {
            $year += 2000;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-n-j', "{$year}-{$match[2]}-{$match[1]}");

        return $date ? $date->format('Y-m-d') : null;
    }

    private function legacyAmount($value): ?float
    {
        $text = trim((string)$value);
        if ($text === '') {
            return null;
        }

        $normalized = str_replace([' ', "\xc2\xa0"], '', $text);
        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? abs((float)$normalized) : null;
    }

    private function legacyCategoryCode(string $description, string $flowType, string $sign): ?string
    {
        $text = mb_strtolower($description);
        if (str_contains($text, 'снял с карты') || str_contains($text, 'cash topup') || str_contains($text, 'topup from card')) {
            return 'cash_topup_from_card';
        }
        if ($sign === '+' && $this->isCommercialIncomeText($text)) {
            return 'commercial_income';
        }
        if ($sign === '+' && $this->isUnclearCommercialIncomeText($text, $sign)) {
            return null;
        }
        if ($sign === '+') {
            return 'non_commercial_income';
        }
        if (str_contains($text, 'netflix')) {
            return 'media_comms';
        }
        if (preg_match('/заправ|топлив|fuel/u', $text) === 1 && preg_match('/авто|машин|car/u', $text) !== 1) {
            return 'fuel';
        }
        if ($flowType === 'cash' && $sign === '-' && (str_contains($text, 'какая-то штука') || str_contains($text, 'unknown'))) {
            return 'other';
        }

        return null;
    }

    private function flowsByType(string $workspaceId, int $userId): array
    {
        $flows = [];
        foreach ($this->listFlows($workspaceId, $userId) as $flow) {
            $flows[$flow['type']] = $flow;
        }

        return $flows;
    }

    private function legacyExcludeReason(string $fileName): ?string
    {
        $text = mb_strtolower($fileName);
        foreach (['не отправлял', 'не отправлено', 'не готово', 'не закончен', 'не закончено', 'не полный', 'неполный', 'черновик', 'draft', 'test'] as $marker) {
            if (str_contains($text, $marker)) {
                return "title marker: {$marker}";
            }
        }

        return null;
    }

    private function legacyImportSource(string $workspaceId, string $importId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_import_sources
            WHERE id = ? AND workspace_id = ?
            LIMIT 1
        ");
        $stmt->execute([$importId, $workspaceId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new FinDeskV2HttpError(404, 'import_not_found');
        }

        return $row;
    }

    private function legacyImportRows(string $importId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_import_rows
            WHERE import_source_id = ?
            ORDER BY sheet_name ASC, `row_number` ASC
        ");
        $stmt->execute([$importId]);

        return $stmt->fetchAll();
    }

    private function emptyLegacyImportReview(array $source): array
    {
        return [
            'import_id' => (string)$source['id'],
            'source_file_name' => $source['file_name'] === null ? null : (string)$source['file_name'],
            'source_file_id' => $source['file_id'] === null ? null : (string)$source['file_id'],
            'source_file_url' => $source['file_url'] === null ? null : (string)$source['file_url'],
            'status' => (string)$source['status'],
            'include_decision' => (string)$source['include_decision'],
            'reason' => $source['reason'] === null ? null : (string)$source['reason'],
            'files_detected' => 1,
            'files_included' => (string)$source['include_decision'] === 'included' ? 1 : 0,
            'files_excluded' => (string)$source['include_decision'] === 'included' ? 0 : 1,
            'final_version_decisions' => [],
            'sheets_scanned' => 0,
            'rows_scanned' => 0,
            'rows_parsed' => 0,
            'entries_created' => 0,
            'rows_ignored' => 0,
            'rows_unrecognized' => 0,
            'summary_rows_ignored' => 0,
            'cash_income_total' => 0.0,
            'cash_expense_total' => 0.0,
            'card_income_total' => 0.0,
            'card_expense_total' => 0.0,
            'source_totals' => [
                'cash_income' => 0.0,
                'cash_expense' => 0.0,
                'card_income' => 0.0,
                'card_expense' => 0.0,
            ],
            'source_summary_totals' => [
                'cash_income' => 0.0,
                'cash_expense' => 0.0,
                'card_income' => 0.0,
                'card_expense' => 0.0,
            ],
            'normalized_totals' => [
                'cash_income' => 0.0,
                'cash_expense' => 0.0,
                'card_income' => 0.0,
                'card_expense' => 0.0,
            ],
            'source_total_comparison' => [
                'cash_income' => 0.0,
                'cash_expense' => 0.0,
                'card_income' => 0.0,
                'card_expense' => 0.0,
            ],
            'months_covered' => [],
            '_months_covered' => [],
            '_sheet_names' => [],
            'duplicate_suspects' => [],
            'row_traces' => [],
        ];
    }

    private function accumulateLegacyImportReview(array &$review, array $parsed, array $row): void
    {
        $review['rows_scanned']++;
        $sheetName = (string)$row['sheet_name'];
        $review['_sheet_names'][$sheetName] = true;
        $status = (string)$row['parse_status'];
        if ($status === 'pending') {
            $status = $parsed['parse_status'];
        }

        if ($status === 'parsed' || $status === 'imported' || $status === 'duplicate_suspect') {
            $review['rows_parsed']++;
        } elseif ($status === 'summary_ignored') {
            $review['summary_rows_ignored']++;
        } elseif ($status === 'unrecognized') {
            $review['rows_unrecognized']++;
        } else {
            $review['rows_ignored']++;
        }

        $entry = $parsed['entry'];
        if ($row['entry_id'] !== null) {
            $review['entries_created']++;
        }

        foreach ($parsed['source_totals'] as $key => $amount) {
            if ($status === 'summary_ignored') {
                $review['source_summary_totals'][$key] += (float)$amount;
            } else {
                $review['source_totals'][$key] += (float)$amount;
            }
        }

        if ($entry !== null && !$parsed['duplicate_suspect']) {
            $kind = $entry['flow_type'] . '_' . ($entry['raw_text'][0] === '-' ? 'expense' : 'income');
            if (isset($review['normalized_totals'][$kind])) {
                $review['normalized_totals'][$kind] += (float)$entry['amount'];
            }
            $month = substr((string)$entry['date'], 0, 7);
            if ($month !== '') {
                $review['_months_covered'][$month] = true;
            }
        }

        foreach (['cash_income', 'cash_expense', 'card_income', 'card_expense'] as $key) {
            $review[$key . '_total'] = $review['normalized_totals'][$key];
        }

        if ($parsed['duplicate_suspect']) {
            $review['duplicate_suspects'][] = [
                'sheet_name' => $sheetName,
                'row_number' => (int)$row['row_number'],
                'reason' => 'same date, flow, sign, amount, and description',
            ];
        }

        $review['row_traces'][] = [
            'import_source_id' => (string)$row['import_source_id'],
            'import_row_id' => (string)$row['id'],
            'sheet_name' => $sheetName,
            'row_number' => (int)$row['row_number'],
            'raw_row_data' => FinDeskV2Support::jsonDecode($row['raw_json'], []),
            'entry_id' => $row['entry_id'] === null ? null : (string)$row['entry_id'],
            'parse_status' => $status,
            'date_source' => $parsed['date_source'],
            'parse_notes' => $row['parse_notes'] ?? $parsed['parse_notes'],
        ];
    }

    private function updateLegacyImportRowStatus(string $rowId, string $status, ?string $entryId, ?string $notes): void
    {
        $this->db->prepare("
            UPDATE v2_import_rows
            SET parse_status = ?, entry_id = ?, parse_notes = ?
            WHERE id = ?
        ")->execute([$status, $entryId, $notes, $rowId]);
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

    private function guardEntryMonthIsOpen(array $entry, array $input = []): void
    {
        if (!$this->isEntryMonthClosed($entry)) {
            return;
        }
        if ($this->closedMonthRecalculationConfirmed($input)) {
            return;
        }
        $month = $this->entryMonthParts($entry);

        throw new FinDeskV2HttpError(409, FinDeskV2Support::jsonEncode([
            'error' => 'closed_month_requires_decision',
            'year' => $month['year'],
            'month' => $month['month'],
            'choices' => ['create_correction', 'recalculate_chain', 'cancel'],
        ]));
    }

    private function guardWorkspaceMonthIsOpen(string $workspaceId, string $date, array $input = []): void
    {
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$dt) {
            throw new FinDeskV2HttpError(422, 'invalid_date');
        }
        $year = (int)$dt->format('Y');
        $month = (int)$dt->format('n');
        $closure = $this->monthClosure($workspaceId, $year, $month);
        if ($closure === null || (int)$closure['is_closed'] !== 1) {
            return;
        }
        if ($this->closedMonthRecalculationConfirmed($input)) {
            return;
        }

        throw new FinDeskV2HttpError(409, FinDeskV2Support::jsonEncode([
            'error' => 'closed_month_requires_decision',
            'year' => $year,
            'month' => $month,
            'choices' => ['create_correction', 'recalculate_chain', 'cancel'],
        ]));
    }

    private function guardAccountableProjectionEntryMutable(array $entry): void
    {
        if ((string)($entry['source_type'] ?? '') !== 'accountable_report') {
            return;
        }

        throw new FinDeskV2HttpError(409, 'accountable_projection_entry_immutable');
    }

    private function closedMonthRecalculationConfirmed(array $input): bool
    {
        $decision = FinDeskV2Support::optionalString($input, 'closed_month_decision', null, 40);
        if ($decision === null) {
            return false;
        }

        FinDeskV2Support::enum($decision, ['recalculate_chain'], 'closed_month_decision');

        return true;
    }

    private function entryMonthParts(array $entry): array
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$entry['date']);
        if (!$date) {
            throw new FinDeskV2HttpError(422, 'invalid_date');
        }

        return [
            'year' => (int)$date->format('Y'),
            'month' => (int)$date->format('n'),
        ];
    }

    private function isEntryMonthClosed(array $entry): bool
    {
        $month = $this->entryMonthParts($entry);
        $stmt = $this->db->prepare("
            SELECT is_closed
            FROM v2_monthly_closures
            WHERE workspace_id = ? AND year = ? AND month = ? AND is_closed = 1
            LIMIT 1
        ");
        $stmt->execute([$entry['workspace_id'], $month['year'], $month['month']]);

        return (bool)$stmt->fetchColumn();
    }

    private function applyEntryCategory(array $entry, string $categoryCode, bool $forceOperational = false): void
    {
        $categoryId = $this->categoryIdByCode($entry['workspace_id'], $categoryCode);
        $status = $entry['status'];
        if ($entry['status'] === 'other_review' && $entry['category_code'] === 'other' && $categoryCode !== 'other') {
            $status = 'recognized';
        }

        if ($forceOperational) {
            $matchedRules = $this->withoutLowerAccountingSemanticMarkers($entry['matched_rules'] ?? []);
            $this->db->prepare("UPDATE v2_entries SET category_id = ?, status = ?, matched_rules_json = ? WHERE id = ?")
                ->execute([$categoryId, $status, FinDeskV2Support::jsonEncode($matchedRules), $entry['id']]);
            return;
        }

        $this->db->prepare("UPDATE v2_entries SET category_id = ?, status = ? WHERE id = ?")->execute([$categoryId, $status, $entry['id']]);
    }

    /** @param array<int, array<string, mixed>> $rules */
    private function withoutLowerAccountingSemanticMarkers(array $rules): array
    {
        $filtered = array_values(array_filter($rules, static function (array $rule): bool {
            if (($rule['source'] ?? null) === 'classification_decision') {
                return false;
            }
            $marker = (string)($rule['marker'] ?? '');
            return !in_array($marker, ['debt_or_return', 'money_movement'], true);
        }));
        $filtered[] = [
            'source' => 'manual_accounting_override',
            'accounting_section' => 'operational',
            'reason' => 'user confirmed regular operational record',
        ];

        return $filtered;
    }

    private function assertValidMonth(int $year, int $month): void
    {
        if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
            throw new FinDeskV2HttpError(422, 'invalid_month');
        }
    }

    private function countedStatuses(): array
    {
        return ['recognized', 'other_review', 'imported', 'accepted', 'corrected'];
    }

    private function countedStatusSqlList(): string
    {
        return "'" . implode("', '", $this->countedStatuses()) . "'";
    }

    private function isCountedStatus(string $status): bool
    {
        return in_array($status, $this->countedStatuses(), true);
    }

    private function uncategorizedReviewCategoryRow(): array
    {
        return [
            'category_code' => 'uncategorized_review',
            'category_name' => [
                'ru' => 'Без категории / проверить',
                'en' => 'Uncategorized / review',
            ],
            'direction' => 'expense',
            'cash_total' => 0.0,
            'card_total' => 0.0,
            'total' => 0.0,
            'entry_count' => 0,
            'review_count' => 0,
            'source_entry_ids' => [],
        ];
    }

    private function cashFlowForWorkspace(string $workspaceId, int $userId): ?array
    {
        foreach ($this->listFlows($workspaceId, $userId) as $flow) {
            if ($flow['type'] === 'cash' && $flow['has_live_balance']) {
                return $flow;
            }
        }

        return null;
    }

    private function cashDeltaBefore(string $flowId, string $beforeDate): float
    {
        $stmt = $this->db->prepare("
            SELECT amount, direction, entry_type, status
            FROM v2_entries
            WHERE flow_id = ?
              AND archived_at IS NULL
              AND date < ?
            ORDER BY date ASC, created_seq ASC
        ");
        $stmt->execute([$flowId, $beforeDate]);

        $delta = 0.0;
        foreach ($stmt->fetchAll() as $entry) {
            $entryDelta = $this->cashBalanceDelta($entry);
            if ($entryDelta !== null) {
                $delta += $entryDelta;
            }
        }

        return $delta;
    }

    private function cashSourceEntryIdsBefore(string $flowId, string $beforeDate): array
    {
        $stmt = $this->db->prepare("
            SELECT id, amount, direction, entry_type, status
            FROM v2_entries
            WHERE flow_id = ?
              AND archived_at IS NULL
              AND date < ?
            ORDER BY date ASC, created_seq ASC
        ");
        $stmt->execute([$flowId, $beforeDate]);

        $ids = [];
        foreach ($stmt->fetchAll() as $entry) {
            if ($this->cashBalanceDelta($entry) !== null) {
                $this->appendSourceEntryId($ids, (string)$entry['id']);
            }
        }

        return $ids;
    }

    private function appendSourceEntryId(array &$ids, string $id): void
    {
        if (!in_array($id, $ids, true)) {
            $ids[] = $id;
        }
    }

    private function sourceEntryIdsFromQuery(array $query): array
    {
        $raw = (string)($query['ids'] ?? '');
        if (trim($raw) === '') {
            return [];
        }

        $ids = [];
        foreach (explode(',', $raw) as $candidate) {
            $id = strtolower(trim($candidate));
            if ($id === '') {
                continue;
            }
            if (!$this->isUuid($id)) {
                throw new FinDeskV2HttpError(422, 'invalid_ids');
            }
            $this->appendSourceEntryId($ids, $id);
        }

        if (count($ids) > 150) {
            throw new FinDeskV2HttpError(422, 'too_many_ids');
        }

        return $ids;
    }

    private function isUuid(string $value): bool
    {
        return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $value) === 1;
    }

    private function flattenSourceEntryIds(array $sourceTrace): array
    {
        $ids = [];
        $appendList = function ($values) use (&$ids): void {
            if (!is_array($values)) {
                return;
            }
            foreach ($values as $value) {
                $id = strtolower(trim((string)$value));
                if ($this->isUuid($id)) {
                    $this->appendSourceEntryId($ids, $id);
                }
            }
        };
        foreach (($sourceTrace['totals'] ?? []) as $entryIds) {
            $appendList($entryIds);
        }
        foreach (($sourceTrace['categories'] ?? []) as $entryIds) {
            $appendList($entryIds);
        }
        $appendList($sourceTrace['basis']['opening_cash']['prior_entry_ids'] ?? []);

        return $ids;
    }

    private function attachmentRefsForEntryIds(string $workspaceId, array $entryIds): array
    {
        if ($entryIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($entryIds), '?'));
        $stmt = $this->db->prepare("
            SELECT
                att.id,
                att.entry_id,
                att.file_name,
                att.file_url,
                att.mime_type,
                att.size_bytes,
                att.image_mode,
                att.created_at
            FROM v2_attachments att
            INNER JOIN v2_entries e ON e.id = att.entry_id
            WHERE e.workspace_id = ?
              AND e.archived_at IS NULL
              AND att.entry_id IN ({$placeholders})
            ORDER BY e.date ASC, e.created_seq ASC, att.created_at ASC
        ");
        $stmt->execute(array_merge([$workspaceId], $entryIds));

        return array_map([$this, 'attachmentRow'], $stmt->fetchAll());
    }

    private function nextReportSnapshotVersion(string $workspaceId, string $reportType, int $year, int $month): int
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(MAX(version), 0) + 1
            FROM v2_report_snapshots
            WHERE workspace_id = ? AND report_type = ? AND year = ? AND month = ?
        ");
        $stmt->execute([$workspaceId, $reportType, $year, $month]);

        return (int)$stmt->fetchColumn();
    }

    private function isReportSnapshotVersionConflict(PDOException $e): bool
    {
        $info = $e->errorInfo;
        $sqlState = (string)($info[0] ?? $e->getCode());
        $driverCode = (int)($info[1] ?? 0);
        $message = $e->getMessage();

        return $sqlState === '23000'
            && $driverCode === 1062
            && str_contains($message, 'uq_v2_report_snapshot_version');
    }

    private function closedAtForMonth(string $workspaceId, int $year, int $month): ?string
    {
        $closure = $this->monthClosure($workspaceId, $year, $month);
        if (!$closure || (int)$closure['is_closed'] !== 1) {
            return null;
        }

        return $closure['closed_at'] ?? null;
    }

    private function atomToSqlDateTime(string $value): string
    {
        try {
            $dt = $value === '' ? new DateTimeImmutable() : new DateTimeImmutable($value);
        } catch (Throwable) {
            $dt = new DateTimeImmutable();
        }

        return $dt->format('Y-m-d H:i:s');
    }

    private function reportSnapshotById(string $snapshotId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM v2_report_snapshots WHERE id = ? LIMIT 1");
        $stmt->execute([$snapshotId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new FinDeskV2HttpError(500, 'snapshot_not_found_after_create');
        }

        return $this->reportSnapshotRow($row);
    }

    private function reportBatchSchemaIsAvailable(): bool
    {
        if ($this->reportBatchSchemaAvailable !== null) {
            return $this->reportBatchSchemaAvailable;
        }

        try {
            $this->reportBatchSchemaAvailable = $this->tableExists('v2_report_batches')
                && $this->tableExists('v2_report_batch_entries');
        } catch (PDOException) {
            $this->reportBatchSchemaAvailable = false;
        }

        return $this->reportBatchSchemaAvailable;
    }

    private function ensureOperationalReportStatusSchema(): void
    {
        try {
            if ($this->tableExists('v2_report_batches')) {
                $type = $this->columnType('v2_report_batches', 'status') ?? '';
                if (!str_contains($type, "'requires_update'") || !str_contains($type, "'returned_for_revision'")) {
                    $this->db->exec("
                        ALTER TABLE v2_report_batches
                        MODIFY status ENUM('draft','created','sent','requires_update','returned_for_revision','superseded') NOT NULL DEFAULT 'created'
                    ");
                }
            }

            if ($this->tableExists('v2_report_packages')) {
                $type = $this->columnType('v2_report_packages', 'status') ?? '';
                if (!str_contains($type, "'requires_update'") || !str_contains($type, "'returned_for_revision'")) {
                    $this->db->exec("
                        ALTER TABLE v2_report_packages
                        MODIFY status ENUM('draft','created','sent','requires_update','returned_for_revision','superseded') NOT NULL DEFAULT 'created'
                    ");
                }
            }

            if ($this->tableExists('v2_report_versions')) {
                $type = $this->columnType('v2_report_versions', 'status') ?? '';
                if (!str_contains($type, "'requires_update'") || !str_contains($type, "'returned_for_revision'")) {
                    $this->db->exec("
                        ALTER TABLE v2_report_versions
                        MODIFY status ENUM('stored','closed','created','sent','requires_update','returned_for_revision','superseded') NOT NULL DEFAULT 'created'
                    ");
                }
            }
        } catch (PDOException) {
            // Older disposable schemas may not have report tables yet; normal setup creates them later.
        }
    }

    private function tableExists(string $tableName): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
            LIMIT 1
        ");
        $stmt->execute([$tableName]);

        return $stmt->fetchColumn() !== false;
    }

    private function columnExists(string $tableName, string $columnName): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1
        ");
        $stmt->execute([$tableName, $columnName]);

        return $stmt->fetchColumn() !== false;
    }

    private function columnType(string $tableName, string $columnName): ?string
    {
        $stmt = $this->db->prepare("
            SELECT COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
            LIMIT 1
        ");
        $stmt->execute([$tableName, $columnName]);
        $type = $stmt->fetchColumn();

        return $type === false ? null : (string)$type;
    }

    private function ensureColumn(string $tableName, string $columnName, string $alterSql): void
    {
        if (!$this->columnExists($tableName, $columnName)) {
            $this->db->exec($alterSql);
        }
    }

    private function ensureWorkspaceMembershipAccessSchema(): void
    {
        if ($this->workspaceMemberAccessScopeAvailable !== null && $this->workspaceMemberAssignedActorAvailable !== null) {
            return;
        }
        if (!$this->tableExists('v2_workspace_members')) {
            $this->workspaceMemberAccessScopeAvailable = false;
            $this->workspaceMemberAssignedActorAvailable = false;
            return;
        }

        $roleType = $this->columnType('v2_workspace_members', 'role') ?? '';
        if (!str_contains($roleType, "'employee'") || !str_contains($roleType, "'finance'")) {
            $this->db->exec("
                ALTER TABLE v2_workspace_members
                MODIFY role ENUM('owner','admin','assistant','finance','employee','viewer') NOT NULL
            ");
        }
        $this->ensureColumn(
            'v2_workspace_members',
            'access_scope',
            "ALTER TABLE v2_workspace_members ADD COLUMN access_scope ENUM('workspace','own_entries','assigned_actor','none') NOT NULL DEFAULT 'workspace' AFTER role"
        );
        $this->ensureColumn(
            'v2_workspace_members',
            'assigned_actor_id',
            'ALTER TABLE v2_workspace_members ADD COLUMN assigned_actor_id CHAR(36) DEFAULT NULL AFTER access_scope'
        );
        $this->db->exec("UPDATE v2_workspace_members SET access_scope = 'own_entries' WHERE role = 'employee' AND access_scope = 'workspace'");
        $this->workspaceMemberAccessScopeAvailable = true;
        $this->workspaceMemberAssignedActorAvailable = true;
    }

    private function ensureWorkspaceInviteSchema(): void
    {
        if ($this->workspaceInviteSchemaAvailable === true) {
            return;
        }

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS v2_workspace_invites (
                id CHAR(36) NOT NULL,
                workspace_id CHAR(36) NOT NULL,
                token_hash CHAR(64) NOT NULL,
                token_hint VARCHAR(12) NOT NULL,
                invited_email VARCHAR(190) DEFAULT NULL,
                invited_name VARCHAR(190) DEFAULT NULL,
                role ENUM('employee') NOT NULL DEFAULT 'employee',
                access_scope ENUM('own_entries') NOT NULL DEFAULT 'own_entries',
                status ENUM('pending','accepted','revoked','expired') NOT NULL DEFAULT 'pending',
                expires_at DATETIME NOT NULL,
                accepted_at DATETIME DEFAULT NULL,
                accepted_by BIGINT UNSIGNED DEFAULT NULL,
                revoked_at DATETIME DEFAULT NULL,
                revoked_by BIGINT UNSIGNED DEFAULT NULL,
                created_by BIGINT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_v2_workspace_invites_token_hash (token_hash),
                KEY idx_v2_workspace_invites_workspace (workspace_id, status, created_at),
                KEY idx_v2_workspace_invites_email (invited_email),
                CONSTRAINT fk_v2_workspace_invites_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $statusType = $this->columnType('v2_workspace_invites', 'status') ?? '';
        if (!str_contains($statusType, "'expired'")) {
            $this->db->exec("
                ALTER TABLE v2_workspace_invites
                MODIFY status ENUM('pending','accepted','revoked','expired') NOT NULL DEFAULT 'pending'
            ");
        }
        $this->workspaceInviteSchemaAvailable = true;
    }

    private function ensureAccountableOfferSchema(): void
    {
        if ($this->accountableOfferSchemaAvailable === true) {
            return;
        }

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS v2_accountable_offers (
                id CHAR(36) NOT NULL,
                workspace_id CHAR(36) NOT NULL,
                employee_user_id BIGINT UNSIGNED DEFAULT NULL,
                employee_email VARCHAR(190) NOT NULL,
                amount DECIMAL(14,2) NOT NULL,
                currency CHAR(3) NOT NULL DEFAULT 'EUR',
                purpose TEXT NOT NULL,
                status ENUM('pending_offer','accepted_by_employee','cancelled') NOT NULL DEFAULT 'pending_offer',
                created_by BIGINT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                accepted_at DATETIME DEFAULT NULL,
                accepted_by BIGINT UNSIGNED DEFAULT NULL,
                cancelled_at DATETIME DEFAULT NULL,
                cancelled_by BIGINT UNSIGNED DEFAULT NULL,
                no_financial_mutation TINYINT(1) NOT NULL DEFAULT 1,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_v2_accountable_offers_workspace (workspace_id, status, created_at),
                KEY idx_v2_accountable_offers_employee_user (employee_user_id),
                KEY idx_v2_accountable_offers_employee_email (employee_email),
                CONSTRAINT fk_v2_accountable_offers_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->ensureColumn(
            'v2_accountable_offers',
            'no_financial_mutation',
            'ALTER TABLE v2_accountable_offers ADD COLUMN no_financial_mutation TINYINT(1) NOT NULL DEFAULT 1 AFTER cancelled_by'
        );
        $this->accountableOfferSchemaAvailable = true;
    }

    private function ensureAccountableReportSchema(): void
    {
        if ($this->accountableReportSchemaAvailable === true) {
            return;
        }
        $this->ensureAccountableOfferSchema();

        $flowType = $this->columnType('v2_flows', 'type') ?? '';
        if (!str_contains($flowType, "'accountable'")) {
            $this->db->exec("
                ALTER TABLE v2_flows
                MODIFY type ENUM('cash','card','assistant_journal','accountable') NOT NULL
            ");
        }
        $entryType = $this->columnType('v2_entries', 'entry_type') ?? '';
        if (!str_contains($entryType, "'accountable_expense'")) {
            $this->db->exec("
                ALTER TABLE v2_entries
                MODIFY entry_type ENUM('cash_income','cash_expense','card_expense','card_income','opening_balance','correction','info','unrecognized','assistant_pending','accountable_expense') NOT NULL
            ");
        }
        $sourceType = $this->columnType('v2_entries', 'source_type') ?? '';
        if (!str_contains($sourceType, "'accountable_report'")) {
            $this->db->exec("
                ALTER TABLE v2_entries
                MODIFY source_type ENUM('manual','import','assistant','correction','accountable_report') NOT NULL DEFAULT 'manual'
            ");
        }

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS v2_accountable_reports (
                id CHAR(36) NOT NULL,
                workspace_id CHAR(36) NOT NULL,
                offer_id CHAR(36) NOT NULL,
                employee_user_id BIGINT UNSIGNED NOT NULL,
                title VARCHAR(190) NOT NULL,
                status ENUM('draft','submitted','accepted_by_admin','rework_requested','rejected','cancelled') NOT NULL DEFAULT 'draft',
                currency CHAR(3) NOT NULL DEFAULT 'EUR',
                total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                row_count INT UNSIGNED NOT NULL DEFAULT 0,
                submitted_at DATETIME DEFAULT NULL,
                submitted_by BIGINT UNSIGNED DEFAULT NULL,
                reviewed_at DATETIME DEFAULT NULL,
                reviewed_by BIGINT UNSIGNED DEFAULT NULL,
                review_note TEXT DEFAULT NULL,
                accepted_total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                rejected_total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                accepted_cash_expenses DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                accepted_noncash_expenses DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                settlement_status ENUM('pending','closed','return_due','reimburse_due','discrepancy') DEFAULT NULL,
                materialized_at DATETIME DEFAULT NULL,
                ledger_materialization_status ENUM('not_materialized','materialized','partial','revoked') NOT NULL DEFAULT 'not_materialized',
                ledger_materialized_at DATETIME DEFAULT NULL,
                ledger_materialized_by BIGINT UNSIGNED DEFAULT NULL,
                ledger_materialization_hash CHAR(64) DEFAULT NULL,
                created_by BIGINT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                no_financial_mutation TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (id),
                KEY idx_v2_accountable_reports_workspace (workspace_id, status, created_at),
                KEY idx_v2_accountable_reports_offer (offer_id),
                KEY idx_v2_accountable_reports_employee (workspace_id, employee_user_id, status),
                CONSTRAINT fk_v2_accountable_reports_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_v2_accountable_reports_offer FOREIGN KEY (offer_id) REFERENCES v2_accountable_offers (id)
                    ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS v2_accountable_report_rows (
                id CHAR(36) NOT NULL,
                report_id CHAR(36) NOT NULL,
                `row_number` INT UNSIGNED NOT NULL,
                expense_date DATE NOT NULL,
                description TEXT NOT NULL,
                amount DECIMAL(14,2) NOT NULL,
                currency CHAR(3) NOT NULL DEFAULT 'EUR',
                category_code VARCHAR(80) DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                review_status ENUM('pending_review','accepted','adjusted','rejected') NOT NULL DEFAULT 'pending_review',
                accepted_amount DECIMAL(14,2) DEFAULT NULL,
                accepted_category_code VARCHAR(80) DEFAULT NULL,
                payment_method ENUM('cash','card','noncash','own_funds') DEFAULT NULL,
                review_note TEXT DEFAULT NULL,
                operational_entry_id CHAR(36) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_v2_accountable_report_row_number (report_id, `row_number`),
                KEY idx_v2_accountable_report_rows_report (report_id),
                CONSTRAINT fk_v2_accountable_report_rows_report FOREIGN KEY (report_id) REFERENCES v2_accountable_reports (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $statusType = $this->columnType('v2_accountable_reports', 'status') ?? '';
        if (!str_contains($statusType, "'accepted_by_admin'")) {
            $this->db->exec("
                ALTER TABLE v2_accountable_reports
                MODIFY status ENUM('draft','submitted','accepted_by_admin','rework_requested','rejected','cancelled') NOT NULL DEFAULT 'draft'
            ");
        }
        $this->ensureColumn('v2_accountable_reports', 'reviewed_at', 'ALTER TABLE v2_accountable_reports ADD COLUMN reviewed_at DATETIME DEFAULT NULL AFTER submitted_by');
        $this->ensureColumn('v2_accountable_reports', 'reviewed_by', 'ALTER TABLE v2_accountable_reports ADD COLUMN reviewed_by BIGINT UNSIGNED DEFAULT NULL AFTER reviewed_at');
        $this->ensureColumn('v2_accountable_reports', 'review_note', 'ALTER TABLE v2_accountable_reports ADD COLUMN review_note TEXT DEFAULT NULL AFTER reviewed_by');
        $this->ensureColumn('v2_accountable_reports', 'accepted_total_amount', 'ALTER TABLE v2_accountable_reports ADD COLUMN accepted_total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER review_note');
        $this->ensureColumn('v2_accountable_reports', 'rejected_total_amount', 'ALTER TABLE v2_accountable_reports ADD COLUMN rejected_total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER accepted_total_amount');
        $this->ensureColumn('v2_accountable_reports', 'accepted_cash_expenses', 'ALTER TABLE v2_accountable_reports ADD COLUMN accepted_cash_expenses DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER rejected_total_amount');
        $this->ensureColumn('v2_accountable_reports', 'accepted_noncash_expenses', 'ALTER TABLE v2_accountable_reports ADD COLUMN accepted_noncash_expenses DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER accepted_cash_expenses');
        $this->ensureColumn('v2_accountable_reports', 'settlement_status', "ALTER TABLE v2_accountable_reports ADD COLUMN settlement_status ENUM('pending','closed','return_due','reimburse_due','discrepancy') DEFAULT NULL AFTER accepted_noncash_expenses");
        $this->ensureColumn('v2_accountable_reports', 'materialized_at', 'ALTER TABLE v2_accountable_reports ADD COLUMN materialized_at DATETIME DEFAULT NULL AFTER settlement_status');
        $this->ensureColumn('v2_accountable_reports', 'ledger_materialization_status', "ALTER TABLE v2_accountable_reports ADD COLUMN ledger_materialization_status ENUM('not_materialized','materialized','partial','revoked') NOT NULL DEFAULT 'not_materialized' AFTER materialized_at");
        $this->ensureColumn('v2_accountable_reports', 'ledger_materialized_at', 'ALTER TABLE v2_accountable_reports ADD COLUMN ledger_materialized_at DATETIME DEFAULT NULL AFTER ledger_materialization_status');
        $this->ensureColumn('v2_accountable_reports', 'ledger_materialized_by', 'ALTER TABLE v2_accountable_reports ADD COLUMN ledger_materialized_by BIGINT UNSIGNED DEFAULT NULL AFTER ledger_materialized_at');
        $this->ensureColumn('v2_accountable_reports', 'ledger_materialization_hash', 'ALTER TABLE v2_accountable_reports ADD COLUMN ledger_materialization_hash CHAR(64) DEFAULT NULL AFTER ledger_materialized_by');
        $this->ensureColumn('v2_accountable_report_rows', 'review_status', "ALTER TABLE v2_accountable_report_rows ADD COLUMN review_status ENUM('pending_review','accepted','adjusted','rejected') NOT NULL DEFAULT 'pending_review' AFTER notes");
        $this->ensureColumn('v2_accountable_report_rows', 'accepted_amount', 'ALTER TABLE v2_accountable_report_rows ADD COLUMN accepted_amount DECIMAL(14,2) DEFAULT NULL AFTER review_status');
        $this->ensureColumn('v2_accountable_report_rows', 'accepted_category_code', 'ALTER TABLE v2_accountable_report_rows ADD COLUMN accepted_category_code VARCHAR(80) DEFAULT NULL AFTER accepted_amount');
        $this->ensureColumn('v2_accountable_report_rows', 'payment_method', "ALTER TABLE v2_accountable_report_rows ADD COLUMN payment_method ENUM('cash','card','noncash','own_funds') DEFAULT NULL AFTER accepted_category_code");
        $this->ensureColumn('v2_accountable_report_rows', 'review_note', 'ALTER TABLE v2_accountable_report_rows ADD COLUMN review_note TEXT DEFAULT NULL AFTER payment_method');
        $this->ensureColumn('v2_accountable_report_rows', 'operational_entry_id', 'ALTER TABLE v2_accountable_report_rows ADD COLUMN operational_entry_id CHAR(36) DEFAULT NULL AFTER review_note');
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS v2_accountable_settlements (
                id CHAR(36) NOT NULL,
                workspace_id CHAR(36) NOT NULL,
                offer_id CHAR(36) NOT NULL,
                report_id CHAR(36) NOT NULL,
                employee_user_id BIGINT UNSIGNED NOT NULL,
                issued_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                accepted_cash_expenses DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                accepted_noncash_expenses DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                expected_remaining DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                actual_remaining DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                return_due_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                reimburse_due_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                difference_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                status ENUM('closed','return_due','reimburse_due','discrepancy') NOT NULL,
                resolution_status ENUM('open','resolved') NOT NULL DEFAULT 'open',
                resolved_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                resolved_entry_id CHAR(36) DEFAULT NULL,
                resolved_at DATETIME DEFAULT NULL,
                resolved_by BIGINT UNSIGNED DEFAULT NULL,
                resolution_note TEXT DEFAULT NULL,
                created_by BIGINT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_v2_accountable_settlements_report (report_id),
                KEY idx_v2_accountable_settlements_workspace (workspace_id, status, created_at),
                KEY idx_v2_accountable_settlements_offer (offer_id),
                CONSTRAINT fk_v2_accountable_settlements_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_v2_accountable_settlements_offer FOREIGN KEY (offer_id) REFERENCES v2_accountable_offers (id)
                    ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT fk_v2_accountable_settlements_report FOREIGN KEY (report_id) REFERENCES v2_accountable_reports (id)
                    ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->ensureColumn('v2_accountable_settlements', 'resolution_status', "ALTER TABLE v2_accountable_settlements ADD COLUMN resolution_status ENUM('open','resolved') NOT NULL DEFAULT 'open' AFTER status");
        $this->ensureColumn('v2_accountable_settlements', 'resolved_amount', 'ALTER TABLE v2_accountable_settlements ADD COLUMN resolved_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER resolution_status');
        $this->ensureColumn('v2_accountable_settlements', 'resolved_entry_id', 'ALTER TABLE v2_accountable_settlements ADD COLUMN resolved_entry_id CHAR(36) DEFAULT NULL AFTER resolved_amount');
        $this->ensureColumn('v2_accountable_settlements', 'resolved_at', 'ALTER TABLE v2_accountable_settlements ADD COLUMN resolved_at DATETIME DEFAULT NULL AFTER resolved_entry_id');
        $this->ensureColumn('v2_accountable_settlements', 'resolved_by', 'ALTER TABLE v2_accountable_settlements ADD COLUMN resolved_by BIGINT UNSIGNED DEFAULT NULL AFTER resolved_at');
        $this->ensureColumn('v2_accountable_settlements', 'resolution_note', 'ALTER TABLE v2_accountable_settlements ADD COLUMN resolution_note TEXT DEFAULT NULL AFTER resolved_by');
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS v2_accountable_report_entry_links (
                id CHAR(36) NOT NULL,
                workspace_id CHAR(36) NOT NULL,
                report_id CHAR(36) NOT NULL,
                report_row_id CHAR(36) NOT NULL,
                entry_id CHAR(36) NOT NULL,
                idempotency_key CHAR(64) NOT NULL,
                cash_effect ENUM('none') NOT NULL DEFAULT 'none',
                payment_method ENUM('cash','card','noncash','own_funds') NOT NULL,
                accepted_amount DECIMAL(14,2) NOT NULL,
                category_code VARCHAR(80) NOT NULL,
                created_by BIGINT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_v2_accountable_report_entry_links_row (report_row_id),
                UNIQUE KEY uq_v2_accountable_report_entry_links_entry (entry_id),
                UNIQUE KEY uq_v2_accountable_report_entry_links_key (idempotency_key),
                KEY idx_v2_accountable_report_entry_links_workspace (workspace_id, created_at),
                KEY idx_v2_accountable_report_entry_links_report (report_id),
                CONSTRAINT fk_v2_accountable_report_entry_links_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_v2_accountable_report_entry_links_report FOREIGN KEY (report_id) REFERENCES v2_accountable_reports (id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_v2_accountable_report_entry_links_row FOREIGN KEY (report_row_id) REFERENCES v2_accountable_report_rows (id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_v2_accountable_report_entry_links_entry FOREIGN KEY (entry_id) REFERENCES v2_entries (id)
                    ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->accountableReportSchemaAvailable = true;
    }

    private function ensureQuickNoteSchema(): void
    {
        if ($this->quickNoteSchemaAvailable === true) {
            return;
        }

        $importSourceType = $this->columnType('v2_import_sources', 'source_type') ?? '';
        if (!str_contains($importSourceType, "'quick_note'")) {
            $this->db->exec("
                ALTER TABLE v2_import_sources
                MODIFY source_type ENUM('google_drive','excel','legacy_db','manual_upload','quick_note') NOT NULL
            ");
        }

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS v2_quick_notes (
                id CHAR(36) NOT NULL,
                workspace_id CHAR(36) NOT NULL,
                created_by BIGINT UNSIGNED DEFAULT NULL,
                note_date DATE NOT NULL,
                title VARCHAR(190) NOT NULL,
                raw_text TEXT NOT NULL,
                status ENUM('draft','reviewed','converted') NOT NULL DEFAULT 'draft',
                smith_preview_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (smith_preview_json IS NULL OR json_valid(smith_preview_json)),
                converted_at DATETIME DEFAULT NULL,
                archived_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_v2_quick_notes_workspace_status (workspace_id, status, note_date),
                KEY idx_v2_quick_notes_created_by (created_by),
                CONSTRAINT fk_v2_quick_notes_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->quickNoteSchemaAvailable = true;
    }

    private function ensureWorkspaceLiabilityOpeningSchema(): void
    {
        if ($this->workspaceLiabilityOpeningSchemaAvailable === true) {
            return;
        }

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS v2_workspace_liability_openings (
                id CHAR(36) NOT NULL,
                workspace_id CHAR(36) NOT NULL,
                liability_type ENUM('admin_debt') NOT NULL DEFAULT 'admin_debt',
                counterparty VARCHAR(190) NOT NULL DEFAULT 'Администратор',
                amount DECIMAL(14,2) NOT NULL,
                currency CHAR(3) NOT NULL DEFAULT 'EUR',
                basis_date DATE NOT NULL,
                title VARCHAR(190) NOT NULL,
                note TEXT DEFAULT NULL,
                source_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (source_json IS NULL OR json_valid(source_json)),
                created_by BIGINT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                archived_at DATETIME DEFAULT NULL,
                PRIMARY KEY (id),
                KEY idx_v2_liability_openings_workspace (workspace_id, liability_type, archived_at, basis_date),
                CONSTRAINT fk_v2_liability_openings_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->workspaceLiabilityOpeningSchemaAvailable = true;
    }

    private function ensureReportPackageSchema(): void
    {
        if ($this->operationalPackageSchemaAvailable === true && $this->operationalHtmlSnapshotSchemaAvailable === true) {
            return;
        }
        $statements = [
            "CREATE TABLE IF NOT EXISTS v2_report_versions (
                id CHAR(36) NOT NULL,
                workspace_id CHAR(36) NOT NULL,
                report_id CHAR(36) NOT NULL,
                report_type ENUM('operational_fragment','operational_package') NOT NULL,
                version INT UNSIGNED NOT NULL,
                format ENUM('html') NOT NULL DEFAULT 'html',
                status ENUM('stored','closed','created','sent','requires_update','returned_for_revision','superseded') NOT NULL DEFAULT 'created',
                html_filename VARCHAR(255) DEFAULT NULL,
                content_hash CHAR(64) NOT NULL,
                snapshot_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(snapshot_json)),
                created_by BIGINT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_v2_report_versions_report (workspace_id, report_type, report_id, version, format),
                KEY idx_v2_report_versions_workspace (workspace_id, created_at),
                KEY idx_v2_report_versions_report_latest (report_type, report_id, created_at),
                CONSTRAINT fk_v2_report_versions_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS v2_report_packages (
                id CHAR(36) NOT NULL,
                workspace_id CHAR(36) NOT NULL,
                package_type ENUM('operational_fragment_package') NOT NULL DEFAULT 'operational_fragment_package',
                title VARCHAR(190) NOT NULL,
                status ENUM('draft','created','sent','requires_update','returned_for_revision','superseded') NOT NULL DEFAULT 'created',
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                fragment_count INT UNSIGNED NOT NULL DEFAULT 0,
                entry_count INT UNSIGNED NOT NULL DEFAULT 0,
                generated_at DATETIME NOT NULL,
                closed_at DATETIME DEFAULT NULL,
                comment TEXT DEFAULT NULL,
                html_filename VARCHAR(255) DEFAULT NULL,
                summary_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(summary_json)),
                fragment_ids_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(fragment_ids_json)),
                source_entry_ids_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(source_entry_ids_json)),
                content_hash CHAR(64) NOT NULL,
                created_by BIGINT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_v2_report_packages_workspace (workspace_id, created_at),
                KEY idx_v2_report_packages_period (workspace_id, start_date, end_date),
                KEY idx_v2_report_packages_status (status),
                CONSTRAINT fk_v2_report_packages_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS v2_report_package_items (
                id CHAR(36) NOT NULL,
                package_id CHAR(36) NOT NULL,
                batch_id CHAR(36) NOT NULL,
                html_snapshot_id CHAR(36) DEFAULT NULL,
                item_order INT UNSIGNED NOT NULL,
                fragment_snapshot_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(fragment_snapshot_json)),
                html_snapshot_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (html_snapshot_json IS NULL OR json_valid(html_snapshot_json)),
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_v2_report_package_item (package_id, batch_id),
                KEY idx_v2_report_package_items_batch (batch_id),
                KEY idx_v2_report_package_items_html_snapshot (html_snapshot_id),
                CONSTRAINT fk_v2_report_package_items_package FOREIGN KEY (package_id) REFERENCES v2_report_packages (id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_v2_report_package_items_batch FOREIGN KEY (batch_id) REFERENCES v2_report_batches (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS v2_report_batch_html_snapshots (
                id CHAR(36) NOT NULL,
                workspace_id CHAR(36) NOT NULL,
                batch_id CHAR(36) NOT NULL,
                version INT UNSIGNED NOT NULL,
                status ENUM('stored','closed','superseded') NOT NULL DEFAULT 'stored',
                generated_at DATETIME NOT NULL,
                stored_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                html_filename VARCHAR(255) DEFAULT NULL,
                html_content LONGTEXT NOT NULL,
                html_size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
                html_hash CHAR(64) NOT NULL,
                source_batch_hash CHAR(64) NOT NULL,
                comment TEXT DEFAULT NULL,
                created_by BIGINT UNSIGNED DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_v2_report_batch_html_snapshot_version (workspace_id, batch_id, version),
                KEY idx_v2_report_batch_html_snapshots_batch (batch_id, version),
                KEY idx_v2_report_batch_html_snapshots_workspace (workspace_id, created_at),
                CONSTRAINT fk_v2_report_batch_html_snapshots_workspace FOREIGN KEY (workspace_id) REFERENCES v2_workspaces (id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_v2_report_batch_html_snapshots_batch FOREIGN KEY (batch_id) REFERENCES v2_report_batches (id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
        foreach ($statements as $sql) {
            $this->db->exec($sql);
        }
        $this->ensureColumn('v2_report_packages', 'package_type', "ALTER TABLE v2_report_packages ADD COLUMN package_type ENUM('operational_fragment_package') NOT NULL DEFAULT 'operational_fragment_package' AFTER workspace_id");
        $this->ensureColumn('v2_report_packages', 'comment', "ALTER TABLE v2_report_packages ADD COLUMN comment TEXT DEFAULT NULL AFTER closed_at");
        $this->ensureColumn('v2_report_packages', 'source_entry_ids_json', "ALTER TABLE v2_report_packages ADD COLUMN source_entry_ids_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (source_entry_ids_json IS NULL OR json_valid(source_entry_ids_json)) AFTER fragment_ids_json");
        $this->ensureColumn('v2_report_package_items', 'html_snapshot_id', "ALTER TABLE v2_report_package_items ADD COLUMN html_snapshot_id CHAR(36) DEFAULT NULL AFTER batch_id");
        $this->ensureColumn('v2_report_package_items', 'html_snapshot_json', "ALTER TABLE v2_report_package_items ADD COLUMN html_snapshot_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (html_snapshot_json IS NULL OR json_valid(html_snapshot_json)) AFTER fragment_snapshot_json");
        $this->operationalHtmlSnapshotSchemaAvailable = true;
        $this->operationalPackageSchemaAvailable = true;
    }

    private function nextReportVersion(string $workspaceId, string $reportId, string $reportType): int
    {
        $this->ensureReportPackageSchema();
        $stmt = $this->db->prepare("
            SELECT COALESCE(MAX(version), 0) + 1
            FROM v2_report_versions
            WHERE workspace_id = ?
              AND report_type = ?
              AND report_id = ?
              AND format = 'html'
        ");
        $stmt->execute([$workspaceId, $reportType, $reportId]);

        return (int)$stmt->fetchColumn();
    }

    private function storeReportVersion(string $workspaceId, string $reportId, string $reportType, string $status, ?string $htmlFilename, string $contentHash, array $snapshot, int $userId): void
    {
        $this->ensureReportPackageSchema();
        $this->db->prepare("
            INSERT INTO v2_report_versions (
                id, workspace_id, report_id, report_type, version, format, status,
                html_filename, content_hash, snapshot_json, created_by
            )
            VALUES (?, ?, ?, ?, ?, 'html', ?, ?, ?, ?, ?)
        ")->execute([
            FinDeskV2Support::uuid(),
            $workspaceId,
            $reportId,
            $reportType,
            $this->nextReportVersion($workspaceId, $reportId, $reportType),
            $status,
            $htmlFilename,
            $contentHash,
            FinDeskV2Support::jsonEncode($snapshot),
            $userId,
        ]);
    }

    private function listReportVersions(string $workspaceId, string $reportId, string $reportType): array
    {
        $this->ensureReportPackageSchema();
        $stmt = $this->db->prepare("
            SELECT id, report_type, report_id, version, format, status, html_filename, content_hash, created_at
            FROM v2_report_versions
            WHERE workspace_id = ?
              AND report_type = ?
              AND report_id = ?
            ORDER BY version DESC
        ");
        $stmt->execute([$workspaceId, $reportType, $reportId]);

        return array_map(static function (array $row): array {
            $type = (string)$row['report_type'];
            $id = (string)$row['report_id'];
            return [
                'id' => (string)$row['id'],
                'report_type' => $type,
                'report_id' => $id,
                'version' => (int)$row['version'],
                'format' => (string)$row['format'],
                'status' => (string)$row['status'],
                'html_filename' => $row['html_filename'] ?? null,
                'html_url' => $type === 'operational_package'
                    ? ('/v2-report.php?type=package&id=' . rawurlencode($id) . '&version=' . (int)$row['version'])
                    : ('/v2-report.php?id=' . rawurlencode($id) . '&version=' . (int)$row['version']),
                'content_hash' => (string)$row['content_hash'],
                'created_at' => $row['created_at'] ?? null,
            ];
        }, $stmt->fetchAll());
    }

    private function monthClosure(string $workspaceId, int $year, int $month): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_monthly_closures
            WHERE workspace_id = ? AND year = ? AND month = ?
            LIMIT 1
        ");
        $stmt->execute([$workspaceId, $year, $month]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function monthClosureRow(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        return [
            'id' => (string)$row['id'],
            'workspace_id' => (string)$row['workspace_id'],
            'year' => (int)$row['year'],
            'month' => (int)$row['month'],
            'opening_balance' => $row['opening_balance'] === null ? null : (float)$row['opening_balance'],
            'closing_balance' => $row['closing_balance'] === null ? null : (float)$row['closing_balance'],
            'is_closed' => (bool)$row['is_closed'],
            'comment' => ($row['comment'] ?? null) === null ? null : (string)$row['comment'],
            'closed_by' => $row['closed_by'] === null ? null : (int)$row['closed_by'],
            'closed_at' => $row['closed_at'] ?? null,
        ];
    }

    private function strictSignedAmount(string $rawText, string $key): array
    {
        if (preg_match('/^([+-])\s*([0-9]+(?:[.,][0-9]{1,2})?)/u', $rawText, $match) !== 1) {
            throw new FinDeskV2HttpError(422, 'invalid_' . $key);
        }

        return [
            'sign' => $match[1],
            'amount' => number_format((float)str_replace(',', '.', $match[2]), 2, '.', ''),
            'direction' => $match[1] === '+' ? 'in' : 'out',
        ];
    }

    private function sourceFilesForMonth(string $workspaceId, string $monthStart, string $monthEnd): array
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT
                s.id,
                s.source_type,
                s.file_name,
                s.file_url,
                s.file_id,
                s.status,
                s.include_decision
            FROM v2_entries e
            INNER JOIN v2_import_sources s ON s.id = e.source_id
            WHERE e.workspace_id = ?
              AND e.archived_at IS NULL
              AND e.date >= ?
              AND e.date < ?
            ORDER BY s.file_name ASC, s.created_at ASC
        ");
        $stmt->execute([$workspaceId, $monthStart, $monthEnd]);

        return array_map(static fn (array $row): array => [
            'id' => (string)$row['id'],
            'source_type' => (string)$row['source_type'],
            'file_name' => $row['file_name'] === null ? null : (string)$row['file_name'],
            'file_url' => $row['file_url'] === null ? null : (string)$row['file_url'],
            'file_id' => $row['file_id'] === null ? null : (string)$row['file_id'],
            'status' => (string)$row['status'],
            'include_decision' => (string)$row['include_decision'],
        ], $stmt->fetchAll());
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

        if (!$this->isCountedStatus((string)$entry['status'])) {
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

    private function reportMoneyPosition(float|int|string|null $adminCash, array $accountableSummary = []): array
    {
        $adminCashValue = $adminCash === null || $adminCash === '' ? null : round((float)$adminCash, 2);
        $employeeHeldCash = round(max((float)($accountableSummary['open_position_total'] ?? 0.0), 0.0), 2);
        $returnDue = round(max((float)($accountableSummary['return_due_total'] ?? 0.0), 0.0), 2);
        $reimburseDue = round(max((float)($accountableSummary['reimburse_due_total'] ?? 0.0), 0.0), 2);
        $physicalAvailable = $adminCashValue === null
            ? null
            : round($adminCashValue + $employeeHeldCash, 2);

        return [
            'policy' => 'physical_pool_admin_cash_plus_employee_held_cash',
            'physical_available_total' => $physicalAvailable,
            'admin_cash' => $adminCashValue,
            'employee_held_cash' => $employeeHeldCash,
            'return_due_from_employees' => $returnDue,
            'reimburse_due_to_employees' => $reimburseDue,
            'accepted_employee_expenses_total' => round((float)($accountableSummary['accepted_cash_expenses_total'] ?? 0.0), 2),
            'submitted_employee_reports_total' => round((float)($accountableSummary['submitted_report_total'] ?? 0.0), 2),
            'pending_employee_offer_total' => round((float)($accountableSummary['pending_offer_total'] ?? 0.0), 2),
            'card_balance' => null,
            'card_balance_available' => false,
        ];
    }

    private function withReportMoneyPosition(array $report): array
    {
        if (is_array($report['money_position'] ?? null)) {
            return $report;
        }

        $totals = is_array($report['totals'] ?? null) ? $report['totals'] : [];
        $blocks = is_array($report['blocks'] ?? null) ? $report['blocks'] : [];
        $lowerBlock = is_array($blocks['lower_accounting'] ?? null) ? $blocks['lower_accounting'] : [];
        $fallbackOpen = (float)($lowerBlock['total'] ?? $totals['lower_accounting_total'] ?? 0.0);
        $moneyPosition = $this->reportMoneyPosition($totals['ending_cash'] ?? null, [
            'open_position_total' => max($fallbackOpen, 0.0),
        ]);
        $moneyPosition['policy'] = 'legacy_report_fallback_admin_cash_plus_lower_accounting_open_amount';
        $report['money_position'] = $moneyPosition;
        if (!isset($report['blocks']) || !is_array($report['blocks'])) {
            $report['blocks'] = [];
        }
        $report['blocks']['money_position'] = $moneyPosition;

        return $report;
    }

    private function getEntry(string $entryId, int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                e.*,
                w.name AS workspace_name,
                f.type AS flow_type,
                f.name AS flow_name,
                c.code AS category_code,
                c.name_json AS category_name_json,
                a.name AS actor_name
            FROM v2_entries e
            INNER JOIN v2_workspaces w ON w.id = e.workspace_id
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
        $this->assertEntryVisible($row, $userId, 'entry_not_found');

        return $this->entryRow($row);
    }

    private function getAttachmentForUser(string $attachmentId, int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                att.*,
                e.workspace_id AS entry_workspace_id,
                e.created_by AS entry_created_by,
                e.actor_id AS entry_actor_id
            FROM v2_attachments att
            INNER JOIN v2_entries e ON e.id = att.entry_id
            INNER JOIN v2_workspace_members m ON m.workspace_id = e.workspace_id
            WHERE att.id = ? AND m.user_id = ? AND e.archived_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$attachmentId, $userId]);
        $row = $stmt->fetch();

        if (!$row) {
            throw new FinDeskV2HttpError(404, 'attachment_not_found');
        }
        $this->assertEntryVisible([
            'workspace_id' => $row['entry_workspace_id'],
            'created_by' => $row['entry_created_by'],
            'actor_id' => $row['entry_actor_id'],
        ], $userId, 'attachment_not_found');

        return $this->attachmentRow($row);
    }

    private function requireWorkspaceWriter(string $workspaceId, int $userId): void
    {
        $access = $this->workspaceAccess($workspaceId, $userId);
        if (!$access['can_write_workspace']) {
            throw new FinDeskV2HttpError(403, 'workspace_read_only');
        }
    }

    private function requireWorkspaceOwnerAdmin(string $workspaceId, int $userId): void
    {
        $access = $this->workspaceAccess($workspaceId, $userId);
        if (!$access['can_admin']) {
            throw new FinDeskV2HttpError(403, 'workspace_admin_required');
        }
    }

    private function requireWorkspaceEntryWriter(string $workspaceId, int $userId): void
    {
        $access = $this->workspaceAccess($workspaceId, $userId);
        if (!$access['can_write']) {
            throw new FinDeskV2HttpError(403, 'workspace_read_only');
        }
    }

    private function requireWorkspaceFullReader(string $workspaceId, int $userId): void
    {
        $access = $this->workspaceAccess($workspaceId, $userId);
        if (!$access['can_read_workspace']) {
            throw new FinDeskV2HttpError(403, 'workspace_scope_required');
        }
    }

    private function workspaceAccess(string $workspaceId, int $userId): array
    {
        $memberProjection = $this->workspaceMemberProjectionSql('m');
        $stmt = $this->db->prepare("
            SELECT {$memberProjection}
            FROM v2_workspace_members m
            INNER JOIN v2_workspaces w ON w.id = m.workspace_id
            WHERE m.workspace_id = ? AND m.user_id = ? AND w.archived_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$workspaceId, $userId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new FinDeskV2HttpError(404, 'workspace_not_found');
        }

        return $this->workspaceAccessFromMemberRow($row);
    }

    private function workspaceMemberExists(string $workspaceId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM v2_workspace_members
            WHERE workspace_id = ? AND user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$workspaceId, $userId]);

        return $stmt->fetchColumn() !== false;
    }

    private function workspaceRowById(string $workspaceId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_workspaces
            WHERE id = ? AND archived_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$workspaceId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new FinDeskV2HttpError(404, 'workspace_not_found');
        }

        return $row;
    }

    private function workspaceInviteById(string $inviteId, string $workspaceId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_workspace_invites
            WHERE id = ? AND workspace_id = ?
            LIMIT 1
        ");
        $stmt->execute([$inviteId, $workspaceId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new FinDeskV2HttpError(404, 'invite_not_found');
        }

        return $row;
    }

    private function workspaceInviteByToken(string $token, bool $forUpdate = false): array
    {
        $token = trim($token);
        if ($token === '' || !preg_match('/^[a-f0-9]{48}$/i', $token)) {
            throw new FinDeskV2HttpError(422, 'invalid_invite_token');
        }
        $lock = $forUpdate ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_workspace_invites
            WHERE token_hash = ?
            LIMIT 1{$lock}
        ");
        $stmt->execute([hash('sha256', $token)]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new FinDeskV2HttpError(404, 'invite_not_found');
        }

        return $row;
    }

    private function assertWorkspaceInvitePending(array $row): void
    {
        $status = (string)$row['status'];
        if ($status === 'accepted') {
            throw new FinDeskV2HttpError(409, 'invite_already_accepted');
        }
        if ($status === 'revoked') {
            throw new FinDeskV2HttpError(409, 'invite_revoked');
        }
        if ($status === 'expired') {
            throw new FinDeskV2HttpError(409, 'invite_expired');
        }
        $stmt = $this->db->prepare("SELECT ? <= NOW()");
        $stmt->execute([(string)$row['expires_at']]);
        if ((int)$stmt->fetchColumn() === 1) {
            $this->db->prepare("UPDATE v2_workspace_invites SET status = 'expired' WHERE id = ? AND status = 'pending'")
                ->execute([$row['id']]);
            throw new FinDeskV2HttpError(409, 'invite_expired');
        }
    }

    private function workspaceInviteRow(array $row, bool $includeSecret, ?string $token = null): array
    {
        $invite = [
            'id' => (string)$row['id'],
            'workspace_id' => (string)$row['workspace_id'],
            'token_hint' => (string)$row['token_hint'],
            'invited_email' => $row['invited_email'] === null ? null : (string)$row['invited_email'],
            'invited_name' => $row['invited_name'] === null ? null : (string)$row['invited_name'],
            'role' => (string)$row['role'],
            'role_label' => self::WORKSPACE_ROLE_LABELS[(string)$row['role']] ?? (string)$row['role'],
            'access_scope' => (string)$row['access_scope'],
            'status' => (string)$row['status'],
            'expires_at' => $row['expires_at'] ?? null,
            'accepted_at' => $row['accepted_at'] ?? null,
            'accepted_by' => isset($row['accepted_by']) ? (int)$row['accepted_by'] : null,
            'revoked_at' => $row['revoked_at'] ?? null,
            'revoked_by' => isset($row['revoked_by']) ? (int)$row['revoked_by'] : null,
            'created_by' => isset($row['created_by']) ? (int)$row['created_by'] : null,
            'created_at' => $row['created_at'] ?? null,
        ];
        if ($includeSecret && $token !== null) {
            $invite['token'] = $token;
            $invite['url'] = $this->workspaceInviteUrl($token);
        }

        return $invite;
    }

    private function workspaceInviteUrl(string $token): string
    {
        $config = function_exists('ql_config') ? ql_config() : [];
        $appUrl = rtrim((string)($config['app_url'] ?? ''), '/');
        if ($appUrl === '') {
            $appUrl = '';
        }

        return $appUrl . '/?invite=' . rawurlencode($token);
    }

    private function workspaceInviteAuditPayload(array $invite): array
    {
        unset($invite['token'], $invite['url']);

        return $invite;
    }

    private function listEmployeeAccountableOffers(string $workspaceId, int $userId): array
    {
        $this->ensureAccountableOfferSchema();
        $email = $this->userEmail($userId);
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_accountable_offers
            WHERE workspace_id = ?
              AND (employee_user_id = ? OR employee_email = ?)
            ORDER BY created_at DESC, id DESC
            LIMIT 100
        ");
        $stmt->execute([$workspaceId, $userId, $email]);

        return array_map([$this, 'accountableOfferRow'], $stmt->fetchAll());
    }

    private function normalizeAccountableOfferTarget(string $workspaceId, array $input): array
    {
        $hasEmployeeUserId = array_key_exists('employee_user_id', $input) && $input['employee_user_id'] !== null && $input['employee_user_id'] !== '';
        $emailKey = null;
        foreach (['employee_email', 'invited_email', 'email'] as $key) {
            if (array_key_exists($key, $input) && trim((string)$input[$key]) !== '') {
                $emailKey = $key;
                break;
            }
        }

        if ($hasEmployeeUserId && $emailKey !== null) {
            throw new FinDeskV2HttpError(422, 'ambiguous_accountable_offer_target');
        }
        if (!$hasEmployeeUserId && $emailKey === null) {
            throw new FinDeskV2HttpError(422, 'missing_accountable_offer_target');
        }

        if ($hasEmployeeUserId) {
            $employeeUserId = $this->optionalInt($input, 'employee_user_id', 0);
            if ($employeeUserId <= 0) {
                throw new FinDeskV2HttpError(422, 'invalid_employee_user_id');
            }

            return $this->workspaceEmployeeTargetByUserId($workspaceId, $employeeUserId);
        }

        $email = $this->normalizeRequiredEmail(FinDeskV2Support::requireString($input, (string)$emailKey, 190));

        return [
            'employee_user_id' => $this->workspaceEmployeeUserIdByEmail($workspaceId, $email),
            'employee_email' => $email,
        ];
    }

    private function workspaceEmployeeTargetByUserId(string $workspaceId, int $employeeUserId): array
    {
        $stmt = $this->db->prepare("
            SELECT u.id, u.email
            FROM users u
            INNER JOIN v2_workspace_members m ON m.user_id = u.id
            WHERE m.workspace_id = ?
              AND m.user_id = ?
              AND m.role = 'employee'
              AND u.status = 'active'
              AND u.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$workspaceId, $employeeUserId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new FinDeskV2HttpError(422, 'employee_member_not_found');
        }

        return [
            'employee_user_id' => (int)$row['id'],
            'employee_email' => $this->normalizeRequiredEmail((string)$row['email']),
        ];
    }

    private function workspaceEmployeeUserIdByEmail(string $workspaceId, string $email): ?int
    {
        $stmt = $this->db->prepare("
            SELECT u.id
            FROM users u
            INNER JOIN v2_workspace_members m ON m.user_id = u.id
            WHERE m.workspace_id = ?
              AND m.role = 'employee'
              AND u.email = ?
              AND u.status = 'active'
              AND u.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$workspaceId, $email]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int)$id;
    }

    private function accountableOfferById(string $offerId, bool $forUpdate = false): array
    {
        $lock = $forUpdate ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_accountable_offers
            WHERE id = ?
            LIMIT 1{$lock}
        ");
        $stmt->execute([$offerId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new FinDeskV2HttpError(404, 'accountable_offer_not_found');
        }

        return $row;
    }

    private function assertAccountableOfferVisibleToEmployee(array $offer, int $userId): void
    {
        $workspaceId = (string)$offer['workspace_id'];
        $access = $this->getWorkspaceAccess($workspaceId, $userId);
        if ((string)$access['role'] !== 'employee') {
            throw new FinDeskV2HttpError(404, 'accountable_offer_not_found');
        }
        $email = $this->userEmail($userId);
        $employeeUserId = $offer['employee_user_id'] === null ? null : (int)$offer['employee_user_id'];
        if ($employeeUserId !== null) {
            if ($employeeUserId === $userId) {
                return;
            }
            throw new FinDeskV2HttpError(404, 'accountable_offer_not_found');
        }
        if ((string)$offer['employee_email'] === $email) {
            return;
        }

        throw new FinDeskV2HttpError(404, 'accountable_offer_not_found');
    }

    private function accountableEmployeeKey(mixed $employeeUserId, string $employeeEmail): string
    {
        if ($employeeUserId !== null && (int)$employeeUserId > 0) {
            return 'user:' . (int)$employeeUserId;
        }
        $email = strtolower(trim($employeeEmail));
        if ($email !== '') {
            return 'email:' . $email;
        }

        return 'unknown';
    }

    private function emptyAccountableDashboardEmployee(mixed $employeeUserId, string $employeeEmail, string $currency): array
    {
        $email = strtolower(trim($employeeEmail));
        return [
            'employee_user_id' => $employeeUserId === null ? null : (int)$employeeUserId,
            'employee_label' => $email !== '' ? $email : 'Сотрудник не указан',
            'currency' => $currency,
            'offer_count' => 0,
            'report_count' => 0,
            'metrics' => [
                'pending_offer_total' => 0.0,
                'issued_total' => 0.0,
                'submitted_report_total' => 0.0,
                'accepted_report_total' => 0.0,
                'accepted_cash_expenses_total' => 0.0,
                'accepted_noncash_expenses_total' => 0.0,
                'not_materialized_total' => 0.0,
                'materialized_total' => 0.0,
                'return_due_total' => 0.0,
                'reimburse_due_total' => 0.0,
                'return_due_gross_total' => 0.0,
                'reimburse_due_gross_total' => 0.0,
                'settled_return_total' => 0.0,
                'settled_reimburse_total' => 0.0,
                'open_position_total' => 0.0,
                'submitted_report_count' => 0,
                'accepted_report_count' => 0,
                'not_materialized_report_count' => 0,
                'materialized_report_count' => 0,
            ],
            'offers' => [],
            'reports' => [],
        ];
    }

    private function accountableDashboardOfferRow(array $offer): array
    {
        return [
            'id' => (string)$offer['id'],
            'employee_user_id' => $offer['employee_user_id'],
            'amount' => (float)$offer['amount'],
            'currency' => (string)$offer['currency'],
            'purpose' => $offer['purpose'],
            'status' => (string)$offer['status'],
            'created_at' => $offer['created_at'],
            'accepted_at' => $offer['accepted_at'],
            'no_financial_mutation' => (bool)$offer['no_financial_mutation'],
        ];
    }

    private function accountableDashboardReportRow(array $report): array
    {
        $settlement = is_array($report['settlement'] ?? null) ? $report['settlement'] : null;
        return [
            'id' => (string)$report['id'],
            'offer_id' => (string)$report['offer_id'],
            'employee_user_id' => (int)$report['employee_user_id'],
            'title' => (string)$report['title'],
            'status' => (string)$report['status'],
            'currency' => (string)$report['currency'],
            'total_amount' => (float)$report['total_amount'],
            'row_count' => (int)$report['row_count'],
            'submitted_at' => $report['submitted_at'],
            'reviewed_at' => $report['reviewed_at'],
            'accepted_total_amount' => (float)$report['accepted_total_amount'],
            'rejected_total_amount' => (float)$report['rejected_total_amount'],
            'accepted_cash_expenses' => (float)$report['accepted_cash_expenses'],
            'accepted_noncash_expenses' => (float)$report['accepted_noncash_expenses'],
            'settlement_status' => $report['settlement_status'],
            'ledger_materialization_status' => (string)$report['ledger_materialization_status'],
            'ledger_materialized_at' => $report['ledger_materialized_at'],
            'settlement' => $settlement === null ? null : [
                'issued_amount' => (float)$settlement['issued_amount'],
                'expected_remaining' => (float)$settlement['expected_remaining'],
                'return_due_amount' => (float)$settlement['return_due_amount'],
                'reimburse_due_amount' => (float)$settlement['reimburse_due_amount'],
                'status' => (string)$settlement['status'],
                'resolution_status' => (string)($settlement['resolution_status'] ?? 'open'),
                'resolved_amount' => (float)($settlement['resolved_amount'] ?? 0),
                'resolved_entry_id' => $settlement['resolved_entry_id'] ?? null,
                'resolved_at' => $settlement['resolved_at'] ?? null,
            ],
            'no_financial_mutation' => (bool)$report['no_financial_mutation'],
        ];
    }

    private function accountableOfferRow(array $row): array
    {
        return [
            'id' => (string)$row['id'],
            'workspace_id' => (string)$row['workspace_id'],
            'employee_user_id' => $row['employee_user_id'] === null ? null : (int)$row['employee_user_id'],
            'employee_email' => (string)$row['employee_email'],
            'amount' => (float)$row['amount'],
            'currency' => (string)$row['currency'],
            'purpose' => $row['purpose'] === null ? null : (string)$row['purpose'],
            'status' => (string)$row['status'],
            'created_by' => $row['created_by'] === null ? null : (int)$row['created_by'],
            'created_at' => $row['created_at'] ?? null,
            'accepted_at' => $row['accepted_at'] ?? null,
            'accepted_by' => $row['accepted_by'] === null ? null : (int)$row['accepted_by'],
            'cancelled_at' => $row['cancelled_at'] ?? null,
            'cancelled_by' => $row['cancelled_by'] === null ? null : (int)$row['cancelled_by'],
            'no_financial_mutation' => !array_key_exists('no_financial_mutation', $row) || (bool)$row['no_financial_mutation'],
        ];
    }

    private function accountableReportById(string $reportId, bool $forUpdate = false): array
    {
        $lock = $forUpdate ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_accountable_reports
            WHERE id = ?
            LIMIT 1{$lock}
        ");
        $stmt->execute([$reportId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new FinDeskV2HttpError(404, 'accountable_report_not_found');
        }

        return $row;
    }

    private function assertAccountableReportOwnedByEmployee(array $report, int $userId): void
    {
        $access = $this->getWorkspaceAccess((string)$report['workspace_id'], $userId);
        if ((string)$access['role'] !== 'employee' || (int)$report['employee_user_id'] !== $userId) {
            throw new FinDeskV2HttpError(404, 'accountable_report_not_found');
        }
    }

    private function assertAccountableReportVisibleForReview(array $report, int $userId): void
    {
        $access = $this->getWorkspaceAccess((string)$report['workspace_id'], $userId);
        if ($access['can_admin']) {
            return;
        }
        if ((string)$access['role'] === 'employee' && (int)$report['employee_user_id'] === $userId) {
            return;
        }
        if ((string)$access['role'] === 'employee') {
            throw new FinDeskV2HttpError(404, 'accountable_report_not_found');
        }
        throw new FinDeskV2HttpError(403, 'workspace_admin_required');
    }

    private function requireAccountableReportAdmin(array $report, int $userId): void
    {
        $access = $this->getWorkspaceAccess((string)$report['workspace_id'], $userId);
        if (!$access['can_admin']) {
            if ((string)$access['role'] === 'employee') {
                throw new FinDeskV2HttpError(404, 'accountable_report_not_found');
            }
            throw new FinDeskV2HttpError(403, 'workspace_admin_required');
        }
    }

    private function accountableReportInputRows(array $input, string $defaultCurrency): array
    {
        if (!isset($input['rows']) || !is_array($input['rows']) || $input['rows'] === []) {
            throw new FinDeskV2HttpError(422, 'missing_rows');
        }
        if (count($input['rows']) > 100) {
            throw new FinDeskV2HttpError(422, 'too_many_rows');
        }

        $rows = [];
        foreach ($input['rows'] as $row) {
            if (!is_array($row)) {
                throw new FinDeskV2HttpError(422, 'invalid_rows');
            }
            $dateKey = array_key_exists('expense_date', $row) ? 'expense_date' : 'date';
            $amount = FinDeskV2Support::nullableAmount($row['amount'] ?? null);
            if ($amount === null || (float)$amount <= 0) {
                throw new FinDeskV2HttpError(422, 'invalid_amount');
            }
            $currency = strtoupper(FinDeskV2Support::optionalString($row, 'currency', $defaultCurrency, 3) ?? $defaultCurrency);
            if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
                throw new FinDeskV2HttpError(422, 'invalid_currency');
            }
            $rows[] = [
                'date' => FinDeskV2Support::date($row, $dateKey),
                'description' => FinDeskV2Support::requireString($row, 'description', 1000),
                'amount' => $amount,
                'currency' => $currency,
                'category_code' => FinDeskV2Support::optionalString($row, 'category_code', null, 80),
                'notes' => FinDeskV2Support::optionalString($row, 'notes', null, 1000)
                    ?? FinDeskV2Support::optionalString($row, 'receipt_note', null, 1000),
            ];
        }

        return $rows;
    }

    private function accountableReportRow(array $row, bool $includeRows = false): array
    {
        $report = [
            'id' => (string)$row['id'],
            'workspace_id' => (string)$row['workspace_id'],
            'offer_id' => (string)$row['offer_id'],
            'employee_user_id' => (int)$row['employee_user_id'],
            'title' => (string)$row['title'],
            'status' => (string)$row['status'],
            'currency' => (string)$row['currency'],
            'total_amount' => (float)$row['total_amount'],
            'row_count' => (int)$row['row_count'],
            'submitted_at' => $row['submitted_at'] ?? null,
            'submitted_by' => $row['submitted_by'] === null ? null : (int)$row['submitted_by'],
            'reviewed_at' => $row['reviewed_at'] ?? null,
            'reviewed_by' => ($row['reviewed_by'] ?? null) === null ? null : (int)$row['reviewed_by'],
            'review_note' => $row['review_note'] ?? null,
            'accepted_total_amount' => isset($row['accepted_total_amount']) ? (float)$row['accepted_total_amount'] : 0.0,
            'rejected_total_amount' => isset($row['rejected_total_amount']) ? (float)$row['rejected_total_amount'] : 0.0,
            'accepted_cash_expenses' => isset($row['accepted_cash_expenses']) ? (float)$row['accepted_cash_expenses'] : 0.0,
            'accepted_noncash_expenses' => isset($row['accepted_noncash_expenses']) ? (float)$row['accepted_noncash_expenses'] : 0.0,
            'settlement_status' => $row['settlement_status'] ?? null,
            'materialized_at' => $row['materialized_at'] ?? null,
            'ledger_materialization_status' => $row['ledger_materialization_status'] ?? 'not_materialized',
            'ledger_materialized_at' => $row['ledger_materialized_at'] ?? null,
            'ledger_materialized_by' => ($row['ledger_materialized_by'] ?? null) === null ? null : (int)$row['ledger_materialized_by'],
            'ledger_materialization_hash' => $row['ledger_materialization_hash'] ?? null,
            'created_by' => $row['created_by'] === null ? null : (int)$row['created_by'],
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'no_financial_mutation' => !array_key_exists('no_financial_mutation', $row) || (bool)$row['no_financial_mutation'],
        ];
        if ($includeRows) {
            $report['rows'] = $this->accountableReportRows((string)$row['id']);
            $report['settlement'] = $this->accountableSettlementForReport((string)$row['id']);
        }

        return $report;
    }

    private function accountableReportReviewPlan(array $report, array $offer, array $input): array
    {
        if ((string)$report['workspace_id'] !== (string)$offer['workspace_id']) {
            throw new FinDeskV2HttpError(422, 'accountable_report_offer_mismatch');
        }
        if ((string)$report['currency'] !== (string)$offer['currency']) {
            throw new FinDeskV2HttpError(422, 'accountable_report_currency_mismatch');
        }

        $decisionInput = [];
        if (isset($input['rows']) && is_array($input['rows'])) {
            foreach ($input['rows'] as $rowDecision) {
                if (!is_array($rowDecision)) {
                    throw new FinDeskV2HttpError(422, 'invalid_rows');
                }
                $rowId = FinDeskV2Support::requireString($rowDecision, 'id', 36);
                $decisionInput[$rowId] = $rowDecision;
            }
        }

        $defaultPaymentMethod = FinDeskV2Support::enum(
            FinDeskV2Support::optionalString($input, 'payment_method', 'cash', 40) ?? 'cash',
            ['cash', 'card', 'noncash', 'own_funds'],
            'payment_method'
        );
        $rows = [];
        $acceptedTotal = 0.0;
        $rejectedTotal = 0.0;
        $acceptedCash = 0.0;
        $acceptedNoncash = 0.0;
        foreach ($this->accountableReportRows((string)$report['id']) as $sourceRow) {
            $decision = $decisionInput[$sourceRow['id']] ?? [];
            $reviewStatus = FinDeskV2Support::enum(
                FinDeskV2Support::optionalString($decision, 'review_status', 'accepted', 40) ?? 'accepted',
                ['accepted', 'adjusted', 'rejected'],
                'review_status'
            );
            $paymentMethod = FinDeskV2Support::enum(
                FinDeskV2Support::optionalString($decision, 'payment_method', $defaultPaymentMethod, 40) ?? $defaultPaymentMethod,
                ['cash', 'card', 'noncash', 'own_funds'],
                'payment_method'
            );
            $acceptedAmount = $reviewStatus === 'rejected'
                ? 0.0
                : (float)(FinDeskV2Support::nullableAmount($decision['accepted_amount'] ?? null) ?? $sourceRow['amount']);
            if ($acceptedAmount < 0 || $acceptedAmount > (float)$sourceRow['amount']) {
                throw new FinDeskV2HttpError(422, 'invalid_accepted_amount');
            }
            if ($reviewStatus === 'accepted' && abs($acceptedAmount - (float)$sourceRow['amount']) > 0.004) {
                $reviewStatus = 'adjusted';
            }

            $rejectedAmount = (float)$sourceRow['amount'] - $acceptedAmount;
            $acceptedTotal += $acceptedAmount;
            $rejectedTotal += $rejectedAmount;
            if ($paymentMethod === 'cash') {
                $acceptedCash += $acceptedAmount;
            } else {
                $acceptedNoncash += $acceptedAmount;
            }
            $rows[] = [
                'id' => $sourceRow['id'],
                'row_number' => $sourceRow['row_number'],
                'review_status' => $reviewStatus,
                'accepted_amount' => number_format($acceptedAmount, 2, '.', ''),
                'rejected_amount' => round($rejectedAmount, 2),
                'payment_method' => $paymentMethod,
                'accepted_category_code' => FinDeskV2Support::optionalString($decision, 'category_code', $sourceRow['category_code'], 80),
                'review_note' => FinDeskV2Support::optionalString($decision, 'review_note', null, 1000)
                    ?? FinDeskV2Support::optionalString($decision, 'note', null, 1000),
            ];
        }
        if ($acceptedTotal <= 0) {
            throw new FinDeskV2HttpError(422, 'accountable_report_no_accepted_rows');
        }

        $issued = (float)$offer['amount'];
        $expectedRemaining = $issued - $acceptedCash;
        $actualRemaining = FinDeskV2Support::nullableAmount($input['actual_remaining'] ?? null);
        if ($actualRemaining === null) {
            $actualRemaining = max($expectedRemaining, 0.0);
        }
        $returnDue = max($expectedRemaining, 0.0);
        $reimburseDue = max(-$expectedRemaining, 0.0);
        $status = 'closed';
        if ($reimburseDue > 0.004) {
            $status = 'reimburse_due';
        } elseif ($returnDue > 0.004) {
            $status = 'return_due';
        }

        return [
            'report_id' => (string)$report['id'],
            'offer_id' => (string)$offer['id'],
            'currency' => (string)$report['currency'],
            'accepted_total_amount' => number_format($acceptedTotal, 2, '.', ''),
            'rejected_total_amount' => number_format($rejectedTotal, 2, '.', ''),
            'accepted_cash_expenses' => number_format($acceptedCash, 2, '.', ''),
            'accepted_noncash_expenses' => number_format($acceptedNoncash, 2, '.', ''),
            'rows' => $rows,
            'settlement' => [
                'issued_amount' => number_format($issued, 2, '.', ''),
                'accepted_cash_expenses' => number_format($acceptedCash, 2, '.', ''),
                'accepted_noncash_expenses' => number_format($acceptedNoncash, 2, '.', ''),
                'expected_remaining' => number_format($expectedRemaining, 2, '.', ''),
                'actual_remaining' => number_format((float)$actualRemaining, 2, '.', ''),
                'return_due_amount' => number_format($returnDue, 2, '.', ''),
                'reimburse_due_amount' => number_format($reimburseDue, 2, '.', ''),
                'difference_amount' => number_format((float)$actualRemaining - $expectedRemaining, 2, '.', ''),
                'status' => $status,
            ],
        ];
    }

    private function accountableReportRows(string $reportId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_accountable_report_rows
            WHERE report_id = ?
            ORDER BY `row_number` ASC
        ");
        $stmt->execute([$reportId]);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'id' => (string)$row['id'],
                'report_id' => (string)$row['report_id'],
                'row_number' => (int)$row['row_number'],
                'expense_date' => (string)$row['expense_date'],
                'description' => (string)$row['description'],
                'amount' => (float)$row['amount'],
                'currency' => (string)$row['currency'],
                'category_code' => $row['category_code'] === null ? null : (string)$row['category_code'],
                'notes' => $row['notes'] === null ? null : (string)$row['notes'],
                'review_status' => $row['review_status'] ?? 'pending_review',
                'accepted_amount' => isset($row['accepted_amount']) ? (float)$row['accepted_amount'] : null,
                'accepted_category_code' => $row['accepted_category_code'] === null ? null : (string)$row['accepted_category_code'],
                'payment_method' => $row['payment_method'] ?? null,
                'review_note' => $row['review_note'] === null ? null : (string)$row['review_note'],
                'operational_entry_id' => $row['operational_entry_id'] === null ? null : (string)$row['operational_entry_id'],
            ];
        }

        return $rows;
    }

    private function accountableSettlementForReport(string $reportId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_accountable_settlements
            WHERE report_id = ?
            LIMIT 1
        ");
        $stmt->execute([$reportId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return $this->accountableSettlementRow($row);
    }

    private function accountableSettlementById(string $settlementId, bool $forUpdate = false): array
    {
        $lock = $forUpdate ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_accountable_settlements
            WHERE id = ?
            LIMIT 1{$lock}
        ");
        $stmt->execute([$settlementId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new FinDeskV2HttpError(404, 'accountable_settlement_not_found');
        }

        return $row;
    }

    private function resolveAccountableSettlementInCurrentTransaction(array $settlement, array $entry, array $input, int $userId): array
    {
        $workspaceId = (string)$settlement['workspace_id'];
        $settlementId = (string)$settlement['id'];
        $status = (string)$settlement['status'];
        $expectedAmount = $status === 'return_due'
            ? (float)$settlement['return_due_amount']
            : (float)$settlement['reimburse_due_amount'];
        $expectedDirection = $status === 'return_due' ? 'in' : 'out';

        if ((string)$settlement['resolution_status'] === 'resolved') {
            return $this->accountableSettlementRow($settlement);
        }
        if (!in_array($status, ['return_due', 'reimburse_due'], true)) {
            throw new FinDeskV2HttpError(409, 'accountable_settlement_not_open');
        }
        if ((string)$entry['workspace_id'] !== $workspaceId) {
            throw new FinDeskV2HttpError(422, 'settlement_entry_workspace_mismatch');
        }
        if (($entry['flow']['type'] ?? null) !== 'cash') {
            throw new FinDeskV2HttpError(422, 'settlement_entry_cash_required');
        }
        if (($entry['category_code'] ?? null) !== null) {
            throw new FinDeskV2HttpError(422, 'settlement_entry_must_be_balance_only');
        }
        if (($entry['direction'] ?? null) !== $expectedDirection) {
            throw new FinDeskV2HttpError(422, 'settlement_entry_direction_mismatch');
        }
        if (abs((float)$entry['amount'] - $expectedAmount) > 0.004) {
            throw new FinDeskV2HttpError(422, 'settlement_entry_amount_mismatch');
        }

        $before = $this->accountableSettlementRow($settlement);
        $note = FinDeskV2Support::optionalString($input, 'note', null, 1000)
            ?? FinDeskV2Support::optionalString($input, 'resolution_note', null, 1000);
        $this->db->prepare("
            UPDATE v2_accountable_settlements
            SET resolution_status = 'resolved',
                resolved_amount = ?,
                resolved_entry_id = ?,
                resolved_at = NOW(),
                resolved_by = ?,
                resolution_note = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ")->execute([
            number_format($expectedAmount, 2, '.', ''),
            (string)$entry['id'],
            $userId,
            $note,
            $settlementId,
        ]);

        $after = $this->accountableSettlementRow($this->accountableSettlementById($settlementId));
        $this->audit($workspaceId, 'accountable_settlement', $settlementId, 'resolve_physical_cash', $before, [
            'settlement' => $after,
            'entry' => [
                'id' => $entry['id'],
                'raw_text' => $entry['raw_text'],
                'amount' => $entry['amount'],
                'direction' => $entry['direction'],
            ],
        ], $userId);

        return $after;
    }

    private function accountableSettlementRow(array $row): array
    {
        return [
            'id' => (string)$row['id'],
            'workspace_id' => (string)$row['workspace_id'],
            'offer_id' => (string)$row['offer_id'],
            'report_id' => (string)$row['report_id'],
            'employee_user_id' => (int)$row['employee_user_id'],
            'issued_amount' => (float)$row['issued_amount'],
            'accepted_cash_expenses' => (float)$row['accepted_cash_expenses'],
            'accepted_noncash_expenses' => (float)$row['accepted_noncash_expenses'],
            'expected_remaining' => (float)$row['expected_remaining'],
            'actual_remaining' => (float)$row['actual_remaining'],
            'return_due_amount' => (float)$row['return_due_amount'],
            'reimburse_due_amount' => (float)$row['reimburse_due_amount'],
            'difference_amount' => (float)$row['difference_amount'],
            'status' => (string)$row['status'],
            'resolution_status' => $row['resolution_status'] ?? 'open',
            'resolved_amount' => isset($row['resolved_amount']) ? (float)$row['resolved_amount'] : 0.0,
            'resolved_entry_id' => ($row['resolved_entry_id'] ?? null) === null ? null : (string)$row['resolved_entry_id'],
            'resolved_at' => $row['resolved_at'] ?? null,
            'resolved_by' => ($row['resolved_by'] ?? null) === null ? null : (int)$row['resolved_by'],
            'resolution_note' => $row['resolution_note'] ?? null,
            'created_by' => $row['created_by'] === null ? null : (int)$row['created_by'],
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function accountableReportMaterializationPlan(array $report): array
    {
        if ((string)$report['status'] !== 'accepted_by_admin') {
            throw new FinDeskV2HttpError(409, 'accountable_report_not_accepted_by_admin');
        }
        $rows = [];
        $eligibleCount = 0;
        $total = 0.0;
        foreach ($this->accountableReportRows((string)$report['id']) as $row) {
            $status = (string)($row['review_status'] ?? 'pending_review');
            $acceptedAmount = $row['accepted_amount'] === null ? 0.0 : (float)$row['accepted_amount'];
            $categoryCode = $row['accepted_category_code'] ?? $row['category_code'] ?? null;
            $paymentMethod = $row['payment_method'] ?? null;
            $materializable = in_array($status, ['accepted', 'adjusted'], true)
                && $acceptedAmount > 0
                && $categoryCode !== null
                && $paymentMethod !== null;
            if ($materializable) {
                $eligibleCount++;
                $total += $acceptedAmount;
            }
            $rows[] = [
                'id' => $row['id'],
                'row_number' => $row['row_number'],
                'expense_date' => $row['expense_date'],
                'description' => $row['description'],
                'review_status' => $status,
                'accepted_amount' => $acceptedAmount,
                'category_code' => $categoryCode,
                'payment_method' => $paymentMethod,
                'cash_effect' => 'none',
                'materializable' => $materializable,
                'existing_entry_id' => $row['operational_entry_id'] ?? null,
                'idempotency_key' => hash('sha256', implode(':', [
                    'accountable_report_row',
                    'v1',
                    (string)$row['id'],
                    number_format($acceptedAmount, 2, '.', ''),
                    (string)$categoryCode,
                    (string)$paymentMethod,
                    'cash_effect_none',
                ])),
            ];
        }

        return [
            'report_id' => (string)$report['id'],
            'workspace_id' => (string)$report['workspace_id'],
            'currency' => (string)$report['currency'],
            'policy' => 'cash_effect_none_category_projection',
            'eligible_row_count' => $eligibleCount,
            'projected_total_amount' => round($total, 2),
            'cash_delta' => 0.0,
            'card_delta' => 0.0,
            'rows' => $rows,
        ];
    }

    private function accountableReportMaterializationResult(string $reportId, int $userId): array
    {
        $report = $this->accountableReportRow($this->accountableReportById($reportId), true);
        $links = $this->accountableReportEntryLinks($reportId);
        $entryIds = array_values(array_filter(array_map(static fn (array $link): string => (string)$link['entry_id'], $links)));

        return [
            'report_id' => $reportId,
            'status' => $report['ledger_materialization_status'] ?? 'not_materialized',
            'ledger_materialized_at' => $report['ledger_materialized_at'] ?? null,
            'ledger_materialized_by' => $report['ledger_materialized_by'] ?? null,
            'ledger_materialization_hash' => $report['ledger_materialization_hash'] ?? null,
            'policy' => 'cash_effect_none_category_projection',
            'entry_count' => count($links),
            'entry_ids' => $entryIds,
            'links' => $links,
        ];
    }

    private function accountableProjectionFlowForWorkspace(string $workspaceId, int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_flows
            WHERE workspace_id = ?
              AND type = 'accountable'
            ORDER BY is_default DESC, created_at ASC, id ASC
            LIMIT 1
        ");
        $stmt->execute([$workspaceId]);
        $row = $stmt->fetch();
        if ($row) {
            return $this->flowRow($row);
        }

        return $this->createDefaultFlow($workspaceId, 'Accountable reports', 'accountable', false, false, '0.00');
    }

    private function accountableReportEntryLinksByRow(string $reportId): array
    {
        $links = [];
        foreach ($this->accountableReportEntryLinks($reportId) as $link) {
            $links[(string)$link['report_row_id']] = $link;
        }

        return $links;
    }

    private function accountableReportEntryLinks(string $reportId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_accountable_report_entry_links
            WHERE report_id = ?
            ORDER BY created_at ASC, id ASC
        ");
        $stmt->execute([$reportId]);

        return array_map(static fn (array $row): array => [
            'id' => (string)$row['id'],
            'workspace_id' => (string)$row['workspace_id'],
            'report_id' => (string)$row['report_id'],
            'report_row_id' => (string)$row['report_row_id'],
            'entry_id' => (string)$row['entry_id'],
            'idempotency_key' => (string)$row['idempotency_key'],
            'cash_effect' => (string)$row['cash_effect'],
            'payment_method' => (string)$row['payment_method'],
            'accepted_amount' => (float)$row['accepted_amount'],
            'category_code' => (string)$row['category_code'],
            'created_by' => $row['created_by'] === null ? null : (int)$row['created_by'],
            'created_at' => $row['created_at'] ?? null,
        ], $stmt->fetchAll());
    }

    private function normalizeRequiredEmail(string $email): string
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new FinDeskV2HttpError(422, 'invalid_email');
        }

        return $email;
    }

    private function isDuplicateKey(PDOException $e): bool
    {
        $info = $e->errorInfo;
        $sqlState = (string)($info[0] ?? $e->getCode());
        $driverCode = (int)($info[1] ?? 0);

        return $sqlState === '23000' && $driverCode === 1062;
    }

    private function userEmail(int $userId): string
    {
        $stmt = $this->db->prepare("
            SELECT email
            FROM users
            WHERE id = ? AND status = 'active' AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $email = $stmt->fetchColumn();
        if (!$email) {
            throw new FinDeskV2HttpError(401, 'not_authenticated');
        }

        return strtolower(trim((string)$email));
    }

    private function workspaceMemberProjectionSql(string $alias): string
    {
        $parts = ["{$alias}.role AS member_role"];
        $parts[] = $this->workspaceMemberAccessScopeIsAvailable()
            ? "{$alias}.access_scope AS member_access_scope"
            : "NULL AS member_access_scope";
        $parts[] = $this->workspaceMemberAssignedActorIsAvailable()
            ? "{$alias}.assigned_actor_id AS member_assigned_actor_id"
            : "NULL AS member_assigned_actor_id";

        return implode(', ', $parts);
    }

    private function workspaceMemberAccessScopeIsAvailable(): bool
    {
        if ($this->workspaceMemberAccessScopeAvailable !== null) {
            return $this->workspaceMemberAccessScopeAvailable;
        }

        try {
            $this->workspaceMemberAccessScopeAvailable = $this->columnExists('v2_workspace_members', 'access_scope');
        } catch (PDOException) {
            $this->workspaceMemberAccessScopeAvailable = false;
        }

        return $this->workspaceMemberAccessScopeAvailable;
    }

    private function workspaceMemberAssignedActorIsAvailable(): bool
    {
        if ($this->workspaceMemberAssignedActorAvailable !== null) {
            return $this->workspaceMemberAssignedActorAvailable;
        }

        try {
            $this->workspaceMemberAssignedActorAvailable = $this->columnExists('v2_workspace_members', 'assigned_actor_id');
        } catch (PDOException) {
            $this->workspaceMemberAssignedActorAvailable = false;
        }

        return $this->workspaceMemberAssignedActorAvailable;
    }

    private function workspaceAccessFromMemberRow(array $row): array
    {
        $role = (string)($row['member_role'] ?? '');
        $scope = $this->normalizedWorkspaceAccessScope($role, $row['member_access_scope'] ?? null);
        $assignedActorId = $row['member_assigned_actor_id'] ?? null;
        $assignedActorId = $assignedActorId === null || $assignedActorId === '' ? null : (string)$assignedActorId;
        $canWriteWorkspace = in_array($role, self::WORKSPACE_WRITER_ROLES, true);
        $canAdmin = in_array($role, self::WORKSPACE_ADMIN_ROLES, true);
        $canReadWorkspace = $scope === 'workspace';
        $canReadEntries = $canReadWorkspace || in_array($scope, ['own_entries', 'assigned_actor'], true);
        $canWrite = $canWriteWorkspace;

        return [
            'role' => $role,
            'role_label' => self::WORKSPACE_ROLE_LABELS[$role] ?? $role,
            'membership_status' => 'active',
            'access_scope' => $scope,
            'assigned_actor_id' => $assignedActorId,
            'can_read_workspace' => $canReadWorkspace,
            'can_read_entries' => $canReadEntries,
            'can_write_workspace' => $canWriteWorkspace,
            'can_write_scoped_entries' => $role === 'employee' && in_array($scope, ['own_entries', 'assigned_actor'], true),
            'can_write' => $canWrite,
            'can_admin' => $canAdmin,
        ];
    }

    private function normalizedWorkspaceAccessScope(string $role, mixed $rawScope): string
    {
        $scope = is_string($rawScope) && in_array($rawScope, self::WORKSPACE_ACCESS_SCOPES, true)
            ? $rawScope
            : null;

        if ($role === 'employee') {
            return in_array($scope, ['own_entries', 'assigned_actor', 'none'], true) ? $scope : 'own_entries';
        }

        return $scope ?? 'workspace';
    }

    private function entryVisibilitySql(string $alias, array $access, int $userId, string $error): array
    {
        if ($access['can_read_workspace']) {
            return ['sql' => null, 'params' => []];
        }

        if (!$access['can_read_entries']) {
            throw new FinDeskV2HttpError(403, $error);
        }

        if ($access['access_scope'] === 'assigned_actor') {
            $assignedActorId = $access['assigned_actor_id'];
            if ($assignedActorId !== null) {
                return [
                    'sql' => "({$alias}.created_by = ? OR {$alias}.actor_id = ?)",
                    'params' => [$userId, $assignedActorId],
                ];
            }
        }

        return [
            'sql' => "{$alias}.created_by = ?",
            'params' => [$userId],
        ];
    }

    private function assertEntryVisible(array $row, int $userId, string $error): void
    {
        $access = $this->workspaceAccess((string)$row['workspace_id'], $userId);
        if ($access['can_read_workspace']) {
            return;
        }
        if (!$access['can_read_entries']) {
            throw new FinDeskV2HttpError(404, $error);
        }

        $createdBy = $row['created_by'] ?? null;
        if ($createdBy !== null && (int)$createdBy === $userId) {
            return;
        }

        if ($access['access_scope'] === 'assigned_actor'
            && $access['assigned_actor_id'] !== null
            && isset($row['actor_id'])
            && (string)$row['actor_id'] === $access['assigned_actor_id']
        ) {
            return;
        }

        throw new FinDeskV2HttpError(404, $error);
    }

    private function normalizeAttachmentPayload(array $input): array
    {
        if (isset($input['file']) && is_array($input['file'])) {
            return $this->normalizeUploadedAttachment($input);
        }

        $fileName = $this->cleanAttachmentFileName(FinDeskV2Support::requireString($input, 'file_name', 255));
        $rawEncoded = trim((string)($input['content_base64'] ?? ''));
        if ($rawEncoded === '') {
            throw new FinDeskV2HttpError(422, 'missing_content_base64');
        }
        $encoded = preg_replace('/\s+/', '', $rawEncoded);
        if (!is_string($encoded) || $encoded === '' || str_contains($encoded, ',')) {
            throw new FinDeskV2HttpError(422, 'invalid_content_base64');
        }
        if (strlen($encoded) > (int)ceil(self::ATTACHMENT_MAX_BYTES * 1.4) + 16) {
            throw new FinDeskV2HttpError(413, 'attachment_too_large');
        }

        $content = base64_decode($encoded, true);
        if ($content === false) {
            throw new FinDeskV2HttpError(422, 'invalid_content_base64');
        }

        return $this->buildAttachmentPayload($fileName, $content, $input);
    }

    private function normalizeUploadedAttachment(array $input): array
    {
        $file = $input['file'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new FinDeskV2HttpError(422, 'invalid_attachment_upload');
        }
        if ((int)($file['size'] ?? 0) > self::ATTACHMENT_MAX_BYTES) {
            throw new FinDeskV2HttpError(413, 'attachment_too_large');
        }
        $tmpName = (string)($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new FinDeskV2HttpError(422, 'invalid_attachment_upload');
        }
        $content = file_get_contents($tmpName);
        if ($content === false) {
            throw new FinDeskV2HttpError(422, 'invalid_attachment_upload');
        }

        return $this->buildAttachmentPayload(
            $this->cleanAttachmentFileName((string)($file['name'] ?? 'attachment')),
            $content,
            $input
        );
    }

    private function buildAttachmentPayload(string $fileName, string $content, array $input): array
    {
        $size = strlen($content);
        if ($size <= 0) {
            throw new FinDeskV2HttpError(422, 'empty_attachment');
        }
        if ($size > self::ATTACHMENT_MAX_BYTES) {
            throw new FinDeskV2HttpError(413, 'attachment_too_large');
        }

        $mimeType = $this->detectAttachmentMime($content);
        $imageMode = FinDeskV2Support::optionalString($input, 'image_mode', null, 40);
        if ($imageMode !== null) {
            $imageMode = FinDeskV2Support::enum($imageMode, ['original', 'compressed', 'grayscale_scan'], 'image_mode');
        }

        return [
            'file_name' => $fileName,
            'content' => $content,
            'mime_type' => $mimeType,
            'size_bytes' => $size,
            'image_mode' => $imageMode,
        ];
    }

    private function detectAttachmentMime(string $content): string
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($content) ?: 'application/octet-stream';
        if ($mimeType === 'image/pjpeg') {
            $mimeType = 'image/jpeg';
        }
        if (!array_key_exists($mimeType, self::ATTACHMENT_ALLOWED_MIME_EXTENSIONS)) {
            throw new FinDeskV2HttpError(422, 'unsupported_attachment_type');
        }

        return $mimeType;
    }

    private function cleanAttachmentFileName(string $fileName): string
    {
        $fileName = trim($fileName);
        if ($fileName === '' || preg_match('/[\/\\\\\x00-\x1F\x7F]/u', $fileName) === 1) {
            throw new FinDeskV2HttpError(422, 'invalid_file_name');
        }

        return mb_substr($fileName, 0, 255);
    }

    private function attachmentWritePath(string $relativePath): string
    {
        $root = dirname(__DIR__, 2);
        if (!str_starts_with($relativePath, 'storage/v2/attachments/')) {
            throw new FinDeskV2HttpError(500, 'invalid_attachment_path');
        }
        $absolutePath = $root . '/' . $relativePath;
        $directory = dirname($absolutePath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new FinDeskV2HttpError(500, 'attachment_store_failed');
        }

        return $absolutePath;
    }

    private function deleteAttachmentFile(string $relativePath): bool
    {
        if (!str_starts_with($relativePath, 'storage/v2/attachments/')) {
            throw new FinDeskV2HttpError(500, 'invalid_attachment_path');
        }

        $root = dirname(__DIR__, 2);
        $storageRoot = $root . '/storage/v2/attachments';
        $storageReal = realpath($storageRoot);
        $fileReal = realpath($root . '/' . $relativePath);
        if ($fileReal === false) {
            return false;
        }
        if ($storageReal === false || !str_starts_with($fileReal, $storageReal . DIRECTORY_SEPARATOR)) {
            throw new FinDeskV2HttpError(500, 'invalid_attachment_path');
        }
        if (!is_file($fileReal)) {
            return false;
        }
        if (!unlink($fileReal)) {
            throw new FinDeskV2HttpError(500, 'attachment_delete_failed');
        }

        return true;
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

    private function createCategoryRuleInCurrentTransaction(string $workspaceId, array $input, int $userId): array
    {
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

        return $this->getCategoryRule($id, $workspaceId, $userId);
    }

    private function normalizeEntryInput(string $workspaceId, array $flow, array $input, bool $persistRelations = true): array
    {
        $rawText = FinDeskV2Support::requireString($input, 'raw_text', 2000);
        $sourceType = FinDeskV2Support::enum(
            FinDeskV2Support::optionalString($input, 'source_type', 'manual', 40) ?? 'manual',
            ['manual', 'import', 'assistant', 'correction', 'accountable_report'],
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
                } elseif ($sourceType === 'import') {
                    $entryType = 'card_income';
                    $status = 'imported';
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
        $effectiveCategoryCode = $categoryCode;
        if ($categoryCode !== null) {
            $categoryId = $this->categoryIdByCode($workspaceId, $categoryCode);
        } elseif ($sign !== null && !($flow['type'] === 'card' && $sign === '+' && $sourceType !== 'correction')) {
            $inferred = $this->inferEntrySemantics($rawText, $flow, $sign);
            if ($inferred['category_code'] !== null) {
                $effectiveCategoryCode = $inferred['category_code'];
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
            $matchedRules = $this->mergeSemanticMarkers($matchedRules, $this->inferSemanticMarkers($rawText, (string)$flow['type'], $sign, $effectiveCategoryCode));
            $actorName = $this->extractActorName($rawText);
            if ($actorName !== null && $persistRelations) {
                $actorId = $this->getOrCreateActor($workspaceId, $actorName);
            }
        }

        $finalStatus = $sign === null || ($flow['type'] === 'card' && $sign === '+' && !in_array($sourceType, ['correction', 'import'], true))
            ? $status
            : FinDeskV2Support::enum(
                FinDeskV2Support::optionalString($input, 'status', $status, 40) ?? $status,
                ['recognized', 'unrecognized', 'other_review', 'excluded', 'imported', 'assistant_pending', 'accepted', 'rejected', 'corrected', 'duplicate_suspect'],
                'status'
            );
        $classificationDecision = $sign === null
            ? null
            : $this->classificationDecision((string)$rawText, (string)$flow['type'], $sign, $effectiveCategoryCode, $finalStatus, $matchedRules);
        if ($classificationDecision !== null) {
            $matchedRules = $this->replaceClassificationDecision($matchedRules, $classificationDecision);
        }
        $confidence = FinDeskV2Support::nullableAmount($input['confidence'] ?? null);
        if ($confidence === null && $classificationDecision !== null) {
            $confidence = number_format((float)$classificationDecision['confidence'], 2, '.', '');
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
            'status' => $finalStatus,
            'source_type' => $sourceType,
            'source_id' => FinDeskV2Support::optionalString($input, 'source_id', null, 36),
            'source_row_id' => FinDeskV2Support::optionalString($input, 'source_row_id', null, 36),
            'notes' => FinDeskV2Support::optionalString($input, 'notes', null, 2000),
            'confidence' => $confidence,
            'matched_rules' => $matchedRules,
        ];
    }

    private function inferEntrySemantics(string $rawText, array $flow, string $sign): array
    {
        $text = mb_strtolower($rawText);
        $categoryCode = null;
        $status = null;
        $matchedRules = [];

        if (preg_match('/цоги\s*мар|цогимар|cogimar/u', $text) === 1) {
            $categoryCode = 'other';
            $status = 'other_review';
            $matchedRules[] = ['source' => 'fixture_review_override', 'pattern' => 'cogimar_review', 'category_code' => 'other'];
        } elseif (preg_match('/netflix|нетфликс|ivi|иви|starlink|старлинк|интернет|инет|интенрнет|сим.?карт|sonos|сонос|модем|роуминг|сайт[а-я]* клауди|домен|хостинг|платн[а-я]* погод|прогноз погод|прогнох погод|обновлен[а-я]* карт|hdmi|шнур[а-я]* телефон|чехол телефон/u', $text) === 1) {
            $categoryCode = 'media_comms';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'media_comms', 'category_code' => 'media_comms'];
        } elseif (preg_match('/заправ|топлив|fuel/u', $text) === 1 && preg_match('/авто|машин|car/u', $text) !== 1) {
            $categoryCode = 'fuel';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'fuel', 'category_code' => 'fuel'];
            if (preg_match('/тузик|tender/u', $text) === 1) {
                $matchedRules[] = ['source' => 'fixture_secondary_marker', 'marker' => 'tender_related'];
            }
        } elseif (preg_match('/выход в море|переход коринф|проход через коринф|tepai|такс[аы] по вход|марин|порт|паром/u', $text) === 1 && $sign === '-') {
            $categoryCode = 'marina_ports';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'marina_ports', 'category_code' => 'marina_ports'];
        } elseif (preg_match('/тур.?регистрац|тамож|дьюти|документ|печат[ьи]|налог|ндс|страхов|регистрац|юрист|адвокат|license|insurance|customs|виньет|лиценз|леценз|sanada|такса|такс[аы] банк перевод|траст компани|внж|крулист|crew.?list|виза|судебн[а-я]* перевод|открытие счета|обеспечение счета|берегов[а-я]* служб|морск[а-я]* сертиф[а-я]*|сертифиткат|разрешен[а-я]* на вход|флаг[а-я]* итали|флаг[а-я]* кайман|границ|просрочк[а-я]* нахождения/u', $text) === 1 && $sign === '-') {
            $categoryCode = 'admin_legal';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'admin_legal', 'category_code' => 'admin_legal'];
        } elseif (preg_match('/стоянк|зимовк|склад|гараж|электричеств|муринг/u', $text) === 1 && $sign === '-') {
            $categoryCode = 'berth';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'berth', 'category_code' => 'berth'];
        } elseif (preg_match('/сервис|обслуж|мастер|ремонт|репарац|механик|токарь|опреснител|спас.?плот|пересертифик|дайвер|водолаз|электрик|откачка серых вод|откачка черн[а-я]* танк|черн[а-я]* танк|откачк[а-я]* вод|откачк[а-я]* грязн[а-я]* вод|выкачк[а-я]* танк|замен|монтаж|варк|консервац|тест систем|огнетуш|(?:^|\s)то(?:\s|$)/u', $text) === 1 && $sign === '-') {
            $categoryCode = 'service_water';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'service_water', 'category_code' => 'service_water'];
        } elseif (preg_match('/аккум|кабел|cable|фильтр|запчаст|детал|инструмент|насос|мотор|батаре[яи]|батарейк|сантехник|подрульк|пордрульк|лебедк|смазк[а-я]* для лебед|компрессор|диммер|гелькоут|кранц|кранец|швартов|веревк|регулятор давления|контрольк|конде[яй]?|подгонк[а-я]*.*контрол[её]к.*кондиц|безопа[сст]+ност[а-я]* плаван|материал[а-я]* по тику|пропитк[аеи]? тик[а]?|расодники? тик|для тика|тик.?клинер|тик.?силер|тик.?вандер|силер для платформы|средств[ао] для тика|очистител[ья]* тика|пятновыводител[ья]* тик|дезинфектор тик|обработк[а-я]* тика|щетк[а-я]*.*тик|тик.*щетк|блок управления туалет|петл[яи].*(?:холодильн|хододильн)|амортизатор[а-я]*.*люк|люк[иа].*танк|датчик.*танк|ролик[а-я]* цепи|маркер[а-я]* цепи|подстаканник|экран на флай|кругов[а-я]* огонь|фонар[а-я]* на корм|плоттер|навионикс|навион|удлинитель|хомут|адаптер|болт|крепеж|крепеж[а-я]* гайк|втулк[а-я]* под стапел|строительн[а-я]* фен|мультиметр|предохранитель|сикафлекс|sikaflex|шарнир[а-я]*|шуруп[а-я]*|чертеж[а-я]* для 3д/u', $text) === 1) {
            $categoryCode = 'tech_parts';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'tech_parts', 'category_code' => 'tech_parts'];
        } elseif (preg_match('/айфон|iphone|самокат|скутер|параплан|музыкант|прогулк[а-я]* гост|нац парк|вход в музей|снаст|зарядк[а-я]* шефу|маски$|маски ласты|подводн[а-я]* маск|перья на сап|весло сап|набор для ныряния|отел[ьяеи]?|гостиниц/u', $text) === 1 && $sign === '-') {
            $categoryCode = 'guest_trip_support';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'guest_trip_support', 'category_code' => 'guest_trip_support'];
        } elseif (preg_match('/^(?:[+-]?\s*\d+(?:[.,]\d+)?\s+)?(?:лв|леонид владимирович)$|расходы лв|общая потраченная сумма лв|игра лв|(?:передал|отдал|дал|выдал)\s+(?:лв|леонид владимирович|арику?|саше?|гост)/u', $text) === 1 && $sign === '-') {
            $categoryCode = 'guest_cash_issued';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'guest_cash_issued', 'category_code' => 'guest_cash_issued'];
        } elseif (preg_match('/представительск|подарок|подарк[а-я]* служб|презент|розы|сувенир|украшен[а-я]* др|делов\p{L}*[^,.;]*(обед|ужин|встреч)|(?:обед|ужин|ланч|встреча)[^,.;]*(?:\sс\s|\sдля\s)[\p{L}]|hospitality|business lunch|business dinner|lunch with|dinner with/u', $text) === 1 && $sign === '-') {
            $categoryCode = 'representation_expenses';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'representation_expenses', 'category_code' => 'representation_expenses'];
        } elseif (preg_match('/брендир|(?:^|[\s-])форм[а-я]*|одежд[аы]? экипаж|спец.?одеж|спецодеж|агент|магазин|хоз.?товар|принтер|(?:^|\s)инвентарь(?!\s+по\s+кухне)(?:\s|$)|банковск[а-я]* перевод|комисси[яи] банк|банковск[а-я]* комисс|банковск[а-я]* процент[а-я]*.*перевод|забрал свои|bank fee|bank commission/u', $text) === 1 && $sign === '-') {
            $categoryCode = 'current_boat_expenses';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'current_boat_expenses', 'category_code' => 'current_boat_expenses'];
        } elseif (preg_match('/хим|мойк|моющ[а-я]* средств[а-я]*|салф|тряпк|пена|полиров|уборк|химчист|clean|laundry|detergent|прачк|прачеч|полирол|пенообразователь|керхер|мусор|вывоз мусора|отбеливател|плесен|грибк|распылител|щетк[а-я]*(?: для лодк)?/u', $text) === 1 && $sign === '-') {
            $categoryCode = 'cleaning';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'cleaning', 'category_code' => 'cleaning'];
        } elseif (preg_match('/такси|трансфер|аренда авто|арендованн[а-я]* авто|рентакар|билеты?|перел[её]т|авиа|поезд|автобус|самол[её]т|air serbia|логистик|забрал гостей|дорожн[а-я]* расход|запра[вк][а-я]* авто|парковк|курьер|доставк|почт[а-я]* в сербию|велосипед[а-я]* млет|перевозк[а-я]* гидроцикл|taxi|transfer|car rental|tickets|delivery/u', $text) === 1 && $sign === '-') {
            $categoryCode = 'transport_expenses';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'transport_expenses', 'category_code' => 'transport_expenses'];
        } elseif (preg_match('/продукт|продуукт|рыб|стейк|мяс|баранин|хлеб|фрукт|овощ|напит|вино|пиво|кола|сок|сироп|сладост|коктел|коктейл|устриц|скамп|шкамп|краб|кальмар|лангустин|осминог|лосось|тунец|салмон|сыр|морож|инжир|яйц|орех|мед|соус|острог|перекус|еда|ресторан|кафе|алкоголь|виски|водк|шампан|грей.?гус|моет|moet|вдова клико|аберлоу|ликер|кофе(?![\\s-]?машин)|холодн[а-я]* чай|рынок|клубник|монтефиш|обед|докупк[а-я]* необходим[а-я]* в поход|закупк[а-я]* в поход|косметик|гигиен|шампун|аптечк|аптек|лекарств|(?:^|\s)вода(?!\s+электричеств)(?:\s|$)|вода (?:на|в) лодк/u', $text) === 1 && $sign === '-') {
            $categoryCode = 'provisions';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'provisions', 'category_code' => 'provisions'];
        } elseif (preg_match('/кухонн[^,.;]*принадлежн|кухонн[а-я]* расход|инвентарь по кухне|кухн[а-я]*.*интерьер|кухн[а-я]*.*обновлен|утварь.*кухн|перешив.*подуш|подушк|чехл|шезлонг|кофе[\\s-]?машин|кофемашин|блендер|соковыжималк|микроволновк|печка|капучинатор|графин|пепельниц|жалюзи|одеял|наволочк|плед|комплект постельн|мешк[иа]|контейнер|замк[иа] на дверц|на кухню/u', $text) === 1 && $sign === '-') {
            $categoryCode = 'interior';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'interior', 'category_code' => 'interior'];
        } elseif (preg_match('/цветы/u', $text) === 1 && preg_match('/розы|подарок|презент/u', $text) !== 1 && $sign === '-') {
            $categoryCode = 'provisions';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'flowers_provisions', 'category_code' => 'provisions'];
        } elseif ($sign === '+' && $flow['type'] === 'cash' && $this->isAccountableCashAlignmentText($text)) {
            $matchedRules[] = [
                'source' => 'semantic_marker',
                'marker' => 'money_movement',
                'pattern' => 'accountable_cash_alignment',
                'rule' => 'accountable cash return/alignment changes cash balance but is not owner funding income',
            ];
        } elseif ($this->isCommercialIncomeText($text) && $sign === '+') {
            $categoryCode = 'commercial_income';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'commercial_income', 'category_code' => 'commercial_income'];
        } elseif ($sign === '+' && $flow['type'] === 'cash' && !$this->isUnclearCommercialIncomeText($text, $sign)) {
            $categoryCode = 'non_commercial_income';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'non_commercial_income', 'category_code' => 'non_commercial_income'];
        } elseif ($sign === '-' && (preg_match('/аванс|чаев/u', $text) === 1 && ($this->extractActorName($rawText) !== null || str_contains($text, 'чаев')) || preg_match('/работник в помощь/u', $text) === 1)) {
            $categoryCode = 'crew';
            $matchedRules[] = ['source' => 'fixture_keyword', 'pattern' => 'crew_payment', 'category_code' => 'crew'];
        } elseif (
            preg_match('/какая-то штука|kakaya|\b(other|unknown|unclear|misc)(?:[_ -]?expense)?\b|проче|друго|неизвест|планшет|обезналич|консьерж|книжк[а-я]* моряка|подставк[а-я]* под динги/u', $text) === 1
            && $flow['type'] === 'cash'
            && $sign === '-'
        ) {
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

    private function classificationDecision(string $rawText, string $flowType, string $sign, ?string $categoryCode, string $status, array $matchedRules): array
    {
        $text = $this->normalizedRuleText($rawText);
        $semanticMarkers = $this->semanticMarkersFromRules($matchedRules);
        $markerNames = array_fill_keys(array_map(static fn (array $marker): string => (string)($marker['marker'] ?? ''), $semanticMarkers), true);
        $matchedSignals = $this->classificationMatchedSignals($matchedRules);
        $blockers = [];
        $reviewReason = null;
        $confidence = 0.92;

        if (isset($markerNames['non_yacht_or_personal'])) {
            $blockers[] = 'non_yacht_or_personal';
            $reviewReason = 'blocked_by_personal';
            $confidence = 0.20;
        } elseif (isset($markerNames['debt_or_return'])) {
            $blockers[] = 'debt_or_return';
            $reviewReason = 'blocked_by_debt';
            $confidence = 0.20;
        } elseif (isset($markerNames['money_movement'])) {
            $blockers[] = 'money_movement';
            $reviewReason = 'private_money_movement';
            $confidence = 0.20;
        } elseif ($this->isUnclearCommercialIncomeText($text, $sign)) {
            $blockers[] = 'missing_yacht_charter_phrase';
            $reviewReason = 'commercial_income_unclear';
            $confidence = 0.30;
        } elseif (isset($markerNames['mixed_dictionary_context'])) {
            $reviewReason = 'mixed_context';
            $confidence = 0.64;
        } elseif (isset($markerNames['weak_dictionary_context'])) {
            $reviewReason = 'weak_only';
            $confidence = 0.48;
        } elseif ($status === 'other_review' || $categoryCode === 'other') {
            $reviewReason = 'other_review';
            $confidence = 0.30;
        } elseif ($categoryCode === null && !isset($markerNames['owner_funding']) && !isset($markerNames['cash_location_safe'])) {
            $reviewReason = 'no_category';
            $confidence = 0.20;
        }

        if ($flowType === 'card' && $sign === '+') {
            $blockers[] = 'card_income_manual_guard';
            if ($status === 'unrecognized') {
                $reviewReason = 'card_income_not_allowed';
                $confidence = 0.10;
            }
        }

        return [
            'source' => 'classification_decision',
            'category_code' => $categoryCode,
            'confidence' => round($confidence, 2),
            'review_reason' => $reviewReason,
            'matched_signals' => $matchedSignals,
            'blockers' => array_values(array_unique($blockers)),
        ];
    }

    private function classificationMatchedSignals(array $matchedRules): array
    {
        $signals = [];
        foreach ($matchedRules as $rule) {
            if (($rule['source'] ?? null) === 'classification_decision') {
                continue;
            }
            if (isset($rule['category_code'])) {
                $signals[] = [
                    'type' => 'category',
                    'category_code' => (string)$rule['category_code'],
                    'pattern' => (string)($rule['pattern'] ?? ''),
                    'source' => (string)($rule['source'] ?? 'rule'),
                ];
                continue;
            }
            if (isset($rule['marker'])) {
                $signals[] = [
                    'type' => 'semantic_marker',
                    'marker' => (string)$rule['marker'],
                    'pattern' => (string)($rule['pattern'] ?? ''),
                    'source' => (string)($rule['source'] ?? 'rule'),
                ];
            }
        }

        return $signals;
    }

    private function replaceClassificationDecision(array $matchedRules, array $classificationDecision): array
    {
        $filtered = array_values(array_filter($matchedRules, static fn (array $rule): bool => ($rule['source'] ?? null) !== 'classification_decision'));
        $filtered[] = $classificationDecision;

        return $filtered;
    }

    private function classificationDecisionFromRules(array $matchedRules): ?array
    {
        foreach (array_reverse($matchedRules) as $rule) {
            if (($rule['source'] ?? null) !== 'classification_decision') {
                continue;
            }

            return $this->classificationDecisionPublic($rule);
        }

        return null;
    }

    private function classificationDecisionPublic(?array $decision): ?array
    {
        if ($decision === null) {
            return null;
        }

        return [
            'category_code' => $decision['category_code'] ?? null,
            'confidence' => isset($decision['confidence']) ? (float)$decision['confidence'] : null,
            'review_reason' => $decision['review_reason'] ?? null,
            'matched_signals' => is_array($decision['matched_signals'] ?? null) ? $decision['matched_signals'] : [],
            'blockers' => is_array($decision['blockers'] ?? null) ? $decision['blockers'] : [],
        ];
    }

    private function isCommercialIncomeText(string $normalizedText): bool
    {
        return preg_match('/чартер|оплата чартера|аренд[^,.;]*яхт|ареда яхты|яхт[^,.;]*аренд|сдач[аеи]?[^,.;]*яхт|charter|yacht[^,.;]*(rental|booking)/u', $normalizedText) === 1;
    }

    private function isUnclearCommercialIncomeText(string $normalizedText, string $sign): bool
    {
        return $sign === '+'
            && !$this->isCommercialIncomeText($normalizedText)
            && preg_match('/комисси|агентск|брокерск|agency fee|brokerage|commission|booking/u', $normalizedText) === 1;
    }

    private function dictionaryTrainingDecisionType(array $input): string
    {
        $raw = FinDeskV2Support::optionalString($input, 'decision_type', null, 80)
            ?? FinDeskV2Support::optionalString($input, 'decision', null, 80);
        if ($raw === null) {
            throw new FinDeskV2HttpError(422, 'missing_decision_type');
        }

        $aliases = [
            'accept' => 'approve_existing_guess_local',
            'reject' => 'reject_training',
            'skip' => 'defer',
        ];
        $raw = $aliases[$raw] ?? $raw;
        if ($raw === 'promote_universal') {
            throw new FinDeskV2HttpError(422, 'universal_promotion_not_supported');
        }

        return FinDeskV2Support::enum($raw, [
            'defer',
            'reject_training',
            'approve_existing_guess_local',
            'correct_category_local',
            'mark_semantic_blocked',
            'propose_universal_candidate',
        ], 'decision_type');
    }

    private function dictionaryTrainingStringList($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            $item = trim((string)$item);
            if ($item !== '') {
                $items[] = mb_substr($item, 0, 190);
            }
        }

        return array_values(array_unique($items));
    }

    private function assertDictionaryTrainingRuleAllowed(?string $reviewReason, array $blockers): void
    {
        if ($blockers !== []) {
            throw new FinDeskV2HttpError(422, 'dictionary_training_blocked');
        }
        if (in_array($reviewReason, [
            'blocked_by_personal',
            'blocked_by_debt',
            'private_money_movement',
            'commercial_income_unclear',
            'card_income_not_allowed',
        ], true)) {
            throw new FinDeskV2HttpError(422, 'dictionary_training_blocked');
        }
    }

    private function dictionaryTrainingExistingRuleMatches(array $decision, array $ruleInput, string $workspaceId, int $userId): bool
    {
        if (($decision['category_rule_id'] ?? null) === null) {
            return false;
        }

        $rule = $this->getCategoryRule((string)$decision['category_rule_id'], $workspaceId, $userId);

        return ($rule['category_code'] ?? null) === ($ruleInput['category_code'] ?? null)
            && ($rule['pattern'] ?? null) === ($ruleInput['pattern'] ?? null)
            && ($rule['pattern_type'] ?? null) === ($ruleInput['pattern_type'] ?? null)
            && ($rule['language'] ?? null) === ($ruleInput['language'] ?? null)
            && (int)($rule['weight'] ?? 0) === (int)($ruleInput['weight'] ?? 0)
            && (int)($rule['negative_weight'] ?? 0) === (int)($ruleInput['negative_weight'] ?? 0)
            && ($rule['requires_any'] ?? []) === ($ruleInput['requires_any'] ?? [])
            && ($rule['excludes_any'] ?? []) === ($ruleInput['excludes_any'] ?? []);
    }

    private function dictionaryTrainingDecisionRawBySourceRow(string $workspaceId, string $sourceRowId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT d.*, c.code AS category_code
            FROM v2_dictionary_training_decisions d
            LEFT JOIN v2_categories c ON c.id = d.category_id
            WHERE d.workspace_id = ? AND d.source_row_id = ?
            LIMIT 1
        ");
        $stmt->execute([$workspaceId, $sourceRowId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function dictionaryTrainingDecisionById(string $decisionId, string $workspaceId): array
    {
        $stmt = $this->db->prepare("
            SELECT d.*, c.code AS category_code
            FROM v2_dictionary_training_decisions d
            LEFT JOIN v2_categories c ON c.id = d.category_id
            WHERE d.id = ? AND d.workspace_id = ?
            LIMIT 1
        ");
        $stmt->execute([$decisionId, $workspaceId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new FinDeskV2HttpError(404, 'dictionary_training_decision_not_found');
        }

        return $row;
    }

    private function dictionaryTrainingSourceRow(string $archiveWorkspaceId, string $sourceRowId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                s.id AS source_id,
                s.file_name,
                s.workspace_id,
                r.id AS source_row_id,
                r.sheet_name,
                r.row_number,
                r.raw_json,
                r.parse_status,
                r.parse_notes
            FROM v2_import_rows r
            INNER JOIN v2_import_sources s ON s.id = r.import_source_id
            WHERE r.id = ?
              AND s.workspace_id = ?
              AND s.include_decision = 'included'
            LIMIT 1
        ");
        $stmt->execute([$sourceRowId, $archiveWorkspaceId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new FinDeskV2HttpError(404, 'dictionary_source_row_not_found');
        }

        return $row;
    }

    private function dictionaryTrainingSnapshotFromSourceRow(array $row): array
    {
        $raw = FinDeskV2Support::jsonDecode($row['raw_json'] ?? null, []);
        if (!is_array($raw)) {
            $raw = [];
        }
        $description = $this->dictionaryDescription($raw);
        $money = $this->dictionaryMoney($raw);
        $guess = $money === null ? ['category_code' => null, 'pattern' => null] : $this->dictionaryCategoryGuess($description, $money['flow_type'], $money['sign']);
        $rawText = trim(($money['sign'] ?? '') . ($money === null ? '' : number_format((float)$money['amount'], 2, '.', '')) . ' ' . $description);
        $markers = $money === null ? [] : $this->semanticMarkersFromRules(
            $this->inferSemanticMarkers($rawText, (string)$money['flow_type'], (string)$money['sign'], $guess['category_code'])
        );

        return $this->dictionaryReviewExample($row, $raw, $description, $money, $guess, $markers);
    }

    private function dictionaryArchiveWorkspace(array $workspace, int $userId): array
    {
        $name = (string)$workspace['name'];
        if (str_ends_with($name, ' Archive Raw History')) {
            return $workspace;
        }

        $stmt = $this->db->prepare("
            SELECT w.*
            FROM v2_workspaces w
            INNER JOIN v2_workspace_members m ON m.workspace_id = w.id
            WHERE w.name = ?
              AND m.user_id = ?
              AND w.archived_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$name . ' Archive Raw History', $userId]);
        $row = $stmt->fetch();

        return $row ? $this->workspaceRow($row) : $workspace;
    }

    private function dictionaryDescription(array $raw): string
    {
        return trim((string)($raw['описание платежа'] ?? $raw['description'] ?? $raw['описание'] ?? ''));
    }

    /** @return array{flow_type: string, sign: string, amount: float}|null */
    private function dictionaryMoney(array $raw): ?array
    {
        $amounts = [
            ['flow_type' => 'cash', 'sign' => '+', 'amount' => $this->dictionaryAmount($raw['приход кеш'] ?? $raw['приход кэш'] ?? $raw['cash income'] ?? $raw['приход'] ?? null)],
            ['flow_type' => 'cash', 'sign' => '-', 'amount' => $this->dictionaryAmount($raw['расход кеш'] ?? $raw['расход кэш'] ?? $raw['cash expense'] ?? $raw['расход'] ?? null)],
            ['flow_type' => 'card', 'sign' => '+', 'amount' => $this->dictionaryAmount($raw['приход карта'] ?? $raw['приход карты'] ?? $raw['card income'] ?? null)],
            ['flow_type' => 'card', 'sign' => '-', 'amount' => $this->dictionaryAmount($raw['расход карта'] ?? $raw['расход карты'] ?? $raw['card expense'] ?? null)],
        ];
        $nonZero = array_values(array_filter($amounts, static fn (array $item): bool => $item['amount'] !== null && abs((float)$item['amount']) > 0.0001));
        if (count($nonZero) !== 1) {
            return null;
        }

        return $nonZero[0];
    }

    private function dictionaryAmount($value): ?float
    {
        $text = trim((string)$value);
        if ($text === '') {
            return null;
        }
        $normalized = str_replace([' ', "\xc2\xa0"], '', $text);
        $normalized = str_replace(',', '.', $normalized);

        return is_numeric($normalized) ? abs((float)$normalized) : null;
    }

    /** @return array{category_code: ?string, pattern: ?string} */
    private function dictionaryCategoryGuess(string $description, string $flowType, string $sign): array
    {
        $text = $this->normalizedRuleText($description);
        if (preg_match('/цоги\s*мар|цогимар|cogimar/u', $text) === 1) {
            return ['category_code' => null, 'pattern' => null];
        }
        foreach (self::DICTIONARY_CATEGORY_RULES as $code => $pattern) {
            if (preg_match($pattern, $text) !== 1) {
                continue;
            }
            if ($code === 'fuel' && preg_match('/авто|машин|car/u', $text) === 1) {
                continue;
            }
            if ($sign === '+' && !in_array($code, ['commercial_income', 'cash_topup_from_card'], true)) {
                continue;
            }
            if ($flowType === 'card' && $sign === '+' && $code !== 'cash_topup_from_card') {
                continue;
            }

            return ['category_code' => $code, 'pattern' => $pattern];
        }

        if (
            $flowType === 'cash'
            && $sign === '+'
            && !$this->isAccountableCashAlignmentText($text)
            && !$this->isUnclearCommercialIncomeText($text, $sign)
        ) {
            return ['category_code' => 'non_commercial_income', 'pattern' => 'non_commercial_income'];
        }

        return ['category_code' => null, 'pattern' => null];
    }

    private function isAccountableCashAlignmentText(string $normalizedText): bool
    {
        $hasAccountableContext = preg_match('/под ?отчет|подотчет|пот отчет|accountable/u', $normalizedText) === 1;
        $hasReturnOrBalanceContext = preg_match('/возврат|вернул|вернула|вернули|остаток|недорасход|cash balance alignment|alignment/u', $normalizedText) === 1;

        return $hasAccountableContext && $hasReturnOrBalanceContext;
    }

    private function dictionaryNeedsReviewOverride(string $description): bool
    {
        $text = $this->normalizedRuleText($description);
        if ($text === '') {
            return false;
        }

        return preg_match('/цоги\s*мар|цогимар|cogimar|долг|возврат|вернул|под ?отчет|подотчет|пот отчет|кредит|займ|заем|рассрочк|порше|porsche|для рф|катер рф|для себя|brokerage|agency fee|сим[^,.;]*фрукт|фрукт[^,.;]*сим|тендер[^,.;]*остаток|остаток[^,.;]*тендер|планшет|обезналич|консьерж|книжк[а-я]* моряка|подставк[а-я]* под динги/u', $text) === 1;
    }

    private function dictionaryReviewGroup(array $markers, ?string $categoryCode, bool $needsReview): array
    {
        $markerLabels = [
            'cash_location_safe' => 'Safe / cash location',
            'actor_context' => 'Actor / source context',
            'owner_funding' => 'Owner funding',
            'money_movement' => 'Money movement / private settlement',
            'debt_or_return' => 'Debt / loan / credit',
            'non_yacht_or_personal' => 'Non-yacht / personal context',
            'commercial_income_allowed' => 'Commercial income allowed',
            'tender_related' => 'Tender related',
            'weak_dictionary_context' => 'Weak dictionary context',
            'mixed_dictionary_context' => 'Mixed dictionary context',
        ];
        $seenMarkers = array_fill_keys(array_map(static fn (array $marker): string => (string)($marker['marker'] ?? ''), $markers), true);
        foreach (array_keys($markerLabels) as $marker) {
            if (isset($seenMarkers[$marker])) {
                return [
                    'key' => 'semantic:' . $marker,
                    'kind' => 'semantic',
                    'label' => $markerLabels[$marker],
                    'semantic_markers' => [$marker],
                    'current_rule_guess' => $categoryCode,
                    'needs_review' => $needsReview,
                ];
            }
        }

        if ($categoryCode !== null) {
            return [
                'key' => 'category:' . $categoryCode,
                'kind' => 'category_guess',
                'label' => 'Category guess: ' . $categoryCode,
                'semantic_markers' => [],
                'current_rule_guess' => $categoryCode,
                'needs_review' => $needsReview,
            ];
        }

        return [
            'key' => 'review:needs_review',
            'kind' => 'review',
            'label' => 'Needs review',
            'semantic_markers' => [],
            'current_rule_guess' => null,
            'needs_review' => true,
        ];
    }

    private function dictionaryReviewExample(array $row, array $raw, string $description, ?array $money, array $guess, array $markers): array
    {
        $matchedRules = [];
        if (($guess['category_code'] ?? null) !== null) {
            $matchedRules[] = [
                'source' => 'dictionary_guess',
                'pattern' => (string)($guess['pattern'] ?? ''),
                'category_code' => (string)$guess['category_code'],
            ];
        }
        foreach ($markers as $marker) {
            if (is_array($marker)) {
                $matchedRules[] = $marker;
            }
        }

        $classificationDecision = null;
        if ($money !== null && isset($money['flow_type'], $money['sign'])) {
            $status = ($guess['category_code'] ?? null) === null ? 'other_review' : 'recognized';
            $rawText = trim((string)$money['sign'] . (string)($money['amount'] ?? '') . ' ' . $description);
            $classificationDecision = $this->classificationDecisionPublic($this->classificationDecision(
                $rawText,
                (string)$money['flow_type'],
                (string)$money['sign'],
                $guess['category_code'] ?? null,
                $status,
                $matchedRules
            ));
        }

        return [
            'description' => $description,
            'flow_type' => $money['flow_type'] ?? null,
            'sign' => $money['sign'] ?? null,
            'amount' => $money['amount'] ?? null,
            'parse_status' => (string)$row['parse_status'],
            'parse_notes' => $row['parse_notes'] ?? null,
            'date_context' => $raw['_date_context'] ?? null,
            'current_rule_guess' => $guess['category_code'] ?? null,
            'matched_pattern' => $guess['pattern'] ?? null,
            'semantic_markers' => $markers,
            'confidence' => $classificationDecision['confidence'] ?? null,
            'review_reason' => $classificationDecision['review_reason'] ?? null,
            'matched_signals' => $classificationDecision['matched_signals'] ?? [],
            'blockers' => $classificationDecision['blockers'] ?? [],
            'classification_decision' => $classificationDecision,
            'source' => [
                'source_id' => (string)$row['source_id'],
                'source_row_id' => (string)$row['source_row_id'],
                'file_name' => (string)$row['file_name'],
                'sheet_name' => (string)$row['sheet_name'],
                'row_number' => (int)$row['row_number'],
            ],
            'raw' => $raw,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function inferSemanticMarkers(string $rawText, string $flowType, string $sign, ?string $categoryCode): array
    {
        $text = $this->normalizedRuleText($rawText);
        $markers = [];

        if (preg_match('/сейф|сеф|из сейфа|из сефа|в сейф|в сеф|взял из сейфа|забрал из сейфа|получено из сейфа|получил из сейфа|принял из сейфа|убрал в сейф|положил в сейф|принял сейфы/u', $text) === 1) {
            $markers[] = [
                'source' => 'semantic_marker',
                'marker' => 'cash_location_safe',
                'pattern' => 'сейф',
                'rule' => 'safe is cash source/location context, not category or commercial income',
            ];
        }

        if (preg_match('/долг|возврат|вернул|под ?отчет|подотчет|пот отчет|кредит|займ|заем|рассрочк/u', $text) === 1) {
            $markers[] = [
                'source' => 'semantic_marker',
                'marker' => 'debt_or_return',
                'pattern' => 'debt_or_return',
                'rule' => 'debt/return/accountable marker, not category by itself',
            ];
        }

        if (preg_match('/остались на карте.*сдал|оплатил с карты для себя|вернул в кеш кассу|свои нужды.*карты.*кеш|карты.*свои нужды.*кеш|пр[еe]вод со счета на карту|перевод со счета на карту/u', $text) === 1) {
            $markers[] = [
                'source' => 'semantic_marker',
                'marker' => 'money_movement',
                'pattern' => 'money_movement_private_settlement',
                'rule' => 'card/cash/private settlement wording is movement/review context, not expense category by itself',
            ];
        }

        if ($sign === '+' && $flowType === 'cash' && $this->isAccountableCashAlignmentText($text)) {
            $markers[] = [
                'source' => 'semantic_marker',
                'marker' => 'money_movement',
                'pattern' => 'accountable_cash_alignment',
                'rule' => 'accountable cash return/alignment changes cash balance but is not owner funding income',
            ];
        }

        if (preg_match('/порше|porsche|для рф|отправк[а-я]* в рф|катер рф|аудио система для рф|музыка на катер рф|мото навигатор/u', $text) === 1) {
            $markers[] = [
                'source' => 'semantic_marker',
                'marker' => 'non_yacht_or_personal',
                'pattern' => 'non_yacht_or_personal',
                'rule' => 'personal or non-yacht context must not train yacht operational categories',
            ];
        }

        $hasActorContext = preg_match('/\bлв\b|леонид владимирович|александр|александра|\bсаша\b|саше\b|олег\b|вова\b|володя\b|натали|наталь|арик|арика|данил/u', $text) === 1
            && ($sign === '-' || preg_match('/расход|игра|передал|отдал|дал|перев[её]л|для друга|для лв/u', $text) === 1);
        if ($hasActorContext) {
            $markers[] = [
                'source' => 'semantic_marker',
                'marker' => 'actor_context',
                'pattern' => 'actor_source_context',
                'rule' => 'actor/source names are context markers and must not force category or owner funding',
            ];
        }

        if (preg_match('/тузик|тендер|tender|dinghy|williams/u', $text) === 1) {
            $markers[] = [
                'source' => 'semantic_marker',
                'marker' => 'tender_related',
                'pattern' => 'tender_related',
            ];
        }

        if ($sign === '-' && $this->isWeakDictionaryContext($text)) {
            $markers[] = [
                'source' => 'semantic_marker',
                'marker' => 'weak_dictionary_context',
                'pattern' => 'weak_category_word',
                'rule' => 'weak generic words can suggest a category but must not train universal rules alone',
            ];
        }

        if ($sign === '-' && $this->isMixedDictionaryContext($text)) {
            $markers[] = [
                'source' => 'semantic_marker',
                'marker' => 'mixed_dictionary_context',
                'pattern' => 'mixed_category_context',
                'rule' => 'mixed category signals need review or explicit dominance rule before training',
            ];
        }

        $isCommercialIncome = $sign === '+' && $this->isCommercialIncomeText($text);
        if ($isCommercialIncome) {
            $markers[] = [
                'source' => 'semantic_marker',
                'marker' => 'commercial_income_allowed',
                'pattern' => 'commercial_yacht_income',
            ];
        }

        $isAccountableCashAlignment = $sign === '+' && $flowType === 'cash' && $this->isAccountableCashAlignmentText($text);
        $isCardToCash = $categoryCode === 'cash_topup_from_card'
            || preg_match('/снял кеш|снял с карты|снятие с карты|cash topup|topup from card|card to cash|банкомат|atm/u', $text) === 1;
        if ($sign === '+' && !$isCommercialIncome && !$isCardToCash && !$isAccountableCashAlignment && !$hasActorContext) {
            $source = $this->ownerFundingSource($text);
            $marker = [
                'source' => 'semantic_marker',
                'marker' => 'owner_funding',
                'pattern' => 'non_commercial_income',
                'rule' => 'income without explicit yacht rental/charter wording is owner funding',
            ];
            if ($source !== null) {
                $marker += $source;
            }
            $markers[] = $marker;
        }

        return $markers;
    }

    /** @return array{source_actor?: string, source_label?: string}|null */
    private function ownerFundingSource(string $normalizedText): ?array
    {
        if (preg_match('/александр|александра|от саши|\bсаша\b/u', $normalizedText) === 1) {
            return ['source_actor' => 'Александр'];
        }
        if (preg_match('/натали|наталь/u', $normalizedText) === 1) {
            return ['source_actor' => 'Наталия'];
        }
        if (preg_match('/арик|арика/u', $normalizedText) === 1) {
            return ['source_actor' => 'Арик'];
        }
        if (preg_match('/\bлв\b|леонид владимирович/u', $normalizedText) === 1) {
            return ['source_actor' => 'Леонид Владимирович'];
        }
        if (preg_match('/данил/u', $normalizedText) === 1) {
            return ['source_actor' => 'Данил'];
        }
        if (preg_match('/крипт|usdt|усдт/u', $normalizedText) === 1) {
            return ['source_label' => 'crypto'];
        }
        if (preg_match('/германи/u', $normalizedText) === 1) {
            return ['source_label' => 'Germany'];
        }
        if (preg_match('/\bрф\b|росси/u', $normalizedText) === 1) {
            return ['source_label' => 'Russia'];
        }
        if (preg_match('/служебн.*карт/u', $normalizedText) === 1) {
            return ['source_label' => 'service card'];
        }

        return null;
    }

    private function isWeakDictionaryContext(string $normalizedText): bool
    {
        if (preg_match('/представительск|подарок|подарк[а-я]* служб|презент|розы|сувенир|украшен[а-я]* др|делов\p{L}*[^,.;]*(обед|ужин|встреч)|(?:обед|ужин|ланч|встреча)[^,.;]*(?:\sс\s|\sдля\s)[\p{L}]|hospitality|business lunch|business dinner|lunch with|dinner with/u', $normalizedText) === 1) {
            return false;
        }

        if (preg_match('/(?:^|\s)(магазин|агент|доставк[а-я]*|курьер|обед|кафе|ресторан|инвентарь)(?:\s|$)/u', $normalizedText) !== 1) {
            return false;
        }

        return preg_match('/подарок|презент|делов|встреч|такси|трансфер|билет|авто|запчаст|кабел|фильтр|насос|кухн|лодк|гост|поход|рыб|мяс|продукт|банк|комисси/u', $normalizedText) !== 1;
    }

    private function isMixedDictionaryContext(string $normalizedText): bool
    {
        $signals = 0;
        foreach ([
            '/такси|трансфер|билет|авто|доставк|курьер/u',
            '/подарок|презент|розы|сувенир|делов|встреч/u',
            '/продукт|рыб|мяс|вода(?!\s+электричеств)|аптек|ресторан|кафе/u',
            '/запчаст|кабел|фильтр|насос|контрольк|конде/u',
            '/сервис|ремонт|замен|монтаж|мастер/u',
            '/долг|возврат|вернул|под ?отчет|кредит|займ|заем/u',
            '/порше|porsche|для рф|катер рф|для себя/u',
        ] as $pattern) {
            if (preg_match($pattern, $normalizedText) === 1) {
                $signals++;
            }
            if ($signals > 1) {
                return true;
            }
        }

        return false;
    }

    private function normalizedRuleText(string $text): string
    {
        $text = mb_strtolower($text);
        $text = str_replace('ё', 'е', $text);
        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }

    /** @param array<int, array<string, mixed>> $markers */
    private function semanticMarkerArrayHas(array $markers, string $marker): bool
    {
        foreach ($markers as $item) {
            if (($item['marker'] ?? null) === $marker) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, array<string, mixed>> $rules */
    private function mergeSemanticMarkers(array $rules, array $markers): array
    {
        $seen = [];
        foreach ($rules as $rule) {
            if (isset($rule['marker'])) {
                $seen[(string)$rule['marker']] = true;
            }
        }
        foreach ($markers as $marker) {
            $key = (string)($marker['marker'] ?? '');
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $rules[] = $marker;
            $seen[$key] = true;
        }

        return $rules;
    }

    /** @param array<int, array<string, mixed>> $rules */
    private function semanticMarkersFromRules(array $rules): array
    {
        $markers = [];
        $seen = [];
        foreach ($rules as $rule) {
            if (($rule['source'] ?? null) !== 'semantic_marker' && !isset($rule['marker'])) {
                continue;
            }
            $marker = (string)($rule['marker'] ?? '');
            if ($marker === '' || isset($seen[$marker])) {
                continue;
            }
            $item = ['marker' => $marker];
            foreach (['source_actor', 'source_label', 'pattern', 'rule'] as $key) {
                if (isset($rule[$key]) && $rule[$key] !== '') {
                    $item[$key] = $rule[$key];
                }
            }
            $markers[] = $item;
            $seen[$marker] = true;
        }

        return $markers;
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

    private function sanitizeDictionaryInternetQuery(string $query): string
    {
        $value = preg_replace('/[+\-]?\s*\d+(?:[.,]\d+)?/u', ' ', $query);
        $value = preg_replace('/\b\d{4}-\d{2}-\d{2}\b/u', ' ', (string)$value);
        $value = preg_replace('/\s+/u', ' ', (string)$value);

        return trim(mb_substr((string)$value, 0, 190));
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
        $workspace = [
            'id' => (string)$row['id'],
            'name' => (string)$row['name'],
            'type' => (string)$row['type'],
            'currency' => (string)$row['currency'],
            'locale' => (string)$row['locale'],
            'created_by' => isset($row['created_by']) ? (int)$row['created_by'] : null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];

        if (array_key_exists('member_role', $row) && $row['member_role'] !== null) {
            $workspace += $this->workspaceAccessFromMemberRow($row);
        }

        return $workspace;
    }

    private function workspaceAssistantSettingsRaw(string $workspaceId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_workspace_assistant_settings
            WHERE workspace_id = ?
            LIMIT 1
        ");
        $stmt->execute([$workspaceId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function workspaceAssistantSettingsDefaults(string $workspaceId): array
    {
        return [
            'workspace_id' => $workspaceId,
            'mr_smith_enabled' => false,
            'internet_reference_mode' => 'per_request',
            'provider_key' => 'stub',
            'retention_days' => 30,
            'updated_by' => null,
            'created_at' => null,
            'updated_at' => null,
        ];
    }

    private function workspaceAssistantSettingsRow(array $row): array
    {
        return [
            'workspace_id' => (string)$row['workspace_id'],
            'mr_smith_enabled' => (bool)$row['mr_smith_enabled'],
            'internet_reference_mode' => (string)$row['internet_reference_mode'],
            'provider_key' => (string)$row['provider_key'],
            'retention_days' => (int)$row['retention_days'],
            'updated_by' => isset($row['updated_by']) ? (int)$row['updated_by'] : null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function dictionaryInternetReferenceLookupRow(array $row): array
    {
        return [
            'id' => (string)$row['id'],
            'workspace_id' => (string)$row['workspace_id'],
            'source_row_id' => $row['source_row_id'] === null ? null : (string)$row['source_row_id'],
            'provider_key' => (string)$row['provider_key'],
            'provider_request_id' => $row['provider_request_id'] === null ? null : (string)$row['provider_request_id'],
            'consent_source' => (string)$row['consent_source'],
            'sanitized_query' => (string)$row['sanitized_query'],
            'query_hash' => (string)$row['query_hash'],
            'masked_fields' => FinDeskV2Support::jsonDecode($row['masked_fields_json'] ?? '[]', []),
            'result_status' => (string)$row['result_status'],
            'latency_ms' => (int)$row['latency_ms'],
            'matches' => FinDeskV2Support::jsonDecode($row['matches_json'] ?? '[]', []),
            'selected_match' => FinDeskV2Support::jsonDecode($row['selected_match_json'] ?? null, null),
            'no_financial_mutation' => (bool)$row['no_financial_mutation'],
            'created_by' => isset($row['created_by']) ? (int)$row['created_by'] : null,
            'created_at' => $row['created_at'] ?? null,
            'retention_delete_after' => $row['retention_delete_after'] ?? null,
        ];
    }

    private function internetReferenceProvider(string $providerKey): FinDeskV2InternetReferenceProvider
    {
        if (!in_array($providerKey, $this->internetReferenceProviderKeys(), true)) {
            throw new FinDeskV2HttpError(422, 'invalid_provider_key');
        }

        if ($providerKey === 'allowlisted_http') {
            $domains = FinDeskV2InternetReferenceProviderConfig::allowedDomains();
            if ($domains === []) {
                throw new FinDeskV2HttpError(422, 'internet_reference_allowlist_empty');
            }

            return new FinDeskV2AllowlistedHttpInternetReferenceProvider($domains);
        }

        return new FinDeskV2StubInternetReferenceProvider();
    }

    private function internetReferenceProviderKeys(): array
    {
        $keys = ['stub'];
        if (
            FinDeskV2InternetReferenceProviderConfig::allowlistedHttpEnabled()
            && FinDeskV2InternetReferenceProviderConfig::allowedDomains() !== []
        ) {
            $keys[] = 'allowlisted_http';
        }

        return $keys;
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

    private function dictionaryTrainingDecisionRow(array $row): array
    {
        return [
            'id' => (string)$row['id'],
            'workspace_id' => (string)$row['workspace_id'],
            'archive_workspace_id' => $row['archive_workspace_id'] ?? null,
            'source_id' => $row['source_id'] ?? null,
            'source_row_id' => $row['source_row_id'] ?? null,
            'decision_scope' => (string)$row['decision_scope'],
            'group_key' => $row['group_key'] ?? null,
            'source_row_ids' => FinDeskV2Support::jsonDecode($row['source_row_ids_json'] ?? '[]', []),
            'decision_type' => (string)$row['decision_type'],
            'current_rule_guess' => $row['current_rule_guess'] ?? null,
            'target_category_code' => $row['category_code'] ?? null,
            'category_rule_id' => $row['category_rule_id'] ?? null,
            'pattern' => $row['pattern'] ?? null,
            'pattern_type' => $row['pattern_type'] ?? null,
            'language' => (string)$row['language'],
            'weight' => $row['weight'] === null ? null : (int)$row['weight'],
            'negative_weight' => $row['negative_weight'] === null ? null : (int)$row['negative_weight'],
            'requires_any' => FinDeskV2Support::jsonDecode($row['requires_any_json'] ?? '[]', []),
            'excludes_any' => FinDeskV2Support::jsonDecode($row['excludes_any_json'] ?? '[]', []),
            'confidence' => $row['confidence'] === null ? null : (float)$row['confidence'],
            'review_reason' => $row['review_reason'] ?? null,
            'blockers' => FinDeskV2Support::jsonDecode($row['blockers_json'] ?? '[]', []),
            'matched_signals' => FinDeskV2Support::jsonDecode($row['matched_signals_json'] ?? '[]', []),
            'semantic_markers' => FinDeskV2Support::jsonDecode($row['semantic_markers_json'] ?? '[]', []),
            'source_snapshot' => FinDeskV2Support::jsonDecode($row['example_snapshot_json'] ?? '{}', []),
            'note' => $row['note'] ?? null,
            'decided_by' => $row['decided_by'] === null ? null : (int)$row['decided_by'],
            'decided_at' => $row['decided_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function attachmentRow(array $row): array
    {
        return [
            'id' => (string)$row['id'],
            'entry_id' => (string)$row['entry_id'],
            'file_name' => (string)$row['file_name'],
            'file_url' => (string)$row['file_url'],
            'mime_type' => $row['mime_type'] ?? null,
            'size_bytes' => $row['size_bytes'] === null ? null : (int)$row['size_bytes'],
            'image_mode' => $row['image_mode'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    private function reportSnapshotRow(array $row): array
    {
        return [
            'id' => (string)$row['id'],
            'workspace_id' => (string)$row['workspace_id'],
            'report_type' => (string)$row['report_type'],
            'year' => (int)$row['year'],
            'month' => (int)$row['month'],
            'version' => (int)$row['version'],
            'status' => (string)$row['status'],
            'generated_at' => $row['generated_at'] ?? null,
            'stored_at' => $row['stored_at'] ?? null,
            'closed_at' => $row['closed_at'] ?? null,
            'comment' => ($row['comment'] ?? null) === null ? null : (string)$row['comment'],
            'summary' => $this->withReportMoneyPosition(FinDeskV2Support::jsonDecode($row['summary_json'] ?? null, [])),
            'source_trace' => FinDeskV2Support::jsonDecode($row['source_trace_json'] ?? null, []),
            'source_entry_ids' => FinDeskV2Support::jsonDecode($row['source_entry_ids_json'] ?? null, []),
            'correction_ids' => FinDeskV2Support::jsonDecode($row['correction_ids_json'] ?? null, []),
            'attachment_refs' => FinDeskV2Support::jsonDecode($row['attachment_refs_json'] ?? null, []),
            'forecast_snapshot' => FinDeskV2Support::jsonDecode($row['forecast_snapshot_json'] ?? null, null),
            'content_hash' => (string)$row['content_hash'],
            'created_by' => $row['created_by'] === null ? null : (int)$row['created_by'],
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    private function reportBatchRow(array $row): array
    {
        $sourceEntryIds = FinDeskV2Support::jsonDecode($row['source_entry_ids_json'] ?? '[]', []);
        $summary = $this->withReportMoneyPosition(FinDeskV2Support::jsonDecode($row['summary_json'] ?? '[]', []));
        $entrySnapshot = FinDeskV2Support::jsonDecode($row['entry_snapshot_json'] ?? '[]', []);

        return [
            'id' => (string)$row['id'],
            'workspace_id' => (string)$row['workspace_id'],
            'report_type' => (string)$row['batch_type'],
            'batch_type' => (string)$row['batch_type'],
            'title' => (string)$row['title'],
            'status' => (string)$row['status'],
            'period' => [
                'from' => (string)$row['start_date'],
                'to' => (string)$row['end_date'],
            ],
            'start_date' => (string)$row['start_date'],
            'end_date' => (string)$row['end_date'],
            'from_entry_id' => $row['from_entry_id'] === null ? null : (string)$row['from_entry_id'],
            'to_entry_id' => $row['to_entry_id'] === null ? null : (string)$row['to_entry_id'],
            'entry_count' => (int)$row['entry_count'],
            'entries_count' => (int)$row['entry_count'],
            'generated_at' => $row['generated_at'] ?? null,
            'closed_at' => $row['closed_at'] ?? null,
            'html_filename' => $row['html_filename'] ?? null,
            'html_url' => '/v2-report.php?id=' . rawurlencode((string)$row['id']),
            'summary' => $summary,
            'snapshot' => $summary,
            'source_trace' => FinDeskV2Support::jsonDecode($row['source_trace_json'] ?? '[]', []),
            'source_entry_ids' => is_array($sourceEntryIds) ? $sourceEntryIds : [],
            'entry_snapshot' => $entrySnapshot,
            'entries' => $entrySnapshot,
            'content_hash' => (string)$row['content_hash'],
            'created_by' => $row['created_by'] === null ? null : (int)$row['created_by'],
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function reportBatchHtmlSnapshotRow(array $row, bool $includeHtml = false): array
    {
        $snapshot = [
            'id' => (string)$row['id'],
            'workspace_id' => (string)$row['workspace_id'],
            'batch_id' => (string)$row['batch_id'],
            'version' => (int)$row['version'],
            'status' => (string)$row['status'],
            'generated_at' => $row['generated_at'] ?? null,
            'stored_at' => $row['stored_at'] ?? null,
            'html_filename' => $row['html_filename'] ?? null,
            'html_size_bytes' => (int)($row['html_size_bytes'] ?? 0),
            'html_hash' => (string)$row['html_hash'],
            'source_batch_hash' => (string)$row['source_batch_hash'],
            'comment' => $row['comment'] === null ? null : (string)$row['comment'],
            'created_by' => $row['created_by'] === null ? null : (int)$row['created_by'],
            'created_at' => $row['created_at'] ?? null,
        ];
        if ($includeHtml) {
            $snapshot['html_content'] = (string)($row['html_content'] ?? '');
        }

        return $snapshot;
    }

    private function reportPackageRow(array $row, bool $withItems = false): array
    {
        $fragmentIds = FinDeskV2Support::jsonDecode($row['fragment_ids_json'] ?? '[]', []);
        $sourceEntryIds = FinDeskV2Support::jsonDecode($row['source_entry_ids_json'] ?? '[]', []);
        $summary = $this->withReportMoneyPosition(FinDeskV2Support::jsonDecode($row['summary_json'] ?? '[]', []));
        $entryCount = (int)($row['entry_count'] ?? ($summary['header']['entries_count'] ?? 0));
        $package = [
            'id' => (string)$row['id'],
            'workspace_id' => (string)$row['workspace_id'],
            'report_type' => 'operational_package',
            'package_type' => (string)($row['package_type'] ?? 'operational_fragment_package'),
            'title' => (string)$row['title'],
            'status' => (string)$row['status'],
            'period' => [
                'from' => (string)$row['start_date'],
                'to' => (string)$row['end_date'],
            ],
            'start_date' => (string)$row['start_date'],
            'end_date' => (string)$row['end_date'],
            'fragment_count' => (int)$row['fragment_count'],
            'entry_count' => $entryCount,
            'entries_count' => $entryCount,
            'generated_at' => $row['generated_at'] ?? null,
            'closed_at' => $row['closed_at'] ?? null,
            'comment' => ($row['comment'] ?? null) === null ? null : (string)$row['comment'],
            'html_filename' => $row['html_filename'] ?? null,
            'html_url' => '/v2-report.php?type=package&id=' . rawurlencode((string)$row['id']),
            'summary' => $summary,
            'snapshot' => $summary,
            'fragment_ids' => is_array($fragmentIds) ? $fragmentIds : [],
            'source_entry_ids' => is_array($sourceEntryIds) ? $sourceEntryIds : [],
            'content_hash' => (string)$row['content_hash'],
            'created_by' => $row['created_by'] === null ? null : (int)$row['created_by'],
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];

        if ($withItems) {
            $items = $this->reportPackageItemRows((string)$row['id']);
            $package['items'] = $items;
            $package['fragments'] = array_values(array_filter(array_map(
                static fn (array $item): array => is_array($item['fragment'] ?? null) ? $item['fragment'] : [],
                $items
            )));
            $package['versions'] = $this->listReportVersions((string)$row['workspace_id'], (string)$row['id'], 'operational_package');
        }

        return $package;
    }

    private function reportPackageItemRows(string $packageId): array
    {
        $this->ensureReportPackageSchema();
        $stmt = $this->db->prepare("
            SELECT batch_id, html_snapshot_id, item_order, fragment_snapshot_json, html_snapshot_json
            FROM v2_report_package_items
            WHERE package_id = ?
            ORDER BY item_order ASC
        ");
        $stmt->execute([$packageId]);

        $items = [];
        foreach ($stmt->fetchAll() as $row) {
            $items[] = [
                'batch_id' => (string)$row['batch_id'],
                'html_snapshot_id' => $row['html_snapshot_id'] === null ? null : (string)$row['html_snapshot_id'],
                'row_number' => (int)$row['item_order'],
                'fragment' => FinDeskV2Support::jsonDecode($row['fragment_snapshot_json'] ?? '[]', []),
                'html_snapshot' => FinDeskV2Support::jsonDecode($row['html_snapshot_json'] ?? null, null),
            ];
        }

        return $items;
    }

    private function operationalHtmlSnapshotSchemaIsAvailable(): bool
    {
        if ($this->operationalHtmlSnapshotSchemaAvailable !== null) {
            return $this->operationalHtmlSnapshotSchemaAvailable;
        }

        try {
            $this->operationalHtmlSnapshotSchemaAvailable = $this->tableExists('v2_report_batch_html_snapshots');
        } catch (PDOException) {
            $this->operationalHtmlSnapshotSchemaAvailable = false;
        }

        return $this->operationalHtmlSnapshotSchemaAvailable;
    }

    private function operationalPackageSchemaIsAvailable(): bool
    {
        if ($this->operationalPackageSchemaAvailable !== null) {
            return $this->operationalPackageSchemaAvailable;
        }

        try {
            $this->operationalPackageSchemaAvailable = $this->tableExists('v2_report_packages')
                && $this->tableExists('v2_report_package_items')
                && $this->tableExists('v2_report_versions')
                && $this->tableExists('v2_report_batch_html_snapshots');
        } catch (PDOException) {
            $this->operationalPackageSchemaAvailable = false;
        }

        return $this->operationalPackageSchemaAvailable;
    }

    private function storeOperationalReportFragmentHtmlSnapshot(
        string $workspaceId,
        array $batch,
        array $workspace,
        int $userId,
        string $status,
        ?string $comment,
        bool $required
    ): array {
        if (!$this->operationalHtmlSnapshotSchemaIsAvailable()) {
            if ($required) {
                throw new FinDeskV2HttpError(503, 'report_html_snapshot_schema_missing');
            } else {
                return [];
            }
        }

        $status = FinDeskV2Support::enum($status, ['stored', 'closed'], 'status');
        $batchId = (string)$batch['id'];
        $version = $this->nextOperationalHtmlSnapshotVersion($workspaceId, $batchId);
        $html = $this->operationalReportHtml($batch, $workspace);
        $htmlHash = hash('sha256', $html);
        $htmlFilename = $this->operationalReportHtmlSnapshotRelativePath($workspaceId, $batchId, $version);
        $snapshotId = FinDeskV2Support::uuid();

        $this->db->prepare("
            INSERT INTO v2_report_batch_html_snapshots (
                id, workspace_id, batch_id, version, status, generated_at, html_filename,
                html_content, html_size_bytes, html_hash, source_batch_hash, comment, created_by
            )
            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $snapshotId,
            $workspaceId,
            $batchId,
            $version,
            $status,
            $htmlFilename,
            $html,
            strlen($html),
            $htmlHash,
            (string)$batch['content_hash'],
            $comment,
            $userId,
        ]);
        $this->writeReportHtmlSnapshotFile($htmlFilename, $html);

        $snapshot = $this->getOperationalReportFragmentHtmlSnapshot($workspaceId, $batchId, $snapshotId, $userId);
        unset($snapshot['html_content']);

        return $snapshot;
    }

    private function nextOperationalHtmlSnapshotVersion(string $workspaceId, string $batchId): int
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(MAX(version), 0) + 1
            FROM v2_report_batch_html_snapshots
            WHERE workspace_id = ? AND batch_id = ?
        ");
        $stmt->execute([$workspaceId, $batchId]);

        return (int)$stmt->fetchColumn();
    }

    private function latestOperationalHtmlSnapshotForBatch(string $batchId): ?array
    {
        if (!$this->operationalHtmlSnapshotSchemaIsAvailable()) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_report_batch_html_snapshots
            WHERE batch_id = ?
            ORDER BY version DESC, created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$batchId]);
        $row = $stmt->fetch();

        return $row ? $this->reportBatchHtmlSnapshotRow($row, false) : null;
    }

    private function operationalReportHtmlSnapshotRelativePath(string $workspaceId, string $batchId, int $version): string
    {
        return 'storage/v2/report-batches/' . $workspaceId . '/html-snapshots/' . $batchId . '-v' . $version . '.html';
    }

    private function writeReportHtmlSnapshotFile(string $relativePath, string $html): void
    {
        $absolutePath = dirname(__DIR__, 2) . '/' . ltrim($relativePath, '/');
        $dir = dirname($absolutePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        if (@file_put_contents($absolutePath, $html) === false) {
            throw new FinDeskV2HttpError(500, 'report_html_snapshot_store_failed');
        }
        @chmod($absolutePath, 0600);
    }

    private function operationalPackageFragmentIds(array $input): array
    {
        $raw = $input['fragment_ids'] ?? $input['report_ids'] ?? $input['batch_ids'] ?? $input['ids'] ?? [];
        if (is_string($raw)) {
            $raw = preg_split('/\s*,\s*/', trim($raw)) ?: [];
        }
        if (!is_array($raw)) {
            throw new FinDeskV2HttpError(422, 'invalid_fragment_ids');
        }

        $ids = [];
        foreach ($raw as $candidate) {
            $id = strtolower(trim((string)$candidate));
            if ($id === '') {
                continue;
            }
            if (!$this->isUuid($id)) {
                throw new FinDeskV2HttpError(422, 'invalid_fragment_ids');
            }
            $this->appendSourceEntryId($ids, $id);
        }
        if (count($ids) < 2) {
            throw new FinDeskV2HttpError(422, 'report_package_requires_multiple_fragments');
        }
        if (count($ids) > 50) {
            throw new FinDeskV2HttpError(422, 'too_many_fragment_ids');
        }

        return $ids;
    }

    private function operationalPackageFragments(string $workspaceId, array $fragmentIds): array
    {
        $placeholders = implode(', ', array_fill(0, count($fragmentIds), '?'));
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_report_batches
            WHERE workspace_id = ?
              AND batch_type = 'operational_fragment'
              AND id IN ({$placeholders})
        ");
        $stmt->execute(array_merge([$workspaceId], $fragmentIds));

        $byId = [];
        foreach ($stmt->fetchAll() as $row) {
            $byId[(string)$row['id']] = $this->reportBatchRow($row);
        }

        $fragments = [];
        foreach ($fragmentIds as $id) {
            if (!isset($byId[$id])) {
                throw new FinDeskV2HttpError(422, 'report_package_fragments_not_found');
            }
            $fragments[] = $byId[$id];
        }

        return $fragments;
    }

    private function operationalFragmentIsClosed(array $fragment): bool
    {
        return !empty($fragment['closed_at'])
            && !in_array((string)$fragment['status'], ['draft', 'requires_update', 'returned_for_revision', 'superseded'], true);
    }

    private function operationalReportPackageSummary(
        string $workspaceId,
        array $fragments,
        array $htmlSnapshots,
        string $title,
        ?string $comment
    ): array {
        $sourceEntryIds = [];
        $totals = [
            'total_cash_income' => 0.0,
            'cash_income' => 0.0,
            'cash_expense' => 0.0,
            'card_expense' => 0.0,
            'commercial_income' => 0.0,
            'other_review_total' => 0.0,
            'lower_accounting_total' => 0.0,
            'admin_debt_total' => 0.0,
            'corrections_total' => 0.0,
        ];
        $categories = [];
        $entryCount = 0;
        $openingCash = null;
        $endingCash = null;
        $closedAt = null;
        $fragmentRows = [];
        $moneyPosition = null;
        $adminDebtPackage = [
            'count' => 0,
            'opening_total' => null,
            'increased_total' => 0.0,
            'returned_total' => 0.0,
            'net_change' => 0.0,
            'total' => 0.0,
            'entries' => [],
            'source_entry_ids' => [],
            'basis_breakdown' => null,
        ];

        foreach ($fragments as $index => $fragment) {
            $summary = is_array($fragment['summary'] ?? null) ? $fragment['summary'] : [];
            $fragmentTotals = is_array($summary['totals'] ?? null) ? $summary['totals'] : [];
            if ($index === 0) {
                $openingCash = $fragmentTotals['opening_cash'] ?? null;
            }
            $endingCash = $fragmentTotals['ending_cash'] ?? $endingCash;
            if (is_array($summary['money_position'] ?? null)) {
                $moneyPosition = $summary['money_position'];
            }
            $fragmentAdminDebt = is_array($summary['blocks']['admin_debt'] ?? null) ? $summary['blocks']['admin_debt'] : [];
            if ($fragmentAdminDebt !== []) {
                if ($adminDebtPackage['opening_total'] === null) {
                    $adminDebtPackage['opening_total'] = (float)($fragmentAdminDebt['opening_total'] ?? 0.0);
                }
                if ($adminDebtPackage['basis_breakdown'] === null && is_array($fragmentAdminDebt['basis_breakdown'] ?? null)) {
                    $adminDebtPackage['basis_breakdown'] = $fragmentAdminDebt['basis_breakdown'];
                }
                $adminDebtPackage['increased_total'] += (float)($fragmentAdminDebt['increased_total'] ?? 0.0);
                $adminDebtPackage['returned_total'] += (float)($fragmentAdminDebt['returned_total'] ?? 0.0);
                $adminDebtPackage['net_change'] += (float)($fragmentAdminDebt['net_change'] ?? 0.0);
                $adminDebtPackage['total'] = (float)($fragmentAdminDebt['total'] ?? $adminDebtPackage['total']);
                foreach (($fragmentAdminDebt['entries'] ?? []) as $entry) {
                    if (is_array($entry)) {
                        $adminDebtPackage['entries'][] = $entry;
                    }
                }
                foreach (($fragmentAdminDebt['source_entry_ids'] ?? []) as $entryId) {
                    $this->appendSourceEntryId($adminDebtPackage['source_entry_ids'], (string)$entryId);
                }
            }
            foreach ($totals as $key => $value) {
                $totals[$key] = $value + (float)($fragmentTotals[$key] ?? 0.0);
            }
            foreach ($fragment['source_entry_ids'] ?? [] as $entryId) {
                $this->appendSourceEntryId($sourceEntryIds, (string)$entryId);
            }
            foreach ($summary['blocks']['categories']['rows'] ?? [] as $category) {
                $code = (string)($category['category_code'] ?? '');
                if ($code === '') {
                    continue;
                }
                if (!isset($categories[$code])) {
                    $categories[$code] = [
                        'category_code' => $code,
                        'category_name' => $category['category_name'] ?? null,
                        'cash_total' => 0.0,
                        'card_total' => 0.0,
                        'total' => 0.0,
                        'entry_count' => 0,
                        'source_entry_ids' => [],
                    ];
                }
                $categories[$code]['cash_total'] += (float)($category['cash_total'] ?? 0.0);
                $categories[$code]['card_total'] += (float)($category['card_total'] ?? 0.0);
                $categories[$code]['total'] += (float)($category['total'] ?? 0.0);
                $categories[$code]['entry_count'] += (int)($category['entry_count'] ?? 0);
                foreach ($category['source_entry_ids'] ?? [] as $entryId) {
                    $this->appendSourceEntryId($categories[$code]['source_entry_ids'], (string)$entryId);
                }
            }
            $entryCount += (int)($fragment['entry_count'] ?? 0);
            if (!empty($fragment['closed_at']) && ($closedAt === null || strcmp((string)$fragment['closed_at'], $closedAt) > 0)) {
                $closedAt = (string)$fragment['closed_at'];
            }
            $htmlSnapshot = $htmlSnapshots[(string)$fragment['id']] ?? null;
            $fragmentRows[] = [
                'id' => (string)$fragment['id'],
                'title' => (string)$fragment['title'],
                'status' => (string)$fragment['status'],
                'start_date' => (string)$fragment['start_date'],
                'end_date' => (string)$fragment['end_date'],
                'closed_at' => $fragment['closed_at'] ?? null,
                'entry_count' => (int)($fragment['entry_count'] ?? 0),
                'content_hash' => (string)$fragment['content_hash'],
                'html_snapshot_id' => is_array($htmlSnapshot) ? (string)$htmlSnapshot['id'] : null,
                'html_snapshot_version' => is_array($htmlSnapshot) ? (int)$htmlSnapshot['version'] : null,
                'html_hash' => is_array($htmlSnapshot) ? (string)$htmlSnapshot['html_hash'] : null,
            ];
        }

        foreach ($totals as $key => $value) {
            $totals[$key] = round((float)$value, 2);
        }
        if (!is_array($moneyPosition)) {
            $moneyPosition = $this->reportMoneyPosition($endingCash);
        }
        $adminDebtPackage['count'] = count($adminDebtPackage['entries']);
        $adminDebtPackage['opening_total'] = round((float)($adminDebtPackage['opening_total'] ?? 0.0), 2);
        $adminDebtPackage['increased_total'] = round((float)$adminDebtPackage['increased_total'], 2);
        $adminDebtPackage['returned_total'] = round((float)$adminDebtPackage['returned_total'], 2);
        $adminDebtPackage['net_change'] = round((float)$adminDebtPackage['net_change'], 2);
        $adminDebtPackage['total'] = round((float)$adminDebtPackage['total'], 2);
        $totals['admin_debt_total'] = $adminDebtPackage['total'];

        return [
            'package_type' => 'operational_fragment_package',
            'workspace_id' => $workspaceId,
            'title' => $title,
            'comment' => $comment,
            'header' => [
                'report_type' => 'operational_package',
                'start_date' => (string)$fragments[0]['start_date'],
                'end_date' => (string)$fragments[count($fragments) - 1]['end_date'],
                'range_label' => (string)$fragments[0]['start_date'] . ' - ' . (string)$fragments[count($fragments) - 1]['end_date'],
                'generated_at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
                'closed_at' => $closedAt,
                'fragments_count' => count($fragments),
                'entries_count' => $entryCount,
            ],
            'totals' => $totals + [
                'opening_cash' => $openingCash,
                'ending_cash' => $endingCash,
            ],
            'money_position' => $moneyPosition,
            'blocks' => [
                'money_position' => $moneyPosition,
                'fragments' => $fragmentRows,
                'admin_debt' => $adminDebtPackage,
                'categories' => [
                    'rows' => array_values($categories),
                ],
            ],
            'source_entry_ids' => $sourceEntryIds,
            'source_trace' => [
                'fragment_ids' => array_map(static fn (array $fragment): string => (string)$fragment['id'], $fragments),
                'source_entry_ids' => $sourceEntryIds,
            ],
        ];
    }

    private function reportPackageById(string $workspaceId, string $packageId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_report_packages
            WHERE workspace_id = ?
              AND id = ?
            LIMIT 1
        ");
        $stmt->execute([$workspaceId, $packageId]);
        $row = $stmt->fetch();

        return $row ? $this->reportPackageRow($row, true) : null;
    }

    private function operationalFragmentEntryIds(array $input): array
    {
        $raw = $input['entry_ids'] ?? $input['ids'] ?? [];
        if (is_string($raw)) {
            $raw = preg_split('/\s*,\s*/', trim($raw)) ?: [];
        }
        if (!is_array($raw)) {
            throw new FinDeskV2HttpError(422, 'invalid_entry_ids');
        }

        $ids = [];
        foreach ($raw as $candidate) {
            $id = strtolower(trim((string)$candidate));
            if ($id === '') {
                continue;
            }
            if (!$this->isUuid($id)) {
                throw new FinDeskV2HttpError(422, 'invalid_entry_ids');
            }
            $this->appendSourceEntryId($ids, $id);
        }
        if ($ids === []) {
            throw new FinDeskV2HttpError(422, 'missing_entry_ids');
        }
        if (count($ids) > 5000) {
            throw new FinDeskV2HttpError(422, 'too_many_entry_ids');
        }

        return $ids;
    }

    private function buildOperationalFragmentReport(string $workspaceId, array $entryIds, int $userId, ?string $exceptReportId = null): array
    {
        $workspace = $this->getWorkspace($workspaceId, $userId);
        $rows = $this->operationalFragmentRows($workspaceId, $entryIds);
        $locks = $this->activeReportLocksForEntryIds($entryIds, $exceptReportId);
        $first = $rows[0];
        $last = $rows[count($rows) - 1];
        $startDate = (string)$first['date'];
        $endDate = (string)$last['date'];
        $sourceEntryIds = array_map(static fn (array $row): string => (string)$row['id'], $rows);
        $cashFlow = $this->cashFlowForWorkspace($workspaceId, $userId);
        $firstCash = null;
        foreach ($rows as $row) {
            if ((string)$row['flow_type'] === 'cash') {
                $firstCash = $row;
                break;
            }
        }
        $openingCash = $firstCash === null || $cashFlow === null
            ? null
            : $this->cashBalanceBeforeEntryRow($cashFlow['id'], (string)$firstCash['date'], (int)$firstCash['created_seq']);
        $selectedCashDelta = 0.0;

        $sourceTrace = [
            'fragment_entry_ids' => $sourceEntryIds,
            'locked_entry_ids' => array_keys($locks),
            'totals' => [
                'opening_cash' => [],
                'total_cash_income' => [],
                'cash_income' => [],
                'cash_expense' => [],
                'card_expense' => [],
                'commercial_income' => [],
                'other_review_total' => [],
                'lower_accounting_total' => [],
                'admin_debt_total' => [],
                'corrections_total' => [],
                'ending_cash' => [],
            ],
            'categories' => [],
            'basis' => [
                'opening_cash' => $cashFlow === null ? null : [
                    'type' => 'cash_balance_before_selected_fragment',
                    'flow_id' => $cashFlow['id'],
                    'flow_name' => $cashFlow['name'],
                    'total' => $openingCash,
                    'period_start' => $startDate,
                    'period_end' => $endDate,
                    'label' => 'Cash before selected report fragment',
                ],
            ],
        ];

        $categories = [];
        foreach ($this->listCategories($workspaceId, $userId) as $category) {
            $categories[$category['code']] = [
                'category_code' => $category['code'],
                'category_name' => $category['name'],
                'direction' => $category['direction'],
                'cash_total' => 0.0,
                'card_total' => 0.0,
                'total' => 0.0,
                'entry_count' => 0,
                'review_count' => 0,
                'source_entry_ids' => [],
            ];
        }
        $categories['uncategorized_review'] = $this->uncategorizedReviewCategoryRow();

        $periodTotals = [
            'cash_income' => 0.0,
            'cash_expense' => 0.0,
            'card_expense' => 0.0,
            'commercial_income' => 0.0,
            'cash_topup_from_card_cash_side' => 0.0,
            'cash_topup_from_card_card_side' => 0.0,
            'corrections' => 0.0,
        ];
        $entries = [];
        $otherReviewEntries = [];
        $lowerAccountingEntries = [];
        $adminDebtEntries = [];
        $cardByCategory = [];
        $cardExpenseCount = 0;
        $cardReviewCount = 0;
        $unrecognizedCount = 0;

        foreach ($rows as $index => $row) {
            $id = (string)$row['id'];
            $entry = $this->entryRow($row);
            $entry['created_seq'] = (int)$row['created_seq'];
            $entry['fragment_row_number'] = $index + 1;
            $entry['report_lock'] = $locks[$id] ?? null;
            $entries[] = $entry;

            $amount = $row['amount'] === null ? null : (float)$row['amount'];
            $flowType = (string)$row['flow_type'];
            $direction = (string)$row['direction'];
            $entryType = (string)$row['entry_type'];
            $status = (string)$row['status'];
            $categoryCode = $row['category_code'] === null ? null : (string)$row['category_code'];
            $semanticMarkers = $this->semanticMarkersFromRules(FinDeskV2Support::jsonDecode($row['matched_rules_json'] ?? '[]', []));
            $accounting = $this->accountingClassification($categoryCode, $semanticMarkers, (string)$row['raw_text']);
            $isLowerAccounting = $accounting['section'] === 'lower_accounting';
            $isAdminDebt = $accounting['section'] === 'admin_debt';
            $isCountedEntry = $this->isCountedStatus($status) && $amount !== null;
            $isUncategorizedReview = $isCountedEntry && !$isLowerAccounting && !$isAdminDebt && $entryType !== 'correction' && $categoryCode === null;
            $isOtherReview = ($status === 'other_review' && $entryType === 'cash_expense' && $categoryCode === 'other')
                || $isUncategorizedReview;

            if ($status === 'unrecognized' || $status === 'duplicate_suspect') {
                $unrecognizedCount++;
            }
            if ($flowType === 'card' && $status === 'other_review') {
                $cardReviewCount++;
            }
            if ($isOtherReview) {
                $otherReviewEntries[] = $entry;
                $this->appendSourceEntryId($sourceTrace['totals']['other_review_total'], $id);
            }
            if (!$isCountedEntry) {
                continue;
            }

            if ($isLowerAccounting) {
                $lowerAccountingEntries[] = $entry + [
                    'accounting_section' => $accounting['section'],
                    'accounting_type' => $accounting['type'],
                    'accounting_label' => $accounting['label'],
                ];
                $this->appendSourceEntryId($sourceTrace['totals']['lower_accounting_total'], $id);
            }
            if ($isAdminDebt) {
                $adminDebtEntries[] = $entry + [
                    'accounting_section' => $accounting['section'],
                    'accounting_type' => $accounting['type'],
                    'accounting_label' => $accounting['label'],
                ];
                $this->appendSourceEntryId($sourceTrace['totals']['admin_debt_total'], $id);
            }

            if ($flowType === 'cash') {
                $cashDelta = $this->cashBalanceDelta($row);
                if ($cashDelta !== null) {
                    $selectedCashDelta += $cashDelta;
                    $this->appendSourceEntryId($sourceTrace['totals']['ending_cash'], $id);
                }
            }
            if ($isLowerAccounting || $isAdminDebt) {
                continue;
            }
            if ($flowType === 'cash' && $direction === 'in' && $entryType === 'cash_income') {
                if ($categoryCode === 'commercial_income') {
                    $periodTotals['commercial_income'] += $amount;
                    $this->appendSourceEntryId($sourceTrace['totals']['commercial_income'], $id);
                    $this->appendSourceEntryId($sourceTrace['totals']['total_cash_income'], $id);
                } elseif ($categoryCode === 'cash_topup_from_card') {
                    $periodTotals['cash_topup_from_card_cash_side'] += $amount;
                } else {
                    $periodTotals['cash_income'] += $amount;
                    $this->appendSourceEntryId($sourceTrace['totals']['cash_income'], $id);
                    $this->appendSourceEntryId($sourceTrace['totals']['total_cash_income'], $id);
                }
            }
            if ($flowType === 'cash' && $direction === 'out' && $entryType === 'cash_expense') {
                $periodTotals['cash_expense'] += $amount;
                $this->appendSourceEntryId($sourceTrace['totals']['cash_expense'], $id);
            }
            if ($flowType === 'card' && $direction === 'out' && $entryType === 'card_expense') {
                $cardExpenseCount++;
                $periodTotals['card_expense'] += $amount;
                $this->appendSourceEntryId($sourceTrace['totals']['card_expense'], $id);
                if ($categoryCode === 'cash_topup_from_card') {
                    $periodTotals['cash_topup_from_card_card_side'] += $amount;
                }
            }
            if ($entryType === 'correction') {
                $periodTotals['corrections'] += $direction === 'out' ? -$amount : $amount;
                $this->appendSourceEntryId($sourceTrace['totals']['corrections_total'], $id);
            }
            $effectiveCategoryCode = $categoryCode ?? 'uncategorized_review';
            if (!$isLowerAccounting && isset($categories[$effectiveCategoryCode])) {
                $categories[$effectiveCategoryCode]['entry_count']++;
                $categories[$effectiveCategoryCode]['review_count'] += ($status === 'other_review' || $categoryCode === null) ? 1 : 0;
                $categories[$effectiveCategoryCode]['total'] += $amount;
                if ($flowType === 'cash') {
                    $categories[$effectiveCategoryCode]['cash_total'] += $amount;
                }
                if ($flowType === 'card') {
                    $categories[$effectiveCategoryCode]['card_total'] += $amount;
                }
                $this->appendSourceEntryId($categories[$effectiveCategoryCode]['source_entry_ids'], $id);
                $sourceTrace['categories'][$effectiveCategoryCode] = $categories[$effectiveCategoryCode]['source_entry_ids'];
                if ($flowType === 'card' && $direction === 'out' && $entryType === 'card_expense') {
                    if (!isset($cardByCategory[$effectiveCategoryCode])) {
                        $cardByCategory[$effectiveCategoryCode] = [
                            'category_code' => $effectiveCategoryCode,
                            'category_name' => $categories[$effectiveCategoryCode]['category_name'],
                            'total' => 0.0,
                            'entry_count' => 0,
                            'source_entry_ids' => [],
                        ];
                    }
                    $cardByCategory[$effectiveCategoryCode]['total'] += $amount;
                    $cardByCategory[$effectiveCategoryCode]['entry_count']++;
                    $this->appendSourceEntryId($cardByCategory[$effectiveCategoryCode]['source_entry_ids'], $id);
                }
            }
        }

        $categoryRows = array_values(array_filter(
            $categories,
            static fn (array $row): bool => $row['entry_count'] > 0 || abs((float)$row['total']) > 0.0001
        ));
        usort($categoryRows, static fn (array $a, array $b): int => strcmp((string)$a['category_code'], (string)$b['category_code']));
        $lowerAccountingSettlements = $this->lowerAccountingSettlementSummary($lowerAccountingEntries);
        $lowerAccountingOpenTotal = (float)($lowerAccountingSettlements['net_open_total'] ?? 0.0);
        $adminDebt = $this->adminDebtSummary($workspaceId, $startDate, (new DateTimeImmutable($endDate))->modify('+1 day')->format('Y-m-d'), $adminDebtEntries);
        $endingCash = $openingCash === null ? null : $openingCash + $selectedCashDelta;
        $accountableDashboard = $this->getAccountableDashboard($workspaceId, $userId);
        $moneyPosition = $this->reportMoneyPosition($endingCash, $accountableDashboard['summary'] ?? []);

        return [
            'header' => [
                'report_type' => 'operational_fragment',
                'workspace' => [
                    'id' => $workspace['id'],
                    'name' => $workspace['name'],
                    'type' => $workspace['type'],
                ],
                'currency' => $workspace['currency'] ?: 'EUR',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'from_entry_id' => (string)$first['id'],
                'to_entry_id' => (string)$last['id'],
                'range_label' => $startDate === $endDate ? $startDate : ($startDate . ' - ' . $endDate),
                'generated_at' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
                'entries_count' => count($entries),
                'review_count' => count($otherReviewEntries),
                'unrecognized_count' => $unrecognizedCount,
                'locked_count' => count($locks),
            ],
            'totals' => [
                'opening_cash' => $openingCash,
                'total_cash_income' => $periodTotals['cash_income'] + $periodTotals['commercial_income'],
                'cash_income' => $periodTotals['cash_income'],
                'cash_expense' => $periodTotals['cash_expense'],
                'card_expense' => $periodTotals['card_expense'],
                'commercial_income' => $periodTotals['commercial_income'],
                'other_review_total' => array_sum(array_map(static fn (array $entry): float => (float)($entry['amount'] ?? 0), $otherReviewEntries)),
                'lower_accounting_total' => $lowerAccountingOpenTotal,
                'admin_debt_total' => $adminDebt['total'],
                'corrections_total' => $periodTotals['corrections'],
                'ending_cash' => $endingCash,
            ],
            'money_position' => $moneyPosition,
            'blocks' => [
                'cash' => [
                    'opening_cash' => $openingCash,
                    'cash_income' => $periodTotals['cash_income'],
                    'cash_topup_from_card' => $periodTotals['cash_topup_from_card_cash_side'],
                    'commercial_income' => $periodTotals['commercial_income'],
                    'cash_expense' => $periodTotals['cash_expense'],
                    'corrections_total' => $periodTotals['corrections'],
                    'ending_cash' => $endingCash,
                    'source_entry_ids' => $sourceTrace['totals']['ending_cash'],
                ],
                'money_position' => $moneyPosition,
                'card' => [
                    'card_expense' => $periodTotals['card_expense'],
                    'cash_topup_to_cash' => $periodTotals['cash_topup_from_card_card_side'],
                    'entries_count' => $cardExpenseCount,
                    'review_count' => $cardReviewCount,
                    'by_category' => array_values($cardByCategory),
                    'source_entry_ids' => $sourceTrace['totals']['card_expense'],
                ],
                'categories' => [
                    'rows' => $categoryRows,
                ],
                'other_review' => [
                    'count' => count($otherReviewEntries),
                    'entries' => $otherReviewEntries,
                    'source_entry_ids' => $sourceTrace['totals']['other_review_total'],
                ],
                'lower_accounting' => [
                    'count' => count($lowerAccountingEntries),
                    'total' => $lowerAccountingOpenTotal,
                    'entries' => $lowerAccountingEntries,
                    'settlements' => $lowerAccountingSettlements,
                    'source_entry_ids' => $sourceTrace['totals']['lower_accounting_total'],
                ],
                'admin_debt' => $adminDebt,
            ],
            'entries' => $entries,
            'source_trace' => $sourceTrace,
        ];
    }

    private function operationalFragmentRows(string $workspaceId, array $entryIds): array
    {
        $placeholders = implode(', ', array_fill(0, count($entryIds), '?'));
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
            WHERE e.workspace_id = ?
              AND e.archived_at IS NULL
              AND e.id IN ({$placeholders})
        ");
        $stmt->execute(array_merge([$workspaceId], $entryIds));
        $rows = $stmt->fetchAll();
        if (count($rows) !== count($entryIds)) {
            throw new FinDeskV2HttpError(422, 'report_fragment_entries_not_found');
        }
        usort($rows, static function (array $a, array $b): int {
            $date = strcmp((string)$a['date'], (string)$b['date']);
            return $date !== 0 ? $date : ((int)$a['created_seq'] <=> (int)$b['created_seq']);
        });

        return $rows;
    }

    private function cashBalanceBeforeEntryRow(string $flowId, string $date, int $createdSeq): float
    {
        $stmt = $this->db->prepare("
            SELECT balance_after
            FROM v2_entries
            WHERE flow_id = ?
              AND (
                  archived_at IS NULL
                  OR (
                      source_type = 'correction'
                      AND matched_rules_json LIKE '%visible_chain_seam%'
                  )
              )
              AND balance_after IS NOT NULL
              AND (date < ? OR (date = ? AND created_seq < ?))
            ORDER BY date DESC, created_seq DESC
            LIMIT 1
        ");
        $stmt->execute([$flowId, $date, $date, $createdSeq]);
        $previous = $stmt->fetchColumn();
        if ($previous !== false) {
            return (float)$previous;
        }
        $flow = $this->db->prepare("SELECT opening_balance FROM v2_flows WHERE id = ? LIMIT 1");
        $flow->execute([$flowId]);

        return (float)$flow->fetchColumn();
    }

    private function activeReportLocksForEntryIds(array $entryIds, ?string $exceptReportId = null): array
    {
        if ($entryIds === []) {
            return [];
        }
        if (!$this->reportBatchSchemaIsAvailable()) {
            return [];
        }
        $placeholders = implode(', ', array_fill(0, count($entryIds), '?'));
        $stmt = $this->db->prepare("
            SELECT
                rbe.entry_id,
                rb.id AS report_id,
                rb.title,
                rb.status,
                rb.start_date,
                rb.end_date,
                rb.entry_count,
                rb.generated_at,
                rb.closed_at,
                rb.summary_json,
                rb.created_at
            FROM v2_report_batch_entries rbe
            INNER JOIN v2_report_batches rb ON rb.id = rbe.batch_id
            WHERE rbe.entry_id IN ({$placeholders})
              AND rb.batch_type = 'operational_fragment'
              AND rb.status IN ('created', 'sent', 'requires_update', 'returned_for_revision')
            ORDER BY rb.created_at DESC
        ");
        $stmt->execute($entryIds);
        $locks = [];
        foreach ($stmt->fetchAll() as $row) {
            if ($exceptReportId !== null && (string)$row['report_id'] === $exceptReportId) {
                continue;
            }
            $entryId = (string)$row['entry_id'];
            if (isset($locks[$entryId])) {
                continue;
            }
            $summary = FinDeskV2Support::jsonDecode($row['summary_json'] ?? '[]', []);
            $totals = is_array($summary['totals'] ?? null) ? $summary['totals'] : [];
            $locks[$entryId] = [
                'report_id' => (string)$row['report_id'],
                'title' => (string)$row['title'],
                'status' => (string)$row['status'],
                'period_start' => (string)$row['start_date'],
                'period_end' => (string)$row['end_date'],
                'entry_count' => (int)$row['entry_count'],
                'entries_count' => (int)$row['entry_count'],
                'generated_at' => $row['generated_at'] ?? null,
                'closed_at' => $row['closed_at'] ?? null,
                'ending_cash' => isset($totals['ending_cash']) ? (float)$totals['ending_cash'] : null,
                'created_at' => $row['created_at'] ?? null,
            ];
        }

        return $locks;
    }

    private function supersedeDuplicateOperationalReportFragments(string $workspaceId, array $sourceEntryIds, int $userId): array
    {
        $sourceEntryIds = array_values(array_map('strval', $sourceEntryIds));
        sort($sourceEntryIds, SORT_STRING);
        if ($sourceEntryIds === [] || !$this->reportBatchSchemaIsAvailable()) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT id, source_entry_ids_json
            FROM v2_report_batches
            WHERE workspace_id = ?
              AND batch_type = 'operational_fragment'
              AND status IN ('created', 'sent', 'requires_update', 'returned_for_revision')
              AND entry_count = ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$workspaceId, count($sourceEntryIds)]);
        $duplicates = [];
        foreach ($stmt->fetchAll() as $row) {
            $candidateIds = array_values(array_map('strval', FinDeskV2Support::jsonDecode($row['source_entry_ids_json'] ?? '[]', [])));
            sort($candidateIds, SORT_STRING);
            if ($candidateIds === $sourceEntryIds) {
                $duplicates[] = (string)$row['id'];
            }
        }
        if ($duplicates === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($duplicates), '?'));
        $this->db->prepare("
            UPDATE v2_report_batches
            SET status = 'superseded',
                closed_at = NULL,
                updated_at = CURRENT_TIMESTAMP
            WHERE workspace_id = ?
              AND batch_type = 'operational_fragment'
              AND id IN ({$placeholders})
        ")->execute(array_merge([$workspaceId], $duplicates));

        $this->audit($workspaceId, 'report_batch', $workspaceId, 'operational_fragment_duplicate_supersede', [
            'source_entry_ids' => $sourceEntryIds,
        ], [
            'superseded_report_ids' => $duplicates,
        ], $userId);

        return $duplicates;
    }

    private function activeReportLockForEntryId(string $entryId): ?array
    {
        $locks = $this->activeReportLocksForEntryIds([$entryId]);

        return $locks[$entryId] ?? null;
    }

    private function markOperationalReportsRequiringUpdateForEntry(array $entry, int $userId, string $reason): array
    {
        if (!$this->reportBatchSchemaIsAvailable()) {
            return [];
        }

        $workspaceId = (string)($entry['workspace_id'] ?? '');
        $entryId = (string)($entry['id'] ?? '');
        $entryDate = (string)($entry['date'] ?? '');
        if ($workspaceId === '' || $entryId === '' || $entryDate === '') {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT id, title, status, start_date, end_date
            FROM v2_report_batches
            WHERE workspace_id = ?
              AND batch_type = 'operational_fragment'
              AND status IN ('created', 'sent')
              AND end_date >= ?
            ORDER BY start_date ASC, created_at ASC
        ");
        $stmt->execute([$workspaceId, $entryDate]);
        $fragments = $stmt->fetchAll();
        if ($fragments === []) {
            return [];
        }

        $fragmentIds = array_values(array_map(static fn (array $row): string => (string)$row['id'], $fragments));
        $placeholders = implode(', ', array_fill(0, count($fragmentIds), '?'));
        $this->db->prepare("
            UPDATE v2_report_batches
            SET status = 'requires_update',
                updated_at = CURRENT_TIMESTAMP
            WHERE workspace_id = ?
              AND batch_type = 'operational_fragment'
              AND status IN ('created', 'sent')
              AND id IN ({$placeholders})
        ")->execute(array_merge([$workspaceId], $fragmentIds));

        $packageIds = $this->markOperationalPackagesRequiringUpdateForFragments($workspaceId, $fragmentIds);
        $after = [
            'entry_id' => $entryId,
            'entry_date' => $entryDate,
            'reason' => $reason,
            'status' => 'requires_update',
            'fragment_ids' => $fragmentIds,
            'package_ids' => $packageIds,
        ];
        $this->audit($workspaceId, 'report_batch', $entryId, 'operational_reports_require_update', ['entry' => $entry, 'reports' => $fragments], $after, $userId);

        return $after;
    }

    private function markOperationalPackagesRequiringUpdateForFragments(string $workspaceId, array $fragmentIds): array
    {
        if ($fragmentIds === [] || !$this->operationalPackageSchemaIsAvailable()) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT id, fragment_ids_json
            FROM v2_report_packages
            WHERE workspace_id = ?
              AND status IN ('created', 'sent')
        ");
        $stmt->execute([$workspaceId]);
        $affected = [];
        $fragmentLookup = array_fill_keys(array_map('strval', $fragmentIds), true);
        foreach ($stmt->fetchAll() as $row) {
            $ids = FinDeskV2Support::jsonDecode($row['fragment_ids_json'] ?? '[]', []);
            if (!is_array($ids)) {
                continue;
            }
            foreach ($ids as $id) {
                if (isset($fragmentLookup[(string)$id])) {
                    $affected[] = (string)$row['id'];
                    break;
                }
            }
        }

        if ($affected === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($affected), '?'));
        $this->db->prepare("
            UPDATE v2_report_packages
            SET status = 'requires_update',
                updated_at = CURRENT_TIMESTAMP
            WHERE workspace_id = ?
              AND status IN ('created', 'sent')
              AND id IN ({$placeholders})
        ")->execute(array_merge([$workspaceId], $affected));

        return $affected;
    }

    private function guardEntryReportLock(array $entry, array $input = []): void
    {
        $decision = FinDeskV2Support::optionalString($input, 'report_fragment_decision', null, 40);
        if ($decision !== null) {
            FinDeskV2Support::enum($decision, ['recalculate_fragment'], 'report_fragment_decision');
            return;
        }
        $lock = $this->activeReportLockForEntryId((string)$entry['id']);
        if ($lock === null) {
            return;
        }

        throw new FinDeskV2HttpError(409, FinDeskV2Support::jsonEncode([
            'error' => 'report_fragment_requires_decision',
            'report' => $lock,
            'choices' => ['recalculate_fragment', 'cancel'],
        ]));
    }

    private function operationalReportHtmlRelativePath(string $workspaceId, string $batchId): string
    {
        return 'storage/v2/report-batches/' . $workspaceId . '/' . $batchId . '.html';
    }

    private function writeOperationalReportHtmlFile(array $batch, array $workspace): void
    {
        $relativePath = (string)($batch['html_filename'] ?? '');
        if ($relativePath === '') {
            return;
        }
        $absolutePath = dirname(__DIR__, 2) . '/' . ltrim($relativePath, '/');
        $dir = dirname($absolutePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        if (@file_put_contents($absolutePath, $this->operationalReportHtml($batch, $workspace)) === false) {
            throw new FinDeskV2HttpError(500, 'report_fragment_html_store_failed');
        }
        @chmod($absolutePath, 0600);
    }

    private function adminDebtHintText(array $adminDebt, callable $money): string
    {
        $lines = ['Сводка задолженности администратора'];
        $basis = is_array($adminDebt['basis_breakdown'] ?? null) ? $adminDebt['basis_breakdown'] : [];
        if ($basis !== []) {
            $creditTotal = (float)($basis['credit_total'] ?? 0.0);
            $creditCount = $basis['credit_count'] ?? null;
            $creditUnit = $basis['credit_unit'] ?? null;
            if ($creditTotal > 0.005 && $creditCount !== null && $creditUnit !== null) {
                $lines[] = 'Основание: ' . (int)$creditCount . ' выдач по ' . $money($creditUnit) . ' = ' . $money($creditTotal);
            } elseif ($creditTotal > 0.005) {
                $lines[] = 'Основание: ' . $money($creditTotal);
            }

            $returnedTotal = (float)($basis['returned_total'] ?? 0.0);
            $returnCount = $basis['return_count'] ?? null;
            $returnUnit = $basis['return_unit'] ?? null;
            if ($returnedTotal > 0.005 && $returnCount !== null && $returnUnit !== null) {
                $lines[] = 'Возвращено до базы: ' . (int)$returnCount . ' возвратов по ' . $money($returnUnit) . ' = ' . $money($returnedTotal);
            } elseif ($returnedTotal > 0.005) {
                $lines[] = 'Возвращено до базы: ' . $money($returnedTotal);
            }

            if (isset($basis['remaining_total'])) {
                $lines[] = 'Базовый остаток: ' . $money($basis['remaining_total']);
            }
        }
        $lines[] = 'На начало периода: ' . $money($adminDebt['opening_total'] ?? 0);
        $lines[] = 'Увеличение периода: ' . $money($adminDebt['increased_total'] ?? 0);
        $lines[] = 'Возврат периода: ' . $money($adminDebt['returned_total'] ?? 0);
        $lines[] = 'Остаток: ' . $money($adminDebt['total'] ?? 0);

        return implode("\n", $lines);
    }

    private function operationalReportHtml(array $batch, array $workspace, bool $autoPrint = false): string
    {
        $summary = $batch['summary'] ?? [];
        $totals = $summary['totals'] ?? [];
        $position = is_array($summary['money_position'] ?? null) ? $summary['money_position'] : $this->reportMoneyPosition($totals['ending_cash'] ?? null);
        $entries = $batch['entry_snapshot'] ?? [];
        $categories = $summary['blocks']['categories']['rows'] ?? [];
        $adminDebt = is_array($summary['blocks']['admin_debt'] ?? null) ? $summary['blocks']['admin_debt'] : [];
        $brandMark = $this->brandAssetDataUri('findesk-mark.svg');
        $money = static function ($value): string {
            if ($value === null || $value === '') {
                return '—';
            }
            return number_format((float)$value, 2, ',', ' ') . ' €';
        };
        $h = static fn ($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $totalCashIncome = (float)($totals['total_cash_income'] ?? ((float)($totals['cash_income'] ?? 0) + (float)($totals['commercial_income'] ?? 0)));
        $entryById = [];
        foreach ($entries as $entry) {
            if (isset($entry['id'])) {
                $entryById[(string)$entry['id']] = $entry;
            }
        }
        $sourceTrace = is_array($batch['source_trace'] ?? null) ? $batch['source_trace'] : [];
        $correctionIds = $sourceTrace['totals']['corrections_total'] ?? [];
        $correctionIds = is_array($correctionIds) ? $correctionIds : [];
        $correctionRowsHtml = '';
        foreach ($correctionIds as $id) {
            $entry = $entryById[(string)$id] ?? null;
            if ($entry === null) {
                continue;
            }
            $signed = trim((string)($entry['sign'] ?? '')) . $money($entry['amount'] ?? null);
            $correctionRowsHtml .= '<div class="thread-row correction"><span>' . $h($entry['date'] ?? '') . '</span><strong>' . $h($entry['raw_text'] ?? 'Корректировка') . '</strong><em>' . $h($signed) . '</em></div>';
        }
        $previousReport = null;
        $nextReport = null;
        if (!empty($batch['workspace_id']) && !empty($batch['start_date'])) {
            $prevStmt = $this->db->prepare("
                SELECT title, summary_json
                FROM v2_report_batches
                WHERE workspace_id = ?
                  AND batch_type = 'operational_fragment'
                  AND status <> 'superseded'
                  AND end_date < ?
                ORDER BY end_date DESC, start_date DESC
                LIMIT 1
            ");
            $prevStmt->execute([$batch['workspace_id'], $batch['start_date']]);
            $previousReport = $prevStmt->fetch() ?: null;
        }
        if (!empty($batch['workspace_id']) && !empty($batch['end_date'])) {
            $nextStmt = $this->db->prepare("
                SELECT title, summary_json
                FROM v2_report_batches
                WHERE workspace_id = ?
                  AND batch_type = 'operational_fragment'
                  AND status <> 'superseded'
                  AND start_date > ?
                ORDER BY start_date ASC, end_date ASC
                LIMIT 1
            ");
            $nextStmt->execute([$batch['workspace_id'], $batch['end_date']]);
            $nextReport = $nextStmt->fetch() ?: null;
        }
        $previousSummary = $previousReport ? FinDeskV2Support::jsonDecode($previousReport['summary_json'] ?? '{}', []) : [];
        $nextSummary = $nextReport ? FinDeskV2Support::jsonDecode($nextReport['summary_json'] ?? '{}', []) : [];
        $cashThreadHtml = '<h2>Нить остатка</h2><section class="card thread">';
        if ($previousReport) {
            $cashThreadHtml .= '<div class="thread-row"><span>Предыдущий отчет</span><strong>' . $h($previousReport['title'] ?? 'Отчет') . '</strong><em>финал ' . $h($money($previousSummary['totals']['ending_cash'] ?? null)) . '</em></div>';
        }
        $cashThreadHtml .= '<div class="thread-row"><span>Входящий этой смычки</span><strong>' . $h($money($totals['opening_cash'] ?? null)) . '</strong><em>начало периода</em></div>'
            . ($correctionRowsHtml !== '' ? $correctionRowsHtml : '')
            . '<div class="thread-row"><span>Финал этой смычки</span><strong>' . $h($money($totals['ending_cash'] ?? null)) . '</strong><em>остаток отчета</em></div>';
        if ($nextReport) {
            $cashThreadHtml .= '<div class="thread-row"><span>Следующий отчет</span><strong>' . $h($nextReport['title'] ?? 'Отчет') . '</strong><em>входящий ' . $h($money($nextSummary['totals']['opening_cash'] ?? null)) . '</em></div>';
        }
        if (abs((float)($totals['corrections_total'] ?? 0)) > 0.005) {
            $cashThreadHtml .= '<p>Корректировка не является новым доходом. Это служебная стыковка, чтобы историческая лента сошлась с фактическим остатком следующего периода.</p>';
        }
        $cashThreadHtml .= '</section>';
        $categoryHtml = '';
        foreach ($categories as $category) {
            $ids = $category['source_entry_ids'] ?? [];
            $details = '';
            foreach ($ids as $id) {
                $entry = $entryById[(string)$id] ?? null;
                if ($entry === null) {
                    continue;
                }
                $details .= '<tr><td>' . $h($entry['date'] ?? '') . '</td><td>' . $h($entry['raw_text'] ?? '') . '</td><td>' . $h($money($entry['amount'] ?? null)) . '</td></tr>';
            }
            $name = $category['category_name']['ru'] ?? $category['category_code'] ?? 'Категория';
            $categoryHtml .= '<details><summary><strong>' . $h($name) . '</strong><span>' . $h($money($category['total'] ?? 0)) . '</span></summary>'
                . '<table><thead><tr><th>Дата</th><th>Запись</th><th>Сумма</th></tr></thead><tbody>' . $details . '</tbody></table></details>';
        }
        $adminDebtHtml = '';
        if (abs((float)($adminDebt['total'] ?? 0)) > 0.005 || !empty($adminDebt['entries'])) {
            $adminRows = '';
            foreach (($adminDebt['entries'] ?? []) as $entry) {
                $effect = array_key_exists('admin_debt_effect', $entry)
                    ? (float)$entry['admin_debt_effect']
                    : (((string)($entry['direction'] ?? 'out')) === 'in' ? 0 - (float)($entry['amount'] ?? 0) : (float)($entry['amount'] ?? 0));
                $adminRows .= '<tr><td>' . $h($entry['date'] ?? '') . '</td><td>' . $h($entry['raw_text'] ?? $entry['title'] ?? 'Основание') . '</td><td>' . $h($money(abs($effect))) . '</td><td>' . $h($effect < 0 ? 'уменьшает' : 'увеличивает') . '</td></tr>';
            }
            if ($adminRows === '') {
                $adminRows = '<tr><td>—</td><td>Переходящий остаток личного долга администратора</td><td>' . $h($money($adminDebt['total'] ?? 0)) . '</td><td>остаток</td></tr>';
            }
            $adminDebtHint = $this->adminDebtHintText($adminDebt, $money);
            $adminDebtHtml = '<h2>Задолженность администратора <span class="pill" title="' . $h($adminDebtHint) . '">?</span></h2><section class="card grid">'
                . '<div class="metric"><span>Остаток долга</span><strong>' . $h($money($adminDebt['total'] ?? 0)) . '</strong></div>'
                . '<div class="metric"><span>Начало периода</span><strong>' . $h($money($adminDebt['opening_total'] ?? 0)) . '</strong></div>'
                . '<div class="metric"><span>Новые личные траты</span><strong>' . $h($money($adminDebt['increased_total'] ?? 0)) . '</strong></div>'
                . '<div class="metric"><span>Возвраты</span><strong>' . $h($money($adminDebt['returned_total'] ?? 0)) . '</strong></div>'
                . '</section><section class="card"><table><thead><tr><th>Дата</th><th>Основание</th><th>Сумма</th><th>Влияние</th></tr></thead><tbody>' . $adminRows . '</tbody></table></section>';
        }
        $entriesHtml = '';
        foreach ($entries as $index => $entry) {
            $entriesHtml .= '<tr><td>' . ($index + 1) . '</td><td>' . $h($entry['date'] ?? '') . '</td><td>' . $h($entry['raw_text'] ?? '') . '</td><td>' . $h($entry['flow']['type'] ?? '') . '</td><td>' . $h($money($entry['amount'] ?? null)) . '</td></tr>';
        }
        $statusLabel = match ((string)($batch['status'] ?? 'created')) {
            'sent' => 'Отправлен',
            'requires_update' => 'Требует обновления',
            'returned_for_revision' => 'На доработке',
            'superseded' => 'Заменен',
            'draft' => 'Черновик',
            default => 'Создан',
        };
        $closedLabel = $batch['closed_at'] ? ('Закрыт: ' . substr((string)$batch['closed_at'], 0, 10)) : 'Закрытие не указано';
        $autoPrintScript = $autoPrint
            ? '<script>window.addEventListener("load",()=>setTimeout(()=>window.print(),250));</script>'
            : '';
        $detailsScript = '<script>(()=>{const details=[...document.querySelectorAll("details")];const mobile=matchMedia("(max-width:760px), (pointer:coarse)").matches;if(!mobile)details.forEach(d=>d.open=false);})();</script>';

        return '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $h($batch['title'] ?? 'Отчетный фрагмент') . '</title>'
            . ($brandMark === '' ? '' : '<link rel="icon" href="' . $h($brandMark) . '" type="image/svg+xml">')
            . '<style>body{font:14px/1.4 Inter,Arial,sans-serif;margin:0;background:#f6f8fb;color:#111827}.wrap{max-width:1040px;margin:0 auto;padding:18px}.card,details{background:#fff;border:1px solid #d8e0ea;border-radius:8px;margin:8px 0;overflow:hidden}.head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.brand-title{display:flex;gap:12px;align-items:flex-start}.brand-title img{width:42px;height:42px;flex:0 0 auto;border-radius:10px}.brand-title h1{margin:0}.muted{color:#667085}.meta{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}.pill{border:1px solid #d8e0ea;border-radius:999px;background:#fff;padding:3px 9px;color:#344054;font-size:12px;font-weight:700}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:8px}.metric{padding:12px}.metric span{display:block;color:#667085;font-size:12px}.metric strong{font-size:20px}.thread-row{display:grid;grid-template-columns:minmax(130px,.8fr) minmax(0,2.5fr) minmax(120px,.8fr);gap:12px;align-items:center;padding:10px 12px;border-top:1px solid #d8e0ea}.thread-row:first-child{border-top:0}.thread-row span,.thread-row em{color:#667085;font-size:12px;font-style:normal}.thread-row strong{font-size:13px}.thread-row.correction{background:#fffdf5}.thread p{margin:0;padding:9px 12px;border-top:1px solid #d8e0ea;color:#667085;font-size:12px}summary{display:flex;justify-content:space-between;gap:12px;padding:10px 12px;cursor:pointer}table{width:100%;border-collapse:collapse}th,td{border-top:1px solid #d8e0ea;padding:8px;text-align:left;vertical-align:top}th{color:#667085;font-size:12px;background:#f3f6fa}@media(max-width:760px){.head{display:block}.brand-title{margin-bottom:10px}.thread-row{grid-template-columns:1fr;gap:3px}}@media print{body{background:#fff}.wrap{max-width:none;padding:0}.card,details{break-inside:avoid}}</style>'
            . '</head><body><main class="wrap">'
            . '<section class="head"><div class="brand-title">' . ($brandMark === '' ? '' : '<img src="' . $h($brandMark) . '" alt="FinDesk">') . '<div><h1>' . $h($batch['title'] ?? 'Отчетный фрагмент') . '</h1><div class="muted">' . $h($workspace['name'] ?? '') . ' · ' . $h($summary['header']['range_label'] ?? '') . ' · ' . $h($batch['created_at'] ?? '') . '</div><div class="meta"><span class="pill">' . $h($statusLabel) . '</span><span class="pill">' . $h($closedLabel) . '</span></div></div></div><div class="muted">hash ' . $h(substr((string)($batch['content_hash'] ?? ''), 0, 12)) . '</div></section>'
            . '<section class="card grid">'
            . '<div class="metric"><span>Входящий</span><strong>' . $h($money($totals['opening_cash'] ?? null)) . '</strong></div>'
            . '<div class="metric"><span>Поступления всего</span><strong>' . $h($money($totalCashIncome)) . '</strong></div>'
            . '<div class="metric"><span>Коммерческие</span><strong>' . $h($money($totals['commercial_income'] ?? 0)) . '</strong></div>'
            . '<div class="metric"><span>Прочие поступления</span><strong>' . $h($money($totals['cash_income'] ?? 0)) . '</strong></div>'
            . '<div class="metric"><span>Расход</span><strong>' . $h($money($totals['cash_expense'] ?? 0)) . '</strong></div>'
            . '<div class="metric"><span>Корректировки</span><strong>' . $h($money($totals['corrections_total'] ?? 0)) . '</strong></div>'
            . '<div class="metric"><span>Остаток</span><strong>' . $h($money($totals['ending_cash'] ?? null)) . '</strong></div>'
            . '</section>' . $cashThreadHtml . '<h2>Деньги на дату отчета</h2><section class="card grid">'
            . '<div class="metric"><span>Всего физически доступно</span><strong>' . $h($money($position['physical_available_total'] ?? null)) . '</strong></div>'
            . '<div class="metric"><span>У администратора</span><strong>' . $h($money($position['admin_cash'] ?? null)) . '</strong></div>'
            . '<div class="metric"><span>У сотрудников</span><strong>' . $h($money($position['employee_held_cash'] ?? 0)) . '</strong></div>'
            . '<div class="metric"><span>К возмещению сотрудникам</span><strong>' . $h($money($position['reimburse_due_to_employees'] ?? 0)) . '</strong></div>'
            . '</section>' . $adminDebtHtml . '<h2>Категории</h2>' . ($categoryHtml ?: '<section class="card metric">Категорийных расходов нет.</section>')
            . '<h2>Записи</h2><section class="card"><table><thead><tr><th>#</th><th>Дата</th><th>Запись</th><th>Поток</th><th>Сумма</th></tr></thead><tbody>' . $entriesHtml . '</tbody></table></section>'
            . '</main>' . $detailsScript . $autoPrintScript . '</body></html>';
    }

    private function brandAssetDataUri(string $fileName): string
    {
        if (!preg_match('/^[a-z0-9._-]+$/i', $fileName)) {
            return '';
        }
        $path = dirname(__DIR__, 2) . '/public/assets/v2/' . $fileName;
        if (!is_file($path)) {
            return '';
        }
        $content = file_get_contents($path);

        return $content === false ? '' : 'data:image/svg+xml;base64,' . base64_encode($content);
    }

    private function reportPackageHtmlRelativePath(string $workspaceId, string $packageId): string
    {
        return 'storage/v2/report-packages/' . $workspaceId . '/' . $packageId . '.html';
    }

    private function writeReportPackageHtmlFile(array $package, array $workspace): void
    {
        $relativePath = (string)($package['html_filename'] ?? '');
        if ($relativePath === '') {
            return;
        }
        $absolutePath = dirname(__DIR__, 2) . '/' . ltrim($relativePath, '/');
        $dir = dirname($absolutePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        if (@file_put_contents($absolutePath, $this->reportPackageHtml($package, $workspace)) === false) {
            throw new FinDeskV2HttpError(500, 'report_package_html_store_failed');
        }
        @chmod($absolutePath, 0600);
    }

    private function reportPackageHtml(array $package, array $workspace, bool $autoPrint = false): string
    {
        $summary = $package['summary'] ?? [];
        $totals = $summary['totals'] ?? [];
        $position = is_array($summary['money_position'] ?? null) ? $summary['money_position'] : $this->reportMoneyPosition($totals['ending_cash'] ?? null);
        $fragments = $summary['blocks']['fragments'] ?? [];
        $packageFragments = $package['fragments'] ?? [];
        $categories = $summary['blocks']['categories']['rows'] ?? [];
        $adminDebt = is_array($summary['blocks']['admin_debt'] ?? null) ? $summary['blocks']['admin_debt'] : [];
        $brandMark = $this->brandAssetDataUri('findesk-mark.svg');
        $money = static function ($value): string {
            if ($value === null || $value === '') {
                return '—';
            }
            return number_format((float)$value, 2, ',', ' ') . ' €';
        };
        $h = static fn ($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $totalCashIncome = (float)($totals['total_cash_income'] ?? ((float)($totals['cash_income'] ?? 0) + (float)($totals['commercial_income'] ?? 0)));
        $entryById = [];
        foreach ($packageFragments as $fragment) {
            foreach (($fragment['entry_snapshot'] ?? $fragment['entries'] ?? []) as $entry) {
                if (isset($entry['id'])) {
                    $entryById[(string)$entry['id']] = $entry;
                }
            }
        }
        $categoryHtml = '';
        foreach ($categories as $category) {
            $ids = $category['source_entry_ids'] ?? [];
            $details = '';
            foreach ($ids as $id) {
                $entry = $entryById[(string)$id] ?? null;
                if ($entry === null) {
                    continue;
                }
                $details .= '<tr><td>' . $h($entry['date'] ?? '') . '</td><td>' . $h($entry['raw_text'] ?? '') . '</td><td>' . $h($money($entry['amount'] ?? null)) . '</td></tr>';
            }
            $name = $category['category_name']['ru'] ?? $category['category_code'] ?? 'Категория';
            $categoryHtml .= '<details><summary><strong>' . $h($name) . '</strong><span>' . $h($money($category['total'] ?? 0)) . '</span></summary>'
                . '<table><thead><tr><th>Дата</th><th>Запись</th><th>Сумма</th></tr></thead><tbody>' . $details . '</tbody></table></details>';
        }
        $adminDebtHtml = '';
        if (abs((float)($adminDebt['total'] ?? 0)) > 0.005 || !empty($adminDebt['entries'])) {
            $adminRows = '';
            foreach (($adminDebt['entries'] ?? []) as $entry) {
                $effect = array_key_exists('admin_debt_effect', $entry)
                    ? (float)$entry['admin_debt_effect']
                    : (((string)($entry['direction'] ?? 'out')) === 'in' ? 0 - (float)($entry['amount'] ?? 0) : (float)($entry['amount'] ?? 0));
                $adminRows .= '<tr><td>' . $h($entry['date'] ?? '') . '</td><td>' . $h($entry['raw_text'] ?? $entry['title'] ?? 'Основание') . '</td><td>' . $h($money(abs($effect))) . '</td><td>' . $h($effect < 0 ? 'уменьшает' : 'увеличивает') . '</td></tr>';
            }
            if ($adminRows === '') {
                $adminRows = '<tr><td>—</td><td>Переходящий остаток личного долга администратора</td><td>' . $h($money($adminDebt['total'] ?? 0)) . '</td><td>остаток</td></tr>';
            }
            $adminDebtHint = $this->adminDebtHintText($adminDebt, $money);
            $adminDebtHtml = '<h2>Задолженность администратора <span class="pill" title="' . $h($adminDebtHint) . '">?</span></h2><section class="card grid">'
                . '<div class="metric"><span>Остаток долга</span><strong>' . $h($money($adminDebt['total'] ?? 0)) . '</strong></div>'
                . '<div class="metric"><span>Начало пакета</span><strong>' . $h($money($adminDebt['opening_total'] ?? 0)) . '</strong></div>'
                . '<div class="metric"><span>Новые личные траты</span><strong>' . $h($money($adminDebt['increased_total'] ?? 0)) . '</strong></div>'
                . '<div class="metric"><span>Возвраты</span><strong>' . $h($money($adminDebt['returned_total'] ?? 0)) . '</strong></div>'
                . '</section><section class="card"><table><thead><tr><th>Дата</th><th>Основание</th><th>Сумма</th><th>Влияние</th></tr></thead><tbody>' . $adminRows . '</tbody></table></section>';
        }
        $fragmentRows = '';
        foreach ($fragments as $index => $fragment) {
            $fragmentRows .= '<tr><td>' . ($index + 1) . '</td><td>' . $h($fragment['title'] ?? '') . '</td><td>'
                . $h(($fragment['start_date'] ?? '') . ' - ' . ($fragment['end_date'] ?? '')) . '</td><td>'
                . $h((string)($fragment['entry_count'] ?? 0)) . '</td><td>'
                . $h((string)($fragment['html_snapshot_version'] ?? '')) . '</td><td>'
                . $h(substr((string)($fragment['content_hash'] ?? ''), 0, 12)) . '</td></tr>';
        }
        $statusLabel = match ((string)($package['status'] ?? 'created')) {
            'sent' => 'Отправлен',
            'requires_update' => 'Требует обновления',
            'returned_for_revision' => 'На доработке',
            'superseded' => 'Заменен',
            'draft' => 'Черновик',
            default => 'Создан',
        };
        $autoPrintScript = $autoPrint
            ? '<script>window.addEventListener("load",()=>setTimeout(()=>window.print(),250));</script>'
            : '';
        $detailsScript = '<script>(()=>{const details=[...document.querySelectorAll("details")];const mobile=matchMedia("(max-width:760px), (pointer:coarse)").matches;if(!mobile)details.forEach(d=>d.open=false);})();</script>';

        return '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $h($package['title'] ?? 'Пакет отчетов') . '</title>'
            . ($brandMark === '' ? '' : '<link rel="icon" href="' . $h($brandMark) . '" type="image/svg+xml">')
            . '<style>body{font:14px/1.4 Inter,Arial,sans-serif;margin:0;background:#f6f8fb;color:#111827}.wrap{max-width:1040px;margin:0 auto;padding:18px}.card,details{background:#fff;border:1px solid #d8e0ea;border-radius:8px;margin:8px 0;overflow:hidden}.head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.brand-title{display:flex;gap:12px;align-items:flex-start}.brand-title img{width:42px;height:42px;flex:0 0 auto;border-radius:10px}.brand-title h1{margin:0}.muted{color:#667085}.meta{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}.pill{border:1px solid #d8e0ea;border-radius:999px;background:#fff;padding:3px 9px;color:#344054;font-size:12px;font-weight:700}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:8px}.metric{padding:12px}.metric span{display:block;color:#667085;font-size:12px}.metric strong{font-size:20px}summary{display:flex;justify-content:space-between;gap:12px;padding:10px 12px;cursor:pointer}table{width:100%;border-collapse:collapse}th,td{border-top:1px solid #d8e0ea;padding:8px;text-align:left;vertical-align:top}th{color:#667085;font-size:12px;background:#f3f6fa}@media(max-width:760px){.head{display:block}.brand-title{margin-bottom:10px}}@media print{body{background:#fff}.wrap{max-width:none;padding:0}.card,details{break-inside:avoid}}</style>'
            . '</head><body><main class="wrap">'
            . '<section class="head"><div class="brand-title">' . ($brandMark === '' ? '' : '<img src="' . $h($brandMark) . '" alt="FinDesk">') . '<div><h1>' . $h($package['title'] ?? 'Пакет отчетов') . '</h1><div class="muted">' . $h($workspace['name'] ?? '') . ' · ' . $h($summary['header']['range_label'] ?? '') . ' · ' . $h($package['created_at'] ?? '') . '</div><div class="meta"><span class="pill">' . $h($statusLabel) . '</span><span class="pill">Фрагментов: ' . $h((string)($package['fragment_count'] ?? 0)) . '</span></div></div></div><div class="muted">hash ' . $h(substr((string)($package['content_hash'] ?? ''), 0, 12)) . '</div></section>'
            . '<section class="card grid">'
            . '<div class="metric"><span>Входящий</span><strong>' . $h($money($totals['opening_cash'] ?? null)) . '</strong></div>'
            . '<div class="metric"><span>Поступления всего</span><strong>' . $h($money($totalCashIncome)) . '</strong></div>'
            . '<div class="metric"><span>Коммерческие</span><strong>' . $h($money($totals['commercial_income'] ?? 0)) . '</strong></div>'
            . '<div class="metric"><span>Прочие поступления</span><strong>' . $h($money($totals['cash_income'] ?? 0)) . '</strong></div>'
            . '<div class="metric"><span>Расход</span><strong>' . $h($money($totals['cash_expense'] ?? 0)) . '</strong></div>'
            . '<div class="metric"><span>Корректировки</span><strong>' . $h($money($totals['corrections_total'] ?? 0)) . '</strong></div>'
            . '<div class="metric"><span>Остаток</span><strong>' . $h($money($totals['ending_cash'] ?? null)) . '</strong></div>'
            . '</section><h2>Деньги на дату отчета</h2><section class="card grid">'
            . '<div class="metric"><span>Всего физически доступно</span><strong>' . $h($money($position['physical_available_total'] ?? null)) . '</strong></div>'
            . '<div class="metric"><span>У администратора</span><strong>' . $h($money($position['admin_cash'] ?? null)) . '</strong></div>'
            . '<div class="metric"><span>У сотрудников</span><strong>' . $h($money($position['employee_held_cash'] ?? 0)) . '</strong></div>'
            . '<div class="metric"><span>К возмещению сотрудникам</span><strong>' . $h($money($position['reimburse_due_to_employees'] ?? 0)) . '</strong></div>'
            . '</section>' . $adminDebtHtml . '<h2>Категории</h2>' . ($categoryHtml ?: '<section class="card metric">Категорийных расходов нет.</section>')
            . '<h2>Фрагменты</h2><section class="card"><table><thead><tr><th>#</th><th>Фрагмент</th><th>Период</th><th>Записей</th><th>HTML v</th><th>Hash</th></tr></thead><tbody>' . $fragmentRows . '</tbody></table></section>'
            . '</main>' . $detailsScript . $autoPrintScript . '</body></html>';
    }

    private function getQuickNote(string $workspaceId, string $noteId, int $userId): array
    {
        $this->ensureQuickNoteSchema();
        $this->getWorkspace($workspaceId, $userId);
        $this->requireWorkspaceFullReader($workspaceId, $userId);
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_quick_notes
            WHERE id = ? AND workspace_id = ? AND archived_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$noteId, $workspaceId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new FinDeskV2HttpError(404, 'quick_note_not_found');
        }

        return $this->quickNoteRow($row);
    }

    private function quickNoteRow(array $row): array
    {
        return [
            'id' => (string)$row['id'],
            'workspace_id' => (string)$row['workspace_id'],
            'created_by' => $row['created_by'] === null ? null : (int)$row['created_by'],
            'note_date' => (string)$row['note_date'],
            'title' => (string)$row['title'],
            'raw_text' => (string)$row['raw_text'],
            'status' => (string)$row['status'],
            'smith_preview' => FinDeskV2Support::jsonDecode($row['smith_preview_json'] ?? null, []),
            'converted_at' => $row['converted_at'] === null ? null : (string)$row['converted_at'],
            'created_at' => (string)$row['created_at'],
            'updated_at' => (string)$row['updated_at'],
        ];
    }

    private function quickNoteLines(string $rawText): array
    {
        $lines = [];
        foreach (preg_split('/\R/u', $rawText) ?: [] as $line) {
            $line = trim((string)$line);
            if ($line === '' || preg_match('/^заметка\s+от\s+\d{1,2}\.\d{1,2}\.\d{2,4}$/iu', $line) === 1) {
                continue;
            }
            $lines[] = mb_substr($line, 0, 2000);
        }

        return $lines;
    }

    private function ensureQuickNoteImportSource(string $workspaceId, array $note): void
    {
        $this->db->prepare("
            INSERT IGNORE INTO v2_import_sources (
                id, workspace_id, source_type, file_name, file_id, status, include_decision, reason
            )
            VALUES (?, ?, 'quick_note', ?, ?, 'accepted', 'included', ?)
        ")->execute([
            (string)$note['id'],
            $workspaceId,
            (string)$note['title'],
            (string)$note['id'],
            'Quick note parsed by Mr. Smith',
        ]);
    }

    private function quickNoteDuplicateCandidates(string $workspaceId, array $preview): array
    {
        if (($preview['amount'] ?? null) === null || empty($preview['date'])) {
            return [];
        }
        $stmt = $this->db->prepare("
            SELECT e.*, f.type AS flow_type, f.name AS flow_name, c.code AS category_code,
                   c.name_json AS category_name_json, a.name AS actor_name
            FROM v2_entries e
            INNER JOIN v2_flows f ON f.id = e.flow_id
            LEFT JOIN v2_categories c ON c.id = e.category_id
            LEFT JOIN v2_actors a ON a.id = e.actor_id
            WHERE e.workspace_id = ?
              AND e.archived_at IS NULL
              AND e.date = ?
              AND e.amount = ?
              AND e.direction = ?
            ORDER BY e.created_seq ASC
            LIMIT 8
        ");
        $stmt->execute([
            $workspaceId,
            (string)$preview['date'],
            number_format((float)$preview['amount'], 2, '.', ''),
            (string)($preview['direction'] ?? 'none'),
        ]);

        return array_map([$this, 'entryRow'], $stmt->fetchAll());
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

        $semanticMarkers = $this->semanticMarkersFromRules($entry['matched_rules']);
        $classificationDecision = $this->classificationDecisionFromRules($entry['matched_rules']);
        $accounting = $this->accountingClassification($categoryCode, $semanticMarkers, (string)$entry['raw_text']);
        $reviewReason = $accounting['section'] === 'admin_debt' ? null : ($classificationDecision['review_reason'] ?? null);
        $settlement = $this->lowerAccountingSettlementEntry(
            (string)$entry['raw_text'],
            (string)$entry['direction'],
            $entry['amount'] === null ? null : (float)$entry['amount'],
            $accounting,
            $semanticMarkers,
            $entry['actor_name'] ?? null
        );

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
            'review_reason' => $reviewReason,
            'matched_signals' => $classificationDecision['matched_signals'] ?? [],
            'blockers' => $classificationDecision['blockers'] ?? [],
            'classification_decision' => $classificationDecision,
            'accounting_section' => $accounting['section'],
            'accounting_type' => $accounting['type'],
            'accounting_label' => $accounting['label'],
            'settlement_counterparty' => $settlement['counterparty'],
            'settlement_effect' => $settlement['effect'],
            'settlement_direction' => $settlement['direction'],
            'semantic_markers' => $semanticMarkers,
            'matched_rules' => $entry['matched_rules'],
            'will_save' => false,
        ];
    }

    private function entryRow(array $row): array
    {
        $matchedRules = FinDeskV2Support::jsonDecode($row['matched_rules_json'] ?? '[]', []);
        $semanticMarkers = $this->semanticMarkersFromRules($matchedRules);
        $classificationDecision = $this->classificationDecisionFromRules($matchedRules);
        $accounting = $this->accountingClassification($row['category_code'] ?? null, $semanticMarkers, (string)$row['raw_text']);
        $reviewReason = $accounting['section'] === 'admin_debt' ? null : ($classificationDecision['review_reason'] ?? null);
        $settlement = $this->lowerAccountingSettlementEntry(
            (string)$row['raw_text'],
            (string)$row['direction'],
            $row['amount'] === null ? null : (float)$row['amount'],
            $accounting,
            $semanticMarkers,
            $row['actor_name'] ?? null
        );
        $archiveException = $accounting['section'] === 'lower_accounting'
            ? $this->archiveLowerAccountingExceptionForEntry([
                'id' => (string)$row['workspace_id'],
                'name' => (string)($row['workspace_name'] ?? ''),
            ], $row)
            : null;

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
            'source_id' => $row['source_id'] ?? null,
            'source_row_id' => $row['source_row_id'] ?? null,
            'notes' => $row['notes'] ?? null,
            'confidence' => $row['confidence'] === null ? null : (float)$row['confidence'],
            'review_reason' => $reviewReason,
            'matched_signals' => $classificationDecision['matched_signals'] ?? [],
            'blockers' => $classificationDecision['blockers'] ?? [],
            'classification_decision' => $classificationDecision,
            'accounting_section' => $accounting['section'],
            'accounting_type' => $accounting['type'],
            'accounting_label' => $accounting['label'],
            'settlement_counterparty' => $settlement['counterparty'],
            'settlement_effect' => $settlement['effect'],
            'settlement_direction' => $settlement['direction'],
            'settlement_archive_exception' => $archiveException,
            'semantic_markers' => $semanticMarkers,
            'matched_rules' => $matchedRules,
            'report_lock' => $this->activeReportLockForEntryId((string)$row['id']),
            'created_seq' => isset($row['created_seq']) ? (int)$row['created_seq'] : null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    /** @param array<int, array<string, mixed>> $semanticMarkers */
    private function accountingClassification(?string $categoryCode, array $semanticMarkers, string $rawText = ''): array
    {
        if ($this->isAdminDebtAccountingText($rawText, $semanticMarkers)) {
            return [
                'section' => 'admin_debt',
                'type' => 'admin_debt',
                'label' => 'Administrator debt',
            ];
        }

        foreach ($semanticMarkers as $marker) {
            if (($marker['marker'] ?? null) === 'debt_or_return') {
                if (!$this->debtOrReturnMarkerRequiresLowerAccounting($categoryCode, $rawText)) {
                    continue;
                }
                return [
                    'section' => 'lower_accounting',
                    'type' => 'debt_or_return',
                    'label' => 'Debt / loan / return / accountable',
                ];
            }
            if (($marker['marker'] ?? null) === 'money_movement') {
                if ($categoryCode === 'cash_topup_from_card') {
                    continue;
                }

                return [
                    'section' => 'lower_accounting',
                    'type' => 'money_movement',
                    'label' => 'Money movement / settlement',
                ];
            }
        }

        return [
            'section' => 'operational',
            'type' => 'operational',
            'label' => 'Operational',
        ];
    }

    /** @param array<int, array<string, mixed>> $semanticMarkers */
    private function isAdminDebtAccountingText(string $rawText, array $semanticMarkers = []): bool
    {
        $text = $this->normalizedRuleText($rawText);
        if ($text === '') {
            return false;
        }

        if (preg_match('/мой кредит|моя часть кредита|кредит себе|последний кредит|мой долг|взял себе|для себя|себе|домой|с тему|temu|мото навигатор/u', $text) === 1) {
            return true;
        }

        foreach ($semanticMarkers as $marker) {
            if (($marker['marker'] ?? null) === 'non_yacht_or_personal'
                && preg_match('/порше|porsche|мото навигатор|для рф|катер рф/u', $text) === 1
            ) {
                return true;
            }
        }

        return false;
    }

    private function debtOrReturnMarkerRequiresLowerAccounting(?string $categoryCode, string $rawText): bool
    {
        $text = $this->normalizedRuleText($rawText);
        if (preg_match('/под ?отчет|подотчет|пот отчет|кредит|займ|заем|рассрочк/u', $text) === 1) {
            return true;
        }

        $hasConcreteOperationalCategory = $categoryCode !== null
            && $categoryCode !== ''
            && $categoryCode !== 'other';

        if ($hasConcreteOperationalCategory) {
            return false;
        }

        return preg_match('/долг|возврат|вернул/u', $text) === 1;
    }

    /** @param array{section: string, type: string, label: string} $accounting */
    private function lowerAccountingSettlementEntry(string $rawText, string $direction, ?float $amount, array $accounting, array $semanticMarkers, ?string $actorName): array
    {
        if ($accounting['section'] !== 'lower_accounting' || $amount === null) {
            return [
                'counterparty' => null,
                'effect' => 0.0,
                'direction' => null,
            ];
        }

        $effect = 0.0;
        $settlementDirection = 'neutral';
        if ($direction === 'out') {
            $effect = $amount;
            $settlementDirection = 'issued';
        } elseif ($direction === 'in') {
            $effect = 0 - $amount;
            $settlementDirection = 'returned';
        }

        $counterparty = $accounting['type'] === 'guest_cash_issued'
            ? 'Гости'
            : $this->lowerAccountingCounterparty($rawText, $semanticMarkers, $actorName);

        return [
            'counterparty' => $counterparty,
            'effect' => round($effect, 2),
            'direction' => $settlementDirection,
        ];
    }

    /** @param array<int, array<string, mixed>> $entries */
    private function adminDebtSummary(string $workspaceId, string $periodStart, string $periodEnd, array $entries): array
    {
        $openings = $this->adminDebtOpeningRows($workspaceId);
        if ($openings === []) {
            return [
                'count' => 0,
                'opening_total' => 0.0,
                'basis_breakdown' => null,
                'increased_total' => 0.0,
                'returned_total' => 0.0,
                'net_change' => 0.0,
                'total' => 0.0,
                'entries' => [],
                'source_entry_ids' => [],
                'basis_rows' => [],
                'basis_source_ids' => [],
            ];
        }

        $basisBreakdown = $this->adminDebtBasisBreakdown($openings);
        $basisStart = (string)$openings[0]['basis_date'];
        $openingAtStart = 0.0;
        $basisInsidePeriod = 0.0;
        $basisRows = [];
        foreach ($openings as $opening) {
            $amount = (float)$opening['amount'];
            $basisDate = (string)$opening['basis_date'];
            if ($basisDate < $periodStart) {
                $openingAtStart += $amount;
                continue;
            }
            if ($basisDate < $periodEnd) {
                $basisInsidePeriod += $amount;
                $basisRows[] = [
                    'id' => (string)$opening['id'],
                    'is_basis' => true,
                    'type' => 'admin_debt_opening',
                    'date' => $basisDate,
                    'raw_text' => (string)$opening['title'],
                    'amount' => $amount,
                    'direction' => 'out',
                    'flow_type' => 'liability',
                    'counterparty' => (string)$opening['counterparty'],
                    'note' => $opening['note'] === null ? null : (string)$opening['note'],
                ];
            }
        }

        $priorEffect = $basisStart < $periodStart
            ? $this->adminDebtEffectBefore($workspaceId, $basisStart, $periodStart)
            : 0.0;
        $openingAtStart += $priorEffect;

        $movementRows = [];
        $sourceEntryIds = [];
        $increased = 0.0;
        $returned = 0.0;
        foreach ($entries as $entry) {
            if ((string)($entry['date'] ?? '') < $basisStart) {
                continue;
            }
            $effect = $this->adminDebtEntryEffect($entry);
            if (abs($effect) < 0.005) {
                continue;
            }
            if ($effect > 0) {
                $increased += $effect;
            } else {
                $returned += abs($effect);
            }
            $movementRows[] = $entry + [
                'admin_debt_effect' => round($effect, 2),
            ];
            if (!empty($entry['id'])) {
                $this->appendSourceEntryId($sourceEntryIds, (string)$entry['id']);
            }
        }

        $netChange = $basisInsidePeriod + $increased - $returned;
        $total = $openingAtStart + $netChange;

        return [
            'count' => count($movementRows),
            'opening_total' => round($openingAtStart, 2),
            'basis_breakdown' => $basisBreakdown,
            'basis_inside_period_total' => round($basisInsidePeriod, 2),
            'increased_total' => round($increased, 2),
            'returned_total' => round($returned, 2),
            'net_change' => round($netChange, 2),
            'total' => round($total, 2),
            'entries' => array_merge($basisRows, $movementRows),
            'movement_entries' => $movementRows,
            'source_entry_ids' => $sourceEntryIds,
            'basis_rows' => $basisRows,
            'basis_source_ids' => array_map(static fn (array $row): string => (string)$row['id'], $basisRows),
        ];
    }

    /** @param array<int, array<string, mixed>> $openings */
    private function adminDebtBasisBreakdown(array $openings): ?array
    {
        if ($openings === []) {
            return null;
        }

        $opening = $openings[0];
        $source = FinDeskV2Support::jsonDecode($opening['source_json'] ?? null, []);
        $source = is_array($source) ? $source : [];
        $amount = (float)($opening['amount'] ?? 0.0);
        $creditTotal = (float)($source['confirmed_credit_total'] ?? $source['credit_total'] ?? 0.0);
        $returnedTotal = (float)($source['confirmed_returns'] ?? $source['returned_total'] ?? 0.0);

        return [
            'basis_date' => (string)($opening['basis_date'] ?? ''),
            'title' => (string)($opening['title'] ?? ''),
            'note' => $opening['note'] === null ? null : (string)$opening['note'],
            'credit_total' => round($creditTotal, 2),
            'credit_count' => isset($source['confirmed_credit_count']) ? (int)$source['confirmed_credit_count'] : null,
            'credit_unit' => isset($source['confirmed_credit_unit']) ? round((float)$source['confirmed_credit_unit'], 2) : null,
            'returned_total' => round($returnedTotal, 2),
            'return_count' => isset($source['confirmed_return_count']) ? (int)$source['confirmed_return_count'] : null,
            'return_unit' => isset($source['confirmed_return_unit']) ? round((float)$source['confirmed_return_unit'], 2) : null,
            'remaining_total' => round((float)($source['remaining_admin_debt'] ?? $amount), 2),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function adminDebtOpeningRows(string $workspaceId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM v2_workspace_liability_openings
            WHERE workspace_id = ?
              AND liability_type = 'admin_debt'
              AND archived_at IS NULL
            ORDER BY basis_date ASC, created_at ASC
        ");
        $stmt->execute([$workspaceId]);

        return $stmt->fetchAll();
    }

    private function adminDebtEffectBefore(string $workspaceId, string $basisStart, string $periodStart): float
    {
        $stmt = $this->db->prepare("
            SELECT
                e.id,
                e.date,
                e.raw_text,
                e.amount,
                e.direction,
                e.entry_type,
                e.status,
                e.matched_rules_json,
                f.type AS flow_type,
                c.code AS category_code
            FROM v2_entries e
            INNER JOIN v2_flows f ON f.id = e.flow_id
            LEFT JOIN v2_categories c ON c.id = e.category_id
            WHERE e.workspace_id = ?
              AND e.archived_at IS NULL
              AND e.date >= ?
              AND e.date < ?
        ");
        $stmt->execute([$workspaceId, $basisStart, $periodStart]);

        $effect = 0.0;
        foreach ($stmt->fetchAll() as $entry) {
            if (!$this->isCountedStatus((string)$entry['status']) || $entry['amount'] === null) {
                continue;
            }
            $semanticMarkers = $this->semanticMarkersFromRules(FinDeskV2Support::jsonDecode($entry['matched_rules_json'] ?? '[]', []));
            $accounting = $this->accountingClassification(
                $entry['category_code'] === null ? null : (string)$entry['category_code'],
                $semanticMarkers,
                (string)$entry['raw_text']
            );
            if ($accounting['section'] !== 'admin_debt') {
                continue;
            }
            $effect += $this->adminDebtEntryEffect($entry);
        }

        return round($effect, 2);
    }

    private function adminDebtEntryEffect(array $entry): float
    {
        if ($entry['amount'] === null) {
            return 0.0;
        }
        $amount = (float)$entry['amount'];
        $direction = (string)$entry['direction'];
        if ($direction === 'out') {
            return round($amount, 2);
        }
        if ($direction === 'in') {
            return round(0 - $amount, 2);
        }

        return 0.0;
    }

    /** @param array<int, array<string, mixed>> $semanticMarkers */
    private function lowerAccountingCounterparty(string $rawText, array $semanticMarkers, ?string $actorName): string
    {
        if ($actorName !== null && trim($actorName) !== '') {
            return trim($actorName);
        }

        foreach ($semanticMarkers as $marker) {
            if (isset($marker['source_actor']) && trim((string)$marker['source_actor']) !== '') {
                return trim((string)$marker['source_actor']);
            }
        }

        $text = $this->normalizedRuleText($rawText);
        foreach ($semanticMarkers as $marker) {
            if (($marker['marker'] ?? null) === 'money_movement'
                && preg_match('/для себя|вернул в кеш кассу|свои нужды|остались на карте.*сдал/u', $text) === 1
            ) {
                return 'Private/self settlement';
            }
        }

        $patterns = [
            'Леонид Владимирович' => '/\bлв\b|леонид владимирович/u',
            'Александр' => '/александр|александра|\bсаша\b|саше\b/u',
            'Наталия' => '/натали|наталь/u',
            'Арик' => '/арик|арика/u',
            'Данил' => '/данил/u',
            'Евгения' => '/евгени/u',
            'Вова' => '/\bвова\b|волод/u',
            'Женя' => '/\bженя\b|жене\b|жен[её]к/u',
            'Таможня / Duty Free' => '/тамож|дьюти\s?фри|duty\s?free/u',
        ];
        foreach ($patterns as $name => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return $name;
            }
        }

        return 'Unassigned';
    }

    private function archiveLowerAccountingExceptionForEntry(array $workspace, array $entry): ?array
    {
        $matchedRulesException = $this->archiveLowerAccountingExceptionFromMatchedRules($entry);
        if ($matchedRulesException !== null) {
            return $matchedRulesException;
        }

        foreach ($this->archiveLowerAccountingExceptions() as $exception) {
            if (!$this->archiveLowerAccountingExceptionWorkspaceMatches($workspace, $exception)) {
                continue;
            }

            $issue = is_array($exception['issue'] ?? null) ? $exception['issue'] : [];
            if (!$this->archiveLowerAccountingExceptionIssueMatches($entry, $issue)) {
                continue;
            }

            return [
                'id' => (string)($exception['id'] ?? ''),
                'counterparty' => (string)($exception['counterparty'] ?? ''),
                'closed_amount' => round((float)($exception['closed_amount'] ?? 0), 2),
                'status' => (string)($exception['status'] ?? 'closed_by_archive_exception'),
                'note' => (string)($exception['note'] ?? ''),
                'closed_by' => is_array($exception['closed_by'] ?? null) ? $exception['closed_by'] : [],
                'reported_breakdown' => is_array($exception['reported_breakdown'] ?? null) ? $exception['reported_breakdown'] : [],
            ];
        }

        return null;
    }

    private function archiveLowerAccountingExceptionFromMatchedRules(array $entry): ?array
    {
        $matchedRules = [];
        if (isset($entry['matched_rules']) && is_array($entry['matched_rules'])) {
            $matchedRules = $entry['matched_rules'];
        } else {
            $matchedRules = FinDeskV2Support::jsonDecode($entry['matched_rules_json'] ?? '[]', []);
            if (!is_array($matchedRules)) {
                $matchedRules = [];
            }
        }

        foreach ($matchedRules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            if (($rule['source'] ?? null) === 'archive_exception') {
                $closedAmount = round(max(0.0, (float)($rule['closed_amount'] ?? 0.0)), 2);
                if ($closedAmount <= 0) {
                    continue;
                }

                return [
                    'id' => (string)($rule['exception_id'] ?? $rule['id'] ?? ''),
                    'counterparty' => (string)($rule['counterparty'] ?? ''),
                    'closed_amount' => $closedAmount,
                    'status' => (string)($rule['status'] ?? 'closed_by_archive_exception'),
                    'note' => (string)($rule['note'] ?? 'Closed by archive exception embedded in entry rules.'),
                    'closed_by' => is_array($rule['closed_by'] ?? null) ? $rule['closed_by'] : [],
                    'reported_breakdown' => is_array($rule['reported_breakdown'] ?? null) ? $rule['reported_breakdown'] : [],
                ];
            }

            if (($rule['reason'] ?? null) === 'credit_share_user_confirmed') {
                $amount = max(0.0, (float)($entry['amount'] ?? 0.0));
                if ($amount <= 0) {
                    continue;
                }

                return [
                    'id' => 'credit-share-user-confirmed-' . (string)($entry['id'] ?? ''),
                    'counterparty' => 'Кредитная цепочка',
                    'closed_amount' => round($amount, 2),
                    'status' => 'closed_by_archive_exception',
                    'note' => 'User confirmed this as part of the historical credit repayment chain; it is not open accountable cash.',
                    'closed_by' => [],
                    'reported_breakdown' => [],
                ];
            }
        }

        return null;
    }

    private function archiveLowerAccountingExceptionWorkspaceMatches(array $workspace, array $exception): bool
    {
        $workspaceId = trim((string)($exception['workspace_id'] ?? ''));
        if ($workspaceId !== '' && $workspaceId !== (string)($workspace['id'] ?? '')) {
            return false;
        }

        $workspaceName = trim((string)($exception['workspace_name'] ?? ''));
        if ($workspaceName !== '' && $workspaceName !== (string)($workspace['name'] ?? '')) {
            return false;
        }

        return true;
    }

    private function archiveLowerAccountingExceptionIssueMatches(array $entry, array $issue): bool
    {
        $entryId = trim((string)($issue['entry_id'] ?? ''));
        if ($entryId !== '' && $entryId === (string)($entry['id'] ?? '')) {
            return true;
        }

        $sourceRowId = trim((string)($issue['source_row_id'] ?? ''));
        if ($sourceRowId !== '' && $sourceRowId === (string)($entry['source_row_id'] ?? '')) {
            return true;
        }

        $date = trim((string)($issue['date'] ?? ''));
        $rawText = trim((string)($issue['raw_text'] ?? ''));
        $amount = array_key_exists('amount', $issue) ? (float)$issue['amount'] : null;

        if ($date !== '' && $date !== (string)($entry['date'] ?? '')) {
            return false;
        }
        if ($rawText !== '' && $rawText !== trim((string)($entry['raw_text'] ?? ''))) {
            return false;
        }
        if ($amount !== null && abs($amount - (float)($entry['amount'] ?? 0)) > 0.001) {
            return false;
        }

        return $date !== '' || $rawText !== '' || $amount !== null;
    }

    /** @return array<int, array<string, mixed>> */
    private function archiveLowerAccountingExceptions(): array
    {
        if ($this->archiveLowerAccountingExceptions !== null) {
            return $this->archiveLowerAccountingExceptions;
        }

        $config = function_exists('ql_config') ? ql_config() : [];
        $storagePath = (string)($config['storage_path'] ?? dirname(__DIR__, 2) . '/storage');
        $path = rtrim($storagePath, '/') . '/imports/lower-accounting-archive-exceptions.json';
        if (!is_file($path)) {
            $this->archiveLowerAccountingExceptions = [];
            return $this->archiveLowerAccountingExceptions;
        }

        $raw = @file_get_contents($path);
        $decoded = FinDeskV2Support::jsonDecode(is_string($raw) ? $raw : null, []);
        $exceptions = is_array($decoded['exceptions'] ?? null) ? $decoded['exceptions'] : [];

        $this->archiveLowerAccountingExceptions = array_values(array_filter(
            $exceptions,
            static fn ($exception): bool => is_array($exception)
        ));

        return $this->archiveLowerAccountingExceptions;
    }

    /** @param array<int, array<string, mixed>> $entries */
    private function lowerAccountingSettlementSummary(array $entries): array
    {
        $groups = [];
        foreach ($entries as $entry) {
            $counterparty = (string)($entry['settlement_counterparty'] ?? 'Unassigned');
            if ($counterparty === '') {
                $counterparty = 'Unassigned';
            }
            $key = mb_strtolower($counterparty);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'counterparty' => $counterparty,
                    'needs_actor' => $counterparty === 'Unassigned',
                    'issued_total' => 0.0,
                    'returned_total' => 0.0,
                    'archive_closed_total' => 0.0,
                    'archive_exception_count' => 0,
                    'net_open' => 0.0,
                    'open_amount' => 0.0,
                    'over_returned_amount' => 0.0,
                    'status' => 'neutral',
                    'needs_review_reason' => null,
                    'entry_count' => 0,
                    'source_entry_ids' => [],
                    'archive_exceptions' => [],
                ];
            }

            $effect = (float)($entry['settlement_effect'] ?? 0.0);
            if ($effect > 0) {
                $groups[$key]['issued_total'] += $effect;
            } elseif ($effect < 0) {
                $groups[$key]['returned_total'] += abs($effect);
            }
            $groups[$key]['net_open'] += $effect;
            $groups[$key]['entry_count']++;
            if (isset($entry['id'])) {
                $this->appendSourceEntryId($groups[$key]['source_entry_ids'], (string)$entry['id']);
            }

            $archiveException = is_array($entry['settlement_archive_exception'] ?? null) ? $entry['settlement_archive_exception'] : null;
            if ($archiveException !== null && $effect > 0) {
                $closedAmount = min($effect, max(0.0, (float)($archiveException['closed_amount'] ?? 0.0)));
                if ($closedAmount > 0) {
                    $groups[$key]['archive_closed_total'] += $closedAmount;
                    $groups[$key]['archive_exception_count']++;
                    $groups[$key]['net_open'] -= $closedAmount;
                    $groups[$key]['archive_exceptions'][] = $archiveException;
                }
            }
        }

        $summary = [
            'issued_total' => 0.0,
            'returned_total' => 0.0,
            'net_open_total' => 0.0,
            'open_count' => 0,
            'partial_count' => 0,
            'closed_count' => 0,
            'over_returned_count' => 0,
            'by_counterparty' => [],
        ];

        foreach ($groups as $group) {
            $group['issued_total'] = round((float)$group['issued_total'], 2);
            $group['returned_total'] = round((float)$group['returned_total'], 2);
            $group['archive_closed_total'] = round((float)$group['archive_closed_total'], 2);
            $group['net_open'] = round((float)$group['net_open'], 2);
            $group['open_amount'] = max(0.0, $group['net_open']);
            $group['over_returned_amount'] = max(0.0, 0 - $group['net_open']);
            $closedCredit = $group['returned_total'] + $group['archive_closed_total'];

            if (abs($group['net_open']) < 0.001 && $group['issued_total'] > 0 && $closedCredit > 0) {
                $group['status'] = $group['archive_closed_total'] > 0 ? 'closed_archive_exception' : 'closed';
                $summary['closed_count']++;
            } elseif ($group['needs_actor']) {
                $group['status'] = 'needs_actor';
                $group['needs_review_reason'] = 'counterparty_not_resolved';
            } elseif ($group['issued_total'] <= 0 && $group['returned_total'] > 0) {
                $group['status'] = 'review';
                $group['needs_review_reason'] = 'return_without_issue';
            } elseif ($group['issued_total'] > 0 && $closedCredit > 0 && $group['net_open'] > 0) {
                $group['status'] = 'partial';
                $summary['partial_count']++;
                $summary['open_count']++;
            } elseif ($group['net_open'] > 0) {
                $group['status'] = 'open';
                $summary['open_count']++;
            } elseif ($group['net_open'] < 0) {
                $group['status'] = 'review';
                $group['needs_review_reason'] = 'returned_more_than_issued';
                $summary['over_returned_count']++;
            }

            $summary['issued_total'] += $group['issued_total'];
            $summary['returned_total'] += $group['returned_total'];
            $summary['net_open_total'] += $group['open_amount'];
            $summary['by_counterparty'][] = $group;
        }

        usort($summary['by_counterparty'], static function (array $a, array $b): int {
            $amountCompare = (float)$b['open_amount'] <=> (float)$a['open_amount'];
            return $amountCompare !== 0 ? $amountCompare : strcmp((string)$a['counterparty'], (string)$b['counterparty']);
        });

        $summary['issued_total'] = round((float)$summary['issued_total'], 2);
        $summary['returned_total'] = round((float)$summary['returned_total'], 2);
        $summary['net_open_total'] = round((float)$summary['net_open_total'], 2);

        return $summary;
    }
}
