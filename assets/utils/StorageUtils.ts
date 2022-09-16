export class StorageUtils {
    static getItem(key: string): any {
        return JSON.parse(localStorage.getItem(key));
    }

    static setItem(key: string, value: any): void {
        localStorage.setItem(key, JSON.stringify(value));
    }

    static removeItem(key: string): void {
        localStorage.removeItem(key);
    }
}