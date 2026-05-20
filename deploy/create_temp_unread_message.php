<?php
require __DIR__ . '/../app/db.php';

$db = ql_db();

$group = $db->query("
    SELECT g.id, g.name
    FROM groups g
    JOIN group_members gm ON gm.group_id = g.id
    WHERE gm.user_id = 1
      AND gm.status = 'active'
      AND g.status = 'active'
    ORDER BY g.id ASC
    LIMIT 1
")->fetch();

if (!$group) {
    echo "No active group for user 1\n";
    exit(1);
}

$email = 'temp.sender+' . time() . '@quickledger.test';

$db->beginTransaction();

try {
    $stmt = $db->prepare("
        INSERT INTO users (email, display_name, preferred_language, timezone, last_login_at)
        VALUES (?, 'Temp Sender', 'en', 'UTC', NOW())
    ");
    $stmt->execute([$email]);
    $senderId = (int)$db->lastInsertId();

    $member = $db->prepare("
        INSERT INTO group_members (group_id, user_id, display_name, role, status)
        VALUES (?, ?, 'Temp Sender', 'member', 'active')
    ");
    $member->execute([(int)$group['id'], $senderId]);

    $msg = $db->prepare("
        INSERT INTO group_messages (group_id, sender_user_id, message_text, message_type)
        VALUES (?, ?, 'Temporary unread test message. This should open the modal.', 'text')
    ");
    $msg->execute([(int)$group['id'], $senderId]);
    $messageId = (int)$db->lastInsertId();

    file_put_contents(__DIR__ . '/temp_unread_message_ids.txt', json_encode([
        'group_id' => (int)$group['id'],
        'group_name' => $group['name'],
        'sender_user_id' => $senderId,
        'message_id' => $messageId,
        'email' => $email,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $db->commit();

    echo "TEMP unread message created\n";
    echo "Group: {$group['name']} ({$group['id']})\n";
    echo "Sender user id: {$senderId}\n";
    echo "Message id: {$messageId}\n";
    echo "IDs saved to deploy/temp_unread_message_ids.txt\n";
} catch (Throwable $e) {
    $db->rollBack();
    echo "FAIL\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
