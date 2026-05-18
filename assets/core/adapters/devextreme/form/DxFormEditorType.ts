import type { FormEditorKind } from '@core/form/FormEditorKind';

// DevExtreme exposes editorType as a vendor-specific literal union; keep that
// typing local to this adapter while the app works with FormEditorKind.
export const getDxEditorType = (editorKind: FormEditorKind | null): any => {
  switch (editorKind) {
    case 'text':
      return 'dxTextBox';
    case 'number':
      return 'dxNumberBox';
    case 'date':
      return 'dxDateBox';
    case 'select':
      return 'dxSelectBox';
    case 'checkbox':
      return 'dxCheckBox';
    case 'radio':
      return 'dxRadioGroup';
    case 'textarea':
      return 'dxTextArea';
    case 'file':
      return 'dxFileUploader';
    case 'switch':
      return 'dxSwitch';
    case 'tags':
      return 'dxTagBox';
    case 'html':
      return 'dxHtmlEditor';
    case null:
      return undefined;
  }
};
