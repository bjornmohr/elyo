import { Injectable, inject } from '@angular/core';
import { Router } from '@angular/router';
import { ApiClient } from './api-client.service';
import { AuthStore } from '../store/auth.store';
import { User, LoginResponse, MeResponse, Portal } from '../models/auth.models';
import { Observable, tap, catchError, of, finalize, map, shareReplay } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private api = inject(ApiClient);
  private store = inject(AuthStore);
  private router = inject(Router);
  private meRequest$: Observable<User | null> | null = null;

  login(credentials: { email: string; password: string; requested_portal?: string }): Observable<LoginResponse> {
    this.store.setLoading(true);
    return this.api.post<LoginResponse>('/auth/login', credentials).pipe(
      tap(res => {
        this.store.setToken(res.access_token);
        this.store.setUser(res.user);
        this.store.setAllowedPortals(res.allowedPortals);
        this.store.setActivePortal(this.resolvePortal(res.activePortal, res.allowedPortals));
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
    if (this.store.user()) return of(this.store.user());
    if (this.meRequest$) return this.meRequest$;

    this.store.setLoading(true);
    this.meRequest$ = this.api.get<MeResponse>('/auth/me').pipe(
      tap(res => {
        this.store.setUser(res);
        this.store.setAllowedPortals(res.allowedPortals);
        this.store.setActivePortal(this.resolvePortal(res.activePortal, res.allowedPortals));
      }),
      map(res => res as User),
      catchError(() => {
        this.store.clear();
        return of(null);
      }),
      finalize(() => {
        this.store.setLoading(false);
        this.meRequest$ = null;
      }),
      shareReplay(1)
    );

    return this.meRequest$;
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

  resolvePortal(activePortal: Portal | null, allowedPortals: Portal[]): Portal | null {
    if (activePortal && allowedPortals.includes(activePortal)) {
      return activePortal;
    }

    return allowedPortals[0] ?? null;
  }

  portalForUrl(url: string | null): Portal | null {
    if (!url) return null;

    const path = url.split('?')[0].split('#')[0];
    const firstSegment = path.split('/').filter(Boolean)[0];

    switch (firstSegment) {
      case 'admin':
      case 'company':
      case 'employee':
      case 'partner':
        return firstSegment;
      default:
        return null;
    }
  }

  isAllowedPortalUrl(url: string | null, allowedPortals: Portal[]): boolean {
    const portal = this.portalForUrl(url);
    return !!portal && allowedPortals.includes(portal);
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
