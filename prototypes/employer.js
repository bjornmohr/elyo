// ELYO employer prototype — static demo, no backend.

/* ---------- Navigation ---------- */
function goPage(name) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  const target = document.getElementById('page-' + name);
  if (target) target.classList.add('active');
  document.querySelectorAll('#side-nav a').forEach(a => {
    const active = a.dataset.page === name;
    a.classList.toggle('bg-teal-50', active);
    a.classList.toggle('text-teal-700', active);
    a.classList.toggle('text-gray-600', !active);
  });
  document.querySelector('main').scrollTo(0, 0);
}

document.addEventListener('click', e => {
  const link = e.target.closest('a[data-page]');
  if (link) {
    e.preventDefault();
    goPage(link.dataset.page);
  }
});

goPage(window.location.hash.replace('#', '') || 'dashboard');

/* ---------- Usage funnel (port of CompanyUsageFunnelComponent) ---------- */
const FUNNEL = {
  cohort: '2026-06',
  stages: [
    { key: 'registered', label: 'Registriert', count: 210, rate: 100 },
    { key: 'context_entered', label: 'Kontext eingegeben', count: 155, rate: 74 },
    { key: 'recommendation_received', label: 'Empfehlung erhalten', count: 128, rate: 61 },
    { key: 'measure_started', label: 'Maßnahme gestartet', count: 88, rate: 42 },
    { key: 'active_14d', label: 'Nach 14 Tagen aktiv', count: 59, rate: 28 },
  ],
  categoryInsight: 'Höchste Aktivierung bei Sport (~52 %), niedrigste bei Ernährung (~18 %).',
  categories: [
    { key: 'sport', label: 'Sport', recommendationReceived: 41, measureStarted: 29, active14d: 22 },
    { key: 'workshop', label: 'Workshop', recommendationReceived: 30, measureStarted: 20, active14d: 16 },
    { key: 'mental', label: 'Mental', recommendationReceived: 33, measureStarted: 23, active14d: 17 },
    { key: 'nutrition', label: 'Ernährung', recommendationReceived: 24, measureStarted: 16, active14d: 4 },
  ],
};
const STAGE_COLORS = ['#0f766e', '#2c8272', '#5c8f6c', '#a08a4e', '#8a6a3a'];
let funnelCategory = null;

function pyramidWidth(i, total) {
  if (total <= 1) return 100;
  return Math.round(100 - i * (59 / (total - 1)));
}
function isCategoryStage(key) {
  return key === 'recommendation_received' || key === 'measure_started' || key === 'active_14d';
}
function categoryCountFor(stageKey, category) {
  switch (stageKey) {
    case 'recommendation_received': return category.recommendationReceived;
    case 'measure_started': return category.measureStarted;
    default: return category.active14d;
  }
}
function categoryRateLabel(stageKey, category) {
  if (stageKey === 'recommendation_received' || category.recommendationReceived <= 0) return 'Basis 100%';
  return `${Math.round(categoryCountFor(stageKey, category) / category.recommendationReceived * 100)}%`;
}
function categoryShareFor(stage, category) {
  if (stage.count <= 0) return 0;
  return Math.min(100, Math.round(categoryCountFor(stage.key, category) / stage.count * 100));
}

