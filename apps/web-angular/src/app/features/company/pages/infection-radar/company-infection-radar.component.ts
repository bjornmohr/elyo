import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CompanyInsightsService, InfectionRadar } from '../../services/company-insights.service';

@Component({
  selector: 'app-company-infection-radar',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="space-y-6">
      <div class="flex items-center gap-3">
        <h1 class="text-2xl font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">Infektionsradar</h1>
        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase" style="background: #fdf3e3; color: #9a6b1f; letter-spacing: .04em">Konzept</span>
      </div>

      @if (loading()) {
        <div class="flex justify-center py-12">
          <div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin"></div>
        </div>
      } @else if (!radar()) {
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
          <p class="text-gray-500 text-sm">Noch keine Infektionsdaten vorhanden.</p>
        </div>
      } @else if (radar(); as data) {
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
          <div class="xl:col-span-2 space-y-5">
            <div class="p-5 flex items-center gap-4" [style.background]="statusTint(data.overallStatus)"
                 [style.border]="'1px solid ' + statusBorder(data.overallStatus)" style="border-radius: 14px">
              <div class="w-3 h-3 rounded-full" [style.background]="statusColor(data.overallStatus)"></div>
              <div>
                <div class="text-sm font-semibold" [style.color]="statusText(data.overallStatus)">{{ statusLabel(data.overallStatus) }}</div>
                <div class="text-xs" [style.color]="statusText(data.overallStatus)">Gesamtunternehmen · basierend auf anonymen Symptommeldungen</div>
              </div>
            </div>

            <div class="bg-white p-5 space-y-2" style="border: 1px solid #ece6d8; border-radius: 14px">
              <h2 class="text-sm font-semibold text-gray-900">Standorte</h2>
              @for (location of data.locations; track location.name) {
                <div class="flex items-center justify-between py-1.5" style="border-bottom: 1px solid #f1ede3">
                  <span class="text-sm text-gray-900">{{ location.name }}</span>
                  <span class="text-[11px] font-semibold rounded-full flex items-center gap-1.5" style="padding: 3px 9px"
                        [style.background]="statusTint(location.status)" [style.color]="statusText(location.status)">
                    <span class="w-1.5 h-1.5 rounded-full" [style.background]="statusColor(location.status)"></span>
                    {{ statusLabel(location.status) }}
                  </span>
                </div>
              }
            </div>

            <div class="bg-white p-5 space-y-3" style="border: 1px solid #ece6d8; border-radius: 14px">
              <h2 class="text-sm font-semibold text-gray-900">Symptommeldungen · letzte 7 Tage</h2>
              <div class="flex items-end gap-2 h-24">
                @for (report of data.symptomReports7d; track report.date) {
                  <div class="flex-1 flex flex-col items-center gap-1">
                    <div class="w-full rounded-t" style="background: #d9a441" [style.height.%]="barHeight(report.count)"></div>
                    <span class="text-[9px]" style="color: #9aa39c">{{ dayLabel(report.date) }}</span>
                  </div>
                }
              </div>
              <p class="text-[11px] italic" style="color: #9aa39c">Nur Aggregate — keine Einzelfalldaten.</p>
            </div>
          </div>

          <div class="space-y-5">
            <div class="bg-white p-5" style="border: 1px solid #ece6d8; border-radius: 14px">
              <div class="text-[11px] uppercase font-semibold" style="color: #6f7d76; letter-spacing: .04em">RKI 7-Tage-Inzidenz</div>
              <div class="text-[30px] font-semibold mt-1" style="font-family: 'Fraunces', Georgia, serif">{{ data.rkiIncidence.value }}</div>
              <div class="text-xs font-semibold" [style.color]="data.rkiIncidence.deltaPercent > 0 ? '#c14a3f' : '#0f766e'">
                {{ data.rkiIncidence.deltaPercent > 0 ? '+' : '' }}{{ data.rkiIncidence.deltaPercent }} % ggü. Vorwoche
              </div>
              <p class="text-[11px] mt-2" style="color: #9aa39c">{{ data.rkiIncidence.district }} · externe Datenquelle (RKI, Landkreisebene)</p>
            </div>

            @for (recommendation of data.recommendations; track recommendation.title) {
              <div class="bg-white p-4 space-y-1" style="border: 1px solid #ece6d8; border-radius: 14px">
                <div class="text-sm font-semibold text-gray-900">{{ recommendation.title }}</div>
                <p class="text-xs" style="color: #6f7d76">{{ recommendation.text }}</p>
              </div>
            }

            <p class="text-[11px] italic leading-relaxed" style="color: #9aa39c">
              Datenschutz: Symptommeldungen sind freiwillig und werden ausschließlich aggregiert ausgewertet.
              Reine Aggregatwerte — keine Einzelfalldaten.
            </p>
          </div>
        </div>
      }
    </div>
  `,
})
export class CompanyInfectionRadarComponent implements OnInit {
  private insightsService = inject(CompanyInsightsService);

  loading = signal(true);
  radar = signal<InfectionRadar | null>(null);

  maxSymptomCount = computed(() => {
    const reports = this.radar()?.symptomReports7d ?? [];
    return Math.max(1, ...reports.map(report => report.count));
  });

  ngOnInit() {
    this.insightsService.getInfectionRadar().subscribe({
      next: res => {
        this.radar.set(res.data ?? null);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  barHeight(count: number) {
    return Math.round((count / this.maxSymptomCount()) * 100);
  }

  dayLabel(date: string) {
    return new Date(date).toLocaleDateString('de-DE', { weekday: 'short' });
  }

  statusLabel(status: string) {
    switch (status) {
      case 'NORMAL': return 'Normal';
      case 'ELEVATED': return 'Erhöhtes Aufkommen';
      case 'CRITICAL': return 'Kritisch';
      default: return status;
    }
  }

  statusColor(status: string) {
    switch (status) {
      case 'CRITICAL': return '#c14a3f';
      case 'ELEVATED': return '#d9a441';
      default: return '#14b8a6';
    }
  }

  statusTint(status: string) {
    switch (status) {
      case 'CRITICAL': return '#f3d9d5';
      case 'ELEVATED': return '#fdf3e3';
      default: return '#ecfaf7';
    }
  }

  statusBorder(status: string) {
    switch (status) {
      case 'CRITICAL': return '#e5b8b2';
      case 'ELEVATED': return '#f3e2bc';
      default: return '#c5ebe3';
    }
  }

  statusText(status: string) {
    switch (status) {
      case 'CRITICAL': return '#c14a3f';
      case 'ELEVATED': return '#9a6b1f';
      default: return '#0f766e';
    }
  }
}
