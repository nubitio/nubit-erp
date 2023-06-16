import {useEffect} from "react";
import Branch from "../Branch";
import "./BranchPage.scss";

export const BranchPage = () => {
    const container = "wrapper"
    const branch = new Branch(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            branch.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
