import {Field} from "../field/Field";
import {FieldType} from "../field/FieldType";
import {Column, Item, Lookup} from "devextreme-react/data-grid";
import {dxUtils} from "../common/DxUtils";
import {Button} from "devextreme-react";

export const buildToolbar = (items: any[]) => {
    return items?.map((item: any, index) => {
        return <Item location='after' locateInMenu='auto' key={index}>
            <Button
                icon={item.icon}
                text={item.text}
                type={item.type}
                stylingMode='outlined'
                onClick={item.onClick}
            />
        </Item>
    });
}

export const buildLookup = (field: Field) => {
    if (!field.filterable) return;
    let lookup;
    if (field.type === FieldType.ENTITY) {
        lookup = <Lookup
            dataSource={{
                store: dxUtils.getDataStore(field.url, field.valueField),
                paginate: true,
                pageSize: 10
            }}
            valueExpr={field.valueField}
            displayExpr={field.textField}
        />
    }
    if (field.type === FieldType.ENUM) {
        lookup = <Lookup
            dataSource={field.data}
            valueExpr="value"
            displayExpr="text"
        />
    }
    if (field.type === FieldType.SWITCH) {
        lookup = <Lookup
            dataSource={[{value: true, text: 'Si'}, {value: false, text: 'No'}]}
            valueExpr="value"
            displayExpr="text"
        />
    }
    return lookup;
}

export const buildColumns = (fields: Field[], forceLookup = false) => {
    return fields.map((field: Field, index) => {
        let clone = {...field};
        if (!field.visible) return null;
        if (forceLookup) clone.filterable = true;
        return <Column
            key={index}
            dataField={field.name}
            caption={field.label}
            alignment={field.align}
            allowSorting={field.sortable}
            allowFiltering={field.filterable}
            cellRender={field.formatter}
        >
            {buildLookup(clone)}
        </Column>
    });
}

export const getIdField = (fields: Field[]) => {
    return fields.find((field: Field) => field.isIdentity)?.name ?? '';
}
