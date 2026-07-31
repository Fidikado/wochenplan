// Theme früh anwenden (Fallback, falls inline-Script fehlt)
(function () {
    const t = localStorage.getItem('theme');
    if (t === 'dark') document.documentElement.classList.add('dark');
    else if (t === 'light') document.documentElement.classList.remove('dark');
})();

document.addEventListener('DOMContentLoaded', () => {
    const btnTheme = document.getElementById('btn-theme-toggle');
    if (btnTheme) {
        btnTheme.addEventListener('click', () => {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
        });
    }
    let statusHideTimer = null;
    let categoryMap = {
        hauptspeise: 'Hauptspeise',
        vorspeise: 'Vorspeise',
        suppe: 'Suppe',
        salat: 'Salat',
        beilage: 'Beilage',
        fruehstueck: 'Frühstück',
        brot: 'Brot',
        kuchen: 'Kuchen',
        dessert: 'Dessert',
        getraenk: 'Getränk',
        snack: 'Snack'
    };
    let categories = Object.entries(categoryMap).map(([id, label]) => ({ id, label }));

    // === Tab-Navigation ===
    const tabs = document.querySelectorAll('.tab');
    const tabContents = document.querySelectorAll('.tab-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tabContents.forEach(tc => tc.classList.remove('active'));
            tab.classList.add('active');
            document.getElementById('tab-' + tab.dataset.tab)?.classList.add('active');
            hidePreview();
            hideStatus();
        });
    });

    // === Manuelles Formular ===
    const formManuell = document.getElementById('form-manuell');
    if (formManuell) {
        formManuell.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(formManuell);
            await saveRecipeForm(fd, 'api/save-recipe.php');
            formManuell.reset();
        });
    }

    // === Textblock parsen ===
    const btnParseText = document.getElementById('btn-parse-text');
    if (btnParseText) {
        btnParseText.addEventListener('click', async () => {
            const text = document.getElementById('textblock-input').value.trim();
            if (!text) return showStatus('Bitte Text eingeben', 'error');

            showLoading(true);
            try {
                const res = await fetch('api/parse-text.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ text })
                });
                const data = await res.json();
                if (data.error) throw new Error(data.error);
                showPreview(data);
            } catch (err) {
                showStatus('Fehler: ' + err.message, 'error');
            } finally {
                showLoading(false);
            }
        });
    }

    // === Bild parsen ===
    const bildInput = document.getElementById('bild-input');
    const btnParseImage = document.getElementById('btn-parse-image');
    if (bildInput) {
        bildInput.addEventListener('change', () => {
            const files = Array.from(bildInput.files || []).slice(0, 3);
            const preview = document.getElementById('bild-preview');
            if (preview) preview.innerHTML = '';
            if (files.length > 0) {
                btnParseImage.disabled = false;
                files.forEach((f) => {
                    const img = document.createElement('img');
                    img.src = URL.createObjectURL(f);
                    img.alt = 'Vorschau';
                    preview?.appendChild(img);
                });
                if ((bildInput.files?.length || 0) > 3) {
                    showStatus('Es werden nur die ersten 3 Bilder verwendet.', 'error');
                }
            } else {
                btnParseImage.disabled = true;
                if (preview) preview.innerHTML = '';
            }
        });
    }
    if (btnParseImage) {
        btnParseImage.addEventListener('click', async () => {
            const files = Array.from(bildInput.files || []).slice(0, 3);
            if (files.length === 0) return;

            showLoading(true);
            try {
                const fd = new FormData();
                files.forEach((f) => fd.append('images[]', f));
                const res = await fetch('api/parse-image.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();
                if (data.error) throw new Error(data.error);
                showPreview(data);
            } catch (err) {
                showStatus('Fehler: ' + err.message, 'error');
            } finally {
                showLoading(false);
            }
        });
    }

    // === URL parsen ===
    const btnParseUrl = document.getElementById('btn-parse-url');
    if (btnParseUrl) {
        btnParseUrl.addEventListener('click', async () => {
            const url = document.getElementById('link-input').value.trim();
            if (!url) return showStatus('Bitte URL eingeben', 'error');

            showLoading(true);
            try {
                const res = await fetch('api/parse-url.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ url })
                });
                const data = await res.json();
                if (data.error) throw new Error(data.error);
                showPreview(data);
            } catch (err) {
                showStatus('Fehler: ' + err.message, 'error');
            } finally {
                showLoading(false);
            }
        });
    }

    // === Vorschau-Formular ===
    const formPreview = document.getElementById('form-preview');
    if (formPreview) {
        formPreview.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(formPreview);
            await saveRecipeForm(fd, 'api/save-recipe.php');
            hidePreview();
        });
    }

    const categorySelects = document.querySelectorAll('select[data-category-select]');
    const formCategoryAdd = document.getElementById('form-category-add');
    const categoryList = document.getElementById('category-list');
    const categorySummary = document.getElementById('category-summary');
    if (categorySelects.length > 0 || formCategoryAdd || categoryList) {
        loadCategories();
    }

    // === Wochenplan ===
    const weekGrid = document.getElementById('week-grid');
    const btnGenerate = document.getElementById('btn-generate');
    const btnPrintPlan = document.getElementById('btn-print-plan');
    const btnConfirmPlan = document.getElementById('btn-confirm-plan');
    const btnShowHistory = document.getElementById('btn-show-history');
    let currentPlanData = null;
    let dragFromIndex = null;
    let swapDayIndex = null;

    if (weekGrid) {
        loadWeekPlan();
    }

    // === Tausch-Modal ===
    const swapModal = document.getElementById('swap-modal');
    const swapSearch = document.getElementById('swap-search');
    const swapList = document.getElementById('swap-list');
    let allRecipesForSwap = [];

    if (swapModal) {
        swapModal.querySelector('.modal-close')?.addEventListener('click', () => {
            swapModal.classList.add('hidden');
        });
        swapModal.addEventListener('click', (e) => {
            if (e.target === swapModal) swapModal.classList.add('hidden');
        });
    }

    if (swapSearch) {
        swapSearch.addEventListener('input', () => {
            renderSwapList(swapSearch.value.trim().toLowerCase());
        });
    }

    // === Einkaufsliste ===
    const shoppingList = document.getElementById('shopping-list');
    const btnGenerateList = document.getElementById('btn-generate-list');
    const btnPrintList = document.getElementById('btn-print-list');
    const loadingShopping = document.getElementById('loading-shopping');

    if (btnPrintList) {
        btnPrintList.addEventListener('click', () => window.print());
    }

    if (shoppingList) {
        loadShoppingList();
    }

    if (btnGenerateList) {
        btnGenerateList.addEventListener('click', async () => {
            btnGenerateList.disabled = true;
            btnGenerateList.textContent = 'Generiere...';
            if (loadingShopping) loadingShopping.classList.remove('hidden');
            try {
                const res = await fetch('api/generate-shopping-list.php', { method: 'POST' });
                const data = await res.json();
                if (data.error) throw new Error(data.error);
                renderShoppingList(data);
            } catch (err) {
                showStatus('Fehler: ' + err.message, 'error');
            } finally {
                btnGenerateList.disabled = false;
                btnGenerateList.textContent = 'Einkaufsliste generieren';
                if (loadingShopping) loadingShopping.classList.add('hidden');
            }
        });
    }

    if (btnGenerate) {
        btnGenerate.addEventListener('click', async () => {
            btnGenerate.disabled = true;
            btnGenerate.textContent = 'Generiere...';
            try {
                const res = await fetch('api/generate-plan.php', { method: 'POST' });
                const data = await res.json();
                if (data.error) throw new Error(data.error);
                renderWeekPlan(data);
            } catch (err) {
                showStatus('Fehler: ' + err.message, 'error');
            } finally {
                btnGenerate.disabled = false;
                btnGenerate.textContent = 'Neuen Wochenplan generieren';
            }
        });
    }

    // === Wochenplan bestätigen ===
    const confirmModal = document.getElementById('confirm-modal');
    const btnDoConfirm = document.getElementById('btn-do-confirm');
    const btnCancelConfirm = document.getElementById('btn-cancel-confirm');

    if (confirmModal) {
        confirmModal.querySelector('.modal-close')?.addEventListener('click', () => {
            confirmModal.classList.add('hidden');
        });
        confirmModal.addEventListener('click', (e) => {
            if (e.target === confirmModal) confirmModal.classList.add('hidden');
        });
    }
    if (btnCancelConfirm) {
        btnCancelConfirm.addEventListener('click', () => confirmModal.classList.add('hidden'));
    }

    if (btnConfirmPlan) {
        btnConfirmPlan.addEventListener('click', () => {
            if (!currentPlanData || !currentPlanData.plan) return;
            renderConfirmPreview(currentPlanData.plan);
            confirmModal.classList.remove('hidden');
        });
    }

    if (btnDoConfirm) {
        btnDoConfirm.addEventListener('click', async () => {
            btnDoConfirm.disabled = true;
            btnDoConfirm.textContent = 'Bestätige...';
            try {
                const data = await fetchJsonOrThrow('api/confirm-plan.php', { method: 'POST' });
                confirmModal.classList.add('hidden');
                showStatus(`Wochenplan "${data.confirmed.week_label}" bestätigt – ${data.confirmed.recipe_count} Rezeptzähler erhöht`, 'success');
            } catch (err) {
                showStatus('Fehler: ' + err.message, 'error');
            } finally {
                btnDoConfirm.disabled = false;
                btnDoConfirm.textContent = 'Jetzt bestätigen';
            }
        });
    }

    function renderConfirmPreview(plan) {
        const el = document.getElementById('confirm-plan-preview');
        if (!el) return;
        const activeDays = plan.filter(d => !d.disabled && d.recipe && d.recipe.title);
        el.innerHTML = activeDays.map(d => `
            <div class="confirm-day-row">
                <span class="confirm-day-name">${d.tag}</span>
                <span class="confirm-day-recipe">${d.recipe.title}</span>
            </div>
        `).join('') || '<p class="empty-msg">Keine aktiven Tage.</p>';
    }

    // === Verlauf ===
    const historyModal = document.getElementById('history-modal');
    const historyPlanModal = document.getElementById('history-plan-modal');

    if (historyModal) {
        historyModal.querySelector('.modal-close')?.addEventListener('click', () => {
            historyModal.classList.add('hidden');
        });
        historyModal.addEventListener('click', (e) => {
            if (e.target === historyModal) historyModal.classList.add('hidden');
        });
    }

    if (historyPlanModal) {
        historyPlanModal.querySelector('.modal-close')?.addEventListener('click', () => {
            historyPlanModal.classList.add('hidden');
        });
        historyPlanModal.addEventListener('click', (e) => {
            if (e.target === historyPlanModal) historyPlanModal.classList.add('hidden');
        });
    }

    if (btnShowHistory) {
        btnShowHistory.addEventListener('click', async () => {
            try {
                const list = await fetchJsonOrThrow('api/get-confirmed-plans.php');
                renderHistoryList(list);
                historyModal.classList.remove('hidden');
            } catch (err) {
                showStatus('Fehler: ' + err.message, 'error');
            }
        });
    }

    function renderHistoryList(list) {
        const el = document.getElementById('history-list');
        if (!el) return;
        if (!list || list.length === 0) {
            el.innerHTML = '<p class="empty-msg">Noch keine bestätigten Pläne vorhanden.</p>';
            return;
        }
        el.innerHTML = list.map(item => `
            <div class="history-item" data-id="${item.id}">
                <div class="history-item-label">${item.week_label}</div>
                <div class="history-item-date">${item.confirmed_at.slice(0, 10)}</div>
                <button class="btn btn-ghost history-item-btn" data-id="${item.id}">Ansehen</button>
            </div>
        `).join('');
        el.querySelectorAll('[data-id]').forEach(btn => {
            if (btn.tagName !== 'BUTTON') return;
            btn.addEventListener('click', async () => {
                const id = btn.dataset.id;
                try {
                    const plan = await fetchJsonOrThrow('api/get-confirmed-plans.php?id=' + encodeURIComponent(id));
                    historyModal.classList.add('hidden');
                    renderHistoryPlan(plan);
                    historyPlanModal.classList.remove('hidden');
                } catch (err) {
                    showStatus('Fehler: ' + err.message, 'error');
                }
            });
        });
    }

    function renderHistoryPlan(data) {
        const header = document.getElementById('history-plan-header');
        const grid = document.getElementById('history-plan-grid');
        if (!header || !grid) return;

        header.innerHTML = `
            <h3>${data.week_label}</h3>
            <p class="history-plan-date">Bestätigt am ${data.confirmed_at.slice(0, 10)}</p>
        `;

        const plan = Array.isArray(data.plan?.plan) ? data.plan.plan : [];
        if (plan.length === 0) {
            grid.innerHTML = '<p class="empty-msg">Keine Einträge vorhanden.</p>';
            return;
        }

        const dayOrder = new Map([
            ['Samstag', 0], ['Sonntag', 1], ['Montag', 2], ['Dienstag', 3],
            ['Mittwoch', 4], ['Donnerstag', 5], ['Freitag', 6]
        ]);
        const sorted = [...plan].sort((a, b) => {
            const oa = dayOrder.get(a.tag) ?? 99;
            const ob = dayOrder.get(b.tag) ?? 99;
            return oa - ob;
        });

        grid.innerHTML = sorted.map(day => {
            if (day.disabled) {
                return `<div class="history-day-row history-day-off">
                    <span class="history-day-name">${day.tag}</span>
                    <span class="history-day-recipe history-day-off-text">Ausgeschaltet</span>
                </div>`;
            }
            const typ = day.recipe?.typ || 'normal';
            const badge = typ === 'thermomix'
                ? '<span class="badge badge-tm">TM</span>'
                : typ === 'airfryer'
                    ? '<span class="badge badge-af">AF</span>'
                    : '';
            return `<div class="history-day-row">
                <span class="history-day-name">${day.tag}</span>
                <span class="history-day-recipe">${badge}${day.recipe?.title || '–'}</span>
                <span class="history-day-meta">${day.recipe?.kochzeit || '?'} Min.</span>
            </div>`;
        }).join('');
    }

    if (btnPrintPlan) {
        btnPrintPlan.addEventListener('click', async () => {
            btnPrintPlan.disabled = true;
            btnPrintPlan.textContent = 'Starte Gemini...';
            try {
                const start = await fetchJsonOrThrow('api/generate-plan-print.php', { method: 'POST' });
                if (!start.job_id) throw new Error('Kein Print-Job erhalten');

                btnPrintPlan.textContent = 'Gemini rendert...';
                const data = await waitForPlanPrintJob(start.job_id, (status) => {
                    btnPrintPlan.textContent = status === 'running' ? 'Gemini rendert...' : 'Warte auf Job...';
                });
                if (!data.filename) throw new Error('Keine Ausgabedatei erhalten');

                const downloadUrl = 'api/download-print.php?file=' + encodeURIComponent(data.filename);
                const a = document.createElement('a');
                a.href = downloadUrl;
                a.download = data.filename;
                document.body.appendChild(a);
                a.click();
                a.remove();
                showStatus('Datei wird heruntergeladen', 'success');
            } catch (err) {
                showStatus('Fehler: ' + err.message, 'error');
            } finally {
                btnPrintPlan.disabled = false;
                btnPrintPlan.textContent = 'Datei herunterladen (via Gemini)';
            }
        });
    }

    // === Admin: Wochenplan-Vorlage ===
    const formTemplateUpload = document.getElementById('form-template-upload');
    const templatePreview = document.getElementById('template-preview');
    const formPrintModel = document.getElementById('form-print-model');
    const printModelSelect = document.getElementById('print-model-select');
    const printModelHelp = document.getElementById('print-model-help');

    if (formTemplateUpload) {
        loadPlanTemplate();

        formTemplateUpload.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(formTemplateUpload);
            showLoading(true);
            try {
                const res = await fetch('api/upload-plan-template.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();
                if (data.error) throw new Error(data.error);
                showStatus(data.message || 'Vorlage gespeichert', 'success');
                if (data.template) renderTemplatePreview(data.template);
                formTemplateUpload.reset();
            } catch (err) {
                showStatus('Fehler: ' + err.message, 'error');
            } finally {
                showLoading(false);
            }
        });
    }

    if (formPrintModel) {
        formPrintModel.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(formPrintModel);
            showLoading(true);
            try {
                const res = await fetch('api/update-plan-print-model.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();
                if (data.error) throw new Error(data.error);
                showStatus(data.message || 'Bildmodell gespeichert', 'success');
                await loadPlanTemplate();
            } catch (err) {
                showStatus('Fehler: ' + err.message, 'error');
            } finally {
                showLoading(false);
            }
        });
    }

    if (formCategoryAdd) {
        formCategoryAdd.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(formCategoryAdd);
            showLoading(true);
            try {
                const res = await fetch('api/add-category.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();
                if (data.error) throw new Error(data.error);
                if (Array.isArray(data.categories)) {
                    setCategories(data.categories);
                } else {
                    await loadCategories();
                }
                formCategoryAdd.reset();
                showStatus(data.message || 'Kategorie gespeichert', 'success');
            } catch (err) {
                showStatus('Fehler: ' + err.message, 'error');
            } finally {
                showLoading(false);
            }
        });
    }

    // === Modal ===
    const modal = document.getElementById('recipe-modal');
    if (modal) {
        modal.querySelector('.modal-close')?.addEventListener('click', () => {
            modal.classList.add('hidden');
        });
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.classList.add('hidden');
        });
    }

    // === Hilfsfunktionen ===

    async function saveRecipeForm(formData, url) {
        showLoading(true);
        try {
            const res = await fetch(url, {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.error) throw new Error(data.error);
            showStatus(data.message, 'success');
        } catch (err) {
            showStatus('Fehler: ' + err.message, 'error');
        } finally {
            showLoading(false);
        }
    }

    async function fetchJsonOrThrow(url, options) {
        const res = await fetch(url, options);
        const text = await res.text();

        let data = null;
        if (text !== '') {
            try {
                data = JSON.parse(text);
            } catch {
                const snippet = text.slice(0, 300).trim();
                throw new Error(`HTTP ${res.status}: Ungültige JSON-Antwort${snippet ? ' - ' + snippet : ''}`);
            }
        }

        if (!res.ok) {
            const message = data && typeof data.error === 'string' && data.error !== ''
                ? data.error
                : `HTTP ${res.status}`;
            throw new Error(message);
        }

        if (data && typeof data === 'object' && typeof data.error === 'string' && data.error !== '') {
            throw new Error(data.error);
        }

        return data;
    }

    function delay(ms) {
        return new Promise(resolve => window.setTimeout(resolve, ms));
    }

    async function waitForPlanPrintJob(jobId, onStatus) {
        const startedAt = Date.now();
        const timeoutMs = 5 * 60 * 1000;

        while ((Date.now() - startedAt) < timeoutMs) {
            const data = await fetchJsonOrThrow('api/get-plan-print-status.php?job_id=' + encodeURIComponent(jobId));
            onStatus?.(data.status || 'queued');

            if (data.status === 'completed') {
                return data;
            }
            if (data.status === 'failed') {
                throw new Error(data.error || 'Gemini-Export fehlgeschlagen');
            }

            await delay(2000);
        }

        throw new Error('Gemini-Export hat zu lange gedauert');
    }

    function showPreview(data) {
        const preview = document.getElementById('preview');
        if (!preview) return;

        preview.classList.remove('hidden');
        preview.querySelector('[name="title"]').value = data.title || '';
        ensureCategoryOption(preview.querySelector('[name="kategorie"]'), data.kategorie);
        preview.querySelector('[name="kategorie"]').value = normalizeCategory(data.kategorie);
        preview.querySelector('[name="typ"]').value = data.typ || 'normal';
        preview.querySelector('[name="kochzeit"]').value = data.kochzeit || 30;
        preview.querySelector('[name="portionen"]').value = data.portionen || 4;
        preview.querySelector('[name="tags"]').value = Array.isArray(data.tags) ? data.tags.join(', ') : (data.tags || '');
        preview.querySelector('[name="zutaten"]').value = data.zutaten || '';
        preview.querySelector('[name="zubereitung"]').value = data.zubereitung || '';
        preview.querySelector('[name="anmerkung"]').value = data.anmerkung || '';

        preview.scrollIntoView({ behavior: 'smooth' });
    }

    function hidePreview() {
        document.getElementById('preview')?.classList.add('hidden');
    }

    function showStatus(msg, type) {
        const el = document.getElementById('status');
        if (!el) return;
        if (statusHideTimer) {
            clearTimeout(statusHideTimer);
            statusHideTimer = null;
        }
        el.textContent = msg;
        el.className = 'status ' + type;
        el.classList.remove('hidden');
        statusHideTimer = setTimeout(() => {
            el.classList.add('hidden');
            statusHideTimer = null;
        }, type === 'error' ? 10000 : 5000);
    }

    function hideStatus() {
        if (statusHideTimer) {
            clearTimeout(statusHideTimer);
            statusHideTimer = null;
        }
        document.getElementById('status')?.classList.add('hidden');
    }

    function showLoading(show) {
        const el = document.getElementById('loading');
        if (el) el.classList.toggle('hidden', !show);
    }

    async function loadWeekPlan() {
        try {
            const res = await fetch('api/generate-plan.php');
            const data = await res.json();
            if (data) {
                await enrichPlanWithImages(data);
                renderWeekPlan(data);
            }
            else renderEmptyPlan();
        } catch {
            renderEmptyPlan();
        }
    }

    async function enrichPlanWithImages(data) {
        if (!data || !data.plan || data.plan.length === 0) return;
        const needs = data.plan.some(d => d.recipe && d.recipe.id && !d.recipe.image);
        if (!needs) return;
        try {
            const res = await fetch('api/get-recipes.php');
            const recipes = await res.json();
            const byId = new Map(recipes.filter(r => r.id).map(r => [r.id, r]));
            data.plan.forEach(d => {
                const rec = d.recipe || {};
                const fresh = rec.id && byId.has(rec.id) ? byId.get(rec.id) : null;
                if (fresh) {
                    if (!rec.image) {
                        rec.image = fresh.image || '';
                    }
                }
                d.recipe = rec;
            });
        } catch {
            // Kein hartes Fehlerhandling nötig
        }
    }

    function renderWeekPlan(data) {
        if (!weekGrid) return;
        weekGrid.innerHTML = '';
        currentPlanData = data;

        // Einkaufslisten-Button anzeigen/verstecken
        const btnShopping = document.getElementById('btn-shopping');
        if (btnShopping) {
            if (data && data.plan && data.plan.length > 0) {
                btnShopping.classList.remove('hidden');
            } else {
                btnShopping.classList.add('hidden');
            }
        }

        if (btnConfirmPlan) {
            if (data && data.plan && data.plan.length > 0) {
                btnConfirmPlan.classList.remove('hidden');
            } else {
                btnConfirmPlan.classList.add('hidden');
            }
        }

        if (!data.plan || data.plan.length === 0) {
            renderEmptyPlan();
            return;
        }

        const dayOrder = new Map([
            ['Samstag', 0],
            ['Sonntag', 1],
            ['Montag', 2],
            ['Dienstag', 3],
            ['Mittwoch', 4],
            ['Donnerstag', 5],
            ['Freitag', 6]
        ]);
        const planWithIndex = data.plan.map((day, index) => ({ day, index }));
        planWithIndex.sort((a, b) => {
            const oa = dayOrder.get(a.day.tag);
            const ob = dayOrder.get(b.day.tag);
            if (oa == null && ob == null) return a.index - b.index;
            if (oa == null) return 1;
            if (ob == null) return -1;
            return oa - ob;
        });

        planWithIndex.forEach(({ day, index }) => {
            const card = document.createElement('div');
            const isWeekend = day.tag === 'Samstag' || day.tag === 'Sonntag';
            const isDisabled = Boolean(day.disabled);
            card.className = 'day-card' + (isWeekend ? ' weekend' : '') + (isDisabled ? ' day-disabled' : '');
            card.draggable = !isDisabled;
            card.dataset.index = index;

            const toggleLabel = isDisabled ? 'Aktiv' : 'Frei';
            const toggleTitle = isDisabled ? 'Tag wieder aktivieren' : 'Tag ausschalten';

            if (isDisabled) {
                card.innerHTML = `
                    <div class="day-card-head">
                        <div>
                            <div class="day-name">${day.tag}</div>
                            <div class="day-label day-label-off">Ausgeschaltet</div>
                        </div>
                        <button class="btn-toggle-day" title="${toggleTitle}">${toggleLabel}</button>
                    </div>
                    <div class="day-thumb day-thumb-off"></div>
                    <div class="day-card-body">
                        <div class="day-recipe day-recipe-off">Kein Essen geplant</div>
                    </div>
                `;
            } else {
                const typ = day.recipe.typ || 'normal';
                const typBadge = typ === 'thermomix'
                    ? '<span class="badge badge-tm">TM</span>'
                    : typ === 'airfryer'
                        ? '<span class="badge badge-af">AF</span>'
                        : '';
                const dayTags = day.recipe.tags || [];
                const isZufallsrezept = Array.isArray(dayTags) && dayTags.some(t => String(t).toLowerCase() === 'zufallsrezept');
                const zufallBadge = isZufallsrezept ? '<span class="badge badge-zufall">Zufallsrezept</span>' : '';
                const dayThumb = day.recipe.image
                    ? `<div class="day-thumb" style="background-image:url('${day.recipe.image}')"></div>`
                    : '<div class="day-thumb day-thumb-placeholder"></div>';
                const kochzeitText = day.recipe && day.recipe.kochzeit ? day.recipe.kochzeit + ' Min.' : '–';
                const dayCategory = getCategoryLabel(day.recipe.kategorie);
                card.innerHTML = `
                    <div class="day-card-head">
                        <div>
                            <div class="day-name">${day.tag}</div>
                            <div class="day-label">${isWeekend ? 'Wochenendküche' : 'Wochenmenü'}</div>
                        </div>
                        <div class="day-card-actions">
                            <button class="btn-swap" title="Rezept austauschen">Tausch</button>
                            <button class="btn-toggle-day" title="${toggleTitle}">${toggleLabel}</button>
                        </div>
                    </div>
                    ${dayThumb}
                    <div class="day-card-body">
                        <div class="day-recipe">${typBadge}${zufallBadge}${day.recipe.title}</div>
                        <div class="day-meta">${dayCategory} · ${kochzeitText}</div>
                    </div>
                `;
            }

            // Toggle-Button
            card.querySelector('.btn-toggle-day').addEventListener('click', (e) => {
                e.stopPropagation();
                currentPlanData.plan[index].disabled = !isDisabled;
                renderWeekPlan(currentPlanData);
                savePlan();
            });

            if (!isDisabled) {
                // Klick auf Card → Rezept-Modal (aber nicht wenn Buttons geklickt)
                card.addEventListener('click', (e) => {
                    if (e.target.closest('.btn-swap') || e.target.closest('.btn-toggle-day')) return;
                    showRecipeModal(day.recipe);
                });

                // Swap-Button
                card.querySelector('.btn-swap').addEventListener('click', (e) => {
                    e.stopPropagation();
                    openSwapModal(index);
                });

                // Drag & Drop Events
                card.addEventListener('dragstart', (e) => {
                    dragFromIndex = index;
                    card.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                });

                card.addEventListener('dragend', () => {
                    card.classList.remove('dragging');
                    document.querySelectorAll('.day-card.drag-over').forEach(c => c.classList.remove('drag-over'));
                });

                card.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    card.classList.add('drag-over');
                });

                card.addEventListener('dragleave', () => {
                    card.classList.remove('drag-over');
                });

                card.addEventListener('drop', (e) => {
                    e.preventDefault();
                    card.classList.remove('drag-over');
                    const toIndex = index;
                    if (dragFromIndex !== null && dragFromIndex !== toIndex) {
                        swapDays(dragFromIndex, toIndex);
                    }
                    dragFromIndex = null;
                });
            }

            weekGrid.appendChild(card);
        });

        if (data.erstellt) {
            const info = document.createElement('p');
            info.className = 'plan-info';
            info.textContent = 'Erstellt: ' + data.erstellt;
            weekGrid.parentNode.insertBefore(info, weekGrid.nextSibling);
            // Altes Info-Element entfernen
            const oldInfo = weekGrid.parentNode.querySelector('.plan-info + .plan-info');
            if (oldInfo) oldInfo.remove();
        }
    }

    function swapDays(fromIdx, toIdx) {
        if (!currentPlanData || !currentPlanData.plan) return;
        const plan = currentPlanData.plan;
        // Nur die Rezepte tauschen, Tage bleiben
        const tempRecipe = plan[fromIdx].recipe;
        plan[fromIdx].recipe = plan[toIdx].recipe;
        plan[toIdx].recipe = tempRecipe;
        renderWeekPlan(currentPlanData);
        savePlan();
    }

    async function savePlan() {
        if (!currentPlanData) return;
        try {
            await fetch('api/update-plan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ plan: currentPlanData.plan })
            });
        } catch (err) {
            showStatus('Fehler beim Speichern: ' + err.message, 'error');
        }
    }

    async function openSwapModal(dayIndex) {
        swapDayIndex = dayIndex;
        const dayName = currentPlanData.plan[dayIndex].tag;
        const currentTitle = currentPlanData.plan[dayIndex].recipe.title;
        document.getElementById('swap-info').textContent = `${dayName}: "${currentTitle}" ersetzen durch:`;

        if (swapSearch) swapSearch.value = '';
        swapModal.classList.remove('hidden');

        // Rezepte laden falls noch nicht geschehen
        if (allRecipesForSwap.length === 0) {
            try {
                const recipes = await fetchJsonOrThrow('api/get-recipes.php');
                allRecipesForSwap = Array.isArray(recipes)
                    ? recipes.filter(recipe => normalizeCategory(recipe.kategorie) === 'hauptspeise')
                    : [];
            } catch (err) {
                allRecipesForSwap = [];
                showStatus('Fehler beim Laden der Rezepte: ' + err.message, 'error');
            }
        }
        renderSwapList('');
    }

    function renderSwapList(search) {
        if (!swapList) return;
        swapList.innerHTML = '';

        let filtered = allRecipesForSwap;
        if (search) {
            filtered = filtered.filter(r =>
                (r.title || '').toLowerCase().includes(search) ||
                (Array.isArray(r.tags) ? r.tags.join(' ') : '').toLowerCase().includes(search)
            );
        }

        if (filtered.length === 0) {
            swapList.innerHTML = '<p class="empty-msg">Keine Rezepte gefunden.</p>';
            return;
        }

        filtered.forEach(recipe => {
            const item = document.createElement('div');
            item.className = 'swap-item';
            const typ = recipe.typ || 'normal';
            const tmBadge = typ === 'thermomix'
                ? '<span class="badge badge-tm">TM</span>'
                : typ === 'airfryer'
                    ? '<span class="badge badge-af">AF</span>'
                    : '';
            item.innerHTML = `
                <div class="swap-item-title">${tmBadge}${recipe.title || 'Ohne Titel'}</div>
                <div class="swap-item-meta">${recipe.kochzeit || '?'} Min. · ${recipe.portionen || '?'} Portionen</div>
            `;
            item.addEventListener('click', () => {
                if (swapDayIndex === null || !currentPlanData) return;
                currentPlanData.plan[swapDayIndex].recipe = {
                    id: recipe.id || null,
                    title: recipe.title || 'Unbekannt',
                    kategorie: normalizeCategory(recipe.kategorie),
                    typ: recipe.typ || 'normal',
                    kochzeit: recipe.kochzeit || 30,
                    portionen: recipe.portionen || 4,
                    tags: recipe.tags || [],
                    anmerkung: recipe.anmerkung || '',
                    image: recipe.image || '',
                    body: recipe.body || '',
                };
                swapModal.classList.add('hidden');
                renderWeekPlan(currentPlanData);
                savePlan();
                showStatus('Rezept ausgetauscht', 'success');
            });
            swapList.appendChild(item);
        });
    }

    function renderEmptyPlan() {
        if (!weekGrid) return;
        weekGrid.innerHTML = '<p class="empty-msg">Noch kein Wochenplan erstellt. Klicke auf "Neuen Wochenplan generieren".</p>';
    }

    function renderStarsHtml(rating, recipeId) {
        const canRate = Number(recipeId || 0) > 0;
        const stars = [1, 2, 3, 4, 5].map(v => {
            const filled = v <= rating;
            return `<span class="star${filled ? ' star-filled' : ''}${canRate ? ' star-interactive' : ''}" data-value="${v}" title="${v} Stern${v !== 1 ? 'e' : ''}">★</span>`;
        }).join('');
        const label = rating > 0 ? '' : '<span class="star-label">Noch nicht bewertet</span>';
        return `<div class="stars-row">${stars}${label}</div>`;
    }

    function renderTagsHtml(tags) {
        if (!Array.isArray(tags)) {
            return '';
        }
        return tags.map(t => {
            const tagClass = String(t).toLowerCase() === 'zufallsrezept' ? 'tag tag-zufallsrezept' : 'tag';
            return `<span class="${tagClass}">${t}</span>`;
        }).join('');
    }

    function renderRecipeDetailHtml(recipe, imageOverride) {
        const imagePath = imageOverride ?? recipe.image;
        const imageHtml = imagePath
            ? `<div class="recipe-media"><img src="${imagePath}" alt="${recipe.title}"></div>`
            : '';

        return `
            <div class="recipe-detail">
                ${imageHtml}
                <div class="recipe-text">${simpleMarkdown(recipe.body || '')}</div>
            </div>
        `;
    }

    function showRecipeModal(recipe) {
        const modal = document.getElementById('recipe-modal');
        if (!modal) return;

        currentEditRecipe = recipe;
        resetThermomixPreview();
        document.getElementById('modal-title').textContent = recipe.title;
        const modalKategorie = document.getElementById('modal-kategorie');
        if (modalKategorie) modalKategorie.innerHTML = `<span class="badge badge-normal">${getCategoryLabel(recipe.kategorie)}</span>`;
        document.getElementById('modal-typ').innerHTML = recipe.typ === 'thermomix'
            ? '<span class="badge badge-tm">Thermomix</span>'
            : recipe.typ === 'airfryer'
                ? '<span class="badge badge-af">Airfryer</span>'
                : '<span class="badge badge-normal">Normal</span>';
        document.getElementById('modal-kochzeit').textContent = recipe.kochzeit + ' Minuten';
        document.getElementById('modal-portionen').textContent = recipe.portionen + ' Portionen';

        // Bewertung
        const ratingEl = document.getElementById('modal-rating');
        if (ratingEl) {
            ratingEl.innerHTML = renderStarsHtml(recipe.rating || 0, recipe.id);
            ratingEl.querySelectorAll('.star').forEach(star => {
                star.addEventListener('click', async () => {
                    if (!recipe.id) return;
                    const val = Number(star.dataset.value);
                    try {
                        const result = await fetchJsonOrThrow('api/rate-recipe.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: recipe.id, rating: val })
                        });
                        recipe.rating = result.recipe.rating;
                        ratingEl.innerHTML = renderStarsHtml(recipe.rating, recipe.id);
                        ratingEl.querySelectorAll('.star').forEach(s => {
                            s.addEventListener('click', async () => {
                                // Re-attach (handled by re-calling showRecipeModal on next open)
                            });
                        });
                        // allRecipes aktualisieren
                        const idx = allRecipes.findIndex(r => Number(r.id) === Number(recipe.id));
                        if (idx !== -1) allRecipes[idx].rating = recipe.rating;
                        showStatus('Bewertung gespeichert', 'success');
                    } catch (err) {
                        showStatus('Fehler: ' + err.message, 'error');
                    }
                });
            });
        }

        // Erscheinungszähler
        const appearancesEl = document.getElementById('modal-appearances');
        if (appearancesEl) {
            const count = recipe.plan_appearances || 0;
            if (count > 0) {
                appearancesEl.textContent = `${count}× im Wochenplan bestätigt`;
                appearancesEl.classList.remove('hidden');
            } else {
                appearancesEl.classList.add('hidden');
            }
        }

        const tagsEl = document.getElementById('modal-tags');
        if (tagsEl) {
            tagsEl.innerHTML = renderTagsHtml(recipe.tags);
        }

        const body = document.getElementById('modal-body');
        if (body) {
            body.innerHTML = renderRecipeDetailHtml(recipe);
        }

        const anmerkungEl = document.getElementById('modal-anmerkung');
        if (anmerkungEl) {
            const anm = recipe.anmerkung || '';
            if (anm) {
                const isUrl = /^https?:\/\//.test(anm);
                anmerkungEl.innerHTML = '<strong>Anmerkung:</strong> ' + (isUrl ? `<a href="${anm}" target="_blank">${anm}</a>` : anm);
                anmerkungEl.classList.remove('hidden');
            } else {
                anmerkungEl.innerHTML = '';
                anmerkungEl.classList.add('hidden');
            }
        }

        if (btnShowThermomix) {
            const canConvertToThermomix = !['thermomix'].includes(recipe.typ || 'normal') && Number(recipe.id || 0) > 0;
            btnShowThermomix.classList.toggle('hidden', !canConvertToThermomix);
            btnShowThermomix.disabled = false;
            btnShowThermomix.textContent = 'Als Thermomix anzeigen';
        }

        modal.classList.remove('hidden');
    }

    // === Rezept bearbeiten ===
    let currentEditRecipe = null;
    let currentThermomixPreview = null;

    const btnCookRecipe = document.getElementById('btn-cook-recipe');
    const btnEditRecipe = document.getElementById('btn-edit-recipe');
    const btnShowThermomix = document.getElementById('btn-show-thermomix');
    const btnSaveThermomix = document.getElementById('btn-save-thermomix');
    const editModal = document.getElementById('edit-modal');
    const formEdit = document.getElementById('form-edit');
    const btnCancelEdit = document.getElementById('btn-cancel-edit');
    const editTagsInput = formEdit?.querySelector('[name="tags"]');
    const btnRemoveRandomTag = editModal?.querySelector('[data-remove-random-tag]');
    const thermomixPreview = document.getElementById('thermomix-preview');

    if (btnCookRecipe) {
        btnCookRecipe.addEventListener('click', () => {
            const id = Number(currentEditRecipe?.id || 0);
            if (id < 1) return;
            window.location.href = 'kochen.php?id=' + encodeURIComponent(id);
        });
    }

    if (btnEditRecipe) {
        btnEditRecipe.addEventListener('click', () => {
            if (!currentEditRecipe) return;
            openEditModal(currentEditRecipe);
        });
    }

    if (btnShowThermomix) {
        btnShowThermomix.addEventListener('click', async () => {
            if (!currentEditRecipe || Number(currentEditRecipe.id || 0) < 1) return;

            btnShowThermomix.disabled = true;
            btnShowThermomix.textContent = 'Erstelle...';
            try {
                const data = await fetchJsonOrThrow('api/convert-to-thermomix.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: currentEditRecipe.id })
                });
                if (!data?.recipe) {
                    throw new Error('Keine Thermomix-Vorschau erhalten');
                }

                currentThermomixPreview = {
                    ...data.recipe,
                    image: currentEditRecipe.image || data.recipe.image || ''
                };
                renderThermomixPreview(currentThermomixPreview);
                showStatus('Thermomix-Version erstellt. Bei Bedarf jetzt separat speichern.', 'success');
            } catch (err) {
                resetThermomixPreview();
                showStatus('Fehler: ' + err.message, 'error');
            } finally {
                btnShowThermomix.disabled = false;
                btnShowThermomix.textContent = 'Als Thermomix anzeigen';
            }
        });
    }

    if (btnSaveThermomix) {
        btnSaveThermomix.addEventListener('click', async () => {
            if (!currentEditRecipe || !currentThermomixPreview || Number(currentEditRecipe.id || 0) < 1) return;

            btnSaveThermomix.disabled = true;
            btnSaveThermomix.textContent = 'Speichere...';
            try {
                const data = await fetchJsonOrThrow('api/save-thermomix-conversion.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        source_id: currentEditRecipe.id,
                        recipe: currentThermomixPreview
                    })
                });

                const savedRecipe = await fetchRecipeById(data.id || 0);
                showStatus(data.message || 'Thermomix-Rezept gespeichert', 'success');

                if (savedRecipe) {
                    showRecipeModal(savedRecipe);
                } else {
                    resetThermomixPreview();
                    if (recipeList) {
                        await loadAllRecipes();
                    }
                }
            } catch (err) {
                showStatus('Fehler: ' + err.message, 'error');
            } finally {
                btnSaveThermomix.disabled = false;
                btnSaveThermomix.textContent = 'Als neues Thermomix-Rezept speichern';
            }
        });
    }

    const btnDeleteRecipe = document.getElementById('btn-delete-recipe');
    if (btnDeleteRecipe) {
        btnDeleteRecipe.addEventListener('click', async () => {
            if (!currentEditRecipe || !currentEditRecipe.id) return;
            if (!confirm(`Rezept "${currentEditRecipe.title}" wirklich löschen?`)) return;

            try {
                const res = await fetch('api/delete-recipe.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: currentEditRecipe.id })
                });
                const data = await res.json();
                if (data.error) throw new Error(data.error);
                document.getElementById('recipe-modal')?.classList.add('hidden');
                showStatus(data.message, 'success');
                if (recipeList) {
                    loadAllRecipes();
                } else if (currentPlanData) {
                    loadWeekPlan();
                }
            } catch (err) {
                showStatus('Fehler: ' + err.message, 'error');
            }
        });
    }

    if (editModal) {
        editModal.querySelector('.modal-close')?.addEventListener('click', () => {
            editModal.classList.add('hidden');
        });
        editModal.addEventListener('click', (e) => {
            if (e.target === editModal) editModal.classList.add('hidden');
        });
    }

    if (btnCancelEdit) {
        btnCancelEdit.addEventListener('click', () => {
            editModal.classList.add('hidden');
        });
    }

    if (editTagsInput) {
        editTagsInput.addEventListener('input', () => {
            syncRandomTagButtonState();
        });
    }

    if (btnRemoveRandomTag) {
        btnRemoveRandomTag.addEventListener('click', () => {
            removeRandomRecipeTagFromForm();
        });
    }

    function resetThermomixPreview() {
        currentThermomixPreview = null;
        if (!thermomixPreview) return;

        thermomixPreview.classList.add('hidden');
        document.getElementById('tm-preview-title').textContent = '';
        document.getElementById('tm-preview-kochzeit').textContent = '';
        document.getElementById('tm-preview-portionen').textContent = '';
        document.getElementById('tm-preview-tags').innerHTML = '';
        document.getElementById('tm-preview-body').innerHTML = '';
    }

    function renderThermomixPreview(recipe) {
        if (!thermomixPreview) return;

        document.getElementById('tm-preview-title').textContent = recipe.title || 'Thermomix-Version';
        document.getElementById('tm-preview-kochzeit').textContent = (recipe.kochzeit || '?') + ' Minuten';
        document.getElementById('tm-preview-portionen').textContent = (recipe.portionen || '?') + ' Portionen';
        document.getElementById('tm-preview-tags').innerHTML = renderTagsHtml(recipe.tags);
        document.getElementById('tm-preview-body').innerHTML = renderRecipeDetailHtml(recipe, recipe.image || currentEditRecipe?.image || '');
        thermomixPreview.classList.remove('hidden');
    }

    async function fetchRecipeById(recipeId) {
        const targetId = Number(recipeId || 0);
        if (targetId < 1) {
            return null;
        }

        const recipes = await fetchJsonOrThrow('api/get-recipes.php');
        if (!Array.isArray(recipes)) {
            throw new Error('Ungültige Rezeptliste');
        }

        allRecipes = recipes;
        if (recipeList) {
            renderRecipeList();
        }

        return recipes.find(recipe => Number(recipe.id || 0) === targetId) || null;
    }

    function openEditModal(recipe) {
        if (!editModal || !formEdit) return;
        const categoryField = formEdit.querySelector('[name="kategorie"]');
        resetThermomixPreview();

        // Detail-Modal schließen
        document.getElementById('recipe-modal')?.classList.add('hidden');

        formEdit.querySelector('[name="id"]').value = recipe.id || '';
        formEdit.querySelector('[name="title"]').value = recipe.title || '';
        ensureCategoryOption(categoryField, recipe.kategorie);
        if (categoryField) {
            categoryField.value = normalizeCategory(recipe.kategorie);
        }
        formEdit.querySelector('[name="typ"]').value = recipe.typ || 'normal';
        formEdit.querySelector('[name="kochzeit"]').value = recipe.kochzeit || 30;
        formEdit.querySelector('[name="portionen"]').value = recipe.portionen || 4;
        formEdit.querySelector('[name="tags"]').value = Array.isArray(recipe.tags) ? recipe.tags.join(', ') : (recipe.tags || '');
        syncRandomTagButtonState();

        // Zutaten und Zubereitung aus body extrahieren
        const body = recipe.body || '';
        let zutaten = '';
        let zubereitung = '';

        const zutatenMatch = body.match(/## Zutaten\n([\s\S]*?)(?=\n## |$)/);
        if (zutatenMatch) {
            zutaten = zutatenMatch[1].replace(/^- /gm, '').trim();
        }

        const zubereitungMatch = body.match(/## Zubereitung\n([\s\S]*?)$/);
        if (zubereitungMatch) {
            zubereitung = zubereitungMatch[1].replace(/^\d+\.\s*/gm, '').trim();
        }

        formEdit.querySelector('[name="zutaten"]').value = zutaten;
        formEdit.querySelector('[name="zubereitung"]').value = zubereitung;
        formEdit.querySelector('[name="anmerkung"]').value = recipe.anmerkung || '';
        const removeFlag = formEdit.querySelector('[name="remove_image"]');
        if (removeFlag) removeFlag.value = '';
        const previewEl = document.getElementById('edit-image-preview');
        if (previewEl) {
            if (recipe.image) {
                previewEl.innerHTML = `<img src="${recipe.image}" alt="${recipe.title}">`;
                previewEl.classList.remove('hidden');
            } else {
                previewEl.innerHTML = '';
                previewEl.classList.add('hidden');
            }
        }

        const removeBtn = editModal.querySelector('[data-remove-image]');
        if (removeBtn) {
            // Button-Zustand abhängig vom Bild
            removeBtn.disabled = !recipe.image;
            removeBtn.onclick = () => {
                if (removeBtn.disabled) return;
                if (!confirm('Bild wirklich entfernen?')) return;
                let flag = formEdit.querySelector('[name="remove_image"]');
                if (!flag) {
                    flag = document.createElement('input');
                    flag.type = 'hidden';
                    flag.name = 'remove_image';
                    formEdit.appendChild(flag);
                }
                flag.value = '1';
                if (previewEl) {
                    previewEl.innerHTML = '';
                    previewEl.classList.add('hidden');
                }
                const fileInput = formEdit.querySelector('input[type="file"][name="image"]');
                if (fileInput) fileInput.value = '';
                showStatus('Bild wird entfernt (beim Speichern)', 'success');
                removeBtn.disabled = true;
            };
        }

        editModal.classList.remove('hidden');
    }

    if (formEdit) {
        formEdit.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(formEdit);
            showLoading(true);
            try {
                const res = await fetch('api/update-recipe.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();
                if (data.error) throw new Error(data.error);
                await refreshRecipeAfterUpdate(data.id || Number(fd.get('id') || 0));
                showStatus(data.message, 'success');
                editModal.classList.add('hidden');
            } catch (err) {
                showStatus('Fehler: ' + err.message, 'error');
            } finally {
                showLoading(false);
            }
        });
    }

    // === Rezeptübersicht ===
    const recipeList = document.getElementById('recipe-list');
    const filterKategorie = document.getElementById('filter-kategorie');
    const filterTyp = document.getElementById('filter-typ');
    const searchInput = document.getElementById('search-input');
    let allRecipes = [];

    if (recipeList) {
        loadAllRecipes();
        filterKategorie?.addEventListener('change', renderRecipeList);
        filterTyp?.addEventListener('change', renderRecipeList);
        searchInput?.addEventListener('input', renderRecipeList);
    }

    async function loadAllRecipes() {
        try {
            const recipes = await fetchJsonOrThrow('api/get-recipes.php');
            if (!Array.isArray(recipes)) {
                throw new Error('Ungültige Rezeptliste');
            }
            allRecipes = recipes;
            renderRecipeList();
        } catch (err) {
            recipeList.innerHTML = `<p class="empty-msg">Fehler beim Laden der Rezepte: ${err.message}</p>`;
            showStatus('Fehler beim Laden der Rezepte: ' + err.message, 'error');
        }
    }

    function renderRecipeList() {
        if (!recipeList) return;
        recipeList.innerHTML = '';

        let filtered = allRecipes;
        const categoryFilter = filterKategorie?.value || 'alle';
        const typFilter = filterTyp?.value || 'alle';
        const search = searchInput?.value.trim().toLowerCase() || '';

        if (categoryFilter !== 'alle') {
            filtered = filtered.filter(r => normalizeCategory(r.kategorie) === categoryFilter);
        }
        if (typFilter !== 'alle') {
            filtered = filtered.filter(r => (r.typ || 'normal') === typFilter);
        }
        if (search) {
            filtered = filtered.filter(r =>
                (r.title || '').toLowerCase().includes(search) ||
                (Array.isArray(r.tags) ? r.tags.join(' ') : '').toLowerCase().includes(search)
            );
        }

        if (filtered.length === 0) {
            recipeList.innerHTML = '<p class="empty-msg">Keine Rezepte gefunden.</p>';
            return;
        }

        filtered.forEach((recipe, index) => {
            const card = document.createElement('div');
            const recipeTyp = recipe.typ || 'normal';
            const isTM = recipeTyp === 'thermomix';
            const isAF = recipeTyp === 'airfryer';
            card.className = 'recipe-card';
            const tmBadge = isTM
                ? '<span class="badge badge-tm">TM</span>'
                : isAF
                    ? '<span class="badge badge-af">AF</span>'
                    : '';
            const categoryBadge = `<span class="badge badge-normal">${getCategoryLabel(recipe.kategorie)}</span>`;
            const tags = Array.isArray(recipe.tags)
                ? recipe.tags.map(t => {
                    const tagClass = String(t).toLowerCase() === 'zufallsrezept' ? 'tag tag-zufallsrezept' : 'tag';
                    return `<span class="${tagClass}">${t}</span>`;
                }).join('')
                : '';
            const thumb = recipe.image
                ? `<div class="recipe-card-media"><img class="recipe-thumb" src="${recipe.image}" alt="${recipe.title}"></div>`
                : `<div class="recipe-card-media recipe-card-media-placeholder"><span>${(recipe.title || 'R').charAt(0).toUpperCase()}</span></div>`;
            card.innerHTML = `
                ${thumb}
                <div class="recipe-card-content">
                    <div class="recipe-card-topline">
                        ${categoryBadge}
                        <span class="recipe-card-time">${recipe.kochzeit || '?'} Min.</span>
                    </div>
                    <div class="recipe-card-title">${tmBadge}${recipe.title || 'Ohne Titel'}</div>
                    <div class="recipe-card-meta">${recipe.portionen || '?'} Portionen · ${recipeTyp === 'thermomix' ? 'Thermomix' : recipeTyp === 'airfryer' ? 'Airfryer' : 'Klassisch'}</div>
                    <div class="recipe-card-tags">${tags}</div>
                    ${recipe.rating > 0 ? `<div class="recipe-card-stars">${renderStarsHtml(recipe.rating, null)}</div>` : ''}
                </div>
            `;
            card.addEventListener('click', () => showRecipeModal(recipe));
            recipeList.appendChild(card);
        });
    }

    // === Einkaufsliste Hilfsfunktionen ===

    async function loadShoppingList() {
        try {
            const res = await fetch('api/generate-shopping-list.php');
            const data = await res.json();
            if (data && data.kategorien && data.kategorien.length > 0) {
                renderShoppingList(data);
            } else {
                renderEmptyShoppingList();
            }
        } catch {
            renderEmptyShoppingList();
        }
    }

    function renderShoppingList(data) {
        if (!shoppingList) return;
        shoppingList.innerHTML = '';

        if (!data.kategorien || data.kategorien.length === 0) {
            renderEmptyShoppingList();
            return;
        }

        if (btnPrintList) btnPrintList.classList.remove('hidden');

        if (data.wochenplan_erstellt) {
            const info = document.createElement('p');
            info.className = 'shopping-info';
            info.textContent = 'Basierend auf Wochenplan vom ' + data.wochenplan_erstellt;
            shoppingList.appendChild(info);
        }

        data.kategorien.forEach((kat, katIdx) => {
            const section = document.createElement('div');
            section.className = 'shopping-category';
            section.innerHTML = `
                <div class="shopping-category-head">
                    <h3 class="shopping-category-title">${kat.name}</h3>
                    <span class="shopping-category-count">${kat.zutaten.length}</span>
                </div>
            `;

            const list = document.createElement('ul');
            list.className = 'shopping-items';

            kat.zutaten.forEach((zutat, zutatIdx) => {
                const li = document.createElement('li');
                li.className = 'shopping-item';
                li.innerHTML = `
                    <span class="shopping-item-text">${zutat}</span>
                    <button class="btn-remove-item" title="Entfernen">Erledigt</button>
                `;
                li.querySelector('.btn-remove-item').addEventListener('click', () => {
                    removeShoppingItem(katIdx, zutatIdx, li);
                });
                list.appendChild(li);
            });

            section.appendChild(list);
            shoppingList.appendChild(section);
        });
    }

    function renderEmptyShoppingList() {
        if (!shoppingList) return;
        shoppingList.innerHTML = '<p class="empty-msg">Noch keine Einkaufsliste erstellt. Klicke auf "Einkaufsliste generieren".</p>';
        if (btnPrintList) btnPrintList.classList.add('hidden');
    }

    async function removeShoppingItem(katIdx, zutatIdx, element) {
        element.classList.add('removing');
        try {
            const res = await fetch('api/update-shopping-list.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ kategorie: katIdx, zutat: zutatIdx })
            });
            const result = await res.json();
            if (result.error) throw new Error(result.error);
            // Liste neu rendern mit aktualisierten Daten
            renderShoppingList(result.data);
        } catch (err) {
            element.classList.remove('removing');
            showStatus('Fehler: ' + err.message, 'error');
        }
    }

    function simpleMarkdown(md) {
        return md
            .replace(/^## (.+)$/gm, '<h3>$1</h3>')
            .replace(/^- (.+)$/gm, '<li>$1</li>')
            .replace(/(<li>.*<\/li>)/gs, '<ul>$1</ul>')
            .replace(/^\d+\. (.+)$/gm, '<li>$1</li>')
            .replace(/\n/g, '<br>');
    }

    async function loadPlanTemplate() {
        if (!templatePreview && !printModelSelect) return;
        try {
            const res = await fetch('api/get-plan-template.php');
            const data = await res.json();
            if (data.print_model) {
                renderPrintModelSettings(data.print_model);
            }
            if (data.template) {
                renderTemplatePreview(data.template);
            } else {
                templatePreview?.classList.add('hidden');
                if (templatePreview) templatePreview.innerHTML = '';
            }
        } catch {
            templatePreview?.classList.add('hidden');
            if (templatePreview) templatePreview.innerHTML = '';
        }
    }

    function renderPrintModelSettings(printModel) {
        if (!printModelSelect) return;

        const options = Array.isArray(printModel.options) ? printModel.options : [];
        if (options.length > 0) {
            printModelSelect.innerHTML = options.map(option => (
                `<option value="${option.id}">${option.label}</option>`
            )).join('');
        }

        printModelSelect.value = printModel.current || 'gemini-3.1-flash-image';

        if (printModelHelp) {
            const current = options.find(option => option.id === printModelSelect.value) || null;
            printModelHelp.textContent = current?.description || '';
        }
    }

    function renderTemplatePreview(template) {
        if (!templatePreview) return;
        const dim = (template.width && template.height) ? `${template.width} x ${template.height}px` : 'unbekannt';
        const size = template.size_kb ? `${template.size_kb} KB` : 'unbekannt';
        const uploaded = template.uploaded_at || 'unbekannt';
        templatePreview.innerHTML = `
            <h3>Aktive Vorlage</h3>
            <img src="${template.path}" alt="Wochenplan-Vorlage">
            <p>Upload: ${uploaded} · Größe: ${size} · Auflösung: ${dim}</p>
        `;
        templatePreview.classList.remove('hidden');
    }

    async function loadCategories() {
        try {
            const res = await fetch('api/get-categories.php');
            const data = await res.json();
            if (Array.isArray(data.categories) && data.categories.length > 0) {
                setCategories(data.categories);
            } else {
                populateCategorySelects();
                renderCategoryList();
            }
        } catch {
            populateCategorySelects();
            renderCategoryList();
        }
    }

    function setCategories(list) {
        categories = list.map(item => ({
            id: item.id,
            label: item.label,
            is_default: Boolean(item.is_default),
            usage_count: Number(item.usage_count || 0)
        }));
        categoryMap = {};
        categories.forEach(item => {
            categoryMap[item.id] = item.label;
        });
        populateCategorySelects();
        renderCategoryList();
    }

    function populateCategorySelects() {
        categorySelects.forEach(select => {
            const currentValue = select.value;
            const includeAll = select.dataset.includeAll === '1';
            select.innerHTML = '';
            if (includeAll) {
                const allOption = document.createElement('option');
                allOption.value = 'alle';
                allOption.textContent = 'Alle Kategorien';
                select.appendChild(allOption);
            }
            categories.forEach(item => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = item.label;
                select.appendChild(option);
            });
            const nextValue = includeAll && currentValue === '' ? 'alle' : normalizeCategory(currentValue);
            select.value = includeAll && currentValue === 'alle' ? 'alle' : nextValue;
        });
    }

    function renderCategoryList() {
        if (!categoryList) return;
        if (!categories.length) {
            categoryList.innerHTML = '<p class="empty-msg">Noch keine Kategorien vorhanden.</p>';
            if (categorySummary) {
                categorySummary.classList.add('hidden');
                categorySummary.innerHTML = '';
            }
            return;
        }

        const defaultCategories = categories.filter(item => item.is_default);
        const customCategories = categories.filter(item => !item.is_default);

        if (categorySummary) {
            categorySummary.innerHTML = `
                <div class="admin-summary-card">
                    <strong>${categories.length}</strong>
                    <span>Kategorien gesamt</span>
                </div>
                <div class="admin-summary-card">
                    <strong>${defaultCategories.length}</strong>
                    <span>Standard</span>
                </div>
                <div class="admin-summary-card">
                    <strong>${customCategories.length}</strong>
                    <span>Benutzerdefiniert</span>
                </div>
            `;
            categorySummary.classList.remove('hidden');
        }

        categoryList.innerHTML = `
            ${renderCategorySection('Standardkategorien', 'Fest eingebaut und nicht löschbar.', defaultCategories, false)}
            ${renderCategorySection('Eigene Kategorien', 'Von dir angelegt und bei Nichtverwendung löschbar.', customCategories, true)}
        `;

        categoryList.querySelectorAll('[data-category-delete]').forEach(button => {
            button.addEventListener('click', async () => {
                const id = button.dataset.categoryDelete;
                if (!id || button.disabled) return;
                const category = categories.find(item => item.id === id);
                const label = category?.label || id;
                if (!confirm(`Kategorie "${label}" wirklich löschen?`)) return;

                button.disabled = true;
                try {
                    const res = await fetch('api/delete-category.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id })
                    });
                    const data = await res.json();
                    if (data.error) throw new Error(data.error);
                    if (Array.isArray(data.categories)) {
                        setCategories(data.categories);
                    } else {
                        await loadCategories();
                    }
                    showStatus(data.message || 'Kategorie gelöscht', 'success');
                } catch (err) {
                    button.disabled = false;
                    showStatus('Fehler: ' + err.message, 'error');
                }
            });
        });

        categoryList.querySelectorAll('[data-category-toggle]').forEach(button => {
            button.addEventListener('click', () => {
                const section = button.closest('.category-section');
                if (!section) return;
                section.classList.toggle('category-section-collapsed');
            });
        });
    }

    function renderCategorySection(title, description, items, allowDelete) {
        const rows = items.length > 0
            ? items.map(item => `
                <div class="category-row">
                    <div class="category-row-main">
                        <div class="category-row-title">${item.label}</div>
                        <div class="category-row-subline">
                            <code class="category-item-id">${item.id}</code>
                            <span class="category-item-usage">${item.usage_count} Rezept${item.usage_count === 1 ? '' : 'e'}</span>
                        </div>
                    </div>
                    <div class="category-row-action">
                        ${allowDelete
                            ? `<button class="btn btn-delete btn-category-delete" data-category-delete="${item.id}">Löschen</button>`
                            : '<span class="category-lock">Geschützt</span>'}
                    </div>
                </div>
            `).join('')
            : '<p class="empty-msg">Keine Einträge.</p>';

        const countLabel = `${items.length} Kategorie${items.length === 1 ? '' : 'n'}`;
        const sectionClass = 'category-section category-section-collapsed';

        return `
            <section class="${sectionClass}">
                <button type="button" class="category-section-toggle" data-category-toggle>
                    <span class="category-section-toggle-main">
                        <span class="category-section-toggle-title">${title}</span>
                        <span class="category-section-toggle-desc">${description}</span>
                    </span>
                    <span class="category-section-toggle-side">
                        <span class="category-section-count">${countLabel}</span>
                        <span class="category-section-chevron" aria-hidden="true"></span>
                    </span>
                </button>
                <div class="category-section-list">
                    ${rows}
                </div>
            </section>
        `;
    }

    function ensureCategoryOption(select, category) {
        if (!select) return;
        const normalized = normalizeCategory(category);
        if (select.querySelector(`option[value="${normalized}"]`)) {
            return;
        }
        const option = document.createElement('option');
        option.value = normalized;
        option.textContent = getCategoryLabel(normalized);
        select.appendChild(option);
    }

    function normalizeCategory(category) {
        return categoryMap[category] ? category : 'hauptspeise';
    }

    function getCategoryLabel(category) {
        return categoryMap[normalizeCategory(category)] || 'Hauptspeise';
    }

    function parseTags(value) {
        if (Array.isArray(value)) {
            return value
                .map(tag => String(tag).trim())
                .filter(tag => tag !== '');
        }
        return String(value || '')
            .split(',')
            .map(tag => tag.trim())
            .filter(tag => tag !== '');
    }

    function hasRandomRecipeTag(value) {
        return parseTags(value).some(tag => tag.toLowerCase() === 'zufallsrezept');
    }

    function syncRandomTagButtonState() {
        if (!btnRemoveRandomTag || !editTagsInput) return;
        const hasTag = hasRandomRecipeTag(editTagsInput.value);
        btnRemoveRandomTag.classList.toggle('hidden', !hasTag);
        btnRemoveRandomTag.disabled = !hasTag;
    }

    function removeRandomRecipeTagFromForm() {
        if (!editTagsInput) return;
        const filtered = parseTags(editTagsInput.value).filter(tag => tag.toLowerCase() !== 'zufallsrezept');
        editTagsInput.value = filtered.join(', ');
        syncRandomTagButtonState();
        showStatus('Zufallsrezept-Markierung entfernt. Jetzt einfach speichern.', 'success');
    }

    async function refreshRecipeAfterUpdate(recipeId) {
        const targetId = Number(recipeId || 0);
        if (targetId < 1) {
            if (recipeList) {
                await loadAllRecipes();
            }
            return;
        }

        try {
            const recipes = await fetchJsonOrThrow('api/get-recipes.php');
            if (!Array.isArray(recipes)) {
                throw new Error('Ungültige Rezeptliste');
            }

            allRecipes = recipes;
            const freshRecipe = recipes.find(recipe => Number(recipe.id || 0) === targetId) || null;
            if (freshRecipe && currentEditRecipe) {
                Object.keys(currentEditRecipe).forEach(key => {
                    delete currentEditRecipe[key];
                });
                Object.assign(currentEditRecipe, freshRecipe);
            }

            if (currentPlanData?.plan?.length && freshRecipe) {
                currentPlanData.plan.forEach(day => {
                    const dayRecipeId = Number(day?.recipe?.id || 0);
                    if (targetId > 0 && dayRecipeId === targetId) {
                        day.recipe = { ...freshRecipe };
                    }
                });
                renderWeekPlan(currentPlanData);
            }

            if (recipeList) {
                renderRecipeList();
            }
        } catch {
            if (recipeList) {
                await loadAllRecipes();
            }
        }
    }
});
