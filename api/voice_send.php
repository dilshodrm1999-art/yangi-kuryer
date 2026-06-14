<?php
/**
 * Ratsiya: ovozli xabar yuborish.
 * POST (multipart): csrf, audio (fayl), receiver_id (ixtiyoriy)
 *  - admin  -> receiver_id majburiy (qaysi kuryerga)
 *  - kuryer -> receiver_id e'tiborga olinmaydi (barcha adminlarga = NULL)
 * Javob: { ok, id }
 */
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$u = current_user();
if (!$u || !in_array($u['role'], ['admin', 'courier'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'error' => 'csrf']);
    exit;
}

$err = null;
$path = upload_voice('audio', $err);
if ($path === null) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $err ?: 'no_audio']);
    exit;
}

$receiverId = null;
if ($u['role'] === 'admin') {
    $receiverId = (int)($_POST['receiver_id'] ?? 0) ?: null;
    if (!$receiverId) {
        echo json_encode(['ok' => false, 'error' => 'no_receiver']);
        exit;
    }
    // Qabul qiluvchi haqiqatan kuryer ekanini tekshiramiz
    $chk = db()->prepare("SELECT id FROM users WHERE id=? AND role='courier'");
    $chk->execute([$receiverId]);
    if (!$chk->fetch()) {
        echo json_encode(['ok' => false, 'error' => 'bad_receiver']);
        exit;
    }
}
// Kuryer yuborsa: receiver_id = NULL (barcha adminlarga)

$stmt = db()->prepare('INSERT INTO voice_messages (sender_id, receiver_id, audio) VALUES (?,?,?)');
$stmt->execute([$u['id'], $receiverId, $path]);

echo json_encode(['ok' => true, 'id' => (int)db()->lastInsertId(), 'audio' => $path]);
