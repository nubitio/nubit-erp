export {};

declare global {
    interface Window {
        Observer: any;
        DataGrid: any;
        Dialog: any;
        Form: any;
        EventType: any;
        FormType: any;
        Constants: any;
    }

    interface JQuery {
        datagrid: any;
        dialog: any;
        form: any;
        messager: any;
        linkbutton: any;
        filebox: any;
        textbox: any;
        numberbox: any;
        combobox: any;
        switchbutton: any;
        animateCss: any;
    }
}
