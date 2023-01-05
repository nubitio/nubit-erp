import React from "react";
import ProductGrid from "./ProductGrid";
import {CrudHeader} from "../common/CrudHeader";

class ProductPage extends React.Component<any, any> {
    render() {
        return (
            <div className="container-fluid">
                <CrudHeader pageTitle="Products" showAddButton={false}/>
                <ProductGrid/>
            </div>
        );
    }
}

export default ProductPage;