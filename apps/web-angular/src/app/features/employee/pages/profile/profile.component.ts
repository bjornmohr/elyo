import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { EmployeeService } from '../../services/employee.service';
import { NotificationService } from '../../../../shared/notifications/notification.service';

type ScreeningView = 'overview' | 'intro' | 'questions' | 'complete';
type ScreeningAnswerMap = Record<string, number>;

interface ScreeningQuestion {
  id: string;
  sectionId: string;
  text: string;
  reverseScored?: boolean;
}

interface ScreeningSection {
  id: string;
  title: string;
  questions: ScreeningQuestion[];
}

interface DemoScreeningSubmission {
  completedAt: string;
  nextDueAt: string;
  answers: ScreeningAnswerMap;
  pointsPrepared: number;
}

const SCREENING_POINTS = 50;
const SCREENING_CYCLE_DAYS = 56;
const SCREENING_STORAGE_KEY = 'elyo.demo.screening.latest';

const SCALE_OPTIONS = [
  { value: 1, label: 'Trifft gar nicht zu' },
  { value: 2, label: 'Trifft eher nicht zu' },
  { value: 3, label: 'Teils/teils' },
  { value: 4, label: 'Trifft eher zu' },
  { value: 5, label: 'Trifft voll zu' },
];

const SCREENING_SECTIONS: ScreeningSection[] = [
  {
    id: 'wellbeing',
    title: 'Allgemeines Wohlbefinden',
    questions: [
      { id: 'wellbeing-health', sectionId: 'wellbeing', text: 'Ich fühle mich körperlich gesund und belastbar.' },
      { id: 'wellbeing-energy', sectionId: 'wellbeing', text: 'Ich habe im Alltag ausreichend Energie.' },
      { id: 'wellbeing-recovery', sectionId: 'wellbeing', text: 'Ich erhole mich gut nach körperlicher oder mentaler Belastung.' },
      { id: 'wellbeing-life-satisfaction', sectionId: 'wellbeing', text: 'Ich bin insgesamt zufrieden mit meinem aktuellen Leben.' },
      { id: 'wellbeing-complaints', sectionId: 'wellbeing', text: 'Ich bin weitgehend frei von Beschwerden, zum Beispiel Schmerzen, Verspannung oder Verdauungsbeschwerden.' },
    ],
  },
  {
    id: 'activity',
    title: 'Bewegung & Aktivität',
    questions: [
      { id: 'activity-daily-movement', sectionId: 'activity', text: 'Ich bewege mich im Alltag regelmäßig, zum Beispiel Gehen, Treppen oder aktive Wege.' },
      { id: 'activity-level', sectionId: 'activity', text: 'Ich erreiche an den meisten Tagen ein gutes Aktivitätsniveau, zum Beispiel etwa 7.000 oder mehr Schritte.' },
      { id: 'activity-sport', sectionId: 'activity', text: 'Ich mache mindestens 2-mal pro Woche gezielte körperliche Aktivität oder Sport.' },
      { id: 'activity-sitting', sectionId: 'activity', text: 'Ich sitze den Großteil des Tages, zum Beispiel mehr als 8 Stunden.', reverseScored: true },
      { id: 'activity-after-movement', sectionId: 'activity', text: 'Nach Bewegung fühle ich mich eher energiegeladen als erschöpft.' },
    ],
  },
  {
    id: 'nutrition',
    title: 'Ernährung & Hydration',
    questions: [
      { id: 'nutrition-fluid', sectionId: 'nutrition', text: 'Ich trinke täglich ausreichend Flüssigkeit, etwa 1,5 bis 2 Liter Wasser oder Tee.' },
      { id: 'nutrition-fresh-food', sectionId: 'nutrition', text: 'Ich esse täglich frische, nährstoffreiche Lebensmittel, zum Beispiel Gemüse oder Obst.' },
      { id: 'nutrition-processed-food', sectionId: 'nutrition', text: 'Ich konsumiere selten stark verarbeitete Lebensmittel.' },
      { id: 'nutrition-mindful-meals', sectionId: 'nutrition', text: 'Ich esse meine Mahlzeiten überwiegend bewusst, ohne starke Ablenkung.' },
    ],
  },
  {
    id: 'sleep',
    title: 'Schlaf & Regeneration',
    questions: [
      { id: 'sleep-duration', sectionId: 'sleep', text: 'Ich schlafe im Durchschnitt ausreichend, etwa 7 bis 8 Stunden.' },
      { id: 'sleep-falling-asleep', sectionId: 'sleep', text: 'Ich kann abends gut abschalten und einschlafen.' },
      { id: 'sleep-through-night', sectionId: 'sleep', text: 'Ich schlafe die Nacht überwiegend durch.' },
      { id: 'sleep-rested', sectionId: 'sleep', text: 'Ich wache morgens erholt auf.' },
      { id: 'sleep-restless', sectionId: 'sleep', text: 'Ich habe einen unruhigen Schlaf oder leide unter nächtlichem Aufwachen.', reverseScored: true },
    ],
  },
  {
    id: 'stress',
    title: 'Stress & mentale Belastung',
    questions: [
      { id: 'stress-time-pressure', sectionId: 'stress', text: 'Ich fühle mich im Alltag häufig unter Zeitdruck oder Stress.', reverseScored: true },
      { id: 'stress-evening-exhaustion', sectionId: 'stress', text: 'Ich fühle mich am Ende des Tages erschöpft.', reverseScored: true },
      { id: 'stress-switch-off', sectionId: 'stress', text: 'Ich kann nach der Arbeit gut abschalten.' },
      { id: 'stress-daily-control', sectionId: 'stress', text: 'Ich habe das Gefühl, meinen Alltag gut im Griff zu haben.' },
      { id: 'stress-body-reactions', sectionId: 'stress', text: 'Ich bemerke stressbedingte körperliche Reaktionen, zum Beispiel Herzklopfen, Muskelverspannungen oder Magenbeschwerden.', reverseScored: true },
    ],
  },
  {
    id: 'selfcare',
    title: 'Umfeld & Selbstfürsorge',
    questions: [
      { id: 'selfcare-support', sectionId: 'selfcare', text: 'Ich fühle mich in meinem Umfeld unterstützt.' },
      { id: 'selfcare-time', sectionId: 'selfcare', text: 'Ich nehme mir regelmäßig bewusst Zeit für mich selbst.' },
      { id: 'selfcare-boundaries', sectionId: 'selfcare', text: 'Ich kann meine Grenzen gut setzen.' },
      { id: 'selfcare-energy-strategies', sectionId: 'selfcare', text: 'Ich kenne Strategien, die mir helfen, neue Energie zu tanken.' },
    ],
  },
  {
    id: 'substances',
    title: 'Substanzkonsum & Kompensation',
    questions: [
      { id: 'substances-alcohol', sectionId: 'substances', text: 'Ich konsumiere regelmäßig Alkohol.', reverseScored: true },
      { id: 'substances-nicotine', sectionId: 'substances', text: 'Ich konsumiere Nikotin, zum Beispiel Zigaretten oder Vapes.', reverseScored: true },
      { id: 'substances-caffeine', sectionId: 'substances', text: 'Ich benötige Koffein, um durch den Tag zu kommen.', reverseScored: true },
      { id: 'substances-stress-food', sectionId: 'substances', text: 'Ich greife bei Stress häufig zu Essen, zum Beispiel Snacks oder Süßes.', reverseScored: true },
    ],
  },
  {
    id: 'focus',
    title: 'Kognitive Leistungsfähigkeit & Fokus',
    questions: [
      { id: 'focus-concentration', sectionId: 'focus', text: 'Wenn ich mich körperlich oder mental unwohl fühle, fällt es mir schwer, mich über längere Zeit auf meine Arbeitsaufgaben zu konzentrieren.', reverseScored: true },
      { id: 'focus-errors', sectionId: 'focus', text: 'Ich bemerke in meinem Arbeitsalltag, dass mir aufgrund von Müdigkeit oder Erschöpfung häufiger Flüchtigkeitsfehler unterlaufen.', reverseScored: true },
      { id: 'focus-presenteeism', sectionId: 'focus', text: 'Ich gehe trotz spürbarer gesundheitlicher Einschränkungen zur Arbeit, obwohl ein Erholungstag für meinen Körper medizinisch sinnvoller wäre.', reverseScored: true },
    ],
  },
  {
    id: 'workload',
    title: 'Quantitative Arbeitsbelastung & Zeitdruck',
    questions: [
      { id: 'workload-amount', sectionId: 'workload', text: 'Die Menge der Arbeit und die vorgegebenen Termine beziehungsweise Fristen sind in meinem Arbeitsalltag oft kaum zu bewältigen.', reverseScored: true },
      { id: 'workload-breaks', sectionId: 'workload', text: 'Meine Arbeit ist so intensiv oder eng getaktet, dass ich regelmäßig auf Pausen verzichten muss, um mein Pensum zu schaffen.', reverseScored: true },
      { id: 'workload-interruptions', sectionId: 'workload', text: 'Häufige Unterbrechungen, Störungen oder unvorhergesehene Planänderungen erschweren es mir, meine eigentliche Arbeit strukturiert zu erledigen.', reverseScored: true },
    ],
  },
  {
    id: 'work-climate',
    title: 'Arbeitsklima & Soziale Unterstützung',
    questions: [
      { id: 'work-climate-colleagues', sectionId: 'work-climate', text: 'Wenn an meinem Arbeitsplatz Probleme oder hohe Belastungsspitzen auftreten, kann ich mich auf die praktische Unterstützung meiner Kollegen verlassen.' },
      { id: 'work-climate-leadership', sectionId: 'work-climate', text: 'Ich erlebe von meinen direkten Vorgesetzten Anerkennung für meine Leistung und erhalte bei Bedarf konstruktives Feedback.' },
      { id: 'work-climate-conflicts', sectionId: 'work-climate', text: 'Konflikte oder Meinungsverschiedenheiten in meinem Team werden offen, sachlich und respektvoll gelöst.' },
    ],
  },
  {
    id: 'work-satisfaction',
    title: 'Arbeitszufriedenheit & Belohnung',
    questions: [
      { id: 'work-satisfaction-meaning', sectionId: 'work-satisfaction', text: 'Ich erlebe meine tägliche Arbeit als sinnvoll und habe das Gefühl, mit meiner Leistung einen wichtigen Beitrag zu leisten.' },
      { id: 'work-satisfaction-future', sectionId: 'work-satisfaction', text: 'Wenn ich an meine berufliche Zukunft in diesem Unternehmen denke, fühle ich mich sicher und blicke positiv nach vorne.' },
      { id: 'work-satisfaction-reward', sectionId: 'work-satisfaction', text: 'Die Ausgewogenheit zwischen dem Aufwand, den ich für meine Arbeit betreibe, und der Wertschätzung oder den Entwicklungschancen, die ich zurückerhalte, stimmt für mich.' },
    ],
  },
];

