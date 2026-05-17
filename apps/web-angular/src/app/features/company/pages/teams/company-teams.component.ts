import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ApiClient } from '../../../../core/services/api-client.service';

@Component({
  selector: 'app-company-teams',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  template: `
    <div class="space-y-6">
      <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">Teamübersicht</h1>
        <button type="button" (click)="toggleForm()" class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700">
          {{ showForm() ? 'Schließen' : 'Team hinzufügen' }}
        </button>
      </div>

      @if (showForm()) {
        <form [formGroup]="teamForm" (ngSubmit)="submit()" class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <label class="block">
              <span class="text-sm font-medium text-gray-700">Name <span class="text-red-500">*</span></span>
              <input formControlName="name" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500" [class.border-red-300]="invalid('name')" />
              @if (invalid('name')) { <span class="text-xs text-red-600">Mindestens 2 Zeichen erforderlich.</span> }
            </label>
            <label class="block">
              <span class="text-sm font-medium text-gray-700">Farbe</span>
              <input type="color" formControlName="color" class="mt-1 h-10 w-full rounded-lg border border-gray-200 px-2 py-1" />
            </label>
          </div>

          <label class="block">
            <span class="text-sm font-medium text-gray-700">Beschreibung</span>
            <textarea formControlName="description" rows="3" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500"></textarea>
          </label>

          <label class="block">
            <span class="text-sm font-medium text-gray-700">Manager</span>
            <select formControlName="managerId" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500">
              <option [ngValue]="null">Kein Manager</option>
              @for (user of managerOptions(); track user.id) {
                <option [ngValue]="user.id">{{ user.name }} ({{ user.email }})</option>
              }
            </select>
          </label>

          @if (formError()) {
            <div class="rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700">{{ formError() }}</div>
          }

          <div class="flex justify-end">
            <button type="submit" [disabled]="saving()" class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700 disabled:bg-gray-300">
              {{ saving() ? 'Speichern…' : 'Team speichern' }}
            </button>
          </div>
        </form>
      }

      @if (loading()) {
        <div class="flex justify-center py-12"><div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin"></div></div>
      } @else if (teams().length === 0) {
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center"><p class="text-gray-500 text-sm">Noch keine Teams vorhanden.</p></div>
      } @else {
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
          @for (team of teams(); track team.id) {
            <div class="bg-white rounded-xl border border-gray-200 p-5">
              <div class="flex items-center gap-3">
                <span class="w-3 h-3 rounded-full" [style.background]="team.color || '#14b8a6'"></span>
                <h2 class="font-semibold text-gray-900">{{ team.name }}</h2>
              </div>
              <p class="text-sm text-gray-500 mt-2">{{ team.description || 'Kein Beschreibungstext' }}</p>
              <div class="grid grid-cols-2 gap-3 mt-4 text-sm">
                <div class="rounded-lg bg-stone-50 p-3"><div class="text-xs text-gray-400">Mitglieder</div><div class="font-semibold">{{ team.memberCount ?? 0 }}</div></div>
                <div class="rounded-lg bg-stone-50 p-3"><div class="text-xs text-gray-400">Manager</div><div class="font-semibold truncate">{{ team.manager?.name || '—' }}</div></div>
              </div>
            </div>
          }
        </div>
      }
    </div>
  `
})
export class CompanyTeamsComponent implements OnInit {
  private api = inject(ApiClient);
  private fb = inject(FormBuilder);

  teams = signal<any[]>([]);
  users = signal<any[]>([]);
  loading = signal(true);
  saving = signal(false);
  showForm = signal(false);
  formError = signal<string | null>(null);

  teamForm = this.fb.group({
    name: ['', [Validators.required, Validators.minLength(2), Validators.maxLength(100)]],
    description: ['', [Validators.maxLength(500)]],
    color: ['#14b8a6', [Validators.pattern(/^#[0-9A-Fa-f]{6}$/)]],
    managerId: [null as number | null],
  });

  ngOnInit() {
    this.loadTeams();
    this.api.get<{ data: any[] }>('/company/users').subscribe({
      next: res => this.users.set(res.data ?? []),
    });
  }

  managerOptions() {
    return this.users().filter(user => (user.roles ?? []).includes('COMPANY_MANAGER'));
  }

  toggleForm() {
    this.showForm.update(value => !value);
    this.formError.set(null);
  }

  invalid(control: string) {
    const field = this.teamForm.get(control);
    return !!field && field.invalid && (field.dirty || field.touched);
  }

  submit() {
    this.formError.set(null);
    if (this.teamForm.invalid) {
      this.teamForm.markAllAsTouched();
      return;
    }

    this.saving.set(true);
    this.api.post<{ data: any }>('/company/teams', this.teamForm.value).subscribe({
      next: res => {
        this.teams.update(teams => [res.data, ...teams]);
        this.teamForm.reset({ name: '', description: '', color: '#14b8a6', managerId: null });
        this.showForm.set(false);
        this.saving.set(false);
      },
      error: err => {
        this.formError.set(this.validationMessage(err));
        this.saving.set(false);
      }
    });
  }

  private loadTeams() {
    this.api.get<{ data: any[] }>('/company/teams').subscribe({
      next: res => { this.teams.set(res.data ?? []); this.loading.set(false); },
      error: () => this.loading.set(false),
    });
  }

  private validationMessage(err: any) {
    const errors = err.error?.errors;
    if (errors) return Object.values(errors).flat().join(' ');
    return err.error?.message || 'Team konnte nicht gespeichert werden.';
  }
}
