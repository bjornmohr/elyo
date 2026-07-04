import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { AssignedMeasure, EmployeeService } from '../../services/employee.service';

/** Demo logic: the check-in location is faked/local (see Handoff 02), default office. */
type DemoLocation = 'office' | 'home' | 'plant' | 'onroad';

const LOCATION_LABELS: Record<DemoLocation, string> = {
  office: 'Büro',
  home: 'Home-Office',
  plant: 'Werk',
  onroad: 'Unterwegs',
};

const CATEGORY_LABELS: Record<string, string> = {
  MOBILITY: 'Mobilität',
  STRENGTH: 'Kraft',
  BREATHING: 'Atmung',
  MINDFULNESS: 'Achtsamkeit',
  EDUCATION: 'Wissen',
  REFLECTION: 'Reflexion',
  MIXED: 'Gemischt',
};

@Component({
  selector: 'app-employee-measures',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="max-w-3xl mx-auto p-4 space-y-6">
      <header class="flex items-center justify-between gap-4">
        <div class="flex items-center space-x-4">
          <a routerLink="/employee" class="p-2 hover:bg-slate-100 rounded-full transition-colors text-slate-500">←</a>
          <h1 class="text-xl font-bold text-slate-800">Deine Maßnahmen</h1>
        </div>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-700">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
          Heute: {{ locationLabel() }}
        </span>
      </header>

      @if (hiddenCount() > 0) {
        <p class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-2.5 text-xs text-amber-700">
          {{ hiddenCount() }} Maßnahme{{ hiddenCount() === 1 ? '' : 'n' }} mit Bodenübungen ausgeblendet — an deinem heutigen Ort ({{ locationLabel() }}) nicht machbar.
        </p>
      }

      @if (loading()) {
        <div class="flex justify-center py-12">
          <div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin"></div>
        </div>
      } @else if (visibleMeasures().length === 0) {
        <div class="bg-white rounded-3xl border border-slate-100 p-10 text-center text-slate-400">
          Dir sind aktuell keine Maßnahmen zugewiesen.
        </div>
      } @else {
        <div class="space-y-4">
          @for (measure of visibleMeasures(); track measure.id) {
            <a [routerLink]="['/employee/measures', measure.id]"
               class="block bg-white rounded-2xl border border-slate-100 p-6 space-y-4 hover:border-teal-200 hover:shadow-sm transition-all">
              <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-4">
                  <div class="relative w-14 h-14 flex-shrink-0">
                    <svg viewBox="0 0 56 56" class="w-14 h-14 -rotate-90">
                      <circle cx="28" cy="28" r="24" fill="none" stroke="#f1f5f9" stroke-width="5"/>
                      <circle cx="28" cy="28" r="24" fill="none" stroke="#0d9488" stroke-width="5"
                              stroke-linecap="round"
                              [attr.stroke-dasharray]="progressDash(measure)"/>
                    </svg>
                    <span class="absolute inset-0 flex items-center justify-center text-xs font-bold text-slate-700">
                      {{ measure.weeklyDone ?? 0 }}/{{ measure.weeklyTarget ?? 0 }}
                    </span>
                  </div>
                  <div>
                    <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold tracking-wide uppercase bg-teal-50 text-teal-700">
                      {{ categoryLabel(measure.category) }}
                    </span>
                    <h2 class="font-bold text-slate-800 mt-1.5">{{ measure.title }}</h2>
                    @if (measure.assignmentReason) {
                      <p class="text-xs text-slate-500 mt-0.5">{{ measure.assignmentReason }}</p>
                    }
                  </div>
                </div>
                @if (measure.streakDays) {
                  <span class="inline-flex items-center gap-1 rounded-full bg-orange-50 px-2.5 py-1 text-xs font-semibold text-orange-600 whitespace-nowrap">
                    🔥 {{ measure.streakDays }} Tage
                  </span>
                }
              </div>

              <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                <span>{{ measure.exerciseCount }} Übung{{ measure.exerciseCount === 1 ? '' : 'en' }}</span>
                @if (measure.estMinutes) {
                  <span>· ~{{ measure.estMinutes }} Min</span>
                }
                @if (measure.effect) {
                  <span class="ml-auto inline-flex items-center rounded-full px-2.5 py-1 font-semibold"
                        [class]="measure.effect.direction === 'up' ? 'bg-emerald-50 text-emerald-700' : 'bg-teal-50 text-teal-700'">
                    {{ effectLabel(measure.effect) }}
                  </span>
                }
              </div>
            </a>
          }
        </div>
      }
    </div>
  `
})
export class EmployeeMeasuresComponent implements OnInit {
  private employeeService = inject(EmployeeService);

  measures = signal<AssignedMeasure[]>([]);
  loading = signal(true);
  location = signal<DemoLocation>('office');

  visibleMeasures = computed(() => this.measures().filter(m => !this.isHidden(m)));
  hiddenCount = computed(() => this.measures().length - this.visibleMeasures().length);

  ngOnInit() {
    this.location.set(this.readDemoLocation());
    this.employeeService.getAssignedMeasures().subscribe({
      next: measures => { this.measures.set(measures); this.loading.set(false); },
      error: () => this.loading.set(false),
    });
  }

  locationLabel(): string {
    return LOCATION_LABELS[this.location()];
  }

  categoryLabel(category: string | null): string {
    return category ? (CATEGORY_LABELS[category] ?? category) : 'Programm';
  }

  effectLabel(effect: NonNullable<AssignedMeasure['effect']>): string {
    const arrow = effect.direction === 'up' ? '↑' : '↓';
    const format = (v: number | null) => v === null ? '–' : String(v).replace('.', ',');
    switch (effect.metric) {
      case 'pain': return `Schmerz ${format(effect.before)} → ${format(effect.after)} ${arrow}`;
      case 'sleep_hours': return `Schlaf ${format(effect.before)} → ${format(effect.after)} h ${arrow}`;
      case 'stress': return `Stress ${format(effect.before)} → ${format(effect.after)} ${arrow}`;
    }
  }

  progressDash(measure: AssignedMeasure): string {
    const circumference = 2 * Math.PI * 24;
    const target = measure.weeklyTarget ?? 0;
    const ratio = target > 0 ? Math.min(1, (measure.weeklyDone ?? 0) / target) : 0;
    return `${ratio * circumference} ${circumference}`;
  }

  /** Office/plant hide floor-based measures — demo filter per Handoff 03. */
  private isHidden(measure: AssignedMeasure): boolean {
    const location = this.location();
    return measure.requiresFloor && (location === 'office' || location === 'plant');
  }

  private readDemoLocation(): DemoLocation {
    try {
      const key = `elyo.demo.checkin.${new Date().toISOString().slice(0, 10)}`;
      const raw = localStorage.getItem(key);
      if (raw) {
        const parsed = JSON.parse(raw);
        if (['office', 'home', 'plant', 'onroad'].includes(parsed?.location)) {
          return parsed.location;
        }
      }
    } catch {
      // localStorage unavailable or corrupt entry — fall back to office
    }
    return 'office';
  }
}
