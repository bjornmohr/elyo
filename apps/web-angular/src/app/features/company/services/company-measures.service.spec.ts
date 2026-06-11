import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { ApiClient } from '../../../core/services/api-client.service';
import { CompanyMeasuresService } from './company-measures.service';

describe('CompanyMeasuresService', () => {
  let api: { post: ReturnType<typeof vi.fn>; patch: ReturnType<typeof vi.fn> };
  let service: CompanyMeasuresService;

  beforeEach(() => {
    api = {
      post: vi.fn(() => of({ data: { measureId: 3 } })),
      patch: vi.fn(() => of({ data: { id: 3 } })),
    };

    TestBed.configureTestingModule({
      providers: [
        CompanyMeasuresService,
        { provide: ApiClient, useValue: api },
      ],
    });

    service = TestBed.inject(CompanyMeasuresService);
  });

  it('generates a measure check-in token with an empty request body only', () => {
    service.generateMeasureCheckinToken(3).subscribe();

    expect(api.post).toHaveBeenCalledWith('/company/measures/3/checkin-token', {});

    const body = api.post.mock.calls[0][1] as Record<string, unknown>;
    expect(Object.keys(body)).toEqual([]);
    expect(body).not.toHaveProperty('user_id');
    expect(body).not.toHaveProperty('userId');
    expect(body).not.toHaveProperty('employee_id');
    expect(body).not.toHaveProperty('employeeId');
    expect(body).not.toHaveProperty('company_id');
    expect(body).not.toHaveProperty('companyId');
    expect(body).not.toHaveProperty('participated_at');
    expect(body).not.toHaveProperty('participatedAt');
  });

  it('creates a company measure through the company measure endpoint', () => {
    const payload = { title: 'Measure' };

    service.createMeasure(payload).subscribe();

    expect(api.post).toHaveBeenCalledWith('/company/measures', payload);
  });

  it('updates a company measure through the existing patch endpoint', () => {
    const payload = { title: 'Updated measure' };

    service.updateMeasure(3, payload).subscribe();

    expect(api.patch).toHaveBeenCalledWith('/company/measures/3', payload);
  });
});
