import {Component} from "react";
import {Form} from "devextreme-react";
import {ProductDto} from "../../dto/product.dto";
import {ButtonItem, ButtonOptions, NumericRule, RequiredRule, SimpleItem} from "devextreme-react/form";

export default class ProductForm extends Component<any, any> {
    productModel: ProductDto = {} as ProductDto;

    constructor(props: any) {
        super(props);
    }

    render() {
        return (
            <div>
                <form action="product/add" method="post" onSubmit={this.handleSubmit}>
                    <Form formData={this.productModel}>
                        <SimpleItem dataField="name" isRequired={true}/>
                        <SimpleItem dataField="price">
                            <RequiredRule message="Price is required"/>
                            <NumericRule message="Price must be a number"/>
                        </SimpleItem>
                        <ButtonItem horizontalAlignment="left">
                            <ButtonOptions text="Submit" type="success" useSubmitBehavior={true}/>
                        </ButtonItem>
                    </Form>
                </form>
            </div>
        );
    }

    onSubmitClick() {
        alert("Product saved!");
    }

    handleSubmit(event: any) {
        console.log(event);
        event.preventDefault();
    }
}