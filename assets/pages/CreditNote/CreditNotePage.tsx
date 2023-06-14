import {useEffect} from "react";
import CreditNote from "../CreditNote";
import "./CreditNotePage.scss";

export const CreditNotePage = () => {
    const container = "wrapper"
    const creditNote = new CreditNote(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            creditNote.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
