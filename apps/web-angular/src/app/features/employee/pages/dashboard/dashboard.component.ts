import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';
import { RouterLink } from '@angular/router';
import { CheckinDemoStorageService, DemoCheckin } from '../../services/checkin-demo-storage.service';
import { DashboardData, DashboardLever, EmployeeService, MetricAggregate } from '../../services/employee.service';
import { LabMarker } from '../../models/lab-marker.model';
import { categoryIcon } from '../../shared/measure-category-icons';

type LeverSafeModeState = 'paused' | 'gentle' | null;

interface LeverCard extends DashboardLever {
  icon: ReturnType<typeof categoryIcon>;
  iconSvg: SafeHtml;
  safeModeState: LeverSafeModeState;
}

/**
 * 1a — weekly trend dashboard: hero score, four metric tiles, body signals,
 * levers, and the Schonmodus banner. Wellbeing aggregates are real (1-5 from
 * daily entries); sleep/bodySignals/healthFlag/levers come from the demo
 * provider and are null in prod mode — every block renders null-safe.
 */
@Component({
  selector: 'app-employee-dashboard',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="w-full max-w-[min(100%,76rem)] space-y-7">
      <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <h1 class="text-[30px] font-bold leading-tight text-slate-800">Hallo!</h1>
          <p class="mt-1 text-base text-slate-500">{{ today | date:'EEEE, d. MMMM' }}</p>
        </div>
        <div class="flex flex-wrap gap-2.5 sm:justify-end">
          <span class="inline-flex items-center gap-1.5 rounded-full bg-teal-50 px-4 py-2 text-sm font-semibold text-teal-700">
            {{ data()?.points ?? 0 }} Punkte
          </span>
          <span class="inline-flex items-center gap-1.5 rounded-full bg-orange-50 px-4 py-2 text-sm font-semibold text-orange-600">
            🔥 {{ data()?.streak ?? 0 }} Tage
          </span>
        </div>
      </header>

      @if (safeModeFlag(); as flag) {
        @if (shouldShowSafeModeBanner()) {
          <div class="rounded-2xl border border-amber-100 bg-amber-50 px-5 py-4">
            <div class="flex items-center gap-2.5">
              <span class="text-[17px] font-bold leading-tight text-amber-800">{{ flag.label }}</span>
              <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-amber-700">{{ safeModeBadge(flag.badge) }}</span>
            </div>
            <p class="text-sm text-amber-800 mt-1">{{ flag.note }}</p>
          </div>
        }
      }

      @if (data()?.wellbeing; as wellbeing) {
        <div class="rounded-[26px] p-8 text-white" style="background: linear-gradient(135deg, #14b8a6, #0d9488)">
          <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <p class="text-sm font-semibold uppercase tracking-wider text-teal-50">Dein Wohlbefinden diese Woche</p>
              <div class="flex items-baseline gap-2.5 mt-2.5" title="Abgeleiteter Wert — berechnet aus deinen 3 Werten (Stimmung, Energie, Stress)">
                <span class="text-[clamp(3rem,5vw,68px)] font-black leading-none">{{ format(wellbeing.current) }}</span>
                <span class="text-teal-50 text-lg">von {{ wellbeing.scale }}</span>
              </div>
              @if (wellbeing.delta !== null) {
                <span class="mt-3 inline-flex items-center rounded-full bg-white/20 px-3.5 py-1.5 text-sm font-semibold">
                  {{ wellbeing.delta >= 0 ? '+' : '' }}{{ format(wellbeing.delta) }} vs. letzte Woche
                </span>
              }
              <p class="text-[13px] text-teal-50 mt-2.5">berechnet aus deinen 3 Werten</p>
            </div>
            @if (sparklinePoints(); as points) {
              <svg viewBox="0 0 140 48" class="h-[4.25rem] w-[12.5rem] max-w-full flex-shrink-0" aria-hidden="true">
                <polyline [attr.points]="points" fill="none" stroke="rgba(255,255,255,0.92)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            }
          </div>
        </div>
      }

      @if (data()?.metrics; as metrics) {
        <div class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,16.25rem),1fr))] gap-5">
          @for (tile of metricTiles(); track tile.label) {
            <div class="bg-white rounded-[20px] border border-slate-100 p-[22px]">
              <p class="text-[13px] font-semibold uppercase tracking-wide text-slate-500">{{ tile.label }}</p>
              <div class="flex items-baseline gap-1.5 mt-2">
                <span class="text-[34px] font-bold leading-tight text-slate-800">{{ tile.value }}</span>
                <span class="text-[15px] text-slate-500">{{ tile.unit }}</span>
              </div>
              <div class="h-2 rounded-full bg-slate-100 mt-3 overflow-hidden">
                <div class="h-full rounded-full" [class]="tile.barClass" [style.width.%]="tile.barPercent"></div>
              </div>
              <p class="text-[13px] text-slate-500 mt-2">{{ tile.subline }}</p>
            </div>
          }
        </div>
      }

      <div class="bg-white rounded-[20px] border border-slate-100 p-6">
        <div class="flex items-start justify-between gap-4 mb-4">
          <div class="flex items-center gap-3">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[13px] bg-teal-50 text-teal-700">
              <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path></svg>
            </span>
            <div>
              <h3 class="text-[17px] font-semibold text-slate-800">Laborwerte</h3>
              <p class="text-[13px] text-slate-500 mt-0.5">Persönliche Orientierung</p>
            </div>
          </div>
          <a routerLink="/employee/lab-markers" class="inline-flex min-h-11 items-center text-sm font-semibold text-teal-600 hover:text-teal-700 whitespace-nowrap">Ansehen →</a>
        </div>

        @if (labMarkersError()) {
          <div class="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3 text-sm text-slate-500">
            Laborwerte können gerade nicht geladen werden.
          </div>
        } @else if (highlightedLabMarkers().length > 0) {
          <p class="mb-4 text-sm text-slate-600">
            <span class="font-bold text-slate-800">{{ highlightedLabMarkers().length }} Marker</span> außerhalb des Orientierungsbereichs.
          </p>
          <div class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,11.25rem),1fr))] gap-3.5">
            @for (marker of highlightedLabMarkers().slice(0, 3); track marker.markerKey) {
              <a routerLink="/employee/lab-markers" class="rounded-2xl border border-slate-100 bg-slate-50 p-4 transition-colors hover:border-teal-100">
                <div class="flex items-center gap-2">
                  <span class="h-2.5 w-2.5 rounded-full" [ngClass]="labDotClass(marker.status)"></span>
                  <span class="text-sm font-semibold text-slate-700">{{ marker.name }}</span>
                </div>
                <div class="mt-2">
                  <span class="text-2xl font-bold text-slate-800">{{ formatLabValue(marker.value) }}</span>
                  <span class="ml-1 text-sm text-slate-500">{{ marker.unit }}</span>
                </div>
                <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-bold" [ngClass]="labStatusClass(marker.status)">{{ marker.status }}</span>
              </a>
            }
          </div>
        } @else {
          <div class="flex items-center gap-3 rounded-2xl border border-teal-100 bg-teal-50 px-4 py-4">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-teal-100 text-sm font-bold text-teal-700">✓</span>
            <p class="text-sm text-teal-800"><span class="font-semibold">Alles im Orientierungsbereich.</span> Details findest du in der Laborwerte-Ansicht.</p>
          </div>
        }

        <div class="mt-4 flex items-center gap-2 border-t border-slate-100 pt-3 text-[13px] text-slate-500">
          <span>🔒</span>
          <span>Nur für dich sichtbar. Arbeitgeber sehen keine individuellen Werte.</span>
        </div>
      </div>

      <div class="bg-white rounded-[20px] border border-slate-100 p-6 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
        <div>
          <p class="font-semibold text-slate-800 text-[17px]">
            {{ checkinDone() ? 'Check-in für heute erledigt ✓' : 'Dein Check-in wartet' }}
          </p>
          <p class="text-sm text-slate-500 mt-1">
            {{ checkinDone() ? 'Stark — bis morgen!' : 'Wie fühlst du dich heute? Dauert unter einer Minute.' }}
          </p>
        </div>
        <a routerLink="/employee/checkin"
           class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-teal-600 px-6 py-3.5 text-base font-semibold text-white hover:bg-teal-700 transition-colors whitespace-nowrap sm:w-auto">
          Check-in starten
        </a>
      </div>

      @if (data()?.bodySignals; as signals) {
        <div class="bg-white rounded-[20px] border border-slate-100 p-7">
          <h3 class="text-sm font-bold tracking-wide uppercase text-teal-700 mb-5">Körpersignale</h3>
          <div class="space-y-3.5">
            @for (signal of signals; track signal.label) {
              <div class="flex flex-col gap-1.5 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                <span class="text-base text-slate-700">{{ signal.label }}</span>
                <span class="inline-flex flex-wrap items-center gap-2 text-sm text-slate-500 sm:justify-end">
                  {{ signal.thisWeekDays }}× diese Woche
                  <span class="text-slate-300">·</span>
                  {{ signal.lastWeekDays }}× letzte Woche
                  <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold" [class]="trendChipClass(signal.trend)">{{ trendLabel(signal.trend) }}</span>
                </span>
              </div>
            }
          </div>
        </div>
      }

      @if (leverCards(); as levers) {
        @if (levers.length > 0) {
          <div>
            <div class="flex flex-col gap-3 mb-5 sm:flex-row sm:items-center sm:justify-between">
              <div class="flex flex-wrap items-center gap-3">
                <h3 class="text-xl font-bold text-slate-800">Deine Hebel für diese Woche</h3>
                <span class="rounded-full bg-teal-50 px-3 py-1.5 text-[13px] font-semibold text-teal-700">aus deinen Check-ins</span>
              </div>
              <a routerLink="/employee/measures" class="inline-flex min-h-11 items-center text-sm font-semibold text-teal-600 hover:text-teal-700 whitespace-nowrap">Alle Maßnahmen →</a>
            </div>
            <div class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,21.25rem),1fr))] gap-5">
              @for (lever of levers; track lever.title) {
                <a [routerLink]="['/employee/measures', lever.measureId]"
                   class="group flex flex-col rounded-[20px] border p-6 transition-all"
                   [ngClass]="leverCardClass(lever)">
                  <div class="flex items-center justify-between mb-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-[14px]" [style.background]="lever.icon.bg">
                      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" [attr.stroke]="lever.icon.color"
                           stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                           [innerHTML]="lever.iconSvg"></svg>
                    </span>
                    <span class="rounded-full px-3 py-1.5 text-[13px] font-bold"
                          [ngClass]="leverBadgeClass(lever)"
                          [style.color]="lever.safeModeState ? null : lever.icon.color"
                          [style.background]="lever.safeModeState ? null : lever.icon.bg">
                      {{ leverBadge(lever) }}
                    </span>
                  </div>
                  <span class="text-[17px] font-semibold text-slate-800">{{ lever.title }}</span>
                  <p class="text-sm text-slate-500 leading-relaxed mt-2 mb-4">{{ lever.reason }}</p>
                  @if (leverSafeModeNote(lever); as note) {
                    <p class="mb-4 rounded-2xl px-4 py-3 text-sm font-semibold leading-relaxed"
                       [ngClass]="lever.safeModeState === 'paused' ? 'bg-amber-50 text-amber-800' : 'bg-teal-50 text-teal-700'">
                      {{ note }}
                    </p>
                  }
                  <div class="mt-auto flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <span class="text-[13px] font-semibold" [ngClass]="lever.safeModeState === 'paused' ? 'text-amber-700' : 'text-teal-600'">{{ lever.expected }}</span>
                    <span class="text-sm font-semibold" [ngClass]="lever.safeModeState === 'paused' ? 'text-slate-600' : 'text-teal-600 group-hover:text-teal-700'">
                      {{ leverCtaLabel(lever) }} →
                    </span>
                  </div>
                </a>
              }
            </div>
          </div>
        }
      }
    </div>
  `
})
export class DashboardComponent implements OnInit {
  private employeeService = inject(EmployeeService);
  private checkinStorage = inject(CheckinDemoStorageService);
  private sanitizer = inject(DomSanitizer);

  data = signal<DashboardData | null>(null);
  labMarkers = signal<LabMarker[]>([]);
  labMarkersError = signal(false);
  today = new Date();

  /** Levers enriched with their category icon + pre-sanitised glyph markup. */
  leverCards = computed<LeverCard[]>(() =>
    (this.data()?.levers ?? []).map(lever => {
      const icon = categoryIcon(lever.category);
      return {
        ...lever,
        icon,
        iconSvg: this.sanitizer.bypassSecurityTrustHtml(icon.svg) as SafeHtml,
        safeModeState: this.measureSafeModeState(lever),
      };
    })
  );

  /** Local demo check-in (Handoff 02) also counts as done — client-side polish only. */
  demoCheckinToday = computed(() => this.checkinStorage.loadToday());

  highlightedLabMarkers = computed(() => this.labMarkers().filter(marker => marker.isHighlighted));

  hasCompletedTodayCheckIn = computed(() =>
    (this.data()?.todayCheckinCompleted ?? false) || this.demoCheckinToday() !== null
  );

  checkinDone = computed(() => this.hasCompletedTodayCheckIn());

  safeModeFlag = computed(() => {
    const flag = this.data()?.healthFlag ?? null;
    return flag?.state === 'caution' ? flag : null;
  });

  isSafeModeActive = computed(() => {
    const demoCheckin = this.demoCheckinToday();
    if (demoCheckin) {
      return this.demoCheckinTriggersSafeMode(demoCheckin);
    }

    // When no demo check-in exists, the API health flag is the only available
    // recovery-mode signal. It is still gated by hasCompletedTodayCheckIn.
    return this.safeModeFlag() !== null;
  });

  shouldShowSafeModeBanner = computed(() =>
    this.hasCompletedTodayCheckIn() && this.isSafeModeActive()
  );

  ngOnInit() {
    this.employeeService.getDashboard().subscribe(data => {
      this.data.set(data);
    });
    this.employeeService.getLabMarkers().subscribe({
      next: markers => {
        this.labMarkers.set(markers);
        this.labMarkersError.set(false);
      },
      error: () => this.labMarkersError.set(true),
    });
  }

  format(value: number | null): string {
    return value === null ? '–' : value.toFixed(1).replace('.', ',');
  }

  sparklinePoints = computed<string | null>(() => {
    const sparkline = this.data()?.wellbeing?.sparkline ?? [];
    if (sparkline.length < 2) return null;

    const min = 1;
    const max = 5;
    const stepX = 140 / (sparkline.length - 1);
    return sparkline
      .map((score, i) => `${(i * stepX).toFixed(1)},${(44 - ((score - min) / (max - min)) * 40).toFixed(1)}`)
      .join(' ');
  });

  metricTiles = computed(() => {
    const data = this.data();
    if (!data?.metrics) return [];

    const tiles = [
      this.tile('Stimmung', data.metrics.mood, false),
      this.tile('Energie', data.metrics.energy, false),
      this.tile('Stress', data.metrics.stress, true),
    ];

    if (data.sleep) {
      const delta = Math.round((data.sleep.currentH - data.sleep.previousH) * 10) / 10;
      tiles.push({
        label: 'Schlaf',
        value: this.format(data.sleep.currentH),
        unit: 'h',
        barPercent: Math.min(100, (data.sleep.currentH / 9) * 100),
        barClass: 'bg-teal-500',
        subline: `letzte Woche ${this.format(data.sleep.previousH)} h (${delta >= 0 ? '+' : ''}${this.format(delta)})`,
      });
    }

    return tiles;
  });

  trendLabel(trend: 'up' | 'down' | 'flat'): string {
    return trend === 'up' ? '↑ höher' : trend === 'down' ? '↓ besser' : '→ stabil';
  }

  trendChipClass(trend: 'up' | 'down' | 'flat'): string {
    // For body signals fewer occurrences are better, so down is green.
    return trend === 'down'
      ? 'bg-emerald-50 text-emerald-700'
      : trend === 'up'
        ? 'bg-red-50 text-red-600'
        : 'bg-slate-100 text-slate-600';
  }

  safeModeBadge(badge: string): string {
    return badge.toUpperCase();
  }

  leverCardClass(lever: LeverCard): string {
    if (lever.safeModeState === 'paused') {
      return 'border-amber-200 bg-amber-50/40 hover:border-amber-300 hover:shadow-sm';
    }

    if (lever.safeModeState === 'gentle') {
      return 'border-teal-200 bg-white shadow-sm hover:border-teal-300';
    }

    return 'border-slate-100 bg-white hover:border-teal-200 hover:shadow-sm';
  }

  leverBadge(lever: LeverCard): string {
    if (lever.safeModeState === 'paused') return 'pausiert';
    if (lever.safeModeState === 'gentle') return 'sanft empfohlen';
    return lever.badge;
  }

  leverBadgeClass(lever: LeverCard): string {
    if (lever.safeModeState === 'paused') return 'bg-amber-100 text-amber-800';
    if (lever.safeModeState === 'gentle') return 'bg-teal-50 text-teal-700';
    return '';
  }

  leverSafeModeNote(lever: LeverCard): string | null {
    if (lever.safeModeState === 'paused') {
      return 'Heute wegen Schonmodus pausiert.';
    }

    if (lever.safeModeState === 'gentle') {
      return 'Sanfte Einheit im Schonmodus empfohlen.';
    }

    return null;
  }

  leverCtaLabel(lever: LeverCard): string {
    return lever.safeModeState === 'paused' ? 'Details ansehen' : 'Ansehen';
  }

  formatLabValue(value: number): string {
    return String(Math.round(value * 100) / 100).replace('.', ',');
  }

  labStatusClass(status: LabMarker['status']): string {
    switch (status) {
      case 'unter Bereich': return 'bg-amber-50 text-amber-700';
      case 'über Bereich': return 'bg-orange-50 text-orange-700';
      default: return 'bg-teal-50 text-teal-700';
    }
  }

  labDotClass(status: LabMarker['status']): string {
    switch (status) {
      case 'unter Bereich': return 'bg-amber-500';
      case 'über Bereich': return 'bg-orange-500';
      default: return 'bg-teal-500';
    }
  }

  private measureSafeModeState(lever: DashboardLever): LeverSafeModeState {
    if (!this.shouldShowSafeModeBanner()) return null;

    const category = lever.category?.toUpperCase() ?? '';
    if (['SLEEP', 'BREATHING', 'MINDFULNESS', 'REFLECTION'].includes(category)) {
      return 'gentle';
    }

    // TODO: Replace this demo-category mapping with backend measure intensity
    // metadata once the dashboard contract exposes which levers are paused.
    if (['MOBILITY', 'STRENGTH', 'MIXED'].includes(category)) {
      return 'paused';
    }

    return null;
  }

  private demoCheckinTriggersSafeMode(checkin: DemoCheckin): boolean {
    return checkin.illness.sick
      || Object.keys(checkin.illness).some(key => key !== 'sick')
      || checkin.energy <= 2
      || (checkin.sleep?.recovery ?? 5) <= 2;
  }

  private tile(label: string, metric: MetricAggregate, lowerIsBetter: boolean) {
    return {
      label,
      value: this.format(metric.current),
      unit: '/5',
      barPercent: metric.current === null ? 0 : (metric.current / 5) * 100,
      barClass: lowerIsBetter ? 'bg-amber-400' : 'bg-teal-500',
      subline: metric.previous === null
        ? '–'
        : `letzte Woche ${this.format(metric.previous)}${lowerIsBetter ? ' · niedriger ist besser' : ''}`,
    };
  }
}
