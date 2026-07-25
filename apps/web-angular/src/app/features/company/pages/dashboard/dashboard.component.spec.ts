import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { ApiClient } from '../../../../core/services/api-client.service';
import { AuthStore } from '../../../../core/store/auth.store';
import { CompanyDashboardComponent } from './dashboard.component';

describe('CompanyDashboardComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CompanyDashboardComponent],
      providers: [
        {
          provide: ApiClient,
          useValue: {
            get: vi.fn(() => of({
              company: {
                status: 'reporting_pending',
                data: null,
                isAboveThreshold: null,
                responseCount: null,
              },
              trend: {
                status: 'reporting_pending',
                data: null,
              },
              teams: [
                {
                  id: 'team-1',
                  name: 'Team One',
                  metrics: {
                    status: 'reporting_pending',
                    data: null,
                  },
                },
              ],
            })),
          },
        },
        {
          provide: AuthStore,
          useValue: {
            teamLayerEnabled: () => true,
          },
        },
      ],
    }).compileComponents();
  });

  it('renders the reporting-pending dashboard without treating the trend block as health data', () => {
    const fixture = TestBed.createComponent(CompanyDashboardComponent);

    expect(() => fixture.detectChanges()).not.toThrow();

    const text = fixture.nativeElement.textContent;
    expect(text).toContain('Berichtsdaten werden vorbereitet');
    expect(text).not.toContain('Aus Datenschutzgründen');
    expect(text).not.toContain('Alle Teams erfüllen die Anonymitätsschwelle');
  });

  it('does not claim anonymity thresholds were met when no teams exist', () => {
    const fixture = TestBed.createComponent(CompanyDashboardComponent);
    fixture.componentInstance.data.set({
      company: {
        status: 'reporting_pending',
        data: null,
        isAboveThreshold: null,
        responseCount: null,
      },
      trend: {
        status: 'reporting_pending',
        data: null,
      },
      teams: [],
    });

    expect(fixture.componentInstance.teamMetricsLabel()).toBe('Berichtsdaten werden vorbereitet');
  });
});
