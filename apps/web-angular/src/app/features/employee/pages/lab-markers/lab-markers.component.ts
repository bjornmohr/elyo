import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { EmployeeService } from '../../services/employee.service';
import { LabMarker } from '../../models/lab-marker.model';
import { LAB_MARKER_EXPLANATIONS, LabMarkerExplanation } from '../../data/lab-marker-catalog';

type LabGroupKey = 'blutbild' | 'immun' | 'mikro';

const GROUPS: Array<{ key: LabGroupKey; title: string; accent: string }> = [
  { key: 'blutbild', title: 'Blutbild & Sauerstofftransport', accent: '#0d9488' },
  { key: 'immun', title: 'Immun- / Entzündungssignale', accent: '#ea580c' },
  { key: 'mikro', title: 'Mikronährstoffe & Speicher', accent: '#7c3aed' },
];

const FOCUS_ROUTINES = [
  {
    title: 'Tageslicht-Routine',
    tag: 'Tageslicht',
    text: 'Regelmäßig morgens rausgehen und Tageslicht bewusst einplanen.',
    tone: 'violet',
  },
  {
    title: 'Unverarbeitete Mahlzeit',
    tag: 'Ernährung',
    text: 'Eine möglichst unverarbeitete Mahlzeit als stabile Routine einbauen.',
    tone: 'blue',
  },
  {
    title: 'Eisenfreundliche Kombination',
    tag: 'Ernährung',
    text: 'Eisenquellen mit Vitamin-C-reichen Lebensmitteln kombinieren.',
    tone: 'teal',
  },
  {
    title: 'Werte im Blick behalten',
    tag: 'Kontrolle',
    text: 'Auffällige oder wiederholt abweichende Werte beim nächsten Termin besprechen.',
    tone: 'cyan',
  },
];

const MEASURE_CARDS = [
  {
    title: 'Vitamin-D-Routine',
    tag: 'Wissen',
    text: 'Tageslicht- und Ernährungsimpulse für einen stabilen Vitamin-D-Spiegel.',
    meta: '4 Wochen · Selbstlern',
    tone: 'blue',
  },
  {
    title: 'Eisenfreundliche Ernährung',
    tag: 'Gemischt',
    text: 'Eisenreiche Mahlzeiten clever mit Vitamin C kombinieren.',
    meta: '3 Wochen · Selbstlern',
    tone: 'teal',
  },
  {
    title: 'Regeneration & Erholung',
    tag: 'Atmung',
    text: 'Sanfte Routinen und Schonmodus, wenn Werte über dem Bereich liegen.',
    meta: 'Flexibel · Selbstlern',
    tone: 'orange',
  },
  {
    title: 'Schonende Bewegung',
    tag: 'Mobilität',
    text: 'Leichte Einheiten, die den Körper nicht zusätzlich fordern.',
    meta: 'Fortlaufend · Vor Ort',
    tone: 'teal',
  },
];

