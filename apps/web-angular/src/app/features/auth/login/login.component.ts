import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';
import { AuthLayoutComponent } from '../components/auth-layout.component';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink, AuthLayoutComponent],
  template: `
    <app-auth-layout>
      <div class="animate-fade-up">
        <div class="mb-8">
          <h2 class="text-2xl font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">
            Willkommen zurück
          </h2>
          <p class="text-gray-400 text-sm mt-1">Melde dich mit deinen Zugangsdaten an.</p>
        </div>

        <form [formGroup]="loginForm" (ngSubmit)="onSubmit()" class="space-y-4">
          @if (error()) {
            <div class="text-sm px-4 py-3 rounded-xl" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca">
              {{ error() }}
            </div>
          }

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">E-Mail</label>
            <input
              type="email"
              formControlName="email"
              placeholder="name&#64;unternehmen.de"
              class="w-full px-4 py-2.5 rounded-xl border bg-stone-50 text-sm text-gray-900 outline-none transition-colors focus:border-teal-500 border-gray-200"
            />
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Passwort</label>
            <input
              type="password"
              formControlName="password"
              placeholder="••••••••"
              class="w-full px-4 py-2.5 rounded-xl border bg-stone-50 text-sm text-gray-900 outline-none transition-colors focus:border-teal-500 border-gray-200"
            />
          </div>

          <button
            type="submit"
            [disabled]="loginForm.invalid || loading()"
            class="w-full flex items-center justify-center gap-2 py-3 rounded-xl text-sm font-semibold text-white transition-all duration-200 mt-2"
            [style.background]="loading() ? '#9ca3af' : 'linear-gradient(135deg, #14b8a6, #0d9488)'"
            [style.cursor]="loading() ? 'not-allowed' : 'pointer'"
          >
            @if (loading()) {
              <div class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
              Anmelden…
            } @else {
              Anmelden
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
            }
          </button>
        </form>

        <p class="text-center text-sm text-gray-400 mt-6">
          Noch kein Konto?
          <a routerLink="/auth/register" class="font-semibold hover:underline" style="color: #14b8a6">
            Zugang per Einladung
          </a>
        </p>
      </div>
    </app-auth-layout>
  `
})
export class LoginComponent {
  private fb = inject(FormBuilder);
  private authService = inject(AuthService);
  private router = inject(Router);

  loginForm = this.fb.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required]]
  });

  loading = signal(false);
  error = signal<string | null>(null);

  onSubmit() {
    if (this.loginForm.invalid) return;

    this.loading.set(true);
    this.error.set(null);

    const requestedPortal = this.authService.detectPortalFromHostname();

    this.authService.login({
      email: this.loginForm.value.email!,
      password: this.loginForm.value.password!,
      requested_portal: requestedPortal ?? undefined,
    }).subscribe({
      next: (res) => {
        const route = this.authService.getDefaultRoute(res.activePortal);
        this.router.navigate([route]);
      },
      error: (err) => {
        if (err.status === 403 && err.error?.error?.code === 'PORTAL_FORBIDDEN') {
          this.error.set('Sie haben keinen Zugang zu diesem Portal.');
        } else {
          this.error.set('E-Mail oder Passwort falsch.');
        }
        this.loading.set(false);
      }
    });
  }
}
