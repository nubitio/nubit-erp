import React from "react";
import ReactDOM from "react-dom/client";
import {EfactApp} from "./EfactApp";

import './app.scss';

window.addEventListener('load', () => {
    const root = ReactDOM.createRoot(
        document.getElementById('root') as HTMLElement
    );
    root.render(
        <React.StrictMode>
            <EfactApp/>
        </React.StrictMode>
    );
});
