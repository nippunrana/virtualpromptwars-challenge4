/**
 * ArenaNexus 2026 Fan Companion Controller
 */

document.addEventListener('DOMContentLoaded', () => {
    // Tab switching elements
    const tabNav = document.getElementById('tab-nav');
    const tabFood = document.getElementById('tab-food');
    const tabChat = document.getElementById('tab-chat');

    const sectionNav = document.getElementById('section-nav');
    const sectionFood = document.getElementById('section-food');
    const sectionChat = document.getElementById('section-chat');

    // Wayfinding elements
    const selectStart = document.getElementById('route-start');
    const selectEnd = document.getElementById('route-end');
    const checkStepFree = document.getElementById('route-step-free');
    const btnFindRoute = document.getElementById('btn-find-route');
    const routeResultContainer = document.getElementById('route-result-container');
    const routeExplanationText = document.getElementById('route-explanation-text');
    const routeStepsContainer = document.getElementById('route-steps-container');

    // Food finder elements
    const checkVegan = document.getElementById('diet-vegan');
    const checkVegetarian = document.getElementById('diet-vegetarian');
    const checkHalal = document.getElementById('diet-halal');
    const checkGf = document.getElementById('diet-gf');
    const btnSearchFood = document.getElementById('btn-search-food');
    const foodResultsContainer = document.getElementById('food-results-container');

    // Chatbot elements
    const chatInput = document.getElementById('chat-input');
    const btnSendChat = document.getElementById('btn-send-chat');
    const chatWindow = document.getElementById('chat-window');

    // Active tab state
    let activeTab = 'nav';

    // 1. Tab Event Listeners
    tabNav.addEventListener('click', () => switchTab('nav'));
    tabFood.addEventListener('click', () => switchTab('food'));
    tabChat.addEventListener('click', () => switchTab('chat'));

    function switchTab(tab) {
        tabNav.classList.remove('active');
        tabFood.classList.remove('active');
        tabChat.classList.remove('active');

        sectionNav.style.display = 'none';
        sectionFood.style.display = 'none';
        sectionChat.style.display = 'none';

        if (tab === 'nav') {
            tabNav.classList.add('active');
            sectionNav.style.display = 'block';
        } else if (tab === 'food') {
            tabFood.classList.add('active');
            sectionFood.style.display = 'block';
            // Auto search on tab open
            searchConcessions();
        } else if (tab === 'chat') {
            tabChat.classList.add('active');
            sectionChat.style.display = 'block';
        }
        activeTab = tab;
    }

    // 2. Wayfinding Route Calculator
    if (btnFindRoute) {
        btnFindRoute.addEventListener('click', async () => {
            const startVal = selectStart.value;
            const endVal = selectEnd.value;
            const stepFree = checkStepFree.checked;

            if (!startVal || !endVal) {
                alert('Please select both start and destination zones.');
                return;
            }

            btnFindRoute.disabled = true;
            btnFindRoute.textContent = '🗺️ Routing Path...';
            routeResultContainer.style.display = 'none';

            try {
                const response = await fetch(`../api/route.php?start=${startVal}&end=${endVal}&step_free=${stepFree}`);
                const data = await response.json();

                if (data.error) {
                    alert('Routing Error: ' . data.error);
                    return;
                }

                // Render explanation
                routeExplanationText.textContent = data.instructions || 'Route calculated.';

                // Render sequence paths
                let stepsHtml = '';
                data.path.forEach((step, idx) => {
                    const statusColor = getStatusColor(step.congestion);
                    const elevatorIcon = step.elevator ? '♿' : '🪜';
                    const arrowIcon = idx < data.path.length - 1 ? '<div style="text-align: center; font-size: 10px; color: var(--color-text-muted); margin: 2px 0;">↓</div>' : '';

                    stepsHtml += `
                        <div class="route-step-pill">
                            <span style="font-size: 14px;">${elevatorIcon}</span>
                            <div style="flex: 1;">
                                <div style="font-weight: 700; color: var(--color-text-primary);">${step.name}</div>
                                <div style="font-size: 9px; color: var(--color-text-muted);">
                                    Congestion: <strong style="color: ${statusColor};">${step.congestion}%</strong>
                                </div>
                            </div>
                        </div>
                        ${arrowIcon}
                    `;
                });

                routeStepsContainer.innerHTML = stepsHtml;
                routeResultContainer.style.display = 'block';

            } catch (err) {
                console.error('Wayfinding failed:', err);
            } finally {
                btnFindRoute.disabled = false;
                btnFindRoute.textContent = '🗺️ Find Optimal Route';
            }
        });
    }

    // 3. Concession Food Finder
    if (btnSearchFood) {
        btnSearchFood.addEventListener('click', searchConcessions);
    }

    async function searchConcessions() {
        btnSearchFood.disabled = true;
        btnSearchFood.textContent = '🔍 Querying...';

        const vegan = checkVegan.checked;
        const vegetarian = checkVegetarian.checked;
        const halal = checkHalal.checked;
        const gf = checkGf.checked;

        try {
            const response = await fetch(`../api/concessions.php?vegan=${vegan}&vegetarian=${vegetarian}&halal=${halal}&gluten_free=${gf}`);
            const concessions = await response.json();

            if (concessions.length === 0) {
                foodResultsContainer.innerHTML = `
                    <div class="glass-panel" style="text-align: center; color: var(--color-text-muted); font-size: var(--text-sm); padding: var(--space-5);">
                        No matching concession stands found.
                    </div>
                `;
                return;
            }

            let html = '';
            concessions.forEach(c => {
                const statusColor = getStatusColor(c.congestion_density);

                html += `
                    <div class="concession-item">
                        <div>
                            <h4 style="font-size: var(--text-sm); font-weight: 700; color: var(--color-text-primary);">${c.name}</h4>
                            <p style="font-size: var(--text-xs); color: var(--color-text-secondary); margin-bottom: 4px;">${c.cuisine}</p>
                            <div class="diet-tags">
                                ${c.is_vegan ? '<span class="diet-tag vegan">Vegan</span>' : ''}
                                ${c.is_vegetarian ? '<span class="diet-tag vegetarian">Veggie</span>' : ''}
                                ${c.is_halal ? '<span class="diet-tag halal">Halal</span>' : ''}
                                ${c.is_gluten_free ? '<span class="diet-tag gf">GF</span>' : ''}
                            </div>
                        </div>
                        <div style="text-align: right; display: flex; flex-direction: column; gap: 6px;">
                            <div style="font-size: var(--text-xs);">
                                Wait: <strong style="color: var(--color-gold);">${c.avg_wait_time} min</strong>
                            </div>
                            <button class="btn btn-secondary btn-route-stall" data-id="${c.id}" style="padding: 0.35rem 0.6rem; font-size: 10px; border-radius: var(--radius-md);">
                                Route Me Here
                            </button>
                        </div>
                    </div>
                `;
            });

            foodResultsContainer.innerHTML = html;

            // Route Me Here click bindings
            document.querySelectorAll('.btn-route-stall').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const id = e.target.getAttribute('data-id');
                    
                    // Set Wayfinder destination
                    selectEnd.value = id;
                    
                    // Automatically switch to navigation tab
                    switchTab('nav');
                    
                    // Scroll to top of select box
                    selectEnd.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    
                    // Optionally alert or flash select box
                    selectEnd.style.borderColor = 'var(--color-gold)';
                    setTimeout(() => { selectEnd.style.borderColor = ''; }, 2000);
                });
            });

        } catch (err) {
            console.error('Concession search failed:', err);
        } finally {
            btnSearchFood.disabled = false;
            btnSearchFood.textContent = '🔍 Search Concessions';
        }
    }

    // 4. grounded Chatbot Companion
    if (btnSendChat) {
        btnSendChat.addEventListener('click', sendChatMessage);
        chatInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                sendChatMessage();
            }
        });
    }

    async function sendChatMessage() {
        const text = chatInput.value.trim();
        if (!text) return;

        // Clear input
        chatInput.value = '';

        // Add user bubble
        appendBubble('user', text);
        
        // Disable controls
        chatInput.disabled = true;
        btnSendChat.disabled = true;

        // Add dummy bot typing bubble
        const typingBubble = appendBubble('bot', 'AI is thinking...');

        try {
            const response = await fetch('../api/chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: text })
            });
            const data = await response.json();

            // Replace typing bubble
            typingBubble.textContent = data.response || 'Sorry, I encountered an error processing that message.';
            
        } catch (err) {
            console.error('Chat failed:', err);
            typingBubble.textContent = 'Unable to connect to assistant. Running in offline mode.';
        } finally {
            chatInput.disabled = false;
            btnSendChat.disabled = false;
            chatInput.focus();
            chatWindow.scrollTop = chatWindow.scrollHeight;
        }
    }

    function appendBubble(role, content) {
        const bubble = document.createElement('div');
        bubble.className = `chat-bubble ${role}`;
        bubble.textContent = content;
        chatWindow.appendChild(bubble);
        chatWindow.scrollTop = chatWindow.scrollHeight;
        return bubble;
    }

    // Utilities
    function getStatusColor(congestion) {
        if (congestion >= 85) return 'var(--color-critical)';
        if (congestion >= 70) return 'var(--color-warning)';
        return 'var(--color-normal)';
    }
});
