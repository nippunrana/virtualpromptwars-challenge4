<?php
declare(strict_types=1);
/**
 * ArenaNexus 2026 Dynamic Router API
 * Finds optimal path between zones avoiding congestion and respect accessibility settings,
 * then uses Gemini 3.1 Flash-Lite to write a friendly, narrative navigation instruction.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/gemini.php';

if (!$db) {
    sendResponse(['error' => 'Database connection failed'], 500);
}

// Define the static spatial layout graph of the stadium
$graph = [
    'transit_metro' => ['gate_a' => 5, 'gate_b' => 8, 'gate_d' => 3],
    'transit_valet' => ['gate_c' => 4, 'gate_f' => 4],
    'transit_shuttle' => ['gate_b' => 6, 'gate_c' => 5],
    'transit_rideshare' => ['gate_a' => 6, 'gate_e' => 5],
    
    'gate_a' => ['transit_metro' => 5, 'transit_rideshare' => 6, 'sec_101' => 4, 'con_grill' => 3, 'rest_gate_a' => 2],
    'gate_b' => ['transit_metro' => 8, 'transit_shuttle' => 6, 'sec_102' => 4, 'con_tacos' => 3],
    'gate_c' => ['transit_valet' => 4, 'transit_shuttle' => 5, 'sec_103' => 3, 'rest_gate_c' => 2],
    'gate_d' => ['transit_metro' => 3, 'sec_104' => 4, 'con_north' => 3],
    'gate_e' => ['transit_rideshare' => 5, 'sec_201' => 5, 'con_salad' => 3],
    'gate_f' => ['transit_valet' => 4, 'sec_203' => 4, 'con_cafe' => 3],

    'sec_101' => ['gate_a' => 4, 'sec_102' => 3, 'sec_201' => 5],
    'sec_102' => ['gate_b' => 4, 'sec_101' => 3, 'sec_103' => 3],
    'sec_103' => ['gate_c' => 3, 'sec_102' => 3, 'sec_104' => 3, 'rest_sec_103' => 2],
    'sec_104' => ['gate_d' => 4, 'sec_103' => 3, 'sec_204' => 5],
    
    'sec_201' => ['gate_e' => 5, 'sec_101' => 5, 'sec_202' => 3, 'rest_sec_201' => 2],
    'sec_202' => ['sec_201' => 3, 'sec_203' => 3],
    'sec_203' => ['gate_f' => 4, 'sec_202' => 3, 'sec_204' => 3],
    'sec_204' => ['gate_d' => 5, 'sec_203' => 3, 'sec_104' => 5],

    // Concessions
    'con_grill' => ['gate_a' => 3, 'con_salad' => 4],
    'con_tacos' => ['gate_b' => 3],
    'con_salad' => ['gate_e' => 3, 'con_grill' => 4],
    'con_cafe' => ['gate_f' => 3, 'con_south' => 4],
    'con_north' => ['gate_d' => 3],
    'con_south' => ['con_cafe' => 4],

    // Restrooms
    'rest_gate_a' => ['gate_a' => 2],
    'rest_gate_c' => ['gate_c' => 2],
    'rest_sec_103' => ['sec_103' => 2],
    'rest_sec_201' => ['sec_201' => 2],
];

// Read input parameters
$start = isset($_GET['start']) ? trim($_GET['start']) : '';
$end = isset($_GET['end']) ? trim($_GET['end']) : '';
$stepFree = isset($_GET['step_free']) && ($_GET['step_free'] === 'true' || $_GET['step_free'] == 1) ? true : false;

if (empty($start) || empty($end)) {
    sendResponse(['error' => 'Missing start or end parameters'], 400);
}

if (!array_key_exists($start, $graph) || !array_key_exists($end, $graph)) {
    sendResponse(['error' => 'Invalid start or end zone ID'], 400);
}

try {
    // Fetch current capacities, congestion densities and elevator flags
    $stmt = $db->query("SELECT id, name, congestion_density, elevator_access, status FROM stadium_zones");
    $zoneDataRaw = $stmt->fetchAll();
    $zoneData = [];
    foreach ($zoneDataRaw as $z) {
        $zoneData[$z['id']] = $z;
    }

    // 1. Dijkstra implementation in PHP (delegated to modular core for testing)
    require_once __DIR__ . '/router_core.php';
    $path = calculateRoute($start, $end, $stepFree, $graph, $zoneData);

    if ($path === null) {
        sendResponse(['error' => 'No path found between selected zones'], 404);
    }

    // Get list of zone objects
    $pathZones = [];
    $penaltiesEncountered = [];
    foreach ($path as $zoneId) {
        $info = $zoneData[$zoneId];
        $pathZones[] = [
            'id' => $zoneId,
            'name' => $info['name'],
            'congestion' => $info['congestion_density'],
            'elevator' => (bool)$info['elevator_access'],
            'status' => $info['status']
        ];

        if ($info['congestion_density'] >= 70) {
            $penaltiesEncountered[] = "{$info['name']} is heavily congested ({$info['congestion_density']}% density)";
        }
    }

    // Write a reason string for AI prompt context
    $reasonNotes = [];
    if ($stepFree) {
        $reasonNotes[] = "Step-free routing was activated. Elevators and accessible pathways were prioritized.";
    }
    if (!empty($penaltiesEncountered)) {
        $reasonNotes[] = "Bypassed or penalized bottleneck zones: " . implode(', ', $penaltiesEncountered) . ".";
    } else {
        $reasonNotes[] = "Selected fastest path based on current low congestion.";
    }
    $reasonStr = implode(' ', $reasonNotes);

    // 2. Call Gemini for Narrative Directions
    $pathNames = array_column($pathZones, 'name');
    $pathListStr = implode(' -> ', $pathNames);

    $systemInstruction = "You are a friendly, helpful FIFA World Cup 2026 Stadium Wayfinder AI. "
        . "Your goal is to explain navigation routing directions to fans inside the stadium. "
        . "Make the output encouraging and highly conversational, yet clear and brief. "
        . "Explain WHY this route was selected, referencing details like avoiding stairs (if step-free is active) "
        . "or skipping congested sections to save wait time.";

    $prompt = "Start Zone: {$zoneData[$start]['name']}\n"
        . "Destination Zone: {$zoneData[$end]['name']}\n"
        . "Accessibility Filter (Step-Free): " . ($stepFree ? "Yes" : "No") . "\n"
        . "Calculated Path Sequence: " . $pathListStr . "\n"
        . "Backend routing logic notes: " . $reasonStr . "\n\n"
        . "Draft a paragraph explaining this route. Mention the key points along the route and why it is better than alternative routes.";

    $gemini = new GeminiClient();
    $narrative = $gemini->generateContent($prompt, $systemInstruction);

    sendResponse([
        'start' => $zoneData[$start]['name'],
        'end' => $zoneData[$end]['name'],
        'step_free' => $stepFree,
        'path' => $pathZones,
        'routing_logic' => $reasonStr,
        'instructions' => $narrative
    ]);

} catch (Exception $e) {
    sendResponse(['error' => 'Routing failed: ' . $e->getMessage()], 500);
}
