<?php
/**
 * ArenaNexus 2026 Admin Operations Dashboard
 */
require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ArenaNexus - Operations Control Center</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=1.0.1">
    <style>
        /* Specific page tweaks for split view dashboard */
        .admin-main {
            padding: var(--space-6);
            display: grid;
            grid-template-columns: 1fr;
            gap: var(--space-5);
        }
        @media (min-width: 1200px) {
            .admin-main {
                grid-template-columns: 1.4fr 1fr;
            }
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: var(--space-4);
            margin-bottom: var(--space-5);
        }
        .stat-card {
            background: var(--surface-glass);
            border: 1px solid var(--border-glass);
            border-radius: var(--radius-lg);
            padding: var(--space-4);
            text-align: center;
        }
        .stat-val {
            font-family: var(--font-display);
            font-size: var(--text-xl);
            font-weight: 800;
            color: var(--color-accent);
            margin-top: var(--space-1);
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar Navigation -->
        <aside class="admin-sidebar">
            <div>
                <div class="admin-header">
                    <a href="../index.php" style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 20px;">🛡️</span>
                        <h2 style="font-size: var(--text-lg); text-transform: uppercase; color: var(--color-text-primary);">ArenaNexus</h2>
                    </a>
                    <div style="font-size: 10px; color: var(--color-gold); font-weight: 800; letter-spacing: 0.1em; margin-top: 4px;">
                        OPS CONTROL CENTRE
                    </div>
                </div>

                <nav>
                    <a href="#" class="admin-nav-item active">
                        <span>📊</span> Control Dashboard
                    </a>
                    <a href="../volunteer/index.php" class="admin-nav-item">
                        <span>🤝</span> Volunteer Portal
                    </a>
                    <a href="../fan/index.php" class="admin-nav-item">
                        <span>🎟️</span> Fan Companion
                    </a>
                </nav>
            </div>

            <div style="font-size: var(--text-xs); color: var(--color-text-muted);">
                FIFA World Cup 2026 Operations Suite<br>
                VPS Instance: Active
            </div>
        </aside>

        <!-- Main Body -->
        <main style="flex: 1; overflow-y: auto; max-height: 100vh;">
            <!-- Header bar -->
            <header class="flex-between" style="padding: var(--space-5) var(--space-6); background: var(--bg-secondary); border-bottom: 1px solid var(--border-glass);">
                <div>
                    <h1 style="font-size: var(--text-xl);">Stadium Command Center</h1>
                    <p style="font-size: var(--text-xs);">Match Day Operations & Real-Time Incident Response Panel</p>
                </div>
                <div class="sim-controls">
                    <button class="btn btn-primary" id="btn-tick">
                        ⚡ Simulate Match Tick
                    </button>
                </div>
            </header>

            <!-- Dashboard Split Layout -->
            <div class="admin-main">
                
                <!-- Left Panel: SVG Map and Telemetry Grid -->
                <div style="display: flex; flex-direction: column; gap: var(--space-5);">
                    <!-- Statistics Summary -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <p style="font-size: 10px; text-transform: uppercase;">Average Wait</p>
                            <div class="stat-val" id="stat-wait">9.5m</div>
                        </div>
                        <div class="stat-card">
                            <p style="font-size: 10px; text-transform: uppercase;">Active Alerts</p>
                            <div class="stat-val" id="stat-incidents" style="color: var(--color-warning);">0</div>
                        </div>
                        <div class="stat-card">
                            <p style="font-size: 10px; text-transform: uppercase;">Critical Zones</p>
                            <div class="stat-val" id="stat-critical" style="color: var(--color-critical);">1</div>
                        </div>
                        <div class="stat-card">
                            <p style="font-size: 10px; text-transform: uppercase;">Active Staff</p>
                            <div class="stat-val" id="stat-staff" style="color: var(--color-normal);">6</div>
                        </div>
                    </div>

                    <!-- Interactive SVG Stadium Map -->
                    <div class="glass-panel map-card">
                        <div style="position: absolute; top: var(--space-4); left: var(--space-4); font-size: 10px; font-weight: 800; color: var(--color-gold);">
                            LIVE STADIUM CONGESTION MAP
                        </div>
                        
                        <svg class="stadium-svg" viewBox="0 0 600 600">
                            <!-- Outer Boundary (Outer Ring Transit/Parking) -->
                            <rect x="10" y="10" width="580" height="580" rx="30" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="2" />
                            
                            <!-- Transit Nodes (Corners) -->
                            <circle class="stadium-zone-path" id="transit_metro" cx="50" cy="50" r="30" data-name="Metro Stadium Station" />
                            <text x="50" y="55" fill="#fff" font-size="9" text-anchor="middle" font-family="sans-serif" pointer-events="none">METRO</text>
                            
                            <circle class="stadium-zone-path" id="transit_shuttle" cx="550" cy="50" r="30" data-name="Shuttle Bus Hub" />
                            <text x="550" y="55" fill="#fff" font-size="9" text-anchor="middle" font-family="sans-serif" pointer-events="none">BUS</text>

                            <circle class="stadium-zone-path" id="transit_valet" cx="50" cy="550" r="30" data-name="VIP Valet Parking" />
                            <text x="50" y="555" fill="#fff" font-size="9" text-anchor="middle" font-family="sans-serif" pointer-events="none">VALET</text>

                            <circle class="stadium-zone-path" id="transit_rideshare" cx="550" cy="550" r="30" data-name="Rideshare Drop-off Zone" />
                            <text x="550" y="555" fill="#fff" font-size="9" text-anchor="middle" font-family="sans-serif" pointer-events="none">RIDE</text>

                            <!-- Stadium Ring structure (Gates A-F) -->
                            <circle cx="300" cy="300" r="210" fill="none" stroke="rgba(255,255,255,0.03)" stroke-width="40" />
                            
                            <!-- Gates as segments on the outer circle -->
                            <path class="stadium-zone-path" id="gate_a" d="M 450 300 A 150 150 0 0 0 375 170" fill="none" stroke-width="20" data-name="Gate A" />
                            <text x="430" y="240" fill="#fff" font-size="10" font-family="sans-serif" pointer-events="none">Gate A</text>

                            <path class="stadium-zone-path" id="gate_b" d="M 375 170 A 150 150 0 0 0 225 170" fill="none" stroke-width="20" data-name="Gate B" />
                            <text x="300" y="145" fill="#fff" font-size="10" text-anchor="middle" font-family="sans-serif" pointer-events="none">Gate B</text>

                            <path class="stadium-zone-path" id="gate_c" d="M 225 170 A 150 150 0 0 0 150 300" fill="none" stroke-width="20" data-name="Gate C (ADA)" />
                            <text x="170" y="240" fill="#fff" font-size="10" text-anchor="end" font-family="sans-serif" pointer-events="none">Gate C</text>

                            <path class="stadium-zone-path" id="gate_d" d="M 150 300 A 150 150 0 0 0 225 430" fill="none" stroke-width="20" data-name="Gate D" />
                            <text x="170" y="370" fill="#fff" font-size="10" text-anchor="end" font-family="sans-serif" pointer-events="none">Gate D</text>

                            <path class="stadium-zone-path" id="gate_e" d="M 225 430 A 150 150 0 0 0 375 430" fill="none" stroke-width="20" data-name="Gate E" />
                            <text x="300" y="465" fill="#fff" font-size="10" text-anchor="middle" font-family="sans-serif" pointer-events="none">Gate E</text>

                            <path class="stadium-zone-path" id="gate_f" d="M 375 430 A 150 150 0 0 0 450 300" fill="none" stroke-width="20" data-name="Gate F" />
                            <text x="430" y="370" fill="#fff" font-size="10" font-family="sans-serif" pointer-events="none">Gate F</text>

                            <!-- Internal Bowl Sections -->
                            <rect class="stadium-zone-path" id="sec_101" x="340" y="250" width="70" height="50" rx="4" data-name="Section 101" />
                            <text x="375" y="280" fill="#fff" font-size="8" text-anchor="middle" pointer-events="none">Sec 101</text>

                            <rect class="stadium-zone-path" id="sec_102" x="265" y="210" width="70" height="50" rx="4" data-name="Section 102" />
                            <text x="300" y="240" fill="#fff" font-size="8" text-anchor="middle" pointer-events="none">Sec 102</text>

                            <rect class="stadium-zone-path" id="sec_103" x="190" y="250" width="70" height="50" rx="4" data-name="Section 103 (ADA)" />
                            <text x="225" y="280" fill="#fff" font-size="8" text-anchor="middle" pointer-events="none">Sec 103</text>

                            <rect class="stadium-zone-path" id="sec_104" x="265" y="340" width="70" height="50" rx="4" data-name="Section 104" />
                            <text x="300" y="370" fill="#fff" font-size="8" text-anchor="middle" pointer-events="none">Sec 104</text>
                            
                            <!-- Inner Pitch (Center visual) -->
                            <rect x="250" y="270" width="100" height="60" rx="2" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="1.5" pointer-events="none" />
                            <circle cx="300" cy="300" r="15" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="1.5" pointer-events="none" />
                            <line x1="300" y1="270" x2="300" y2="330" stroke="rgba(255,255,255,0.15)" stroke-width="1.5" pointer-events="none" />
                        </svg>
                        
                        <!-- Mini Map Legend -->
                        <div style="position: absolute; bottom: var(--space-4); right: var(--space-4); display: flex; gap: 12px; font-size: 8px;">
                            <div style="display: flex; align-items: center; gap: 4px;">
                                <div style="width: 8px; height: 8px; background: rgba(16, 185, 129, 0.5); border: 1px solid var(--color-normal); border-radius: 2px;"></div> Normal
                            </div>
                            <div style="display: flex; align-items: center; gap: 4px;">
                                <div style="width: 8px; height: 8px; background: rgba(249, 115, 22, 0.5); border: 1px solid var(--color-warning); border-radius: 2px;"></div> Warn
                            </div>
                            <div style="display: flex; align-items: center; gap: 4px;">
                                <div style="width: 8px; height: 8px; background: rgba(239, 68, 68, 0.5); border: 1px solid var(--color-critical); border-radius: 2px;"></div> Critical
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Panel: Active Incidents & Broadcast Alerts -->
                <div style="display: flex; flex-direction: column; gap: var(--space-5);">
                    
                    <!-- AI Incident Feed -->
                    <div class="glass-panel">
                        <div class="flex-between" style="margin-bottom: var(--space-4);">
                            <h3 style="font-size: var(--text-base);">Active Incidents Feed (AI Triaged)</h3>
                            <span class="badge badge-normal" id="lbl-inc-count">0 Open</span>
                        </div>
                        <div class="incident-list" id="incident-container">
                            <p style="text-align: center; color: var(--color-text-muted); font-size: var(--text-sm); padding: var(--space-5);">
                                Monitoring telemetry streams. No active incidents.
                            </p>
                        </div>
                    </div>

                    <!-- Live Multilingual Broadcast Announcements -->
                    <div class="glass-panel">
                        <div class="flex-between" style="margin-bottom: var(--space-4);">
                            <h3 style="font-size: var(--text-base);">Live Broadcast Queue</h3>
                            <span style="font-size: 10px; color: var(--color-accent); font-weight: 800;">AUTO-TRANSLATED</span>
                        </div>
                        <div class="broadcast-list" id="broadcast-container">
                            <p style="text-align: center; color: var(--color-text-muted); font-size: var(--text-sm); padding: var(--space-5);">
                                No warnings currently broadcasted in the venue.
                            </p>
                        </div>
                    </div>

                </div>

            </div>
        </main>
    </div>

    <!-- Script triggers -->
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
