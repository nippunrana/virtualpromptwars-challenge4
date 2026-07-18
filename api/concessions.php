<?php
declare(strict_types=1);
/**
 * ArenaNexus 2026 Concessions Finder API
 * Searches and filters food concessions by wait time and dietary tags.
 */

require_once __DIR__ . '/../config.php';

if (!$db) {
    sendResponse(['error' => 'Database connection failed'], 500);
}

// Get query parameters
$vegan = isset($_GET['vegan']) && ($_GET['vegan'] === 'true' || $_GET['vegan'] == 1);
$vegetarian = isset($_GET['vegetarian']) && ($_GET['vegetarian'] === 'true' || $_GET['vegetarian'] == 1);
$nonVeg = isset($_GET['non_veg']) && ($_GET['non_veg'] === 'true' || $_GET['non_veg'] == 1);
$glutenFree = isset($_GET['gluten_free']) && ($_GET['gluten_free'] === 'true' || $_GET['gluten_free'] == 1);
$maxWait = isset($_GET['max_wait']) ? intval($_GET['max_wait']) : 0;

try {
    $sql = "SELECT c.id, z.name, z.congestion_density, z.status as zone_status, 
                   c.cuisine, c.is_vegan, c.is_vegetarian, c.is_non_veg, c.is_gluten_free, c.avg_wait_time 
            FROM concessions c
            JOIN stadium_zones z ON c.id = z.id
            WHERE 1=1";
    
    $params = [];

    if ($vegan) {
        $sql .= " AND c.is_vegan = TRUE";
    }
    if ($vegetarian) {
        $sql .= " AND c.is_vegetarian = TRUE";
    }
    if ($nonVeg) {
        $sql .= " AND c.is_non_veg = TRUE";
    }
    if ($glutenFree) {
        $sql .= " AND c.is_gluten_free = TRUE";
    }
    if ($maxWait > 0) {
        $sql .= " AND c.avg_wait_time <= ?";
        $params[] = $maxWait;
    }

    $sql .= " ORDER BY c.avg_wait_time ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();

    // Map boolean responses for JSON compatibility
    foreach ($results as &$row) {
        $row['is_vegan'] = (bool)$row['is_vegan'];
        $row['is_vegetarian'] = (bool)$row['is_vegetarian'];
        $row['is_non_veg'] = (bool)$row['is_non_veg'];
        $row['is_gluten_free'] = (bool)$row['is_gluten_free'];
    }

    sendResponse($results);

} catch (Exception $e) {
    sendResponse(['error' => 'Concession lookup failed: ' . $e->getMessage()], 500);
}
