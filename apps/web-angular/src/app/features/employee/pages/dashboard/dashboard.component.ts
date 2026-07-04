import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { DashboardData, EmployeeService, MetricAggregate } from '../../services/employee.service';

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
    <div class="max-w-3xl mx-auto p-4 space-y-6">
      <header class="flex items-start justify-between gap-4">
        <div>
          <h1 class="text-xl font-bold text-slate-800">Hallo!</h1>
          <p class="text-sm text-slate-400">{{ today | date:'EEEE, d. MMMM' }}</p>
        </div>
        <div class="flex gap-2">
          <span class="inline-flex items-center gap-1 rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-700">
            {{ data()?.points ?? 0 }} Punkte
          </span>
          <span class="inline-flex items-center gap-1 rounded-full bg-orange-50 px-3 py-1 text-xs font-semibold text-orange-600">
            🔥 {{ data()?.streak ?? 0 }} Tage
          </span>
        </div>
      </header>

      @if (data()?.healthFlag; as flag) {
        @if (flag.state === 'caution') {
          <div class="rounded-2xl border border-amber-100 bg-amber-50 px-5 py-4">
            <div class="flex items-center gap-2">
              <span class="text-sm font-bold text-amber-800">{{ flag.label }}</span>
              <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-700">{{ flag.badge }}</span>
            </div>
            <p class="text-xs text-amber-700 mt-1">{{ flag.note }}</p>
          </div>
        }
      }

      @if (data()?.wellbeing; as wellbeing) {
        <div class="rounded-3xl p-6 text-white" style="background: linear-gradient(135deg, #14b8a6, #0d9488)">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wider text-teal-100">Dein Wohlbefinden diese Woche</p>
              <div class="flex items-baseline gap-2 mt-2" title="Abgeleiteter Wert — berechnet aus deinen 3 Werten (Stimmung, Energie, Stress)">
                <span class="text-5xl font-black">{{ format(wellbeing.current) }}</span>
                <span class="text-teal-100 text-sm">von {{ wellbeing.scale }}</span>
              </div>
              @if (wellbeing.delta !== null) {
                <span class="mt-2 inline-flex items-center rounded-full bg-white/20 px-2.5 py-1 text-xs font-semibold">
                  {{ wellbeing.delta >= 0 ? '+' : '' }}{{ format(wellbeing.delta) }} vs. letzte Woche
                </span>
              }
              <p class="text-[11px] text-teal-100/80 mt-2">berechnet aus deinen 3 Werten</p>
            </div>
            @if (sparklinePoints(); as points) {
              <svg viewBox="0 0 140 48" class="w-36 h-12 flex-shrink-0" aria-hidden="true">
                <polyline [attr.points]="points" fill="none" stroke="rgba(255,255,255,0.9)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            }
          </div>
        </div>
      }

      @if (data()?.metrics; as metrics) {
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
          @for (tile of metricTiles(); track tile.label) {
            <div class="bg-white rounded-2xl border border-slate-100 p-4">
              <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ tile.label }}</p>
              <div class="flex items-baseline gap-1 mt-1.5">
                <span class="text-2xl font-bold text-slate-800">{{ tile.value }}</span>
                <span class="text-xs text-slate-400">{{ tile.unit }}</span>
              </div>
              <div class="h-1.5 rounded-full bg-slate-100 mt-2 overflow-hidden">
                <div class="h-full rounded-full" [class]="tile.barClass" [style.width.%]="tile.barPercent"></div>
              </div>
              <p class="text-[11px] text-slate-400 mt-1.5">{{ tile.subline }}</p>
            </div>
          }
        </div>
      }

      <div class="bg-white rounded-2xl border border-slate-100 p-5 flex items-center justify-between gap-4">
        <div>
          <p class="font-semibold text-slate-800 text-sm">
            {{ data()?.todayCheckinCompleted ? 'Check-in für heute erledigt ✓' : 'Dein Check-in wartet' }}
          </p>
          <p class="text-xs text-slate-400 mt-0.5">
            {{ data()?.todayCheckinCompleted ? 'Stark — bis morgen!' : 'Wie fühlst du dich heute? Dauert unter einer Minute.' }}
          </p>
        </div>
        <a routerLink="/employee/checkin"
           class="rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-700 transition-colors whitespace-nowrap">
          Check-in starten
        </a>
      </div>

      @if (data()?.bodySignals; as signals) {
        <div class="bg-white rounded-2xl border border-slate-100 p-6">
          <h3 class="text-xs font-bold tracking-wide uppercase text-teal-700 mb-4">Körpersignale</h3>
          <div class="space-y-3">
            @for (signal of signals; track signal.label) {
              <div class="flex items-center justify-between gap-4">
                <span class="text-sm text-slate-700">{{ signal.label }}</span>
                <span class="text-xs text-slate-500">
                  {{ signal.thisWeekDays }}× diese Woche
                  <span class="text-slate-300">·</span>
                  {{ signal.lastWeekDays }}× letzte Woche
                  <span class="ml-1 font-bold" [class]="trendClass(signal.trend)">{{ trendArrow(signal.trend) }}</span>
                </span>
              </div>
            }
          </div>
        </div>
      }

      @if (data()?.levers; as levers) {
        @if (levers.length > 0) {
          <div class="bg-white rounded-2xl border border-slate-100 p-6">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-xs font-bold tracking-wide uppercase text-teal-700">Deine Hebel</h3>
              <a routerLink="/employee/measures" class="text-xs font-semibold text-teal-600 hover:text-teal-700">Alle Maßnahmen →</a>
            </div>
            <div class="space-y-3">
              @for (lever of levers; track lever.title) {
                <a [routerLink]="['/employee/measures', lever.measureId]"
                   class="block rounded-xl border border-slate-100 p-4 hover:border-teal-200 transition-colors">
                  <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-slate-800">{{ lever.title }}</span>
                    <span class="rounded-full bg-teal-50 px-2 py-0.5 text-[10px] font-bold text-teal-700">{{ lever.badge }}</span>
                  </div>
                  <p class="text-xs text-slate-500 mt-1">{{ lever.reason }}</p>
                  <p class="text-xs font-semibold text-teal-600 mt-1.5">{{ lever.expected }}</p>
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

  data = signal<DashboardData | null>(null);
  today = new Date();

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

  trendArrow(trend: 'up' | 'down' | 'flat'): string {
    return trend === 'up' ? '↑' : trend === 'down' ? '↓' : '→';
  }

  trendClass(trend: 'up' | 'down' | 'flat'): string {
    // For body signals fewer occurrences are better, so down is green.
    return trend === 'down' ? 'text-emerald-600' : trend === 'up' ? 'text-red-500' : 'text-slate-400';
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
