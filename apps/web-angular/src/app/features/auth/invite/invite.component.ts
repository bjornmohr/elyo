import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { ApiClient } from '../../../core/services/api-client.service';
import { AuthStore } from '../../../core/store/auth.store';
import { AuthService } from '../../../core/services/auth.service';
import { AuthLayoutComponent } from '../components/auth-layout.component';

interface InviteInfo {
  valid: boolean;
  email: string;
  companyName: string;
  role: string;
  expiresAt?: string;
  error?: string;
}

@Component({
  selector: 'app-invite',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, AuthLayoutComponent],
  template: `
    <app-auth-layout>
      <div class="animate-fade-up">
        @if (verifying()) {
          <div class="flex flex-col items-center justify-center py-12">
            <div class="w-10 h-10 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin mb-4"></div>
            <p class="text-gray-500 text-sm">Einladung wird geprüft…</p>
          </div>
        }

        @if (inviteError()) {
          <div class="text-center py-8">
            <div class="w-16 h-16 bg-red-50 text-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
              <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            </div>
            <h2 class="text-xl font-semibold text-gray-900 mb-2">Ungültige Einladung</h2>
            <p class="text-gray-500 text-sm mb-6">{{ inviteError() }}</p>
            <p class="text-gray-400 text-xs">Bitte frage nach einem neuen Einladungslink.</p>
          </div>
        }

        @if (invite() && !verifying() && !inviteError()) {
          <div class="mb-8">
            <h2 class="text-2xl font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">
              Einladung annehmen
            </h2>
            <p class="text-gray-400 text-sm mt-1">
              Du wurdest eingeladen, dem Team von <strong>{{ invite()?.companyName }}</strong> beizutreten.
            </p>
          </div>

          <form [formGroup]="inviteForm" (ngSubmit)="onSubmit()" class="space-y-4">
            @if (submitError()) {
              <div class="text-sm px-4 py-3 rounded-xl" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca">
                {{ submitError() }}
              </div>
            }

            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">E-Mail</label>
              <input
                type="email"
                [value]="invite()?.email"
                disabled
                class="w-full px-4 py-2.5 rounded-xl border bg-gray-100 text-sm text-gray-500 outline-none border-gray-200 cursor-not-allowed"
              />
            </div>

            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Dein Name</label>
              <input
                type="text"
                formControlName="name"
                placeholder="Max Muster"
                class="w-full px-4 py-2.5 rounded-xl border bg-stone-50 text-sm text-gray-900 outline-none transition-colors focus:border-teal-500 border-gray-200"
              />
            </div>

            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Passwort wählen</label>
              <input
                type="password"
                formControlName="password"
                placeholder="Mindestens 8 Zeichen"
                class="w-full px-4 py-2.5 rounded-xl border bg-stone-50 text-sm text-gray-900 outline-none transition-colors focus:border-teal-500 border-gray-200"
              />
            </div>

            <div class="space-y-1.5">
              <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Passwort bestätigen</label>
              <input
                type="password"
                formControlName="password_confirmation"
                placeholder="Passwort wiederholen"
                class="w-full px-4 py-2.5 rounded-xl border bg-stone-50 text-sm text-gray-900 outline-none transition-colors focus:border-teal-500 border-gray-200"
              />
            </div>

            <button
              type="submit"
              [disabled]="inviteForm.invalid || loading()"
              class="w-full flex items-center justify-center gap-2 py-3 rounded-xl text-sm font-semibold text-white transition-all duration-200 mt-2"
              [style.background]="loading() ? '#9ca3af' : 'linear-gradient(135deg, #14b8a6, #0d9488)'"
              [style.cursor]="loading() ? 'not-allowed' : 'pointer'"
            >
              @if (loading()) {
                <div class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                Konto wird erstellt…
              } @else {
                Konto erstellen & loslegen
              }
            </button>
          </form>
        }
      </div>
    </app-auth-layout>
  `
})
export class InviteComponent implements OnInit {
  private fb = inject(FormBuilder);
  private api = inject(ApiClient);
  private store = inject(AuthStore);
  private authService = inject(AuthService);
  private route = inject(ActivatedRoute);
  private router = inject(Router);

  token: string | null = null;
  invite = signal<InviteInfo | null>(null);
  verifying = signal(true);
  inviteError = signal<string | null>(null);
  loading = signal(false);
  submitError = signal<string | null>(null);

  inviteForm = this.fb.group({
    name: ['', [Validators.required]],
    password: ['', [Validators.required, Validators.minLength(8)]],
    password_confirmation: ['', [Validators.required]],
  });

  ngOnInit() {
    this.token = this.route.snapshot.paramMap.get('token');
    if (!this.token) {
      this.inviteError.set('Kein Einladungs-Token gefunden.');
      this.verifying.set(false);
      return;
    }
    this.verifyInvite(this.token);
  }

  private verifyInvite(token: string) {
    this.api.get<InviteInfo>('/auth/invite/verify', { token }).subscribe({
      next: (info) => {
        if (info.valid) {
          this.invite.set(info);
        } else {
          this.inviteError.set(info.error || 'Die Einladung ist ungültig oder abgelaufen.');
        }
        this.verifying.set(false);
      },
      error: (err) => {
        this.inviteError.set(err.error?.error || 'Die Einladung ist ungültig oder abgelaufen.');
        this.verifying.set(false);
      }
    });
  }

  onSubmit() {
    if (this.inviteForm.invalid || !this.token) return;

    this.loading.set(true);
    this.submitError.set(null);

    this.api.post<any>('/auth/invite/accept', {
      token: this.token,
      ...this.inviteForm.value,
    }).subscribe({
      next: (res) => {
        if (res.access_token) {
          this.store.setToken(res.access_token);
          this.store.setUser(res.user);
          // Redirect based on role
          this.authService.getMe().subscribe(() => {
            const route = this.authService.getDefaultRoute(this.store.activePortal());
            this.router.navigate([route]);
          });
        } else {
          this.router.navigate(['/auth/login'], { queryParams: { invited: '1' } });
        }
      },
      error: (err) => {
        this.submitError.set(err.error?.error?.message || 'Fehler beim Annehmen der Einladung.');
        this.loading.set(false);
      }
    });
  }
}
