import { Routes } from '@angular/router';
import { authGuard, roleGuard } from './core/guards/auth.guards';
import { Role } from './core/models/auth.models';
import { PlaceholderComponent } from './features/placeholder.component';

export const routes: Routes = [
  {
    path: 'auth',
    children: [
      { path: 'login', component: PlaceholderComponent },
      { path: 'register', component: PlaceholderComponent },
      { path: 'invite', component: PlaceholderComponent },
    ]
  },
  {
    path: 'employee',
    canActivate: [authGuard, roleGuard([Role.EMPLOYEE])],
    children: [
      { path: '', loadComponent: () => import('./features/employee/pages/dashboard/dashboard.component').then(m => m.DashboardComponent) },
      { path: 'checkin', loadComponent: () => import('./features/employee/pages/checkin/checkin.component').then(m => m.CheckinComponent) },
      { path: 'history', loadComponent: () => import('./features/employee/pages/history/history.component').then(m => m.HistoryComponent) },
      { path: 'profile', loadComponent: () => import('./features/employee/pages/profile/profile.component').then(m => m.ProfileComponent) },
      { path: 'surveys', loadComponent: () => import('./features/employee/pages/surveys/surveys.component').then(m => m.SurveysComponent) },
    ]
  },
  {
    path: 'company',
    canActivate: [authGuard, roleGuard([Role.COMPANY_ADMIN, Role.COMPANY_MANAGER])],
    component: PlaceholderComponent
  },
  {
    path: 'partner',
    canActivate: [authGuard, roleGuard([Role.PARTNER])],
    component: PlaceholderComponent
  },
  {
    path: 'admin',
    canActivate: [authGuard, roleGuard([Role.ELYO_ADMIN])],
    component: PlaceholderComponent
  },
  { path: '', redirectTo: '/auth/login', pathMatch: 'full' },
  { path: '**', redirectTo: '/auth/login' }
];
