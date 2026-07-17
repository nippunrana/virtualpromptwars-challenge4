<?php
/**
 * ArenaNexus 2026 Volunteer Co-Pilot Portal
 */
require_once __DIR__ . '/../config.php';

// Fetch list of volunteers for the selection dropdown
$volunteers = [];
if ($db) {
    try {
        $stmt = $db->query("SELECT id, name, primary_language, current_zone_id FROM volunteers ORDER BY id ASC");
        $volunteers = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Failed to fetch volunteers: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ArenaNexus - Volunteer Co-Pilot</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=1.0.2">
    <style>
        .volunteer-select-bar {
            background: var(--bg-secondary);
            border: 1px solid var(--border-glass);
            border-radius: var(--radius-lg);
            padding: var(--space-3);
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: var(--space-4);
        }
        .v-select {
            background: var(--bg-sunken);
            color: var(--color-text-primary);
            border: 1px solid var(--border-glass);
            padding: var(--space-2) var(--space-3);
            border-radius: var(--radius-md);
            outline: none;
            font-size: var(--text-sm);
        }
    </style>
</head>
<body style="background-color: var(--bg-sunken);">

    <div class="mobile-shell">
        <!-- Header -->
        <header class="mobile-header flex-between">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 18px;">🤝</span>
                <div>
                    <h1 style="font-size: var(--text-base); line-height: 1.2;">Volunteer Co-Pilot</h1>
                    <span style="font-size: 8px; color: var(--color-gold); font-weight: 800; letter-spacing: 0.15em;">FIFA 2026 GROUND NETWORK</span>
                </div>
            </div>
            <a href="../index.php" style="font-size: var(--text-xs); color: var(--color-text-muted);">Exit</a>
        </header>

        <!-- Scrollable Content -->
        <div class="mobile-content">
            
            <!-- Volunteer Selector -->
            <div class="volunteer-select-bar">
                <span style="font-size: var(--text-xs); font-weight: 700; color: var(--color-text-secondary);">Select Profile:</span>
                <select class="v-select" id="volunteer-selector">
                    <option value="">-- Choose Profile --</option>
                    <?php foreach ($volunteers as $v): ?>
                        <option value="<?php echo $v['id']; ?>" data-lang="<?php echo $v['primary_language']; ?>" data-zone="<?php echo $v['current_zone_id']; ?>">
                            <?php echo htmlspecialchars($v['name']); ?> (<?php echo htmlspecialchars($v['primary_language']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Profile Info Panel (Visible when selected) -->
            <div class="glass-panel" id="panel-profile" style="display: none; padding: var(--space-3); font-size: var(--text-xs); background: rgba(255,255,255,0.02);">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-2);">
                    <div><strong>Current Duty:</strong> <span id="lbl-profile-zone">Gate A</span></div>
                    <div><strong>Transceiver:</strong> <span id="lbl-profile-lang" style="color: var(--color-gold);">Spanish</span></div>
                </div>
            </div>

            <!-- Assigned Tasks Section -->
            <div class="glass-panel task-card" id="task-panel">
                <h3 style="font-size: var(--text-sm); margin-bottom: var(--space-3);">Your Assigned Tasks</h3>
                <div id="task-details-container">
                    <p style="text-align: center; color: var(--color-text-muted); font-size: var(--text-xs); padding: var(--space-4);">
                        Please select a profile to synchronize task dispatching.
                    </p>
                </div>
            </div>

            <!-- GenAI Translation Intercom -->
            <div class="glass-panel intercom-card" id="intercom-panel" style="display: none;">
                <h3 style="font-size: var(--text-sm);">GenAI Live Intercom Transceiver</h3>
                <p style="font-size: var(--text-xs); color: var(--color-text-muted); margin-bottom: var(--space-2);">
                    Translate and resolve fan queries contextually in real-time.
                </p>
                
                <div style="display: flex; flex-direction: column; gap: var(--space-2);">
                    <div style="display: flex; gap: var(--space-2); align-items: center;">
                        <span style="font-size: var(--text-xs); color: var(--color-text-muted);">From Fan Lang:</span>
                        <select class="v-select" id="translation-lang" style="padding: 0.35rem 0.5rem; font-size: var(--text-xs);">
                            <option value="Spanish">Spanish (Español)</option>
                            <option value="French">French (Français)</option>
                            <option value="Arabic">Arabic (العربية)</option>
                            <option value="Japanese">Japanese (日本語)</option>
                            <option value="German">German (Deutsch)</option>
                        </select>
                    </div>

                    <div class="intercom-input-wrapper">
                        <input type="text" class="form-input" id="inquiry-input" placeholder="e.g. ¿Dónde están los baños accesibles?">
                        <button class="btn btn-accent" id="btn-translate" style="padding: 0.75rem 1rem;">
                            Translate
                        </button>
                    </div>
                </div>

                <div class="intercom-result" id="intercom-result-container" style="display: none;">
                    <div style="font-weight: 800; font-size: 9px; color: var(--color-accent); letter-spacing: 0.05em; margin-bottom: var(--space-1); text-transform: uppercase;">
                        Gemini Multi-lingual Output
                    </div>
                    <div id="intercom-result-text" style="line-height: 1.4; color: var(--color-text-primary);"></div>
                </div>
            </div>

        </div>

        <!-- Sticky Status Footer / Warnings -->
        <div class="mobile-nav" style="border-top: 1px solid var(--border-glass); padding: 0 var(--space-4); display: flex; align-items: center; justify-content: center; font-size: var(--text-xs); color: var(--color-text-secondary);">
            <div id="footer-status">🟢 Standing By • GPS Synchronized</div>
        </div>
    </div>

    <!-- Script connection -->
    <script src="../assets/js/volunteer.js?v=1.0.2"></script>
</body>
</html>
