import {DataGrid} from "devextreme-react";
import {Column, Editing, FilterRow, HeaderFilter, Pager, Paging} from "devextreme-react/data-grid";
import React from "react";
import {SharedUtils} from "../../core/utils/SharedUtils";

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
        fetch("http://localhost:8080/product/get")
            .then(response => response.json())
            .then(data => this.setState({products: data.rows}));
    }

    render() {
        return (
            <DataGrid
                dataSource={this.state.products}
                showBorders={true}
                columnMinWidth={50}
                columnAutoWidth={true}
            >
                <Editing mode="popup" allowAdding={true} allowUpdating={true} allowDeleting={true}/>
                <FilterRow visible={true}/>
                <HeaderFilter visible={true}/>
                <Column dataField="codigo"/>
                <Column dataField="tipo"/>
                <Column dataField="descripcion"/>
                <Column dataField="categoria"/>
                <Column dataField="precio" dataType="number" format="#.00"/>
                <Column dataField="imagen" cellRender={SharedUtils.dxImageFormatter} alignment="center"/>
                <Column dataField="estado" cellRender={SharedUtils.dxActiveFormatter} dataType="boolean"/>
                <Paging defaultPageSize={10}/>
                <Pager visible={true}/>
            </DataGrid>
        );
    }
}

export default ProductGrid;