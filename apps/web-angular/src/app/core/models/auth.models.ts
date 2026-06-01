export enum Role {
  ELYO_ADMIN = 'ELYO_ADMIN',
  ELYO_SUPPORT = 'ELYO_SUPPORT',
  COMPANY_OWNER = 'COMPANY_OWNER',
  COMPANY_ADMIN = 'COMPANY_ADMIN',
  COMPANY_MANAGER = 'COMPANY_MANAGER',
  EMPLOYEE = 'EMPLOYEE',
  PARTNER = 'PARTNER',
}

export type Portal = 'admin' | 'company' | 'employee' | 'partner';

export interface User {
  id: number;
  email: string;
  name: string;
  roles: string[];
  companyId?: number | null;
  companyName?: string | null;
  teamLayerEnabled?: boolean;
}

export interface LoginResponse {
  access_token: string;
  token_type: string;
  user: User;
  activePortal: Portal | null;
  allowedPortals: Portal[];
}

export interface MeResponse extends User {
  activePortal: Portal | null;
  allowedPortals: Portal[];
}

export interface AuthState {
  user: User | null;
  token: string | null;
  activePortal: Portal | null;
  allowedPortals: Portal[];
  loading: boolean;
}
