import './styles/app.scss';
import Observable from "./Observable";
import DataGrid from "./DataGrid";
import Dialog from "./Dialog";
import Form from "./Form";
import {EventType} from "./EventType";
import {FormType} from "./FormType";
import {Constants} from "./Constants";
import ThemeManager from "./ThemeManager";
import Customer from "./pages/Customer";
import Drawer from "./Drawer";
import User from "./pages/User";

window.Observer = new Observable();
window.DataGrid = DataGrid;
window.Dialog = Dialog;
window.Drawer = Drawer;
window.Form = Form;
window.EventType = EventType;
window.FormType = FormType;
window.Constants = Constants;
window.ThemeManager = new ThemeManager();
window.app = window.app || {};

window.app.customer = Customer;
window.app.user = User;