@Component({
  selector: 'app-employee-lab-markers',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="w-full max-w-[min(100%,76rem)] space-y-7">
      <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-[30px] font-bold leading-tight text-slate-800">Laborwerte & Marker</h1>
            <span class="rounded-full bg-teal-50 px-3 py-1.5 text-[13px] font-semibold text-teal-700">zur persönlichen Orientierung</span>
          </div>
          <p class="mt-2 max-w-3xl text-base leading-7 text-slate-500">
            Freiwillig hinterlegte Laborwerte, verständlich erklärt und mit Präventionsroutinen verknüpft.
          </p>
        </div>
      </header>

      <div class="flex items-start gap-3 rounded-2xl border border-teal-200 bg-teal-50 px-5 py-4">
        <span class="text-lg leading-tight">🔒</span>
        <div>
          <p class="text-sm font-semibold text-teal-900">Deine Laborwerte sind nur für dich sichtbar. Arbeitgeber sehen keine individuellen Gesundheitsdaten.</p>
          <p class="mt-1 text-[13px] leading-5 text-teal-800">Referenzbereiche können je Labor, Alter, Geschlecht und Kontext variieren. Diese Ansicht ersetzt keine ärztliche Diagnose oder Behandlung.</p>
        </div>
      </div>

      @if (loading()) {
        <div class="rounded-3xl border border-slate-100 bg-white p-10 text-center text-sm text-slate-500">
          Laborwerte werden geladen ...
        </div>
      } @else if (error()) {
        <div class="rounded-3xl border border-slate-100 bg-white p-10 text-center text-sm text-slate-500">
          Laborwerte können gerade nicht geladen werden.
        </div>
      } @else {
        <section class="space-y-4">
          @if (highlightedMarkers().length > 0) {
            <div class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,20rem),1fr))] gap-5">
              @for (marker of highlightedMarkers(); track marker.markerKey) {
                <article class="flex flex-col rounded-[20px] border border-slate-100 bg-white p-[22px]">
                  <div class="flex items-start justify-between gap-3">
                    <div>
                      <div class="flex items-center gap-1.5">
                        <h2 class="text-[17px] font-semibold text-slate-800">{{ marker.name }}</h2>
                        @if (markerExplanation(marker.markerKey); as explanation) {
                          <span class="relative inline-flex"
                                (mouseenter)="openMarkerExplanation(popoverKey(marker, 'highlight'))"
                                (mouseleave)="closeMarkerExplanation(popoverKey(marker, 'highlight'))">
                            <button type="button"
                                    class="-my-2 inline-flex min-h-11 min-w-11 items-center justify-center rounded-full text-slate-500 transition hover:text-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2"
                                    aria-label="Was beschreibt dieser Marker?"
                                    [attr.aria-expanded]="isExplanationOpen(popoverKey(marker, 'highlight'))"
                                    [attr.aria-controls]="popoverId(marker, 'highlight')"
                                    (focus)="openMarkerExplanation(popoverKey(marker, 'highlight'))"
                                    (click)="openMarkerExplanation(popoverKey(marker, 'highlight'))"
                                    (keydown.escape)="closeMarkerExplanation(popoverKey(marker, 'highlight'))">
                              <span class="flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-sm font-bold">i</span>
                            </button>
                            @if (isExplanationOpen(popoverKey(marker, 'highlight'))) {
                              <div [id]="popoverId(marker, 'highlight')" role="dialog" [attr.aria-label]="explanation.title" class="absolute left-0 top-full z-30 mt-2 w-[min(20rem,calc(100vw-3rem))] rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-lg">
                                <div class="flex items-start justify-between gap-3">
                                  <p class="text-sm font-semibold text-slate-800">{{ explanation.title }}</p>
                                  <button type="button"
                                          class="-mr-1 -mt-1 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold text-slate-500 hover:bg-slate-50 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2"
                                          aria-label="Erklärung schließen"
                                          (click)="closeMarkerExplanation(popoverKey(marker, 'highlight'))">x</button>
                                </div>
                                <p class="mt-2 text-sm leading-6 text-slate-600">{{ explanation.shortExplanation }}</p>
                                <p class="mt-2 text-sm leading-6 text-slate-500">{{ explanation.contextNote }}</p>
                                <p class="mt-3 rounded-xl bg-teal-50 px-3 py-2 text-sm leading-5 text-teal-800">{{ explanation.safetyNote }}</p>
                              </div>
                            }
                          </span>
                        }
                      </div>
                      <p class="mt-1 text-[13px] text-slate-500">{{ rangeLabel(marker) }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1.5 text-xs font-bold" [ngClass]="statusClass(marker.status)">{{ marker.status }}</span>
                  </div>

                  <div class="my-3 flex flex-col items-center">
                    <svg width="180" height="104" viewBox="0 0 140 80" class="overflow-visible" aria-hidden="true">
                      <path [attr.d]="arcPath(0, 1)" fill="none" stroke="#f1f5f9" stroke-width="12" stroke-linecap="round"></path>
                      <path [attr.d]="orientationArcPath(marker)" fill="none" stroke="#99f6e4" stroke-width="12" stroke-linecap="round"></path>
                      <circle [attr.cx]="gaugePoint(marker).x" [attr.cy]="gaugePoint(marker).y" r="7.5" [attr.fill]="statusDotColor(marker.status)" stroke="#ffffff" stroke-width="3.5"></circle>
                    </svg>
                    <div class="-mt-4 text-center">
                      <span class="text-[32px] font-bold text-slate-800">{{ formatValue(marker.value) }}</span>
                      <span class="ml-1 text-sm text-slate-500">{{ marker.unit }}</span>
                    </div>
                    <div class="mt-2 grid w-full max-w-[13rem] grid-cols-3 gap-2 text-center text-xs font-semibold text-slate-500">
                      <span>unter Bereich</span>
                      <span class="text-teal-700">Orientierung</span>
                      <span>über Bereich</span>
                    </div>
                  </div>

                  <p class="text-sm leading-6 text-slate-600">{{ interpretation(marker) }}</p>

                  <p class="mt-4 text-xs font-bold uppercase tracking-wide text-teal-700">Empfohlene Routinen</p>
                  <div class="mt-2 flex flex-col gap-2">
                    @for (routine of recommendedRoutines(marker); track routine) {
                      <div class="flex items-start gap-2.5">
                        <span class="mt-0.5 flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-full bg-teal-100 text-xs font-bold text-teal-700">✓</span>
                        <span class="text-sm leading-5 text-slate-700">{{ routine }}</span>
                      </div>
                    }
                  </div>

                  <div class="mt-4 flex items-start gap-2 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2.5">
                    <span class="text-sm leading-tight">ⓘ</span>
                    <span class="text-xs leading-5 text-slate-500">{{ hint(marker) }}</span>
                  </div>
                </article>
              }
            </div>
          } @else {
            <div class="flex flex-wrap items-center gap-5 rounded-[20px] border border-slate-100 bg-white p-7">
              <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-teal-50 text-2xl">✓</div>
              <div class="min-w-60 flex-1">
                <h2 class="text-lg font-semibold text-slate-800">Alles im Orientierungsbereich</h2>
                <p class="mt-1 text-sm leading-6 text-slate-500">Deine hinterlegten Werte liegen aktuell im angezeigten Orientierungsbereich. Schau dir unten die vollständige Übersicht an oder behalte deine Werte in Ruhe im Blick.</p>
              </div>
              <span class="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700">stabil</span>
            </div>
          }

          <div class="flex items-start gap-3 rounded-2xl border border-slate-100 bg-slate-50 px-5 py-4">
            <span class="text-base leading-tight">ⓘ</span>
            <div>
              <p class="text-sm font-semibold text-slate-700">Wichtig zur Einordnung</p>
              <p class="mt-1 text-sm leading-6 text-slate-500">Diese Ansicht erklärt freiwillig hinterlegte Werte zur persönlichen Orientierung. Referenzbereiche können variieren und die Einordnung hängt vom Gesamtbild ab. Sie ersetzt keine ärztliche Diagnose oder Behandlung.</p>
              <p class="mt-2 text-[13px] text-slate-500">🔒 Arbeitgeber sehen keine individuellen Laborwerte.</p>
            </div>
          </div>
        </section>

        <section class="space-y-4">
          <div>
            <h2 class="text-[22px] font-bold text-slate-800">Alle Werte</h2>
            <p class="mt-1 text-[15px] text-slate-500">Kompakte Übersicht mit Orientierungsbereichen. <span class="font-semibold text-teal-700">Orientierungsbereich</span> ist grün markiert, dein Wert als Punkt.</p>
          </div>

          <div class="space-y-5">
            @for (group of groupedMarkers(); track group.key) {
              <article class="rounded-[20px] border border-slate-100 bg-white px-6 py-[22px]">
                <div class="mb-5 flex items-center gap-2.5">
                  <span class="h-2.5 w-2.5 rounded-[3px]" [style.background]="group.accent"></span>
                  <h3 class="text-sm font-bold uppercase tracking-wide" [style.color]="group.accent">{{ group.title }}</h3>
                  <span class="text-xs text-slate-500">{{ group.markers.length }} Werte</span>
                </div>

                <div class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,20.5rem),1fr))] gap-x-7 gap-y-5">
                  @for (marker of group.markers; track marker.markerKey) {
                    <div class="space-y-2">
                      <div class="flex items-baseline justify-between gap-2">
                        <span class="flex items-center gap-1.5">
                          <span class="text-sm font-semibold text-slate-700">{{ marker.name }}</span>
                          @if (markerExplanation(marker.markerKey); as explanation) {
                            <span class="relative inline-flex"
                                  (mouseenter)="openMarkerExplanation(popoverKey(marker, 'overview'))"
                                  (mouseleave)="closeMarkerExplanation(popoverKey(marker, 'overview'))">
                              <button type="button"
                                      class="-my-2 inline-flex min-h-11 min-w-11 items-center justify-center rounded-full text-slate-500 transition hover:text-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2"
                                      aria-label="Was beschreibt dieser Marker?"
                                      [attr.aria-expanded]="isExplanationOpen(popoverKey(marker, 'overview'))"
                                      [attr.aria-controls]="popoverId(marker, 'overview')"
                                      (focus)="openMarkerExplanation(popoverKey(marker, 'overview'))"
                                      (click)="openMarkerExplanation(popoverKey(marker, 'overview'))"
                                      (keydown.escape)="closeMarkerExplanation(popoverKey(marker, 'overview'))">
                                <span class="flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-sm font-bold">i</span>
                              </button>
                              @if (isExplanationOpen(popoverKey(marker, 'overview'))) {
                                <div [id]="popoverId(marker, 'overview')" role="dialog" [attr.aria-label]="explanation.title" class="absolute left-0 top-full z-30 mt-2 w-[min(20rem,calc(100vw-3rem))] rounded-2xl border border-slate-200 bg-white p-4 text-left shadow-lg">
                                  <div class="flex items-start justify-between gap-3">
                                    <p class="text-sm font-semibold text-slate-800">{{ explanation.title }}</p>
                                    <button type="button"
                                            class="-mr-1 -mt-1 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold text-slate-500 hover:bg-slate-50 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2"
                                            aria-label="Erklärung schließen"
                                            (click)="closeMarkerExplanation(popoverKey(marker, 'overview'))">x</button>
                                  </div>
                                  <p class="mt-2 text-sm leading-6 text-slate-600">{{ explanation.shortExplanation }}</p>
                                  <p class="mt-2 text-sm leading-6 text-slate-500">{{ explanation.contextNote }}</p>
                                  <p class="mt-3 rounded-xl bg-teal-50 px-3 py-2 text-sm leading-5 text-teal-800">{{ explanation.safetyNote }}</p>
                                </div>
                              }
                            </span>
                          }
                        </span>
                        <span class="text-[13px] text-slate-500"><span class="font-bold text-slate-800">{{ formatValue(marker.value) }}</span> {{ marker.unit }}</span>
                      </div>
                      <div class="relative h-2 overflow-hidden rounded-full bg-slate-100">
                        <div class="absolute inset-y-0 bg-teal-100" [style.left.%]="rangeStartPercent(marker)" [style.width.%]="rangeWidthPercent(marker)"></div>
                      </div>
                      <div class="relative h-0">
                        <span class="absolute -top-[13px] h-3 w-3 rounded-full border-[2.5px] border-white shadow-[0_0_0_1px_#e2e8f0]"
                              [style.left.%]="valuePercent(marker)" [style.background]="statusDotColor(marker.status)" style="transform: translateX(-50%)"></span>
                      </div>
                      <div class="mt-1 flex items-center justify-between gap-2">
                        <span class="text-xs font-semibold" [ngClass]="statusTextClass(marker.status)">{{ marker.status }}</span>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-bold" [ngClass]="softTagClass(marker)">{{ softTag(marker) }}</span>
                      </div>
                    </div>
                  }
                </div>
              </article>
            }
          </div>
        </section>

        <section class="space-y-4">
          <div class="flex flex-wrap items-center gap-3">
            <h2 class="text-[22px] font-bold text-slate-800">Dein Fokus für diese Woche</h2>
            <span class="rounded-full bg-teal-50 px-3 py-1.5 text-[13px] font-semibold text-teal-700">präventive Routinen</span>
          </div>
          <p class="text-[15px] text-slate-500">{{ focusTitle() }}</p>
          <div class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,15.625rem),1fr))] gap-5">
            @for (routine of focusRoutines; track routine.title) {
              <article class="flex flex-col rounded-[20px] border border-slate-100 bg-white p-[22px]">
                <div class="mb-4 flex items-center justify-between gap-3">
                  <span class="flex h-12 w-12 items-center justify-center rounded-[14px]" [ngClass]="routineIconClass(routine.tone)">◆</span>
                  <span class="rounded-full px-3 py-1.5 text-[13px] font-bold" [ngClass]="routineBadgeClass(routine.tone)">{{ routine.tag }}</span>
                </div>
                <h3 class="text-base font-semibold text-slate-800">{{ routine.title }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-500">{{ routine.text }}</p>
              </article>
            }
          </div>
        </section>

        <section class="space-y-4">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-[22px] font-bold text-slate-800">Passende Maßnahmen</h2>
            <a routerLink="/employee/measures" class="inline-flex min-h-11 items-center text-sm font-semibold text-teal-600 hover:text-teal-700">Alle Maßnahmen →</a>
          </div>
          <p class="text-[15px] text-slate-500">Aus deinem ELYO-Maßnahmen-Hub, passend zu deinen Routinen.</p>
          <div class="grid grid-cols-[repeat(auto-fit,minmax(min(100%,21.25rem),1fr))] gap-5">
            @for (measure of measureCards; track measure.title) {
              <a routerLink="/employee/measures" class="flex flex-col rounded-[20px] border border-slate-100 bg-white p-6 transition-all hover:border-teal-200 hover:shadow-sm">
                <div class="mb-4 flex items-center justify-between gap-3">
                  <span class="flex h-12 w-12 items-center justify-center rounded-[14px]" [ngClass]="routineIconClass(measure.tone)">●</span>
                  <span class="rounded-full bg-teal-50 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-teal-700">{{ measure.tag }}</span>
                </div>
                <h3 class="text-[17px] font-semibold text-slate-800">{{ measure.title }}</h3>
                <p class="mt-2 text-sm leading-6 text-slate-500">{{ measure.text }}</p>
                <div class="mt-auto flex items-center justify-between gap-2 pt-4">
                  <span class="text-[13px] text-slate-500">{{ measure.meta }}</span>
                  <span class="text-sm font-semibold text-teal-600">Ansehen →</span>
                </div>
              </a>
            }
          </div>
        </section>
      }
    </div>
  `,
})
export class EmployeeLabMarkersComponent implements OnInit {
  private employeeService = inject(EmployeeService);

  markers = signal<LabMarker[]>([]);
  loading = signal(true);
  error = signal(false);
  activeExplanationKey = signal<string | null>(null);

  focusRoutines = FOCUS_ROUTINES;
  measureCards = MEASURE_CARDS;

  highlightedMarkers = computed(() => this.markers().filter(marker => marker.isHighlighted));

  groupedMarkers = computed(() => GROUPS.map(group => ({
    ...group,
    markers: this.markers().filter(marker => marker.group === group.key),
  })).filter(group => group.markers.length > 0));

  ngOnInit(): void {
    this.employeeService.getLabMarkers().subscribe({
      next: markers => {
        this.markers.set(markers);
        this.loading.set(false);
        this.error.set(false);
      },
      error: () => {
        this.loading.set(false);
        this.error.set(true);
      },
    });
  }

  focusTitle(): string {
    return this.highlightedMarkers().length > 0
      ? 'Kleine präventive Routinen für die kommende Woche.'
      : 'Weiter so. Halte deine Routinen und behalte deine Werte in Ruhe im Blick.';
  }

  markerExplanation(markerKey: string): LabMarkerExplanation | null {
    return LAB_MARKER_EXPLANATIONS[markerKey] ?? null;
  }

  popoverKey(marker: LabMarker, placement: 'highlight' | 'overview'): string {
    return `${placement}-${marker.markerKey}`;
  }

  popoverId(marker: LabMarker, placement: 'highlight' | 'overview'): string {
    return `lab-marker-explanation-${this.popoverKey(marker, placement)}`;
  }

  isExplanationOpen(key: string): boolean {
    return this.activeExplanationKey() === key;
  }

  openMarkerExplanation(key: string): void {
    this.activeExplanationKey.set(key);
  }

  closeMarkerExplanation(key: string): void {
    if (this.activeExplanationKey() === key) {
      this.activeExplanationKey.set(null);
    }
  }

  rangeLabel(marker: LabMarker): string {
    if (marker.low === null || marker.high === null) return 'Orientierungsbereich';
    if (marker.low === 0) return `Orientierungsbereich < ${this.formatValue(marker.high)} ${marker.unit}`;

    return `Orientierungsbereich ${this.formatValue(marker.low)}–${this.formatValue(marker.high)} ${marker.unit}`;
  }

  interpretation(marker: LabMarker): string {
    switch (marker.markerKey) {
      case 'vitd':
        return 'Dein hinterlegter Wert liegt unter dem angezeigten Orientierungsbereich. Mögliche Einflussfaktoren können Tageslicht, Ernährung und individuelle Faktoren sein.';
      case 'ferritin':
        return 'Der hinterlegte Ferritinwert liegt unter dem angezeigten Orientierungsbereich. Ferritin wird häufig im Zusammenhang mit Eisenspeichern betrachtet; die Einordnung hängt vom Gesamtbild ab.';
      case 'crp':
        return 'Der hinterlegte Wert liegt über dem angezeigten Orientierungsbereich. Entzündungssignale sind unspezifisch und immer im Gesamtbild und im Kontext von Beschwerden zu betrachten.';
      default:
        return 'Der hinterlegte Wert liegt außerhalb des angezeigten Orientierungsbereichs. Die Einordnung hängt vom Gesamtbild ab.';
    }
  }

  recommendedRoutines(marker: LabMarker): string[] {
    switch (marker.markerKey) {
      case 'vitd':
        return [
          'Tageslicht-Routine aufbauen, z. B. regelmäßig morgens rausgehen',
          'Vitamin-D-reiche Lebensmittel ergänzen',
          'Supplementierung nur nach ärztlicher oder fachlicher Rücksprache prüfen',
        ];
      case 'ferritin':
        return [
          'Eisenreiche Mahlzeiten einplanen',
          'Eisenquellen mit Vitamin C kombinieren',
          'Bei Beschwerden oder wiederholt auffälligen Werten ärztlich abklären',
        ];
      case 'crp':
        return [
          'Intensive Belastung heute bewusst reduzieren',
          'Schonmodus und sanfte Routinen nutzen',
          'Bei Beschwerden oder wiederholt auffälligen Werten ärztlich abklären',
        ];
      default:
        return [
          'Werte in Ruhe im Blick behalten',
          'Bei Beschwerden oder wiederholt auffälligen Werten ärztlich oder fachlich abklären',
        ];
    }
  }

  hint(marker: LabMarker): string {
    switch (marker.markerKey) {
      case 'vitd':
        return 'Referenzbereiche können je Labor und Kontext variieren.';
      case 'ferritin':
        return 'Referenzbereiche können je Labor, Alter, Geschlecht und Kontext variieren.';
      case 'crp':
        return 'Ein einzelner Wert ist nur eine Momentaufnahme.';
      default:
        return 'Referenzbereiche können je Labor, Alter, Geschlecht und Kontext variieren.';
    }
  }

  formatValue(value: number | null): string {
    if (value === null) return '–';
    return String(Math.round(value * 100) / 100).replace('.', ',');
  }

  softTag(marker: LabMarker): 'stabil' | 'beobachten' {
    return marker.status === 'im Orientierungsbereich' ? 'stabil' : 'beobachten';
  }

  statusClass(status: LabMarker['status']): string {
    switch (status) {
      case 'unter Bereich': return 'bg-amber-50 text-amber-700';
      case 'über Bereich': return 'bg-orange-50 text-orange-700';
      default: return 'bg-teal-50 text-teal-700';
    }
  }

  statusTextClass(status: LabMarker['status']): string {
    switch (status) {
      case 'unter Bereich': return 'text-amber-700';
      case 'über Bereich': return 'text-orange-700';
      default: return 'text-teal-700';
    }
  }

  softTagClass(marker: LabMarker): string {
    return this.softTag(marker) === 'stabil'
      ? 'bg-emerald-50 text-emerald-700'
      : 'bg-amber-50 text-amber-700';
  }

  statusDotColor(status: LabMarker['status']): string {
    switch (status) {
      case 'unter Bereich': return '#f59e0b';
      case 'über Bereich': return '#ea580c';
      default: return '#14b8a6';
    }
  }

  routineIconClass(tone: string): string {
    switch (tone) {
      case 'violet': return 'bg-violet-50 text-violet-700';
      case 'blue': return 'bg-indigo-50 text-indigo-700';
      case 'orange': return 'bg-orange-50 text-orange-700';
      case 'cyan': return 'bg-cyan-50 text-cyan-700';
      default: return 'bg-teal-50 text-teal-700';
    }
  }

  routineBadgeClass(tone: string): string {
    switch (tone) {
      case 'violet': return 'bg-violet-50 text-violet-700';
      case 'blue': return 'bg-indigo-50 text-indigo-700';
      case 'orange': return 'bg-orange-50 text-orange-700';
      case 'cyan': return 'bg-cyan-50 text-cyan-700';
      default: return 'bg-teal-50 text-teal-700';
    }
  }

  rangeStartPercent(marker: LabMarker): number {
    const bounds = this.displayBounds(marker);
    return this.percent(marker.low ?? bounds.min, bounds);
  }

  rangeWidthPercent(marker: LabMarker): number {
    const bounds = this.displayBounds(marker);
    return Math.max(0, this.percent(marker.high ?? bounds.max, bounds) - this.rangeStartPercent(marker));
  }

  valuePercent(marker: LabMarker): number {
    return this.percent(marker.value, this.displayBounds(marker));
  }

  orientationArcPath(marker: LabMarker): string {
    const bounds = this.displayBounds(marker);
    return this.arcPath(
      this.percent(marker.low ?? bounds.min, bounds) / 100,
      this.percent(marker.high ?? bounds.max, bounds) / 100,
    );
  }

  gaugePoint(marker: LabMarker): { x: string; y: string } {
    const point = this.polar(180 - (this.valuePercent(marker) / 100) * 180);
    return { x: point.x.toFixed(2), y: point.y.toFixed(2) };
  }

  arcPath(startFraction: number, endFraction: number): string {
    const start = this.polar(180 - startFraction * 180);
    const end = this.polar(180 - endFraction * 180);

    return `M ${start.x.toFixed(2)} ${start.y.toFixed(2)} A 54 54 0 0 1 ${end.x.toFixed(2)} ${end.y.toFixed(2)}`;
  }

  private displayBounds(marker: LabMarker): { min: number; max: number } {
    const low = marker.low ?? Math.max(0, marker.value * 0.75);
    const high = marker.high ?? marker.value * 1.25;
    const width = Math.max(high - low, 1);

    return {
      min: Math.max(0, low - width * 0.55),
      max: high + width * 0.55,
    };
  }

  private percent(value: number, bounds: { min: number; max: number }): number {
    return Math.max(0, Math.min(100, ((value - bounds.min) / (bounds.max - bounds.min)) * 100));
  }

  private polar(degrees: number): { x: number; y: number } {
    const radians = (degrees * Math.PI) / 180;
    return {
      x: 70 + 54 * Math.cos(radians),
      y: 70 - 54 * Math.sin(radians),
    };
  }
}
