<?php

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/ledger.php';
require_once __DIR__ . '/../app/groups.php';
require_once __DIR__ . '/../app/messages.php';
require_once __DIR__ . '/../app/business.php';
require_once __DIR__ . '/../app/on_the_go.php';
require_once __DIR__ . '/../app/advances.php';
$aiModulePath = __DIR__ . '/../app/ai.php';
if (is_file($aiModulePath)) {
    require_once $aiModulePath;
}

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

    if ($action === 'audit_list') {
        if (!function_exists('ql_audit_list')) {
            ql_json(['ok' => false, 'error' => 'audit_unavailable']);
        }
        ql_json(ql_audit_list(ql_input()));
    }

    if ($action === 'ai_analysis_run') {
        if (!function_exists('ql_ai_analysis_run')) {
            ql_json(['ok' => false, 'error' => 'ai_unavailable']);
        }
        ql_json(ql_ai_analysis_run(ql_input()));
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

    if ($action === 'on_the_go_signed_sync') {
        ql_json(ql_on_the_go_signed_sync(ql_input()));
    }

    if ($action === 'on_the_go_field_draft_save') {
        ql_json(ql_on_the_go_field_draft_save(ql_input()));
    }

    if ($action === 'on_the_go_field_recover') {
        ql_json(ql_on_the_go_field_recover(ql_input()));
    }

    if ($action === 'on_the_go_proof_state_begin') {
        ql_json(ql_on_the_go_proof_state_begin(ql_input()));
    }

    if ($action === 'on_the_go_proof_state_fail') {
        ql_json(ql_on_the_go_proof_state_fail(ql_input()));
    }

    if ($action === 'on_the_go_proof_state_list') {
        ql_json(ql_on_the_go_proof_state_list(ql_input()));
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

    if ($action === 'on_the_go_submit_to_ledger') {
        ql_json(ql_on_the_go_submit_to_ledger(ql_input()));
    }

    if ($action === 'on_the_go_card_list') {
        ql_json(ql_on_the_go_card_list(ql_input()));
    }

    if ($action === 'on_the_go_card_detail') {
        ql_json(ql_on_the_go_card_detail(ql_input()));
    }

    if ($action === 'on_the_go_card_submit') {
        ql_json(ql_on_the_go_card_submit(ql_input()));
    }

    if ($action === 'on_the_go_card_include') {
        ql_json(ql_on_the_go_card_include(ql_input()));
    }

    if ($action === 'on_the_go_card_uninclude') {
        ql_json(ql_on_the_go_card_uninclude(ql_input()));
    }

    if ($action === 'on_the_go_card_unsubmit') {
        ql_json(ql_on_the_go_card_unsubmit(ql_input()));
    }

    if ($action === 'on_the_go_card_archive_completed') {
        ql_json(ql_on_the_go_card_archive_completed(ql_input()));
    }

    if ($action === 'on_the_go_card_request_return') {
        ql_json(ql_on_the_go_card_request_return(ql_input()));
    }

    if ($action === 'on_the_go_card_delete') {
        ql_json(ql_on_the_go_card_delete(ql_input()));
    }

    if ($action === 'on_the_go_journal_export') {
        ql_json(ql_on_the_go_journal_export(ql_input()));
    }

    if ($action === 'on_the_go_journal_download') {
        ql_on_the_go_journal_download();
    }

    if ($action === 'on_the_go_report_list') {
        ql_json(ql_on_the_go_report_list(ql_input()));
    }

    if ($action === 'advance_create') {
        ql_json(ql_advance_create(ql_input()));
    }

    if ($action === 'advance_list') {
        ql_json(ql_advance_list(ql_input()));
    }

    if ($action === 'advance_detail') {
        ql_json(ql_advance_detail(ql_input()));
    }

    if ($action === 'advance_submit') {
        ql_json(ql_advance_submit(ql_input()));
    }

    if ($action === 'advance_accept') {
        ql_json(ql_advance_accept(ql_input()));
    }

    if ($action === 'advance_return') {
        ql_json(ql_advance_return(ql_input()));
    }

    if ($action === 'advance_unaccept') {
        ql_json(ql_advance_unaccept(ql_input()));
    }

    if ($action === 'advance_return_cash') {
        ql_json(ql_advance_return_cash(ql_input()));
    }

    if ($action === 'advance_cancel') {
        ql_json(ql_advance_cancel(ql_input()));
    }

    if ($action === 'ledger_create') {
        ql_json(ql_ledger_create(ql_input()));
    }

    if ($action === 'ledger_list') {
        ql_json(ql_ledger_list(ql_input()));
    }

    if ($action === 'ledger_balance') {
        ql_json(ql_ledger_balance(ql_input()));
    }

    if ($action === 'ledger_work_position') {
        ql_json(ql_ledger_work_position(ql_input()));
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

    if ($action === 'ledger_group_excel') {
        ql_ledger_group_excel_download();
        exit;
    }

    if ($action === 'ledger_group_google_sheet') {
        ql_json(ql_ledger_group_google_sheet(ql_input()));
    }

    if ($action === 'ledger_group_final_report_excel') {
        ql_ledger_group_final_report_excel_download();
        exit;
    }

    if ($action === 'ledger_group_final_report_proof_download') {
        ql_ledger_group_final_report_proof_download();
        exit;
    }

    if ($action === 'ledger_group_final_report_package_export') {
        ql_ledger_group_final_report_package_export_download();
        exit;
    }

    if ($action === 'ledger_group_final_report_google_sheet') {
        ql_json(ql_ledger_group_final_report_google_sheet(ql_input()));
    }

    if ($action === 'ledger_group_final_report_list') {
        ql_json(ql_ledger_group_final_report_list(ql_input()));
    }

    if ($action === 'ledger_group_final_report_package') {
        ql_json(ql_ledger_group_final_report_package(ql_input()));
    }

    if ($action === 'ledger_group_final_report_detail') {
        ql_json(ql_ledger_group_final_report_detail(ql_input()));
    }

    if ($action === 'ledger_group_finalize_report') {
        ql_json(ql_ledger_group_finalize_report(ql_input()));
    }

    if ($action === 'ledger_group_open_received_funds') {
        ql_json(ql_ledger_group_open_received_funds(ql_input()));
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

    if ($action === 'group_delete') {
        ql_json(ql_group_delete(ql_input()));
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
