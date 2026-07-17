<?php
/**
 * ArenaNexus 2026 Zones Telemetry API
 */
require_once __DIR__ . '/../config.php';

if (!$db) {
    sendResponse(['error' => 'Database connection failed'], 500);
}

try {
    $stmt = $db->query("SELECT id, name, type, current_capacity, max_capacity, congestion_density, elevator_access, status 
        FROM stadium_zones 
        ORDER BY id ASC");
    $zones = $stmt->fetchAll();
    
    // Cast variables correctly for JSON transfer
    foreach ($zones as &$zone) {
        $zone['current_capacity'] = intval($zone['current_capacity']);
        $zone['max_capacity'] = intval($zone['max_capacity']);
        $zone['congestion_density'] = intval($zone['congestion_density']);
        $zone['elevator_access'] = (bool)$zone['elevator_access'];
    }
    
    sendResponse($zones);
} catch (Exception $e) {
    sendResponse(['error' => 'Failed to fetch zones: ' . $e->getMessage()], 500);
}
