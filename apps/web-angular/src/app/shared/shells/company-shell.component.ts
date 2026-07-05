import { Component, inject } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';
import { AuthStore } from '../../core/store/auth.store';
import { NotificationBannerComponent } from '../notifications/notification-banner.component';

@Component({
  selector: 'app-company-shell',
  standalone: true,
  imports: [RouterOutlet, RouterLink, RouterLinkActive, NotificationBannerComponent],
  template: `
    <div class="flex h-screen min-h-screen overflow-hidden" style="background: hsl(40, 20%, 97%)">
      <aside class="sticky top-0 h-screen w-64 flex-shrink-0 border-r border-gray-200 bg-white">
        <div class="flex h-full min-h-0 flex-col">
          <div class="flex-shrink-0 px-6 py-7">
            <img src="assets/brand/elyo-logo.png" alt="ELYO" class="h-auto w-32 object-contain" />
          </div>
          <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto px-3">
            <a routerLink="/company/dashboard" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
              Übersicht
            </a>
            <a routerLink="/company/users" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
              Benutzer
            </a>
            <a routerLink="/company/invitations" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
              Einladungen
            </a>
            @if (store.teamLayerEnabled()) {
              <a routerLink="/company/teams" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                Teams
              </a>
            }
            <a routerLink="/company/surveys" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
              Umfragen
            </a>
            <a routerLink="/company/measures" [routerLinkActiveOptions]="{ exact: true }" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
              Maßnahmen
            </a>
            <div class="ml-[30px] border-l pl-2 space-y-1" style="border-color: #e5ded3">
              <a routerLink="/company/measures" [routerLinkActiveOptions]="{ exact: true }" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center px-3 py-1.5 rounded-lg text-[13px] text-gray-600 hover:bg-gray-50 transition-colors">
                Alle Maßnahmen
              </a>
              <a routerLink="/company/measures/statistics" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center px-3 py-1.5 rounded-lg text-[13px] text-gray-600 hover:bg-gray-50 transition-colors">
                Statistiken
              </a>
            </div>
            <a routerLink="/company/reports" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
              Berichte
            </a>
            @if (store.riskLandscapeEnabled() || store.usageFunnelEnabled() || store.infectionRadarEnabled()) {
              <div class="pt-4 px-3 text-xs font-semibold uppercase text-gray-500" style="letter-spacing: .06em">Neue Module</div>
              @if (store.riskLandscapeEnabled()) {
                <a routerLink="/company/risk-landscape" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                  Risikolandschaft
                  <span class="ml-auto text-xs font-bold px-2 py-0.5 rounded-full" style="background: #fdf3e3; color: #9a6b1f">NEU</span>
                </a>
              }
              @if (store.usageFunnelEnabled()) {
                <a routerLink="/company/usage-funnel" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                  Nutzungsverhalten
                  <span class="ml-auto text-xs font-bold px-2 py-0.5 rounded-full" style="background: #fdf3e3; color: #9a6b1f">NEU</span>
                </a>
              }
              @if (store.infectionRadarEnabled()) {
                <a routerLink="/company/infection-radar" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
                  <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 14.76V3.5a2 2 0 0 0-4 0v11.26a4.5 4.5 0 1 0 4 0z"></path></svg>
                  Infektionsradar
                  <span class="ml-auto text-xs font-bold px-2 py-0.5 rounded-full" style="background: #fdf3e3; color: #9a6b1f">NEU</span>
                </a>
              }
            }
          </nav>
          <div class="flex-shrink-0 border-t border-gray-100 p-4">
            <div class="text-xs text-gray-400 mb-2">{{ store.user()?.companyName }}</div>
            <button (click)="logout()" class="flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
              Abmelden
            </button>
          </div>
        </div>
      </aside>
      <main class="min-h-0 flex-1 overflow-y-auto p-8">
        <app-notification-banner />
        <router-outlet />
      </main>
    </div>
  `
})
export class CompanyShellComponent {
  store = inject(AuthStore);
  private authService = inject(AuthService);

  logout() {
    this.authService.logout();
  }
}
