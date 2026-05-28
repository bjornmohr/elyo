import { Component, inject, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ApiClient } from '../../../../core/services/api-client.service';
import { Role } from '../../../../core/models/auth.models';
import { AuthStore } from '../../../../core/store/auth.store';
import { NotificationService } from '../../../../shared/notifications/notification.service';

interface InvitationListItem {
  id: number;
  email: string;
  role: string;
  teamId?: number | null;
  status: string;
  expiresAt: string;
}

interface CompanyTeamOption {
  id: number;
  name: string;
}

interface CreateInvitationPayload {
  email: string;
  role: string;
  teamId?: number;
}

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
              @if (!isManagerOnly()) {
                <option value="COMPANY_ADMIN">Company Admin</option>
                @if (teamLayerEnabled()) {
                  <option value="COMPANY_MANAGER">Manager</option>
                }
              }
            </select>
          </div>
          @if (showTeamSelect()) {
            <div class="w-56 space-y-1">
              <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Teamzugehörigkeit</label>
              <select formControlName="teamId"
                class="w-full px-3 py-2 rounded-lg border bg-stone-50 text-sm text-gray-900 outline-none focus:border-teal-500 border-gray-200">
                @if (!isManagerOnly()) {
                  <option [ngValue]="null">Kein Team</option>
                }
                @for (team of teams(); track team.id) {
                  <option [ngValue]="team.id">{{ team.name }}</option>
                }
              </select>
            </div>
          }
          @if (managerHasNoTeams()) {
            <div class="w-full text-sm px-4 py-2 rounded-lg" style="background: #fffbeb; color: #92400e; border: 1px solid #fde68a">
              Sie verwalten aktuell kein Team. Einladungen sind erst möglich, wenn Ihnen ein Team zugeordnet ist.
            </div>
          }
          @if (managerDisabledByTeamLayer()) {
            <div class="w-full text-sm px-4 py-2 rounded-lg" style="background: #fffbeb; color: #92400e; border: 1px solid #fde68a">
              Manager-Einladungen sind nur bei aktivierter Team-Ebene möglich.
            </div>
          }
          <button type="submit" [disabled]="!canSubmit()"
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
  private authStore = inject(AuthStore);
  private notifications = inject(NotificationService);

  invitations = signal<InvitationListItem[]>([]);
  teams = signal<CompanyTeamOption[]>([]);
  loading = signal(true);
  teamsLoading = signal(false);
  inviting = signal(false);
  inviteError = signal<string | null>(null);
  inviteSuccess = signal(false);
  lastToken = signal<string | null>(null);
  private teamsLoaded = false;

  inviteForm = this.fb.group({
    email: ['', [Validators.required, Validators.email]],
    role: ['EMPLOYEE', [Validators.required]],
    teamId: [null as number | null],
  });

  ngOnInit() {
    if (this.isManagerOnly()) {
      this.inviteForm.controls.role.setValue(Role.EMPLOYEE);
      this.inviteForm.controls.role.disable();
    }

    this.inviteForm.controls.role.valueChanges.subscribe(role => {
      if (!this.isManagerOnly()) {
        this.inviteForm.controls.teamId.setValue(null);
      }

      if (role && this.teamLayerEnabled()) {
        this.loadTeams();
      }
    });

    if (this.teamLayerEnabled()) {
      this.loadTeams();
    }
    this.loadInvitations();
  }

  loadInvitations() {
    this.api.get<{ data: any[] }>('/company/invitations').subscribe({
      next: (res) => { this.invitations.set(res.data); this.loading.set(false); },
      error: () => this.loading.set(false),
    });
  }

  onInvite() {
    if (!this.canSubmit()) {
      this.inviteForm.markAllAsTouched();
      if (this.isManagerOnly()) {
        this.inviteError.set(this.managerDisabledByTeamLayer()
          ? 'Manager-Einladungen sind nur bei aktivierter Team-Ebene möglich.'
          : this.managerHasNoTeams()
          ? 'Sie verwalten aktuell kein Team und können keine Einladungen erstellen.'
          : 'Bitte wählen Sie ein verwaltetes Team aus.');
      }
      return;
    }

    this.inviting.set(true);
    this.inviteError.set(null);
    this.inviteSuccess.set(false);

    this.api.post<{ data: any }>('/company/invitations', this.invitationPayload()).subscribe({
      next: (res) => {
        this.inviteSuccess.set(true);
        this.lastToken.set(res.data.invite_token);
        this.inviting.set(false);
        this.inviteForm.reset({ role: Role.EMPLOYEE, teamId: this.defaultTeamId() });
        if (this.isManagerOnly()) {
          this.inviteForm.controls.role.disable();
        }
        this.notifications.success('Einladung wurde gespeichert.');
        this.loadInvitations();
      },
      error: (err) => {
        const message = this.validationMessage(err);
        this.inviteError.set(message);
        this.notifications.error(message);
        this.inviting.set(false);
      }
    });
  }

  revoke(id: number) {
    this.api.delete(`/company/invitations/${id}`).subscribe({
      next: () => {
        this.notifications.success('Einladung wurde widerrufen.');
        this.loadInvitations();
      },
      error: () => this.notifications.error('Einladung konnte nicht widerrufen werden.'),
    });
  }

  showTeamSelect() {
    if (!this.teamLayerEnabled()) {
      return false;
    }

    if (this.isManagerOnly()) {
      return this.teamsLoading() || this.teams().length > 1;
    }

    return this.teamsLoading() || this.teams().length > 0;
  }

  canSubmit() {
    if (this.inviteForm.invalid || this.inviting()) return false;
    if (!this.teamLayerEnabled() && this.isManagerOnly()) return false;
    if (this.teamsLoading()) return false;
    return !this.isManagerOnly() || this.inviteForm.controls.teamId.value !== null;
  }

  managerHasNoTeams() {
    return this.teamLayerEnabled() && this.isManagerOnly() && this.teamsLoaded && this.teams().length === 0;
  }

  managerDisabledByTeamLayer() {
    return this.isManagerOnly() && !this.teamLayerEnabled();
  }

  isManagerOnly() {
    const roles = this.authStore.roles();
    return roles.includes(Role.COMPANY_MANAGER) && !roles.some(role => [Role.COMPANY_ADMIN, Role.COMPANY_OWNER].includes(role as Role));
  }

  teamLayerEnabled() {
    return this.authStore.teamLayerEnabled();
  }

  private loadTeams() {
    if (!this.teamLayerEnabled() || this.teamsLoaded || this.teamsLoading()) return;

    this.teamsLoading.set(true);
    this.api.get<{ data: CompanyTeamOption[] }>('/company/teams').subscribe({
      next: res => {
        this.teams.set(res.data ?? []);
        this.teamsLoaded = true;
        this.teamsLoading.set(false);
        this.applyManagerTeamDefault();
      },
      error: () => {
        this.teamsLoading.set(false);
      },
    });
  }

  private applyManagerTeamDefault() {
    if (this.isManagerOnly() && this.teams().length === 1) {
      this.inviteForm.controls.teamId.setValue(this.teams()[0].id);
    }
  }

  private defaultTeamId() {
    return this.isManagerOnly() && this.teams().length === 1 ? this.teams()[0].id : null;
  }

  private invitationPayload(): CreateInvitationPayload {
    const raw = this.inviteForm.getRawValue();
    const role = this.isManagerOnly() ? Role.EMPLOYEE : raw.role ?? Role.EMPLOYEE;
    const payload: CreateInvitationPayload = {
      email: raw.email ?? '',
      role,
    };

    if (this.teamLayerEnabled() && raw.teamId != null) {
      payload.teamId = raw.teamId;
    }

    return payload;
  }

  private validationMessage(err: any) {
    const errors = err.error?.errors;
    if (errors) return Object.values(errors).flat().join(' ');
    return err.error?.error?.message || err.error?.message || 'Einladung fehlgeschlagen.';
  }
}
