import './DxDialog.scss';
import React, {useCallback, useEffect, useRef, useState} from 'react';

import {Popup, ToolbarItem} from 'devextreme-react/popup';
import ValidationGroup from 'devextreme-react/validation-group';
import {useScreenSize} from '../../utils/media-query';
import {Button} from 'devextreme-react';
import {bus} from "../event/EventBus";

export const DxDialog = ({options, children}: any) => {
    const [title, setTitle] = useState<string>(options.title ?? 'Nuevo');
    const width = options.width ?? 480;
    const [visible, setVisible] = useState<boolean>(options.visible ?? false);
    const [loading, setLoading] = useState<boolean>(false);
    const {isXSmall} = useScreenSize();
    const validationGroup = useRef<ValidationGroup>(null);

    const open = (title: string) => {
        validationGroup.current?.instance.reset();
        setVisible(true);
        setTitle(title);
    }

    const close = () => {
        validationGroup.current?.instance.reset();
        setVisible(false);
        bus.emit(options.events?.CANCEL);
    };

    const onCancelClick = useCallback(() => {
        close();
    }, [close, validationGroup]);

    const onSaveClick = useCallback(() => {
        if (!validationGroup.current?.instance.validate().isValid) return;
        bus.emit(options.events?.SAVE);
        close();
    }, [validationGroup]);

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

    return (
        <Popup
            title={title}
            visible={visible}
            fullScreen={isXSmall}
            width={width}
            wrapperAttr={{class: `form-popup`}}
            height='auto'
        >
            <ToolbarItem
                toolbar='bottom'
                location='center'
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

            <ValidationGroup ref={validationGroup}>
                {children}
            </ValidationGroup>
        </Popup>
    );
};
