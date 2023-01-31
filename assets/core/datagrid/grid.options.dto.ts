export default interface GridOptionsDto {
    title?: string;
    url?: string;
    method?: string;
    remoteFilter?: boolean;
    toolbar?: any[];
    columns?: any[];
    pagination?: boolean;
    showFooter?: boolean;
    loader?: (param: any, success: (data: any) => void, error: (data: any) => void) => void;
    onLoadSuccess?: (data: any) => void;
    view?: any;
    detailFormatter?: (index: number, row: any) => string;
    onExpandRow?: (index: number, row: any) => void;
}