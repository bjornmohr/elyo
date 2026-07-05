import { Routes } from '@angular/router';
import { authGuard, featureFlagGuard, portalGuard } from './core/guards/auth.guards';

export const routes: Routes = [
  // Auth (public)
  {
    path: 'auth',
    children: [
      { path: 'login', loadComponent: () => import('./features/auth/login/login.component').then(m => m.LoginComponent) },
      { path: 'register', loadComponent: () => import('./features/auth/register/register.component').then(m => m.RegisterComponent) },
      { path: 'invite/:token', loadComponent: () => import('./features/auth/invite/invite.component').then(m => m.InviteComponent) },
      { path: 'invite', redirectTo: 'login', pathMatch: 'full' },
    ]
  },

  // Employee portal
  {
    path: 'employee',
    canActivate: [authGuard, portalGuard('employee')],
    loadComponent: () => import('./shared/shells/employee-shell.component').then(m => m.EmployeeShellComponent),
    children: [
      { path: 'dashboard', loadComponent: () => import('./features/employee/pages/dashboard/dashboard.component').then(m => m.DashboardComponent) },
      { path: 'checkin', loadComponent: () => import('./features/employee/pages/checkin/checkin-stepper.component').then(m => m.CheckinStepperComponent) },
      { path: 'checkin/chat', loadComponent: () => import('./features/employee/pages/checkin/checkin-chat.component').then(m => m.CheckinChatComponent) },
      { path: 'history', loadComponent: () => import('./features/employee/pages/history/history.component').then(m => m.HistoryComponent) },
      { path: 'profile', loadComponent: () => import('./features/employee/pages/profile/profile.component').then(m => m.ProfileComponent) },
      { path: 'surveys', loadComponent: () => import('./features/employee/pages/surveys/surveys.component').then(m => m.SurveysComponent) },
      { path: 'measures', loadComponent: () => import('./features/employee/pages/measures/measures.component').then(m => m.EmployeeMeasuresComponent) },
      { path: 'measures/:id', loadComponent: () => import('./features/employee/pages/measure-detail/measure-detail.component').then(m => m.EmployeeMeasureDetailComponent) },
      { path: 'measures/:id/exercise/:position', loadComponent: () => import('./features/employee/pages/measure-exercise/measure-exercise.component').then(m => m.EmployeeMeasureExerciseComponent) },
      { path: 'measure-checkins/:token', loadComponent: () => import('./features/employee/pages/measure-checkin/measure-checkin.component').then(m => m.EmployeeMeasureCheckinComponent) },
      { path: 'lab-markers', loadComponent: () => import('./features/employee/pages/lab-markers/lab-markers.component').then(m => m.EmployeeLabMarkersComponent) },
      { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
    ]
  },

  // Company portal
  {
    path: 'company',
    canActivate: [authGuard, portalGuard('company')],
    loadComponent: () => import('./shared/shells/company-shell.component').then(m => m.CompanyShellComponent),
    children: [
      { path: 'dashboard', loadComponent: () => import('./features/company/pages/dashboard/dashboard.component').then(m => m.CompanyDashboardComponent) },
      { path: 'users', loadComponent: () => import('./features/company/pages/users/company-users.component').then(m => m.CompanyUsersComponent) },
      { path: 'invitations', loadComponent: () => import('./features/company/pages/invitations/company-invitations.component').then(m => m.CompanyInvitationsComponent) },
      { path: 'teams', loadComponent: () => import('./features/company/pages/teams/company-teams.component').then(m => m.CompanyTeamsComponent) },
      { path: 'surveys', loadComponent: () => import('./features/company/pages/surveys/company-surveys.component').then(m => m.CompanySurveysComponent) },
      { path: 'measures/statistics', loadComponent: () => import('./features/company/pages/measure-statistics/company-measure-statistics.component').then(m => m.CompanyMeasureStatisticsComponent) },
      { path: 'measures', loadComponent: () => import('./features/company/pages/measures/company-measures.component').then(m => m.CompanyMeasuresComponent) },
      { path: 'risk-landscape', canActivate: [featureFlagGuard('riskLandscapeEnabled')], loadComponent: () => import('./features/company/pages/risk-landscape/company-risk-landscape.component').then(m => m.CompanyRiskLandscapeComponent) },
      { path: 'usage-funnel', canActivate: [featureFlagGuard('usageFunnelEnabled')], loadComponent: () => import('./features/company/pages/usage-funnel/company-usage-funnel.component').then(m => m.CompanyUsageFunnelComponent) },
      { path: 'infection-radar', canActivate: [featureFlagGuard('infectionRadarEnabled')], loadComponent: () => import('./features/company/pages/infection-radar/company-infection-radar.component').then(m => m.CompanyInfectionRadarComponent) },
      { path: 'reports', loadComponent: () => import('./features/company/pages/reports/company-reports.component').then(m => m.CompanyReportsComponent) },
      { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
    ]
  },

  // Admin portal
  {
    path: 'admin',
    canActivate: [authGuard, portalGuard('admin')],
    loadComponent: () => import('./shared/shells/admin-shell.component').then(m => m.AdminShellComponent),
    children: [
      { path: 'companies', loadComponent: () => import('./features/admin/pages/companies/companies.component').then(m => m.AdminCompaniesComponent) },
      { path: 'companies/create', loadComponent: () => import('./features/admin/pages/companies-create/companies-create.component').then(m => m.AdminCompaniesCreateComponent) },
      { path: 'users', loadComponent: () => import('./features/admin/pages/users/admin-users.component').then(m => m.AdminUsersComponent) },
      { path: 'points', loadComponent: () => import('./features/admin/pages/points/admin-points.component').then(m => m.AdminPointsComponent) },
      { path: 'system-exercises', loadComponent: () => import('./features/admin/pages/system-exercises/admin-system-exercises.component').then(m => m.AdminSystemExercisesComponent) },
      { path: 'system-measure-templates', loadComponent: () => import('./features/admin/pages/system-measure-templates/admin-system-measure-templates.component').then(m => m.AdminSystemMeasureTemplatesComponent) },
      { path: '', redirectTo: 'companies', pathMatch: 'full' },
    ]
  },

  // Partner portal
  {
    path: 'partner',
    canActivate: [authGuard, portalGuard('partner')],
    children: [
      { path: 'dashboard', loadComponent: () => import('./features/partner/pages/dashboard/partner-dashboard.component').then(m => m.PartnerDashboardComponent) },
      { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
    ]
  },

  // Default
  { path: '', redirectTo: '/auth/login', pathMatch: 'full' },
  { path: '**', redirectTo: '/auth/login' },
];
