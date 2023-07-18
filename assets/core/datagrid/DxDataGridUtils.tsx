import {Field} from "../field/Field";
import {FieldType} from "../field/FieldType";
import {Column, Item, ITotalItemProps, Lookup, RequiredRule, TotalItem} from "devextreme-react/data-grid";
import {dxUtils} from "../common/DxUtils";
import {Button} from "devextreme-react";

export const buildToolbar = (items: any[] = []) => {
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

const buildLookup = (field: Field) => {
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

const buildEditorOptions = (field: Field) => {
    let editorOptions: any = {};
    if (field.type === FieldType.ENTITY) {
        editorOptions = {
            dropDownOptions: {
                minWidth: 200
            },
            wrapItemText: true,
        }
    }
    editorOptions = {
        ...editorOptions,
        readOnly: field.readonly,
        disabled: field.disabled,
    };
    return editorOptions;
}

const buildValidationRules = (field: Field) => {
    let validationRules: any[] = [];
    if (field.required) {
        validationRules.push({
            type: 'required',
            message: `${field.label} es requerido`
        });
    }
    validationRules = validationRules.concat(field.validators ?? []);
    return validationRules.map((rule, index) => {
        switch (rule.type) {
            case 'required':
                return <RequiredRule key={index}/>
            default:
                return null;
        }
    });
}

export const buildColumns = (fields: Field[], forceLookup = false) => {
    return fields.map((field: Field, index) => {
        let clone = {...field};
        if (!field.visible) return null;
        if (forceLookup && field.type === FieldType.NONE) return null;
        if (forceLookup) clone.filterable = true;
        let editorOptions = buildEditorOptions(clone);
        return <Column
            key={index}
            dataField={field.name}
            caption={field.label}
            alignment={field.align}
            allowSorting={field.sortable}
            allowFiltering={field.filterable}
            cellRender={field.formatter}
            setCellValue={field.onChange}
            //width={field.width}
            minWidth={field.minWidth}
            editorOptions={editorOptions}
            dataType={field.valueType}
        >
            {buildLookup(clone)}
            {buildValidationRules(clone)}
        </Column>
    });
}

export const buildSummary = (fields: ITotalItemProps[] | undefined) => {
    return fields?.map((field: ITotalItemProps, index) => {
        console.log(field)
        return <TotalItem
            key={index}
            column={field.column}
            summaryType={field.summaryType}
            displayFormat={field.displayFormat}
            valueFormat={field.valueFormat}
            customizeText={field.customizeText}
        />
    });
}

export const getIdField = (fields: Field[]) => {
    return fields.find((field: Field) => field.isIdentity)?.name ?? '';
}
