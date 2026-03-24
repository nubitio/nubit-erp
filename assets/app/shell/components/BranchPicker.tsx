import { useCallback, useEffect, useRef, useState } from 'react';
import { flushSync } from 'react-dom';
import { useNavigate } from 'react-router-dom';
import { Button } from 'devextreme-react';
import List from 'devextreme-react/list';
import { Popup, PopupRef, ToolbarItem } from 'devextreme-react/popup';
import ArrayStore from 'devextreme/data/array_store';

import { authService } from '@features/auth';
import { branchService } from '@features/branch';
import { resolveBranchSelectionRedirect, resolvePostLoginRedirect } from '@shared/lib/authRedirect';
import { requestAuthSessionSync } from '@shared/lib/authSessionSync';
import toast from '@shared/lib/SharedUtils';
import { useScreenSize } from '@utils/media-query';

import '../../../shared/ui/utils/branch-picker/BranchPicker.scss';

interface Branch {
  id: string;
  name: string;
}

interface BranchPickerProps {
  visible: boolean;
  setVisible: (value: boolean) => void;
  initialBranches?: Branch[];
  redirectPath?: string;
}

export const BranchPicker = ({
  visible,
  setVisible,
  initialBranches = [],
  redirectPath,
}: BranchPickerProps) => {
  const ref = useRef<PopupRef>(null);
  const navigate = useNavigate();
  const { isXSmall } = useScreenSize();
  const [dataSource, setDataSource] = useState<ArrayStore | null>(null);
  const [selectedBranch, setSelectedBranch] = useState<Branch | null>(null);
  const [selecting, setSelecting] = useState(false);
  const [loadingBranches, setLoadingBranches] = useState(false);
  const [hasLoaded, setHasLoaded] = useState(false);

  const onSelectionChanged = (e: { addedItems?: Branch[] }) => {
    setSelectedBranch((e.addedItems?.[0] as Branch) ?? null);
  };

  const close = useCallback(() => {
    setVisible(false);
  }, [setVisible]);

  const onCancelClick = useCallback(() => {
    close();
  }, [close]);

  const onAcceptClick = useCallback(() => {
    if (selectedBranch == null || selecting) {
      toast('Debe seleccionar un local', 'error');
      return;
    }

    setSelecting(true);
    authService.selectBranch(selectedBranch.id).subscribe({
      next: () => {
        branchService.setSelectedBranchId(selectedBranch.id);
        branchService.setSelectedBranchName(selectedBranch.name);
        close();
        const nextPath = redirectPath
          ? resolvePostLoginRedirect(redirectPath)
          : resolveBranchSelectionRedirect(undefined, window.location.search, window.location.hash);

        flushSync(() => {
          requestAuthSessionSync();
        });

        navigate(nextPath, { replace: true });
      },
      error: () => {
        setSelecting(false);
      },
      complete: () => {
        setSelecting(false);
      },
    });
  }, [selectedBranch, selecting, close, navigate, redirectPath]);

  const loadBranchData = useCallback((data: Branch[]) => {
    setDataSource(
      new ArrayStore({
        key: 'id',
        data,
      }),
    );

    const lastBranchId = branchService.getSelectedBranchId();
    const rememberedBranch = data.find((item) => item.id === lastBranchId) ?? null;

    if (rememberedBranch) {
      setSelectedBranch(rememberedBranch);
    }

    setHasLoaded(true);
    setLoadingBranches(false);
  }, []);

  useEffect(() => {
    if (!visible || initialBranches.length === 0) {
      return;
    }

    // eslint-disable-next-line react-hooks/set-state-in-effect -- loading initial branch options into local popup state is intentional during visibility sync
    loadBranchData(initialBranches);
  }, [visible, initialBranches, loadBranchData]);

  useEffect(() => {
    if (!visible || hasLoaded) {
      return;
    }

    // eslint-disable-next-line react-hooks/set-state-in-effect -- setting loading flag before async branch fetch is the expected control flow here
    setLoadingBranches(true);

    const subscription = branchService.getAll().subscribe({
      next: (response) => {
        const data: Branch[] = response?.data ?? [];
        loadBranchData(data);
      },
      error: () => {
        setHasLoaded(false);
        setLoadingBranches(false);
      },
    });

    return () => {
      subscription?.unsubscribe?.();
    };
  }, [visible, hasLoaded, loadBranchData]);

  useEffect(() => {
    if (!visible) {
      // eslint-disable-next-line react-hooks/set-state-in-effect -- reset local popup state when the shell picker closes
      setSelectedBranch(null);
      // eslint-disable-next-line react-hooks/set-state-in-effect -- reset cached visibility state when the shell picker closes
      setHasLoaded(false);
    }
  }, [visible]);

  return (
    <Popup
      ref={ref}
      title="Seleccionar local"
      width={480}
      height={360}
      fullScreen={isXSmall}
      visible={visible}
      showCloseButton={false}
      hideOnOutsideClick={!selecting}
      wrapperAttr={{ class: 'picker-popup' }}
    >
      <ToolbarItem toolbar="bottom" location="after">
        <div className="picker-popup-buttons-container">
          <Button
            text="Cancelar"
            stylingMode="outlined"
            onClick={onCancelClick}
            disabled={selecting}
          />
          <Button
            text="Aceptar"
            stylingMode="outlined"
            type="default"
            onClick={onAcceptClick}
            disabled={selecting}
          />
        </div>
      </ToolbarItem>
      <List
        dataSource={dataSource}
        showSelectionControls={true}
        selectionMode="single"
        displayExpr="name"
        selectByClick={true}
        noDataText={loadingBranches ? 'Cargando locales...' : 'No hay locales disponibles'}
        onSelectionChanged={onSelectionChanged}
        selectedItemKeys={selectedBranch ? [selectedBranch.id] : []}
        repaintChangesOnly={true}
        itemRender={(item: Branch) => (
          <span data-testid="branch-picker-option" data-branch-id={item.id}>
            {item.name}
          </span>
        )}
      />
    </Popup>
  );
};
