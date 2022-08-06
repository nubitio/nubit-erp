import ThemeManager from "../ThemeManager";

export {};

declare global {
    interface Window {
        Observer: any;
        DataGrid: any;
        Dialog: any;
        Drawer: any;
        Form: any;
        EventType: any;
        FormType: any;
        Constants: any;
        ThemeManager: ThemeManager;
        detailview: any;
        app: any;
    }

    interface JQuery {
        datagrid: any;
        dialog: any;
        drawer: any;
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
