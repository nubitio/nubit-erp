import {StorageUtils} from "../utils/StorageUtils";

export const userInformation = () => {
    let session = StorageUtils.getItem('session');
    let currentUserId = session['user_id'];
    let currentUserName = session['username'];
    let currentUserRoleId = session['role_id'];
    let currentBranchId = session['branch_id'];
    let currentBranchName = session['branch_name'];
    let currentWarehouseId = session['warehouse_id'];

    return {
        currentUserId,
        currentUserName,
        currentUserRoleId,
        currentBranchId,
        currentBranchName,
        currentWarehouseId
    }
}
