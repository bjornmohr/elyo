import { TestBed } from '@angular/core/testing';
import { of, throwError } from 'rxjs';
import { Role } from '../../../../core/models/auth.models';
import { AuthStore } from '../../../../core/store/auth.store';
import { NotificationService } from '../../../../shared/notifications/notification.service';
import { CompanyMeasuresService } from '../../services/company-measures.service';
import { CompanyTeamsService } from '../../services/company-teams.service';
import { CompanyMeasuresComponent } from './company-measures.component';

describe('CompanyMeasuresComponent', () => {
  let companyMeasuresService: {
    listMeasures: ReturnType<typeof vi.fn>;
    getParticipationSummary: ReturnType<typeof vi.fn>;
    createMeasure: ReturnType<typeof vi.fn>;
    updateMeasure: ReturnType<typeof vi.fn>;
    generateMeasureCheckinToken: ReturnType<typeof vi.fn>;
  };
  let companyTeamsService: { listTeams: ReturnType<typeof vi.fn> };
  let notifications: { success: ReturnType<typeof vi.fn>; error: ReturnType<typeof vi.fn> };

  beforeEach(async () => {
    notifications = {
      success: vi.fn(),
      error: vi.fn(),
    };
    companyMeasuresService = {
      listMeasures: vi.fn(() => of({
        data: [
          { id: 1, title: 'Workshop', category: 'mental', status: 'ACTIVE', team: null },
          { id: 2, title: 'Coaching', category: 'workshop', status: 'ACTIVE', team: null },
        ],
      })),
      getParticipationSummary: vi.fn((measureId: number) => {
        if (measureId === 1) {
          return of({
            data: {
              measureId: 1,
              isAboveThreshold: true,
              eligibleCount: 20,
              participantCount: 12,
              participationRate: 60,
              suppressionReason: null,
              teamBreakdown: null,
            },
          });
        }

        if (measureId === 2) {
          return of({
            data: {
              measureId: 2,
              isAboveThreshold: false,
              eligibleCount: 7,
              participantCount: 2,
              participationRate: 28,
              suppressionReason: 'minimum_group_size',
              teamBreakdown: [{ teamName: 'People Team', participantCount: 1 }],
              participants: [{ name: 'Erika Example' }],
            },
          });
        }

        return of({ data: null });
      }),
      createMeasure: vi.fn(() => of({ data: { id: 9, title: 'Created measure' } })),
      updateMeasure: vi.fn(() => of({ data: { id: 1, title: 'Updated measure', category: 'mental', description: 'Updated description', status: 'ACTIVE' } })),
      generateMeasureCheckinToken: vi.fn(),
    };
    companyTeamsService = {
      listTeams: vi.fn(() => of({ data: [] })),
    };

    await TestBed.configureTestingModule({
      imports: [CompanyMeasuresComponent],
      providers: [
        { provide: CompanyMeasuresService, useValue: companyMeasuresService },
        { provide: CompanyTeamsService, useValue: companyTeamsService },
        {
          provide: AuthStore,
          useValue: {
            roles: () => [Role.COMPANY_ADMIN],
            teamLayerEnabled: () => false,
          },
        },
        { provide: NotificationService, useValue: notifications },
      ],
    }).compileComponents();
  });

  it('displays above-threshold aggregate participation only', () => {
    const fixture = TestBed.createComponent(CompanyMeasuresComponent);
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent;
    expect(text).toContain('60% Teilnahmequote');
    expect(text).toContain('12 von 20 Berechtigten');
  });

  it('suppresses below-threshold counts and rates', () => {
    const fixture = TestBed.createComponent(CompanyMeasuresComponent);
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent;
    expect(text).toContain('Mindestgruppengröße nicht erreicht');
    expect(text).not.toContain('28% Teilnahmequote');
    expect(text).not.toContain('2 von 7 Berechtigten');
    expect(text).not.toContain('minimum_group_size');
  });

  it('does not render team breakdowns or individual participant fields', () => {
    const fixture = TestBed.createComponent(CompanyMeasuresComponent);
    fixture.detectChanges();

    const text = fixture.nativeElement.textContent;
    expect(text).not.toContain('People Team');
    expect(text).not.toContain('Erika Example');
  });

  it('allows QR_CODE as the only non-self-report verification option', () => {
    const fixture = TestBed.createComponent(CompanyMeasuresComponent);
    fixture.detectChanges();

    fixture.componentInstance.toggleForm();
    fixture.detectChanges();

    const options = Array.from<HTMLOptionElement>(fixture.nativeElement.querySelectorAll('select[formcontrolname="verificationRequirement"] option'))
      .map((option: HTMLOptionElement) => option.value);

    expect(options).toEqual(['SELF_REPORT', 'QR_CODE']);
  });

  it('composes check-in links from checkinPath returned by the API', () => {
    companyMeasuresService.listMeasures.mockReturnValue(of({
      data: [
        {
          id: 3,
          title: 'QR measure',
          category: 'sport',
          status: 'ACTIVE',
          verificationRequirement: 'QR_CODE',
          team: null,
        },
      ],
    }));
    companyMeasuresService.generateMeasureCheckinToken.mockReturnValue(of({
      data: {
        measureId: 3,
        token: 'abc',
        checkinPath: '/employee/measure-checkins/abc',
        validFrom: null,
        validUntil: null,
        revokedAt: null,
      },
    }));

    const fixture = TestBed.createComponent(CompanyMeasuresComponent);
    fixture.detectChanges();

    fixture.componentInstance.rotateCheckinLink(fixture.componentInstance.measures()[0]);
    fixture.detectChanges();

    expect(companyMeasuresService.generateMeasureCheckinToken).toHaveBeenCalledWith(3);
    expect(fixture.nativeElement.textContent).toContain('Kopieren');
    expect(fixture.nativeElement.querySelector('input[readonly]').value).toBe(`${window.location.origin}/employee/measure-checkins/abc`);
  });

  it('does not call token generation for self-report measures', () => {
    const fixture = TestBed.createComponent(CompanyMeasuresComponent);
    fixture.detectChanges();

    fixture.componentInstance.rotateCheckinLink({
      id: 4,
      title: 'Self-report measure',
      category: 'sport',
      status: 'ACTIVE',
      verificationRequirement: 'SELF_REPORT',
    } as any);

    expect(companyMeasuresService.generateMeasureCheckinToken).not.toHaveBeenCalled();
    expect(notifications.error).toHaveBeenCalledWith('Check-in-Links sind nur für QR-Maßnahmen verfügbar.');
  });

  it('shows a clear message when QR token generation is rejected for the measure type', () => {
    companyMeasuresService.generateMeasureCheckinToken.mockReturnValue(throwError(() => ({
      status: 409,
      error: {
        error: {
          code: 'MEASURE_DOES_NOT_ALLOW_QR_CHECKIN',
        },
      },
    })));
    const fixture = TestBed.createComponent(CompanyMeasuresComponent);
    fixture.detectChanges();

    fixture.componentInstance.rotateCheckinLink({
      id: 5,
      title: 'Stale QR measure',
      category: 'sport',
      status: 'ACTIVE',
      verificationRequirement: 'QR_CODE',
    } as any);

    expect(notifications.error).toHaveBeenCalledWith('Check-in-Links sind nur für QR-Maßnahmen verfügbar.');
  });

  it('shows an error when end is not after start', () => {
    const fixture = TestBed.createComponent(CompanyMeasuresComponent);
    fixture.detectChanges();

    fixture.componentInstance.toggleForm();
    fixture.componentInstance.measureForm.patchValue({
      title: 'Invalid schedule',
      category: 'sport',
      description: 'A valid description.',
      startsAt: '2026-06-20T10:00',
      endsAt: '2026-06-20T10:00',
    });
    fixture.componentInstance.submit();
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Ende muss nach dem Start liegen.');
    expect(companyMeasuresService.createMeasure).not.toHaveBeenCalled();
  });

  it('does not call the create service for invalid required fields', () => {
    const fixture = TestBed.createComponent(CompanyMeasuresComponent);
    fixture.detectChanges();

    fixture.componentInstance.toggleForm();
    fixture.componentInstance.submit();
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Dieses Feld ist erforderlich.');
    expect(companyMeasuresService.createMeasure).not.toHaveBeenCalled();
  });

  it('displays backend validation errors returned by the API', () => {
    companyMeasuresService.createMeasure.mockReturnValue(throwError(() => ({
      status: 422,
      error: {
        errors: {
          endsAt: ['Ende muss nach dem Start liegen.'],
        },
      },
    })));
    const fixture = TestBed.createComponent(CompanyMeasuresComponent);
    fixture.detectChanges();

    fixture.componentInstance.toggleForm();
    fixture.componentInstance.measureForm.patchValue({
      title: 'Valid title',
      category: 'sport',
      description: 'A valid description.',
    });
    fixture.componentInstance.submit();
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Ende muss nach dem Start liegen.');
  });

  it('opens edit mode with a populated form', () => {
    companyMeasuresService.listMeasures.mockReturnValue(of({
      data: [{
        id: 8,
        title: 'Editable measure',
        category: 'mental',
        description: 'A measure that can be edited.',
        status: 'ACTIVE',
        startsAt: '2026-06-20T09:00:00.000000Z',
        endsAt: '2026-06-20T10:00:00.000000Z',
        team: null,
      }],
    }));

    const fixture = TestBed.createComponent(CompanyMeasuresComponent);
    fixture.detectChanges();

    fixture.componentInstance.editMeasure(fixture.componentInstance.measures()[0]);
    fixture.detectChanges();

    expect(fixture.componentInstance.editingMeasureId()).toBe(8);
    expect(fixture.componentInstance.measureForm.value.title).toBe('Editable measure');
    expect(fixture.nativeElement.textContent).toContain('Maßnahme bearbeiten');
  });

  it('prefills edit schedule fields with local wall time and round-trips the instant on save', () => {
    const startsAtUtc = '2026-06-20T09:00:00.000000Z';
    const endsAtUtc = '2026-06-20T10:00:00.000000Z';
    companyMeasuresService.listMeasures.mockReturnValue(of({
      data: [{
        id: 8,
        title: 'Editable measure',
        category: 'mental',
        description: 'A measure that can be edited.',
        status: 'ACTIVE',
        startsAt: startsAtUtc,
        endsAt: endsAtUtc,
        team: null,
      }],
    }));
    companyMeasuresService.updateMeasure.mockReturnValue(of({ data: { id: 8, title: 'Editable measure' } }));

    const fixture = TestBed.createComponent(CompanyMeasuresComponent);
    fixture.detectChanges();

    fixture.componentInstance.editMeasure(fixture.componentInstance.measures()[0]);

    // datetime-local controls hold local wall time, so the expected value is
    // built from local date parts of the same instant - stable across runner
    // timezones, and different from the raw UTC slice whenever TZ !== UTC.
    const pad = (part: number) => String(part).padStart(2, '0');
    const toLocal = (iso: string) => {
      const date = new Date(iso);
      return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    };
    expect(fixture.componentInstance.measureForm.value.startsAt).toBe(toLocal(startsAtUtc));
    expect(fixture.componentInstance.measureForm.value.endsAt).toBe(toLocal(endsAtUtc));

    fixture.componentInstance.submit();

    // Saving without touching the schedule must not shift the stored instant.
    expect(companyMeasuresService.updateMeasure).toHaveBeenCalledWith(8, expect.objectContaining({
      startsAt: new Date(startsAtUtc).toISOString(),
      endsAt: new Date(endsAtUtc).toISOString(),
    }));
  });

  it('serializes datetime-local values to explicit ISO strings on create', () => {
    const fixture = TestBed.createComponent(CompanyMeasuresComponent);
    fixture.detectChanges();

    fixture.componentInstance.toggleForm();
    fixture.componentInstance.measureForm.patchValue({
      title: 'Scheduled measure',
      category: 'sport',
      description: 'A valid description.',
      startsAt: '2026-06-20T09:00',
      endsAt: '2026-06-20T10:30',
    });
    fixture.componentInstance.submit();

    expect(companyMeasuresService.createMeasure).toHaveBeenCalledWith(expect.objectContaining({
      startsAt: new Date('2026-06-20T09:00').toISOString(),
      endsAt: new Date('2026-06-20T10:30').toISOString(),
    }));
  });

  it('keeps the manual duration when editing a scheduled measure without a full schedule window', () => {
    companyMeasuresService.listMeasures.mockReturnValue(of({
      data: [{
        id: 11,
        title: 'Manual duration measure',
        category: 'sport',
        description: 'A scheduled measure with manual duration.',
        status: 'ACTIVE',
        executionType: 'EVENT_PARTICIPATION',
        startsAt: null,
        endsAt: null,
        durationMinutes: 60,
        team: null,
      }],
    }));
    companyMeasuresService.updateMeasure.mockReturnValue(of({ data: { id: 11, title: 'Manual duration measure' } }));

    const fixture = TestBed.createComponent(CompanyMeasuresComponent);
    fixture.detectChanges();

    fixture.componentInstance.editMeasure(fixture.componentInstance.measures()[0]);

    expect(fixture.componentInstance.durationPreviewMinutes()).toBeNull();
    expect(fixture.componentInstance.measureForm.value.durationMinutes).toBe(60);

    fixture.componentInstance.submit();

    expect(companyMeasuresService.updateMeasure).toHaveBeenCalledWith(11, expect.objectContaining({
      durationMinutes: 60,
      startsAt: null,
      endsAt: null,
    }));
  });

  it('calls the update service when saving a valid edit', () => {
    const fixture = TestBed.createComponent(CompanyMeasuresComponent);
    fixture.detectChanges();

    const measure = {
      id: 1,
      title: 'Workshop',
      category: 'mental',
      description: 'A workshop description.',
      status: 'ACTIVE',
    } as any;
    fixture.componentInstance.editMeasure(measure);
    fixture.componentInstance.measureForm.patchValue({
      title: 'Updated workshop',
      description: 'Updated workshop description.',
    });
    fixture.componentInstance.submit();

    expect(companyMeasuresService.updateMeasure).toHaveBeenCalledWith(1, expect.objectContaining({
      title: 'Updated workshop',
      description: 'Updated workshop description.',
    }));
    expect(companyMeasuresService.updateMeasure.mock.calls[0][1]).not.toHaveProperty('status');
  });

  it('does not show an active edit action for completed measures', () => {
    companyMeasuresService.listMeasures.mockReturnValue(of({
      data: [{
        id: 10,
        title: 'Completed measure',
        category: 'sport',
        description: 'A completed measure.',
        status: 'COMPLETED',
        team: null,
      }],
    }));
    const fixture = TestBed.createComponent(CompanyMeasuresComponent);
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Nicht bearbeitbar');
    expect(fixture.nativeElement.textContent).not.toContain('Bearbeiten');
  });

  it('shows a read-only status without a status select in edit mode', () => {
    const fixture = TestBed.createComponent(CompanyMeasuresComponent);
    fixture.detectChanges();

    const measure = {
      id: 4,
      title: 'Active measure',
      category: 'sport',
      description: 'An active measure description.',
      status: 'ACTIVE',
    } as any;
    fixture.componentInstance.editMeasure(measure);
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('select[formcontrolname="status"]')).toBeNull();
    expect(fixture.nativeElement.textContent).not.toContain('Vorgeschlagen');
    expect(fixture.nativeElement.textContent).toContain('Der Status kann beim Bearbeiten nicht geändert werden.');
  });

  it('still offers the status select in create mode', () => {
    const fixture = TestBed.createComponent(CompanyMeasuresComponent);
    fixture.detectChanges();

    fixture.componentInstance.toggleForm();
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('select[formcontrolname="status"]')).not.toBeNull();
  });

  it('displays backend status and verification errors returned for an edit', () => {
    companyMeasuresService.updateMeasure.mockReturnValue(throwError(() => ({
      status: 422,
      error: {
        errors: {
          status: ['Completed or dismissed measures cannot be edited.'],
          verificationRequirement: ['Verification requirement cannot be changed after participation exists.'],
        },
      },
    })));
    const fixture = TestBed.createComponent(CompanyMeasuresComponent);
    fixture.detectChanges();

    const measure = {
      id: 5,
      title: 'Active measure',
      category: 'sport',
      description: 'An active measure description.',
      status: 'ACTIVE',
    } as any;
    fixture.componentInstance.editMeasure(measure);
    fixture.componentInstance.submit();
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Completed or dismissed measures cannot be edited.');
    expect(fixture.nativeElement.textContent).toContain('Verification requirement cannot be changed after participation exists.');
  });

  it('shows a derived duration preview for scheduled measures', () => {
    const fixture = TestBed.createComponent(CompanyMeasuresComponent);
    fixture.detectChanges();

    fixture.componentInstance.toggleForm();
    fixture.componentInstance.measureForm.patchValue({
      executionType: 'EVENT_PARTICIPATION',
      startsAt: '2026-06-20T09:00',
      endsAt: '2026-06-20T10:30',
    });
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('90 Min.');
  });
});
