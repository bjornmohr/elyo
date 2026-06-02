import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { ApiClient } from '../../../../core/services/api-client.service';
import { NotificationService } from '../../../../shared/notifications/notification.service';
import { AdminPointsComponent } from './admin-points.component';

describe('AdminPointsComponent', () => {
  let api: { get: ReturnType<typeof vi.fn>; put: ReturnType<typeof vi.fn> };

  const backendConfig = {
    daily_checkin: 1,
    streak_7days: 7,
    streak_30days: 30,
    anamnesis_completed: 15,
    medical_document_upload: 5,
    measure_participation: 3,
  };

  beforeEach(async () => {
    api = {
      get: vi.fn(() => of({ data: backendConfig })),
      put: vi.fn(() => of({ data: backendConfig })),
    };

    await TestBed.configureTestingModule({
      imports: [AdminPointsComponent],
      providers: [
        { provide: ApiClient, useValue: api },
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

  it('includes measure participation in the configured fields and form controls', () => {
    const fixture = TestBed.createComponent(AdminPointsComponent);
    const component = fixture.componentInstance;

    expect(component.fields.map(field => field.key)).toEqual([
      'daily_checkin',
      'streak_7days',
      'streak_30days',
      'anamnesis_completed',
      'medical_document_upload',
      'measure_participation',
    ]);
    expect(component.form.contains('measure_participation')).toBe(true);
  });

  it('patches measure participation from backend data', () => {
    const fixture = TestBed.createComponent(AdminPointsComponent);
    fixture.detectChanges();

    expect(fixture.componentInstance.form.value.measure_participation).toBe(3);
  });

  it('saves measure participation with the existing point fields', () => {
    const fixture = TestBed.createComponent(AdminPointsComponent);
    fixture.detectChanges();
    fixture.componentInstance.form.patchValue({
      daily_checkin: 2,
      streak_7days: 8,
      streak_30days: 31,
      anamnesis_completed: 16,
      medical_document_upload: 6,
      measure_participation: 4,
    });

    fixture.componentInstance.save();

    expect(api.put).toHaveBeenCalledWith('/admin/points-config', {
      daily_checkin: 2,
      streak_7days: 8,
      streak_30days: 31,
      anamnesis_completed: 16,
      medical_document_upload: 6,
      measure_participation: 4,
    });
  });
});
