import { useState } from 'react';
import type { DataRecord } from '@nubit/crud';

export const useCreditNoteSelectionState = () => {
  const [editDisabled, setEditDisabled] = useState(true);
  const [deleteDisabled, setDeleteDisabled] = useState(true);

  const handleSelectionChanged = (rows: DataRecord[]) => {
    const selectedRow = rows[0];
    const isPending = !!selectedRow && selectedRow.sunatStatus === undefined;
    setEditDisabled(!isPending);
    setDeleteDisabled(!isPending);
  };

  return {
    editDisabled,
    deleteDisabled,
    handleSelectionChanged,
  };
};
