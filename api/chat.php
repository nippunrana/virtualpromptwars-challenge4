<?php
/**
 * ArenaNexus 2026 Fan Assistant Chatbot API
 * Uses Gemini 3.1 Flash-Lite grounded in live PostgreSQL telemetry.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/gemini.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(['error' => 'Only POST requests allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$message = isset($input['message']) ? trim($input['message']) : '';

if (empty($message)) {
    sendResponse(['error' => 'Missing message parameter'], 400);
}

if (!$db) {
    sendResponse(['error' => 'Database connection failed'], 500);
}

try {
    // 1. Fetch current stadium telemetry for grounding context
    $stmt = $db->query("SELECT name, type, congestion_density, elevator_access, status FROM stadium_zones");
    $zones = $stmt->fetchAll();
    
    $conStmt = $db->query("SELECT z.name, c.cuisine, c.is_vegan, c.is_vegetarian, c.is_non_veg, c.is_gluten_free, c.avg_wait_time 
        FROM concessions c 
        JOIN stadium_zones z ON c.id = z.id");
    $concessions = $conStmt->fetchAll();

    // 2. Build grounding context strings
    $stadiumContext = "LIVE STADIUM METRICS FOR GROUNDING:\n";
    foreach ($zones as $z) {
        $stadiumContext .= "- {$z['name']} (Type: {$z['type']}): Congestion {$z['congestion_density']}%, Elevators: " . ($z['elevator_access'] ? 'Yes' : 'No') . ", Status: {$z['status']}\n";
    }

    $stadiumContext .= "\nCONCESSION STANDS & WAIT TIMES:\n";
    foreach ($concessions as $c) {
        $diets = [];
        if ($c['is_vegan']) $diets[] = 'Vegan';
        if ($c['is_vegetarian']) $diets[] = 'Vegetarian';
        if ($c['is_non_veg']) $diets[] = 'Non-Veg';
        if ($c['is_gluten_free']) $diets[] = 'Gluten-Free';
        $dietStr = empty($diets) ? 'None' : implode(', ', $diets);
        
        $stadiumContext .= "- {$c['name']} (Cuisine: {$c['cuisine']}): Avg Wait: {$c['avg_wait_time']} mins, Dietary: {$dietStr}\n";
    }

    // 3. Setup Gemini prompts
    $systemInstruction = "You are the ArenaNexus Stadium Guest Assistant Bot for the FIFA World Cup 2026. "
        . "Your goal is to assist stadium fans with any questions they have. "
        . "You must use the provided LIVE STADIUM METRICS AND CONCESSIONS CONTEXT to answer questions with factual accuracy.\n\n"
        . "Rules:\n"
        . "- If a fan asks about line wait times, food recommendations, or gate capacities, use the live numbers from the context.\n"
        . "- If a fan is in a wheelchair or requires step-free paths, guide them to Gate C or Section 103 (which have elevator access).\n"
        . "- Keep your answers short, helpful, and friendly. Answer in the same language the user asks, if possible.";

    $prompt = "Grounding Context:\n"
        . $stadiumContext . "\n\n"
        . "Fan Message: \"{$message}\"\n\n"
        . "Please formulate your response:";

    $gemini = new GeminiClient();
    $aiResponse = $gemini->generateContent($prompt, $systemInstruction);

    sendResponse(['response' => $aiResponse]);

} catch (Exception $e) {
    sendResponse(['error' => 'Chat assistant error: ' . $e->getMessage()], 500);
}
