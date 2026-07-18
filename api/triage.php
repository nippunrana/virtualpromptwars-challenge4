<?php
declare(strict_types=1);

/**
 * ArenaNexus 2026 Incident Parser & AI Triage Engine
 * Triages reported incidents using Gemini 3.1 Flash-Lite structured JSON outputs.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/gemini.php';

/**
 * Triage an incident by ID using Gemini AI.
 *
 * @param int  $incidentId The incident row ID to triage.
 * @param PDO  $db         Active database connection.
 * @return bool True on success, false on failure.
 */
function triageIncidentDirectly(int $incidentId, PDO $db): bool {
    try {
        // 1. Fetch incident details
        $stmt = $db->prepare("SELECT i.id, i.type, i.description, i.zone_id, z.name as zone_name 
            FROM incidents i 
            LEFT JOIN stadium_zones z ON i.zone_id = z.id 
            WHERE i.id = ?");
        $stmt->execute([$incidentId]);
        $incident = $stmt->fetch();

        if (!$incident) {
            error_log("Triage failed: Incident ID {$incidentId} not found.");
            return false;
        }

        // 2. Fetch available volunteers with their locations and languages
        $volStmt = $db->query("SELECT id, name, current_zone_id, primary_language FROM volunteers WHERE status = 'Available'");
        $volunteers = $volStmt->fetchAll();

        // Build a text list of available volunteers for the prompt
        $volTextList = "";
        foreach ($volunteers as $v) {
            $volTextList .= "ID: {$v['id']}, Name: {$v['name']}, Current Zone: {$v['current_zone_id']}, Language: {$v['primary_language']}\n";
        }
        if (empty($volTextList)) {
            $volTextList = "No volunteers currently available. Assign to ID null or any standby.";
        }

        // 3. Construct System Prompt & User Prompt
        $systemInstruction = "You are the Lead AI Operations Coordinator for FIFA World Cup 2026 Stadium Control. "
            . "Your job is to analyze reported stadium incidents, determine severity, generate a step-by-step Standard Operating Procedure (SOP) action plan, "
            . "assign the best suited volunteer based on location or language matches, and draft real-time multilingual warning/alert messages (English, Spanish, French) to display in the affected zone.";

        $prompt = "Incident Details:\n"
            . "- Type: {$incident['type']}\n"
            . "- Zone: {$incident['zone_name']} (ID: {$incident['zone_id']})\n"
            . "- Description: {$incident['description']}\n\n"
            . "Available Volunteers:\n"
            . $volTextList . "\n"
            . "Analyze this incident and provide structured output matching the JSON schema. "
            . "For volunteer selection:\n"
            . "- If a fan speaks a foreign language, prefer a volunteer who speaks that language.\n"
            . "- Otherwise, select the volunteer physically closest to the zone (e.g. if the incident is at Gate A, Carlos Gomez is currently at Gate A).\n"
            . "- If no volunteers match well, assign the closest available or the first in list.\n\n"
            . "Draft the broadcasts to warn stadium visitors. Keep them polite, clear, and direct. Explain detours if necessary.";

        // JSON schema for Gemini 3.1 Flash-Lite
        $jsonSchema = [
            'type' => 'object',
            'properties' => [
                'severity' => [
                    'type' => 'string',
                    'enum' => ['Low', 'Medium', 'High', 'Critical']
                ],
                'action_plan' => [
                    'type' => 'string',
                    'description' => 'A markdown bulleted list outlining the immediate steps ground staff and the assigned volunteer must take.'
                ],
                'recommended_volunteer_id' => [
                    'type' => 'integer',
                    'description' => 'The ID of the recommended volunteer from the provided list, or null if none can be assigned.'
                ],
                'broadcast_en' => [
                    'type' => 'string',
                    'description' => 'Announcement broadcast script in English.'
                ],
                'broadcast_es' => [
                    'type' => 'string',
                    'description' => 'Announcement broadcast script in Spanish.'
                ],
                'broadcast_fr' => [
                    'type' => 'string',
                    'description' => 'Announcement broadcast script in French.'
                ]
            ],
            'required' => ['severity', 'action_plan', 'recommended_volunteer_id', 'broadcast_en', 'broadcast_es', 'broadcast_fr']
        ];

        // 4. Invoke Gemini API
        $gemini = new GeminiClient();
        $aiResponseText = $gemini->generateContent($prompt, $systemInstruction, $jsonSchema);
        
        $aiData = json_decode($aiResponseText, true);

        if (!$aiData || !isset($aiData['severity'])) {
            error_log("Triage failed: Gemini returned invalid JSON structure. Raw response: " . $aiResponseText);
            return false;
        }

        // 5. Update Database
        $startedTransaction = false;
        if (!$db->inTransaction()) {
            $db->beginTransaction();
            $startedTransaction = true;
        }

        // Update Incident status, severity, assigned volunteer, and AI analysis json
        $updateInc = $db->prepare("UPDATE incidents SET 
            severity = ?, 
            assigned_volunteer_id = ?, 
            ai_analysis = ?,
            status = 'In Progress'
            WHERE id = ?");
        
        $assignedVolId = $aiData['recommended_volunteer_id'];
        // If volunteer ID is invalid or not in list, set to null
        $validVolIds = array_column($volunteers, 'id');
        if (!in_array($assignedVolId, $validVolIds)) {
            $assignedVolId = !empty($validVolIds) ? $validVolIds[0] : null;
        }

        $updateInc->execute([
            $aiData['severity'],
            $assignedVolId,
            json_encode($aiData),
            $incidentId
        ]);

        // If volunteer was assigned, mark them as 'Busy'
        if ($assignedVolId) {
            $updateVol = $db->prepare("UPDATE volunteers SET status = 'Busy' WHERE id = ?");
            $updateVol->execute([$assignedVolId]);
        }

        // Insert Broadcast alert
        $insertBroadcast = $db->prepare("INSERT INTO broadcasts (target_zone_id, message_en, message_es, message_fr) VALUES (?, ?, ?, ?)");
        $insertBroadcast->execute([
            $incident['zone_id'],
            $aiData['broadcast_en'],
            $aiData['broadcast_es'],
            $aiData['broadcast_fr']
        ]);

        if ($startedTransaction && $db->inTransaction()) {
            $db->commit();
        }
        return true;

    } catch (Exception $e) {
        if ($startedTransaction && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Error during incident triage: " . $e->getMessage());
        return false;
    }
}

// Handling HTTP Web Requests
if (basename($_SERVER['PHP_SELF']) === 'triage.php') {
    // Only execute if requested directly via HTTP POST or GET list
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Read JSON input
        $input = json_decode(file_get_contents('php://input') ?: '{}', true);
        
        // Fallback to $_POST, with sanitization and length limits
        $type        = mb_substr(trim(strip_tags($input['type']        ?? $_POST['type']        ?? '')), 0, 100);
        $reportedBy  = mb_substr(trim(strip_tags($input['reported_by'] ?? $_POST['reported_by'] ?? 'sensor')), 0, 100);
        $zoneId      = mb_substr(trim(strip_tags($input['zone_id']     ?? $_POST['zone_id']     ?? '')), 0, 100);
        $description = mb_substr(trim(strip_tags($input['description'] ?? $_POST['description'] ?? '')), 0, 1000);

        if (empty($type) || empty($zoneId) || empty($description)) {
            sendResponse(['error' => 'Missing required fields: type, zone_id, description'], 400);
        }

        if (!$db) {
            sendResponse(['error' => 'Database connection failed'], 500);
        }

        try {
            $stmt = $db->prepare("INSERT INTO incidents (type, reported_by, zone_id, description, status) VALUES (?, ?, ?, ?, 'Open')");
            $stmt->execute([$type, $reportedBy, $zoneId, $description]);
            $newId = $db->lastInsertId();

            // Run triage
            $triageResult = triageIncidentDirectly($newId, $db);

            if ($triageResult) {
                // Fetch the updated incident details
                $fetchStmt = $db->prepare("SELECT * FROM incidents WHERE id = ?");
                $fetchStmt->execute([$newId]);
                $updatedInc = $fetchStmt->fetch();
                $updatedInc['ai_analysis'] = json_decode($updatedInc['ai_analysis'], true);
                
                sendResponse([
                    'status' => 'success',
                    'message' => 'Incident created and triaged by GenAI',
                    'incident' => $updatedInc
                ]);
            } else {
                sendResponse([
                    'status' => 'partial_success',
                    'message' => 'Incident created, but AI triage failed or defaulted.',
                    'incident_id' => $newId
                ]);
            }
        } catch (Exception $e) {
            sendResponse(['error' => 'Failed to create incident: ' . $e->getMessage()], 500);
        }
    } else {
        // GET Request: List active incidents
        if (!$db) {
            sendResponse(['error' => 'Database connection failed'], 500);
        }
        try {
            $stmt = $db->query("SELECT i.*, z.name as zone_name, v.name as volunteer_name 
                FROM incidents i 
                LEFT JOIN stadium_zones z ON i.zone_id = z.id 
                LEFT JOIN volunteers v ON i.assigned_volunteer_id = v.id 
                ORDER BY i.created_at DESC");
            $incidents = $stmt->fetchAll();
            
            // Format AI analysis field
            foreach ($incidents as &$inc) {
                if ($inc['ai_analysis']) {
                    $inc['ai_analysis'] = json_decode($inc['ai_analysis'], true);
                }
            }
            sendResponse($incidents);
        } catch (Exception $e) {
            sendResponse(['error' => 'Failed to fetch incidents: ' . $e->getMessage()], 500);
        }
    }
}
