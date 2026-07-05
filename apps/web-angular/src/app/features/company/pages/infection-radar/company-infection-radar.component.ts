import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CompanyInsightsService, InfectionRadar, InfectionRadarLevel, InfectionRadarRecommendationType, InfectionRadarTrend } from '../../services/company-insights.service';

@Component({
  selector: 'app-company-infection-radar',
  standalone: true,
  imports: [CommonModule],
  template: `
    <section class="space-y-6">
      <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div class="space-y-2">
          <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">Infektionsradar</h1>
            <span class="rounded-full px-3 py-1 text-xs font-bold uppercase" style="background: #fdf3e3; color: #9a6b1f; letter-spacing: .04em">Atemwegs-Frühwarnsystem</span>
          </div>
          <p class="max-w-3xl text-base leading-7" style="color: #5f6f67">
            Aggregierte Atemwegssignale aus Check-ins und externer Lage helfen, frühzeitig organisatorischen Handlungsbedarf zu erkennen.
          </p>
        </div>
      </div>

      @if (loading()) {
        <div class="rounded-2xl bg-white p-6 text-sm" style="border: 1px solid #ece6d8; color: #5f6f67">
          Infektionsradar wird geladen ...
        </div>
      } @else if (error()) {
        <div class="rounded-2xl bg-white p-6 text-sm" style="border: 1px solid #f0c8be; color: #9f3a31">
          Der Infektionsradar konnte gerade nicht geladen werden.
        </div>
      } @else if (!radar()) {
        <div class="rounded-2xl bg-white p-6 text-sm" style="border: 1px solid #ece6d8; color: #5f6f67">
          Infektionsradar ist für diesen Modus noch nicht verfügbar.
        </div>
      } @else {
        @let data = radar()!;
        @let warning = earlyWarning();
        @let internal = data.internalSignals;
        @let external = data.externalSignals;

        <div class="grid gap-5 xl:grid-cols-[1.4fr_0.9fr]">
          <div class="rounded-2xl bg-white p-6 shadow-sm" [style.border]="'1px solid ' + levelBorder(warning.level)">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
              <div class="space-y-3">
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold uppercase" [style.background]="levelTint(warning.level)" [style.color]="levelText(warning.level)">
                  Frühwarnlage {{ levelLabel(warning.level).toLowerCase() }}
                </span>
                <div>
                  <h2 class="text-3xl font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">{{ warning.score }} / 100</h2>
                  <p class="text-sm font-semibold" style="color: #5f6f67">organisatorischer Frühwarnindikator</p>
                </div>
                <p class="max-w-2xl text-base leading-7 text-gray-900">{{ warning.summary }}</p>
              </div>
              <div class="rounded-2xl p-5 lg:max-w-sm" style="background: #faf8f3; border: 1px solid #ece6d8">
                <p class="text-sm font-bold uppercase" style="color: #6f7d76; letter-spacing: .04em">Empfohlene Aktion</p>
                <p class="mt-2 text-base font-semibold leading-6 text-gray-900">{{ warning.recommendedAction }}</p>
              </div>
            </div>
          </div>

          <div class="rounded-2xl bg-white p-6 shadow-sm" style="border: 1px solid #ece6d8">
            <h2 class="text-lg font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">Datenschutz & Datenbasis</h2>
            <p class="mt-3 text-sm leading-6" style="color: #5f6f67">
              Dieses Radar nutzt ausschließlich aggregierte Signale. Einzelne Antworten sind für Arbeitgeber nicht sichtbar. Standortauswertungen werden nur angezeigt, wenn genügend Daten für eine sichere Aggregation vorliegen.
            </p>
            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
              <div class="rounded-xl p-3" style="background: #faf8f3">
                <p class="font-semibold text-gray-900">{{ formatPercent(internal?.checkInCoverage) }}</p>
                <p style="color: #5f6f67">Check-in-Abdeckung</p>
              </div>
              <div class="rounded-xl p-3" style="background: #faf8f3">
                <p class="font-semibold text-gray-900">{{ external?.source ?? 'Demo-Daten' }}</p>
                <p style="color: #5f6f67">{{ external?.dataUpdatedAt ? ('Stand ' + formatDate(external?.dataUpdatedAt)) : 'später RKI/ARE-Anbindung' }}</p>
              </div>
            </div>
          </div>
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
          <div class="rounded-2xl bg-white p-6 shadow-sm" style="border: 1px solid #ece6d8">
            <div class="flex items-center justify-between gap-3">
              <h2 class="text-lg font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">Interne Signale</h2>
              <span class="rounded-full px-3 py-1 text-xs font-bold uppercase" style="background: #ecfaf7; color: #0f766e; letter-spacing: .04em">Check-ins</span>
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
              <div class="rounded-xl p-4" style="background: #faf8f3">
                <p class="text-2xl font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">{{ formatPercent(internal?.symptomRate7d) }}</p>
                <p class="text-sm" style="color: #5f6f67">Check-ins mit Erkältungssignalen</p>
              </div>
              <div class="rounded-xl p-4" style="background: #faf8f3">
                <p class="text-2xl font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">{{ formatDeltaPp(internal?.symptomRateDelta) }}</p>
                <p class="text-sm" style="color: #5f6f67">Veränderung zur Vorwoche</p>
              </div>
              <div class="rounded-xl p-4" style="background: #faf8f3">
                <p class="text-2xl font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">{{ internal?.safeModeActivations7d ?? 'Noch nicht genug Daten' }}</p>
                <p class="text-sm" style="color: #5f6f67">Schonmodus-Aktivierungen</p>
              </div>
              <div class="rounded-xl p-4" style="background: #faf8f3">
                <p class="text-2xl font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">{{ formatPercent(internal?.checkInCoverage) }}</p>
                <p class="text-sm" style="color: #5f6f67">Check-in-Abdeckung</p>
              </div>
            </div>

            <div class="mt-5">
              <p class="text-sm font-semibold text-gray-900">Häufigste Symptome</p>
              <div class="mt-3 flex flex-wrap gap-2">
                @for (symptom of internal?.topSymptoms ?? []; track symptom.label) {
                  <span class="rounded-full px-3 py-1.5 text-sm font-semibold" style="background: #ecfaf7; color: #0f766e">
                    {{ symptom.label }} · {{ formatPercent(symptom.share) }}
                  </span>
                } @empty {
                  <span class="text-sm" style="color: #5f6f67">Noch nicht genug Daten</span>
                }
              </div>
            </div>
          </div>

          <div class="rounded-2xl bg-white p-6 shadow-sm" style="border: 1px solid #ece6d8">
            <div class="flex items-center justify-between gap-3">
              <h2 class="text-lg font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">Externe Atemwegslage</h2>
              <span class="rounded-full px-3 py-1 text-xs font-bold uppercase" [style.background]="levelTint(external?.areLevel ?? data.overallStatus)" [style.color]="levelText(external?.areLevel ?? data.overallStatus)" style="letter-spacing: .04em">
                {{ levelLabel(external?.areLevel ?? data.overallStatus) }}
              </span>
            </div>
            <div class="mt-5 space-y-4">
              <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl p-4" style="background: #faf8f3">
                  <p class="text-2xl font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">{{ levelLabel(external?.areLevel ?? data.overallStatus) }}</p>
                  <p class="text-sm" style="color: #5f6f67">Regionale Atemwegslage</p>
                </div>
                <div class="rounded-xl p-4" style="background: #faf8f3">
                  <p class="text-2xl font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">{{ trendLabel(external?.areTrend) }}</p>
                  <p class="text-sm" style="color: #5f6f67">Trend zur Vorwoche</p>
                </div>
              </div>
              <div>
                <p class="text-sm font-semibold text-gray-900">Dominante externe Signale</p>
                <p class="mt-2 text-sm" style="color: #5f6f67">{{ external?.dominantSignals?.join(', ') || 'Noch nicht genug Daten' }}</p>
              </div>
              <div class="rounded-xl p-4 text-sm" style="background: #faf8f3; color: #5f6f67">
                <span class="font-semibold text-gray-900">Unterstützender Wert:</span>
                {{ data.rkiIncidence.district }} · {{ data.rkiIncidence.value }} ({{ deltaLabel(data.rkiIncidence.deltaPercent) }} %)
              </div>
            </div>
          </div>
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-sm" style="border: 1px solid #ece6d8">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <h2 class="text-lg font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">Aggregierte Erkältungssignale</h2>
              <p class="text-sm" style="color: #5f6f67">Meldungen über Check-ins in den letzten 7 Tagen.</p>
            </div>
            <p class="text-sm font-semibold" style="color: #5f6f67">Trend: {{ trendLabel(external?.areTrend) }}</p>
          </div>
          <div class="mt-6 flex h-56 items-end gap-3 overflow-x-auto rounded-2xl p-4" style="background: #faf8f3">
            @for (point of data.symptomReports7d; track point.date) {
              <div class="flex min-w-16 flex-1 flex-col items-center gap-2">
                <div class="flex h-40 w-full items-end">
                  <div class="w-full rounded-t-xl" style="background: #d9a441" [style.height.%]="barHeight(point.count)"></div>
                </div>
                <span class="text-sm font-semibold text-gray-900">{{ point.count }}</span>
                <span class="text-xs" style="color: #5f6f67">{{ dayLabel(point.date) }}</span>
              </div>
            }
          </div>
          <p class="mt-3 text-sm" style="color: #5f6f67">Die Werte sind aggregierte Demo-Signale und keine medizinische Diagnose.</p>
        </div>

        <div class="grid gap-5 xl:grid-cols-[1fr_1fr]">
          <div class="rounded-2xl bg-white p-6 shadow-sm" style="border: 1px solid #ece6d8">
            <h2 class="text-lg font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">Standorte</h2>
            <div class="mt-5 grid gap-4 md:grid-cols-2">
              @for (location of data.locations; track location.name) {
                <div class="rounded-2xl p-5" [style.border]="'1px solid ' + levelBorder(locationLevel(location))" [style.background]="isReportable(location) ? '#ffffff' : '#faf8f3'">
                  <div class="flex items-start justify-between gap-3">
                    <div>
                      <h3 class="text-base font-semibold text-gray-900">{{ location.name }}</h3>
                      <p class="mt-1 text-sm" style="color: #5f6f67">{{ isReportable(location) ? 'Datenbasis ausreichend' : 'Datenschutzschwelle nicht erreicht' }}</p>
                    </div>
                    <span class="rounded-full px-2.5 py-1 text-xs font-bold uppercase" [style.background]="levelTint(locationLevel(location))" [style.color]="levelText(locationLevel(location))">
                      {{ levelLabel(locationLevel(location)) }}
                    </span>
                  </div>
                  @if (isReportable(location)) {
                    <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                      <div class="rounded-xl p-3" style="background: #faf8f3">
                        <p class="font-semibold text-gray-900">{{ formatPercent(location.symptomRate) }}</p>
                        <p style="color: #5f6f67">Erkältungssignale</p>
                      </div>
                      <div class="rounded-xl p-3" style="background: #faf8f3">
                        <p class="font-semibold text-gray-900">{{ trendLabel(location.trend) }}</p>
                        <p style="color: #5f6f67">Trend</p>
                      </div>
                    </div>
                  } @else {
                    <p class="mt-4 text-sm leading-6" style="color: #5f6f67">Keine Standortauswertung verfügbar, damit kleine Gruppen nicht erkennbar werden.</p>
                  }
                </div>
              }
            </div>
          </div>

          <div class="rounded-2xl bg-white p-6 shadow-sm" style="border: 1px solid #ece6d8">
            <h2 class="text-lg font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">Empfohlene Aktionen</h2>
            <div class="mt-5 space-y-4">
              @for (recommendation of data.recommendations; track recommendation.title) {
                <article class="rounded-2xl p-5" style="border: 1px solid #ece6d8; background: #faf8f3">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full px-2.5 py-1 text-xs font-bold uppercase" style="background: #ecfaf7; color: #0f766e; letter-spacing: .04em">
                      {{ recommendationTypeLabel(recommendation.type) }}
                    </span>
                    @if (recommendation.target) {
                      <span class="text-sm font-semibold" style="color: #5f6f67">{{ recommendation.target }}</span>
                    }
                  </div>
                  <h3 class="mt-3 text-base font-semibold text-gray-900">{{ recommendation.title }}</h3>
                  <p class="mt-2 text-sm leading-6" style="color: #5f6f67">{{ recommendation.text }}</p>
                  @if (recommendation.actionLabel) {
                    <p class="mt-4 text-sm font-semibold" style="color: #0f766e">{{ recommendation.actionLabel }}</p>
                  }
                </article>
              }
            </div>
          </div>
        </div>
      }
    </section>
  `,
})
export class CompanyInfectionRadarComponent implements OnInit {
  private readonly insights = inject(CompanyInsightsService);

