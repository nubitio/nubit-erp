import './styles/app.scss';
import Observable from "./Observable";
import DataGrid from "./DataGrid";
import Dialog from "./Dialog";
import Form from "./Form";
import {EventType} from "./EventType";
import {FormType} from "./FormType";
import {Constants} from "./Constants";

window.Observer = new Observable();
window.DataGrid = DataGrid;
window.Dialog = Dialog;
window.Form = Form;
window.EventType = EventType;
window.FormType = FormType;
window.Constants = Constants;