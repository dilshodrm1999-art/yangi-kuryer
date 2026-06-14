<?php
/**
 * Ratsiya yozishmalari tarixi (admin uchun).
 * GET: courier_id (ixtiyoriy) — faqat shu kuryer bilan yozishmalar
 *      after (ixtiyoriy) — shu ID dan keyingilarini olish (jonli yangilash)
 *
 * Javob: { ok, messages: [{id, dir:'in'|'out', operator, courier, courier_id, audio, time, date}] }
 *   dir='in'  — kuryerdan adminga
 *   dir='out' — admindan kuryerga (operator = admin ismi)
 */
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$u = current_user();
if (!$u || $u['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}

$courierId = (int)($_GET['courier_id'] ?? 0);
$after     = (int)($_GET['after'] ?? 0);

// Kuryer <-> admin yozishmalari:
//  - admindan kuryerga: sender=admin, receiver=courier
//  - kuryerdan adminlarga: sender=courier, receiver IS NULL
$params = [];
$where  = "((snd.role='admin' AND rcv.role='courier') OR (snd.role='courier' AND v.receiver_id IS NULL))";
if ($courierId > 0) {
    $where .= " AND ( (snd.role='admin' AND v.receiver_id = ?) OR (snd.role='courier' AND v.sender_id = ?) )";
    $params[] = $courierId;
    $params[] = $courierId;
}
if ($after > 0) {
    $where .= " AND v.id > ?";
    $params[] = $after;
}

$sql = "SELECT v.id, v.audio, v.created_at, v.sender_id, v.receiver_id,
               snd.role AS sender_role, snd.name AS sender_name,
               rcv.name AS receiver_name, rcv.id AS receiver_uid
        FROM voice_messages v
        JOIN users snd ON snd.id = v.sender_id
        LEFT JOIN users rcv ON rcv.id = v.receiver_id
        WHERE $where
        ORDER BY v.id ASC
        LIMIT 200";
$stmt = db()->prepare($sql);
$stmt->execute($params);

$messages = [];
foreach ($stmt->fetchAll() as $r) {
    $isOut = ($r['sender_role'] === 'admin'); // admindan kuryerga
    if ($isOut) {
        $courierUid  = (int)$r['receiver_uid'];
        $courierName = $r['receiver_name'];
        $operator    = $r['sender_name'];
    } else {
        $courierUid  = (int)$r['sender_id'];
        $courierName = $r['sender_name'];
        $operator    = null;
    }
    $messages[] = [
        'id'         => (int)$r['id'],
        'dir'        => $isOut ? 'out' : 'in',
        'operator'   => $operator,
        'courier'    => $courierName,
        'courier_id' => $courierUid,
        'short'      => 'K' . str_pad((string)$courierUid, 3, '0', STR_PAD_LEFT),
        'audio'      => $r['audio'],
        'time'       => date('H:i', strtotime($r['created_at'])),
        'date'       => date('d.m.Y', strtotime($r['created_at'])),
    ];
}

echo json_encode(['ok' => true, 'messages' => $messages]);
