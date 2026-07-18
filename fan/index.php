<?php
/**
 * ArenaNexus 2026 Fan Companion Portal
 */
require_once __DIR__ . '/../config.php';

// Fetch zones list for navigation dropdowns
$zones = [];
if ($db) {
    try {
        $stmt = $db->query("SELECT id, name FROM stadium_zones ORDER BY type DESC, name ASC");
        $zones = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Failed to fetch zones: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ArenaNexus - Fan Companion</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=1.0.2">
    <style>
        .nav-tabs {
            display: flex;
            background: var(--bg-secondary);
            border: 1px solid var(--border-glass);
            border-radius: var(--radius-lg);
            padding: 4px;
            margin-bottom: var(--space-4);
        }
        .tab-btn {
            flex: 1;
            background: none;
            border: none;
            color: var(--color-text-secondary);
            font-size: 11px;
            font-weight: 700;
            padding: 0.5rem;
            border-radius: var(--radius-md);
            cursor: pointer;
            text-align: center;
            transition: all var(--duration-fast);
        }
        .tab-btn.active {
            background: var(--surface-glass-hover);
            color: var(--color-text-primary);
            border: 1px solid var(--border-glass-strong);
        }
        .route-step-pill {
            background: rgba(255,255,255,0.03);
            border: 1px solid var(--border-glass);
            border-radius: var(--radius-md);
            padding: var(--space-2) var(--space-3);
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: var(--text-xs);
        }
    </style>
</head>
<body style="background-color: var(--bg-sunken);">

    <div class="mobile-shell">
        <!-- Header -->
        <header class="mobile-header flex-between">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 18px;" aria-hidden="true">🎟️</span>
                <div>
                    <h1 style="font-size: var(--text-base); line-height: 1.2;">Fan Companion</h1>
                    <span style="font-size: 8px; color: var(--color-accent); font-weight: 800; letter-spacing: 0.15em;">FIFA 2026 SPECTATOR NET</span>
                </div>
            </div>
            <a href="../index.php" style="font-size: var(--text-xs); color: var(--text-muted);">Exit</a>
        </header>

        <!-- Scrollable Content -->
        <div class="mobile-content">
            
            <!-- Navigation Tabs -->
            <div class="nav-tabs">
                <button class="tab-btn active" id="tab-nav"><span aria-hidden="true">🗺️</span> Wayfinding</button>
                <button class="tab-btn" id="tab-food"><span aria-hidden="true">🍔</span> Food Finder</button>
                <button class="tab-btn" id="tab-chat"><span aria-hidden="true">💬</span> Assist Bot</button>
            </div>

            <!-- TAB 1: WAYFINDING PANEL -->
            <div class="tab-section" id="section-nav">
                <div class="glass-panel" style="display: flex; flex-direction: column; gap: var(--space-4);">
                    <h3 style="font-size: var(--text-sm);">Congestion-Aware Navigation</h3>
                    
                    <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                        <div>
                            <label for="route-start" style="display: block; font-size: 10px; color: var(--color-text-secondary); margin-bottom: 4px; text-transform: uppercase;">Start Location</label>
                            <select class="form-input" id="route-start" style="width: 100%;">
                                <option value="">-- Select Zone --</option>
                                <?php foreach ($zones as $zone): ?>
                                    <option value="<?php echo $zone['id']; ?>"><?php echo htmlspecialchars($zone['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="route-end" style="display: block; font-size: 10px; color: var(--color-text-secondary); margin-bottom: 4px; text-transform: uppercase;">Destination</label>
                            <select class="form-input" id="route-end" style="width: 100%;">
                                <option value="">-- Select Zone --</option>
                                <?php foreach ($zones as $zone): ?>
                                    <option value="<?php echo $zone['id']; ?>"><?php echo htmlspecialchars($zone['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <label for="route-step-free" style="display: inline-flex; align-items: center; gap: 8px; font-size: var(--text-xs); color: var(--color-text-secondary); cursor: pointer; margin-top: 4px;">
                            <input type="checkbox" id="route-step-free">
                            <span>Step-Free Route Only (Elevators/Ramps)</span>
                        </label>
                    </div>

                    <button class="btn btn-accent" id="btn-find-route" style="justify-content: center; padding: 0.75rem;">
                        🗺️ Find Optimal Route
                    </button>
                </div>

                <!-- Wayfinding Results Container -->
                <div class="glass-panel" id="route-result-container" style="display: none; margin-top: var(--space-4); border-color: var(--color-accent);" aria-live="polite">
                    <h4 style="font-size: var(--text-xs); color: var(--color-accent); font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; margin-bottom: var(--space-2);">
                        Calculated Wayfinder Guidance
                    </h4>
                    
                    <div id="route-explanation-text" style="font-size: var(--text-xs); line-height: 1.5; color: var(--color-text-primary); margin-bottom: var(--space-4);"></div>

                    <div style="font-weight: 800; font-size: 9px; color: var(--color-gold); text-transform: uppercase; margin-bottom: var(--space-2); letter-spacing: 0.05em;">
                        Navigational Sequence
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 8px;" id="route-steps-container"></div>
                </div>
            </div>

            <!-- TAB 2: DIETARY FOOD FINDER -->
            <div class="tab-section" id="section-food" style="display: none;">
                <div class="glass-panel" style="display: flex; flex-direction: column; gap: var(--space-4);">
                    <h3 style="font-size: var(--text-sm);">Dietary Amenity Finder</h3>
                    <p style="font-size: var(--text-xs); color: var(--color-text-muted);">
                        Filter stadium concession stands based on dietary requirements and find the shortest queues.
                    </p>

                    <!-- Dietary Filters -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-2);">
                        <label for="diet-vegan" style="display: inline-flex; align-items: center; gap: 8px; font-size: var(--text-xs); color: var(--color-text-secondary); cursor: pointer;">
                            <input type="checkbox" id="diet-vegan">
                            <span>🌱 Vegan</span>
                        </label>
                        <label for="diet-vegetarian" style="display: inline-flex; align-items: center; gap: 8px; font-size: var(--text-xs); color: var(--color-text-secondary); cursor: pointer;">
                            <input type="checkbox" id="diet-vegetarian">
                            <span>🥗 Vegetarian</span>
                        </label>
                        <label for="diet-non-veg" style="display: inline-flex; align-items: center; gap: 8px; font-size: var(--text-xs); color: var(--color-text-secondary); cursor: pointer;">
                            <input type="checkbox" id="diet-non-veg">
                            <span>🥩 Non-Veg</span>
                        </label>
                        <label for="diet-gf" style="display: inline-flex; align-items: center; gap: 8px; font-size: var(--text-xs); color: var(--color-text-secondary); cursor: pointer;">
                            <input type="checkbox" id="diet-gf">
                            <span>🌾 Gluten-Free</span>
                        </label>
                    </div>

                    <button class="btn btn-primary" id="btn-search-food" style="justify-content: center; padding: 0.75rem;">
                        🔍 Search Concessions
                    </button>
                </div>

                <!-- Food Results Container -->
                <div style="margin-top: var(--space-4); display: flex; flex-direction: column; gap: var(--space-2);" id="food-results-container" aria-live="polite"></div>
            </div>

            <!-- TAB 3: STADIUM ASSIST BOT -->
            <div class="tab-section" id="section-chat" style="display: none;">
                <div class="glass-panel" style="display: flex; flex-direction: column; gap: var(--space-3);">
                    <h3 style="font-size: var(--text-sm);">GenAI Grounded Assist Bot</h3>
                    
                    <div class="chat-container" id="chat-window" aria-live="polite">
                        <div class="chat-bubble bot">
                            Hi! I am your AI Stadium Assistant. I have live access to bathroom lines, concession wait times, and gate traffic. Ask me anything! (e.g. "Where can I get vegan food with the shortest line?")
                        </div>
                    </div>

                    <div class="intercom-input-wrapper">
                        <label for="chat-input" class="sr-only">Ask a stadium operation question</label>
                        <input type="text" class="form-input" id="chat-input" placeholder="Ask a question...">
                        <button class="btn btn-accent" id="btn-send-chat" style="padding: 0.75rem 1rem;">
                            Send
                        </button>
                    </div>
                </div>
            </div>

        </div>

        <!-- Sticky Footer Status -->
        <div class="mobile-nav" style="border-top: 1px solid var(--border-glass); padding: 0 var(--space-4); display: flex; align-items: center; justify-content: center; font-size: var(--text-xs); color: var(--color-text-secondary);">
            <div id="fan-footer-status" aria-live="polite">🏟️ Welcome to ArenaNexus • Enjoy the Match!</div>
        </div>
    </div>

    <!-- Script connection -->
    <script src="../assets/js/fan.js?v=1.0.2"></script>
</body>
</html>
