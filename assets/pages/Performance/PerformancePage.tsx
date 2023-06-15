import {useEffect} from "react";
import Performance from "../Performance";
import "./PerformancePage.scss";

export const PerformancePage = () => {
    const container = "wrapper"
    const performance = new Performance(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            performance.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
