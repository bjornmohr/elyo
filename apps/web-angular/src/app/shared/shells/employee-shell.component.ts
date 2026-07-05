import { Component, inject } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';
import { NotificationBannerComponent } from '../notifications/notification-banner.component';

@Component({
  selector: 'app-employee-shell',
  standalone: true,
  imports: [RouterOutlet, RouterLink, RouterLinkActive, NotificationBannerComponent],
  template: `
    <div class="flex h-screen min-h-screen overflow-hidden" style="background: hsl(40, 20%, 97%)">
      <aside class="sticky top-0 h-screen w-64 flex-shrink-0 border-r border-gray-200 bg-white">
        <div class="flex h-full min-h-0 flex-col">
          <div class="flex-shrink-0 px-[26px] py-7">
            <img src="assets/brand/elyo-logo.png" alt="ELYO" class="h-auto w-32 object-contain" />
          </div>
          <nav class="min-h-0 flex-1 space-y-[5px] overflow-y-auto px-3">
            <a routerLink="/employee/dashboard" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center gap-3 px-3.5 py-[11px] rounded-[10px] text-[15px] text-gray-600 hover:bg-gray-50 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
              Übersicht
            </a>
            <a routerLink="/employee/checkin" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center gap-3 px-3.5 py-[11px] rounded-[10px] text-[15px] text-gray-600 hover:bg-gray-50 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
              Check-in
            </a>
            <a routerLink="/employee/measures" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center gap-3 px-3.5 py-[11px] rounded-[10px] text-[15px] text-gray-600 hover:bg-gray-50 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
              Maßnahmen
            </a>
            <a routerLink="/employee/lab-markers" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center gap-3 px-3.5 py-[11px] rounded-[10px] text-[15px] text-gray-600 hover:bg-gray-50 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path></svg>
              Laborwerte
            </a>
            <a routerLink="/employee/history" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center gap-3 px-3.5 py-[11px] rounded-[10px] text-[15px] text-gray-600 hover:bg-gray-50 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
              Verlauf
            </a>
            <a routerLink="/employee/badges" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center gap-3 px-3.5 py-[11px] rounded-[10px] text-[15px] text-gray-600 hover:bg-gray-50 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"></circle><path d="M8.5 12.5 7 22l5-3 5 3-1.5-9.5"></path></svg>
              Badges
            </a>
            <a routerLink="/employee/surveys" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center gap-3 px-3.5 py-[11px] rounded-[10px] text-[15px] text-gray-600 hover:bg-gray-50 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
              Umfragen
            </a>
            <a routerLink="/employee/profile" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center gap-3 px-3.5 py-[11px] rounded-[10px] text-[15px] text-gray-600 hover:bg-gray-50 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
              Profil
            </a>
          </nav>
          <div class="flex-shrink-0 border-t border-gray-100 p-[18px]">
            <button (click)="logout()" class="flex items-center gap-2 text-[15px] text-gray-500 hover:text-gray-700 transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
              Abmelden
            </button>
          </div>
        </div>
      </aside>
      <main class="min-h-0 flex-1 overflow-y-auto p-9">
        <app-notification-banner />
        <router-outlet />
      </main>
    </div>
  `
})
export class EmployeeShellComponent {
  private authService = inject(AuthService);

  logout() {
    this.authService.logout();
  }
}
