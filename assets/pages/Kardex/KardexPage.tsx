import {useEffect} from "react";
import Kardex from "../Kardex";
import "./KardexPage.scss";

export const KardexPage = () => {
    const container = "wrapper"
    const kardex = new Kardex(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            kardex.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
            </div>
    );
};
