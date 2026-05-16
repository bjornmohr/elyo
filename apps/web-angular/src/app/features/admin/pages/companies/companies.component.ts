import { Component, inject, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { ApiClient } from '../../../../core/services/api-client.service';

@Component({
  selector: 'app-admin-companies',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div>
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">Unternehmen</h1>
        <a routerLink="/admin/companies/create" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white" style="background: linear-gradient(135deg, #14b8a6, #0d9488)">
          + Unternehmen anlegen
        </a>
      </div>

      @if (loading()) {
        <div class="flex justify-center py-12">
          <div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin"></div>
        </div>
      } @else if (companies().length === 0) {
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
          <p class="text-gray-500 text-sm">Noch keine Unternehmen angelegt.</p>
        </div>
      } @else {
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100 bg-gray-50/50">
                <th class="text-left px-4 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wider">Name</th>
                <th class="text-left px-4 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wider">Slug</th>
                <th class="text-left px-4 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wider">Status</th>
                <th class="text-left px-4 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wider">Benutzer</th>
              </tr>
            </thead>
            <tbody>
              @for (company of companies(); track company.id) {
                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                  <td class="px-4 py-3 font-medium text-gray-900">{{ company.name }}</td>
                  <td class="px-4 py-3 text-gray-500">{{ company.slug }}</td>
                  <td class="px-4 py-3">
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium"
                      [class]="company.status === 'active' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'">
                      {{ company.status }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-gray-500">{{ company.users_count }}</td>
                </tr>
              }
            </tbody>
          </table>
        </div>
      }
    </div>
  `
})
export class AdminCompaniesComponent implements OnInit {
  private api = inject(ApiClient);
  companies = signal<any[]>([]);
  loading = signal(true);

  ngOnInit() {
    this.api.get<{ data: any[] }>('/admin/companies').subscribe({
      next: (res) => {
        this.companies.set(res.data);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }
}
