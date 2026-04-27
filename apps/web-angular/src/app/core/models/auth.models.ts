export enum Role {
  EMPLOYEE = 'EMPLOYEE',
  COMPANY_MANAGER = 'COMPANY_MANAGER',
  COMPANY_ADMIN = 'COMPANY_ADMIN',
  ELYO_ADMIN = 'ELYO_ADMIN',
  PARTNER = 'PARTNER'
}

export interface User {
  id: string;
  email: string;
  name: string;
  role: Role;
  companyId?: string;
}

export interface AuthState {
  user: User | null;
  token: string | null;
  loading: boolean;
}
