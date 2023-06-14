import {useEffect} from "react";
import Operation from "../Operation";
import "./OperationPage.scss";

export const OperationPage = () => {
    const container = "wrapper"
    const operation = new Operation(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            operation.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
            </div>
    );
};
