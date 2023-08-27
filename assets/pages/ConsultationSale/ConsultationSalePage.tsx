import {useEffect, useRef, useState} from "react";
import "./ConsultationSalePage.scss";
import {useParams} from "react-router-dom";
import {consultationService} from "../../services/consultation.service";
import {Item as ToolbarItem, Toolbar} from "devextreme-react/toolbar";
import {Item} from "devextreme-react/data-grid";
import Button from "devextreme-react/button";
import {bus} from "../../core/event/EventBus";
import {Field} from "../../core/field/Field";
import {DxDataGrid, DxDataGridEvents} from "../../core/datagrid/DxDataGrid";
import {Constants} from "../../core/common/Constants";
import {DxForm, DxFormEvents} from "../../core/form/DxForm";
import {ConsultationFields} from "../Consultation/ConsultationFields";
import {DxDialog} from "../../core/dialog/DxDialog";
import {SalePage} from "../Sale/SalePage";
import {SaleFields} from "../Sale/SaleFields";
import {ConsultationSaleFields} from "./ConsultationSaleFields";

export const ConsultationSalePage = () => {
    const container = "wrapper"
    const {id} = useParams<{ id: string }>();
    const [consultationData, setConsultationData] = useState<string>('');
    const gridRef = useRef<DxDataGridEvents|any>(null);
    const formRef = useRef<DxFormEvents|any>(null);
    const [editing, setEditing] = useState<boolean>(false);
    const fields = ConsultationFields();
    const detailFieldsRef = useRef<Field[]|any>();
    detailFieldsRef.current = ConsultationSaleFields(Number(id));


    const API_URL = Constants.API_BASE_URL + 'consultation';
    const API_DETAIL_URL = Constants.API_BASE_URL + 'consultation-sales';

    const EVENTS = {
        ADD: 'consultation:add',
        EDIT: 'consultation:edit',
        DELETE: 'consultation:delete',
        SAVE: 'consultation:save',
        CANCEL: 'consultation:cancel',
        SUCCESS: 'consultation:success',
        LOADING: 'consultation:loading',
    }

    const DETAIL_EVENTS = {
        ADD: 'consultation-detail:add',
        EDIT: 'consultation-detail:edit',
        DELETE: 'consultation-detail:delete',
        SAVE: 'consultation-detail:save',
        CANCEL: 'consultation-detail:cancel',
        SUCCESS: 'consultation-detail:success',
        LOADING: 'consultation-detail:loading',
    }

    const toggleEditing = () => {
        setEditing(!editing);
    }
    const onEditClick = () => {
        bus.emit(EVENTS.EDIT, {row: formRef.current?.getValues()});
        toggleEditing();
    }

    const onSaveClick = () => {
        bus.emit(EVENTS.SAVE);
        toggleEditing();
    }

    const onCancelClick = () => {
        bus.emit(EVENTS.CANCEL);
        toggleEditing();
    }


    useEffect(()=>{
        consultationService.getOneById(Number(id)).subscribe((response)=>{
            //console.log(response.data);
            formRef.current?.setValues(response.data);
        })
    });

    const render = (detailFields: any) => {
        return (
            <div>
                <Toolbar className='toolbar-details'>
                    <ToolbarItem location='before'>
                        <Button icon='arrowleft' onClick={() => {
                            window.history.back();
                        }}/>
                    </ToolbarItem>
                    <ToolbarItem location='before' text={consultationData}/>
                    <Item location='after' locateInMenu='auto'>
                        <Button
                            icon='plus'
                            text='Nuevo'
                            type='success'
                            stylingMode='outlined'
                            onClick={() => bus.emit(DETAIL_EVENTS.ADD)}
                        />
                    </Item>
                    <Item location='after' locateInMenu='auto'>
                        <Button
                            icon='edit'
                            text='Editar'
                            type='default'
                            stylingMode='outlined'
                            onClick={() => bus.emit(DETAIL_EVENTS.EDIT, {row: gridRef.current?.getSelectedRow()})}
                        />
                    </Item>
                    <Item location='after' locateInMenu='auto'>
                        <Button
                            icon='trash'
                            text='Eliminar'
                            type='danger'
                            stylingMode='outlined'
                            onClick={() => bus.emit(DETAIL_EVENTS.DELETE, {row: gridRef.current?.getSelectedRow()})}
                        />
                    </Item>
                    <ToolbarItem
                        location='after'
                        locateInMenu='auto'
                        widget='dxButton'
                        showText='inMenu'
                    >
                        <Button
                            text='Refresh'
                            icon='refresh'
                            stylingMode='text'
                            onClick={() => {
                                gridRef.current?.refresh();
                            }}
                        />
                    </ToolbarItem>
                </Toolbar>
                <div className='panels'>
                    <div className='left'>
                        <Toolbar className='toolbar-form'>
                            <Item location='before'>
                                <span className='dx-form-group-caption'>Consult</span>
                            </Item>
                            <Item location='after' visible={!editing}>
                                <Button text='Editar' icon='edit' stylingMode='outlined' type='default'
                                        onClick={onEditClick}/>
                            </Item>
                            <Item location='after' visible={editing}>
                                <Button text='Guardar' stylingMode='outlined' type='default' onClick={onSaveClick}/>
                            </Item>
                            <Item location='after' visible={editing}>
                                <Button text='Cancelar' stylingMode='text' onClick={onCancelClick}/>
                            </Item>
                        </Toolbar>
                        <DxForm
                            ref={formRef}
                            url={API_URL}
                            fields={fields}
                            events={EVENTS}
                            editable={editing}
                        />
                    </div>
                    <div className='right'>
                        <DxDataGrid
                            ref={gridRef}
                            title='FileDetail'
                            url={API_DETAIL_URL}
                            fields={detailFields}
                            filter={[
                                {
                                    field: 'Sale',
                                    operator: '=',
                                    value: Number(id)
                                }
                            ]}
                            mode='simple'
                        />
                        <DxDialog
                            events={DETAIL_EVENTS}
                        >
                            <DxForm
                                url={API_DETAIL_URL}
                                fields={detailFields}
                                events={DETAIL_EVENTS}
                                editable={true}
                            />
                        </DxDialog>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div id={container} className='panels-wrapper'>
            {Number(id) ? render(detailFieldsRef.current) : null}
        </div>
    );
};
