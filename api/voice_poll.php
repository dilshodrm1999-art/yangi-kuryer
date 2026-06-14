<?php
/**
 * Ratsiya: yangi ovozli xabarlarni olish (avtomatik eshittirish uchun).
 *
 * GET: after (oxirgi olingan xabar ID)
 *  - kuryer: o'ziga (receiver_id = men) kelgan, hali eshitilmagan xabarlar
 *  - admin:  kuryerlardan kelgan (receiver_id IS NULL), hali eshitilmagan xabarlar
 *
 * Qaytarilgan xabarlar darhol "eshitilgan" (is_read=1) deb belgilanadi —
 * shuning uchun ular faqat BIR MARTA avtomatik eshittiriladi (kuryerda saqlanmaydi).
 * To'liq yozishmalar tarixi admin/ratsiya.php sahifasida saqlanadi.
 *
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
    // Faqat o'ziga atalgan, hali eshitilmagan xabarlar
    $sql = "SELECT v.id, v.audio, v.created_at, s.name AS sender_name, s.id AS sender_id
            FROM voice_messages v JOIN users s ON s.id = v.sender_id
            WHERE v.receiver_id = ? AND v.is_read = 0 AND v.id > ?
            ORDER BY v.id ASC LIMIT 20";
    $stmt = db()->prepare($sql);
    $stmt->execute([$u['id'], $after]);
} else {
    // Admin: kuryerlardan kelgan, hali eshitilmagan xabarlar
    $sql = "SELECT v.id, v.audio, v.created_at, s.name AS sender_name, s.id AS sender_id
            FROM voice_messages v JOIN users s ON s.id = v.sender_id
            WHERE v.receiver_id IS NULL AND s.role = 'courier' AND v.is_read = 0 AND v.id > ?
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

// Eshitilgan deb belgilash — boshqa eshittirilmaydi (kuryerda xabar qolmaydi)
if ($messages) {
    $ids = implode(',', array_map(fn($m) => $m['id'], $messages));
    db()->query("UPDATE voice_messages SET is_read = 1 WHERE id IN ($ids)");
}

echo json_encode(['ok' => true, 'messages' => $messages]);
