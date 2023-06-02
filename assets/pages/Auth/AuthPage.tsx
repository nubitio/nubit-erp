import {useEffect} from "react";
import Auth from "../Auth";
import "./AuthPage.scss";

export const AuthPage = () => {
    const container = "wrapper"
    const auth = new Auth(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            auth.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
