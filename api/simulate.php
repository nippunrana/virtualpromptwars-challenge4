<?php
/**
 * ArenaNexus 2026 Match Telemetry Simulator
 * Simulates real-time crowd dynamics, congestion updates, and incident generation.
 */

require_once __DIR__ . '/../config.php';

if (!$db) {
    sendResponse(['error' => 'Database connection failed'], 500);
}

try {
    // 1. Fetch current status of all zones
    $stmt = $db->query("SELECT id, name, type, current_capacity, max_capacity, congestion_density, status FROM stadium_zones");
    $zones = $stmt->fetchAll();

    $updatedZones = [];

    // Begin transaction
    $db->beginTransaction();

    $updateZoneStmt = $db->prepare("UPDATE stadium_zones SET 
        current_capacity = ?, 
        congestion_density = ?, 
        status = ? 
        WHERE id = ?");

    $logTelemetryStmt = $db->prepare("INSERT INTO telemetry_logs (zone_id, congestion_density) VALUES (?, ?)");

    foreach ($zones as $zone) {
        $id = $zone['id'];
        $type = $zone['type'];
        $capacity = $zone['current_capacity'];
        $max = $zone['max_capacity'];
        $density = $zone['congestion_density'];
        
        // Random fluctuation depending on zone type
        // Let's create realistic fluctuations representing active match flow
        if ($type === 'gate') {
            $change = rand(-40, 50);
        } elseif ($type === 'section') {
            $change = rand(-15, 20);
        } elseif ($type === 'concession') {
            $change = rand(-20, 25);
        } else {
            $change = rand(-10, 10);
        }

        $newCapacity = max(0, min($max, $capacity + $change));
        
        // Compute new congestion density percentage
        $newDensity = round(($newCapacity / $max) * 100);
        $newDensity = max(0, min(100, $newDensity));

        // Adjust status based on density thresholds
        $newStatus = 'Normal';
        if ($newDensity >= 85) {
            $newStatus = 'Critical';
        } elseif ($newDensity >= 70) {
            $newStatus = 'Warning';
        }

        // Save back
        $updateZoneStmt->execute([$newCapacity, $newDensity, $newStatus, $id]);
        
        // Log telemetry occasionally (say 50% chance per tick to keep logs size controlled)
        if (rand(0, 1) === 1) {
            $logTelemetryStmt->execute([$id, $newDensity]);
        }

        $updatedZones[] = [
            'id' => $id,
            'name' => $zone['name'],
            'type' => $type,
            'current_capacity' => $newCapacity,
            'max_capacity' => $max,
            'congestion_density' => $newDensity,
            'status' => $newStatus
        ];
    }

    // Fluctuate concession wait times using PostgreSQL GREATEST and type cast
    $db->exec("UPDATE concessions SET avg_wait_time = GREATEST(2, avg_wait_time + CAST(floor(random() * 5) - 2 AS INTEGER))");

    // 2. Random Incident Generation
    // 30% chance to generate an incident on a simulation tick if active open incidents are less than 5
    $incidentCreated = null;
    $openIncidentsStmt = $db->query("SELECT COUNT(*) FROM incidents WHERE status = 'Open'");
    $openCount = $openIncidentsStmt->fetchColumn();

    if ($openCount < 5 && rand(1, 10) <= 3) {
        $incTemplates = [
            [
                'type' => 'medical',
                'zone_id' => 'gate_c',
                'description' => 'A fan at Gate C West VIP is showing signs of heat exhaustion and dizziness, needs immediate water and assistance.',
                'reported_by' => 'volunteer'
            ],
            [
                'type' => 'crowd',
                'zone_id' => 'transit_metro',
                'description' => 'Crowd crush warning: Post-match departure rush is causing heavy bottlenecks at the Metro Station entrance gates.',
                'reported_by' => 'sensor'
            ],
            [
                'type' => 'maintenance',
                'zone_id' => 'sec_102',
                'description' => 'A large soda spill in Section 102 (Row 12) is causing a slip hazard. Clean-up crew needed.',
                'reported_by' => 'fan'
            ],
            [
                'type' => 'security',
                'zone_id' => 'con_north',
                'description' => 'Verbal altercation between opposing team fans near Food Court North. Stewards requested for presence.',
                'reported_by' => 'volunteer'
            ],
            [
                'type' => 'fan_query',
                'zone_id' => 'gate_b',
                'description' => 'A Spanish-speaking family is lost near Gate B trying to find Section 103 (wheelchair accessible seating).',
                'reported_by' => 'volunteer'
            ]
        ];

        // Pick a random template
        $template = $incTemplates[array_rand($incTemplates)];
        
        // Let's call our triage backend logic internally to automatically process the incident with Gemini!
        // We'll write to incidents table first.
        $insertIncident = $db->prepare("INSERT INTO incidents (type, reported_by, zone_id, description, status) VALUES (?, ?, ?, ?, 'Open')");
        $insertIncident->execute([
            $template['type'],
            $template['reported_by'],
            $template['zone_id'],
            $template['description']
        ]);
        
        $newId = $db->lastInsertId();
        
        // Call the AI triage engine (curl to itself or require api/triage.php internally)
        // We will execute a local sub-process or handle it in a helper function.
        // Let's require the triage function to run Gemini triage on the new incident.
        $incidentCreated = [
            'id' => $newId,
            'type' => $template['type'],
            'zone_id' => $template['zone_id'],
            'description' => $template['description']
        ];
    }

    $db->commit();

    // Trigger AI triage for the new incident if created
    if ($incidentCreated) {
        $ch = curl_init();
        // Since we are running PHP, let's trigger it asynchronously or hit the endpoint
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $baseUrl = $protocol . '://' . $host . dirname($requestUri);
        
        // If we can construct the url, let's curl it to trigger the AI triage. Otherwise we do it directly.
        // To be safe, we will call the triage function directly using a helper class
        require_once __DIR__ . '/triage.php';
        triageIncidentDirectly($incidentCreated['id'], $db);
    }

    sendResponse([
        'status' => 'success',
        'message' => 'Telemetry simulated successfully',
        'zones' => $updatedZones,
        'new_incident' => $incidentCreated
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    sendResponse([
        'status' => 'error',
        'message' => 'Simulation failed: ' . $e->getMessage()
    ], 500);
}
