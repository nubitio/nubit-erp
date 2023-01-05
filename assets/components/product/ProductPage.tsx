import React from "react";
import {Button} from "devextreme-react";
import ProductGrid from "./ProductGrid";

class ProductPage extends React.Component<any, any> {
    render() {
        return (
            <div>
                <Button text="Click me" onClick={this.sayHello}/>
                <ProductGrid/>
            </div>
        );
    }

    sayHello() {
        alert("Hello World!");
    }
}

export default ProductPage;