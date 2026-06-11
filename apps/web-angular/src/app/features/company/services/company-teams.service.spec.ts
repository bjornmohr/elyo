import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { ApiClient } from '../../../core/services/api-client.service';
import { CompanyTeamsService } from './company-teams.service';

describe('CompanyTeamsService', () => {
  let api: { get: ReturnType<typeof vi.fn> };
  let service: CompanyTeamsService;

  beforeEach(() => {
    api = {
      get: vi.fn(() => of({ data: [] })),
    };

    TestBed.configureTestingModule({
      providers: [
        CompanyTeamsService,
        { provide: ApiClient, useValue: api },
      ],
    });

    service = TestBed.inject(CompanyTeamsService);
  });

  it('lists company teams through the company teams endpoint', () => {
    service.listTeams().subscribe();

    expect(api.get).toHaveBeenCalledWith('/company/teams');
  });
});
