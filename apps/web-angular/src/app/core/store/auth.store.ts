import { Injectable, signal, computed } from '@angular/core';
import { AuthState, User, Role, Portal } from '../models/auth.models';

@Injectable({
  providedIn: 'root'
})
export class AuthStore {
  private state = signal<AuthState>({
    user: null,
    token: localStorage.getItem('elyo_token'),
    activePortal: null,
    allowedPortals: [],
    loading: false,
  });

  user = computed(() => this.state().user);
  token = computed(() => this.state().token);
  loading = computed(() => this.state().loading);
  isAuthenticated = computed(() => !!this.state().user);
  activePortal = computed(() => this.state().activePortal);
  allowedPortals = computed(() => this.state().allowedPortals);
  roles = computed(() => this.state().user?.roles ?? []);
  teamLayerEnabled = computed(() => this.state().user?.teamLayerEnabled ?? false);

  setUser(user: User | null) {
    this.state.update(s => ({ ...s, user }));
  }

  setToken(token: string | null) {
    if (token) {
      localStorage.setItem('elyo_token', token);
    } else {
      localStorage.removeItem('elyo_token');
    }
    this.state.update(s => ({ ...s, token }));
  }

  setActivePortal(portal: Portal | null) {
    this.state.update(s => ({ ...s, activePortal: portal }));
  }

  setAllowedPortals(portals: Portal[]) {
    this.state.update(s => ({ ...s, allowedPortals: portals }));
  }

  setLoading(loading: boolean) {
    this.state.update(s => ({ ...s, loading }));
  }

  hasRole(roles: Role[]): boolean {
    const userRoles = this.roles();
    return userRoles.some(r => roles.includes(r as Role));
  }

  clear() {
    localStorage.removeItem('elyo_token');
    this.state.set({
      user: null,
      token: null,
      activePortal: null,
      allowedPortals: [],
      loading: false,
    });
  }
}
