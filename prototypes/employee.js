// ELYO employee prototype — static demo, no backend.
// Medallion rendering + badge data are ported 1:1 from
// badge-medallion.component.ts / employee-badges-demo.service.ts.

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

/* ---------- Date header (Angular: today | date:'EEEE, d. MMMM') ---------- */
document.querySelectorAll('[data-today]').forEach(el => {
  el.textContent = new Intl.DateTimeFormat('de-DE', { weekday: 'long', day: 'numeric', month: 'long' })
    .format(new Date()).replace(',', ',');
});

/* ---------- Badge medallion (port of BadgeMedallionComponent) ---------- */
const TONES = {
  teal: { base: '#14b8a6', dark: '#0f766e', light: '#5eead4', glow: 'rgba(20,184,166,.45)' },
  blue: { base: '#3b82f6', dark: '#1d4ed8', light: '#93c5fd', glow: 'rgba(59,130,246,.40)' },
  amber: { base: '#f59e0b', dark: '#b45309', light: '#fcd34d', glow: 'rgba(245,158,11,.40)' },
  violet: { base: '#8b5cf6', dark: '#6d28d9', light: '#c4b5fd', glow: 'rgba(139,92,246,.40)' },
  slate: { base: '#64748b', dark: '#475569', light: '#cbd5e1', glow: 'rgba(100,116,139,.35)' },
};

const GLYPHS = {
  check: '<polyline points="20 6 9 17 4 12"></polyline>',
  checkcircle: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>',
  flask: '<path d="M9 3h6M10 3v6l-4.6 8.5A2 2 0 0 0 7.2 21h9.6a2 2 0 0 0 1.8-3.5L14 9V3"></path>',
  compass: '<circle cx="12" cy="12" r="10"></circle><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88"></polygon>',
  moon: '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>',
  wind: '<path d="M9.59 4.59A2 2 0 1 1 11 8H2m10.59 11.41A2 2 0 1 0 14 16H2m15.73-8.27A2.5 2.5 0 1 1 19.5 12H2"></path>',
  sun: '<circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"></path>',
  droplet: '<path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path>',
  activity: '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>',
  target: '<circle cx="12" cy="12" r="10"></circle><circle cx="12" cy="12" r="6"></circle><circle cx="12" cy="12" r="2"></circle>',
  shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>',
  pause: '<rect x="6" y="4" width="4" height="16" rx="1"></rect><rect x="14" y="4" width="4" height="16" rx="1"></rect>',
  refresh: '<polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>',
};

function medallionHtml(tone, iconKey, status, progressPercent, size) {
  const t = TONES[tone];
  const pct = Math.max(0, Math.min(100, progressPercent));
  const innerSize = Math.round(size * 0.87);
  const medalSize = Math.round(size * 0.73);
  const glyphSize = Math.max(18, Math.round(size * 0.36));
  const sealSize = Math.max(20, Math.round(size * 0.33));

  let ring;
  if (status === 'LOCKED') ring = '#e5e7eb';
  else if (status === 'EARNED') ring = `conic-gradient(from -90deg, ${t.light}, ${t.base}, ${t.dark}, ${t.base}, ${t.light})`;
  else ring = `conic-gradient(${t.base} 0% ${pct}%, #e5e7eb ${pct}% 100%)`;

  const medalBg = status === 'LOCKED'
    ? 'radial-gradient(circle at 34% 26%, #f8fafc 0%, #cbd5e1 55%, #94a3b8 100%)'
    : `radial-gradient(circle at 34% 26%, ${t.light} 0%, ${t.base} 48%, ${t.dark} 100%)`;

  const medalShadow = status === 'EARNED'
    ? `0 10px 24px ${t.glow}, inset 0 2px 3px rgba(255,255,255,.5)`
    : status === 'LOCKED'
      ? 'inset 0 2px 3px rgba(255,255,255,.6)'
      : '0 6px 16px rgba(15,23,42,.1), inset 0 2px 3px rgba(255,255,255,.45)';

  let seal = '';
  if (status === 'EARNED') {
    seal = `<span class="absolute bottom-0 right-0 grid rounded-full border-2 border-white bg-teal-700 text-white" style="width:${sealSize}px;height:${sealSize}px" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.6" stroke-linecap="round" stroke-linejoin="round" class="m-auto h-3 w-3"><polyline points="20 6 9 17 4 12"></polyline></svg></span>`;
  } else if (status === 'LOCKED') {
    seal = `<span class="absolute bottom-0 right-0 grid rounded-full border-2 border-white bg-slate-400 text-white" style="width:${sealSize}px;height:${sealSize}px" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" class="m-auto h-3 w-3"><rect x="4" y="11" width="16" height="10" rx="2"></rect><path d="M8 11V7a4 4 0 0 1 8 0v4"></path></svg></span>`;
  }

  return `<span class="relative grid shrink-0 place-items-center rounded-full" style="width:${size}px;height:${size}px;background:${ring}">
    <span class="grid place-items-center rounded-full" style="width:${innerSize}px;height:${innerSize}px;background:${status === 'EARNED' ? '#f0fdfa' : '#ffffff'}">
      <span class="grid place-items-center rounded-full" style="width:${medalSize}px;height:${medalSize}px;background:${medalBg};box-shadow:${medalShadow}">
        <svg viewBox="0 0 24 24" fill="none" width="${glyphSize}" height="${glyphSize}"
             stroke="${status === 'LOCKED' ? '#94a3b8' : '#ffffff'}"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
             style="filter:${status === 'LOCKED' ? 'none' : 'drop-shadow(0 1px 1px rgba(0,0,0,.25))'}">${GLYPHS[iconKey]}</svg>
      </span>
    </span>
    ${seal}
  </span>`;
}

function renderMedallions(root) {
  (root || document).querySelectorAll('[data-medallion]').forEach(el => {
    el.outerHTML = medallionHtml(el.dataset.tone, el.dataset.icon, el.dataset.status, Number(el.dataset.progress || 0), Number(el.dataset.size || 60));
  });
}
renderMedallions();

/* ---------- Badge data (port of EmployeeBadgesDemoService, streak = 5) ---------- */
const BADGE_CATEGORY_LABELS = {
  STARTER: 'Starter', STREAK: 'Routine', QUEST: 'Quest', INSIGHT: 'Reflexion',
  RECOVERY: 'Erholung', PREVENTION: 'Prävention', LAB: 'Labor',
};

const BADGES = [
  { id: 'baseline-set', title: 'Baseline gesetzt', description: 'Erstes Screening abgeschlossen.', category: 'STARTER', iconKey: 'check', tone: 'teal', status: 'EARNED', progressCurrent: 1, progressTarget: 1, progressPercent: 100, earnedAt: '2026-06-10', benefit: 'Deine Startwerte sind die Basis für passende Empfehlungen.' },
  { id: 'first-checkin', title: 'Erster Check-in', description: 'Den ersten täglichen Check-in abgeschlossen.', category: 'STARTER', iconKey: 'checkcircle', tone: 'teal', status: 'EARNED', progressCurrent: 1, progressTarget: 1, progressPercent: 100, earnedAt: '2026-06-12', benefit: 'Check-ins machen deine Muster über die Zeit sichtbar.' },
  { id: 'first-measure', title: 'Erste Maßnahme', description: 'Die erste empfohlene Maßnahme gestartet.', category: 'STARTER', iconKey: 'target', tone: 'blue', status: 'IN_PROGRESS', progressCurrent: 0, progressTarget: 1, progressPercent: 0, unit: 'Maßnahmen', benefit: 'Eine erste Maßnahme macht aus einer Empfehlung eine konkrete Routine.' },
  { id: 'seven-day-compass', title: '7-Tage-Kompass', description: '7 Tage in Folge eingecheckt.', category: 'STREAK', iconKey: 'compass', tone: 'amber', status: 'IN_PROGRESS', progressCurrent: 5, progressTarget: 7, progressPercent: 71, unit: 'Tage', benefit: 'Regelmäßigkeit macht Veränderungen und Muster sichtbar.' },
  { id: 'sleep-series', title: 'Schlaf-Serie', description: '5 Tage Schlafroutine dokumentiert.', category: 'STREAK', iconKey: 'moon', tone: 'blue', status: 'IN_PROGRESS', progressCurrent: 3, progressTarget: 5, progressPercent: 60, unit: 'Tage', benefit: 'Eine feste Schlafroutine kann Energie und Stimmung stabilisieren.' },
  { id: 'hydration-series', title: 'Hydration-Serie', description: '5 Tage Flüssigkeitsziel erreicht.', category: 'STREAK', iconKey: 'droplet', tone: 'teal', status: 'IN_PROGRESS', progressCurrent: 2, progressTarget: 5, progressPercent: 40, unit: 'Tage', benefit: 'Genug zu trinken unterstützt Konzentration und Energie im Alltag.' },
  { id: 'mobility-starter', title: 'Mobilitätsstarter', description: '3 Mobilitätsübungen abgeschlossen.', category: 'QUEST', iconKey: 'activity', tone: 'violet', status: 'IN_PROGRESS', progressCurrent: 1, progressTarget: 3, progressPercent: 33, unit: 'Übungen', benefit: 'Kurze Bewegungseinheiten lösen Steifheit aus dem Arbeitsalltag.' },
  { id: 'stress-resilience-1', title: 'Stress-Resilienz I', description: '3 Atem- oder Entspannungsübungen abgeschlossen.', category: 'QUEST', iconKey: 'wind', tone: 'teal', status: 'IN_PROGRESS', progressCurrent: 2, progressTarget: 3, progressPercent: 67, unit: 'Übungen', benefit: 'Kurze Übungen helfen dir, in stressigen Phasen bewusst runterzufahren.' },
  { id: 'prevention-cycle', title: 'Präventionszyklus', description: 'Screening abgeschlossen und passende Maßnahmen begonnen.', category: 'PREVENTION', iconKey: 'shield', tone: 'violet', status: 'LOCKED', progressCurrent: 0, progressTarget: 2, progressPercent: 0, unit: 'Schritte', benefit: 'Screening, Check-ins und Maßnahmen greifen hier sichtbar ineinander.' },
  { id: 'smart-pause', title: 'Smart Pause', description: 'Schonmodus genutzt und eine sanfte Alternative gewählt.', category: 'RECOVERY', iconKey: 'pause', tone: 'amber', status: 'LOCKED', progressCurrent: 0, progressTarget: 1, progressPercent: 0, benefit: 'Kluge Pausen helfen, Belastung nicht einfach zu übergehen.' },
  { id: 'back-in-flow', title: 'Zurück im Flow', description: 'Nach einer Pause wieder mit Check-ins oder Maßnahmen eingestiegen.', category: 'RECOVERY', iconKey: 'refresh', tone: 'blue', status: 'LOCKED', progressCurrent: 0, progressTarget: 1, progressPercent: 0, benefit: 'Nach einer Pause wieder einzusteigen zählt mehr als perfekt zu bleiben.' },
  { id: 'marker-understander', title: 'Marker-Versteher', description: 'Laborwerte angesehen und auffällige Marker erklärt bekommen.', category: 'LAB', iconKey: 'flask', tone: 'blue', status: 'EARNED', progressCurrent: 1, progressTarget: 1, progressPercent: 100, earnedAt: '2026-06-20', benefit: 'Du verstehst besser, was hinter deinen Werten steckt.' },
  { id: 'vitamin-d-routine', title: 'Vitamin-D-Routine', description: '3 Tageslicht-Routinen innerhalb von 14 Tagen abgeschlossen.', category: 'LAB', iconKey: 'sun', tone: 'amber', status: 'IN_PROGRESS', progressCurrent: 1, progressTarget: 3, progressPercent: 33, unit: 'Routinen', benefit: 'Tageslicht bewusst einzuplanen macht Versorgung und Tagesrhythmus greifbarer.' },
  { id: 'body-radar', title: 'Körperradar', description: 'Mehrere Check-ins mit Körpersignalen dokumentiert.', category: 'INSIGHT', iconKey: 'target', tone: 'violet', status: 'IN_PROGRESS', progressCurrent: 2, progressTarget: 4, progressPercent: 50, unit: 'Check-ins', benefit: 'Körpersignale früh wahrzunehmen hilft, rechtzeitig gegenzusteuern.' },
];

/* ---------- Badge detail modal (port of BadgeDetailModalComponent) ---------- */
const badgeModal = document.getElementById('badge-modal');

function formatShortDate(date) {
  if (!date) return 'Demo';
  return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit' }).format(new Date(date));
}