@Component({
  selector: 'app-profile',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  template: `
    <div class="w-full max-w-[min(100%,76rem)] space-y-7">
      <header class="flex items-center space-x-4">
        <a routerLink="/employee" class="inline-flex min-h-11 min-w-11 items-center justify-center p-2.5 hover:bg-slate-100 rounded-full transition-colors text-slate-500">←</a>
        <div>
          <h1 class="text-2xl font-bold text-slate-800">Gesundheitsprofil</h1>
          <p class="mt-1 text-sm text-slate-500">Screening, Basisdaten und Dokumente für deine persönliche Orientierung.</p>
        </div>
      </header>

      @switch (screeningView()) {
        @case ('overview') {
          <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="rounded-[28px] border border-slate-100 bg-white p-7 shadow-sm">
              <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                <div>
                  <p class="text-sm font-semibold uppercase tracking-wide text-teal-700">Alle 8 Wochen</p>
                  <h2 class="mt-2 text-2xl font-bold text-slate-800">Screening-Bogen</h2>
                  <p class="mt-3 max-w-2xl text-base leading-relaxed text-slate-500">
                    Alle 8 Wochen fällig. Deine Antworten helfen dir, Entwicklungen über Zeit besser einzuordnen.
                  </p>
                </div>
                <span class="inline-flex self-start rounded-full px-4 py-2 text-sm font-bold" [ngClass]="screeningDue() ? 'bg-amber-50 text-amber-700' : 'bg-teal-50 text-teal-700'">
                  {{ screeningDue() ? 'fällig' : 'aktuell' }}
                </span>
              </div>

              <div class="mt-6 grid grid-cols-[repeat(auto-fit,minmax(min(100%,13rem),1fr))] gap-4">
                <div class="rounded-2xl bg-slate-50 p-4">
                  <p class="text-sm font-semibold text-slate-500">Dauer</p>
                  <p class="mt-1 text-lg font-bold text-slate-800">ca. 5-8 Minuten</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                  <p class="text-sm font-semibold text-slate-500">Belohnung</p>
                  <p class="mt-1 text-lg font-bold text-slate-800">+{{ screeningPoints }} Punkte</p>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                  <p class="text-sm font-semibold text-slate-500">Status</p>
                  <p class="mt-1 text-lg font-bold text-slate-800">{{ screeningStatusLabel() }}</p>
                </div>
              </div>

              @if (latestScreening(); as latest) {
                <div class="mt-6 rounded-2xl border border-slate-100 p-4">
                  <p class="text-sm text-slate-500">Zuletzt ausgefüllt am {{ latest.completedAt | date:'dd.MM.yyyy' }}</p>
                  <p class="mt-1 text-sm font-semibold text-slate-700">Nächster Screening-Termin: {{ latest.nextDueAt | date:'dd.MM.yyyy' }}</p>
                </div>
              }

              <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
                <button type="button"
                        (click)="openScreening()"
                        class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-teal-600 px-6 py-3 text-base font-bold text-white shadow-lg shadow-teal-100 transition-colors hover:bg-teal-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">
                  {{ screeningDue() ? 'Screening starten' : 'Screening ansehen' }}
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
          </section>
        }

        @case ('intro') {
          <section class="rounded-[28px] border border-slate-100 bg-white p-7 shadow-sm">
            <button type="button" (click)="screeningView.set('overview')" class="mb-6 inline-flex min-h-11 items-center rounded-full px-4 text-sm font-semibold text-slate-500 hover:bg-slate-100">← Zurück</button>
            <p class="text-sm font-semibold uppercase tracking-wide text-teal-700">Screening-Bogen</p>
            <h2 class="mt-2 text-2xl font-bold text-slate-800">Ein geführter Blick auf deine Entwicklung</h2>
            <p class="mt-3 max-w-3xl text-base leading-relaxed text-slate-500">
              Beantworte die Aussagen auf einer Skala von 1 bis 5. Es geht um persönliche Orientierung und darum, Empfehlungen später besser auf deine Situation abzustimmen.
            </p>

            <div class="mt-6 grid grid-cols-[repeat(auto-fit,minmax(min(100%,16rem),1fr))] gap-4">
              <div class="rounded-2xl bg-slate-50 p-5">
                <p class="text-sm font-semibold text-slate-500">Umfang</p>
                <p class="mt-1 text-lg font-bold text-slate-800">{{ screeningSections.length }} Abschnitte · {{ totalQuestionCount() }} Fragen</p>
              </div>
              <div class="rounded-2xl bg-slate-50 p-5">
                <p class="text-sm font-semibold text-slate-500">Punkte</p>
                <p class="mt-1 text-lg font-bold text-slate-800">+{{ screeningPoints }} nach Abschluss</p>
              </div>
              <div class="rounded-2xl bg-slate-50 p-5">
                <p class="text-sm font-semibold text-slate-500">Rhythmus</p>
                <p class="mt-1 text-lg font-bold text-slate-800">alle 8 Wochen</p>
              </div>
            </div>

            <p class="mt-6 rounded-2xl bg-teal-50 p-4 text-sm leading-relaxed text-teal-800">
              Deine Antworten sind für deine persönliche Orientierung gedacht. Unternehmen sehen keine individuellen Antworten.
            </p>

            <button type="button"
                    (click)="startScreening()"
                    class="mt-6 inline-flex min-h-11 items-center justify-center rounded-2xl bg-teal-600 px-6 py-3 text-base font-bold text-white shadow-lg shadow-teal-100 transition-colors hover:bg-teal-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">
              Abschnitt 1 starten
            </button>
          </section>
        }

        @case ('questions') {
          @if (currentSection(); as section) {
            <section class="rounded-[28px] border border-slate-100 bg-white p-6 shadow-sm sm:p-7">
              <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                  <p class="text-sm font-semibold uppercase tracking-wide text-teal-700">
                    {{ screeningReviewMode() ? 'Ansicht' : 'Abschnitt' }} {{ currentSectionIndex() + 1 }} von {{ screeningSections.length }}
                  </p>
                  <h2 class="mt-2 text-2xl font-bold text-slate-800">{{ section.title }}</h2>
                  <p class="mt-2 text-sm text-slate-500">
                    {{ screeningReviewMode() ? 'Deine zuletzt gespeicherten Demo-Antworten. Vor dem nächsten Termin gibt es keine weiteren Punkte.' : 'Wähle pro Aussage den Wert, der aktuell am besten passt.' }}
                  </p>
                </div>
                <span class="inline-flex rounded-full bg-slate-100 px-4 py-2 text-sm font-bold text-slate-700">{{ answeredCount() }}/{{ totalQuestionCount() }} beantwortet</span>
              </div>

              <div class="mt-6 h-2 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-teal-500 transition-all" [style.width.%]="progressPercent()"></div>
              </div>

              <div class="mt-7 space-y-5">
                @for (question of section.questions; track question.id) {
                  <div class="rounded-[20px] border border-slate-100 p-5">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                      <p class="text-base font-semibold leading-relaxed text-slate-800">{{ question.text }}</p>
                      @if (question.reverseScored) {
                        <span class="inline-flex self-start rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">umgekehrt gewertet</span>
                      }
                    </div>
                    <div class="mt-4 grid grid-cols-5 gap-2">
                      @for (option of scaleOptions; track option.value) {
                        <button type="button"
                                (click)="answer(question.id, option.value)"
                                [disabled]="screeningReviewMode()"
                                [attr.aria-label]="question.text + ': ' + option.label"
                                class="min-h-11 rounded-2xl border px-2 py-3 text-sm font-bold transition-all focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 disabled:cursor-default"
                                [ngClass]="answerClass(question.id, option.value)">
                          {{ option.value }}
                        </button>
                      }
                    </div>
                    <div class="mt-3 grid grid-cols-5 gap-2 text-center text-xs font-semibold text-slate-500">
                      @for (option of scaleOptions; track option.value) {
                        <span>{{ option.value === 1 || option.value === 3 || option.value === 5 ? option.label : '' }}</span>
                      }
                    </div>
                  </div>
                }
              </div>

              @if (sectionValidationMessage()) {
                <p class="mt-5 rounded-2xl bg-amber-50 p-4 text-sm font-semibold text-amber-800">{{ sectionValidationMessage() }}</p>
              }

              <div class="mt-7 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                <button type="button"
                        (click)="previousSection()"
                        class="inline-flex min-h-11 items-center justify-center rounded-2xl border border-slate-200 px-6 py-3 text-base font-bold text-slate-700 transition-colors hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">
                  {{ currentSectionIndex() === 0 ? (screeningReviewMode() ? 'Zum Profil' : 'Zur Einführung') : 'Zurück' }}
                </button>
                <button type="button"
                        (click)="nextSection()"
                        class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-teal-600 px-6 py-3 text-base font-bold text-white shadow-lg shadow-teal-100 transition-colors hover:bg-teal-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">
                  {{ isLastSection() ? (screeningReviewMode() ? 'Zum Profil' : 'Screening abschließen') : 'Weiter' }}
                </button>
              </div>
            </section>
          }
        }

        @case ('complete') {
          <section class="rounded-[28px] border border-teal-100 bg-white p-8 text-center shadow-sm">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-teal-50 text-3xl">✓</div>
            <h2 class="mt-5 text-2xl font-bold text-slate-800">Screening abgeschlossen</h2>
            <p class="mx-auto mt-3 max-w-2xl text-base leading-relaxed text-slate-500">
              Danke dir. Deine Antworten helfen, Empfehlungen künftig besser auf deine Situation abzustimmen.
            </p>
            <div class="mx-auto mt-6 grid max-w-2xl grid-cols-[repeat(auto-fit,minmax(min(100%,14rem),1fr))] gap-4">
              <div class="rounded-2xl bg-teal-50 p-5">
                <p class="text-sm font-semibold text-teal-700">Belohnung vorbereitet</p>
                <p class="mt-1 text-3xl font-bold text-teal-800">+{{ screeningPoints }}</p>
                <p class="mt-1 text-sm text-teal-700">Punkte</p>
              </div>
              <div class="rounded-2xl bg-slate-50 p-5">
                <p class="text-sm font-semibold text-slate-500">Nächster Termin</p>
                <p class="mt-1 text-lg font-bold text-slate-800">{{ nextDueDate() | date:'dd.MM.yyyy' }}</p>
                <p class="mt-1 text-sm text-slate-500">in 8 Wochen</p>
              </div>
            </div>
            <p class="mx-auto mt-6 max-w-2xl rounded-2xl bg-slate-50 p-4 text-sm leading-relaxed text-slate-500">
              Badges für regelmäßige Screenings folgen bald. Für dieses MVP wird der Abschluss lokal als Demo-Zustand gespeichert.
            </p>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center">
              <button type="button"
                      (click)="screeningView.set('overview')"
                      class="inline-flex min-h-11 items-center justify-center rounded-2xl bg-teal-600 px-6 py-3 text-base font-bold text-white shadow-lg shadow-teal-100 transition-colors hover:bg-teal-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">
                Zum Profil
              </button>
              <a routerLink="/employee/dashboard" class="inline-flex min-h-11 items-center justify-center rounded-2xl border border-slate-200 px-6 py-3 text-base font-bold text-slate-700 transition-colors hover:bg-slate-50">
                Zur Übersicht
              </a>
            </div>
          </section>
        }
      }

      @if (screeningView() === 'overview') {
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
          <div class="p-6 bg-slate-50 border-b border-slate-100">
            <h2 class="text-lg font-bold text-slate-800">Optionale Basisdaten</h2>
            <p class="text-sm text-slate-500">Diese Angaben bleiben getrennt vom neuen Screening-Bogen und können weiter gepflegt werden.</p>
          </div>

          <form (ngSubmit)="save()" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <label class="space-y-1">
              <span class="text-sm font-semibold text-slate-600">Name</span>
              <input [(ngModel)]="form.name" name="name" required class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500">
            </label>
            <label class="space-y-1">
              <span class="text-sm font-semibold text-slate-600">Geburtsjahr</span>
              <input type="number" [(ngModel)]="form.birthYear" name="birthYear" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500">
            </label>
            <label class="space-y-1">
              <span class="text-sm font-semibold text-slate-600">Biologisches Geschlecht</span>
              <select [(ngModel)]="form.biologicalSex" name="biologicalSex" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500">
                <option value="PREFER_NOT_TO_SAY">Keine Angabe</option>
                <option value="MALE">Männlich</option>
                <option value="FEMALE">Weiblich</option>
                <option value="OTHER">Divers</option>
              </select>
            </label>
            <label class="space-y-1">
              <span class="text-sm font-semibold text-slate-600">Aktivitätsniveau</span>
              <select [(ngModel)]="form.activityLevel" name="activityLevel" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500">
                <option value="">Nicht gesetzt</option><option>NIEDRIG</option><option>MITTEL</option><option>HOCH</option>
              </select>
            </label>
            <label class="space-y-1">
              <span class="text-sm font-semibold text-slate-600">Schlafqualität</span>
              <select [(ngModel)]="form.sleepQuality" name="sleepQuality" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500">
                <option value="">Nicht gesetzt</option><option>SCHLECHT</option><option>OKAY</option><option>GUT</option>
              </select>
            </label>
            <label class="space-y-1">
              <span class="text-sm font-semibold text-slate-600">Stressneigung</span>
              <select [(ngModel)]="form.stressTendency" name="stressTendency" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500">
                <option value="">Nicht gesetzt</option><option>NIEDRIG</option><option>MITTEL</option><option>HOCH</option>
              </select>
            </label>
            <label class="space-y-1">
              <span class="text-sm font-semibold text-slate-600">Rauchstatus</span>
              <select [(ngModel)]="form.smokingStatus" name="smokingStatus" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500">
                <option value="">Nicht gesetzt</option><option>NIE</option><option>FRÜHER</option><option>AKTUELL</option>
              </select>
            </label>
            <label class="space-y-1">
              <span class="text-sm font-semibold text-slate-600">Ernährungsform</span>
              <input [(ngModel)]="form.nutritionType" name="nutritionType" class="w-full p-3 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-2 focus:ring-teal-500">
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-700 md:col-span-2">
              <input type="checkbox" [(ngModel)]="form.hasMedication" name="hasMedication" class="rounded border-slate-300 text-teal-600">
              Regelmäßige Medikamente
            </label>
            <div class="md:col-span-2 flex flex-col gap-3 pt-2 sm:flex-row sm:items-center sm:justify-between">
              <span class="text-sm text-slate-500">Vollständigkeit: {{ profile()?.anamnesis?.completionPct ?? 0 }}%</span>
              <button type="submit" class="min-h-11 px-5 py-3 bg-teal-600 text-white font-bold rounded-2xl shadow-lg shadow-teal-100">Basisdaten speichern</button>
            </div>
          </form>
        </div>

        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
          <div class="p-6 bg-slate-50 border-b border-slate-100">
            <h2 class="text-lg font-bold text-slate-800">Medizinische PDFs</h2>
            <p class="text-sm text-slate-500">Lade zusätzliche medizinische Dokumente hoch. Uploads können Punkte bringen.</p>
          </div>
          <div class="p-6 space-y-4">
            <input type="file" accept="application/pdf" (change)="upload($event)" class="block w-full text-sm text-slate-500">
            @if (uploadError()) {
              <p class="text-sm text-red-600">{{ uploadError() }}</p>
            }
            @for (document of documents(); track document.id) {
              <div class="flex items-center justify-between rounded-2xl bg-slate-50 p-4">
                <span class="font-medium text-slate-700">{{ document.fileName }}</span>
                <span class="text-sm text-slate-500">{{ document.uploadedAt | date:'mediumDate' }}</span>
              </div>
            }
          </div>
        </div>
      }
    </div>
  `
})
export class ProfileComponent implements OnInit {
  private employeeService = inject(EmployeeService);
  private notifications = inject(NotificationService);
  profile = signal<any>(null);
  documents = signal<any[]>([]);
  uploadError = signal<string | null>(null);
  screeningView = signal<ScreeningView>('overview');
  currentSectionIndex = signal(0);
  screeningAnswers = signal<ScreeningAnswerMap>({});
  latestScreening = signal<DemoScreeningSubmission | null>(null);
  sectionValidationMessage = signal<string | null>(null);
  screeningReviewMode = signal(false);

