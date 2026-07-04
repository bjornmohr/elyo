import { Component, OnInit, inject, input, output, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CompanyMeasuresService, MeasureImpact } from '../../services/company-measures.service';

@Component({
  selector: 'app-measure-impact-dialog',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(19, 32, 27, 0.4)" (click)="close.emit()">
      <div class="w-full max-w-[760px] max-h-[90vh] overflow-auto bg-white p-6 space-y-5" style="border-radius: 14px" (click)="$event.stopPropagation()">
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="flex items-center gap-2">
              <h2 class="text-xl font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">Wirkungsanalyse</h2>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase" style="background: #fdf3e3; color: #9a6b1f; letter-spacing: .04em">Konzept</span>
            </div>
            <p class="text-xs mt-1" style="color: #6f7d76">{{ subtitle() }}</p>
          </div>
          <button type="button" (click)="close.emit()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>

        @if (loading()) {
          <div class="flex justify-center py-10">
            <div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin"></div>
          </div>
        } @else if (impact(); as data) {
          <div class="rounded-lg p-4 text-xs leading-relaxed" style="background: #faf8f3; color: #6f7d76">
            <span class="font-semibold text-gray-700">Berechnungslogik:</span>
            Wirkung = Δ Feld-Score Teilnehmende ({{ data.windowWeeks }} Wochen vor → {{ data.windowWeeks }} Wochen nach)
            minus Δ Feld-Score Nicht-Teilnehmende im selben Zeitraum.
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="rounded-lg border p-4" style="border-color: #ece6d8">
              <div class="text-[11px] uppercase font-semibold" style="color: #6f7d76; letter-spacing: .04em">
                Teilnehmende (n={{ data.participants.n }})
              </div>
              <div class="flex items-baseline gap-2 mt-2">
                <span class="text-[22px] font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">{{ data.participants.scoreBefore }}</span>
                <span style="color: #6f7d76">→</span>
                <span class="text-[22px] font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">{{ data.participants.scoreAfter }}</span>
                <span class="ml-auto text-xs font-semibold px-2 py-0.5 rounded-full" style="background: #ecfaf7; color: #0f766e">
                  {{ delta(data.participants.scoreAfter - data.participants.scoreBefore) }}
                </span>
              </div>
            </div>
            <div class="rounded-lg border p-4" style="border-color: #ece6d8">
              <div class="text-[11px] uppercase font-semibold" style="color: #6f7d76; letter-spacing: .04em">
                Kontrollgruppe (n={{ data.control.n }})
              </div>
              <div class="flex items-baseline gap-2 mt-2">
                <span class="text-[22px] font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">{{ data.control.scoreBefore }}</span>
                <span style="color: #6f7d76">→</span>
                <span class="text-[22px] font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">{{ data.control.scoreAfter }}</span>
                <span class="ml-auto text-xs font-semibold px-2 py-0.5 rounded-full" style="background: #f1ede3; color: #6f7d76">
                  {{ delta(data.control.scoreAfter - data.control.scoreBefore) }}
                </span>
              </div>
            </div>
          </div>

          <div class="rounded-lg p-4 flex items-center justify-between" style="background: #ecfaf7">
            <div>
              <div class="text-[11px] uppercase font-semibold" style="color: #0f766e; letter-spacing: .04em">Netto-Wirkung</div>
              <div class="text-[28px] font-semibold" style="font-family: 'Fraunces', Georgia, serif; color: #0f766e">
                {{ delta(data.netEffect) }} Punkte
              </div>
            </div>
            <div class="text-lg" style="color: #d9a441" [attr.aria-label]="data.rating + ' von 5 Sternen'">
              {{ stars(data.rating) }}
              <span class="text-xs align-middle ml-1" style="color: #6f7d76">vorläufig</span>
            </div>
          </div>

          <p class="text-[11px] italic leading-relaxed" style="color: #9aa39c">
            Beide Gruppen liegen oberhalb der Anonymitätsschwelle. Ausgewiesen werden ausschließlich
            Gruppen-Durchschnitte, keine individuellen Verläufe.
          </p>
        } @else {
          <div class="py-10 text-center text-sm italic" style="color: #6f7d76">Noch keine Daten</div>
        }
      </div>
    </div>
  `,
})
export class MeasureImpactDialogComponent implements OnInit {
  measure = input.required<{ id: number; title: string; category: string; completedAt?: string | null }>();
  close = output<void>();

  private measuresService = inject(CompanyMeasuresService);

  loading = signal(true);
  impact = signal<MeasureImpact | null>(null);

  ngOnInit() {
    this.measuresService.getImpact(this.measure().id).subscribe({
      next: res => {
        this.impact.set(res.data);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  subtitle() {
    const measure = this.measure();
    const parts = [measure.title, this.categoryLabel(measure.category)];
    if (measure.completedAt) {
      parts.push(`abgeschlossen ${new Date(measure.completedAt).toLocaleDateString('de-DE')}`);
    }
    return parts.join(' · ');
  }

  delta(value: number) {
    return value > 0 ? `+${value}` : `${value}`;
  }

  stars(rating: number) {
    return '★'.repeat(Math.max(0, Math.min(5, rating))) + '☆'.repeat(Math.max(0, 5 - rating));
  }

  private categoryLabel(category: string) {
    switch (category) {
      case 'workshop': return 'Workshop';
      case 'flexibility': return 'Flexibilität';
      case 'sport': return 'Sport';
      case 'mental': return 'Mental';
      case 'nutrition': return 'Ernährung';
      default: return category;
    }
  }
}
