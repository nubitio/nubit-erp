export default interface GridOptionsDto {
    title?: string;
    url?: string;
    remoteFilter?: boolean;
    toolbar?: any[];
    columns?: any[];
    pagination?: boolean;
    showFooter?: boolean;
    onLoadSuccess?: (data: any) => void;
    view?: any;
    detailFormatter?: (index: number, row: any) => string;
    onExpandRow?: (index: number, row: any) => void;
}