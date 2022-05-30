import './styles/app.scss';
import Observable from "./Observable";
import DataGrid from "./DataGrid";
import Dialog from "./Dialog";
import Form from "./Form";
import {EventType} from "./EventType";
import {FormType} from "./FormType";

window.Observer = new Observable();
window.DataGrid = DataGrid;
window.Dialog = Dialog;
window.Form = Form;
window.EventType = EventType;
window.FormType = FormType;