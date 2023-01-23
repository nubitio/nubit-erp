export default class NumberUtils {
    static isNumber(value: any): boolean {
        return (typeof (value) === 'number' || typeof (value) === "string" && value.trim() !== '') && !isNaN(value as number);
    }
}