function openBadgeModal(id) {
  const badge = BADGES.find(b => b.id === id);
  if (!badge) return;
  document.getElementById('badge-modal-medallion').innerHTML =
    medallionHtml(badge.tone, badge.iconKey, badge.status, badge.progressPercent, 100);
  document.getElementById('badge-modal-category').textContent = BADGE_CATEGORY_LABELS[badge.category];
  document.getElementById('badge-modal-title').textContent = badge.title;
  document.getElementById('badge-modal-description').textContent = badge.description;
  document.getElementById('badge-modal-benefit').textContent = badge.benefit;

  const footer = document.getElementById('badge-modal-footer');
  if (badge.status === 'IN_PROGRESS') {
    footer.innerHTML = `<div class="mt-6 text-left">
      <div class="flex items-center justify-between gap-3 text-sm">
        <span class="font-bold text-slate-700">${badge.progressCurrent}/${badge.progressTarget} ${badge.unit ?? 'geschafft'}</span>
        <span class="text-slate-500">${badge.progressPercent}%</span>
      </div>
      <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuenow="${badge.progressCurrent}" aria-valuemin="0" aria-valuemax="${badge.progressTarget}">
        <div class="h-full rounded-full bg-gradient-to-r from-amber-300 to-amber-500" style="width:${badge.progressPercent}%"></div>
      </div>
    </div>`;
  } else if (badge.status === 'EARNED') {
    footer.innerHTML = `<p class="mt-6 rounded-2xl bg-teal-50 px-4 py-3 text-sm font-semibold text-teal-700">Freigeschaltet am ${formatShortDate(badge.earnedAt)}</p>`;
  } else {
    footer.innerHTML = `<p class="mt-6 rounded-2xl bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-600">Dieses Badge wird über passende Routinen sichtbar, sobald du damit startest.</p>`;
  }

  badgeModal.classList.remove('hidden');
  badgeModal.classList.add('flex');
}

function closeBadgeModal() {
  badgeModal.classList.add('hidden');
  badgeModal.classList.remove('flex');
}

document.addEventListener('click', e => {
  const opener = e.target.closest('[data-open-badge]');
  if (opener) { openBadgeModal(opener.dataset.openBadge); return; }
  if (e.target === badgeModal || e.target.closest('#badge-modal-close')) closeBadgeModal();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeBadgeModal(); });
badgeModal.querySelector('article').addEventListener('click', e => e.stopPropagation());

/* ---------- Generic accordions ([data-toggle] / [data-toggle-body]) ---------- */
document.addEventListener('click', e => {
  const toggle = e.target.closest('[data-toggle]');
  if (!toggle) return;
  const key = toggle.dataset.toggle;
  const body = document.querySelector(`[data-toggle-body="${key}"]`);
  const icon = document.querySelector(`[data-toggle-icon="${key}"]`);
  if (!body) return;
  const isHidden = body.classList.toggle('hidden');
  toggle.setAttribute('aria-expanded', String(!isHidden));
  if (icon) icon.classList.toggle('rotate-180', !isHidden);
});

/* ---------- Check-in stepper (port of CheckinStepperComponent) ---------- */
const LOCATIONS = [
  { value: 'office', label: 'Büro', icon: '🏢' },
  { value: 'home', label: 'Home-Office', icon: '🏠' },
  { value: 'plant', label: 'Werk', icon: '🏭' },
  { value: 'onroad', label: 'Unterwegs', icon: '🚗' },
];
const MOOD_OPTIONS = [
  { value: 1, label: 'Sehr schlecht', emoji: '😫' },
  { value: 2, label: 'Schlecht', emoji: '😟' },
  { value: 3, label: 'Geht so', emoji: '😐' },
  { value: 4, label: 'Gut', emoji: '😊' },
  { value: 5, label: 'Sehr gut', emoji: '🤩' },
];
const FREQUENT_SYMPTOMS = [
  { key: 'neck', label: 'Nackenschmerzen', pain: true, regions: ['Seitlich links', 'Seitlich rechts', 'Nacken-Schulter-Übergang', 'Ansatz Hinterkopf'] },
  { key: 'fatigue', label: 'Müdigkeit', pain: false, regions: ['Allgemein'] },
  { key: 'headache', label: 'Kopfschmerzen', pain: true, regions: ['Stirn', 'Schläfen', 'Hinterkopf', 'Ganzer Kopf'] },
];
const OTHER_SYMPTOMS = [
  { key: 'back', label: 'Rückenschmerzen', pain: true, regions: ['Unterer Rücken', 'Mittlerer Rücken', 'Oberer Rücken'] },
  { key: 'shoulder', label: 'Schulterschmerzen', pain: true, regions: ['Links', 'Rechts', 'Beidseitig', 'Schulterblatt'] },
  { key: 'eyes', label: 'Augenbelastung', pain: false, regions: ['Augen'] },
  { key: 'tension', label: 'Innere Unruhe', pain: false, regions: ['Allgemein'] },
];
const ILLNESS_TYPES = [
  { key: 'cold', label: 'Erkältung', subs: ['Halsschmerzen', 'Schnupfen', 'Husten', 'Fieber', 'Gliederschmerzen'] },
  { key: 'gi', label: 'Magen-Darm', subs: ['Übelkeit', 'Bauchschmerzen', 'Durchfall', 'Appetitlosigkeit'] },
  { key: 'flu', label: 'Grippeartig', subs: ['Fieber', 'Schüttelfrost', 'Gliederschmerzen', 'Abgeschlagenheit'] },
  { key: 'migraine', label: 'Migräne / starke Kopfschmerzen', subs: ['Pochender Kopfschmerz', 'Lichtempfindlichkeit', 'Übelkeit', 'Sehstörungen'] },
  { key: 'allergy', label: 'Allergie', subs: ['Niesen', 'Juckende Augen', 'Hautausschlag', 'Atembeschwerden'] },
];
const CI_STEPS = ['location', 'mood', 'energy', 'stress', 'signals', 'summary'];
const CI_SCALE = [1, 2, 3, 4, 5];

const ci = {
  step: 'location',
  justSaved: false,
  lastPainKey: null,
  draft: emptyDraft(),
};

function emptyDraft() {
  return { location: null, mood: null, energy: null, stress: null, sleepWanted: false, sleepHours: 7, sleepRecovery: null, symptoms: {}, sick: null, illnessType: null, illnessSubs: [], illnessSeverity: null };
}

function ciSleepActive() {
  return ci.draft.sleepWanted || (ci.draft.energy !== null && ci.draft.energy <= 2) || ci.draft.symptoms['fatigue'] !== undefined;
}

function ciStepIndex() { return CI_STEPS.indexOf(ci.step); }

function ciStepValid() {
  const d = ci.draft;
  switch (ci.step) {
    case 'location': return d.location !== null;
    case 'mood': return d.mood !== null;
    case 'energy': return d.energy !== null && (!ciSleepActive() || d.sleepRecovery !== null);
    case 'stress': return d.stress !== null;
    case 'signals': return d.sick !== null && (d.sick === false || d.illnessType === null || d.illnessSubs.length > 0);
    default: return true;
  }
}

function fmtHours(h) { return h.toFixed(1).replace('.', ','); }

function ciSummaryRows() {
  const d = ci.draft;
  const all = [...FREQUENT_SYMPTOMS, ...OTHER_SYMPTOMS];
  const mood = MOOD_OPTIONS.find(m => m.value === d.mood);
  const rows = [
    { label: 'Ort', value: LOCATIONS.find(l => l.value === d.location)?.label ?? '–' },
    { label: 'Stimmung', value: mood ? `${mood.emoji} ${mood.label}` : '–' },
    { label: 'Energie', value: `${d.energy ?? '–'}/5` },
    { label: 'Stress', value: `${d.stress ?? '–'}/5` },
  ];
  if (ciSleepActive()) rows.push({ label: 'Schlaf', value: `${fmtHours(d.sleepHours)} h · Erholung ${d.sleepRecovery ?? '–'}/5` });
  const symptoms = Object.keys(d.symptoms);
  rows.push({ label: 'Körpersignale', value: symptoms.length ? symptoms.map(k => all.find(s => s.key === k)?.label ?? k).join(', ') : 'Keine' });
  rows.push({ label: 'Krankheitsgefühl', value: d.sick ? `Ja${d.illnessType ? ` (${ILLNESS_TYPES.find(t => t.key === d.illnessType)?.label ?? ''}: ${d.illnessSubs.join(', ')})` : ''}` : 'Nein' });
  return rows;
}

function ciScaleButtons(field, extra) {
  return `<div class="grid grid-cols-5 gap-2">` + CI_SCALE.map(v => {
    const sel = ci.draft[field] === v;
    const cls = extra === 'amber'
      ? (sel ? 'border-amber-500 bg-amber-50 text-amber-700' : 'border-slate-200 text-slate-600')
      : extra === 'sleep'
        ? (sel ? 'border-teal-500 bg-teal-50 text-teal-700' : 'border-slate-200 bg-white text-slate-600')
        : (sel ? 'border-teal-500 bg-teal-50 text-teal-700' : 'border-slate-100 text-slate-600 hover:border-teal-200');
    const py = extra ? 'py-2' : 'py-3';
    return `<button type="button" data-ci-set="${field}:${v}" class="rounded-xl border ${py} text-sm font-bold transition-colors ${cls}">${v}</button>`;
  }).join('') + `</div>`;
}

