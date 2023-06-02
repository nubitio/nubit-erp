import {useEffect} from "react";
import User from "../User";
import "./UserPage.scss";

export const UserPage = () => {
    const container = "wrapper"
    const user = new User(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            user.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
