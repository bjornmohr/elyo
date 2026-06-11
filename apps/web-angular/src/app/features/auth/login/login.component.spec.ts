import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, Router, convertToParamMap } from '@angular/router';
import { of, throwError } from 'rxjs';
import { AuthService } from '../../../core/services/auth.service';
import { AuthStore } from '../../../core/store/auth.store';
import { LoginComponent } from './login.component';

describe('LoginComponent', () => {
  let authService: {
    login: ReturnType<typeof vi.fn>;
    getMe: ReturnType<typeof vi.fn>;
    detectPortalFromHostname: ReturnType<typeof vi.fn>;
    resolvePortal: ReturnType<typeof vi.fn>;
    isAllowedPortalUrl: ReturnType<typeof vi.fn>;
    getDefaultRoute: ReturnType<typeof vi.fn>;
  };

  beforeEach(async () => {
    authService = {
      login: vi.fn(() => of({
        access_token: 'token',
        token_type: 'Bearer',
        user: {},
        activePortal: 'employee',
        allowedPortals: ['employee'],
      })),
      getMe: vi.fn(() => of(null)),
      detectPortalFromHostname: vi.fn(() => null),
      resolvePortal: vi.fn(() => 'employee'),
      isAllowedPortalUrl: vi.fn(() => false),
      getDefaultRoute: vi.fn(() => '/employee/dashboard'),
    };

    await TestBed.configureTestingModule({
      imports: [LoginComponent],
      providers: [
        { provide: AuthService, useValue: authService },
        {
          provide: AuthStore,
          useValue: {
            isAuthenticated: () => false,
            token: () => null,
            activePortal: () => 'employee',
            allowedPortals: () => ['employee'],
          },
        },
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              queryParamMap: convertToParamMap({}),
            },
          },
        },
        {
          provide: Router,
          useValue: {
            navigateByUrl: vi.fn(),
          },
        },
      ],
    }).compileComponents();
  });

  it('shows required field errors and does not call login on empty submit', () => {
    const fixture = TestBed.createComponent(LoginComponent);
    fixture.detectChanges();

    fixture.componentInstance.onSubmit();
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('E-Mail ist erforderlich.');
    expect(fixture.nativeElement.textContent).toContain('Passwort ist erforderlich.');
    expect(authService.login).not.toHaveBeenCalled();
  });

  it('shows invalid email format feedback', () => {
    const fixture = TestBed.createComponent(LoginComponent);
    fixture.detectChanges();

    fixture.componentInstance.loginForm.patchValue({
      email: 'not-an-email',
      password: 'password',
    });
    fixture.componentInstance.onSubmit();
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Bitte eine gültige E-Mail-Adresse eingeben.');
    expect(authService.login).not.toHaveBeenCalled();
  });

  it('shows a general error for invalid credentials', () => {
    authService.login.mockReturnValue(throwError(() => ({
      status: 422,
      error: {
        errors: {
          email: ['Die Anmeldedaten sind ungültig.'],
        },
      },
    })));
    const fixture = TestBed.createComponent(LoginComponent);
    fixture.detectChanges();

    fixture.componentInstance.loginForm.patchValue({
      email: 'employee@example.test',
      password: 'wrong-password',
    });
    fixture.componentInstance.onSubmit();
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('E-Mail oder Passwort ist ungültig.');
  });

  it('shows backend validation errors on fields when possible', () => {
    authService.login.mockReturnValue(throwError(() => ({
      status: 422,
      error: {
        errors: {
          email: ['Die E-Mail ist ungültig.'],
        },
      },
    })));
    const fixture = TestBed.createComponent(LoginComponent);
    fixture.detectChanges();

    fixture.componentInstance.loginForm.patchValue({
      email: 'employee@example.test',
      password: 'password',
    });
    fixture.componentInstance.onSubmit();
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Die E-Mail ist ungültig.');
  });
});
