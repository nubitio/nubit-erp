export default interface FilterRule {
    field: string;
    op: string;
    value: string | number | boolean;
}