function ciRender() {
  const progress = document.getElementById('ci-progress');
  const body = document.getElementById('ci-body');
  const footer = document.getElementById('ci-footer');
  const d = ci.draft;

  progress.style.display = ci.step === 'done' ? 'none' : '';
  progress.innerHTML = CI_STEPS.map((s, i) =>
    `<div class="h-2 flex-1 rounded-full transition-colors ${i <= ciStepIndex() ? 'bg-teal-500' : 'bg-slate-100'}"></div>`).join('');

  let html = '';
  switch (ci.step) {
    case 'location':
      html = `<div class="flex-1 space-y-5">
        <h2 class="text-lg font-bold text-slate-800">Wo bist du heute?</h2>
        <p class="text-sm text-slate-500">Wir schlagen dir nur Übungen vor, die an deinem heutigen Ort machbar sind.</p>
        <div class="grid grid-cols-2 gap-3">` +
        LOCATIONS.map(l => `<button type="button" data-ci-set="location:${l.value}"
          class="rounded-2xl border px-4 py-5 text-center transition-colors ${d.location === l.value ? 'border-teal-500 bg-teal-50' : 'border-slate-100 hover:border-teal-200'}">
          <div class="text-2xl">${l.icon}</div>
          <div class="text-sm font-semibold text-slate-700 mt-1.5">${l.label}</div>
        </button>`).join('') + `</div></div>`;
      break;
    case 'mood':
      html = `<div class="flex-1 space-y-5">
        <h2 class="text-lg font-bold text-slate-800">Wie ist deine Stimmung?</h2>
        <div class="grid grid-cols-5 gap-2">` +
        MOOD_OPTIONS.map(o => `<button type="button" data-ci-set="mood:${o.value}"
          class="rounded-2xl border px-2 py-4 text-center transition-colors ${d.mood === o.value ? 'border-teal-500 bg-teal-50' : 'border-slate-100 hover:border-teal-200'}">
          <div class="text-2xl">${o.emoji}</div>
          <div class="text-xs text-slate-500 mt-1 leading-tight">${o.label}</div>
        </button>`).join('') + `</div></div>`;
      break;
    case 'energy': {
      let sleepBlock;
      if (ciSleepActive()) {
        sleepBlock = `<div class="rounded-2xl bg-slate-50 border border-slate-100 p-4 space-y-4">
          <p class="text-sm font-semibold text-slate-700">Kurz zum Schlaf</p>
          <div class="flex items-center justify-between gap-4">
            <span class="text-sm text-slate-600">Wie lange hast du geschlafen?</span>
            <div class="flex items-center gap-2">
              <button type="button" data-ci-sleep="-0.5" class="w-11 h-11 rounded-full border border-slate-200 text-slate-600 font-bold hover:bg-white">−</button>
              <span class="text-sm font-bold text-slate-800 w-14 text-center">${fmtHours(d.sleepHours)} h</span>
              <button type="button" data-ci-sleep="0.5" class="w-11 h-11 rounded-full border border-slate-200 text-slate-600 font-bold hover:bg-white">＋</button>
            </div>
          </div>
          <div>
            <p class="text-sm text-slate-600 mb-2">Wie erholt fühlst du dich?</p>
            ${ciScaleButtons('sleepRecovery', 'sleep')}
          </div>
        </div>`;
      } else {
        sleepBlock = `<button type="button" data-ci-set="sleepWanted:true" class="min-h-11 text-sm font-semibold text-teal-600 hover:text-teal-700">＋ Ich habe schlecht geschlafen</button>`;
      }
      html = `<div class="flex-1 space-y-5">
        <h2 class="text-lg font-bold text-slate-800">Wie viel Energie hast du?</h2>
        <div class="space-y-2">
          ${ciScaleButtons('energy')}
          <div class="flex justify-between text-sm text-slate-500"><span>Erschöpft</span><span>Voller Energie</span></div>
        </div>
        ${sleepBlock}</div>`;
      break;
    }
    case 'stress':
      html = `<div class="flex-1 space-y-5">
        <h2 class="text-lg font-bold text-slate-800">Wie gestresst bist du?</h2>
        <div class="space-y-2">
          ${ciScaleButtons('stress')}
          <div class="flex justify-between text-sm text-slate-500"><span>Entspannt</span><span>Sehr gestresst</span></div>
        </div></div>`;
      break;
    case 'signals': {
      const pill = s => `<button type="button" data-ci-symptom="${s.key}"
        class="min-h-11 rounded-full border px-3.5 py-1.5 text-sm font-semibold transition-colors ${d.symptoms[s.key] !== undefined ? 'border-teal-500 bg-teal-50 text-teal-700' : 'border-slate-200 text-slate-600 hover:border-teal-200'}">${s.label}</button>`;

      let painBlock = '';
      const painKey = ci.lastPainKey;
      const pain = painKey && d.symptoms[painKey] ? [...FREQUENT_SYMPTOMS, ...OTHER_SYMPTOMS].find(s => s.key === painKey) : null;
      if (pain) {
        painBlock = `<div class="rounded-2xl bg-slate-50 border border-slate-100 p-4 space-y-3">
          <p class="text-sm font-semibold text-slate-700">${pain.label} — wo genau?</p>
          <div class="flex flex-wrap gap-2">` +
          pain.regions.map(r => `<button type="button" data-ci-region="${pain.key}|${r}"
            class="min-h-11 rounded-full border px-3 py-1 text-sm font-semibold transition-colors ${d.symptoms[pain.key].region === r ? 'border-teal-500 bg-white text-teal-700' : 'border-slate-200 bg-white text-slate-600'}">${r}</button>`).join('') +
          `</div>
          <p class="text-sm text-slate-600">Wie stark? <span class="text-slate-500">(1 = leicht, 5 = stark)</span></p>
          <div class="grid grid-cols-5 gap-2">` +
          CI_SCALE.map(v => `<button type="button" data-ci-severity="${pain.key}|${v}"
            class="rounded-xl border py-2 text-sm font-bold transition-colors ${d.symptoms[pain.key].severity === v ? 'border-teal-500 bg-teal-50 text-teal-700' : 'border-slate-200 bg-white text-slate-600'}">${v}</button>`).join('') +
          `</div></div>`;
      }

      let illnessBlock = '';
      if (d.sick === true) {
        illnessBlock = `<div class="flex flex-wrap gap-2">` +
          ILLNESS_TYPES.map(t => `<button type="button" data-ci-illness="${t.key}"
            class="min-h-11 rounded-xl border px-3.5 py-2 text-sm font-semibold transition-colors ${d.illnessType === t.key ? 'border-amber-500 bg-amber-50 text-amber-700' : 'border-slate-200 text-slate-600'}">${t.label}</button>`).join('') + `</div>`;
        if (d.illnessType) {
          const subs = ILLNESS_TYPES.find(t => t.key === d.illnessType)?.subs ?? [];
          illnessBlock += `<div class="flex flex-wrap gap-2">` +
            subs.map(sub => `<button type="button" data-ci-sub="${sub}"
              class="min-h-11 rounded-full border px-3 py-1 text-sm font-semibold transition-colors ${d.illnessSubs.includes(sub) ? 'border-amber-500 bg-amber-50 text-amber-700' : 'border-slate-200 text-slate-600'}">${sub}</button>`).join('') + `</div>
            <p class="text-sm text-slate-600">Wie stark insgesamt?</p>
            ${ciScaleButtons('illnessSeverity', 'amber')}
            <p class="text-sm text-amber-700 bg-amber-50 rounded-lg px-3 py-2">Intensive Übungen werden pausiert — wir schlagen dir Erholung und sanfte Einheiten vor.</p>`;
        }
      }

      html = `<div class="flex-1 space-y-5">
        <h2 class="text-lg font-bold text-slate-800">Körpersignale &amp; Befinden</h2>
        <div>
          <p class="text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">Zuletzt häufig</p>
          <div class="flex flex-wrap gap-2">${FREQUENT_SYMPTOMS.map(pill).join('')}</div>
        </div>
        <div>
          <p class="text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">Weitere Signale</p>
          <div class="flex flex-wrap gap-2">${OTHER_SYMPTOMS.map(pill).join('')}</div>
        </div>
        ${painBlock}
        <div class="rounded-2xl border border-slate-100 p-4 space-y-3">
          <p class="text-sm font-semibold text-slate-700">Fühlst du dich krank?</p>
          <div class="flex gap-2">
            <button type="button" data-ci-sick="false" class="flex-1 rounded-xl border py-2.5 text-sm font-semibold transition-colors ${d.sick === false ? 'border-teal-500 bg-teal-50 text-teal-700' : 'border-slate-200 text-slate-600'}">Nein</button>
            <button type="button" data-ci-sick="true" class="flex-1 rounded-xl border py-2.5 text-sm font-semibold transition-colors ${d.sick === true ? 'border-amber-500 bg-amber-50 text-amber-700' : 'border-slate-200 text-slate-600'}">Ja</button>
          </div>
          ${illnessBlock}
        </div></div>`;
      break;
    }
    case 'summary':
      html = `<div class="flex-1 space-y-4">
        <h2 class="text-lg font-bold text-slate-800">Zusammenfassung</h2>
        <dl class="space-y-2 text-sm">` +
        ciSummaryRows().map(row => `<div class="flex justify-between gap-4 border-b border-slate-50 pb-2">
          <dt class="text-slate-500">${row.label}</dt>
          <dd class="font-semibold text-slate-700 text-right">${row.value}</dd>
        </div>`).join('') +
        `</dl>
        <p class="rounded-xl bg-teal-50 px-4 py-2.5 text-sm font-semibold text-teal-700">Du erhältst 10 Punkte für deinen Check-in.</p></div>`;
      break;
    case 'done':
      html = `<div class="flex-1 flex flex-col items-center justify-center text-center space-y-4 py-8">
        <div class="w-16 h-16 rounded-full bg-teal-50 flex items-center justify-center text-3xl">✓</div>
        <h2 class="text-lg font-bold text-slate-800">${ci.justSaved ? 'Check-in gespeichert' : 'Check-in für heute erledigt'}</h2>
        ${ci.justSaved ? '<p class="text-sm font-bold text-teal-600">+10 Punkte</p>' : ''}
        <p class="text-sm text-slate-500 max-w-xs">Deine Angaben bleiben in dieser Demo lokal auf deinem Gerät gespeichert.</p>
        <div class="flex gap-3">
          <button type="button" data-ci-restart class="min-h-11 rounded-lg border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors">Erneut ausfüllen</button>
          <a href="#dashboard" data-page="dashboard" class="inline-flex min-h-11 items-center rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-700 transition-colors">Zurück zur Übersicht</a>
        </div></div>`;
      break;
  }
  body.innerHTML = html;

  if (ci.step === 'done') {
    footer.innerHTML = '';
  } else {
    const isSummary = ci.step === 'summary';
    footer.innerHTML = `<div class="flex justify-between gap-3 pt-6 mt-auto">
      <button type="button" data-ci-back ${ciStepIndex() === 0 ? 'disabled' : ''}
              class="min-h-11 rounded-lg border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-40 transition-colors">Zurück</button>
      ${isSummary
        ? `<button type="button" data-ci-save class="min-h-11 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-700 transition-colors">Speichern</button>`
        : `<button type="button" data-ci-next ${ciStepValid() ? '' : 'disabled'} class="min-h-11 rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-700 disabled:bg-slate-300 transition-colors">Weiter</button>`}
    </div>`;
  }
}

document.addEventListener('click', e => {
  const d = ci.draft;
  const set = e.target.closest('[data-ci-set]');
  if (set) {
    const [field, raw] = set.dataset.ciSet.split(':');
    d[field] = raw === 'true' ? true : isNaN(Number(raw)) ? raw : Number(raw);
    ciRender();
    return;
  }
  const sym = e.target.closest('[data-ci-symptom]');
  if (sym) {
    const key = sym.dataset.ciSymptom;
    const def = [...FREQUENT_SYMPTOMS, ...OTHER_SYMPTOMS].find(s => s.key === key);
    if (d.symptoms[key] !== undefined) {
      delete d.symptoms[key];
      if (ci.lastPainKey === key) ci.lastPainKey = null;
    } else {
      d.symptoms[key] = { region: def.regions[0], severity: 3 };
      if (def.pain) ci.lastPainKey = key;
      if (key === 'fatigue') d.sleepWanted = true;
    }
    ciRender();
    return;
  }
  const region = e.target.closest('[data-ci-region]');
  if (region) { const [k, r] = region.dataset.ciRegion.split('|'); d.symptoms[k].region = r; ciRender(); return; }
  const severity = e.target.closest('[data-ci-severity]');
  if (severity) { const [k, v] = severity.dataset.ciSeverity.split('|'); d.symptoms[k].severity = Number(v); ciRender(); return; }
  const sick = e.target.closest('[data-ci-sick]');
  if (sick) {
    d.sick = sick.dataset.ciSick === 'true';
    if (!d.sick) { d.illnessType = null; d.illnessSubs = []; d.illnessSeverity = null; }
    ciRender();
    return;
  }
  const illness = e.target.closest('[data-ci-illness]');
  if (illness) {
    if (d.illnessType !== illness.dataset.ciIllness) { d.illnessType = illness.dataset.ciIllness; d.illnessSubs = []; }
    ciRender();
    return;
  }
  const sub = e.target.closest('[data-ci-sub]');
  if (sub) {
    const s = sub.dataset.ciSub;
    d.illnessSubs = d.illnessSubs.includes(s) ? d.illnessSubs.filter(x => x !== s) : [...d.illnessSubs, s];
    ciRender();
    return;
  }
  const sleep = e.target.closest('[data-ci-sleep]');
  if (sleep) { d.sleepHours = Math.max(0, Math.min(14, d.sleepHours + Number(sleep.dataset.ciSleep))); ciRender(); return; }
  if (e.target.closest('[data-ci-back]')) { const i = ciStepIndex(); if (i > 0) ci.step = CI_STEPS[i - 1]; ciRender(); return; }
  if (e.target.closest('[data-ci-next]')) { const i = ciStepIndex(); if (ciStepValid() && i < CI_STEPS.length - 1) ci.step = CI_STEPS[i + 1]; ciRender(); return; }
  if (e.target.closest('[data-ci-save]')) {
    ci.justSaved = true;
    ci.step = 'done';
    const title = document.querySelector('[data-checkin-title]');
    const sub2 = document.querySelector('[data-checkin-sub]');
    if (title) title.textContent = 'Check-in für heute erledigt ✓';
    if (sub2) sub2.textContent = 'Stark — bis morgen!';
    ciRender();
    return;
  }
  if (e.target.closest('[data-ci-restart]')) {
    ci.draft = emptyDraft();
    ci.justSaved = false;
    ci.lastPainKey = null;
    ci.step = 'location';
    ciRender();
    return;
  }
});

ciRender();

/* ---------- Buttons that navigate like routerLink ---------- */
document.addEventListener('click', e => {
  const btn = e.target.closest('[data-page-btn]');
  if (btn) goPage(btn.dataset.pageBtn);
});

/* ---------- Measure exercise (port of MeasureExercise timer/sets, hold 30s, 3 sets) ---------- */
const ex = { set: 1, sets: 3, hold: 30, seconds: 30, running: false, handle: null };

function exTimerLabel() {
  const m = Math.floor(ex.seconds / 60);
  const s = ex.seconds % 60;
  return `${m}:${String(s).padStart(2, '0')}`;
}

