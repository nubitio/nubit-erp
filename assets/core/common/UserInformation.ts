import { StorageUtils } from '../utils/StorageUtils';
import { User } from '../../types';
import { branchService } from '../../service/branch.service';

export const userInformation = () => {
  const user = StorageUtils.getItem('user') as User;
  const currentUserId = user.userId;
  const currentUserName = user.username;
  const currentUserRoleId = user.role;
  const currentBranchId = branchService.getSelectedBranchId() ?? '';
  const currentBranchName = branchService.getSelectedBranchName() ?? '';

  return {
    currentUserId,
    currentUserName,
    currentUserRoleId,
    currentBranchId,
    currentBranchName,
  };
};