function renderFunnel() {
  const el = document.getElementById('funnel-main');
  if (!el) return;
  const category = funnelCategory ? FUNNEL.categories.find(c => c.key === funnelCategory) : null;

  const tabs = `<div class="flex items-center justify-between flex-wrap gap-2">
    <div class="flex gap-1.5">
      <button type="button" data-funnel-cat=""
              class="text-[11px] font-semibold rounded-full"
              style="padding: 6px 12px; background: ${funnelCategory === null ? '#13201b' : '#f1ede3'}; color: ${funnelCategory === null ? '#ffffff' : '#6f7d76'}">Alle</button>
      ${FUNNEL.categories.map(c => `<button type="button" data-funnel-cat="${c.key}"
              class="text-[11px] font-semibold rounded-full"
              style="padding: 6px 12px; background: ${funnelCategory === c.key ? '#13201b' : '#f1ede3'}; color: ${funnelCategory === c.key ? '#ffffff' : '#6f7d76'}">${c.label}</button>`).join('')}
    </div>
    <span class="text-[10px] italic" style="color: #9aa39c">
      ${category ? 'Basis: Empfehlungen der Kategorie' : 'Kategorie wählen für Aufschlüsselung'}
    </span>
  </div>`;

  const total = FUNNEL.stages.length;
  const stages = FUNNEL.stages.map((stage, i) => {
    const first = i === 0;
    const last = i === total - 1;
    const width = pyramidWidth(i, total);
    if (category) {
      if (isCategoryStage(stage.key)) {
        return `<div class="text-center relative overflow-hidden"
             style="width: ${width}%; border-radius: ${last ? '0 0 8px 8px' : '0'}; background: #ecfaf7; border: 1px solid #d5eee7; padding: 12px 14px 18px">
          <div class="text-sm font-semibold" style="color: #0f766e">${stage.label}</div>
          <div class="text-[11px] mt-0.5" style="color: #0f766e">
            ${category.label}: ${categoryCountFor(stage.key, category)} · ${categoryRateLabel(stage.key, category)}
          </div>
          <div class="absolute bottom-0 left-0 right-0 flex justify-center">
            <div class="h-1.5 rounded-t" style="width: ${categoryShareFor(stage, category)}%; background: ${STAGE_COLORS[i]}"></div>
          </div>
        </div>`;
      }
      return `<div class="text-center"
           style="width: ${width}%; border-radius: ${first ? '8px 8px 0 0' : '0'}; background: #faf8f3; border: 1px dashed #e5ded3; padding: ${first ? '15px' : '12px 14px'}">
        <div class="text-sm font-semibold" style="color: #9aa39c">${stage.label}</div>
        <div class="text-[11px] mt-0.5" style="color: #9aa39c">${stage.count} · unternehmensweit</div>
      </div>`;
    }
    return `<div class="text-white text-center"
         style="width: ${width}%; background: ${STAGE_COLORS[i]}; border-radius: ${first ? '8px 8px 0 0' : last ? '0 0 8px 8px' : '0'}; padding: ${first ? '16px' : '14px'}">
      <div class="text-sm font-semibold">${stage.label}</div>
      <div class="text-[11px] mt-0.5" style="opacity: .85">${stage.count}${first ? ' Mitarbeitende' : ''} · ${stage.rate}%</div>
    </div>`;
  }).join('');

  const footer = category
    ? `<div class="flex justify-center gap-4 text-[10px]" style="color: #9aa39c">
        <span><span class="inline-block w-2.5 h-2.5 rounded-sm align-middle mr-1" style="background: #0f766e"></span>${category.label}-Anteil</span>
        <span><span class="inline-block w-2.5 h-2.5 rounded-sm align-middle mr-1" style="background: #ecfaf7; border: 1px solid #d5eee7"></span>Alle Kategorien</span>
        <span><span class="inline-block w-2.5 h-2.5 rounded-sm align-middle mr-1" style="border: 1px dashed #b7bdb4"></span>kategorielos</span>
      </div>`
    : `<p class="text-[11px] text-center" style="color: #9aa39c">${FUNNEL.categoryInsight}</p>`;

  el.innerHTML = `${tabs}<div class="flex flex-col items-center" style="gap: 4px">${stages}</div>${footer}`;
}

document.addEventListener('click', e => {
  const tab = e.target.closest('[data-funnel-cat]');
  if (tab) {
    funnelCategory = tab.dataset.funnelCat || null;
    renderFunnel();
  }
});

renderFunnel();

/* ---------- Infection radar 7d bars (counts from demo JSON) ---------- */
(function renderRadarBars() {
  const el = document.getElementById('radar-bars');
  if (!el) return;
  const counts = [7, 8, 11, 12, 16, 18, 20];
  const max = Math.max(...counts, 1);
  const today = new Date();
  el.innerHTML = counts.map((count, i) => {
    const date = new Date(today);
    date.setDate(date.getDate() - (counts.length - 1 - i));
    const label = new Intl.DateTimeFormat('de-DE', { weekday: 'short' }).format(date);
    const height = Math.max(8, Math.round((count / max) * 100));
    return `<div class="flex min-w-16 flex-1 flex-col items-center gap-2">
      <div class="flex h-40 w-full items-end">
        <div class="w-full rounded-t-xl" style="background: #d9a441; height: ${height}%"></div>
      </div>
      <span class="text-sm font-semibold text-gray-900">${count}</span>
      <span class="text-xs" style="color: #5f6f67">${label}</span>
    </div>`;
  }).join('');
})();

