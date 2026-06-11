import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { Role } from '../../../../core/models/auth.models';
import { AuthStore } from '../../../../core/store/auth.store';
import { NotificationService } from '../../../../shared/notifications/notification.service';
import { CompanyMeasuresService, MeasureCheckinTokenResponse } from '../../services/company-measures.service';
import { CompanyTeamsService } from '../../services/company-teams.service';

interface MeasureParticipationSummary {
  measureId: number;
  isAboveThreshold: boolean;
  eligibleCount: number | null;
  participantCount: number | null;
  participationRate: number | null;
  suppressionReason: string | null;
  teamBreakdown: null;
}

interface CompanyMeasure {
  id: number;
  title: string;
  category: string;
  description: string;
  status: string;
  deliveryType?: string | null;
  executionType?: string | null;
  verificationRequirement?: 'SELF_REPORT' | 'QR_CODE' | null;
  startsAt?: string | null;
  endsAt?: string | null;
  durationMinutes?: number | null;
  instructions?: string | null;
  locationName?: string | null;
  locationAddress?: string | null;
  capacity?: number | null;
  pointsOverride?: number | null;
  team?: { name: string } | null;
}

interface MeasureCheckinLink extends MeasureCheckinTokenResponse {
  checkinUrl: string;
}

const SCHEDULED_EXECUTION_TYPES = ['EVENT_PARTICIPATION', 'GUIDED_SESSION'];

function dateRangeValidator(control: AbstractControl): ValidationErrors | null {
  const startsAt = control.get('startsAt')?.value;
  const endsAt = control.get('endsAt')?.value;

  if (!startsAt || !endsAt) return null;

  return new Date(endsAt).getTime() > new Date(startsAt).getTime()
    ? null
    : { dateRange: true };
}

