<?php
require __DIR__ . '/../app/db.php';

$file = __DIR__ . '/temp_unread_message_ids.txt';

if (!is_file($file)) {
    echo "No temp ids file found. Nothing to delete.\n";
    exit;
}

$ids = json_decode(file_get_contents($file), true);

$messageId = (int)($ids['message_id'] ?? 0);
$senderId = (int)($ids['sender_user_id'] ?? 0);

if (!$messageId || !$senderId) {
    echo "Invalid temp ids file.\n";
    exit(1);
}

$db = ql_db();
$db->beginTransaction();

try {
    $db->prepare("DELETE FROM group_message_reads WHERE message_id = ?")->execute([$messageId]);
    $db->prepare("DELETE FROM group_messages WHERE id = ?")->execute([$messageId]);
    $db->prepare("DELETE FROM group_members WHERE user_id = ?")->execute([$senderId]);
    $db->prepare("DELETE FROM users WHERE id = ?")->execute([$senderId]);

    $db->commit();

    unlink($file);

    echo "TEMP unread message deleted\n";
    echo "Deleted message id: {$messageId}\n";
    echo "Deleted sender user id: {$senderId}\n";
} catch (Throwable $e) {
    $db->rollBack();
    echo "FAIL\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
