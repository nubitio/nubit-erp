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
    const [newTotal, setNewTotal] = useState(total);

    const onDiscountPercentChanged = useCallback((e: any) => {
        setDiscountPercent(e.value);
        let discountAmount = e.value * total;
        setDiscountAmount(discountAmount);
    }, [total, discountAmount]);

    const onDiscountAmountChanged = useCallback((e: any) => {
        if (total === 0) return;
        setDiscountAmount(e.value);
        let discountPercent = e.value / total;
        setDiscountPercent(discountPercent);
        setNewTotal(total - e.value)
    }, [total, discountPercent]);

    const onApply = useCallback((discountPercent: number) => {
        setVisible(false);
        bus.emit<number>(DiscountDialogEvent.APPLY, discountPercent);
    }, []);

    useEffect(() => {
        bus.on<{ total: number, discountPercent: number }>(DiscountDialogEvent.OPEN, (data) => {
            setVisible(true);
            resetState();
            setTotal(data.total);
            setNewTotal(data.total);
            if (data.discountPercent) {
                let discountPercent = data.discountPercent / 100;
                setDiscountPercent(discountPercent);
                let discountAmount = data.total * discountPercent;
                setDiscountAmount(discountAmount);
                setNewTotal(data.total - discountAmount)
            }
        });
    }, []);

    const resetState = () => {
        setTotal(0.00);
        setDiscountPercent(0.00);
        setDiscountAmount(0.00);
        setNewTotal(0.00);
    }

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
                    location="after"
                    widget="dxButton"
                    toolbar="bottom"
                    options={{
                        text: "Apply",
                        onClick: () => onApply(discountPercent)
                    }}
                />
                <h3 className="text-center">
                    {NumberUtils.formatCurrency(newTotal)}
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
                            onValueChanged={onDiscountPercentChanged}
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
                            onValueChanged={onDiscountAmountChanged}
                        />
                    </div>
                </div>
            </Popup>
        </div>
    );
}