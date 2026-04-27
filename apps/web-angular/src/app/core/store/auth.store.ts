import { Injectable, signal, computed } from '@angular/core';
import { AuthState, User, Role } from '../models/auth.models';

@Injectable({
  providedIn: 'root'
})
export class AuthStore {
  private state = signal<AuthState>({
    user: null,
    token: localStorage.getItem('elyo_token'),
    loading: false
  });

  user = computed(() => this.state().user);
  token = computed(() => this.state().token);
  loading = computed(() => this.state().loading);
  isAuthenticated = computed(() => !!this.state().user);
  role = computed(() => this.state().user?.role);

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

  setLoading(loading: boolean) {
    this.state.update(s => ({ ...s, loading }));
  }

  hasRole(roles: Role[]): boolean {
    const userRole = this.role();
    return !!userRole && roles.includes(userRole);
  }
}
