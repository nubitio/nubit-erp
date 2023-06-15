import {useEffect} from "react";
import VoucherType from "../VoucherType";
import "./VoucherTypePage.scss";

export const VoucherTypePage = () => {
    const container = "wrapper"
    const voucherType = new VoucherType(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            voucherType.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
