<?php
declare(strict_types=1);

/**
 * ArenaNexus 2026 Consolidated Status API
 *
 * Returns zones, active incidents, and broadcasts in a single response.
 * Used by the admin dashboard to reduce polling from 3 requests/cycle to 1.
 */

require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(['error' => 'Only GET requests allowed'], 405);
}

if (!$db) {
    sendResponse(['error' => 'Database connection failed'], 500);
}

try {
    // 1. Zones — specific columns only (no SELECT *)
    $stmtZones = $db->query(
        "SELECT id, name, type, current_capacity, max_capacity,
                congestion_density, elevator_access, status
         FROM stadium_zones
         ORDER BY id ASC"
    );
    $zones = $stmtZones->fetchAll();
    foreach ($zones as &$zone) {
        $zone['current_capacity']   = (int) $zone['current_capacity'];
        $zone['max_capacity']       = (int) $zone['max_capacity'];
        $zone['congestion_density'] = (int) $zone['congestion_density'];
        $zone['elevator_access']    = (bool) $zone['elevator_access'];
    }
    unset($zone);

    // 2. Incidents — most-recent 50, specific columns only
    $stmtInc = $db->query(
        "SELECT i.id, i.type, i.description, i.status, i.severity,
                i.zone_id, i.ai_analysis, i.created_at,
                z.name AS zone_name,
                v.name AS volunteer_name
         FROM incidents i
         LEFT JOIN stadium_zones z ON i.zone_id = z.id
         LEFT JOIN volunteers    v ON i.assigned_volunteer_id = v.id
         ORDER BY i.created_at DESC
         LIMIT 50"
    );
    $incidents = $stmtInc->fetchAll();
    foreach ($incidents as &$inc) {
        if (!empty($inc['ai_analysis'])) {
            $inc['ai_analysis'] = json_decode($inc['ai_analysis'], true);
        }
    }
    unset($inc);

    // 3. Broadcasts — most-recent 20, specific columns only
    $stmtBc = $db->query(
        "SELECT id, target_zone_id, message_en, message_es, message_fr,
                is_active, created_at
         FROM broadcasts
         ORDER BY created_at DESC
         LIMIT 20"
    );
    $broadcasts = $stmtBc->fetchAll();
    foreach ($broadcasts as &$bc) {
        $bc['is_active'] = (bool) $bc['is_active'];
    }
    unset($bc);

    sendResponse([
        'zones'      => $zones,
        'incidents'  => $incidents,
        'broadcasts' => $broadcasts,
    ]);

} catch (Exception $e) {
    sendResponse(['error' => 'Status fetch failed: ' . $e->getMessage()], 500);
}
