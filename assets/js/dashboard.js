/**
 * ArenaNexus 2026 Dashboard Controller
 */

document.addEventListener('DOMContentLoaded', () => {
    // UI Elements
    const btnTick = document.getElementById('btn-tick');
    const statWait = document.getElementById('stat-wait');
    const statIncidentsCount = document.getElementById('stat-incidents');
    const statCritical = document.getElementById('stat-critical');
    const statStaff = document.getElementById('stat-staff');
    const lblIncCount = document.getElementById('lbl-inc-count');
    const incidentContainer = document.getElementById('incident-container');
    const broadcastContainer = document.getElementById('broadcast-container');

    // Init data load
    refreshDashboard();

    // Set polling interval for updates (every 5 seconds)
    const updateInterval = setInterval(refreshDashboard, 5000);

    // Click: Manual Simulation Tick
    if (btnTick) {
        btnTick.addEventListener('click', async () => {
            btnTick.disabled = true;
            btnTick.textContent = '⚡ Simulating...';
            
            try {
                const response = await fetch('../api/simulate.php');
                const data = await response.json();
                
                if (data.status === 'success') {
                    // Show a transient toast notification if a new incident was generated
                    if (data.new_incident) {
                        showToast(`🚨 New AI Triage alert created: ${data.new_incident.type.toUpperCase()}`);
                    }
                    refreshDashboard();
                } else {
                    console.error('Simulation failed:', data.message);
                }
            } catch (err) {
                console.error('Error during tick:', err);
            } finally {
                btnTick.disabled = false;
                btnTick.textContent = '⚡ Simulate Match Tick';
            }
        });
    }

    /**
     * Fetch status updates and redraw dashboard elements
     */
    async function refreshDashboard() {
        try {
            // 1. Fetch Zones Status (for SVG map and stats)
            const zoneResponse = await fetch('../api/route.php?start=transit_metro&end=sec_103'); // generic fetch to read zone table (we can read via simulated endpoint too)
            // Wait, route.php doesn't give all zones. Let's fetch the zones from triage.php list or write a simple query,
            // or let's update route.php or just write a small API endpoint if we need to.
            // Wait! In config.php we can read zones from the database. Let's make sure we have a way to fetch zones.
            // Let's look at the database zones. We can add a simple GET filter to triage.php or concessions.php, or just create a zones endpoint.
            // Actually, we can get active incidents and broadcasts. Let's fetch zones by creating a simple script, or let's read the incidents to see who is active.
            // Let's check how we can fetch zones data. Wait, let's query the database directly or write a small `/api/zones.php` endpoint.
            // Yes! Writing a small `/api/zones.php` is perfect, clean, and complies with Task-level boundaries.
            // Let's create `/api/zones.php` next. Let's assume it exists and returns all zones.
            const responseZones = await fetch('../api/zones.php');
            const zones = await responseZones.json();
            
            updateStadiumMap(zones);
            updateGlobalStats(zones);

            // 2. Fetch Incidents
            const responseIncidents = await fetch('../api/triage.php');
            const incidents = await responseIncidents.json();
            updateIncidentList(incidents);

            // 3. Fetch Broadcasts
            const responseBroadcasts = await fetch('../api/broadcasts.php'); // We'll make this file next too!
            const broadcasts = await responseBroadcasts.json();
            updateBroadcastList(broadcasts);

        } catch (err) {
            console.error('Dashboard refresh failed:', err);
        }
    }

    /**
     * Map SVG paths color states based on congestion density
     */
    function updateStadiumMap(zones) {
        if (!Array.isArray(zones)) return;

        zones.forEach(zone => {
            const svgPath = document.getElementById(zone.id);
            if (svgPath) {
                // Clear old classes
                svgPath.classList.remove('zone-normal', 'zone-warning', 'zone-critical');
                
                // Add correct class
                if (zone.congestion_density >= 85) {
                    svgPath.classList.add('zone-critical');
                } else if (zone.congestion_density >= 70) {
                    svgPath.classList.add('zone-warning');
                } else {
                    svgPath.classList.add('zone-normal');
                }

                // Add tooltips
                svgPath.setAttribute('title', `${zone.name}: ${zone.congestion_density}% capacity`);
            }
        });
    }

    /**
     * Update numerical counters at the top of the command center
     */
    function updateGlobalStats(zones) {
        if (!Array.isArray(zones)) return;

        let criticalCount = 0;
        let totalDensity = 0;
        let concessionsCount = 0;
        let totalConcessionWait = 0;

        zones.forEach(zone => {
            if (zone.congestion_density >= 85) {
                criticalCount++;
            }
            totalDensity += zone.congestion_density;
        });

        statCritical.textContent = criticalCount;
    }

    /**
     * Populate the incident list
     */
    function updateIncidentList(incidents) {
        if (!Array.isArray(incidents)) return;

        // Update counts
        const openCount = incidents.filter(i => i.status === 'In Progress' || i.status === 'Open').length;
        lblIncCount.textContent = `${openCount} Open`;
        statIncidentsCount.textContent = openCount;

        if (incidents.length === 0) {
            incidentContainer.innerHTML = `
                <p style="text-align: center; color: var(--color-text-muted); font-size: var(--text-sm); padding: var(--space-5);">
                    Monitoring telemetry streams. No active incidents.
                </p>
            `;
            return;
        }

        let html = '';
        incidents.forEach(inc => {
            const severityClass = `severity-${inc.severity}`;
            const volunteerName = inc.volunteer_name || 'Unassigned';
            const actionPlan = inc.ai_analysis ? inc.ai_analysis.action_plan : '';
            const statusLabel = inc.status === 'Resolved' ? '✅ Resolved' : '⚠️ In Progress';
            
            // Format time
            const date = new Date(inc.created_at);
            const timeStr = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

            html += `
                <div class="glass-panel incident-card ${severityClass}" style="margin-bottom: var(--space-2); padding: var(--space-4);">
                    <div class="flex-between" style="margin-bottom: var(--space-2);">
                        <span class="badge ${inc.severity === 'Critical' || inc.severity === 'High' ? 'badge-critical' : 'badge-warning'}">
                            ${inc.severity} - ${inc.type}
                        </span>
                        <span style="font-size: var(--text-xs); color: var(--color-text-muted);">${timeStr} | ${statusLabel}</span>
                    </div>
                    <h4 style="font-size: var(--text-sm); font-weight: 600; color: var(--color-text-primary); margin-bottom: var(--space-1);">${inc.zone_name}</h4>
                    <p style="font-size: var(--text-xs); line-height: 1.4; color: var(--color-text-secondary); margin-bottom: var(--space-3);">
                        "${inc.description}"
                    </p>
                    
                    <div style="font-size: var(--text-xs); margin-bottom: var(--space-3);">
                        <span style="color: var(--color-gold); font-weight: 700;">Assigned Crew:</span> ${volunteerName}
                    </div>

                    ${actionPlan ? `
                        <div class="action-plan-box">
                            <div style="font-weight: 800; font-size: 10px; color: var(--color-gold); margin-bottom: var(--space-1); letter-spacing: 0.05em; text-transform: uppercase;">
                                Gemini 3.1 Flash-Lite Action SOP
                            </div>
                            <div style="line-height: 1.4; color: #e5e7eb;">
                                ${formatMarkdown(actionPlan)}
                            </div>
                        </div>
                    ` : ''}

                    ${inc.status !== 'Resolved' ? `
                        <div style="display: flex; justify-content: flex-end; margin-top: var(--space-3);">
                            <button class="btn btn-secondary btn-resolve" data-id="${inc.id}" style="padding: 0.35rem 0.75rem; font-size: 10px; border-radius: var(--radius-md);">
                                Mark Resolved
                            </button>
                        </div>
                    ` : ''}
                </div>
            `;
        });

        incidentContainer.innerHTML = html;

        // Attach event listeners to Resolve buttons
        document.querySelectorAll('.btn-resolve').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                const id = e.target.getAttribute('data-id');
                e.target.disabled = true;
                e.target.textContent = 'Resolving...';

                try {
                    const response = await fetch('../api/resolve.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id })
                    });
                    const res = await response.json();
                    if (res.status === 'success') {
                        refreshDashboard();
                    }
                } catch (err) {
                    console.error('Failed to resolve incident:', err);
                    e.target.disabled = false;
                    e.target.textContent = 'Mark Resolved';
                }
            });
        });
    }

    /**
     * Populate the broadcast list
     */
    function updateBroadcastList(broadcasts) {
        if (!Array.isArray(broadcasts)) return;

        if (broadcasts.length === 0) {
            broadcastContainer.innerHTML = `
                <p style="text-align: center; color: var(--color-text-muted); font-size: var(--text-sm); padding: var(--space-5);">
                    No warnings currently broadcasted in the venue.
                </p>
            `;
            return;
        }

        let html = '';
        // Show last 4 broadcasts
        broadcasts.slice(0, 4).forEach(bc => {
            html += `
                <div class="glass-panel" style="background: rgba(243, 198, 35, 0.03); border-color: rgba(243, 198, 35, 0.1); padding: var(--space-3); margin-bottom: var(--space-2);">
                    <div style="font-size: 8px; font-weight: 800; color: var(--color-gold); letter-spacing: 0.1em; margin-bottom: var(--space-1); text-transform: uppercase;">
                        Broadcast ID: ${bc.id}
                    </div>
                    <div style="font-size: var(--text-xs); line-height: 1.4; display: flex; flex-direction: column; gap: 4px;">
                        <div><strong style="color: var(--color-accent);">EN:</strong> <span style="color: var(--color-text-primary);">${bc.message_en}</span></div>
                        ${bc.message_es ? `<div><strong style="color: var(--color-gold);">ES:</strong> <span style="color: var(--color-text-secondary);">${bc.message_es}</span></div>` : ''}
                        ${bc.message_fr ? `<div><strong style="color: #a855f7;">FR:</strong> <span style="color: var(--color-text-secondary);">${bc.message_fr}</span></div>` : ''}
                    </div>
                </div>
            `;
        });

        broadcastContainer.innerHTML = html;
    }

    /**
     * Simple parser for markdown bullets in AI responses
     */
    function formatMarkdown(text) {
        if (!text) return '';
        // Convert simple markdown list indicators like 1. or - or *
        let formatted = text
            .replace(/\n/g, '<br>')
            .replace(/(\d+\.\s+)(.*?)(?=<br>|$)/g, '<li>$2</li>')
            .replace(/(-\s+)(.*?)(?=<br>|$)/g, '<li>$2</li>')
            .replace(/(\*\s+)(.*?)(?=<br>|$)/g, '<li>$2</li>');
        
        if (formatted.includes('<li>')) {
            formatted = '<ul style="margin-left: 15px; margin-top: 5px;">' + formatted + '</ul>';
        }
        return formatted;
    }

    /**
     * UI Toast popup helper
     */
    function showToast(message) {
        const toast = document.createElement('div');
        toast.className = 'glass-panel';
        toast.style.position = 'fixed';
        toast.style.bottom = '20px';
        toast.style.right = '20px';
        toast.style.zIndex = '9999';
        toast.style.borderColor = 'var(--color-gold)';
        toast.style.backgroundColor = 'rgba(24, 21, 60, 0.95)';
        toast.style.padding = '12px 20px';
        toast.style.borderRadius = '8px';
        toast.style.boxShadow = '0 10px 30px rgba(0,0,0,0.5)';
        toast.style.fontSize = '12px';
        toast.style.fontWeight = '700';
        toast.style.color = '#ffffff';
        toast.style.animation = 'slideUp 0.3s ease-out';
        toast.textContent = message;
        
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.5s ease';
            setTimeout(() => toast.remove(), 500);
        }, 4000);
    }
});
