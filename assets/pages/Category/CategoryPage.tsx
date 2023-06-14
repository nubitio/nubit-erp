import {useEffect} from "react";
import Category from "../Category";
import "./Category.scss";

export const CategoryPage = () => {
    const container = "wrapper"
    const category = new Category(container)
    let alreadyRender = false;

    useEffect(() => {
        if (!alreadyRender) {
            category.render()
            alreadyRender = true
        }
    }, [])

    return (
        <div id={container}>
        </div>
    );
};