  readonly screeningSections = SCREENING_SECTIONS;
  readonly scaleOptions = SCALE_OPTIONS;
  readonly screeningPoints = SCREENING_POINTS;

  screeningDue = computed(() => {
    const latest = this.latestScreening();
    if (!latest) return true;
    return new Date() >= new Date(latest.nextDueAt);
  });

  currentSection = computed(() => this.screeningSections[this.currentSectionIndex()] ?? null);
  totalQuestionCount = computed(() => this.screeningSections.reduce((sum, section) => sum + section.questions.length, 0));
  answeredCount = computed(() => Object.keys(this.screeningAnswers()).length);
  progressPercent = computed(() => Math.round((this.answeredCount() / this.totalQuestionCount()) * 100));
  nextDueDate = computed(() => this.latestScreening()?.nextDueAt ?? this.addDays(new Date(), SCREENING_CYCLE_DAYS).toISOString());

  form: any = {
    name: '',
    birthYear: null,
    biologicalSex: 'PREFER_NOT_TO_SAY',
    activityLevel: '',
    sleepQuality: '',
    stressTendency: '',
    smokingStatus: '',
    nutritionType: '',
    chronicPatterns: [],
    hasMedication: false,
  };

  ngOnInit() {
    this.latestScreening.set(this.readDemoScreening());
    this.employeeService.getProfile().subscribe((res: any) => {
      const data = res.data;
      const anamnesis = data.anamnesis ?? {};
      this.profile.set(data);
      this.documents.set(data.documents ?? []);
      this.form = {
        name: data.name,
        birthYear: anamnesis.birthYear ?? null,
        biologicalSex: anamnesis.biologicalSex ?? 'PREFER_NOT_TO_SAY',
        activityLevel: anamnesis.activityLevel ?? '',
        sleepQuality: anamnesis.sleepQuality ?? '',
        stressTendency: anamnesis.stressTendency ?? '',
        smokingStatus: anamnesis.smokingStatus ?? '',
        nutritionType: anamnesis.nutritionType ?? '',
        chronicPatterns: anamnesis.chronicPatterns ?? [],
        hasMedication: anamnesis.hasMedication ?? false,
      };
    });
  }

