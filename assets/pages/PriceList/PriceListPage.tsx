import {useEffect} from "react";
import PriceList from "../PriceList";
import "./PriceListPage.scss";

export const PriceListPage = () => {
    const container = "wrapper"
    const priceList = new PriceList(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            priceList.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
