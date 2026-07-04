import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CompanyMeasuresService, MeasureFieldStatistics } from '../../services/company-measures.service';

@Component({
  selector: 'app-company-measure-statistics',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="space-y-6">
      <div class="flex items-center gap-3">
        <h1 class="text-2xl font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">Maßnahmen · Statistiken</h1>
        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase" style="background: #fdf3e3; color: #9a6b1f; letter-spacing: .04em">teilweise Konzept</span>
      </div>

      @if (loading()) {
        <div class="flex justify-center py-12">
          <div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin"></div>
        </div>
      } @else if (fields().length === 0) {
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
          <p class="text-gray-500 text-sm">Noch keine Statistiken vorhanden.</p>
        </div>
      } @else {
        <div class="bg-white overflow-hidden" style="border: 1px solid #ece6d8; border-radius: 14px">
          <table class="w-full text-sm">
            <thead>
              <tr style="background: #faf8f3; border-bottom: 1px solid #f1ede3">
                <th class="text-left px-4 py-3 text-[11px] uppercase font-semibold" style="color: #6f7d76; letter-spacing: .04em">Risikofeld</th>
                <th class="text-left px-4 py-3 text-[11px] uppercase font-semibold" style="color: #6f7d76; letter-spacing: .04em">Maßnahmen</th>
                <th class="text-left px-4 py-3 text-[11px] uppercase font-semibold" style="color: #6f7d76; letter-spacing: .04em">Ø Teilnahmequote</th>
                <th class="text-left px-4 py-3 text-[11px] uppercase font-semibold" style="color: #6f7d76; letter-spacing: .04em">
                  Ø Wirkung
                  <span class="ml-1 text-[9px] font-bold px-1.5 py-0.5 rounded-full normal-case" style="background: #fdf3e3; color: #9a6b1f">KONZEPT</span>
                </th>
                <th class="text-left px-4 py-3 text-[11px] uppercase font-semibold" style="color: #6f7d76; letter-spacing: .04em">Trend im Feld</th>
              </tr>
            </thead>
            <tbody>
              @for (row of fields(); track row.field) {
                <tr style="border-bottom: 1px solid #f1ede3" [style.background]="row.measureCount === 0 ? '#fdf3e3' : 'transparent'">
                  <td class="px-4 py-3 font-medium text-gray-900">{{ row.fieldLabel }}</td>
                  <td class="px-4 py-3 font-semibold" [style.color]="row.measureCount === 0 ? '#c14a3f' : '#13201b'">
                    {{ row.measureCount }}{{ row.measureCount === 0 ? ' — Lücke' : '' }}
                  </td>
                  <td class="px-4 py-3">
                    @if (row.avgParticipationRate !== null) {
                      <span class="font-semibold" style="color: #0f766e">{{ row.avgParticipationRate }} %</span>
                    } @else if (!row.isAboveThreshold) {
                      <span class="text-xs italic" style="color: #6f7d76">geschützt</span>
                    } @else {
                      <span class="text-xs" style="color: #9aa39c">—</span>
                    }
                  </td>
                  <td class="px-4 py-3">
                    @if (row.avgImpactRating !== null) {
                      <span style="color: #d9a441">{{ stars(row.avgImpactRating) }}</span>
                      @if (row.impactIsPreliminary) {
                        <span class="text-[11px] italic ml-1" style="color: #9aa39c">vorläufig</span>
                      }
                    } @else {
                      <span class="text-xs" style="color: #9aa39c">Noch keine Daten</span>
                    }
                  </td>
                  <td class="px-4 py-3">
                    @if (row.fieldTrend30d !== null) {
                      <span class="text-xs font-semibold" [style.color]="row.fieldTrend30d > 0 ? '#c14a3f' : '#0f766e'">
                        {{ trendLabel(row.fieldTrend30d) }}
                      </span>
                    } @else {
                      <span class="text-xs" style="color: #9aa39c">—</span>
                    }
                  </td>
                </tr>
              }
            </tbody>
          </table>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
          <div class="bg-white p-5 space-y-3" style="border: 1px solid #ece6d8; border-radius: 14px">
            <h2 class="text-sm font-semibold text-gray-900">Teilnahmequote nach Feld</h2>
            @for (row of fieldsWithRate(); track row.field) {
              <div class="space-y-1">
                <div class="flex justify-between text-xs">
                  <span style="color: #6f7d76">{{ row.fieldLabel }}</span>
                  <span class="font-semibold" style="color: #0f766e">{{ row.avgParticipationRate }} %</span>
                </div>
                <div class="h-2 rounded-full overflow-hidden" style="background: #f1ede3">
                  <div class="h-full rounded-full" style="background: #14b8a6" [style.width.%]="row.avgParticipationRate"></div>
                </div>
              </div>
            } @empty {
              <p class="text-xs italic" style="color: #9aa39c">Noch keine Teilnahmedaten vorhanden.</p>
            }
          </div>

          <div class="p-5 space-y-2" style="background: #fdf3e3; border: 1px solid #f3e2bc; border-radius: 14px">
            <h2 class="text-sm font-semibold" style="color: #9a6b1f">Größte Angebotslücke</h2>
            @if (gapFields().length > 0) {
              <p class="text-sm" style="color: #9a6b1f">
                {{ gapFieldLabels() }} — {{ gapFields().length === 1 ? 'dieses Feld hat' : 'diese Felder haben' }} aktuell keine Maßnahme.
              </p>
              <p class="text-xs" style="color: #9a6b1f">Empfehlung: Angebot für die Lücken-Felder prüfen und passende Maßnahmen ergänzen.</p>
            } @else {
              <p class="text-sm" style="color: #9a6b1f">Alle Risikofelder sind mit mindestens einer Maßnahme abgedeckt.</p>
            }
          </div>
        </div>
      }
    </div>
  `,
})
export class CompanyMeasureStatisticsComponent implements OnInit {
  private measuresService = inject(CompanyMeasuresService);

  loading = signal(true);
  fields = signal<MeasureFieldStatistics[]>([]);

  fieldsWithRate = computed(() => this.fields().filter(row => row.avgParticipationRate !== null));
  gapFields = computed(() => this.fields().filter(row => row.measureCount === 0));

  ngOnInit() {
    this.measuresService.getStatistics().subscribe({
      next: res => {
        this.fields.set(res.data ?? []);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  gapFieldLabels() {
    return this.gapFields().map(row => row.fieldLabel).join(' & ');
  }

  stars(rating: number) {
    const rounded = Math.round(rating);
    return '★'.repeat(Math.max(0, Math.min(5, rounded))) + '☆'.repeat(Math.max(0, 5 - rounded));
  }

  trendLabel(trend: number) {
    if (trend > 0) return `+${trend} %`;
    if (trend < 0) return `${trend} %`;
    return '± 0 %';
  }
}