  openScreening(): void {
    if (!this.screeningDue() && this.latestScreening()) {
      this.screeningReviewMode.set(true);
      this.screeningAnswers.set({ ...this.latestScreening()!.answers });
      this.currentSectionIndex.set(0);
      this.sectionValidationMessage.set(null);
      this.screeningView.set('questions');
      return;
    }

    this.screeningReviewMode.set(false);
    this.screeningAnswers.set({});
    this.sectionValidationMessage.set(null);
    this.screeningView.set('intro');
  }

  startScreening(): void {
    this.screeningReviewMode.set(false);
    this.currentSectionIndex.set(0);
    this.sectionValidationMessage.set(null);
    this.screeningView.set('questions');
  }

  answer(questionId: string, value: number): void {
    if (this.screeningReviewMode()) return;
    this.screeningAnswers.update(answers => ({ ...answers, [questionId]: value }));
    this.sectionValidationMessage.set(null);
  }

  answerClass(questionId: string, value: number): string {
    return this.screeningAnswers()[questionId] === value
      ? 'border-teal-600 bg-teal-600 text-white shadow-sm'
      : 'border-slate-200 bg-white text-slate-700 hover:border-teal-300 hover:bg-teal-50';
  }

  previousSection(): void {
    this.sectionValidationMessage.set(null);
    if (this.currentSectionIndex() === 0) {
      this.screeningView.set(this.screeningReviewMode() ? 'overview' : 'intro');
      return;
    }
    this.currentSectionIndex.update(index => index - 1);
  }

