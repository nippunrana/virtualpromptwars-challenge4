# ArenaNexus 2026: GenAI Stadium Command & Co-Pilot Platform

This project is a GenAI-enabled stadium operations and fan experience platform designed for the FIFA World Cup 2026. It leverages simulated telemetry (sensor streams, capacity levels, incident queues) in PostgreSQL and processes them using Google Gemini 3.1 Flash-Lite to orchestrate operational control, volunteer coordination, and fan assistance.

## Technology Stack
- **Backend:** PHP 8.x
- **Database:** PostgreSQL (v14+)
- **Frontend:** Vanilla HTML5, Vanilla CSS3 (Custom properties, grid, flexbox, glassmorphism), Vanilla JS (Modern ES6+, cURL-based REST APIs)
- **Generative AI:** Google Gemini 3.1 Flash-Lite (via Google AI Studio REST API)

## Database Schema (PostgreSQL)

```sql
-- Stadium Zones (Gates, Sections, Concessions, Restrooms)
CREATE TABLE IF NOT EXISTS stadium_zones (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50) NOT NULL, -- 'gate', 'section', 'concession', 'restroom', 'transit'
    current_capacity INT DEFAULT 0,
    max_capacity INT DEFAULT 1000,
    congestion_density INT DEFAULT 0, -- Percentage (0 - 100)
    elevator_access BOOLEAN DEFAULT FALSE,
    status VARCHAR(20) DEFAULT 'Normal' -- 'Normal', 'Warning', 'Critical'
);

-- Concession details (Specialization, Dietary flags)
CREATE TABLE IF NOT EXISTS concessions (
    id VARCHAR(50) PRIMARY KEY REFERENCES stadium_zones(id) ON DELETE CASCADE,
    cuisine VARCHAR(100) NOT NULL,
    is_vegan BOOLEAN DEFAULT FALSE,
    is_vegetarian BOOLEAN DEFAULT FALSE,
    is_halal BOOLEAN DEFAULT FALSE,
    is_gluten_free BOOLEAN DEFAULT FALSE,
    avg_wait_time INT DEFAULT 5 -- minutes
);

-- Active Volunteers / Stewards
CREATE TABLE IF NOT EXISTS volunteers (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    current_zone_id VARCHAR(50) REFERENCES stadium_zones(id),
    status VARCHAR(20) DEFAULT 'Available', -- 'Available', 'Busy', 'Off-duty'
    primary_language VARCHAR(50) DEFAULT 'English',
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Incidents Queue
CREATE TABLE IF NOT EXISTS incidents (
    id SERIAL PRIMARY KEY,
    type VARCHAR(50) NOT NULL, -- 'medical', 'security', 'crowd', 'maintenance', 'fan_query'
    reported_by VARCHAR(50) DEFAULT 'sensor', -- 'sensor', 'volunteer', 'fan'
    zone_id VARCHAR(50) REFERENCES stadium_zones(id),
    description TEXT NOT NULL,
    severity VARCHAR(20) DEFAULT 'Low', -- 'Low', 'Medium', 'High', 'Critical'
    status VARCHAR(20) DEFAULT 'Open', -- 'Open', 'In Progress', 'Resolved'
    assigned_volunteer_id INT REFERENCES volunteers(id),
    ai_analysis JSONB, -- Stores Gemini's parsed JSON: severity, action_plan, recommended_volunteers, translations
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP
);

-- Live Broadcast Alerts
CREATE TABLE IF NOT EXISTS broadcasts (
    id SERIAL PRIMARY KEY,
    target_zone_id VARCHAR(50) REFERENCES stadium_zones(id),
    message_en TEXT NOT NULL,
    message_es TEXT,
    message_fr TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Telemetry Logs for Charts
CREATE TABLE IF NOT EXISTS telemetry_logs (
    id SERIAL PRIMARY KEY,
    zone_id VARCHAR(50) REFERENCES stadium_zones(id),
    congestion_density INT,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Directory Structure
- `/`
  - `index.php` -> Main entry point & role selector
  - `config.php` -> Global config file that parses and loads configurations from .env
  - `.env` -> Local environment file containing database credentials and GEMINI_API_KEY
  - `ai-context.md` -> (This file) Project blueprint
  - `api/`
    - `simulate.php` -> Simulates crowd metrics, bin levels, and triggers random incidents
    - `gemini.php` -> Helper class for cURL connection to Gemini API
    - `triage.php` -> Endpoint for incident creation, triaging with Gemini, and updating DB
    - `route.php` -> Dynamic route calculations (combining Dijkstra/A* congestion penalties with GenAI descriptions)
    - `concessions.php` -> Fetches dietary-specific concessions and wait times
  - `admin/`
    - `index.php` -> Admin Dashboard UI
  - `volunteer/`
    - `index.php` -> Volunteer Co-Pilot UI
  - `fan/`
    - `index.php` -> Fan Companion UI
  - `assets/`
    - `css/`
      - `style.css` -> Shared visual design tokens (colors, variables, resets, core classes)
    - `js/`
      - `dashboard.js` -> Ops center interactive controls and charts
      - `volunteer.js` -> Volunteer communications and translation helpers
      - `fan.js` -> Routing, dietary queries, and fan chatbot
