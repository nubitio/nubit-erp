import { BaseFieldBuilder } from './BaseFieldBuilder';
import { FieldType } from './FieldType';

class TextFieldBuilder extends BaseFieldBuilder {
  constructor() {
    super(FieldType.TEXT);
  }

  maxLength(maxLength: number): TextFieldBuilder {
    this._field.maxLength = maxLength;
    return this;
  }
}

export const textField = () => new TextFieldBuilder();
