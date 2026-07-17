/**
 * ArenaNexus 2026 Volunteer App Controller
 */

document.addEventListener('DOMContentLoaded', () => {
    const selector = document.getElementById('volunteer-selector');
    const panelProfile = document.getElementById('panel-profile');
    const lblProfileZone = document.getElementById('lbl-profile-zone');
    const lblProfileLang = document.getElementById('lbl-profile-lang');
    
    const taskPanel = document.getElementById('task-panel');
    const taskDetailsContainer = document.getElementById('task-details-container');
    const intercomPanel = document.getElementById('intercom-panel');
    const footerStatus = document.getElementById('footer-status');

    // Intercom elements
    const selectLang = document.getElementById('translation-lang');
    const inputInquiry = document.getElementById('inquiry-input');
    const btnTranslate = document.getElementById('btn-translate');
    const intercomResultContainer = document.getElementById('intercom-result-container');
    const intercomResultText = document.getElementById('intercom-result-text');

    let volunteerId = null;
    let volunteerLang = '';
    let volunteerZone = '';
    let taskPollInterval = null;

    // Selector Change Event
    if (selector) {
        selector.addEventListener('change', (e) => {
            const val = e.target.value;
            if (!val) {
                // Clear state
                volunteerId = null;
                panelProfile.style.display = 'none';
                intercomPanel.style.display = 'none';
                taskDetailsContainer.innerHTML = `
                    <p style="text-align: center; color: var(--color-text-muted); font-size: var(--text-xs); padding: var(--space-4);">
                        Please select a profile to synchronize task dispatching.
                    </p>
                `;
                footerStatus.innerHTML = '🟢 Standing By • GPS Synchronized';
                clearInterval(taskPollInterval);
                return;
            }

            const option = selector.options[selector.selectedIndex];
            volunteerId = parseInt(val);
            volunteerLang = option.getAttribute('data-lang');
            volunteerZone = option.getAttribute('data-zone');

            // Update UI panels
            lblProfileZone.textContent = formatZoneName(volunteerZone);
            lblProfileLang.textContent = volunteerLang;
            panelProfile.style.display = 'block';
            intercomPanel.style.display = 'block';

            // Load and poll tasks
            loadActiveTasks();
            clearInterval(taskPollInterval);
            taskPollInterval = setInterval(loadActiveTasks, 4000);
        });
    }

    /**
     * Poll active incidents assigned to this volunteer
     */
    async function loadActiveTasks() {
        if (!volunteerId) return;

        try {
            const response = await fetch('../api/triage.php');
            const incidents = await response.json();

            // Find current active task assigned to this volunteer
            const activeTask = incidents.find(inc => 
                parseInt(inc.assigned_volunteer_id) === volunteerId && 
                inc.status === 'In Progress'
            );

            if (!activeTask) {
                taskDetailsContainer.innerHTML = `
                    <div style="text-align: center; padding: var(--space-4);">
                        <span style="font-size: 24px; display: block; margin-bottom: 4px;">✅</span>
                        <span style="font-size: var(--text-xs); color: var(--color-normal); font-weight: 700;">ALL CLEAR</span>
                        <p style="font-size: 10px; color: var(--color-text-muted); margin-top: 4px;">No active dispatch. Stand by at your station.</p>
                    </div>
                `;
                footerStatus.innerHTML = `🟢 Duty: ${formatZoneName(volunteerZone)} • Standing By`;
                return;
            }

            // Build task UI with SOP steps converted to interactive checkboxes
            const sopText = activeTask.ai_analysis ? activeTask.ai_analysis.action_plan : '';
            const sopSteps = parseSopToSteps(sopText);
            const severityClass = `severity-${activeTask.severity}`;

            let html = `
                <div class="glass-panel" style="background: rgba(0,0,0,0.02); border: 1px solid var(--border-glass); padding: var(--space-3); border-left: 4px solid ${getSeverityColor(activeTask.severity)};">
                    <div class="flex-between" style="margin-bottom: var(--space-2);">
                        <span class="badge ${activeTask.severity === 'Critical' || activeTask.severity === 'High' ? 'badge-critical' : 'badge-warning'}" style="font-size: 9px; padding: 2px 6px;">
                            DISPATCH: ${activeTask.type.toUpperCase()}
                        </span>
                        <span style="font-size: 9px; color: var(--color-text-muted);">ACTIVE TASK</span>
                    </div>
                    <h4 style="font-size: var(--text-sm); font-weight: 700; margin-bottom: 4px;">Location: ${activeTask.zone_name}</h4>
                    <p style="font-size: var(--text-xs); color: var(--color-text-primary); line-height: 1.4; margin-bottom: var(--space-3);">
                        "${activeTask.description}"
                    </p>

                    ${sopSteps.length > 0 ? `
                        <div style="margin-bottom: var(--space-3);">
                            <div style="font-weight: 800; font-size: 9px; color: var(--color-gold); text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.05em;">
                                Live SOP Checklist
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 6px;">
                                ${sopSteps.map((step, idx) => `
                                    <label style="display: flex; align-items: flex-start; gap: 8px; font-size: 11px; color: var(--color-text-secondary); cursor: pointer;">
                                        <input type="checkbox" style="margin-top: 2px;">
                                        <span>${step}</span>
                                    </label>
                                `).join('')}
                            </div>
                        </div>
                    ` : ''}

                    <div style="display: flex; justify-content: flex-end; gap: var(--space-2); margin-top: var(--space-3);">
                        <button class="btn btn-primary btn-complete-task" data-id="${activeTask.id}" style="width: 100%; justify-content: center; font-size: var(--text-xs); padding: 0.5rem 1rem;">
                            Resolve & Clear Task
                        </button>
                    </div>
                </div>
            `;

            taskDetailsContainer.innerHTML = html;
            footerStatus.innerHTML = `🚨 ACTIVE INCIDENT DISPATCHED AT ${activeTask.zone_name.toUpperCase()}`;

            // Attach click handler to resolve task
            const btnComplete = taskDetailsContainer.querySelector('.btn-complete-task');
            if (btnComplete) {
                btnComplete.addEventListener('click', async () => {
                    btnComplete.disabled = true;
                    btnComplete.textContent = 'Clearing...';

                    try {
                        const response = await fetch('../api/resolve.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: activeTask.id })
                        });
                        const res = await response.json();
                        if (res.status === 'success') {
                            loadActiveTasks();
                        }
                    } catch (err) {
                        console.error('Failed to clear task:', err);
                        btnComplete.disabled = false;
                        btnComplete.textContent = 'Resolve & Clear Task';
                    }
                });
            }

        } catch (err) {
            console.error('Failed to check tasks:', err);
        }
    }

    // Intercom Translate Click handler
    if (btnTranslate) {
        btnTranslate.addEventListener('click', async () => {
            const queryText = inputInquiry.value.trim();
            const targetLang = selectLang.value;

            if (empty(queryText)) return;

            btnTranslate.disabled = true;
            btnTranslate.textContent = 'Translating...';

            try {
                const response = await fetch('../api/translate.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        language: targetLang,
                        text: queryText
                    })
                });
                
                const data = await response.json();

                if (data.translation) {
                    intercomResultText.innerHTML = `
                        <div style="margin-bottom: var(--space-2);">
                            <strong style="color: var(--color-text-secondary); font-size: 10px;">ENGLISH TRANSLATION:</strong>
                             <p style="font-size: var(--text-xs); color: var(--color-text-primary); line-height: 1.4; font-style: italic;">"${data.translation}"</p>
                        </div>
                        <div style="margin-bottom: var(--space-2);">
                            <strong style="color: var(--color-text-secondary); font-size: 10px;">STEWARD RESPONSE:</strong>
                            <p style="font-size: var(--text-xs); color: var(--color-accent); line-height: 1.4;">${data.response_en}</p>
                        </div>
                        <div>
                            <strong style="color: var(--color-text-secondary); font-size: 10px;">FAN TRANSLATION (${targetLang.toUpperCase()}):</strong>
                            <p style="font-size: var(--text-xs); color: var(--color-gold); line-height: 1.4;">${data.response_lang}</p>
                        </div>
                    `;
                    intercomResultContainer.style.display = 'block';
                }
            } catch (err) {
                console.error('Translation call failed:', err);
            } finally {
                btnTranslate.disabled = false;
                btnTranslate.textContent = 'Translate';
            }
        });
    }

    // Helper functions
    function formatZoneName(id) {
        if (!id) return '';
        return id.replace(/_/g, ' ').toUpperCase();
    }

    function getSeverityColor(severity) {
        if (severity === 'Critical') return 'var(--color-critical)';
        if (severity === 'High') return 'var(--color-warning)';
        if (severity === 'Medium') return 'var(--color-accent)';
        return 'var(--color-text-muted)';
    }

    function parseSopToSteps(text) {
        if (!text) return [];
        // Standardize newlines first
        let normalized = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        
        // If there are no newlines but there are inline markdown list items,
        // let's add newlines before the asterisks or numbers to split them.
        if (!normalized.includes('\n')) {
            normalized = normalized.replace(/\s+\*\s+(\d+\.)?/g, '\n* ');
        }
        
        const lines = normalized.split('\n');
        const steps = [];
        lines.forEach(line => {
            const clean = line.replace(/^\s*([*\-]\s*|\d+\.\s*)/, '').trim();
            if (clean.length > 3) {
                steps.push(clean);
            }
        });
        return steps;
    }

    function empty(str) {
        return (!str || str.length === 0);
    }
});
