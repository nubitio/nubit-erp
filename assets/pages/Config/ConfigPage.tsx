import {useEffect} from "react";
import Config from "../Config";
import "./ConfigPage.scss";

export const ConfigPage = () => {
    const container = "wrapper"
    const config = new Config(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            config.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
