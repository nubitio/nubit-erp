import { httpClient } from '../core/http/HttpClient';
import { Constants } from '../core/common/Constants';
import { StorageUtils } from '../core/utils/StorageUtils';

class BranchService {
  private static readonly STORAGE_KEY = 'selectedBranchId';

  getAll() {
    return httpClient.get<any>(`${Constants.API_BASE_URL}branches`);
  }

  /**
   * Get the currently selected branch ID
   */
  getSelectedBranchId(): number | null {
    const branchId = StorageUtils.getItem(BranchService.STORAGE_KEY);
    return branchId ? parseInt(branchId, 10) : null;
  }

  /**
   * Set the selected branch ID
   * This will be automatically included in all HTTP requests via the X-Branch-Id header
   */
  setSelectedBranchId(branchId: number): void {
    StorageUtils.setItem(BranchService.STORAGE_KEY, branchId.toString());
  }

  /**
   * Clear the selected branch ID
   */
  clearSelectedBranchId(): void {
    StorageUtils.removeItem(BranchService.STORAGE_KEY);
  }

  /**
   * Check if a branch is selected
   */
  hasSelectedBranch(): boolean {
    return this.getSelectedBranchId() !== null;
  }
}

export const branchService = new BranchService();
