import {NumberBox, Popup} from "devextreme-react";
import {ToolbarItem} from "devextreme-react/popup";
import {useCallback, useEffect, useState} from "react";
import {bus} from "../../core/event/EventBus";
import NumberUtils from "../../core/utils/NumberUtils";

export enum DiscountDialogEvent {
    OPEN = 'discount:dialog:open',
    APPLY = 'discount:apply'
}

export const DiscountDialog = () => {
    const [visible, setVisible] = useState(false);
    const [total, setTotal] = useState(0.00);
    const [discountPercent, setDiscountPercent] = useState(0.00);
    const [discountAmount, setDiscountAmount] = useState(0.00);

    const onApply = useCallback((discountPercent: number) => {
        setVisible(false);
        bus.emit<number>(DiscountDialogEvent.APPLY, discountPercent);
    }, []);

    useEffect(() => {
        setDiscountAmount(discountPercent * total);
    }, [discountPercent, total]);

    useEffect(() => {
        if (total > 0) {
            setDiscountPercent(discountAmount / total);
        }
    }, [discountAmount, total]);

    useEffect(() => {
        bus.on<{ discountPercent: number, total: number }>(DiscountDialogEvent.OPEN, (data) => {
            setTotal(data.total);
            setDiscountPercent(data.discountPercent);
            setDiscountAmount(data.discountPercent * data.total);
            setVisible(true);
        });
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
                width={420}
                height={360}
            >
                <ToolbarItem
                    key="apply-button"
                    location="after"
                    widget="dxButton"
                    toolbar="bottom"
                    options={{
                        text: "Apply",
                        onClick: () => onApply(discountPercent)
                    }}
                />
                <h3 className="text-center">
                    {NumberUtils.formatCurrency(total - discountAmount)}
                </h3>
                {discountAmount > 0 &&
                    <h6 className="text-center text-decoration-line-through">
                        {NumberUtils.formatCurrency(total)}
                    </h6>
                }
                <div className="p-2">
                    <div className="mb-4">
                        <NumberBox
                            label={'Discount Percent'}
                            format="#0%"
                            step={0.01}
                            value={discountPercent}
                            min={0}
                            max={1}
                            showClearButton={true}
                            stylingMode={'outlined'}
                            onValueChanged={(event) => setDiscountPercent(event.value)}
                        />
                    </div>
                    <div className="mb-4">
                        <NumberBox
                            label={'Discount Amount'}
                            format="S/ #,##0.00"
                            value={discountAmount}
                            min={0}
                            max={total}
                            showClearButton={true}
                            stylingMode={'outlined'}
                            onValueChanged={(event) => setDiscountAmount(event.value)}
                        />
                    </div>
                </div>
            </Popup>
        </div>
    );
}