/* ---------- Teams form + invitations (in-memory demo) ---------- */
document.addEventListener('click', e => {
  if (e.target.closest('#team-form-toggle')) {
    const form = document.getElementById('team-form');
    const hidden = form.classList.toggle('hidden');
    document.getElementById('team-form-toggle').textContent = hidden ? 'Team hinzufügen' : 'Schließen';
    return;
  }
  if (e.target.closest('#team-save')) {
    const name = document.getElementById('team-name').value.trim();
    if (name.length < 2) return;
    const color = document.getElementById('team-color').value;
    const description = document.getElementById('team-description').value.trim();
    const manager = document.getElementById('team-manager').value;
    const card = document.createElement('div');
    card.className = 'bg-white rounded-xl border border-gray-200 p-5';
    card.innerHTML = `<div class="flex items-center gap-3">
        <span class="w-3 h-3 rounded-full" style="background: ${color}"></span>
        <h2 class="font-semibold text-gray-900"></h2>
      </div>
      <p class="text-sm text-gray-500 mt-2"></p>
      <div class="grid grid-cols-2 gap-3 mt-4 text-sm">
        <div class="rounded-lg bg-stone-50 p-3"><div class="text-xs text-gray-400">Mitglieder</div><div class="font-semibold">0</div></div>
        <div class="rounded-lg bg-stone-50 p-3"><div class="text-xs text-gray-400">Manager</div><div class="font-semibold truncate"></div></div>
      </div>`;
    card.querySelector('h2').textContent = name;
    card.querySelector('p').textContent = description || 'Kein Beschreibungstext';
    card.querySelectorAll('.font-semibold.truncate')[0].textContent = manager || '—';
    document.getElementById('teams-grid').prepend(card);
    document.getElementById('team-form').classList.add('hidden');
    document.getElementById('team-form-toggle').textContent = 'Team hinzufügen';
    document.getElementById('team-name').value = '';
    document.getElementById('team-description').value = '';
    return;
  }
  if (e.target.closest('#invite-submit')) {
    const emailInput = document.getElementById('invite-email');
    const email = emailInput.value.trim();
    if (!email || !email.includes('@')) return;
    const role = document.getElementById('invite-role').value;
    const expires = new Date();
    expires.setDate(expires.getDate() + 7);
    const expiresText = new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' }).format(expires).replace(' ', ' ');
    const row = document.createElement('tr');
    row.className = 'border-b border-gray-50 hover:bg-gray-50/50 transition-colors';
    row.innerHTML = `<td class="px-4 py-3 text-gray-900"></td>
      <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-teal-50 text-teal-700">${role}</span></td>
      <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-50 text-yellow-700">pending</span></td>
      <td class="px-4 py-3 text-gray-500 text-xs">${expiresText}</td>
      <td class="px-4 py-3"><button class="text-xs text-red-500 hover:underline">Widerrufen</button></td>`;
    row.querySelector('td').textContent = email;
    document.getElementById('invite-table-body').prepend(row);
    document.getElementById('invite-success').classList.remove('hidden');
    emailInput.value = '';
  }
});

