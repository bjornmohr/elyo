import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { EmployeeService, WellbeingEntry } from '../../services/employee.service';

interface SummaryCard {
  label: string;
  value: string;
  subline: string;
}

interface DailyCheckInCard {
  date: Date;
  dateKey: string;
  entry: WellbeingEntry | null;
  isToday: boolean;
}

interface DetailRow {
  label: string;
  value: string;
  hint: string | null;
}

@Component({
  selector: 'app-history',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="w-full max-w-[min(100%,76rem)] space-y-7">
      <header class="flex flex-col gap-4 sm:flex-row sm:items-start">
        <a routerLink="/employee"
           aria-label="Zurück zur Übersicht"
           class="inline-flex min-h-11 min-w-11 items-center justify-center self-start rounded-full p-2.5 text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700">
          ←
        </a>
        <div>
          <h1 class="text-[30px] font-bold leading-tight text-slate-800">Dein Check-in Verlauf</h1>
          <p class="mt-2 max-w-2xl text-base leading-relaxed text-slate-500">
            Sieh dir an, wie sich Wohlbefinden, Energie und Stress über die letzten Tage entwickelt haben.
          </p>
        </div>
      </header>

      @if (isLoading()) {
        <section class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,16rem),1fr))] gap-5" aria-label="Verlauf wird geladen">
          @for (item of loadingCards; track item) {
            <div class="min-h-40 animate-pulse rounded-[20px] border border-slate-100 bg-white p-5">
              <div class="h-4 w-24 rounded bg-slate-100"></div>
              <div class="mt-8 h-8 w-20 rounded bg-slate-100"></div>
              <div class="mt-5 h-4 w-full rounded bg-slate-100"></div>
              <div class="mt-3 h-4 w-2/3 rounded bg-slate-100"></div>
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
             class="mt-6 inline-flex min-h-11 items-center justify-center rounded-2xl bg-teal-600 px-8 py-3 text-base font-bold text-white shadow-lg shadow-teal-100 transition-colors hover:bg-teal-700">
            Check-in starten
          </a>
        </section>
      } @else {
        <section class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,15rem),1fr))] gap-5" aria-label="Zusammenfassung">
          @for (card of summaryCards(); track card.label) {
            <div class="rounded-[20px] border border-slate-100 bg-white p-5">
              <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ card.label }}</p>
              <p class="mt-2 text-3xl font-bold leading-tight text-slate-800">{{ card.value }}</p>
              <p class="mt-2 text-sm leading-relaxed text-slate-500">{{ card.subline }}</p>
            </div>
          }
        </section>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem] lg:items-start">
          <section aria-label="Tägliche Check-ins">
            <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
              <div>
                <h2 class="text-xl font-bold text-slate-800">Letzte 14 Tage</h2>
                <p class="mt-1 text-sm text-slate-500">Wähle einen erledigten Check-in aus, um die Details zu sehen.</p>
              </div>
            </div>

            <div class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,16rem),1fr))] gap-5">
              @for (card of checkInCards(); track card.dateKey) {
                <button type="button"
                        [disabled]="!card.entry"
                        [attr.aria-pressed]="card.entry ? isSelected(card.entry) : null"
                        (click)="card.entry && selectCheckIn(card.entry)"
                        class="group min-h-[15rem] rounded-[20px] border p-5 text-left transition-all focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 disabled:cursor-default"
                        [ngClass]="cardClass(card)">
                  <div class="flex items-start justify-between gap-4">
                    <div>
                      <p class="text-sm font-bold uppercase tracking-wide text-slate-500">{{ formatWeekday(card.date) }}</p>
                      <p class="mt-1 text-lg font-bold text-slate-800">{{ formatDateLabel(card.date) }}</p>
                    </div>
                    <span class="rounded-full px-3 py-1.5 text-sm font-semibold" [ngClass]="statusClass(card)">
                      {{ statusLabel(card) }}
                    </span>
                  </div>

                  @if (card.entry; as entry) {
                    <div class="mt-6">
                      <div class="flex items-baseline gap-2">
                        <span class="text-[34px] font-black leading-none" [ngClass]="scoreTextClass(entry.score)">
                          {{ formatScore(entry.score) }}
                        </span>
                        <span class="text-base font-semibold text-slate-500">/ 5</span>
                      </div>
                      <p class="mt-2 text-sm text-slate-500">Wohlbefinden</p>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-2">
                      @for (pill of metricPills(entry); track pill.label) {
                        <span class="rounded-full bg-slate-50 px-3 py-1.5 text-sm font-semibold text-slate-700">
                          {{ pill.label }} {{ pill.value }}
                        </span>
                      }
                    </div>

                    <div class="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                      @if (entry.notes) {
                        <span class="rounded-full bg-teal-50 px-3 py-1.5 text-sm font-semibold text-teal-700">Notiz vorhanden</span>
                      }
                      @if (isSelected(entry)) {
                        <span class="rounded-full bg-slate-900 px-3 py-1.5 text-sm font-semibold text-white">Ausgewählt</span>
                      }
                    </div>
                  } @else {
                    <div class="mt-6">
                      <p class="text-2xl font-bold text-slate-700">Kein Check-in</p>
                      <p class="mt-2 text-sm leading-relaxed text-slate-500">
                        {{ card.isToday ? 'Du kannst heute noch einchecken.' : 'Noch keine Daten für diesen Tag.' }}
                      </p>
                    </div>
                  }
                </button>
              }
            </div>
          </section>

          <aside class="rounded-[24px] border border-slate-100 bg-white p-6 lg:sticky lg:top-6" aria-label="Check-in Details">
            @if (selectedEntry(); as selected) {
              <div class="flex items-start justify-between gap-4">
                <div>
                  <p class="text-sm font-semibold uppercase tracking-wide text-teal-700">Ausgewählter Tag</p>
                  <h2 class="mt-1 text-xl font-bold text-slate-800">{{ formatLongDate(selected.createdAt) }}</h2>
                  <p class="mt-1 text-sm text-slate-500">{{ formatSubmittedTime(selected) }}</p>
                </div>
                <button type="button"
                        (click)="closeSelectedCheckIn()"
                        aria-label="Details schließen"
                        class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-full text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">
                  ×
                </button>
              </div>

              <div class="mt-6 rounded-[20px] bg-slate-50 p-5">
                <p class="text-sm font-semibold text-slate-500">Wohlbefinden</p>
                <div class="mt-2 flex items-baseline gap-2">
                  <span class="text-[38px] font-black leading-none" [ngClass]="scoreTextClass(selected.score)">
                    {{ formatScore(selected.score) }}
                  </span>
                  <span class="text-base font-semibold text-slate-500">/ 5</span>
                </div>
              </div>

              <div class="mt-5 space-y-3">
                @for (row of selectedDetailRows(); track row.label) {
                  <div class="rounded-2xl border border-slate-100 p-4">
                    <div class="flex items-start justify-between gap-4">
                      <span class="text-sm font-semibold text-slate-500">{{ row.label }}</span>
                      <span class="text-base font-bold text-slate-800">{{ row.value }}</span>
                    </div>
                    @if (row.hint) {
                      <p class="mt-1 text-sm text-slate-500">{{ row.hint }}</p>
                    }
                  </div>
                }
              </div>

              @if (selected.notes) {
                <div class="mt-5 rounded-2xl border border-teal-100 bg-teal-50 p-4">
                  <p class="text-sm font-semibold text-teal-700">Notiz</p>
                  <p class="mt-2 text-sm leading-relaxed text-slate-700">"{{ selected.notes }}"</p>
                </div>
              }

              @if (hasPartialDetails(selected)) {
                <p class="mt-5 rounded-2xl bg-amber-50 p-4 text-sm leading-relaxed text-amber-800">
                  Für diesen Check-in sind noch nicht alle Detailwerte verfügbar.
                </p>
              }
            } @else {
              <div class="rounded-[20px] bg-slate-50 p-5">
                <h2 class="text-lg font-bold text-slate-800">Details ansehen</h2>
                <p class="mt-2 text-sm leading-relaxed text-slate-500">
                  Klicke auf eine Check-in Karte, um die für diesen Tag eingetragenen Werte klar zu sehen.
                </p>
              </div>
            }
          </aside>
        </div>
      }
    </div>
  `
})
export class HistoryComponent implements OnInit {
  private employeeService = inject(EmployeeService);

  entries = signal<WellbeingEntry[]>([]);
  isLoading = signal(true);
  loadError = signal(false);
  selectedEntry = signal<WellbeingEntry | null>(null);

  readonly loadingCards = [1, 2, 3, 4, 5, 6];

  private readonly dayCount = 14;
  private readonly today = new Date();

  sortedEntries = computed(() =>
    [...this.entries()].sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime())
  );

  summaryCards = computed<SummaryCard[]>(() => {
    const entries = this.entries();
    const scores = entries.map(entry => entry.score).filter(score => Number.isFinite(score));
    const cards: SummaryCard[] = [];

    cards.push({
      label: 'Ø Wohlbefinden',
      value: scores.length ? `${this.formatScore(this.average(scores))} / 5` : 'Noch nicht genug Daten',
      subline: scores.length ? `berechnet aus ${scores.length} Check-ins` : 'Sobald Werte vorliegen, erscheint hier dein Schnitt.',
    });

    cards.push({
      label: 'Check-ins diese Woche',
      value: `${this.entriesThisWeek(entries)}`,
      subline: 'seit Montag erfasst',
    });

    const stressEntries = entries.filter(entry => entry.stress !== null);
    if (stressEntries.length) {
      cards.push({
        label: 'Stress-Tage',
        value: `${stressEntries.filter(entry => (entry.stress ?? 0) >= 4).length}`,
        subline: 'Tage mit Stresswert ab 4 / 5',
      });
    }

    // TODO: Add "Häufigstes Körpersignal" when the history API exposes body signals per day.
    return cards;
  });

  checkInCards = computed<DailyCheckInCard[]>(() => {
    const entriesByDay = new Map<string, WellbeingEntry>();
    for (const entry of this.sortedEntries()) {
      const key = this.dateKey(entry.createdAt);
      if (key && !entriesByDay.has(key)) {
        entriesByDay.set(key, entry);
      }
    }

    return Array.from({ length: this.dayCount }, (_, index) => {
      const date = this.addDays(this.startOfDay(this.today), -index);
      const dateKey = this.dateKey(date);

      return {
        date,
        dateKey,
        entry: entriesByDay.get(dateKey) ?? null,
        isToday: index === 0,
      };
    });
  });

  selectedDetailRows = computed<DetailRow[]>(() => {
    const entry = this.selectedEntry();
    if (!entry) return [];

    const rows: DetailRow[] = [];
    if (entry.mood !== null) {
      rows.push({ label: 'Stimmung', value: `${entry.mood} / 5`, hint: this.getMoodLabel(entry.mood) });
    }
    if (entry.energy !== null) {
      rows.push({ label: 'Energie', value: `${entry.energy} / 5`, hint: this.getEnergyLabel(entry.energy) });
    }
    if (entry.stress !== null) {
      rows.push({ label: 'Stress', value: `${entry.stress} / 5`, hint: this.getStressLabel(entry.stress) });
    }

    // TODO: Add Schlaf / Erholung and Körpersignale when those fields are available in history entries.
    return rows;
  });

  ngOnInit() {
    this.employeeService.getHistory().subscribe({
      next: entries => {
        this.entries.set(entries);
        this.loadError.set(false);
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

  selectCheckIn(entry: WellbeingEntry): void {
    this.selectedEntry.set(entry);
  }

  closeSelectedCheckIn(): void {
    this.selectedEntry.set(null);
  }

  isSelected(entry: WellbeingEntry): boolean {
    return this.selectedEntry()?.id === entry.id;
  }

  cardClass(card: DailyCheckInCard): string {
    if (!card.entry) {
      return 'border-dashed border-slate-200 bg-slate-50 text-slate-500';
    }

    if (this.isSelected(card.entry)) {
      return 'border-slate-900 bg-white shadow-sm ring-2 ring-slate-900';
    }

    return 'border-slate-100 bg-white hover:border-teal-200 hover:shadow-sm';
  }

  statusLabel(card: DailyCheckInCard): string {
    if (card.entry) return 'Erledigt';
    return card.isToday ? 'Heute offen' : 'Fehlt';
  }

  statusClass(card: DailyCheckInCard): string {
    if (card.entry) return 'bg-teal-50 text-teal-700';
    if (card.isToday) return 'bg-amber-50 text-amber-700';
    return 'bg-slate-100 text-slate-600';
  }

  scoreTextClass(score: number): string {
    if (score >= 4) return 'text-teal-600';
    if (score >= 3) return 'text-emerald-700';
    if (score >= 2.5) return 'text-amber-600';
    return 'text-red-600';
  }

  metricPills(entry: WellbeingEntry): Array<{ label: string; value: string }> {
    const pills: Array<{ label: string; value: string }> = [];
    if (entry.mood !== null) pills.push({ label: 'Stimmung', value: this.getMoodLabel(entry.mood) });
    if (entry.energy !== null) pills.push({ label: 'Energie', value: this.getEnergyLabel(entry.energy) });
    if (entry.stress !== null) pills.push({ label: 'Stress', value: this.getStressLabel(entry.stress) });
    return pills;
  }

  hasPartialDetails(entry: WellbeingEntry): boolean {
    return entry.mood === null || entry.energy === null || entry.stress === null;
  }

  formatScore(score: number): string {
    return score.toFixed(1).replace('.', ',');
  }

  formatWeekday(date: Date): string {
    return new Intl.DateTimeFormat('de-DE', { weekday: 'short' }).format(date);
  }

  formatDateLabel(date: Date): string {
    return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit' }).format(date);
  }

  formatLongDate(value: string | Date): string {
    return new Intl.DateTimeFormat('de-DE', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    }).format(new Date(value));
  }

  formatSubmittedTime(entry: WellbeingEntry): string {
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

  private entriesThisWeek(entries: WellbeingEntry[]): number {
    const weekStart = this.startOfWeek(this.today);
    const todayEnd = this.addDays(this.startOfDay(this.today), 1);

    return entries.filter(entry => {
      const date = new Date(entry.createdAt);
      return date >= weekStart && date < todayEnd;
    }).length;
  }

  private average(values: number[]): number {
    return values.reduce((sum, value) => sum + value, 0) / values.length;
  }

  private startOfWeek(date: Date): Date {
    const day = date.getDay();
    const diff = day === 0 ? -6 : 1 - day;
    return this.addDays(this.startOfDay(date), diff);
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
