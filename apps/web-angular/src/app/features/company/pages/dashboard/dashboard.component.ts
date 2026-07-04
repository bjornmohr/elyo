import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ApiClient } from '../../../../core/services/api-client.service';
import { AuthStore } from '../../../../core/store/auth.store';
import { DashboardExecutiveSummaryComponent } from './dashboard-executive-summary.component';

@Component({
  selector: 'app-company-dashboard',
  standalone: true,
  imports: [CommonModule, DashboardExecutiveSummaryComponent],
  template: `
    <div class="w-full p-6 space-y-8">
      <header>
        <h1 class="text-3xl font-bold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">Unternehmensübersicht</h1>
        <p class="text-gray-500 mt-2">Aggregierte Gesundheits- und Wohlbefindensdaten für dein Unternehmen.</p>
      </header>

      @if (loading()) {
        <div class="flex justify-center py-20">
          <div class="w-12 h-12 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin"></div>
        </div>
      }

      @if (error()) {
        <div class="bg-red-50 border border-red-100 text-red-600 p-4 rounded-2xl">
          {{ error() }}
        </div>
      }

      @if (!loading() && !error()) {
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 animate-fade-up">
          <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
            <h3 class="text-gray-400 text-sm font-semibold uppercase tracking-wider mb-2">Gesamtscore</h3>
            <p class="text-4xl font-bold text-teal-600">{{ scoreLabel() }}<span class="text-base font-semibold text-gray-300 ml-1">von 5</span></p>
            <p class="text-xs text-gray-400 mt-2">{{ responseCountLabel() }}</p>
          </div>

          <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
            <h3 class="text-gray-400 text-sm font-semibold uppercase tracking-wider mb-2">Teilnahme</h3>
            <p class="text-4xl font-bold text-gray-900">{{ participationLabel() }}</p>
            <p class="text-xs text-gray-400 mt-2">{{ participantLabel() }}</p>
          </div>

          @if (teamLayerEnabled()) {
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
              <h3 class="text-gray-400 text-sm font-semibold uppercase tracking-wider mb-2">Aktive Teams</h3>
              <p class="text-4xl font-bold text-gray-900">{{ data()?.teams?.length || '0' }}</p>
              <p class="text-xs text-gray-400 mt-2">Alle Teams erfüllen die Anonymitätsschwelle</p>
            </div>
          }
        </div>

        <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 animate-fade-up" style="animation-delay: 100ms">
          <h3 class="font-semibold text-gray-900 mb-6">Wohlbefindenstrend</h3>
          <div class="h-64 flex items-end justify-between gap-2">
            <!-- Placeholder for chart -->
            @for (i of trendBars(); track $index) {
              <div
                class="flex-1 bg-teal-500/20 rounded-t-lg hover:bg-teal-500/40 transition-colors relative group"
                [style.height.%]="i"
              >
                <div class="absolute -top-8 left-1/2 -translate-x-1/2 bg-gray-900 text-white text-[10px] py-1 px-2 rounded opacity-0 group-hover:opacity-100 transition-opacity">
                  {{ (i / 20).toFixed(1) }}
                </div>
              </div>
            }
          </div>
          <div class="flex justify-between mt-4 text-[10px] text-gray-400 uppercase tracking-widest">
            @for (point of data()?.trend; track point.period) {
              <span>{{ point.period }}</span>
            }
          </div>
        </div>

        @if (data()?.executiveSummary; as summary) {
          <app-dashboard-executive-summary [summary]="summary" />
        }
      }
    </div>
  `
})
export class CompanyDashboardComponent implements OnInit {
  private api = inject(ApiClient);
  private authStore = inject(AuthStore);

  data = signal<any>(null);
  loading = signal(true);
  error = signal<string | null>(null);

  ngOnInit() {
    this.api.get<any>('/company/dashboard').subscribe({
      next: (res: any) => {
        this.data.set(res);
        this.loading.set(false);
      },
      error: (err: any) => {
        this.error.set(err.error?.message || 'Failed to load dashboard data.');
        this.loading.set(false);
      }
    });
  }

  scoreLabel() {
    const company = this.data()?.company;
    return company?.isAboveThreshold ? (company.avgScore ?? 0).toFixed(1) : '—';
  }

  participationLabel() {
    const rate = this.data()?.company?.participationRate;
    return typeof rate === 'number' ? `${rate}%` : '—';
  }

  participantLabel() {
    const company = this.data()?.company;
    if (company?.isAboveThreshold === false) {
      return 'Aus Datenschutzgründen erst ab ausreichender Gruppengröße sichtbar';
    }

    const respondents = company?.respondentCount;
    const eligible = company?.eligibleEmployeeCount;
    return eligible > 0
      ? `${respondents} von ${eligible} aktiven Mitarbeitenden`
      : 'Keine aktiven Mitarbeitenden';
  }

  responseCountLabel() {
    const company = this.data()?.company;
    if (company?.isAboveThreshold === false || company?.responseCount === null) {
      return 'Durch Anonymitätsschwelle geschützt';
    }

    return `${company?.responseCount ?? 0} Check-ins in diesem Zeitraum`;
  }

  teamLayerEnabled() {
    return this.authStore.teamLayerEnabled();
  }

  trendBars() {
    const trend = this.data()?.trend ?? [];
    // avgScore is on the canonical 1-5 scale; scale to 0-100% bar heights.
    return trend.length ? trend.map((point: any) => Math.max(8, Math.min(100, Math.round((point.avgScore ?? 0) * 20)))) : [];
  }
}
