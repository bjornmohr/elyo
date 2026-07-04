import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CompanyInsightsService, UsageFunnel, UsageFunnelCategory } from '../../services/company-insights.service';

const STAGE_COLORS = ['#0f766e', '#2c8272', '#5c8f6c', '#a08a4e', '#8a6a3a'];

@Component({
  selector: 'app-company-usage-funnel',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="space-y-6">
      <div class="flex items-center gap-3">
        <h1 class="text-2xl font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">Nutzungsverhalten</h1>
        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase" style="background: #fdf3e3; color: #9a6b1f; letter-spacing: .04em">Konzept</span>
      </div>

      @if (loading()) {
        <div class="flex justify-center py-12">
          <div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin"></div>
        </div>
      } @else if (!funnel()) {
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
          <p class="text-gray-500 text-sm">Noch keine Nutzungsdaten vorhanden.</p>
        </div>
      } @else if (funnel(); as data) {
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
          <div class="xl:col-span-2 space-y-5">
            <div class="bg-white p-8 space-y-5" style="border: 1px solid #ece6d8; border-radius: 14px">
              <div class="flex items-center justify-between flex-wrap gap-2">
                <div class="flex gap-1.5">
                  <button type="button" (click)="selectedCategory.set(null)"
                          class="text-[11px] font-semibold rounded-full"
                          [style.background]="selectedCategory() === null ? '#13201b' : '#f1ede3'"
                          [style.color]="selectedCategory() === null ? '#ffffff' : '#6f7d76'"
                          style="padding: 6px 12px">Alle</button>
                  @if (data.categories?.length) {
                    @for (category of data.categories; track category.key) {
                      <button type="button" (click)="selectedCategory.set(category.key)"
                              class="text-[11px] font-semibold rounded-full"
                              [style.background]="selectedCategory() === category.key ? '#13201b' : '#f1ede3'"
                              [style.color]="selectedCategory() === category.key ? '#ffffff' : '#6f7d76'"
                              style="padding: 6px 12px">{{ category.label }}</button>
                    }
                  } @else {
                    @for (category of disabledCategoryTabs; track category) {
                      <span class="text-[11px] font-semibold rounded-full cursor-not-allowed" style="background: #f1ede3; color: #b7bdb4; padding: 6px 12px">{{ category }}</span>
                    }
                  }
                </div>
                <span class="text-[10px] italic" style="color: #9aa39c">
                  {{ selectedCategoryData() ? 'Basis: Empfehlungen der Kategorie' : data.categories?.length ? 'Kategorie wählen für Aufschlüsselung' : 'Aufschlüsselung nach Kategorie geplant' }}
                </span>
              </div>
              <div class="flex flex-col items-center" style="gap: 4px">
                @for (stage of data.stages; track stage.key; let i = $index; let first = $first; let last = $last) {
                  @if (selectedCategoryData(); as category) {
                    @if (isCategoryStage(stage.key)) {
                      <div class="text-center relative overflow-hidden"
                           [style.width.%]="pyramidWidth(i, data.stages.length)"
                           [style.border-radius]="last ? '0 0 8px 8px' : '0'"
                           style="background: #ecfaf7; border: 1px solid #d5eee7; padding: 12px 14px 18px">
                        <div class="text-sm font-semibold" style="color: #0f766e">{{ stage.label }}</div>
                        <div class="text-[11px] mt-0.5" style="color: #0f766e">
                          {{ category.label }}: {{ categoryCountFor(stage.key, category) }} · {{ categoryRateLabel(stage.key, category) }}
                        </div>
                        <div class="absolute bottom-0 left-0 right-0 flex justify-center">
                          <div class="h-1.5 rounded-t"
                               [style.width.%]="categoryShareFor(stage, category)"
                               [style.background]="stageColor(i)"></div>
                        </div>
                      </div>
                    } @else {
                      <div class="text-center"
                           [style.width.%]="pyramidWidth(i, data.stages.length)"
                           [style.border-radius]="first ? '8px 8px 0 0' : '0'"
                           style="background: #faf8f3; border: 1px dashed #e5ded3; padding: 12px 14px"
                           [style.padding]="first ? '15px' : '12px 14px'">
                        <div class="text-sm font-semibold" style="color: #9aa39c">{{ stage.label }}</div>
                        <div class="text-[11px] mt-0.5" style="color: #9aa39c">{{ stage.count }} · unternehmensweit</div>
                      </div>
                    }
                  } @else {
                    <div class="text-white text-center"
                         [style.width.%]="pyramidWidth(i, data.stages.length)"
                         [style.background]="stageColor(i)"
                         [style.border-radius]="first ? '8px 8px 0 0' : last ? '0 0 8px 8px' : '0'"
                         [style.padding]="first ? '16px' : '14px'">
                      <div class="text-sm font-semibold">{{ stage.label }}</div>
                      <div class="text-[11px] mt-0.5" style="opacity: .85">{{ stage.count }}{{ first ? ' Mitarbeitende' : '' }} · {{ stage.rate }}%</div>
                    </div>
                  }
                }
              </div>
              @if (selectedCategoryData(); as category) {
                <div class="flex justify-center gap-4 text-[10px]" style="color: #9aa39c">
                  <span><span class="inline-block w-2.5 h-2.5 rounded-sm align-middle mr-1" style="background: #0f766e"></span>{{ category.label }}-Anteil</span>
                  <span><span class="inline-block w-2.5 h-2.5 rounded-sm align-middle mr-1" style="background: #ecfaf7; border: 1px solid #d5eee7"></span>Alle Kategorien</span>
                  <span><span class="inline-block w-2.5 h-2.5 rounded-sm align-middle mr-1" style="border: 1px dashed #b7bdb4"></span>kategorielos</span>
                </div>
              } @else if (data.categoryInsight) {
                <p class="text-[11px] text-center" style="color: #9aa39c">{{ data.categoryInsight }}</p>
              }
            </div>

            @if (data.previousCohort; as previous) {
              <div class="bg-white p-5 space-y-3" style="border: 1px solid #ece6d8; border-radius: 14px">
                <h2 class="text-sm font-semibold text-gray-900">Kohortenvergleich {{ previous.cohort }} vs. {{ data.cohort }}</h2>
                <div class="space-y-2">
                  @for (stage of comparisonStages(); track stage.key) {
                    <div class="flex items-center gap-3">
                      <div class="w-40 text-xs text-right" style="color: #6f7d76">{{ stage.label }}</div>
                      <div class="flex-1 space-y-1">
                        <div class="h-3 rounded" style="background: #cfe9e2" [style.width.%]="stage.previousRate"></div>
                        <div class="h-3 rounded" style="background: #0f766e" [style.width.%]="stage.rate"></div>
                      </div>
                      <div class="w-20 text-[11px]" style="color: #6f7d76">{{ stage.previousRate }} % → {{ stage.rate }} %</div>
                    </div>
                  }
                </div>
                <div class="flex gap-4 text-[10px]" style="color: #9aa39c">
                  <span><span class="inline-block w-3 h-2 rounded-sm align-middle mr-1" style="background: #cfe9e2"></span>{{ previous.cohort }}</span>
                  <span><span class="inline-block w-3 h-2 rounded-sm align-middle mr-1" style="background: #0f766e"></span>{{ data.cohort }}</span>
                </div>
              </div>
            }
          </div>

          <div class="space-y-5">
            <div class="grid grid-cols-2 gap-4">
              <div class="bg-white p-4" style="border: 1px solid #ece6d8; border-radius: 14px">
                <div class="text-[11px] uppercase font-semibold" style="color: #6f7d76; letter-spacing: .04em">Ø Zeit bis 1. Entscheidung</div>
                <div class="text-[22px] font-semibold mt-1" style="font-family: 'Fraunces', Georgia, serif">{{ data.avgDaysToFirstDecision }} Tage</div>
              </div>
              <div class="bg-white p-4" style="border: 1px solid #ece6d8; border-radius: 14px">
                <div class="text-[11px] uppercase font-semibold" style="color: #6f7d76; letter-spacing: .04em">Wiederkehrrate (14 Tage)</div>
                <div class="text-[22px] font-semibold mt-1" style="font-family: 'Fraunces', Georgia, serif">{{ data.returnRate14d }} %</div>
              </div>
            </div>

            @if (biggestDrop(); as drop) {
              <div class="p-4 space-y-1" style="background: #fdf3e3; border: 1px solid #f3e2bc; border-radius: 14px">
                <div class="text-sm font-semibold" style="color: #9a6b1f">Größte Abbruchstelle</div>
                <p class="text-xs" style="color: #9a6b1f">{{ drop.fromLabel }} → {{ drop.toLabel }}: −{{ drop.delta }} Prozentpunkte</p>
              </div>
            }

            <div class="bg-white p-5 space-y-2" style="border: 1px solid #ece6d8; border-radius: 14px">
              <h2 class="text-sm font-semibold text-gray-900">Verweildauer je Übergang</h2>
              @for (transition of data.transitions; track transition.from + transition.to) {
                <div class="flex items-center justify-between text-xs rounded-lg px-2 py-1.5"
                     [style.background]="isCriticalTransition(transition) ? '#fdf3e3' : 'transparent'">
                  <span style="color: #6f7d76">{{ stageLabel(transition.from) }} → {{ stageLabel(transition.to) }}</span>
                  <span class="font-semibold" [style.color]="isCriticalTransition(transition) ? '#9a6b1f' : '#13201b'">
                    {{ transitionDays(transition) }}
                  </span>
                </div>
              }
            </div>

          </div>
        </div>
      }
    </div>
  `,
})
export class CompanyUsageFunnelComponent implements OnInit {
  private insightsService = inject(CompanyInsightsService);

  loading = signal(true);
  funnel = signal<UsageFunnel | null>(null);

  readonly disabledCategoryTabs = ['Workshop', 'Sport', 'Mental', 'Ernährung'];

  selectedCategory = signal<string | null>(null);

  selectedCategoryData = computed(() => {
    const key = this.selectedCategory();
    if (!key) return null;
    return this.funnel()?.categories?.find(category => category.key === key) ?? null;
  });

  biggestDrop = computed(() => {
    const data = this.funnel();
    if (!data || data.stages.length < 2) return null;

    let drop: { fromLabel: string; toLabel: string; toKey: string; delta: number } | null = null;
    for (let i = 1; i < data.stages.length; i++) {
      const delta = data.stages[i - 1].rate - data.stages[i].rate;
      if (!drop || delta > drop.delta) {
        drop = {
          fromLabel: data.stages[i - 1].label,
          toLabel: data.stages[i].label,
          toKey: data.stages[i].key,
          delta,
        };
      }
    }
    return drop;
  });

  comparisonStages = computed(() => {
    const data = this.funnel();
    if (!data?.previousCohort) return [];

    return data.previousCohort.stages
      .map(previousStage => {
        const currentStage = data.stages.find(stage => stage.key === previousStage.key);
        return currentStage
          ? { key: currentStage.key, label: currentStage.label, rate: currentStage.rate, previousRate: previousStage.rate }
          : null;
      })
      .filter((stage): stage is NonNullable<typeof stage> => stage !== null);
  });

  ngOnInit() {
    this.insightsService.getUsageFunnel().subscribe({
      next: res => {
        this.funnel.set(res.data ?? null);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  stageColor(index: number) {
    return STAGE_COLORS[Math.min(index, STAGE_COLORS.length - 1)];
  }

  // Evenly stepped widths (100% -> 41%) as in the mockup; the labels carry
  // the real counts and rates, the width is purely the pyramid silhouette.
  pyramidWidth(index: number, total: number) {
    if (total <= 1) return 100;
    return Math.round(100 - index * (59 / (total - 1)));
  }

  // Categories only exist from the recommendation stage onward; the top of
  // the funnel is company-wide by definition.
  isCategoryStage(key: string) {
    return key === 'recommendation_received' || key === 'measure_started' || key === 'active_14d';
  }

  categoryCountFor(stageKey: string, category: UsageFunnelCategory) {
    switch (stageKey) {
      case 'recommendation_received': return category.recommendationReceived;
      case 'measure_started': return category.measureStarted;
      default: return category.active14d;
    }
  }

  categoryRateLabel(stageKey: string, category: UsageFunnelCategory) {
    if (stageKey === 'recommendation_received' || category.recommendationReceived <= 0) return 'Basis 100%';
    return `${Math.round(this.categoryCountFor(stageKey, category) / category.recommendationReceived * 100)}%`;
  }

  categoryShareFor(stage: { key: string; count: number }, category: UsageFunnelCategory) {
    if (stage.count <= 0) return 0;
    return Math.min(100, Math.round(this.categoryCountFor(stage.key, category) / stage.count * 100));
  }

  stageLabel(key: string) {
    return this.funnel()?.stages.find(stage => stage.key === key)?.label ?? key;
  }

  transitionDays(transition: { to: string; avgDays: number | null }) {
    if (transition.avgDays === null) return '—';
    // The final stage window is fixed by definition, not measured.
    return transition.to === 'active_14d' ? `${transition.avgDays} Tage (fix)` : `${transition.avgDays} Tage`;
  }

  isCriticalTransition(transition: { to: string }) {
    return this.biggestDrop()?.toKey === transition.to;
  }
}
