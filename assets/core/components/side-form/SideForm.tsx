import './SideForm.scss';
import { Item, Toolbar } from 'devextreme-react/toolbar';
import Button from 'devextreme-react/button';
import { DxForm, DxFormEvents } from '../../form/DxForm';
import classNames from 'classnames';
import React, { forwardRef, useEffect, useImperativeHandle, useRef, useState } from 'react';
import { useEvents } from '../../event/EventHook';
import { httpClient } from '../../../shared/api/HttpClient';
import LoadPanel from 'devextreme-react/load-panel';
import { Field } from '../../field/Field';

type SideFormProps = {
  title?: string;
  sub: number | string | undefined;
  url: string;
  fields: Field[];
  events: any;
};

export const SideForm = forwardRef<DxFormEvents, SideFormProps>((props, ref) => {
  const formRef = useRef<DxFormEvents | null>(null);
  const [editing, setEditing] = useState<boolean>(false);
  const [loading, setLoading] = useState<boolean>(false);
  const [on, emit] = useEvents();

  const toggleEditing = () => {
    setEditing(!editing);
  };

  const onEditClick = () => {
    emit(props.events?.EDIT, { row: formRef.current?.getValues() });
    toggleEditing();
  };

  const onSaveClick = () => {
    emit(props.events?.SAVE);
  };

  const onCancelClick = () => {
    emit(props.events?.CANCEL);
    toggleEditing();
  };

  const setupObservers = () => {
    on(props.events?.SUCCESS, () => {
      setEditing(false);
      loadData();
    });
    on<boolean>(props.events?.LOADING, (isLoading) => setLoading(isLoading));
  };

  const loadData = () => {
    setLoading(true);
    httpClient
      .get(props.url + '/' + props.sub)
      .subscribe((response) => {
        formRef.current?.setValues(response.data);
      })
      .add(() => setLoading(false));
  };

  useImperativeHandle(ref, () => formRef.current!);

  const loadDataRef = useRef(loadData);
  loadDataRef.current = loadData;
  const setupObserversRef = useRef(setupObservers);
  setupObserversRef.current = setupObservers;

  useEffect(() => {
    loadDataRef.current();
    setupObserversRef.current();
  }, [props.sub]);

  return (
    <div className="container" id="sideform">
      <Toolbar className="toolbar-form">
        <Item location="before">
          <span className="dx-form-group-caption">{props.title}</span>
        </Item>
        <Item location="after" visible={!editing}>
          <Button
            text="Editar"
            icon="edit"
            stylingMode="outlined"
            type="default"
            onClick={onEditClick}
          />
        </Item>
        <Item location="after" visible={editing}>
          <Button
            text={loading ? 'Guardando...' : 'Guardar'}
            stylingMode="outlined"
            type="default"
            onClick={onSaveClick}
            disabled={loading}
          />
        </Item>
        <Item location="after" visible={editing}>
          <Button text="Cancelar" stylingMode="text" onClick={onCancelClick} disabled={loading} />
        </Item>
      </Toolbar>
      <div className="side-form-wrapper">
        <DxForm
          className={classNames({ form: true, 'plain-styled-form': true, 'view-mode': !editing })}
          ref={formRef}
          url={props.url}
          fields={props.fields}
          events={props.events}
          editable={editing}
        />
      </div>
      <LoadPanel
        shadingColor="rgba(0,0,0,0.4)"
        position={{ of: '#sideform' }}
        visible={loading}
        showIndicator={true}
        shading={true}
        showPane={true}
        hideOnOutsideClick={false}
      />
    </div>
  );
});

SideForm.displayName = 'SideForm';
