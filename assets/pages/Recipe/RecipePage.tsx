import {useEffect} from "react";
import Recipe from "../Recipe";
import "./RecipePage.scss";

export const RecipePage = () => {
    const container = "wrapper"
    const recipe = new Recipe(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            recipe.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
