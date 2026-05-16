import { Component, inject, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ApiClient } from '../../../../core/services/api-client.service';

@Component({
  selector: 'app-company-users',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div>
      <h1 class="text-2xl font-semibold text-gray-900 mb-6" style="font-family: 'Fraunces', Georgia, serif">Benutzer</h1>

      @if (loading()) {
        <div class="flex justify-center py-12">
          <div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin"></div>
        </div>
      } @else if (users().length === 0) {
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
          <p class="text-gray-500 text-sm">Noch keine Benutzer vorhanden.</p>
        </div>
      } @else {
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100 bg-gray-50/50">
                <th class="text-left px-4 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wider">Name</th>
                <th class="text-left px-4 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wider">E-Mail</th>
                <th class="text-left px-4 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wider">Rollen</th>
                <th class="text-left px-4 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wider">Status</th>
              </tr>
            </thead>
            <tbody>
              @for (user of users(); track user.id) {
                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                  <td class="px-4 py-3 font-medium text-gray-900">{{ user.name }}</td>
                  <td class="px-4 py-3 text-gray-500">{{ user.email }}</td>
                  <td class="px-4 py-3">
                    @for (role of user.roles; track role) {
                      <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-teal-50 text-teal-700 mr-1">{{ role }}</span>
                    }
                  </td>
                  <td class="px-4 py-3">
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium"
                      [class]="user.status === 'active' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'">
                      {{ user.status }}
                    </span>
                  </td>
                </tr>
              }
            </tbody>
          </table>
        </div>
      }
    </div>
  `
})
export class CompanyUsersComponent implements OnInit {
  private api = inject(ApiClient);
  users = signal<any[]>([]);
  loading = signal(true);

  ngOnInit() {
    this.api.get<{ data: any[] }>('/company/users').subscribe({
      next: (res) => { this.users.set(res.data); this.loading.set(false); },
      error: () => this.loading.set(false),
    });
  }
}
