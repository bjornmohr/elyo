import { Component, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { CheckinDemoStorageService } from '../../services/checkin-demo-storage.service';
import {
  CheckinDraft,
  FREQUENT_SYMPTOMS,
  ILLNESS_TYPES,
  IllnessTypeKey,
  LOCATIONS,
  MOOD_OPTIONS,
  OTHER_SYMPTOMS,
  SymptomDefinition,
  draftComplete,
  emptyDraft,
  illnessLabelFor,
  illnessSubsFor,
  sleepBranchActive,
  toDemoCheckin,
} from './checkin-state';

type Step = 'location' | 'mood' | 'energy' | 'stress' | 'signals' | 'summary' | 'done';

const STEPS: Step[] = ['location', 'mood', 'energy', 'stress', 'signals', 'summary'];

/**
 * 2a — guided adaptive stepper. The whole run is a demo: saving writes to
 * localStorage only (no network), the points feedback is faked client-side.
 */
@Component({
  selector: 'app-employee-checkin-stepper',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="w-full p-4 space-y-6">
      <header class="flex items-center justify-between gap-4">
        <div class="flex items-center space-x-4">
          <a routerLink="/employee/dashboard" class="p-2 hover:bg-slate-100 rounded-full transition-colors text-slate-500">←</a>
          <h1 class="text-xl font-bold text-slate-800">Dein Check-in</h1>
        </div>
        <a routerLink="/employee/checkin/chat" class="text-xs font-semibold text-teal-600 hover:text-teal-700">Als Chat beantworten →</a>
      </header>

      @if (step() !== 'done') {
        <div class="flex items-center gap-1.5">
          @for (s of steps; track s; let i = $index) {
            <div class="h-1.5 flex-1 rounded-full transition-colors"
                 [class]="i <= stepIndex() ? 'bg-teal-500' : 'bg-slate-100'"></div>
          }
        </div>
      }

      <div class="bg-white rounded-3xl border border-slate-100 p-6 sm:p-8 min-h-[380px] flex flex-col">
        @switch (step()) {
          @case ('location') {
            <div class="flex-1 space-y-5">
              <h2 class="text-lg font-bold text-slate-800">Wo bist du heute?</h2>
              <p class="text-xs text-slate-400">Wir schlagen dir nur Übungen vor, die an deinem heutigen Ort machbar sind.</p>
              <div class="grid grid-cols-2 gap-3">
                @for (location of locations; track location.value) {
                  <button type="button" (click)="draft.location = location.value"
                          class="rounded-2xl border px-4 py-5 text-center transition-colors"
                          [class]="draft.location === location.value ? 'border-teal-500 bg-teal-50' : 'border-slate-100 hover:border-teal-200'">
                    <div class="text-2xl">{{ location.icon }}</div>
                    <div class="text-sm font-semibold text-slate-700 mt-1.5">{{ location.label }}</div>
                  </button>
                }
              </div>
            </div>
          }
          @case ('mood') {
            <div class="flex-1 space-y-5">
              <h2 class="text-lg font-bold text-slate-800">Wie ist deine Stimmung?</h2>
              <div class="grid grid-cols-5 gap-2">
                @for (option of moodOptions; track option.value) {
                  <button type="button" (click)="draft.mood = option.value"
                          class="rounded-2xl border px-2 py-4 text-center transition-colors"
                          [class]="draft.mood === option.value ? 'border-teal-500 bg-teal-50' : 'border-slate-100 hover:border-teal-200'">
                    <div class="text-2xl">{{ option.emoji }}</div>
                    <div class="text-[10px] text-slate-500 mt-1 leading-tight">{{ option.label }}</div>
                  </button>
                }
              </div>
            </div>
          }
          @case ('energy') {
            <div class="flex-1 space-y-5">
              <h2 class="text-lg font-bold text-slate-800">Wie viel Energie hast du?</h2>
              <div class="space-y-2">
                <div class="grid grid-cols-5 gap-2">
                  @for (value of scale; track value) {
                    <button type="button" (click)="draft.energy = value"
                            class="rounded-xl border py-3 text-sm font-bold transition-colors"
                            [class]="draft.energy === value ? 'border-teal-500 bg-teal-50 text-teal-700' : 'border-slate-100 text-slate-600 hover:border-teal-200'">
                      {{ value }}
                    </button>
                  }
                </div>
                <div class="flex justify-between text-[11px] text-slate-400"><span>Erschöpft</span><span>Voller Energie</span></div>
              </div>

              @if (sleepActive()) {
                <div class="rounded-2xl bg-slate-50 border border-slate-100 p-4 space-y-4">
                  <p class="text-sm font-semibold text-slate-700">Kurz zum Schlaf</p>
                  <div class="flex items-center justify-between gap-4">
                    <span class="text-sm text-slate-600">Wie lange hast du geschlafen?</span>
                    <div class="flex items-center gap-2">
                      <button type="button" (click)="adjustSleep(-0.5)" class="w-8 h-8 rounded-full border border-slate-200 text-slate-600 font-bold hover:bg-white">−</button>
                      <span class="text-sm font-bold text-slate-800 w-14 text-center">{{ formatHours(draft.sleepHours) }} h</span>
                      <button type="button" (click)="adjustSleep(0.5)" class="w-8 h-8 rounded-full border border-slate-200 text-slate-600 font-bold hover:bg-white">＋</button>
                    </div>
                  </div>
                  <div>
                    <p class="text-sm text-slate-600 mb-2">Wie erholt fühlst du dich?</p>
                    <div class="grid grid-cols-5 gap-2">
                      @for (value of scale; track value) {
                        <button type="button" (click)="draft.sleepRecovery = value"
                                class="rounded-xl border py-2 text-sm font-bold transition-colors"
                                [class]="draft.sleepRecovery === value ? 'border-teal-500 bg-teal-50 text-teal-700' : 'border-slate-200 bg-white text-slate-600'">
                          {{ value }}
                        </button>
                      }
                    </div>
                  </div>
                </div>
              } @else {
                <button type="button" (click)="draft.sleepWanted = true" class="text-xs font-semibold text-teal-600 hover:text-teal-700">
                  ＋ Ich habe schlecht geschlafen
                </button>
              }
            </div>
          }
          @case ('stress') {
            <div class="flex-1 space-y-5">
              <h2 class="text-lg font-bold text-slate-800">Wie gestresst bist du?</h2>
              <div class="space-y-2">
                <div class="grid grid-cols-5 gap-2">
                  @for (value of scale; track value) {
                    <button type="button" (click)="draft.stress = value"
                            class="rounded-xl border py-3 text-sm font-bold transition-colors"
                            [class]="draft.stress === value ? 'border-teal-500 bg-teal-50 text-teal-700' : 'border-slate-100 text-slate-600 hover:border-teal-200'">
                      {{ value }}
                    </button>
                  }
                </div>
                <div class="flex justify-between text-[11px] text-slate-400"><span>Entspannt</span><span>Sehr gestresst</span></div>
              </div>
            </div>
          }
          @case ('signals') {
            <div class="flex-1 space-y-5">
              <h2 class="text-lg font-bold text-slate-800">Körpersignale &amp; Befinden</h2>

              <div>
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400 mb-2">Zuletzt häufig</p>
                <div class="flex flex-wrap gap-2">
                  @for (symptom of frequentSymptoms; track symptom.key) {
                    <button type="button" (click)="toggleSymptom(symptom)"
                            class="rounded-full border px-3.5 py-1.5 text-xs font-semibold transition-colors"
                            [class]="hasSymptom(symptom.key) ? 'border-teal-500 bg-teal-50 text-teal-700' : 'border-slate-200 text-slate-600 hover:border-teal-200'">
                      {{ symptom.label }}
                    </button>
                  }
                </div>
              </div>

              <div>
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400 mb-2">Weitere Signale</p>
                <div class="flex flex-wrap gap-2">
                  @for (symptom of otherSymptoms; track symptom.key) {
                    <button type="button" (click)="toggleSymptom(symptom)"
                            class="rounded-full border px-3.5 py-1.5 text-xs font-semibold transition-colors"
                            [class]="hasSymptom(symptom.key) ? 'border-teal-500 bg-teal-50 text-teal-700' : 'border-slate-200 text-slate-600 hover:border-teal-200'">
                      {{ symptom.label }}
                    </button>
                  }
                </div>
              </div>

              @if (expandedPainSymptom(); as pain) {
                <div class="rounded-2xl bg-slate-50 border border-slate-100 p-4 space-y-3">
                  <p class="text-sm font-semibold text-slate-700">{{ pain.label }} — wo genau?</p>
                  <div class="flex flex-wrap gap-2">
                    @for (region of pain.regions; track region) {
                      <button type="button" (click)="setSymptomRegion(pain.key, region)"
                              class="rounded-full border px-3 py-1 text-xs font-semibold transition-colors"
                              [class]="draft.symptoms[pain.key].region === region ? 'border-teal-500 bg-white text-teal-700' : 'border-slate-200 bg-white text-slate-600'">
                        {{ region }}
                      </button>
                    }
                  </div>
                  <p class="text-sm text-slate-600">Wie stark? <span class="text-slate-400">(1 = leicht, 5 = stark)</span></p>
                  <div class="grid grid-cols-5 gap-2">
                    @for (value of scale; track value) {
                      <button type="button" (click)="setSymptomSeverity(pain.key, value)"
                              class="rounded-xl border py-2 text-sm font-bold transition-colors"
                              [class]="draft.symptoms[pain.key].severity === value ? 'border-teal-500 bg-teal-50 text-teal-700' : 'border-slate-200 bg-white text-slate-600'">
                        {{ value }}
                      </button>
                    }
                  </div>
                </div>
              }

              <div class="rounded-2xl border border-slate-100 p-4 space-y-3">
                <p class="text-sm font-semibold text-slate-700">Fühlst du dich krank?</p>
                <div class="flex gap-2">
                  <button type="button" (click)="setSick(false)"
                          class="flex-1 rounded-xl border py-2.5 text-sm font-semibold transition-colors"
                          [class]="draft.sick === false ? 'border-teal-500 bg-teal-50 text-teal-700' : 'border-slate-200 text-slate-600'">Nein</button>
                  <button type="button" (click)="setSick(true)"
                          class="flex-1 rounded-xl border py-2.5 text-sm font-semibold transition-colors"
                          [class]="draft.sick === true ? 'border-amber-500 bg-amber-50 text-amber-700' : 'border-slate-200 text-slate-600'">Ja</button>
                </div>

                @if (draft.sick === true) {
                  <div class="flex flex-wrap gap-2">
                    @for (type of illnessTypes; track type.key) {
                      <button type="button" (click)="setIllnessType(type.key)"
                              class="rounded-xl border px-3.5 py-2 text-xs font-semibold transition-colors"
                              [class]="draft.illnessType === type.key ? 'border-amber-500 bg-amber-50 text-amber-700' : 'border-slate-200 text-slate-600'">{{ type.label }}</button>
                    }
                  </div>

                  @if (draft.illnessType) {
                    <div class="flex flex-wrap gap-2">
                      @for (sub of illnessSubs(); track sub) {
                        <button type="button" (click)="toggleIllnessSub(sub)"
                                class="rounded-full border px-3 py-1 text-xs font-semibold transition-colors"
                                [class]="draft.illnessSubs.includes(sub) ? 'border-amber-500 bg-amber-50 text-amber-700' : 'border-slate-200 text-slate-600'">
                          {{ sub }}
                        </button>
                      }
                    </div>
                    <p class="text-sm text-slate-600">Wie stark insgesamt?</p>
                    <div class="grid grid-cols-5 gap-2">
                      @for (value of scale; track value) {
                        <button type="button" (click)="draft.illnessSeverity = value"
                                class="rounded-xl border py-2 text-sm font-bold transition-colors"
                                [class]="draft.illnessSeverity === value ? 'border-amber-500 bg-amber-50 text-amber-700' : 'border-slate-200 text-slate-600'">
                          {{ value }}
                        </button>
                      }
                    </div>
                    <p class="text-[11px] text-amber-700 bg-amber-50 rounded-lg px-3 py-2">Intensive Übungen werden pausiert — wir schlagen dir Erholung und sanfte Einheiten vor.</p>
                  }
                }
              </div>
            </div>
          }
          @case ('summary') {
            <div class="flex-1 space-y-4">
              <h2 class="text-lg font-bold text-slate-800">Zusammenfassung</h2>
              <dl class="space-y-2 text-sm">
                @for (row of summaryRows(); track row.label) {
                  <div class="flex justify-between gap-4 border-b border-slate-50 pb-2">
                    <dt class="text-slate-400">{{ row.label }}</dt>
                    <dd class="font-semibold text-slate-700 text-right">{{ row.value }}</dd>
                  </div>
                }
              </dl>
              <p class="rounded-xl bg-teal-50 px-4 py-2.5 text-xs font-semibold text-teal-700">Du erhältst 10 Punkte für deinen Check-in.</p>
            </div>
          }
          @case ('done') {
            <div class="flex-1 flex flex-col items-center justify-center text-center space-y-4 py-8">
              <div class="w-16 h-16 rounded-full bg-teal-50 flex items-center justify-center text-3xl">✓</div>
              <h2 class="text-lg font-bold text-slate-800">{{ justSaved() ? 'Check-in gespeichert' : 'Check-in für heute erledigt' }}</h2>
              @if (justSaved()) {
                <p class="text-sm font-bold text-teal-600">+10 Punkte</p>
              }
              <p class="text-xs text-slate-400 max-w-xs">Deine Angaben bleiben in dieser Demo lokal auf deinem Gerät gespeichert.</p>
              <div class="flex gap-3">
                <button type="button" (click)="restart()" class="rounded-lg border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors">
                  Erneut ausfüllen
                </button>
                <a routerLink="/employee/dashboard" class="rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-700 transition-colors">
                  Zurück zur Übersicht
                </a>
              </div>
            </div>
          }
        }

        @if (step() !== 'done') {
          <div class="flex justify-between gap-3 pt-6 mt-auto">
            <button type="button" (click)="back()" [disabled]="stepIndex() === 0"
                    class="rounded-lg border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 disabled:opacity-40 transition-colors">
              Zurück
            </button>
            @if (step() === 'summary') {
              <button type="button" (click)="save()"
                      class="rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-700 transition-colors">
                Speichern
              </button>
            } @else {
              <button type="button" (click)="next()" [disabled]="!stepValid()"
                      class="rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-700 disabled:bg-slate-300 transition-colors">
                Weiter
              </button>
            }
          </div>
        }
      </div>
    </div>
  `
})
export class CheckinStepperComponent {
  private storage = inject(CheckinDemoStorageService);

  steps = STEPS;
  scale = [1, 2, 3, 4, 5];
  locations = LOCATIONS;
  moodOptions = MOOD_OPTIONS;
  frequentSymptoms = FREQUENT_SYMPTOMS;
  otherSymptoms = OTHER_SYMPTOMS;
  illnessTypes = ILLNESS_TYPES;

  draft: CheckinDraft = emptyDraft();
  // A check-in saved earlier today (persisted in localStorage) reopens on the
  // done screen instead of restarting the flow.
  step = signal<Step>(this.storage.todayCompleted() ? 'done' : 'location');
  justSaved = signal(false);
  private lastPainKey = signal<string | null>(null);

  stepIndex = computed(() => STEPS.indexOf(this.step() as (typeof STEPS)[number]));

  restart() {
    this.draft = emptyDraft();
    this.justSaved.set(false);
    this.lastPainKey.set(null);
    this.step.set('location');
  }

  sleepActive(): boolean {
    return sleepBranchActive(this.draft);
  }

  stepValid(): boolean {
    switch (this.step()) {
      case 'location': return this.draft.location !== null;
      case 'mood': return this.draft.mood !== null;
      case 'energy': return this.draft.energy !== null && (!this.sleepActive() || this.draft.sleepRecovery !== null);
      case 'stress': return this.draft.stress !== null;
      case 'signals': return this.draft.sick !== null && (this.draft.sick === false || this.draft.illnessType === null || this.draft.illnessSubs.length > 0);
      default: return true;
    }
  }

  next() {
    const index = this.stepIndex();
    if (index < STEPS.length - 1) this.step.set(STEPS[index + 1]);
  }

  back() {
    const index = this.stepIndex();
    if (index > 0) this.step.set(STEPS[index - 1]);
  }

  hasSymptom(key: string): boolean {
    return this.draft.symptoms[key] !== undefined;
  }

  toggleSymptom(symptom: SymptomDefinition) {
    if (this.hasSymptom(symptom.key)) {
      delete this.draft.symptoms[symptom.key];
      if (this.lastPainKey() === symptom.key) this.lastPainKey.set(null);
      return;
    }
    this.draft.symptoms[symptom.key] = { region: symptom.regions[0], severity: 3 };
    if (symptom.pain) this.lastPainKey.set(symptom.key);
    if (symptom.key === 'fatigue') this.draft.sleepWanted = true;
  }

  expandedPainSymptom(): SymptomDefinition | null {
    const key = this.lastPainKey();
    if (!key || !this.hasSymptom(key)) return null;
    return [...FREQUENT_SYMPTOMS, ...OTHER_SYMPTOMS].find(s => s.key === key) ?? null;
  }

  setSymptomRegion(key: string, region: string) {
    this.draft.symptoms[key].region = region;
  }

  setSymptomSeverity(key: string, severity: number) {
    this.draft.symptoms[key].severity = severity;
  }

  setSick(sick: boolean) {
    this.draft.sick = sick;
    if (!sick) {
      this.draft.illnessType = null;
      this.draft.illnessSubs = [];
      this.draft.illnessSeverity = null;
    }
  }

  setIllnessType(type: IllnessTypeKey) {
    if (this.draft.illnessType !== type) {
      this.draft.illnessType = type;
      this.draft.illnessSubs = [];
    }
  }

  illnessSubs(): string[] {
    return illnessSubsFor(this.draft.illnessType);
  }

  toggleIllnessSub(sub: string) {
    this.draft.illnessSubs = this.draft.illnessSubs.includes(sub)
      ? this.draft.illnessSubs.filter(s => s !== sub)
      : [...this.draft.illnessSubs, sub];
  }

  adjustSleep(delta: number) {
    this.draft.sleepHours = Math.max(0, Math.min(14, this.draft.sleepHours + delta));
  }

  formatHours(hours: number): string {
    return hours.toFixed(1).replace('.', ',');
  }

  summaryRows(): Array<{ label: string; value: string }> {
    const location = LOCATIONS.find(l => l.value === this.draft.location)?.label ?? '–';
    const mood = MOOD_OPTIONS.find(m => m.value === this.draft.mood);
    const rows = [
      { label: 'Ort', value: location },
      { label: 'Stimmung', value: mood ? `${mood.emoji} ${mood.label}` : '–' },
      { label: 'Energie', value: `${this.draft.energy ?? '–'}/5` },
      { label: 'Stress', value: `${this.draft.stress ?? '–'}/5` },
    ];
    if (this.sleepActive()) {
      rows.push({ label: 'Schlaf', value: `${this.formatHours(this.draft.sleepHours)} h · Erholung ${this.draft.sleepRecovery ?? '–'}/5` });
    }
    const symptoms = Object.keys(this.draft.symptoms);
    rows.push({
      label: 'Körpersignale',
      value: symptoms.length
        ? symptoms.map(key => [...FREQUENT_SYMPTOMS, ...OTHER_SYMPTOMS].find(s => s.key === key)?.label ?? key).join(', ')
        : 'Keine',
    });
    rows.push({
      label: 'Krankheitsgefühl',
      value: this.draft.sick
        ? `Ja${this.draft.illnessType ? ` (${illnessLabelFor(this.draft.illnessType)}: ${this.draft.illnessSubs.join(', ')})` : ''}`
        : 'Nein',
    });
    return rows;
  }

  save() {
    if (!draftComplete(this.draft)) return;
    // Demo-only persistence: localStorage, never the API.
    this.storage.save(toDemoCheckin(this.draft, this.storage.todayKey()));
    this.justSaved.set(true);
    this.step.set('done');
  }
}
