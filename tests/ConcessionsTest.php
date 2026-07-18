<?php
use PHPUnit\Framework\TestCase;

class ConcessionsTest extends TestCase {
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

    private function queryConcessions($vegan, $vegetarian, $nonVeg, $glutenFree, $maxWait) {
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

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        foreach ($results as &$row) {
            $row['is_vegan'] = (bool)$row['is_vegan'];
            $row['is_vegetarian'] = (bool)$row['is_vegetarian'];
            $row['is_non_veg'] = (bool)$row['is_non_veg'];
            $row['is_gluten_free'] = (bool)$row['is_gluten_free'];
        }
        return $results;
    }

    public function testConcessionsFiltering() {
        // Insert a test zone
        $stmt = $this->db->prepare("INSERT INTO stadium_zones (id, name, type, congestion_density, status, elevator_access) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['test_con_zone_1', 'Test Concession Zone 1', 'Section', 10, 'Normal', 1]);
        $stmt->execute(['test_con_zone_2', 'Test Concession Zone 2', 'Section', 20, 'Normal', 1]);

        // Insert mock concessions
        $stmtCon = $this->db->prepare("INSERT INTO concessions (id, cuisine, is_vegan, is_vegetarian, is_non_veg, is_gluten_free, avg_wait_time) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtCon->execute(['test_con_zone_1', 'Vegan Tacos', 1, 1, 0, 1, 5]);
        $stmtCon->execute(['test_con_zone_2', 'Beef Burgers', 0, 0, 1, 0, 15]);

        // Test Vegan filter
        $results = $this->queryConcessions(true, false, false, false, 0);
        $veganIds = array_column($results, 'id');
        $this->assertContains('test_con_zone_1', $veganIds);
        $this->assertNotContains('test_con_zone_2', $veganIds);

        // Test Max Wait filter
        $results = $this->queryConcessions(false, false, false, false, 10);
        $waitIds = array_column($results, 'id');
        $this->assertContains('test_con_zone_1', $waitIds);
        $this->assertNotContains('test_con_zone_2', $waitIds);
    }
}
