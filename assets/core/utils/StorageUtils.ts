export class StorageUtils {
  static getItem(key: string): any {
    let item = localStorage.getItem(key);
    if (item) {
      return JSON.parse(item);
    }
    return null;
  }

  static setItem(key: string, value: any): void {
    if (value === null || value === undefined) {
      return;
    }
    localStorage.setItem(key, JSON.stringify(value));
  }

  static removeItem(key: string): void {
    localStorage.removeItem(key);
  }
}
