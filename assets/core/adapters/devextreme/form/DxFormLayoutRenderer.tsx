import Form, { FormRef, GroupItem, Item, Tab, TabbedItem } from 'devextreme-react/form';
import type { DataGridRef } from 'devextreme-react/data-grid';
import type DataSource from 'devextreme/data/data_source';
import type { MutableRefObject, ReactNode } from 'react';

import type { FormLayout } from '@core/crud/FormLayout';
import type { Field } from '@core/field/Field';

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
  formLayout?: FormLayout;
  formRef: MutableRefObject<FormRef | null>;
  gridRef: MutableRefObject<DataGridRef | null>;
  groupOtherText: string;
  renderFields: (fields: Field[]) => ReactNode;
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
  formLayout,
  formRef,
  gridRef,
  groupOtherText,
  renderFields,
}: DxFormLayoutRendererProps) {
  const pickFields = (names: string[]): Field[] =>
    fields.filter((field) => names.includes(field.name));
  const remainingFields = (assignedNames: string[]): Field[] =>
    fields.filter((field) => !assignedNames.includes(field.name));

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

  if (formLayout?.type === 'tabs') {
    const { tabs } = formLayout;
    const allAssigned = tabs.flatMap((tab) => tab.fields);
    const overflow = remainingFields(allAssigned);
    return (
      <Form {...formProps}>
        <TabbedItem>
          {tabs.map((tab) => (
            <Tab key={tab.label} title={tab.label} icon={tab.icon}>
              {renderFields(pickFields(tab.fields))}
            </Tab>
          ))}
          {overflow.length > 0 && <Tab title={groupOtherText}>{renderFields(overflow)}</Tab>}
        </TabbedItem>
      </Form>
    );
  }

  if (formLayout?.type === 'sections') {
    const { sections } = formLayout;
    const allAssigned = sections.flatMap((section) => section.fields);
    const overflow = remainingFields(allAssigned);
    return (
      <Form {...formProps}>
        {sections.map((section) => (
          <GroupItem key={section.label} caption={section.label}>
            {renderFields(pickFields(section.fields))}
          </GroupItem>
        ))}
        {overflow.length > 0 && <GroupItem>{renderFields(overflow)}</GroupItem>}
      </Form>
    );
  }

  return (
    <Form {...formProps}>
      <GroupItem>{renderFields(fields)}</GroupItem>
    </Form>
  );
}
