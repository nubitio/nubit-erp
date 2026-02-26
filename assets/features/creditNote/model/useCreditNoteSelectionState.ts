import { useState } from 'react';

type GridSelectionEvent = {
  component: {
    getSelectedRowsData: () => any[];
  };
};

export const useCreditNoteSelectionState = () => {
  const [editDisabled, setEditDisabled] = useState(true);
  const [deleteDisabled, setDeleteDisabled] = useState(true);

  const handleSelectionChanged = (e: GridSelectionEvent) => {
    const selectedRow = e.component.getSelectedRowsData()[0];
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
