import type { DataRecord } from '../data/DataRecord';

export type FormDataRecord = DataRecord;

export type FormDataSnapshot = FormDataRecord;

export type FormDataChangedHandler = (formData: FormDataSnapshot) => void;
