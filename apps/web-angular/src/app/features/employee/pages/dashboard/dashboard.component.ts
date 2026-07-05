import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';
import { RouterLink } from '@angular/router';
import { CheckinDemoStorageService } from '../../services/checkin-demo-storage.service';
import { DashboardData, EmployeeService, MetricAggregate } from '../../services/employee.service';
import { categoryIcon } from '../../shared/measure-category-icons';

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

      @if (data()?.healthFlag; as flag) {
        @if (flag.state === 'caution') {
          <div class="rounded-2xl border border-amber-100 bg-amber-50 px-5 py-4">
            <div class="flex items-center gap-2.5">
              <span class="text-[17px] font-bold leading-tight text-amber-800">{{ flag.label }}</span>
              <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-amber-700">{{ flag.badge }}</span>
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
                   class="group flex flex-col rounded-[20px] border border-slate-100 bg-white p-6 hover:border-teal-200 hover:shadow-sm transition-all">
                  <div class="flex items-center justify-between mb-4">
                    <span class="flex h-12 w-12 items-center justify-center rounded-[14px]" [style.background]="lever.icon.bg">
                      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" [attr.stroke]="lever.icon.color"
                           stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
                           [innerHTML]="lever.iconSvg"></svg>
                    </span>
                    <span class="rounded-full px-3 py-1.5 text-[13px] font-bold"
                          [style.color]="lever.icon.color" [style.background]="lever.icon.bg">{{ lever.badge }}</span>
                  </div>
                  <span class="text-[17px] font-semibold text-slate-800">{{ lever.title }}</span>
                  <p class="text-sm text-slate-500 leading-relaxed mt-2 mb-4">{{ lever.reason }}</p>
                  <div class="mt-auto flex items-center justify-between">
                    <span class="text-[13px] font-semibold text-teal-600">{{ lever.expected }}</span>
                    <span class="text-sm font-semibold text-teal-600 group-hover:text-teal-700">Ansehen →</span>
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
  today = new Date();

  /** Levers enriched with their category icon + pre-sanitised glyph markup. */
  leverCards = computed(() =>
    (this.data()?.levers ?? []).map(lever => {
      const icon = categoryIcon(lever.category);
      return {
        ...lever,
        icon,
        iconSvg: this.sanitizer.bypassSecurityTrustHtml(icon.svg) as SafeHtml,
      };
    })
  );

  /** Local demo check-in (Handoff 02) also counts as done — client-side polish only. */
  checkinDone = computed(() =>
    (this.data()?.todayCheckinCompleted ?? false) || this.checkinStorage.todayCompleted()
  );

  ngOnInit() {
    this.employeeService.getDashboard().subscribe(data => {
      this.data.set(data);
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
