import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ApiClient } from '../../../../core/services/api-client.service';

@Component({
  selector: 'app-company-teams',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div>
      <h1 class="text-2xl font-semibold text-gray-900 mb-6" style="font-family: 'Fraunces', Georgia, serif">Teams</h1>
      @if (loading()) {
        <div class="flex justify-center py-12"><div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin"></div></div>
      } @else if (teams().length === 0) {
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center"><p class="text-gray-500 text-sm">Noch keine Teams vorhanden.</p></div>
      } @else {
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
          @for (team of teams(); track team.id) {
            <div class="bg-white rounded-xl border border-gray-200 p-5">
              <div class="flex items-center gap-3">
                <span class="w-3 h-3 rounded-full" [style.background]="team.color || '#14b8a6'"></span>
                <h2 class="font-semibold text-gray-900">{{ team.name }}</h2>
              </div>
              <p class="text-sm text-gray-500 mt-2">{{ team.description || 'Kein Beschreibungstext' }}</p>
              <div class="grid grid-cols-2 gap-3 mt-4 text-sm">
                <div class="rounded-lg bg-stone-50 p-3"><div class="text-xs text-gray-400">Mitglieder</div><div class="font-semibold">{{ team.memberCount ?? 0 }}</div></div>
                <div class="rounded-lg bg-stone-50 p-3"><div class="text-xs text-gray-400">Manager</div><div class="font-semibold truncate">{{ team.manager?.name || '—' }}</div></div>
              </div>
            </div>
          }
        </div>
      }
    </div>
  `
})
export class CompanyTeamsComponent implements OnInit {
  private api = inject(ApiClient);
  teams = signal<any[]>([]);
  loading = signal(true);

  ngOnInit() {
    this.api.get<{ data: any[] }>('/company/teams').subscribe({
      next: res => { this.teams.set(res.data ?? []); this.loading.set(false); },
      error: () => this.loading.set(false),
    });
  }
}
