import { Injectable, inject } from '@angular/core';
import { Router } from '@angular/router';
import { ApiClient } from './api-client.service';
import { AuthStore } from '../store/auth.store';
import { User, LoginResponse, MeResponse, Portal } from '../models/auth.models';
import { Observable, tap, catchError, of, finalize, map } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private api = inject(ApiClient);
  private store = inject(AuthStore);
  private router = inject(Router);

  login(credentials: { email: string; password: string; requested_portal?: string }): Observable<LoginResponse> {
    this.store.setLoading(true);
    return this.api.post<LoginResponse>('/auth/login', credentials).pipe(
      tap(res => {
        this.store.setToken(res.access_token);
        this.store.setUser(res.user);
        this.store.setActivePortal(res.activePortal);
        this.store.setAllowedPortals(res.allowedPortals);
      }),
      finalize(() => this.store.setLoading(false))
    );
  }

  logout(): void {
    this.api.post('/auth/logout', {}).subscribe();
    this.store.clear();
    this.router.navigate(['/auth/login']);
  }

  getMe(): Observable<User | null> {
    if (!this.store.token()) return of(null);
    return this.api.get<MeResponse>('/auth/me').pipe(
      tap(res => {
        this.store.setUser(res);
        this.store.setAllowedPortals(res.allowedPortals);
        if (!this.store.activePortal() && res.allowedPortals.length > 0) {
          const detected = this.detectPortalFromHostname();
          const portal = detected && res.allowedPortals.includes(detected) ? detected : res.allowedPortals[0];
          this.store.setActivePortal(portal);
        }
      }),
      map(res => res as User),
      catchError(() => {
        this.store.clear();
        return of(null);
      })
    );
  }

  detectPortalFromHostname(): Portal | null {
    const hostname = window.location.hostname;
    if (hostname.startsWith('admin')) return 'admin';
    if (hostname.startsWith('company')) return 'company';
    if (hostname.startsWith('app') || hostname.startsWith('employee')) return 'employee';
    if (hostname.startsWith('partner')) return 'partner';
    // Default for localhost / unknown
    return null;
  }

  getDefaultRoute(portal: Portal | null): string {
    switch (portal) {
      case 'admin': return '/admin/companies';
      case 'company': return '/company/dashboard';
      case 'employee': return '/employee/dashboard';
      case 'partner': return '/partner/dashboard';
      default: return '/auth/login';
    }
  }
}