@Component({
  selector: 'app-company-measures',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  template: `
    <div class="space-y-6">
      <div class="flex items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold text-gray-900" style="font-family: 'Fraunces', Georgia, serif">Maßnahmen</h1>
        @if (!managerDisabledByTeamLayer()) {
          <button type="button" (click)="toggleForm()"
                  class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700">
            {{ showForm() ? 'Schließen' : 'Maßnahme hinzufügen' }}
          </button>
        }
      </div>

      @if (managerDisabledByTeamLayer()) {
        <div class="rounded-xl border border-amber-100 bg-amber-50 p-5 text-amber-800">
          <div class="font-semibold">Maßnahmen auf Team-Ebene nicht verfügbar</div>
          <p class="text-sm mt-1">Manager können Maßnahmen nur bei aktivierter Team-Ebene erstellen.</p>
        </div>
      }

      @if (showForm() && !managerDisabledByTeamLayer()) {
        <form [formGroup]="measureForm" (ngSubmit)="submit()"
              class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
          <div class="flex items-center justify-between gap-4">
            <h2 class="text-base font-semibold text-gray-900">
              {{ editingMeasureId() ? 'Maßnahme bearbeiten' : 'Neue Maßnahme' }}
            </h2>
            @if (editingMeasureId()) {
              <button type="button" (click)="resetForm()"
                      class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                Bearbeitung beenden
              </button>
            }
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <label class="block">
              <span class="text-sm font-medium text-gray-700">Titel <span class="text-red-500">*</span></span>
              <input formControlName="title"
                     class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500"
                     [class.border-red-300]="invalid('title')"/>
              @if (fieldMessage('title'); as message) {
                <span class="text-xs text-red-600">{{ message }}</span>
              }
            </label>
            <label class="block">
              <span class="text-sm font-medium text-gray-700">Kategorie <span class="text-red-500">*</span></span>
              <select formControlName="category"
                      class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500"
                      [class.border-red-300]="invalid('category')">
                <option value="">Kategorie wählen</option>
                <option value="workshop">Workshop</option>
                <option value="flexibility">Flexibilität</option>
                <option value="sport">Sport</option>
                <option value="mental">Mental</option>
                <option value="nutrition">Ernährung</option>
              </select>
              @if (fieldMessage('category'); as message) {
                <span class="text-xs text-red-600">{{ message }}</span>
              }
            </label>
          </div>

          <label class="block">
            <span class="text-sm font-medium text-gray-700">Beschreibung <span class="text-red-500">*</span></span>
            <textarea formControlName="description" rows="3"
                      class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500"
                      [class.border-red-300]="invalid('description')"></textarea>
            @if (fieldMessage('description'); as message) {
              <span class="text-xs text-red-600">{{ message }}</span>
            }
          </label>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @if (teamLayerEnabled()) {
              <label class="block">
                <span class="text-sm font-medium text-gray-700">Team</span>
                <select formControlName="teamId"
                        class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500">
                  <option [ngValue]="null">Alle Teams</option>
                  @for (team of teams(); track team.id) {
                    <option [ngValue]="team.id">{{ team.name }}</option>
                  }
                </select>
              </label>
            }
            <label class="block">
              <span class="text-sm font-medium text-gray-700">Status</span>
              @if (editingMeasureId()) {
                <div class="mt-1 w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                  {{ statusLabel(measureForm.get('status')?.value) }}
                </div>
                <span class="text-xs text-gray-500">Der Status kann beim Bearbeiten nicht geändert werden.</span>
              } @else {
                <select formControlName="status"
                        class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500">
                  <option value="ACTIVE">Aktiv</option>
                  <option value="SUGGESTED">Vorgeschlagen</option>
                </select>
              }
              @if (fieldMessage('status'); as message) {
                <span class="text-xs text-red-600">{{ message }}</span>
              }
            </label>
          </div>

          <div class="space-y-4 rounded-lg border border-gray-100 bg-gray-50 p-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <label class="block">
                <span class="text-sm font-medium text-gray-700">Durchführung</span>
                <select formControlName="deliveryType"
                        class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500">
                  <option value="ONSITE">Vor Ort</option>
                  <option value="REMOTE">Remote</option>
                  <option value="HYBRID">Hybrid</option>
                </select>
              </label>
              <label class="block">
                <span class="text-sm font-medium text-gray-700">Ablauf</span>
                <select formControlName="executionType"
                        class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500">
                  <option value="EVENT_PARTICIPATION">Event-Teilnahme</option>
                  <option value="INFORMATION_ONLY">Information</option>
                  <option value="GUIDED_SESSION">Geführte Session</option>
                  <option value="SELF_REPORTED_ACTION">Selbst gemeldet</option>
                  <option value="CHALLENGE">Challenge</option>
                </select>
              </label>
              <label class="block">
                <span class="text-sm font-medium text-gray-700">Nachweis</span>
                <select formControlName="verificationRequirement"
                        class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500"
                        [class.border-red-300]="invalid('verificationRequirement')">
                  <option value="SELF_REPORT">Selbstmeldung</option>
                  <option value="QR_CODE">QR-Check-in</option>
                </select>
                @if (fieldMessage('verificationRequirement'); as message) {
                  <span class="text-xs text-red-600">{{ message }}</span>
                }
              </label>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
              <label class="block">
                <span class="text-sm font-medium text-gray-700">Start</span>
                <input type="datetime-local" formControlName="startsAt"
                       class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500"/>
              </label>
              <label class="block">
                <span class="text-sm font-medium text-gray-700">Ende</span>
                <input type="datetime-local" formControlName="endsAt"
                       class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500"
                       [class.border-red-300]="invalid('endsAt') || dateRangeInvalid()"/>
                @if (fieldMessage('endsAt'); as message) {
                  <span class="text-xs text-red-600">{{ message }}</span>
                } @else if (dateRangeInvalid()) {
                  <span class="text-xs text-red-600">Ende muss nach dem Start liegen.</span>
                }
              </label>
              <label class="block">
                <span class="text-sm font-medium text-gray-700">Dauer (Min.)</span>
                @if (durationPreviewMinutes() !== null) {
                  <div class="mt-1 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-700">
                    {{ durationPreviewMinutes() }} Min.
                  </div>
                  <span class="text-xs text-gray-500">Wird aus Start und Ende berechnet.</span>
                } @else {
                  <input type="number" min="1" formControlName="durationMinutes"
                         class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500"
                         [class.border-red-300]="invalid('durationMinutes')"/>
                  @if (fieldMessage('durationMinutes'); as message) {
                    <span class="text-xs text-red-600">{{ message }}</span>
                  }
                }
              </label>
              <label class="block">
                <span class="text-sm font-medium text-gray-700">Kapazität</span>
                <input type="number" min="1" formControlName="capacity"
                       class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500"
                       [class.border-red-300]="invalid('capacity')"/>
                @if (fieldMessage('capacity'); as message) {
                  <span class="text-xs text-red-600">{{ message }}</span>
                }
              </label>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <label class="block">
                <span class="text-sm font-medium text-gray-700">Ort</span>
                <input formControlName="locationName"
                       class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500"/>
              </label>
            </div>

            <label class="block">
              <span class="text-sm font-medium text-gray-700">Adresse</span>
              <input formControlName="locationAddress"
                     class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500"/>
            </label>

            <label class="block">
              <span class="text-sm font-medium text-gray-700">Hinweise</span>
              <textarea formControlName="instructions" rows="2"
                        class="mt-1 w-full rounded-lg border px-3 py-2 text-sm outline-none focus:border-teal-500"></textarea>
            </label>
          </div>

          @if (formError()) {
            <div class="rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-sm text-red-700">{{ formError() }}
            </div>
          }

          <div class="flex justify-end">
            <button type="submit" [disabled]="saving()"
                    class="px-4 py-2 rounded-lg bg-teal-600 text-white text-sm font-semibold hover:bg-teal-700 disabled:bg-gray-300">
              {{ saving() ? 'Speichern…' : 'Maßnahme speichern' }}
            </button>
          </div>
        </form>
      }

      @if (!managerDisabledByTeamLayer()) {
        @if (loading()) {
          <div class="flex justify-center py-12">
            <div class="w-8 h-8 border-4 border-teal-500/30 border-t-teal-500 rounded-full animate-spin"></div>
          </div>
        } @else if (measures().length === 0) {
          <div class="bg-white rounded-xl border border-gray-200 p-12 text-center"><p class="text-gray-500 text-sm">Noch
            keine Maßnahmen vorhanden.</p></div>
        } @else {
          <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
              <thead>
              <tr class="bg-gray-50 border-b border-gray-100">
                <th class="text-left px-4 py-3 text-xs uppercase text-gray-500">Titel</th>
                <th class="text-left px-4 py-3 text-xs uppercase text-gray-500">Kategorie</th>
                @if (teamLayerEnabled()) {
                  <th class="text-left px-4 py-3 text-xs uppercase text-gray-500">Team</th>
                }
                <th class="text-left px-4 py-3 text-xs uppercase text-gray-500">Details</th>
                <th class="text-left px-4 py-3 text-xs uppercase text-gray-500">Teilnahme</th>
                <th class="text-left px-4 py-3 text-xs uppercase text-gray-500">Status</th>
                <th class="text-left px-4 py-3 text-xs uppercase text-gray-500">Check-in</th>
                <th class="text-left px-4 py-3 text-xs uppercase text-gray-500">Aktion</th>
              </tr>
              </thead>
              <tbody>
                @for (measure of measures(); track measure.id) {
                  <tr class="border-b border-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-900">{{ measure.title }}</td>
                    <td class="px-4 py-3 text-gray-500">{{ measure.category }}</td>
                    @if (teamLayerEnabled()) {
                      <td class="px-4 py-3 text-gray-500">{{ measure.team?.name || 'Alle Teams' }}</td>
                    }
                    <td class="px-4 py-3 text-gray-500">
                      <div class="space-y-1">
                        <div>{{ label(measure.deliveryType) }} / {{ label(measure.executionType) }}</div>
                        @if (measure.startsAt || measure.endsAt) {
                          <div class="text-xs">{{ scheduleLabel(measure) }}</div>
                        }
                        @if (measure.locationName) {
                          <div class="text-xs">{{ measure.locationName }}</div>
                        }
                      </div>
                    </td>
                    <td class="px-4 py-3 text-gray-500">
                      @if (summaryFor(measure.id); as summary) {
                        @if (summary.isAboveThreshold) {
                          <div class="space-y-0.5">
                            <div class="font-medium text-gray-900">{{ participationRateLabel(summary) }}</div>
                            <div class="text-xs text-gray-500">
                              {{ participationCountLabel(summary) }}
                            </div>
                          </div>
                        } @else {
                          <span class="text-xs text-gray-500">Mindestgruppengröße nicht erreicht</span>
                        }
                      } @else {
                        <span class="text-xs text-gray-400">Nicht verfügbar</span>
                      }
                    </td>
                    <td class="px-4 py-3"><span
                      class="px-2 py-0.5 rounded-full text-xs bg-teal-50 text-teal-700">{{ measure.status }}</span></td>
                    <td class="px-4 py-3">
                      @if (measure.verificationRequirement !== 'QR_CODE') {
                        <span class="text-xs text-gray-400">Nicht erforderlich</span>
                      } @else if (checkinLinkFor(measure.id); as link) {
                        <div class="space-y-2">
                          <input readonly [value]="link.checkinUrl"
                                 class="w-72 max-w-full rounded-lg border border-gray-200 px-2 py-1 text-xs text-gray-600"/>
                          <div class="text-xs text-gray-500">Dieser Link wird nur direkt nach Erstellung oder Erneuerung angezeigt.</div>
                          <div class="flex gap-2">
                            <button type="button" (click)="copyCheckinLink(link.checkinUrl)"
                                    class="rounded-lg border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                              Kopieren
                            </button>
                            <button type="button" (click)="rotateCheckinLink(measure)"
                                    [disabled]="isRotatingCheckin(measure.id)"
                                    class="rounded-lg border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:text-gray-300">
                              Erneuern
                            </button>
                          </div>
                        </div>
                      } @else {
                        <button type="button" (click)="rotateCheckinLink(measure)"
                                [disabled]="measure.status !== 'ACTIVE' || isRotatingCheckin(measure.id)"
                                class="rounded-lg bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-700 disabled:bg-gray-300">
                          {{ isRotatingCheckin(measure.id) ? 'Wird erstellt…' : 'Link erstellen' }}
                        </button>
                      }
                    </td>
                    <td class="px-4 py-3">
                      @if (isEditableMeasure(measure)) {
                        <button type="button" (click)="editMeasure(measure)"
                                class="rounded-lg border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                          Bearbeiten
                        </button>
                      } @else {
                        <span class="text-xs text-gray-400">Nicht bearbeitbar</span>
                      }
                    </td>
                  </tr>
                }
              </tbody>
            </table>
          </div>
        }
      }
    </div>
  `
})
export class CompanyMeasuresComponent implements OnInit {
  private companyMeasuresService = inject(CompanyMeasuresService);
  private companyTeamsService = inject(CompanyTeamsService);
  private authStore = inject(AuthStore);
  private fb = inject(FormBuilder);
  private notifications = inject(NotificationService);

