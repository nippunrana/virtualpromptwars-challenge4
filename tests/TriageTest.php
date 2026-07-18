<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../api/triage.php';

class TriageTest extends TestCase {
    private $db;

    protected function setUp(): void {
        require_once __DIR__ . '/../config.php';
        global $db;
        $this->db = $db;
        $this->assertNotNull($this->db, "Database connection is required for integration testing.");
        $this->db->beginTransaction();
    }

    protected function tearDown(): void {
        if ($this->db && $this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    public function testTriageIncidentDirectly() {
        // Insert mock data
        $stmtZone = $this->db->prepare("INSERT INTO stadium_zones (id, name, type, congestion_density, status, elevator_access) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtZone->execute(['test_gate_c', 'Gate C ADA', 'Gate', 40, 'Normal', 1]);

        // Insert a mock incident
        $stmtInc = $this->db->prepare("INSERT INTO incidents (type, reported_by, zone_id, description, status) VALUES (?, ?, ?, ?, ?)");
        $stmtInc->execute(['medical', 'sensor', 'test_gate_c', 'A fan is reporting chest pains and needs urgent medical assistance', 'Open']);
        $incidentId = $this->db->lastInsertId();

        // Run triage
        $result = triageIncidentDirectly($incidentId, $this->db);
        $this->assertTrue($result);

        // Fetch back and assert
        $checkStmt = $this->db->prepare("SELECT severity, assigned_volunteer_id, ai_analysis FROM incidents WHERE id = ?");
        $checkStmt->execute([$incidentId]);
        $updated = $checkStmt->fetch();

        $this->assertEquals('Critical', $updated['severity']);
        $this->assertNotNull($updated['assigned_volunteer_id']);
        
        $aiAnalysis = json_decode($updated['ai_analysis'], true);
        $this->assertNotNull($aiAnalysis);
        $this->assertNotEmpty($aiAnalysis['action_plan']);
    }
}
