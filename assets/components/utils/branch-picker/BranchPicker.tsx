import './BranchPicker.scss';
import { Popup, PopupRef, ToolbarItem } from 'devextreme-react/popup';
import { Button } from 'devextreme-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useScreenSize } from '../../../utils/media-query';
import ArrayStore from 'devextreme/data/array_store';
import { branchService } from '../../../features/branch';
import toast from '../../../shared/lib/SharedUtils';
import List from 'devextreme-react/list';
import { authService } from '../../../features/auth';

interface Branch {
  id: string;
  name: string;
}

interface BranchPickerProps {
  visible: boolean;
  setVisible: (value: boolean) => void;
  initialBranches?: Branch[];
}

export const BranchPicker = ({ visible, setVisible, initialBranches = [] }: BranchPickerProps) => {
  const ref = useRef<PopupRef>(null);
  const { isXSmall } = useScreenSize();
  const [dataSource, setDataSource] = useState<ArrayStore | null>(null);
  const [selectedBranch, setSelectedBranch] = useState<Branch | null>(null);
  const [selecting, setSelecting] = useState(false);
  const [loadingBranches, setLoadingBranches] = useState(false);
  const [hasLoaded, setHasLoaded] = useState(false);

  const onSelectionChanged = (e: any) => {
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
        window.location.href = '/';
      },
      error: () => {
        setSelecting(false);
      },
      complete: () => {
        setSelecting(false);
      },
    });
  }, [selectedBranch, selecting, close]);

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

    // eslint-disable-next-line react-hooks/set-state-in-effect -- loadBranchData calls setState; this is data loading on visibility change
    loadBranchData(initialBranches);
  }, [visible, initialBranches, loadBranchData]);

  useEffect(() => {
    if (!visible || hasLoaded) {
      return;
    }
    // eslint-disable-next-line react-hooks/set-state-in-effect -- setting loading flag before async operation; standard data-fetch pattern
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
      // eslint-disable-next-line react-hooks/set-state-in-effect -- resetting selection state on hide; intentional cleanup pattern
      setSelectedBranch(null);
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
      wrapperAttr={{ class: `picker-popup` }}
    >
      <ToolbarItem toolbar="bottom" location="after">
        <div className={`picker-popup-buttons-container`}>
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
        selectionMode={'single'}
        displayExpr={'name'}
        selectByClick={true}
        noDataText={loadingBranches ? 'Cargando locales...' : 'No hay locales disponibles'}
        onSelectionChanged={onSelectionChanged}
        selectedItemKeys={selectedBranch ? [selectedBranch.id] : []}
        itemRender={(item: Branch) => (
          <span data-testid="branch-picker-option" data-branch-id={item.id}>
            {item.name}
          </span>
        )}
      />
    </Popup>
  );
};
