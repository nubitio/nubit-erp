import { User } from '../types';
import { StorageUtils } from '../shared/lib/StorageUtils';
import { branchService } from '../features/branch';

/* eslint-disable @typescript-eslint/no-unused-vars */
export async function signIn(email: string, password: string) {
  const defaultUser = {
    userId: 1,
    username: 'admin',
    email: 'demo@demo.com',
    name: 'Admin',
    role: 0,
    roles: ['admin'],
    warehouseId: 1,
    avatarUrl: '',
  };
  try {
    // Send request
    return {
      isOk: true,
      data: { ...defaultUser, email },
    };
  } catch {
    return {
      isOk: false,
      message: 'Authentication failed',
    };
  }
}

export async function getUser() {
  if (!branchService.hasSelectedBranch()) {
    return undefined;
  }
  return StorageUtils.getItem('user') as User;
}

export async function createAccount(email: string, password: string) {
  try {
    // Send request
    return {
      isOk: true,
    };
  } catch {
    return {
      isOk: false,
      message: 'Failed to create account',
    };
  }
}

export async function changePassword(email: string, recoveryCode?: string) {
  try {
    // Send request
    return {
      isOk: true,
      data: { email },
    };
  } catch {
    return {
      isOk: false,
      message: 'Failed to change password',
    };
  }
}

export async function resetPassword(email: string) {
  try {
    // Send request
    return {
      isOk: true,
    };
  } catch {
    return {
      isOk: false,
      message: 'Failed to reset password',
    };
  }
}
