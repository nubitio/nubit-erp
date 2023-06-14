import {useEffect} from "react";
import Customer from "../Customer";
import "./CustomerPage.scss";

export const CustomerPage = () => {
    const container = "wrapper"
    const customer = new Customer(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            customer.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
