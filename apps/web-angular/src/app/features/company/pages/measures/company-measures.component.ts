import { Component, OnInit, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { Role } from '../../../../core/models/auth.models';
import { AuthStore } from '../../../../core/store/auth.store';
import { NotificationService } from '../../../../shared/notifications/notification.service';
import { CompanyMeasuresService, MeasureCheckinTokenResponse, MeasureExecution } from '../../services/company-measures.service';
import { CompanyTeamsService } from '../../services/company-teams.service';
import { MeasureImpactDialogComponent } from './measure-impact-dialog.component';

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
  completedAt?: string | null;
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
  imports: [CommonModule, ReactiveFormsModule, MeasureImpactDialogComponent],
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
          <div class="space-y-3">
            @for (measure of measures(); track measure.id) {
              <div class="bg-white overflow-hidden transition-colors" style="border-radius: 14px"
                   [style.border]="expandedMeasureId() === measure.id ? '1px solid #0f766e' : '1px solid #ece6d8'">
                <button type="button" (click)="toggleExpanded(measure)"
                        class="w-full flex items-center gap-3 text-left" style="padding: 16px 22px">
                  <div class="min-w-0 flex-1 flex items-center gap-3 flex-wrap">
                    <span class="text-sm font-medium text-gray-900">{{ measure.title }}</span>
                    <span class="text-[11px]" style="color: #6f7d76">{{ categoryLabel(measure.category) }}</span>
                    @if (teamLayerEnabled()) {
                      <span class="text-[11px]" style="color: #9aa39c">{{ measure.team?.name || 'Alle Teams' }}</span>
                    }
                    <span class="text-[11px] font-semibold rounded-full" style="padding: 3px 9px"
                          [style.background]="measure.status === 'ACTIVE' ? '#ecfaf7' : '#f1ede3'"
                          [style.color]="measure.status === 'ACTIVE' ? '#0f766e' : '#6f7d76'">
                      {{ statusLabel(measure.status) }}
                    </span>
                    <span class="text-[11px] font-semibold rounded-full" style="padding: 3px 9px"
                          [style.background]="derivedStatus(measure) === 'RUNNING' ? '#ecfaf7' : '#f1ede3'"
                          [style.color]="derivedStatus(measure) === 'RUNNING' ? '#0f766e' : '#6f7d76'">
                      {{ derivedStatusLabel(measure) }}
                    </span>
                  </div>
                  <div class="flex items-center gap-3 flex-shrink-0">
                    @if (summaryFor(measure.id); as summary) {
                      @if (summary.isAboveThreshold) {
                        <span class="text-xs font-semibold" style="color: #0f766e">{{ participationRateLabel(summary) }}</span>
                      } @else {
                        <span class="text-xs italic" style="color: #6f7d76">geschützt</span>
                      }
                    }
                    <span class="text-gray-400 text-xs">{{ expandedMeasureId() === measure.id ? '▾' : '▸' }}</span>
                  </div>
                </button>

                @if (expandedMeasureId() === measure.id) {
                  <div class="pb-5 pt-4" style="border-top: 1px solid #f1ede3; padding-left: 22px; padding-right: 22px">
                    <div class="flex justify-end mb-3">
                      @if (isEditableMeasure(measure)) {
                        <button type="button" (click)="editMeasure(measure)"
                                class="text-[11px] font-semibold text-gray-700 hover:bg-gray-50"
                                style="border: 1px solid #e5ded3; border-radius: 8px; padding: 5px 12px">
                          Bearbeiten
                        </button>
                      } @else {
                        <span class="text-[11px]" style="color: #9aa39c">Nicht bearbeitbar</span>
                      }
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                      <div class="space-y-2">
                        <div class="text-[11px] uppercase font-semibold" style="color: #6f7d76; letter-spacing: .04em">Durchführung</div>
                        <div class="text-sm text-gray-900">{{ deliveryLabel(measure.deliveryType) }} · {{ executionLabel(measure.executionType) }}</div>
                        @if (measure.startsAt || measure.endsAt) {
                          <div class="text-xs" style="color: #6f7d76">{{ scheduleLabel(measure) }}</div>
                        }
                        @if (measure.locationName) {
                          <div class="text-xs" style="color: #6f7d76">{{ measure.locationName }}</div>
                        }
                        @if (executionFor(measure.id); as execution) {
                          @if (execution.registeredCount !== null) {
                            <div class="text-xs" style="color: #6f7d76">
                              {{ execution.registeredCount }} angemeldet{{ execution.capacity ? ' / ' + execution.capacity + ' Plätze' : '' }}
                            </div>
                          } @else if (!execution.isAboveThreshold) {
                            <div class="text-xs italic" style="color: #6f7d76">Mindestgruppengröße nicht erreicht</div>
                          }
                          @if (execution.checkin.required) {
                            @if (execution.checkin.active) {
                              <div class="text-xs font-semibold" style="color: #0f766e">
                                QR-Check-in aktiv{{ execution.checkin.createdAt ? ' — erstellt am ' + formatDate(execution.checkin.createdAt) : '' }}
                              </div>
                            } @else {
                              <div class="text-xs" style="color: #6f7d76">Kein aktiver Check-in-Link</div>
                            }
                          } @else {
                            <div class="text-xs" style="color: #9aa39c">Kein Check-in erforderlich</div>
                          }
                        }
                        @if (measure.verificationRequirement === 'QR_CODE') {
                          @if (checkinLinkFor(measure.id); as link) {
                            <div class="space-y-2 pt-1">
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
                            <div class="pt-1">
                              <button type="button" (click)="rotateCheckinLink(measure)"
                                      [disabled]="measure.status !== 'ACTIVE' || isRotatingCheckin(measure.id)"
                                      class="rounded-lg bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-700 disabled:bg-gray-300">
                                {{ isRotatingCheckin(measure.id) ? 'Wird erstellt…' : 'Link erstellen' }}
                              </button>
                            </div>
                          }
                        }
                      </div>
                      <div class="space-y-2">
                        <div class="flex items-center gap-2">
                          <span class="text-[11px] uppercase font-semibold" style="color: #6f7d76; letter-spacing: .04em">Wirkung</span>
                          @if (measureImpactEnabled()) {
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase" style="background: #fdf3e3; color: #9a6b1f; letter-spacing: .04em">Konzept</span>
                          }
                        </div>
                        @if (!measureImpactEnabled()) {
                          <div class="text-xs italic" style="color: #9aa39c">Noch keine Daten</div>
                        } @else if (derivedStatus(measure) === 'COMPLETED') {
                          <button type="button" (click)="openImpactDialog(measure)"
                                  class="text-[11px] font-semibold text-gray-700 hover:bg-gray-50"
                                  style="border: 1px solid #e5ded3; border-radius: 8px; padding: 5px 12px">
                            Wirkungsanalyse anzeigen
                          </button>
                        } @else if (derivedStatus(measure) === 'UPCOMING') {
                          <div class="text-xs italic" style="color: #9aa39c">Termin steht noch bevor — Wirkung wird nach Abschluss ermittelt.</div>
                        } @else {
                          <div class="text-xs italic" style="color: #9aa39c">Noch keine Daten</div>
                        }
                      </div>
                    </div>
                  </div>
                }
              </div>
            }
          </div>
        }
      }

      @if (impactDialogMeasure(); as dialogMeasure) {
        <app-measure-impact-dialog [measure]="dialogMeasure" (close)="impactDialogMeasure.set(null)" />
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
  expandedMeasureId = signal<number | null>(null);
  executionDetails = signal<Record<number, MeasureExecution | null>>({});
  impactDialogMeasure = signal<CompanyMeasure | null>(null);

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
          this.executionDetails.update(details => {
            const next = { ...details };
            delete next[res.data.id];
            return next;
          });
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

  measureImpactEnabled() {
    return this.authStore.measureImpactEnabled();
  }

  toggleExpanded(measure: CompanyMeasure) {
    const next = this.expandedMeasureId() === measure.id ? null : measure.id;
    this.expandedMeasureId.set(next);
    if (next !== null && this.executionDetails()[measure.id] === undefined) {
      this.companyMeasuresService.getExecution(measure.id).subscribe({
        next: res => this.executionDetails.update(details => ({ ...details, [measure.id]: res.data ?? null })),
        error: () => this.executionDetails.update(details => ({ ...details, [measure.id]: null })),
      });
    }
  }

  executionFor(measureId: number): MeasureExecution | null {
    return this.executionDetails()[measureId] ?? null;
  }

  // Same derivation rules as the backend's MeasureExecutionService, so the
  // collapsed rows get their chip without one request per measure.
  derivedStatus(measure: CompanyMeasure): 'UPCOMING' | 'RUNNING' | 'COMPLETED' | 'PLANNED' {
    const now = Date.now();
    if (measure.status === 'COMPLETED' || (measure.status === 'ACTIVE' && !!measure.endsAt && new Date(measure.endsAt).getTime() < now)) {
      return 'COMPLETED';
    }
    if (measure.status === 'SUGGESTED' || measure.status === 'DISMISSED') {
      return 'PLANNED';
    }
    if (!!measure.startsAt && new Date(measure.startsAt).getTime() > now) {
      return 'UPCOMING';
    }
    return 'RUNNING';
  }

  derivedStatusLabel(measure: CompanyMeasure) {
    switch (this.derivedStatus(measure)) {
      case 'UPCOMING': return 'Bevorstehend';
      case 'PLANNED': return 'Geplant';
      case 'RUNNING': return 'Läuft';
      case 'COMPLETED': return 'Abgeschlossen';
    }
  }

  openImpactDialog(measure: CompanyMeasure) {
    this.impactDialogMeasure.set(measure);
  }

  categoryLabel(category: string | null | undefined) {
    switch (category) {
      case 'workshop': return 'Workshop';
      case 'flexibility': return 'Flexibilität';
      case 'sport': return 'Sport';
      case 'mental': return 'Mental';
      case 'nutrition': return 'Ernährung';
      default: return category || '-';
    }
  }

  deliveryLabel(value: string | null | undefined) {
    switch (value) {
      case 'ONSITE': return 'Vor Ort';
      case 'REMOTE': return 'Remote';
      case 'HYBRID': return 'Hybrid';
      default: return '-';
    }
  }

  executionLabel(value: string | null | undefined) {
    switch (value) {
      case 'EVENT_PARTICIPATION': return 'Event-Teilnahme';
      case 'INFORMATION_ONLY': return 'Information';
      case 'GUIDED_SESSION': return 'Geführte Session';
      case 'SELF_REPORTED_ACTION': return 'Selbst gemeldet';
      case 'CHALLENGE': return 'Challenge';
      default: return '-';
    }
  }

  formatDate(value: string) {
    return new Date(value).toLocaleDateString('de-DE');
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
      && !!rawPayload.executionType
      && SCHEDULED_EXECUTION_TYPES.includes(rawPayload.executionType)
      && (!rawPayload.startsAt || !rawPayload.endsAt)
      // Only clear the stale derived value; a duration the user typed after
      // breaking the schedule window is an intentional manual duration.
      && !this.measureForm.get('durationMinutes')?.dirty
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
