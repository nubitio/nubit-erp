import {DataGrid} from "devextreme-react";
import {Column, Editing, FilterRow, Form, Pager, Paging, Popup} from "devextreme-react/data-grid";
import React from "react";
import {SharedUtils} from "../../core/utils/SharedUtils";
import {ProductService} from "./product.service";
import CustomStore from "devextreme/data/custom_store";
import {Item} from "devextreme-react/form";

class ProductGrid extends React.Component<any, any> {
    productsService = new ProductService();

    constructor(props: any) {
        super(props);
        this.state = {
            productsData: new CustomStore({
                key: "producto_id",
                load: (loadOptions) => this.productsService.getAll(loadOptions),
                insert: (values) => this.productsService.insert(values),
            }),
        };
    }

    componentDidMount() {
    }

    render() {
        return (
            <DataGrid
                dataSource={this.state.productsData}
                showBorders={true}
                columnMinWidth={50}
                columnAutoWidth={true}
                repaintChangesOnly={true}
                remoteOperations={true}
            >
                <Editing mode="popup" allowAdding={true} allowUpdating={true} allowDeleting={true}>
                    <Popup title="Product" showTitle={true} width={700} height={560}/>
                    <Form colCount={1}>
                        <Item dataField="producto_id" editorOptions={{readOnly: true}}/>
                        <Item dataField="codigo"/>
                        <Item dataField="tipo"/>
                        <Item dataField="descripcion"/>
                        <Item dataField="categoria"/>
                        <Item dataField="precio"/>
                        <Item dataField="iamgen"/>
                        <Item dataField="estado"/>
                    </Form>
                </Editing>
                <FilterRow visible={true}/>
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