<?php
/**
 * ArenaNexus 2026 Entry Portal
 * Styled as a premium World Cup 2026 Match Ticket.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ArenaNexus 2026 - Entrance Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="ticket-portal">
    <div class="ticket-wrapper">
        <div class="ticket">
            <!-- Ticket Main Section -->
            <div class="ticket-main">
                <div>
                    <div class="ticket-header">
                        <div style="width: 12px; height: 12px; border-radius: 50%; background-color: var(--color-gold);"></div>
                        <h2 class="ticket-title">FIFA WORLD CUP 2026</h2>
                    </div>
                    <div style="font-size: var(--text-xs); color: var(--color-gold); font-weight: 800; letter-spacing: 0.15em; margin-top: var(--space-2);">
                        STADIUM OPERATIONS PLATFORM
                    </div>
                </div>

                <div class="ticket-body">
                    <h3 style="font-size: var(--text-xl); margin-bottom: var(--space-2); color: var(--color-text-primary);">ArenaNexus Command Suite</h3>
                    <p style="font-size: var(--text-sm); line-height: 1.4;">
                        Select an operational interface to access real-time stadium metrics, AI-driven crowd control dispatching, and multilingual guest assistance services.
                    </p>
                </div>

                <div class="ticket-meta">
                    <div>
                        <div class="ticket-meta-label">MATCH STATUS</div>
                        <div class="ticket-meta-val" style="color: var(--color-normal);">LIVE • IN PROGRESS</div>
                    </div>
                    <div>
                        <div class="ticket-meta-label">VENUE</div>
                        <div class="ticket-meta-val">ArenaNexus Center</div>
                    </div>
                    <div>
                        <div class="ticket-meta-label">TELEMETRY STACKS</div>
                        <div class="ticket-meta-val">PHP + Postgres + Gemini</div>
                    </div>
                    <div>
                        <div class="ticket-meta-label">GATEWAY CODE</div>
                        <div class="ticket-meta-val" style="font-family: monospace;">ARNX-2026-LIVE</div>
                    </div>
                </div>
            </div>

            <!-- Ticket Stub Section -->
            <div class="ticket-stub">
                <div style="text-align: center; width: 100%;">
                    <div class="ticket-meta-label" style="margin-bottom: var(--space-3);">SELECT INTERFACE</div>
                    
                    <div class="ticket-choices">
                        <a href="admin/index.php" class="btn btn-accent ticket-choice-btn">
                            Ops Command
                        </a>
                        <a href="volunteer/index.php" class="btn btn-secondary ticket-choice-btn">
                            Volunteer App
                        </a>
                        <a href="fan/index.php" class="btn btn-secondary ticket-choice-btn">
                            Fan Companion
                        </a>
                    </div>
                </div>

                <div class="barcode-container">
                    <div class="barcode"></div>
                    <div style="font-family: monospace; font-size: 8px; color: var(--color-text-muted); letter-spacing: 0.3em;">
                        *ARNX-2026*
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
