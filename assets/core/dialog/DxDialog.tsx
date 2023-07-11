import './DxDialog.scss';
import React, {forwardRef, useCallback, useEffect, useImperativeHandle, useState} from 'react';

import {Popup, ToolbarItem} from 'devextreme-react/popup';
import {useScreenSize} from '../../utils/media-query';
import {Button} from 'devextreme-react';
import {bus} from "../event/EventBus";

export interface DxDialogOptions {
    title?: string;
    width?: number;
    height?: number;
    maxHeight?: number;
    visible?: boolean;
    events?: any;
    children?: any;
}

export interface DxDialogEvents {

}

export const DxDialog = forwardRef<DxDialogEvents, DxDialogOptions>((options, ref) => {
    const [title, setTitle] = useState<string>(options.title ?? 'Nuevo');
    const width = options.width ?? 480;
    const [visible, setVisible] = useState<boolean>(options.visible ?? false);
    const [loading, setLoading] = useState<boolean>(false);
    const {isXSmall} = useScreenSize();

    const open = (title: string) => {
        setVisible(true);
        setTitle(title);
    }

    const close = () => {
        setVisible(false);
    };

    const onCancelClick = useCallback(() => {
        close();
        bus.emit(options.events?.CANCEL);
    }, []);

    const onSaveClick = useCallback(() => {
        bus.emit(options.events?.SAVE);
    }, [options.events.SAVE]);

    const setupObservers = () => {
        bus.on<any>(options.events?.ADD, (payload) => open(payload?.title ?? 'Nuevo'));

        bus.on(options.events?.EDIT, () => open('Editar'));

        bus.on<boolean>(options.events?.LOADING, (isLoading) => setLoading(isLoading));

        bus.on(options.events?.SUCCESS, () => close());
    }

    let alreadySetup = false;

    useEffect(() => {
        if (!alreadySetup) {
            setupObservers();
            alreadySetup = true;
        }
    }, []);

    useImperativeHandle(ref, () => ({}));

    return (
        <Popup
            title={title}
            visible={visible}
            fullScreen={isXSmall}
            width={width}
            height={options.height ?? 'auto'}
            maxHeight={options.maxHeight ?? 640}
            wrapperAttr={{class: `form-popup`}}
        >
            <ToolbarItem
                toolbar='bottom'
                location='after'
            >
                <div className={`form-popup-buttons-container ${width <= 360 ? 'flex-buttons' : ''}`}>
                    <Button
                        text='Cancelar'
                        stylingMode='outlined'
                        onClick={onCancelClick}
                        disabled={loading}
                    />
                    <Button
                        text={loading ? 'Guardando...' : 'Guardar'}
                        stylingMode='outlined'
                        type='default'
                        onClick={onSaveClick}
                        disabled={loading}
                    />
                </div>
            </ToolbarItem>
            {options.children}
        </Popup>
    );
});