  measures = signal<CompanyMeasure[]>([]);
  teams = signal<any[]>([]);
  loading = signal(true);
  saving = signal(false);
  showForm = signal(false);
  formError = signal<string | null>(null);
  fieldErrors = signal<Record<string, string>>({});
  editingMeasureId = signal<number | null>(null);
  editingHadCompleteScheduledWindow = signal(false);
  participationSummaries = signal<Record<number, MeasureParticipationSummary | null>>({});
  checkinLinks = signal<Record<number, MeasureCheckinLink>>({});
  rotatingCheckinIds = signal<Set<number>>(new Set());

  measureForm = this.fb.group({
    title: ['', [Validators.required, Validators.minLength(3), Validators.maxLength(100)]],
    category: ['', [Validators.required]],
    description: ['', [Validators.required, Validators.minLength(10), Validators.maxLength(500)]],
    teamId: [null as number | null],
    status: ['ACTIVE', [Validators.required]],
    deliveryType: ['ONSITE', [Validators.required]],
    executionType: ['EVENT_PARTICIPATION', [Validators.required]],
    verificationRequirement: ['SELF_REPORT', [Validators.required]],
    startsAt: [null as string | null],
    endsAt: [null as string | null],
    durationMinutes: [null as number | null, [Validators.min(1)]],
    instructions: [null as string | null, [Validators.maxLength(2000)]],
    locationName: [null as string | null, [Validators.maxLength(255)]],
    locationAddress: [null as string | null, [Validators.maxLength(1000)]],
    capacity: [null as number | null, [Validators.min(1)]],
  }, { validators: dateRangeValidator });

