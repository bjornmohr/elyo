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
});
