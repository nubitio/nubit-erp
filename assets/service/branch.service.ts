import {httpClient} from '../core/http/HttpClient';
import {Constants} from '../core/common/Constants';
import {StorageUtils} from '../core/utils/StorageUtils';

class BranchService {
  private static readonly SELECTED_BRANCH_ID = 'selectedBranchId';
  private static readonly SELECTED_BRANCH_NAME = 'selectedBranchName';

  getAll() {
    return httpClient.get<any>(`${Constants.API_BASE_URL}branches`);
  }

  /**
   * Get the currently selected branch ID
   */
  getSelectedBranchId(): string | null {
    return StorageUtils.getItem(BranchService.SELECTED_BRANCH_ID);
  }

  /**
   * Set the selected branch ID
   * This will be automatically included in all HTTP requests via the X-Branch-Id header
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
    return StorageUtils.getItem(BranchService.SELECTED_BRANCH_NAME);
  }

  clearSelectedBranchName(): void {
    StorageUtils.removeItem(BranchService.SELECTED_BRANCH_NAME);
  }

  hasSelectedBranchName(): boolean {
    return this.getSelectedBranchName() !== null;
  }
}

export const branchService = new BranchService();
