<?php
declare(strict_types=1);
/**
 * ArenaNexus 2026 Incident Resolution Endpoint
 */
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(['error' => 'Only POST requests allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? intval($input['id']) : 0;

if ($id <= 0) {
    sendResponse(['error' => 'Invalid or missing incident ID'], 400);
}

if (!$db) {
    sendResponse(['error' => 'Database connection failed'], 500);
}

try {
    $db->beginTransaction();

    // 1. Fetch assigned volunteer ID before resolving
    $fetchStmt = $db->prepare("SELECT assigned_volunteer_id, zone_id FROM incidents WHERE id = ?");
    $fetchStmt->execute([$id]);
    $incident = $fetchStmt->fetch();

    if (!$incident) {
        $db->rollBack();
        sendResponse(['error' => 'Incident not found'], 404);
    }

    $volunteerId = $incident['assigned_volunteer_id'];
    $zoneId = $incident['zone_id'];

    // 2. Mark incident as resolved
    $updateInc = $db->prepare("UPDATE incidents SET status = 'Resolved', resolved_at = NOW() WHERE id = ?");
    $updateInc->execute([$id]);

    // 3. Mark volunteer as available
    if ($volunteerId) {
        $updateVol = $db->prepare("UPDATE volunteers SET status = 'Available' WHERE id = ?");
        $updateVol->execute([$volunteerId]);
    }

    // 4. Mark broadcasts for this zone as inactive
    if ($zoneId) {
        $updateBroadcasts = $db->prepare("UPDATE broadcasts SET is_active = FALSE WHERE target_zone_id = ?");
        $updateBroadcasts->execute([$zoneId]);
    }

    $db->commit();
    sendResponse(['status' => 'success', 'message' => 'Incident resolved, volunteer status freed']);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    sendResponse(['error' => 'Failed to resolve incident: ' . $e->getMessage()], 500);
}
