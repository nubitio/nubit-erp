import {Popup, TextBox} from "devextreme-react";
import {ToolbarItem} from "devextreme-react/popup";
import {useCallback, useState} from "react";

export const DiscountDialog = (props: any) => {
    const [visible, setVisible] = useState(true);
    const onDiscountPercentChanged = useCallback((e: any) => {

    }, []);
    const onDiscountAmountChanged = useCallback((e: any) => {

    }, []);
    return (
        <div>
            <Popup
                visible={visible}
                onHiding={() => setVisible(false)}
                dragEnabled={false}
                hideOnOutsideClick={true}
                showTitle={true}
                title="Discount"
                width={400}
                height={300}
            >
                <ToolbarItem
                    location="after"
                    widget="dxButton"
                    toolbar="bottom"
                    options={{
                        text: "Apply"
                    }}
                />
                <div>
                    Total: {props.total}
                </div>
                <div>
                    {props.subtotal}
                </div>
                <TextBox
                    label={'Discount Percent'}
                    onValueChanged={onDiscountPercentChanged}
                />
                <TextBox
                    label={'Discount Amount'}
                    onValueChanged={onDiscountAmountChanged}
                />
            </Popup>
        </div>
    );
}