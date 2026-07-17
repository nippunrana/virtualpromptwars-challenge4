<?php
/**
 * ArenaNexus 2026 Database Setup & Seeding Script
 */

require_once __DIR__ . '/../config.php';

if (!$db) {
    die("Database connection is not configured or failed to initialize. Please check your .env file.\n");
}

echo "Initializing database tables...\n";

try {
    // Enable error outputs
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create Table: Stadium Zones
    $db->exec("CREATE TABLE IF NOT EXISTS stadium_zones (
        id VARCHAR(50) PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        type VARCHAR(50) NOT NULL,
        current_capacity INT DEFAULT 0,
        max_capacity INT DEFAULT 1000,
        congestion_density INT DEFAULT 0,
        elevator_access BOOLEAN DEFAULT FALSE,
        status VARCHAR(20) DEFAULT 'Normal'
    )");
    echo "- Table 'stadium_zones' created or exists.\n";

    // Create Table: Concessions
    $db->exec("CREATE TABLE IF NOT EXISTS concessions (
        id VARCHAR(50) PRIMARY KEY REFERENCES stadium_zones(id) ON DELETE CASCADE,
        cuisine VARCHAR(100) NOT NULL,
        is_vegan BOOLEAN DEFAULT FALSE,
        is_vegetarian BOOLEAN DEFAULT FALSE,
        is_halal BOOLEAN DEFAULT FALSE,
        is_gluten_free BOOLEAN DEFAULT FALSE,
        avg_wait_time INT DEFAULT 5
    )");
    echo "- Table 'concessions' created or exists.\n";

    // Create Table: Volunteers
    $db->exec("CREATE TABLE IF NOT EXISTS volunteers (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        current_zone_id VARCHAR(50) REFERENCES stadium_zones(id) ON DELETE SET NULL,
        status VARCHAR(20) DEFAULT 'Available',
        primary_language VARCHAR(50) DEFAULT 'English',
        last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "- Table 'volunteers' created or exists.\n";

    // Create Table: Incidents
    $db->exec("CREATE TABLE IF NOT EXISTS incidents (
        id SERIAL PRIMARY KEY,
        type VARCHAR(50) NOT NULL,
        reported_by VARCHAR(50) DEFAULT 'sensor',
        zone_id VARCHAR(50) REFERENCES stadium_zones(id) ON DELETE SET NULL,
        description TEXT NOT NULL,
        severity VARCHAR(20) DEFAULT 'Low',
        status VARCHAR(20) DEFAULT 'Open',
        assigned_volunteer_id INT REFERENCES volunteers(id) ON DELETE SET NULL,
        ai_analysis JSONB,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        resolved_at TIMESTAMP
    )");
    echo "- Table 'incidents' created or exists.\n";

    // Create Table: Broadcasts
    $db->exec("CREATE TABLE IF NOT EXISTS broadcasts (
        id SERIAL PRIMARY KEY,
        target_zone_id VARCHAR(50) REFERENCES stadium_zones(id) ON DELETE CASCADE,
        message_en TEXT NOT NULL,
        message_es TEXT,
        message_fr TEXT,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "- Table 'broadcasts' created or exists.\n";

    // Create Table: Telemetry Logs
    $db->exec("CREATE TABLE IF NOT EXISTS telemetry_logs (
        id SERIAL PRIMARY KEY,
        zone_id VARCHAR(50) REFERENCES stadium_zones(id) ON DELETE CASCADE,
        congestion_density INT,
        recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "- Table 'telemetry_logs' created or exists.\n";

    echo "\nSeeding initial data...\n";

    // 1. Seed Stadium Zones
    $zones = [
        ['gate_a', 'Gate A (Main East Entrance)', 'gate', 150, 2000, 7, false, 'Normal'],
        ['gate_b', 'Gate B (South Entrance)', 'gate', 380, 1500, 25, false, 'Normal'],
        ['gate_c', 'Gate C (West VIP & Accessibility)', 'gate', 550, 800, 68, true, 'Normal'],
        ['gate_d', 'Gate D (North Transit Link)', 'gate', 850, 1000, 85, false, 'Warning'],
        ['gate_e', 'Gate E (Press & Staff)', 'gate', 60, 500, 12, true, 'Normal'],
        ['gate_f', 'Gate F (Premium Suites)', 'gate', 120, 600, 20, true, 'Normal'],
        
        ['sec_101', 'Section 101 (Lower Deck East)', 'section', 420, 500, 84, false, 'Warning'],
        ['sec_102', 'Section 102 (Lower Deck South)', 'section', 150, 500, 30, false, 'Normal'],
        ['sec_103', 'Section 103 (Lower Deck West - ADA)', 'section', 80, 100, 80, true, 'Warning'],
        ['sec_104', 'Section 104 (Lower Deck North)', 'section', 210, 500, 42, false, 'Normal'],
        ['sec_201', 'Section 201 (Upper Deck East)', 'section', 310, 800, 38, false, 'Normal'],
        ['sec_202', 'Section 202 (Upper Deck South)', 'section', 120, 800, 15, false, 'Normal'],
        ['sec_203', 'Section 203 (Upper Deck West)', 'section', 640, 800, 80, true, 'Warning'],
        ['sec_204', 'Section 204 (Upper Deck North)', 'section', 150, 800, 18, false, 'Normal'],

        ['con_north', 'Food Court North', 'concession', 180, 400, 45, true, 'Normal'],
        ['con_south', 'Food Court South', 'concession', 320, 400, 80, true, 'Warning'],
        ['con_grill', 'Golden Boot Grills', 'concession', 45, 100, 45, false, 'Normal'],
        ['con_tacos', 'Aztec Tacos', 'concession', 90, 120, 75, false, 'Normal'],
        ['con_salad', 'Green Field Salads', 'concession', 15, 80, 18, false, 'Normal'],
        ['con_cafe', 'FIFA Café', 'concession', 50, 80, 62, true, 'Normal'],

        ['rest_gate_a', 'Restrooms Gate A', 'restroom', 10, 30, 33, false, 'Normal'],
        ['rest_gate_c', 'Restrooms Gate C (ADA)', 'restroom', 25, 30, 83, true, 'Warning'],
        ['rest_sec_103', 'Restrooms Section 103', 'restroom', 5, 20, 25, false, 'Normal'],
        ['rest_sec_201', 'Restrooms Section 201', 'restroom', 8, 30, 26, false, 'Normal'],

        ['transit_metro', 'Metro Stadium Station', 'transit', 4500, 5000, 90, true, 'Critical'],
        ['transit_valet', 'VIP Valet Parking', 'transit', 50, 200, 25, true, 'Normal'],
        ['transit_shuttle', 'Shuttle Bus Hub', 'transit', 1200, 1500, 80, true, 'Warning'],
        ['transit_rideshare', 'Rideshare Drop-off Zone', 'transit', 250, 400, 62, false, 'Normal']
    ];

    $insertZone = $db->prepare("INSERT INTO stadium_zones (id, name, type, current_capacity, max_capacity, congestion_density, elevator_access, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT (id) DO UPDATE SET 
        current_capacity = EXCLUDED.current_capacity, 
        congestion_density = EXCLUDED.congestion_density, 
        status = EXCLUDED.status");

    foreach ($zones as $zone) {
        $mappedZone = $zone;
        $mappedZone[6] = $zone[6] ? 1 : 0; // elevator_access
        $insertZone->execute($mappedZone);
    }
    echo "- Seeded " . count($zones) . " stadium zones.\n";

    // 2. Seed Concessions
    $concessions = [
        ['con_grill', 'Burgers & Fries', false, false, true, false, 12],
        ['con_tacos', 'Mexican Street Tacos', true, true, false, true, 8],
        ['con_salad', 'Organic Salads & Wraps', true, true, true, true, 5],
        ['con_cafe', 'Coffee & Pastries', true, false, true, true, 4],
        ['con_north', 'Pizza & Hot Dogs', true, false, false, false, 15],
        ['con_south', 'Halal Kebab & Rice', false, false, true, true, 10]
    ];

    $insertConcession = $db->prepare("INSERT INTO concessions (id, cuisine, is_vegan, is_vegetarian, is_halal, is_gluten_free, avg_wait_time) 
        VALUES (?, ?, ?, ?, ?, ?, ?) ON CONFLICT (id) DO UPDATE SET 
        cuisine = EXCLUDED.cuisine, 
        avg_wait_time = EXCLUDED.avg_wait_time");

    foreach ($concessions as $concession) {
        $mappedConcession = $concession;
        $mappedConcession[2] = $concession[2] ? 1 : 0; // is_vegan
        $mappedConcession[3] = $concession[3] ? 1 : 0; // is_vegetarian
        $mappedConcession[4] = $concession[4] ? 1 : 0; // is_halal
        $mappedConcession[5] = $concession[5] ? 1 : 0; // is_gluten_free
        $insertConcession->execute($mappedConcession);
    }
    echo "- Seeded " . count($concessions) . " concession details.\n";

    // 3. Seed Volunteers
    $volunteers = [
        ['Carlos Gomez', 'gate_a', 'Available', 'Spanish'],
        ['Jean-Pierre', 'gate_c', 'Available', 'French'],
        ['Sarah Jenkins', 'sec_102', 'Available', 'English'],
        ['Fatima Al-Sayed', 'transit_valet', 'Available', 'Arabic'],
        ['Yuki Tanaka', 'gate_b', 'Available', 'Japanese'],
        ['John Doe', 'transit_metro', 'Available', 'English']
    ];

    // Truncate first to avoid duplicate seed rows
    $db->exec("TRUNCATE TABLE volunteers CASCADE");
    $insertVolunteer = $db->prepare("INSERT INTO volunteers (name, current_zone_id, status, primary_language) VALUES (?, ?, ?, ?)");
    foreach ($volunteers as $volunteer) {
        $insertVolunteer->execute($volunteer);
    }
    echo "- Seeded " . count($volunteers) . " volunteers.\n";

    // 4. Seed Telemetry Logs (Initial values)
    $db->exec("TRUNCATE TABLE telemetry_logs");
    $insertTelemetry = $db->prepare("INSERT INTO telemetry_logs (zone_id, congestion_density) VALUES (?, ?)");
    foreach ($zones as $zone) {
        $insertTelemetry->execute([$zone[0], $zone[5]]);
    }
    echo "- Seeded initial telemetry logs.\n";

    echo "\nDatabase schema built and seeded successfully!\n";

} catch (PDOException $e) {
    die("Database setup failed: " . $e->getMessage() . "\n");
}