function exRender() {
  const setEl = document.getElementById('ex-current-set');
  const timerEl = document.getElementById('ex-timer');
  const toggleEl = document.getElementById('ex-timer-toggle');
  const completeEl = document.getElementById('ex-complete-set');
  if (!setEl) return;
  setEl.textContent = String(ex.set);
  timerEl.textContent = exTimerLabel();
  toggleEl.textContent = ex.running ? 'Pause' : 'Timer starten';
  completeEl.textContent = ex.set >= ex.sets ? 'Übung abschließen ✓' : 'Satz abgeschlossen ✓';
}

function exStopTimer() {
  if (ex.handle) { clearInterval(ex.handle); ex.handle = null; }
  ex.running = false;
}

document.addEventListener('click', e => {
  if (e.target.closest('#ex-timer-toggle')) {
    if (ex.running) { exStopTimer(); exRender(); return; }
    if (ex.seconds <= 0) ex.seconds = ex.hold;
    ex.running = true;
    ex.handle = setInterval(() => {
      ex.seconds = Math.max(0, ex.seconds - 1);
      if (ex.seconds === 0) exStopTimer();
      exRender();
    }, 1000);
    exRender();
    return;
  }
  if (e.target.closest('#ex-complete-set')) {
    exStopTimer();
    if (ex.set >= ex.sets) {
      ex.set = 1;
      ex.seconds = ex.hold;
      goPage('measure-detail');
    } else {
      ex.set += 1;
      ex.seconds = ex.hold;
    }
    exRender();
  }
});

exRender();

/* ---------- Lab markers (port of EmployeeLabMarkersComponent) ---------- */
const LAB_MARKERS = [
  { markerKey: 'hb', name: 'Hämoglobin', unit: 'g/dl', low: 13.5, high: 17.5, group: 'blutbild', value: 14.8, status: 'im Orientierungsbereich', isHighlighted: false },
  { markerKey: 'hkt', name: 'Hämatokrit', unit: '%', low: 40, high: 52, group: 'blutbild', value: 44, status: 'im Orientierungsbereich', isHighlighted: false },
  { markerKey: 'ery', name: 'Erythrozyten', unit: 'Mio/µl', low: 4.5, high: 5.9, group: 'blutbild', value: 5.1, status: 'im Orientierungsbereich', isHighlighted: false },
  { markerKey: 'mcv', name: 'MCV', unit: 'fl', low: 80, high: 96, group: 'blutbild', value: 88, status: 'im Orientierungsbereich', isHighlighted: false },
  { markerKey: 'mch', name: 'MCH', unit: 'pg', low: 28, high: 33, group: 'blutbild', value: 30, status: 'im Orientierungsbereich', isHighlighted: false },
  { markerKey: 'mchc', name: 'MCHC', unit: 'g/dl', low: 33, high: 36, group: 'blutbild', value: 34, status: 'im Orientierungsbereich', isHighlighted: false },
  { markerKey: 'rdw', name: 'RDW', unit: '%', low: 11.5, high: 14.5, group: 'blutbild', value: 13, status: 'im Orientierungsbereich', isHighlighted: false },
  { markerKey: 'leuko', name: 'Leukozyten', unit: '/nl', low: 4, high: 10, group: 'immun', value: 6.2, status: 'im Orientierungsbereich', isHighlighted: false },
  { markerKey: 'thrombo', name: 'Thrombozyten', unit: '/nl', low: 150, high: 400, group: 'immun', value: 250, status: 'im Orientierungsbereich', isHighlighted: false },
  { markerKey: 'crp', name: 'CRP', unit: 'mg/l', low: 0, high: 5, group: 'immun', value: 7, status: 'über Bereich', isHighlighted: true },
  { markerKey: 'vitd', name: 'Vitamin D', unit: 'ng/ml', low: 30, high: 50, group: 'mikro', value: 18, status: 'unter Bereich', isHighlighted: true },
  { markerKey: 'ferritin', name: 'Ferritin', unit: 'ng/ml', low: 30, high: 300, group: 'mikro', value: 18, status: 'unter Bereich', isHighlighted: true },
];

const LAB_GROUPS = [
  { key: 'blutbild', title: 'Blutbild & Sauerstofftransport', accent: '#0d9488' },
  { key: 'immun', title: 'Immun- / Entzündungssignale', accent: '#ea580c' },
  { key: 'mikro', title: 'Mikronährstoffe & Speicher', accent: '#7c3aed' },
];

const LAB_EXPLANATIONS = {
  vitd: { title: 'Was beschreibt Vitamin D?', shortExplanation: 'Vitamin D wird häufig im Zusammenhang mit Knochenstoffwechsel und allgemeiner Versorgung betrachtet.', contextNote: 'Die Einordnung hängt unter anderem von Labor, Jahreszeit, Tageslicht, Ernährung und individuellem Kontext ab.', safetyNote: 'Diese Erklärung ersetzt keine ärztliche Einordnung.' },
  ferritin: { title: 'Was beschreibt Ferritin?', shortExplanation: 'Ferritin wird häufig als Speicherform von Eisen betrachtet.', contextNote: 'Die Einordnung hängt unter anderem von Blutbild, Entzündungssignalen und individuellem Kontext ab.', safetyNote: 'Diese Erklärung ersetzt keine ärztliche Einordnung.' },
  crp: { title: 'Was beschreibt CRP?', shortExplanation: 'CRP ist ein unspezifisches Entzündungssignal.', contextNote: 'Die Einordnung sollte immer im Kontext von Beschwerden, Verlauf und weiteren Werten betrachtet werden.', safetyNote: 'Diese Erklärung ersetzt keine ärztliche Einordnung.' },
  hb: { title: 'Was beschreibt Hämoglobin?', shortExplanation: 'Hämoglobin ist ein Bestandteil der roten Blutkörperchen und wird häufig im Zusammenhang mit Sauerstofftransport betrachtet.', contextNote: 'Die Einordnung hängt unter anderem von Blutbild, Referenzbereich und individuellem Kontext ab.', safetyNote: 'Diese Erklärung ersetzt keine ärztliche Einordnung.' },
  hkt: { title: 'Was beschreibt Hämatokrit?', shortExplanation: 'Hämatokrit beschreibt den Anteil der Blutzellen am Blutvolumen.', contextNote: 'Die Einordnung hängt unter anderem von Flüssigkeitshaushalt, Blutbild und individuellem Kontext ab.', safetyNote: 'Diese Erklärung ersetzt keine ärztliche Einordnung.' },
  ery: { title: 'Was beschreiben Erythrozyten?', shortExplanation: 'Erythrozyten sind rote Blutkörperchen und werden häufig im Zusammenhang mit Sauerstofftransport betrachtet.', contextNote: 'Die Einordnung hängt unter anderem von weiteren Blutbildwerten und individuellem Kontext ab.', safetyNote: 'Diese Erklärung ersetzt keine ärztliche Einordnung.' },
  mcv: { title: 'Was beschreibt MCV?', shortExplanation: 'MCV beschreibt die durchschnittliche Größe der roten Blutkörperchen.', contextNote: 'Die Einordnung erfolgt typischerweise gemeinsam mit weiteren Blutbildwerten.', safetyNote: 'Diese Erklärung ersetzt keine ärztliche Einordnung.' },
  mch: { title: 'Was beschreibt MCH?', shortExplanation: 'MCH beschreibt die durchschnittliche Menge an Hämoglobin pro rotem Blutkörperchen.', contextNote: 'Die Einordnung erfolgt typischerweise gemeinsam mit weiteren Blutbildwerten.', safetyNote: 'Diese Erklärung ersetzt keine ärztliche Einordnung.' },
  mchc: { title: 'Was beschreibt MCHC?', shortExplanation: 'MCHC beschreibt die durchschnittliche Hämoglobin-Konzentration in den roten Blutkörperchen.', contextNote: 'Die Einordnung erfolgt typischerweise gemeinsam mit weiteren Blutbildwerten.', safetyNote: 'Diese Erklärung ersetzt keine ärztliche Einordnung.' },
  rdw: { title: 'Was beschreibt RDW?', shortExplanation: 'RDW beschreibt, wie stark sich rote Blutkörperchen in ihrer Größe unterscheiden.', contextNote: 'Die Einordnung erfolgt typischerweise gemeinsam mit weiteren Blutbildwerten.', safetyNote: 'Diese Erklärung ersetzt keine ärztliche Einordnung.' },
  leuko: { title: 'Was beschreiben Leukozyten?', shortExplanation: 'Leukozyten sind weiße Blutkörperchen und werden häufig im Zusammenhang mit Immunaktivität betrachtet.', contextNote: 'Die Einordnung hängt unter anderem von Verlauf, Beschwerden und weiteren Werten ab.', safetyNote: 'Diese Erklärung ersetzt keine ärztliche Einordnung.' },
  thrombo: { title: 'Was beschreiben Thrombozyten?', shortExplanation: 'Thrombozyten sind Blutplättchen und werden häufig im Zusammenhang mit Blutgerinnung betrachtet.', contextNote: 'Die Einordnung hängt unter anderem von Blutbild, Verlauf und individuellem Kontext ab.', safetyNote: 'Diese Erklärung ersetzt keine ärztliche Einordnung.' },
};

const LAB_INTERPRETATIONS = {
  vitd: 'Dein hinterlegter Wert liegt unter dem angezeigten Orientierungsbereich. Mögliche Einflussfaktoren können Tageslicht, Ernährung und individuelle Faktoren sein.',
  ferritin: 'Der hinterlegte Ferritinwert liegt unter dem angezeigten Orientierungsbereich. Ferritin wird häufig im Zusammenhang mit Eisenspeichern betrachtet; die Einordnung hängt vom Gesamtbild ab.',
  crp: 'Der hinterlegte Wert liegt über dem angezeigten Orientierungsbereich. Entzündungssignale sind unspezifisch und immer im Gesamtbild und im Kontext von Beschwerden zu betrachten.',
};

const LAB_ROUTINES = {
  vitd: ['Tageslicht-Routine aufbauen, z. B. regelmäßig morgens rausgehen', 'Vitamin-D-reiche Lebensmittel ergänzen', 'Supplementierung nur nach ärztlicher oder fachlicher Rücksprache prüfen'],
  ferritin: ['Eisenreiche Mahlzeiten einplanen', 'Eisenquellen mit Vitamin C kombinieren', 'Bei Beschwerden oder wiederholt auffälligen Werten ärztlich abklären'],
  crp: ['Intensive Belastung heute bewusst reduzieren', 'Schonmodus und sanfte Routinen nutzen', 'Bei Beschwerden oder wiederholt auffälligen Werten ärztlich abklären'],
};

const LAB_HINTS = {
  vitd: 'Referenzbereiche können je Labor und Kontext variieren.',
  ferritin: 'Referenzbereiche können je Labor, Alter, Geschlecht und Kontext variieren.',
  crp: 'Ein einzelner Wert ist nur eine Momentaufnahme.',
};