/* ---------- Measures list (port of CompanyMeasuresComponent, demo seeder data) ---------- */
const MEASURES = [
  {
    id: 1, title: 'Fokuszeit am Vormittag', category: 'Mental', status: 'ACTIVE', statusLabel: 'Aktiv',
    derived: 'RUNNING', derivedLabel: 'Läuft', team: 'Alle Teams', participation: '67% Teilnahmequote',
    delivery: '-', execution: '-', schedule: null, location: null, executionInfo: null,
    impact: 'none',
  },
  {
    id: 2, title: 'Rückenfit Bürostuhl-Workshop', category: 'Flexibilität', status: 'ACTIVE', statusLabel: 'Aktiv',
    derived: 'UPCOMING', derivedLabel: 'Bevorstehend', team: 'Alle Teams', participation: '50% Teilnahmequote',
    delivery: 'Vor Ort', execution: 'Event-Teilnahme',
    schedule: '11.7.2026, 14:00:00 - 11.7.2026, 15:30:00', location: 'Kantine EG',
    executionInfo: '32 angemeldet / 80 Plätze', checkin: 'QR-Check-in aktiv — erstellt am 01.07.2026', qr: true,
    impact: 'upcoming',
  },
  {
    id: 3, title: 'Achtsamkeits-Session', category: 'Mental', status: 'ACTIVE', statusLabel: 'Aktiv',
    derived: 'UPCOMING', derivedLabel: 'Bevorstehend', team: 'Alle Teams', participation: '33% Teilnahmequote',
    delivery: 'Remote', execution: 'Geführte Session',
    schedule: '13.7.2026, 09:00:00 - 13.7.2026, 09:45:00', location: null,
    executionInfo: '12 angemeldet', checkin: 'Kein Check-in erforderlich',
    impact: 'upcoming',
  },
  {
    id: 4, title: 'Lauftreff Mittwochs', category: 'Sport', status: 'ACTIVE', statusLabel: 'Aktiv',
    derived: 'RUNNING', derivedLabel: 'Läuft', team: 'Alle Teams', participation: '67% Teilnahmequote',
    delivery: 'Vor Ort', execution: 'Challenge',
    schedule: '16.6.2026, 18:00:00', location: null,
    executionInfo: null, checkin: 'Kein Check-in erforderlich',
    impact: 'none',
  },
  {
    id: 5, title: 'Yoga am Morgen', category: 'Sport', status: 'ACTIVE', statusLabel: 'Aktiv',
    derived: 'RUNNING', derivedLabel: 'Läuft', team: 'Alle Teams', participation: '50% Teilnahmequote',
    delivery: 'Hybrid', execution: 'Geführte Session',
    schedule: '23.6.2026, 07:30:00', location: null,
    executionInfo: null, checkin: 'Kein Check-in erforderlich',
    impact: 'none',
  },
  {
    id: 6, title: 'Vitamin-Info-Stand', category: 'Ernährung', status: 'COMPLETED', statusLabel: 'Abgeschlossen',
    derived: 'COMPLETED', derivedLabel: 'Abgeschlossen', team: 'Alle Teams', participation: '83% Teilnahmequote',
    delivery: 'Vor Ort', execution: 'Event-Teilnahme',
    schedule: '23.6.2026, 11:00:00 - 23.6.2026, 15:00:00', location: 'Foyer Haupteingang',
    executionInfo: null, checkin: 'Kein Check-in erforderlich',
    impact: 'completed',
  },
  {
    id: 7, title: 'Ernährungs-Webinar', category: 'Ernährung', status: 'SUGGESTED', statusLabel: 'Vorgeschlagen',
    derived: 'PLANNED', derivedLabel: 'Geplant', team: 'Alle Teams', participation: null,
    delivery: 'Remote', execution: 'Information', schedule: null, location: null,
    executionInfo: null, checkin: 'Kein Check-in erforderlich',
    impact: 'none',
  },
];

let expandedMeasureId = null;

function measureImpactBlock(m) {
  const conceptBadge = '<span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase" style="background: #fdf3e3; color: #9a6b1f; letter-spacing: .04em">Konzept</span>';
  let body;
  if (m.impact === 'completed') {
    body = `<button type="button" data-impact-open
            class="text-[11px] font-semibold text-gray-700 hover:bg-gray-50"
            style="border: 1px solid #e5ded3; border-radius: 8px; padding: 5px 12px">
      Wirkungsanalyse anzeigen
    </button>`;
  } else if (m.impact === 'upcoming') {
    body = '<div class="text-xs italic" style="color: #9aa39c">Termin steht noch bevor — Wirkung wird nach Abschluss ermittelt.</div>';
  } else {
    body = '<div class="text-xs italic" style="color: #9aa39c">Noch keine Daten</div>';
  }
  return `<div class="space-y-2">
    <div class="flex items-center gap-2">
      <span class="text-[11px] uppercase font-semibold" style="color: #6f7d76; letter-spacing: .04em">Wirkung</span>
      ${conceptBadge}
    </div>
    ${body}
  </div>`;
}

