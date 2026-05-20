<?php

require_once __DIR__ . '/messages.php';

function ql_bd_money($value): string
{
    $raw = str_replace(',', '.', trim((string)$value));

    if ($raw === '') {
        return '0.00';
    }

    if (!preg_match('/^\d+(\.\d{1,2})?$/', $raw)) {
        return '0.00';
    }

    return number_format((float)$raw, 2, '.', '');
}

function ql_bd_rate($value): string
{
    $raw = str_replace(',', '.', trim((string)$value));

    if ($raw === '') {
        return '0.00';
    }

    if (!preg_match('/^\d+(\.\d{1,2})?$/', $raw)) {
        return '0.00';
    }

    $n = max(0, min(100, (float)$raw));
    return number_format($n, 2, '.', '');
}

function ql_company_profile_get(array $input = []): array
{
    $user = ql_require_user();

    $stmt = ql_db()->prepare("
        SELECT *
        FROM company_profiles
        WHERE user_id = ?
          AND deleted_at IS NULL
        ORDER BY is_default DESC, id ASC
        LIMIT 1
    ");
    $stmt->execute([(int)$user['id']]);

    $profile = $stmt->fetch();

    return ['ok' => true, 'profile' => $profile ?: null];
}

function ql_company_profile_save(array $input): array
{
    $user = ql_require_user();

    $data = [
        'profile_name' => trim((string)($input['profile_name'] ?? 'Default company')) ?: 'Default company',
        'company_name' => trim((string)($input['company_name'] ?? '')) ?: null,
        'address' => trim((string)($input['address'] ?? '')) ?: null,
        'city' => trim((string)($input['city'] ?? '')) ?: null,
        'country' => trim((string)($input['country'] ?? '')) ?: null,
        'email' => trim((string)($input['email'] ?? '')) ?: null,
        'phone' => trim((string)($input['phone'] ?? '')) ?: null,
        'website' => trim((string)($input['website'] ?? '')) ?: null,
        'registration_number' => trim((string)($input['registration_number'] ?? '')) ?: null,
        'vat_number' => trim((string)($input['vat_number'] ?? '')) ?: null,
        'default_vat_rate' => ql_bd_rate($input['default_vat_rate'] ?? '0'),
        'default_discount_rate' => ql_bd_rate($input['default_discount_rate'] ?? '0'),
        'currency' => strtoupper(substr(trim((string)($input['currency'] ?? 'EUR')), 0, 3)) ?: 'EUR',
        'notes' => trim((string)($input['notes'] ?? '')) ?: null,
    ];

    $db = ql_db();

    $existing = $db->prepare("
        SELECT id
        FROM company_profiles
        WHERE user_id = ?
          AND deleted_at IS NULL
        ORDER BY is_default DESC, id ASC
        LIMIT 1
    ");
    $existing->execute([(int)$user['id']]);
    $row = $existing->fetch();

    if ($row) {
        $stmt = $db->prepare("
            UPDATE company_profiles
            SET
                profile_name = ?,
                company_name = ?,
                address = ?,
                city = ?,
                country = ?,
                email = ?,
                phone = ?,
                website = ?,
                registration_number = ?,
                vat_number = ?,
                default_vat_rate = ?,
                default_discount_rate = ?,
                currency = ?,
                notes = ?
            WHERE id = ?
              AND user_id = ?
        ");

        $stmt->execute([
            $data['profile_name'],
            $data['company_name'],
            $data['address'],
            $data['city'],
            $data['country'],
            $data['email'],
            $data['phone'],
            $data['website'],
            $data['registration_number'],
            $data['vat_number'],
            $data['default_vat_rate'],
            $data['default_discount_rate'],
            $data['currency'],
            $data['notes'],
            (int)$row['id'],
            (int)$user['id'],
        ]);
    } else {
        $stmt = $db->prepare("
            INSERT INTO company_profiles
                (user_id, profile_name, company_name, address, city, country, email, phone, website, registration_number, vat_number, default_vat_rate, default_discount_rate, currency, notes, is_default)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");

        $stmt->execute([
            (int)$user['id'],
            $data['profile_name'],
            $data['company_name'],
            $data['address'],
            $data['city'],
            $data['country'],
            $data['email'],
            $data['phone'],
            $data['website'],
            $data['registration_number'],
            $data['vat_number'],
            $data['default_vat_rate'],
            $data['default_discount_rate'],
            $data['currency'],
            $data['notes'],
        ]);
    }

    return ql_company_profile_get([]);
}

function ql_client_create(array $input): array
{
    $user = ql_require_user();

    $clientName = trim((string)($input['client_name'] ?? ''));

    if ($clientName === '') {
        return ['ok' => false, 'error' => 'empty_client_name'];
    }

    $stmt = ql_db()->prepare("
        INSERT INTO clients
            (user_id, client_name, contact_person, email, phone, address, city, country, registration_number, vat_number, notes)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        (int)$user['id'],
        $clientName,
        trim((string)($input['contact_person'] ?? '')) ?: null,
        trim((string)($input['email'] ?? '')) ?: null,
        trim((string)($input['phone'] ?? '')) ?: null,
        trim((string)($input['address'] ?? '')) ?: null,
        trim((string)($input['city'] ?? '')) ?: null,
        trim((string)($input['country'] ?? '')) ?: null,
        trim((string)($input['registration_number'] ?? '')) ?: null,
        trim((string)($input['vat_number'] ?? '')) ?: null,
        trim((string)($input['notes'] ?? '')) ?: null,
    ]);

    return ['ok' => true, 'client' => ql_client_get((int)ql_db()->lastInsertId(), (int)$user['id'])];
}

function ql_client_get(int $clientId, int $userId): ?array
{
    $stmt = ql_db()->prepare("
        SELECT *
        FROM clients
        WHERE id = ?
          AND user_id = ?
          AND deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$clientId, $userId]);

    $client = $stmt->fetch();
    return $client ?: null;
}

function ql_client_list(array $input = []): array
{
    $user = ql_require_user();

    $stmt = ql_db()->prepare("
        SELECT *
        FROM clients
        WHERE user_id = ?
          AND deleted_at IS NULL
        ORDER BY client_name ASC, id DESC
    ");
    $stmt->execute([(int)$user['id']]);

    return ['ok' => true, 'clients' => $stmt->fetchAll()];
}

function ql_next_proforma_number(int $userId): string
{
    $prefix = 'PF-' . date('Y') . '-';

    $stmt = ql_db()->prepare("
        SELECT proforma_number
        FROM proformas
        WHERE user_id = ?
          AND proforma_number LIKE ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([$userId, $prefix . '%']);
    $last = $stmt->fetchColumn();

    $n = 1;

    if ($last && preg_match('/(\d+)$/', $last, $m)) {
        $n = (int)$m[1] + 1;
    }

    return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

function ql_proforma_calculate(array $items, float $vatRate, float $discountRate): array
{
    $cleanItems = [];
    $subtotal = 0.0;
    $order = 1;

    foreach ($items as $item) {
        $name = trim((string)($item['item_name'] ?? ''));

        if ($name === '') {
            continue;
        }

        $qty = (float)ql_bd_money($item['quantity'] ?? '1');
        if ($qty <= 0) {
            $qty = 1.0;
        }

        $unitPrice = (float)ql_bd_money($item['unit_price'] ?? '0');
        $lineSubtotal = round($qty * $unitPrice, 2);

        $subtotal += $lineSubtotal;

        $cleanItems[] = [
            'item_order' => $order++,
            'item_name' => $name,
            'item_description' => trim((string)($item['item_description'] ?? '')) ?: null,
            'quantity' => number_format($qty, 2, '.', ''),
            'unit_name' => trim((string)($item['unit_name'] ?? 'pcs')) ?: 'pcs',
            'unit_price' => number_format($unitPrice, 2, '.', ''),
            'line_subtotal' => number_format($lineSubtotal, 2, '.', ''),
        ];
    }

    $discountAmount = round($subtotal * ($discountRate / 100), 2);
    $afterDiscount = max(0, $subtotal - $discountAmount);
    $vatAmount = round($afterDiscount * ($vatRate / 100), 2);
    $total = round($afterDiscount + $vatAmount, 2);

    return [
        'items' => $cleanItems,
        'subtotal' => number_format($subtotal, 2, '.', ''),
        'discount_amount' => number_format($discountAmount, 2, '.', ''),
        'vat_amount' => number_format($vatAmount, 2, '.', ''),
        'total_amount' => number_format($total, 2, '.', ''),
    ];
}

function ql_proforma_create(array $input): array
{
    $user = ql_require_user();

    $items = $input['items'] ?? [];
    if (!is_array($items)) {
        $items = [];
    }

    $vatRate = (float)ql_bd_rate($input['vat_rate'] ?? '0');
    $discountRate = (float)ql_bd_rate($input['discount_rate'] ?? '0');
    $calc = ql_proforma_calculate($items, $vatRate, $discountRate);

    if (!count($calc['items'])) {
        return ['ok' => false, 'error' => 'empty_items'];
    }

    $companyProfileId = (int)($input['company_profile_id'] ?? 0);
    $clientId = (int)($input['client_id'] ?? 0);

    if ($companyProfileId <= 0) {
        $profile = ql_company_profile_get([])['profile'] ?? null;
        $companyProfileId = $profile ? (int)$profile['id'] : null;
    }

    if ($clientId <= 0) {
        $clientId = null;
    }

    $issueDate = trim((string)($input['issue_date'] ?? date('Y-m-d')));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $issueDate)) {
        $issueDate = date('Y-m-d');
    }

    $dueDate = trim((string)($input['due_date'] ?? ''));
    if ($dueDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
        $dueDate = '';
    }

    $currency = strtoupper(substr(trim((string)($input['currency'] ?? 'EUR')), 0, 3)) ?: 'EUR';
    $number = trim((string)($input['proforma_number'] ?? ''));

    if ($number === '') {
        $number = ql_next_proforma_number((int)$user['id']);
    }

    $db = ql_db();
    $db->beginTransaction();

    try {
        $stmt = $db->prepare("
            INSERT INTO proformas
                (user_id, company_profile_id, client_id, proforma_number, title, issue_date, due_date, currency, vat_rate, discount_rate, subtotal, discount_amount, vat_amount, total_amount, status, public_note, internal_note)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?)
        ");

        $stmt->execute([
            (int)$user['id'],
            $companyProfileId,
            $clientId,
            $number,
            trim((string)($input['title'] ?? 'Proforma')) ?: 'Proforma',
            $issueDate,
            $dueDate ?: null,
            $currency,
            number_format($vatRate, 2, '.', ''),
            number_format($discountRate, 2, '.', ''),
            $calc['subtotal'],
            $calc['discount_amount'],
            $calc['vat_amount'],
            $calc['total_amount'],
            trim((string)($input['public_note'] ?? '')) ?: null,
            trim((string)($input['internal_note'] ?? '')) ?: null,
        ]);

        $proformaId = (int)$db->lastInsertId();

        $itemStmt = $db->prepare("
            INSERT INTO proforma_items
                (proforma_id, item_order, item_name, item_description, quantity, unit_name, unit_price, line_subtotal)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($calc['items'] as $item) {
            $itemStmt->execute([
                $proformaId,
                $item['item_order'],
                $item['item_name'],
                $item['item_description'],
                $item['quantity'],
                $item['unit_name'],
                $item['unit_price'],
                $item['line_subtotal'],
            ]);
        }

        $db->commit();

        return ['ok' => true, 'proforma' => ql_proforma_get_by_id($proformaId, (int)$user['id'])];
    } catch (Throwable $e) {
        $db->rollBack();
        return ['ok' => false, 'error' => 'server_error', 'message' => $e->getMessage()];
    }
}

function ql_proforma_get_by_id(int $proformaId, int $userId): ?array
{
    $stmt = ql_db()->prepare("
        SELECT
            p.*,
            cp.company_name,
            cp.address AS company_address,
            cp.city AS company_city,
            cp.country AS company_country,
            cp.email AS company_email,
            cp.phone AS company_phone,
            cp.registration_number AS company_registration_number,
            cp.vat_number AS company_vat_number,
            c.client_name,
            c.contact_person AS client_contact_person,
            c.email AS client_email,
            c.phone AS client_phone,
            c.address AS client_address,
            c.city AS client_city,
            c.country AS client_country,
            c.registration_number AS client_registration_number,
            c.vat_number AS client_vat_number
        FROM proformas p
        LEFT JOIN company_profiles cp ON cp.id = p.company_profile_id
        LEFT JOIN clients c ON c.id = p.client_id
        WHERE p.id = ?
          AND p.user_id = ?
          AND p.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->execute([$proformaId, $userId]);

    $proforma = $stmt->fetch();

    if (!$proforma) {
        return null;
    }

    $items = ql_db()->prepare("
        SELECT *
        FROM proforma_items
        WHERE proforma_id = ?
          AND deleted_at IS NULL
        ORDER BY item_order ASC, id ASC
    ");
    $items->execute([$proformaId]);

    $proforma['items'] = $items->fetchAll();

    return $proforma;
}

function ql_proforma_get(array $input): array
{
    $user = ql_require_user();
    $id = (int)($input['id'] ?? 0);

    if ($id <= 0) {
        return ['ok' => false, 'error' => 'invalid_proforma_id'];
    }

    $proforma = ql_proforma_get_by_id($id, (int)$user['id']);

    if (!$proforma) {
        return ['ok' => false, 'error' => 'proforma_not_found'];
    }

    return ['ok' => true, 'proforma' => $proforma];
}

function ql_proforma_list(array $input = []): array
{
    $user = ql_require_user();

    $stmt = ql_db()->prepare("
        SELECT
            p.id,
            p.proforma_number,
            p.title,
            p.issue_date,
            p.due_date,
            p.currency,
            p.total_amount,
            p.status,
            p.created_at,
            c.client_name
        FROM proformas p
        LEFT JOIN clients c ON c.id = p.client_id
        WHERE p.user_id = ?
          AND p.deleted_at IS NULL
        ORDER BY p.created_at DESC, p.id DESC
        LIMIT 100
    ");
    $stmt->execute([(int)$user['id']]);

    return ['ok' => true, 'proformas' => $stmt->fetchAll()];
}
