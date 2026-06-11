import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AuthService } from '../../../core/services/auth.service';
import { AuthStore } from '../../../core/store/auth.store';
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
              class="w-full px-4 py-2.5 rounded-xl border bg-stone-50 text-sm text-gray-900 outline-none transition-colors focus:border-teal-500"
              [class.border-red-300]="fieldInvalid('email')"
              [class.border-gray-200]="!fieldInvalid('email')"
            />
            @if (fieldMessage('email'); as message) {
              <p class="text-xs text-red-600">{{ message }}</p>
            }
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Passwort</label>
            <input
              type="password"
              formControlName="password"
              placeholder="••••••••"
              class="w-full px-4 py-2.5 rounded-xl border bg-stone-50 text-sm text-gray-900 outline-none transition-colors focus:border-teal-500"
              [class.border-red-300]="fieldInvalid('password')"
              [class.border-gray-200]="!fieldInvalid('password')"
            />
            @if (fieldMessage('password'); as message) {
              <p class="text-xs text-red-600">{{ message }}</p>
            }
          </div>

          <button
            type="submit"
            [disabled]="loading()"
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
export class LoginComponent implements OnInit {
  private fb = inject(FormBuilder);
  private authService = inject(AuthService);
  private authStore = inject(AuthStore);
  private route = inject(ActivatedRoute);
  private router = inject(Router);

  loginForm = this.fb.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required]]
  });

  loading = signal(false);
  error = signal<string | null>(null);
  fieldErrors = signal<Record<string, string>>({});

  ngOnInit() {
    if (this.authStore.isAuthenticated()) {
      this.redirectAfterAuth(this.authStore.activePortal());
      return;
    }

    if (!this.authStore.token()) return;

    this.loading.set(true);
    this.authService.getMe().subscribe({
      next: user => {
        if (user) {
          this.redirectAfterAuth(this.authStore.activePortal());
        } else {
          this.loading.set(false);
        }
      },
      error: () => this.loading.set(false)
    });
  }

  onSubmit() {
    this.error.set(null);
    this.fieldErrors.set({});

    if (this.loginForm.invalid) {
      this.loginForm.markAllAsTouched();
      return;
    }

    this.submitLogin(this.authService.detectPortalFromHostname());
  }

  private submitLogin(requestedPortal: string | null) {
    this.loading.set(true);
    this.error.set(null);

    this.authService.login({
      email: this.loginForm.value.email!,
      password: this.loginForm.value.password!,
      requested_portal: requestedPortal ?? undefined,
    }).subscribe({
      next: () => {
        this.redirectAfterAuth(this.authStore.activePortal());
      },
      error: (err) => {
        if (requestedPortal && err.status === 403 && err.error?.error?.code === 'PORTAL_FORBIDDEN') {
          this.submitLogin(null);
        } else if (err.status === 403 && err.error?.error?.code === 'PORTAL_FORBIDDEN') {
          this.error.set('Sie haben keinen Zugang zu diesem Portal.');
          this.loading.set(false);
        } else {
          this.applyLoginError(err);
          this.loading.set(false);
        }
      }
    });
  }

  fieldInvalid(control: string) {
    const field = this.loginForm.get(control);
    return !!this.fieldErrors()[control] || (!!field && field.invalid && (field.dirty || field.touched));
  }

  fieldMessage(control: string) {
    const backendMessage = this.fieldErrors()[control];
    if (backendMessage) return backendMessage;

    const field = this.loginForm.get(control);
    if (!field || !(field.dirty || field.touched) || !field.errors) return null;

    if (field.errors['required']) return control === 'email' ? 'E-Mail ist erforderlich.' : 'Passwort ist erforderlich.';
    if (field.errors['email']) return 'Bitte eine gültige E-Mail-Adresse eingeben.';

    return 'Eingabe ist ungültig.';
  }

  private applyLoginError(err: any) {
    const errors = err.error?.errors as Record<string, string[]> | undefined;
    const emailMessage = errors?.['email']?.[0] ?? '';

    if (err.status === 422 && emailMessage.includes('Anmeldedaten')) {
      this.error.set('E-Mail oder Passwort ist ungültig.');
      return;
    }

    if (errors) {
      this.fieldErrors.set(Object.fromEntries(
        Object.entries(errors).map(([key, messages]) => [key, messages[0]])
      ));
      this.error.set('Bitte prüfe die markierten Felder.');
      return;
    }

    this.error.set('E-Mail oder Passwort ist ungültig.');
  }

  private redirectAfterAuth(portal = this.authStore.activePortal()) {
    const returnUrl = this.route.snapshot.queryParamMap.get('returnUrl');
    const allowedPortals = this.authStore.allowedPortals();
    const resolvedPortal = this.authService.resolvePortal(portal, allowedPortals);

    if (!resolvedPortal) {
      this.error.set('Sie haben keinen Zugang zu einem Portal.');
      this.loading.set(false);
      return;
    }

    const target = returnUrl && returnUrl !== '/auth/login' && this.authService.isAllowedPortalUrl(returnUrl, allowedPortals)
      ? returnUrl
      : this.authService.getDefaultRoute(resolvedPortal);

    this.router.navigateByUrl(target);
  }
}
