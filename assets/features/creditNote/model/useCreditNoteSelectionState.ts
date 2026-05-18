import { useState } from 'react';
import type { DataGridSelectionChangedEvent } from '@core/datagrid/DataGridViewOptions';

export const useCreditNoteSelectionState = () => {
  const [editDisabled, setEditDisabled] = useState(true);
  const [deleteDisabled, setDeleteDisabled] = useState(true);

  const handleSelectionChanged = (e: DataGridSelectionChangedEvent) => {
    const selectedRow = e.selectedRowsData?.[0];
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
