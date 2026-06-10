import { TestBed } from '@angular/core/testing';
import { of, throwError } from 'rxjs';
import { Role } from '../../../../core/models/auth.models';
import { ApiClient } from '../../../../core/services/api-client.service';
import { AuthStore } from '../../../../core/store/auth.store';
import { NotificationService } from '../../../../shared/notifications/notification.service';
import { CompanyMeasuresService } from '../../services/company-measures.service';
import { CompanyMeasuresComponent } from './company-measures.component';

describe('CompanyMeasuresComponent', () => {
  let api: { get: ReturnType<typeof vi.fn>; post: ReturnType<typeof vi.fn> };
  let companyMeasuresService: { generateMeasureCheckinToken: ReturnType<typeof vi.fn> };
  let notifications: { success: ReturnType<typeof vi.fn>; error: ReturnType<typeof vi.fn> };

  beforeEach(async () => {
    notifications = {
      success: vi.fn(),
      error: vi.fn(),
    };
    api = {
      get: vi.fn((path: string) => {
        if (path === '/company/measures') {
          return of({
            data: [
              { id: 1, title: 'Workshop', category: 'mental', status: 'ACTIVE', team: null },
              { id: 2, title: 'Coaching', category: 'workshop', status: 'ACTIVE', team: null },
            ],
          });
        }

        if (path === '/company/measures/1/participation-summary') {
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

        if (path === '/company/measures/2/participation-summary') {
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

        throw new Error(`Unexpected API path: ${path}`);
      }),
      post: vi.fn(),
    };
    companyMeasuresService = {
      generateMeasureCheckinToken: vi.fn(),
    };

    await TestBed.configureTestingModule({
      imports: [CompanyMeasuresComponent],
      providers: [
        { provide: ApiClient, useValue: api },
        { provide: CompanyMeasuresService, useValue: companyMeasuresService },
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
    api.get.mockImplementation((path: string) => {
      if (path === '/company/measures') {
        return of({
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
        });
      }

      if (path === '/company/measures/3/participation-summary') {
        return of({
          data: {
            measureId: 3,
            isAboveThreshold: false,
            eligibleCount: null,
            participantCount: null,
            participationRate: null,
            suppressionReason: 'minimum_group_size',
            teamBreakdown: null,
          },
        });
      }

      throw new Error(`Unexpected API path: ${path}`);
    });
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
});
