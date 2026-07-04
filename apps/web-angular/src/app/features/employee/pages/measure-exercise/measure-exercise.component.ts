import { Component, OnDestroy, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AssignedMeasureDetail, EmployeeService, MeasureExercise } from '../../services/employee.service';

/**
 * 3d — exercise execution. All run state (current set, completed exercises,
 * fake before/after effect) lives in localStorage only; no API writes.
 */
@Component({
  selector: 'app-employee-measure-exercise',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="max-w-3xl mx-auto p-4 space-y-6">
      <header class="flex items-center space-x-4">
        <a [routerLink]="['/employee/measures', measureId]" class="p-2 hover:bg-slate-100 rounded-full transition-colors text-slate-500">←</a>
        <div>
          <h1 class="text-xl font-bold text-slate-800">{{ exercise()?.title ?? 'Übung' }}</h1>
          @if (measure(); as m) {
            <p class="text-xs text-slate-400">{{ m.title }} · Übung {{ exercise()?.position }} von {{ m.exercises.length }}</p>
          }
        </div>
      </header>

      @if (loading()) {
        <div class="flex justify-center py-12">
          <div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin"></div>
        </div>
      } @else if (exercise(); as ex) {
        <div class="bg-white rounded-2xl border border-slate-100 p-6 space-y-5">
          @if (assetUrl(ex.mainPictogramPath); as mainUrl) {
            <div class="flex justify-center rounded-2xl bg-slate-50 py-6">
              <img [src]="mainUrl" [alt]="ex.mainPictogramAlt ?? ex.title" class="h-52 w-auto" />
            </div>
          }

          @if (ex.description) {
            <p class="text-sm text-slate-500">{{ ex.description }}</p>
          }

          <div class="flex flex-wrap gap-2">
            @for (tag of suitabilityTags(ex); track tag) {
              <span class="inline-flex items-center rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-700">{{ tag }}</span>
            }
          </div>

          @if (ex.steps.length > 0) {
            <div class="rounded-2xl border border-slate-100 p-5">
              <h3 class="text-[11px] font-bold tracking-wide uppercase text-teal-700 mb-4">So geht's</h3>
              <div class="grid gap-3" [class.sm:grid-cols-3]="stepsWithPictograms(ex)">
                @for (step of ex.steps; track $index) {
                  <div class="flex gap-3 items-start" [class.flex-col]="stepsWithPictograms(ex)" [class.items-center]="stepsWithPictograms(ex)">
                    @if (assetUrl(step.pictogramPath); as stepUrl) {
                      <img [src]="stepUrl" [alt]="step.alt ?? step.text" class="h-14 w-14" />
                    } @else {
                      <span class="w-6 h-6 rounded-full bg-teal-50 text-teal-700 text-xs font-bold flex items-center justify-center flex-shrink-0">{{ $index + 1 }}</span>
                    }
                    <p class="text-xs text-slate-600 leading-relaxed" [class.text-center]="stepsWithPictograms(ex)">
                      @if (stepsWithPictograms(ex)) { {{ $index + 1 }} · }{{ step.text }}
                    </p>
                  </div>
                }
              </div>
            </div>
          }

          <div class="grid grid-cols-3 gap-3 text-center">
            <div class="rounded-xl border border-slate-100 px-3 py-3">
              <p class="text-lg font-bold text-slate-800">{{ currentSet() }}</p>
              <p class="text-[11px] text-slate-500 mt-0.5">Satz von {{ ex.sets ?? 1 }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 px-3 py-3">
              <p class="text-lg font-bold text-slate-800">{{ ex.repetitions ?? (ex.holdSeconds ? ex.holdSeconds + ' s' : '–') }}</p>
              <p class="text-[11px] text-slate-500 mt-0.5">{{ ex.repetitions ? 'Wiederholungen' : (ex.holdSeconds ? 'Halten' : '') }}</p>
            </div>
            <div class="rounded-xl border border-slate-100 px-3 py-3">
              <p class="text-lg font-bold text-slate-800">{{ timerLabel() }}</p>
              <p class="text-[11px] text-slate-500 mt-0.5">Timer</p>
            </div>
          </div>

          @if (ex.safetyNotes) {
            <p class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-2.5 text-xs text-amber-700">{{ ex.safetyNotes }}</p>
          }

          <div class="flex flex-col gap-3 sm:flex-row">
            <button type="button" (click)="toggleTimer()"
                    class="flex-1 rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors">
              {{ timerRunning() ? 'Pause' : 'Timer starten' }}
            </button>
            <button type="button" (click)="skip()"
                    class="flex-1 rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors">
              Überspringen
            </button>
            <button type="button" (click)="completeSet()"
                    class="flex-1 rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-700 transition-colors">
              {{ isLastSet() ? 'Übung abschließen ✓' : 'Satz abgeschlossen ✓' }}
            </button>
          </div>
        </div>
      } @else {
        <div class="bg-white rounded-3xl border border-slate-100 p-10 text-center text-slate-400">
          Übung nicht gefunden.
        </div>
      }
    </div>
  `
})
export class EmployeeMeasureExerciseComponent implements OnInit, OnDestroy {
  private employeeService = inject(EmployeeService);
  private route = inject(ActivatedRoute);
  private router = inject(Router);

  measureId = 0;
  private position = 0;

  measure = signal<AssignedMeasureDetail | null>(null);
  loading = signal(true);
  currentSet = signal(1);
  timerSeconds = signal(0);
  timerRunning = signal(false);
  private timerHandle: ReturnType<typeof setInterval> | null = null;

  exercise = computed<MeasureExercise | null>(() =>
    this.measure()?.exercises.find(e => e.position === this.position) ?? null
  );

  ngOnInit() {
    this.measureId = Number(this.route.snapshot.paramMap.get('id'));
    this.position = Number(this.route.snapshot.paramMap.get('position'));
    this.currentSet.set(this.readStoredSet());
    this.employeeService.getAssignedMeasure(this.measureId).subscribe({
      next: measure => { this.measure.set(measure); this.loading.set(false); },
      error: () => this.loading.set(false),
    });
  }

  ngOnDestroy() {
    this.stopTimer();
  }

  assetUrl(path: string | null): string | null {
    return this.employeeService.assetUrl(path);
  }

  stepsWithPictograms(exercise: MeasureExercise): boolean {
    return exercise.steps.some(step => !!step.pictogramPath);
  }

  suitabilityTags(exercise: MeasureExercise): string[] {
    const tags: string[] = [];
    if (exercise.postureTags.includes('standing')) tags.push('Im Stehen');
    if (exercise.postureTags.includes('sitting')) tags.push('Im Sitzen');
    if (exercise.locationTags.includes('office')) tags.push('Büro-geeignet');
    if (exercise.locationTags.includes('plant')) tags.push('Werk-geeignet');
    if (!exercise.requiresFloor) tags.push('kein Boden nötig');
    return tags;
  }

  timerLabel(): string {
    const total = this.timerSeconds();
    const minutes = Math.floor(total / 60);
    const seconds = total % 60;
    return `${minutes}:${String(seconds).padStart(2, '0')}`;
  }

  toggleTimer() {
    if (this.timerRunning()) {
      this.stopTimer();
      return;
    }
    this.timerRunning.set(true);
    this.timerHandle = setInterval(() => this.timerSeconds.update(s => s + 1), 1000);
  }

  isLastSet(): boolean {
    const sets = this.exercise()?.sets ?? 1;
    return this.currentSet() >= sets;
  }

  completeSet() {
    if (!this.isLastSet()) {
      this.currentSet.update(s => s + 1);
      this.persistRunState();
      return;
    }
    this.finishExercise();
  }

  skip() {
    this.finishExercise();
  }

  private finishExercise() {
    this.stopTimer();
    const state = this.readRunState();
    if (!state.completedPositions.includes(this.position)) {
      state.completedPositions.push(this.position);
    }
    delete state.currentSets?.[this.position];

    const effect = this.measure()?.effect;
    if (effect && effect.before !== null && effect.after !== null) {
      state.lastEffect = { before: effect.before, after: effect.after };
    }

    this.writeRunState(state);
    this.router.navigate(['/employee/measures', this.measureId]);
  }

  private persistRunState() {
    const state = this.readRunState();
    state.currentSets = { ...(state.currentSets ?? {}), [this.position]: this.currentSet() };
    this.writeRunState(state);
  }

  private readStoredSet(): number {
    return this.readRunState().currentSets?.[this.position] ?? 1;
  }

  private readRunState(): { completedPositions: number[]; currentSets?: Record<number, number>; lastEffect?: { before: number; after: number } } {
    try {
      const raw = localStorage.getItem(this.storageKey());
      if (raw) {
        const parsed = JSON.parse(raw);
        return {
          completedPositions: Array.isArray(parsed?.completedPositions) ? parsed.completedPositions : [],
          currentSets: parsed?.currentSets ?? {},
          lastEffect: parsed?.lastEffect,
        };
      }
    } catch {
      // localStorage unavailable — run state stays in memory only
    }
    return { completedPositions: [], currentSets: {} };
  }

  private writeRunState(state: object) {
    try {
      localStorage.setItem(this.storageKey(), JSON.stringify(state));
    } catch {
      // best effort — demo state only
    }
  }

  private storageKey(): string {
    return `elyo.demo.measure-run.${this.measureId}`;
  }

  private stopTimer() {
    if (this.timerHandle) {
      clearInterval(this.timerHandle);
      this.timerHandle = null;
    }
    this.timerRunning.set(false);
  }
}