  radar = signal<InfectionRadar | null>(null);
  loading = signal(true);
  error = signal(false);

  maxSymptomCount = computed(() => Math.max(...(this.radar()?.symptomReports7d.map((point) => point.count) ?? [1]), 1));

  earlyWarning = computed<{ level: InfectionRadarLevel; score: number; summary: string; recommendedAction: string }>(() => {
    const radar = this.radar();
    if (radar?.earlyWarning) {
      return radar.earlyWarning;
    }

    return {
      level: radar?.overallStatus ?? 'NORMAL',
      score: radar?.overallStatus === 'CRITICAL' ? 84 : radar?.overallStatus === 'ELEVATED' ? 68 : 24,
      summary: 'Aggregierte Atemwegssignale werden beobachtet. Für diese Demo sind noch nicht alle Signalquellen verfügbar.',
      recommendedAction: 'Radar weiter beobachten und verfügbare Maßnahmen prüfen.',
    };
  });

  ngOnInit(): void {
    this.insights.getInfectionRadar().subscribe({
      next: (response) => {
        this.radar.set(response.data);
        this.loading.set(false);
      },
      error: () => {
        this.error.set(true);
        this.loading.set(false);
      },
    });
  }

  barHeight(count: number) {
    return Math.max(8, Math.round((count / this.maxSymptomCount()) * 100));
  }

