import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthStore } from '../store/auth.store';
import { Role } from '../models/auth.models';

export const authGuard: CanActivateFn = (route, state) => {
  const store = inject(AuthStore);
  const router = inject(Router);

  if (store.isAuthenticated()) {
    return true;
  }

  router.navigate(['/auth/login'], { queryParams: { returnUrl: state.url } });
  return false;
};

export const roleGuard = (roles: Role[]): CanActivateFn => {
  return (route, state) => {
    const store = inject(AuthStore);
    const router = inject(Router);

    if (store.hasRole(roles)) {
      return true;
    }

    router.navigate(['/']);
    return false;
  };
};
