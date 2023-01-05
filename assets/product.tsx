import ProductPage from "./components/product/ProductPage";
import React from "react";
import ReactDOM from "react-dom/client";

window.addEventListener('load', () => {
    const root = ReactDOM.createRoot(
        document.getElementById('app') as HTMLElement
    );
    root.render(
        <React.StrictMode>
            <ProductPage/>
        </React.StrictMode>
    );
});