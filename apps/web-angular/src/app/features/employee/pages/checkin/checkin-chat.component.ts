import { Component, inject, signal } from '@angular/core';
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
  draftComplete,
  emptyDraft,
  illnessLabelFor,
  illnessSubsFor,
  sleepBranchActive,
  toDemoCheckin,
} from './checkin-state';

type ChatStep =
  | 'location' | 'mood' | 'energy' | 'sleepHours' | 'sleepRecovery'
  | 'stress' | 'symptoms' | 'sick' | 'illnessType' | 'illnessSubs' | 'illnessSeverity'
  | 'confirm' | 'done';

interface ChatEntry {
  question: string;
  answer: string;
}

/**
 * 2c — conversational check-in: same data model as the 2a stepper, presented
 * one question at a time with an answered-history feed. localStorage only.
 */
@Component({
  selector: 'app-employee-checkin-chat',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="w-full p-4 space-y-6">
      <header class="flex items-center justify-between gap-4">
        <div class="flex items-center space-x-4">
          <a routerLink="/employee/dashboard" class="p-2 hover:bg-slate-100 rounded-full transition-colors text-slate-500">←</a>
          <h1 class="text-xl font-bold text-slate-800">Check-in als Chat</h1>
        </div>
        <a routerLink="/employee/checkin" class="text-xs font-semibold text-teal-600 hover:text-teal-700">Lieber geführt →</a>
      </header>

      <div class="bg-white rounded-3xl border border-slate-100 p-6 space-y-4 min-h-[420px]">
        @for (entry of history(); track $index) {
          <div class="space-y-2">
            <div class="max-w-[85%] rounded-2xl rounded-bl-sm bg-slate-50 px-4 py-2.5 text-sm text-slate-700">{{ entry.question }}</div>
            <div class="flex justify-end">
              <div class="max-w-[85%] rounded-2xl rounded-br-sm bg-teal-600 px-4 py-2.5 text-sm text-white">{{ entry.answer }}</div>
            </div>
          </div>
        }

        @if (step() !== 'done') {
          <div class="max-w-[85%] rounded-2xl rounded-bl-sm bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-800">
            {{ currentQuestion() }}
          </div>

          <div class="flex flex-wrap gap-2 pt-1">
            @switch (step()) {
              @case ('location') {
                @for (location of locations; track location.value) {
                  <button type="button" (click)="answerLocation(location.value, location.icon + ' ' + location.label)" class="chat-chip">{{ location.icon }} {{ location.label }}</button>
                }
              }
              @case ('mood') {
                @for (option of moodOptions; track option.value) {
                  <button type="button" (click)="answerMood(option.value, option.emoji + ' ' + option.label)" class="chat-chip">{{ option.emoji }} {{ option.label }}</button>
                }
              }
              @case ('energy') {
                @for (value of scale; track value) {
                  <button type="button" (click)="answerEnergy(value)" class="chat-chip">{{ value }}/5</button>
                }
              }
              @case ('sleepHours') {
                @for (hours of sleepHourOptions; track hours) {
                  <button type="button" (click)="answerSleepHours(hours)" class="chat-chip">{{ formatHours(hours) }} h</button>
                }
              }
              @case ('sleepRecovery') {
                @for (value of scale; track value) {
                  <button type="button" (click)="answerSleepRecovery(value)" class="chat-chip">{{ value }}/5</button>
                }
              }
              @case ('stress') {
                @for (value of scale; track value) {
                  <button type="button" (click)="answerStress(value)" class="chat-chip">{{ value }}/5</button>
                }
              }
              @case ('symptoms') {
                @for (symptom of allSymptoms; track symptom.key) {
                  <button type="button" (click)="toggleSymptom(symptom.key)"
                          class="chat-chip" [class.chat-chip-active]="selectedSymptoms().includes(symptom.key)">
                    {{ symptom.label }}
                  </button>
                }
                <button type="button" (click)="confirmSymptoms()" class="chat-chip-primary">
                  {{ selectedSymptoms().length ? 'Übernehmen' : 'Keine Beschwerden' }}
                </button>
              }
              @case ('sick') {
                <button type="button" (click)="answerSick(false)" class="chat-chip">Nein</button>
                <button type="button" (click)="answerSick(true)" class="chat-chip">Ja</button>
              }
              @case ('illnessType') {
                @for (type of illnessTypes; track type.key) {
                  <button type="button" (click)="answerIllnessType(type.key)" class="chat-chip">{{ type.label }}</button>
                }
              }
              @case ('illnessSubs') {
                @for (sub of illnessSubOptions(); track sub) {
                  <button type="button" (click)="toggleIllnessSub(sub)"
                          class="chat-chip" [class.chat-chip-active]="draft.illnessSubs.includes(sub)">
                    {{ sub }}
                  </button>
                }
                <button type="button" (click)="confirmIllnessSubs()" [disabled]="draft.illnessSubs.length === 0" class="chat-chip-primary disabled:opacity-40">Übernehmen</button>
              }
              @case ('illnessSeverity') {
                @for (value of scale; track value) {
                  <button type="button" (click)="answerIllnessSeverity(value)" class="chat-chip">{{ value }}/5</button>
                }
              }
              @case ('confirm') {
                <button type="button" (click)="save()" class="chat-chip-primary">Speichern ✓</button>
              }
            }
          </div>
        } @else {
          <div class="flex flex-col items-center text-center space-y-3 py-6">
            <div class="w-14 h-14 rounded-full bg-teal-50 flex items-center justify-center text-2xl">✓</div>
            @if (justSaved()) {
              <p class="text-sm font-bold text-slate-800">Check-in gespeichert · <span class="text-teal-600">+10 Punkte</span></p>
            } @else {
              <p class="text-sm font-bold text-slate-800">Check-in für heute erledigt</p>
            }
            <p class="text-xs text-slate-400 max-w-xs">Deine Angaben bleiben in dieser Demo lokal auf deinem Gerät gespeichert.</p>
            <div class="flex gap-3">
              <button type="button" (click)="restart()" class="rounded-lg border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors">Erneut ausfüllen</button>
              <a routerLink="/employee/dashboard" class="rounded-lg bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-700 transition-colors">Zurück zur Übersicht</a>
            </div>
          </div>
        }
      </div>
    </div>
  `,
  styles: [`
    .chat-chip {
      border-radius: 999px; border: 1px solid #e2e8f0; padding: 0.4rem 0.9rem;
      font-size: 0.75rem; font-weight: 600; color: #475569; transition: all .15s;
    }
    .chat-chip:hover { border-color: #99f6e4; }
    .chat-chip-active { border-color: #14b8a6; background: #f0fdfa; color: #0f766e; }
    .chat-chip-primary {
      border-radius: 999px; background: #0d9488; padding: 0.4rem 0.9rem;
      font-size: 0.75rem; font-weight: 600; color: white;
    }
  `]
})
export class CheckinChatComponent {
  private storage = inject(CheckinDemoStorageService);

  scale = [1, 2, 3, 4, 5];
  locations = LOCATIONS;
  moodOptions = MOOD_OPTIONS;
  allSymptoms = [...FREQUENT_SYMPTOMS, ...OTHER_SYMPTOMS];
  illnessTypes = ILLNESS_TYPES;
  sleepHourOptions = [5, 5.5, 6, 6.5, 7, 7.5, 8, 9];

  draft: CheckinDraft = emptyDraft();
  // A check-in saved earlier today (persisted in localStorage) reopens on the
  // done screen instead of restarting the conversation.
  step = signal<ChatStep>(this.storage.todayCompleted() ? 'done' : 'location');
  justSaved = signal(false);
  history = signal<ChatEntry[]>([]);
  selectedSymptoms = signal<string[]>([]);

  restart() {
    this.draft = emptyDraft();
    this.justSaved.set(false);
    this.history.set([]);
    this.selectedSymptoms.set([]);
    this.step.set('location');
  }

  private readonly questions: Record<Exclude<ChatStep, 'done'>, string> = {
    location: 'Wo bist du heute?',
    mood: 'Wie ist deine Stimmung?',
    energy: 'Wie viel Energie hast du? (1 = erschöpft, 5 = voller Energie)',
    sleepHours: 'Du wirkst müde — wie lange hast du geschlafen?',
    sleepRecovery: 'Und wie erholt fühlst du dich? (1–5)',
    stress: 'Wie gestresst bist du? (1 = entspannt, 5 = sehr gestresst)',
    symptoms: 'Gibt es Körpersignale, die du melden willst?',
    sick: 'Fühlst du dich krank?',
    illnessType: 'Was trifft eher zu?',
    illnessSubs: 'Welche Symptome hast du?',
    illnessSeverity: 'Wie stark insgesamt? (1–5)',
    confirm: 'Alles beisammen — soll ich deinen Check-in speichern? Du erhältst 10 Punkte.',
  };

  currentQuestion(): string {
    const step = this.step();
    return step === 'done' ? '' : this.questions[step];
  }

  private push(answer: string, next: ChatStep) {
    this.history.update(entries => [...entries, { question: this.currentQuestion(), answer }]);
    this.step.set(next);
  }

  answerLocation(value: CheckinDraft['location'], label: string) {
    this.draft.location = value;
    this.push(label, 'mood');
  }

  answerMood(value: number, label: string) {
    this.draft.mood = value;
    this.push(label, 'energy');
  }

  answerEnergy(value: number) {
    this.draft.energy = value;
    this.push(`${value}/5`, sleepBranchActive(this.draft) ? 'sleepHours' : 'stress');
  }

  answerSleepHours(hours: number) {
    this.draft.sleepWanted = true;
    this.draft.sleepHours = hours;
    this.push(`${this.formatHours(hours)} h`, 'sleepRecovery');
  }

  answerSleepRecovery(value: number) {
    this.draft.sleepRecovery = value;
    this.push(`${value}/5`, 'stress');
  }

  answerStress(value: number) {
    this.draft.stress = value;
    this.push(`${value}/5`, 'symptoms');
  }

  toggleSymptom(key: string) {
    this.selectedSymptoms.update(keys => keys.includes(key) ? keys.filter(k => k !== key) : [...keys, key]);
  }

  confirmSymptoms() {
    for (const key of this.selectedSymptoms()) {
      const symptom = this.allSymptoms.find(s => s.key === key);
      if (symptom) {
        this.draft.symptoms[key] = { region: symptom.regions[0], severity: 3 };
        if (key === 'fatigue') this.draft.sleepWanted = true;
      }
    }
    const labels = this.selectedSymptoms()
      .map(key => this.allSymptoms.find(s => s.key === key)?.label ?? key);
    this.push(labels.length ? labels.join(', ') : 'Keine Beschwerden', 'sick');
  }

  answerSick(sick: boolean) {
    this.draft.sick = sick;
    this.push(sick ? 'Ja' : 'Nein', sick ? 'illnessType' : 'confirm');
  }

  answerIllnessType(type: IllnessTypeKey) {
    this.draft.illnessType = type;
    this.draft.illnessSubs = [];
    this.push(illnessLabelFor(type), 'illnessSubs');
  }

  illnessSubOptions(): string[] {
    return illnessSubsFor(this.draft.illnessType);
  }

  toggleIllnessSub(sub: string) {
    this.draft.illnessSubs = this.draft.illnessSubs.includes(sub)
      ? this.draft.illnessSubs.filter(s => s !== sub)
      : [...this.draft.illnessSubs, sub];
  }

  confirmIllnessSubs() {
    if (this.draft.illnessSubs.length === 0) return;
    this.push(this.draft.illnessSubs.join(', '), 'illnessSeverity');
  }

  answerIllnessSeverity(value: number) {
    this.draft.illnessSeverity = value;
    this.push(`${value}/5`, 'confirm');
  }

  formatHours(hours: number): string {
    return hours.toFixed(1).replace('.', ',');
  }

  save() {
    if (!draftComplete(this.draft)) return;
    // Demo-only persistence: localStorage, never the API.
    this.storage.save(toDemoCheckin(this.draft, this.storage.todayKey()));
    this.justSaved.set(true);
    this.push('Speichern ✓', 'done');
  }
}
