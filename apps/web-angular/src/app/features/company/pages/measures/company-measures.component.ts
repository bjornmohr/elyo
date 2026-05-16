import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ApiClient } from '../../../../core/services/api-client.service';

@Component({
  selector: 'app-company-measures',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div>
      <h1 class="text-2xl font-semibold text-gray-900 mb-6" style="font-family: 'Fraunces', Georgia, serif">Maßnahmen</h1>
      @if (loading()) {
        <div class="flex justify-center py-12"><div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin"></div></div>
      } @else if (measures().length === 0) {
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center"><p class="text-gray-500 text-sm">Noch keine Maßnahmen vorhanden.</p></div>
      } @else {
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 border-b border-gray-100">
              <th class="text-left px-4 py-3 text-xs uppercase text-gray-500">Titel</th>
              <th class="text-left px-4 py-3 text-xs uppercase text-gray-500">Kategorie</th>
              <th class="text-left px-4 py-3 text-xs uppercase text-gray-500">Team</th>
              <th class="text-left px-4 py-3 text-xs uppercase text-gray-500">Status</th>
            </tr></thead>
            <tbody>
              @for (measure of measures(); track measure.id) {
                <tr class="border-b border-gray-50">
                  <td class="px-4 py-3 font-medium text-gray-900">{{ measure.title }}</td>
                  <td class="px-4 py-3 text-gray-500">{{ measure.category }}</td>
                  <td class="px-4 py-3 text-gray-500">{{ measure.team?.name || 'Alle Teams' }}</td>
                  <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs bg-teal-50 text-teal-700">{{ measure.status }}</span></td>
                </tr>
              }
            </tbody>
          </table>
        </div>
      }
    </div>
  `
})
export class CompanyMeasuresComponent implements OnInit {
  private api = inject(ApiClient);
  measures = signal<any[]>([]);
  loading = signal(true);

  ngOnInit() {
    this.api.get<{ data: any[] }>('/company/measures').subscribe({
      next: res => { this.measures.set(res.data ?? []); this.loading.set(false); },
      error: () => this.loading.set(false),
    });
  }
}