  ngOnInit() {
    if (this.managerDisabledByTeamLayer()) {
      this.loading.set(false);
      return;
    }

    this.loadMeasures();
    if (this.teamLayerEnabled()) {
      this.companyTeamsService.listTeams().subscribe({
        next: res => this.teams.set(res.data ?? []),
      });
    }
  }

  teamLayerEnabled() {
    return this.authStore.teamLayerEnabled();
  }

  managerDisabledByTeamLayer() {
    return this.isManagerOnly() && !this.teamLayerEnabled();
  }

  toggleForm() {
    if (this.managerDisabledByTeamLayer()) {
      this.notifications.error('Manager können Maßnahmen nur bei aktivierter Team-Ebene erstellen.');
      return;
    }

    const next = !this.showForm();
    if (!next) {
      this.resetForm();
    }
    this.showForm.set(next);
    this.formError.set(null);
  }

  invalid(control: string) {
    const field = this.measureForm.get(control);
    return !!this.fieldErrors()[control] || (!!field && field.invalid && (field.dirty || field.touched));
  }

  fieldMessage(control: string) {
    const backendMessage = this.fieldErrors()[control];
    if (backendMessage) return backendMessage;

    const field = this.measureForm.get(control);
    if (!field || !(field.dirty || field.touched) || !field.errors) return null;

    if (field.errors['required']) return 'Dieses Feld ist erforderlich.';
    if (field.errors['email']) return 'Bitte eine gültige E-Mail-Adresse eingeben.';
    if (field.errors['minlength']) return `Mindestens ${field.errors['minlength'].requiredLength} Zeichen erforderlich.`;
    if (field.errors['maxlength']) return `Maximal ${field.errors['maxlength'].requiredLength} Zeichen erlaubt.`;
    if (field.errors['min']) return `Muss mindestens ${field.errors['min'].min} sein.`;

    return 'Eingabe ist ungültig.';
  }

