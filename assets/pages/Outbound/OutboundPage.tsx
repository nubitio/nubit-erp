import {useEffect} from "react";
import Outbound from "../Outbound";
import "./OutboundPage.scss";

export const OutboundPage = () => {
    const container = "wrapper"
    const outbound = new Outbound(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            outbound.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
