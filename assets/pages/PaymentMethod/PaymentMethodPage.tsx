import {useEffect} from "react";
import PaymentMethod from "../PaymentMethod";
import "./PaymentMethodPage.scss";

export const PaymentMethodPage = () => {
    const container = "wrapper"
    const paymentMethod = new PaymentMethod(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            paymentMethod.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