  nextSection(): void {
    if (!this.currentSectionComplete()) {
      this.sectionValidationMessage.set('Bitte beantworte alle Aussagen in diesem Abschnitt, bevor du fortfährst.');
      return;
    }

    this.sectionValidationMessage.set(null);
    if (this.isLastSection()) {
      if (this.screeningReviewMode()) {
        this.screeningView.set('overview');
        return;
      }
      this.completeScreening();
      return;
    }

    this.currentSectionIndex.update(index => index + 1);
  }

  isLastSection(): boolean {
    return this.currentSectionIndex() === this.screeningSections.length - 1;
  }

  screeningStatusLabel(): string {
    return this.screeningDue() ? 'jetzt fällig' : 'nicht fällig';
  }

  private currentSectionComplete(): boolean {
    const section = this.currentSection();
    if (!section) return false;
    const answers = this.screeningAnswers();
    return section.questions.every(question => answers[question.id] !== undefined);
  }

  private completeScreening(): void {
    const completedAt = new Date();
    const submission: DemoScreeningSubmission = {
      completedAt: completedAt.toISOString(),
      nextDueAt: this.addDays(completedAt, SCREENING_CYCLE_DAYS).toISOString(),
      answers: this.screeningAnswers(),
      pointsPrepared: SCREENING_POINTS,
    };

    // TODO: Replace localStorage with employee screening endpoints and award
    // a backend points event once recurring screening persistence exists.
    this.writeDemoScreening(submission);
    this.latestScreening.set(submission);
    this.screeningView.set('complete');
  }

