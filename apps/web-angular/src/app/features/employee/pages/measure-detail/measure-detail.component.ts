import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { AssignedMeasureDetail, EmployeeService, MeasureEffect, MeasureExercise } from '../../services/employee.service';

interface LocalRunState {
  completedPositions: number[];
  lastEffect?: { before: number; after: number };
}

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
  selector: 'app-employee-measure-detail',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="max-w-3xl mx-auto p-4 space-y-6">
      <header class="flex items-center space-x-4">
        <a routerLink="/employee/measures" class="p-2 hover:bg-slate-100 rounded-full transition-colors text-slate-500">←</a>
        <h1 class="text-xl font-bold text-slate-800">Programm-Detail</h1>
      </header>

      @if (loading()) {
        <div class="flex justify-center py-12">
          <div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin"></div>
        </div>
      } @else if (measure(); as m) {
        <div class="bg-white rounded-2xl border border-slate-100 p-6 space-y-4">
          <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold tracking-wide uppercase bg-teal-50 text-teal-700">
            {{ categoryLabel(m.category) }}
          </span>
          <div>
            <h2 class="text-lg font-bold text-slate-800">{{ m.title }}</h2>
            @if (m.description) {
              <p class="text-sm text-slate-500 mt-1">{{ m.description }}</p>
            }
          </div>

          @if (m.assignmentReason) {
            <div class="rounded-xl bg-teal-50/60 border border-teal-100 px-4 py-3 text-xs text-teal-800">
              <span class="font-semibold">Warum dieses Programm?</span> Empfohlen {{ m.assignmentReason }}.
            </div>
          }

          <div class="flex flex-wrap gap-2">
            @for (tag of suitabilityTags(m); track tag) {
              <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ tag }}</span>
            }
          </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-100 p-6 space-y-3">
          <h3 class="text-xs font-bold tracking-wide uppercase text-teal-700">Übungen</h3>
          @for (exercise of m.exercises; track exercise.id) {
            <div class="flex items-center gap-4 rounded-xl border px-4 py-3"
                 [class]="isDone(exercise.position) ? 'border-emerald-100 bg-emerald-50/40' : 'border-slate-100'">
              <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
                   [class]="isDone(exercise.position) ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'">
                @if (isDone(exercise.position)) { ✓ } @else { {{ exercise.position }} }
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-slate-800" [class.line-through]="isDone(exercise.position)">{{ exercise.title }}</p>
                <p class="text-xs text-slate-500">{{ exerciseMeta(exercise) }}</p>
              </div>
              <a [routerLink]="['/employee/measures', m.id, 'exercise', exercise.position]"
                 class="rounded-lg bg-teal-600 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-teal-700 transition-colors whitespace-nowrap">
                {{ isDone(exercise.position) ? 'Wiederholen' : 'Starten' }}
              </a>
            </div>
          }
        </div>

        @if (m.lastSession; as session) {
          <div class="bg-white rounded-2xl border border-slate-100 p-6 space-y-3">
            <h3 class="text-xs font-bold tracking-wide uppercase text-teal-700">Wirkung deiner letzten Einheit</h3>
            <div class="grid grid-cols-3 gap-3 text-center">
              <div class="rounded-xl bg-slate-50 px-3 py-3">
                <p class="text-lg font-bold text-slate-800">{{ effectValue(session.effect) }}</p>
                <p class="text-[11px] text-slate-500 mt-0.5">{{ effectCaption(session.effect) }}</p>
              </div>
              <div class="rounded-xl bg-slate-50 px-3 py-3">
                <p class="text-lg font-bold text-slate-800">{{ session.effort ?? '–' }}/5</p>
                <p class="text-[11px] text-slate-500 mt-0.5">Aufwand</p>
              </div>
              <div class="rounded-xl bg-slate-50 px-3 py-3">
                <p class="text-lg font-bold text-teal-700">+{{ session.points ?? 0 }}</p>
                <p class="text-[11px] text-slate-500 mt-0.5">Punkte</p>
              </div>
            </div>
            <p class="text-[11px] text-slate-400">Demo-Werte — Vorher/Nachher wird lokal erfasst und nicht gespeichert.</p>
          </div>
        }
      } @else {
        <div class="bg-white rounded-3xl border border-slate-100 p-10 text-center text-slate-400">
          Maßnahme nicht gefunden.
        </div>
      }
    </div>
  `
})
export class EmployeeMeasureDetailComponent implements OnInit {
  private employeeService = inject(EmployeeService);
  private route = inject(ActivatedRoute);

  measure = signal<AssignedMeasureDetail | null>(null);
  loading = signal(true);
  private runState = signal<LocalRunState>({ completedPositions: [] });

  ngOnInit() {
    const id = Number(this.route.snapshot.paramMap.get('id'));
    this.runState.set(this.readRunState(id));
    this.employeeService.getAssignedMeasure(id).subscribe({
      next: measure => { this.measure.set(measure); this.loading.set(false); },
      error: () => this.loading.set(false),
    });
  }

  categoryLabel(category: string | null): string {
    return category ? (CATEGORY_LABELS[category] ?? category) : 'Programm';
  }

  isDone(position: number): boolean {
    return this.runState().completedPositions.includes(position);
  }

  exerciseMeta(exercise: MeasureExercise): string {
    const parts: string[] = [];
    if (exercise.sets && exercise.repetitions) parts.push(`${exercise.sets} × ${exercise.repetitions} Wdh.`);
    else if (exercise.sets && exercise.holdSeconds) parts.push(`${exercise.sets} × ${exercise.holdSeconds} s halten`);
    else if (exercise.holdSeconds) parts.push(`${exercise.holdSeconds} s halten`);
    if (exercise.durationMinutes) parts.push(`~${exercise.durationMinutes} Min`);
    return parts.join(' · ') || 'Übung';
  }

  suitabilityTags(measure: AssignedMeasureDetail): string[] {
    const tags: string[] = [];
    if (measure.postureTags.includes('standing')) tags.push('Im Stehen');
    if (measure.postureTags.includes('sitting')) tags.push('Im Sitzen');
    if (measure.locationTags.includes('office')) tags.push('Büro-geeignet');
    if (measure.locationTags.includes('plant')) tags.push('Werk-geeignet');
    if (measure.locationTags.includes('home')) tags.push('Für Zuhause');
    if (measure.locationTags.includes('onroad')) tags.push('Unterwegs machbar');
    tags.push(measure.requiresFloor ? 'Boden nötig' : 'kein Boden nötig');
    return tags;
  }

  effectValue(effect: MeasureEffect): string {
    const format = (v: number | null) => v === null ? '–' : String(v).replace('.', ',');
    const suffix = effect.metric === 'sleep_hours' ? ' h' : '';
    return `${format(effect.before)} → ${format(effect.after)}${suffix}`;
  }

  effectCaption(effect: MeasureEffect): string {
    const arrow = effect.direction === 'up' ? '↑' : '↓';
    switch (effect.metric) {
      case 'pain': return `Schmerz ${arrow}`;
      case 'sleep_hours': return `Schlaf ${arrow}`;
      case 'stress': return `Stress ${arrow}`;
    }
  }

  private readRunState(measureId: number): LocalRunState {
    try {
      const raw = localStorage.getItem(`elyo.demo.measure-run.${measureId}`);
      if (raw) {
        const parsed = JSON.parse(raw);
        return {
          completedPositions: Array.isArray(parsed?.completedPositions) ? parsed.completedPositions : [],
          lastEffect: parsed?.lastEffect,
        };
      }
    } catch {
      // localStorage unavailable — treat as fresh run
    }
    return { completedPositions: [] };
  }
}
