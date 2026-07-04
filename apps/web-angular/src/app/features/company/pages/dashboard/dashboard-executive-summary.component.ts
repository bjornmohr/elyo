import { Component, input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';

export interface ExecutiveSummary {
  period: string;
  kpis: {
    healthIndex: { value: number; deltaPercent: number };
    riskTrend30d: { value: number; label: string };
    activeUserRate: { value: number; deltaPercent: number };
    measureImpactScore: { value: number; max: number; label: string };
    topFields: { rank: number; field: string; fieldLabel: string }[];
  };
  riskCompact: { field: string; fieldLabel: string; relevanceLevel: number; trend30d: number }[];
  funnelCompact: { rates: number[]; avgDaysToFirstDecision: number; returnRate14d: number };
  infectionWidget: { overallStatus: string; highlightLocation: string; rkiIncidence: number; rkiDeltaPercent: number };
  impactReporting: { program: string; usageRate: number; relevanceScore: number; rating: number }[];
  recommendations: string[];
}

@Component({
  selector: 'app-dashboard-executive-summary',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <section class="space-y-6">
      <div class="flex items-center gap-3">
        <h2 class="text-xl font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">Executive Summary</h2>
        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase" style="background: #fdf3e3; color: #9a6b1f; letter-spacing: .04em">Konzept</span>
        <span class="text-xs" style="color: #9aa39c">{{ summary().period }}</span>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
        <div class="bg-white p-4" style="border: 1px solid #ece6d8; border-radius: 14px">
          <div class="text-[11px] uppercase font-semibold" style="color: #6f7d76; letter-spacing: .04em">Gesundheitsindex</div>
          <div class="text-[26px] font-semibold" style="font-family: 'Fraunces', Georgia, serif">{{ summary().kpis.healthIndex.value }}<span class="text-sm font-normal" style="color: #9aa39c">/100</span></div>
          <div class="text-xs font-semibold" style="color: #0f766e">{{ deltaLabel(summary().kpis.healthIndex.deltaPercent) }} ggü. Vormonat</div>
        </div>
        <div class="bg-white p-4" style="border: 1px solid #ece6d8; border-radius: 14px">
          <div class="text-[11px] uppercase font-semibold" style="color: #6f7d76; letter-spacing: .04em">Risikotrend (30 Tage)</div>
          <div class="text-[26px] font-semibold" style="font-family: 'Fraunces', Georgia, serif" [style.color]="summary().kpis.riskTrend30d.value > 0 ? '#c14a3f' : '#0f766e'">
            {{ deltaLabel(summary().kpis.riskTrend30d.value) }} %
          </div>
          <div class="text-xs" style="color: #6f7d76">{{ summary().kpis.riskTrend30d.label }}</div>
        </div>
        <div class="bg-white p-4" style="border: 1px solid #ece6d8; border-radius: 14px">
          <div class="text-[11px] uppercase font-semibold" style="color: #6f7d76; letter-spacing: .04em">Aktive Nutzerquote</div>
          <div class="text-[26px] font-semibold" style="font-family: 'Fraunces', Georgia, serif">{{ summary().kpis.activeUserRate.value }} %</div>
          <div class="h-1.5 rounded-full mt-1 overflow-hidden" style="background: #f1ede3">
            <div class="h-full rounded-full" style="background: #14b8a6" [style.width.%]="summary().kpis.activeUserRate.value"></div>
          </div>
          <div class="text-xs font-semibold mt-1" style="color: #0f766e">{{ deltaLabel(summary().kpis.activeUserRate.deltaPercent) }} Pp.</div>
        </div>
        <div class="bg-white p-4" style="border: 1px solid #ece6d8; border-radius: 14px">
          <div class="text-[11px] uppercase font-semibold" style="color: #6f7d76; letter-spacing: .04em">Maßnahmenwirkung</div>
          <div class="text-[26px] font-semibold" style="font-family: 'Fraunces', Georgia, serif">{{ summary().kpis.measureImpactScore.value }}<span class="text-sm font-normal" style="color: #9aa39c">/{{ summary().kpis.measureImpactScore.max }}</span></div>
          <div class="text-xs" style="color: #6f7d76">{{ summary().kpis.measureImpactScore.label }}</div>
        </div>
        <div class="bg-white p-4" style="border: 1px solid #ece6d8; border-radius: 14px">
          <div class="text-[11px] uppercase font-semibold" style="color: #6f7d76; letter-spacing: .04em">Top-Handlungsfelder</div>
          <div class="space-y-1 mt-1">
            @for (field of summary().kpis.topFields; track field.rank) {
              <div class="flex items-center gap-2 text-xs">
                <span class="w-4 h-4 rounded-full flex items-center justify-center text-[10px] font-bold text-white" style="background: #0f766e">{{ field.rank }}</span>
                <span class="text-gray-900">{{ field.fieldLabel }}</span>
              </div>
            }
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        <a routerLink="/company/risk-landscape" class="bg-white p-5 space-y-2 hover:shadow-sm transition-shadow" style="border: 1px solid #ece6d8; border-radius: 14px">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900">Risikolandschaft</h3>
            <span class="text-xs" style="color: #0f766e">Details →</span>
          </div>
          @for (row of summary().riskCompact; track row.field) {
            <div class="flex items-center gap-2 text-xs py-0.5">
              <span class="w-36 truncate" style="color: #6f7d76">{{ row.fieldLabel }}</span>
              <div class="flex gap-0.5">
                @for (segment of [1, 2, 3, 4, 5]; track segment) {
                  <div class="h-1.5 w-3 rounded-sm" [style.background]="segment <= row.relevanceLevel ? '#d9a441' : '#f1ede3'"></div>
                }
              </div>
              <span class="ml-auto font-semibold" [style.color]="row.trend30d > 0 ? '#c14a3f' : '#6f7d76'">{{ deltaLabel(row.trend30d) }} %</span>
            </div>
          }
        </a>

        <a routerLink="/company/usage-funnel" class="bg-white p-5 space-y-2 hover:shadow-sm transition-shadow" style="border: 1px solid #ece6d8; border-radius: 14px">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900">Entscheidungs- & Nutzungsverhalten</h3>
            <span class="text-xs" style="color: #0f766e">Details →</span>
          </div>
          <p class="text-[11px]" style="color: #9aa39c; margin-top: -4px">Vom Impuls zur Umsetzung</p>
          <div class="flex flex-col items-center pt-1" style="gap: 3px">
            @for (rate of summary().funnelCompact.rates; track $index; let first = $first; let last = $last) {
              <div class="text-white text-center text-xs font-semibold"
                   [style.width.%]="pyramidWidth($index, summary().funnelCompact.rates.length)"
                   [style.background]="stageColor($index)"
                   [style.border-radius]="first ? '6px 6px 0 0' : last ? '0 0 6px 6px' : '0'"
                   style="padding: 9px">
                {{ funnelStageLabel($index) }}&nbsp; {{ rate }}%
              </div>
            }
          </div>
          <div class="grid grid-cols-2 gap-2.5 pt-2">
            <div class="text-center rounded-[10px] p-3" style="background: #faf8f3">
              <div class="text-[10px] uppercase" style="color: #9aa39c; letter-spacing: .04em">Ø Zeit bis 1. Entscheidung</div>
              <div class="text-xl font-semibold mt-1" style="font-family: 'Fraunces', Georgia, serif">{{ summary().funnelCompact.avgDaysToFirstDecision }} Tage</div>
            </div>
            <div class="text-center rounded-[10px] p-3" style="background: #faf8f3">
              <div class="text-[10px] uppercase" style="color: #9aa39c; letter-spacing: .04em">Wiederkehrrate (14 Tage)</div>
              <div class="text-xl font-semibold mt-1" style="font-family: 'Fraunces', Georgia, serif">{{ summary().funnelCompact.returnRate14d }}%</div>
            </div>
          </div>
        </a>

        <a routerLink="/company/infection-radar" class="p-5 space-y-2 hover:shadow-sm transition-shadow"
           [style.background]="summary().infectionWidget.overallStatus === 'NORMAL' ? '#ecfaf7' : '#fdf3e3'"
           [style.border]="'1px solid ' + (summary().infectionWidget.overallStatus === 'NORMAL' ? '#c5ebe3' : '#f3e2bc')"
           style="border-radius: 14px">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold" [style.color]="widgetText()">Infektionsradar</h3>
            <span class="text-xs" [style.color]="widgetText()">Details →</span>
          </div>
          <div class="text-sm font-semibold" [style.color]="widgetText()">{{ overallStatusLabel() }}</div>
          <div class="text-xs" [style.color]="widgetText()">Auffällig: {{ summary().infectionWidget.highlightLocation }}</div>
          <div class="text-xs" [style.color]="widgetText()">
            RKI-Inzidenz {{ summary().infectionWidget.rkiIncidence }} ({{ deltaLabel(summary().infectionWidget.rkiDeltaPercent) }} %)
          </div>
        </a>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        <div class="xl:col-span-2 bg-white overflow-hidden" style="border: 1px solid #ece6d8; border-radius: 14px">
          <div class="px-5 pt-4 pb-2 text-sm font-semibold text-gray-900">Wirkungsreporting</div>
          <table class="w-full text-sm">
            <thead>
              <tr style="background: #faf8f3; border-bottom: 1px solid #f1ede3">
                <th class="text-left px-5 py-2 text-[11px] uppercase font-semibold" style="color: #6f7d76; letter-spacing: .04em">Programm</th>
                <th class="text-left px-5 py-2 text-[11px] uppercase font-semibold" style="color: #6f7d76; letter-spacing: .04em">Nutzung</th>
                <th class="text-left px-5 py-2 text-[11px] uppercase font-semibold" style="color: #6f7d76; letter-spacing: .04em">Relevanz</th>
                <th class="text-left px-5 py-2 text-[11px] uppercase font-semibold" style="color: #6f7d76; letter-spacing: .04em">Wirkung</th>
              </tr>
            </thead>
            <tbody>
              @for (row of summary().impactReporting; track row.program) {
                <tr style="border-bottom: 1px solid #f1ede3">
                  <td class="px-5 py-2.5 text-gray-900">{{ row.program }}</td>
                  <td class="px-5 py-2.5 text-xs" style="color: #6f7d76">{{ row.usageRate }} %</td>
                  <td class="px-5 py-2.5 text-xs" style="color: #6f7d76">{{ row.relevanceScore }}/100</td>
                  <td class="px-5 py-2.5" style="color: #d9a441">{{ stars(row.rating) }}</td>
                </tr>
              }
            </tbody>
          </table>
        </div>

        <div class="bg-white p-5 space-y-3" style="border: 1px solid #ece6d8; border-radius: 14px">
          <h3 class="text-sm font-semibold text-gray-900">Handlungsempfehlungen</h3>
          @for (recommendation of summary().recommendations; track recommendation) {
            <div class="flex gap-2 text-xs" style="color: #6f7d76">
              <span style="color: #0f766e">●</span>
              <span>{{ recommendation }}</span>
            </div>
          }
        </div>
      </div>
    </section>
  `,
})
export class DashboardExecutiveSummaryComponent {
  private static readonly STAGE_LABELS = ['Registriert', 'Kontext eingegeben', 'Empfehlung erhalten', 'Maßnahme gestartet', 'Nach 14 Tagen aktiv'];
  private static readonly STAGE_COLORS = ['#0f766e', '#2c8272', '#5c8f6c', '#a08a4e', '#8a6a3a'];

  summary = input.required<ExecutiveSummary>();

  funnelStageLabel(index: number) {
    return DashboardExecutiveSummaryComponent.STAGE_LABELS[index] ?? '';
  }

  stageColor(index: number) {
    return DashboardExecutiveSummaryComponent.STAGE_COLORS[index] ?? '#0f766e';
  }

  // Evenly stepped widths (100% -> 36%) as in the mockup; the labels carry
  // the real rates, the width is purely the pyramid silhouette.
  pyramidWidth(index: number, total: number) {
    if (total <= 1) return 100;
    return Math.round(100 - index * (64 / (total - 1)));
  }

  deltaLabel(value: number) {
    return value > 0 ? `+${value}` : `${value}`;
  }

  stars(rating: number) {
    const rounded = Math.round(rating);
    return '★'.repeat(Math.max(0, Math.min(5, rounded))) + '☆'.repeat(Math.max(0, 5 - rounded));
  }

  overallStatusLabel() {
    switch (this.summary().infectionWidget.overallStatus) {
      case 'CRITICAL': return 'Kritisch';
      case 'ELEVATED': return 'Erhöhtes Aufkommen';
      default: return 'Normal';
    }
  }

  widgetText() {
    return this.summary().infectionWidget.overallStatus === 'NORMAL' ? '#0f766e' : '#9a6b1f';
  }
}
