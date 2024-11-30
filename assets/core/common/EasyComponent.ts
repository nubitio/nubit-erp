import {Field} from "../field/Field";

export default class EasyComponent {
    protected fields: Field[] = [];

    getFieldByName(name: string): Field {
        let field: Field | undefined = this.fields.find(field => field.name === name);
        if (field === undefined) {
            throw new Error(`Field ${name} not found`);
        }
        return field;
    }
}