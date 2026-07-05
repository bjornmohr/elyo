import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { EmployeeService, WellbeingEntry } from '../../services/employee.service';
import { CheckinDemoStorageService, DemoCheckin } from '../../services/checkin-demo-storage.service';
import { LOCATIONS, FREQUENT_SYMPTOMS, OTHER_SYMPTOMS } from '../checkin/checkin-state';

type FocusMetric = 'wohlbefinden' | 'energie' | 'stress';

/** One slot on the 14-day axis (index 0 = oldest, 13 = today). */
interface DayCell {
  index: number;
  date: Date;
  dateKey: string;
  label: string;
  entry: WellbeingEntry | null;
}

interface MetricMeta {
  key: FocusMetric;
  name: string;
  color: string;
  value: (entry: WellbeingEntry) => number | null;
}

interface MiniCard {
  key: FocusMetric;
  name: string;
  color: string;
  current: string;
  avg: string;
  deltaText: string;
  deltaGood: boolean;
  sparkPath: string;
  active: boolean;
}

interface ChartPoint {
  x: number;
  y: number;
  r: number;
  fill: string;
  dateKey: string;
}

interface ChartGrid {
  y: number;
  ty: number;
  label: string;
}

interface FocusChart {
  color: string;
  grids: ChartGrid[];
  linePath: string;
  areaPath: string;
  points: ChartPoint[];
  xLabels: Array<{ x: number; t: string }>;
  hasSel: boolean;
  selX: number;
  labelY: number;
}

interface CoreRow {
  label: string;
  valText: string;
  color: string;
  width: number;
}

interface SymptomRow {
  label: string;
  region: string;
  sevText: string;
  color: string;
  width: number;
}

interface DayReport {
  longDate: string;
  submitted: string;
  hasLocation: boolean;
  locIcon: string;
  locLabel: string;
  core: CoreRow[];
  hasSleep: boolean;
  sleepText: string;
  sleepRecText: string;
  sleepWidth: number;
  sleepEmpty: string;
  hasSymptoms: boolean;
  symptoms: SymptomRow[];
  symptomsEmpty: string;
  hasNote: boolean;
  note: string;
  noteEmpty: string;
}

