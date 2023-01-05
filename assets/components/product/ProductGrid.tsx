import {DataGrid} from "devextreme-react";
import {Column, Pager} from "devextreme-react/data-grid";
import React from "react";

class ProductGrid extends React.Component<any, any> {
    constructor(props: any) {
        super(props);
        this.state = {
            products: []
        };
    }

    componentDidMount() {
        this.getProducts();
    }

    getProducts() {
        fetch("http://localhost:3000/products")
            .then(response => response.json())
            .then(data => this.setState({products: data}));
    }

    render() {
        return (
            <DataGrid
                dataSource={this.state.products}
                showBorders={true}
            >
                <Column dataField="name"/>
                <Column dataField="price"/>
                <Column dataField="category"/>
                <Pager/>
            </DataGrid>
        );
    }
}

export default ProductGrid;