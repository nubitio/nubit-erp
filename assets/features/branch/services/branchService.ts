import { httpClient } from '@shared/api/HttpClient';
import { getCoreApiBaseUrl } from '@nubitio/core';
import { StorageUtils } from '../../../shared/lib/StorageUtils';

const API = getCoreApiBaseUrl();

export interface OperatingBranch {
  id: string;
  name: string;
  isDefault: boolean;
}

class BranchService {
  private static readonly SELECTED_BRANCH_ID = 'selectedBranchId';
  private static readonly SELECTED_BRANCH_NAME = 'selectedBranchName';

  getOperatingBranches() {
    return httpClient.get<OperatingBranch[]>(`${API}auth/operating-branches`);
  }

  /**
   * Get the currently selected branch ID
   */
  getSelectedBranchId(): string | null {
    const branchId = StorageUtils.getItem(BranchService.SELECTED_BRANCH_ID);
    return typeof branchId === 'string' ? branchId : null;
  }

  /**
   * Set the selected branch ID (kept for UI context only)
   */
  setSelectedBranchId(branchId: string): void {
    StorageUtils.setItem(BranchService.SELECTED_BRANCH_ID, branchId.toString());
  }

  /**
   * Clear the selected branch ID
   */
  clearSelectedBranchId(): void {
    StorageUtils.removeItem(BranchService.SELECTED_BRANCH_ID);
  }

  /**
   * Check if a branch is selected
   */
  hasSelectedBranch(): boolean {
    return this.getSelectedBranchId() !== null;
  }

  setSelectedBranchName(branchName: string): void {
    StorageUtils.setItem(BranchService.SELECTED_BRANCH_NAME, branchName);
  }

  getSelectedBranchName(): string | null {
    const branchName = StorageUtils.getItem(BranchService.SELECTED_BRANCH_NAME);
    return typeof branchName === 'string' ? branchName : null;
  }

  clearSelectedBranchName(): void {
    StorageUtils.removeItem(BranchService.SELECTED_BRANCH_NAME);
  }

  hasSelectedBranchName(): boolean {
    return this.getSelectedBranchName() !== null;
  }
}

export const branchService = new BranchService();
