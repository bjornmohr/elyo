import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ApiClient } from '../../../../core/services/api-client.service';

@Component({
  selector: 'app-company-reports',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div>
      <h1 class="text-2xl font-semibold text-gray-900 mb-6" style="font-family: 'Fraunces', Georgia, serif">Berichte</h1>
      @if (loading()) {
        <div class="flex justify-center py-12"><div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin"></div></div>
      } @else if (trend().length === 0) {
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center"><p class="text-gray-500 text-sm">Noch keine Trenddaten vorhanden.</p></div>
      } @else {
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 border-b border-gray-100">
              <th class="text-left px-4 py-3 text-xs uppercase text-gray-500">Periode</th>
              <th class="text-left px-4 py-3 text-xs uppercase text-gray-500">Score</th>
              <th class="text-left px-4 py-3 text-xs uppercase text-gray-500">Mood</th>
              <th class="text-left px-4 py-3 text-xs uppercase text-gray-500">Stress</th>
              <th class="text-left px-4 py-3 text-xs uppercase text-gray-500">Energie</th>
              <th class="text-left px-4 py-3 text-xs uppercase text-gray-500">Antworten</th>
            </tr></thead>
            <tbody>
              @for (point of trend(); track point.period) {
                <tr class="border-b border-gray-50">
                  <td class="px-4 py-3 font-medium">{{ point.period }}</td>
                  <td class="px-4 py-3">{{ point.avgScore }}</td>
                  <td class="px-4 py-3">{{ point.avgMood }}</td>
                  <td class="px-4 py-3">{{ point.avgStress }}</td>
                  <td class="px-4 py-3">{{ point.avgEnergy }}</td>
                  <td class="px-4 py-3">{{ point.respondents }}</td>
                </tr>
              }
            </tbody>
          </table>
        </div>
      }
    </div>
  `
})
export class CompanyReportsComponent implements OnInit {
  private api = inject(ApiClient);
  trend = signal<any[]>([]);
  loading = signal(true);

  ngOnInit() {
    this.api.get<{ data: any[] }>('/company/reports').subscribe({
      next: res => { this.trend.set(res.data ?? []); this.loading.set(false); },
      error: () => this.loading.set(false),
    });
  }
}
