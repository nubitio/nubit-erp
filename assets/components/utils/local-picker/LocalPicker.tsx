import './LocalPicker.scss';
import { Popup, PopupRef, ToolbarItem } from 'devextreme-react/popup';
import List from 'devextreme-react/list';
import { Button } from 'devextreme-react';
import { useEffect, useRef, useState } from 'react';
import { useScreenSize } from '../../../utils/media-query';
import ArrayStore from 'devextreme/data/array_store';
import { useNavigate } from 'react-router-dom';
import { authService } from '../../../service/auth.service';
import { branchService } from '../../../service/branch.service';
import toast from '../../../core/utils/SharedUtils';
import { StorageUtils } from '../../../core/utils/StorageUtils';

export const LocalPicker = ({ visible, setVisible, username, password }) => {
  const ref = useRef<PopupRef>(null);
  const { isXSmall } = useScreenSize();
  const [dataSource, setDataSource] = useState<ArrayStore | null>(null);
  const [selectedBranch, setSelectedBranch] = useState<string | null>(null);
  const navigate = useNavigate();

  const onItemClick = (e: any) => {
    setSelectedBranch(e.itemData.id);
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
    authService.login(username, password, selectedBranch).subscribe((response) => {
      if (response.data.user) {
        StorageUtils.setItem('user', response.data.user);
      }
      close();
      window.location.href = '/';
    });
  };

  let alreadySetup = false;

  useEffect(() => {
    if (!alreadySetup) {
      branchService.getAll().subscribe((response) => {
        setDataSource(
          new ArrayStore({
            key: 'id',
            data: response.data,
          }),
        );
      });
      alreadySetup = true;
    }
  }, []);

  return (
    <Popup
      ref={ref}
      title="Seleccionar local"
      width={480}
      height={360}
      fullScreen={isXSmall}
      visible={visible}
      showCloseButton={false}
      wrapperAttr={{ class: `picker-popup` }}
    >
      <ToolbarItem toolbar="bottom" location="after">
        <div className={`picker-popup-buttons-container`}>
          <Button text="Cancelar" stylingMode="outlined" onClick={onCancelClick} />
          <Button text="Aceptar" stylingMode="outlined" type="default" onClick={onAcceptClick} />
        </div>
      </ToolbarItem>
      <List
        dataSource={dataSource}
        displayExpr={'name'}
        selectByClick={true}
        onItemClick={onItemClick}
      />
    </Popup>
  );
};
