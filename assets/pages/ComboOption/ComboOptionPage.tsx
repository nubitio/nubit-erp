import {useEffect} from "react";
import ComboOption from "../ComboOption";
import "./ComboOptionPage.scss";

export const ComboOptionPage = () => {
    const container = "wrapper"
    const comboOption = new ComboOption(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            comboOption.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