function labFormatValue(v) { return v === null ? '–' : String(Math.round(v * 100) / 100).replace('.', ','); }
function labStatusClass(s) { return s === 'unter Bereich' ? 'bg-amber-50 text-amber-700' : s === 'über Bereich' ? 'bg-orange-50 text-orange-700' : 'bg-teal-50 text-teal-700'; }
function labStatusTextClass(s) { return s === 'unter Bereich' ? 'text-amber-700' : s === 'über Bereich' ? 'text-orange-700' : 'text-teal-700'; }
function labStatusDotColor(s) { return s === 'unter Bereich' ? '#f59e0b' : s === 'über Bereich' ? '#ea580c' : '#14b8a6'; }
function labSoftTag(m) { return m.status === 'im Orientierungsbereich' ? 'stabil' : 'beobachten'; }
function labSoftTagClass(m) { return labSoftTag(m) === 'stabil' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'; }
function labRangeLabel(m) {
  if (m.low === null || m.high === null) return 'Orientierungsbereich';
  if (m.low === 0) return `Orientierungsbereich < ${labFormatValue(m.high)} ${m.unit}`;
  return `Orientierungsbereich ${labFormatValue(m.low)}–${labFormatValue(m.high)} ${m.unit}`;
}
function labBounds(m) {
  const low = m.low ?? Math.max(0, m.value * 0.75);
  const high = m.high ?? m.value * 1.25;
  const width = Math.max(high - low, 1);
  return { min: Math.max(0, low - width * 0.55), max: high + width * 0.55 };
}
function labPercent(value, b) { return Math.max(0, Math.min(100, ((value - b.min) / (b.max - b.min)) * 100)); }
function labPolar(deg) { const r = (deg * Math.PI) / 180; return { x: 70 + 54 * Math.cos(r), y: 70 - 54 * Math.sin(r) }; }
function labArcPath(startFraction, endFraction) {
  const s = labPolar(180 - startFraction * 180);
  const e = labPolar(180 - endFraction * 180);
  return `M ${s.x.toFixed(2)} ${s.y.toFixed(2)} A 54 54 0 0 1 ${e.x.toFixed(2)} ${e.y.toFixed(2)}`;
}

function labPopover(m, placement) {
  const ex2 = LAB_EXPLANATIONS[m.markerKey];
  if (!ex2) return '';
  const key = `${placement}-${m.markerKey}`;
  return `<span class="relative inline-flex" data-lab-popover-wrap="${key}">
    <button type="button" data-lab-popover="${key}"
            class="-my-2 inline-flex min-h-11 min-w-11 items-center justify-center rounded-full text-slate-500 transition hover:text-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2"
            aria-label="Was beschreibt dieser Marker?">
      <span class="flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-sm font-bold">i</span>
    </button>
    <div data-lab-popover-body="${key}" role="dialog" aria-label="${ex2.title}" class="hidden absolute left-0 top-full z-30 mt-2 w-[min(20rem,calc(100vw-3rem))] rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-lg">
      <div class="flex items-start justify-between gap-3">
        <p class="text-sm font-semibold text-slate-800">${ex2.title}</p>
        <button type="button" data-lab-popover-close="${key}"
                class="-mr-1 -mt-1 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold text-slate-500 hover:bg-slate-50 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2"
                aria-label="Erklärung schließen">x</button>
      </div>
      <p class="mt-2 text-sm leading-6 text-slate-600">${ex2.shortExplanation}</p>
      <p class="mt-2 text-sm leading-6 text-slate-500">${ex2.contextNote}</p>
      <p class="mt-3 rounded-xl bg-teal-50 px-3 py-2 text-sm leading-5 text-teal-800">${ex2.safetyNote}</p>
    </div>
  </span>`;
}

function renderLabMarkers() {
  const highlightedEl = document.getElementById('lab-highlighted');
  const groupsEl = document.getElementById('lab-groups');
  if (!highlightedEl) return;

  highlightedEl.innerHTML = LAB_MARKERS.filter(m => m.isHighlighted).map(m => {
    const b = labBounds(m);
    const gauge = labPolar(180 - (labPercent(m.value, b) / 100) * 180);
    return `<article class="flex flex-col rounded-[20px] border border-slate-100 bg-white p-[22px]">
      <div class="flex items-start justify-between gap-3">
        <div>
          <div class="flex items-center gap-1.5">
            <h2 class="text-[17px] font-semibold text-slate-800">${m.name}</h2>
            ${labPopover(m, 'highlight')}
          </div>
          <p class="mt-1 text-[13px] text-slate-500">${labRangeLabel(m)}</p>
        </div>
        <span class="shrink-0 rounded-full px-3 py-1.5 text-xs font-bold ${labStatusClass(m.status)}">${m.status}</span>
      </div>
      <div class="my-3 flex flex-col items-center">
        <svg width="180" height="104" viewBox="0 0 140 80" class="overflow-visible" aria-hidden="true">
          <path d="${labArcPath(0, 1)}" fill="none" stroke="#f1f5f9" stroke-width="12" stroke-linecap="round"></path>
          <path d="${labArcPath(labPercent(m.low ?? b.min, b) / 100, labPercent(m.high ?? b.max, b) / 100)}" fill="none" stroke="#99f6e4" stroke-width="12" stroke-linecap="round"></path>
          <circle cx="${gauge.x.toFixed(2)}" cy="${gauge.y.toFixed(2)}" r="7.5" fill="${labStatusDotColor(m.status)}" stroke="#ffffff" stroke-width="3.5"></circle>
        </svg>
        <div class="-mt-4 text-center">
          <span class="text-[32px] font-bold text-slate-800">${labFormatValue(m.value)}</span>
          <span class="ml-1 text-sm text-slate-500">${m.unit}</span>
        </div>
        <div class="mt-2 grid w-full max-w-[13rem] grid-cols-3 gap-2 text-center text-xs font-semibold text-slate-500">
          <span>unter Bereich</span>
          <span class="text-teal-700">Orientierung</span>
          <span>über Bereich</span>
        </div>
      </div>
      <p class="text-sm leading-6 text-slate-600">${LAB_INTERPRETATIONS[m.markerKey]}</p>
      <p class="mt-4 text-xs font-bold uppercase tracking-wide text-teal-700">Empfohlene Routinen</p>
      <div class="mt-2 flex flex-col gap-2">
        ${LAB_ROUTINES[m.markerKey].map(r => `<div class="flex items-start gap-2.5">
          <span class="mt-0.5 flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-full bg-teal-100 text-xs font-bold text-teal-700">✓</span>
          <span class="text-sm leading-5 text-slate-700">${r}</span>
        </div>`).join('')}
      </div>
      <div class="mt-4 flex items-start gap-2 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2.5">
        <span class="text-sm leading-tight">ⓘ</span>
        <span class="text-xs leading-5 text-slate-500">${LAB_HINTS[m.markerKey]}</span>
      </div>
    </article>`;
  }).join('');

  groupsEl.innerHTML = LAB_GROUPS.map(group => {
    const markers = LAB_MARKERS.filter(m => m.group === group.key);
    return `<article class="rounded-[20px] border border-slate-100 bg-white px-6 py-[22px]">
      <div class="mb-5 flex items-center gap-2.5">
        <span class="h-2.5 w-2.5 rounded-[3px]" style="background:${group.accent}"></span>
        <h3 class="text-sm font-bold uppercase tracking-wide" style="color:${group.accent}">${group.title}</h3>
        <span class="text-xs text-slate-500">${markers.length} Werte</span>
      </div>
      <div class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,20.5rem),1fr))] gap-x-7 gap-y-5">
        ${markers.map(m => {
          const b = labBounds(m);
          const rangeStart = labPercent(m.low ?? b.min, b);
          const rangeWidth = Math.max(0, labPercent(m.high ?? b.max, b) - rangeStart);
          return `<div class="space-y-2">
            <div class="flex items-baseline justify-between gap-2">
              <span class="flex items-center gap-1.5">
                <span class="text-sm font-semibold text-slate-700">${m.name}</span>
                ${labPopover(m, 'overview')}
              </span>
              <span class="text-[13px] text-slate-500"><span class="font-bold text-slate-800">${labFormatValue(m.value)}</span> ${m.unit}</span>
            </div>
            <div class="relative h-2 overflow-hidden rounded-full bg-slate-100">
              <div class="absolute inset-y-0 bg-teal-100" style="left:${rangeStart}%;width:${rangeWidth}%"></div>
            </div>
            <div class="relative h-0">
              <span class="absolute -top-[13px] h-3 w-3 rounded-full border-[2.5px] border-white shadow-[0_0_0_1px_#e2e8f0]"
                    style="left:${labPercent(m.value, b)}%;background:${labStatusDotColor(m.status)};transform: translateX(-50%)"></span>
            </div>
            <div class="mt-1 flex items-center justify-between gap-2">
              <span class="text-xs font-semibold ${labStatusTextClass(m.status)}">${m.status}</span>
              <span class="rounded-full px-2.5 py-0.5 text-xs font-bold ${labSoftTagClass(m)}">${labSoftTag(m)}</span>
            </div>
          </div>`;
        }).join('')}
      </div>
    </article>`;
  }).join('');
}

renderLabMarkers();

/* Lab popover open/close — one at a time, hover + click like the app */
function closeAllLabPopovers() {
  document.querySelectorAll('[data-lab-popover-body]').forEach(el => el.classList.add('hidden'));
}
document.addEventListener('click', e => {
  const open = e.target.closest('[data-lab-popover]');
  if (open) {
    closeAllLabPopovers();
    document.querySelector(`[data-lab-popover-body="${open.dataset.labPopover}"]`)?.classList.remove('hidden');
    return;
  }
  const close = e.target.closest('[data-lab-popover-close]');
  if (close) {
    document.querySelector(`[data-lab-popover-body="${close.dataset.labPopoverClose}"]`)?.classList.add('hidden');
    return;
  }
  if (!e.target.closest('[data-lab-popover-wrap]')) closeAllLabPopovers();
});
document.addEventListener('mouseover', e => {
  const wrap = e.target.closest('[data-lab-popover-wrap]');
  if (wrap) {
    closeAllLabPopovers();
    document.querySelector(`[data-lab-popover-body="${wrap.dataset.labPopoverWrap}"]`)?.classList.remove('hidden');
  }
});
document.addEventListener('mouseout', e => {
  const wrap = e.target.closest('[data-lab-popover-wrap]');
  if (wrap && !wrap.contains(e.relatedTarget)) {
    document.querySelector(`[data-lab-popover-body="${wrap.dataset.labPopoverWrap}"]`)?.classList.add('hidden');
  }
});

/* ---------- Badges page collections (port of EmployeeBadgesComponent) ---------- */
const CATEGORY_ORDER = ['STARTER', 'STREAK', 'QUEST', 'RECOVERY', 'INSIGHT', 'LAB', 'PREVENTION'];
const CATEGORY_META = {
  STARTER: { iconKey: 'check', tone: 'teal', explanation: 'Deine ersten Schritte in ELYO — Screening, erster Check-in und erste Maßnahme.' },
  STREAK: { iconKey: 'compass', tone: 'amber', explanation: 'Belohnt Regelmäßigkeit — Serien aus täglichen Check-ins und Routinen.' },
  QUEST: { iconKey: 'activity', tone: 'violet', explanation: 'Kurze Aufgaben wie Atem-, Entspannungs- oder Mobilitätsübungen.' },
  RECOVERY: { iconKey: 'pause', tone: 'amber', explanation: 'Würdigt kluge Pausen und den Wiedereinstieg nach einer Auszeit.' },
  INSIGHT: { iconKey: 'target', tone: 'violet', explanation: 'Für das bewusste Wahrnehmen von Körpersignalen und Mustern.' },
  LAB: { iconKey: 'flask', tone: 'blue', explanation: 'Rund um Laborwerte — verstehen und in Routinen übersetzen.' },
  PREVENTION: { iconKey: 'shield', tone: 'violet', explanation: 'Vollständige Präventionszyklen aus Screening und passenden Maßnahmen.' },
};
const TONE_GRADIENTS = {
  teal: 'radial-gradient(circle at 34% 26%, #5eead4 0%, #14b8a6 48%, #0f766e 100%)',
  blue: 'radial-gradient(circle at 34% 26%, #93c5fd 0%, #3b82f6 48%, #1d4ed8 100%)',
  amber: 'radial-gradient(circle at 34% 26%, #fcd34d 0%, #f59e0b 48%, #b45309 100%)',
  violet: 'radial-gradient(circle at 34% 26%, #c4b5fd 0%, #8b5cf6 48%, #6d28d9 100%)',
  slate: 'radial-gradient(circle at 34% 26%, #cbd5e1 0%, #64748b 48%, #475569 100%)',
};

const openCategories = {};
CATEGORY_ORDER.forEach(c => {
  openCategories[c] = BADGES.some(b => b.category === c && b.status === 'IN_PROGRESS');
});

function badgeStatusText(b) {
  if (b.status === 'EARNED') return `Frei · ${formatShortDate(b.earnedAt)}`;
  if (b.status === 'LOCKED') return 'Noch gesperrt';
  return `${b.progressCurrent}/${b.progressTarget} ${b.unit ?? 'geschafft'}`;
}

function badgeCardClass(b) {
  switch (b.status) {
    case 'EARNED': return 'border-teal-200 bg-teal-50/70';
    case 'LOCKED': return 'border-slate-100 bg-slate-50/90';
    default: return 'border-slate-100 bg-white';
  }
}

function renderBadgeCollections() {
  const el = document.getElementById('badge-collections');
  if (!el) return;
  el.innerHTML = CATEGORY_ORDER.map(category => {
    const badges = BADGES.filter(b => b.category === category);
    if (badges.length === 0) return '';
    const earned = badges.filter(b => b.status === 'EARNED').length;
    const meta = CATEGORY_META[category];
    const open = openCategories[category];
    return `<article class="overflow-hidden rounded-[18px] border border-slate-100 bg-white">
      <button type="button"
              class="flex w-full items-center gap-3 px-[18px] py-4 text-left focus:outline-none focus:ring-2 focus:ring-inset focus:ring-teal-500"
              aria-expanded="${open}" data-badge-cat="${category}">
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-[14px] shadow-[0_4px_10px_rgba(15,23,42,.12)]" style="background:${TONE_GRADIENTS[meta.tone]}">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${GLYPHS[meta.iconKey]}</svg>
        </span>
        <span class="min-w-0 flex-1">
          <span class="block text-base font-semibold text-slate-800">${BADGE_CATEGORY_LABELS[category]}</span>
          <span class="mt-0.5 block text-sm text-slate-500">${earned}/${badges.length} freigeschaltet</span>
        </span>
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#94a3b8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
             class="shrink-0 transition-transform${open ? ' rotate-180' : ''}">
          <polyline points="6 9 12 15 18 9"></polyline>
        </svg>
      </button>
      ${open ? `<div class="px-[18px] pb-[18px]">
        <p class="rounded-xl bg-slate-50 px-4 py-3 text-sm leading-6 text-slate-500">${meta.explanation}</p>
        <div class="mt-3 grid grid-cols-[repeat(auto-fit,minmax(min(100%,13.5rem),1fr))] gap-3">
          ${badges.map(b => `<button type="button"
                  class="flex items-center gap-3 rounded-2xl border p-3 text-left transition hover:-translate-y-0.5 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 ${badgeCardClass(b)}"
                  data-open-badge="${b.id}">
            ${medallionHtml(b.tone, b.iconKey, b.status, b.progressPercent, 60)}
            <span class="min-w-0 flex-1">
              <span class="block text-[15px] font-semibold leading-tight text-slate-800">${b.title}</span>
              <span class="mt-1 block text-xs font-semibold text-slate-500">${badgeStatusText(b)}</span>
            </span>
          </button>`).join('')}
        </div>
      </div>` : ''}
    </article>`;
  }).join('');
}

document.addEventListener('click', e => {
  const cat = e.target.closest('[data-badge-cat]');
  if (cat) {
    openCategories[cat.dataset.badgeCat] = !openCategories[cat.dataset.badgeCat];
    renderBadgeCollections();
  }
});

renderBadgeCollections();

/* ---------- History (port of HistoryComponent, 14 demo days) ---------- */
const HIST_METRICS = {
  wohlbefinden: { key: 'wohlbefinden', name: 'Wohlbefinden', color: '#0d9488', value: e => e.score },
  energie: { key: 'energie', name: 'Energie', color: '#4f46e5', value: e => e.energy },
  stress: { key: 'stress', name: 'Stress', color: '#e11d48', value: e => e.stress },
};
const HIST_DAY_COUNT = 14;
const hist = { focus: 'wohlbefinden', selected: null };

function histFmt(v) { return v.toFixed(1).replace('.', ','); }
function histDateKey(d) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

// Deterministic demo entries — values echo the dashboard week (Ø this week ≈ 3,8, +0,3 vs. previous).
const HIST_SCORES = [3.3, 3.6, 3.4, 3.7, 3.2, 3.6, 3.7, 3.6, 3.9, 3.7, 4.0, 3.8, 3.9, 3.8];
const HIST_MOOD =   [3, 4, 3, 4, 3, 4, 4, 4, 4, 4, 4, 4, 4, 4];
const HIST_ENERGY = [3, 4, 3, 4, 3, 3, 4, 3, 4, 3, 4, 3, 4, 3];
const HIST_STRESS = [3, 3, 3, 3, 4, 3, 3, 3, 2, 3, 2, 3, 2, 3];

const HIST_DAYS = (() => {
  const today = new Date();
  const start = new Date(today.getFullYear(), today.getMonth(), today.getDate());
  start.setDate(start.getDate() - (HIST_DAY_COUNT - 1));
  return Array.from({ length: HIST_DAY_COUNT }, (_, index) => {
    const date = new Date(start);
    date.setDate(date.getDate() + index);
    const created = new Date(date);
    created.setHours(8, 14, 0, 0);
    return {
      index,
      date,
      dateKey: histDateKey(date),
      label: new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit' }).format(date),
      entry: {
        score: HIST_SCORES[index],
        mood: HIST_MOOD[index],
        energy: HIST_ENERGY[index],
        stress: HIST_STRESS[index],
        createdAt: created,
        notes: index === HIST_DAY_COUNT - 1 ? 'Guter Start in die Woche, kurzer Spaziergang in der Mittagspause.' : null,
      },
    };
  });
})();
hist.selected = HIST_DAYS[HIST_DAY_COUNT - 1].dateKey;

function histMetricValue(entry, key) {
  const v = HIST_METRICS[key].value(entry);
  return v !== null && Number.isFinite(v) ? v : null;
}

function histDelta(key) {
  const valuesIn = (from, to) => HIST_DAYS
    .filter(d => d.index >= from && d.index <= to && d.entry && histMetricValue(d.entry, key) !== null)
    .map(d => histMetricValue(d.entry, key));
  const thisW = valuesIn(7, 13);
  const lastW = valuesIn(0, 6);
  const avg = a => (a.length ? a.reduce((x, y) => x + y, 0) / a.length : 0);
  if (!thisW.length || !lastW.length) return { good: true, text: '▬ ±0,0' };
  const delta = avg(thisW) - avg(lastW);
  const good = key === 'stress' ? delta < 0 : delta > 0;
  const arrow = delta > 0.05 ? '▲' : delta < -0.05 ? '▼' : '▬';
  const sign = delta >= 0 ? '+' : '−';
  return { good, text: `${arrow} ${sign}${histFmt(Math.abs(delta))}` };
}

function histSparkPath(key) {
  const W = 220, H = 44, pad = 4, maxI = HIST_DAY_COUNT - 1;
  const x = i => +((i / maxI) * W).toFixed(1);
  const y = v => +(pad + (1 - (v - 1) / 4) * (H - pad * 2)).toFixed(1);
  let path = '';
  HIST_DAYS.filter(d => d.entry && histMetricValue(d.entry, key) !== null).forEach((d, k) => {
    path += (k === 0 ? 'M' : 'L') + x(d.index) + ',' + y(histMetricValue(d.entry, key)) + ' ';
  });
  return path.trim();
}

function histMoodLabel(v) { return v >= 4 ? 'positiv' : v >= 3 ? 'ausgeglichen' : 'niedrig'; }
function histEnergyLabel(v) { return v >= 4 ? 'hoch' : v >= 3 ? 'stabil' : 'niedrig'; }
function histStressLabel(v) { return v >= 4 ? 'hoch' : v >= 3 ? 'mittel' : 'niedrig'; }

function renderHistory() {
  const cardsEl = document.getElementById('hist-mini-cards');
  if (!cardsEl) return;

  cardsEl.innerHTML = ['wohlbefinden', 'energie', 'stress'].map(key => {
    const meta = HIST_METRICS[key];
    const values = HIST_DAYS.filter(d => d.entry && histMetricValue(d.entry, key) !== null)
      .map(d => histMetricValue(d.entry, key));
    const current = values.length ? values[values.length - 1] : 0;
    const avg = values.length ? values.reduce((a, b) => a + b, 0) / values.length : 0;
    const delta = histDelta(key);
    const active = key === hist.focus;
    return `<button type="button" data-hist-focus="${key}" aria-pressed="${active}"
            class="rounded-[18px] bg-white p-[18px] text-left transition-all focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600"
            style="border:${active ? '2px solid ' + meta.color : '1px solid #f1f5f9'};box-shadow:${active ? '0 6px 18px rgba(0,0,0,.06)' : 'none'}">
      <div class="flex items-center justify-between gap-2">
        <span class="text-[14px] font-semibold text-[#475569]">${meta.name}</span>
        <span class="whitespace-nowrap rounded-full px-2 py-0.5 text-[13px] font-semibold"
              style="color:${delta.good ? '#0f766e' : '#dc2626'};background:${delta.good ? '#f0fdfa' : '#fef2f2'}">${delta.text}</span>
      </div>
      <div class="my-[10px] flex items-baseline gap-1.5">
        <span class="text-[34px] font-extrabold leading-none" style="font-family:var(--font-display);color:${meta.color}">${histFmt(current)}</span>
        <span class="text-[13px] text-[#94a3b8]">/ 5 · Ø ${histFmt(avg)}</span>
      </div>
      <svg viewBox="0 0 220 44" class="block h-11 w-full" preserveAspectRatio="none" aria-hidden="true">
        <path d="${histSparkPath(key)}" fill="none" stroke="${meta.color}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
      </svg>
    </button>`;
  }).join('');

  const meta = HIST_METRICS[hist.focus];
  document.getElementById('hist-chart-title').textContent = `${meta.name} über die Zeit`;

  const W = 940, H = 220, padT = 14, padB = 26, padL = 22, padR = 6;
  const innerW = W - padL - padR, innerH = H - padT - padB;
  const N = HIST_DAY_COUNT;
  const x = i => +(padL + (i / (N - 1)) * innerW).toFixed(1);
  const y = v => +(padT + (1 - (v - 1) / 4) * innerH).toFixed(1);
  const baseY = +(padT + innerH).toFixed(1);

  const grids = [1, 2, 3, 4, 5].map(v => `<line x1="0" x2="940" y1="${y(v)}" y2="${y(v)}" stroke="#f1f5f9" stroke-width="1"></line>
    <text x="0" y="${y(v) + 3}" font-size="11" fill="#cbd5e1" font-family="DM Sans">${v}</text>`).join('');

  const points = HIST_DAYS.filter(d => d.entry && histMetricValue(d.entry, hist.focus) !== null)
    .map(d => {
      const v = histMetricValue(d.entry, hist.focus);
      const sel = d.dateKey === hist.selected;
      return { x: x(d.index), y: y(v), r: sel ? 6 : 4, fill: sel ? meta.color : '#ffffff', dateKey: d.dateKey };
    });

  let linePath = '';
  points.forEach((p, k) => { linePath += (k === 0 ? 'M' : 'L') + p.x + ',' + p.y + ' '; });
  let areaPath = '';
  if (points.length) {
    areaPath = 'M' + points[0].x + ',' + baseY + ' ';
    points.forEach(p => { areaPath += 'L' + p.x + ',' + p.y + ' '; });
    areaPath += 'L' + points[points.length - 1].x + ',' + baseY + ' Z';
  }
  const selDay = HIST_DAYS.find(d => d.dateKey === hist.selected && d.entry);
  const xLabels = HIST_DAYS.filter(d => d.index % 3 === 0)
    .map(d => `<text x="${x(d.index)}" y="${H - 6}" font-size="11" fill="#94a3b8" text-anchor="middle" font-family="DM Sans">${d.label}</text>`).join('');

  document.getElementById('hist-chart').innerHTML = `<svg viewBox="0 0 940 220" class="block h-auto w-full overflow-visible" role="img" aria-label="${meta.name} über die letzten 14 Tage">
    <defs>
      <linearGradient id="history-focus-gradient" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="${meta.color}" stop-opacity="0.22"></stop>
        <stop offset="100%" stop-color="${meta.color}" stop-opacity="0"></stop>
      </linearGradient>
    </defs>
    ${grids}
    ${selDay ? `<line x1="${x(selDay.index)}" x2="${x(selDay.index)}" y1="0" y2="220" stroke="${meta.color}" stroke-width="1" stroke-dasharray="4 4" opacity="0.5"></line>` : ''}
    <path d="${areaPath}" fill="url(#history-focus-gradient)"></path>
    <path d="${linePath.trim()}" fill="none" stroke="${meta.color}" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></path>
    ${points.map(p => `<circle cx="${p.x}" cy="${p.y}" r="${p.r}" fill="${p.fill}" stroke="${meta.color}" stroke-width="2.5"
        role="button" tabindex="0" aria-label="Tag ${p.dateKey} auswählen"
        class="cursor-pointer outline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-teal-600"
        data-hist-day="${p.dateKey}"></circle>`).join('')}
    ${xLabels}
  </svg>`;

  const day = HIST_DAYS.find(d => d.dateKey === hist.selected);
  const entry = day?.entry ?? null;
  const reportEl = document.getElementById('hist-report');
  if (!entry) { reportEl.innerHTML = ''; return; }

  const coreRow = (label, value, labelFn, color) => `<div>
    <div class="mb-1.5 flex items-baseline justify-between">
      <span class="text-[14px] font-semibold text-[#475569]">${label}</span>
      <span class="text-[13px] text-[#94a3b8]">${value} / 5 · ${labelFn(value)}</span>
    </div>
    <div class="h-2 overflow-hidden rounded-full bg-[#f1f5f9]">
      <div class="h-full rounded-full" style="width:${(value / 5) * 100}%;background:${color}"></div>
    </div>
  </div>`;

  const longDate = new Intl.DateTimeFormat('de-DE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(entry.createdAt);
  const time = new Intl.DateTimeFormat('de-DE', { hour: '2-digit', minute: '2-digit' }).format(entry.createdAt);

  reportEl.innerHTML = `<div class="rounded-[20px] border border-[#e5ded3] p-6" style="background:hsl(40,20%,98%)">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
      <div class="flex flex-wrap items-baseline gap-3">
        <h2 class="text-[22px] font-bold text-[#1e293b]" style="font-family:var(--font-display)">${longDate}</h2>
        <span class="text-[14px] text-[#94a3b8]">Eingetragen um ${time} Uhr</span>
      </div>
    </div>
    <div class="grid grid-cols-[repeat(auto-fit,minmax(280px,1fr))] gap-4">
      <div class="rounded-[16px] border border-[#f1f5f9] bg-white p-[18px]">
        <p class="mb-[14px] text-[13px] font-bold uppercase tracking-[0.04em] text-[#94a3b8]">Kernwerte</p>
        <div class="flex flex-col gap-[14px]">
          ${coreRow('Stimmung', entry.mood, histMoodLabel, '#0d9488')}
          ${coreRow('Energie', entry.energy, histEnergyLabel, '#4f46e5')}
          ${coreRow('Stress', entry.stress, histStressLabel, '#e11d48')}
        </div>
      </div>
      <div class="rounded-[16px] border border-[#f1f5f9] bg-white p-[18px]">
        <p class="mb-[14px] text-[13px] font-bold uppercase tracking-[0.04em] text-[#94a3b8]">Schlaf &amp; Erholung</p>
        <p class="text-[14px] leading-relaxed text-[#cbd5e1]">Nur bei niedriger Energie abgefragt — an diesem Tag nicht erfasst.</p>
      </div>
      <div class="rounded-[16px] border border-[#f1f5f9] bg-white p-[18px]">
        <p class="mb-[14px] text-[13px] font-bold uppercase tracking-[0.04em] text-[#94a3b8]">Körpersignale</p>
        <p class="text-[14px] leading-relaxed text-[#cbd5e1]">Keine Körpersignale gemeldet.</p>
      </div>
      <div class="col-[1/-1] rounded-[16px] border border-[#f1f5f9] bg-white p-[18px]">
        <p class="mb-2.5 text-[13px] font-bold uppercase tracking-[0.04em] text-[#94a3b8]">Notiz</p>
        ${entry.notes
          ? `<p class="text-[15px] italic leading-relaxed text-[#334155]">„${entry.notes}"</p>`
          : `<p class="text-[14px] leading-relaxed text-[#cbd5e1]">Keine Notiz hinterlegt.</p>`}
      </div>
    </div>
  </div>`;
}

document.addEventListener('click', e => {
  const focusBtn = e.target.closest('[data-hist-focus]');
  if (focusBtn) { hist.focus = focusBtn.dataset.histFocus; renderHistory(); return; }
  const dayDot = e.target.closest('[data-hist-day]');
  if (dayDot) { hist.selected = dayDot.dataset.histDay; renderHistory(); }
});

renderHistory();

/* ---------- Surveys ---------- */
document.addEventListener('click', e => {
  if (e.target.closest('#survey-open')) {
    document.getElementById('survey-list').classList.add('hidden');
    document.getElementById('survey-detail').classList.remove('hidden');
    return;
  }
  if (e.target.closest('#survey-cancel') || e.target.closest('#survey-submit')) {
    document.getElementById('survey-detail').classList.add('hidden');
    document.getElementById('survey-list').classList.remove('hidden');
    return;
  }
  const yn = e.target.closest('[data-yn]');
  if (yn) {
    yn.parentElement.querySelectorAll('[data-yn]').forEach(b => b.classList.remove('bg-teal-600', 'text-white'));
    yn.classList.add('bg-teal-600', 'text-white');
  }
});

/* ---------- Profile screening (port of ProfileComponent screening flow) ---------- */
const SCREENING_POINTS = 50;
const SCALE_OPTIONS = [
  { value: 1, label: 'Trifft gar nicht zu' },
  { value: 2, label: 'Trifft eher nicht zu' },
  { value: 3, label: 'Teils/teils' },
  { value: 4, label: 'Trifft eher zu' },
  { value: 5, label: 'Trifft voll zu' },
];
const SCREENING_SECTIONS = [
  { id: 'wellbeing', title: 'Allgemeines Wohlbefinden', questions: [
    { id: 'wellbeing-health', text: 'Ich fühle mich körperlich gesund und belastbar.' },
    { id: 'wellbeing-energy', text: 'Ich habe im Alltag ausreichend Energie.' },
    { id: 'wellbeing-recovery', text: 'Ich erhole mich gut nach körperlicher oder mentaler Belastung.' },
    { id: 'wellbeing-life-satisfaction', text: 'Ich bin insgesamt zufrieden mit meinem aktuellen Leben.' },
    { id: 'wellbeing-complaints', text: 'Ich bin weitgehend frei von Beschwerden, zum Beispiel Schmerzen, Verspannung oder Verdauungsbeschwerden.' },
  ]},
  { id: 'activity', title: 'Bewegung & Aktivität', questions: [
    { id: 'activity-daily-movement', text: 'Ich bewege mich im Alltag regelmäßig, zum Beispiel Gehen, Treppen oder aktive Wege.' },
    { id: 'activity-level', text: 'Ich erreiche an den meisten Tagen ein gutes Aktivitätsniveau, zum Beispiel etwa 7.000 oder mehr Schritte.' },
    { id: 'activity-sport', text: 'Ich mache mindestens 2-mal pro Woche gezielte körperliche Aktivität oder Sport.' },
    { id: 'activity-sitting', text: 'Ich sitze den Großteil des Tages, zum Beispiel mehr als 8 Stunden.', reverseScored: true },
    { id: 'activity-after-movement', text: 'Nach Bewegung fühle ich mich eher energiegeladen als erschöpft.' },
  ]},
  { id: 'nutrition', title: 'Ernährung & Hydration', questions: [
    { id: 'nutrition-fluid', text: 'Ich trinke täglich ausreichend Flüssigkeit, etwa 1,5 bis 2 Liter Wasser oder Tee.' },
    { id: 'nutrition-fresh-food', text: 'Ich esse täglich frische, nährstoffreiche Lebensmittel, zum Beispiel Gemüse oder Obst.' },
    { id: 'nutrition-processed-food', text: 'Ich konsumiere selten stark verarbeitete Lebensmittel.' },
    { id: 'nutrition-mindful-meals', text: 'Ich esse meine Mahlzeiten überwiegend bewusst, ohne starke Ablenkung.' },
  ]},
  { id: 'sleep', title: 'Schlaf & Regeneration', questions: [
    { id: 'sleep-duration', text: 'Ich schlafe im Durchschnitt ausreichend, etwa 7 bis 8 Stunden.' },
    { id: 'sleep-falling-asleep', text: 'Ich kann abends gut abschalten und einschlafen.' },
    { id: 'sleep-through-night', text: 'Ich schlafe die Nacht überwiegend durch.' },
    { id: 'sleep-rested', text: 'Ich wache morgens erholt auf.' },
    { id: 'sleep-restless', text: 'Ich habe einen unruhigen Schlaf oder leide unter nächtlichem Aufwachen.', reverseScored: true },
  ]},
  { id: 'stress', title: 'Stress & mentale Belastung', questions: [
    { id: 'stress-time-pressure', text: 'Ich fühle mich im Alltag häufig unter Zeitdruck oder Stress.', reverseScored: true },
    { id: 'stress-evening-exhaustion', text: 'Ich fühle mich am Ende des Tages erschöpft.', reverseScored: true },
    { id: 'stress-switch-off', text: 'Ich kann nach der Arbeit gut abschalten.' },
    { id: 'stress-daily-control', text: 'Ich habe das Gefühl, meinen Alltag gut im Griff zu haben.' },
    { id: 'stress-body-reactions', text: 'Ich bemerke stressbedingte körperliche Reaktionen, zum Beispiel Herzklopfen, Muskelverspannungen oder Magenbeschwerden.', reverseScored: true },
  ]},
  { id: 'selfcare', title: 'Umfeld & Selbstfürsorge', questions: [
    { id: 'selfcare-support', text: 'Ich fühle mich in meinem Umfeld unterstützt.' },
    { id: 'selfcare-time', text: 'Ich nehme mir regelmäßig bewusst Zeit für mich selbst.' },
    { id: 'selfcare-boundaries', text: 'Ich kann meine Grenzen gut setzen.' },
    { id: 'selfcare-energy-strategies', text: 'Ich kenne Strategien, die mir helfen, neue Energie zu tanken.' },
  ]},
  { id: 'substances', title: 'Substanzkonsum & Kompensation', questions: [
    { id: 'substances-alcohol', text: 'Ich konsumiere regelmäßig Alkohol.', reverseScored: true },
    { id: 'substances-nicotine', text: 'Ich konsumiere Nikotin, zum Beispiel Zigaretten oder Vapes.', reverseScored: true },
    { id: 'substances-caffeine', text: 'Ich benötige Koffein, um durch den Tag zu kommen.', reverseScored: true },
    { id: 'substances-stress-food', text: 'Ich greife bei Stress häufig zu Essen, zum Beispiel Snacks oder Süßes.', reverseScored: true },
  ]},
  { id: 'focus', title: 'Kognitive Leistungsfähigkeit & Fokus', questions: [
    { id: 'focus-concentration', text: 'Wenn ich mich körperlich oder mental unwohl fühle, fällt es mir schwer, mich über längere Zeit auf meine Arbeitsaufgaben zu konzentrieren.', reverseScored: true },
    { id: 'focus-errors', text: 'Ich bemerke in meinem Arbeitsalltag, dass mir aufgrund von Müdigkeit oder Erschöpfung häufiger Flüchtigkeitsfehler unterlaufen.', reverseScored: true },
    { id: 'focus-presenteeism', text: 'Ich gehe trotz spürbarer gesundheitlicher Einschränkungen zur Arbeit, obwohl ein Erholungstag für meinen Körper medizinisch sinnvoller wäre.', reverseScored: true },
  ]},
  { id: 'workload', title: 'Quantitative Arbeitsbelastung & Zeitdruck', questions: [
    { id: 'workload-amount', text: 'Die Menge der Arbeit und die vorgegebenen Termine beziehungsweise Fristen sind in meinem Arbeitsalltag oft kaum zu bewältigen.', reverseScored: true },
    { id: 'workload-breaks', text: 'Meine Arbeit ist so intensiv oder eng getaktet, dass ich regelmäßig auf Pausen verzichten muss, um mein Pensum zu schaffen.', reverseScored: true },
    { id: 'workload-interruptions', text: 'Häufige Unterbrechungen, Störungen oder unvorhergesehene Planänderungen erschweren es mir, meine eigentliche Arbeit strukturiert zu erledigen.', reverseScored: true },
  ]},
  { id: 'work-climate', title: 'Arbeitsklima & Soziale Unterstützung', questions: [
    { id: 'work-climate-colleagues', text: 'Wenn an meinem Arbeitsplatz Probleme oder hohe Belastungsspitzen auftreten, kann ich mich auf die praktische Unterstützung meiner Kollegen verlassen.' },
    { id: 'work-climate-leadership', text: 'Ich erlebe von meinen direkten Vorgesetzten Anerkennung für meine Leistung und erhalte bei Bedarf konstruktives Feedback.' },
    { id: 'work-climate-conflicts', text: 'Konflikte oder Meinungsverschiedenheiten in meinem Team werden offen, sachlich und respektvoll gelöst.' },
  ]},
  { id: 'work-satisfaction', title: 'Arbeitszufriedenheit & Belohnung', questions: [
    { id: 'work-satisfaction-meaning', text: 'Ich erlebe meine tägliche Arbeit als sinnvoll und habe das Gefühl, mit meiner Leistung einen wichtigen Beitrag zu leisten.' },
    { id: 'work-satisfaction-future', text: 'Wenn ich an meine berufliche Zukunft in diesem Unternehmen denke, fühle ich mich sicher und blicke positiv nach vorne.' },
    { id: 'work-satisfaction-reward', text: 'Die Ausgewogenheit zwischen dem Aufwand, den ich für meine Arbeit betreibe, und der Wertschätzung oder den Entwicklungschancen, die ich zurückerhalte, stimmt für mich.' },
  ]},
];

const screening = { view: 'overview', sectionIndex: 0, answers: {}, validation: null };
const SCREENING_TOTAL = SCREENING_SECTIONS.reduce((n, s) => n + s.questions.length, 0);

function screeningAnsweredCount() { return Object.keys(screening.answers).length; }

function renderScreening() {
  const el = document.getElementById('screening-view');
  const extras = document.getElementById('profile-overview-extras');
  if (!el) return;
  extras.style.display = screening.view === 'overview' ? '' : 'none';

  if (screening.view === 'overview') {
    el.innerHTML = `<section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
      <div class="rounded-[28px] border border-slate-100 bg-white p-7 shadow-sm">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-teal-700">Alle 8 Wochen</p>
            <h2 class="mt-2 text-2xl font-bold text-slate-800">Screening-Bogen</h2>
            <p class="mt-3 max-w-2xl text-base leading-relaxed text-slate-500">
              Alle 8 Wochen fällig. Deine Antworten helfen dir, Entwicklungen über Zeit besser einzuordnen.
            </p>
          </div>
          <span class="inline-flex self-start rounded-full px-4 py-2 text-sm font-bold bg-amber-50 text-amber-700">fällig</span>
        </div>
        <div class="mt-6 grid grid-cols-[repeat(auto-fit,minmax(min(100%,13rem),1fr))] gap-4">
          <div class="rounded-2xl bg-slate-50 p-4">
            <p class="text-sm font-semibold text-slate-500">Dauer</p>
            <p class="mt-1 text-lg font-bold text-slate-800">ca. 5-8 Minuten</p>
          </div>
          <div class="rounded-2xl bg-slate-50 p-4">
            <p class="text-sm font-semibold text-slate-500">Belohnung</p>
            <p class="mt-1 text-lg font-bold text-slate-800">+${SCREENING_POINTS} Punkte</p>
          </div>
          <div class="rounded-2xl bg-slate-50 p-4">
            <p class="text-sm font-semibold text-slate-500">Status</p>
            <p class="mt-1 text-lg font-bold text-slate-800">jetzt fällig</p>
          </div>
        </div>
        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
          <button type="button" data-screening="intro"
                  class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-teal-600 px-6 py-3 text-base font-bold text-white shadow-lg shadow-teal-100 transition-colors hover:bg-teal-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">
            Screening starten
          </button>
        </div>
      </div>
      <aside class="rounded-[24px] border border-teal-100 bg-teal-50 p-6">
        <h3 class="text-lg font-bold text-teal-900">Privat für dich</h3>
        <p class="mt-2 text-sm leading-relaxed text-teal-800">
          Deine Antworten sind für deine persönliche Orientierung gedacht. Unternehmen sehen keine individuellen Antworten.
        </p>
        <p class="mt-4 text-sm leading-relaxed text-teal-800">
          Badges für regelmäßige Screenings folgen bald.
        </p>
      </aside>
    </section>`;
    return;
  }

  if (screening.view === 'intro') {
    el.innerHTML = `<section class="rounded-[28px] border border-slate-100 bg-white p-7 shadow-sm">
      <button type="button" data-screening="overview" class="mb-6 inline-flex min-h-11 items-center rounded-full px-4 text-sm font-semibold text-slate-500 hover:bg-slate-100">← Zurück</button>
      <p class="text-sm font-semibold uppercase tracking-wide text-teal-700">Screening-Bogen</p>
      <h2 class="mt-2 text-2xl font-bold text-slate-800">Ein geführter Blick auf deine Entwicklung</h2>
      <p class="mt-3 max-w-3xl text-base leading-relaxed text-slate-500">
        Beantworte die Aussagen auf einer Skala von 1 bis 5. Es geht um persönliche Orientierung und darum, Empfehlungen später besser auf deine Situation abzustimmen.
      </p>
      <div class="mt-6 grid grid-cols-[repeat(auto-fit,minmax(min(100%,16rem),1fr))] gap-4">
        <div class="rounded-2xl bg-slate-50 p-5">
          <p class="text-sm font-semibold text-slate-500">Umfang</p>
          <p class="mt-1 text-lg font-bold text-slate-800">${SCREENING_SECTIONS.length} Abschnitte · ${SCREENING_TOTAL} Fragen</p>
        </div>
        <div class="rounded-2xl bg-slate-50 p-5">
          <p class="text-sm font-semibold text-slate-500">Punkte</p>
          <p class="mt-1 text-lg font-bold text-slate-800">+${SCREENING_POINTS} nach Abschluss</p>
        </div>
        <div class="rounded-2xl bg-slate-50 p-5">
          <p class="text-sm font-semibold text-slate-500">Rhythmus</p>
          <p class="mt-1 text-lg font-bold text-slate-800">alle 8 Wochen</p>
        </div>
      </div>
      <p class="mt-6 rounded-2xl bg-teal-50 p-4 text-sm leading-relaxed text-teal-800">
        Deine Antworten sind für deine persönliche Orientierung gedacht. Unternehmen sehen keine individuellen Antworten.
      </p>
      <button type="button" data-screening="questions"
              class="mt-6 inline-flex min-h-11 items-center justify-center rounded-2xl bg-teal-600 px-6 py-3 text-base font-bold text-white shadow-lg shadow-teal-100 transition-colors hover:bg-teal-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">
        Abschnitt 1 starten
      </button>
    </section>`;
    return;
  }

  if (screening.view === 'questions') {
    const section = SCREENING_SECTIONS[screening.sectionIndex];
    const isLast = screening.sectionIndex === SCREENING_SECTIONS.length - 1;
    el.innerHTML = `<section class="rounded-[28px] border border-slate-100 bg-white p-6 shadow-sm sm:p-7">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <p class="text-sm font-semibold uppercase tracking-wide text-teal-700">Abschnitt ${screening.sectionIndex + 1} von ${SCREENING_SECTIONS.length}</p>
          <h2 class="mt-2 text-2xl font-bold text-slate-800">${section.title}</h2>
          <p class="mt-2 text-sm text-slate-500">Wähle pro Aussage den Wert, der aktuell am besten passt.</p>
        </div>
        <span class="inline-flex rounded-full bg-slate-100 px-4 py-2 text-sm font-bold text-slate-700">${screeningAnsweredCount()}/${SCREENING_TOTAL} beantwortet</span>
      </div>
      <div class="mt-6 h-2 overflow-hidden rounded-full bg-slate-100">
        <div class="h-full rounded-full bg-teal-500 transition-all" style="width:${Math.round((screeningAnsweredCount() / SCREENING_TOTAL) * 100)}%"></div>
      </div>
      <div class="mt-7 space-y-5">
        ${section.questions.map(q => `<div class="rounded-[20px] border border-slate-100 p-5">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <p class="text-base font-semibold leading-relaxed text-slate-800">${q.text}</p>
            ${q.reverseScored ? '<span class="inline-flex self-start rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">umgekehrt gewertet</span>' : ''}
          </div>
          <div class="mt-4 grid grid-cols-5 gap-2">
            ${SCALE_OPTIONS.map(o => `<button type="button" data-screening-answer="${q.id}:${o.value}"
                    aria-label="${q.text}: ${o.label}"
                    class="min-h-11 rounded-2xl border px-2 py-3 text-sm font-bold transition-all focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 ${screening.answers[q.id] === o.value ? 'border-teal-600 bg-teal-600 text-white shadow-sm' : 'border-slate-200 bg-white text-slate-700 hover:border-teal-300 hover:bg-teal-50'}">
              ${o.value}
            </button>`).join('')}
          </div>
          <div class="mt-3 grid grid-cols-5 gap-2 text-center text-xs font-semibold text-slate-500">
            ${SCALE_OPTIONS.map(o => `<span>${o.value === 1 || o.value === 3 || o.value === 5 ? o.label : ''}</span>`).join('')}
          </div>
        </div>`).join('')}
      </div>
      ${screening.validation ? `<p class="mt-5 rounded-2xl bg-amber-50 p-4 text-sm font-semibold text-amber-800">${screening.validation}</p>` : ''}
      <div class="mt-7 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
        <button type="button" data-screening-prev
                class="inline-flex min-h-11 items-center justify-center rounded-2xl border border-slate-200 px-6 py-3 text-base font-bold text-slate-700 transition-colors hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">
          ${screening.sectionIndex === 0 ? 'Zur Einführung' : 'Zurück'}
        </button>
        <button type="button" data-screening-next
                class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-teal-600 px-6 py-3 text-base font-bold text-white shadow-lg shadow-teal-100 transition-colors hover:bg-teal-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">
          ${isLast ? 'Screening abschließen' : 'Weiter'}
        </button>
      </div>
    </section>`;
    return;
  }

  // complete
  const nextDue = new Date();
  nextDue.setDate(nextDue.getDate() + 56);
  const nextDueText = new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(nextDue);
  el.innerHTML = `<section class="rounded-[28px] border border-teal-100 bg-white p-8 text-center shadow-sm">
    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-teal-50 text-3xl">✓</div>
    <h2 class="mt-5 text-2xl font-bold text-slate-800">Screening abgeschlossen</h2>
    <p class="mx-auto mt-3 max-w-2xl text-base leading-relaxed text-slate-500">
      Danke dir. Deine Antworten helfen, Empfehlungen künftig besser auf deine Situation abzustimmen.
    </p>
    <div class="mx-auto mt-6 grid max-w-2xl grid-cols-[repeat(auto-fit,minmax(min(100%,14rem),1fr))] gap-4">
      <div class="rounded-2xl bg-teal-50 p-5">
        <p class="text-sm font-semibold text-teal-700">Belohnung vorbereitet</p>
        <p class="mt-1 text-3xl font-bold text-teal-800">+${SCREENING_POINTS}</p>
        <p class="mt-1 text-sm text-teal-700">Punkte</p>
      </div>
      <div class="rounded-2xl bg-slate-50 p-5">
        <p class="text-sm font-semibold text-slate-500">Nächster Termin</p>
        <p class="mt-1 text-lg font-bold text-slate-800">${nextDueText}</p>
        <p class="mt-1 text-sm text-slate-500">in 8 Wochen</p>
      </div>
    </div>
    <p class="mx-auto mt-6 max-w-2xl rounded-2xl bg-slate-50 p-4 text-sm leading-relaxed text-slate-500">
      Badges für regelmäßige Screenings folgen bald. Für dieses MVP wird der Abschluss lokal als Demo-Zustand gespeichert.
    </p>
    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center">
      <button type="button" data-screening="overview"
              class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-teal-600 px-6 py-3 text-base font-bold text-white shadow-lg shadow-teal-100 transition-colors hover:bg-teal-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">
        Zum Profil
      </button>
      <a href="#dashboard" data-page="dashboard" class="inline-flex min-h-11 items-center justify-center rounded-2xl border border-slate-200 px-6 py-3 text-base font-bold text-slate-700 transition-colors hover:bg-slate-50">
        Zur Übersicht
      </a>
    </div>
  </section>`;
}

document.addEventListener('click', e => {
  const view = e.target.closest('[data-screening]');
  if (view) {
    screening.view = view.dataset.screening;
    if (screening.view === 'questions') screening.sectionIndex = 0;
    screening.validation = null;
    renderScreening();
    return;
  }
  const answer = e.target.closest('[data-screening-answer]');
  if (answer) {
    const [id, value] = answer.dataset.screeningAnswer.split(':');
    screening.answers[id] = Number(value);
    screening.validation = null;
    renderScreening();
    return;
  }
  if (e.target.closest('[data-screening-prev]')) {
    screening.validation = null;
    if (screening.sectionIndex === 0) screening.view = 'intro';
    else screening.sectionIndex -= 1;
    renderScreening();
    return;
  }
  if (e.target.closest('[data-screening-next]')) {
    const section = SCREENING_SECTIONS[screening.sectionIndex];
    const complete = section.questions.every(q => screening.answers[q.id] !== undefined);
    if (!complete) {
      screening.validation = 'Bitte beantworte alle Aussagen in diesem Abschnitt, bevor du fortfährst.';
      renderScreening();
      return;
    }
    screening.validation = null;
    if (screening.sectionIndex === SCREENING_SECTIONS.length - 1) screening.view = 'complete';
    else screening.sectionIndex += 1;
    renderScreening();
    return;
  }
});

renderScreening();
