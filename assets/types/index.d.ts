import ThemeManager from "../core/utils/ThemeManager";
import LocalPicker from "../modules/LocalPicker";

export {};

declare global {
    interface Window {
        observer: any;
        DataGrid: any;
        Dialog: any;
        Drawer: any;
        Form: any;
        EventType: any;
        FormType: any;
        Constants: any;
        ThemeManager: ThemeManager;
        LocalPicker: LocalPicker;
        detailview: any;
        app: any;
        userRoleId: any;
    }

    interface JQuery {
        panel: any;
        layout: any;
        sidemenu: any;
        datagrid: any;
        datalist: any;
        dialog: any;
        drawer: any;
        form: any;
        messager: any;
        linkbutton: any;
        filebox: any;
        textbox: any;
        passwordbox: any;
        datebox: any;
        numberbox: any;
        combobox: any;
        switchbutton: any;
        animateCss: any;
        tree: any;
        serializeJSON: any;
    }

    const jsWebClientPrint: any;
}