@Component({
  selector: 'app-history',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="w-full max-w-[min(100%,76rem)]">
      @if (isLoading()) {
        <section class="grid grid-cols-[repeat(3,1fr)] gap-4" aria-label="Verlauf wird geladen">
          @for (item of loadingCards; track item) {
            <div class="min-h-40 animate-pulse rounded-[18px] border border-slate-100 bg-white p-[18px]">
              <div class="h-4 w-24 rounded bg-slate-100"></div>
              <div class="mt-6 h-8 w-20 rounded bg-slate-100"></div>
              <div class="mt-5 h-11 w-full rounded bg-slate-100"></div>
            </div>
          }
        </section>
      } @else if (loadError()) {
        <section class="rounded-[24px] border border-amber-100 bg-amber-50 p-6">
          <h2 class="text-lg font-bold text-amber-900">Verlauf konnte nicht geladen werden</h2>
          <p class="mt-2 text-sm leading-relaxed text-amber-800">
            Bitte versuche es später erneut. Es wurden keine lokalen Ersatzwerte angezeigt.
          </p>
        </section>
      } @else if (!entries().length) {
        <section class="rounded-[28px] border border-dashed border-slate-200 bg-white p-8 text-center sm:p-12">
          <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-teal-50 text-2xl">✓</div>
          <h2 class="text-xl font-bold text-slate-800">Noch kein Verlauf verfügbar</h2>
          <p class="mx-auto mt-2 max-w-md text-base leading-relaxed text-slate-500">
            Sobald du ein paar Check-ins gemacht hast, siehst du hier deine Entwicklung.
          </p>
          <a routerLink="/employee/checkin"
             class="mt-6 inline-flex min-h-11 items-center justify-center rounded-2xl bg-teal-600 px-8 py-3 text-base font-bold text-white shadow-lg shadow-teal-100 transition-colors hover:bg-teal-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">
            Check-in starten
          </a>
        </section>
      } @else {
        <!-- 1. Kopfzeile -->
        <header class="mb-[22px]">
          <h1 class="text-[28px] font-bold leading-tight text-[#1e293b]" style="font-family:var(--font-display)">Dein Check-in Verlauf</h1>
          <p class="mt-1 text-[15px] text-[#64748b]">Letzte 14 Tage · Trend wählen, Tag anklicken für alle Details</p>
        </header>

        <!-- 2. Drei Mini-Trend-Karten -->
        <section class="mb-[22px] grid grid-cols-1 gap-4 sm:grid-cols-3" aria-label="Trend wählen">
          @for (card of miniCards(); track card.key) {
            <button type="button"
                    (click)="setFocus(card.key)"
                    [attr.aria-pressed]="card.active"
                    class="rounded-[18px] bg-white p-[18px] text-left transition-all focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600"
                    [style.border]="card.active ? ('2px solid ' + card.color) : '1px solid #f1f5f9'"
                    [style.boxShadow]="card.active ? '0 6px 18px rgba(0,0,0,.06)' : 'none'">
              <div class="flex items-center justify-between gap-2">
                <span class="text-[14px] font-semibold text-[#475569]">{{ card.name }}</span>
                <span class="whitespace-nowrap rounded-full px-2 py-0.5 text-[13px] font-semibold"
                      [style.color]="card.deltaGood ? '#0f766e' : '#dc2626'"
                      [style.background]="card.deltaGood ? '#f0fdfa' : '#fef2f2'">{{ card.deltaText }}</span>
              </div>
              <div class="my-[10px] flex items-baseline gap-1.5">
                <span class="text-[34px] font-extrabold leading-none" style="font-family:var(--font-display)" [style.color]="card.color">{{ card.current }}</span>
                <span class="text-[13px] text-[#94a3b8]">/ 5 · Ø {{ card.avg }}</span>
              </div>
              <svg viewBox="0 0 220 44" class="block h-11 w-full" preserveAspectRatio="none" aria-hidden="true">
                <path [attr.d]="card.sparkPath" fill="none" [attr.stroke]="card.color" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
              </svg>
            </button>
          }
        </section>

        <!-- 3. Fokus-Kurve -->
        <p class="mb-3 text-[14px] font-semibold text-[#334155]">{{ focusMetricName() }} über die Zeit</p>
        <div class="mb-[24px] rounded-[20px] border border-[#f1f5f9] bg-white px-4 pb-2 pt-5">
          <svg viewBox="0 0 940 220" class="block h-auto w-full overflow-visible" role="img" [attr.aria-label]="focusMetricName() + ' über die letzten 14 Tage'">
            <defs>
              <linearGradient [attr.id]="gradientId" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" [attr.stop-color]="focusChart().color" stop-opacity="0.22"></stop>
                <stop offset="100%" [attr.stop-color]="focusChart().color" stop-opacity="0"></stop>
              </linearGradient>
            </defs>
            @for (g of focusChart().grids; track g.label) {
              <line x1="0" x2="940" [attr.y1]="g.y" [attr.y2]="g.y" stroke="#f1f5f9" stroke-width="1"></line>
              <text x="0" [attr.y]="g.ty" font-size="11" fill="#cbd5e1" font-family="DM Sans">{{ g.label }}</text>
            }
            @if (focusChart().hasSel) {
              <line [attr.x1]="focusChart().selX" [attr.x2]="focusChart().selX" y1="0" y2="220" [attr.stroke]="focusChart().color" stroke-width="1" stroke-dasharray="4 4" opacity="0.5"></line>
            }
            <path [attr.d]="focusChart().areaPath" [attr.fill]="'url(#' + gradientId + ')'"></path>
            <path [attr.d]="focusChart().linePath" fill="none" [attr.stroke]="focusChart().color" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></path>
            @for (p of focusChart().points; track p.dateKey) {
              <circle [attr.cx]="p.x" [attr.cy]="p.y" [attr.r]="p.r" [attr.fill]="p.fill" [attr.stroke]="focusChart().color" stroke-width="2.5"
                      role="button" tabindex="0"
                      [attr.aria-label]="'Tag ' + p.dateKey + ' auswählen'"
                      class="cursor-pointer outline-offset-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-teal-600"
                      (click)="selectDay(p.dateKey)"
                      (keydown.enter)="selectDay(p.dateKey)"
                      (keydown.space)="$event.preventDefault(); selectDay(p.dateKey)"></circle>
            }
            @for (xl of focusChart().xLabels; track xl.x) {
              <text [attr.x]="xl.x" [attr.y]="focusChart().labelY" font-size="11" fill="#94a3b8" text-anchor="middle" font-family="DM Sans">{{ xl.t }}</text>
            }
          </svg>
        </div>

        <!-- 4. Tagesreport -->
        @if (report(); as rep) {
          <div class="rounded-[20px] border border-[#e5ded3] p-6" style="background:hsl(40,20%,98%)">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
              <div class="flex flex-wrap items-baseline gap-3">
                <h2 class="text-[22px] font-bold text-[#1e293b]" style="font-family:var(--font-display)">{{ rep.longDate }}</h2>
                <span class="text-[14px] text-[#94a3b8]">{{ rep.submitted }}</span>
              </div>
              @if (rep.hasLocation) {
                <span class="inline-flex items-center gap-2 rounded-full border border-[#f1f5f9] bg-white px-3 py-[5px] text-[14px] font-semibold text-[#334155]">
                  {{ rep.locIcon }} {{ rep.locLabel }}
                </span>
              }
            </div>

            <div class="grid grid-cols-[repeat(auto-fit,minmax(280px,1fr))] gap-4">
              <!-- Kernwerte -->
              <div class="rounded-[16px] border border-[#f1f5f9] bg-white p-[18px]">
                <p class="mb-[14px] text-[13px] font-bold uppercase tracking-[0.04em] text-[#94a3b8]">Kernwerte</p>
                <div class="flex flex-col gap-[14px]">
                  @for (m of rep.core; track m.label) {
                    <div>
                      <div class="mb-1.5 flex items-baseline justify-between">
                        <span class="text-[14px] font-semibold text-[#475569]">{{ m.label }}</span>
                        <span class="text-[13px] text-[#94a3b8]">{{ m.valText }}</span>
                      </div>
                      <div class="h-2 overflow-hidden rounded-full bg-[#f1f5f9]">
                        <div class="h-full rounded-full" [style.width.%]="m.width" [style.background]="m.color"></div>
                      </div>
                    </div>
                  }
                </div>
              </div>

              <!-- Schlaf & Erholung -->
              <div class="rounded-[16px] border border-[#f1f5f9] bg-white p-[18px]">
                <p class="mb-[14px] text-[13px] font-bold uppercase tracking-[0.04em] text-[#94a3b8]">Schlaf &amp; Erholung</p>
                @if (rep.hasSleep) {
                  <p class="mb-1 text-[20px] font-bold text-[#0891b2]" style="font-family:var(--font-display)">{{ rep.sleepText }}</p>
                  <div class="mb-1.5 mt-3 flex items-baseline justify-between">
                    <span class="text-[14px] font-semibold text-[#475569]">Erholung</span>
                    <span class="text-[13px] text-[#94a3b8]">{{ rep.sleepRecText }}</span>
                  </div>
                  <div class="h-2 overflow-hidden rounded-full bg-[#f1f5f9]">
                    <div class="h-full rounded-full bg-[#0891b2]" [style.width.%]="rep.sleepWidth"></div>
                  </div>
                } @else {
                  <p class="text-[14px] leading-relaxed text-[#cbd5e1]">{{ rep.sleepEmpty }}</p>
                }
              </div>

              <!-- Körpersignale -->
              <div class="rounded-[16px] border border-[#f1f5f9] bg-white p-[18px]">
                <p class="mb-[14px] text-[13px] font-bold uppercase tracking-[0.04em] text-[#94a3b8]">Körpersignale</p>
                @if (rep.hasSymptoms) {
                  <div class="flex flex-col gap-[14px]">
                    @for (s of rep.symptoms; track s.label) {
                      <div>
                        <div class="mb-0.5 flex items-baseline justify-between">
                          <span class="text-[14px] font-semibold text-[#334155]">{{ s.label }}</span>
                          <span class="text-[13px] text-[#94a3b8]">{{ s.sevText }}</span>
                        </div>
                        <p class="mb-1.5 text-[13px] text-[#94a3b8]">{{ s.region }}</p>
                        <div class="h-2 overflow-hidden rounded-full bg-[#f1f5f9]">
                          <div class="h-full rounded-full" [style.width.%]="s.width" [style.background]="s.color"></div>
                        </div>
                      </div>
                    }
                  </div>
                } @else {
                  <p class="text-[14px] leading-relaxed text-[#cbd5e1]">{{ rep.symptomsEmpty }}</p>
                }
              </div>

              <!-- Notiz -->
              <div class="col-[1/-1] rounded-[16px] border border-[#f1f5f9] bg-white p-[18px]">
                <p class="mb-2.5 text-[13px] font-bold uppercase tracking-[0.04em] text-[#94a3b8]">Notiz</p>
                @if (rep.hasNote) {
                  <p class="text-[15px] italic leading-relaxed text-[#334155]">{{ rep.note }}</p>
                } @else {
                  <p class="text-[14px] leading-relaxed text-[#cbd5e1]">{{ rep.noteEmpty }}</p>
                }
              </div>
            </div>
          </div>
        }
      }
    </div>
  `
})
export class HistoryComponent implements OnInit {
  private employeeService = inject(EmployeeService);
  private demoStorage = inject(CheckinDemoStorageService);

  entries = signal<WellbeingEntry[]>([]);
  isLoading = signal(true);
  loadError = signal(false);
  focusMetric = signal<FocusMetric>('wohlbefinden');
  selectedDay = signal<string | null>(null);

  readonly loadingCards = [1, 2, 3];
  readonly gradientId = 'history-focus-gradient';

  private readonly dayCount = 14;
  private readonly today = new Date();

  private readonly metrics: Record<FocusMetric, MetricMeta> = {
    wohlbefinden: { key: 'wohlbefinden', name: 'Wohlbefinden', color: '#0d9488', value: e => e.score },
    energie: { key: 'energie', name: 'Energie', color: '#4f46e5', value: e => e.energy },
    stress: { key: 'stress', name: 'Stress', color: '#e11d48', value: e => e.stress },
  };

  private readonly locationMap = new Map(LOCATIONS.map(l => [l.value, l]));
  private readonly symptomLabels = new Map(
    [...FREQUENT_SYMPTOMS, ...OTHER_SYMPTOMS].map(s => [s.key, s.label])
  );

  /** 14-day window, oldest → newest, entry attached where a check-in exists. */
  days = computed<DayCell[]>(() => {
    const byDay = new Map<string, WellbeingEntry>();
    for (const entry of this.entries()) {
      const key = this.dateKey(entry.createdAt);
      const existing = byDay.get(key);
      // Keep the most recent entry per calendar day.
      if (key && (!existing || new Date(entry.createdAt) > new Date(existing.createdAt))) {
        byDay.set(key, entry);
      }
    }

    const start = this.addDays(this.startOfDay(this.today), -(this.dayCount - 1));
    return Array.from({ length: this.dayCount }, (_, index) => {
      const date = this.addDays(start, index);
      const dateKey = this.dateKey(date);
      return { index, date, dateKey, label: this.formatDateLabel(date), entry: byDay.get(dateKey) ?? null };
    });
  });

  focusMetricName = computed(() => this.metrics[this.focusMetric()].name);

  miniCards = computed<MiniCard[]>(() => {
    const days = this.days();
    return (['wohlbefinden', 'energie', 'stress'] as FocusMetric[]).map(key => {
      const meta = this.metrics[key];
      const points = days.filter(d => d.entry && this.metricValue(d.entry, key) !== null);
      const values = points.map(d => this.metricValue(d.entry as WellbeingEntry, key) as number);
      const current = values.length ? values[values.length - 1] : 0;
      const avg = values.length ? values.reduce((a, b) => a + b, 0) / values.length : 0;
      const delta = this.deltaFor(key);
      return {
        key,
        name: meta.name,
        color: meta.color,
        current: this.fmt(current),
        avg: this.fmt(avg),
        deltaText: delta.text,
        deltaGood: delta.good,
        sparkPath: this.buildSparkPath(key),
        active: key === this.focusMetric(),
      };
    });
  });

  focusChart = computed<FocusChart>(() => {
    const key = this.focusMetric();
    const meta = this.metrics[key];
    const days = this.days();
    const W = 940, H = 220, padT = 14, padB = 26, padL = 22, padR = 6;
    const innerW = W - padL - padR, innerH = H - padT - padB;
    const N = this.dayCount;
    const x = (i: number) => +(padL + (i / (N - 1)) * innerW).toFixed(1);
    const y = (v: number) => +(padT + (1 - (v - 1) / 4) * innerH).toFixed(1);
    const baseY = +(padT + innerH).toFixed(1);
    const selected = this.selectedDay();

    const grids: ChartGrid[] = [1, 2, 3, 4, 5].map(v => ({ y: y(v), ty: y(v) + 3, label: String(v) }));

    const points: ChartPoint[] = days
      .filter(d => d.entry && this.metricValue(d.entry, key) !== null)
      .map(d => {
        const v = this.metricValue(d.entry as WellbeingEntry, key) as number;
        const sel = d.dateKey === selected;
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

    const xLabels = days.filter(d => d.index % 3 === 0).map(d => ({ x: x(d.index), t: d.label }));
    const selDay = days.find(d => d.dateKey === selected && d.entry);

    return {
      color: meta.color,
      grids,
      linePath: linePath.trim(),
      areaPath,
      points,
      xLabels,
      hasSel: !!selDay,
      selX: selDay ? x(selDay.index) : 0,
      labelY: H - 6,
    };
  });

  report = computed<DayReport | null>(() => {
    const key = this.selectedDay();
    if (!key) return null;
    const day = this.days().find(d => d.dateKey === key);
    const entry = day?.entry ?? null;
    if (!entry) return null;

    const demo = this.demoStorage.load(key);
    const loc = demo ? this.locationMap.get(demo.location) : undefined;

    const mood = demo ? demo.mood : entry.mood;
    const energy = demo ? demo.energy : entry.energy;
    const stress = demo ? demo.stress : entry.stress;

    const core: CoreRow[] = [
      this.coreRow('Stimmung', mood, this.getMoodLabel, '#0d9488'),
      this.coreRow('Energie', energy, this.getEnergyLabel, '#4f46e5'),
      this.coreRow('Stress', stress, this.getStressLabel, '#e11d48'),
    ];

    const symptoms: SymptomRow[] = (demo?.symptoms ?? []).map(s => ({
      label: this.symptomLabels.get(s.key) ?? s.key,
      region: s.region,
      sevText: `Stärke ${s.severity}/5`,
      color: this.severityColor(s.severity),
      width: (s.severity / 5) * 100,
    }));

    return {
      longDate: this.formatLongDate(entry.createdAt),
      submitted: this.formatSubmittedTime(entry),
      hasLocation: !!loc,
      locIcon: loc?.icon ?? '',
      locLabel: loc?.label ?? '',
      core,
      hasSleep: !!demo?.sleep,
      sleepText: demo?.sleep ? `${this.fmt(demo.sleep.hours)} h geschlafen` : '',
      sleepRecText: demo?.sleep ? `Erholung ${demo.sleep.recovery}/5` : '',
      sleepWidth: demo?.sleep ? (demo.sleep.recovery / 5) * 100 : 0,
      sleepEmpty: 'Nur bei niedriger Energie abgefragt — an diesem Tag nicht erfasst.',
      hasSymptoms: symptoms.length > 0,
      symptoms,
      symptomsEmpty: 'Keine Körpersignale gemeldet.',
      hasNote: !!entry.notes,
      note: entry.notes ? `„${entry.notes}"` : '',
      noteEmpty: 'Keine Notiz hinterlegt.',
    };
  });

  ngOnInit() {
    this.employeeService.getHistory().subscribe({
      next: entries => {
        this.entries.set(entries);
        this.loadError.set(false);
        this.selectedDay.set(this.latestDayKey(entries));
      },
      error: () => {
        this.loadError.set(true);
        this.entries.set([]);
        this.isLoading.set(false);
      },
      complete: () => {
        this.isLoading.set(false);
      }
    });
  }

  setFocus(metric: FocusMetric): void {
    this.focusMetric.set(metric);
  }

  selectDay(dateKey: string): void {
    this.selectedDay.set(dateKey);
  }

  // ── Metric helpers ──

  private metricValue(entry: WellbeingEntry, key: FocusMetric): number | null {
    const v = this.metrics[key].value(entry);
    return v !== null && Number.isFinite(v) ? v : null;
  }

  /** Ø(this week: newest 7 days) − Ø(previous 7 days) on the 14-day axis. */
  private deltaFor(key: FocusMetric): { good: boolean; text: string } {
    const days = this.days();
    const valuesIn = (from: number, to: number) =>
      days.filter(d => d.index >= from && d.index <= to && d.entry && this.metricValue(d.entry, key) !== null)
        .map(d => this.metricValue(d.entry as WellbeingEntry, key) as number);

    const thisW = valuesIn(7, 13);
    const lastW = valuesIn(0, 6);
    const avg = (a: number[]) => (a.length ? a.reduce((x, y) => x + y, 0) / a.length : 0);

    if (!thisW.length || !lastW.length) {
      return { good: true, text: '▬ ±0,0' };
    }

    const delta = avg(thisW) - avg(lastW);
    const good = key === 'stress' ? delta < 0 : delta > 0;
    const arrow = delta > 0.05 ? '▲' : delta < -0.05 ? '▼' : '▬';
    const sign = delta >= 0 ? '+' : '−';
    return { good, text: `${arrow} ${sign}${this.fmt(Math.abs(delta))}` };
  }

  private buildSparkPath(key: FocusMetric): string {
    const W = 220, H = 44, pad = 4, maxI = this.dayCount - 1;
    const x = (i: number) => +((i / maxI) * W).toFixed(1);
    const y = (v: number) => +(pad + (1 - (v - 1) / 4) * (H - pad * 2)).toFixed(1);
    const points = this.days().filter(d => d.entry && this.metricValue(d.entry, key) !== null);
    let path = '';
    points.forEach((d, k) => {
      const v = this.metricValue(d.entry as WellbeingEntry, key) as number;
      path += (k === 0 ? 'M' : 'L') + x(d.index) + ',' + y(v) + ' ';
    });
    return path.trim();
  }

  private coreRow(label: string, value: number | null, labelFn: (v: number) => string, color: string): CoreRow {
    const valid = value !== null && Number.isFinite(value);
    return {
      label,
      color,
      valText: valid ? `${value} / 5 · ${labelFn.call(this, value as number)}` : 'nicht erfasst',
      width: valid ? ((value as number) / 5) * 100 : 0,
    };
  }

  private severityColor(v: number): string {
    return v >= 4 ? '#dc2626' : v >= 3 ? '#d97706' : '#f59e0b';
  }

  // ── Formatting ──

  private fmt(value: number): string {
    return value.toFixed(1).replace('.', ',');
  }

  private formatDateLabel(date: Date): string {
    return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit' }).format(date);
  }

  private formatLongDate(value: string | Date): string {
    return new Intl.DateTimeFormat('de-DE', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    }).format(new Date(value));
  }

  private formatSubmittedTime(entry: WellbeingEntry): string {
    const time = new Intl.DateTimeFormat('de-DE', { hour: '2-digit', minute: '2-digit' }).format(new Date(entry.createdAt));
    return `Eingetragen um ${time} Uhr`;
  }

  getMoodLabel(value: number): string {
    if (value >= 4) return 'positiv';
    if (value >= 3) return 'ausgeglichen';
    return 'niedrig';
  }

  getEnergyLabel(value: number): string {
    if (value >= 4) return 'hoch';
    if (value >= 3) return 'stabil';
    return 'niedrig';
  }

  getStressLabel(value: number): string {
    if (value >= 4) return 'hoch';
    if (value >= 3) return 'mittel';
    return 'niedrig';
  }

  // ── Date utilities ──

  private latestDayKey(entries: WellbeingEntry[]): string | null {
    let latest: WellbeingEntry | null = null;
    for (const entry of entries) {
      if (!latest || new Date(entry.createdAt) > new Date(latest.createdAt)) latest = entry;
    }
    return latest ? this.dateKey(latest.createdAt) : null;
  }

  private startOfDay(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
  }

  private addDays(date: Date, days: number): Date {
    const next = new Date(date);
    next.setDate(next.getDate() + days);
    return next;
  }

  private dateKey(value: string | Date): string {
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    const day = `${date.getDate()}`.padStart(2, '0');
    return `${date.getFullYear()}-${month}-${day}`;
  }
}
