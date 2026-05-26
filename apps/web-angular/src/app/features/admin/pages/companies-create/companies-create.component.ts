import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { ApiClient } from '../../../../core/services/api-client.service';
import { NotificationService } from '../../../../shared/notifications/notification.service';

@Component({
  selector: 'app-admin-companies-create',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink],
  template: `
    <div class="max-w-lg">
      <div class="mb-6">
        <a routerLink="/admin/companies" class="text-sm text-teal-600 hover:underline">← Zurück</a>
        <h1 class="text-2xl font-semibold text-gray-900 mt-2" style="font-family: 'Fraunces', Georgia, serif">Unternehmen anlegen</h1>
      </div>

      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <form [formGroup]="form" (ngSubmit)="onSubmit()" class="space-y-4">
          @if (error()) {
            <div class="text-sm px-4 py-3 rounded-xl" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca">
              {{ error() }}
            </div>
          }

          @if (success()) {
            <div class="text-sm px-4 py-3 rounded-xl" style="background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0">
              Unternehmen erfolgreich angelegt!
              @if (inviteToken()) {
                <div class="mt-2 p-2 bg-white rounded border text-xs font-mono break-all">
                  Einladungs-Token: {{ inviteToken() }}
                </div>
              }
            </div>
          }

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Unternehmensname</label>
            <input type="text" formControlName="name" placeholder="Muster GmbH"
              class="w-full px-4 py-2.5 rounded-xl border bg-stone-50 text-sm text-gray-900 outline-none transition-colors focus:border-teal-500 border-gray-200" />
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Slug (optional)</label>
            <input type="text" formControlName="slug" placeholder="muster-gmbh"
              class="w-full px-4 py-2.5 rounded-xl border bg-stone-50 text-sm text-gray-900 outline-none transition-colors focus:border-teal-500 border-gray-200" />
          </div>

          <div class="space-y-1.5">
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">E-Mail des ersten Admins</label>
            <input type="email" formControlName="adminEmail" placeholder="admin&#64;muster.de"
              class="w-full px-4 py-2.5 rounded-xl border bg-stone-50 text-sm text-gray-900 outline-none transition-colors focus:border-teal-500 border-gray-200" />
          </div>

          <label class="flex items-center gap-3 text-sm text-gray-700">
            <input type="checkbox" formControlName="teamLayerEnabled"
              class="h-4 w-4 rounded border-gray-300 text-teal-600 focus:ring-teal-500" />
            Teamlayer aktivieren
          </label>

          <button type="submit" [disabled]="form.invalid || loading() || success()"
            class="w-full flex items-center justify-center gap-2 py-3 rounded-xl text-sm font-semibold text-white transition-all duration-200"
            [style.background]="loading() ? '#9ca3af' : 'linear-gradient(135deg, #14b8a6, #0d9488)'"
            [style.cursor]="loading() ? 'not-allowed' : 'pointer'">
            @if (loading()) {
              <div class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
              Wird erstellt…
            } @else {
              Unternehmen anlegen & Admin einladen
            }
          </button>
        </form>
      </div>
    </div>
  `
})
export class AdminCompaniesCreateComponent {
  private fb = inject(FormBuilder);
  private api = inject(ApiClient);
  private notifications = inject(NotificationService);

  form = this.fb.group({
    name: ['', [Validators.required]],
    slug: [''],
    adminEmail: ['', [Validators.required, Validators.email]],
    teamLayerEnabled: [false],
  });

  loading = signal(false);
  error = signal<string | null>(null);
  success = signal(false);
  inviteToken = signal<string | null>(null);

  onSubmit() {
    if (this.form.invalid) return;
    this.loading.set(true);
    this.error.set(null);

    const { name, slug, teamLayerEnabled } = this.form.value;

    this.api.post<{ data: any }>('/admin/companies', {
      name,
      slug: slug || undefined,
      team_layer_enabled: teamLayerEnabled ?? false,
    }).subscribe({
      next: (res) => {
        const companyId = res.data.id;
        // Invite first company admin
        this.api.post<{ data: any }>(`/admin/companies/${companyId}/invite-company-admin`, {
          email: this.form.value.adminEmail,
        }).subscribe({
          next: (inviteRes) => {
            this.success.set(true);
            this.inviteToken.set(inviteRes.data.invite_token);
            this.notifications.success('Unternehmen wurde angelegt und die Einladung wurde gespeichert.');
            this.loading.set(false);
          },
          error: (err) => {
            this.success.set(true); // Company was created
            const message = 'Unternehmen angelegt, aber Einladung fehlgeschlagen: ' + (err.error?.error?.message || 'Unbekannter Fehler');
            this.error.set(message);
            this.notifications.error(message);
            this.loading.set(false);
          }
        });
      },
      error: (err) => {
        const message = err.error?.error?.message || err.error?.message || 'Fehler beim Anlegen.';
        this.error.set(message);
        this.notifications.error(message);
        this.loading.set(false);
      }
    });
  }
}
