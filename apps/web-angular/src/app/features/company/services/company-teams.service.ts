import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiClient } from '../../../core/services/api-client.service';

@Injectable({
  providedIn: 'root',
})
export class CompanyTeamsService {
  private api = inject(ApiClient);

  listTeams(): Observable<{ data: any[] }> {
    return this.api.get<{ data: any[] }>('/company/teams');
  }
}
