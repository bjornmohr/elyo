import { Component, inject, OnInit } from '@angular/core';
import { RouterOutlet, RouterLink, RouterLinkActive } from '@angular/router';
import { CommonModule } from '@angular/common';
import { AuthStore } from './core/store/auth.store';
import { AuthService } from './core/services/auth.service';
import { ApiClient } from './core/services/api-client.service';
import { Role } from './core/models/auth.models';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [RouterOutlet, RouterLink, RouterLinkActive, CommonModule],
  templateUrl: './app.html',
  styleUrl: './app.scss'
})
export class AppComponent implements OnInit {
  authStore = inject(AuthStore);
  authService = inject(AuthService);
  apiClient = inject(ApiClient);

  healthStatus: 'ok' | 'error' | 'loading' = 'loading';
  Role = Role;

  ngOnInit() {
    this.checkHealth();
    this.authService.getMe().subscribe();
  }

  checkHealth() {
    this.apiClient.get<any>('/health').subscribe({
      next: () => this.healthStatus = 'ok',
      error: () => this.healthStatus = 'error'
    });
  }

  logout() {
    this.authService.logout();
  }
}
