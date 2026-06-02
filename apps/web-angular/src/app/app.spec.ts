import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of } from 'rxjs';
import { ApiClient } from './core/services/api-client.service';
import { AuthService } from './core/services/auth.service';
import { AuthStore } from './core/store/auth.store';
import { AppComponent } from './app';

describe('AppComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AppComponent],
      providers: [
        provideRouter([]),
        {
          provide: ApiClient,
          useValue: {
            get: vi.fn(() => of({})),
          },
        },
        {
          provide: AuthService,
          useValue: {
            getMe: vi.fn(() => of(null)),
            logout: vi.fn(),
            getDefaultRoute: vi.fn(() => '/employee/dashboard'),
          },
        },
        {
          provide: AuthStore,
          useValue: {
            isAuthenticated: () => false,
            allowedPortals: () => [],
            activePortal: () => null,
            user: () => null,
            setActivePortal: vi.fn(),
          },
        },
      ],
    }).compileComponents();
  });

  it('should create the app', () => {
    const fixture = TestBed.createComponent(AppComponent);
    const app = fixture.componentInstance;
    expect(app).toBeTruthy();
  });
});
