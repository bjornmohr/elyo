import { Injectable, inject } from '@angular/core';
import { Router } from '@angular/router';
import { ApiClient } from './api-client.service';
import { AuthStore } from '../store/auth.store';
import { User } from '../models/auth.models';
import { Observable, tap, catchError, of, finalize } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private api = inject(ApiClient);
  private store = inject(AuthStore);
  private router = inject(Router);

  login(credentials: any): Observable<any> {
    this.store.setLoading(true);
    return this.api.post<{user: User, token: string}>('/auth/login', credentials).pipe(
      tap(res => {
        this.store.setUser(res.user);
        this.store.setToken(res.token);
      }),
      finalize(() => this.store.setLoading(false))
    );
  }

  logout(): void {
    this.api.post('/auth/logout', {}).subscribe();
    this.store.setUser(null);
    this.store.setToken(null);
    this.router.navigate(['/auth/login']);
  }

  getMe(): Observable<User | null> {
    if (!this.store.token()) return of(null);

    return this.api.get<User>('/auth/me').pipe(
      tap(user => this.store.setUser(user)),
      catchError(() => {
        this.store.setToken(null);
        return of(null);
      })
    );
  }
}