  dateRangeInvalid() {
    return this.measureForm.hasError('dateRange') && (
      !!this.measureForm.get('startsAt')?.touched || !!this.measureForm.get('endsAt')?.touched
    );
  }

  submit() {
    this.formError.set(null);
    this.fieldErrors.set({});
    if (this.managerDisabledByTeamLayer()) {
      this.formError.set('Manager können Maßnahmen nur bei aktivierter Team-Ebene speichern.');
      return;
    }

    if (this.measureForm.invalid) {
      this.measureForm.markAllAsTouched();
      return;
    }

    this.saving.set(true);
    const editingId = this.editingMeasureId();
    const request$ = editingId
      ? this.companyMeasuresService.updateMeasure(editingId, this.payload())
      : this.companyMeasuresService.createMeasure(this.payload());

    request$.subscribe({
      next: res => {
        if (editingId) {
          this.measures.update(measures => measures.map(measure => measure.id === editingId ? res.data : measure));
        } else {
          this.measures.update(measures => [res.data, ...measures]);
        }
        if (res.data?.id) {
          this.loadParticipationSummary(res.data.id);
        }
        this.resetForm();
        this.showForm.set(false);
        this.notifications.success(editingId ? 'Maßnahme wurde aktualisiert.' : 'Maßnahme wurde gespeichert.');
        this.saving.set(false);
      },
      error: err => {
        const message = this.applyValidationErrors(err);
        this.formError.set(message);
        this.notifications.error(message);
        this.saving.set(false);
      }
    });
  }

