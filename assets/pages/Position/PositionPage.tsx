import {useEffect} from "react";
import Position from "../Position";
import "./PositionPage.scss";

export const PositionPage = () => {
    const container = "wrapper"
    const position = new Position(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            position.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