  dayLabel(date: string) {
    return new Intl.DateTimeFormat('de-DE', { weekday: 'short' }).format(new Date(date));
  }

  formatDate(date: string | undefined) {
    if (!date) return 'Demo';
    return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(date));
  }

  formatPercent(value: number | null | undefined) {
    if (value === null || value === undefined) return 'Noch nicht genug Daten';
    return `${Math.round(value * 100)} %`;
  }

  formatDeltaPp(value: number | null | undefined) {
    if (value === null || value === undefined) return 'Noch nicht genug Daten';
    const points = Math.round(value * 100);
    return `${points > 0 ? '+' : ''}${points} pp`;
  }

  deltaLabel(value: number) {
    return value > 0 ? `+${value}` : `${value}`;
  }

  locationLevel(location: InfectionRadar['locations'][number]): InfectionRadarLevel {
    return location.level ?? location.status ?? 'NORMAL';
  }

  isReportable(location: InfectionRadar['locations'][number]) {
    return location.reportable !== false;
  }

  levelLabel(level: InfectionRadarLevel) {
    switch (level) {
      case 'CRITICAL': return 'Kritisch';
      case 'ELEVATED': return 'Erhöht';
      case 'WATCH': return 'Beobachten';
      default: return 'Normal';
    }
  }

  trendLabel(trend: InfectionRadarTrend | null | undefined) {
    switch (trend) {
      case 'UP': return 'steigend';
      case 'DOWN': return 'sinkend';
      case 'STABLE': return 'stabil';
      default: return 'Noch nicht genug Daten';
    }
  }

  recommendationTypeLabel(type: InfectionRadarRecommendationType | undefined) {
    switch (type) {
      case 'COMMUNICATION': return 'Kommunikation';
      case 'MEASURE': return 'Maßnahme';
      case 'WORK_ORGANIZATION': return 'Arbeitsorganisation';
      default: return 'Empfehlung';
    }
  }

  levelTint(level: InfectionRadarLevel) {
    switch (level) {
      case 'CRITICAL': return '#fde7e2';
      case 'ELEVATED': return '#fdf3e3';
      case 'WATCH': return '#fff8d7';
      default: return '#ecfaf7';
    }
  }

  levelBorder(level: InfectionRadarLevel) {
    switch (level) {
      case 'CRITICAL': return '#f0c8be';
      case 'ELEVATED': return '#f3e2bc';
      case 'WATCH': return '#efe3a2';
      default: return '#c5ebe3';
    }
  }

  levelText(level: InfectionRadarLevel) {
    switch (level) {
      case 'CRITICAL': return '#9f3a31';
      case 'ELEVATED': return '#9a6b1f';
      case 'WATCH': return '#8a6a0f';
      default: return '#0f766e';
    }
  }
}
