import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CompanyInsightsService, RiskField, RiskLandscape } from '../../services/company-insights.service';

@Component({
  selector: 'app-company-risk-landscape',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="space-y-6">
      <div class="flex items-center gap-3">
        <h1 class="text-2xl font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">Risikolandschaft</h1>
        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase" style="background: #fdf3e3; color: #9a6b1f; letter-spacing: .04em">Konzept</span>
      </div>

      @if (loading()) {
        <div class="flex justify-center py-12">
          <div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin"></div>
        </div>
      } @else if (fields().length === 0) {
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
          <p class="text-gray-500 text-sm">Noch keine Risikodaten vorhanden.</p>
        </div>
      } @else {
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
          <div class="xl:col-span-2 bg-white overflow-hidden" style="border: 1px solid #ece6d8; border-radius: 14px">
            <table class="w-full text-sm">
              <thead>
                <tr style="background: #faf8f3; border-bottom: 1px solid #f1ede3">
                  <th class="text-left px-4 py-3 text-[11px] uppercase font-semibold" style="color: #6f7d76; letter-spacing: .04em">Feld</th>
                  <th class="text-left px-4 py-3 text-[11px] uppercase font-semibold" style="color: #6f7d76; letter-spacing: .04em">Relevanz</th>
                  <th class="text-left px-4 py-3 text-[11px] uppercase font-semibold" style="color: #6f7d76; letter-spacing: .04em">Score</th>
                  <th class="text-left px-4 py-3 text-[11px] uppercase font-semibold" style="color: #6f7d76; letter-spacing: .04em">30-Tage-Trend</th>
                  <th class="text-left px-4 py-3 text-[11px] uppercase font-semibold" style="color: #6f7d76; letter-spacing: .04em">Verknüpfte Maßnahmen</th>
                </tr>
              </thead>
              <tbody>
                @for (row of fields(); track row.field) {
                  <tr style="border-bottom: 1px solid #f1ede3">
                    <td class="px-4 py-3 font-medium text-gray-900">{{ row.fieldLabel }}</td>
                    <td class="px-4 py-3">
                      <div class="flex gap-0.5">
                        @for (segment of segments; track segment) {
                          <div class="h-2 w-4 rounded-sm"
                               [style.background]="segment <= row.relevanceLevel ? segmentColor(row.score) : '#f1ede3'"></div>
                        }
                      </div>
                    </td>
                    <td class="px-4 py-3 font-semibold" style="font-family: 'Fraunces', Georgia, serif">{{ row.score }}<span class="text-xs font-normal" style="color: #9aa39c">/100</span></td>
                    <td class="px-4 py-3 text-xs font-semibold" [style.color]="row.trend30d > 0 ? '#c14a3f' : '#0f766e'">{{ trendLabel(row.trend30d) }}</td>
                    <td class="px-4 py-3">
                      @if (row.linkedMeasures.activeCount === 0 && row.linkedMeasures.suggestedCount === 0) {
                        <span class="text-[11px] font-semibold rounded-full" style="padding: 3px 9px; background: #f3d9d5; color: #c14a3f">0 aktiv — Lücke</span>
                      } @else {
                        <span class="text-xs" style="color: #6f7d76">
                          {{ row.linkedMeasures.activeCount }} aktiv{{ row.linkedMeasures.suggestedCount > 0 ? ' · ' + row.linkedMeasures.suggestedCount + ' vorgeschlagen' : '' }}
                        </span>
                      }
                    </td>
                  </tr>
                }
              </tbody>
            </table>
          </div>

          <div class="space-y-5">
            <div class="bg-white p-5 space-y-2" style="border: 1px solid #ece6d8; border-radius: 14px">
              <h2 class="text-sm font-semibold text-gray-900">Top-Handlungsfelder</h2>
              @for (row of topFields(); track row.field; let i = $index) {
                <div class="flex items-center gap-3 text-sm">
                  <span class="w-5 h-5 rounded-full flex items-center justify-center text-[11px] font-bold text-white" style="background: #0f766e">{{ i + 1 }}</span>
                  <span class="text-gray-900">{{ row.fieldLabel }}</span>
                  <span class="ml-auto text-xs font-semibold" style="color: #6f7d76">{{ row.score }}/100</span>
                </div>
              }
            </div>

            @if (focusField(); as focus) {
              <div class="bg-white p-5 space-y-3" style="border: 1px solid #ece6d8; border-radius: 14px">
                <h2 class="text-sm font-semibold text-gray-900">Feld im Fokus: {{ focus.fieldLabel }}</h2>
                <div class="flex items-end gap-1.5 h-16">
                  @for (month of focus.monthlyScores; track month.period) {
                    <div class="flex-1 rounded-t" style="background: #14b8a6" [style.height.%]="month.score"></div>
                  }
                </div>
                <div class="flex justify-between text-[10px]" style="color: #9aa39c">
                  <span>{{ focus.monthlyScores[0].period }}</span>
                  <span>{{ focus.monthlyScores[focus.monthlyScores.length - 1].period }}</span>
                </div>
                @if (focus.monthsAboveHighSince !== null) {
                  <span class="inline-block text-[11px] font-semibold rounded-full" style="padding: 3px 9px; background: #fdf3e3; color: #9a6b1f">
                    {{ focus.monthsAboveHighSince }} Monate in Folge hoch
                  </span>
                }
              </div>
            }

            <div class="bg-white p-5 space-y-3" style="border: 1px solid #ece6d8; border-radius: 14px">
              <h2 class="text-sm font-semibold text-gray-900">Empfehlungen</h2>
              @for (recommendation of recommendations(); track recommendation.title) {
                <div>
                  <div class="text-sm font-medium text-gray-900">{{ recommendation.title }}</div>
                  <p class="text-xs mt-0.5" style="color: #6f7d76">{{ recommendation.text }}</p>
                </div>
              }
            </div>

            <p class="text-[11px] italic leading-relaxed" style="color: #9aa39c">
              Alle Werte sind anonymisierte Aggregate oberhalb der Mindestgruppengröße. Keine Einzelpersonen-Daten.
            </p>
          </div>
        </div>
      }
    </div>
  `,
})
export class CompanyRiskLandscapeComponent implements OnInit {
  private insightsService = inject(CompanyInsightsService);

  segments = [1, 2, 3, 4, 5];
  loading = signal(true);
  fields = signal<RiskField[]>([]);
  recommendations = signal<{ title: string; text: string }[]>([]);

  topFields = computed(() => [...this.fields()].sort((a, b) => b.score - a.score).slice(0, 3));
  focusField = computed(() => this.topFields()[0] ?? null);

  ngOnInit() {
    this.insightsService.getRiskLandscape().subscribe({
      next: res => {
        const data = res.data as RiskLandscape | [] | null;
        if (data && !Array.isArray(data)) {
          this.fields.set(data.fields ?? []);
          this.recommendations.set(data.recommendations ?? []);
        }
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  segmentColor(score: number) {
    if (score >= 60) return '#c14a3f';
    if (score >= 40) return '#d9a441';
    return '#14b8a6';
  }

  trendLabel(trend: number) {
    if (trend > 0) return `+${trend} %`;
    if (trend < 0) return `${trend} %`;
    return '± 0 %';
  }
}
