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

    // Keyboard support for interactive SVG map paths
    document.querySelectorAll('.stadium-zone-path').forEach(path => {
        path.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                path.click();
            }
        });
    });

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
     * Fetch all status updates from the consolidated endpoint and redraw the dashboard.
     */
    async function refreshDashboard() {
        try {
            // Single consolidated request replaces 3 separate polling calls
            const response = await fetch('../api/status.php');
            const data = await response.json();

            updateStadiumMap(data.zones ?? []);
            updateGlobalStats(data.zones ?? []);
            updateIncidentList(data.incidents ?? []);
            updateBroadcastList(data.broadcasts ?? []);

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

                // Add tooltips and accessibility labels
                svgPath.setAttribute('title', `${zone.name}: ${zone.congestion_density}% capacity`);
                svgPath.setAttribute('aria-label', `${zone.name}: ${zone.congestion_density}% congestion capacity, status: ${zone.status}`);
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
                    <h4 style="font-size: var(--text-sm); font-weight: 600; color: var(--color-text-primary); margin-bottom: var(--space-1);">${escapeHTML(inc.zone_name)}</h4>
                    <p style="font-size: var(--text-xs); line-height: 1.4; color: var(--color-text-secondary); margin-bottom: var(--space-3);">
                        "${escapeHTML(inc.description)}"
                    </p>
                    
                    <div style="font-size: var(--text-xs); margin-bottom: var(--space-3);">
                        <span style="color: var(--color-gold); font-weight: 700;">Assigned Crew:</span> ${escapeHTML(volunteerName)}
                    </div>

                    ${actionPlan ? `
                        <div class="action-plan-box">
                            <div style="font-weight: 800; font-size: 10px; color: var(--color-gold); margin-bottom: var(--space-1); letter-spacing: 0.05em; text-transform: uppercase;">
                                Gemini 3.1 Flash-Lite Action SOP
                            </div>
                            <div style="line-height: 1.4; color: var(--color-text-primary);">
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
                        <div><strong style="color: var(--color-accent);">EN:</strong> <span style="color: var(--color-text-primary);">${escapeHTML(bc.message_en)}</span></div>
                        ${bc.message_es ? `<div><strong style="color: var(--color-gold);">ES:</strong> <span style="color: var(--color-text-secondary);">${escapeHTML(bc.message_es)}</span></div>` : ''}
                        ${bc.message_fr ? `<div><strong style="color: #a855f7;">FR:</strong> <span style="color: var(--color-text-secondary);">${escapeHTML(bc.message_fr)}</span></div>` : ''}
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
        // Standardize newlines first
        let normalized = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        
        // If there are no newlines but there are inline markdown list items (e.g. "* ... * 2. ..."),
        // let's add newlines before the asterisks or numbers to split them.
        if (!normalized.includes('\n')) {
            normalized = normalized.replace(/\s+\*\s+(\d+\.)?/g, '\n* ');
        }

        const lines = normalized.split('\n');
        let html = '';
        let inList = false;
        
        lines.forEach(line => {
            let cleanLine = line.trim();
            if (!cleanLine) return;
            
            // Match leading bullet or number: * or - or 1. or * 1.
            const match = cleanLine.match(/^([*\-]\s*|\d+\.\s*)(.*)/);
            if (match) {
                if (!inList) {
                    html += '<ul style="margin-left: 15px; margin-top: 5px; margin-bottom: 5px; padding-left: 0;">';
                    inList = true;
                }
                let itemText = match[2].trim();
                // Strip nested or leftover bullet indicators
                itemText = itemText.replace(/^([*\-]\s*|\d+\.\s*)/, '');
                html += `<li>${escapeHTML(itemText)}</li>`;
            } else {
                if (inList) {
                    html += '</ul>';
                    inList = false;
                }
                html += `<p style="margin-bottom: 8px;">${escapeHTML(cleanLine)}</p>`;
            }
        });
        
        if (inList) {
            html += '</ul>';
        }
        return html;
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

    function escapeHTML(str) {
        if (!str) return '';
        return String(str).replace(/[&<>'"]/g, 
            tag => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#39;',
                '"': '&quot;'
            }[tag] || tag)
        );
    }
});
