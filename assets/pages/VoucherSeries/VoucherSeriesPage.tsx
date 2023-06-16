import {useEffect} from "react";
import VoucherSeries from "../VoucherSeries";
import "./VoucherSeriesPage.scss";

export const VoucherSeriesPage = () => {
    const container = "wrapper"
    const voucherSeries = new VoucherSeries(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            voucherSeries.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
