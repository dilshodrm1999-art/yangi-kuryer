<?php
/**
 * Ratsiya: yangi ovozli xabarlarni olish (polling).
 * GET: after (oxirgi olingan xabar ID), ixtiyoriy
 *  - kuryer: o'ziga atalgan (receiver_id = men) xabarlar
 *  - admin:  kuryerlardan kelgan (receiver_id IS NULL) xabarlar
 * Javob: { ok, messages: [{id, audio, sender_name, sender_short, created_at}] }
 */
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$u = current_user();
if (!$u || !in_array($u['role'], ['admin', 'courier'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$after = (int)($_GET['after'] ?? 0);

if ($u['role'] === 'courier') {
    $sql = "SELECT v.id, v.audio, v.created_at, s.name AS sender_name, s.id AS sender_id
            FROM voice_messages v JOIN users s ON s.id = v.sender_id
            WHERE v.receiver_id = ? AND v.id > ?
            ORDER BY v.id ASC LIMIT 20";
    $stmt = db()->prepare($sql);
    $stmt->execute([$u['id'], $after]);
} else {
    // admin: kuryerlardan kelgan xabarlar (receiver_id IS NULL)
    $sql = "SELECT v.id, v.audio, v.created_at, s.name AS sender_name, s.id AS sender_id
            FROM voice_messages v JOIN users s ON s.id = v.sender_id
            WHERE v.receiver_id IS NULL AND s.role = 'courier' AND v.id > ?
            ORDER BY v.id ASC LIMIT 20";
    $stmt = db()->prepare($sql);
    $stmt->execute([$after]);
}

$rows = $stmt->fetchAll();
$messages = array_map(fn($r) => [
    'id'           => (int)$r['id'],
    'audio'        => $r['audio'],
    'sender_name'  => $r['sender_name'],
    'sender_short' => 'K' . str_pad((string)$r['sender_id'], 3, '0', STR_PAD_LEFT),
    'created_at'   => date('H:i', strtotime($r['created_at'])),
], $rows);

// O'qilgan deb belgilash
if ($messages) {
    $ids = implode(',', array_map(fn($m) => $m['id'], $messages));
    db()->query("UPDATE voice_messages SET is_read = 1 WHERE id IN ($ids)");
}

echo json_encode(['ok' => true, 'messages' => $messages]);
