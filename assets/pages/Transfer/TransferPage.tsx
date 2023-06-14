import {useEffect} from "react";
import Transfer from "../Transfer";
import "./TransferPage.scss";

export const TransferPage = () => {
    const container = "wrapper"
    const transfer = new Transfer(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            transfer.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
            </div>
    );
};