function renderMeasures() {
  const el = document.getElementById('measure-list');
  if (!el) return;
  el.innerHTML = MEASURES.map(m => {
    const expanded = expandedMeasureId === m.id;
    const active = m.status === 'ACTIVE';
    const running = m.derived === 'RUNNING';
    let details = '';
    if (expanded) {
      details = `<div class="pb-5 pt-4" style="border-top: 1px solid #f1ede3; padding-left: 22px; padding-right: 22px">
        <div class="flex justify-end mb-3">
          ${m.status === 'COMPLETED' ? '<span class="text-[11px]" style="color: #9aa39c">Nicht bearbeitbar</span>'
            : `<button type="button" class="text-[11px] font-semibold text-gray-700 hover:bg-gray-50" style="border: 1px solid #e5ded3; border-radius: 8px; padding: 5px 12px">Bearbeiten</button>`}
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
          <div class="space-y-2">
            <div class="text-[11px] uppercase font-semibold" style="color: #6f7d76; letter-spacing: .04em">Durchführung</div>
            <div class="text-sm text-gray-900">${m.delivery} · ${m.execution}</div>
            ${m.schedule ? `<div class="text-xs" style="color: #6f7d76">${m.schedule}</div>` : ''}
            ${m.location ? `<div class="text-xs" style="color: #6f7d76">${m.location}</div>` : ''}
            ${m.executionInfo ? `<div class="text-xs" style="color: #6f7d76">${m.executionInfo}</div>` : ''}
            ${m.checkin ? (m.checkin.startsWith('QR')
              ? `<div class="text-xs font-semibold" style="color: #0f766e">${m.checkin}</div>`
              : `<div class="text-xs" style="color: #9aa39c">${m.checkin}</div>`) : ''}
            ${m.qr ? `<div class="pt-1">
              <button type="button" class="rounded-lg bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-700">Link erstellen</button>
            </div>` : ''}
          </div>
          ${measureImpactBlock(m)}
        </div>
      </div>`;
    }
    return `<div class="bg-white overflow-hidden transition-colors" style="border-radius: 14px; border: 1px solid ${expanded ? '#0f766e' : '#ece6d8'}">
      <button type="button" data-measure-toggle="${m.id}" class="w-full flex items-center gap-3 text-left" style="padding: 16px 22px">
        <div class="min-w-0 flex-1 flex items-center gap-3 flex-wrap">
          <span class="text-sm font-medium text-gray-900">${m.title}</span>
          <span class="text-[11px]" style="color: #6f7d76">${m.category}</span>
          <span class="text-[11px]" style="color: #9aa39c">${m.team}</span>
          <span class="text-[11px] font-semibold rounded-full" style="padding: 3px 9px; background: ${active ? '#ecfaf7' : '#f1ede3'}; color: ${active ? '#0f766e' : '#6f7d76'}">${m.statusLabel}</span>
          <span class="text-[11px] font-semibold rounded-full" style="padding: 3px 9px; background: ${running ? '#ecfaf7' : '#f1ede3'}; color: ${running ? '#0f766e' : '#6f7d76'}">${m.derivedLabel}</span>
        </div>
        <div class="flex items-center gap-3 flex-shrink-0">
          ${m.participation
            ? `<span class="text-xs font-semibold" style="color: #0f766e">${m.participation}</span>`
            : '<span class="text-xs italic" style="color: #6f7d76">geschützt</span>'}
          <span class="text-gray-400 text-xs">${expanded ? '▾' : '▸'}</span>
        </div>
      </button>
      ${details}
    </div>`;
  }).join('');
}

document.addEventListener('click', e => {
  const toggle = e.target.closest('[data-measure-toggle]');
  if (toggle) {
    const id = Number(toggle.dataset.measureToggle);
    expandedMeasureId = expandedMeasureId === id ? null : id;
    renderMeasures();
    return;
  }
  if (e.target.closest('#measure-form-toggle')) {
    const form = document.getElementById('measure-form');
    const hidden = form.classList.toggle('hidden');
    document.getElementById('measure-form-toggle').textContent = hidden ? 'Maßnahme hinzufügen' : 'Schließen';
    return;
  }
  if (e.target.closest('[data-impact-open]')) {
    const dialog = document.getElementById('impact-dialog');
    dialog.classList.remove('hidden');
    dialog.classList.add('flex');
    return;
  }
  const dialog = document.getElementById('impact-dialog');
  if (dialog && !dialog.classList.contains('hidden')) {
    if (e.target === dialog || e.target.closest('#impact-dialog-close')) {
      dialog.classList.add('hidden');
      dialog.classList.remove('flex');
    }
  }
});

renderMeasures();