  private readDemoScreening(): DemoScreeningSubmission | null {
    try {
      const raw = localStorage.getItem(SCREENING_STORAGE_KEY);
      return raw ? JSON.parse(raw) as DemoScreeningSubmission : null;
    } catch {
      return null;
    }
  }

  private writeDemoScreening(submission: DemoScreeningSubmission): void {
    try {
      localStorage.setItem(SCREENING_STORAGE_KEY, JSON.stringify(submission));
    } catch {
      // Demo state is lost when localStorage is unavailable; the API is not touched.
    }
  }

  private addDays(date: Date, days: number): Date {
    const next = new Date(date);
    next.setDate(next.getDate() + days);
    return next;
  }

  save() {
    this.employeeService.updateProfile(this.form).subscribe({
      next: (res: any) => {
        this.profile.set({ ...this.profile(), ...res.data });
        this.notifications.success('Anamnese wurde gespeichert.');
      },
      error: err => {
        this.notifications.error(err.error?.message || 'Anamnese konnte nicht gespeichert werden.');
      },
    });
  }

  upload(event: Event) {
    this.uploadError.set(null);
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;
    if (file.type !== 'application/pdf') {
      this.uploadError.set('Es können nur PDF-Dateien hochgeladen werden.');
      return;
    }
    this.employeeService.uploadDocument(file).subscribe({
      next: (res: any) => {
        this.documents.update(documents => [res.data, ...documents]);
        this.notifications.success('Dokument wurde gespeichert.');
      },
      error: err => {
        const message = err.error?.message || 'Upload fehlgeschlagen.';
        this.uploadError.set(message);
        this.notifications.error(message);
      },
    });
  }
}
