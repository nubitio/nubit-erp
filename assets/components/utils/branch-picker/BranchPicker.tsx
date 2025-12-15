import './BranchPicker.scss';
import {Popup, PopupRef, ToolbarItem} from 'devextreme-react/popup';
import {Button} from 'devextreme-react';
import {useEffect, useRef, useState} from 'react';
import {useScreenSize} from '../../../utils/media-query';
import ArrayStore from 'devextreme/data/array_store';
import {branchService} from '../../../service/branch.service';
import toast from '../../../core/utils/SharedUtils';
import List from 'devextreme-react/list';
import {authService} from '../../../service/auth.service';

export const BranchPicker = ({visible, setVisible}) => {
  const ref = useRef<PopupRef>(null);
  const {isXSmall} = useScreenSize();
  const [dataSource, setDataSource] = useState<ArrayStore | null>(null);
  const [selectedBranch, setSelectedBranch] = useState<any>(null);
  const [selecting, setSelecting] = useState(false);
  const [hasLoaded, setHasLoaded] = useState(false);

  const onItemClick = (e: any) => {
    setSelectedBranch(e.itemData);
  };

  const close = () => {
    setVisible(false);
  };

  const onCancelClick = () => {
    close();
  };

  const onAcceptClick = () => {
    if (selectedBranch == null) {
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
      }
    });
  };

  useEffect(() => {
    if (!visible || hasLoaded) {
      return;
    }

    const subscription = branchService.getAll().subscribe({
      next: (response) => {
        const data = response?.data ?? [];
        setDataSource(
          new ArrayStore({
            key: 'id',
            data: data,
          }),
        );

        if (Array.isArray(data) && data.length === 1) {
          setSelectedBranch(data[0]);
        }

        setHasLoaded(true);
      },
      error: () => {
        setHasLoaded(false);
      },
    });

    return () => {
      subscription?.unsubscribe?.();
    };
  }, [visible, hasLoaded]);

  return (
    <Popup
      ref={ref}
      title="Seleccionar local"
      width={480}
      height={360}
      fullScreen={isXSmall}
      visible={visible}
      showCloseButton={false}
      wrapperAttr={{class: `picker-popup`}}
    >
      <ToolbarItem toolbar="bottom" location="after">
        <div className={`picker-popup-buttons-container`}>
          <Button text="Cancelar" stylingMode="outlined" onClick={onCancelClick} disabled={selecting}/>
          <Button text="Aceptar" stylingMode="outlined" type="default" onClick={onAcceptClick} disabled={selecting}/>
        </div>
      </ToolbarItem>
      <List
        dataSource={dataSource}
        showSelectionControls={true}
        selectionMode={'single'}
        displayExpr={'name'}
        selectByClick={true}
        onItemClick={onItemClick}
        // Reflect controlled selection so the single item appears selected when auto-set
        selectedItemKeys={selectedBranch ? [selectedBranch.id] : []}
      />
    </Popup>
  );
};
