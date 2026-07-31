/**
 * Kochmodus: ein Schritt pro Bildschirm, Timer, Portionsskalierung.
 *
 * Läuft nur auf kochen.php und bleibt deshalb bewusst außerhalb von app.js -
 * die Küchenansicht soll nicht die Wochenplan-, Einkaufslisten- und
 * Admin-Logik aller anderen Seiten mitladen.
 *
 * Bedienkonzept abgeleitet vom MorphCook-Kochmodus (MIT), siehe
 * THIRD_PARTY_NOTICES.md.
 */
document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    const format = window.CookFormat;
    const recipeId = Number(document.body.dataset.recipeId || 0);

    const el = (id) => document.getElementById(id);

    const view = {
        loading: el('cook-loading'),
        empty: el('cook-empty'),
        emptyTitle: el('cook-empty-title'),
        emptyText: el('cook-empty-text'),
        emptyEdit: el('cook-empty-edit'),
        main: el('cook-view'),
        done: el('cook-done'),
        doneTitle: el('cook-done-title'),
        doneMeta: el('cook-done-meta'),
        title: el('cook-title'),
        stepCount: el('cook-step-count'),
        ticks: el('cook-ticks'),
        stepNumber: el('cook-step-number'),
        stepText: el('cook-step-text'),
        splitToggle: el('cook-split-toggle'),
        ingredients: el('cook-ingredients'),
        ingredientsTitle: el('cook-ingredients-title'),
        ingredientList: el('cook-ingredient-list'),
        ingredientsToggle: el('cook-ingredients-toggle'),
        servings: el('cook-servings'),
        servingsValue: el('cook-servings-value'),
        timers: el('cook-timers'),
        timerChips: el('cook-timer-chips'),
        timerPanel: el('cook-timer-panel'),
        timerValue: el('cook-timer-value'),
        timerLabel: el('cook-timer-label'),
        timerToggle: el('cook-timer-toggle'),
        timerReset: el('cook-timer-reset'),
        timerClose: el('cook-timer-close'),
        timerFill: el('cook-timer-fill'),
        prev: el('cook-prev'),
        next: el('cook-next'),
        exit: el('cook-exit'),
        exitDialog: el('cook-exit-dialog'),
        exitCancel: el('cook-exit-cancel'),
        exitConfirm: el('cook-exit-confirm'),
        restart: el('cook-restart'),
        flash: el('cook-flash'),
        status: el('cook-status'),
    };

    const state = {
        session: null,
        stepIndex: 0,
        servings: 0,
        baseServings: 0,
        splitOpen: new Set(),
        doneSentences: new Set(),
        showAllIngredients: false,
        finished: false,
    };

    const timer = {
        total: 0,
        remaining: 0,
        label: '',
        running: false,
        finished: false,
        ticker: null,
    };

    let wakeLock = null;
    let saveHandle = null;

    // === Hilfen ==========================================================

    function show(node, visible) {
        if (node) node.classList.toggle('hidden', !visible);
    }

    function status(message) {
        if (!view.status) return;
        view.status.textContent = message || '';
        view.status.classList.toggle('is-visible', Boolean(message));
        if (message) {
            window.setTimeout(() => {
                if (view.status.textContent === message) {
                    view.status.textContent = '';
                    view.status.classList.remove('is-visible');
                }
            }, 4000);
        }
    }

    async function fetchJsonOrThrow(url, options) {
        const response = await fetch(url, options);
        const text = await response.text();

        let data = null;
        if (text !== '') {
            try {
                data = JSON.parse(text);
            } catch {
                throw new Error(`HTTP ${response.status}: Ungültige JSON-Antwort`);
            }
        }

        if (!response.ok) {
            throw new Error(data && data.error ? data.error : `HTTP ${response.status}`);
        }
        if (data && typeof data.error === 'string' && data.error !== '') {
            throw new Error(data.error);
        }

        return data;
    }

    function currentStep() {
        if (!state.session || !state.session.steps.length) return null;
        return state.session.steps[state.stepIndex] || null;
    }

    function scale() {
        if (!state.baseServings || !state.servings) return 1;
        return state.servings / state.baseServings;
    }

    // === Persistenz ======================================================

    function persist(immediate) {
        if (!recipeId || state.finished) return;

        window.clearTimeout(saveHandle);
        const run = () => {
            const payload = {
                recipe_id: recipeId,
                step_index: state.stepIndex,
                servings: state.servings,
                timer_seconds: timer.total || null,
                timer_ends_at: timer.running ? new Date(Date.now() + timer.remaining * 1000).toISOString() : null,
                timer_remaining: timer.total && !timer.running ? timer.remaining : null,
            };

            fetchJsonOrThrow('api/save-cook-progress.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            }).catch((error) => status('Fortschritt nicht gespeichert: ' + error.message));
        };

        if (immediate) run();
        else saveHandle = window.setTimeout(run, 400);
    }

    // === Timer ===========================================================

    function stopTicker() {
        if (timer.ticker !== null) {
            window.clearInterval(timer.ticker);
            timer.ticker = null;
        }
    }

    function openTimer(seconds, label, options) {
        const settings = options || {};
        stopTicker();
        timer.total = seconds;
        timer.remaining = typeof settings.remaining === 'number' ? Math.max(0, settings.remaining) : seconds;
        timer.label = label || '';
        timer.finished = timer.remaining <= 0;
        timer.running = false;

        show(view.timerPanel, true);
        renderTimer();

        if (settings.running && timer.remaining > 0) startTimer();
    }

    function closeTimer(persistChange) {
        stopTicker();
        timer.total = 0;
        timer.remaining = 0;
        timer.label = '';
        timer.running = false;
        timer.finished = false;
        show(view.timerPanel, false);
        renderTimerChips();
        if (persistChange) persist(true);
    }

    function startTimer() {
        if (!timer.total || timer.remaining <= 0 || timer.running) return;
        timer.running = true;
        timer.finished = false;
        timer.ticker = window.setInterval(tick, 1000);
        renderTimer();
        persist(true);
    }

    function pauseTimer() {
        if (!timer.running) return;
        stopTicker();
        timer.running = false;
        renderTimer();
        persist(true);
    }

    function resetTimer() {
        stopTicker();
        timer.remaining = timer.total;
        timer.running = false;
        timer.finished = false;
        renderTimer();
        persist(true);
    }

    function tick() {
        timer.remaining -= 1;
        if (timer.remaining <= 0) {
            timer.remaining = 0;
            timer.running = false;
            timer.finished = true;
            stopTicker();
            onTimerFinished();
        }
        renderTimer();
    }

    function onTimerFinished() {
        status('Timer abgelaufen: ' + timer.label);
        flash();
        if (navigator.vibrate) {
            try { navigator.vibrate([200, 100, 200]); } catch { /* nicht überall erlaubt */ }
        }
        persist(true);
    }

    /** Farbimpuls für alle, die in einer lauten Küche keinen Ton hören. */
    function flash() {
        if (!view.flash) return;
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        view.flash.classList.remove('hidden');
        view.flash.classList.toggle('cook-flash-pulse', !reduced);
        window.setTimeout(() => {
            view.flash.classList.add('hidden');
            view.flash.classList.remove('cook-flash-pulse');
        }, reduced ? 1500 : 2600);
    }

    function renderTimer() {
        if (!timer.total) return;
        view.timerValue.textContent = format.formatDuration(timer.remaining);
        view.timerLabel.textContent = timer.label;
        view.timerToggle.textContent = timer.running ? '❚❚' : '▶';
        view.timerToggle.setAttribute('aria-label', timer.running ? 'Timer anhalten' : 'Timer starten');
        view.timerPanel.classList.toggle('is-done', timer.finished);

        const progress = timer.total > 0 ? 1 - timer.remaining / timer.total : 0;
        view.timerFill.style.width = Math.round(progress * 100) + '%';
    }

    function renderTimerChips() {
        const step = currentStep();
        view.timerChips.replaceChildren();

        const timers = step ? step.timers : [];
        show(view.timers, timers.length > 0 || timer.total > 0);
        if (!timers.length) return;

        timers.forEach((entry) => {
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'cook-chip';
            chip.textContent = entry.label;
            chip.classList.toggle('is-active', timer.total === entry.seconds && timer.label === entry.label);
            chip.addEventListener('click', () => {
                openTimer(entry.seconds, entry.label);
                renderTimerChips();
                persist(true);
            });
            view.timerChips.appendChild(chip);
        });
    }

    // === Rendern =========================================================

    function renderTicks() {
        const total = state.session.steps.length;
        view.ticks.replaceChildren();
        view.ticks.setAttribute('aria-valuemax', String(total));
        view.ticks.setAttribute('aria-valuenow', String(state.stepIndex + 1));

        for (let i = 0; i < total; i++) {
            const tick = document.createElement('span');
            tick.className = 'cook-tick';
            if (i < state.stepIndex) tick.classList.add('is-past');
            if (i === state.stepIndex) tick.classList.add('is-current');
            view.ticks.appendChild(tick);
        }
    }

    function renderStepText(step) {
        view.stepText.replaceChildren();
        const splitOpen = state.splitOpen.has(step.index);

        if (splitOpen && step.sentences.length > 1) {
            const list = document.createElement('ol');
            list.className = 'cook-sentences';
            step.sentences.forEach((sentence, position) => {
                const key = step.index + ':' + position;
                const item = document.createElement('li');
                item.textContent = sentence;
                item.classList.toggle('is-done', state.doneSentences.has(key));
                item.addEventListener('click', () => {
                    if (state.doneSentences.has(key)) state.doneSentences.delete(key);
                    else state.doneSentences.add(key);
                    item.classList.toggle('is-done', state.doneSentences.has(key));
                });
                list.appendChild(item);
            });
            view.stepText.appendChild(list);
        } else {
            const paragraph = document.createElement('p');
            paragraph.textContent = step.text;
            view.stepText.appendChild(paragraph);
        }

        show(view.splitToggle, step.splittable);
        view.splitToggle.textContent = splitOpen ? 'Wieder zusammenfassen' : 'Feiner unterteilen';
        view.splitToggle.setAttribute('aria-pressed', splitOpen ? 'true' : 'false');
    }

    function renderIngredients(step) {
        const all = state.session.ingredients;
        if (!all.length) {
            show(view.ingredients, false);
            return;
        }

        const matched = step.ingredients.map((index) => all[index]).filter(Boolean);
        // Findet die Heuristik nichts, zeigen wir lieber alles als gar nichts.
        const hasMatches = matched.length > 0;
        const showAll = state.showAllIngredients || !hasMatches;
        const list = showAll ? all : matched;

        view.ingredientsTitle.textContent = showAll
            ? 'Alle Zutaten'
            : 'Zutaten für diesen Schritt';

        view.ingredientList.replaceChildren();
        const factor = scale();

        list.forEach((item) => {
            const row = document.createElement('li');
            const result = format.scaleIngredient(item, factor);

            const text = document.createElement('span');
            text.className = 'cook-ingredient-text';
            text.textContent = result.text;
            row.appendChild(text);

            if (factor !== 1 && !result.scaled && item.qty !== null) {
                // Gebündelte Zeile: Menge gilt nur für den ersten Teil.
                const hint = document.createElement('span');
                hint.className = 'cook-ingredient-hint';
                hint.textContent = 'nicht skaliert';
                row.appendChild(hint);
            }

            view.ingredientList.appendChild(row);
        });

        show(view.ingredients, true);
        show(view.ingredientsToggle, hasMatches);
        view.ingredientsToggle.textContent = state.showAllIngredients
            ? 'Nur Zutaten dieses Schritts'
            : 'Alle Zutaten anzeigen';
        view.ingredientsToggle.setAttribute('aria-pressed', state.showAllIngredients ? 'true' : 'false');
    }

    function render() {
        const step = currentStep();
        if (!step) return;

        const total = state.session.steps.length;
        view.stepCount.textContent = `Schritt ${state.stepIndex + 1} von ${total}`;
        view.stepNumber.textContent = String(state.stepIndex + 1).padStart(2, '0');

        renderTicks();
        renderStepText(step);
        renderIngredients(step);
        renderTimerChips();

        view.prev.disabled = state.stepIndex === 0;
        view.next.textContent = state.stepIndex === total - 1 ? 'Fertig' : 'Weiter';
        view.servingsValue.textContent = String(state.servings);
        show(view.servings, state.session.scalable);

        view.main.scrollTop = 0;
        if (view.main.querySelector('.cook-body')) {
            view.main.querySelector('.cook-body').scrollTop = 0;
        }
    }

    // === Navigation ======================================================

    function goTo(index) {
        const total = state.session.steps.length;
        if (index < 0) return;
        if (index >= total) {
            finish();
            return;
        }

        state.stepIndex = index;
        state.showAllIngredients = false;
        closeTimer(false);
        render();
        persist(true);
    }

    async function finish() {
        state.finished = true;
        stopTicker();
        releaseWakeLock();

        show(view.main, false);
        show(view.done, true);
        view.doneTitle.textContent = state.session.recipe.title;
        view.doneMeta.textContent = state.session.scalable
            ? `${state.servings} Portionen · ${state.session.steps.length} Schritte`
            : `${state.session.steps.length} Schritte`;

        try {
            await fetchJsonOrThrow('api/finish-cook-session.php', { method: 'POST' });
        } catch (error) {
            status('Session nicht abgeschlossen: ' + error.message);
        }
    }

    function changeServings(delta) {
        if (!state.session.scalable) return;
        const next = Math.min(24, Math.max(1, state.servings + delta));
        if (next === state.servings) return;

        state.servings = next;
        view.servingsValue.textContent = String(next);
        renderIngredients(currentStep());
        persist(false);
    }

    // === Wake Lock =======================================================

    async function requestWakeLock() {
        if (!('wakeLock' in navigator)) return;
        try {
            wakeLock = await navigator.wakeLock.request('screen');
        } catch {
            // Browser darf das ablehnen, der Kochmodus funktioniert trotzdem.
        }
    }

    function releaseWakeLock() {
        if (!wakeLock) return;
        try { wakeLock.release(); } catch { /* egal */ }
        wakeLock = null;
    }

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && !state.finished && state.session) {
            requestWakeLock();
        }
    });

    // === Start ===========================================================

    function showEmpty(title, text, allowEdit) {
        show(view.loading, false);
        show(view.main, false);
        show(view.empty, true);
        view.emptyTitle.textContent = title;
        view.emptyText.textContent = text;
        show(view.emptyEdit, Boolean(allowEdit));
    }

    async function bootstrap() {
        if (!recipeId) {
            showEmpty('Kein Rezept gewählt', 'Öffne den Kochmodus aus einem Rezept heraus.', false);
            return;
        }

        let session;
        try {
            session = await fetchJsonOrThrow('api/get-cook-session.php?id=' + encodeURIComponent(recipeId));
        } catch (error) {
            showEmpty('Rezept konnte nicht geladen werden', error.message, false);
            return;
        }

        state.session = session;

        if (!session.steps.length) {
            showEmpty(
                'Dieses Rezept hat keine Zubereitungsschritte',
                `„${session.recipe.title}" enthält keine Schritte, die sich Schritt für Schritt abarbeiten lassen. Ergänze die Zubereitung, dann funktioniert der Kochmodus.`,
                true
            );
            return;
        }

        state.baseServings = session.recipe.portionen;
        state.servings = session.recipe.portionen || 0;

        const progress = session.progress;
        if (progress) {
            state.stepIndex = Math.min(Math.max(0, progress.step_index), session.steps.length - 1);
            if (progress.servings > 0) state.servings = progress.servings;
        }

        view.title.textContent = session.recipe.title;
        document.title = session.recipe.title + ' - Kochmodus';

        show(view.loading, false);
        show(view.main, true);
        render();

        // Timer aus dem gespeicherten Stand wiederherstellen.
        if (progress && progress.timer_seconds) {
            const label = findTimerLabel(progress.timer_seconds);
            if (progress.timer_ends_at) {
                // Der Server liefert ISO-8601 in UTC, Date parst das eindeutig.
                const endsAt = new Date(progress.timer_ends_at).getTime();
                const remaining = Math.max(0, Math.round((endsAt - Date.now()) / 1000));
                openTimer(progress.timer_seconds, label, { remaining, running: remaining > 0 });
                if (remaining <= 0) {
                    timer.finished = true;
                    renderTimer();
                }
            } else if (progress.timer_remaining !== null) {
                openTimer(progress.timer_seconds, label, { remaining: progress.timer_remaining });
            }
            renderTimerChips();
        }

        requestWakeLock();
        persist(true);
    }

    function findTimerLabel(seconds) {
        const step = currentStep();
        if (!step) return '';
        const match = step.timers.find((entry) => entry.seconds === seconds);
        return match ? match.label : format.formatDuration(seconds);
    }

    // === Ereignisse ======================================================

    view.prev.addEventListener('click', () => goTo(state.stepIndex - 1));
    view.next.addEventListener('click', () => goTo(state.stepIndex + 1));

    el('cook-servings-minus').addEventListener('click', () => changeServings(-1));
    el('cook-servings-plus').addEventListener('click', () => changeServings(1));

    view.splitToggle.addEventListener('click', () => {
        const step = currentStep();
        if (!step) return;
        if (state.splitOpen.has(step.index)) state.splitOpen.delete(step.index);
        else state.splitOpen.add(step.index);
        renderStepText(step);
    });

    view.ingredientsToggle.addEventListener('click', () => {
        state.showAllIngredients = !state.showAllIngredients;
        renderIngredients(currentStep());
    });

    view.timerToggle.addEventListener('click', () => (timer.running ? pauseTimer() : startTimer()));
    view.timerReset.addEventListener('click', resetTimer);
    view.timerClose.addEventListener('click', () => closeTimer(true));

    view.exit.addEventListener('click', openExitDialog);
    view.exitCancel.addEventListener('click', closeExitDialog);
    view.exitConfirm.addEventListener('click', () => {
        persist(true);
        releaseWakeLock();
        window.location.href = 'rezepte.php';
    });

    view.restart.addEventListener('click', () => {
        state.finished = false;
        state.stepIndex = 0;
        state.doneSentences.clear();
        show(view.done, false);
        show(view.main, true);
        render();
        requestWakeLock();
        persist(true);
    });

    let lastFocused = null;

    function openExitDialog() {
        lastFocused = document.activeElement;
        show(view.exitDialog, true);
        view.exitCancel.focus();
    }

    function closeExitDialog() {
        show(view.exitDialog, false);
        if (lastFocused && lastFocused.focus) lastFocused.focus();
    }

    document.addEventListener('keydown', (event) => {
        if (state.finished || !state.session) return;

        const dialogOpen = !view.exitDialog.classList.contains('hidden');
        if (event.key === 'Escape') {
            event.preventDefault();
            if (dialogOpen) closeExitDialog();
            else openExitDialog();
            return;
        }
        if (dialogOpen) return;

        const tag = (event.target.tagName || '').toLowerCase();
        if (tag === 'input' || tag === 'textarea' || tag === 'select') return;

        if (event.key === 'ArrowRight') {
            event.preventDefault();
            goTo(state.stepIndex + 1);
        } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            goTo(state.stepIndex - 1);
        } else if (event.key === ' ' && timer.total) {
            event.preventDefault();
            if (timer.running) pauseTimer();
            else startTimer();
        }
    });

    bootstrap();
});
