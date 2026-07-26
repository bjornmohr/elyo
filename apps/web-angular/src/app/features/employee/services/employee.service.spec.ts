import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { ApiClient } from '../../../core/services/api-client.service';
import { EmployeeService } from './employee.service';

describe('EmployeeService', () => {
  let api: { post: ReturnType<typeof vi.fn> };
  let service: EmployeeService;

  beforeEach(() => {
    api = {
      post: vi.fn(() => of({ data: { id: 42 } })),
    };

    TestBed.configureTestingModule({
      providers: [
        EmployeeService,
        { provide: ApiClient, useValue: api },
      ],
    });

    service = TestBed.inject(EmployeeService);
  });

  it('posts measure participation with an empty body only', () => {
    service.participateInMeasure(42).subscribe();

    expect(api.post).toHaveBeenCalledWith('/employee/measures/42/participate', {});

    const body = api.post.mock.calls[0][1] as Record<string, unknown>;
    expect(Object.keys(body)).toEqual([]);
    expect(body).not.toHaveProperty('user_id');
    expect(body).not.toHaveProperty('userId');
    expect(body).not.toHaveProperty('company_id');
    expect(body).not.toHaveProperty('companyId');
    expect(body).not.toHaveProperty('team_id');
    expect(body).not.toHaveProperty('teamId');
    expect(body).not.toHaveProperty('participated_at');
    expect(body).not.toHaveProperty('participatedAt');
  });

  it('submits a check-in with only the required 1-5 scale values', () => {
    service.submitCheckin({
      mood: 5,
      stress: 1,
      energy: 4,
    }).subscribe();

    const body = api.post.mock.calls[0][1] as Record<string, unknown>;
    expect(api.post).toHaveBeenCalledWith('/employee/checkin', {
      mood: 5,
      stress: 1,
      energy: 4,
    });
    // Order-independent: guards against a resurrected `note`, which the API
    // rejects with 422 (ELYO-102 §3.3 / B4).
    expect(Object.keys(body).sort()).toEqual(['energy', 'mood', 'stress']);
  });
});
