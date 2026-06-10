import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { Role } from '../../../../core/models/auth.models';
import { ApiClient } from '../../../../core/services/api-client.service';
import { AuthStore } from '../../../../core/store/auth.store';
import { NotificationService } from '../../../../shared/notifications/notification.service';
import { CompanyMeasuresComponent } from './company-measures.component';

describe('CompanyMeasuresComponent', () => {
  let api: { get: ReturnType<typeof vi.fn>; post: ReturnType<typeof vi.fn> };

  beforeEach(async () => {
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

    await TestBed.configureTestingModule({
      imports: [CompanyMeasuresComponent],
      providers: [
        { provide: ApiClient, useValue: api },
        {
          provide: AuthStore,
          useValue: {
            roles: () => [Role.COMPANY_ADMIN],
            teamLayerEnabled: () => false,
          },
        },
        {
          provide: NotificationService,
          useValue: {
            success: vi.fn(),
            error: vi.fn(),
          },
        },
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
});
