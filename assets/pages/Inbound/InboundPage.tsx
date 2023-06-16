import {useEffect} from "react";
import Inbound from "../Inbound";
import "./InboundPage.scss";

export const InboundPage = () => {
    const container = "wrapper"
    const inbound = new Inbound(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            inbound.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
