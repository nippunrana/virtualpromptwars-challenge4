<?php
declare(strict_types=1);
/**
 * ArenaNexus 2026 Broadcasts API
 */
require_once __DIR__ . '/../config.php';

if (!$db) {
    sendResponse(['error' => 'Database connection failed'], 500);
}

try {
    $stmt = $db->query("SELECT id, target_zone_id, message_en, message_es, message_fr, is_active, created_at 
        FROM broadcasts 
        ORDER BY created_at DESC");
    $broadcasts = $stmt->fetchAll();
    
    foreach ($broadcasts as &$bc) {
        $bc['is_active'] = (bool)$bc['is_active'];
    }
    
    sendResponse($broadcasts);
} catch (Exception $e) {
    sendResponse(['error' => 'Failed to fetch broadcasts: ' . $e->getMessage()], 500);
}
