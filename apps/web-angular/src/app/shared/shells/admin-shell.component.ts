import { Component, inject } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';
import { NotificationBannerComponent } from '../notifications/notification-banner.component';

@Component({
  selector: 'app-admin-shell',
  standalone: true,
  imports: [RouterOutlet, RouterLink, RouterLinkActive, NotificationBannerComponent],
  template: `
    <div class="min-h-screen flex" style="background: hsl(40, 20%, 97%)">
      <aside class="w-64 shrink-0 border-r border-gray-200 bg-white flex flex-col">
        <div class="p-6 flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: linear-gradient(135deg, #14b8a6, #0d9488)">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 2C8 2 3 5.5 3 9a5 5 0 0010 0C13 5.5 8 2 8 2z" fill="white" fill-opacity="0.9"/><path d="M8 6v4M6 8h4" stroke="#0a4540" stroke-width="1.5" stroke-linecap="round"/></svg>
          </div>
          <span class="text-gray-900 text-lg font-semibold" style="font-family: 'Fraunces', Georgia, serif">Elyo Admin</span>
        </div>
        <nav class="flex-1 px-3 space-y-1">
          <a routerLink="/admin/companies" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>
            Unternehmen
          </a>
          <a routerLink="/admin/users" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
            Benutzer
          </a>
          <a routerLink="/admin/points" routerLinkActive="bg-teal-50 text-teal-700" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8"></circle><path d="M12 8v8"></path><path d="M9.5 10.5c0-1.2 1.2-2 2.5-2s2.5.8 2.5 2-1.2 2-2.5 2-2.5.8-2.5 2 1.2 2 2.5 2 2.5-.8 2.5-2"></path></svg>
            Punkte
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
export class AdminShellComponent {
  private authService = inject(AuthService);

  logout() {
    this.authService.logout();
  }
}
