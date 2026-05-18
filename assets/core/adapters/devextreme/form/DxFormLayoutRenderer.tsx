import Form, { FormRef, GroupItem, Item, Tab, TabbedItem } from 'devextreme-react/form';
import type { DataGridRef } from 'devextreme-react/data-grid';
import type DataSource from 'devextreme/data/data_source';
import type { MutableRefObject } from 'react';

import type { Field } from '@core/field/Field';
import type { FormFieldRenderer } from '@core/form/FormFieldRenderer';
import type { FormLayoutModel } from '@core/form/FormLayoutModel';

import { DxFormDetailGrid } from './DxFormDetailGrid';

type FieldDataChangedHandler = React.ComponentProps<typeof Form>['onFieldDataChanged'];

export interface DxFormLayoutRendererProps {
  allowAdding?: boolean;
  allowDeleting?: boolean;
  allowUpdating?: boolean;
  className?: string;
  dataSource: DataSource;
  detailFields?: Field[];
  detailIdField: string;
  detailNoDataText: string;
  emit: <T>(name: string, payload?: T) => void;
  fieldChangeHandler?: FieldDataChangedHandler;
  fields: Field[];
  formLayoutModel: FormLayoutModel;
  formRef: MutableRefObject<FormRef | null>;
  gridRef: MutableRefObject<DataGridRef | null>;
  renderFields: FormFieldRenderer;
}

export function DxFormLayoutRenderer({
  allowAdding,
  allowDeleting,
  allowUpdating,
  className,
  dataSource,
  detailFields,
  detailIdField,
  detailNoDataText,
  emit,
  fieldChangeHandler,
  fields,
  formLayoutModel,
  formRef,
  gridRef,
  renderFields,
}: DxFormLayoutRendererProps) {
  const formProps = {
    className,
    labelMode: 'floating' as const,
    onFieldDataChanged: fieldChangeHandler,
    ref: formRef,
  };

  if ((detailFields?.length ?? 0) > 0) {
    const sidebarColCount = 1;
    return (
      <Form {...formProps}>
        <Item itemType="group" colCount={4}>
          <GroupItem colCount={sidebarColCount}>{renderFields(fields)}</GroupItem>
          <GroupItem colSpan={3}>
            <DxFormDetailGrid
              gridRef={gridRef}
              dataSource={dataSource}
              detailFields={detailFields ?? []}
              detailIdField={detailIdField}
              noDataText={detailNoDataText}
              allowAdding={allowAdding}
              allowUpdating={allowUpdating}
              allowDeleting={allowDeleting}
              emit={emit}
            />
          </GroupItem>
        </Item>
      </Form>
    );
  }

  if (formLayoutModel.type === 'tabs') {
    return (
      <Form {...formProps}>
        <TabbedItem>
          {formLayoutModel.tabs.map((tab) => (
            <Tab key={tab.label} title={tab.label} icon={tab.icon}>
              {renderFields(tab.fields)}
            </Tab>
          ))}
          {formLayoutModel.overflow && (
            <Tab title={formLayoutModel.overflow.label}>
              {renderFields(formLayoutModel.overflow.fields)}
            </Tab>
          )}
        </TabbedItem>
      </Form>
    );
  }

  if (formLayoutModel.type === 'sections') {
    return (
      <Form {...formProps}>
        {formLayoutModel.sections.map((section) => (
          <GroupItem key={section.label} caption={section.label}>
            {renderFields(section.fields)}
          </GroupItem>
        ))}
        {formLayoutModel.overflow && (
          <GroupItem caption={formLayoutModel.overflow.label}>
            {renderFields(formLayoutModel.overflow.fields)}
          </GroupItem>
        )}
      </Form>
    );
  }

  return (
    <Form {...formProps}>
      <GroupItem>{renderFields(formLayoutModel.fields)}</GroupItem>
    </Form>
  );
}
