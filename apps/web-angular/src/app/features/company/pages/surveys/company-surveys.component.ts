import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ApiClient } from '../../../../core/services/api-client.service';

@Component({
  selector: 'app-company-surveys',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div>
      <h1 class="text-2xl font-semibold text-gray-900 mb-6" style="font-family: 'Fraunces', Georgia, serif">Umfragen</h1>
      @if (loading()) {
        <div class="flex justify-center py-12"><div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin"></div></div>
      } @else if (surveys().length === 0) {
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center"><p class="text-gray-500 text-sm">Noch keine Umfragen vorhanden.</p></div>
      } @else {
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          @for (survey of surveys(); track survey.id) {
            <div class="bg-white rounded-xl border border-gray-200 p-5">
              <div class="flex items-start justify-between gap-4">
                <div>
                  <h2 class="font-semibold text-gray-900">{{ survey.title }}</h2>
                  <p class="text-sm text-gray-500 mt-1">{{ survey.description || 'Keine Beschreibung' }}</p>
                </div>
                <span class="px-2 py-0.5 rounded-full text-xs bg-teal-50 text-teal-700">{{ survey.status }}</span>
              </div>
              <div class="flex gap-3 mt-4 text-xs text-gray-500">
                <span>{{ survey.questionsCount ?? 0 }} Fragen</span>
                <span>{{ survey.responsesCount ?? 0 }} Antworten</span>
              </div>
            </div>
          }
        </div>
      }
    </div>
  `
})
export class CompanySurveysComponent implements OnInit {
  private api = inject(ApiClient);
  surveys = signal<any[]>([]);
  loading = signal(true);

  ngOnInit() {
    this.api.get<{ data: any[] }>('/company/surveys').subscribe({
      next: res => { this.surveys.set(res.data ?? []); this.loading.set(false); },
      error: () => this.loading.set(false),
    });
  }
}
