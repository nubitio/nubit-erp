import { StorageUtils } from '../utils/StorageUtils';
import { User } from '../../types';

export const userInformation = () => {
  let user = StorageUtils.getItem('user') as User;
  const currentUserId = user.userId;
  const currentUserName = user.username;
  const currentUserRoleId = user.role;
  const currentBranchId = user.branchId;
  const currentBranchName = user.branchName;

  return {
    currentUserId,
    currentUserName,
    currentUserRoleId,
    currentBranchId,
    currentBranchName,
  };
};
