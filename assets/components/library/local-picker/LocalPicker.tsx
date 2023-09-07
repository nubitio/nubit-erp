import './LocalPicker.scss';
import {Popup, ToolbarItem} from "devextreme-react/popup";
import List from "devextreme-react/list";
import {Button} from "devextreme-react";
import {useEffect, useRef, useState} from "react";
import {authService} from "../../../services/auth.service";
import notify from "devextreme/ui/notify";
import {StorageUtils} from "../../../core/utils/StorageUtils";
import {useScreenSize} from "../../../utils/media-query";
import {branchService} from "../../../services/branch.service";
import ArrayStore from "devextreme/data/array_store";

export const LocalPicker = ({visible, setVisible}) => {
    const ref = useRef<Popup>(null)
    const {isXSmall} = useScreenSize();
    const [dataSource, setDataSource] = useState<ArrayStore | null>(null)
    const [selectedBranch, setSelectedBranch] = useState(null)

    const onItemClick = (e: any) => {
        setSelectedBranch(e.itemData.local_id)
    }

    const close = () => {
        setVisible(false)
    }

    const onCancelClick = () => {
        close()
        if (StorageUtils.getItem('session') == null) {
            window.location.href = '/auth/login';
        }
    }

    const onAcceptClick = () => {
        if (selectedBranch == null) {
            notify('Debe seleccionar un local')
            return
        }
        authService.setLocal(selectedBranch).subscribe((response) => {
            if (response.data.success) {
                StorageUtils.setItem('session', response.data.data);
                window.location.href = '/'
                close()
            }
        });
    }

    let alreadySetup = false

    useEffect(() => {
        if (!alreadySetup) {
            branchService.getAll().subscribe((response) => {
                setDataSource(
                    new ArrayStore({
                        key: 'local_id',
                        data: response.data,
                    })
                )
            })
            alreadySetup = true
        }
    }, [])

    return (
        <Popup
            ref={ref}
            title='Seleccionar local'
            width={400}
            height={300}
            fullScreen={isXSmall}
            visible={visible}
            showCloseButton={false}
            wrapperAttr={{class: `picker-popup`}}
        >
            <ToolbarItem
                toolbar='bottom'
                location='after'
            >
                <div className={`picker-popup-buttons-container`}>
                    <Button
                        text='Cancelar'
                        stylingMode='outlined'
                        onClick={onCancelClick}
                    />
                    <Button
                        text='Aceptar'
                        stylingMode='outlined'
                        type='default'
                        onClick={onAcceptClick}
                    />
                </div>
            </ToolbarItem>
            <List
                dataSource={dataSource}
                displayExpr={'descripcion'}
                selectByClick={true}
                onItemClick={onItemClick}
            />
        </Popup>
    );
}
