export interface EventObject<T = any> {
    name: string;
    data?: T;
}