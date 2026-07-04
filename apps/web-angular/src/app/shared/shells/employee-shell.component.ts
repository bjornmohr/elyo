import { Component, inject } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';
import { NotificationBannerComponent } from '../notifications/notification-banner.component';

@Component({
  selector: 'app-employee-shell',
  standalone: true,
  imports: [RouterOutlet, RouterLink, RouterLinkActive, NotificationBannerComponent],
  template: `
    <div class="min-h-screen flex" style="background: hsl(40, 20%, 97%)">
      <aside class="w-64 flex-shrink-0 border-r border-gray-200 bg-white flex flex-col">
        <div class="p-6 flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: linear-gradient(135deg, #14b8a6, #0d9488)">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 2C8 2 3 5.5 3 9a5 5 0 0010 0C13 5.5 8 2 8 2z" fill="white" fill-opacity="0.9"/><path d="M8 6v4M6 8h4" stroke="#0a4540" stroke-width="1.5" stroke-linecap="round"/></svg>
          </div>
          <span class="text-gray-900 text-lg font-semibold" style="font-family: 'Fraunces', Georgia, serif">Elyo</span>
        </div>
        <nav class="flex-1 px-3 space-y-1">
          <a routerLink="/employee/dashboard" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
            Übersicht
          </a>
          <a routerLink="/employee/checkin" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            Check-in
          </a>
          <a routerLink="/employee/measures" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
            Maßnahmen
          </a>
          <a routerLink="/employee/history" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            Verlauf
          </a>
          <a routerLink="/employee/surveys" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
            Umfragen
          </a>
          <a routerLink="/employee/profile" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            Profil
          </a>
        </nav>
        <div class="p-4 border-t border-gray-100">
          <button (click)="logout()" class="flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            Abmelden
          </button>
        </div>
      </aside>
      <main class="flex-1 p-8 overflow-auto">
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
