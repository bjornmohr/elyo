import { inject } from '@angular/core';
import { CanActivateFn, Router, UrlTree } from '@angular/router';
import { map, Observable, of } from 'rxjs';
import { AuthStore } from '../store/auth.store';
import { Role, Portal, User } from '../models/auth.models';
import { AuthService } from '../services/auth.service';

const loginUrl = (router: Router, returnUrl?: string): UrlTree =>
  router.createUrlTree(['/auth/login'], returnUrl ? { queryParams: { returnUrl } } : undefined);

const restoreUser = (store: AuthStore, authService: AuthService): Observable<User | null> => {
  const user = store.user();
  if (user) return of(user);
  if (!store.token()) return of(null);
  return authService.getMe();
};

const defaultAuthenticatedRoute = (store: AuthStore, authService: AuthService): string => {
  const portal = authService.resolvePortal(store.activePortal(), store.allowedPortals());
  return authService.getDefaultRoute(portal);
};

export const authGuard: CanActivateFn = (route, state) => {
  const store = inject(AuthStore);
  const router = inject(Router);
  const authService = inject(AuthService);

  if (store.isAuthenticated()) {
    return true;
  }

  return restoreUser(store, authService).pipe(
    map(user => user ? true : loginUrl(router, state.url))
  );
};

export const roleGuard = (roles: Role[]): CanActivateFn => {
  return (route, state) => {
    const store = inject(AuthStore);
    const router = inject(Router);
    const authService = inject(AuthService);

    if (store.hasRole(roles)) {
      return true;
    }

    return restoreUser(store, authService).pipe(
      map(user => {
        if (!user) return loginUrl(router, state.url);
        if (store.hasRole(roles)) return true;

        const route = defaultAuthenticatedRoute(store, authService);
        return router.createUrlTree([route]);
      })
    );
  };
};

export const portalGuard = (portal: Portal): CanActivateFn => {
  return (route, state) => {
    const store = inject(AuthStore);
    const router = inject(Router);
    const authService = inject(AuthService);

    if (store.allowedPortals().includes(portal)) {
      store.setActivePortal(portal);
      return true;
    }

    return restoreUser(store, authService).pipe(
      map(user => {
        if (!user) return loginUrl(router, state.url);
        if (store.allowedPortals().includes(portal)) {
          store.setActivePortal(portal);
          return true;
        }

        const route = defaultAuthenticatedRoute(store, authService);
        return router.createUrlTree([route]);
      })
    );
  };
};