  private loadMeasures() {
    this.companyMeasuresService.listMeasures().subscribe({
      next: res => {
        const measures = res.data ?? [];
        this.measures.set(measures);
        this.participationSummaries.set({});
        measures.forEach(measure => this.loadParticipationSummary(measure.id));
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  private loadParticipationSummary(measureId: number) {
    this.companyMeasuresService.getParticipationSummary(measureId).subscribe({
      next: res => {
        this.participationSummaries.update(summaries => ({
          ...summaries,
          [measureId]: res.data ?? null,
        }));
      },
      error: () => {
        this.participationSummaries.update(summaries => ({
          ...summaries,
          [measureId]: null,
        }));
      },
    });
  }

  summaryFor(measureId: number) {
    return this.participationSummaries()[measureId] ?? null;
  }

  participationRateLabel(summary: MeasureParticipationSummary) {
    if (summary.participationRate === null || summary.participationRate === undefined) {
      return 'Teilnahmequote nicht verfügbar';
    }

    return `${summary.participationRate}% Teilnahmequote`;
  }

  participationCountLabel(summary: MeasureParticipationSummary) {
    if (summary.participantCount === null || summary.participantCount === undefined || summary.eligibleCount === null || summary.eligibleCount === undefined) {
      return 'Teilnahmen nicht verfügbar';
    }

    return `${summary.participantCount} von ${summary.eligibleCount} Berechtigten`;
  }

  checkinLinkFor(measureId: number) {
    return this.checkinLinks()[measureId] ?? null;
  }

  isRotatingCheckin(measureId: number) {
    return this.rotatingCheckinIds().has(measureId);
  }

  isEditableMeasure(measure: CompanyMeasure) {
    return ['SUGGESTED', 'ACTIVE'].includes(measure.status);
  }

  editMeasure(measure: CompanyMeasure) {
    if (!this.isEditableMeasure(measure)) {
      return;
    }

    this.editingMeasureId.set(measure.id);
    this.editingHadCompleteScheduledWindow.set(
      !!measure.startsAt
      && !!measure.endsAt
      && !!measure.executionType
      && SCHEDULED_EXECUTION_TYPES.includes(measure.executionType)
    );
    this.fieldErrors.set({});
    this.formError.set(null);
    this.measureForm.reset({
      title: measure.title,
      category: measure.category,
      description: measure.description,
      teamId: null,
      status: measure.status || 'ACTIVE',
      deliveryType: measure.deliveryType || 'ONSITE',
      executionType: measure.executionType || 'EVENT_PARTICIPATION',
      verificationRequirement: measure.verificationRequirement || 'SELF_REPORT',
      startsAt: this.toDateTimeLocal(measure.startsAt),
      endsAt: this.toDateTimeLocal(measure.endsAt),
      durationMinutes: measure.durationMinutes ?? null,
      instructions: measure.instructions ?? null,
      locationName: measure.locationName ?? null,
      locationAddress: measure.locationAddress ?? null,
      capacity: measure.capacity ?? null,
    });
    this.showForm.set(true);
  }

  durationPreviewMinutes() {
    const executionType = this.measureForm.get('executionType')?.value;
    if (!executionType || !SCHEDULED_EXECUTION_TYPES.includes(executionType)) return null;

    const startsAt = this.measureForm.get('startsAt')?.value;
    const endsAt = this.measureForm.get('endsAt')?.value;
    if (!startsAt || !endsAt || this.measureForm.hasError('dateRange')) return null;

    const minutes = Math.round((new Date(endsAt).getTime() - new Date(startsAt).getTime()) / 60000);
    return minutes > 0 ? minutes : null;
  }

  rotateCheckinLink(measure: CompanyMeasure) {
    if (measure.verificationRequirement !== 'QR_CODE') {
      this.notifications.error('Check-in-Links sind nur für QR-Maßnahmen verfügbar.');
      return;
    }

    if (this.checkinLinkFor(measure.id) && !window.confirm('Bestehenden Check-in-Link ersetzen? Der bisherige Link wird ungültig.')) {
      return;
    }

    this.setRotatingCheckin(measure.id, true);
    this.companyMeasuresService.generateMeasureCheckinToken(measure.id).subscribe({
      next: res => {
        const link = res.data;
        this.checkinLinks.update(links => ({
          ...links,
          [measure.id]: {
            ...link,
            checkinUrl: this.browserCheckinUrl(link.checkinPath),
          },
        }));
        this.notifications.success('Check-in-Link wurde erstellt.');
        this.setRotatingCheckin(measure.id, false);
      },
      error: err => {
        const code = err.error?.error?.code;
        const message = code === 'MEASURE_NOT_ACTIVE'
          ? 'Nur aktive Maßnahmen können Check-in-Links erhalten.'
          : code === 'MEASURE_DOES_NOT_ALLOW_QR_CHECKIN'
            ? 'Check-in-Links sind nur für QR-Maßnahmen verfügbar.'
            : 'Check-in-Link konnte nicht erstellt werden.';
        this.notifications.error(message);
        this.setRotatingCheckin(measure.id, false);
      },
    });
  }

  copyCheckinLink(url: string) {
    if (!navigator.clipboard) {
      this.notifications.error('Link konnte nicht kopiert werden.');
      return;
    }

    navigator.clipboard.writeText(url)
      .then(() => this.notifications.success('Check-in-Link wurde kopiert.'))
      .catch(() => this.notifications.error('Link konnte nicht kopiert werden.'));
  }

  label(value: string | null | undefined) {
    return value ? value.toLowerCase().replace(/_/g, ' ') : '-';
  }

  statusLabel(value: string | null | undefined) {
    switch (value) {
      case 'ACTIVE': return 'Aktiv';
      case 'SUGGESTED': return 'Vorgeschlagen';
      case 'COMPLETED': return 'Abgeschlossen';
      case 'DISMISSED': return 'Verworfen';
      default: return value || '-';
    }
  }

  scheduleLabel(measure: CompanyMeasure) {
    const startsAt = measure.startsAt ? new Date(measure.startsAt).toLocaleString('de-DE') : null;
    const endsAt = measure.endsAt ? new Date(measure.endsAt).toLocaleString('de-DE') : null;

    if (startsAt && endsAt) return `${startsAt} - ${endsAt}`;
    return startsAt ?? endsAt ?? '';
  }

  private payload() {
    const { teamId, ...rawPayload } = this.measureForm.value;
    const payload = Object.fromEntries(
      Object.entries(rawPayload).map(([key, value]) => [key, value === '' ? null : value])
    );

    // datetime-local values are timezone-less local wall time; the API
    // contract expects explicit ISO date-time strings with timezone/UTC.
    payload['startsAt'] = this.toIsoDateTime(rawPayload.startsAt);
    payload['endsAt'] = this.toIsoDateTime(rawPayload.endsAt);

    const durationPreview = this.durationPreviewMinutes();
    if (durationPreview !== null) {
      payload['durationMinutes'] = durationPreview;
    } else if (
      this.editingMeasureId()
      && this.editingHadCompleteScheduledWindow()
      && SCHEDULED_EXECUTION_TYPES.includes(String(rawPayload.executionType))
      && (!rawPayload.startsAt || !rawPayload.endsAt)
    ) {
      payload['durationMinutes'] = null;
    }

    const editingId = this.editingMeasureId();
    if (editingId) {
      delete payload['status'];
      return payload;
    }

    return this.teamLayerEnabled() ? { ...payload, teamId } : payload;
  }

  resetForm() {
    this.editingMeasureId.set(null);
    this.editingHadCompleteScheduledWindow.set(false);
    this.fieldErrors.set({});
    this.formError.set(null);
    this.measureForm.reset({
      title: '',
      category: '',
      description: '',
      teamId: null,
      status: 'ACTIVE',
      deliveryType: 'ONSITE',
      executionType: 'EVENT_PARTICIPATION',
      verificationRequirement: 'SELF_REPORT',
      startsAt: null,
      endsAt: null,
      durationMinutes: null,
      instructions: null,
      locationName: null,
      locationAddress: null,
      capacity: null,
    });
  }

  private isManagerOnly() {
    const roles = this.authStore.roles();
    return roles.includes(Role.COMPANY_MANAGER) && !roles.some(role => [Role.COMPANY_ADMIN, Role.COMPANY_OWNER].includes(role as Role));
  }

  private setRotatingCheckin(measureId: number, loading: boolean) {
    this.rotatingCheckinIds.update(ids => {
      const next = new Set(ids);
      if (loading) {
        next.add(measureId);
      } else {
        next.delete(measureId);
      }
      return next;
    });
  }

  private browserCheckinUrl(path: string) {
    return `${window.location.origin}${path}`;
  }

  private applyValidationErrors(err: any) {
    const errors = err.error?.errors as Record<string, string[]> | undefined;
    if (errors) {
      const fieldErrors = Object.fromEntries(
        Object.entries(errors).map(([key, messages]) => [key, messages[0]])
      );
      this.fieldErrors.set(fieldErrors);
      return Object.values(errors).flat().join(' ');
    }
    return err.error?.message || 'Maßnahme konnte nicht gespeichert werden.';
  }

  // datetime-local inputs represent local wall time, so the prefill must use
  // local date parts; UTC formatting via toISOString() would shift the shown
  // time by the timezone offset.
  private toDateTimeLocal(value: string | null | undefined) {
    if (!value) return null;
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;
    const pad = (part: number) => String(part).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
  }

  private toIsoDateTime(value: unknown) {
    if (typeof value !== 'string' || value === '') return null;
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? null : date.toISOString();
  }
}
