export default interface FilterRule {
    field: string;
    op: string;
    value: string | number | boolean;
}

export interface DxFilterRule {
    field: string;
    operator: string;
    value: string | number | boolean;
}
