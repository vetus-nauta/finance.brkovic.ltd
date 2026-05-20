<?php

require __DIR__ . '/../app/auth.php';
require __DIR__ . '/../app/ledger.php';
require __DIR__ . '/../app/groups.php';
require __DIR__ . '/../app/messages.php';
require __DIR__ . '/../app/business.php';
require __DIR__ . '/../app/on_the_go.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'request_code') {
        $input = ql_input();
        ql_json(ql_issue_code((string)($input['email'] ?? '')));
    }

    if ($action === 'verify_code') {
        $input = ql_input();
        ql_json(ql_verify_code((string)($input['email'] ?? ''), (string)($input['code'] ?? '')));
    }

    if ($action === 'current_user') {
        ql_json(['ok' => true, 'user' => ql_current_user()]);
    }


        if ($action === 'on_the_go_create') {
        ql_json(ql_on_the_go_create(ql_input()));
    }

    if ($action === 'on_the_go_list') {
        ql_json(ql_on_the_go_list(ql_input()));
    }

    if ($action === 'on_the_go_tape_create') {
        ql_json(ql_on_the_go_tape_create(ql_input()));
    }

    if ($action === 'on_the_go_tape_list') {
        ql_json(ql_on_the_go_tape_list(ql_input()));
    }

    if ($action === 'on_the_go_session_list') {
        ql_json(ql_on_the_go_session_list(ql_input()));
    }

    if ($action === 'on_the_go_close_session') {
        ql_json(ql_on_the_go_close_session(ql_input()));
    }


    if ($action === 'on_the_go_session_detail') {
        ql_json(ql_on_the_go_session_detail(ql_input()));
    }

    if ($action === 'on_the_go_activate_session') {
        ql_json(ql_on_the_go_activate_session(ql_input()));
    }

    if ($action === 'on_the_go_archive_session') {
        ql_json(ql_on_the_go_archive_session(ql_input()));
    }

    if ($action === 'on_the_go_upload_file') {
        ql_json(ql_on_the_go_upload_file());
    }

    if ($action === 'on_the_go_file_list') {
        ql_json(ql_on_the_go_file_list(ql_input()));
    }

    if ($action === 'on_the_go_file_delete') {
        ql_json(ql_on_the_go_file_delete(ql_input()));
    }

    if ($action === 'on_the_go_file_download') {
        ql_on_the_go_file_download();
    }

    if ($action === 'on_the_go_update') {
        ql_json(ql_on_the_go_update(ql_input()));
    }

    if ($action === 'on_the_go_archive') {
        ql_json(ql_on_the_go_archive(ql_input()));
    }

    if ($action === 'on_the_go_convert_to_ledger') {
        ql_json(ql_on_the_go_convert_to_ledger(ql_input()));
    }

if ($action === 'ledger_create') {
        ql_json(ql_ledger_create(ql_input()));
    }

    if ($action === 'ledger_list') {
        ql_json(ql_ledger_list(ql_input()));
    }

    if ($action === 'ledger_detail') {
        ql_json(ql_ledger_detail(ql_input()));
    }

    if ($action === 'ledger_file_list') {
        ql_json(ql_ledger_file_list(ql_input()));
    }

    if ($action === 'ledger_file_download') {
        ql_ledger_file_download();
    }

    if ($action === 'ledger_update') {
        ql_json(ql_ledger_update(ql_input()));
    }


    if ($action === 'ledger_delete') {
        ql_json(ql_ledger_delete(ql_input()));
    }


    if ($action === 'ledger_upload_file') {
        ql_json(ql_ledger_upload_file());
    }


    if ($action === 'ledger_report') {
        ql_json(ql_ledger_report(ql_input()));
    }


    if ($action === 'group_create') {
        ql_json(ql_group_create(ql_input()));
    }

    if ($action === 'group_list') {
        ql_json(ql_group_list(ql_input()));
    }

    if ($action === 'group_rename') {
        ql_json(ql_group_rename(ql_input()));
    }

    if ($action === 'group_invite_create') {
        ql_json(ql_group_invite_create(ql_input()));
    }

    if ($action === 'group_join') {
        ql_json(ql_group_join(ql_input()));
    }

    if ($action === 'group_members') {
        ql_json(ql_group_members(ql_input()));
    }

    if ($action === 'group_member_access_update') {
        ql_json(ql_group_member_access_update(ql_input()));
    }


    if ($action === 'category_list') {
        ql_json(ql_category_list(ql_input()));
    }

    if ($action === 'category_create') {
        ql_json(ql_category_create(ql_input()));
    }


    if ($action === 'message_send') {
        ql_json(ql_message_send(ql_input()));
    }

    if ($action === 'message_list') {
        ql_json(ql_message_list(ql_input()));
    }

    if ($action === 'message_unread') {
        ql_json(ql_message_unread(ql_input()));
    }

    if ($action === 'message_mark_read') {
        ql_json(ql_message_mark_read(ql_input()));
    }


    if ($action === 'company_profile_get') {
        ql_json(ql_company_profile_get(ql_input()));
    }

    if ($action === 'company_profile_save') {
        ql_json(ql_company_profile_save(ql_input()));
    }

    if ($action === 'client_create') {
        ql_json(ql_client_create(ql_input()));
    }

    if ($action === 'client_list') {
        ql_json(ql_client_list(ql_input()));
    }

    if ($action === 'proforma_create') {
        ql_json(ql_proforma_create(ql_input()));
    }

    if ($action === 'proforma_list') {
        ql_json(ql_proforma_list(ql_input()));
    }

    if ($action === 'proforma_get') {
        ql_json(ql_proforma_get(ql_input()));
    }

    if ($action === 'logout') {
        ql_logout();
        ql_json(['ok' => true]);
    }

    ql_json(['ok' => false, 'error' => 'unknown_action'], 404);
} catch (Throwable $e) {
    ql_json(['ok' => false, 'error' => 'server_error', 'message' => $e->getMessage()], 500);
}
