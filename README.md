# ArenaNexus 2026: GenAI Stadium Control & Co-Pilot Platform

ArenaNexus 2026 is an all-in-one, intelligent stadium operations and fan experience platform designed for the **FIFA World Cup 2026**. Built with a lightweight, zero-compilation stack (**PHP 8.x, PostgreSQL, Vanilla HTML5/CSS3/JS**), the platform leverages real-time database telemetry and **Google Gemini 3.1 Flash-Lite** to orchestrate real-time command, field volunteer dispatch, and fan assistance.

---

## 1. Chosen Verticals & target Personas
Instead of focusing on a single narrow vertical, ArenaNexus 2026 integrates **five core verticals** from the challenge guidelines into a cohesive, three-sided spectator and operations platform:
1.  **Operational Intelligence (Venue Command Center):** A desktop dashboard streaming live telemetry, mapping crowd congestion, and orchestrating incident response.
2.  **Multilingual Assistance (Volunteer Co-Pilot):** A mobile web interface for stewards that translates fan inquiries on-the-fly and pulls SOPs.
3.  **Dynamic Navigation & Accessibility (Fan Companion):** A fan web portal that computes congestion-avoiding and step-free paths (prioritizing elevators/ramps for wheelchair access).
4.  **Smart Amenities (Dietary Finder):** A concessions tracker filtering food stalls by dietary constraints (Vegan, Non-Veg, Gluten-Free) and wait times.
5.  **Smart Logistics (Telemetry Simulator):** A dynamic backend that fluctuates stadium capacity densities, queues, and triggers alerts automatically.

---

## 2. Technical Architecture & Approach

```
┌─────────────────────────────────┐
│     Live Telemetry Simulator    │
│       (api/simulate.php)        │
└────────────────┬────────────────┘
                 │ (Saves stats & logs)
                 ▼
┌─────────────────────────────────┐       (Queries)       ┌──────────────────────┐
│       PostgreSQL Database       │◄──────────────────────┤  GenAI Triage Engine  │
│   (Zones, Concessions, Staff)   │                       │   (api/triage.php)   │
└────────────────┬────────────────┘                       └──────────┬───────────┘
                 │                                                   │
                 │ (Fetches current metrics & layouts)               │ (cURL REST call)
                 ▼                                                   ▼
┌────────────────────────────────────────────────────────────────────┴───────────┐
│                           Google Gemini 3.1 Flash-Lite                         │
│                    (Triages, translates, and drafts directions)                │
└────────────────┬───────────────────────────────────────────────────┬───────────┘
                 │                                                   │
                 ▼                                                   ▼
┌─────────────────────────────────┐                       ┌──────────────────────┐
│    Light-Mode Web Interfaces    │                       │  Grounded Chatbot    │
│  (Admin, Volunteer, Fan Panels)  │                       │    (api/chat.php)    │
└─────────────────────────────────┘                       └──────────────────────┘
```

### Routing Logic (Dijkstra + Congestion Penalty)
Instead of static shortest path logic, ArenaNexus implements a weighted pathfinder in [api/route.php](api/route.php):
*   **Base Weights:** Represents standard walking distances between connected stadium locations.
*   **Congestion Penalty:** The edge weight entering node $V$ is multiplied by $(1 + \text{congestion\_density} / 100)$. Paths automatically diverge around bottlenecked zones.
*   **Accessibility Constraints:** When a fan toggles the "Step-Free" filter, any connection leading to a zone without elevator access is heavily penalized (weight $+500$), forcing the router to find elevator-assisted paths.
*   **GenAI Verbalization:** The sequence of zones is sent to Gemini, which generates a brief, conversational description explaining *why* the path was selected (e.g. bypassing stairs or high crowd queues).

### AI Triage & Dispatch Logic
When an incident is reported (via sensor alert or user input):
*   [api/triage.php](api/triage.php) fetches the description and queries the list of currently available volunteers from PostgreSQL.
*   It asks Gemini 3.1 Flash-Lite (using JSON Schema Mode to ensure structured output) to:
    1.  Determine incident severity (`Low`, `Medium`, `High`, `Critical`).
    2.  Select the best volunteer (e.g., matching a Spanish-speaking volunteer to a Spanish-speaking family, or selecting the volunteer closest to the incident zone).
    3.  Formulate a step-by-step SOP checklist for the volunteer.
    4.  Draft warning broadcasts to be flashed in the affected zones in English, Spanish, and French.
*   The database updates the incident status to "In Progress," sets the volunteer's status to "Busy," and registers the broadcast announcements.

---

## 3. How the Solution Works (Setup Guide)

### Prerequisites
*   A VPS or local server running **Apache/Nginx** and **PHP 8.x**.
*   **PostgreSQL** database service active.
*   A **Google Gemini API Key** (from Google AI Studio).

### Setup Steps
1.  **Configure Environment Variables:**
    Create a `.env` file in the project root containing your database details and Gemini API Key:
    ```env
    DB_HOST=localhost
    DB_PORT=5432
    DB_NAME=promptwars_challenge4
    DB_USER=promptwars_user
    DB_PASSWORD=promptwars_pass_123
    GEMINI_API_KEY=your_actual_gemini_api_key
    ```
2.  **Initialize the Database:**
    Open a terminal in the project directory and run the database setup script, or hit `/database/setup.php` in your browser:
    ```bash
    php database/setup.php
    ```
    This script creates the table schemas and seeds the database with gates, sections, concessions, and default volunteers.
3.  **Run the Portals:**
    Access the entry ticket portal at `/index.php`. From there:
    *   Open the **Ops Command Center** on a desktop monitor. Click **⚡ Simulate Match Tick** to vary queue stats and trigger AI incidents.
    *   Open the **Volunteer App** on a mobile screen. Select a volunteer profile to see assigned dispatches and access the real-time query translator.
    *   Open the **Fan Companion** to test the congestion-avoiding pathfinder and concessions search.

---

## 4. Key Assumptions Made
1.  **Telemetry Data:** Simulated IoT sensor feeds (density percentage, concession lines) are updated dynamically using the simulation tick script.
2.  **Gemini API Key:** If `GEMINI_API_KEY` is not provided in `.env`, the helper classes gracefully fall back to a local mock generator, maintaining full app interactivity for offline evaluation.
3.  **Database User privileges:** The PostgreSQL user has write permissions and can modify the `public` schema.
