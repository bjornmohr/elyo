import { Component, inject, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ApiClient } from '../../../../core/services/api-client.service';

@Component({
  selector: 'app-company-invitations',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  template: `
    <div>
      <h1 class="text-2xl font-semibold text-gray-900 mb-6" style="font-family: 'Fraunces', Georgia, serif">Einladungen</h1>

      <!-- Invite form -->
      <div class="bg-white rounded-xl border border-gray-200 p-6 mb-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-4">Neuen Benutzer einladen</h2>
        <form [formGroup]="inviteForm" (ngSubmit)="onInvite()" class="flex gap-3 items-end flex-wrap">
          @if (inviteError()) {
            <div class="w-full text-sm px-4 py-2 rounded-lg" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca">
              {{ inviteError() }}
            </div>
          }
          @if (inviteSuccess()) {
            <div class="w-full text-sm px-4 py-2 rounded-lg" style="background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0">
              Einladung gesendet!
              @if (lastToken()) {
                <span class="ml-2 font-mono text-xs">Token: {{ lastToken() }}</span>
              }
            </div>
          }
          <div class="flex-1 min-w-[200px] space-y-1">
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">E-Mail</label>
            <input type="email" formControlName="email" placeholder="name&#64;firma.de"
              class="w-full px-3 py-2 rounded-lg border bg-stone-50 text-sm text-gray-900 outline-none focus:border-teal-500 border-gray-200" />
          </div>
          <div class="w-48 space-y-1">
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Rolle</label>
            <select formControlName="role"
              class="w-full px-3 py-2 rounded-lg border bg-stone-50 text-sm text-gray-900 outline-none focus:border-teal-500 border-gray-200">
              <option value="EMPLOYEE">Mitarbeiter</option>
              <option value="COMPANY_ADMIN">Company Admin</option>
              <option value="COMPANY_MANAGER">Manager</option>
            </select>
          </div>
          <button type="submit" [disabled]="inviteForm.invalid || inviting()"
            class="px-4 py-2 rounded-lg text-sm font-semibold text-white"
            style="background: linear-gradient(135deg, #14b8a6, #0d9488)">
            Einladen
          </button>
        </form>
      </div>

      <!-- Invitations list -->
      @if (loading()) {
        <div class="flex justify-center py-12">
          <div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin"></div>
        </div>
      } @else if (invitations().length === 0) {
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
          <p class="text-gray-500 text-sm">Noch keine Einladungen vorhanden.</p>
        </div>
      } @else {
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100 bg-gray-50/50">
                <th class="text-left px-4 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wider">E-Mail</th>
                <th class="text-left px-4 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wider">Rolle</th>
                <th class="text-left px-4 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wider">Status</th>
                <th class="text-left px-4 py-3 font-semibold text-gray-500 text-xs uppercase tracking-wider">Läuft ab</th>
                <th class="text-left px-4 py-3"></th>
              </tr>
            </thead>
            <tbody>
              @for (inv of invitations(); track inv.id) {
                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                  <td class="px-4 py-3 text-gray-900">{{ inv.email }}</td>
                  <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-teal-50 text-teal-700">{{ inv.role }}</span></td>
                  <td class="px-4 py-3">
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium"
                      [class]="inv.status === 'pending' ? 'bg-yellow-50 text-yellow-700' : inv.status === 'accepted' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'">
                      {{ inv.status }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-gray-500 text-xs">{{ inv.expiresAt | date:'short' }}</td>
                  <td class="px-4 py-3">
                    @if (inv.status === 'pending') {
                      <button (click)="revoke(inv.id)" class="text-xs text-red-500 hover:underline">Widerrufen</button>
                    }
                  </td>
                </tr>
              }
            </tbody>
          </table>
        </div>
      }
    </div>
  `
})
export class CompanyInvitationsComponent implements OnInit {
  private api = inject(ApiClient);
  private fb = inject(FormBuilder);

  invitations = signal<any[]>([]);
  loading = signal(true);
  inviting = signal(false);
  inviteError = signal<string | null>(null);
  inviteSuccess = signal(false);
  lastToken = signal<string | null>(null);

  inviteForm = this.fb.group({
    email: ['', [Validators.required, Validators.email]],
    role: ['EMPLOYEE', [Validators.required]],
  });

  ngOnInit() {
    this.loadInvitations();
  }

  loadInvitations() {
    this.api.get<{ data: any[] }>('/company/invitations').subscribe({
      next: (res) => { this.invitations.set(res.data); this.loading.set(false); },
      error: () => this.loading.set(false),
    });
  }

  onInvite() {
    if (this.inviteForm.invalid) return;
    this.inviting.set(true);
    this.inviteError.set(null);
    this.inviteSuccess.set(false);

    this.api.post<{ data: any }>('/company/invitations', this.inviteForm.value).subscribe({
      next: (res) => {
        this.inviteSuccess.set(true);
        this.lastToken.set(res.data.invite_token);
        this.inviting.set(false);
        this.inviteForm.reset({ role: 'EMPLOYEE' });
        this.loadInvitations();
      },
      error: (err) => {
        this.inviteError.set(err.error?.error?.message || 'Einladung fehlgeschlagen.');
        this.inviting.set(false);
      }
    });
  }

  revoke(id: number) {
    this.api.delete(`/company/invitations/${id}`).subscribe({
      next: () => this.loadInvitations(),
    });
  }
}
